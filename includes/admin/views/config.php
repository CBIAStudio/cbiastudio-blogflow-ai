<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_render_view_config')) {
    function cbia_render_view_config() {
        global $cbia_settings_service;

// Config tab view (extracted from legacy cbia-config.php)

$settings_service = isset($cbia_settings_service) ? $cbia_settings_service : null;
$s = $settings_service && method_exists($settings_service, 'get_settings')
    ? $settings_service->get_settings()
    : cbia_get_settings();
$internal_images_enabled = function_exists('cbia_cap_enabled') ? cbia_cap_enabled('internal_images') : true;

$provider_settings = function_exists('cbia_providers_get_settings') ? cbia_providers_get_settings() : [];
$providers_all = function_exists('cbia_providers_get_all') ? cbia_providers_get_all() : [];
$providers_list = isset($providers_all['providers']) && is_array($providers_all['providers']) ? $providers_all['providers'] : [];
$image_providers_list = array();
foreach ($providers_list as $pkey => $pdef) {
    if (function_exists('cbia_providers_supports_image') && !cbia_providers_supports_image((string)$pkey)) {
        continue;
    }
    $image_providers_list[$pkey] = $pdef;
}
$provider_current = function_exists('cbia_providers_get_current_provider') ? cbia_providers_get_current_provider() : 'openai';
$provider_key_urls = array(
    'openai' => 'https://platform.openai.com/api-keys',
    'google' => 'https://makersuite.google.com/app/apikey',
    'deepseek' => 'https://platform.deepseek.com/api_keys',
);

// Defaults seguros
$recommended = function_exists('cbia_providers_get_recommended_text_model')
    ? cbia_providers_get_recommended_text_model($provider_current)
    : 'gpt-5-mini';
$s['openai_model'] = cbia_config_safe_model($s['openai_model'] ?? $recommended);
if (!isset($s['openai_temperature'])) $s['openai_temperature'] = 0.7;
if (!isset($s['post_length_variant'])) $s['post_length_variant'] = 'medium';
if (!isset($s['images_limit'])) $s['images_limit'] = 3;
if (!$internal_images_enabled) $s['images_limit'] = 1;
if (!isset($s['default_category'])) $s['default_category'] = 'News';
if (!isset($s['post_language'])) $s['post_language'] = 'English';
if (!isset($s['responses_max_output_tokens'])) $s['responses_max_output_tokens'] = 6000;
if (!isset($s['image_model'])) $s['image_model'] = 'gpt-image-2';
// CAMBIO: nuevos settings texto/imagen
if (!isset($s['text_provider'])) $s['text_provider'] = $provider_current;
if (!isset($s['image_provider'])) $s['image_provider'] = 'openai';
if (!isset($s['text_model'])) $s['text_model'] = '';
if (!isset($s['google_api_key'])) $s['google_api_key'] = '';
if (!isset($s['deepseek_api_key'])) $s['deepseek_api_key'] = '';
if (!isset($s['deepseek_thinking_mode'])) $s['deepseek_thinking_mode'] = 'disabled';
if (!isset($s['deepseek_reasoning_effort'])) $s['deepseek_reasoning_effort'] = 'high';
if (!isset($s['content_images_banner_enabled'])) $s['content_images_banner_enabled'] = 1;
if (!isset($s['openai_consent'])) $s['openai_consent'] = 1;
if (!isset($s['image_format_intro'])) $s['image_format_intro'] = 'panoramic_1536x1024';
if (!isset($s['image_format_body'])) $s['image_format_body'] = 'banner_1536x1024';
if (!isset($s['image_format_conclusion'])) $s['image_format_conclusion'] = 'banner_1536x1024';
if (!isset($s['image_format_faq'])) $s['image_format_faq'] = 'banner_1536x1024';
if (!isset($s['image_format_internal_1'])) $s['image_format_internal_1'] = $s['image_format_body'];
if (!isset($s['image_format_internal_2'])) $s['image_format_internal_2'] = $s['image_format_body'];
if (!isset($s['image_format_internal_3'])) $s['image_format_internal_3'] = $s['image_format_body'];
if (!isset($s['content_images_banner_css']) || trim((string)$s['content_images_banner_css']) === '') {
    $defaults = function_exists('cbia_get_default_settings') ? cbia_get_default_settings() : [];
    $s['content_images_banner_css'] = (string)($defaults['content_images_banner_css'] ?? '');
}
if (!isset($s['default_author_id'])) $s['default_author_id'] = 0;
$s['prompt_img_global'] = isset($s['prompt_img_global']) ? (string)$s['prompt_img_global'] : '';
$s['prompt_img_featured'] = isset($s['prompt_img_featured']) ? (string)$s['prompt_img_featured'] : '';
$s['prompt_img_internal'] = isset($s['prompt_img_internal']) ? (string)$s['prompt_img_internal'] : '';
$s['prompt_img_internal_1'] = isset($s['prompt_img_internal_1']) ? (string)$s['prompt_img_internal_1'] : '';
$s['prompt_img_internal_2'] = isset($s['prompt_img_internal_2']) ? (string)$s['prompt_img_internal_2'] : '';
$s['prompt_img_internal_3'] = isset($s['prompt_img_internal_3']) ? (string)$s['prompt_img_internal_3'] : '';
$default_img_prompt = function_exists('cbia_default_image_prompt_template') ? cbia_default_image_prompt_template() : 'Professional editorial photography. Subject: {desc} related to {title}. {format} No text, no logos, no watermarks.';
if (trim((string)$s['prompt_img_global']) === '') $s['prompt_img_global'] = $default_img_prompt;
if (trim((string)$s['prompt_img_featured']) === '') $s['prompt_img_featured'] = $s['prompt_img_global'];
if (trim((string)$s['prompt_img_internal']) === '') $s['prompt_img_internal'] = $s['prompt_img_global'];
if (trim((string)$s['prompt_img_internal_1']) === '') $s['prompt_img_internal_1'] = $s['prompt_img_internal'];
if (trim((string)$s['prompt_img_internal_2']) === '') $s['prompt_img_internal_2'] = $s['prompt_img_internal'];
if (trim((string)$s['prompt_img_internal_3']) === '') $s['prompt_img_internal_3'] = $s['prompt_img_internal'];

$yoast_batch = 50;
$yoast_offset = 0;
$yoast_force = false;
$yoast_only_cbia = true;
$yoast_service = function_exists('cbia_container') ? cbia_container()->get('yoast_service') : null;
if ($yoast_service && method_exists($yoast_service, 'handle_post')) {
    list($yoast_batch, $yoast_offset, $yoast_force, $yoast_only_cbia) = $yoast_service->handle_post($yoast_batch, $yoast_offset, $yoast_force, $yoast_only_cbia);
} elseif (function_exists('cbia_yoast_handle_post')) {
    list($yoast_batch, $yoast_offset, $yoast_force, $yoast_only_cbia) = cbia_yoast_handle_post($yoast_batch, $yoast_offset, $yoast_force, $yoast_only_cbia);
}
$yoast_log = $yoast_service && method_exists($yoast_service, 'get_log')
    ? (string)$yoast_service->get_log()
    : (function_exists('cbia_yoast_log_get') ? (string)cbia_yoast_log_get() : '');

$api_key = (string)($s['openai_api_key'] ?? '');
$diag_info = array(
    'Plugin version' => defined('CBIA_VERSION') ? CBIA_VERSION : 'n/a',
    'WordPress' => get_bloginfo('version'),
    'PHP' => PHP_VERSION,
    'Memory limit' => (string)ini_get('memory_limit'),
    'Max execution time' => (string)ini_get('max_execution_time'),
    'WP_DEBUG' => (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false',
    'WP_DEBUG_LOG' => (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) ? 'true' : 'false',
    'DISABLE_WP_CRON' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'true' : 'false',
    'Timezone' => (string)wp_timezone_string(),
    'OpenAI API key' => function_exists('cbia_has_provider_api_key') && cbia_has_provider_api_key('openai') ? 'Yes' : 'No',
    'DeepSeek API key' => function_exists('cbia_has_provider_api_key') && cbia_has_provider_api_key('deepseek') ? 'Yes' : 'No',
    'Google API key' => function_exists('cbia_has_provider_api_key') && cbia_has_provider_api_key('google') ? 'Yes' : 'No',
    'Plugin dir writable' => wp_is_writable(CBIA_PLUGIN_DIR) ? 'Yes' : 'No',
    'WP content writable' => defined('WP_CONTENT_DIR') && wp_is_writable(WP_CONTENT_DIR) ? 'Yes' : 'No',
);
$diag_log = (string)get_option(CBIA_OPTION_LOG, '');
$diag_log_lines = $diag_log ? array_slice(explode("\n", $diag_log), -20) : array();

echo '<div class="cbia-view-container">';
if (isset($_GET['saved'])) {
    echo '<div class="notice notice-success is-dismissible" style="background: rgba(34, 211, 238, 0.1); border-color: var(--abb-cyan); color: var(--abb-cyan);"><p>' . esc_html__('Settings saved successfully.', 'cbiastudio-blogflow-ai') . '</p></div>';
}
if (isset($_GET['keys_saved'])) {
    echo '<div class="notice notice-success is-dismissible" style="background: rgba(34, 211, 238, 0.1); border-color: var(--abb-cyan); color: var(--abb-cyan);"><p>' . esc_html__('API keys saved successfully.', 'cbiastudio-blogflow-ai') . '</p></div>';
}
// CAMBIO: avisos por API key faltante
$warnings = get_transient('cbia_config_warnings');
if (!empty($warnings) && is_array($warnings)) {
    echo '<div class="notice notice-warning"><p>' . esc_html(implode(' ', $warnings)) . '</p></div>';
    delete_transient('cbia_config_warnings');
}

echo '<form method="post" id="cbia-config-form">';
wp_nonce_field('cbia_config_save_action', 'cbia_config_nonce');
echo '<input type="hidden" name="cbia_config_save" value="1" />';

echo '<div class="cbia-section-header">';
echo '<div class="cbia-section-title">' . esc_html__('Provider and model', 'cbiastudio-blogflow-ai') . '</div>';
echo '<p class="description">' . esc_html__('Configure the AI engines used for text and images.', 'cbiastudio-blogflow-ai') . '</p>';
echo '</div>';
echo '<div class="abb-card">';

$text_provider = sanitize_key((string)($s['text_provider'] ?? $provider_current));
if ($text_provider === '' || !isset($providers_list[$text_provider])) $text_provider = 'openai';
$image_provider = sanitize_key((string)($s['image_provider'] ?? 'openai'));
if ($image_provider === '' || !isset($image_providers_list[$image_provider])) $image_provider = 'openai';

$text_provider_data = $providers_list[$text_provider] ?? [];
$text_provider_label = (string)($text_provider_data['label'] ?? 'OpenAI');
$text_provider_logo = plugins_url('assets/images/providers/' . $text_provider . '.svg', CBIA_PLUGIN_FILE);
if (!file_exists(plugin_dir_path(CBIA_PLUGIN_FILE) . 'assets/images/providers/' . $text_provider . '.svg')) {
    $text_provider_logo = plugins_url('assets/images/providers/openai.svg', CBIA_PLUGIN_FILE);
}

$image_provider_data = $image_providers_list[$image_provider] ?? [];
$image_provider_label = (string)($image_provider_data['label'] ?? 'OpenAI');
$image_provider_logo = plugins_url('assets/images/providers/' . $image_provider . '.svg', CBIA_PLUGIN_FILE);
if (!file_exists(plugin_dir_path(CBIA_PLUGIN_FILE) . 'assets/images/providers/' . $image_provider . '.svg')) {
    $image_provider_logo = plugins_url('assets/images/providers/openai.svg', CBIA_PLUGIN_FILE);
}

echo '<div class="abb-provider-grid">';
// Proveedor texto
echo '<div class="abb-field">';
echo '<label>' . esc_html__('Provider (text)', 'cbiastudio-blogflow-ai') . '</label>';
echo '<div class="abb-provider-select" data-scope="text">';
echo '<button type="button" class="abb-provider-trigger" aria-expanded="false">';
echo '<img class="abb-provider-logo" src="' . esc_url($text_provider_logo) . '" alt="' . esc_attr($text_provider_label) . '" />';
echo '<span class="abb-provider-label">' . esc_html($text_provider_label) . '</span>';
echo '<span class="abb-provider-caret">&#9662;</span>';
echo '</button>';
echo '<div class="abb-provider-menu">';
foreach ($providers_list as $pkey => $pdef) {
    $plabel = (string)($pdef['label'] ?? $pkey);
    $plogo = plugins_url('assets/images/providers/' . $pkey . '.svg', CBIA_PLUGIN_FILE);
    if (!file_exists(plugin_dir_path(CBIA_PLUGIN_FILE) . 'assets/images/providers/' . $pkey . '.svg')) {
        $plogo = plugins_url('assets/images/providers/openai.svg', CBIA_PLUGIN_FILE);
    }
    echo '<button type="button" class="abb-provider-option" data-value="' . esc_attr($pkey) . '" data-logo="' . esc_url($plogo) . '" data-label="' . esc_attr($plabel) . '">';
    echo '<img src="' . esc_url($plogo) . '" alt="' . esc_attr($plabel) . '" />';
    echo '<span>' . esc_html($plabel) . '</span>';
    echo '</button>';
}
echo '</div>';
echo '<select class="abb-provider-select-input" name="text_provider" data-scope="text" style="display:none;">';
foreach ($providers_list as $pkey => $pdef) {
    $plabel = (string)($pdef['label'] ?? $pkey);
    $plogo = plugins_url('assets/images/providers/' . $pkey . '.svg', CBIA_PLUGIN_FILE);
    if (!file_exists(plugin_dir_path(CBIA_PLUGIN_FILE) . 'assets/images/providers/' . $pkey . '.svg')) {
        $plogo = plugins_url('assets/images/providers/openai.svg', CBIA_PLUGIN_FILE);
    }
    echo '<option value="' . esc_attr($pkey) . '" data-logo="' . esc_url($plogo) . '"' . selected($text_provider, $pkey, false) . '>' . esc_html($plabel) . '</option>';
}
echo '</select>';
echo '</div>';
echo '</div>';

// Proveedor imagen
echo '<div class="abb-field">';
echo '<label>' . esc_html__('Provider (image)', 'cbiastudio-blogflow-ai') . '</label>';
echo '<div class="abb-provider-select" data-scope="image">';
echo '<button type="button" class="abb-provider-trigger" aria-expanded="false">';
echo '<img class="abb-provider-logo" src="' . esc_url($image_provider_logo) . '" alt="' . esc_attr($image_provider_label) . '" />';
echo '<span class="abb-provider-label">' . esc_html($image_provider_label) . '</span>';
echo '<span class="abb-provider-caret">&#9662;</span>';
echo '</button>';
echo '<div class="abb-provider-menu">';
foreach ($image_providers_list as $pkey => $pdef) {
    $plabel = (string)($pdef['label'] ?? $pkey);
    $plogo = plugins_url('assets/images/providers/' . $pkey . '.svg', CBIA_PLUGIN_FILE);
    if (!file_exists(plugin_dir_path(CBIA_PLUGIN_FILE) . 'assets/images/providers/' . $pkey . '.svg')) {
        $plogo = plugins_url('assets/images/providers/openai.svg', CBIA_PLUGIN_FILE);
    }
    echo '<button type="button" class="abb-provider-option" data-value="' . esc_attr($pkey) . '" data-logo="' . esc_url($plogo) . '" data-label="' . esc_attr($plabel) . '">';
    echo '<img src="' . esc_url($plogo) . '" alt="' . esc_attr($plabel) . '" />';
    echo '<span>' . esc_html($plabel) . '</span>';
    echo '</button>';
}
echo '</div>';
echo '<select class="abb-provider-select-input" name="image_provider" data-scope="image" style="display:none;">';
foreach ($image_providers_list as $pkey => $pdef) {
    $plabel = (string)($pdef['label'] ?? $pkey);
    $plogo = plugins_url('assets/images/providers/' . $pkey . '.svg', CBIA_PLUGIN_FILE);
    if (!file_exists(plugin_dir_path(CBIA_PLUGIN_FILE) . 'assets/images/providers/' . $pkey . '.svg')) {
        $plogo = plugins_url('assets/images/providers/openai.svg', CBIA_PLUGIN_FILE);
    }
    echo '<option value="' . esc_attr($pkey) . '" data-logo="' . esc_url($plogo) . '"' . selected($image_provider, $pkey, false) . '>' . esc_html($plabel) . '</option>';
}
echo '</select>';
echo '</div>';
echo '</div>';

// Modelos texto
$openai_models = cbia_get_allowed_models_for_ui();
foreach ($providers_list as $pkey => $pdef) {
    $text_list = ($pkey === 'openai') ? $openai_models : (function_exists('cbia_providers_get_text_model_list') ? cbia_providers_get_text_model_list($pkey) : []);
    $saved = '';
    if ($text_provider === $pkey && !empty($s['text_model'])) $saved = (string)$s['text_model'];
    if ($saved === '' && !empty($provider_settings['providers'][$pkey]['model'])) $saved = (string)$provider_settings['providers'][$pkey]['model'];
    if ($saved === '' && $pkey === 'openai' && !empty($s['openai_model'])) $saved = (string)$s['openai_model'];
    if ($saved === '' && function_exists('cbia_providers_get_recommended_text_model')) $saved = cbia_providers_get_recommended_text_model($pkey);
    echo '<div class="abb-field abb-provider-model" data-scope="text" data-provider="' . esc_attr($pkey) . '"' . ($text_provider === $pkey ? '' : ' style="display:none;"') . '>';
    echo '<label>' . esc_html__('Model (text)', 'cbiastudio-blogflow-ai') . '</label>';
    echo '<select name="text_model[' . esc_attr($pkey) . ']" class="abb-select">';
    foreach ($text_list as $mdl) {
        $label = $mdl;
        if ($pkey === 'google' && $mdl === 'gemini-2.5-flash') $label .= ' (Recomendado)';
        if ($pkey === 'openai' && $mdl === $recommended) $label .= ' (RECOMENDADO)';
        if ($pkey === 'deepseek' && $mdl === 'deepseek-v4-flash') $label = __('DeepSeek V4 Flash — Recommended', 'cbiastudio-blogflow-ai');
        if ($pkey === 'deepseek' && $mdl === 'deepseek-v4-pro') $label = __('DeepSeek V4 Pro — Maximum capability', 'cbiastudio-blogflow-ai');
        echo '<option value="' . esc_attr($mdl) . '" ' . selected($saved, $mdl, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

// Modelos imagen
foreach ($image_providers_list as $pkey => $pdef) {
    $img_list = function_exists('cbia_providers_get_image_model_list') ? cbia_providers_get_image_model_list($pkey) : [];
    $saved_img = '';
    if ($image_provider === $pkey && !empty($s['image_model'])) $saved_img = (string)$s['image_model'];
    if ($saved_img === '' && !empty($provider_settings['providers'][$pkey]['image_model'])) $saved_img = (string)$provider_settings['providers'][$pkey]['image_model'];
    if ($saved_img === '' && function_exists('cbia_providers_get_recommended_image_model')) $saved_img = cbia_providers_get_recommended_image_model($pkey);
    echo '<div class="abb-field abb-provider-model" data-scope="image" data-provider="' . esc_attr($pkey) . '"' . ($image_provider === $pkey ? '' : ' style="display:none;"') . '>';
    echo '<label>' . esc_html__('Model (image)', 'cbiastudio-blogflow-ai') . '</label>';
    echo '<select name="image_model_by_provider[' . esc_attr($pkey) . ']" class="abb-select cbia-image-model-select">';
    if (empty($img_list)) {
        echo '<option value="">' . esc_html__('Not available', 'cbiastudio-blogflow-ai') . '</option>';
    } else {
        foreach ($img_list as $mdl) {
            $label = $mdl;
            if ($pkey === 'openai' && $mdl === 'gpt-image-2') $label = __('GPT Image 2 — Recommended', 'cbiastudio-blogflow-ai');
            if ($pkey === 'openai' && $mdl === 'gpt-image-1') $label = __('GPT Image 1', 'cbiastudio-blogflow-ai');
            if ($pkey === 'openai' && $mdl === 'gpt-image-1-mini') $label = __('GPT Image 1 Mini', 'cbiastudio-blogflow-ai');
            if ($mdl === 'imagen-3.0-generate-002') $label .= ' (Recomendado)';
            echo '<option value="' . esc_attr($mdl) . '" ' . selected($saved_img, $mdl, false) . '>' . esc_html($label) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
}

$image_quality = class_exists('CBIA_Image_Pricing_Service')
    ? CBIA_Image_Pricing_Service::validate_quality($s['image_quality'] ?? 'auto')
    : 'auto';
$image_qualities = class_exists('CBIA_Image_Pricing_Service')
    ? CBIA_Image_Pricing_Service::get_qualities()
    : array('auto' => __('Automatic', 'cbiastudio-blogflow-ai'), 'low' => __('Low', 'cbiastudio-blogflow-ai'), 'medium' => __('Medium', 'cbiastudio-blogflow-ai'), 'high' => __('High', 'cbiastudio-blogflow-ai'));
$specific_image_qualities = class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_specific_qualities() : array_merge(array('inherit' => __('Use default quality', 'cbiastudio-blogflow-ai')), $image_qualities);
$quality_fields = array(
    'image_quality' => array('id' => 'cbia-image-quality', 'label' => __('Default image quality', 'cbiastudio-blogflow-ai'), 'value' => $image_quality, 'options' => $image_qualities, 'help' => __('This quality is used by default for images without a specific setting.', 'cbiastudio-blogflow-ai')),
    'featured_image_quality' => array('id' => 'cbia-featured-image-quality', 'label' => __('Featured image quality', 'cbiastudio-blogflow-ai'), 'value' => CBIA_Image_Pricing_Service::validate_specific_quality($s['featured_image_quality'] ?? 'inherit'), 'options' => $specific_image_qualities, 'help' => __('Allows a different quality for the article featured image.', 'cbiastudio-blogflow-ai')),
    'content_image_quality' => array('id' => 'cbia-content-image-quality', 'label' => __('Content image quality', 'cbiastudio-blogflow-ai'), 'value' => CBIA_Image_Pricing_Service::validate_specific_quality($s['content_image_quality'] ?? 'inherit'), 'options' => $specific_image_qualities, 'help' => __('Applied to images inserted inside the article content.', 'cbiastudio-blogflow-ai')),
);

$deepseek_config = function_exists('cbia_deepseek_normalize_runtime_config')
    ? cbia_deepseek_normalize_runtime_config($s['text_model'] ?? 'deepseek-v4-flash', $s['deepseek_thinking_mode'], $s['deepseek_reasoning_effort'])
    : array('thinking' => 'disabled', 'reasoning_effort' => 'high');
$deepseek_visible = $text_provider === 'deepseek';
echo '<div class="abb-field abb-deepseek-settings"' . ($deepseek_visible ? '' : ' style="display:none;"') . '>';
echo '<label for="cbia-deepseek-thinking">' . esc_html__('Reasoning mode', 'cbiastudio-blogflow-ai') . '</label>';
echo '<select id="cbia-deepseek-thinking" name="deepseek_thinking_mode" class="abb-select">';
echo '<option value="disabled" ' . selected($deepseek_config['thinking'], 'disabled', false) . '>' . esc_html__('Disabled — recommended for articles', 'cbiastudio-blogflow-ai') . '</option>';
echo '<option value="enabled" ' . selected($deepseek_config['thinking'], 'enabled', false) . '>' . esc_html__('Enabled — advanced reasoning', 'cbiastudio-blogflow-ai') . '</option>';
echo '</select><p class="description">' . esc_html__('When enabled, reasoning tokens consume part of the output-token budget and can leave less room for visible article text.', 'cbiastudio-blogflow-ai') . '</p></div>';
if ($deepseek_visible && $deepseek_config['thinking'] === 'enabled' && in_array(sanitize_key((string)($s['post_length_variant'] ?? 'medium')), array('medium', 'long'), true) && (int)($s['responses_max_output_tokens'] ?? 6000) < 6000) {
    echo '<div class="notice notice-warning inline"><p>' . esc_html__('Thinking is enabled for a medium or long article with a low output-token limit. Increase the limit or disable thinking to reduce truncation and expansion.', 'cbiastudio-blogflow-ai') . '</p></div>';
}
echo '<div class="abb-field abb-deepseek-settings abb-deepseek-effort"' . ($deepseek_visible && $deepseek_config['thinking'] === 'enabled' ? '' : ' style="display:none;"') . '>';
echo '<label for="cbia-deepseek-effort">' . esc_html__('Reasoning effort', 'cbiastudio-blogflow-ai') . '</label>';
echo '<select id="cbia-deepseek-effort" name="deepseek_reasoning_effort" class="abb-select"' . disabled($deepseek_config['thinking'], 'disabled', false) . '>';
echo '<option value="high" ' . selected($deepseek_config['reasoning_effort'], 'high', false) . '>' . esc_html__('High', 'cbiastudio-blogflow-ai') . '</option>';
echo '<option value="max" ' . selected($deepseek_config['reasoning_effort'], 'max', false) . '>' . esc_html__('Maximum', 'cbiastudio-blogflow-ai') . '</option>';
echo '</select></div>';
echo '<div class="abb-field abb-deepseek-settings"' . ($deepseek_visible ? '' : ' style="display:none;"') . '>';
echo '<label>' . esc_html__('Indicative pricing', 'cbiastudio-blogflow-ai') . '</label>';
echo '<p class="description">' . esc_html__('Flash: cache hit $0.0028, cache miss $0.14, output $0.28 per 1M tokens. Pro: cache hit $0.003625, cache miss $0.435, output $0.87 per 1M tokens. Verified July 14, 2026.', 'cbiastudio-blogflow-ai') . '</p></div>';
echo '</div>'; // grid

// API keys: texto
echo '<div class="abb-api-row">';
echo '<label>' . esc_html__('API key (text)', 'cbiastudio-blogflow-ai') . '</label>';
foreach ($providers_list as $pkey => $pdef) {
    $key_val = '';
    if ($pkey === 'openai') $key_val = (string)($s['openai_api_key'] ?? '');
    if ($pkey === 'google') $key_val = (string)($s['google_api_key'] ?? '');
    if ($pkey === 'deepseek') $key_val = (string)($s['deepseek_api_key'] ?? '');
    if ($key_val === '' && !empty($provider_settings['providers'][$pkey]['api_key'])) $key_val = (string)$provider_settings['providers'][$pkey]['api_key'];
    $key_configured = function_exists('cbia_has_provider_api_key') && cbia_has_provider_api_key($pkey);
    $mask_placeholder = $key_configured ? '••••••••••••••••' : '';
    $key_status_label = $key_configured
        ? __('API key configured', 'cbiastudio-blogflow-ai')
        : __('API key not configured', 'cbiastudio-blogflow-ai');
    $link = $provider_key_urls[$pkey] ?? '';
    echo '<div class="abb-provider-key" data-scope="text" data-provider="' . esc_attr($pkey) . '" style="display:none;">';
    echo '<div class="abb-api-input">';
		echo '<input class="abb-input" type="password" name="provider_api_key_text[' . esc_attr($pkey) . ']" value="" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" spellcheck="false" data-key-configured="' . ($key_configured ? '1' : '0') . '" placeholder="' . esc_attr($mask_placeholder) . '" />';
    echo '<span class="abb-api-status ' . ($key_configured ? 'is-configured' : 'is-missing') . '" role="status">' . esc_html($key_status_label) . '</span>';
    if ($link !== '') {
        echo '<a class="button button-secondary abb-api-link" href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Get API key', 'cbiastudio-blogflow-ai') . '</a>';
    }
    echo '</div>';
    echo '</div>';
}
echo '</div>'; // api row texto

// API keys: imagen
echo '<div class="abb-api-row">';
echo '<label>' . esc_html__('API key (image)', 'cbiastudio-blogflow-ai') . '</label>';
foreach ($image_providers_list as $pkey => $pdef) {
    $key_val = '';
    if ($pkey === 'openai') $key_val = (string)($s['openai_api_key'] ?? '');
    if ($pkey === 'google') $key_val = (string)($s['google_api_key'] ?? '');
    if ($pkey === 'deepseek') $key_val = (string)($s['deepseek_api_key'] ?? '');
    if ($key_val === '' && !empty($provider_settings['providers'][$pkey]['api_key'])) $key_val = (string)$provider_settings['providers'][$pkey]['api_key'];
    $key_configured = function_exists('cbia_has_provider_api_key') && cbia_has_provider_api_key($pkey);
    $mask_placeholder = $key_configured ? '••••••••••••••••' : '';
    $key_status_label = $key_configured
        ? __('API key configured', 'cbiastudio-blogflow-ai')
        : __('API key not configured', 'cbiastudio-blogflow-ai');
    $link = $provider_key_urls[$pkey] ?? '';
    echo '<div class="abb-provider-key" data-scope="image" data-provider="' . esc_attr($pkey) . '" style="display:none;">';
    echo '<div class="abb-api-input">';
		echo '<input class="abb-input" type="password" name="provider_api_key_image[' . esc_attr($pkey) . ']" value="" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" spellcheck="false" data-key-configured="' . ($key_configured ? '1' : '0') . '" placeholder="' . esc_attr($mask_placeholder) . '" />';
    echo '<span class="abb-api-status ' . ($key_configured ? 'is-configured' : 'is-missing') . '" role="status">' . esc_html($key_status_label) . '</span>';
    if ($link !== '') {
        echo '<a class="button button-secondary abb-api-link" href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Get API key', 'cbiastudio-blogflow-ai') . '</a>';
    }
    echo '</div>';
    echo '</div>';
}
echo '</div>'; // api row imagen

echo '<div class="abb-actions-row" style="margin-top:10px;">';
echo '<button type="submit" name="cbia_config_save_api_keys" value="1" class="button button-secondary">' . esc_html__('Save API keys', 'cbiastudio-blogflow-ai') . '</button>';
echo '<span class="description" style="margin-left:10px;">' . esc_html__('Saves provider keys without changing prompts, lengths, categories or other generation settings.', 'cbiastudio-blogflow-ai') . '</span>';
echo '</div>';

echo '<p class="description" style="margin-top:8px;">' . esc_html__('You can use different providers for text and image generation. DeepSeek is available only for text.', 'cbiastudio-blogflow-ai') . '</p>';

echo '</div>'; // provider card
echo '</div>'; // section

echo '<div class="cbia-section">';
echo '<div class="cbia-section-title">' . esc_html__('Preferences', 'cbiastudio-blogflow-ai') . '</div>';
echo '<table class="form-table" role="presentation">';

echo '<tr><th scope="row"><label>' . esc_html__('Temperature', 'cbiastudio-blogflow-ai') . '</label></th><td>';
echo '<input type="text" name="openai_temperature" value="' . esc_attr((string)$s['openai_temperature']) . '" style="width:120px;" />';
echo '<p class="description">' . esc_html__('Recommended range: 0.0 to 1.0 (max 2.0).', 'cbiastudio-blogflow-ai') . '</p>';
echo '</td></tr>';

echo '<tr><th scope="row"><label>' . esc_html__('Max output tokens', 'cbiastudio-blogflow-ai') . '</label></th><td>';
echo '<input type="number" min="1500" max="12000" name="responses_max_output_tokens" value="' . esc_attr((string)$s['responses_max_output_tokens']) . '" style="width:120px;" />';
echo '<p class="description">' . esc_html__('Increase this value if the generated text is cut off. Recommended: 6000-8000.', 'cbiastudio-blogflow-ai') . '</p>';
echo '</td></tr>';

echo '<tr><th scope="row"><label>' . esc_html__('Post length', 'cbiastudio-blogflow-ai') . '</label></th><td>';
$variants = [
    'short'  => __('Short (~1000 words)', 'cbiastudio-blogflow-ai'),
    'medium' => __('Medium (~1800-2000 words)', 'cbiastudio-blogflow-ai'),
    'long'   => __('Long (~2000-2200 words)', 'cbiastudio-blogflow-ai'),
];
echo '<div class="cbia-blog-choice-pills cbia-config-length-pills" id="cbia-config-length-pills">';
foreach ($variants as $k => $label) {
    echo '<label class="' . ($s['post_length_variant'] === $k ? 'is-active' : '') . '">';
    echo '<input type="radio" name="post_length_variant" value="' . esc_attr($k) . '" ' . checked($s['post_length_variant'], $k, false) . ' /> ';
    echo esc_html($label);
    echo '</label>';
}
echo '</div>';
echo '</td></tr>';

echo '<tr><th scope="row"><label>' . esc_html__('Featured image prompt', 'cbiastudio-blogflow-ai') . '</label></th><td>';
echo '<p class="description">' . esc_html__('The base prompt is already optimized. It is automatically combined with [IMAGE: ...] and the post title. You only need to edit it if you want to change the style.', 'cbiastudio-blogflow-ai') . '</p>';
echo '<p class="description">' . esc_html__('Available placeholders: {title}, {desc}, {format}.', 'cbiastudio-blogflow-ai') . '</p>';

echo '<div class="cbia-prompt-panel" style="margin-top:10px;">';
echo '<div class="cbia-prompt-actions" style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">';
echo '<button type="button" class="button cbia-prompt-btn" data-type="featured" data-idx="0">' . esc_html__('Edit featured prompt', 'cbiastudio-blogflow-ai') . '</button>';
echo '</div>';
echo '<p class="description">' . esc_html__('The post title is inserted automatically into {title}.', 'cbiastudio-blogflow-ai') . '</p>';
echo '</div>';

echo '<div id="cbia-prompt-modal" class="cbia-modal" style="display:none;">';
echo '  <div class="cbia-modal-inner">';
echo '    <div class="cbia-modal-header">';
echo '      <strong id="cbia-prompt-title">' . esc_html__('Edit prompt', 'cbiastudio-blogflow-ai') . '</strong>';
echo '      <button type="button" class="button-link cbia-modal-close">' . esc_html__('Close', 'cbiastudio-blogflow-ai') . '</button>';
echo '    </div>';
echo '    <textarea id="cbia-prompt-text" rows="8" style="width:100%;"></textarea>';
echo '    <div class="cbia-modal-actions">';
echo '      <button type="button" class="button button-primary" id="cbia-prompt-save">' . esc_html__('Save base prompt', 'cbiastudio-blogflow-ai') . '</button>';
echo '    </div>';
echo '    <div class="cbia-modal-status" id="cbia-prompt-status"></div>';
echo '  </div>';
echo '</div>';

echo '</td></tr>';

echo '<tr class="abb-provider-model" data-scope="image" data-provider="openai"' . ($image_provider === 'openai' ? '' : ' style="display:none;"') . '>';
echo '<th scope="row"><label>' . esc_html__('Image quality', 'cbiastudio-blogflow-ai') . '</label></th><td>';
echo '<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">';
foreach ($quality_fields as $field_name => $field) {
    echo '<div class="abb-field" style="flex:1 1 240px;max-width:320px;">';
    echo '<label for="' . esc_attr($field['id']) . '">' . esc_html($field['label']) . '</label>';
    echo '<select id="' . esc_attr($field['id']) . '" name="' . esc_attr($field_name) . '" form="cbia-config-form" class="abb-select cbia-image-quality-select">';
    foreach ($field['options'] as $quality_key => $quality_label) echo '<option value="' . esc_attr($quality_key) . '" ' . selected($field['value'], $quality_key, false) . '>' . esc_html($quality_label) . '</option>';
    echo '</select><p class="description">' . esc_html($field['help']) . '</p></div>';
}
echo '</div></td></tr>';

echo '<tr><th scope="row"><label>' . esc_html__('Internal images (Pro)', 'cbiastudio-blogflow-ai') . '</label></th><td>';
$images_limit_options = [
    1 => __('0 internal images (featured only)', 'cbiastudio-blogflow-ai'),
    2 => __('1 internal image', 'cbiastudio-blogflow-ai'),
    3 => __('2 internal images', 'cbiastudio-blogflow-ai'),
    4 => __('3 internal images', 'cbiastudio-blogflow-ai'),
];
echo '<input type="hidden" name="images_limit" id="cbia_images_limit_hidden" value="' . esc_attr((string)(int)$s['images_limit']) . '" />';
echo '<select id="cbia_images_limit_ui" name="images_limit_ui" class="abb-select" style="width:220px;" onchange="var h=document.getElementById(\'cbia_images_limit_hidden\'); if(h){h.value=this.value;}" ' . disabled(!$internal_images_enabled, true, false) . '>';
foreach ($images_limit_options as $val => $label) {
    if (!$internal_images_enabled && (int)$val > 1) continue;
    echo '<option value="' . esc_attr((string)$val) . '" ' . selected((int)$s['images_limit'], $val, false) . '>' . esc_html($label) . '</option>';
}
echo '</select>';
echo '<p class="description">' . esc_html__('The featured image is always generated. This selector controls only internal images.', 'cbiastudio-blogflow-ai') . '</p>';
echo '<div class="abb-provider-model" data-scope="image" data-provider="openai"' . ($image_provider === 'openai' ? '' : ' style="display:none;"') . '>';
echo '<div id="cbia-image-cost-estimate" class="notice notice-info inline" style="margin:14px 0 0;padding:10px 12px;">';
echo '<p><strong>' . esc_html__('Estimated image output cost:', 'cbiastudio-blogflow-ai') . '</strong> <span data-cbia-image-total></span></p>';
echo '<p data-cbia-image-breakdown class="description"></p>';
echo '<p class="description">' . esc_html__('Indicative estimate based on OpenAI output prices verified on July 10, 2026. Actual cost may also include prompt tokens and, for edits, input images. OpenAI may change its prices.', 'cbiastudio-blogflow-ai') . '</p>';
echo '</div>';
echo '</div>';
if (!$internal_images_enabled) {
    echo '<p class="description"><strong>' . esc_html__('Pro feature:', 'cbiastudio-blogflow-ai') . '</strong> ' . esc_html__('internal images are available in Pro only.', 'cbiastudio-blogflow-ai') . '</p>';
    echo '<p class="description">' . esc_html__('This module generates and styles internal article images (up to 3 slots) with independent prompts and formats.', 'cbiastudio-blogflow-ai') . '</p>';
}
echo '</td></tr>';

$formats = function_exists('cbia_image_formats_catalog') ? cbia_image_formats_catalog() : [
    'panoramic_1536x1024' => ['label' => 'Panoramica (1536x1024)'],
    'banner_1536x1024' => ['label' => 'Banner (1536x1024, encuadre amplio + headroom 25-35%)'],
];
if ($internal_images_enabled) {
    for ($i = 1; $i <= 3; $i++) {
        $i_int = (int)$i;
        $i_label = esc_html((string)$i_int);
        $key = 'image_format_internal_' . $i;
        $current_internal_format = (string)($s[$key] ?? ($s['image_format_body'] ?? 'banner_1536x1024'));
        // translators: %d is the internal image slot number (1-3).
        echo '<tr><th scope="row"><label>' . esc_html(sprintf(__('Internal image %d format', 'cbiastudio-blogflow-ai'), $i_int)) . '</label></th><td>';
        echo '<div class="abb-inline-row">';
        echo '<select name="' . esc_attr($key) . '" class="abb-select cbia-image-format-select" data-slot="' . esc_attr((string)$i_int) . '" style="width:260px;">';
        foreach ($formats as $fmt_key => $meta) {
            $label = (string)($meta['label'] ?? $fmt_key);
            echo '<option value="' . esc_attr($fmt_key) . '" data-size="' . esc_attr((string)($meta['size'] ?? '')) . '" ' . selected($current_internal_format, $fmt_key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        // translators: %d is the internal image slot number (1-3).
        echo '<button type="button" class="button cbia-prompt-btn cbia-prompt-inline-btn" data-type="internal" data-idx="' . esc_attr((string)$i_int) . '">' . esc_html(sprintf(__('Edit internal prompt %d', 'cbiastudio-blogflow-ai'), $i_int)) . '</button>';
        echo '</div>';
        // translators: %d is the internal image slot number (1-3).
        echo '<p class="description">' . esc_html(sprintf(__('This format applies only to internal image %d. The base prompt is already optimized.', 'cbiastudio-blogflow-ai'), $i_int)) . '</p>';
        echo '</td></tr>';
    }

    echo '<tr><th scope="row"><label>' . esc_html__('Internal banner CSS', 'cbiastudio-blogflow-ai') . '</label></th><td>';
    echo '<textarea name="content_images_banner_css" rows="8" class="large-text code">' . esc_textarea((string)($s['content_images_banner_css'] ?? '')) . '</textarea>';
    echo '<p class="description">' . esc_html__('Applied to img.cbia-banner. You can adjust it for your theme without touching plugin code.', 'cbiastudio-blogflow-ai') . '</p>';
    echo '</td></tr>';
} else {
    echo '<tr><th scope="row"><label>' . esc_html__('Internal image slots (Pro)', 'cbiastudio-blogflow-ai') . '</label></th><td>';
    echo '<p class="description">' . esc_html__('Slot formats, per-slot prompts and internal image CSS are available in Pro only.', 'cbiastudio-blogflow-ai') . '</p>';
    echo '<p class="description">' . esc_html__('Activate CBIAStudio BlogFlow Pro to configure internal image 1/2/3 and their prompt templates.', 'cbiastudio-blogflow-ai') . '</p>';
    echo '</td></tr>';
}

echo '<tr><th scope="row"><label>' . esc_html__('If an image model fails', 'cbiastudio-blogflow-ai') . '</label></th><td>';
$image_failover = (string)($s['image_failover'] ?? 'continue');
echo '<select name="image_failover" class="abb-select" style="width:260px;">';
echo '<option value="continue" ' . selected($image_failover, 'continue', false) . '>' . esc_html__('Continue with the next model', 'cbiastudio-blogflow-ai') . '</option>';
echo '<option value="stop" ' . selected($image_failover, 'stop', false) . '>' . esc_html__('Stop post creation', 'cbiastudio-blogflow-ai') . '</option>';
echo '</select>';
echo '<p class="description">' . esc_html__('By default, the next model is tried if there is an error.', 'cbiastudio-blogflow-ai') . '</p>';
echo '</td></tr>';

echo '</table>';
echo '</div>';

echo '<div class="cbia-section">';
echo '<div class="cbia-section-title">' . esc_html__('Integrations', 'cbiastudio-blogflow-ai') . '</div>';
// Aviso Yoast al final de la configuracion
$yoast_active = defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
if (!$yoast_active) {
    echo '<p class="description"><strong>' . esc_html__('Yoast SEO:', 'cbiastudio-blogflow-ai') . '</strong> ' . esc_html__('not detected. If you install it, the plugin can automatically update the meta description, keyphrase and SEO/readability scores when each post is created.', 'cbiastudio-blogflow-ai') . '</p>';
} else {
    echo '<p class="description"><strong>' . esc_html__('Yoast SEO:', 'cbiastudio-blogflow-ai') . '</strong> ' . esc_html__('detected. The meta description, keyphrase and SEO/readability scores are updated automatically when each post is created.', 'cbiastudio-blogflow-ai') . '</p>';
}
echo '</div>';

echo '<p>';
echo '<button type="submit" name="cbia_config_save" class="button button-primary">' . esc_html__('Save settings', 'cbiastudio-blogflow-ai') . '</button>';
echo '</p>';

ob_start();
?>
(function(){
    var form = document.querySelector('.cbia-view-container form');
    var ui = document.getElementById('cbia_images_limit_ui');
    var hidden = document.getElementById('cbia_images_limit_hidden');
    var lengthPills = document.getElementById('cbia-config-length-pills');
    var quality = document.getElementById('cbia-image-quality');
    var featuredQuality = document.getElementById('cbia-featured-image-quality');
    var contentQuality = document.getElementById('cbia-content-image-quality');
    var estimateBox = document.getElementById('cbia-image-cost-estimate');
    var pricing = <?php echo wp_json_encode(class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_pricing_catalog() : array()); ?>;
    var strings = <?php echo wp_json_encode(array(
        'variable' => __('Price varies: OpenAI will select the quality automatically.', 'cbiastudio-blogflow-ai'),
        'unavailable' => __('Price is not available for this custom size.', 'cbiastudio-blogflow-ai'),
        'total' => __('%s per article', 'cbiastudio-blogflow-ai'),
        'breakdown' => __('Featured: %1$s (%2$s). Content: %3$d × %4$s (%5$s).', 'cbiastudio-blogflow-ai'),
    )); ?>;
    function money(micro){ return '$' + (micro / 1000000).toFixed(3); }
    function selectedImageModel(){
        var provider = document.querySelector('select[name="image_provider"]');
        var key = provider ? provider.value : 'openai';
        var select = document.querySelector('.cbia-image-model-select[name="image_model_by_provider[' + key + ']"]');
        return select ? select.value : '';
    }
    function updateImageEstimate(){
        if(!quality || !featuredQuality || !contentQuality || !estimateBox) return;
        var model = selectedImageModel();
        var globalQ = quality.value || 'auto';
        var featuredQ = featuredQuality.value === 'inherit' ? globalQ : featuredQuality.value;
        var contentQ = contentQuality.value === 'inherit' ? globalQ : contentQuality.value;
        var totalImages = Math.max(1, parseInt(ui ? ui.value : '1', 10) || 1);
        var internalCount = Math.max(0, totalImages - 1);
        var totalNode = estimateBox.querySelector('[data-cbia-image-total]');
        var detailNode = estimateBox.querySelector('[data-cbia-image-breakdown]');
        if(featuredQ === 'auto' || (internalCount > 0 && contentQ === 'auto')){
            if(totalNode) totalNode.textContent = strings.variable;
            if(detailNode) detailNode.textContent = strings.breakdown.replace('%1$s', strings.variable).replace('%2$s', featuredQ).replace('%3$d', String(internalCount)).replace('%4$s', strings.variable).replace('%5$s', contentQ);
            return;
        }
        var featuredPrice = pricing[model] && pricing[model][featuredQ] ? pricing[model][featuredQ]['1536x1024'] : null;
        var total = featuredPrice;
        var available = Number.isFinite(featuredPrice);
        for(var i=1; i<totalImages; i++){
            var format = document.querySelector('.cbia-image-format-select[data-slot="' + i + '"]');
            var option = format && format.options ? format.options[format.selectedIndex] : null;
            var size = option ? option.getAttribute('data-size') : '1536x1024';
            var price = pricing[model] && pricing[model][contentQ] ? pricing[model][contentQ][size] : null;
            if(!Number.isFinite(price)){ available = false; break; }
            total += price;
        }
        if(totalNode) totalNode.textContent = available ? strings.total.replace('%s', money(total)) : strings.unavailable;
        if(detailNode) detailNode.textContent = strings.breakdown.replace('%1$s', Number.isFinite(featuredPrice) ? money(featuredPrice) : strings.unavailable).replace('%2$s', featuredQ).replace('%3$d', String(internalCount)).replace('%4$s', available && internalCount > 0 ? money((total-featuredPrice)/internalCount) : money(0)).replace('%5$s', contentQ);
    }
    function syncChoicePills(wrap){
        if(!wrap) return;
        wrap.querySelectorAll('label').forEach(function(label){
            var input = label.querySelector('input');
            label.classList.toggle('is-active', !!(input && input.checked));
        });
    }
    if (lengthPills) {
        lengthPills.addEventListener('change', function(){
            syncChoicePills(lengthPills);
        });
        syncChoicePills(lengthPills);
    }
    document.addEventListener('change', function(event){
        if(event.target && (event.target.matches('.cbia-image-quality-select') || event.target === ui || event.target.matches('.cbia-image-model-select, .cbia-image-format-select, select[name="image_provider"]'))){
            updateImageEstimate();
        }
    });
    updateImageEstimate();
    if(!form || !ui || !hidden) return;
    hidden.value = ui.value;
    form.addEventListener('submit', function(){
        hidden.value = ui.value || hidden.value || '3';
    });
})();
<?php
wp_add_inline_script('abb-admin', (string)ob_get_clean(), 'after');

echo '</form>';

echo '<section class="cbia-advanced-tools cbia-section">';
echo '<div class="cbia-section-header">';
echo '<div class="cbia-section-title">' . esc_html__('Support and advanced tools', 'cbiastudio-blogflow-ai') . '</div>';
echo '<p class="description">' . esc_html__('Maintenance and support tools. They no longer need their own tabs because the normal plugin flow covers most daily work.', 'cbiastudio-blogflow-ai') . '</p>';
echo '</div>';

echo '<details class="cbia-advanced-card">';
echo '<summary><span class="cbia-advanced-card-title">' . esc_html__('Yoast legacy', 'cbiastudio-blogflow-ai') . '</span><span class="cbia-advanced-card-kicker">' . esc_html__('Occasional maintenance', 'cbiastudio-blogflow-ai') . '</span></summary>';
echo '<div class="cbia-advanced-card-body">';
if (!$yoast_active) {
    echo '<p class="description" style="margin-top:0;"><strong>' . esc_html__('Yoast SEO:', 'cbiastudio-blogflow-ai') . '</strong> ' . esc_html__('not detected. These actions only make sense if Yoast is installed and active.', 'cbiastudio-blogflow-ai') . '</p>';
} else {
    echo '<p class="description" style="margin-top:0;"><strong>' . esc_html__('Yoast SEO:', 'cbiastudio-blogflow-ai') . '</strong> ' . esc_html__('detected. Use these actions only for legacy maintenance, one-off recalculations or old content migrations.', 'cbiastudio-blogflow-ai') . '</p>';
}
echo '<form method="post" class="cbia-advanced-form">';
wp_nonce_field('cbia_yoast_nonce_action', 'cbia_yoast_nonce');
echo '<div class="cbia-advanced-grid">';
echo '<p><label><strong>' . esc_html__('Batch', 'cbiastudio-blogflow-ai') . '</strong></label><br/><input type="number" name="cbia_yoast_batch" min="1" max="500" value="' . esc_attr((string)$yoast_batch) . '" style="width:110px;" /></p>';
echo '<p><label><strong>' . esc_html__('Offset', 'cbiastudio-blogflow-ai') . '</strong></label><br/><input type="number" name="cbia_yoast_offset" min="0" value="' . esc_attr((string)$yoast_offset) . '" style="width:110px;" /></p>';
echo '</div>';
echo '<p class="cbia-advanced-checks">';
echo '<label><input type="checkbox" name="cbia_yoast_force" value="1" ' . checked($yoast_force, true, false) . ' /> ' . esc_html__('Force (overwrite metas/scores even if they already exist)', 'cbiastudio-blogflow-ai') . '</label>';
echo '<label><input type="checkbox" name="cbia_yoast_include_unmarked" value="1" ' . checked(!$yoast_only_cbia, true, false) . ' /> ' . esc_html__('Include non-CBIA posts', 'cbiastudio-blogflow-ai') . '</label>';
echo '</p>';
echo '<div class="cbia-advanced-actions">';
echo '<button type="submit" class="button button-secondary" name="cbia_yoast_action" value="metas">' . esc_html__('Recalculate metas', 'cbiastudio-blogflow-ai') . '</button>';
echo '<button type="submit" class="button button-secondary" name="cbia_yoast_action" value="semaphore">' . esc_html__('Refresh traffic lights', 'cbiastudio-blogflow-ai') . '</button>';
echo '<button type="submit" class="button button-primary" name="cbia_yoast_action" value="both">' . esc_html__('Metas + traffic lights', 'cbiastudio-blogflow-ai') . '</button>';
echo '<button type="submit" class="button" name="cbia_yoast_action" value="clear_log">' . esc_html__('Clear Yoast log', 'cbiastudio-blogflow-ai') . '</button>';
echo '</div>';
echo '<hr/>';
echo '<h4 style="margin:0 0 10px 0;">' . esc_html__('Mark older posts as CBIA', 'cbiastudio-blogflow-ai') . '</h4>';
echo '<div class="cbia-advanced-grid">';
echo '<p><label><strong>' . esc_html__('From (optional)', 'cbiastudio-blogflow-ai') . '</strong></label><br/><input type="datetime-local" name="cbia_yoast_date_from" value="" /></p>';
echo '<p><label><strong>' . esc_html__('To (optional)', 'cbiastudio-blogflow-ai') . '</strong></label><br/><input type="datetime-local" name="cbia_yoast_date_to" value="" /></p>';
echo '</div>';
echo '<p class="cbia-advanced-checks">';
echo '<label><input type="checkbox" name="cbia_yoast_only_signals" value="1" /> ' . esc_html__('Mark only if CBIA signals are detected', 'cbiastudio-blogflow-ai') . '</label>';
echo '</p>';
echo '<div class="cbia-advanced-actions">';
echo '<button type="submit" class="button button-primary" name="cbia_yoast_action" value="mark_legacy">' . esc_html__('Mark older posts as CBIA', 'cbiastudio-blogflow-ai') . '</button>';
echo '</div>';
echo '<p class="description">' . esc_html__('CBIA signals: FAQ/JSON-LD, pending markers or content markers.', 'cbiastudio-blogflow-ai') . '</p>';
echo '<textarea rows="10" readonly style="background:#f9f9f9;width:100%;">' . esc_textarea($yoast_log) . '</textarea>';
echo '</form>';
echo '</div>';
echo '</details>';

echo '<details class="cbia-advanced-card">';
echo '<summary><span class="cbia-advanced-card-title">' . esc_html__('Environment diagnostics', 'cbiastudio-blogflow-ai') . '</span><span class="cbia-advanced-card-kicker">' . esc_html__('Support', 'cbiastudio-blogflow-ai') . '</span></summary>';
echo '<div class="cbia-advanced-card-body">';
echo '<p class="description" style="margin-top:0;">' . esc_html__('Quick environment summary for support and debugging. It no longer needs its own tab.', 'cbiastudio-blogflow-ai') . '</p>';
echo '<div class="cbia-diagnostics-grid">';
foreach ($diag_info as $label => $value) {
    echo '<div class="cbia-diagnostics-item">';
    echo '<span class="cbia-diagnostics-label">' . esc_html((string)$label) . '</span>';
    echo '<code class="cbia-diagnostics-value">' . esc_html((string)$value) . '</code>';
    echo '</div>';
}
echo '</div>';
echo '<h4 style="margin:16px 0 10px 0;">' . esc_html__('Latest log lines', 'cbiastudio-blogflow-ai') . '</h4>';
echo '<textarea rows="10" readonly style="background:#f9f9f9;width:100%;">' . esc_textarea(implode("\n", $diag_log_lines)) . '</textarea>';
echo '</div>';
echo '</details>';

echo '</section>';

echo '</div>';
    }
}

cbia_render_view_config();
