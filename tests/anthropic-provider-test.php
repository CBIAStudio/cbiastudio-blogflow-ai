<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['anthropic_http_calls'] = 0;
$GLOBALS['anthropic_response'] = array();
class WP_Error { private $message; public function __construct($code = '', $message = '') { $this->message = $message; } public function get_error_message() { return $this->message; } }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function __($text, $domain = '') { return $text; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return (int)($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response) { return (string)($response['body'] ?? ''); }
function wp_remote_retrieve_header($response, $name) { return (string)($response['headers'][strtolower($name)] ?? ''); }
function wp_remote_post($url, $args) { $GLOBALS['anthropic_http_calls']++; $GLOBALS['anthropic_url'] = $url; $GLOBALS['anthropic_args'] = $args; return $GLOBALS['anthropic_response']; }
function cbia_provider_safe_remote_post($provider, $url, $args, $api_key) { $args['headers'] = array_merge($args['headers'] ?? array(), cbia_anthropic_headers($api_key)); return wp_remote_post($url, $args); }
function cbia_get_provider_api_key($provider) { return 'test-anthropic-key'; }
function cbia_get_text_model_for_provider($provider, $fallback = '') { return 'claude-sonnet-5'; }
function cbia_get_settings() { return array('responses_max_output_tokens' => 6000); }
function cbia_usage_empty() { return array('input_tokens'=>0,'cached_input_tokens'=>0,'cache_creation_input_tokens'=>0,'cache_creation_5m_input_tokens'=>0,'cache_creation_1h_input_tokens'=>0,'cache_read_input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0); }
function cbia_local_blocked_attempt_meta($type, $provider, $model, $code, $error) { return array(); }
function cbia_attach_attempts_meta($data, $attempts) { $data['_cbia_attempts'] = $attempts; return $data; }
function cbia_is_stop_requested() { return false; }
function cbia_log($message, $level = 'INFO') {}
function cbia_mask_sensitive_log_text($message) { return str_replace('test-anthropic-key', '[REDACTED]', (string)$message); }

require dirname(__DIR__) . '/includes/engine/anthropic.php';

function anthropic_check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$parsed = cbia_anthropic_parse_response(array(
    'id' => 'msg_test', 'model' => 'claude-sonnet-5', 'role' => 'assistant', 'stop_reason' => 'end_turn',
    'content' => array(array('type' => 'thinking', 'thinking' => 'private'), array('type' => 'text', 'text' => '<p>Article</p>'), array('type' => 'text', 'text' => '<p>Second block</p>'), array('type' => 'tool_use', 'name' => 'ignored')),
    'usage' => array('input_tokens' => 100, 'cache_creation_input_tokens' => 20, 'cache_read_input_tokens' => 30, 'output_tokens' => 40, 'service_tier' => 'standard', 'cache_creation' => array('ephemeral_5m_input_tokens' => 8, 'ephemeral_1h_input_tokens' => 12)),
));
anthropic_check($parsed['text'] === "<p>Article</p>\n<p>Second block</p>", 'Only ordered text blocks may be exposed');
anthropic_check($parsed['usage']['total_tokens'] === 190, 'Anthropic usage parsing failed');
anthropic_check($parsed['usage']['cache_creation_5m_input_tokens'] === 8, 'Anthropic 5 minute cache parsing failed');
anthropic_check($parsed['usage']['cache_creation_1h_input_tokens'] === 12, 'Anthropic 1 hour cache parsing failed');
anthropic_check($parsed['completion_status'] === 'complete', 'end_turn normalization failed');
anthropic_check(cbia_anthropic_normalize_stop_reason('max_tokens') === 'output_limit', 'max_tokens normalization failed');
anthropic_check(cbia_anthropic_normalize_stop_reason('tool_use') === 'invalid_for_article', 'tool_use normalization failed');

$GLOBALS['anthropic_response'] = array('response' => array('code' => 200), 'headers' => array('request-id' => 'req_test'), 'body' => json_encode(array(
    'id' => 'msg_test', 'model' => 'claude-sonnet-5', 'role' => 'assistant', 'stop_reason' => 'end_turn', 'stop_sequence' => null,
    'content' => array(array('type' => 'text', 'text' => 'OK')),
    'usage' => array('input_tokens' => 4, 'output_tokens' => 1, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0),
)));
$result = cbia_anthropic_messages_call('Reply OK', 'System', 1, 16, array('phase' => 'configuration_test'));
anthropic_check($result[0] === true && $result[1] === 'OK', 'Mocked Anthropic call failed');
anthropic_check($GLOBALS['anthropic_url'] === 'https://api.anthropic.com/v1/messages', 'Messages endpoint failed');
$headers = $GLOBALS['anthropic_args']['headers'];
anthropic_check(isset($headers['x-api-key'], $headers['anthropic-version'], $headers['content-type']), 'Required Anthropic headers missing');
$payload = json_decode($GLOBALS['anthropic_args']['body'], true);
anthropic_check($payload['model'] === 'claude-sonnet-5' && $payload['max_tokens'] === 16 && $payload['system'] === 'System', 'Messages payload failed');
anthropic_check(count($payload['messages']) === 1 && $payload['messages'][0]['role'] === 'user', 'Messages structure failed');

$source = file_get_contents(dirname(__DIR__) . '/includes/engine/openai.php');
anthropic_check(strpos($source, "if (\$provider === 'anthropic')") !== false, 'Anthropic router missing');
anthropic_check(strpos($source, 'return cbia_anthropic_messages_call') !== false, 'Anthropic must return directly without fallback');
echo "anthropic-provider: OK\n";
