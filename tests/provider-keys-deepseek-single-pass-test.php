<?php
define('ABSPATH', __DIR__ . '/');
define('CBIA_OPTION_SETTINGS', 'cbia_settings');

$GLOBALS['test_options'] = array();
$GLOBALS['test_meta'] = array();

function add_action() {}
function apply_filters($tag, $value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_title($value) { return sanitize_key(str_replace(' ', '-', (string)$value)); }
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['test_options'][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['test_meta'][$post_id][$key] ?? ($single ? '' : array()); }
function update_post_meta($post_id, $key, $value) { $GLOBALS['test_meta'][$post_id][$key] = $value; return true; }
function current_time($type) { return $type === 'mysql' ? '2026-07-21 10:00:00' : time(); }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000021'; }
function __($text, $domain = '') { return $text; }

require dirname(__DIR__) . '/includes/integrations/providers.php';
require dirname(__DIR__) . '/includes/engine/base.php';
require dirname(__DIR__) . '/includes/engine/models.php';
require dirname(__DIR__) . '/includes/engine/usage.php';
require dirname(__DIR__) . '/includes/domain/costs.php';
require dirname(__DIR__) . '/includes/engine/posts.php';
require dirname(__DIR__) . '/includes/engine/prompt.php';

$cases = 0;
function assert_case($condition, $message) {
    global $cases;
    $cases++;
    if (!$condition) throw new RuntimeException("Case {$cases} failed: {$message}");
}
function source_contains($file, $needle) { return strpos(file_get_contents($file), $needle) !== false; }

$root = dirname(__DIR__);
$GLOBALS['test_options']['cbia_settings'] = array(
    'text_provider' => 'deepseek',
    'image_provider' => 'openai',
    'text_model' => 'deepseek-v4-flash',
    'image_model' => 'gpt-image-2',
    'openai_api_key' => 'openai-project-key',
    'deepseek_api_key' => 'deepseek-provider-key',
    'google_api_key' => 'google-provider-key',
);
$GLOBALS['test_options']['cbia_provider_settings'] = array(
    'current_provider' => 'deepseek',
    'providers' => array(
        'openai' => array('api_key' => 'openai-project-key', 'model' => 'gpt-5-mini', 'image_model' => 'gpt-image-2'),
        'deepseek' => array('api_key' => 'deepseek-provider-key', 'model' => 'deepseek-v4-flash'),
        'google' => array('api_key' => 'google-provider-key', 'model' => 'gemini-2.5-flash', 'image_model' => 'imagen-3.0-generate-002'),
    ),
);

assert_case(cbia_get_provider_api_key('openai') === 'openai-project-key', 'OpenAI key resolves independently');
assert_case(cbia_get_provider_api_key('deepseek') === 'deepseek-provider-key', 'DeepSeek key resolves independently');
assert_case(cbia_has_provider_api_key('openai') && cbia_has_provider_api_key('deepseek'), 'DeepSeek text and OpenAI images coexist');
$saved_deepseek_key = $GLOBALS['test_options']['cbia_settings']['deepseek_api_key'];
$saved_nested_deepseek_key = $GLOBALS['test_options']['cbia_provider_settings']['providers']['deepseek']['api_key'];
$GLOBALS['test_options']['cbia_settings']['deepseek_api_key'] = '';
$GLOBALS['test_options']['cbia_provider_settings']['providers']['deepseek']['api_key'] = '';
$GLOBALS['test_options']['cbia_settings']['api_key'] = 'legacy-openai-only';
assert_case(cbia_get_provider_api_key('deepseek') === '', 'legacy generic key never crosses into DeepSeek');
$GLOBALS['test_options']['cbia_settings']['deepseek_api_key'] = $saved_deepseek_key;
$GLOBALS['test_options']['cbia_provider_settings']['providers']['deepseek']['api_key'] = $saved_nested_deepseek_key;
unset($GLOBALS['test_options']['cbia_settings']['api_key']);
assert_case(cbia_is_masked_api_key_value('************'), 'asterisk mask detected');
assert_case(cbia_is_masked_api_key_value('••••••••'), 'bullet mask detected');
assert_case(cbia_normalize_submitted_api_key('********') === '', 'mask is never accepted as a key');

cbia_providers_save_settings(array('current_provider' => 'deepseek', 'providers' => array('deepseek' => array('api_key' => '', 'model' => 'deepseek-v4-flash'))));
$saved = get_option('cbia_provider_settings', array());
assert_case($saved['providers']['openai']['api_key'] === 'openai-project-key', 'saving DeepSeek keeps OpenAI');
assert_case($saved['providers']['deepseek']['api_key'] === 'deepseek-provider-key', 'empty DeepSeek field keeps existing key');
cbia_providers_save_settings(array('providers' => array('openai' => array('api_key' => '************'))));
$saved = get_option('cbia_provider_settings', array());
assert_case($saved['providers']['openai']['api_key'] === 'openai-project-key', 'masked OpenAI field keeps existing key');
assert_case($saved['providers']['google']['api_key'] === 'google-provider-key', 'unsubmitted Google key is preserved');

$preflight = cbia_generation_preflight(cbia_get_settings(), true);
assert_case($preflight['ok'] && $preflight['text']['provider'] === 'deepseek' && $preflight['image']['provider'] === 'openai', 'mixed-provider preflight succeeds');
$GLOBALS['test_options']['cbia_settings']['openai_api_key'] = '';
$GLOBALS['test_options']['cbia_provider_settings']['providers']['openai']['api_key'] = '';
$preflight = cbia_generation_preflight(cbia_get_settings(), true);
assert_case(!$preflight['ok'] && $preflight['errors'][0]['code'] === 'missing_image_api_key', 'missing image key blocks before text');
assert_case(cbia_generation_preflight(cbia_get_settings(), false)['ok'], 'explicit no-image preview does not require image key');

$local = cbia_costes_calculate_row(array('type' => 'image', 'request_sent' => 0, 'result_status' => 'blocked_local', 'error_type' => 'missing_api_key'));
assert_case($local['cost_micro_usd'] === 0 && $local['cost_status'] === 'exact' && $local['cost_source'] === 'local_preflight', 'local block is exact zero');
$timeout = cbia_costes_calculate_row(array('type' => 'image', 'status' => 'timeout', 'request_sent' => 1));
assert_case($timeout['cost_micro_usd'] === null && $timeout['cost_status'] === 'unknown', 'sent timeout remains unknown');

assert_case(cbia_estimate_output_tokens_for_length_target(1800, 2000, 'Spanish', false, false, 'openai', 'gpt-5-mini', '') === 4620, 'OpenAI budget remains 4620');
assert_case(cbia_estimate_output_tokens_for_length_target(1800, 2000, 'Spanish', false, false, 'deepseek', 'deepseek-v4-flash', 'disabled') === 5200, 'DeepSeek medium no-FAQ budget is 5200');
assert_case(cbia_estimate_output_tokens_for_length_target(950, 1100, 'Spanish', false, false, 'deepseek', 'deepseek-v4-flash', 'disabled') !== 5200, 'short length is not forced to 5200');

$policy = cbia_prompt_build_length_policy_block(array('post_length_variant' => 'medium', 'include_faq' => 0), 'Spanish');
assert_case(strpos($policy, '1950-2000') !== false && strpos($policy, '1800 palabras visibles') !== false, 'no-FAQ body target is explicit');
assert_case(strpos($policy, 'No incluyas preguntas frecuentes') !== false && strpos($policy, '7 bloques principales') !== false, 'no-FAQ structure is explicit');
$examples_policy = cbia_prompt_build_length_policy_block(array('post_length_variant' => 'medium', 'include_faq' => 1, 'include_practical_examples' => 1), 'Spanish');
assert_case(strpos($examples_policy, 'La FAQ forma parte de ese total') !== false && strpos($examples_policy, 'Los ejemplos practicos forman parte del total') !== false, 'optional modules are budgeted inside one response');
assert_case(cbia_get_soft_length_floor_words(1800) === 1530, 'medium expansion floor allows 15 percent tolerance');
foreach (array(1398, 1529) as $words) assert_case($words < cbia_get_soft_length_floor_words(1800), "{$words} words requires expansion");
foreach (array(1530, 1725, 1800, 2000) as $words) assert_case($words >= cbia_get_soft_length_floor_words(1800), "{$words} words succeeds first pass");

$hooks = file_get_contents($root . '/includes/core/hooks.php');
$start = strpos($hooks, 'function cbia_ajax_start_generation()');
$end = strpos($hooks, "if (!function_exists('cbia_ajax_get_oldposts_log'))", $start);
$runtime_source = substr($hooks, $start, $end - $start);
assert_case(strpos($runtime_source, 'cbia_update_settings_merge($runtime_settings)') !== false, 'Blog saves only runtime whitelist');
assert_case(strpos($runtime_source, "foreach (array('openai_api_key'") === false, 'Blog runtime sync does not process credentials');
assert_case(source_contains($root . '/includes/admin/views/usage.php', '$text_provider_key') && source_contains($root . '/includes/admin/views/usage.php', '$image_provider_key'), 'Usage separates text and image providers');
assert_case(source_contains($root . '/includes/engine/openai.php', 'cbia_deepseek_chat_call($prompt, $system, $tries, $max_output_override, $context)'), 'DeepSeek receives the output override');
assert_case(source_contains($root . '/includes/engine/openai.php', "if (\$phase === 'expand') \$timeout = max(150, \$timeout);"), 'DeepSeek expansion timeout is 150 seconds');
assert_case(source_contains($root . '/includes/services/article-preview-service.php', "'expansion_calls' => \$preview_expansion_calls"), 'Preview preserves expansion usage');
assert_case(source_contains($root . '/includes/engine/prompt.php', 'if ($is_auto_profile)') && source_contains($root . '/includes/engine/prompt.php', 'return $generated;'), 'Auto by title keeps the generated per-title profile');
assert_case(source_contains($root . '/includes/engine/posts.php', '$expanded_words >= $effective_min_words'), 'Expansion status uses the effective tolerance floor');

echo "provider-keys-deepseek-single-pass: {$cases}/{$cases} OK\n";
