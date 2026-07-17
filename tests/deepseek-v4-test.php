<?php
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['test_options'] = array();
$GLOBALS['test_http_queue'] = array();
$GLOBALS['test_http_calls'] = 0;
$GLOBALS['test_actions'] = array();
$GLOBALS['test_settings'] = array(
	'deepseek_thinking_mode' => 'disabled',
	'deepseek_reasoning_effort' => 'high',
	'responses_max_output_tokens' => 6000,
	'openai_temperature' => 0.5,
);

class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['test_actions'][] = array( $hook, $callback, $priority ); }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['test_options'] ) ? $GLOBALS['test_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['test_options'][ $key ] = $value; return true; }
function __( $text, $domain = '' ) { return $text; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ) { return (int) ( $response['response']['code'] ?? 0 ); }
function wp_remote_retrieve_body( $response ) { return (string) ( $response['body'] ?? '' ); }
function wp_remote_retrieve_header( $response, $name ) { return (string) ( $response['headers'][ strtolower( $name ) ] ?? '' ); }
function wp_remote_post( $url, $args ) {
	$GLOBALS['test_http_calls']++;
	$GLOBALS['test_last_url'] = $url;
	$GLOBALS['test_last_args'] = $args;
	if ( empty( $GLOBALS['test_http_queue'] ) ) return new WP_Error( 'empty_queue', 'No mocked response' );
	return array_shift( $GLOBALS['test_http_queue'] );
}
function cbia_get_settings() { return $GLOBALS['test_settings']; }
function cbia_get_provider_config( $provider ) { return array( 'base_url' => 'https://api.deepseek.com', 'api_key' => 'test-key' ); }
function cbia_get_provider_api_key( $provider ) { return 'test-key'; }
function cbia_get_text_model_for_provider( $provider, $default ) { return (string) ( $GLOBALS['test_model'] ?? $default ); }
function cbia_get_provider_model( $provider, $default ) { return $default; }
function cbia_is_stop_requested() { return false; }
function cbia_log( $message, $level = 'INFO' ) { $GLOBALS['test_logs'][] = array( $level, $message ); }
function cbia_mask_sensitive_log_text( $message ) { return str_replace( 'test-key', '[REDACTED]', (string) $message ); }

require_once dirname( __DIR__ ) . '/includes/engine/usage.php';
require_once dirname( __DIR__ ) . '/includes/support/deepseek-v4.php';
require_once dirname( __DIR__ ) . '/includes/engine/openai.php';

function test_assert( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
}
function mock_response( $code, $data, $headers = array() ) {
	return array( 'response' => array( 'code' => $code ), 'body' => is_string( $data ) ? $data : json_encode( $data ), 'headers' => array_change_key_case( $headers, CASE_LOWER ) );
}

$chat = cbia_deepseek_normalize_runtime_config( 'deepseek-chat', 'enabled', 'max' );
test_assert( $chat['model_effective'] === 'deepseek-v4-flash' && $chat['thinking'] === 'disabled' && $chat['reasoning_effort'] === 'high', 'deepseek-chat migration failed' );
$reasoner = cbia_deepseek_normalize_runtime_config( 'deepseek-reasoner', 'disabled', '' );
test_assert( $reasoner['model_effective'] === 'deepseek-v4-flash' && $reasoner['thinking'] === 'enabled' && $reasoner['reasoning_effort'] === 'high', 'deepseek-reasoner migration failed' );
$invalid = cbia_deepseek_normalize_runtime_config( 'invalid', 'invalid', 'invalid' );
test_assert( $invalid['model_effective'] === 'deepseek-v4-flash' && $invalid['thinking'] === 'disabled' && $invalid['reasoning_effort'] === 'high', 'invalid values were not normalized' );

$messages = array( array( 'role' => 'user', 'content' => 'Test' ) );
$disabled_payload = cbia_deepseek_build_payload( cbia_deepseek_normalize_runtime_config( 'deepseek-v4-flash', 'disabled', 'max' ), $messages, 6000, 0.5 );
test_assert( $disabled_payload['thinking']['type'] === 'disabled' && isset( $disabled_payload['temperature'] ) && ! isset( $disabled_payload['reasoning_effort'] ), 'disabled payload is invalid' );
$enabled_payload = cbia_deepseek_build_payload( cbia_deepseek_normalize_runtime_config( 'deepseek-v4-pro', 'enabled', 'max' ), $messages, 6000, 0.5 );
test_assert( $enabled_payload['thinking']['type'] === 'enabled' && $enabled_payload['reasoning_effort'] === 'max' && ! isset( $enabled_payload['temperature'] ) && ! isset( $enabled_payload['top_p'] ), 'enabled payload is invalid' );

$usage_payload = array( 'usage' => array( 'prompt_tokens' => 2000, 'prompt_cache_hit_tokens' => 1000, 'prompt_cache_miss_tokens' => 1000, 'completion_tokens' => 500, 'total_tokens' => 2500, 'completion_tokens_details' => array( 'reasoning_tokens' => 300 ) ) );
$usage = cbia_deepseek_parse_usage( $usage_payload );
test_assert( $usage['cache_hit_tokens'] === 1000 && $usage['cache_miss_tokens'] === 1000 && $usage['reasoning_tokens'] === 300, 'usage parsing failed' );
$flash_no_cache = cbia_deepseek_calculate_cost( 'deepseek-v4-flash', array( 'input_tokens' => 1000, 'cache_hit_tokens' => 0, 'cache_miss_tokens' => 1000, 'cache_breakdown_available' => 1, 'output_tokens' => 500 ) );
test_assert( $flash_no_cache['cost_micro_usd'] === 280 && $flash_no_cache['cost_status'] === 'exact', 'flash no-cache cost failed' );
$flash_cache = cbia_deepseek_calculate_cost( 'deepseek-v4-flash', array( 'input_tokens' => 2000, 'cache_hit_tokens' => 1000, 'cache_miss_tokens' => 1000, 'cache_breakdown_available' => 1, 'output_tokens' => 500 ) );
test_assert( $flash_cache['cost_micro_usd'] === 283, 'flash cache cost rounding failed' );
$pro = cbia_deepseek_calculate_cost( 'deepseek-v4-pro', array( 'input_tokens' => 1000, 'cache_hit_tokens' => 0, 'cache_miss_tokens' => 1000, 'cache_breakdown_available' => 1, 'output_tokens' => 500 ) );
test_assert( $pro['cost_micro_usd'] === 870, 'pro cost failed' );
$estimated = cbia_deepseek_calculate_cost( 'deepseek-v4-flash', array( 'input_tokens' => 1000, 'output_tokens' => 500 ) );
test_assert( $estimated['cost_micro_usd'] === 280 && $estimated['cost_status'] === 'estimated', 'missing cache breakdown estimate failed' );
$unknown = cbia_deepseek_calculate_cost( 'deepseek-v4-flash', array() );
test_assert( $unknown['cost_micro_usd'] === null && $unknown['cost_status'] === 'unknown', 'missing usage must remain unknown' );

$GLOBALS['test_options'] = array(
	'cbia_settings' => array( 'text_provider' => 'deepseek', 'text_model' => 'deepseek-reasoner', 'deepseek_api_key' => 'preserved-key' ),
	'cbia_provider_settings' => array( 'provider' => 'deepseek', 'providers' => array( 'deepseek' => array( 'model' => 'deepseek-reasoner', 'api_key' => 'preserved-key' ) ) ),
);
cbia_deepseek_maybe_migrate_legacy_settings();
$first_migration = $GLOBALS['test_options'];
cbia_deepseek_maybe_migrate_legacy_settings();
test_assert( $first_migration === $GLOBALS['test_options'], 'migration is not idempotent' );
test_assert( $GLOBALS['test_options']['cbia_settings']['text_model'] === 'deepseek-v4-flash' && $GLOBALS['test_options']['cbia_settings']['deepseek_thinking_mode'] === 'enabled', 'reasoner settings migration failed' );
test_assert( $GLOBALS['test_options']['cbia_provider_settings']['providers']['deepseek']['api_key'] === 'preserved-key', 'migration changed the API key' );

$GLOBALS['test_model'] = 'deepseek-v4-flash';
$GLOBALS['test_http_calls'] = 0;
$GLOBALS['test_http_queue'] = array( mock_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Final content', 'reasoning_content' => 'private chain' ) ) ), 'usage' => array( 'prompt_tokens' => 10, 'prompt_cache_hit_tokens' => 2, 'prompt_cache_miss_tokens' => 8, 'completion_tokens' => 5, 'total_tokens' => 15 ) ), array( 'x-request-id' => 'request-1' ) ) );
$result = cbia_deepseek_chat_call( 'Prompt', 'System', 2 );
test_assert( $result[0] === true && $result[1] === 'Final content' && $result[3] === 'deepseek-v4-flash', 'mocked success failed' );
test_assert( ! isset( $result[5]['choices'][0]['message']['reasoning_content'] ), 'reasoning_content leaked into returned data' );
test_assert( $GLOBALS['test_last_url'] === 'https://api.deepseek.com/chat/completions', 'DeepSeek endpoint is invalid' );
$sent_payload = json_decode( $GLOBALS['test_last_args']['body'], true );
test_assert( $sent_payload['thinking']['type'] === 'disabled' && ! isset( $sent_payload['reasoning_effort'] ), 'runtime disabled payload is invalid' );

foreach ( array( 400, 401, 402, 403 ) as $permanent_code ) {
	$GLOBALS['test_http_calls'] = 0;
	$GLOBALS['test_http_queue'] = array( mock_response( $permanent_code, array( 'error' => array( 'message' => 'Permanent error' ) ) ), mock_response( 200, array() ) );
	$result = cbia_deepseek_chat_call( 'Prompt', '', 2 );
	test_assert( $result[0] === false && $GLOBALS['test_http_calls'] === 1, 'permanent HTTP error retried: ' . $permanent_code );
}

$GLOBALS['test_http_calls'] = 0;
$GLOBALS['test_http_queue'] = array(
	mock_response( 429, array( 'error' => array( 'message' => 'Rate limited' ) ), array( 'retry-after' => '0' ) ),
	mock_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Recovered' ) ) ), 'usage' => array( 'prompt_tokens' => 4, 'completion_tokens' => 2 ) ) ),
);
$result = cbia_deepseek_chat_call( 'Prompt', '', 2 );
test_assert( $result[0] === true && $GLOBALS['test_http_calls'] === 2 && count( $result[5]['_cbia_attempts'] ) === 1, 'HTTP 429 retry failed' );

$GLOBALS['test_http_calls'] = 0;
$GLOBALS['test_http_queue'] = array(
	mock_response( 500, array( 'error' => array( 'message' => 'Temporary server error' ) ), array( 'retry-after' => '0' ) ),
	mock_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Recovered from 500' ) ) ), 'usage' => array( 'prompt_tokens' => 4, 'completion_tokens' => 2 ) ) ),
);
$result = cbia_deepseek_chat_call( 'Prompt', '', 2 );
test_assert( $result[0] === true && $GLOBALS['test_http_calls'] === 2, 'HTTP 500 retry failed' );

$GLOBALS['test_http_calls'] = 0;
$GLOBALS['test_http_queue'] = array(
	new WP_Error( 'http_request_failed', 'Operation timed out after 120000 milliseconds' ),
	mock_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Recovered from timeout' ) ) ), 'usage' => array( 'prompt_tokens' => 4, 'completion_tokens' => 2 ) ) ),
);
$result = cbia_deepseek_chat_call( 'Prompt', '', 2 );
test_assert( $result[0] === true && $GLOBALS['test_http_calls'] === 2 && $result[5]['_cbia_attempts'][0]['status'] === 'timeout', 'timeout retry failed' );

$GLOBALS['test_http_calls'] = 0;
$GLOBALS['test_http_queue'] = array(
	mock_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => '', 'reasoning_content' => 'must not become content' ) ) ) ), array( 'retry-after' => '0' ) ),
	mock_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Recovered from empty content' ) ) ), 'usage' => array( 'prompt_tokens' => 4, 'completion_tokens' => 2 ) ) ),
);
$result = cbia_deepseek_chat_call( 'Prompt', '', 2 );
test_assert( $result[0] === true && $result[1] === 'Recovered from empty content' && $GLOBALS['test_http_calls'] === 2, 'empty content retry failed' );

$GLOBALS['test_http_calls'] = 0;
$GLOBALS['test_http_queue'] = array( mock_response( 200, '{invalid-json' ), mock_response( 200, array() ) );
$result = cbia_deepseek_chat_call( 'Prompt', '', 2 );
test_assert( $result[0] === false && $GLOBALS['test_http_calls'] === 1, 'invalid JSON must fail safely without retry' );

$GLOBALS['test_settings']['deepseek_thinking_mode'] = 'enabled';
$GLOBALS['test_settings']['deepseek_reasoning_effort'] = 'max';
$GLOBALS['test_model'] = 'deepseek-v4-pro';
$GLOBALS['test_http_queue'] = array( mock_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Reasoned result' ) ) ), 'usage' => array( 'prompt_tokens' => 5, 'completion_tokens' => 5, 'completion_tokens_details' => array( 'reasoning_tokens' => 3 ) ) ) ) );
$result = cbia_deepseek_chat_call( 'Prompt', '', 1 );
$sent_payload = json_decode( $GLOBALS['test_last_args']['body'], true );
test_assert( $result[0] === true && $sent_payload['model'] === 'deepseek-v4-pro' && $sent_payload['thinking']['type'] === 'enabled' && $sent_payload['reasoning_effort'] === 'max', 'runtime reasoning payload is invalid' );
test_assert( ! isset( $sent_payload['temperature'] ) && ! isset( $sent_payload['top_p'] ) && ! isset( $sent_payload['presence_penalty'] ) && ! isset( $sent_payload['frequency_penalty'] ), 'reasoning payload contains incompatible sampling parameters' );

echo "DeepSeek V4 tests passed\n";
