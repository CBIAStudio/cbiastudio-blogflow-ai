<?php
/**
 * Unified Usage tab (Usage metrics + Costs section).
 */
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

if (!defined('ABSPATH')) {
    exit;
}

$legacy_days = isset($_GET['usage_days']) ? absint(wp_unslash((string) $_GET['usage_days'])) : 30;
$legacy_range_keys = array(7 => '7d', 30 => '30d', 90 => '90d', 365 => '12m');
$requested_range = isset($_GET['usage_range']) ? sanitize_key(wp_unslash((string) $_GET['usage_range'])) : ($legacy_range_keys[$legacy_days] ?? '30d');
$requested_range_from = isset($_GET['usage_from']) ? sanitize_text_field(wp_unslash((string) $_GET['usage_from'])) : '';
$requested_range_to = isset($_GET['usage_to']) ? sanitize_text_field(wp_unslash((string) $_GET['usage_to'])) : '';
$usage_range = cbia_usage_resolve_range($requested_range, $requested_range_from, $requested_range_to);
$days = (int) $usage_range['days'];
$requested_range = (string) $usage_range['key'];
$requested_range_from = (string) ($usage_range['key'] === 'custom' ? $usage_range['since_day'] : '');
$requested_range_to = (string) ($usage_range['key'] === 'custom' ? $usage_range['end_day'] : '');
$requested_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'usage';
$posted_form = isset($_POST['cbia_form']) ? sanitize_key(wp_unslash((string) $_POST['cbia_form'])) : '';
$default_section = ($requested_tab === 'costes' || $posted_form === 'costes_settings' || $posted_form === 'costes_actions') ? 'costs' : 'overview';
$usage_section = isset($_GET['usage_section']) ? sanitize_key(wp_unslash((string) $_GET['usage_section'])) : $default_section;
if (!in_array($usage_section, array('overview', 'costs'), true)) {
    $usage_section = 'overview';
}
$is_pro_edition = defined('CBIA_EDITION') && strtolower((string) CBIA_EDITION) === 'pro';
$usage_advanced_enabled = function_exists('cbia_cap_enabled') ? cbia_cap_enabled('usage_advanced') : $is_pro_edition;
$costs_advanced_enabled = function_exists('cbia_cap_enabled') ? cbia_cap_enabled('costs_advanced') : $is_pro_edition;
// Base edition must never render cost intelligence panels.
$costs_advanced_enabled = $costs_advanced_enabled && $is_pro_edition;
if (!$costs_advanced_enabled && $usage_section === 'costs') {
    $usage_section = 'overview';
}

$requested_model = isset($_GET['usage_model']) ? sanitize_text_field(wp_unslash((string) $_GET['usage_model'])) : '';
$recent_rows_limit = max(20, (int) apply_filters('cbia_pro_usage_recent_rows_limit', 5000));
$cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
$text_provider_key = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai';
$image_provider_key = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : 'openai';
$log_rows = array();
$recent_rows = array();
$daily_series = array();
$monthly_series = array();
$summaries_by_model = array();
$model_options = array();

$export_url = wp_nonce_url(
    add_query_arg(array(
        'action' => 'cbia_usage_export',
        'usage_days' => (int) $days,
        'usage_range' => $requested_range,
        'usage_from' => $requested_range_from,
        'usage_to' => $requested_range_to,
        'usage_model' => $requested_model,
    ), admin_url('admin-post.php')),
    'cbia_usage_export'
);

$base_tab_url = admin_url('admin.php?page=cbia&tab=usage');
$overview_url = add_query_arg(
    array(
        'usage_section' => 'overview',
        'usage_days' => $days,
        'usage_range' => $requested_range,
        'usage_from' => $requested_range_from,
        'usage_to' => $requested_range_to,
        'usage_model' => $requested_model,
    ),
    $base_tab_url
);
$costs_url = add_query_arg(
    array(
        'usage_section' => 'costs',
        'usage_days' => $days,
        'usage_range' => $requested_range,
        'usage_from' => $requested_range_from,
        'usage_to' => $requested_range_to,
        'usage_model' => $requested_model,
    ),
    $base_tab_url
);
$pro_upgrade_url_default = defined('CBIA_PRO_UPGRADE_URL_DEFAULT')
    ? (string) CBIA_PRO_UPGRADE_URL_DEFAULT
    : 'https://cbia-studio.lemonsqueezy.com/checkout';
$pro_upgrade_url = apply_filters('cbia_pro_upgrade_url', $pro_upgrade_url_default);

$dashboard_payload = array();
$range_args = array('key' => $requested_range, 'from' => $requested_range_from, 'to' => $requested_range_to);
if (!$usage_advanced_enabled) {
    $dashboard_payload = function_exists('cbia_get_usage_dashboard_payload_fast')
        ? cbia_get_usage_dashboard_payload_fast($days, $requested_model, $range_args)
        : (function_exists('cbia_get_usage_dashboard_payload_basic')
            ? cbia_get_usage_dashboard_payload_basic($days, $requested_model, $range_args)
            : array());
} else {
    $dashboard_payload = function_exists('cbia_get_usage_dashboard_payload_fast')
        ? cbia_get_usage_dashboard_payload_fast($days, $requested_model, $range_args)
        : array(
            'rows' => $recent_rows,
            'totalRows' => count($log_rows),
            'rowsLimited' => count($log_rows) > count($recent_rows),
            'recentRowsLimit' => $recent_rows_limit,
            'dailySeries' => $daily_series,
            'monthlySeries' => $monthly_series,
            'summariesByModel' => $summaries_by_model,
            'modelOptions' => $model_options,
            'defaultModel' => $requested_model,
            'periodDays' => $days,
        );
}

$dashboard_payload['defaultModel'] = $requested_model;
$dashboard_payload['periodDays'] = $days;
$dashboard_payload['rangeKey'] = $requested_range;
$dashboard_payload['rangeFrom'] = $requested_range_from;
$dashboard_payload['rangeTo'] = $requested_range_to;
$dashboard_payload['siteTimezone'] = wp_timezone_string();
$dashboard_payload['locale'] = get_locale();
$dashboard_payload['usdToEur'] = isset($cost_settings['usd_to_eur']) ? (float) $cost_settings['usd_to_eur'] : 0.92;
$dashboard_payload['canViewCosts'] = !empty($costs_advanced_enabled) ? 1 : 0;
if (empty($costs_advanced_enabled)) {
    $dashboard_payload = cbia_usage_scrub_cost_intelligence($dashboard_payload);
}
$dashboard_payload['lazyLoad'] = false;
$dashboard_payload['ajaxUrl'] = admin_url('admin-ajax.php');
$dashboard_payload['ajaxNonce'] = wp_create_nonce('cbia_usage_overview');
$dashboard_payload['i18n'] = array(
    'loadingData' => __('Loading real usage data…', 'cbiastudio-blogflow-ai'),
    'loadingHint' => __('Charts and table will fill in automatically in a few seconds.', 'cbiastudio-blogflow-ai'),
    'loadingLogs' => __('Loading logs…', 'cbiastudio-blogflow-ai'),
    'loadingDetail' => __('Preparing detail view…', 'cbiastudio-blogflow-ai'),
    'loadErrorMeta' => __('Could not load usage data right now.', 'cbiastudio-blogflow-ai'),
    'loadErrorRow' => __('Could not load usage data.', 'cbiastudio-blogflow-ai'),
    'loadErrorDetail' => __('Could not load usage data.', 'cbiastudio-blogflow-ai'),
    'costCoverage' => __('Local cost coverage', 'cbiastudio-blogflow-ai'),
    'unknownEvents' => __('Unknown cost events', 'cbiastudio-blogflow-ai'),
    'unknownCost' => __('Cost not determined', 'cbiastudio-blogflow-ai'),
    'provider' => __('Provider', 'cbiastudio-blogflow-ai'),
    'requestedModel' => __('Requested model', 'cbiastudio-blogflow-ai'),
    'effectiveModel' => __('Effective model', 'cbiastudio-blogflow-ai'),
    'thinking' => __('Reasoning mode', 'cbiastudio-blogflow-ai'),
    'reasoningEffort' => __('Reasoning effort', 'cbiastudio-blogflow-ai'),
    'cacheTokens' => __('Cache hit / miss tokens', 'cbiastudio-blogflow-ai'),
    'reasoningTokens' => __('Reasoning tokens', 'cbiastudio-blogflow-ai'),
    'requestedQuality' => __('Requested quality', 'cbiastudio-blogflow-ai'),
    'effectiveQuality' => __('Effective quality', 'cbiastudio-blogflow-ai'),
    'requestedSize' => __('Requested size', 'cbiastudio-blogflow-ai'),
    'effectiveSize' => __('Effective size', 'cbiastudio-blogflow-ai'),
    'outputFormat' => __('Output format', 'cbiastudio-blogflow-ai'),
    'background' => __('Background', 'cbiastudio-blogflow-ai'),
    'notReturned' => __('Not returned', 'cbiastudio-blogflow-ai'),
    'costStatus' => __('Cost status', 'cbiastudio-blogflow-ai'),
    'costSource' => __('Cost source', 'cbiastudio-blogflow-ai'),
    'costReason' => __('Cost reason', 'cbiastudio-blogflow-ai'),
    'automaticQualityWithoutUsage' => __('Cost not determined: OpenAI did not return the effective quality or sufficient usage data.', 'cbiastudio-blogflow-ai'),
    'timeoutWithoutResponseUsage' => __('Cost not determined: the local connection timed out before usage data was received.', 'cbiastudio-blogflow-ai'),
    'outputEstimateOnly' => __('Output estimate only; this is not the total invoiced cost.', 'cbiastudio-blogflow-ai'),
    'apiUsage' => __('Calculated from API usage and the local pricing catalog.', 'cbiastudio-blogflow-ai'),
    'cacheBreakdownMissing' => __('Estimated conservatively because the API did not return the cache breakdown; all input was priced as cache miss.', 'cbiastudio-blogflow-ai'),
    'officialReconciliation' => __('Officially reconciled cost.', 'cbiastudio-blogflow-ai'),
    'insufficientUsageData' => __('Insufficient usage data.', 'cbiastudio-blogflow-ai'),
    'modelWithoutPricing' => __('The effective model has no local price.', 'cbiastudio-blogflow-ai'),
    'missingTokenUsage' => __('The response did not include sufficient token usage.', 'cbiastudio-blogflow-ai'),
    'imageQuality' => __('Image quality', 'cbiastudio-blogflow-ai'),
    'imageRole' => __('Image role', 'cbiastudio-blogflow-ai'),
    'automatic' => __('Automatic', 'cbiastudio-blogflow-ai'),
    'low' => __('Low', 'cbiastudio-blogflow-ai'),
    'medium' => __('Medium', 'cbiastudio-blogflow-ai'),
    'high' => __('High', 'cbiastudio-blogflow-ai'),
    'unknown' => __('Unknown', 'cbiastudio-blogflow-ai'),
    'monthlyChartHint' => __('Latest 12 calendar months, including months with zero data. Provider and model filters apply; the general usage range and other log filters do not change this chart.', 'cbiastudio-blogflow-ai'),
    'featured' => __('Featured', 'cbiastudio-blogflow-ai'),
    'internal' => __('Internal', 'cbiastudio-blogflow-ai'),
    'other' => __('Other', 'cbiastudio-blogflow-ai'),
    'events' => __('events', 'cbiastudio-blogflow-ai'),
    'images' => __('images', 'cbiastudio-blogflow-ai'),
    'activeFilters' => __('active filters', 'cbiastudio-blogflow-ai'),
    'noActiveFilters' => __('No additional filters', 'cbiastudio-blogflow-ai'),
    'imageTokenUsageAvailable' => __('Exact image token usage returned by the API is shown when available. Output price also depends on the effective quality and size.', 'cbiastudio-blogflow-ai'),
    'imageTokenUsageUnavailable' => __('This image response did not include a complete token breakdown. Quality, size and locally known cost remain available.', 'cbiastudio-blogflow-ai'),
    'periodLabel' => __('Period', 'cbiastudio-blogflow-ai'),
    'daysLabel' => __('days', 'cbiastudio-blogflow-ai'),
    'granularityDay' => __('Daily view', 'cbiastudio-blogflow-ai'),
    'granularityWeek' => __('Weekly view', 'cbiastudio-blogflow-ai'),
    'granularityMonth' => __('Monthly view', 'cbiastudio-blogflow-ai'),
    'cost' => __('Cost', 'cbiastudio-blogflow-ai'),
    'calls' => __('Calls', 'cbiastudio-blogflow-ai'),
    'posts' => __('Posts', 'cbiastudio-blogflow-ai'),
    'text' => __('Text', 'cbiastudio-blogflow-ai'),
    'image' => __('Image', 'cbiastudio-blogflow-ai'),
    'seo' => __('SEO', 'cbiastudio-blogflow-ai'),
    'total' => __('Total', 'cbiastudio-blogflow-ai'),
    'noComparison' => __('No comparison', 'cbiastudio-blogflow-ai'),
    'newActivity' => __('New activity', 'cbiastudio-blogflow-ai'),
    'vsPreviousPeriod' => __('vs previous period', 'cbiastudio-blogflow-ai'),
    'increased' => __('increased', 'cbiastudio-blogflow-ai'),
    'decreased' => __('decreased', 'cbiastudio-blogflow-ai'),
    'unchanged' => __('unchanged', 'cbiastudio-blogflow-ai'),
    'knownEvents' => __('Known-cost events', 'cbiastudio-blogflow-ai'),
    'costUnavailable' => __('Cost unavailable', 'cbiastudio-blogflow-ai'),
    'noEventsPeriod' => __('There are no events in this period.', 'cbiastudio-blogflow-ai'),
    'changePeriod' => __('Change period', 'cbiastudio-blogflow-ai'),
    'activityCallsHint' => __('AI calls over the selected period.', 'cbiastudio-blogflow-ai'),
    'activityCostHint' => __('Locally calculated or estimated cost over the selected period.', 'cbiastudio-blogflow-ai'),
    'typeDistributionLabel' => __('Text and image usage distribution', 'cbiastudio-blogflow-ai'),
    'noTypeData' => __('No type data is available for this period.', 'cbiastudio-blogflow-ai'),
    'noImageData' => __('No image data is available for this period.', 'cbiastudio-blogflow-ai'),
    'noMonthlyData' => __('No cost data is available for the last 12 months.', 'cbiastudio-blogflow-ai'),
    'knownCostLabel' => __('Known cost', 'cbiastudio-blogflow-ai'),
    'coverageSummary' => __('Coverage is based on events with a locally known or estimated cost.', 'cbiastudio-blogflow-ai'),
    'quickTable' => __('Table: showing %1$s of %2$s matching events in the recent %3$s-event sample. KPIs and charts use all %4$s events in the period.', 'cbiastudio-blogflow-ai'),
    'showingEvents' => __('Table: showing %1$s of %2$s matching events.', 'cbiastudio-blogflow-ai'),
    'rollingCostChart' => __('Calculated / estimated cost for the last 12 calendar months.', 'cbiastudio-blogflow-ai'),
    'noLogs' => __('No events match these filters.', 'cbiastudio-blogflow-ai'),
    'selectEvent' => __('Select an event to see its post and request details.', 'cbiastudio-blogflow-ai'),
    'openPost' => __('Open post', 'cbiastudio-blogflow-ai'),
    'postSummary' => __('Post summary', 'cbiastudio-blogflow-ai'),
    'realPostSummary' => __('Real totals for this post in the selected view.', 'cbiastudio-blogflow-ai'),
    'inputTokens' => __('Input tokens', 'cbiastudio-blogflow-ai'),
    'outputTokens' => __('Output tokens', 'cbiastudio-blogflow-ai'),
    'totalTokens' => __('Total tokens', 'cbiastudio-blogflow-ai'),
    'totalCost' => __('Total known cost', 'cbiastudio-blogflow-ai'),
    'eventCost' => __('Event cost', 'cbiastudio-blogflow-ai'),
    'billableFailures' => __('Billable failures', 'cbiastudio-blogflow-ai'),
    'lastActivity' => __('Last activity', 'cbiastudio-blogflow-ai'),
    'user' => __('User', 'cbiastudio-blogflow-ai'),
    'types' => __('Types', 'cbiastudio-blogflow-ai'),
    'modelsUsed' => __('Models used', 'cbiastudio-blogflow-ai'),
    'failed' => __('Failed', 'cbiastudio-blogflow-ai'),
    'selectedEvent' => __('Selected event', 'cbiastudio-blogflow-ai'),
    'date' => __('Date', 'cbiastudio-blogflow-ai'),
    'model' => __('Model', 'cbiastudio-blogflow-ai'),
    'request' => __('Request', 'cbiastudio-blogflow-ai'),
    'batchFallback' => __('Batch / fallback', 'cbiastudio-blogflow-ai'),
    'type' => __('Type', 'cbiastudio-blogflow-ai'),
    'section' => __('Section', 'cbiastudio-blogflow-ai'),
    'status' => __('Status', 'cbiastudio-blogflow-ai'),
    'success' => __('Success', 'cbiastudio-blogflow-ai'),
    'error' => __('Error', 'cbiastudio-blogflow-ai'),
    'timeout' => __('Timeout', 'cbiastudio-blogflow-ai'),
    'exact' => __('Exact', 'cbiastudio-blogflow-ai'),
    'estimated' => __('Estimated', 'cbiastudio-blogflow-ai'),
    'officialReconciled' => __('Officially reconciled', 'cbiastudio-blogflow-ai'),
    'summary' => __('Summary', 'cbiastudio-blogflow-ai'),
    'tokensNotApplicable' => __('Not applicable', 'cbiastudio-blogflow-ai'),
    'recalcConfirm' => __('Apply recalculation to stored usage rows? A backup option will be created first.', 'cbiastudio-blogflow-ai'),
    'recalcRunning' => __('Calculating…', 'cbiastudio-blogflow-ai'),
    'recalcFailed' => __('Recalculation failed.', 'cbiastudio-blogflow-ai'),
    'recalcRows' => __('Rows: %1$s · exact: %2$s · estimated: %3$s · unknown: %4$s', 'cbiastudio-blogflow-ai'),
    'backup' => __('backup', 'cbiastudio-blogflow-ai'),
);
?>
<div class="cbia-usage-page">
    <header class="cbia-usage-header">
        <div>
            <h2><?php echo esc_html__('Usage', 'cbiastudio-blogflow-ai'); ?></h2>
            <p class="description"><?php echo esc_html__('Review AI activity, generated content and the events behind it at a glance.', 'cbiastudio-blogflow-ai'); ?></p>
        </div>
        <nav class="cbia-usage-header-actions" aria-label="<?php echo esc_attr__('Usage sections', 'cbiastudio-blogflow-ai'); ?>">
            <a class="button button-primary" href="<?php echo esc_url($overview_url); ?>"><?php echo esc_html__('Summary', 'cbiastudio-blogflow-ai'); ?></a>
            <?php if ($costs_advanced_enabled) : ?>
                <a class="button" href="<?php echo esc_url($costs_url); ?>"><?php echo esc_html__('Settings', 'cbiastudio-blogflow-ai'); ?></a>
            <?php else : ?>
                <button type="button" class="button" disabled aria-disabled="true"><?php echo esc_html__('Settings (Pro)', 'cbiastudio-blogflow-ai'); ?></button>
            <?php endif; ?>
        </nav>
    </header>

    <?php if ($usage_section === 'overview') : ?>
        <?php if (!$usage_advanced_enabled || !$costs_advanced_enabled) : ?>
            <section class="cbia-usage-pro-cta-card cbia-usage-pro-cta-card-compact">
                <div>
                    <span class="cbia-badge-pro">PRO</span>
                    <h3><?php echo esc_html__('Advanced usage and cost intelligence', 'cbiastudio-blogflow-ai'); ?></h3>
                    <p><?php echo esc_html__('Unlock economic KPIs, the fixed 12-month cost view and complete event detail in Pro.', 'cbiastudio-blogflow-ai'); ?></p>
                </div>
                <a class="button button-primary cbia-pro-upgrade-link" href="<?php echo esc_url($pro_upgrade_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Upgrade to Pro', 'cbiastudio-blogflow-ai'); ?></a>
            </section>
        <?php endif; ?>

        <div id="cbia-usage-dashboard" class="cbia-usage-dashboard<?php echo !empty($dashboard_payload['lazyLoad']) ? ' is-loading' : ''; ?>" data-export-url="<?php echo esc_url($export_url); ?>" aria-busy="<?php echo !empty($dashboard_payload['lazyLoad']) ? 'true' : 'false'; ?>">
            <script type="application/json" id="cbia-usage-data"><?php echo wp_json_encode($dashboard_payload); ?></script>
            <div class="cbia-usage-loading-banner" id="cbia-usage-loading-banner" <?php echo !empty($dashboard_payload['lazyLoad']) ? '' : 'hidden'; ?>>
                <span class="spinner is-active" aria-hidden="true"></span>
                <div class="cbia-usage-loading-copy"><strong id="cbia-usage-loading-title"><?php echo esc_html__('Loading real usage data…', 'cbiastudio-blogflow-ai'); ?></strong><span id="cbia-usage-loading-hint"><?php echo esc_html__('Charts and table will fill in automatically in a few seconds.', 'cbiastudio-blogflow-ai'); ?></span></div>
            </div>

            <section class="cbia-usage-filter-card" aria-labelledby="cbia-usage-filter-title">
                <h3 id="cbia-usage-filter-title" class="screen-reader-text"><?php echo esc_html__('Usage filters', 'cbiastudio-blogflow-ai'); ?></h3>
                <div class="cbia-usage-quick-ranges" role="group" aria-label="<?php echo esc_attr__('Quick period', 'cbiastudio-blogflow-ai'); ?>">
                    <?php foreach (array('7d' => __('7 days', 'cbiastudio-blogflow-ai'), '30d' => __('30 days', 'cbiastudio-blogflow-ai'), '90d' => __('90 days', 'cbiastudio-blogflow-ai'), '12m' => __('12 months', 'cbiastudio-blogflow-ai')) as $range_key => $range_label) : ?>
                        <?php $range_url = add_query_arg(array('usage_range' => $range_key, 'usage_from' => false, 'usage_to' => false), $overview_url); ?>
                        <a class="button cbia-usage-range-button<?php echo $requested_range === $range_key ? ' is-active' : ''; ?>" href="<?php echo esc_url($range_url); ?>" <?php echo $requested_range === $range_key ? 'aria-current="true"' : ''; ?>><?php echo esc_html($range_label); ?></a>
                    <?php endforeach; ?>
                    <button type="button" class="button cbia-usage-range-button<?php echo $requested_range === 'custom' ? ' is-active' : ''; ?>" id="cbia-usage-custom-toggle" aria-expanded="<?php echo $requested_range === 'custom' ? 'true' : 'false'; ?>" aria-controls="cbia-usage-custom-range"><?php echo esc_html__('Custom', 'cbiastudio-blogflow-ai'); ?></button>
                </div>
                <form method="get" id="cbia-usage-custom-range" class="cbia-usage-custom-range" <?php echo $requested_range === 'custom' ? '' : 'hidden'; ?>>
                    <input type="hidden" name="page" value="cbia" />
                    <input type="hidden" name="tab" value="usage" />
                    <input type="hidden" name="usage_section" value="overview" />
                    <input type="hidden" name="usage_range" value="custom" />
                    <input type="hidden" name="usage_model" value="<?php echo esc_attr($requested_model); ?>" />
                    <label><?php echo esc_html__('From', 'cbiastudio-blogflow-ai'); ?><input type="date" name="usage_from" autocomplete="off" value="<?php echo esc_attr($requested_range_from); ?>" required /></label>
                    <label><?php echo esc_html__('To', 'cbiastudio-blogflow-ai'); ?><input type="date" name="usage_to" autocomplete="off" value="<?php echo esc_attr($requested_range_to); ?>" required /></label>
                    <button type="submit" class="button button-secondary"><?php echo esc_html__('Apply period', 'cbiastudio-blogflow-ai'); ?></button>
                    <span class="description"><?php echo esc_html(wp_timezone_string()); ?></span>
                </form>
                <div class="cbia-usage-filters">
                    <select id="cbia-usage-provider-filter" class="abb-select" aria-label="<?php echo esc_attr__('Provider', 'cbiastudio-blogflow-ai'); ?>"><option value=""><?php echo esc_html__('All providers', 'cbiastudio-blogflow-ai'); ?></option><option value="openai">OpenAI</option><option value="google">Google</option><option value="deepseek">DeepSeek</option><option value="anthropic">Anthropic</option></select>
                    <select id="cbia-usage-model-filter" class="abb-select" aria-label="<?php echo esc_attr__('Model', 'cbiastudio-blogflow-ai'); ?>"><option value=""><?php echo esc_html__('All models', 'cbiastudio-blogflow-ai'); ?></option></select>
                    <select id="cbia-usage-type-filter" class="abb-select" aria-label="<?php echo esc_attr__('Type', 'cbiastudio-blogflow-ai'); ?>"><option value=""><?php echo esc_html__('All types', 'cbiastudio-blogflow-ai'); ?></option><option value="text"><?php echo esc_html__('Text', 'cbiastudio-blogflow-ai'); ?></option><option value="image"><?php echo esc_html__('Image', 'cbiastudio-blogflow-ai'); ?></option><option value="seo">SEO</option></select>
                    <label class="cbia-usage-search-label"><span class="screen-reader-text"><?php echo esc_html__('Search usage events', 'cbiastudio-blogflow-ai'); ?></span><input type="search" id="cbia-usage-search" name="usage_search" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__('Search posts, models…', 'cbiastudio-blogflow-ai'); ?>" /></label>
                    <details class="cbia-usage-more-filters" id="cbia-usage-more-filters">
                        <summary class="button"><?php echo esc_html__('More filters', 'cbiastudio-blogflow-ai'); ?> (<span id="cbia-usage-more-count">0</span>)</summary>
                        <div class="cbia-usage-more-panel">
                            <select id="cbia-usage-quality-filter" class="abb-select" aria-label="<?php echo esc_attr__('Image quality', 'cbiastudio-blogflow-ai'); ?>"><option value=""><?php echo esc_html__('All image qualities', 'cbiastudio-blogflow-ai'); ?></option><option value="auto"><?php echo esc_html__('Automatic', 'cbiastudio-blogflow-ai'); ?></option><option value="low"><?php echo esc_html__('Low', 'cbiastudio-blogflow-ai'); ?></option><option value="medium"><?php echo esc_html__('Medium', 'cbiastudio-blogflow-ai'); ?></option><option value="high"><?php echo esc_html__('High', 'cbiastudio-blogflow-ai'); ?></option><option value="unknown"><?php echo esc_html__('Unknown', 'cbiastudio-blogflow-ai'); ?></option></select>
                            <select id="cbia-usage-image-role-filter" class="abb-select" aria-label="<?php echo esc_attr__('Image role', 'cbiastudio-blogflow-ai'); ?>"><option value=""><?php echo esc_html__('All image roles', 'cbiastudio-blogflow-ai'); ?></option><option value="featured"><?php echo esc_html__('Featured', 'cbiastudio-blogflow-ai'); ?></option><option value="content"><?php echo esc_html__('Internal', 'cbiastudio-blogflow-ai'); ?></option><option value="other"><?php echo esc_html__('Other', 'cbiastudio-blogflow-ai'); ?></option></select>
                            <?php if ($costs_advanced_enabled) : ?><select id="cbia-usage-status-filter" class="abb-select" aria-label="<?php echo esc_attr__('Cost status', 'cbiastudio-blogflow-ai'); ?>"><option value=""><?php echo esc_html__('All cost statuses', 'cbiastudio-blogflow-ai'); ?></option><option value="exact"><?php echo esc_html__('Exact', 'cbiastudio-blogflow-ai'); ?></option><option value="estimated"><?php echo esc_html__('Estimated', 'cbiastudio-blogflow-ai'); ?></option><option value="unknown"><?php echo esc_html__('Unknown', 'cbiastudio-blogflow-ai'); ?></option><option value="official_reconciled"><?php echo esc_html__('Officially reconciled', 'cbiastudio-blogflow-ai'); ?></option></select><?php endif; ?>
                            <select id="cbia-usage-request-status-filter" class="abb-select" aria-label="<?php echo esc_attr__('Request status', 'cbiastudio-blogflow-ai'); ?>"><option value=""><?php echo esc_html__('All request statuses', 'cbiastudio-blogflow-ai'); ?></option><option value="success"><?php echo esc_html__('Success', 'cbiastudio-blogflow-ai'); ?></option><option value="error"><?php echo esc_html__('Error', 'cbiastudio-blogflow-ai'); ?></option><option value="timeout"><?php echo esc_html__('Timeout', 'cbiastudio-blogflow-ai'); ?></option></select>
                            <label><?php echo esc_html__('Event from', 'cbiastudio-blogflow-ai'); ?><input type="datetime-local" id="cbia-usage-from" name="usage_event_from" autocomplete="off" /></label>
                            <label><?php echo esc_html__('Event to', 'cbiastudio-blogflow-ai'); ?><input type="datetime-local" id="cbia-usage-to" name="usage_event_to" autocomplete="off" /></label>
                            <button type="button" class="button cbia-usage-clear-filters" id="cbia-usage-clear-filters"><?php echo esc_html__('Clear filters', 'cbiastudio-blogflow-ai'); ?></button>
                        </div>
                    </details>
                    <a class="button button-secondary" id="cbia-usage-export" href="<?php echo esc_url($export_url); ?>"><?php echo esc_html__('Export CSV', 'cbiastudio-blogflow-ai'); ?></a>
                </div>
                <p id="cbia-usage-filter-summary" class="cbia-usage-filter-summary" aria-live="polite"></p>
            </section>

            <div class="cbia-usage-kpis<?php echo !$costs_advanced_enabled ? ' is-three' : ''; ?>">
                <?php
                $free_kpis = array(
                    array('posts', 'edit-page', __('Posts', 'cbiastudio-blogflow-ai')),
                    array('calls', 'chart-line', __('Calls', 'cbiastudio-blogflow-ai')),
                    array('images', 'format-image', __('Images', 'cbiastudio-blogflow-ai')),
                );
                if ($costs_advanced_enabled) {
                    array_unshift($free_kpis, array('cost-total', 'money-alt', __('Calculated / estimated cost', 'cbiastudio-blogflow-ai')));
                    $free_kpis[] = array('cost-blog', 'chart-pie', __('Known cost per post', 'cbiastudio-blogflow-ai'));
                    $free_kpis[] = array('coverage', 'yes-alt', __('Cost coverage', 'cbiastudio-blogflow-ai'));
                }
                foreach ($free_kpis as $kpi) :
                ?>
                    <article class="cbia-usage-kpi cbia-usage-kpi-<?php echo esc_attr($kpi[0]); ?>"><span class="cbia-usage-kpi-icon dashicons dashicons-<?php echo esc_attr($kpi[1]); ?>" aria-hidden="true"></span><div class="cbia-usage-kpi-copy"><span class="cbia-usage-kpi-label"><?php echo esc_html($kpi[2]); ?></span><strong id="cbia-usage-kpi-<?php echo esc_attr($kpi[0]); ?>" class="cbia-usage-kpi-value"><?php echo strpos($kpi[0], 'cost') !== false ? '$0.00' : '0'; ?></strong><small id="cbia-usage-kpi-<?php echo esc_attr($kpi[0]); ?>-comparison" class="cbia-usage-kpi-comparison"><?php echo esc_html__('No comparison', 'cbiastudio-blogflow-ai'); ?></small></div></article>
                <?php endforeach; ?>
            </div>
            <details class="cbia-usage-secondary-metrics"><summary><?php echo esc_html__('More metrics', 'cbiastudio-blogflow-ai'); ?></summary><div><span><?php echo esc_html__('Unique users', 'cbiastudio-blogflow-ai'); ?> <strong id="cbia-usage-kpi-users">0</strong></span><span><?php echo esc_html__('Average tokens per call', 'cbiastudio-blogflow-ai'); ?> <strong id="cbia-usage-kpi-avg">0</strong></span></div></details>

            <section class="cbia-usage-panel -activity" aria-labelledby="cbia-usage-activity-title">
                <div class="cbia-usage-panel-head"><div class="cbia-usage-panel-title"><h3 id="cbia-usage-activity-title"><?php echo esc_html($costs_advanced_enabled ? __('Activity and cost', 'cbiastudio-blogflow-ai') : __('Activity', 'cbiastudio-blogflow-ai')); ?></h3><p id="cbia-usage-activity-hint"></p></div><div class="cbia-usage-metric-switch" role="group" aria-label="<?php echo esc_attr__('Chart metric', 'cbiastudio-blogflow-ai'); ?>"><?php if ($costs_advanced_enabled) : ?><button type="button" class="button" data-usage-metric="cost" aria-pressed="false"><?php echo esc_html__('Cost', 'cbiastudio-blogflow-ai'); ?></button><?php endif; ?><button type="button" class="button is-active" data-usage-metric="calls" aria-pressed="true"><?php echo esc_html__('Calls', 'cbiastudio-blogflow-ai'); ?></button></div></div>
                <div class="cbia-usage-chart-wrap cbia-usage-main-chart"><canvas id="cbia-usage-activity-chart" height="320" role="img" aria-label="<?php echo esc_attr__('Usage activity for the selected period.', 'cbiastudio-blogflow-ai'); ?>"></canvas><div class="cbia-usage-empty" id="cbia-usage-activity-empty" hidden><span><?php echo esc_html__('There are no events in this period.', 'cbiastudio-blogflow-ai'); ?></span><button type="button" class="button cbia-usage-change-period"><?php echo esc_html__('Change period', 'cbiastudio-blogflow-ai'); ?></button></div><ul id="cbia-usage-activity-data" class="screen-reader-text"></ul></div>
            </section>

            <div class="cbia-usage-breakdown-grid">
                <section class="cbia-usage-panel -type"><div class="cbia-usage-panel-head"><div><h3><?php echo esc_html__('Usage by type', 'cbiastudio-blogflow-ai'); ?></h3><p id="cbia-usage-type-hint"></p></div></div><div id="cbia-usage-type-chart" class="cbia-usage-type-distribution" role="img" aria-label="<?php echo esc_attr__('Text and image usage distribution', 'cbiastudio-blogflow-ai'); ?>"><div class="cbia-usage-type-bar" aria-hidden="true"><span class="is-text" style="width:0%"></span><span class="is-image" style="width:0%"></span></div><div class="cbia-usage-type-values"><span><i class="is-text" aria-hidden="true"></i><?php echo esc_html__('Text', 'cbiastudio-blogflow-ai'); ?> <strong data-usage-type="text">0 · 0%</strong></span><span><i class="is-image" aria-hidden="true"></i><?php echo esc_html__('Image', 'cbiastudio-blogflow-ai'); ?> <strong data-usage-type="image">0 · 0%</strong></span></div></div><div class="cbia-usage-empty" id="cbia-usage-type-empty" hidden><?php echo esc_html__('No type data is available for this period.', 'cbiastudio-blogflow-ai'); ?></div></section>
                <section class="cbia-usage-panel -image-quality"><div class="cbia-usage-panel-head"><div><h3><?php echo esc_html__('Image quality', 'cbiastudio-blogflow-ai'); ?></h3><p><?php echo esc_html__('Image events ordered by the quality actually used.', 'cbiastudio-blogflow-ai'); ?></p></div></div><div id="cbia-usage-image-quality-chart" class="cbia-usage-horizontal-bars"></div><div class="cbia-usage-empty" id="cbia-usage-image-quality-empty" hidden><?php echo esc_html__('No image data is available for this period.', 'cbiastudio-blogflow-ai'); ?></div></section>
                <section class="cbia-usage-panel -image-role"><div class="cbia-usage-panel-head"><div><h3><?php echo esc_html__('Image role', 'cbiastudio-blogflow-ai'); ?></h3><p><?php echo esc_html__('What the generated images were used for.', 'cbiastudio-blogflow-ai'); ?></p></div></div><div id="cbia-usage-image-role-chart" class="cbia-usage-horizontal-bars"></div><div class="cbia-usage-empty" id="cbia-usage-image-role-empty" hidden><?php echo esc_html__('No image data is available for this period.', 'cbiastudio-blogflow-ai'); ?></div></section>
            </div>

            <?php if ($costs_advanced_enabled) : ?>
                <section class="cbia-usage-panel cbia-usage-coverage-panel"><div class="cbia-usage-coverage-donut" id="cbia-usage-coverage-donut" role="img"><div><strong id="cbia-usage-cost-coverage-badge">0%</strong><span><?php echo esc_html__('Known cost', 'cbiastudio-blogflow-ai'); ?></span></div></div><div class="cbia-usage-cost-control-copy"><h3><?php echo esc_html__('Cost coverage', 'cbiastudio-blogflow-ai'); ?></h3><p><?php echo esc_html__('Coverage is calculated from events with a locally known or estimated cost.', 'cbiastudio-blogflow-ai'); ?></p><p id="cbia-usage-cost-coverage" aria-live="polite"></p></div></section>
                <section class="cbia-usage-panel -monthly"><div class="cbia-usage-panel-head"><div><h3><?php echo esc_html__('Calculated / estimated cost — last 12 months', 'cbiastudio-blogflow-ai'); ?></h3><p id="cbia-usage-monthly-hint"><?php echo esc_html__('Current calendar month plus the previous eleven. Provider and model filters apply; the main period and other event filters do not.', 'cbiastudio-blogflow-ai'); ?></p></div><div class="cbia-usage-legend"><span class="cbia-usage-legend-item is-text"><i aria-hidden="true"></i><?php echo esc_html__('Text', 'cbiastudio-blogflow-ai'); ?></span><span class="cbia-usage-legend-item is-image"><i aria-hidden="true"></i><?php echo esc_html__('Image', 'cbiastudio-blogflow-ai'); ?></span><span class="cbia-usage-legend-item is-seo"><i aria-hidden="true"></i><?php echo esc_html__('SEO', 'cbiastudio-blogflow-ai'); ?></span></div></div><div class="cbia-usage-chart-wrap cbia-usage-monthly-chart"><canvas id="cbia-usage-monthly-chart" height="300" role="img" aria-label="<?php echo esc_attr__('Calculated / estimated cost for the last 12 calendar months.', 'cbiastudio-blogflow-ai'); ?>"></canvas><div class="cbia-usage-empty" id="cbia-usage-monthly-empty" hidden><?php echo esc_html__('No cost data is available for the last 12 months.', 'cbiastudio-blogflow-ai'); ?></div><ul id="cbia-usage-monthly-data" class="screen-reader-text"></ul></div></section>
            <?php endif; ?>

            <section class="cbia-usage-events-section" aria-labelledby="cbia-usage-events-title">
                <div class="cbia-usage-panel-head"><div><h3 id="cbia-usage-events-title"><?php echo esc_html__('Usage events', 'cbiastudio-blogflow-ai'); ?></h3><p class="description" id="cbia-usage-table-meta" aria-live="polite"><?php echo esc_html__('Loading the latest events sample for faster access…', 'cbiastudio-blogflow-ai'); ?></p></div></div>
                <div class="cbia-usage-main-grid">
                    <div class="cbia-usage-panel cbia-usage-table-panel"><div class="cbia-usage-table-wrap"><table class="widefat striped cbia-usage-events-table"><thead><tr><th><?php echo esc_html__('Date', 'cbiastudio-blogflow-ai'); ?></th><th><?php echo esc_html__('Post', 'cbiastudio-blogflow-ai'); ?></th><th><?php echo esc_html__('Type', 'cbiastudio-blogflow-ai'); ?></th><th><?php echo esc_html__('Model', 'cbiastudio-blogflow-ai'); ?></th><th><?php echo esc_html__('Tokens', 'cbiastudio-blogflow-ai'); ?></th><?php if ($costs_advanced_enabled) : ?><th><?php echo esc_html__('Cost', 'cbiastudio-blogflow-ai'); ?></th><?php endif; ?><th><?php echo esc_html__('Status', 'cbiastudio-blogflow-ai'); ?></th></tr></thead><tbody id="cbia-usage-table-body"><tr><td colspan="<?php echo $costs_advanced_enabled ? '7' : '6'; ?>" class="cbia-usage-table-placeholder"><?php echo esc_html__('Loading logs…', 'cbiastudio-blogflow-ai'); ?></td></tr></tbody></table></div></div>
                    <aside class="cbia-usage-panel cbia-usage-detail-panel" id="cbia-usage-detail" aria-live="polite"><div class="cbia-usage-detail-empty"><?php echo esc_html__('Select an event to see its post and request details.', 'cbiastudio-blogflow-ai'); ?></div></aside>
                </div>
            </section>
        </div>
    <?php elseif ($costs_advanced_enabled) : ?>
        <section class="cbia-usage-panel cbia-usage-recalculation-actions" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('cbia_usage_overview')); ?>" data-confirm="<?php echo esc_attr($dashboard_payload['i18n']['recalcConfirm']); ?>" data-running="<?php echo esc_attr($dashboard_payload['i18n']['recalcRunning']); ?>" data-failed="<?php echo esc_attr($dashboard_payload['i18n']['recalcFailed']); ?>" data-result="<?php echo esc_attr($dashboard_payload['i18n']['recalcRows']); ?>" data-backup="<?php echo esc_attr($dashboard_payload['i18n']['backup']); ?>"><h3><?php echo esc_html__('Cost actions', 'cbiastudio-blogflow-ai'); ?></h3><p><?php echo esc_html__('Historical recalculation is explicit and never runs when you open the Usage summary.', 'cbiastudio-blogflow-ai'); ?></p><div><button type="button" class="button" id="cbia-usage-recalc-dry-run"><?php echo esc_html__('Simulate historical cost recalculation', 'cbiastudio-blogflow-ai'); ?></button><button type="button" class="button" id="cbia-usage-recalc-apply"><?php echo esc_html__('Apply recalculation', 'cbiastudio-blogflow-ai'); ?></button><span id="cbia-usage-recalc-result" class="description" aria-live="polite"></span></div></section>
        <?php $cbia_costs_embedded = true; $costs_view = CBIA_INCLUDES_DIR . 'admin/views/costs.php'; if (file_exists($costs_view)) { include $costs_view; } ?>
    <?php endif; ?>
</div>
