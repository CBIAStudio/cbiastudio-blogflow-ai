<?php
define('ABSPATH', __DIR__ . '/');
function apply_filters($hook, $value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }

require dirname(__DIR__) . '/includes/support/provider-model-catalog.php';

$cases = 0;
function catalog_check($condition, $message) {
    global $cases;
    $cases++;
    if (!$condition) throw new RuntimeException("Case {$cases} failed: {$message}");
}

catalog_check(cbia_provider_catalog_recommended_model('openai', 'text') === 'gpt-5-mini', 'OpenAI recommendation changed');
catalog_check(cbia_provider_catalog_get('openai')['default_text_model'] === 'gpt-5-mini', 'OpenAI default changed');
catalog_check(in_array('gpt-5.6-sol', cbia_provider_catalog_model_ids('openai', 'text'), true), 'GPT-5.6 Sol missing');
catalog_check(in_array('gpt-5.6-terra', cbia_provider_catalog_model_ids('openai', 'text'), true), 'GPT-5.6 Terra missing');
catalog_check(in_array('gpt-5.6-luna', cbia_provider_catalog_model_ids('openai', 'text'), true), 'GPT-5.6 Luna missing');
catalog_check(cbia_provider_catalog_price_period('openai', 'gpt-5.6-sol')['input_price_micro_usd_per_mtok'] === 5000000, 'Sol input price');
catalog_check(cbia_provider_catalog_price_period('openai', 'gpt-5.6-terra')['output_price_micro_usd_per_mtok'] === 12000000, 'Terra output price');
catalog_check(cbia_provider_catalog_price_period('openai', 'gpt-5.6-luna')['cached_input_price_micro_usd_per_mtok'] === 20000, 'Luna cached price');
catalog_check(cbia_provider_catalog_model('openai', 'gpt-5.6')['display_name'] === 'GPT-5.6 Sol', 'OpenAI alias');
catalog_check(in_array('gemini-3.6-flash', cbia_provider_catalog_model_ids('google', 'text'), true), 'Gemini 3.6 missing');
catalog_check(cbia_provider_catalog_price_period('google', 'gemini-3.6-flash')['cached_input_price_micro_usd_per_mtok'] === 150000, 'Gemini cache price');
catalog_check(cbia_provider_catalog_image_price_micro_usd('google', 'gemini-3.1-flash-image', 'standard', '1024x1024') === 67000, 'Nano Banana 2 price');
catalog_check(in_array('imagen-3.0-generate-002', cbia_provider_catalog_model_ids('google', 'image'), true), 'saved Imagen 3 compatibility');
catalog_check(cbia_provider_catalog_model('google', 'imagen-3.0-generate-002')['status'] === 'deprecated', 'Imagen 3 status');
catalog_check(cbia_provider_model_catalog_version() === 'providers-2026-08-03-v1', 'catalog version');
catalog_check(cbia_provider_catalog_recommended_model('anthropic', 'text') === 'claude-sonnet-5', 'Anthropic recommendation');
catalog_check(cbia_provider_catalog_get('anthropic')['capabilities'] === array('text'), 'Anthropic must be text-only');
catalog_check(cbia_provider_catalog_price_period('anthropic', 'claude-sonnet-5', '2026-08-15')['input_price_micro_usd_per_mtok'] === 2000000, 'Sonnet promotional price');
catalog_check(cbia_provider_catalog_price_period('anthropic', 'claude-sonnet-5', '2026-09-01')['input_price_micro_usd_per_mtok'] === 3000000, 'Sonnet standard price');

echo "provider-model-catalog: {$cases}/{$cases} OK\n";
