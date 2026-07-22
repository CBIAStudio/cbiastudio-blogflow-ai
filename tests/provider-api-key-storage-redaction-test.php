<?php
define('ABSPATH', __DIR__ . '/');
define('CBIA_OPTION_SETTINGS', 'cbia_settings');

$GLOBALS['test_options'] = array();
function apply_filters($tag, $value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['test_options'][$key] = $value; return true; }
function __($text, $domain = '') { return $text; }
function cbia_get_settings() { return array_replace(array('openai_api_key'=>'','deepseek_api_key'=>'','google_api_key'=>''), (array)get_option('cbia_settings', array())); }

require dirname(__DIR__) . '/includes/integrations/providers.php';
require dirname(__DIR__) . '/includes/engine/base.php';

$count = 0;
function check_case($condition, $message) {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$message}");
}
function has_source($file, $needle) { return strpos(file_get_contents($file), $needle) !== false; }

$root = dirname(__DIR__);
$valid_openai = 'openai-valid-project-key';
$valid_deepseek = 'deepseek-valid-provider-key';
$valid_google = 'google-valid-provider-key';

$r = cbia_sanitize_provider_api_key('openai', $valid_openai);
check_case($r['valid'] && $r['value'] === $valid_openai, 'valid OpenAI key is unchanged');
check_case(cbia_sanitize_provider_api_key('deepseek', $valid_deepseek)['valid'], 'valid DeepSeek key');
check_case(cbia_sanitize_provider_api_key('google', $valid_google)['valid'], 'valid Google key');
check_case(cbia_sanitize_provider_api_key('openai', "  {$valid_openai}  ")['value'] === $valid_openai, 'boundary spaces removed');
check_case(cbia_sanitize_provider_api_key('openai', "\r\n{$valid_openai}\r\n")['value'] === $valid_openai, 'boundary CR/LF removed');
check_case(!cbia_sanitize_provider_api_key('openai', '')['valid'], 'empty rejected');
check_case(!cbia_sanitize_provider_api_key('openai', '   ')['valid'], 'spaces rejected');
check_case(!cbia_sanitize_provider_api_key('openai', '************')['valid'], 'asterisk mask rejected');
check_case(!cbia_sanitize_provider_api_key('openai', 'key***masked')['valid'], 'embedded asterisks rejected');
check_case(!cbia_sanitize_provider_api_key('openai', "{$valid_openai}\t")['valid'], 'trailing tab rejected');
check_case(!cbia_sanitize_provider_api_key('openai', "openai\tkey")['valid'], 'internal tab rejected');
check_case(!cbia_sanitize_provider_api_key('openai', "openai\rkey")['valid'], 'internal CR rejected');
check_case(!cbia_sanitize_provider_api_key('openai', "openai\nkey")['valid'], 'internal LF rejected');
check_case(!cbia_sanitize_provider_api_key('openai', "openai\0key")['valid'], 'NUL rejected');
check_case(!cbia_sanitize_provider_api_key('openai', 'openai key')['valid'], 'internal space rejected');
check_case(!cbia_sanitize_provider_api_key('openai', array())['valid'], 'non-string rejected');

$GLOBALS['test_options']['cbia_settings'] = array('openai_api_key'=>$valid_openai,'deepseek_api_key'=>$valid_deepseek,'google_api_key'=>$valid_google,'text_provider'=>'deepseek','image_provider'=>'openai');
$GLOBALS['test_options']['cbia_provider_settings'] = array('providers'=>array(
    'openai'=>array('api_key'=>$valid_openai,'image_model'=>'gpt-image-1-mini'),
    'deepseek'=>array('api_key'=>$valid_deepseek,'model'=>'deepseek-v4-flash'),
    'google'=>array('api_key'=>$valid_google),
));
check_case(cbia_get_provider_api_key('openai') === $valid_openai, 'OpenAI resolves independently');
check_case(cbia_get_provider_api_key('deepseek') === $valid_deepseek, 'DeepSeek resolves independently');
check_case(cbia_get_provider_api_key('google') === $valid_google, 'Google resolves independently');
check_case(cbia_has_provider_api_key('openai'), 'OpenAI configured boolean');
check_case(cbia_has_provider_api_key('deepseek'), 'DeepSeek configured boolean');
check_case(cbia_has_provider_api_key('google'), 'Google configured boolean');
check_case(cbia_get_provider_api_key('freepik') === '', 'unsupported Freepik is not invented');

check_case(cbia_store_provider_api_key('openai', $valid_openai)['valid'], 'OpenAI saves in canonical store');
check_case(cbia_store_provider_api_key('deepseek', $valid_deepseek)['valid'], 'DeepSeek saves in canonical store');
check_case(cbia_store_provider_api_key('google', $valid_google)['valid'], 'Google saves in canonical store');
$vault = get_option('cbia_provider_api_keys', array());
check_case($vault['openai'] === $valid_openai && $vault['deepseek'] === $valid_deepseek && $vault['google'] === $valid_google, 'canonical store keeps providers isolated');
$before_invalid = $vault['openai'];
cbia_store_provider_api_key('openai', "bad\tkey");
check_case(get_option('cbia_provider_api_keys')['openai'] === $before_invalid, 'invalid candidate cannot replace canonical key');
$GLOBALS['test_options']['cbia_settings']['openai_api_key'] = '';
$GLOBALS['test_options']['cbia_provider_settings']['providers']['openai']['api_key'] = '';
$GLOBALS['test_options']['cbia_settings']['image_model'] = 'gpt-image-2';
check_case(cbia_get_provider_api_key('openai') === $valid_openai, 'model change cannot remove canonical OpenAI key');
cbia_store_provider_api_key('openai', $valid_openai);

cbia_providers_save_settings(array('providers'=>array('openai'=>array('api_key'=>'************','image_model'=>'gpt-image-2'))));
$stored = get_option('cbia_provider_settings', array());
check_case($stored['providers']['openai']['api_key'] === $valid_openai, 'mask preserves old OpenAI key');
check_case($stored['providers']['deepseek']['api_key'] === $valid_deepseek, 'OpenAI save preserves DeepSeek');
check_case($stored['providers']['google']['api_key'] === $valid_google, 'OpenAI save preserves Google');
cbia_providers_save_settings(array('providers'=>array('deepseek'=>array('api_key'=>'','model'=>'deepseek-v4-pro'))));
$stored = get_option('cbia_provider_settings', array());
check_case($stored['providers']['deepseek']['api_key'] === $valid_deepseek, 'empty field preserves DeepSeek');
check_case($stored['providers']['deepseek']['model'] === 'deepseek-v4-pro', 'model can change independently');
check_case(get_option('cbia_provider_api_keys')['deepseek'] === $valid_deepseek, 'DeepSeek model change preserves canonical key');
cbia_providers_save_settings(array('providers'=>array('openai'=>array('api_key'=>"bad\tkey"))));
check_case(get_option('cbia_provider_settings')['providers']['openai']['api_key'] === $valid_openai, 'control candidate preserves OpenAI');
check_case(cbia_get_provider_api_key('openai') !== cbia_get_provider_api_key('deepseek'), 'provider keys never cross');

$secret = 'sk-test-secret-fragment-abcdef';
$safe = cbia_mask_sensitive_log_text('Incorrect API key provided: ' . $secret . '. Authorization: Bearer ' . $secret);
check_case(strpos($safe, $secret) === false, 'complete secret redacted');
check_case(strpos($safe, 'secret-fragment') === false, 'secret fragment redacted');
check_case(strpos(cbia_sanitize_provider_error('openai', 'Incorrect API key provided: '.$secret, 401), $secret) === false, '401 message sanitized');
check_case(strpos(cbia_sanitize_provider_error('openai', 'x', 401), 'rejected') !== false, '401 gives local message');
check_case(strpos(cbia_sanitize_provider_error('deepseek', 'Authorization: Bearer '.$secret), $secret) === false, 'DeepSeek error sanitized');

$view = file_get_contents($root . '/includes/admin/views/config.php');
check_case(strpos($view, 'value=""') !== false, 'key input value remains empty');
check_case(strpos($view, 'data-key-configured=') !== false, 'configured state is boolean');
check_case(strpos($view, '************') === false, 'visual asterisk mask removed');
check_case(strpos($view, '••••••••••••••••') !== false, 'configured keys use a consistent visual bullet mask');
check_case(strpos($view, 'abb-api-status') !== false, 'key fields render a visible configured state');
check_case(strpos($view, 'API key not configured') !== false, 'missing keys render an explicit state');
$hooks = file_get_contents($root . '/includes/core/hooks.php');
check_case(strpos($hooks, "sanitize_text_field((string)wp_unslash(\$_POST['api_key']))") === false, 'AJAX does not transform credentials');
check_case(strpos($hooks, 'cbia_sanitize_provider_api_key($provider, $key)') !== false, 'AJAX uses central sanitizer');
check_case(strpos($hooks, 'cbia_store_provider_api_key($provider, $key)') !== false, 'AJAX writes canonical provider store');
check_case(strpos($hooks, "\$settings['text_provider'] = \$provider") === false, 'key AJAX does not change text provider');
check_case(strpos($hooks, "\$settings['image_provider'] = \$provider") === false, 'key AJAX does not change image provider');
$config = file_get_contents($root . '/includes/admin/config.php');
check_case(strpos($config, "sanitize_text_field(\$provider_existing_key") === false, 'model save never sanitizes stored credentials');
check_case(strpos($config, "'openai_api_key'         => \$api_key") === false, 'model settings partial excludes credentials');
check_case(strpos($config, 'cbia_store_provider_api_key($pkey, $posted_key)') !== false, 'settings form writes canonical provider store');
$main = file_get_contents($root . '/cbiastudio-blogflow-ai.php');
check_case(strpos($main, 'cbia_store_provider_api_key($provider, $partial[$secret_key])') !== false, 'legacy settings writes are routed to canonical store');

$openai = file_get_contents($root . '/includes/engine/openai.php');
check_case(strpos($openai, "cbia_get_provider_api_key('openai')") !== false, 'OpenAI Images resolves OpenAI key');
check_case(strpos($openai, 'provider_rejected_before_generation') !== false, '401 carries exact zero source');
check_case(strpos($openai, 'cbia_sanitize_provider_error') !== false, 'remote image errors sanitized');
$oldposts = file_get_contents($root . '/includes/engine/oldposts.php');
check_case(strpos($oldposts, 'cbia_image_provider_preflight()') !== false, 'Oldposts v3 preflight present');
check_case(strpos($oldposts, "'result_status' => 'blocked_local'") !== false, 'local block recorded');
check_case(strpos($oldposts, '$content_before_image_reset') !== false, 'internal content rollback present');
$generate_pos = strpos($oldposts, 'list($ok, $attach_id, $model, $err, $meta) = cbia_generate_image_openai');
$delete_pos = strpos($oldposts, 'if ($remove_old) delete_post_thumbnail', $generate_pos);
check_case($generate_pos !== false && $delete_pos > $generate_pos, 'featured deletion occurs only after generation succeeds');
check_case(strpos($oldposts, "&& !empty(\$image_preflight['ok'])") !== false, 'destructive actions require preflight');
check_case(strpos($oldposts, '685') === false && strpos($oldposts, '682') === false && strpos($oldposts, '679') === false, 'real-case IDs are not hard-coded');

echo "provider-api-key-storage-redaction: {$count}/{$count} OK\n";
