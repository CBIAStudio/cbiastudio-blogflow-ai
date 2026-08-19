<?php
/**
 * Security regression test for uninstall data cleanup.
 *
 * Run directly with PHP; WordPress is replaced by the narrow stubs below.
 */

define('WP_UNINSTALL_PLUGIN', true);

$cbia_test_root = dirname(__DIR__);
$cbia_test_is_pro = file_exists($cbia_test_root . '/ai-blog-builder-pro.php');
$cbia_test_cases = 0;
$cbia_test_http_calls = 0;
$cbia_test_deleted_metadata = array();

function cbia_uninstall_assert($condition, $message)
{
    global $cbia_test_cases;
    $cbia_test_cases++;

    if (!$condition) {
        throw new RuntimeException("Case {$cbia_test_cases} failed: {$message}");
    }
}

$cbia_test_common_options = array(
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
    'cbia_provider_api_keys',
    'cbia_provider_settings',
    'cbia_provider_connection_status',
    'cbia_provider_credentials_schema_version',
    'cbia_provider_model_lists',
    'cbia_provider_model_sync_meta',
    'cbia_deepseek_migration_version',
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

$cbia_test_options = array_fill_keys($cbia_test_common_options, 'plugin-data');
$cbia_test_options['cbia_provider_api_keys'] = array(
    'openai' => 'fake-openai-secret',
    'google' => 'fake-google-secret',
    'deepseek' => 'fake-deepseek-secret',
);
$cbia_test_options['cbia_pro_license'] = array(
    'license_key' => 'fake-license-secret',
    'instance_id' => 'fake-instance-id',
);
$cbia_test_options['cbia_usage_recalc_backup_20260819_120000'] = 'plugin-backup';

$cbia_test_preserved_options = array(
    'unrelated_plugin_setting' => 'preserve',
    'third_party_cbia_setting' => 'preserve',
    'cbia_provider_api_keys_backup_external' => 'preserve',
    'archive_cbia_usage_recalc_backup_20260819_120000' => 'preserve',
);
$cbia_test_options = array_merge($cbia_test_options, $cbia_test_preserved_options);

$cbia_test_exact_transients = array(
    'cbia_config_errors',
    'cbia_config_warnings',
    'cbia_blog_prompt_warnings',
    'cbia_usage_cost_recalc_lock',
    'cbia_pending_fill_lock',
);
$cbia_test_usage_transients = array();

foreach (array('v5', 'v7', 'v8', 'v9', 'v11') as $cbia_test_cache_version) {
    foreach (array(7, 30, 90, 730) as $cbia_test_cache_days) {
        $cbia_test_usage_transients[] = sprintf(
            'cbia_pro_usage_overview_%s_7_%d',
            $cbia_test_cache_version,
            $cbia_test_cache_days
        );
    }
}

$cbia_test_dynamic_transients = array(
    'cbia_preview_payload_token-1',
    'cbia_preview_create_lock_post-2',
    'cbia_yoast_sync_log_post-3',
    'cbia_terms_sync_log_post-4',
    'cbia_apply_full_post_log_post-5',
);
$cbia_test_preserved_transients = array(
    'unrelated_transient',
    'cbia_preview_payload',
    'archive_cbia_preview_payload_token-1',
);
$cbia_test_transients = array_fill_keys(
    array_merge(
        $cbia_test_exact_transients,
        $cbia_test_usage_transients,
        $cbia_test_dynamic_transients,
        $cbia_test_preserved_transients
    ),
    'transient-data'
);

foreach (array_keys($cbia_test_transients) as $cbia_test_transient_name) {
    $cbia_test_options['_transient_' . $cbia_test_transient_name] = 'transient-data';
    $cbia_test_options['_transient_timeout_' . $cbia_test_transient_name] = 2000000000;
}

$cbia_test_user_meta = array(
    '_cbia_preview_temp_media_ids' => array(11, 12),
    'unrelated_user_preference' => 'preserve',
);

class Cbia_Uninstall_Test_Wpdb
{
    public $options = 'wp_options';
    public $escaped_prefixes = array();
    public $queried_prefixes = array();
    private $pending_prefix = '';

    public function esc_like($value)
    {
        $this->escaped_prefixes[] = $value;
        return $value;
    }

    public function prepare($query, $value)
    {
        cbia_uninstall_assert(
            false !== strpos($query, 'SELECT option_name FROM wp_options WHERE option_name LIKE %s'),
            'dynamic cleanup must use a prepared, read-only option-name lookup'
        );
        cbia_uninstall_assert('%' === substr($value, -1), 'dynamic lookup must use a suffix wildcard only');

        $this->pending_prefix = substr($value, 0, -1);
        $this->queried_prefixes[] = $this->pending_prefix;
        return $query;
    }

    public function get_col($query)
    {
        global $cbia_test_options;

        $prefix = $this->pending_prefix;
        return array_values(
            array_filter(
                array_keys($cbia_test_options),
                static function ($option_name) use ($prefix) {
                    return 0 === strncmp($option_name, $prefix, strlen($prefix));
                }
            )
        );
    }
}

$wpdb = new Cbia_Uninstall_Test_Wpdb();

function delete_option($name)
{
    global $cbia_test_options;
    unset($cbia_test_options[$name]);
    return true;
}

function delete_transient($name)
{
    global $cbia_test_options, $cbia_test_transients;
    unset($cbia_test_transients[$name]);
    unset($cbia_test_options['_transient_' . $name]);
    unset($cbia_test_options['_transient_timeout_' . $name]);
    return true;
}

function get_current_blog_id()
{
    return 7;
}

function delete_metadata($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false)
{
    global $cbia_test_deleted_metadata, $cbia_test_user_meta;
    $cbia_test_deleted_metadata[] = func_get_args();

    if ('user' === $meta_type && 0 === $object_id && true === $delete_all) {
        unset($cbia_test_user_meta[$meta_key]);
    }

    return true;
}

function wp_remote_get()
{
    global $cbia_test_http_calls;
    $cbia_test_http_calls++;
}

function wp_remote_post()
{
    global $cbia_test_http_calls;
    $cbia_test_http_calls++;
}

function wp_remote_request()
{
    global $cbia_test_http_calls;
    $cbia_test_http_calls++;
}

require $cbia_test_root . '/uninstall.php';

foreach ($cbia_test_common_options as $cbia_test_option) {
    cbia_uninstall_assert(!array_key_exists($cbia_test_option, $cbia_test_options), "option {$cbia_test_option} must be deleted");
}

cbia_uninstall_assert(
    !array_key_exists('cbia_usage_recalc_backup_20260819_120000', $cbia_test_options),
    'timestamped usage migration backups must be deleted'
);

if ($cbia_test_is_pro) {
    cbia_uninstall_assert(!array_key_exists('cbia_pro_license', $cbia_test_options), 'Pro license credentials must be deleted');
} else {
    cbia_uninstall_assert(array_key_exists('cbia_pro_license', $cbia_test_options), 'Free must not delete Pro-only license data');
}

foreach ($cbia_test_preserved_options as $cbia_test_option => $cbia_test_value) {
    cbia_uninstall_assert(
        isset($cbia_test_options[$cbia_test_option]) && $cbia_test_value === $cbia_test_options[$cbia_test_option],
        "unrelated option {$cbia_test_option} must be preserved"
    );
}

foreach (array_merge($cbia_test_exact_transients, $cbia_test_usage_transients, $cbia_test_dynamic_transients) as $cbia_test_transient) {
    cbia_uninstall_assert(!array_key_exists($cbia_test_transient, $cbia_test_transients), "transient {$cbia_test_transient} must be deleted");
}

foreach ($cbia_test_preserved_transients as $cbia_test_transient) {
    cbia_uninstall_assert(array_key_exists($cbia_test_transient, $cbia_test_transients), "unrelated transient {$cbia_test_transient} must be preserved");
}

cbia_uninstall_assert(!array_key_exists('_cbia_preview_temp_media_ids', $cbia_test_user_meta), 'temporary user bookkeeping meta must be deleted');
cbia_uninstall_assert(isset($cbia_test_user_meta['unrelated_user_preference']), 'unrelated user meta must be preserved');
cbia_uninstall_assert(
    array(array('user', 0, '_cbia_preview_temp_media_ids', '', true)) === $cbia_test_deleted_metadata,
    'only the exact internal user-meta key must be deleted across users'
);
cbia_uninstall_assert(0 === $cbia_test_http_calls, 'uninstall must not make remote requests');

$cbia_test_allowed_prefixes = array('cbia_usage_recalc_backup_');
foreach (array(
    'cbia_preview_payload_',
    'cbia_preview_create_lock_',
    'cbia_yoast_sync_log_',
    'cbia_terms_sync_log_',
    'cbia_apply_full_post_log_',
) as $cbia_test_prefix) {
    $cbia_test_allowed_prefixes[] = '_transient_' . $cbia_test_prefix;
    $cbia_test_allowed_prefixes[] = '_transient_timeout_' . $cbia_test_prefix;
}

cbia_uninstall_assert($cbia_test_allowed_prefixes === $wpdb->escaped_prefixes, 'only audited dynamic prefixes may be escaped');
cbia_uninstall_assert($cbia_test_allowed_prefixes === $wpdb->queried_prefixes, 'only audited dynamic prefixes may be queried');

$cbia_test_source = file_get_contents($cbia_test_root . '/uninstall.php');
$cbia_test_guard_position = strpos($cbia_test_source, "defined('WP_UNINSTALL_PLUGIN')");
$cbia_test_delete_position = strpos($cbia_test_source, 'delete_option(');
cbia_uninstall_assert(false !== $cbia_test_guard_position && $cbia_test_guard_position < $cbia_test_delete_position, 'direct-access guard must precede deletion');
cbia_uninstall_assert(false !== strpos($cbia_test_source, "'cbia_provider_api_keys'"), 'credential vault must be explicitly covered');
cbia_uninstall_assert($cbia_test_is_pro === (false !== strpos($cbia_test_source, "'cbia_pro_license'")), 'license cleanup must exist only in Pro');
cbia_uninstall_assert(false === stripos($cbia_test_source, 'DELETE FROM'), 'uninstall must not issue direct DELETE SQL');
cbia_uninstall_assert(false === stripos($cbia_test_source, 'DROP TABLE'), 'uninstall must not drop tables');

foreach (array(
    'delete_post(',
    'wp_delete_post(',
    'delete_user(',
    'wp_delete_user(',
    'delete_term(',
    'wp_delete_term(',
    'delete_post_meta(',
    'delete_site_option(',
    'get_sites(',
    'switch_to_blog(',
    'wp_clear_scheduled_hook(',
    'wp_remote_',
    'lemon',
    'webgoh',
) as $cbia_test_forbidden) {
    cbia_uninstall_assert(false === stripos($cbia_test_source, $cbia_test_forbidden), "forbidden uninstall operation {$cbia_test_forbidden}");
}

$cbia_test_main_file = $cbia_test_root . ($cbia_test_is_pro ? '/ai-blog-builder-pro.php' : '/cbiastudio-blogflow-ai.php');
$cbia_test_main_source = file_get_contents($cbia_test_main_file);
cbia_uninstall_assert(false === strpos($cbia_test_main_source, 'register_deactivation_hook'), 'FIX 2.4 must not add deactivation cleanup');

echo 'uninstall-data-cleanup-security: ' . $cbia_test_cases . '/' . $cbia_test_cases . ' OK' . PHP_EOL;
