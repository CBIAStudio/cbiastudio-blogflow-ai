<?php
/**
 * Central OpenAI image configuration and pricing catalog.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'CBIA_Image_Pricing_Service' ) ) {
	final class CBIA_Image_Pricing_Service {
		public const PRICE_LAST_VERIFIED = '2026-07-10';

		public static function get_models(): array {
			return array( 'gpt-image-2', 'gpt-image-1', 'gpt-image-1-mini' );
		}

		public static function get_qualities(): array {
			return array(
				'auto'   => _x( 'Automatic', 'image quality', 'cbiastudio-blogflow-ai' ),
				'low'    => _x( 'Low', 'image quality', 'cbiastudio-blogflow-ai' ),
				'medium' => _x( 'Medium', 'image quality', 'cbiastudio-blogflow-ai' ),
				'high'   => _x( 'High', 'image quality', 'cbiastudio-blogflow-ai' ),
			);
		}

		public static function get_specific_qualities(): array {
			return array_merge(
				array( 'inherit' => __( 'Use default quality', 'cbiastudio-blogflow-ai' ) ),
				self::get_qualities()
			);
		}

		public static function get_quality_label( $quality ): string {
			$quality = self::validate_quality( $quality );
			$labels  = self::get_qualities();
			return (string) $labels[ $quality ];
		}

		public static function get_supported_sizes(): array {
			return array( '1024x1024', '1024x1536', '1536x1024' );
		}

		public static function get_pricing_catalog(): array {
			$catalog = array(
				'gpt-image-1-mini' => array(
					'low'    => array( '1024x1024' => 5000, '1024x1536' => 6000, '1536x1024' => 6000 ),
					'medium' => array( '1024x1024' => 11000, '1024x1536' => 15000, '1536x1024' => 15000 ),
					'high'   => array( '1024x1024' => 36000, '1024x1536' => 52000, '1536x1024' => 52000 ),
				),
				'gpt-image-1' => array(
					'low'    => array( '1024x1024' => 11000, '1024x1536' => 16000, '1536x1024' => 16000 ),
					'medium' => array( '1024x1024' => 42000, '1024x1536' => 63000, '1536x1024' => 63000 ),
					'high'   => array( '1024x1024' => 167000, '1024x1536' => 250000, '1536x1024' => 250000 ),
				),
				'gpt-image-2' => array(
					'low'    => array( '1024x1024' => 6000, '1024x1536' => 5000, '1536x1024' => 5000 ),
					'medium' => array( '1024x1024' => 53000, '1024x1536' => 41000, '1536x1024' => 41000 ),
					'high'   => array( '1024x1024' => 211000, '1024x1536' => 165000, '1536x1024' => 165000 ),
				),
			);

			return (array) apply_filters( 'cbia_image_pricing_catalog', $catalog );
		}

		public static function validate_model( $model, string $fallback = 'gpt-image-2' ): string {
			$model = sanitize_text_field( (string) $model );
			return in_array( $model, self::get_models(), true ) ? $model : $fallback;
		}

		public static function validate_quality( $quality ): string {
			$quality = sanitize_key( (string) $quality );
			return array_key_exists( $quality, self::get_qualities() ) ? $quality : 'auto';
		}

		public static function validate_specific_quality( $quality ): string {
			$quality = sanitize_key( (string) $quality );
			return array_key_exists( $quality, self::get_specific_qualities() ) ? $quality : 'inherit';
		}

		public static function resolve_specific_quality( $specific_quality, $global_quality ): string {
			$specific_quality = self::validate_specific_quality( $specific_quality );
			$global_quality   = self::validate_quality( $global_quality );
			return 'inherit' === $specific_quality ? $global_quality : $specific_quality;
		}

		public static function get_image_type( $section, $idx = 0 ): string {
			$section = sanitize_key( (string) $section );
			$idx     = (int) $idx;
			if ( $idx > 0 || in_array( $section, array( 'body', 'content', 'internal', 'faq', 'closing', 'conclusion' ), true ) ) {
				return 'content';
			}
			if ( in_array( $section, array( 'featured', 'intro' ), true ) ) {
				return 'featured';
			}
			return 'default';
		}

		public static function get_effective_quality( $section = '', $idx = 0, ?array $settings = null ): string {
			$settings = null === $settings && function_exists( 'cbia_get_settings' ) ? cbia_get_settings() : (array) $settings;
			$global   = self::validate_quality( $settings['image_quality'] ?? 'auto' );
			$type     = self::get_image_type( $section, $idx );
			if ( 'featured' === $type ) {
				return self::resolve_specific_quality( $settings['featured_image_quality'] ?? 'inherit', $global );
			}
			if ( 'content' === $type ) {
				return self::resolve_specific_quality( $settings['content_image_quality'] ?? 'inherit', $global );
			}
			return $global;
		}

		public static function validate_size( $size, string $fallback = '1536x1024' ): string {
			$size = sanitize_text_field( (string) $size );
			return in_array( $size, self::get_supported_sizes(), true ) ? $size : $fallback;
		}

		public static function get_price_micro_usd( $model, $quality, $size ): ?int {
			$model   = self::validate_model( $model );
			$quality = self::validate_quality( $quality );
			$size    = sanitize_text_field( (string) $size );
			if ( 'auto' === $quality ) return null;
			$catalog = self::get_pricing_catalog();
			return isset( $catalog[ $model ][ $quality ][ $size ] )
				? max( 0, (int) $catalog[ $model ][ $quality ][ $size ] )
				: null;
		}

		public static function calculate_total_micro_usd( array $images ): ?int {
			$total = 0;
			foreach ( $images as $image ) {
				if ( ! is_array( $image ) ) continue;
				$count = max( 1, (int) ( $image['count'] ?? 1 ) );
				$price = self::get_price_micro_usd(
					$image['model'] ?? '',
					$image['quality'] ?? 'auto',
					$image['size'] ?? ''
				);
				if ( null === $price ) return null;
				$total += $price * $count;
			}
			return $total;
		}

		public static function format_usd( $micro_usd ): string {
			if ( null === $micro_usd ) return '';
			$value = max( 0, (int) $micro_usd ) / 1000000;
			return '$' . number_format( $value, 3, '.', '' );
		}

		public static function prepare_api_payload( $model, $prompt, $size, $quality, array $extra = array() ): array {
			$model   = self::validate_model( $model );
			$size    = self::validate_size( $size );
			$quality = self::validate_quality( $quality );
			$warning = '';
			$payload = array_merge(
				$extra,
				array( 'model' => $model, 'prompt' => (string) $prompt, 'size' => $size )
			);
			if ( 'auto' !== $quality ) $payload['quality'] = $quality;
			if ( 'gpt-image-2' === $model && 'transparent' === ( $payload['background'] ?? '' ) ) {
				$payload['background'] = 'opaque';
				$warning = __( 'GPT Image 2 does not support a transparent background; opaque was used.', 'cbiastudio-blogflow-ai' );
			}
			return array( 'payload' => $payload, 'model' => $model, 'quality' => $quality, 'size' => $size, 'warning' => $warning );
		}
	}
}

if ( ! function_exists( 'cbia_get_image_quality' ) ) {
	function cbia_get_image_quality( $section = '', $idx = 0 ): string {
		return CBIA_Image_Pricing_Service::get_effective_quality( $section, $idx );
	}
}
