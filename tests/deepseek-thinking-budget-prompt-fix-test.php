<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = array();
$GLOBALS['logs'] = array();
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function __($value, $domain = null) { return $value; }
function apply_filters($hook, $value) { return $value; }
function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function cbia_log($message, $level = 'INFO') { $GLOBALS['logs'][] = array($level, $message); }
function cbia_attach_attempts_meta($meta, $attempts) { $meta['_cbia_attempts'] = $attempts; return $meta; }
function cbia_prompt_is_spanish($language) { return strtolower((string)$language) === 'spanish'; }

require dirname(__DIR__) . '/includes/support/provider-model-catalog.php';
require dirname(__DIR__) . '/includes/support/deepseek-v4.php';
require dirname(__DIR__) . '/includes/engine/content.php';
require dirname(__DIR__) . '/includes/engine/posts.php';
require dirname(__DIR__) . '/includes/engine/openai.php';

$root = dirname(__DIR__);
$source = array(
    'base' => file_get_contents($root . '/includes/engine/base.php'),
    'openai' => file_get_contents($root . '/includes/engine/openai.php'),
    'posts' => file_get_contents($root . '/includes/engine/posts.php'),
    'prompt' => file_get_contents($root . '/includes/engine/prompt.php'),
    'preview' => file_get_contents($root . '/includes/services/article-preview-service.php'),
    'blog' => file_get_contents($root . '/includes/engine/blog.php'),
    'view' => file_get_contents($root . '/includes/admin/views/blog.php'),
);
$cases = 0;
function verify_case($condition, $label) {
    global $cases;
    $cases++;
    if (!$condition) throw new RuntimeException("Case {$cases} failed: {$label}");
}
function contains_text($source, $needle) { return strpos($source, $needle) !== false; }

verify_case(contains_text($source['base'], "\$base . '/models'"), 'GET /models');
verify_case(contains_text($source['base'], "\$data['data']"), 'models list parsed');
verify_case(contains_text($source['base'], 'model_available'), 'selected model availability');
verify_case(contains_text($source['base'], '$code === 401'), '401 classification');
verify_case(contains_text($source['base'], '$code === 402'), '402 classification');
verify_case(contains_text($source['base'], '$code === 403'), '403 classification');
verify_case(contains_text($source['base'], '$code === 429'), '429 classification');
verify_case(contains_text($source['base'], '$code >= 500'), '500 classification');
verify_case(contains_text($source['base'], 'transport_error'), 'transport and timeout path');
verify_case(contains_text($source['base'], "'thinking_override'") && contains_text($source['base'], '!$advanced') && contains_text($source['base'], "? 'disabled'"), 'basic chat disables thinking');
verify_case(contains_text($source['base'], ': 32;'), 'basic chat uses 32 tokens');
verify_case(contains_text($source['base'], "'allow_fallback' => false"), 'no fallback');
verify_case(contains_text($source['base'], "'ignore_stop' => true"), 'stop locally bypassed');
verify_case(contains_text($source['base'], 'test_config_advanced') || contains_text($source['view'], 'test_config_advanced'), 'advanced test exposed');
verify_case(contains_text($source['openai'], 'reasoning_content_present'), 'reasoning presence captured');
verify_case(contains_text($source['openai'], "unset(\$data['choices'][0]['message']['reasoning_content'])"), 'reasoning content removed');
verify_case(contains_text($source['base'], "'chat_incomplete'"), 'HTTP 200 incomplete classification');
verify_case(contains_text($source['base'], "'chat_empty_content'"), 'empty final content classification');
verify_case(contains_text($source['base'], "'chat_ok'"), 'successful chat classification');
verify_case(contains_text($source['openai'], "\$phase === 'configuration_test'"), 'configuration phase isolated');
verify_case(contains_text($source['base'], 'stop_flag_changed'), 'stop unchanged reported');
verify_case(contains_text($source['base'], 'usage_event_saved'), 'usage save status logged');
verify_case(contains_text($source['base'], "'cost_status' => \$provider_rejected ? 'exact'"), 'rejected provider request has exact zero cost');
verify_case(contains_text($source['base'], "'connection_ok'"), 'usage failure does not define connection status');

$usage = cbia_deepseek_parse_usage(array('usage'=>array('prompt_tokens'=>100,'completion_tokens'=>80,'completion_tokens_details'=>array('reasoning_tokens'=>30),'total_tokens'=>180)));
verify_case($usage['completion_tokens'] === 80, 'completion tokens');
verify_case($usage['reasoning_tokens'] === 30, 'reasoning tokens');
verify_case($usage['visible_output_tokens_estimated'] === 50, 'visible output estimate');
verify_case(contains_text($source['openai'], 'finish_reason'), 'finish reason captured');
verify_case(contains_text($source['posts'], "array('content_filter', 'provider_error')"), 'unsafe completion states block publication');
verify_case(contains_text($source['openai'], 'cbia_normalize_chat_completion_status') && contains_text($source['openai'], "return 'unknown'"), 'provider finish reasons are normalized safely');
verify_case(contains_text($source['posts'], 'output_limit_reached'), 'output-limit expansion reason');
verify_case(contains_text($source['posts'], '$force_completion') && contains_text($source['posts'], 'needs_manual_review_length'), 'token-limit response requires completion before publication');
verify_case(contains_text($source['preview'], 'preview_length_insufficient'), 'Preview rejects unresolved truncation before images');
verify_case(contains_text($source['posts'], 'below_effective_minimum'), 'short response expansion reason');

$base_settings = array('responses_max_output_tokens'=>6000);
$budget = cbia_resolve_text_token_budget($base_settings, 1800, 2000, 'Spanish', false, false, 'deepseek', 'deepseek-v4-flash', 'disabled');
verify_case($budget['configured'] === 6000, 'configured 6000');
verify_case($budget['effective'] >= 6000, 'effective respects configured');
verify_case($budget['effective'] !== 4620, 'no silent 4620 reduction');
verify_case($budget['faq'] === 0, 'FAQ disabled has no reservation flag');
verify_case($budget['examples'] === 0, 'examples disabled has no reservation flag');
$thinking_budget = cbia_resolve_text_token_budget($base_settings, 1800, 2000, 'Spanish', false, false, 'deepseek', 'deepseek-v4-flash', 'enabled');
verify_case($thinking_budget['calculated'] > 4620, 'thinking considered');
verify_case(cbia_resolve_text_token_budget($base_settings, 950, 1100, 'Spanish')['effective'] >= 6000, 'short configured limit');
verify_case(cbia_resolve_text_token_budget($base_settings, 1800, 2000, 'Spanish')['effective'] >= 6000, 'medium configured limit');
verify_case(cbia_resolve_text_token_budget($base_settings, 2000, 2200, 'Spanish')['effective'] >= 6000, 'long configured limit');
verify_case(contains_text($source['preview'], 'cbia_resolve_text_token_budget'), 'Preview shares budget resolver');
verify_case(contains_text($source['posts'], "'strict_max_output_override' => true") && contains_text($source['posts'], "'remote_text_request' => 2"), 'expansion uses its constrained second-request budget');

$html = '<h2>Inicio</h2><p>Texto.</p><h2>Ejemplos prácticos aplicados</h2><h3>Escenario 1</h3><p>Eliminar.</p><h2>Cierre</h2><p>Conservar.</p>';
$clean = cbia_strip_practical_examples_section($html);
verify_case(strpos($clean, 'Escenario 1') === false, 'examples detected and removed');
verify_case(strpos($clean, 'Conservar') !== false, 'content after examples preserved');
verify_case(cbia_strip_practical_examples_section('<h2>Inicio</h2><p>Sin modulo.</p>') === '<h2>Inicio</h2><p>Sin modulo.</p>', 'examples absent unchanged');
verify_case(contains_text($source['posts'], 'Practical examples cleanup:'), 'examples cleanup logged');
verify_case(!contains_text($source['posts'], 'No crear una seccion independiente de ejemplos'), 'disabled Spanish module is omitted from expansion prompts');
verify_case(!contains_text($source['posts'], 'Do not create a standalone practical examples'), 'disabled English module is omitted from expansion prompts');
verify_case(contains_text($source['prompt'], "if (!empty(\$opts['include_practical_examples']))"), 'profile examples conditional');
verify_case(contains_text($source['prompt'], 'discover_editorial'), 'Discover profile');
verify_case(contains_text($source['prompt'], 'seo_balanced'), 'SEO profile');
verify_case(contains_text($source['prompt'], 'how_to'), 'How-to profile');
verify_case(contains_text($source['posts'], "'remote_text_request' => 2") && contains_text($source['posts'], 'cbia_openai_responses_call($prompt, $title, 1, $max_output'), 'single expansion maximum');
verify_case(strpos($source['posts'], 'cbia_generate_image_openai_with_prompt') > strpos($source['posts'], 'Generated text remains below'), 'images occur after text validation');
verify_case(contains_text($source['blog'], 'Text generation efficiency:'), 'batch efficiency summary');
verify_case(contains_text($source['posts'], '$words_after_faq_cleanup = $words_before_faq_cleanup;'), 'FAQ-enabled metrics initialize cleanup count');
verify_case(contains_text($source['blog'], "!empty(\$metric['first_pass_accepted'])"), 'batch summary uses actual first-pass result');

cbia_image_batch_auth_guard_begin();
verify_case(empty(cbia_image_batch_auth_guard_get()['blocked']), 'image guard starts open');
cbia_image_batch_auth_guard_block('request-id');
verify_case(!empty(cbia_image_batch_auth_guard_get()['blocked']), 'first 401 blocks provider');
$skip = cbia_image_batch_auth_guard_skip('body', 'gpt-image-2');
verify_case($skip[0] === false && $skip[4]['request_sent'] === 0, 'subsequent image request is local');
verify_case($skip[4]['cost_micro_usd'] === 0 && $skip[4]['cost_status'] === 'exact', 'skipped image cost zero');
verify_case((int)cbia_image_batch_auth_guard_get()['skipped'] === 1, 'skipped call counted');
$finished = cbia_image_batch_auth_guard_finish();
verify_case(!isset($GLOBALS['options']['cbia_image_batch_auth_guard']), 'guard cleared after batch');
verify_case(contains_text($source['posts'], 'status' . "' => 'pending'") || contains_text($source['posts'], "'status' => 'pending'"), 'pending image preserved');
verify_case(contains_text($source['openai'], 'cbia_sanitize_provider_error'), 'image error sanitized');
verify_case(contains_text($source['openai'], 'if ($code === 401) cbia_image_batch_auth_guard_block'), 'only HTTP 401 activates cut');
verify_case(!contains_text(substr($source['openai'], strpos($source['openai'], 'function cbia_deepseek_chat_call')), 'cbia_image_batch_auth_guard_skip'), 'DeepSeek text unaffected');
echo "deepseek-thinking-budget-prompt-fix: {$cases}/{$cases} OK\n";
