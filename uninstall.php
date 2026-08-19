<?php
/**
 * Uninstall cleanup for CBIAStudio BlogFlow AI.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/lifecycle.php';
CBIA_Lifecycle::clear_scheduled_events();

// Per-site options owned exclusively by the plugin.
$cbia_options = array(
    // Core configuration, state, and legacy data.
    'cbia_settings',
    'cbia_activity_log',
    'cbia_log_counter',
    'cbia_stop_generation',
    'cbia_checkpoint',
    'cbia_legacy_usage',
    'cbia_costes_settings',
    'cbia_costes_log',
    'cbia_costes_log_counter',
    'cbia_oldposts_settings',
    'cbia_oldposts_log',
    'cbia_yoast_settings',
    'cbia_yoast_log',

    // Provider credentials, configuration, caches, and migrations.
    'cbia_provider_api_keys',
    'cbia_provider_settings',
    'cbia_provider_connection_status',
    'cbia_provider_credentials_schema_version',
    'cbia_provider_model_lists',
    'cbia_provider_model_sync_meta',
    'cbia_deepseek_migration_version',

    // Usage, cost, and internal job state.
    'cbia_pro_usage_event_store_v2',
    'cbia_usage_orphan_rows',
    'cbia_usage_cost_recalc_last',
    'cbia_usage_cost_recalc_version',
    'cbia_image_batch_auth_guard',
    '_cbia_last_scheduled_at',
    'cbia_blog_generation_lock',
    'cbia_oldposts_active_run_state',
    'cbia_oldposts_background_lock',
);

foreach ($cbia_options as $cbia_option) {
    delete_option($cbia_option);
}

// Deterministic per-site transients.
$cbia_transients = array(
    'cbia_config_errors',
    'cbia_config_warnings',
    'cbia_blog_prompt_warnings',
    'cbia_usage_cost_recalc_lock',
    'cbia_pending_fill_lock',
);

foreach ($cbia_transients as $cbia_transient) {
    delete_transient($cbia_transient);
}

// Known usage dashboard cache variants and supported ranges.
$cbia_blog_id = function_exists('get_current_blog_id') ? (int)get_current_blog_id() : 1;
foreach (array('v5', 'v7', 'v8', 'v9', 'v11') as $cbia_cache_version) {
    foreach (array(7, 30, 90, 730) as $cbia_cache_days) {
        delete_transient('cbia_pro_usage_overview_' . $cbia_cache_version . '_' . $cbia_blog_id . '_' . $cbia_cache_days);
    }
}

// Discover only explicitly known dynamic families, then delete exact names via WordPress APIs.
$cbia_wpdb = isset($GLOBALS['wpdb']) ? $GLOBALS['wpdb'] : null;
$cbia_find_option_names_by_prefix = static function ($prefixes) use ($cbia_wpdb) {
    if (!is_object($cbia_wpdb)
        || !isset($cbia_wpdb->options)
        || !method_exists($cbia_wpdb, 'esc_like')
        || !method_exists($cbia_wpdb, 'prepare')
        || !method_exists($cbia_wpdb, 'get_col')) {
        return array();
    }

    $names = array();
    foreach ((array)$prefixes as $prefix) {
        $prefix = (string)$prefix;
        if ($prefix === '') {
            continue;
        }
        $query = $cbia_wpdb->prepare(
            "SELECT option_name FROM {$cbia_wpdb->options} WHERE option_name LIKE %s",
            $cbia_wpdb->esc_like($prefix) . '%'
        );
        foreach ((array)$cbia_wpdb->get_col($query) as $name) {
            $name = (string)$name;
            if (strncmp($name, $prefix, strlen($prefix)) === 0) {
                $names[] = $name;
            }
        }
    }
    return array_values(array_unique($names));
};

foreach ($cbia_find_option_names_by_prefix(array('cbia_usage_recalc_backup_')) as $cbia_dynamic_option) {
    delete_option($cbia_dynamic_option);
}

$cbia_dynamic_transient_prefixes = array(
    'cbia_preview_payload_',
    'cbia_preview_create_lock_',
    'cbia_yoast_sync_log_',
    'cbia_terms_sync_log_',
    'cbia_apply_full_post_log_',
);
$cbia_transient_storage_prefixes = array();
foreach ($cbia_dynamic_transient_prefixes as $cbia_dynamic_transient_prefix) {
    $cbia_transient_storage_prefixes[] = '_transient_' . $cbia_dynamic_transient_prefix;
    $cbia_transient_storage_prefixes[] = '_transient_timeout_' . $cbia_dynamic_transient_prefix;
}

$cbia_dynamic_transients = array();
foreach ($cbia_find_option_names_by_prefix($cbia_transient_storage_prefixes) as $cbia_transient_option) {
    if (strncmp($cbia_transient_option, '_transient_timeout_', 19) === 0) {
        $cbia_dynamic_transients[] = substr($cbia_transient_option, 19);
    } elseif (strncmp($cbia_transient_option, '_transient_', 11) === 0) {
        $cbia_dynamic_transients[] = substr($cbia_transient_option, 11);
    }
}

foreach (array_unique($cbia_dynamic_transients) as $cbia_dynamic_transient) {
    delete_transient($cbia_dynamic_transient);
    delete_option('_transient_' . $cbia_dynamic_transient);
    delete_option('_transient_timeout_' . $cbia_dynamic_transient);
}

// Temporary preview-media ownership is internal and does not affect published content.
if (function_exists('delete_metadata')) {
    delete_metadata('user', 0, '_cbia_preview_temp_media_ids', '', true);
}
