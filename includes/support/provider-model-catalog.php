<?php
/**
 * Canonical provider model, capability and pricing catalog.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'cbia_provider_model_catalog_version' ) ) {
	function cbia_provider_model_catalog_version(): string {
		return 'providers-2026-08-03-v1';
	}
}

if ( ! function_exists( 'cbia_provider_model_catalog' ) ) {
	function cbia_provider_model_catalog(): array {
		$text = static function ( $name, $status, $recommended, $default, $api_family, $reasoning, $levels, $temperature, $verbosity, $cache, $prices, $verified, $source, $group = 'current', $aliases = array() ) {
			return array(
				'display_name' => $name, 'capabilities' => array( 'text' ), 'status' => $status,
				'recommended' => (bool) $recommended, 'default' => (bool) $default, 'group' => $group,
				'api_family' => $api_family, 'supports_reasoning' => (bool) $reasoning,
				'reasoning_levels' => $levels, 'supports_temperature' => (bool) $temperature,
				'supports_verbosity' => (bool) $verbosity, 'supports_prompt_cache' => (bool) $cache,
				'supports_image_generation' => false, 'supported_sizes' => array(), 'supported_qualities' => array(),
				'pricing' => $prices, 'image_price_rules' => array(), 'verified_at' => $verified,
				'source_reference' => $source, 'legacy_aliases' => $aliases,
			);
		};
		$image = static function ( $name, $status, $recommended, $default, $sizes, $qualities, $rules, $verified, $source, $group = 'current', $aliases = array() ) {
			return array(
				'display_name' => $name, 'capabilities' => array( 'image' ), 'status' => $status,
				'recommended' => (bool) $recommended, 'default' => (bool) $default, 'group' => $group,
				'api_family' => 'image_generation', 'supports_reasoning' => false, 'reasoning_levels' => array(),
				'supports_temperature' => false, 'supports_verbosity' => false, 'supports_prompt_cache' => false,
				'supports_image_generation' => true, 'supported_sizes' => $sizes, 'supported_qualities' => $qualities,
				'pricing' => array(), 'image_price_rules' => $rules, 'verified_at' => $verified,
				'source_reference' => $source, 'legacy_aliases' => $aliases,
			);
		};
		$price = static function ( $in, $cached, $out, $from = null, $until = null, $cache_write = null ) {
			return array( array(
				'effective_from' => $from, 'effective_until' => $until,
				'input_price_micro_usd_per_mtok' => (int) $in,
				'cached_input_price_micro_usd_per_mtok' => null === $cached ? null : (int) $cached,
				'cache_write_price_micro_usd_per_mtok' => null === $cache_write ? null : (int) $cache_write,
				'output_price_micro_usd_per_mtok' => (int) $out,
			) );
		};

		$openai_source = 'https://developers.openai.com/api/docs/pricing';
		$google_source = 'https://ai.google.dev/gemini-api/docs/pricing';
		$deepseek_source = 'https://api-docs.deepseek.com/quick_start/pricing/';
		$openai_sizes = array( '1024x1024', '1024x1536', '1536x1024' );
		$openai_qualities = array( 'auto', 'low', 'medium', 'high' );

		$catalog = array(
			'openai' => array(
				'label' => 'OpenAI', 'capabilities' => array( 'text', 'image' ),
				'default_text_model' => 'gpt-5-mini', 'recommended_text_model' => 'gpt-5-mini',
				'default_image_model' => 'gpt-image-2', 'recommended_image_model' => 'gpt-image-2',
				'models' => array(
					'gpt-5-mini' => $text( 'GPT-5 Mini', 'stable', true, true, 'responses', true, array( 'minimal', 'low', 'medium', 'high' ), false, true, true, $price( 250000, 25000, 2000000 ), '2026-08-03', $openai_source, 'recommended' ),
					'gpt-5.6-sol' => $text( 'GPT-5.6 Sol', 'stable', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high', 'xhigh', 'max' ), false, true, true, $price( 5000000, 500000, 30000000, null, null, 6250000 ), '2026-08-03', $openai_source, 'high_quality', array( 'gpt-5.6' ) ),
					'gpt-5.6-terra' => $text( 'GPT-5.6 Terra', 'stable', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high', 'xhigh', 'max' ), false, true, true, $price( 2000000, 200000, 12000000, null, null, 2500000 ), '2026-08-03', $openai_source, 'current' ),
					'gpt-5.6-luna' => $text( 'GPT-5.6 Luna', 'stable', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high', 'xhigh', 'max' ), false, true, true, $price( 200000, 20000, 1200000, null, null, 250000 ), '2026-08-03', $openai_source, 'economic' ),
					'gpt-5.5' => $text( 'GPT-5.5', 'stable', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high', 'xhigh' ), false, true, true, $price( 5000000, 500000, 30000000 ), '2026-08-03', $openai_source ),
					'gpt-5.4' => $text( 'GPT-5.4', 'stable', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high', 'xhigh' ), false, true, true, $price( 2500000, 250000, 15000000 ), '2026-08-03', $openai_source ),
					'gpt-5.4-mini' => $text( 'GPT-5.4 Mini', 'stable', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high' ), false, true, true, $price( 750000, 75000, 4500000 ), '2026-08-03', $openai_source ),
					'gpt-5.2' => $text( 'GPT-5.2', 'compatibility', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high' ), false, true, true, $price( 1750000, 175000, 14000000 ), '2026-08-03', $openai_source, 'compatibility' ),
					'gpt-5.1' => $text( 'GPT-5.1', 'compatibility', false, false, 'responses', true, array( 'none', 'low', 'medium', 'high' ), false, true, true, $price( 1250000, 125000, 10000000 ), '2026-08-03', $openai_source, 'compatibility' ),
					'gpt-5' => $text( 'GPT-5', 'compatibility', false, false, 'responses', true, array( 'minimal', 'low', 'medium', 'high' ), false, true, true, $price( 1250000, 125000, 10000000 ), '2026-08-03', $openai_source, 'compatibility', array( 'gpt-5-chat-latest', 'gpt-5-codex' ) ),
					'gpt-5-nano' => $text( 'GPT-5 Nano', 'stable', false, false, 'responses', true, array( 'minimal', 'low', 'medium', 'high' ), false, true, true, $price( 50000, 5000, 400000 ), '2026-08-03', $openai_source, 'economic' ),
					'gpt-4.1' => $text( 'GPT-4.1', 'compatibility', false, false, 'responses', false, array(), true, false, true, $price( 2000000, 500000, 8000000 ), '2026-08-03', $openai_source, 'compatibility' ),
					'gpt-4.1-mini' => $text( 'GPT-4.1 Mini', 'compatibility', false, false, 'responses', false, array(), true, false, true, $price( 400000, 100000, 1600000 ), '2026-08-03', $openai_source, 'compatibility' ),
					'gpt-4.1-nano' => $text( 'GPT-4.1 Nano', 'compatibility', false, false, 'responses', false, array(), true, false, true, $price( 100000, 25000, 400000 ), '2026-08-03', $openai_source, 'compatibility' ),
					'gpt-image-2' => $image( 'GPT Image 2', 'stable', true, true, $openai_sizes, $openai_qualities, array(
						'low' => array( '1024x1024' => 6000, '1024x1536' => 5000, '1536x1024' => 5000 ),
						'medium' => array( '1024x1024' => 53000, '1024x1536' => 41000, '1536x1024' => 41000 ),
						'high' => array( '1024x1024' => 211000, '1024x1536' => 165000, '1536x1024' => 165000 ),
					), '2026-07-10', $openai_source, 'recommended' ),
					'gpt-image-1' => $image( 'GPT Image 1', 'stable', false, false, $openai_sizes, $openai_qualities, array(
						'low' => array( '1024x1024' => 11000, '1024x1536' => 16000, '1536x1024' => 16000 ),
						'medium' => array( '1024x1024' => 42000, '1024x1536' => 63000, '1536x1024' => 63000 ),
						'high' => array( '1024x1024' => 167000, '1024x1536' => 250000, '1536x1024' => 250000 ),
					), '2026-07-10', $openai_source ),
					'gpt-image-1-mini' => $image( 'GPT Image 1 Mini', 'stable', false, false, $openai_sizes, $openai_qualities, array(
						'low' => array( '1024x1024' => 5000, '1024x1536' => 6000, '1536x1024' => 6000 ),
						'medium' => array( '1024x1024' => 11000, '1024x1536' => 15000, '1536x1024' => 15000 ),
						'high' => array( '1024x1024' => 36000, '1024x1536' => 52000, '1536x1024' => 52000 ),
					), '2026-07-10', $openai_source, 'economic' ),
				),
			),
			'google' => array(
				'label' => 'Google (Gemini)', 'capabilities' => array( 'text', 'image' ),
				'default_text_model' => 'gemini-2.5-flash', 'recommended_text_model' => 'gemini-3.6-flash',
				'default_image_model' => 'imagen-3.0-generate-002', 'recommended_image_model' => 'gemini-3.1-flash-image',
				'models' => array(
					'gemini-3.6-flash' => $text( 'Gemini 3.6 Flash', 'stable', true, false, 'generate_content', true, array( 'minimal', 'low', 'medium', 'high' ), false, false, true, $price( 1500000, 150000, 7500000 ), '2026-08-03', $google_source, 'recommended' ),
					'gemini-3.5-flash' => $text( 'Gemini 3.5 Flash', 'stable', false, false, 'generate_content', true, array( 'minimal', 'low', 'medium', 'high' ), false, false, true, $price( 1500000, 150000, 9000000 ), '2026-08-03', $google_source ),
					'gemini-3.5-flash-lite' => $text( 'Gemini 3.5 Flash-Lite', 'stable', false, false, 'generate_content', true, array( 'minimal', 'low', 'medium', 'high' ), false, false, true, $price( 300000, 30000, 2500000 ), '2026-08-03', $google_source, 'economic' ),
					'gemini-2.5-pro' => $text( 'Gemini 2.5 Pro', 'compatibility', false, false, 'generate_content', true, array( 'low', 'medium', 'high' ), true, false, true, $price( 1250000, 125000, 10000000 ), '2026-08-03', $google_source, 'compatibility' ),
					'gemini-2.5-flash' => $text( 'Gemini 2.5 Flash', 'compatibility', false, true, 'generate_content', true, array( 'low', 'medium', 'high' ), true, false, true, $price( 300000, 30000, 2500000 ), '2026-08-03', $google_source, 'compatibility' ),
					'gemini-2.5-flash-lite' => $text( 'Gemini 2.5 Flash-Lite', 'compatibility', false, false, 'generate_content', true, array( 'low', 'medium', 'high' ), true, false, true, $price( 100000, 10000, 400000 ), '2026-08-03', $google_source, 'compatibility' ),
					'gemini-3.1-flash-image' => $image( 'Nano Banana 2', 'stable', true, false, array( '512x512', '1024x1024', '2048x2048', '4096x4096' ), array( 'standard' ), array( 'standard' => array( '512x512' => 45000, '1024x1024' => 67000, '2048x2048' => 101000, '4096x4096' => 151000 ) ), '2026-08-03', $google_source, 'recommended' ),
					'gemini-3.1-flash-lite-image' => $image( 'Nano Banana 2 Lite', 'stable', false, false, array( '1024x1024' ), array( 'standard' ), array( 'standard' => array( '1024x1024' => 33600 ) ), '2026-08-03', $google_source, 'economic' ),
					'gemini-3-pro-image' => $image( 'Nano Banana Pro', 'stable', false, false, array( '1024x1024', '2048x2048', '4096x4096' ), array( 'standard' ), array( 'standard' => array( '1024x1024' => 134000, '2048x2048' => 134000, '4096x4096' => 240000 ) ), '2026-08-03', $google_source, 'high_quality' ),
					'imagen-3.0-generate-002' => $image( 'Imagen 3', 'deprecated', false, true, array(), array(), array(), '2026-08-03', 'https://ai.google.dev/gemini-api/docs/deprecations', 'compatibility', array( 'imagen-2', 'imagen-3.0-generate-001' ) ),
					'imagen-4.0-generate-001' => $image( 'Imagen 4', 'deprecated', false, false, array(), array(), array(), '2026-08-03', 'https://ai.google.dev/gemini-api/docs/deprecations', 'compatibility' ),
				),
			),
			'deepseek' => array(
				'label' => 'DeepSeek', 'capabilities' => array( 'text' ),
				'default_text_model' => 'deepseek-v4-flash', 'recommended_text_model' => 'deepseek-v4-flash',
				'default_image_model' => '', 'recommended_image_model' => '',
				'models' => array(
					'deepseek-v4-flash' => $text( 'DeepSeek V4 Flash', 'stable', true, true, 'openai_chat', true, array( 'disabled', 'low', 'medium', 'high' ), true, false, true, $price( 140000, 2800, 280000 ), '2026-07-14', $deepseek_source, 'recommended', array( 'deepseek-chat', 'deepseek-reasoner' ) ),
					'deepseek-v4-pro' => $text( 'DeepSeek V4 Pro', 'stable', false, false, 'openai_chat', true, array( 'disabled', 'low', 'medium', 'high' ), true, false, true, $price( 435000, 3625, 870000 ), '2026-07-14', $deepseek_source, 'high_quality' ),
				),
			),
		);

		return (array) apply_filters( 'cbia_provider_model_catalog', $catalog );
	}
}

if ( ! function_exists( 'cbia_provider_catalog_get' ) ) {
	function cbia_provider_catalog_get( string $provider ): array {
		$catalog = cbia_provider_model_catalog();
		return isset( $catalog[ $provider ] ) && is_array( $catalog[ $provider ] ) ? $catalog[ $provider ] : array();
	}
}

if ( ! function_exists( 'cbia_provider_catalog_model' ) ) {
	function cbia_provider_catalog_model( string $provider, string $model ): array {
		$provider_data = cbia_provider_catalog_get( $provider );
		if ( isset( $provider_data['models'][ $model ] ) ) return $provider_data['models'][ $model ];
		foreach ( (array) ( $provider_data['models'] ?? array() ) as $definition ) {
			if ( in_array( $model, (array) ( $definition['legacy_aliases'] ?? array() ), true ) ) return $definition;
		}
		return array();
	}
}

if ( ! function_exists( 'cbia_provider_catalog_model_ids' ) ) {
	function cbia_provider_catalog_model_ids( string $provider, string $capability, bool $include_preview = false ): array {
		$provider_data = cbia_provider_catalog_get( $provider );
		$ids = array();
		foreach ( (array) ( $provider_data['models'] ?? array() ) as $id => $definition ) {
			if ( ! in_array( $capability, (array) ( $definition['capabilities'] ?? array() ), true ) ) continue;
			if ( ! $include_preview && 'preview' === ( $definition['status'] ?? '' ) ) continue;
			$ids[] = (string) $id;
		}
		return $ids;
	}
}

if ( ! function_exists( 'cbia_provider_catalog_recommended_model' ) ) {
	function cbia_provider_catalog_recommended_model( string $provider, string $capability ): string {
		$data = cbia_provider_catalog_get( $provider );
		$key = 'image' === $capability ? 'recommended_image_model' : 'recommended_text_model';
		return (string) ( $data[ $key ] ?? '' );
	}
}

if ( ! function_exists( 'cbia_provider_catalog_price_period' ) ) {
	function cbia_provider_catalog_price_period( string $provider, string $model, $timestamp = null ): array {
		$definition = cbia_provider_catalog_model( $provider, $model );
		$when = null === $timestamp ? time() : ( is_numeric( $timestamp ) ? (int) $timestamp : strtotime( (string) $timestamp ) );
		foreach ( (array) ( $definition['pricing'] ?? array() ) as $period ) {
			$from = empty( $period['effective_from'] ) ? null : strtotime( (string) $period['effective_from'] );
			$until = empty( $period['effective_until'] ) ? null : strtotime( (string) $period['effective_until'] );
			if ( ( null === $from || $when >= $from ) && ( null === $until || $when <= $until ) ) return $period;
		}
		return array();
	}
}

if ( ! function_exists( 'cbia_provider_catalog_text_price_table' ) ) {
	function cbia_provider_catalog_text_price_table( $timestamp = null ): array {
		$table = array();
		foreach ( cbia_provider_model_catalog() as $provider => $provider_data ) {
			foreach ( (array) ( $provider_data['models'] ?? array() ) as $id => $definition ) {
				if ( ! in_array( 'text', (array) ( $definition['capabilities'] ?? array() ), true ) ) continue;
				$period = cbia_provider_catalog_price_period( (string) $provider, (string) $id, $timestamp );
				if ( empty( $period ) ) continue;
				$row = array(
					'in' => (int) $period['input_price_micro_usd_per_mtok'] / 1000000,
					'cin' => null === $period['cached_input_price_micro_usd_per_mtok'] ? (int) $period['input_price_micro_usd_per_mtok'] / 1000000 : (int) $period['cached_input_price_micro_usd_per_mtok'] / 1000000,
					'out' => (int) $period['output_price_micro_usd_per_mtok'] / 1000000,
				);
				$table[ $id ] = $row;
				foreach ( (array) ( $definition['legacy_aliases'] ?? array() ) as $alias ) $table[ $alias ] = $row;
			}
		}
		return $table;
	}
}

if ( ! function_exists( 'cbia_provider_catalog_image_price_rules' ) ) {
	function cbia_provider_catalog_image_price_rules( string $provider, string $model ): array {
		$definition = cbia_provider_catalog_model( $provider, $model );
		return (array) ( $definition['image_price_rules'] ?? array() );
	}
}

if ( ! function_exists( 'cbia_provider_catalog_image_price_micro_usd' ) ) {
	function cbia_provider_catalog_image_price_micro_usd( string $provider, string $model, string $quality, string $size ): ?int {
		$rules = cbia_provider_catalog_image_price_rules( $provider, $model );
		if ( isset( $rules[ $quality ][ $size ] ) && is_numeric( $rules[ $quality ][ $size ] ) ) {
			return max( 0, (int) $rules[ $quality ][ $size ] );
		}
		return null;
	}
}
