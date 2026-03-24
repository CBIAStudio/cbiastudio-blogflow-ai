<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * TAB: Configuracion
 * Guarda en cbia_settings:
 * - openai_api_key, openai_model, openai_temperature
 * - post_length_variant, images_limit
 * - prompt_single_all (+ prompts de imagen por seccion)
 * - default_category, keywords_to_categories, default_tags
 * - default_author_id (autor fijo para posts, ÃƒÂºtil para cron/evento)
 *
 * Sanitiza y MERGEA sin borrar campos de otros tabs.
 */

/* Helpers moved to includes/support/* (sanitize + config-catalog). */

/**
 * Guardado settings (POST)
 */
if (!function_exists('cbia_config_handle_post')) {
	function cbia_config_handle_post(): void {
		if (!is_admin()) return;
		if (!current_user_can('manage_options')) return;
		$post = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();

		if (!isset($post['cbia_config_save'])) return;

		check_admin_referer('cbia_config_save_action', 'cbia_config_nonce');

		$settings = cbia_get_settings();
		// Sanitizar arrays de entrada primero
		$provider_api_key_post = isset($post['provider_api_key']) && is_array($post['provider_api_key'])
			? wp_unslash($post['provider_api_key'])
			: [];
		$provider_api_key_text_post = isset($post['provider_api_key_text']) && is_array($post['provider_api_key_text'])
			? wp_unslash($post['provider_api_key_text'])
			: [];
		$provider_api_key_image_post = isset($post['provider_api_key_image']) && is_array($post['provider_api_key_image'])
			? wp_unslash($post['provider_api_key_image'])
			: [];
		$text_models_post = isset($post['text_model']) && is_array($post['text_model'])
			? wp_unslash($post['text_model'])
			: [];
		$image_models_post = isset($post['image_model_by_provider']) && is_array($post['image_model_by_provider'])
			? wp_unslash($post['image_model_by_provider'])
			: [];
		$provider_base_url_post = isset($post['provider_base_url']) && is_array($post['provider_base_url'])
			? wp_unslash($post['provider_base_url'])
			: [];

		// CAMBIO: providers disponibles (texto/imagen)
		$providers_all = function_exists('cbia_providers_get_all') ? cbia_providers_get_all() : [];
		$providers_list = isset($providers_all['providers']) && is_array($providers_all['providers']) ? $providers_all['providers'] : [];

		// CAMBIO: API keys por proveedor (con fallback legacy)
		$first_non_empty = function (...$vals) {
			foreach ($vals as $val) {
				if (!is_string($val)) continue;
				$val = trim($val);
				if ($val !== '') return $val;
			}
			return '';
		};
		$api_key = $first_non_empty(
			sanitize_text_field((string)($provider_api_key_text_post['openai'] ?? '')),
			sanitize_text_field((string)($provider_api_key_image_post['openai'] ?? '')),
			sanitize_text_field((string)($provider_api_key_post['openai'] ?? '')),
			isset($post['openai_api_key']) ? sanitize_text_field(wp_unslash($post['openai_api_key'])) : '',
			sanitize_text_field((string)($settings['openai_api_key'] ?? ''))
		);
		$google_api_key = $first_non_empty(
			sanitize_text_field((string)($provider_api_key_text_post['google'] ?? '')),
			sanitize_text_field((string)($provider_api_key_image_post['google'] ?? '')),
			sanitize_text_field((string)($provider_api_key_post['google'] ?? '')),
			isset($post['google_api_key']) ? sanitize_text_field(wp_unslash($post['google_api_key'])) : '',
			sanitize_text_field((string)($settings['google_api_key'] ?? ''))
		);
		$deepseek_api_key = $first_non_empty(
			sanitize_text_field((string)($provider_api_key_text_post['deepseek'] ?? '')),
			sanitize_text_field((string)($provider_api_key_image_post['deepseek'] ?? '')),
			sanitize_text_field((string)($provider_api_key_post['deepseek'] ?? '')),
			isset($post['deepseek_api_key']) ? sanitize_text_field(wp_unslash($post['deepseek_api_key'])) : '',
			sanitize_text_field((string)($settings['deepseek_api_key'] ?? ''))
		);
		$openai_consent = 1;

		// CAMBIO: proveedores de texto e imagen
		$text_provider = isset($post['text_provider']) ? sanitize_key((string) wp_unslash($post['text_provider'])) : (string)($settings['text_provider'] ?? '');
		if ($text_provider === '' && function_exists('cbia_providers_get_current_provider')) {
			$text_provider = cbia_providers_get_current_provider();
		}
		if ($text_provider === '' || !isset($providers_list[$text_provider])) $text_provider = 'openai';

		$image_provider = isset($post['image_provider']) ? sanitize_key((string) wp_unslash($post['image_provider'])) : (string)($settings['image_provider'] ?? '');
		if ($image_provider === '' || !isset($providers_list[$image_provider])) $image_provider = 'openai';

		// CAMBIO: modelos por proveedor (texto)
		$text_model = isset($text_models_post[$text_provider]) ? sanitize_text_field((string)$text_models_post[$text_provider]) : '';
		if ($text_model === '' && $text_provider === 'openai' && isset($post['openai_model'])) {
			$text_model = sanitize_text_field(wp_unslash($post['openai_model']));
		}
		if ($text_model === '') {
			$text_model = function_exists('cbia_providers_get_recommended_text_model')
				? cbia_providers_get_recommended_text_model($text_provider)
				: 'gpt-4.1-mini';
		}
		if ($text_provider === 'openai') {
			$text_model = cbia_config_safe_model($text_model);
		}

		// CAMBIO: modelos por proveedor (imagen)
		$image_model = isset($image_models_post[$image_provider]) ? sanitize_text_field((string)$image_models_post[$image_provider]) : '';
		if ($image_model === '' && isset($post['image_model'])) {
			$image_model = sanitize_text_field(wp_unslash($post['image_model']));
		}
		if ($image_model === '') {
			$image_model = function_exists('cbia_providers_get_recommended_image_model')
				? cbia_providers_get_recommended_image_model($image_provider)
				: 'gpt-image-1-mini';
		}

		// CAMBIO: compatibilidad con campo legacy openai_model
		$model = ($text_provider === 'openai') ? $text_model : (string)($settings['openai_model'] ?? 'gpt-4.1-mini');

		// CAMBIO: provider settings (texto + imagen)
		if (function_exists('cbia_providers_get_settings') && function_exists('cbia_providers_get_all')) {
			$provider_settings = cbia_providers_get_settings();
			$providers_all = cbia_providers_get_all();
			$providers_list = isset($providers_all['providers']) && is_array($providers_all['providers']) ? $providers_all['providers'] : [];
			$current_provider = $text_provider ?: ($provider_settings['provider'] ?? 'openai');
			if (!isset($providers_list[$current_provider])) $current_provider = 'openai';

			$providers_new = is_array($provider_settings['providers'] ?? null) ? $provider_settings['providers'] : [];
			foreach ($providers_list as $pkey => $pdef) {
				$api = $first_non_empty(
					sanitize_text_field((string)($provider_api_key_text_post[$pkey] ?? '')),
					sanitize_text_field((string)($provider_api_key_image_post[$pkey] ?? '')),
					sanitize_text_field((string)($provider_api_key_post[$pkey] ?? '')),
					sanitize_text_field((string)($providers_new[$pkey]['api_key'] ?? ''))
				);
				$mdl = isset($text_models_post[$pkey]) ? sanitize_text_field((string)$text_models_post[$pkey]) : (string)($providers_new[$pkey]['model'] ?? ($pdef['models'][0] ?? ''));
				$img = isset($image_models_post[$pkey]) ? sanitize_text_field((string)$image_models_post[$pkey]) : (string)($providers_new[$pkey]['image_model'] ?? '');
				$base = isset($provider_base_url_post[$pkey]) ? sanitize_text_field((string)$provider_base_url_post[$pkey]) : (string)($providers_new[$pkey]['base_url'] ?? ($pdef['base_url'] ?? ''));
				$providers_new[$pkey] = [
					'api_key'     => $api,
					'model'       => $mdl,
					'image_model' => $img,
					'base_url'    => $base,
				];
			}
			if (isset($providers_new['openai'])) {
				$providers_new['openai']['api_key'] = $api_key;
			}
			if (isset($providers_new['google'])) {
				$providers_new['google']['api_key'] = $google_api_key;
			}
			if (isset($providers_new['deepseek'])) {
				$providers_new['deepseek']['api_key'] = $deepseek_api_key;
			}

			if (function_exists('cbia_providers_save_settings')) {
				cbia_providers_save_settings([
					'provider'  => $current_provider,
					'current_provider' => $current_provider,
					'providers' => $providers_new,
				]);
			}
		}

		$temp = isset($post['openai_temperature'])
			? (float) str_replace(',', '.', (string) wp_unslash($post['openai_temperature']))
			: (float)($settings['openai_temperature'] ?? 0.7);

		if ($temp < 0) $temp = 0;
		if ($temp > 2) $temp = 2;

		$post_length_variant = isset($post['post_length_variant'])
			? sanitize_key((string) wp_unslash($post['post_length_variant']))
			: (string)($settings['post_length_variant'] ?? 'medium');

		if (!in_array($post_length_variant, ['short','medium','long'], true)) $post_length_variant = 'medium';

		// Normal: solo imagen destacada
		$images_limit = 1;

		$prompt_single_all = isset($post['prompt_single_all'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($post['prompt_single_all'])))
			: (string)($settings['prompt_single_all'] ?? '');

		$prompt_img_intro = isset($post['prompt_img_intro'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($post['prompt_img_intro'])))
			: (string)($settings['prompt_img_intro'] ?? '');

		$prompt_img_body = isset($post['prompt_img_body'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($post['prompt_img_body'])))
			: (string)($settings['prompt_img_body'] ?? '');

		$prompt_img_conclusion = isset($post['prompt_img_conclusion'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($post['prompt_img_conclusion'])))
			: (string)($settings['prompt_img_conclusion'] ?? '');

		$prompt_img_faq = isset($post['prompt_img_faq'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($post['prompt_img_faq'])))
			: (string)($settings['prompt_img_faq'] ?? '');

		$prompt_img_global = isset($post['prompt_img_global'])
			? cbia_sanitize_textarea_preserve_lines(sanitize_textarea_field(wp_unslash($post['prompt_img_global'])))
			: (string)($settings['prompt_img_global'] ?? '');

		$responses_max_output_tokens = isset($post['responses_max_output_tokens'])
			? absint(wp_unslash($post['responses_max_output_tokens']))
			: (int)($settings['responses_max_output_tokens'] ?? 6000);
		if ($responses_max_output_tokens < 1500) $responses_max_output_tokens = 1500;
		if ($responses_max_output_tokens > 12000) $responses_max_output_tokens = 12000;

		// Preset rapido por modelo (si viene del boton de preset, manda sobre el resto)
		$preset_key = isset($post['cbia_preset_model']) ? sanitize_text_field(wp_unslash($post['cbia_preset_model'])) : '';
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

		$post_language = isset($post['post_language'])
			? sanitize_text_field(wp_unslash($post['post_language']))
			: (string)($settings['post_language'] ?? 'Spanish');
		if ($post_language === '') $post_language = 'Spanish';

		// Normal: sin imÃƒÂ¡genes internas
		$content_images_banner_enabled = 0;

		// Preset rÃƒÂ¡pido de CSS de banner (selector)
		$banner_preset_key = 'forced';

		// Formato de imagen por seccion (UI) - nota: el engine fuerza intro=panorÃƒÂ¡mica y resto=banner (como en v8.4)
		$image_format_intro = isset($post['image_format_intro'])
			? cbia_config_sanitize_image_format(wp_unslash($post['image_format_intro']), 'panoramic_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_intro'] ?? ''), 'panoramic_1536x1024');

		$image_format_body = isset($post['image_format_body'])
			? cbia_config_sanitize_image_format(wp_unslash($post['image_format_body']), 'banner_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_body'] ?? ''), 'banner_1536x1024');

		$image_format_conclusion = isset($post['image_format_conclusion'])
			? cbia_config_sanitize_image_format(wp_unslash($post['image_format_conclusion']), 'banner_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_conclusion'] ?? ''), 'banner_1536x1024');

		$image_format_faq = isset($post['image_format_faq'])
			? cbia_config_sanitize_image_format(wp_unslash($post['image_format_faq']), 'banner_1536x1024')
			: cbia_config_sanitize_image_format((string)($settings['image_format_faq'] ?? ''), 'banner_1536x1024');

		// Free edition: no internal image formats.

		$image_failover = isset($post['image_failover'])
			? sanitize_key((string) wp_unslash($post['image_failover']))
			: (string)($settings['image_failover'] ?? 'continue');
		if (!in_array($image_failover, ['continue', 'stop'], true)) $image_failover = 'continue';

		$default_category = isset($post['default_category'])
			? sanitize_text_field(wp_unslash($post['default_category']))
			: (string)($settings['default_category'] ?? 'News');

		if ($default_category === '') $default_category = 'News';

		$keywords_to_categories = isset($post['keywords_to_categories'])
			? cbia_sanitize_textarea_preserve_lines(wp_unslash($post['keywords_to_categories']))
			: (string)($settings['keywords_to_categories'] ?? '');

		$default_tags = isset($post['default_tags'])
			? cbia_sanitize_csv_tags(sanitize_text_field(wp_unslash($post['default_tags'])))
			: (string)($settings['default_tags'] ?? '');

		// Default author (cron/event): 0 = automatic (current user or admin)
		$default_author_id = isset($post['default_author_id']) ? absint(wp_unslash($post['default_author_id'])) : (int)($settings['default_author_id'] ?? 0);
		if ($default_author_id < 0) $default_author_id = 0;

		$partial = [
			// Provider-specific API keys
			'openai_api_key'         => $api_key,
			'google_api_key'         => $google_api_key,
			'deepseek_api_key'       => $deepseek_api_key,
			'openai_consent'         => $openai_consent,
			// Text/image provider and model
			'text_provider'          => $text_provider,
			'text_model'             => $text_model,
			'image_provider'         => $image_provider,
			'image_model'            => $image_model,
			// Legacy OpenAI compatibility
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
			'image_format_intro'     => $image_format_intro,
			'image_format_body'      => $image_format_body,
			'image_format_conclusion'=> $image_format_conclusion,
			'image_format_faq'       => $image_format_faq,
			'image_failover'         => $image_failover,
			'default_category'       => $default_category,
			'keywords_to_categories' => $keywords_to_categories,
			'default_tags'           => $default_tags,
			'default_author_id'      => $default_author_id,
		];

		// Missing API key warnings (does not block save)
		$warnings = [];
		$key_map = [
			'openai'  => $api_key,
			'google'  => $google_api_key,
			'deepseek'=> $deepseek_api_key,
		];
		if (empty($key_map[$text_provider] ?? '')) {
			/* translators: %s: provider name */
				$warnings[] = sprintf('Missing %s API key for text generation. Add it to use this provider.', ucfirst($text_provider));
		}
		if ($image_provider === 'google') {
			if (empty($google_api_key)) {
				$warnings[] = 'Missing Google API key for Imagen image generation. Add it to use this model.';
			}
		} else {
			if (empty($key_map[$image_provider] ?? '')) {
				/* translators: 1: provider name, 2: provider name */
				$warnings[] = sprintf('Missing %1$s API key for image generation. Add it to use %2$s image models.', ucfirst($image_provider), ucfirst($image_provider));
			}
		}
		if (!empty($warnings)) {
			set_transient('cbia_config_warnings', $warnings, 60);
		} else {
			delete_transient('cbia_config_warnings');
		}

		cbia_update_settings_merge($partial);
		cbia_log('Configuration saved successfully.', 'INFO');

		wp_safe_redirect(admin_url('admin.php?page=cbia&tab=config&saved=1'));
		exit;
	}
}

add_action('admin_init', 'cbia_config_handle_post');

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

        echo '<p>No se pudo cargar Configuracion.</p>';
    }
}

