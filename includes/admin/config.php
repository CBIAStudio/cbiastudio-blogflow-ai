<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * TAB: Configuracion
 * Guarda en cbia_settings:
 * - openai_api_key, openai_model, openai_temperature
 * - post_length_variant, images_limit
 * - prompt_single_all (+ prompts de imagen por seccion)
 * - default_category, keywords_to_categories, default_tags
 * - default_author_id (autor fijo para posts, útil para cron/evento)
 *
 * Sanitiza y MERGEA sin borrar campos de otros tabs.
 */

/* Helpers moved to includes/support/* (sanitize + config-catalog). */

/**
 * Guardado settings (POST)
 */
if (!function_exists('cbia_pro_config_handle_post')) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended -- Values are unslashed/sanitized and nonce is verified inside this handler.
	function cbia_pro_config_handle_post(): void {
		if (!is_admin()) return;
		if (!current_user_can('manage_options')) return;

		if (!isset($_POST['cbia_config_save'])) return;

		check_admin_referer('cbia_config_save_action', 'cbia_config_nonce');

		$settings = cbia_get_settings();
		// Sanitizar arrays de entrada primero
		$provider_api_key_post = isset($_POST['provider_api_key']) && is_array($_POST['provider_api_key'])
			? wp_unslash($_POST['provider_api_key'])
			: [];
		$provider_api_key_text_post = isset($_POST['provider_api_key_text']) && is_array($_POST['provider_api_key_text'])
			? wp_unslash($_POST['provider_api_key_text'])
			: [];
		$provider_api_key_image_post = isset($_POST['provider_api_key_image']) && is_array($_POST['provider_api_key_image'])
			? wp_unslash($_POST['provider_api_key_image'])
			: [];
		$text_models_post = isset($_POST['text_model']) && is_array($_POST['text_model'])
			? wp_unslash($_POST['text_model'])
			: [];
		$image_models_post = isset($_POST['image_model_by_provider']) && is_array($_POST['image_model_by_provider'])
			? wp_unslash($_POST['image_model_by_provider'])
			: [];
		$provider_base_url_post = isset($_POST['provider_base_url']) && is_array($_POST['provider_base_url'])
			? wp_unslash($_POST['provider_base_url'])
			: [];

		// CAMBIO: providers disponibles (texto/imagen)
		$providers_all = function_exists('cbia_providers_get_all') ? cbia_providers_get_all() : [];
		$providers_list = isset($providers_all['providers']) && is_array($providers_all['providers']) ? $providers_all['providers'] : [];
		// Submitted credentials are handled separately from model/settings persistence.
		$first_non_empty = function (string $provider, ...$vals) {
			foreach ($vals as $val) {
				if (!is_string($val)) continue;
				$val = function_exists('cbia_normalize_submitted_api_key') ? cbia_normalize_submitted_api_key($val, $provider) : trim($val);
				if ($val !== '') return $val;
			}
			return '';
		};
		$openai_consent = 1;

		// CAMBIO: proveedores de texto e imagen
		$text_provider = isset($_POST['text_provider']) ? sanitize_key((string) wp_unslash($_POST['text_provider'])) : (string)($settings['text_provider'] ?? '');
		if ($text_provider === '' && function_exists('cbia_providers_get_current_provider')) {
			$text_provider = cbia_providers_get_current_provider();
		}
		if ($text_provider === '' || !isset($providers_list[$text_provider])) $text_provider = 'openai';

		$image_provider = isset($_POST['image_provider']) ? sanitize_key((string) wp_unslash($_POST['image_provider'])) : (string)($settings['image_provider'] ?? '');
		if (
			$image_provider === ''
			|| !isset($providers_list[$image_provider])
			|| (function_exists('cbia_providers_supports_image') && !cbia_providers_supports_image($image_provider))
		) {
			$image_provider = 'openai';
		}

		$api_key_validation_warnings = array();
		$active_key_sources = array(
			array('provider' => $text_provider, 'values' => $provider_api_key_text_post),
			array('provider' => $image_provider, 'values' => $provider_api_key_image_post),
		);
		foreach ($active_key_sources as $active_key_source) {
			$candidate_provider = (string)$active_key_source['provider'];
			$candidate_source = (array)$active_key_source['values'];
			if (!array_key_exists($candidate_provider, $candidate_source) || (string)$candidate_source[$candidate_provider] === '') continue;
			$result = cbia_sanitize_provider_api_key($candidate_provider, (string)$candidate_source[$candidate_provider]);
			if (empty($result['valid'])) {
				$api_key_validation_warnings[] = cbia_provider_api_key_error_message($candidate_provider, (string)$result['code']);
			}
		}

		foreach (array_keys($providers_list) as $pkey) {
			$posted_key = $first_non_empty(
				$pkey,
				$pkey === $text_provider ? (string)($provider_api_key_text_post[$pkey] ?? '') : '',
				$pkey === $image_provider ? (string)($provider_api_key_image_post[$pkey] ?? '') : '',
				(string)($provider_api_key_post[$pkey] ?? '')
			);
			if ($posted_key === '') continue;
			cbia_store_provider_api_key($pkey, $posted_key);
		}

		$save_api_keys_only = isset($_POST['cbia_config_save_api_keys']);
		if ($save_api_keys_only) {

			if (!empty($api_key_validation_warnings)) set_transient('cbia_config_warnings', array_values(array_unique($api_key_validation_warnings)), 60);
			else delete_transient('cbia_config_warnings');
			$redirect_url = admin_url('admin.php?page=cbia&tab=config&keys_saved=1');
			if (!headers_sent()) {
				wp_safe_redirect($redirect_url);
				exit;
			}
			echo '<meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '">';
			exit;
		}

		$api_key = cbia_get_provider_api_key('openai');
		$google_api_key = cbia_get_provider_api_key('google');
		$deepseek_api_key = cbia_get_provider_api_key('deepseek');

		// CAMBIO: modelos por proveedor (texto)
		$text_model = isset($text_models_post[$text_provider]) ? sanitize_text_field((string)$text_models_post[$text_provider]) : '';
		if ($text_model === '' && $text_provider === 'openai' && isset($_POST['openai_model'])) {
			$text_model = sanitize_text_field(wp_unslash($_POST['openai_model']));
		}
		if ($text_model === '') {
			$text_model = function_exists('cbia_providers_get_recommended_text_model')
				? cbia_providers_get_recommended_text_model($text_provider)
				: 'gpt-5-mini';
		}
		$deepseek_thinking_mode = isset($_POST['deepseek_thinking_mode'])
			? sanitize_key((string) wp_unslash($_POST['deepseek_thinking_mode']))
			: (string)($settings['deepseek_thinking_mode'] ?? 'disabled');
		$deepseek_reasoning_effort = isset($_POST['deepseek_reasoning_effort'])
			? sanitize_key((string) wp_unslash($_POST['deepseek_reasoning_effort']))
			: (string)($settings['deepseek_reasoning_effort'] ?? 'high');
		if (function_exists('cbia_deepseek_normalize_runtime_config')) {
			$deepseek_config = cbia_deepseek_normalize_runtime_config($text_provider === 'deepseek' ? $text_model : 'deepseek-v4-flash', $deepseek_thinking_mode, $deepseek_reasoning_effort);
			$deepseek_thinking_mode = (string)$deepseek_config['thinking'];
			$deepseek_reasoning_effort = (string)$deepseek_config['reasoning_effort'];
			if ($text_provider === 'deepseek') $text_model = (string)$deepseek_config['model_effective'];
		}
		if ($text_provider === 'openai') {
			$text_model = cbia_config_safe_model($text_model);
		}

		// CAMBIO: modelos por proveedor (imagen)
		$image_model = isset($image_models_post[$image_provider]) ? sanitize_text_field((string)$image_models_post[$image_provider]) : '';
		if ($image_model === '' && isset($_POST['image_model'])) {
			$image_model = sanitize_text_field(wp_unslash($_POST['image_model']));
		}
		if ($image_model === '') {
			$image_model = function_exists('cbia_providers_get_recommended_image_model')
				? cbia_providers_get_recommended_image_model($image_provider)
				: 'gpt-image-2';
		}
		if ($image_provider === 'openai' && class_exists('CBIA_Image_Pricing_Service')) {
			$image_model = CBIA_Image_Pricing_Service::validate_model($image_model);
		}
		$image_quality = isset($_POST['image_quality'])
			? sanitize_key((string) wp_unslash($_POST['image_quality']))
			: (string)($settings['image_quality'] ?? 'auto');
		$image_quality = class_exists('CBIA_Image_Pricing_Service')
			? CBIA_Image_Pricing_Service::validate_quality($image_quality)
			: (in_array($image_quality, array('auto', 'low', 'medium', 'high'), true) ? $image_quality : 'auto');
		$featured_image_quality = isset($_POST['featured_image_quality'])
			? sanitize_key((string) wp_unslash($_POST['featured_image_quality']))
			: (string)($settings['featured_image_quality'] ?? 'inherit');
		$content_image_quality = isset($_POST['content_image_quality'])
			? sanitize_key((string) wp_unslash($_POST['content_image_quality']))
			: (string)($settings['content_image_quality'] ?? 'inherit');
		$featured_image_quality = class_exists('CBIA_Image_Pricing_Service')
			? CBIA_Image_Pricing_Service::validate_specific_quality($featured_image_quality)
			: (in_array($featured_image_quality, array('inherit', 'auto', 'low', 'medium', 'high'), true) ? $featured_image_quality : 'inherit');
		$content_image_quality = class_exists('CBIA_Image_Pricing_Service')
			? CBIA_Image_Pricing_Service::validate_specific_quality($content_image_quality)
			: (in_array($content_image_quality, array('inherit', 'auto', 'low', 'medium', 'high'), true) ? $content_image_quality : 'inherit');

		// CAMBIO: compatibilidad con campo legacy openai_model
		$model = ($text_provider === 'openai') ? $text_model : (string)($settings['openai_model'] ?? 'gpt-5-mini');

		// CAMBIO: provider settings (texto + imagen)
		if (function_exists('cbia_providers_get_settings') && function_exists('cbia_providers_get_all')) {
			$provider_settings = cbia_providers_get_settings();
			$providers_all = cbia_providers_get_all();
			$providers_list = isset($providers_all['providers']) && is_array($providers_all['providers']) ? $providers_all['providers'] : [];
			$current_provider = $text_provider ?: ($provider_settings['provider'] ?? 'openai');
			if (!isset($providers_list[$current_provider])) $current_provider = 'openai';

			$providers_new = is_array($provider_settings['providers'] ?? null) ? $provider_settings['providers'] : [];
			foreach ($providers_list as $pkey => $pdef) {
				$mdl = isset($text_models_post[$pkey]) ? sanitize_text_field((string)$text_models_post[$pkey]) : (string)($providers_new[$pkey]['model'] ?? ($pdef['models'][0] ?? ''));
				if ($pkey === 'deepseek' && function_exists('cbia_deepseek_normalize_runtime_config')) {
					$mdl = (string)cbia_deepseek_normalize_runtime_config($mdl, $deepseek_thinking_mode, $deepseek_reasoning_effort)['model_effective'];
				}
				$img = isset($image_models_post[$pkey]) ? sanitize_text_field((string)$image_models_post[$pkey]) : (string)($providers_new[$pkey]['image_model'] ?? '');
				if ($pkey === 'openai' && class_exists('CBIA_Image_Pricing_Service')) $img = CBIA_Image_Pricing_Service::validate_model($img);
				if (function_exists('cbia_providers_supports_image') && !cbia_providers_supports_image($pkey)) {
					$img = '';
				}
				$base = isset($provider_base_url_post[$pkey]) ? sanitize_text_field((string)$provider_base_url_post[$pkey]) : (string)($providers_new[$pkey]['base_url'] ?? ($pdef['base_url'] ?? ''));
				if (!isset($providers_new[$pkey]) || !is_array($providers_new[$pkey])) $providers_new[$pkey] = array();
				$providers_new[$pkey]['model'] = $mdl;
				$providers_new[$pkey]['image_model'] = $img;
				$providers_new[$pkey]['base_url'] = $base;
			}

			if (function_exists('cbia_providers_save_settings')) {
				cbia_providers_save_settings([
					'provider'  => $current_provider,
					'current_provider' => $current_provider,
					'providers' => $providers_new,
				]);
			}
		}

		$temp = isset($_POST['openai_temperature'])
			? (float) str_replace(',', '.', (string) wp_unslash($_POST['openai_temperature']))
			: (float)($settings['openai_temperature'] ?? 0.7);

		if ($temp < 0) $temp = 0;
		if ($temp > 2) $temp = 2;

		$post_length_variant = isset($_POST['post_length_variant'])
			? sanitize_key((string) wp_unslash($_POST['post_length_variant']))
			: (string)($settings['post_length_variant'] ?? 'medium');

		if (!in_array($post_length_variant, ['short','medium','long'], true)) $post_length_variant = 'medium';

		$images_limit_raw = isset($_POST['images_limit']) ? wp_unslash($_POST['images_limit']) : null;
		if ($images_limit_raw === null && isset($_POST['images_limit_ui'])) {
			$images_limit_raw = wp_unslash($_POST['images_limit_ui']);
		}
		$images_limit = ($images_limit_raw !== null)
			? absint($images_limit_raw)
			: (int)($settings['images_limit'] ?? 3);
		if ($images_limit < 1) $images_limit = 1;
		if ($images_limit > 4) $images_limit = 4;
		if (function_exists('cbia_cap_enabled') && !cbia_cap_enabled('internal_images')) {
			$images_limit = 1;
		}

		$prompt_single_all = isset($_POST['prompt_single_all'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($_POST['prompt_single_all'])))
			: (string)($settings['prompt_single_all'] ?? '');

		$prompt_img_intro = isset($_POST['prompt_img_intro'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($_POST['prompt_img_intro'])))
			: (string)($settings['prompt_img_intro'] ?? '');

		$prompt_img_body = isset($_POST['prompt_img_body'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($_POST['prompt_img_body'])))
			: (string)($settings['prompt_img_body'] ?? '');

		$prompt_img_conclusion = isset($_POST['prompt_img_conclusion'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($_POST['prompt_img_conclusion'])))
			: (string)($settings['prompt_img_conclusion'] ?? '');

		$prompt_img_faq = isset($_POST['prompt_img_faq'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($_POST['prompt_img_faq'])))
			: (string)($settings['prompt_img_faq'] ?? '');

		$prompt_img_global = isset($_POST['prompt_img_global'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($_POST['prompt_img_global'])))
			: (string)($settings['prompt_img_global'] ?? '');

		$responses_max_output_tokens = isset($_POST['responses_max_output_tokens'])
			? absint(wp_unslash($_POST['responses_max_output_tokens']))
			: (int)($settings['responses_max_output_tokens'] ?? 6000);
		if ($responses_max_output_tokens < 1500) $responses_max_output_tokens = 1500;
		if ($responses_max_output_tokens > 12000) $responses_max_output_tokens = 12000;

		// Preset rapido por modelo (si viene del boton de preset, manda sobre el resto)
		$preset_key = isset($_POST['cbia_preset_model']) ? sanitize_text_field(wp_unslash($_POST['cbia_preset_model'])) : '';
		if ($preset_key !== '' && function_exists('cbia_config_Presets_catalog')) {
			$Presets = cbia_config_Presets_catalog();
			if (isset($Presets[$preset_key])) {
				$p = $Presets[$preset_key];
				$model = cbia_config_safe_model($p['openai_model'] ?? $model);
				// CAMBIO: aplicar preset tambien a text_model si proveedor texto es openai
				if ($text_provider === 'openai') {
					$text_model = $model;
				}
				$temp = isset($p['openai_temperature']) ? (float)$p['openai_temperature'] : (float)$temp;
				$responses_max_output_tokens = isset($p['responses_max_output_tokens']) ? (int)$p['responses_max_output_tokens'] : (int)$responses_max_output_tokens;
				/* translators: %s: preset key */
				cbia_log(sprintf('Preset aplicado en Config: %1$s', (string)$preset_key), 'INFO');
			}
		}

		$post_language = isset($_POST['post_language'])
			? sanitize_text_field(wp_unslash($_POST['post_language']))
			: (string)($settings['post_language'] ?? 'english');
		if ($post_language === '') $post_language = 'english';

		$content_images_banner_enabled = isset($_POST['content_images_banner_enabled'])
			? 1
			: (int)($settings['content_images_banner_enabled'] ?? 1);
		if ($content_images_banner_enabled !== 1) $content_images_banner_enabled = 0;

		// Preset rápido de CSS de banner (selector)
		$banner_preset_key = isset($_POST['banner_preset_key'])
			? sanitize_key((string)wp_unslash($_POST['banner_preset_key']))
			: (string)($settings['banner_preset_key'] ?? 'custom');

		// Formato de imagen por seccion (UI) - nota: el engine fuerza intro=panorámica y resto=banner (como en v8.4)
		$image_format_intro = isset($_POST['image_format_intro'])
			? cbia_config_sanitize_image_format(wp_unslash($_POST['image_format_intro']), 'panoramic_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_intro'] ?? ''), 'panoramic_1536x1024');

		$image_format_body = isset($_POST['image_format_body'])
			? cbia_config_sanitize_image_format(wp_unslash($_POST['image_format_body']), 'banner_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_body'] ?? ''), 'banner_1536x1024');

		$image_format_conclusion = isset($_POST['image_format_conclusion'])
			? cbia_config_sanitize_image_format(wp_unslash($_POST['image_format_conclusion']), 'banner_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_conclusion'] ?? ''), 'banner_1536x1024');

		$image_format_faq = isset($_POST['image_format_faq'])
			? cbia_config_sanitize_image_format(wp_unslash($_POST['image_format_faq']), 'banner_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_faq'] ?? ''), 'banner_1536x1024');

		$image_format_internal_1 = isset($_POST['image_format_internal_1'])
			? cbia_config_sanitize_image_format(wp_unslash($_POST['image_format_internal_1']), $image_format_body)
			: cbia_config_sanitize_image_format((string)($settings['image_format_internal_1'] ?? ''), $image_format_body);

		$image_format_internal_2 = isset($_POST['image_format_internal_2'])
			? cbia_config_sanitize_image_format(wp_unslash($_POST['image_format_internal_2']), $image_format_body)
			: cbia_config_sanitize_image_format((string)($settings['image_format_internal_2'] ?? ''), $image_format_body);

		$image_format_internal_3 = isset($_POST['image_format_internal_3'])
			? cbia_config_sanitize_image_format(wp_unslash($_POST['image_format_internal_3']), $image_format_body)
			: cbia_config_sanitize_image_format((string)($settings['image_format_internal_3'] ?? ''), $image_format_body);

		$content_images_banner_css = isset($_POST['content_images_banner_css'])
			? sanitize_textarea_field(wp_unslash($_POST['content_images_banner_css']))
			: (string)($settings['content_images_banner_css'] ?? '');

		$image_failover = isset($_POST['image_failover'])
			? sanitize_key((string) wp_unslash($_POST['image_failover']))
			: (string)($settings['image_failover'] ?? 'continue');
		if (!in_array($image_failover, ['continue', 'stop'], true)) $image_failover = 'continue';

		$default_category = isset($_POST['default_category'])
			? sanitize_text_field(wp_unslash($_POST['default_category']))
			: (string)($settings['default_category'] ?? 'News');

		if ($default_category === '') $default_category = 'News';

		$keywords_to_categories = isset($_POST['keywords_to_categories'])
			? cbia_sanitize_textarea_preserve_lines(wp_unslash($_POST['keywords_to_categories']))
			: (string)($settings['keywords_to_categories'] ?? '');

		$default_tags = isset($_POST['default_tags'])
			? cbia_sanitize_csv_tags(sanitize_text_field(wp_unslash($_POST['default_tags'])))
			: (string)($settings['default_tags'] ?? '');

		// Autor por defecto (para cron/evento): 0 = automatico (usuario actual o admin)
		$default_author_id = isset($_POST['default_author_id']) ? absint(wp_unslash($_POST['default_author_id'])) : (int)($settings['default_author_id'] ?? 0);
		if ($default_author_id < 0) $default_author_id = 0;

		$partial = [
			'deepseek_thinking_mode' => $deepseek_thinking_mode,
			'deepseek_reasoning_effort' => $deepseek_reasoning_effort,
			'openai_consent'         => $openai_consent,
			// CAMBIO: provider/model texto e imagen
			'text_provider'          => $text_provider,
			'text_model'             => $text_model,
			'image_provider'         => $image_provider,
			'image_model'            => $image_model,
			'image_quality'          => $image_quality,
			'featured_image_quality' => $featured_image_quality,
			'content_image_quality'  => $content_image_quality,
			// CAMBIO: compatibilidad legacy OpenAI
			'openai_model'           => $model,
			'openai_temperature'     => $temp,
			'post_length_variant'    => $post_length_variant,
			'images_limit'           => $images_limit,
			'prompt_single_all'      => $prompt_single_all,
			'prompt_img_intro'       => $prompt_img_intro,
			'prompt_img_body'        => $prompt_img_body,
			'prompt_img_conclusion'  => $prompt_img_conclusion,
			'prompt_img_faq'         => $prompt_img_faq,
			'prompt_img_global'      => $prompt_img_global,
			'responses_max_output_tokens' => $responses_max_output_tokens,
			'post_language'          => $post_language,
			'content_images_banner_enabled' => $content_images_banner_enabled,
			'content_images_banner_css' => $content_images_banner_css,
			'banner_preset_key'      => $banner_preset_key,
			'image_format_intro'     => $image_format_intro,
			'image_format_body'      => $image_format_body,
			'image_format_conclusion'=> $image_format_conclusion,
			'image_format_faq'       => $image_format_faq,
			'image_format_internal_1' => $image_format_internal_1,
			'image_format_internal_2' => $image_format_internal_2,
			'image_format_internal_3' => $image_format_internal_3,
			'image_failover'         => $image_failover,
			'default_category'       => $default_category,
			'keywords_to_categories' => $keywords_to_categories,
			'default_tags'           => $default_tags,
			'default_author_id'      => $default_author_id,
		];

		// CAMBIO: avisos por API key faltante (sin bloquear guardado)
		$warnings = $api_key_validation_warnings;
		$key_map = [
			'openai'  => $api_key,
			'google'  => $google_api_key,
			'deepseek'=> $deepseek_api_key,
		];
		if (empty($key_map[$text_provider] ?? '')) {
			/* translators: %s: provider name */
				$warnings[] = sprintf('Missing API key for %s text generation. Add it to use this provider.', ucfirst($text_provider));
		}
		if ($image_provider === 'google') {
			if (empty($google_api_key)) {
				$warnings[] = 'Missing Google API key for image generation with Imagen. Add it to use this model.';
			}
		} else {
			if (empty($key_map[$image_provider] ?? '')) {
				/* translators: 1: provider name, 2: provider name */
				$warnings[] = sprintf('Missing API key for %1$s image generation. Add it to use %2$s image models.', ucfirst($image_provider), ucfirst($image_provider));
			}
		}
		if (!empty($warnings)) {
			set_transient('cbia_config_warnings', $warnings, 60);
		} else {
			delete_transient('cbia_config_warnings');
		}

		$merged = cbia_update_settings_merge($partial);
		$stored = get_option('cbia_settings', []);
		if (!is_array($stored)) $stored = [];
		$stored['images_limit'] = $images_limit;
		update_option('cbia_settings', $stored, false);

		// Evita ruido en el log global durante ejecuciones largas de lotes.

		$redirect_url = admin_url('admin.php?page=cbia&tab=config&saved=1');
		if (!headers_sent()) {
			wp_safe_redirect($redirect_url);
			exit;
		}
		echo '<meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '">';
		exit;
	}
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended
}

/**
 * En entorno FREE+PRO, desactiva el handler FREE para que no pise ajustes PRO
 * (ej: images_limit forzado a 1).
 */
add_action('admin_init', function () {
	if (function_exists('cbia_config_handle_post')) {
		remove_action('admin_init', 'cbia_config_handle_post');
	}
}, 0);

add_action('admin_init', 'cbia_pro_config_handle_post', 1);

/**
 * Render tab
 */
if (!function_exists('cbia_render_tab_config')) {
    function cbia_render_tab_config(){
        if (!current_user_can('manage_options')) return;

        $view = (defined('CBIA_INCLUDES_DIR') ? CBIA_INCLUDES_DIR . 'admin/views/config.php' : __DIR__ . '/views/config.php');
        if (file_exists($view)) {
            include $view;
            return;
        }

        echo '<p>Could not load Configuration.</p>';
    }
}
