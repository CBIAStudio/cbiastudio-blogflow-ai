<?php
/**
 * OpenAI calls (Responses + Images).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_attach_attempts_meta')) {
	function cbia_attach_attempts_meta($raw, array $attempts) {
		if (!is_array($raw)) $raw = array();
		$raw['_cbia_attempts'] = $attempts;
		return $raw;
	}
}

if (!function_exists('cbia_get_current_provider_key')) {
	function cbia_get_current_provider_key(): string {
		if (!empty($GLOBALS['cbia_force_text_provider'])) {
			$p = sanitize_key((string)$GLOBALS['cbia_force_text_provider']);
			if ($p !== '') return $p;
		}
		// CAMBIO: usa proveedor de texto (no imagen)
		if (function_exists('cbia_get_text_provider')) {
			$p = cbia_get_text_provider();
			return $p !== '' ? $p : 'openai';
		}
		if (function_exists('cbia_providers_get_settings') && function_exists('cbia_providers_get_current_provider')) {
			$provider_settings = cbia_providers_get_settings();
			$current_provider = cbia_providers_get_current_provider();
			return $current_provider ?: 'openai';
		}
		return 'openai';
	}
}

if (!function_exists('cbia_google_imagen_model_id')) {
	function cbia_google_imagen_model_id(string $model): string {
		// Mantener compatibilidad con alias legacy.
		if ($model === 'imagen-2') return 'imagen-3.0-generate-002';
		if ($model === 'imagen-3.0-generate-001') return 'imagen-3.0-generate-002';
		return $model;
	}
}

if (!function_exists('cbia_google_image_aspect_ratio_from_size')) {
	function cbia_google_image_aspect_ratio_from_size(string $size): string {
		$parts = explode('x', strtolower($size));
		if (count($parts) !== 2) return '';
		$w = (int)$parts[0];
		$h = (int)$parts[1];
		if ($w <= 0 || $h <= 0) return '';
		$ratio = $w / $h;
		if (abs($ratio - 1.0) < 0.1) return '1:1';
		if ($ratio >= 1.6) return '16:9';
		if ($ratio > 1.0) return '4:3';
		if ($ratio <= 0.65) return '9:16';
		return '3:4';
	}
}

if (!function_exists('cbia_google_generate_image_gemini')) {
	/**
	 * Google Gemini Image (gemini-3-pro-image-preview).
	 * Retorna [ok, attach_id, model, err]
	 */
	function cbia_google_generate_image_gemini($prompt, $section, $title, $alt_text, $idx, $model) {
		$cfg = cbia_get_provider_config('google');
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('google') : (string)($cfg['api_key'] ?? '');
		if ($api_key === '') {
			cbia_log(__('Missing Google API key for image generation (Gemini).', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, 0, (string)$model, __('No API key (Google)', 'cbiastudio-blogflow-ai')];
		}

		$base_url = rtrim((string)($cfg['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/');
		$api_version = trim((string)($cfg['api_version'] ?? 'v1beta'), '/');
		$url = $base_url . '/' . $api_version . '/models/' . rawurlencode((string)$model) . ':generateContent';

		$size = cbia_image_size_for_section($section, $idx);
		$aspect = cbia_google_image_aspect_ratio_from_size($size);

		$payload = [
			'contents' => [
				[
					'role' => 'user',
					'parts' => [
						['text' => (string)$prompt],
					],
				],
			],
			'generationConfig' => [
				'responseModalities' => ['TEXT', 'IMAGE'],
			],
		];
		if ($aspect !== '') {
			$payload['generationConfig']['imageConfig'] = ['aspectRatio' => $aspect];
		}

		$resp = wp_remote_post($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'x-goog-api-key' => $api_key,
			],
			'body' => wp_json_encode($payload),
			'timeout' => 60,
		]);
		if (is_wp_error($resp)) {
			cbia_log(('Google Gemini Image HTTP error: ') . $resp->get_error_message(), 'ERROR');
			return [false, 0, (string)$model, $resp->get_error_message()];
		}

		$code = (int) wp_remote_retrieve_response_code($resp);
		$body = (string) wp_remote_retrieve_body($resp);
		$data = json_decode($body, true);
		if ($code < 200 || $code >= 300) {
			$msg = is_array($data) && !empty($data['error']['message']) ? (string)$data['error']['message'] : 'HTTP error';
			cbia_log(sprintf(('Google Gemini Image error HTTP %s | %s'), $code, $msg), 'ERROR');
			return [false, 0, (string)$model, $msg];
		}

		$bytes = '';
		if (!empty($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
			foreach ($data['candidates'][0]['content']['parts'] as $p) {
				if (!is_array($p)) continue;
				if (!empty($p['inlineData']['data'])) {
					$bytes = base64_decode((string)$p['inlineData']['data']);
					break;
				}
				if (!empty($p['inline_data']['data'])) {
					$bytes = base64_decode((string)$p['inline_data']['data']);
					break;
				}
			}
		}

		if ($bytes === '') {
			cbia_log(('Google Gemini Image: response had no bytes.'), 'ERROR');
			return [false, 0, (string)$model, 'Image response was empty'];
		}

		$alt = $alt_text !== '' ? (string)$alt_text : cbia_build_img_alt($title, $section, $prompt);
		list($attach_id, $uerr) = cbia_upload_image_to_media($bytes, $title, $section, $alt);
		if (!$attach_id) {
			cbia_log(('Google Gemini Image: failed uploading to Media Library: ') . $uerr, 'ERROR');
			return [false, 0, (string)$model, $uerr];
		}

		return [true, (int)$attach_id, (string)$model, ''];
	}
}

if (!function_exists('cbia_google_generate_image_imagen')) {
	/**
	 * Google Imagen (Gemini API :predict con API key).
	 * Retorna [ok, attach_id, model, err]
	 */
	function cbia_google_generate_image_imagen($prompt, $section, $title, $alt_text, $idx, $model) {
		$cfg = cbia_get_provider_config('google');
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('google') : (string)($cfg['api_key'] ?? '');
		if ($api_key === '') {
			cbia_log(__('Missing Google API key for image generation (Imagen).', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, 0, (string)$model, __('No API key (Google)', 'cbiastudio-blogflow-ai')];
		}

		$base_url = rtrim((string)($cfg['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/');
		$api_version = trim((string)($cfg['api_version'] ?? 'v1beta'), '/');
		$imagen_model = cbia_google_imagen_model_id((string)$model);
		$url = $base_url . '/' . $api_version . '/models/' . rawurlencode($imagen_model) . ':predict?key=' . rawurlencode($api_key);

		$size = cbia_image_size_for_section($section, $idx);
		$aspect = cbia_google_image_aspect_ratio_from_size($size);

		$payload = [
			'instances' => [
				['prompt' => (string)$prompt],
			],
			'parameters' => [
				'sampleCount' => 1,
				'aspectRatio' => $aspect !== '' ? $aspect : '16:9',
				'outputMimeType' => 'image/jpeg',
				'safetySetting' => 'BLOCK_MEDIUM_AND_ABOVE',
			],
		];

		$resp = wp_remote_post($url, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode($payload),
			'timeout' => 60,
		]);
		if (is_wp_error($resp)) {
			cbia_log(('Google Imagen HTTP error: ') . $resp->get_error_message(), 'ERROR');
			return [false, 0, (string)$model, $resp->get_error_message()];
		}

		$code = (int) wp_remote_retrieve_response_code($resp);
		$body = (string) wp_remote_retrieve_body($resp);
		$data = json_decode($body, true);
		if ($code < 200 || $code >= 300) {
			$msg = is_array($data) && !empty($data['error']['message']) ? (string)$data['error']['message'] : 'HTTP error';
			cbia_log(sprintf(('Google Imagen error HTTP %s | %s'), $code, $msg), 'ERROR');
			return [false, 0, (string)$model, $msg];
		}

		$bytes = '';
		if (!empty($data['predictions'][0]['bytesBase64Encoded'])) {
			$bytes = base64_decode((string)$data['predictions'][0]['bytesBase64Encoded']);
		} elseif (!empty($data['predictions'][0]['image'])) {
			$bytes = base64_decode((string)$data['predictions'][0]['image']);
		} elseif (!empty($data['predictions'][0]['bytes_base64_encoded'])) {
			$bytes = base64_decode((string)$data['predictions'][0]['bytes_base64_encoded']);
		}

		if ($bytes === '') {
			cbia_log(('Google Imagen: response had no bytes.'), 'ERROR');
			return [false, 0, (string)$model, 'Image response was empty'];
		}

		$alt = $alt_text !== '' ? (string)$alt_text : cbia_build_img_alt($title, $section, $prompt);
		list($attach_id, $uerr) = cbia_upload_image_to_media($bytes, $title, $section, $alt);
		if (!$attach_id) {
			cbia_log(('Google Imagen: failed uploading to Media Library: ') . $uerr, 'ERROR');
			return [false, 0, (string)$model, $uerr];
		}

		return [true, (int)$attach_id, (string)$model, ''];
	}
}

if (!function_exists('cbia_google_generate_image_with_prompt')) {
	/**
	 * Google imagen (Gemini o Imagen). Retorna [ok, attach_id, model, err]
	 */
	function cbia_google_generate_image_with_prompt($prompt, $section, $title, $alt_text = '', $idx = 0) {
		$model = function_exists('cbia_get_image_model_for_provider')
			? cbia_get_image_model_for_provider('google', function_exists('cbia_providers_get_recommended_image_model') ? cbia_providers_get_recommended_image_model('google') : 'imagen-3.0-generate-002')
			: 'imagen-3.0-generate-002';
		$model = cbia_google_imagen_model_id((string)$model);

		// Fallback automÃƒÂ¡tico para cuota/disponibilidad.
		$chain = array_values(array_unique(array_filter(array(
			(string)$model,
			'gemini-2.5-flash-image',
		))));

		$last_err = '';
		foreach ($chain as $model_try) {
			if (stripos((string)$model_try, 'imagen-') === 0) {
				list($ok, $attach_id, $model_used, $err) = cbia_google_generate_image_imagen($prompt, $section, $title, $alt_text, $idx, $model_try);
			} else {
				list($ok, $attach_id, $model_used, $err) = cbia_google_generate_image_gemini($prompt, $section, $title, $alt_text, $idx, $model_try);
			}
			if ($ok) return [$ok, $attach_id, $model_used, $err];
			$last_err = (string)$err;
			cbia_log(sprintf("Google image fallback: failed model=%s, trying next if available.", (string)$model_try), 'WARN');
		}

		return [false, 0, '', $last_err !== '' ? $last_err : __('Could not generate image (Google).', 'cbiastudio-blogflow-ai')];
	}
}

if (!function_exists('cbia_google_generate_image')) {
	function cbia_google_generate_image($desc, $section, $title, $idx = 0) {
		$prompt = cbia_build_image_prompt($desc, $section, $title);
		return cbia_google_generate_image_with_prompt($prompt, $section, $title, '', $idx);
	}
}

/* =========================================================
   =============== OPENAI: RESPONSES CALL (6) ===============
   ========================================================= */

if (!function_exists('cbia_openai_responses_call')) {
	/**
	 * Devuelve 6 valores:
	 * [ok(bool), text(string), usage(array), model_used(string), err(string), raw(array|string)]
	 */
		function cbia_openai_responses_call($prompt, $title_for_log = '', $tries = 2, $max_output_override = 0) {
			cbia_try_unlimited_runtime();
			$attempts = array();
			// CAMBIO: proveedor de texto
			$provider = cbia_get_current_provider_key();
		if (!cbia_openai_consent_ok()) {
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('OpenAI consent not accepted', 'cbiastudio-blogflow-ai'), []];
		}

		$s = cbia_get_settings();
		// CAMBIO: modelo segun proveedor texto
		$model_preferred = function_exists('cbia_get_text_model_for_provider')
			? cbia_get_text_model_for_provider($provider, cbia_pick_model())
			: cbia_pick_model();
		$disable_model_fallback = !empty($GLOBALS['cbia_disable_text_model_fallback']);
		$chain = $disable_model_fallback
			? array_values(array_filter(array(trim((string)$model_preferred))))
			: cbia_model_fallback_chain($model_preferred);

		$system = "Eres un redactor editorial. Devuelve HTML simple con <h2>, <h3>, <p>, <ul>, <li>. NO uses <h1> ni envolturas <html>/<head>/<body>. No uses <table>, <iframe> ni <blockquote>.";
		$global_last_error = __('Could not get a streaming response.', 'cbiastudio-blogflow-ai');
		$last_err = __('Could not get a response.', 'cbiastudio-blogflow-ai');
		$input = [
			['role' => 'system', 'content' => $system],
			['role' => 'user', 'content' => (string)$prompt],
		];

		if ($provider === 'google') {
			return cbia_google_generate_content_call($prompt, $system, $tries);
		}
		if ($provider === 'deepseek') {
			$deepseek_result = cbia_deepseek_chat_call($prompt, $system, $tries);
			if (!empty($deepseek_result[0])) {
				return $deepseek_result;
			}

			$last_err = (string)($deepseek_result[4] ?? __('Could not get a response.', 'cbiastudio-blogflow-ai'));
			$prev_forced = isset($GLOBALS['cbia_force_text_provider']) ? (string)$GLOBALS['cbia_force_text_provider'] : '';

			$google_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('google') : '';
			if ($google_key !== '') {
				cbia_log("DeepSeek failed ({$last_err}). Text fallback -> Google Gemini.", 'WARN');
				$GLOBALS['cbia_force_text_provider'] = 'google';
				$google_result = cbia_openai_responses_call($prompt, $title_for_log, $tries, $max_output_override);
				if ($prev_forced !== '') $GLOBALS['cbia_force_text_provider'] = $prev_forced;
				else unset($GLOBALS['cbia_force_text_provider']);
				if (!empty($google_result[0])) return $google_result;
				$last_err = (string)($google_result[4] ?? $last_err);
			}

			$openai_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : '';
			if ($openai_key !== '') {
				cbia_log("Google fallback unavailable/failed. Text fallback -> OpenAI.", 'WARN');
				$GLOBALS['cbia_force_text_provider'] = 'openai';
				$openai_result = cbia_openai_responses_call($prompt, $title_for_log, $tries, $max_output_override);
				if ($prev_forced !== '') $GLOBALS['cbia_force_text_provider'] = $prev_forced;
				else unset($GLOBALS['cbia_force_text_provider']);
				if (!empty($openai_result[0])) return $openai_result;
				$last_err = (string)($openai_result[4] ?? $last_err);
			}

			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], 'deepseek-chat', $last_err, []];
		}
		// CAMBIO: key OpenAI desde settings por proveedor
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : cbia_openai_api_key();
		if (!$api_key) {
			cbia_log(__('Missing OpenAI API key for text generation.', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('No API key', 'cbiastudio-blogflow-ai'), []];
		}

		foreach ($chain as $model) {
			if (!cbia_is_responses_model($model)) continue;

			for ($t = 1; $t <= max(1, (int)$tries); $t++) {
				if (cbia_is_stop_requested()) {
					return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Stop enabled', 'cbiastudio-blogflow-ai'), []];
				}

				cbia_log(("OpenAI Responses: model={$model} attempt {$t}/{$tries} ") . ($title_for_log ? "| '{$title_for_log}'" : ''), 'INFO');

				$max_out = (int)($s['responses_max_output_tokens'] ?? 6000);
				$max_out_override = (int)$max_output_override;
				if ($max_out_override > 0) {
					$max_out = max($max_out, $max_out_override);
				}
				if ($max_out < 1500) $max_out = 1500;
				if ($max_out > 12000) $max_out = 12000;
				$temperature = isset($s['openai_temperature']) ? (float)$s['openai_temperature'] : 0.7;
				if ($temperature < 0) $temperature = 0;
				if ($temperature > 2) $temperature = 2;

				$payload = [
					'model' => $model,
					'input' => $input,
					// Max output prudente (luego el prompt manda)
					'max_output_tokens' => $max_out,
					'temperature' => $temperature,
				];

				$resp = wp_remote_post('https://api.openai.com/v1/responses', [
					'headers' => cbia_http_headers_openai($api_key),
					'body'    => wp_json_encode($payload),
					'timeout' => 60,
				]);

				if (is_wp_error($resp)) {
					$err = $resp->get_error_message();
					if (function_exists('cbia_mask_sensitive_log_text')) $err = cbia_mask_sensitive_log_text((string)$err);
					cbia_log(("HTTP error: {$err}"), 'ERROR');
					$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => (string)$err);
					$last_err = (string)$err;
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code($resp);
				$body = (string) wp_remote_retrieve_body($resp);
				$data = json_decode($body, true);

				if ($code < 200 || $code >= 300) {
					$msg = '';
					if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
					if (function_exists('cbia_mask_sensitive_log_text')) $msg = cbia_mask_sensitive_log_text((string)$msg);
					$err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
					cbia_log(("OpenAI error: {$err}"), 'ERROR');
					$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => (string)$err);
					$last_err = (string)$err;
					if (in_array($code, array(401, 403, 404), true)) {
						return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, $last_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				if (is_array($data) && !empty($data['error']['message'])) {
					$err = (string)$data['error']['message'];
					if (function_exists('cbia_mask_sensitive_log_text')) $err = cbia_mask_sensitive_log_text((string)$err);
					cbia_log(("OpenAI error payload: {$err}"), 'ERROR');
					$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => (string)$err);
					$last_err = (string)$err;
					if (stripos($err, 'incorrect api key') !== false || stripos($err, 'unauthorized') !== false || stripos($err, 'forbidden') !== false) {
						return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, $last_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				$text = cbia_extract_text_from_responses_payload($data);
				$usage = cbia_usage_from_responses_payload($data);

				if ($text === '') {
					cbia_log(("Response without text (model={$model})"), 'ERROR');
					$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => 'Response without text');
					$last_err = 'Response without text';
					continue;
				}

				cbia_log(("OpenAI Responses OK: model={$model} tokens_in=") . (int)($usage['input_tokens'] ?? 0) . " tokens_out=" . (int)($usage['output_tokens'] ?? 0), 'INFO');

				return [true, $text, $usage, $model, '', cbia_attach_attempts_meta($data, $attempts)];
			}
		}

		return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', $last_err, cbia_attach_attempts_meta(array(), $attempts)];
	}
}

if (!function_exists('cbia_openai_responses_stream_call')) {
	/**
	 * Streaming real con OpenAI Responses API.
	 * Devuelve: [ok, text, usage, model_used, err, raw]
	 */
	function cbia_openai_responses_stream_call($prompt, $title_for_log = '', $tries = 2, $on_delta = null) {
		cbia_try_unlimited_runtime();
		$provider = cbia_get_current_provider_key();
		if ($provider !== 'openai') {
			return cbia_openai_responses_call($prompt, $title_for_log, $tries);
		}
		if (!cbia_openai_consent_ok()) {
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('OpenAI consent not accepted', 'cbiastudio-blogflow-ai'), []];
		}

		$s = cbia_get_settings();
		$model_preferred = function_exists('cbia_get_text_model_for_provider')
			? cbia_get_text_model_for_provider($provider, cbia_pick_model())
			: cbia_pick_model();
		$disable_model_fallback = !empty($GLOBALS['cbia_disable_text_model_fallback']);
		$chain = $disable_model_fallback
			? array_values(array_filter(array(trim((string)$model_preferred))))
			: cbia_model_fallback_chain($model_preferred);
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : cbia_openai_api_key();
		if (!$api_key) {
			cbia_log(__('Missing OpenAI API key for text generation.', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('No API key', 'cbiastudio-blogflow-ai'), []];
		}

		$system = "Eres un redactor editorial. Devuelve HTML simple con <h2>, <h3>, <p>, <ul>, <li>. NO uses <h1> ni envolturas <html>/<head>/<body>. No uses <table>, <iframe> ni <blockquote>.";
		$input = [
			['role' => 'system', 'content' => $system],
			['role' => 'user', 'content' => (string)$prompt],
		];

		foreach ($chain as $model) {
			if (!cbia_is_responses_model($model)) continue;
			for ($t = 1; $t <= max(1, (int)$tries); $t++) {
				if (cbia_is_stop_requested()) {
					return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Stop enabled', 'cbiastudio-blogflow-ai'), []];
				}

				cbia_log(("OpenAI Responses STREAM: model={$model} attempt {$t}/{$tries} ") . ($title_for_log ? "| '{$title_for_log}'" : ''), 'INFO');

				$max_out = (int)($s['responses_max_output_tokens'] ?? 6000);
				if ($max_out < 1500) $max_out = 1500;
				if ($max_out > 12000) $max_out = 12000;
				$temperature = isset($s['openai_temperature']) ? (float)$s['openai_temperature'] : 0.7;
				if ($temperature < 0) $temperature = 0;
				if ($temperature > 2) $temperature = 2;

				$payload = [
					'model' => $model,
					'input' => $input,
					'max_output_tokens' => $max_out,
					'temperature' => $temperature,
					'stream' => true,
				];

				$acc_text = '';
				$last_usage = ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0];
				$last_error = '';
				$last_event = [];
				$line_buffer = '';

				$resp = wp_remote_post('https://api.openai.com/v1/responses', [
					'headers' => [
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					],
					'body'    => wp_json_encode($payload),
					'timeout' => 120,
				]);
				if (is_wp_error($resp)) {
					$last_error = $resp->get_error_message();
					if (function_exists('cbia_mask_sensitive_log_text')) $last_error = cbia_mask_sensitive_log_text((string)$last_error);
					$global_last_error = $last_error;
					cbia_log(sprintf('HTTP stream error: %s', $last_error), 'ERROR');
					continue;
				}
				$http_code = (int) wp_remote_retrieve_response_code($resp);
				$body = (string) wp_remote_retrieve_body($resp);
				$lines = preg_split("/\r\n|\n|\r/", $body);
				if (is_array($lines)) {
					foreach ($lines as $line) {
						$line = trim((string)$line);
						if ($line === '' || stripos($line, 'data:') !== 0) continue;
						$json = trim(substr($line, 5));
						if ($json === '[DONE]') continue;
						$evt = json_decode($json, true);
						if (!is_array($evt)) continue;
						$last_event = $evt;
						if (!empty($evt['error']['message'])) {
							$last_error = (string)$evt['error']['message'];
							if (function_exists('cbia_mask_sensitive_log_text')) $last_error = cbia_mask_sensitive_log_text((string)$last_error);
							continue;
						}
						$delta = '';
						if (isset($evt['delta']) && is_string($evt['delta'])) $delta = $evt['delta'];
						elseif (isset($evt['text']) && is_string($evt['text'])) $delta = $evt['text'];
						elseif (isset($evt['output_text']) && is_string($evt['output_text'])) $delta = $evt['output_text'];
						if ($delta !== '') {
							$acc_text .= $delta;
							if (is_callable($on_delta)) call_user_func($on_delta, $delta, $acc_text, $last_usage);
						}
						if (!empty($evt['response']) && is_array($evt['response'])) {
							$maybe_text = cbia_extract_text_from_responses_payload($evt['response']);
							if ($maybe_text !== '') $acc_text = $maybe_text;
							$last_usage = cbia_usage_from_responses_payload($evt['response']);
						} elseif (!empty($evt['usage']) && is_array($evt['usage'])) {
							$in = (int)($evt['usage']['input_tokens'] ?? 0);
							$out = (int)($evt['usage']['output_tokens'] ?? 0);
							$total = (int)($evt['usage']['total_tokens'] ?? ($in + $out));
							$last_usage = ['input_tokens' => $in, 'output_tokens' => $out, 'total_tokens' => $total];
						}
					}
				}
				if ($http_code < 200 || $http_code >= 300) {
					$last_error = 'HTTP ' . $http_code . ($last_error ? (' | ' . $last_error) : '');
					if (function_exists('cbia_mask_sensitive_log_text')) $last_error = cbia_mask_sensitive_log_text((string)$last_error);
					$global_last_error = $last_error;
					cbia_log(("OpenAI stream error: {$last_error}"), 'ERROR');
					if (in_array($http_code, array(401, 403, 404), true)) {
						return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, $last_error, $last_event];
					}
					continue;
				}
				if ($last_error !== '') {
					if (function_exists('cbia_mask_sensitive_log_text')) $last_error = cbia_mask_sensitive_log_text((string)$last_error);
					$global_last_error = $last_error;
					cbia_log(("OpenAI stream payload error: {$last_error}"), 'ERROR');
					if (stripos($last_error, 'incorrect api key') !== false || stripos($last_error, 'unauthorized') !== false || stripos($last_error, 'forbidden') !== false) {
						return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, $last_error, $last_event];
					}
					continue;
				}
				if ($acc_text === '') {
					cbia_log(("Streaming response without text (model={$model})"), 'ERROR');
					$global_last_error = 'Streaming response without text';
					continue;
				}

				cbia_log(("OpenAI Responses STREAM OK: model={$model} tokens_in=") . (int)($last_usage['input_tokens'] ?? 0) . " tokens_out=" . (int)($last_usage['output_tokens'] ?? 0), 'INFO');
				return [true, $acc_text, $last_usage, $model, '', $last_event];
			}
		}
		return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', $global_last_error, []];
	}
}
/* =========================================================
   ================== OPENAI: IMÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂGENES ======================
   ========================================================= */

if (!function_exists('cbia_generate_image_openai')) {
	/**
	 * Retorna [ok(bool), attach_id(int), model_used(string), err(string)]
	 */
	function cbia_generate_image_openai($desc, $section, $title, $idx = 0) {
			cbia_try_unlimited_runtime();
			$attempts = array();
			// CAMBIO: proveedor de imagen segun settings
			$img_provider = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : 'openai';
			if ($img_provider === 'google' || $img_provider === 'gemini') {
				return cbia_google_generate_image($desc, $section, $title, $idx);
			}
			if ($img_provider !== 'openai') {
				/* translators: %s is the selected image provider key. */
				cbia_log(sprintf(__('Image provider "%s" is not supported.', 'cbiastudio-blogflow-ai'), (string)$img_provider), 'ERROR');
				return [false, 0, '', __('Unsupported image provider', 'cbiastudio-blogflow-ai')];
			}
			// CAMBIO: key OpenAI desde settings por proveedor
			$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : cbia_openai_api_key();
			if (!$api_key) return [false, 0, '', __('No API key', 'cbiastudio-blogflow-ai')];
		if (!cbia_openai_consent_ok()) return [false, 0, '', __('OpenAI consent not accepted', 'cbiastudio-blogflow-ai')];

		if (cbia_is_stop_requested()) return [false, 0, '', __('Stop enabled', 'cbiastudio-blogflow-ai')];

		$s = cbia_get_settings();
		$image_failover = isset($s['image_failover']) ? (string)$s['image_failover'] : 'continue';
		if (!in_array($image_failover, ['continue', 'stop'], true)) $image_failover = 'continue';

		$prompt = cbia_build_image_prompt($desc, $section, $title);
		$size = cbia_image_size_for_section($section, $idx);
		$alt  = cbia_build_img_alt($title, $section, $desc);
		$section_label = function_exists('cbia_section_label') ? cbia_section_label($section) : (string)$section;

		// CAMBIO: modelo preferido segun settings
		$preferred_model = function_exists('cbia_get_image_model_for_provider')
			? cbia_get_image_model_for_provider('openai', function_exists('cbia_providers_get_recommended_image_model') ? cbia_providers_get_recommended_image_model('openai') : 'gpt-image-2')
			: 'gpt-image-2';
		foreach (cbia_image_model_chain('openai', $preferred_model) as $model) {
			$tries = 2;
			for ($t = 1; $t <= $tries; $t++) {
				if (cbia_is_stop_requested()) return [false, 0, $model, __('Stop enabled', 'cbiastudio-blogflow-ai')];

				$delay = function_exists('cbia_get_image_request_delay') ? cbia_get_image_request_delay() : 0;
				if ($delay > 0) sleep($delay);

				/* translators: 1: image model, 2: section label, 3: current attempt, 4: total attempts. */
				cbia_log((sprintf(__('AI image: model=%1$s section=%2$s attempt %3$d/%4$d', 'cbiastudio-blogflow-ai'), (string)$model, (string)$section_label, (int)$t, (int)$tries)), 'INFO');

				$payload = [
					'model'  => $model,
					'prompt' => $prompt,
					'n'      => 1,
					'size'   => $size,
				];

				$resp = wp_remote_post('https://api.openai.com/v1/images/generations', [
					'headers' => cbia_http_headers_openai($api_key),
					'body'    => wp_json_encode($payload),
					'timeout' => cbia_image_api_timeout_seconds(),
				]);

				if (is_wp_error($resp)) {
					$http_err = (string)$resp->get_error_message();
					if (strpos($http_err, 'cURL error 28') !== false) {
						$http_err .= sprintf(' (timeout=%ss, download_timeout=%ss)', (string)cbia_image_api_timeout_seconds(), (string)cbia_image_download_timeout_seconds());
					}
					cbia_log((__('AI image HTTP error: ', 'cbiastudio-blogflow-ai')) . $http_err, 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $http_err);
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code($resp);
				$body = (string) wp_remote_retrieve_body($resp);
				$data = json_decode($body, true);

				if ($code < 200 || $code >= 300) {
					$msg = '';
					if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
					$http_err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
					/* translators: %d is the HTTP status code returned by the image API. */
					cbia_log((sprintf(__('AI image HTTP %d error', 'cbiastudio-blogflow-ai'), (int)$code)) . ($msg ? " | {$msg}" : ''), 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $http_err);
					if (in_array($code, array(401, 403, 404), true)) {
						return [false, 0, $model, $http_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				if (is_array($data) && !empty($data['error']['message'])) {
					$payload_err = (string)$data['error']['message'];
					cbia_log((__('AI image payload error: ', 'cbiastudio-blogflow-ai')) . $payload_err, 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $payload_err);
					if (stripos($payload_err, 'incorrect api key') !== false || stripos($payload_err, 'unauthorized') !== false || stripos($payload_err, 'forbidden') !== false) {
						return [false, 0, $model, $payload_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				$bytes = '';
				if (!empty($data['data'][0]['b64_json'])) {
					$bytes = base64_decode((string)$data['data'][0]['b64_json']);
				} elseif (!empty($data['data'][0]['url'])) {
					$img = wp_remote_get((string)$data['data'][0]['url'], ['timeout' => cbia_image_download_timeout_seconds()]);
					if (!is_wp_error($img) && (int)wp_remote_retrieve_response_code($img) === 200) {
						$bytes = (string)wp_remote_retrieve_body($img);
					}
				}

				if ($bytes === '') {
					/* translators: %s is the image model name. */
					cbia_log((sprintf(__('AI image: response without bytes (model=%s)', 'cbiastudio-blogflow-ai'), (string)$model)), 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => __('Response without bytes', 'cbiastudio-blogflow-ai'));
					continue;
				}

				list($attach_id, $uerr) = cbia_upload_image_to_media($bytes, $title, $section, $alt);
				if (!$attach_id) {
					/* translators: %s is the upload error message from WordPress media handling. */
					cbia_log((sprintf(__('AI image: upload to Media Library failed: %s', 'cbiastudio-blogflow-ai'), (string)$uerr)), 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => (string)$uerr, 'billable' => 1);
					continue;
				}

				/* translators: 1: section label, 2: attachment ID. */
				cbia_log((sprintf(__('AI image OK: section=%1$s attach_id=%2$d', 'cbiastudio-blogflow-ai'), (string)$section_label, (int)$attach_id)), 'INFO');
				return [true, (int)$attach_id, $model, '', cbia_attach_attempts_meta(array(), $attempts)];
			}
			if ($image_failover === 'stop') {
				/* translators: %s is the image model name that failed. */
				cbia_log(sprintf(__('AI image: model=%s failed; process stopped by configuration.', 'cbiastudio-blogflow-ai'), (string)$model), 'ERROR');
				return [false, 0, (string)$model, __('Stopped by failover configuration', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts)];
			}
		}

		return [false, 0, '', __('Image generation failed after retries', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts)];
	}
}

if (!function_exists('cbia_generate_image_openai_with_prompt')) {
	/**
	 * Retorna [ok(bool), attach_id(int), model_used(string), err(string)]
	 */
	function cbia_generate_image_openai_with_prompt($prompt, $section, $title, $alt_text = '', $idx = 0) {
		cbia_try_unlimited_runtime();
		$attempts = array();
		// CAMBIO: proveedor de imagen segun settings
		$img_provider = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : 'openai';
		if ($img_provider === 'google' || $img_provider === 'gemini') {
			return cbia_google_generate_image_with_prompt($prompt, $section, $title, $alt_text, $idx);
		}
		if ($img_provider !== 'openai') {
			/* translators: %s is the selected image provider key. */
			cbia_log(sprintf(__('Image provider "%s" not supported for this flow; using OpenAI fallback.', 'cbiastudio-blogflow-ai'), (string)$img_provider), 'WARN');
			$img_provider = 'openai';
		}
		// CAMBIO: key OpenAI desde settings por proveedor
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : cbia_openai_api_key();
		if (!$api_key) return [false, 0, '', __('No API key', 'cbiastudio-blogflow-ai')];
		if (!cbia_openai_consent_ok()) return [false, 0, '', __('OpenAI consent not accepted', 'cbiastudio-blogflow-ai')];
		if (cbia_is_stop_requested()) return [false, 0, '', __('Stop enabled', 'cbiastudio-blogflow-ai')];

		$s = cbia_get_settings();
		$image_failover = isset($s['image_failover']) ? (string)$s['image_failover'] : 'continue';
		if (!in_array($image_failover, ['continue', 'stop'], true)) $image_failover = 'continue';

		$size = cbia_image_size_for_section($section, $idx);
		$alt  = $alt_text !== '' ? (string)$alt_text : cbia_build_img_alt($title, $section, $prompt);
		$section_label = function_exists('cbia_section_label') ? cbia_section_label($section) : (string)$section;

		// CAMBIO: modelo preferido segun settings
		$preferred_model = function_exists('cbia_get_image_model_for_provider')
			? cbia_get_image_model_for_provider('openai', function_exists('cbia_providers_get_recommended_image_model') ? cbia_providers_get_recommended_image_model('openai') : 'gpt-image-2')
			: 'gpt-image-2';
		foreach (cbia_image_model_chain('openai', $preferred_model) as $model) {
			$tries = 2;
			for ($t = 1; $t <= $tries; $t++) {
				if (cbia_is_stop_requested()) return [false, 0, $model, __('Stop enabled', 'cbiastudio-blogflow-ai')];

				$delay = function_exists('cbia_get_image_request_delay') ? cbia_get_image_request_delay() : 0;
				if ($delay > 0) sleep($delay);

				/* translators: 1: image model, 2: section label, 3: current attempt, 4: total attempts. */
				cbia_log((sprintf(__('AI image: model=%1$s section=%2$s attempt %3$d/%4$d', 'cbiastudio-blogflow-ai'), (string)$model, (string)$section_label, (int)$t, (int)$tries)), 'INFO');

				$payload = [
					'model'  => $model,
					'prompt' => (string)$prompt,
					'n'      => 1,
					'size'   => $size,
				];

				$resp = wp_remote_post('https://api.openai.com/v1/images/generations', [
					'headers' => cbia_http_headers_openai($api_key),
					'body'    => wp_json_encode($payload),
					'timeout' => cbia_image_api_timeout_seconds(),
				]);

				if (is_wp_error($resp)) {
					$http_err = (string)$resp->get_error_message();
					if (strpos($http_err, 'cURL error 28') !== false) {
						$http_err .= sprintf(' (timeout=%ss, download_timeout=%ss)', (string)cbia_image_api_timeout_seconds(), (string)cbia_image_download_timeout_seconds());
					}
					cbia_log((__('AI image HTTP error: ', 'cbiastudio-blogflow-ai')) . $http_err, 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $http_err);
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code($resp);
				$body = (string) wp_remote_retrieve_body($resp);
				$data = json_decode($body, true);

				if ($code < 200 || $code >= 300) {
					$msg = '';
					if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
					$http_err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
					/* translators: %d is the HTTP status code returned by the image API. */
					cbia_log((sprintf(__('AI image HTTP %d error', 'cbiastudio-blogflow-ai'), (int)$code)) . ($msg ? " | {$msg}" : ''), 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $http_err);
					if (in_array($code, array(401, 403, 404), true)) {
						return [false, 0, $model, $http_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				if (is_array($data) && !empty($data['error']['message'])) {
					$payload_err = (string)$data['error']['message'];
					cbia_log((__('AI image payload error: ', 'cbiastudio-blogflow-ai')) . $payload_err, 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $payload_err);
					if (stripos($payload_err, 'incorrect api key') !== false || stripos($payload_err, 'unauthorized') !== false || stripos($payload_err, 'forbidden') !== false) {
						return [false, 0, $model, $payload_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				$bytes = '';
				if (!empty($data['data'][0]['b64_json'])) {
					$bytes = base64_decode((string)$data['data'][0]['b64_json']);
				} elseif (!empty($data['data'][0]['url'])) {
					$img = wp_remote_get((string)$data['data'][0]['url'], ['timeout' => cbia_image_download_timeout_seconds()]);
					if (!is_wp_error($img) && (int)wp_remote_retrieve_response_code($img) === 200) {
						$bytes = (string)wp_remote_retrieve_body($img);
					}
				}

				if ($bytes === '') {
					/* translators: %s is the image model name. */
					cbia_log((sprintf(__('AI image: response without bytes (model=%s)', 'cbiastudio-blogflow-ai'), (string)$model)), 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => __('Response without bytes', 'cbiastudio-blogflow-ai'));
					continue;
				}

				list($attach_id, $uerr) = cbia_upload_image_to_media($bytes, $title, $section, $alt);
				if (!$attach_id) {
					/* translators: %s is the upload error message from WordPress media handling. */
					cbia_log((sprintf(__('AI image: upload to Media Library failed: %s', 'cbiastudio-blogflow-ai'), (string)$uerr)), 'ERROR');
					$attempts[] = array('type' => 'image', 'section' => (string)$section, 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => (string)$uerr, 'billable' => 1);
					continue;
				}

				/* translators: 1: section label, 2: attachment ID. */
				cbia_log((sprintf(__('AI image OK: section=%1$s attach_id=%2$d', 'cbiastudio-blogflow-ai'), (string)$section_label, (int)$attach_id)), 'INFO');
				return [true, (int)$attach_id, $model, '', cbia_attach_attempts_meta(array(), $attempts)];
			}
			if ($image_failover === 'stop') {
				/* translators: %s is the image model name that failed. */
				cbia_log(sprintf(__('AI image: model=%s failed; process stopped by configuration.', 'cbiastudio-blogflow-ai'), (string)$model), 'ERROR');
				return [false, 0, (string)$model, __('Stopped by failover configuration', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts)];
			}
		}

		return [false, 0, '', __('Image generation failed after retries', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts)];
	}
}

if (!function_exists('cbia_get_provider_config')) {
	function cbia_get_provider_config(string $provider): array {
		if (function_exists('cbia_providers_get_provider')) {
			return cbia_providers_get_provider($provider);
		}
		return [];
	}
}

if (!function_exists('cbia_get_provider_model')) {
	function cbia_get_provider_model(string $provider, string $fallback = ''): string {
		$cfg = cbia_get_provider_config($provider);
		$model = isset($cfg['model']) ? (string)$cfg['model'] : '';
		return $model !== '' ? $model : $fallback;
	}
}

if (!function_exists('cbia_image_api_timeout_seconds')) {
	function cbia_image_api_timeout_seconds(): int {
		$s = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
		$timeout = isset($s['image_timeout_seconds']) ? (int)$s['image_timeout_seconds'] : 180;
		if ($timeout < 30) $timeout = 30;
		if ($timeout > 240) $timeout = 240;
		return $timeout;
	}
}

if (!function_exists('cbia_image_download_timeout_seconds')) {
	function cbia_image_download_timeout_seconds(): int {
		$s = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
		$timeout = isset($s['image_download_timeout_seconds']) ? (int)$s['image_download_timeout_seconds'] : 180;
		if ($timeout < 20) $timeout = 20;
		if ($timeout > 240) $timeout = 240;
		return $timeout;
	}
}

if (!function_exists('cbia_google_generate_content_call')) {
	/**
	 * Google Gemini generateContent (REST).
	 * Returns [ok, text, usage, model, err, raw]
	 */
	function cbia_google_generate_content_call($prompt, $system = '', $tries = 2) {
		$attempts = array();
		$cfg = cbia_get_provider_config('google');
		// CAMBIO: key y modelo segun settings de texto
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('google') : (string)($cfg['api_key'] ?? '');
		if ($api_key === '') {
			cbia_log(__('Missing Google API key for text generation.', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('No API key (Google)', 'cbiastudio-blogflow-ai'), []];
		}

		$model = function_exists('cbia_get_text_model_for_provider')
			? cbia_get_text_model_for_provider('google', 'gemini-2.5-flash')
			: cbia_get_provider_model('google', 'gemini-2.5-flash');
		$base_url = rtrim((string)($cfg['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/');
		$api_version = trim((string)($cfg['api_version'] ?? 'v1beta'), '/');

		$max_out = (int)(cbia_get_settings()['responses_max_output_tokens'] ?? 6000);
		if ($max_out < 256) $max_out = 256;
		if ($max_out > 12000) $max_out = 12000;

		$url = $base_url . '/' . $api_version . '/models/' . rawurlencode($model) . ':generateContent';

		$payload = [
			'contents' => [
				[
					'role' => 'user',
					'parts' => [
						['text' => (string)$prompt],
					],
				],
			],
			'generationConfig' => [
				'maxOutputTokens' => $max_out,
			],
		];
		if ($system !== '') {
			$payload['system_instruction'] = [
				'parts' => [
					['text' => (string)$system],
				],
			];
		}

		for ($t = 1; $t <= max(1, (int)$tries); $t++) {
			if (cbia_is_stop_requested()) {
				return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Stop enabled', 'cbiastudio-blogflow-ai'), []];
			}

			/* translators: 1: text model name, 2: current attempt, 3: total attempts. */
			cbia_log((sprintf(__('Google Gemini: model=%1$s attempt %2$d/%3$d', 'cbiastudio-blogflow-ai'), (string)$model, (int)$t, (int)$tries)), 'INFO');

			$resp = wp_remote_post($url, [
				'headers' => [
					'Content-Type' => 'application/json',
					'x-goog-api-key' => $api_key,
				],
				'body'    => wp_json_encode($payload),
				'timeout' => 60,
			]);

			if (is_wp_error($resp)) {
				$err = (string)$resp->get_error_message();
				cbia_log(("Google Gemini HTTP error: ") . $err, 'ERROR');
				$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $err);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code($resp);
			$body = (string) wp_remote_retrieve_body($resp);
			$data = json_decode($body, true);

			if ($code < 200 || $code >= 300) {
				$msg = '';
				if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
				$err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
				cbia_log(("Google Gemini error: {$err}"), 'ERROR');
				$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => (string)$err);
				continue;
			}

			if (!is_array($data)) {
				cbia_log(("Google Gemini: invalid response"), 'ERROR');
				$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => 'Invalid response');
				continue;
			}

			$text = '';
			if (!empty($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
				foreach ($data['candidates'][0]['content']['parts'] as $p) {
					if (is_array($p) && isset($p['text'])) {
						$text .= (string)$p['text'];
					}
				}
			}

			if ($text === '') {
				cbia_log(("Google Gemini: response without text (model={$model})"), 'ERROR');
				$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => 'Response without text');
				continue;
			}

			$usage = ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0];
			if (!empty($data['usageMetadata'])) {
				$usage['input_tokens'] = (int)($data['usageMetadata']['promptTokenCount'] ?? 0);
				$usage['output_tokens'] = (int)($data['usageMetadata']['candidatesTokenCount'] ?? 0);
				$usage['total_tokens'] = (int)($data['usageMetadata']['totalTokenCount'] ?? 0);
			}

			cbia_log(("Google Gemini OK: model={$model} tokens_in=") . (int)$usage['input_tokens'] . " tokens_out=" . (int)$usage['output_tokens'], 'INFO');
			return [true, $text, $usage, $model, '', cbia_attach_attempts_meta($data, $attempts)];
		}

		return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Could not get a response.', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts)];
	}
}

if (!function_exists('cbia_deepseek_chat_call')) {
	/**
	 * DeepSeek chat completions (OpenAI-compatible).
	 * Returns [ok, text, usage, model, err, raw]
	 */
	function cbia_deepseek_chat_call($prompt, $system = '', $tries = 2) {
		$attempts = array();
		$cfg = cbia_get_provider_config('deepseek');
		// CAMBIO: key y modelo segun settings de texto
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('deepseek') : (string)($cfg['api_key'] ?? '');
		if ($api_key === '') {
			cbia_log(__('Missing DeepSeek API key for text generation.', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('No API key (DeepSeek)', 'cbiastudio-blogflow-ai'), []];
		}

		$model = function_exists('cbia_get_text_model_for_provider')
			? cbia_get_text_model_for_provider('deepseek', 'deepseek-chat')
			: cbia_get_provider_model('deepseek', 'deepseek-chat');
		$base_url = rtrim((string)($cfg['base_url'] ?? 'https://api.deepseek.com'), '/');
		$api_version = trim((string)($cfg['api_version'] ?? 'v1'), '/');
		$path = $api_version !== '' ? '/' . $api_version . '/chat/completions' : '/chat/completions';
		$url = $base_url . $path;

		$max_out = (int)(cbia_get_settings()['responses_max_output_tokens'] ?? 6000);
		if ($max_out < 256) $max_out = 256;
		if ($max_out > 12000) $max_out = 12000;

		$messages = [];
		if ($system !== '') {
			$messages[] = ['role' => 'system', 'content' => (string)$system];
		}
		$messages[] = ['role' => 'user', 'content' => (string)$prompt];

		$payload = [
			'model' => $model,
			'messages' => $messages,
			'stream' => false,
			'max_tokens' => $max_out,
			'temperature' => (float)(cbia_get_settings()['openai_temperature'] ?? 0.7),
		];

		for ($t = 1; $t <= max(1, (int)$tries); $t++) {
			if (cbia_is_stop_requested()) {
				return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Stop enabled', 'cbiastudio-blogflow-ai'), []];
			}

			cbia_log(("DeepSeek: model={$model} attempt {$t}/{$tries}"), 'INFO');

			$resp = wp_remote_post($url, [
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => wp_json_encode($payload),
				'timeout' => 60,
			]);

			if (is_wp_error($resp)) {
				$err = (string)$resp->get_error_message();
				cbia_log(("DeepSeek HTTP error: ") . $err, 'ERROR');
				$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => $err);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code($resp);
			$body = (string) wp_remote_retrieve_body($resp);
			$data = json_decode($body, true);

			if ($code < 200 || $code >= 300) {
				$msg = '';
				if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
				$err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
				cbia_log(("DeepSeek error: {$err}"), 'ERROR');
				$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => (string)$err);
				continue;
			}

			if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
				cbia_log(("DeepSeek: response without text (model={$model})"), 'ERROR');
				$attempts[] = array('type' => 'text', 'model' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'error' => 'Response without text');
				continue;
			}

			$text = (string)$data['choices'][0]['message']['content'];
			$usage = ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0];
			if (!empty($data['usage'])) {
				$usage['input_tokens'] = (int)($data['usage']['prompt_tokens'] ?? 0);
				$usage['output_tokens'] = (int)($data['usage']['completion_tokens'] ?? 0);
				$usage['total_tokens'] = (int)($data['usage']['total_tokens'] ?? 0);
			}

			cbia_log(("DeepSeek OK: model={$model} tokens_in=") . (int)$usage['input_tokens'] . " tokens_out=" . (int)$usage['output_tokens'], 'INFO');
			return [true, $text, $usage, $model, '', cbia_attach_attempts_meta($data, $attempts)];
		}

		return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Could not get a response.', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts)];
	}
}
