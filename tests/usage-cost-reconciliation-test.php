<?php
define('ABSPATH', __DIR__ . '/');
function add_action() {}
function apply_filters($name, $value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function current_time($type) { return $type === 'mysql' ? '2026-07-13 12:00:00' : time(); }
class CBIA_Image_Pricing_Service {
    public static function get_models() { return array('gpt-image-2', 'gpt-image-1', 'gpt-image-1-mini'); }
    public static function get_price_micro_usd($model, $quality, $size) {
        if ($model === 'gpt-image-2' && $quality === 'low' && $size === '1536x1024') return 5000;
        if ($model === 'gpt-image-2' && $quality === 'medium' && $size === '1536x1024') return 41000;
        return null;
    }
}
require dirname(__DIR__) . '/includes/engine/usage.php';
require dirname(__DIR__) . '/includes/domain/costs.php';

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$response_base = array(
    'size' => '1536x1024',
    'output_format' => 'png',
    'background' => 'opaque',
);
foreach (array('low', 'medium', 'high') as $response_quality) {
    $parsed = cbia_usage_from_images_payload(array_merge($response_base, array('quality' => $response_quality)), 'auto', '1536x1024');
    assert_same('auto', $parsed['requested_quality'], 'Auto requested quality ' . $response_quality);
    assert_same($response_quality, $parsed['effective_quality'], 'Response effective quality ' . $response_quality);
}
$parsed_medium = cbia_usage_from_images_payload(array_merge($response_base, array('quality' => 'medium')), 'auto', '1536x1024');
assert_same('1536x1024', $parsed_medium['requested_size'], 'Requested size');
assert_same('1536x1024', $parsed_medium['effective_size'], 'Effective size');
assert_same('png', $parsed_medium['output_format'], 'Output format');
assert_same('opaque', $parsed_medium['background'], 'Background');

$parsed_missing = cbia_usage_from_images_payload(array(), 'auto', '1536x1024');
assert_same(null, $parsed_missing['effective_quality'], 'Auto without response quality');
assert_same('1536x1024', $parsed_missing['effective_size'], 'Fixed requested size fallback');
assert_same('', $parsed_missing['output_format'], 'Missing output format');

$parsed_fixed = cbia_usage_from_images_payload(array(), 'medium', '1536x1024');
assert_same('medium', $parsed_fixed['effective_quality'], 'Fixed requested quality fallback');
$parsed_fixed_response = cbia_usage_from_images_payload(array('quality' => 'medium'), 'medium', '1536x1024');
assert_same('medium', $parsed_fixed_response['effective_quality'], 'Fixed requested quality prefers response');
$parsed_auto_size = cbia_usage_from_images_payload(array('quality' => 'medium'), 'auto', 'auto');
assert_same(null, $parsed_auto_size['effective_size'], 'Auto requested size without response remains unknown');
$parsed_without_data = cbia_usage_from_images_payload(array('quality' => 'medium', 'size' => '1536x1024'), 'auto', '1536x1024');
assert_same('medium', $parsed_without_data['effective_quality'], 'Metadata parsing is safe when data is absent');

$parsed_usage = cbia_usage_from_images_payload(array_merge($response_base, array(
    'quality' => 'medium',
    'usage' => array(
        'input_tokens' => 100,
        'output_tokens' => 4096,
        'total_tokens' => 4196,
        'input_tokens_details' => array('text_tokens' => 100, 'image_tokens' => 0),
        'output_tokens_details' => array('image_tokens' => 4096),
    ),
)), 'auto', '1536x1024');
assert_same(100, $parsed_usage['text_input_tokens'], 'Image response text tokens');
assert_same(4096, $parsed_usage['image_output_tokens'], 'Image response output image tokens');
assert_same(100, $parsed_usage['input_text_tokens'], 'Canonical input text tokens');
assert_same(0, $parsed_usage['input_image_tokens'], 'Canonical input image tokens');
assert_same(4096, $parsed_usage['output_image_tokens'], 'Canonical output image tokens');

$parsed_invalid = cbia_usage_from_images_payload('{invalid-json', 'auto', '1536x1024');
assert_same(null, $parsed_invalid['effective_quality'], 'Invalid JSON-safe payload');

$auto_effective_medium = cbia_costes_calculate_row(array_merge($parsed_medium, array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1)));
assert_same(41000, $auto_effective_medium['cost_micro_usd'], 'Auto plus effective medium estimate');
assert_same('estimated', $auto_effective_medium['cost_status'], 'Auto plus effective medium status');
assert_same('local_catalog', $auto_effective_medium['cost_source'], 'Auto plus effective medium source');
foreach (array('regeneration', 'featured', 'content', 'cron', 'manual') as $context) {
    $route_cost = cbia_costes_calculate_row(array_merge($parsed_medium, array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'context' => $context)));
    assert_same(41000, $route_cost['cost_micro_usd'], 'Effective metadata retained for ' . $context);
}

$auto_effective_unknown = cbia_costes_calculate_row(array_merge($parsed_missing, array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1)));
assert_same(null, $auto_effective_unknown['cost_micro_usd'], 'Auto without effective quality remains unknown');
assert_same('unknown', $auto_effective_unknown['cost_status'], 'Auto without effective quality status');

$auto_with_usage = cbia_costes_calculate_row(array_merge($parsed_usage, array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1)));
assert_same('exact', $auto_with_usage['cost_status'], 'Auto with complete usage status');

$text = cbia_costes_calculate_row(array('type' => 'text', 'model' => 'gpt-5-mini', 'ok' => 1, 'in' => 1000, 'out' => 1000, 'cached_tokens_reported' => 1));
assert_same('exact', $text['cost_status'], 'Text cost status');
assert_same(2250, $text['cost_micro_usd'], 'Text micro-USD');

$auto = cbia_costes_calculate_row(array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'quality' => 'auto', 'size' => '1536x1024'));
assert_same('unknown', $auto['cost_status'], 'Auto image without usage');
assert_same(null, $auto['cost_micro_usd'], 'Auto image is not zero');
assert_same('automatic_quality_without_usage', $auto['cost_reason'], 'Auto image reason');

$fixed = cbia_costes_calculate_row(array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'quality' => 'low', 'size' => '1536x1024'));
assert_same('estimated', $fixed['cost_status'], 'Fixed image estimate');
assert_same(5000, $fixed['cost_micro_usd'], 'Fixed image micro-USD');

$image_usage = cbia_costes_calculate_row(array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'quality' => 'auto', 'image_input_tokens' => 100, 'text_input_tokens' => 20, 'image_output_tokens' => 200));
assert_same('exact', $image_usage['cost_status'], 'Image API usage status');
assert_same(6900, $image_usage['cost_micro_usd'], 'Image API usage micro-USD');

$timeout = cbia_costes_calculate_row(array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 0, 'quality' => 'auto', 'error' => 'cURL error 28: timeout'));
assert_same('unknown', $timeout['cost_status'], 'Timeout remains unknown');
assert_same(null, $timeout['cost_micro_usd'], 'Timeout is not zero');
assert_same('timeout_without_response_usage', $timeout['cost_reason'], 'Timeout reason');

$fixture = array();
for ($i = 0; $i < 45; $i++) $fixture[] = array('type' => 'text', 'model' => 'gpt-5-mini', 'ok' => 1, 'in' => 100, 'out' => 200, 'cached_tokens_reported' => 1, 'batch_id' => 'fixture-20-posts');
$fixture[] = array('type' => 'text', 'model' => 'gpt-5.4-mini', 'model_requested' => 'gpt-5-mini', 'model_effective' => 'gpt-5.4-mini', 'fallback_from' => 'gpt-5-mini', 'parent_attempt' => 1, 'attempt' => 2, 'ok' => 1, 'in' => 100, 'out' => 200, 'cached_tokens_reported' => 1, 'batch_id' => 'fixture-20-posts');
$fixture[] = array('type' => 'text', 'model' => 'gpt-5-mini', 'ok' => 0, 'status' => 'timeout', 'error' => 'cURL error 28: timeout', 'attempt' => 1, 'batch_id' => 'fixture-20-posts');
for ($i = 0; $i < 56; $i++) $fixture[] = array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'quality' => 'auto', 'size' => '1536x1024', 'batch_id' => 'fixture-20-posts');
for ($i = 0; $i < 2; $i++) $fixture[] = array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'quality' => 'low', 'size' => '1536x1024', 'batch_id' => 'fixture-20-posts');
$fixture[] = array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'quality' => 'medium', 'size' => '1536x1024', 'batch_id' => 'fixture-20-posts');
$fixture[] = array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 1, 'quality' => 'auto', 'image_input_tokens' => 100, 'text_input_tokens' => 20, 'image_output_tokens' => 200, 'batch_id' => 'fixture-20-posts');
for ($i = 0; $i < 2; $i++) $fixture[] = array('type' => 'image', 'model' => 'gpt-image-2', 'ok' => 0, 'status' => 'timeout', 'error' => 'cURL error 28: timeout', 'quality' => 'auto', 'size' => '1536x1024', 'batch_id' => 'fixture-20-posts');
$status_counts = array('exact' => 0, 'estimated' => 0, 'unknown' => 0, 'official_reconciled' => 0);
foreach ($fixture as $row) $status_counts[cbia_costes_calculate_row($row)['cost_status']]++;
assert_same(109, count($fixture), 'Fixture event count');
assert_same(47, $status_counts['exact'], 'Fixture exact events');
assert_same(3, $status_counts['estimated'], 'Fixture estimated events');
assert_same(59, $status_counts['unknown'], 'Fixture unknown events');

echo "usage-cost-reconciliation: OK\n";
