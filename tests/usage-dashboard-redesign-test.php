<?php
define('ABSPATH', __DIR__ . '/');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!function_exists('wp_timezone')) {
    function wp_timezone() { return new DateTimeZone('Europe/Madrid'); }
}

require dirname(__DIR__) . '/includes/support/usage-dashboard.php';

$checks = 0;
function usage_redesign_check($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException("Case {$checks} failed: {$message}");
    }
}

$timezone = new DateTimeZone('Europe/Madrid');
$now = new DateTimeImmutable('2026-08-27 12:00:00', $timezone);
usage_redesign_check(cbia_usage_parse_site_timestamp('2026-08-27 23:30:00') === (new DateTimeImmutable('2026-08-27 23:30:00', $timezone))->getTimestamp(), 'stored local timestamps are interpreted in the WordPress timezone');

$default = cbia_usage_resolve_range('', '', '', $now);
usage_redesign_check($default['key'] === '30d' && $default['days'] === 30, 'the dashboard defaults to 30 days');
usage_redesign_check($default['since_day'] === '2026-07-29' && $default['end_day'] === '2026-08-27', 'the default range is inclusive in site time');
usage_redesign_check(cbia_usage_row_in_general_range(array('day' => '2026-08-27', 'sort_ts' => $default['end_ts'] + 7200), $default), 'site-local day boundaries remain authoritative for previously stored rows');
usage_redesign_check(cbia_usage_resolve_range('7d', '', '', $now)['days'] === 7, 'the 7-day shortcut resolves');
usage_redesign_check(cbia_usage_resolve_range('30d', '', '', $now)['days'] === 30, 'the 30-day shortcut resolves');
usage_redesign_check(cbia_usage_resolve_range('90d', '', '', $now)['days'] === 90, 'the 90-day shortcut resolves');
usage_redesign_check(cbia_usage_resolve_range('12m', '', '', $now)['days'] === 365, 'the 12-month shortcut resolves to a bounded inclusive window');

$custom = cbia_usage_resolve_range('custom', '2026-08-05', '2026-08-17', $now);
usage_redesign_check($custom['days'] === 13, 'custom periods include both boundary dates');
usage_redesign_check($custom['previous_since_day'] === '2026-07-23' && $custom['previous_end_day'] === '2026-08-04', 'custom comparisons use the immediately preceding equal-length range');
usage_redesign_check(cbia_usage_resolve_range('custom', 'bad', '2026-08-17', $now)['key'] === '30d', 'invalid custom input safely falls back to 30 days');
usage_redesign_check(cbia_usage_resolve_range('custom', '2020-01-01', '2026-08-27', $now)['days'] === 730, 'custom input is bounded to the retained maximum');

usage_redesign_check(cbia_usage_range_granularity(30, '30d') === 'day', 'short ranges use day buckets');
usage_redesign_check(cbia_usage_range_granularity(90, '90d') === 'week', 'medium ranges use week buckets');
usage_redesign_check(cbia_usage_range_granularity(365, '12m') === 'month', 'long ranges use month buckets');
usage_redesign_check(cbia_usage_metric_comparison(4, 0)['status'] === 'new_activity', 'zero-to-activity comparisons never emit infinity');
usage_redesign_check(cbia_usage_metric_comparison(0, 0)['status'] === 'no_comparison', 'empty comparisons avoid a misleading percentage');
usage_redesign_check(cbia_usage_metric_comparison(75, 100)['percent'] === -25.0, 'normal previous-period percentages remain accurate');
$scrubbed = cbia_usage_scrub_cost_intelligence(array(
    'rows' => array(array('cost_eur' => 1.0, 'cost_usd' => 1.0, 'cost_status' => 'estimated')),
    'summariesByModel' => array('__all__' => array('totalCost' => 1.0, 'knownCostEvents' => 1, 'monthlySeries' => array(array('cost_eur' => 1.0)))),
    'monthlySeriesByProvider' => array('openai' => array(array('cost_eur' => 1.0))),
));
usage_redesign_check($scrubbed['rows'][0]['cost_eur'] === null && !isset($scrubbed['rows'][0]['cost_usd']) && $scrubbed['summariesByModel']['__all__']['knownCostEvents'] === 0 && $scrubbed['monthlySeriesByProvider'] === array(), 'capability scrubbing removes direct and aggregate cost intelligence');

$rolling = cbia_usage_build_rolling_month_aggregates(array(
    array('month' => '2025-09', 'type' => 'text', 'provider' => 'openai', 'model' => 'alpha', 'cost_eur' => 1.25),
    array('month' => '2026-08', 'type' => 'image', 'provider' => 'google', 'model' => 'image-a', 'cost_eur' => 2.50),
    array('month' => '2026-08', 'type' => 'text', 'provider' => 'openai', 'model' => 'alpha', 'cost_eur' => null),
), $now);
usage_redesign_check(count($rolling['series']) === 12, 'the independent cost history always has 12 buckets');
usage_redesign_check($rolling['series'][0]['month'] === '2025-09' && $rolling['series'][11]['month'] === '2026-08', 'rolling history is current month plus the previous eleven');
usage_redesign_check(count(array_filter($rolling['series'], static function ($row) { return (int) $row['calls'] === 0; })) === 10, 'missing months are zero-filled');
usage_redesign_check(isset($rolling['by_provider']['openai']) && isset($rolling['by_provider']['google']), 'provider series are pre-aggregated');
usage_redesign_check(isset($rolling['by_provider_model']['openai']['alpha']), 'combined provider/model series are pre-aggregated');
usage_redesign_check(abs(array_sum(array_column($rolling['series'], 'cost_eur')) - 3.75) < 0.000001, 'unknown costs are not invented in monthly totals');

$root = dirname(__DIR__);
$view = file_get_contents($root . '/includes/admin/views/usage.php');
$hooks = file_get_contents($root . '/includes/core/hooks.php');
$support = file_get_contents($root . '/includes/support/usage-dashboard.php');
$script = file_get_contents($root . '/assets/js/admin.js');
$styles = file_get_contents($root . '/assets/css/admin.css');
$is_pro = substr($root, -4) === '-pro';
$view_marker = $is_pro ? "See your estimated spend" : "Review AI activity";
$view_start = strpos($view, $view_marker);
$active_view = $view_start === false ? $view : substr($view, $view_start);
$table_view = substr($active_view, strpos($active_view, 'cbia-usage-events-table'));
$script_start = strrpos($script, 'function initUsageDashboard()');
$script_end = strpos($script, 'function initAiComposer()', $script_start);
$active_script = substr($script, $script_start, $script_end - $script_start);

usage_redesign_check(strpos($active_view, 'cbia-usage-quick-ranges') !== false && strpos($active_view, 'cbia-usage-custom-range') !== false, 'quick and custom period controls render');
usage_redesign_check(strpos($active_view, 'cbia-usage-provider-filter') < strpos($active_view, 'cbia-usage-more-filters'), 'provider/model/type/search filters precede progressive filters');
usage_redesign_check(strpos($active_view, 'cbia-usage-kpis') < strpos($active_view, 'cbia-usage-activity-chart'), 'KPIs precede the primary chart');
usage_redesign_check(strpos($active_view, 'data-usage-metric="calls"') !== false, 'the primary chart has an explicit calls selector');
usage_redesign_check(strpos($active_view, 'cbia-usage-type-distribution') !== false, 'type distribution is a semantic DOM component');
usage_redesign_check(strpos($active_view, 'cbia-usage-horizontal-bars') !== false, 'image quality and role use horizontal DOM bars');
usage_redesign_check(strpos($active_view, 'cbia-usage-events-table') < strpos($active_view, 'cbia-usage-detail-panel'), 'the event master table precedes its detail panel');
usage_redesign_check(strpos($table_view, "__('Date'") < strpos($table_view, "__('Post'") && strpos($table_view, "__('Post'") < strpos($table_view, "__('Type'"), 'the event table begins Date, Post, Type');
usage_redesign_check(strpos($active_view, 'aria-live="polite"') !== false && strpos($active_view, 'role="img"') !== false, 'live regions and chart alternatives are exposed');
usage_redesign_check(strpos($active_script, "payload.periodDays || 30") !== false, 'the active client controller has a 30-day defensive default');
usage_redesign_check(strpos($active_script, "payload.granularity || 'day'") !== false, 'the active client consumes backend granularity');
usage_redesign_check(strpos($active_script, 'previousSummariesByModel') !== false && strpos($active_script, "t('newActivity')") !== false, 'the active client renders safe previous-period comparisons');
usage_redesign_check(strpos($active_script, "replace('_', '-')") !== false && strpos($active_script, "Intl.NumberFormat('es-ES')") === false, 'numbers use the WordPress locale instead of a hardcoded locale');
usage_redesign_check(strpos($active_script, 'new Intl.DateTimeFormat(locale') !== false, 'event dates use locale-aware formatting');
usage_redesign_check(strpos($active_script, "sort(function (a, b)") !== false && strpos($active_script, 'b.value - a.value') !== false, 'breakdown bars sort descending');
usage_redesign_check(strpos($active_script, 'monthlySeriesByProviderModel') !== false, 'the fixed monthly chart selects pre-aggregated provider/model data');
usage_redesign_check(strpos($active_script, 'setChartTooltip(canvas') !== false && strpos($styles, '.cbia-usage-chart-tooltip') !== false, 'primary and monthly charts expose contextual tooltips');
usage_redesign_check(strpos($active_script, 'filtered.slice(0, 50)') !== false, 'the event table bounds DOM rendering while retaining the full filter dataset');
usage_redesign_check(strpos($active_script, 'safeAdminUrl(row.post_edit_url') !== false, 'event edit links are restricted to same-origin HTTP(S) admin URLs');
usage_redesign_check(strpos($active_view, 'name="usage_search"') !== false && strpos($active_view, 'autocomplete="off"') !== false, 'client-side filters keep accessible form metadata');
usage_redesign_check(strpos($active_script, "controls.costStatus.value = 'unknown'") !== false || !$is_pro, 'PRO can jump directly to unknown-cost events');
usage_redesign_check(strpos($styles, 'grid-template-columns: repeat(6') !== false, 'desktop KPI hierarchy supports six primary cards');
usage_redesign_check(strpos($styles, '@media (max-width: 1200px)') !== false && strpos($styles, '@media (max-width: 782px)') !== false && strpos($styles, '@media (max-width: 480px)') !== false, 'desktop, tablet and mobile breakpoints are explicit');
usage_redesign_check(strpos($styles, '@media (prefers-reduced-motion: reduce)') !== false, 'reduced-motion preferences are respected');
usage_redesign_check(strpos($styles, 'position: sticky;') !== false && strpos($styles, 'overflow: auto;') !== false, 'large event samples remain navigable with sticky headers and bounded scrolling');
usage_redesign_check(strpos($hooks, "current_user_can('manage_options')") !== false && strpos($hooks, "check_ajax_referer('cbia_usage_overview'") !== false, 'AJAX permission and nonce checks are unchanged');
usage_redesign_check(strpos($hooks, "check_admin_referer('cbia_usage_export')") !== false && strpos($hooks, "usage_range") !== false && strpos($hooks, "usage_from") !== false && strpos($hooks, "usage_to") !== false, 'CSV export keeps nonce protection and follows the selected range');
usage_redesign_check(strpos($hooks, "body.set('confirm', 'RECALCULATE')") === false, 'server code does not make dashboard reads trigger recalculation');
usage_redesign_check(strpos($script, "body.set('confirm', 'RECALCULATE')") !== false && strpos($active_view, 'cbia-usage-recalc-apply') > strpos($active_view, 'usage_section ==='), 'historical recalculation remains an explicit Settings action');
usage_redesign_check(strpos($hooks, 'previousSummariesByModel') !== false && strpos($hooks, 'cbia_usage_row_in_previous_range') !== false, 'backend payloads contain previous-period summaries');
usage_redesign_check(strpos($hooks, '$previous_summary_rows') !== false, 'the query fallback also aggregates the immediately previous period');
usage_redesign_check(strpos($hooks, "cbia_pro_usage_overview_v13_") !== false, 'the redesigned payload uses its own cache namespace');

if ($is_pro) {
    usage_redesign_check(strpos($active_view, 'cbia-usage-coverage-donut') < strpos($active_view, 'cbia-usage-monthly-chart'), 'PRO places coverage guidance before fixed monthly history');
    usage_redesign_check(strpos($active_view, 'cost-total') < strpos($active_view, "array('posts'") && strpos($active_view, "array('posts'") < strpos($active_view, "array('calls'"), 'PRO starts KPI order with cost, posts and calls');
    usage_redesign_check(strpos($active_view, 'role="dialog" aria-modal="true"') !== false, 'PRO cost guidance is an accessible dialog');
} else {
    usage_redesign_check(strpos($view, "\$dashboard_payload['canViewCosts']") !== false && strpos($view, 'cbia_usage_scrub_cost_intelligence') !== false, 'FREE capability gating removes hidden cost aggregates');
    usage_redesign_check(strpos($support, "'cost_usd', 'cost_micro_usd', 'cost_currency'") !== false && strpos($support, "unset(\$data['usdToEur']") !== false, 'FREE capability gating removes row-level and configuration cost intelligence');
    usage_redesign_check(strpos($active_view, 'Settings (Pro)') !== false, 'FREE keeps cost settings capability-gated');
    usage_redesign_check(strpos($active_view, "if (\$costs_advanced_enabled)") !== false, 'FREE cost panels remain behind the existing capability');
}

echo "usage-dashboard-redesign: {$checks}/{$checks} OK\n";
