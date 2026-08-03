<?php
/**
 * DeepSeek V4 normalization, pricing and migration helpers.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'cbia_deepseek_normalize_runtime_config' ) ) {
	function cbia_deepseek_normalize_runtime_config( $model, $thinking_mode = '', $reasoning_effort = '' ) {
		$requested_model = sanitize_text_field( (string) $model );
		$model           = sanitize_key( $requested_model );
		$thinking_mode   = sanitize_key( (string) $thinking_mode );
		$reasoning_effort = sanitize_key( (string) $reasoning_effort );

		if ( 'deepseek-chat' === $model ) {
			$model         = 'deepseek-v4-flash';
			$thinking_mode = 'disabled';
			$reasoning_effort = 'high';
		} elseif ( 'deepseek-reasoner' === $model ) {
			$model         = 'deepseek-v4-flash';
			$thinking_mode = 'enabled';
			$reasoning_effort = 'high';
		}

		if ( ! in_array( $model, array( 'deepseek-v4-flash', 'deepseek-v4-pro' ), true ) ) {
			$model = 'deepseek-v4-flash';
		}
		if ( ! in_array( $thinking_mode, array( 'disabled', 'enabled' ), true ) ) {
			$thinking_mode = 'disabled';
		}
		if ( ! in_array( $reasoning_effort, array( 'high', 'max' ), true ) ) {
			$reasoning_effort = 'high';
		}

		return array(
			'model_requested'  => $requested_model !== '' ? $requested_model : $model,
			'model_effective'  => $model,
			'thinking'         => $thinking_mode,
			'reasoning_effort' => $reasoning_effort,
		);
	}
}

if ( ! function_exists( 'cbia_deepseek_get_runtime_config' ) ) {
	function cbia_deepseek_get_runtime_config( $model = '' ) {
		$settings = function_exists( 'cbia_get_settings' ) ? cbia_get_settings() : array();
		if ( '' === trim( (string) $model ) ) {
			$model = function_exists( 'cbia_get_text_model_for_provider' )
				? cbia_get_text_model_for_provider( 'deepseek', 'deepseek-v4-flash' )
				: 'deepseek-v4-flash';
		}
		return cbia_deepseek_normalize_runtime_config(
			$model,
			$settings['deepseek_thinking_mode'] ?? 'disabled',
			$settings['deepseek_reasoning_effort'] ?? 'high'
		);
	}
}

if ( ! function_exists( 'cbia_deepseek_build_payload' ) ) {
	function cbia_deepseek_build_payload( array $config, array $messages, $max_tokens, $temperature = 0.5, $minimum_tokens = 256 ) {
		$payload = array(
			'model'      => (string) $config['model_effective'],
			'messages'   => $messages,
			'max_tokens' => max( max( 16, (int) $minimum_tokens ), (int) $max_tokens ),
			'stream'     => false,
			'thinking'   => array( 'type' => (string) $config['thinking'] ),
		);
		if ( 'enabled' === $config['thinking'] ) {
			$payload['reasoning_effort'] = (string) $config['reasoning_effort'];
		} else {
			$payload['temperature'] = max( 0.0, min( 2.0, (float) $temperature ) );
		}
		return $payload;
	}
}

if ( ! function_exists( 'cbia_deepseek_parse_usage' ) ) {
	function cbia_deepseek_parse_usage( $data ) {
		$usage = is_array( $data ) && is_array( $data['usage'] ?? null ) ? $data['usage'] : array();
		$input = max( 0, (int) ( $usage['prompt_tokens'] ?? 0 ) );
		$output = max( 0, (int) ( $usage['completion_tokens'] ?? 0 ) );
		$has_hit = array_key_exists( 'prompt_cache_hit_tokens', $usage );
		$has_miss = array_key_exists( 'prompt_cache_miss_tokens', $usage );
		$hit = $has_hit ? max( 0, (int) $usage['prompt_cache_hit_tokens'] ) : 0;
		$miss = $has_miss ? max( 0, (int) $usage['prompt_cache_miss_tokens'] ) : max( 0, $input - $hit );
		$details = is_array( $usage['completion_tokens_details'] ?? null ) ? $usage['completion_tokens_details'] : array();
		$reasoning = max( 0, (int) ( $usage['reasoning_tokens'] ?? ( $details['reasoning_tokens'] ?? 0 ) ) );
		$reasoning = min( $output, $reasoning );
		return array(
			'input_tokens'              => $input,
			'cached_input_tokens'       => min( $input, $hit ),
			'cache_hit_tokens'          => min( $input, $hit ),
			'cache_miss_tokens'         => min( $input, $miss ),
			'cache_breakdown_available' => ( $has_hit || $has_miss ) ? 1 : 0,
			'output_tokens'             => $output,
			'completion_tokens'         => $output,
			'reasoning_tokens'          => $reasoning,
			'visible_output_tokens_estimated' => max( 0, $output - $reasoning ),
			'total_tokens'              => max( 0, (int) ( $usage['total_tokens'] ?? ( $input + $output ) ) ),
		);
	}
}

if ( ! function_exists( 'cbia_deepseek_pricing_catalog' ) ) {
	function cbia_deepseek_pricing_catalog() {
		$catalog = array();
		foreach ( function_exists( 'cbia_provider_catalog_model_ids' ) ? cbia_provider_catalog_model_ids( 'deepseek', 'text', true ) : array() as $model ) {
			$period = cbia_provider_catalog_price_period( 'deepseek', $model );
			if ( empty( $period ) ) continue;
			$catalog[ $model ] = array(
				'cache_hit'  => (int) ( $period['cached_input_price_micro_usd_per_mtok'] ?? $period['input_price_micro_usd_per_mtok'] ),
				'cache_miss' => (int) $period['input_price_micro_usd_per_mtok'],
				'output'     => (int) $period['output_price_micro_usd_per_mtok'],
			);
		}
		return apply_filters( 'cbia_deepseek_pricing_catalog', $catalog );
	}
}

if ( ! function_exists( 'cbia_deepseek_calculate_cost' ) ) {
	/**
	 * Calculate DeepSeek cost in integer micro-USD from API usage.
	 * Missing cache breakdown is conservatively priced as cache miss.
	 */
	function cbia_deepseek_calculate_cost( $model, array $usage ) {
		$config  = cbia_deepseek_normalize_runtime_config( $model );
		$model   = $config['model_effective'];
		$catalog = cbia_deepseek_pricing_catalog();
		if ( ! isset( $catalog[ $model ] ) ) {
			return array( 'cost_micro_usd' => null, 'cost_status' => 'unknown', 'cost_reason' => 'model_without_pricing' );
		}

		$input  = max( 0, (int) ( $usage['input_tokens'] ?? 0 ) );
		$output = max( 0, (int) ( $usage['output_tokens'] ?? 0 ) );
		if ( 0 === $input + $output ) {
			return array( 'cost_micro_usd' => null, 'cost_status' => 'unknown', 'cost_reason' => 'missing_token_usage' );
		}

		$has_breakdown = ! empty( $usage['cache_breakdown_available'] );
		$hit  = $has_breakdown ? min( $input, max( 0, (int) ( $usage['cache_hit_tokens'] ?? 0 ) ) ) : 0;
		$miss = $has_breakdown
			? min( $input, max( 0, (int) ( $usage['cache_miss_tokens'] ?? ( $input - $hit ) ) ) )
			: $input;
		if ( $has_breakdown && $hit + $miss < $input ) {
			$miss += $input - $hit - $miss;
		}

		$price = $catalog[ $model ];
		$numerator = ( $hit * (int) $price['cache_hit'] )
			+ ( $miss * (int) $price['cache_miss'] )
			+ ( $output * (int) $price['output'] );

		return array(
			'cost_micro_usd' => max( 0, (int) round( $numerator / 1000000 ) ),
			'cost_status'    => $has_breakdown ? 'exact' : 'estimated',
			'cost_reason'    => $has_breakdown ? 'api_usage' : 'cache_breakdown_missing_all_input_priced_as_miss',
		);
	}
}

if ( ! function_exists( 'cbia_deepseek_timeout_seconds' ) ) {
	function cbia_deepseek_timeout_seconds( $thinking_mode, $model ) {
		$timeout = 'enabled' === $thinking_mode ? 180 : 120;
		return max( 30, (int) apply_filters( 'cbia_deepseek_timeout', $timeout, $thinking_mode, $model ) );
	}
}

if ( ! function_exists( 'cbia_deepseek_is_retryable_http_code' ) ) {
	function cbia_deepseek_is_retryable_http_code( $code ) {
		return in_array( (int) $code, array( 408, 429, 500, 502, 503, 504 ), true );
	}
}

if ( ! function_exists( 'cbia_deepseek_retry_after_seconds' ) ) {
	function cbia_deepseek_retry_after_seconds( $response, $attempt ) {
		$header = is_array( $response ) ? trim( (string) wp_remote_retrieve_header( $response, 'retry-after' ) ) : '';
		if ( ctype_digit( $header ) ) {
			return max( 0, (int) $header );
		}
		if ( '' !== $header ) {
			$retry_at = strtotime( $header );
			if ( false !== $retry_at ) return max( 0, $retry_at - time() );
		}
		return min( 4, max( 1, (int) $attempt ) );
	}
}

if ( ! function_exists( 'cbia_deepseek_wait_before_retry' ) ) {
	function cbia_deepseek_wait_before_retry( $response, $attempt ) {
		$delay = cbia_deepseek_retry_after_seconds( $response, $attempt );
		$max_delay = max( 0, (int) apply_filters( 'cbia_deepseek_max_retry_after', 120 ) );
		if ( $delay > $max_delay ) {
			return false;
		}
		if ( $delay > 0 ) sleep( $delay );
		return true;
	}
}

if ( ! function_exists( 'cbia_deepseek_maybe_migrate_legacy_settings' ) ) {
	function cbia_deepseek_maybe_migrate_legacy_settings() {
		$marker = 'deepseek_v4_20260714_v1';
		if ( get_option( 'cbia_deepseek_migration_version', '' ) === $marker ) return;

		$settings = get_option( 'cbia_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$legacy_model = sanitize_key( (string) ( $settings['text_model'] ?? '' ) );
		if ( in_array( $legacy_model, array( 'deepseek-chat', 'deepseek-reasoner' ), true ) ) {
			$normalized = cbia_deepseek_normalize_runtime_config( $legacy_model, '', 'high' );
			$settings['text_model'] = $normalized['model_effective'];
			$settings['deepseek_thinking_mode'] = $normalized['thinking'];
		}
		if ( ! isset( $settings['deepseek_thinking_mode'] ) ) $settings['deepseek_thinking_mode'] = 'disabled';
		if ( ! isset( $settings['deepseek_reasoning_effort'] ) ) $settings['deepseek_reasoning_effort'] = 'high';
		update_option( 'cbia_settings', $settings, false );

		$providers = get_option( 'cbia_provider_settings', array() );
		$providers = is_array( $providers ) ? $providers : array();
		$provider_model = sanitize_key( (string) ( $providers['providers']['deepseek']['model'] ?? '' ) );
		if ( in_array( $provider_model, array( 'deepseek-chat', 'deepseek-reasoner' ), true ) ) {
			$provider_config = cbia_deepseek_normalize_runtime_config( $provider_model, '', 'high' );
			$providers['providers']['deepseek']['model'] = $provider_config['model_effective'];
			update_option( 'cbia_provider_settings', $providers, false );
			$current_provider = sanitize_key( (string) ( $settings['text_provider'] ?? ( $providers['provider'] ?? ( $providers['current_provider'] ?? '' ) ) ) );
			if ( 'deepseek' === $current_provider ) {
				$settings['text_model'] = $provider_config['model_effective'];
				$settings['deepseek_thinking_mode'] = $provider_config['thinking'];
				$settings['deepseek_reasoning_effort'] = 'high';
				update_option( 'cbia_settings', $settings, false );
			}
		}

		$model_lists = get_option( 'cbia_provider_model_lists', array() );
		if ( is_array( $model_lists ) && isset( $model_lists['deepseek'] ) ) {
			$model_lists['deepseek'] = array( 'deepseek-v4-flash', 'deepseek-v4-pro' );
			update_option( 'cbia_provider_model_lists', $model_lists, false );
		}
		update_option( 'cbia_deepseek_migration_version', $marker, false );
		if ( function_exists( 'cbia_log' ) ) cbia_log( 'DeepSeek V4 settings migration completed.', 'INFO' );
	}
	add_action( 'admin_init', 'cbia_deepseek_maybe_migrate_legacy_settings', 5 );
}
