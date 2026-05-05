<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!current_user_can('manage_options')) return;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$service = isset($cbia_blog_service) ? $cbia_blog_service : null;

$saved_notice = '';
if ($service && method_exists($service, 'handle_post')) {
    $saved_notice = (string)$service->handle_post();
} elseif (function_exists('cbia_blog_handle_post')) {
    $saved_notice = cbia_blog_handle_post();
}

$settings = $service && method_exists($service, 'get_settings')
    ? $service->get_settings()
    : (function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array()));
$runtime_advanced_enabled = function_exists('cbia_cap_enabled') ? cbia_cap_enabled('runtime_advanced') : true;

$mode = $settings['title_input_mode'] ?? 'manual';
$manual_titles = $settings['manual_titles'] ?? '';
$blog_prompt_mode = function_exists('cbia_prompt_get_mode')
    ? cbia_prompt_get_mode((array)$settings)
    : sanitize_key((string)($settings['blog_prompt_mode'] ?? 'recommended'));
if (!in_array($blog_prompt_mode, array('recommended', 'legacy'), true)) $blog_prompt_mode = 'recommended';
$blog_prompt_profile = function_exists('cbia_get_prompt_profile')
    ? cbia_get_prompt_profile((array)$settings)
    : sanitize_key((string)($settings['blog_prompt_profile'] ?? 'discover_editorial'));
$prompt_profile_options = function_exists('cbia_prompt_get_profile_options')
    ? cbia_prompt_get_profile_options()
    : array();
$include_faq = isset($settings['include_faq']) ? (int)$settings['include_faq'] : 1;
$include_practical_examples = isset($settings['include_practical_examples']) ? (int)$settings['include_practical_examples'] : 0;
$search_intent_strength = sanitize_key((string)($settings['search_intent_strength'] ?? 'balanced'));
if (!in_array($search_intent_strength, array('soft', 'balanced', 'strong'), true)) $search_intent_strength = 'balanced';
$blog_prompt_custom_instructions = function_exists('cbia_prompt_sanitize_custom_instructions')
    ? cbia_prompt_sanitize_custom_instructions((string)($settings['blog_prompt_custom_instructions'] ?? ''))
    : sanitize_textarea_field((string)($settings['blog_prompt_custom_instructions'] ?? ''));
$profile_prompt_active = function_exists('cbia_prompt_has_explicit_profile') ? cbia_prompt_has_explicit_profile() : false;
$recommended_prompt_block = function_exists('cbia_prompt_get_recommended_editable_block')
    ? cbia_prompt_get_recommended_editable_block((array)$settings, (string)($settings['post_language'] ?? 'English'))
    : '';
$prompt_profile_cards = array(
    'discover_editorial' => array(
        'icon' => 'dashicons-welcome-write-blog',
        'preset' => array('faq' => 1, 'examples' => 0, 'intent' => 'soft'),
    ),
    'seo_balanced' => array(
        'icon' => 'dashicons-chart-area',
        'preset' => array('faq' => 1, 'examples' => 0, 'intent' => 'strong'),
    ),
    'how_to' => array(
        'icon' => 'dashicons-editor-ul',
        'preset' => array('faq' => 1, 'examples' => 1, 'intent' => 'balanced'),
    ),
);
$blog_prompt_editable = (string)($settings['blog_prompt_editable'] ?? '');
$prompt_language = (string)($settings['post_language'] ?? 'English');
if ($blog_prompt_editable === '' && function_exists('cbia_prompt_recommended_editable_default')) {
    $blog_prompt_editable = (function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($prompt_language))
        ? cbia_prompt_recommended_editable_legacy_default()
        : cbia_prompt_recommended_editable_default();
}
if (function_exists('cbia_prompt_maybe_upgrade_legacy_editable')) {
    $blog_prompt_editable = cbia_prompt_maybe_upgrade_legacy_editable($blog_prompt_editable, $prompt_language);
}
$blog_prompt_editable = function_exists('cbia_prompt_sanitize_editable_block')
    ? cbia_prompt_sanitize_editable_block($blog_prompt_editable)
    : $blog_prompt_editable;
$legacy_full_prompt = (string)($settings['legacy_full_prompt'] ?? '');
$legacy_placeholder = (string)($settings['prompt_single_all'] ?? '');
if (function_exists('cbia_fix_mojibake')) {
    $legacy_full_prompt = cbia_fix_mojibake($legacy_full_prompt);
    $legacy_placeholder = cbia_fix_mojibake($legacy_placeholder);
}
if (function_exists('cbia_prompt_clean_legacy_template')) {
    $legacy_full_prompt = cbia_prompt_clean_legacy_template($legacy_full_prompt, $prompt_language);
    $legacy_placeholder = cbia_prompt_clean_legacy_template($legacy_placeholder, $prompt_language);
}
$csv_url = $settings['csv_url'] ?? '';
$csv_sample_url = defined('CBIA_PRO_PLUGIN_URL')
    ? CBIA_PRO_PLUGIN_URL . 'assets/csv/titles-sample.csv'
    : plugins_url('assets/csv/titles-sample.csv', dirname(__DIR__, 3) . '/ai-blog-builder-pro.php');
$csv_preview = array(
    'ok' => false,
    'titles' => array(),
    'total' => 0,
    'error' => '',
);
if ($mode === 'csv' && trim((string)$csv_url) !== '') {
    $csv_preview_url = function_exists('cbia_pro_validate_remote_csv_url')
        ? cbia_pro_validate_remote_csv_url($csv_url)
        : trim((string)$csv_url);
    if ($csv_preview_url === '') {
        $csv_preview['error'] = 'CSV URL invalid or blocked.';
    } else {
        $resp = wp_remote_get($csv_preview_url, array('timeout' => 15));
        if (is_wp_error($resp)) {
            $csv_preview['error'] = (string)$resp->get_error_message();
        } else {
            $code = (int)wp_remote_retrieve_response_code($resp);
            if ($code >= 400) {
                $csv_preview['error'] = 'HTTP ' . $code;
            } else {
                $body = (string)wp_remote_retrieve_body($resp);
                $lines = preg_split('/\r\n|\r|\n/', $body);
                $titles = array();
                foreach ((array)$lines as $idx => $line) {
                    $line = trim((string)$line);
                    if ($line === '') continue;
                    $row = str_getcsv($line);
                    $first = trim((string)($row[0] ?? ''));
                    if ($first === '') continue;
                    $first_l = strtolower($first);
                    if ($idx === 0 && in_array($first_l, array('title', 'titulo', 'tÃ­tulo'), true)) continue;
                    $titles[] = $first;
                }
                $titles = array_values(array_unique(array_filter(array_map('trim', $titles))));
                $csv_preview['ok'] = true;
                $csv_preview['total'] = count($titles);
                $csv_preview['titles'] = array_slice($titles, 0, 8);
            }
        }
    }
}

$first_dt = $settings['first_publication_datetime'] ?? '';
$first_dt_default_local = function_exists('wp_date')
    ? wp_date('Y-m-d\TH:i', current_time('timestamp'))
    : date_i18n('Y-m-d\TH:i', current_time('timestamp'));
$first_dt_local = $first_dt_default_local;
if ($first_dt !== '') {
    $first_dt_local = substr(str_replace(' ', 'T', $first_dt), 0, 16);
}

$interval = max(1, intval($settings['publication_interval'] ?? 5));
$enable_cron = !empty($settings['enable_cron_fill']);

$cp_status = __('idle', 'cbiastudio-blogflow-ai');
$last_dt = __('(no records)', 'cbiastudio-blogflow-ai');
if ($service && method_exists($service, 'get_checkpoint_status')) {
    $status_payload = $service->get_checkpoint_status();
    if (is_array($status_payload)) {
        $cp_status = (string)($status_payload['status'] ?? $cp_status);
        $last_dt = (string)($status_payload['last'] ?? $last_dt);
    }
} else {
    $cp = cbia_checkpoint_get();
    $cp_status = (!empty($cp) && !empty($cp['running']))
        ? sprintf(
            /* translators: 1: current checkpoint index, 2: total queued posts */
            __('RUNNING | idx %1$d of %2$d', 'cbiastudio-blogflow-ai'),
            intval($cp['idx'] ?? 0),
            count((array)($cp['queue'] ?? array()))
        )
        : __('idle', 'cbiastudio-blogflow-ai');
    $last_dt = $service && method_exists($service, 'get_last_scheduled_at')
        ? ($service->get_last_scheduled_at() ?: __('(no records)', 'cbiastudio-blogflow-ai'))
        : (function_exists('cbia_get_last_scheduled_at') ? (cbia_get_last_scheduled_at() ?: __('(no records)', 'cbiastudio-blogflow-ai')) : __('(no records)', 'cbiastudio-blogflow-ai'));
}

$log_payload = $service && method_exists($service, 'get_log') ? $service->get_log() : cbia_get_log();
$log_text = is_array($log_payload) ? (string)($log_payload['log'] ?? '') : '';

if ($saved_notice === 'saved' || $saved_notice === 'guardado') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Blog settings saved.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($saved_notice === 'saved_warn' || $saved_notice === 'guardado_warn') {
    $warns = get_transient('cbia_blog_prompt_warnings');
    if (!is_array($warns)) $warns = array();
    $msg = (string)__('Blog settings saved with warnings.', 'cbiastudio-blogflow-ai');
    if (!empty($warns)) {
        $msg .= ' ' . implode(' ', array_map('sanitize_text_field', $warns));
    }
    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($msg) . '</p></div>';
} elseif ($saved_notice === 'test') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Test executed. Check the log.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($saved_notice === 'stop') {
    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Stop enabled.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($saved_notice === 'pending') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Fill pending executed. Check the log.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($saved_notice === 'checkpoint') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Checkpoint cleared and schedule reset.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($saved_notice === 'log') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Log cleared.', 'cbiastudio-blogflow-ai') . '</p></div>';
}

$ajax_nonce = wp_create_nonce('cbia_ajax_nonce');
$manual_titles_list = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$manual_titles))));
$manual_single_title = isset($manual_titles_list[0]) ? (string)$manual_titles_list[0] : '';
?>

<section class="cbia-blog-hub-intro cbia-card">
<h2 style="margin-top:0;margin-bottom:8px;"><?php echo esc_html__('Blog Operations Center', 'cbiastudio-blogflow-ai'); ?></h2>
<p style="margin:0 0 8px 0;"><?php echo wp_kses_post(__('Use this tab to generate by titles, schedule batches, and monitor the queue. For single-post work, the main flow is in <strong>Posts &gt; Create with AI</strong>.', 'cbiastudio-blogflow-ai')); ?></p>
<p class="description" style="margin:0;"><?php echo esc_html__('Use this tab only for batch execution and queue monitoring.', 'cbiastudio-blogflow-ai'); ?></p>
</section>

<div id="cbia-blog-section-titles" class="cbia-blog-anchor" aria-hidden="true"></div>
<h2 class="cbia-ui-kicker"><?php echo esc_html__('Generation by titles', 'cbiastudio-blogflow-ai'); ?></h2>
<div class="cbia-blog-block-inner">
<form method="post">
<input type="hidden" name="cbia_form" value="blog_save" />
<?php wp_nonce_field('cbia_blog_save_nonce'); ?>

<table class="form-table">
<tr>
<th><?php echo esc_html__('Mode', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<div class="cbia-blog-choice-pills" id="cbia-title-mode-pills">
<label class="<?php echo $mode === 'manual' ? 'is-active' : ''; ?>"><input type="radio" name="title_input_mode" value="manual" <?php checked($mode,'manual'); ?> /> <?php echo esc_html__('Manual', 'cbiastudio-blogflow-ai'); ?></label>
<label class="<?php echo $mode === 'csv' ? 'is-active' : ''; ?>"><input type="radio" name="title_input_mode" value="csv" <?php checked($mode,'csv'); ?> /> CSV</label>
</div>
</td>
</tr>
<tr id="cbia_row_manual" <?php if($mode!=='manual') echo 'style="display:none;"'; ?>>
<th><?php echo esc_html__('Manual titles', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<?php if ($runtime_advanced_enabled) : ?>
<textarea name="manual_titles" rows="6" style="width:100%;max-width:1100px;" placeholder="<?php echo esc_attr__('One title per line', 'cbiastudio-blogflow-ai'); ?>"><?php echo esc_textarea($manual_titles); ?></textarea>
<p class="description"><?php echo esc_html__('Save and then click "Create Blogs (with resume)".', 'cbiastudio-blogflow-ai'); ?></p>
<?php else : ?>
<input type="text" name="manual_titles" value="<?php echo esc_attr($manual_single_title); ?>" style="width:100%;max-width:1100px;" placeholder="<?php echo esc_attr__('Single title', 'cbiastudio-blogflow-ai'); ?>" />
<p class="description"><strong><?php echo esc_html__('Base mode:', 'cbiastudio-blogflow-ai'); ?></strong> <?php echo esc_html__('manual mode accepts one title only. Batch queue and scheduling are available in Pro.', 'cbiastudio-blogflow-ai'); ?></p>
<?php endif; ?>

<p style="margin-top:10px;">
<button type="submit" class="button button-primary"><?php echo esc_html__('Save', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</td>
</tr>
<tr id="cbia_row_csv" <?php if($mode!=='csv') echo 'style="display:none;"'; ?>>
<th><?php echo esc_html__('CSV URL', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="text" name="csv_url" value="<?php echo esc_attr($csv_url); ?>" style="width:100%;max-width:1100px;" />
<div style="margin-top:8px;max-width:1100px;padding:10px 12px;border:1px solid #dcdcde;border-radius:8px;background:#f8fafc;">
    <p class="description" style="margin:0 0 6px 0;">
        <?php echo esc_html__('Use a CSV with one title per row (single column).', 'cbiastudio-blogflow-ai'); ?>
    </p>
    <p style="margin:0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <a href="<?php echo esc_url($csv_sample_url); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Download sample CSV', 'cbiastudio-blogflow-ai'); ?></a>
        <button type="button" class="button-link" id="cbia_csv_help_btn" aria-haspopup="dialog" aria-controls="cbia_csv_help_modal" style="padding:0;margin:0;">
            <span class="dashicons dashicons-info-outline" style="font-size:16px;line-height:1.2;vertical-align:middle;"></span>
            <?php echo esc_html__('How to use CSV', 'cbiastudio-blogflow-ai'); ?>
        </button>
    </p>
    <p class="description" style="margin:6px 0 0 0;"><?php echo esc_html__('Google Drive /file/d/... links are automatically converted to direct download format.', 'cbiastudio-blogflow-ai'); ?></p>
</div>
<p style="margin-top:10px;">
<button type="submit" class="button button-primary"><?php echo esc_html__('Save', 'cbiastudio-blogflow-ai'); ?></button>
<button type="button" class="button" id="cbia_csv_test_btn"><?php echo esc_html__('Test CSV now', 'cbiastudio-blogflow-ai'); ?></button>
</p>
<div id="cbia_csv_test_result" style="margin-top:10px;max-width:1100px;"></div>
<?php if ($mode === 'csv' && trim((string)$csv_url) !== ''): ?>
    <div style="margin-top:10px;max-width:1100px;padding:10px 12px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">
        <?php if (!empty($csv_preview['error'])): ?>
            <p style="margin:0;color:#b32d2e;">
                <strong><?php echo esc_html__('Could not read CSV:', 'cbiastudio-blogflow-ai'); ?></strong>
                <?php echo esc_html($csv_preview['error']); ?>
            </p>
        <?php else: ?>
            <p style="margin:0 0 8px 0;">
                <strong><?php echo esc_html__('Detected titles in CSV:', 'cbiastudio-blogflow-ai'); ?></strong>
                <?php echo esc_html((string)$csv_preview['total']); ?>
            </p>
            <?php if (!empty($csv_preview['titles'])): ?>
                <ol style="margin:0 0 0 18px;">
                    <?php foreach ($csv_preview['titles'] as $t): ?>
                        <li><?php echo esc_html($t); ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php if ((int)$csv_preview['total'] > count((array)$csv_preview['titles'])): ?>
                    <p class="description" style="margin:8px 0 0 0;">
                        <?php echo esc_html__('Showing first titles only.', 'cbiastudio-blogflow-ai'); ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="description" style="margin:0;"><?php echo esc_html__('No titles found. Check CSV format: first column "title", one row per title.', 'cbiastudio-blogflow-ai'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
</td>
</tr>
<tr>
<th><?php echo esc_html__('Blog content prompt', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<div class="cbia-blog-prompt-panel" style="padding:12px;border:1px solid #dcdcde;border-radius:8px;max-width:1100px;">
<p class="description" style="margin-top:0;"><?php echo esc_html__('Editorial prompt optimized for Google Discover and image marker insertion. You can adjust style, but fixed rules prevent truncation and keep compatibility.', 'cbiastudio-blogflow-ai'); ?></p>
<p class="description" style="margin-top:0;"><?php echo esc_html__('Language is automatically applied from the language selector and is not edited in this prompt.', 'cbiastudio-blogflow-ai'); ?></p>
<div class="cbia-prompt-mode-grid" id="cbia-prompt-mode-pills">
    <label class="cbia-prompt-mode-card <?php echo $blog_prompt_mode === 'recommended' ? 'is-active' : ''; ?>">
        <input type="radio" name="blog_prompt_mode_ui" value="recommended" <?php checked($blog_prompt_mode, 'recommended'); ?> />
        <span class="cbia-prompt-mode-card-head">
            <span class="dashicons dashicons-superhero-alt"></span>
            <span><?php echo esc_html__('Recommended prompts', 'cbiastudio-blogflow-ai'); ?></span>
        </span>
        <span class="cbia-prompt-mode-card-copy"><?php echo esc_html__('Structured profiles for the normal workflow. Best balance between quality, stability and SEO control.', 'cbiastudio-blogflow-ai'); ?></span>
    </label>
    <label class="cbia-prompt-mode-card <?php echo $blog_prompt_mode === 'legacy' ? 'is-active' : ''; ?>">
        <input type="radio" name="blog_prompt_mode_ui" value="legacy" <?php checked($blog_prompt_mode, 'legacy'); ?> />
        <span class="cbia-prompt-mode-card-head">
            <span class="dashicons dashicons-warning"></span>
            <span><?php echo esc_html__('Advanced / Legacy', 'cbiastudio-blogflow-ai'); ?></span>
        </span>
        <span class="cbia-prompt-mode-card-copy"><?php echo esc_html__('Full manual control of the historical prompt. Use only when you need to override the protected recommended flow.', 'cbiastudio-blogflow-ai'); ?></span>
    </label>
</div>
<input type="hidden" name="blog_prompt_mode" id="cbia_blog_prompt_mode" value="<?php echo esc_attr($blog_prompt_mode); ?>" />
<input type="hidden" name="blog_prompt_custom_instructions" id="cbia_blog_prompt_custom_instructions" value="" />

<div id="cbia_prompt_edit_wrap" style="margin-top:16px;">
    <div id="cbia_prompt_edit_recommended">
        <?php if (!$profile_prompt_active): ?>
            <p class="description" style="margin-top:0;"><?php echo esc_html__('This site is still using the historical recommended block. Saving this panel will migrate recommended mode to profile-based prompts while keeping the current runtime safe.', 'cbiastudio-blogflow-ai'); ?></p>
        <?php endif; ?>
        <div class="cbia-prompt-profile-grid" id="cbia_prompt_profile_cards">
            <?php foreach ($prompt_profile_options as $profile_key => $profile_meta): $card_meta = $prompt_profile_cards[$profile_key] ?? array(); $preset_meta = (array)($card_meta['preset'] ?? array()); ?>
                <label class="cbia-prompt-profile-card <?php echo $blog_prompt_profile === $profile_key ? 'is-active' : ''; ?>" data-profile-card="<?php echo esc_attr($profile_key); ?>">
                    <input type="radio" name="blog_prompt_profile" value="<?php echo esc_attr($profile_key); ?>" <?php checked($blog_prompt_profile, $profile_key); ?> />
                    <span class="cbia-prompt-profile-icon dashicons <?php echo esc_attr((string)($card_meta['icon'] ?? 'dashicons-edit')); ?>"></span>
                    <strong><?php echo esc_html((string)($profile_meta['label'] ?? $profile_key)); ?></strong>
                    <span class="cbia-prompt-profile-copy"><?php echo esc_html((string)($profile_meta['description'] ?? '')); ?></span>
                    <span class="cbia-prompt-profile-preset">
                        <?php
                        $faq_label = !empty($preset_meta['faq']) ? __('FAQ On', 'cbiastudio-blogflow-ai') : __('FAQ Off', 'cbiastudio-blogflow-ai');
                        $ex_label = !empty($preset_meta['examples']) ? __('Examples On', 'cbiastudio-blogflow-ai') : __('Examples Off', 'cbiastudio-blogflow-ai');
                        $intent_key = sanitize_key((string)($preset_meta['intent'] ?? 'balanced'));
                        $intent_map = array(
                            'soft' => __('Soft', 'cbiastudio-blogflow-ai'),
                            'balanced' => __('Balanced', 'cbiastudio-blogflow-ai'),
                            'strong' => __('Strong', 'cbiastudio-blogflow-ai'),
                        );
                        $intent_label = $intent_map[$intent_key] ?? $intent_map['balanced'];
                        printf(
                            /* translators: 1: FAQ preset, 2: practical examples preset, 3: intent preset */
                            esc_html__('Preset: %1$s | %2$s | Intent %3$s', 'cbiastudio-blogflow-ai'),
                            esc_html($faq_label),
                            esc_html($ex_label),
                            esc_html($intent_label)
                        );
                        ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description" id="cbia_blog_prompt_profile_help" style="margin:10px 0 0 0;"><?php echo esc_html((string)($prompt_profile_options[$blog_prompt_profile]['description'] ?? '')); ?></p>

        <div class="cbia-prompt-config-state">
            <p id="cbia_prompt_config_summary" class="description"><?php echo esc_html__('Loading profile preset status...', 'cbiastudio-blogflow-ai'); ?></p>
            <button type="button" class="button" id="cbia_btn_restore_profile_preset"><?php echo esc_html__('Use recommended preset', 'cbiastudio-blogflow-ai'); ?></button>
        </div>

        <div class="cbia-prompt-options-grid">
            <label class="cbia-prompt-option-chip">
                <input type="checkbox" name="include_faq" id="cbia_include_faq" value="1" <?php checked($include_faq, 1); ?> />
                <span class="dashicons dashicons-editor-help"></span>
                <span>
                    <strong><?php echo esc_html__('Text + FAQ', 'cbiastudio-blogflow-ai'); ?></strong>
                    <small><?php echo esc_html__('Keeps the FAQ block in the generated article.', 'cbiastudio-blogflow-ai'); ?></small>
                </span>
            </label>
            <label class="cbia-prompt-option-chip">
                <input type="checkbox" name="include_practical_examples" id="cbia_include_practical_examples" value="1" <?php checked($include_practical_examples, 1); ?> />
                <span class="dashicons dashicons-lightbulb"></span>
                <span>
                    <strong><?php echo esc_html__('Practical examples', 'cbiastudio-blogflow-ai'); ?></strong>
                    <small><?php echo esc_html__('Adds scenarios, examples and practical angles when useful.', 'cbiastudio-blogflow-ai'); ?></small>
                </span>
            </label>
            <div class="cbia-prompt-intent-card">
                <span class="dashicons dashicons-search"></span>
                <div>
                    <strong><?php echo esc_html__('Search intent strength', 'cbiastudio-blogflow-ai'); ?></strong>
                    <small><?php echo esc_html__('Controls how strongly the prompt prioritizes direct search answers vs editorial flow.', 'cbiastudio-blogflow-ai'); ?></small>
                </div>
                <select name="search_intent_strength" id="cbia_search_intent_strength">
                    <option value="soft" <?php selected($search_intent_strength, 'soft'); ?>><?php echo esc_html__('Soft', 'cbiastudio-blogflow-ai'); ?></option>
                    <option value="balanced" <?php selected($search_intent_strength, 'balanced'); ?>><?php echo esc_html__('Balanced', 'cbiastudio-blogflow-ai'); ?></option>
                    <option value="strong" <?php selected($search_intent_strength, 'strong'); ?>><?php echo esc_html__('Strong', 'cbiastudio-blogflow-ai'); ?></option>
                </select>
            </div>
        </div>

        <details class="cbia-prompt-editor-card cbia-prompt-editor-accordion">
            <summary class="cbia-prompt-editor-summary">
                <span class="cbia-prompt-editor-summary-copy">
                    <strong><?php echo esc_html__('Editable prompt block', 'cbiastudio-blogflow-ai'); ?></strong>
                    <small><?php echo esc_html__('Expand to review and edit the exact editorial block sent to the model.', 'cbiastudio-blogflow-ai'); ?></small>
                </span>
                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </summary>
            <div class="cbia-prompt-editor-body">
                <div class="cbia-prompt-editor-head">
                    <div>
                        <p><?php echo esc_html__('This is the editorial block sent inside the protected prompt template. You can adjust it without touching the fixed rules, HTML safeguards or image marker format.', 'cbiastudio-blogflow-ai'); ?></p>
                    </div>
                    <button type="button" class="button" id="cbia_btn_restore_prompt"><?php echo esc_html__('Reset to selected profile', 'cbiastudio-blogflow-ai'); ?></button>
                </div>
                <textarea name="blog_prompt_editable" id="cbia_blog_prompt_editable" rows="14" style="width:100%;" placeholder="<?php echo esc_attr__('The generated editorial block will appear here.', 'cbiastudio-blogflow-ai'); ?>"><?php echo esc_textarea($recommended_prompt_block !== '' ? $recommended_prompt_block : $blog_prompt_editable); ?></textarea>
            </div>
        </details>
    </div>
    <div id="cbia_prompt_edit_legacy">
        <p class="description" style="margin-top:0;"><?php echo esc_html__('Warning: legacy mode lets you replace the full prompt and can break structure, language or image markers if edited carelessly.', 'cbiastudio-blogflow-ai'); ?></p>
        <textarea name="legacy_full_prompt" rows="12" style="width:100%;" placeholder="<?php echo esc_attr__('Full legacy prompt', 'cbiastudio-blogflow-ai'); ?>"><?php echo esc_textarea($legacy_full_prompt !== '' ? $legacy_full_prompt : $legacy_placeholder); ?></textarea>
        <p class="description"><?php echo esc_html__('Advanced mode: uses the full historical prompt for compatibility.', 'cbiastudio-blogflow-ai'); ?></p>
    </div>
</div>
<p style="margin:12px 0 0 0;">
    <button type="submit" class="button button-primary"><?php echo esc_html__('Save prompt settings', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</div>
</td>
</tr>
</table>
</form>
</div>

<div id="cbia_csv_help_modal" role="dialog" aria-modal="true" aria-labelledby="cbia_csv_help_title" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;">
    <div style="background:#fff;max-width:760px;margin:8vh auto;padding:16px 18px;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.2);position:relative;">
        <button type="button" id="cbia_csv_help_close" class="button-link" style="position:absolute;top:10px;right:12px;"><?php echo esc_html__('Close', 'cbiastudio-blogflow-ai'); ?></button>
        <h3 id="cbia_csv_help_title" style="margin-top:0;"><?php echo esc_html__('CSV usage guide', 'cbiastudio-blogflow-ai'); ?></h3>
        <ol style="margin-left:18px;">
            <li><?php echo esc_html__('Download the sample CSV and keep the first header as: title', 'cbiastudio-blogflow-ai'); ?></li>
            <li><?php echo esc_html__('Add one post title per row in the first column.', 'cbiastudio-blogflow-ai'); ?></li>
            <li><?php echo esc_html__('Upload the CSV to a public URL (Google Drive, Dropbox, your site, etc.).', 'cbiastudio-blogflow-ai'); ?></li>
            <li><?php echo esc_html__('Paste a direct-download URL in CSV URL (not a preview page).', 'cbiastudio-blogflow-ai'); ?></li>
        </ol>
        <p style="margin-bottom:6px;"><strong><?php echo esc_html__('Google Drive direct URL', 'cbiastudio-blogflow-ai'); ?></strong></p>
        <code style="display:block;padding:8px;background:#f6f7f7;border-radius:4px;word-break:break-all;">https://drive.google.com/uc?export=download&id=FILE_ID</code>
        <p class="description" style="margin-top:8px;"><?php echo esc_html__('If your link looks like /file/d/FILE_ID/view, convert it to the direct URL format above.', 'cbiastudio-blogflow-ai'); ?></p>
    </div>
</div>

<?php
$preview_titles = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$manual_titles))));
$recent_posts = get_posts(array(
    'post_type'      => 'post',
    'post_status'    => array('draft', 'future', 'publish'),
    'posts_per_page' => 8,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'suppress_filters' => false,
));
?>

<div id="cbia-blog-section-config" class="cbia-blog-anchor" aria-hidden="true"></div>
<h2 class="cbia-ui-kicker"><?php echo esc_html($runtime_advanced_enabled ? __('Editorial configuration and scheduling', 'cbiastudio-blogflow-ai') : __('Editorial configuration', 'cbiastudio-blogflow-ai')); ?></h2>
<div class="cbia-blog-block-inner">
<form method="post">
<input type="hidden" name="cbia_form" value="blog_save" />
<?php wp_nonce_field('cbia_blog_save_nonce'); ?>
<table class="form-table">
<tr>
<th>Autor por defecto</th>
<td>
<p class="description">Recomendado para ejecuciones por evento/cron. Si lo dejas en "Automatico", WordPress puede mostrar "-" si no hay usuario actual.</p>
<?php
$author_args = [
    'name'             => 'default_author_id',
    'selected'         => (int)($settings['default_author_id'] ?? 0),
    'show_option_none' => '- Automatico (usuario actual / admin) -',
    'option_none_value'=> 0,
    'capability'       => ['edit_posts'],
    'class'            => 'regular-text',
];
ob_start();
wp_dropdown_users($author_args);
$dd = ob_get_clean();
$dd = str_replace('class=\'', 'style="width:420px;" class=\'', $dd);
$dd = str_replace('class="', 'style="width:420px;" class="', $dd);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated HTML from wp_dropdown_users().
echo $dd;
?>
</td>
</tr>
<tr>
<th><?php echo esc_html__('Post language', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<?php
$language_options = [
    'Spanish'     => __('Spanish', 'cbiastudio-blogflow-ai'),
    'Portuguese'  => __('Portuguese', 'cbiastudio-blogflow-ai'),
    'English'     => __('English', 'cbiastudio-blogflow-ai'),
    'French'      => __('French', 'cbiastudio-blogflow-ai'),
    'Italian'     => __('Italian', 'cbiastudio-blogflow-ai'),
    'German'      => __('German', 'cbiastudio-blogflow-ai'),
    'Dutch'       => __('Dutch', 'cbiastudio-blogflow-ai'),
    'Swedish'     => __('Swedish', 'cbiastudio-blogflow-ai'),
    'Danish'      => __('Danish', 'cbiastudio-blogflow-ai'),
    'Norwegian'   => __('Norwegian', 'cbiastudio-blogflow-ai'),
    'Finnish'     => __('Finnish', 'cbiastudio-blogflow-ai'),
    'Polish'      => __('Polish', 'cbiastudio-blogflow-ai'),
    'Czech'       => __('Czech', 'cbiastudio-blogflow-ai'),
    'Slovak'      => __('Slovak', 'cbiastudio-blogflow-ai'),
    'Hungarian'   => __('Hungarian', 'cbiastudio-blogflow-ai'),
    'Romanian'    => __('Romanian', 'cbiastudio-blogflow-ai'),
    'Bulgarian'   => __('Bulgarian', 'cbiastudio-blogflow-ai'),
    'Greek'       => __('Greek', 'cbiastudio-blogflow-ai'),
    'Croatian'    => __('Croatian', 'cbiastudio-blogflow-ai'),
    'Slovenian'   => __('Slovenian', 'cbiastudio-blogflow-ai'),
    'Estonian'    => __('Estonian', 'cbiastudio-blogflow-ai'),
    'Latvian'     => __('Latvian', 'cbiastudio-blogflow-ai'),
    'Lithuanian'  => __('Lithuanian', 'cbiastudio-blogflow-ai'),
    'Irish'       => __('Irish', 'cbiastudio-blogflow-ai'),
    'Maltese'     => __('Maltese', 'cbiastudio-blogflow-ai'),
    'Romansh'     => __('Romansh', 'cbiastudio-blogflow-ai'),
];
$legacy_language_map = [
    'Espanol' => 'Spanish', 'espanol' => 'Spanish', 'espaÃ±ol' => 'Spanish',
    'Portugues' => 'Portuguese', 'Ingles' => 'English', 'Frances' => 'French',
    'espaÃ±ol' => 'Spanish',
    'italiano' => 'Italian', 'Aleman' => 'German', 'Holandes' => 'Dutch',
    'sueco' => 'Swedish', 'Danes' => 'Danish', 'noruego' => 'Norwegian',
    'Fines' => 'Finnish', 'polaco' => 'Polish', 'checo' => 'Czech',
    'eslovaco' => 'Slovak', 'Hungaro' => 'Hungarian', 'rumano' => 'Romanian',
    'Bulgaro' => 'Bulgarian', 'griego' => 'Greek', 'croata' => 'Croatian',
    'esloveno' => 'Slovenian', 'estonio' => 'Estonian', 'Leton' => 'Latvian',
    'lituano' => 'Lithuanian', 'Irlandes' => 'Irish', 'Maltes' => 'Maltese',
    'romanche' => 'Romansh',
];
$current_language_raw = (string)($settings['post_language'] ?? 'English');
$current_language = $legacy_language_map[$current_language_raw] ?? $current_language_raw;
if (!isset($language_options[$current_language])) $current_language = 'English';
echo '<select name="post_language" class="abb-select" style="width:220px;">';
foreach ($language_options as $val => $label) {
    echo '<option value="' . esc_attr($val) . '" ' . selected($current_language, $val, false) . '>' . esc_html($label) . '</option>';
}
echo '</select>';
?>
<p class="description"><?php echo esc_html__('Used for {POST_LANGUAGE} and to normalize the "Frequently asked questions" heading.', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th><?php echo esc_html__('Default category', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="text" name="default_category" value="<?php echo esc_attr((string)($settings['default_category'] ?? 'News')); ?>" style="width:420px;" />
</td>
</tr>
<tr>
<th><?php echo esc_html__('Rules: keywords -> categories', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<textarea name="keywords_to_categories" rows="6" style="width:100%;"><?php echo esc_textarea((string)($settings['keywords_to_categories'] ?? '')); ?></textarea>
<p class="description"><?php echo wp_kses_post(__('Line format: <code>Category: kw1, kw2, kw3</code>. Compared against (title + content).', 'cbiastudio-blogflow-ai')); ?></p>
</td>
</tr>
<tr>
<th><?php echo esc_html__('Allowed tags', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="text" name="default_tags" value="<?php echo esc_attr((string)($settings['default_tags'] ?? '')); ?>" style="width:100%;" />
<p class="description"><?php echo esc_html__('Comma-separated. The engine can only use tags from this list (max 7 per post).', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
</table>
<p style="margin-top:10px;">
<button type="submit" class="button button-primary"><?php echo esc_html__('Save', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</form>

<?php if ($runtime_advanced_enabled) : ?>
<h3 style="margin-top:20px;"><?php echo esc_html__('Scheduling', 'cbiastudio-blogflow-ai'); ?></h3>
<form method="post">
<input type="hidden" name="cbia_form" value="blog_save" />
<?php wp_nonce_field('cbia_blog_save_nonce'); ?>

<table class="form-table">
<tr>
<th><?php echo esc_html__('First date/time', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="datetime-local" name="first_publication_datetime_local" value="<?php echo esc_attr($first_dt_local); ?>" min="<?php echo esc_attr($first_dt_default_local); ?>" step="60" />
<p class="description"><?php echo esc_html__('If left empty, generation starts immediately. If you set a date/time, the first post is scheduled and the next ones follow the interval.', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th><?php echo esc_html__('Interval between publications (days)', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="number" min="1" name="publication_interval" value="<?php echo esc_attr($interval); ?>" style="width:90px;" />
</td>
</tr>
</table>

<h3 style="margin-top:20px;"><?php echo esc_html__('CRON: fill pending', 'cbiastudio-blogflow-ai'); ?></h3>
<div class="cbia-blog-switch-stack">
<label class="cbia-oldv2-switch-row">
<span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Enable CRON for pending images', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Runs hourly pending-image filling.', 'cbiastudio-blogflow-ai'); ?></span></span>
<span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="enable_cron_fill" <?php checked($enable_cron); ?> /><span class="cbia-oldv2-switch-ui"></span></span>
</label>
</div>

<p style="margin-top:10px;">
<button type="submit" class="button button-primary"><?php echo esc_html__('Save', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</form>
<?php else : ?>
<section class="notice notice-info inline" style="margin-top:20px;">
    <p><strong><?php echo esc_html__('Scheduling and queue checkpoint', 'cbiastudio-blogflow-ai'); ?> <span class="cbia-badge-pro">PRO</span></strong><br/><?php echo esc_html__('Automatic scheduling, interval chaining and checkpoint status are available in Pro only.', 'cbiastudio-blogflow-ai'); ?></p>
</section>
<?php endif; ?>
</div>

<hr/>

<?php if ($runtime_advanced_enabled) : ?>
<div id="cbia-blog-section-ops" class="cbia-blog-anchor" aria-hidden="true"></div>
<section class="cbia-blog-ops-note cbia-card" style="margin-bottom:16px;">
<h2 class="cbia-ui-kicker" style="margin-top:0;"><?php echo esc_html__('Queue and status', 'cbiastudio-blogflow-ai'); ?></h2>
<p style="margin:0 0 8px 0;"><?php echo esc_html__('Use this block to control batch execution, checkpoint state, and process log.', 'cbiastudio-blogflow-ai'); ?></p>
<p class="description" style="margin:0;"><?php echo esc_html__('This is the operations center to generate, stop, resume, and review batch status.', 'cbiastudio-blogflow-ai'); ?></p>
</section>

<section class="cbia-card cbia-blog-ops-grid">
    <div class="cbia-blog-ops-col">
        <h3 style="margin-top:0;"><?php echo esc_html__('Checkpoint status', 'cbiastudio-blogflow-ai'); ?></h3>
        <p><strong id="cbia_cp_status"><?php echo esc_html($cp_status); ?></strong></p>
        <p style="margin-bottom:0;"><strong><?php echo esc_html__('Last scheduled/published:', 'cbiastudio-blogflow-ai'); ?></strong> <code id="cbia_cp_last"><?php echo esc_html($last_dt); ?></code></p>
    </div>
    <div class="cbia-blog-ops-col">
        <div class="cbia-blog-ops-head">
            <h3 style="margin:0;"><?php echo esc_html__('Recent results', 'cbiastudio-blogflow-ai'); ?></h3>
            <span class="cbia-blog-ops-kicker"><?php echo esc_html__('Latest posts', 'cbiastudio-blogflow-ai'); ?></span>
        </div>
        <?php if (!empty($recent_posts)) : ?>
            <div class="cbia-blog-results-compact">
                <?php foreach ($recent_posts as $recent_post) : ?>
                    <?php
                    $recent_id = (int)$recent_post->ID;
                    $recent_title = get_the_title($recent_id);
                    if ($recent_title === '') {
                        $recent_title = (string)__('(Untitled)', 'cbiastudio-blogflow-ai');
                    }
                    $recent_status = get_post_status($recent_id);
                    $recent_date = get_the_date('Y-m-d H:i', $recent_id);
                    $recent_edit_url = get_edit_post_link($recent_id, '');
                    $recent_view_url = ($recent_status === 'publish') ? get_permalink($recent_id) : get_preview_post_link($recent_id);
                    ?>
                    <article class="cbia-blog-result-row">
                        <div class="cbia-blog-result-row-main">
                            <strong class="cbia-blog-result-title"><?php echo esc_html($recent_title); ?></strong>
                            <div class="cbia-blog-result-meta">
                                <span>#<?php echo esc_html((string)$recent_id); ?></span>
                                <span><?php echo esc_html($recent_date); ?></span>
                                <span class="cbia-blog-result-status status-<?php echo esc_attr($recent_status); ?>"><?php echo esc_html($recent_status); ?></span>
                            </div>
                        </div>
                        <div class="cbia-blog-result-actions">
                            <?php if (!empty($recent_edit_url)) : ?>
                                <a class="button button-secondary" href="<?php echo esc_url($recent_edit_url); ?>"><?php echo esc_html__('Edit', 'cbiastudio-blogflow-ai'); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($recent_view_url)) : ?>
                                <a class="button" href="<?php echo esc_url($recent_view_url); ?>" target="_blank" rel="noopener"><?php echo esc_html__('View', 'cbiastudio-blogflow-ai'); ?></a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p style="margin:0;"><?php echo esc_html__('There are no recent posts to display yet.', 'cbiastudio-blogflow-ai'); ?></p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<hr/>

<div id="cbia-blog-section-actions" class="cbia-blog-anchor" aria-hidden="true"></div>
<h2 class="cbia-ui-kicker"><?php echo esc_html__('Batch actions', 'cbiastudio-blogflow-ai'); ?></h2>
<div class="cbia-blog-block-inner">
<form method="post" id="cbia_actions_form">
<input type="hidden" name="cbia_form" value="blog_actions" />
<?php wp_nonce_field('cbia_blog_actions_nonce'); ?>

<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
<button type="submit" class="button" name="cbia_action" value="test_config"><?php echo esc_html__('Test configuration', 'cbiastudio-blogflow-ai'); ?></button>

<button type="button" class="button button-primary" id="cbia_btn_generate"><?php echo esc_html__('Run batch (with resume)', 'cbiastudio-blogflow-ai'); ?></button>

<button type="submit" class="button" name="cbia_action" value="stop_generation" style="background:#b70000;color:#fff;border-color:#7a0000;"><?php echo esc_html__('Stop (STOP)', 'cbiastudio-blogflow-ai'); ?></button>
<button type="submit" class="button" name="cbia_action" value="fill_pending_imgs"><?php echo esc_html__('Fill pending', 'cbiastudio-blogflow-ai'); ?></button>
<button type="submit" class="button" name="cbia_action" value="clear_checkpoint"><?php echo esc_html__('Clear checkpoint', 'cbiastudio-blogflow-ai'); ?></button>
<button type="submit" class="button" name="cbia_action" value="clear_log"><?php echo esc_html__('Clear log', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</form>
</div>

<div id="cbia-blog-section-log" class="cbia-blog-anchor" aria-hidden="true"></div>
<h2 class="cbia-ui-kicker"><?php echo esc_html__('Operational log', 'cbiastudio-blogflow-ai'); ?></h2>
<div class="cbia-blog-block-inner">
<textarea id="cbia_log" rows="14" readonly style="width:100%;max-width:1100px;background:#f9f9f9;"><?php echo esc_textarea($log_text); ?></textarea>
</div>

<?php ob_start(); ?>
(function(){
    const manualRow = document.getElementById('cbia_row_manual');
    const csvRow = document.getElementById('cbia_row_csv');
    const csvUrlInput = document.querySelector('input[name="csv_url"]');
    const csvTestBtn = document.getElementById('cbia_csv_test_btn');
    const csvTestResult = document.getElementById('cbia_csv_test_result');
    const csvHelpBtn = document.getElementById('cbia_csv_help_btn');
    const csvHelpModal = document.getElementById('cbia_csv_help_modal');
    const csvHelpClose = document.getElementById('cbia_csv_help_close');
    const radios = document.querySelectorAll('input[name="title_input_mode"]');
    const titleModePills = document.getElementById('cbia-title-mode-pills');
    function syncChoicePills(wrap){
        if (!wrap) return;
        wrap.querySelectorAll('label').forEach(function(label){
            const input = label.querySelector('input');
            label.classList.toggle('is-active', !!(input && input.checked));
        });
    }
    radios.forEach(r => r.addEventListener('change', function(){
        if(this.value === 'manual'){ manualRow.style.display=''; csvRow.style.display='none'; }
        else { manualRow.style.display='none'; csvRow.style.display=''; }
        syncChoicePills(titleModePills);
    }));
    syncChoicePills(titleModePills);

    function openCsvHelp(){
        if (!csvHelpModal) return;
        csvHelpModal.style.display = '';
    }
    function closeCsvHelp(){
        if (!csvHelpModal) return;
        csvHelpModal.style.display = 'none';
    }
    if (csvHelpBtn) csvHelpBtn.addEventListener('click', openCsvHelp);
    if (csvHelpClose) csvHelpClose.addEventListener('click', closeCsvHelp);
    if (csvHelpModal) {
        csvHelpModal.addEventListener('click', function(evt){
            if (evt.target === csvHelpModal) closeCsvHelp();
        });
    }
    document.addEventListener('keydown', function(evt){
        if (evt.key !== 'Escape') return;
        if (csvHelpModal && csvHelpModal.style.display !== 'none') {
            closeCsvHelp();
        }
    });
    function escHtml(s){
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    function renderCsvTestResult(payload, isError){
        if (!csvTestResult) return;
        if (isError) {
            csvTestResult.innerHTML = '<div style="padding:10px 12px;border:1px solid #d63638;border-radius:8px;background:#fff5f5;color:#b32d2e;"><strong>CSV test failed:</strong> ' + escHtml(payload && payload.message ? payload.message : 'Unknown error') + '</div>';
            return;
        }
        const titles = Array.isArray(payload.titles) ? payload.titles : [];
        let html = '<div style="padding:10px 12px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">';
        html += '<p style="margin:0 0 8px 0;"><strong>Detected titles:</strong> ' + escHtml(payload.total || 0) + '</p>';
        if (payload.normalized_url && csvUrlInput) {
            csvUrlInput.value = payload.normalized_url;
        }
        if (titles.length) {
            html += '<ol style="margin:0 0 0 18px;">';
            titles.forEach(function(t){ html += '<li>' + escHtml(t) + '</li>'; });
            html += '</ol>';
            if ((payload.total || 0) > titles.length) {
                html += '<p class="description" style="margin:8px 0 0 0;">Showing first titles only.</p>';
            }
        } else {
            html += '<p class="description" style="margin:0;">No titles found.</p>';
        }
        html += '</div>';
        csvTestResult.innerHTML = html;
    }
    if (csvTestBtn) {
        csvTestBtn.addEventListener('click', function(){
            if (!csvUrlInput || !String(csvUrlInput.value || '').trim()) {
                renderCsvTestResult({ message: 'Enter a CSV URL first.' }, true);
                return;
            }
            csvTestBtn.disabled = true;
            csvTestBtn.textContent = 'Testing...';
            const fd = new FormData();
            fd.append('action', 'cbia_test_csv_titles');
            fd.append('_ajax_nonce', <?php echo wp_json_encode($ajax_nonce); ?>);
            fd.append('csv_url', csvUrlInput.value);
            fetch(ajaxurl, { method:'POST', credentials:'same-origin', body: fd })
                .then(r => r.text())
                .then(text => {
                    let data = null;
                    try { data = JSON.parse(text); } catch(e) { data = null; }
                    if (!data || !data.success) {
                        renderCsvTestResult({ message: data && data.data && data.data.message ? data.data.message : 'Invalid response' }, true);
                        return;
                    }
                    renderCsvTestResult(data.data || {}, false);
                })
                .catch(() => renderCsvTestResult({ message: 'Network error' }, true))
                .finally(() => {
                    csvTestBtn.disabled = false;
                    csvTestBtn.textContent = 'Test CSV now';
                });
        });
    }

    const logBox = document.getElementById('cbia_log');

    function extractLogText(payload){
        if (!payload) return '';
        if (typeof payload === 'string') return payload;
        if (typeof payload === 'object') {
            if (payload.log && typeof payload.log === 'string') return payload.log;
            if (payload.data && payload.data.log && typeof payload.data.log === 'string') return payload.data.log;
            try { return JSON.stringify(payload, null, 2); } catch(e){ return String(payload); }
        }
        return String(payload);
    }

    function refreshLog(){
        if (typeof ajaxurl === 'undefined') return;
        const logUrl = ajaxurl + '?action=cbia_get_log&_ajax_nonce=' + encodeURIComponent(<?php echo wp_json_encode($ajax_nonce); ?>) + '&ts=' + Date.now();
        fetch(logUrl, { credentials:'same-origin', cache:'no-store' })
        .then(r => r.text())
        .then(text => {
            if(!logBox) return;
            let data = null;
            try { data = JSON.parse(text); } catch(e) {
                logBox.value = extractLogText(text);
                logBox.scrollTop = logBox.scrollHeight;
                return;
            }
            if (data && data.success) {
                logBox.value = extractLogText(data.data);
            } else {
                logBox.value = extractLogText(data);
            }
            logBox.scrollTop = logBox.scrollHeight;
        })
        .catch(()=>{});
    }
    setInterval(refreshLog, 3000);
    refreshLog();

    const cpStatus = document.getElementById('cbia_cp_status');
    const cpLast = document.getElementById('cbia_cp_last');

    function refreshCheckpoint(){
        if (typeof ajaxurl === 'undefined') return;
        const statusUrl = ajaxurl + '?action=cbia_get_checkpoint_status&_ajax_nonce=' + encodeURIComponent(<?php echo wp_json_encode($ajax_nonce); ?>);
        fetch(statusUrl, { credentials:'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data = null;
            try { data = JSON.parse(text); } catch(e) { return; }
            if (!data || !data.success || !data.data) return;
            if (cpStatus) cpStatus.textContent = data.data.status || '';
            if (cpLast) cpLast.textContent = data.data.last || '';
        })
        .catch(()=>{});
    }
    const runtimeAdvancedEnabled = <?php echo wp_json_encode((bool)$runtime_advanced_enabled); ?>;
    if (runtimeAdvancedEnabled) {
        setInterval(refreshCheckpoint, 5000);
        refreshCheckpoint();
    }

    const btn = document.getElementById('cbia_btn_generate');
    const firstPublicationInput = document.querySelector('input[name="first_publication_datetime_local"]');
    const publicationIntervalInput = document.querySelector('input[name="publication_interval"]');
    const manualTitlesInput = document.querySelector('textarea[name="manual_titles"]');
    if(btn){
        btn.addEventListener('click', function(){
            btn.disabled = true;
            const old = btn.textContent;
            btn.textContent = <?php echo wp_json_encode(__('Starting...', 'cbiastudio-blogflow-ai')); ?>;

            const fd = new FormData();
            fd.append('action','cbia_start_generation');
            fd.append('_ajax_nonce', <?php echo wp_json_encode($ajax_nonce); ?>);
            const titleModeSelected = document.querySelector('input[name="title_input_mode"]:checked');
            if (titleModeSelected) fd.append('title_input_mode', titleModeSelected.value || 'manual');
            if (manualTitlesInput) fd.append('manual_titles', manualTitlesInput.value || '');
            if (csvUrlInput) fd.append('csv_url', csvUrlInput.value || '');
            if (firstPublicationInput) fd.append('first_publication_datetime_local', firstPublicationInput.value || '');
            if (publicationIntervalInput) fd.append('publication_interval', publicationIntervalInput.value || '');

            fetch(ajaxurl, { method:'POST', credentials:'same-origin', body: fd })
            .then(r => r.text())
            .then(text => {
                let data = null;
                try { data = JSON.parse(text); } catch(e) { data = null; }
                if(data && data.success){
                    btn.textContent = <?php echo wp_json_encode(__('Running (check log)...', 'cbiastudio-blogflow-ai')); ?>;
                    setTimeout(()=>{ btn.disabled=false; btn.textContent=old; }, 4000);
                }else{
                    const msg = (data && data.data && (data.data.msg || data.data.error))
                        ? String(data.data.msg || data.data.error)
                        : 'Could not start the automatic queue.';
                    if (logBox) {
                        logBox.value += (logBox.value ? '\n' : '') + '[ERROR] START AJAX: ' + msg;
                        logBox.scrollTop = logBox.scrollHeight;
                    }
                    btn.disabled=false; btn.textContent=old;
                }
            })
            .catch(() => {
                if (logBox) {
                    logBox.value += (logBox.value ? '\n' : '') + '[ERROR] START AJAX: ' + <?php echo wp_json_encode(__('network error or invalid response.', 'cbiastudio-blogflow-ai')); ?>;
                    logBox.scrollTop = logBox.scrollHeight;
                }
                btn.disabled=false; btn.textContent=old;
            });
        });
    }

    const postLanguage = document.querySelector('select[name="post_language"]');
    const legacyPrompt = document.querySelector('textarea[name="legacy_full_prompt"]');

    // Prompt panel (recommended/legacy).
    const modeInputs = document.querySelectorAll('input[name="blog_prompt_mode_ui"]');
    const editWrap = document.getElementById('cbia_prompt_edit_wrap');
    const editRecommended = document.getElementById('cbia_prompt_edit_recommended');
    const editLegacy = document.getElementById('cbia_prompt_edit_legacy');
    const restoreBtn = document.getElementById('cbia_btn_restore_prompt');
    const profileInputs = document.querySelectorAll('input[name="blog_prompt_profile"]');
    const profileHelp = document.getElementById('cbia_blog_prompt_profile_help');
    const configSummary = document.getElementById('cbia_prompt_config_summary');
    const restoreProfilePresetBtn = document.getElementById('cbia_btn_restore_profile_preset');
    const includeFaq = document.getElementById('cbia_include_faq');
    const includeExamples = document.getElementById('cbia_include_practical_examples');
    const intentStrength = document.getElementById('cbia_search_intent_strength');
    const editableTa = document.getElementById('cbia_blog_prompt_editable');
    const promptModeState = document.getElementById('cbia_blog_prompt_mode');
    const profileDescriptions = <?php echo wp_json_encode(array_map(static function ($meta) { return (string)($meta['description'] ?? ''); }, $prompt_profile_options)); ?>;
    const profileTemplates = <?php
        $profile_template_payload = array('default' => array(), 'spanish' => array());
        foreach (array('default' => 'English', 'spanish' => 'Spanish') as $bucket => $lang_ref) {
            foreach (array_keys($prompt_profile_options) as $profile_key) {
                foreach (array(0, 1) as $faq_flag) {
                    foreach (array(0, 1) as $examples_flag) {
                        foreach (array('soft', 'balanced', 'strong') as $strength_key) {
                            $template_key = $profile_key . '|' . $faq_flag . '|' . $examples_flag . '|' . $strength_key;
                            $profile_template_payload[$bucket][$template_key] = cbia_build_prompt_profile_block($profile_key, array(
                                'profile' => $profile_key,
                                'include_faq' => (bool) $faq_flag,
                                'include_practical_examples' => (bool) $examples_flag,
                                'search_intent_strength' => $strength_key,
                                'custom_instructions' => '',
                            ), $lang_ref);
                        }
                    }
                }
            }
        }
        echo wp_json_encode($profile_template_payload);
    ?>;
    const profilePresets = <?php
        $profile_preset_payload = array();
        foreach ($prompt_profile_cards as $profile_key => $card_meta) {
            $preset_meta = (array)($card_meta['preset'] ?? array());
            $intent_key = sanitize_key((string)($preset_meta['intent'] ?? 'balanced'));
            if (!in_array($intent_key, array('soft', 'balanced', 'strong'), true)) {
                $intent_key = 'balanced';
            }
            $profile_preset_payload[$profile_key] = array(
                'faq' => !empty($preset_meta['faq']),
                'examples' => !empty($preset_meta['examples']),
                'intent' => $intent_key,
            );
        }
        echo wp_json_encode($profile_preset_payload);
    ?>;
    const promptPresetUi = <?php echo wp_json_encode(array(
        'faq_on' => __('FAQ On', 'cbiastudio-blogflow-ai'),
        'faq_off' => __('FAQ Off', 'cbiastudio-blogflow-ai'),
        'examples_on' => __('Examples On', 'cbiastudio-blogflow-ai'),
        'examples_off' => __('Examples Off', 'cbiastudio-blogflow-ai'),
        'intent_word' => __('Intent', 'cbiastudio-blogflow-ai'),
        'intent_soft' => __('Soft', 'cbiastudio-blogflow-ai'),
        'intent_balanced' => __('Balanced', 'cbiastudio-blogflow-ai'),
        'intent_strong' => __('Strong', 'cbiastudio-blogflow-ai'),
        'preset_prefix' => __('Recommended preset', 'cbiastudio-blogflow-ai'),
        'preset_active' => __('Preset active. You can still customize the options below.', 'cbiastudio-blogflow-ai'),
        'preset_custom' => __('Customized options active. Prompt profile stays selected, but your overrides are being used.', 'cbiastudio-blogflow-ai'),
    )); ?>;
    let lastPromptLanguage = postLanguage ? String(postLanguage.value || '') : '';
    let lastGeneratedBlock = editableTa ? normalizePromptText(editableTa.value) : '';

    function getPromptMode(){
        const selected = document.querySelector('input[name="blog_prompt_mode_ui"]:checked');
        return selected ? selected.value : 'recommended';
    }

    function getSelectedProfile(){
        const selected = document.querySelector('input[name="blog_prompt_profile"]:checked');
        return selected ? String(selected.value || 'discover_editorial') : 'discover_editorial';
    }

    function syncPromptModeState(){
        if (promptModeState) {
            promptModeState.value = getPromptMode();
        }
    }

    function normalizePromptText(value){
        return String(value || '').replace(/\r\n?/g, '\n').trim();
    }

    function syncProfileHelp(){
        if (!profileHelp) return;
        const key = getSelectedProfile();
        profileHelp.textContent = profileDescriptions[key] || '';
    }

    function getPresetForProfile(profileKey){
        const raw = profilePresets && profilePresets[profileKey] ? profilePresets[profileKey] : {};
        const intent = String(raw.intent || 'balanced');
        return {
            faq: !!raw.faq,
            examples: !!raw.examples,
            intent: ['soft', 'balanced', 'strong'].includes(intent) ? intent : 'balanced'
        };
    }

    function getCurrentOptionState(){
        return {
            faq: !!(includeFaq && includeFaq.checked),
            examples: !!(includeExamples && includeExamples.checked),
            intent: intentStrength ? String(intentStrength.value || 'balanced') : 'balanced'
        };
    }

    function isSelectedProfilePreset(){
        const current = getCurrentOptionState();
        const preset = getPresetForProfile(getSelectedProfile());
        return current.faq === preset.faq && current.examples === preset.examples && current.intent === preset.intent;
    }

    function syncPresetSummary(){
        if (!configSummary) return;
        const preset = getPresetForProfile(getSelectedProfile());
        const intentLabels = {
            soft: promptPresetUi.intent_soft || 'Soft',
            balanced: promptPresetUi.intent_balanced || 'Balanced',
            strong: promptPresetUi.intent_strong || 'Strong'
        };
        const faqLabel = preset.faq ? (promptPresetUi.faq_on || 'FAQ On') : (promptPresetUi.faq_off || 'FAQ Off');
        const examplesLabel = preset.examples ? (promptPresetUi.examples_on || 'Examples On') : (promptPresetUi.examples_off || 'Examples Off');
        const intentLabel = intentLabels[preset.intent] || intentLabels.balanced;
        const prefix = promptPresetUi.preset_prefix || 'Recommended preset';
        const intentWord = promptPresetUi.intent_word || 'Intent';
        const presetLine = prefix + ': ' + faqLabel + ' | ' + examplesLabel + ' | ' + intentWord + ' ' + intentLabel + '.';
        const isPreset = isSelectedProfilePreset();
        const statusLine = isPreset
            ? (promptPresetUi.preset_active || 'Preset active.')
            : (promptPresetUi.preset_custom || 'Customized options active.');
        configSummary.textContent = presetLine + ' ' + statusLine;
        configSummary.classList.toggle('is-custom', !isPreset);
        if (restoreProfilePresetBtn) {
            restoreProfilePresetBtn.disabled = isPreset;
        }
    }

    function applySelectedProfilePreset(forceBlock){
        const preset = getPresetForProfile(getSelectedProfile());
        if (includeFaq) includeFaq.checked = !!preset.faq;
        if (includeExamples) includeExamples.checked = !!preset.examples;
        if (intentStrength) intentStrength.value = preset.intent;
        syncRecommendedEditableBlock(!!forceBlock);
        syncPresetSummary();
    }

    function isSpanishLanguage(value){
        const v = String(value || '').trim().toLowerCase();
        return v === 'spanish' || v === 'espanol' || v === 'espaÃ±ol';
    }

    function getPromptTemplateBucket(value){
        return isSpanishLanguage(value) ? 'spanish' : 'default';
    }

    function getCurrentGeneratedBlock(){
        const faqFlag = includeFaq && includeFaq.checked ? '1' : '0';
        const examplesFlag = includeExamples && includeExamples.checked ? '1' : '0';
        const strengthKey = intentStrength ? String(intentStrength.value || 'balanced') : 'balanced';
        const templateKey = [getSelectedProfile(), faqFlag, examplesFlag, strengthKey].join('|');
        const bucket = profileTemplates[getPromptTemplateBucket(postLanguage ? postLanguage.value : lastPromptLanguage)] || profileTemplates.default || {};
        return String(bucket[templateKey] || '');
    }

    function syncPromptModeCards(){
        document.querySelectorAll('.cbia-prompt-mode-card').forEach(function(card){
            const input = card.querySelector('input[name="blog_prompt_mode_ui"]');
            card.classList.toggle('is-active', !!(input && input.checked));
        });
    }

    function syncPromptProfileCards(){
        document.querySelectorAll('.cbia-prompt-profile-card').forEach(function(card){
            const input = card.querySelector('input[name="blog_prompt_profile"]');
            card.classList.toggle('is-active', !!(input && input.checked));
        });
    }

    function syncRecommendedEditableBlock(forceReplace){
        if (!editableTa) return;
        const generatedBlock = getCurrentGeneratedBlock();
        const currentValue = normalizePromptText(editableTa.value);
        const shouldReplace = !!forceReplace || currentValue === '' || currentValue === lastGeneratedBlock;
        if (shouldReplace) {
            editableTa.value = generatedBlock;
        }
        lastGeneratedBlock = normalizePromptText(generatedBlock);
        lastPromptLanguage = postLanguage ? String(postLanguage.value || '') : lastPromptLanguage;
    }

    function refreshPromptEditor(){
        const mode = getPromptMode();
        if (editWrap) editWrap.style.display = '';
        if (editRecommended) editRecommended.style.display = mode === 'recommended' ? '' : 'none';
        if (editLegacy) editLegacy.style.display = mode === 'legacy' ? '' : 'none';
        syncPromptModeState();
        syncPromptModeCards();
        syncPromptProfileCards();
        syncProfileHelp();
        if (mode === 'recommended') {
            syncRecommendedEditableBlock(false);
        }
        syncPresetSummary();
    }

    modeInputs.forEach(function(r){
        r.addEventListener('change', function(){
            refreshPromptEditor();
        });
    });

    function ensureHiddenInForm(form, name, value){
        if (!form) return;
        let field = form.querySelector('input[type="hidden"][data-cbia-mirror="1"][name="' + name + '"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            field.setAttribute('data-cbia-mirror', '1');
            form.appendChild(field);
        }
        field.value = String(value == null ? '' : value);
    }

    function syncPromptFieldsToAllSaveForms(){
        const mode = getPromptMode();
        const editableVal = editableTa ? editableTa.value : '';
        const legacyVal = legacyPrompt ? legacyPrompt.value : '';
        const profileVal = getSelectedProfile();
        const includeFaqVal = includeFaq && includeFaq.checked ? '1' : '';
        const includeExamplesVal = includeExamples && includeExamples.checked ? '1' : '';
        const intentVal = intentStrength ? intentStrength.value : 'balanced';
        const customVal = '';
        const saveForms = document.querySelectorAll('form input[name="cbia_form"][value="blog_save"]');
        saveForms.forEach(function(hiddenCbiaForm){
            const form = hiddenCbiaForm.form;
            if (!form) return;
            ensureHiddenInForm(form, 'blog_prompt_mode', mode);
            if (editableTa) ensureHiddenInForm(form, 'blog_prompt_editable', editableVal);
            ensureHiddenInForm(form, 'blog_prompt_profile', profileVal);
            ensureHiddenInForm(form, 'include_faq', includeFaqVal);
            ensureHiddenInForm(form, 'include_practical_examples', includeExamplesVal);
            ensureHiddenInForm(form, 'search_intent_strength', intentVal);
            ensureHiddenInForm(form, 'blog_prompt_custom_instructions', customVal);
            if (legacyPrompt) ensureHiddenInForm(form, 'legacy_full_prompt', legacyVal);
        });
    }

    const saveForms = document.querySelectorAll('form input[name="cbia_form"][value="blog_save"]');
    saveForms.forEach(function(hiddenCbiaForm){
        const form = hiddenCbiaForm.form;
        if (!form) return;
        form.addEventListener('submit', function(){
            syncPromptModeState();
            syncPromptFieldsToAllSaveForms();
        });
    });

    if (restoreBtn) {
        restoreBtn.addEventListener('click', function(){
            syncRecommendedEditableBlock(true);
            syncPromptFieldsToAllSaveForms();
        });
    }
    if (restoreProfilePresetBtn) {
        restoreProfilePresetBtn.addEventListener('click', function(){
            applySelectedProfilePreset(true);
            syncPromptFieldsToAllSaveForms();
        });
    }
    if (postLanguage) {
        postLanguage.addEventListener('change', function(){
            syncRecommendedEditableBlock(false);
            syncPresetSummary();
            syncPromptFieldsToAllSaveForms();
        });
    }
    profileInputs.forEach(function(field){
        field.addEventListener('change', function(){
            applySelectedProfilePreset(true);
            refreshPromptEditor();
            syncPromptFieldsToAllSaveForms();
        });
    });
    [includeFaq, includeExamples, intentStrength].forEach(function(field){
        if (!field) return;
        field.addEventListener('change', function(){
            syncRecommendedEditableBlock(false);
            syncPresetSummary();
            syncPromptFieldsToAllSaveForms();
        });
    });
    if (editableTa) {
        editableTa.addEventListener('input', syncPromptFieldsToAllSaveForms);
    }

    syncPromptModeState();
    syncPromptFieldsToAllSaveForms();
    syncProfileHelp();
    syncPresetSummary();

    refreshPromptEditor();
})();
<?php wp_add_inline_script('abb-admin', (string) ob_get_clean(), 'after'); ?>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
