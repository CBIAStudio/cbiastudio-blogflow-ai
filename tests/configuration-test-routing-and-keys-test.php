<?php
$root = dirname(__DIR__);
$files = array(
    'base' => file_get_contents($root . '/includes/engine/base.php'),
    'openai' => file_get_contents($root . '/includes/engine/openai.php'),
    'config' => file_get_contents($root . '/includes/admin/config.php'),
    'config_view' => file_get_contents($root . '/includes/admin/views/config.php'),
    'blog_view' => file_get_contents($root . '/includes/admin/views/blog.php'),
    'blog_service' => file_get_contents($root . '/includes/services/blog-service.php'),
    'blog_engine' => file_get_contents($root . '/includes/engine/blog.php'),
    'hooks' => file_get_contents($root . '/includes/core/hooks.php'),
);

$cases = 0;
function check_case($condition, $message) {
    global $cases;
    $cases++;
    if (!$condition) throw new RuntimeException("Case {$cases} failed: {$message}");
}
function has($source, $needle) { return strpos($source, $needle) !== false; }

check_case(has($files['base'], "cbia_get_text_provider()"), 'test resolves the saved text provider');
check_case(has($files['base'], "cbia_get_text_model_for_provider(\$provider, '')"), 'test resolves the provider-specific model');
check_case(!has(substr($files['base'], strpos($files['base'], 'function cbia_run_test_configuration'), 18000), 'cbia_pick_model'), 'test does not use the legacy OpenAI model picker');
check_case(has($files['base'], "'allow_fallback' => false"), 'test disables provider fallback');
check_case(has($files['base'], "'ignore_stop' => true"), 'test bypasses STOP locally');
check_case(has($files['base'], "'defer_usage_recording' => true"), 'test defers Usage to one central record');
check_case(has($files['base'], "'phase' => 'configuration_test'"), 'test phase is explicit');
check_case(has($files['base'], "'paid_generation' => false"), 'image test is local only');
check_case(has($files['base'], 'stop_flag_changed='), 'test logs STOP changes');
check_case(has($files['base'], "'request_sent' => 0"), 'missing key is local preflight');
check_case(has($files['base'], "'cost_source' => 'local_preflight'"), 'local preflight cost source is exact');
check_case(has($files['base'], "'configuration_test', 1, \$test_max, \$context") && has($files['base'], ': 32;'), 'test uses one request with 32 output tokens in basic mode');
check_case(has($files['openai'], "if (!\$allow_fallback) return \$deepseek_result;"), 'DeepSeek cannot cross-fallback during test');
check_case(has($files['openai'], "(\$disable_model_fallback || !\$allow_fallback)"), 'OpenAI model chain is disabled during test');
check_case(has($files['openai'], "empty(\$context['ignore_stop']) && cbia_is_stop_requested()"), 'clients preserve normal STOP behavior outside test');
check_case(has($files['openai'], "cbia_google_generate_content_call(\$prompt, \$system, \$tries, \$max_output_override, \$context)"), 'Google receives isolated test context');
check_case(has($files['openai'], "'provider' => 'google'"), 'Google returns request metadata');
check_case(has($files['openai'], "sanitize_key((string)(\$context['phase'] ?? '')) === 'configuration_test'"), 'test token limits are phase-scoped');

$key_start = strpos($files['config'], '$save_api_keys_only');
$key_end = strpos($files['config'], '// CAMBIO: modelos por proveedor', $key_start);
$key_block = substr($files['config'], $key_start, $key_end - $key_start);
check_case(!has($key_block, "'text_provider'"), 'key-only save does not change text provider');
check_case(!has($key_block, "'image_provider'"), 'key-only save does not change image provider');
check_case(!has($key_block, "'current_provider'"), 'key-only save does not change provider registry selection');
check_case(has($files['config'], "if (\$posted_key === '') continue;"), 'only explicitly posted keys are saved');
check_case(has($files['config'], 'cbia_store_provider_api_key($pkey, $posted_key)'), 'canonical provider key store remains synchronized');
check_case(has($files['config'], '$pkey === $text_provider ?') && has($files['config'], '$pkey === $image_provider ?'), 'key save ignores inactive provider-scope fields');
check_case(has($files['config'], 'cbia_normalize_submitted_api_key($val, $provider)'), 'key normalization receives the effective provider');
check_case(has($files['config_view'], 'placeholder="' . "' . esc_attr(\$mask_placeholder)"), 'key mask is a placeholder');
check_case(has($files['config_view'], 'value="" autocomplete="new-password"'), 'real keys are never rendered and browser password autofill is discouraged');
check_case(has($files['config_view'], 'data-lpignore="true"') && has($files['config_view'], 'data-1p-ignore="true"'), 'password managers are asked not to overwrite API key fields');
check_case(!has($files['config_view'], 'substr($api_key'), 'diagnostics expose no key fragments');
check_case(has($files['config_view'], "cbia_has_provider_api_key('deepseek')"), 'DeepSeek diagnostic is yes/no only');
check_case(has($files['config_view'], "cbia_has_provider_api_key('google')"), 'Google diagnostic is yes/no only');
check_case(has($files['blog_service'], "\$GLOBALS['cbia_configuration_test_result']"), 'service exposes structured result to UI');
check_case(has($files['blog_engine'], "\$GLOBALS['cbia_configuration_test_result']"), 'fallback handler exposes structured result to UI');
check_case(has($files['blog_view'], 'Local configuration checked; no paid image was generated.'), 'UI explains local image check');
check_case(has($files['blog_view'], 'notice-error'), 'UI shows failed tests as errors');
check_case(has($files['blog_view'], 'check_admin_referer') || has($files['blog_service'], "check_admin_referer('cbia_blog_actions_nonce')"), 'test action is nonce protected');
check_case(has($files['blog_service'], "current_user_can('manage_options')"), 'test action requires manage_options');

$runtime_start = strpos($files['hooks'], 'function cbia_ajax_start_generation()');
$runtime_end = strpos($files['hooks'], "if (!function_exists('cbia_ajax_get_oldposts_log'))", $runtime_start);
$runtime = substr($files['hooks'], $runtime_start, $runtime_end - $runtime_start);
check_case(!has($runtime, 'openai_api_key') && !has($runtime, 'deepseek_api_key') && !has($runtime, 'google_api_key'), 'Blog runtime sync carries no credentials');
check_case(!has($files['base'], 'freepik_api_key'), 'no duplicate unsupported Freepik key option was introduced');

echo "configuration-test-routing-and-keys: {$cases}/{$cases} OK\n";
