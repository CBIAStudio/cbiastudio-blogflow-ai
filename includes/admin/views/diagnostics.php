<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) { exit; }
if (!current_user_can('manage_options')) return;

$settings = get_option('cbia_settings', array());
$api_key = (string)($settings['openai_api_key'] ?? '');
$api_masked = $api_key !== '' ? (substr($api_key, 0, 4) . '...' . substr($api_key, -4)) : '';

$yes = __('Yes', 'cbiastudio-blogflow-ai');
$no = __('No', 'cbiastudio-blogflow-ai');
$not_available = __('n/a', 'cbiastudio-blogflow-ai');

$info = array(
    __('Plugin version', 'cbiastudio-blogflow-ai') => defined('CBIA_VERSION') ? CBIA_VERSION : $not_available,
    __('WordPress', 'cbiastudio-blogflow-ai') => get_bloginfo('version'),
    __('PHP', 'cbiastudio-blogflow-ai') => PHP_VERSION,
    __('Memory limit (PHP)', 'cbiastudio-blogflow-ai') => (string)ini_get('memory_limit'),
    __('Max execution time', 'cbiastudio-blogflow-ai') => (string)ini_get('max_execution_time'),
    __('Upload max filesize', 'cbiastudio-blogflow-ai') => (string)ini_get('upload_max_filesize'),
    __('Post max size', 'cbiastudio-blogflow-ai') => (string)ini_get('post_max_size'),
    __('WP_DEBUG', 'cbiastudio-blogflow-ai') => (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false',
    __('WP_DEBUG_LOG', 'cbiastudio-blogflow-ai') => (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) ? 'true' : 'false',
    __('DISABLE_WP_CRON', 'cbiastudio-blogflow-ai') => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'true' : 'false',
    __('Timezone', 'cbiastudio-blogflow-ai') => (string)wp_timezone_string(),
    // translators: %s is a masked API key preview.
    __('OpenAI API Key', 'cbiastudio-blogflow-ai') => $api_key !== '' ? sprintf(__('Yes (%s)', 'cbiastudio-blogflow-ai'), $api_masked) : $no,
    __('Plugin directory writable', 'cbiastudio-blogflow-ai') => wp_is_writable(CBIA_PLUGIN_DIR) ? $yes : $no,
    __('WP content writable', 'cbiastudio-blogflow-ai') => defined('WP_CONTENT_DIR') && wp_is_writable(WP_CONTENT_DIR) ? $yes : $no,
);

$log = (string)get_option(CBIA_OPTION_LOG, '');
$log_lines = $log ? array_slice(explode("\n", $log), -20) : array();
?>

<div class="wrap" style="padding-left:0;">
    <h2><?php echo esc_html__('Diagnostics', 'cbiastudio-blogflow-ai'); ?></h2>

    <p class="description">
        <?php echo esc_html__('Quick environment and plugin status summary. Useful for support and troubleshooting.', 'cbiastudio-blogflow-ai'); ?>
    </p>

    <table class="widefat striped" style="max-width:980px;">
        <tbody>
        <?php foreach ($info as $label => $value): ?>
            <tr>
                <td style="width:280px;"><strong><?php echo esc_html($label); ?></strong></td>
                <td><code><?php echo esc_html((string)$value); ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="margin-top:24px;"><?php echo esc_html__('Latest log lines', 'cbiastudio-blogflow-ai'); ?></h3>
    <textarea rows="10" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea(implode("\n", $log_lines)); ?></textarea>
</div>
