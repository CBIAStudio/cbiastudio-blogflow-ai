<?php
/**
 * Standalone checks for the OpenAI image pricing service. No API calls are made.
 */

define( 'ABSPATH', __DIR__ . '/' );
function __( $text ) { return $text; }
function _x( $text ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }

require dirname( __DIR__ ) . '/includes/support/image-pricing.php';

function cbia_assert_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
}

cbia_assert_same( 20000, CBIA_Image_Pricing_Service::calculate_total_micro_usd( array( array( 'model' => 'gpt-image-2', 'quality' => 'low', 'size' => '1536x1024', 'count' => 4 ) ) ), '4 x GPT Image 2 low landscape' );
cbia_assert_same( 56000, CBIA_Image_Pricing_Service::calculate_total_micro_usd( array( array( 'model' => 'gpt-image-2', 'quality' => 'low', 'size' => '1536x1024', 'count' => 3 ), array( 'model' => 'gpt-image-2', 'quality' => 'medium', 'size' => '1536x1024', 'count' => 1 ) ) ), '3 low + 1 medium GPT Image 2' );
cbia_assert_same( 60000, CBIA_Image_Pricing_Service::calculate_total_micro_usd( array( array( 'model' => 'gpt-image-1-mini', 'quality' => 'medium', 'size' => '1536x1024', 'count' => 4 ) ) ), '4 x GPT Image 1 mini medium landscape' );
cbia_assert_same( 1000000, CBIA_Image_Pricing_Service::calculate_total_micro_usd( array( array( 'model' => 'gpt-image-1', 'quality' => 'high', 'size' => '1536x1024', 'count' => 4 ) ) ), '4 x GPT Image 1 high landscape' );
cbia_assert_same( null, CBIA_Image_Pricing_Service::get_price_micro_usd( 'gpt-image-2', 'auto', '1536x1024' ), 'Auto has no invented price' );
cbia_assert_same( 'auto', CBIA_Image_Pricing_Service::resolve_specific_quality( 'inherit', 'auto' ), 'Global auto + featured inherit' );
cbia_assert_same( 'low', CBIA_Image_Pricing_Service::resolve_specific_quality( 'inherit', 'low' ), 'Global low + featured inherit' );
cbia_assert_same( 'medium', CBIA_Image_Pricing_Service::resolve_specific_quality( 'medium', 'low' ), 'Global low + featured medium' );
cbia_assert_same( 'medium', CBIA_Image_Pricing_Service::resolve_specific_quality( 'inherit', 'medium' ), 'Global medium + content inherit' );
cbia_assert_same( 'low', CBIA_Image_Pricing_Service::resolve_specific_quality( 'low', 'high' ), 'Global high + content low' );
cbia_assert_same( 'auto', CBIA_Image_Pricing_Service::validate_quality( 'invalid' ), 'Invalid global quality' );
cbia_assert_same( 'inherit', CBIA_Image_Pricing_Service::validate_specific_quality( 'invalid' ), 'Invalid specific quality' );

$auto = CBIA_Image_Pricing_Service::prepare_api_payload( 'gpt-image-2', 'test', '1536x1024', 'auto', array( 'n' => 1 ) );
cbia_assert_same( false, array_key_exists( 'quality', $auto['payload'] ), 'Auto quality omitted from payload' );
$high = CBIA_Image_Pricing_Service::prepare_api_payload( 'gpt-image-1', 'test', '1536x1024', 'high', array( 'n' => 1 ) );
cbia_assert_same( 'high', $high['payload']['quality'], 'Explicit quality sent' );
$medium = CBIA_Image_Pricing_Service::prepare_api_payload( 'gpt-image-2', 'featured', '1536x1024', 'medium', array( 'n' => 1 ) );
cbia_assert_same( 'medium', $medium['payload']['quality'], 'Featured medium payload' );
$low = CBIA_Image_Pricing_Service::prepare_api_payload( 'gpt-image-2', 'content', '1536x1024', 'low', array( 'n' => 1 ) );
cbia_assert_same( 'low', $low['payload']['quality'], 'Content low payload' );
$opaque = CBIA_Image_Pricing_Service::prepare_api_payload( 'gpt-image-2', 'test', '1536x1024', 'low', array( 'background' => 'transparent' ) );
cbia_assert_same( 'opaque', $opaque['payload']['background'], 'GPT Image 2 transparent fallback' );

echo "image-pricing-test: OK\n";
