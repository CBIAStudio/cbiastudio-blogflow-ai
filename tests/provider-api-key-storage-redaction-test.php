<?php
define('ABSPATH', __DIR__ . '/');
define('CBIA_OPTION_SETTINGS', 'cbia_settings');

$GLOBALS['test_options'] = array();
$GLOBALS['test_autoload'] = array();
function apply_filters($tag, $value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['test_options'][$key] = $value; $GLOBALS['test_autoload'][$key] = $autoload; return true; }
function current_time($type) { return '2026-08-03 12:00:00'; }
function __($text, $domain = '') { return $text; }
function cbia_get_settings() { return (array)get_option('cbia_settings', array()); }

require dirname(__DIR__) . '/includes/integrations/providers.php';
require dirname(__DIR__) . '/includes/engine/base.php';

$count = 0;
function check_case($condition, $message) {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$message}");
}
function source_has($source, $needle) { return strpos($source, $needle) !== false; }

$root = dirname(__DIR__);
$keys = array(
    'openai' => 'sk-openai-valid-project-key-1234567890',
    'deepseek' => 'deepseek-valid-provider-key-1234567890',
    'google' => 'google-valid-provider-key-1234567890',
	'anthropic' => 'anthropic-valid-provider-key-1234567890',
);

check_case(cbia_supported_credential_providers() === array('openai', 'google', 'deepseek', 'anthropic'), 'only implemented credential providers are exposed');
foreach ($keys as $provider => $key) check_case(cbia_sanitize_provider_api_key($provider, $key)['valid'], "valid {$provider} key");
check_case(cbia_sanitize_provider_api_key('openai', "  {$keys['openai']}  ")['value'] === $keys['openai'], 'boundary spaces removed');
check_case(cbia_sanitize_provider_api_key('openai', "\r\n{$keys['openai']}\r\n")['value'] === $keys['openai'], 'boundary CR/LF removed');
foreach (array('', '   ', '************', 'key***masked', "openai\tkey", "openai\rkey", "openai\nkey", "openai\0key", 'openai key', 'short') as $invalid) {
    check_case(!cbia_sanitize_provider_api_key('openai', $invalid)['valid'], 'invalid or masked value rejected');
}
check_case(!cbia_sanitize_provider_api_key('openai', array())['valid'], 'non-string rejected');
check_case(!cbia_store_provider_api_key('freepik', 'unsupported-provider-key')['valid'], 'unsupported provider rejected');

foreach ($keys as $provider => $key) check_case(cbia_save_provider_api_key($provider, $key)['valid'], "save {$provider}");
$vault = get_option('cbia_provider_api_keys', array());
check_case($vault === $keys, 'all provider keys coexist in canonical vault');
check_case($GLOBALS['test_autoload']['cbia_provider_api_keys'] === false, 'canonical vault uses autoload false');
check_case(empty(get_option('cbia_settings', array())), 'new saves do not duplicate credentials into general settings');
check_case(empty(get_option('cbia_provider_settings', array())['providers']['openai']['api_key'] ?? ''), 'new saves do not duplicate credentials into provider settings');

cbia_mark_provider_test_result('openai', array('status' => 'verified'));
$verified = cbia_get_provider_connection_status('openai');
check_case($verified['verified'] && $verified['last_success'] === '2026-08-03 12:00:00', 'verified state stores last successful check');
$replacement = 'sk-openai-replacement-project-key-1234567890';
cbia_save_provider_api_key('openai', $replacement);
$status = cbia_get_provider_connection_status('openai');
check_case($status['status'] === 'not_tested' && $status['last_success'] === '', 'new key clears verification inherited from old key');
check_case(cbia_get_provider_api_key('deepseek') === $keys['deepseek'], 'updating OpenAI preserves DeepSeek');
check_case(cbia_get_provider_api_key('google') === $keys['google'], 'updating OpenAI preserves Google');
cbia_save_provider_api_key('openai', "bad\tkey");
check_case(cbia_get_provider_api_key('openai') === $replacement, 'invalid replacement preserves previous key');

$GLOBALS['test_options']['cbia_provider_api_keys'] = array();
$GLOBALS['test_options']['cbia_provider_credentials_schema_version'] = 0;
$GLOBALS['test_options']['cbia_settings'] = array(
    'openai_api_key' => $keys['openai'],
    'deepseek_api_key' => $keys['deepseek'],
    'api_key' => 'sk-generic-openai-key-1234567890',
    'text_provider' => 'deepseek',
    'image_provider' => 'openai',
    'text_model' => 'deepseek-v4-flash',
);
$GLOBALS['test_options']['cbia_provider_settings'] = array('providers' => array('google' => array('api_key' => $keys['google'])));
cbia_maybe_migrate_provider_credentials();
$migrated = get_option('cbia_provider_api_keys', array());
check_case(count($migrated) === 3 && $migrated['openai'] === $keys['openai'] && $migrated['deepseek'] === $keys['deepseek'] && $migrated['google'] === $keys['google'], 'migration uses dedicated and unambiguous legacy credentials');
check_case(get_option('cbia_provider_credentials_schema_version') === 1, 'credential schema version recorded');
check_case($GLOBALS['test_autoload']['cbia_provider_credentials_schema_version'] === false, 'schema version uses autoload false');
$GLOBALS['test_options']['cbia_settings']['openai_api_key'] = 'sk-different-legacy-key-1234567890';
cbia_maybe_migrate_provider_credentials();
check_case(get_option('cbia_provider_api_keys')['openai'] === $keys['openai'], 'migration is idempotent');
check_case(get_option('cbia_settings')['openai_api_key'] === 'sk-different-legacy-key-1234567890', 'migration preserves legacy source for compatibility');

$GLOBALS['test_options']['cbia_provider_api_keys'] = array('openai' => $replacement, 'deepseek' => $keys['deepseek'], 'google' => $keys['google']);
$GLOBALS['test_options']['cbia_provider_connection_status'] = array('openai' => array('status' => 'verified'), 'deepseek' => array('status' => 'verified'));
$GLOBALS['test_options']['cbia_settings']['openai_api_key'] = $keys['openai'];
$GLOBALS['test_options']['cbia_settings']['deepseek_api_key'] = $keys['deepseek'];
$GLOBALS['test_options']['cbia_provider_settings']['providers']['openai']['api_key'] = $keys['openai'];
check_case(cbia_delete_provider_api_key('openai'), 'explicit disconnect succeeds');
check_case(cbia_get_provider_api_key('openai') === '', 'disconnect removes canonical and legacy OpenAI copies');
check_case(cbia_get_provider_api_key('deepseek') === $keys['deepseek'], 'disconnect preserves another provider');
check_case(isset(get_option('cbia_provider_connection_status')['deepseek']), 'disconnect preserves another provider status');
check_case(!isset(get_option('cbia_provider_connection_status')['openai']), 'disconnect removes only target status');
check_case(!cbia_delete_provider_api_key('freepik'), 'unsupported disconnect rejected');

$view = file_get_contents($root . '/includes/admin/views/config.php');
$hooks = file_get_contents($root . '/includes/core/hooks.php');
$js = file_get_contents($root . '/assets/js/admin.js');
$provider_js = substr($js, strpos($js, 'function initProviderConnections'));
$css = file_get_contents($root . '/assets/css/admin.css');
$config = file_get_contents($root . '/includes/admin/config.php');
$main_path = is_file($root . '/cbiastudio-blogflow-ai.php') ? $root . '/cbiastudio-blogflow-ai.php' : $root . '/ai-blog-builder-pro.php';
$main = file_get_contents($main_path);
$runtime = substr($hooks, strpos($hooks, 'function cbia_ajax_start_generation()'), strpos($hooks, "if (!function_exists('cbia_ajax_get_oldposts_log'))") - strpos($hooks, 'function cbia_ajax_start_generation()'));

foreach (array('openai', 'google', 'deepseek', 'anthropic') as $provider) check_case(source_has($view, "'{$provider}'"), "{$provider} connection card source exists");
check_case(!source_has($view, "'freepik' =>"), 'unsupported Freepik card is absent');
check_case(source_has($view, 'cbia-provider-credential-input') && source_has($view, 'type="password" value="" autocomplete="new-password"'), 'credential input is empty password field');
check_case(!source_has($view, 'name="provider_api_key_text[') && !source_has($view, 'name="provider_api_key_image['), 'legacy credential fields are removed from general form');
check_case(!source_has($view, 'cbia_config_save_api_keys'), 'legacy save API keys button removed');
check_case(source_has($view, 'aria-live="polite"'), 'AJAX result is announced accessibly');
check_case(source_has($view, 'data-disconnect-confirm='), 'disconnect requires explicit confirmation');
check_case(source_has($view, 'data-action="open-editor"') && source_has($view, 'data-action="save-key"') && source_has($view, 'data-action="test"') && source_has($view, 'data-action="disconnect"'), 'connection actions render');
check_case(source_has($view, 'In use for text') && source_has($view, 'In use for images'), 'usage badges derive from provider selections');
check_case(source_has($js, "request(card, 'cbia_provider_connection_save', input ? input.value : '')") && source_has($js, "params.append('api_key', apiKey)"), 'save AJAX sends only provider credential payload');
check_case(source_has($js, "window.confirm(card.getAttribute('data-disconnect-confirm')"), 'disconnect confirmation wired');
check_case(source_has($js, "input.value = ''"), 'browser credential field is cleared after request');
check_case(!source_has($provider_js, 'localStorage') && !source_has($provider_js, 'sessionStorage'), 'credentials are not cached in browser storage');
check_case(source_has($css, '@media (max-width: 960px)') && source_has($css, 'grid-template-columns: 1fr'), 'cards have mobile layout');
check_case(source_has($css, ':focus-visible'), 'cards retain keyboard focus indicator');
check_case(source_has($hooks, "check_ajax_referer('cbia_ajax_nonce')"), 'connection endpoints require nonce');
check_case(source_has($hooks, "current_user_can('manage_options')"), 'connection endpoints require manage_options');
check_case(source_has($hooks, 'cbia_supported_credential_providers()'), 'connection endpoints enforce provider whitelist');
check_case(source_has($hooks, 'cbia_provider_connection_public_state($provider)'), 'AJAX returns public state only');
check_case(!source_has($hooks, "'api_key' => cbia_get_provider_api_key"), 'AJAX response does not include credential');
check_case(source_has($hooks, 'cbia_sanitize_provider_error'), 'network errors are sanitized');
check_case(!source_has($runtime, 'openai_api_key') && !source_has($runtime, 'deepseek_api_key') && !source_has($runtime, 'google_api_key'), 'Blog runtime sync contains no credentials');
check_case(!source_has($config, 'provider_api_key_text_post') && !source_has($config, 'provider_api_key_image_post'), 'general settings handler ignores credential fields');
check_case(!source_has($main, "'openai_api_key' => ''") && !source_has($main, "'deepseek_api_key' => ''") && !source_has($main, "'google_api_key' => ''"), 'general defaults contain no credentials');
check_case(source_has(file_get_contents($root . '/includes/engine/openai.php'), "cbia_get_provider_api_key('openai')"), 'OpenAI images use central OpenAI credential');
check_case(source_has(file_get_contents($root . '/includes/admin/views/oldposts.php'), 'cbia_has_provider_api_key'), 'Oldposts UI uses central credential status');

$secret = 'sk-test-secret-fragment-abcdef123456';
$safe = cbia_mask_sensitive_log_text('Incorrect API key provided: ' . $secret . '. Authorization: Bearer ' . $secret);
check_case(strpos($safe, $secret) === false && strpos($safe, 'secret-fragment') === false, 'logs redact credentials and fragments');
check_case(strpos(cbia_sanitize_provider_error('openai', 'Incorrect API key provided: ' . $secret, 401), $secret) === false, '401 response is sanitized');

echo "provider-api-key-storage-redaction: {$count}/{$count} OK\n";
