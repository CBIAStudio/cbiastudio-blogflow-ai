<?php
/**
 * Contract tests for WP-Cron cleanup during the plugin lifecycle.
 */

define('ABSPATH', __DIR__ . '/');

$cbia_lifecycle_root = dirname(__DIR__);
$cbia_lifecycle_is_pro = file_exists($cbia_lifecycle_root . '/ai-blog-builder-pro.php');
$cbia_lifecycle_cases = 0;
$cbia_lifecycle_cleared_hooks = array();
$cbia_lifecycle_base_active = false;
$cbia_lifecycle_network_active = false;
$cbia_lifecycle_options = array(
    'cbia_settings' => array('enable_cron_fill' => 1, 'tone' => 'professional'),
    'cbia_provider_settings' => array('provider' => 'openai'),
    'cbia_provider_api_keys' => array('openai' => 'fake-openai-key'),
    'cbia_activity_log' => 'fake-log-entry',
    'cbia_pro_usage_event_store_v2' => array('rows' => array(array('id' => 1))),
    'cbia_pro_license' => array('license_key' => 'fake-license-key'),
);
$cbia_lifecycle_original_options = $cbia_lifecycle_options;

$cbia_lifecycle_owned_hooks = array(
    'cbia_pending_fill_event',
    'cbia_generation_event',
    'cbia_oldposts_process_background_run',
);

function cbia_lifecycle_assert($condition, $message)
{
    global $cbia_lifecycle_cases;
    $cbia_lifecycle_cases++;

    if (!$condition) {
        throw new RuntimeException("Case {$cbia_lifecycle_cases} failed: {$message}");
    }
}

function cbia_lifecycle_seed_events()
{
    return array(
        'cbia_pending_fill_event' => array(
            array('timestamp' => 1000, 'schedule' => 'hourly', 'args' => array()),
            array('timestamp' => 5000, 'schedule' => 'hourly', 'args' => array()),
        ),
        'cbia_generation_event' => array(
            array('timestamp' => 1100, 'schedule' => false, 'args' => array()),
            array('timestamp' => 5100, 'schedule' => false, 'args' => array()),
        ),
        'cbia_oldposts_process_background_run' => array(
            array('timestamp' => 1200, 'schedule' => false, 'args' => array()),
            array('timestamp' => 5200, 'schedule' => false, 'args' => array()),
        ),
        'unrelated_plugin_cron_event' => array(
            array('timestamp' => 1300, 'schedule' => 'daily', 'args' => array()),
            array('timestamp' => 5300, 'schedule' => false, 'args' => array('third-party')),
        ),
    );
}

$cbia_lifecycle_events = cbia_lifecycle_seed_events();

function get_option($name, $default = false)
{
    global $cbia_lifecycle_base_active, $cbia_lifecycle_options;

    if ('active_plugins' === $name) {
        return $cbia_lifecycle_base_active
            ? array('cbiastudio-blogflow-ai/cbiastudio-blogflow-ai.php')
            : array();
    }

    return array_key_exists($name, $cbia_lifecycle_options)
        ? $cbia_lifecycle_options[$name]
        : $default;
}

function is_multisite()
{
    global $cbia_lifecycle_network_active;
    return $cbia_lifecycle_network_active;
}

function get_site_option($name, $default = false)
{
    global $cbia_lifecycle_network_active;

    if ('active_sitewide_plugins' === $name && $cbia_lifecycle_network_active) {
        return array('cbiastudio-blogflow-ai/cbiastudio-blogflow-ai.php' => 1000);
    }

    return $default;
}

function wp_clear_scheduled_hook($hook, $args = array())
{
    global $cbia_lifecycle_cleared_hooks, $cbia_lifecycle_events;
    $cbia_lifecycle_cleared_hooks[] = $hook;

    if (!isset($cbia_lifecycle_events[$hook])) {
        return 0;
    }

    $before = count($cbia_lifecycle_events[$hook]);
    $cbia_lifecycle_events[$hook] = array_values(
        array_filter(
            $cbia_lifecycle_events[$hook],
            static function ($event) use ($args) {
                return (array)($event['args'] ?? array()) !== $args;
            }
        )
    );

    if (empty($cbia_lifecycle_events[$hook])) {
        unset($cbia_lifecycle_events[$hook]);
    }

    $after = isset($cbia_lifecycle_events[$hook]) ? count($cbia_lifecycle_events[$hook]) : 0;
    return $before - $after;
}

function wp_next_scheduled($hook, $args = array())
{
    global $cbia_lifecycle_events;

    $timestamps = array();
    foreach ((array)($cbia_lifecycle_events[$hook] ?? array()) as $event) {
        if ((array)($event['args'] ?? array()) === $args) {
            $timestamps[] = (int)$event['timestamp'];
        }
    }

    return empty($timestamps) ? false : min($timestamps);
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = array())
{
    global $cbia_lifecycle_events;
    $cbia_lifecycle_events[$hook][] = array(
        'timestamp' => (int)$timestamp,
        'schedule' => (string)$recurrence,
        'args' => (array)$args,
    );
    return true;
}

function wp_schedule_single_event($timestamp, $hook, $args = array())
{
    global $cbia_lifecycle_events;
    $cbia_lifecycle_events[$hook][] = array(
        'timestamp' => (int)$timestamp,
        'schedule' => false,
        'args' => (array)$args,
    );
    return true;
}

require $cbia_lifecycle_root . '/includes/lifecycle.php';

$cbia_lifecycle_class = $cbia_lifecycle_is_pro ? 'CBIA_Pro_Lifecycle' : 'CBIA_Lifecycle';
cbia_lifecycle_assert(class_exists($cbia_lifecycle_class), 'edition lifecycle helper must be available');
cbia_lifecycle_assert(
    $cbia_lifecycle_owned_hooks === $cbia_lifecycle_class::scheduled_event_hooks(),
    'helper must expose the complete audited hook list in deterministic order'
);

if ($cbia_lifecycle_is_pro) {
    $cbia_lifecycle_base_active = true;
    $cbia_lifecycle_events = cbia_lifecycle_seed_events();
    $cbia_lifecycle_before_shared_cleanup = $cbia_lifecycle_events;
    $cbia_lifecycle_class::clear_scheduled_events();
    cbia_lifecycle_assert(
        $cbia_lifecycle_before_shared_cleanup['cbia_pending_fill_event'] === $cbia_lifecycle_events['cbia_pending_fill_event']
            && $cbia_lifecycle_before_shared_cleanup['cbia_generation_event'] === $cbia_lifecycle_events['cbia_generation_event'],
        'deactivating Pro must preserve base-owned events while Free owns the runtime'
    );
    cbia_lifecycle_assert(!isset($cbia_lifecycle_events['cbia_oldposts_process_background_run']), 'deactivating Pro must clear its premium Old Posts event');
    cbia_lifecycle_assert(isset($cbia_lifecycle_events['unrelated_plugin_cron_event']), 'Pro cleanup must preserve third-party events in add-on mode');
    cbia_lifecycle_assert(
        array('cbia_oldposts_process_background_run') === $cbia_lifecycle_cleared_hooks,
        'Pro must clear only its premium hook while Free remains active'
    );

    $cbia_lifecycle_base_active = false;
    $cbia_lifecycle_network_active = true;
    $cbia_lifecycle_events = cbia_lifecycle_seed_events();
    $cbia_lifecycle_cleared_hooks = array();
    $cbia_lifecycle_before_network_cleanup = $cbia_lifecycle_events;
    $cbia_lifecycle_class::clear_scheduled_events();
    cbia_lifecycle_assert(
        $cbia_lifecycle_before_network_cleanup['cbia_pending_fill_event'] === $cbia_lifecycle_events['cbia_pending_fill_event']
            && $cbia_lifecycle_before_network_cleanup['cbia_generation_event'] === $cbia_lifecycle_events['cbia_generation_event'],
        'deactivating Pro must preserve events owned by network-active Free'
    );
    cbia_lifecycle_assert(!isset($cbia_lifecycle_events['cbia_oldposts_process_background_run']), 'network add-on deactivation must clear the premium Old Posts event');
    cbia_lifecycle_assert(
        array('cbia_oldposts_process_background_run') === $cbia_lifecycle_cleared_hooks,
        'network ownership must restrict Pro cleanup to its premium hook'
    );
}

$cbia_lifecycle_base_active = false;
$cbia_lifecycle_network_active = false;
$cbia_lifecycle_events = cbia_lifecycle_seed_events();
$cbia_lifecycle_cleared_hooks = array();
$cbia_lifecycle_class::clear_scheduled_events();

foreach ($cbia_lifecycle_owned_hooks as $cbia_lifecycle_hook) {
    cbia_lifecycle_assert(false === wp_next_scheduled($cbia_lifecycle_hook), "all instances of {$cbia_lifecycle_hook} must be cleared");
    cbia_lifecycle_assert(!isset($cbia_lifecycle_events[$cbia_lifecycle_hook]), "no {$cbia_lifecycle_hook} instance may remain");
}

cbia_lifecycle_assert(
    $cbia_lifecycle_owned_hooks === $cbia_lifecycle_cleared_hooks,
    'cleanup must call wp_clear_scheduled_hook once for each owned hook only'
);
cbia_lifecycle_assert(isset($cbia_lifecycle_events['unrelated_plugin_cron_event']), 'third-party cron event must remain scheduled');
cbia_lifecycle_assert(1300 === wp_next_scheduled('unrelated_plugin_cron_event'), 'third-party schedule must remain unchanged');
cbia_lifecycle_assert($cbia_lifecycle_original_options === $cbia_lifecycle_options, 'deactivation must preserve every stored option');
cbia_lifecycle_assert(isset($cbia_lifecycle_options['cbia_settings']), 'settings must be preserved on deactivation');
cbia_lifecycle_assert(isset($cbia_lifecycle_options['cbia_provider_api_keys']), 'API keys must be preserved on deactivation');
cbia_lifecycle_assert(isset($cbia_lifecycle_options['cbia_activity_log']), 'logs must be preserved on deactivation');
cbia_lifecycle_assert(isset($cbia_lifecycle_options['cbia_pro_usage_event_store_v2']), 'usage must be preserved on deactivation');
if ($cbia_lifecycle_is_pro) {
    cbia_lifecycle_assert(isset($cbia_lifecycle_options['cbia_pro_license']), 'Pro license must be preserved on deactivation');
}

// Normal runtime code can schedule the hooks again after a later activation.
wp_schedule_event(7000, 'hourly', 'cbia_pending_fill_event');
wp_schedule_single_event(7100, 'cbia_generation_event');
wp_schedule_single_event(7200, 'cbia_oldposts_process_background_run');
foreach ($cbia_lifecycle_owned_hooks as $cbia_lifecycle_hook) {
    cbia_lifecycle_assert(false !== wp_next_scheduled($cbia_lifecycle_hook), "{$cbia_lifecycle_hook} must be schedulable after reactivation");
}

$cbia_lifecycle_entry = $cbia_lifecycle_root . ($cbia_lifecycle_is_pro ? '/ai-blog-builder-pro.php' : '/cbiastudio-blogflow-ai.php');
$cbia_lifecycle_entry_source = file_get_contents($cbia_lifecycle_entry);
$cbia_lifecycle_helper_source = file_get_contents($cbia_lifecycle_root . '/includes/lifecycle.php');
$cbia_lifecycle_uninstall_source = file_get_contents($cbia_lifecycle_root . '/uninstall.php');
$cbia_lifecycle_scheduler_source = file_get_contents($cbia_lifecycle_root . '/includes/jobs/scheduler.php');
$cbia_lifecycle_blog_source = file_get_contents($cbia_lifecycle_root . '/includes/engine/blog.php');
$cbia_lifecycle_hooks_source = file_get_contents($cbia_lifecycle_root . '/includes/core/hooks.php');

$cbia_lifecycle_require_needle = $cbia_lifecycle_is_pro
    ? "require_once __DIR__ . '/includes/lifecycle.php';"
    : "require_once CBIA_BASE_INCLUDES_DIR . 'lifecycle.php';";
$cbia_lifecycle_callback_needle = $cbia_lifecycle_is_pro
    ? "register_deactivation_hook(__FILE__, array('CBIA_Pro_Lifecycle', 'clear_scheduled_events'));"
    : "register_deactivation_hook(__FILE__, array('CBIA_Lifecycle', 'clear_scheduled_events'));";
$cbia_lifecycle_require_position = strpos($cbia_lifecycle_entry_source, $cbia_lifecycle_require_needle);
$cbia_lifecycle_register_position = strpos($cbia_lifecycle_entry_source, $cbia_lifecycle_callback_needle);
$cbia_lifecycle_late_position = strpos(
    $cbia_lifecycle_entry_source,
    $cbia_lifecycle_is_pro ? "if (!abb_pro_is_free_active())" : "add_action('plugins_loaded'"
);

cbia_lifecycle_assert(false !== $cbia_lifecycle_require_position, 'entrypoint must load the lightweight lifecycle helper');
cbia_lifecycle_assert(false !== $cbia_lifecycle_register_position, 'entrypoint must register the exact deactivation callback');
cbia_lifecycle_assert(
    $cbia_lifecycle_require_position < $cbia_lifecycle_register_position
        && $cbia_lifecycle_register_position < $cbia_lifecycle_late_position,
    'deactivation callback must be available and registered before late bootstrap or early return'
);
cbia_lifecycle_assert(1 === substr_count($cbia_lifecycle_entry_source, 'register_deactivation_hook('), 'entrypoint must register one deactivation hook');
cbia_lifecycle_assert(
    false !== strpos($cbia_lifecycle_uninstall_source, $cbia_lifecycle_class . '::clear_scheduled_events();'),
    'uninstall must reuse the same lifecycle helper'
);

foreach ($cbia_lifecycle_owned_hooks as $cbia_lifecycle_hook) {
    cbia_lifecycle_assert(
        1 === substr_count($cbia_lifecycle_helper_source, "'{$cbia_lifecycle_hook}'"),
        "helper must list {$cbia_lifecycle_hook} exactly once"
    );
    cbia_lifecycle_assert(
        false === strpos($cbia_lifecycle_uninstall_source, "'{$cbia_lifecycle_hook}'"),
        "uninstall must not duplicate the {$cbia_lifecycle_hook} list"
    );
}

cbia_lifecycle_assert(
    false !== strpos($cbia_lifecycle_scheduler_source, "wp_schedule_event(time() + 300, 'hourly', 'cbia_pending_fill_event')"),
    'pending-fill recurring scheduling must remain available'
);
cbia_lifecycle_assert(
    false !== strpos($cbia_lifecycle_blog_source, 'wp_schedule_single_event(time() + $delay_seconds, \'cbia_generation_event\')'),
    'generation single-event scheduling must remain available'
);
cbia_lifecycle_assert(
    false !== strpos($cbia_lifecycle_hooks_source, 'wp_schedule_single_event(time() + $delay, \'cbia_oldposts_process_background_run\')'),
    'Old Posts background scheduling must remain available'
);

foreach (array(
    'delete_option(',
    'update_option(',
    'add_option(',
    'wp_remote_',
    'DELETE FROM',
    '_get_cron_array(',
    '_set_cron_array(',
    "update_option('cron'",
    'Lemon',
    'Webgoh',
) as $cbia_lifecycle_forbidden) {
    cbia_lifecycle_assert(
        false === stripos($cbia_lifecycle_helper_source, $cbia_lifecycle_forbidden),
        "lifecycle helper must not contain {$cbia_lifecycle_forbidden}"
    );
}

echo 'plugin-lifecycle-cron-cleanup: ' . $cbia_lifecycle_cases . '/' . $cbia_lifecycle_cases . ' OK' . PHP_EOL;
