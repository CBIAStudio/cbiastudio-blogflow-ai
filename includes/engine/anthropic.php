<?php
/**
 * Anthropic Messages API client.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'cbia_anthropic_headers' ) ) {
	function cbia_anthropic_headers( string $api_key ): array {
		return array(
			'content-type' => 'application/json',
			'x-api-key' => $api_key,
			'anthropic-version' => '2023-06-01',
		);
	}
}

if ( ! function_exists( 'cbia_anthropic_normalize_stop_reason' ) ) {
	function cbia_anthropic_normalize_stop_reason( $reason ): string {
		$reason = sanitize_key( (string) $reason );
		if ( in_array( $reason, array( 'end_turn', 'stop_sequence' ), true ) ) return 'complete';
		if ( 'max_tokens' === $reason ) return 'output_limit';
		if ( 'tool_use' === $reason ) return 'invalid_for_article';
		return 'unknown';
	}
}

if ( ! function_exists( 'cbia_anthropic_parse_response' ) ) {
	function cbia_anthropic_parse_response( $data ): array {
		$text_parts = array();
		if ( is_array( $data ) && ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) && isset( $block['text'] ) ) {
					$text_parts[] = (string) $block['text'];
				}
			}
		}
		$usage_data = is_array( $data['usage'] ?? null ) ? $data['usage'] : array();
		$input = max( 0, (int) ( $usage_data['input_tokens'] ?? 0 ) );
		$output = max( 0, (int) ( $usage_data['output_tokens'] ?? 0 ) );
		$cache_creation = max( 0, (int) ( $usage_data['cache_creation_input_tokens'] ?? 0 ) );
		$cache_read = max( 0, (int) ( $usage_data['cache_read_input_tokens'] ?? 0 ) );
		$cache_creation_detail = is_array( $usage_data['cache_creation'] ?? null ) ? $usage_data['cache_creation'] : array();
		$cache_creation_5m = max( 0, (int) ( $cache_creation_detail['ephemeral_5m_input_tokens'] ?? 0 ) );
		$cache_creation_1h = max( 0, (int) ( $cache_creation_detail['ephemeral_1h_input_tokens'] ?? 0 ) );
		if ( 0 === $cache_creation_5m + $cache_creation_1h && $cache_creation > 0 ) $cache_creation_5m = $cache_creation;
		$usage = array(
			'input_tokens' => $input,
			'cached_input_tokens' => $cache_read,
			'cached_tokens_reported' => array_key_exists( 'cache_read_input_tokens', $usage_data ) ? 1 : 0,
			'cache_creation_input_tokens' => $cache_creation,
			'cache_creation_5m_input_tokens' => $cache_creation_5m,
			'cache_creation_1h_input_tokens' => $cache_creation_1h,
			'cache_read_input_tokens' => $cache_read,
			'output_tokens' => $output,
			'total_tokens' => $input + $cache_creation + $cache_read + $output,
		);
		$stop_reason = sanitize_key( (string) ( $data['stop_reason'] ?? '' ) );
		return array(
			'text' => trim( implode( "\n", $text_parts ) ),
			'usage' => $usage,
			'stop_reason' => $stop_reason,
			'completion_status' => cbia_anthropic_normalize_stop_reason( $stop_reason ),
			'id' => sanitize_text_field( (string) ( $data['id'] ?? '' ) ),
			'model' => sanitize_text_field( (string) ( $data['model'] ?? '' ) ),
			'role' => sanitize_key( (string) ( $data['role'] ?? '' ) ),
			'stop_sequence' => sanitize_text_field( (string) ( $data['stop_sequence'] ?? '' ) ),
			'service_tier' => sanitize_key( (string) ( $usage_data['service_tier'] ?? ( $data['service_tier'] ?? '' ) ) ),
		);
	}
}

if ( ! function_exists( 'cbia_anthropic_messages_call' ) ) {
	/** Returns [ok, text, usage, effective_model, error, raw]. */
	function cbia_anthropic_messages_call( $prompt, $system = '', $tries = 2, $max_output_override = 0, $context = array() ) {
		$context = is_array( $context ) ? $context : array();
		$api_key = function_exists( 'cbia_get_provider_api_key' ) ? cbia_get_provider_api_key( 'anthropic' ) : '';
		$model = ! empty( $context['model'] )
			? sanitize_text_field( (string) $context['model'] )
			: ( function_exists( 'cbia_get_text_model_for_provider' ) ? cbia_get_text_model_for_provider( 'anthropic', 'claude-sonnet-5' ) : 'claude-sonnet-5' );
		if ( '' === $api_key ) {
			$error = __( 'No API key (Anthropic)', 'cbiastudio-blogflow-ai' );
			return array( false, '', cbia_usage_empty(), $model, $error, cbia_local_blocked_attempt_meta( 'text', 'anthropic', $model, 'missing_api_key', $error ) );
		}
		$settings = cbia_get_settings();
		$phase = sanitize_key( (string) ( $context['phase'] ?? '' ) );
		$max_tokens = (int) $max_output_override > 0 ? (int) $max_output_override : (int) ( $settings['responses_max_output_tokens'] ?? 6000 );
		$max_tokens = 'configuration_test' === $phase ? max( 16, min( 64, $max_tokens ) ) : max( 256, min( 12000, $max_tokens ) );
		$payload = array(
			'model' => $model,
			'max_tokens' => $max_tokens,
			'messages' => array( array( 'role' => 'user', 'content' => (string) $prompt ) ),
		);
		if ( '' !== $system ) $payload['system'] = (string) $system;
		$attempts = array();
		$last_error = __( 'Could not get a response.', 'cbiastudio-blogflow-ai' );
		$last_usage = cbia_usage_empty();
		$tries = max( 1, (int) $tries );
		for ( $attempt = 1; $attempt <= $tries; $attempt++ ) {
			if ( empty( $context['ignore_stop'] ) && cbia_is_stop_requested() ) {
				return array( false, '', cbia_usage_empty(), $model, __( 'Stop enabled', 'cbiastudio-blogflow-ai' ), cbia_attach_attempts_meta( array(), $attempts ) );
			}
			cbia_log( sprintf( 'Anthropic Messages: model=%s attempt %d/%d', $model, $attempt, $tries ), 'INFO' );
			$started = microtime( true );
			$response = cbia_provider_safe_remote_post( 'anthropic', 'https://api.anthropic.com/v1/messages', array(
				'body' => wp_json_encode( $payload ),
				'timeout' => 'configuration_test' === $phase ? 30 : ( 'expand' === $phase ? 150 : 90 ),
			), $api_key );
			$elapsed_ms = max( 0, (int) round( ( microtime( true ) - $started ) * 1000 ) );
			if ( is_wp_error( $response ) ) {
				$last_error = function_exists( 'cbia_mask_sensitive_log_text' ) ? cbia_mask_sensitive_log_text( $response->get_error_message() ) : sanitize_text_field( $response->get_error_message() );
				$is_timeout = false !== stripos( $last_error, 'timeout' ) || false !== stripos( $last_error, 'timed out' );
				$attempts[] = array( 'type' => 'text', 'provider' => 'anthropic', 'model' => $model, 'model_requested' => $model, 'model_effective' => $model, 'attempt' => $attempt, 'ok' => 0, 'request_sent' => 1, 'status' => $is_timeout ? 'timeout' : 'error', 'elapsed_ms' => $elapsed_ms, 'error' => $last_error );
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$request_id = sanitize_text_field( (string) wp_remote_retrieve_header( $response, 'request-id' ) );
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) ) {
				$last_error = __( 'Anthropic returned invalid JSON.', 'cbiastudio-blogflow-ai' );
				$attempts[] = array( 'type' => 'text', 'provider' => 'anthropic', 'model' => $model, 'model_effective' => $model, 'attempt' => $attempt, 'ok' => 0, 'request_sent' => 1, 'status' => 'error', 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'error' => $last_error );
				continue;
			}
			$parsed = cbia_anthropic_parse_response( $data );
			$last_usage = $parsed['usage'];
			if ( $code < 200 || $code >= 300 ) {
				$message = sanitize_text_field( (string) ( $data['error']['message'] ?? '' ) );
				if ( function_exists( 'cbia_mask_sensitive_log_text' ) ) $message = cbia_mask_sensitive_log_text( $message );
				$last_error = 'HTTP ' . $code . ( '' !== $message ? ' | ' . $message : '' );
				$attempts[] = array_merge( $last_usage, array( 'type' => 'text', 'provider' => 'anthropic', 'model' => $model, 'model_effective' => $model, 'attempt' => $attempt, 'ok' => 0, 'request_sent' => 1, 'status' => in_array( $code, array( 401, 403 ), true ) ? 'authentication_error' : 'error', 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'error' => $last_error ) );
				if ( ! in_array( $code, array( 408, 429, 500, 502, 503, 504 ), true ) ) break;
				continue;
			}
			$effective_model = '' !== $parsed['model'] ? $parsed['model'] : $model;
			$status = $parsed['completion_status'];
			$ok = 'complete' === $status && '' !== $parsed['text'];
			if ( 'invalid_for_article' === $status ) $last_error = __( 'Anthropic returned tool use instead of article text.', 'cbiastudio-blogflow-ai' );
			elseif ( 'output_limit' === $status ) $last_error = __( 'Anthropic reached the output token limit.', 'cbiastudio-blogflow-ai' );
			elseif ( '' === $parsed['text'] ) $last_error = __( 'Anthropic returned no text content.', 'cbiastudio-blogflow-ai' );
			else $last_error = '';
			$data['_cbia_request_meta'] = array_merge( $last_usage, array(
				'provider' => 'anthropic', 'model_requested' => $model, 'model_effective' => $effective_model,
				'http_code' => $code, 'request_id' => $request_id, 'provider_response_id' => $parsed['id'],
				'role' => $parsed['role'], 'stop_reason' => $parsed['stop_reason'], 'stop_sequence' => $parsed['stop_sequence'],
				'service_tier' => $parsed['service_tier'], 'elapsed_ms' => $elapsed_ms, 'attempt' => $attempt,
				'ok' => $ok ? 1 : 0, 'status' => $ok ? 'success' : $status, 'completion_status' => $status,
				'finish_reason' => $parsed['stop_reason'], 'provider_status' => $parsed['stop_reason'],
			) );
			cbia_log( sprintf( 'Anthropic %s: model=%s tokens_in=%d cache_create=%d cache_read=%d tokens_out=%d stop_reason=%s', $ok ? 'OK' : 'incomplete', $effective_model, $last_usage['input_tokens'], $last_usage['cache_creation_input_tokens'], $last_usage['cache_read_input_tokens'], $last_usage['output_tokens'], $parsed['stop_reason'] ?: 'unknown' ), $ok ? 'INFO' : 'WARN' );
			return array( $ok, $parsed['text'], $last_usage, $effective_model, $last_error, cbia_attach_attempts_meta( $data, $attempts ) );
		}
		return array( false, '', $last_usage, $model, $last_error, cbia_attach_attempts_meta( array(), $attempts ) );
	}
}
