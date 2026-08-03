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

if (!function_exists('cbia_local_blocked_attempt_meta')) {
	function cbia_local_blocked_attempt_meta($type, $provider, $model, $error_type, $error, $section = ''): array {
		$attempt = array(
			'type' => sanitize_key((string)$type),
			'provider' => sanitize_key((string)$provider),
			'model' => sanitize_text_field((string)$model),
			'model_requested' => sanitize_text_field((string)$model),
			'model_effective' => sanitize_text_field((string)$model),
			'attempt' => 1,
			'ok' => 0,
			'status' => 'blocked_local',
			'result_status' => 'blocked_local',
			'error_type' => sanitize_key((string)$error_type),
			'error' => sanitize_text_field((string)$error),
			'request_sent' => 0,
			'billable' => 0,
			'cost_micro_usd' => 0,
			'cost_status' => 'exact',
			'cost_source' => 'local_preflight',
		);
		if ($section !== '') $attempt['section'] = sanitize_key((string)$section);
		return cbia_attach_attempts_meta($attempt, array($attempt));
	}
}

if (!function_exists('cbia_merge_provider_attempts')) {
	function cbia_merge_provider_attempts($result, $previous_raw, $fallback_from) {
		if (!is_array($result)) return $result;
		$raw = is_array($result[5] ?? null) ? $result[5] : array();
		$previous_attempts = is_array($previous_raw['_cbia_attempts'] ?? null) ? $previous_raw['_cbia_attempts'] : array();
		$current_attempts = is_array($raw['_cbia_attempts'] ?? null) ? $raw['_cbia_attempts'] : array();
		$raw['_cbia_attempts'] = array_merge($previous_attempts, $current_attempts);
		if (is_array($raw['_cbia_request_meta'] ?? null)) $raw['_cbia_request_meta']['fallback_from'] = sanitize_text_field((string)$fallback_from);
		$result[5] = $raw;
		return $result;
	}
}

if (!function_exists('cbia_openai_model_supports_temperature')) {
	function cbia_openai_model_supports_temperature($model) {
		$model = strtolower(trim((string)$model));
		return !in_array($model, array('gpt-5', 'gpt-5-mini', 'gpt-5-nano'), true);
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
			$msg = cbia_sanitize_provider_error('google', is_array($data) && !empty($data['error']['message']) ? (string)$data['error']['message'] : 'HTTP error', $code);
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
			$msg = cbia_sanitize_provider_error('google', is_array($data) && !empty($data['error']['message']) ? (string)$data['error']['message'] : 'HTTP error', $code);
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
	function cbia_openai_text_timeout($context = array()) {
		$phase = sanitize_key((string)($context['phase'] ?? $context['context'] ?? 'initial'));
		$default = strpos($phase, 'expand') !== false ? 150 : 120;
		return max(30, (int)apply_filters('cbia_openai_text_timeout', $default, $phase, $context));
	}

	function cbia_openai_text_attempt_context($context, $title, $sequence, $model, $preferred) {
		$context = is_array($context) ? $context : array();
		if (empty($context['temporary_context_id'])) {
			$context['temporary_context_id'] = 'tmp-' . substr(hash('sha256', (string)$title . '|' . microtime(true) . '|' . wp_generate_uuid4()), 0, 24);
		}
		$context['attempt_id'] = sanitize_text_field((string)$context['temporary_context_id']) . '-a' . max(1, (int)$sequence);
		$context['attempt'] = max(1, (int)$sequence);
		if (empty($context['batch_id']) && !empty($GLOBALS['cbia_usage_batch_id'])) $context['batch_id'] = sanitize_text_field((string)$GLOBALS['cbia_usage_batch_id']);
		if ($sequence > 1) {
			$context['parent_attempt'] = 1;
			$context['fallback_from'] = (string)$preferred;
		}
		$context['model_requested'] = (string)$preferred;
		$context['model_effective'] = (string)$model;
		$context['model'] = (string)$model;
		$context['provider'] = 'openai';
		$context['type'] = 'text';
		$context['title'] = (string)$title;
		return $context;
	}

	function cbia_openai_record_text_attempt($context, $usage, $result) {
		if (!empty($context['defer_usage_recording'])) return true;
		$row = array_merge(is_array($usage) ? $usage : array(), is_array($context) ? $context : array(), is_array($result) ? $result : array());
		$saved = function_exists('cbia_costes_record_usage') ? cbia_costes_record_usage((int)($context['post_id'] ?? 0), $row) : false;
		cbia_log('Usage V2 OpenAI attempt=' . sanitize_text_field((string)($row['attempt_id'] ?? 'unknown')) . ' saved=' . ($saved ? 'yes' : 'no') . ' status=' . sanitize_key((string)($row['status'] ?? 'unknown')), $saved ? 'INFO' : 'WARN');
		return (bool)$saved;
	}

	function cbia_normalize_openai_completion_status($provider_status, $incomplete_reason = '') {
		$provider_status = sanitize_key((string)$provider_status);
		$incomplete_reason = sanitize_key((string)$incomplete_reason);
		if ($provider_status === 'completed' || $provider_status === '') return 'complete';
		if (in_array($incomplete_reason, array('max_output_tokens', 'max_tokens', 'length'), true)) return 'output_limit';
		if (in_array($incomplete_reason, array('content_filter', 'safety'), true)) return 'content_filter';
		if ($provider_status === 'incomplete') return 'incomplete';
		if (in_array($provider_status, array('failed', 'cancelled'), true)) return 'provider_error';
		return 'unknown';
	}

	function cbia_normalize_chat_completion_status($finish_reason) {
		$finish_reason = sanitize_key((string)$finish_reason);
		if (in_array($finish_reason, array('stop', 'completed'), true)) return 'complete';
		if (in_array($finish_reason, array('length', 'max_tokens', 'max_output_tokens'), true)) return 'output_limit';
		if (in_array($finish_reason, array('content_filter', 'safety', 'recitation'), true)) return 'content_filter';
		if (in_array($finish_reason, array('error', 'insufficient_system_resource'), true)) return 'provider_error';
		return 'unknown';
	}

		function cbia_openai_responses_call($prompt, $title_for_log = '', $tries = 2, $max_output_override = 0, $context = array()) {
			cbia_try_unlimited_runtime();
			$attempts = array();
			// CAMBIO: proveedor de texto
			$context = is_array($context) ? $context : array();
			$provider = !empty($context['provider']) ? sanitize_key((string)$context['provider']) : cbia_get_current_provider_key();
			$allow_fallback = !array_key_exists('allow_fallback', $context) || !empty($context['allow_fallback']);
			$ignore_stop = !empty($context['ignore_stop']);
		if ($provider === 'openai' && !cbia_openai_consent_ok()) {
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('OpenAI consent not accepted', 'cbiastudio-blogflow-ai'), []];
		}

		$s = cbia_get_settings();
		// CAMBIO: modelo segun proveedor texto
		$model_preferred = !empty($context['model'])
			? sanitize_text_field((string)$context['model'])
			: (function_exists('cbia_get_text_model_for_provider')
			? cbia_get_text_model_for_provider($provider, cbia_pick_model())
			: cbia_pick_model());
		$disable_model_fallback = !empty($GLOBALS['cbia_disable_text_model_fallback']);
		$chain = ($disable_model_fallback || !$allow_fallback)
			? array_values(array_filter(array(trim((string)$model_preferred))))
			: cbia_openai_text_attempt_chain($model_preferred);
		if (empty($context['temporary_context_id'])) $context['temporary_context_id'] = 'tmp-' . substr(hash('sha256', (string)$title_for_log . '|' . microtime(true) . '|' . wp_generate_uuid4()), 0, 24);

		$system = sanitize_key((string)($context['phase'] ?? '')) === 'configuration_test'
			? ''
			: "Eres un redactor editorial. Devuelve HTML simple con <h2>, <h3>, <p>, <ul>, <li>. NO uses <h1> ni envolturas <html>/<head>/<body>. No uses <table>, <iframe> ni <blockquote>.";
		$global_last_error = __('Could not get a streaming response.', 'cbiastudio-blogflow-ai');
		$last_err = __('Could not get a response.', 'cbiastudio-blogflow-ai');
		$input = array();
		if ($system !== '') $input[] = ['role' => 'system', 'content' => $system];
		$input[] = ['role' => 'user', 'content' => (string)$prompt];

		if ($provider === 'google') {
			return cbia_google_generate_content_call($prompt, $system, $tries, $max_output_override, $context);
		}
		if ($provider === 'deepseek') {
			$deepseek_result = cbia_deepseek_chat_call($prompt, $system, $tries, $max_output_override, $context);
			if (!empty($deepseek_result[0])) {
				return $deepseek_result;
			}
			if (!$allow_fallback) return $deepseek_result;

			$last_err = (string)($deepseek_result[4] ?? __('Could not get a response.', 'cbiastudio-blogflow-ai'));
			$deepseek_raw = is_array($deepseek_result[5] ?? null) ? $deepseek_result[5] : array();
			$deepseek_model = (string)($deepseek_result[3] ?? 'deepseek-v4-flash');
			$prev_forced = isset($GLOBALS['cbia_force_text_provider']) ? (string)$GLOBALS['cbia_force_text_provider'] : '';

			$google_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('google') : '';
			if ($google_key !== '') {
				cbia_log("DeepSeek failed ({$last_err}). Text fallback -> Google Gemini.", 'WARN');
				$GLOBALS['cbia_force_text_provider'] = 'google';
				$google_result = cbia_openai_responses_call($prompt, $title_for_log, $tries, $max_output_override, $context);
				if ($prev_forced !== '') $GLOBALS['cbia_force_text_provider'] = $prev_forced;
				else unset($GLOBALS['cbia_force_text_provider']);
				$google_result = cbia_merge_provider_attempts($google_result, $deepseek_raw, $deepseek_model);
				if (!empty($google_result[0])) return $google_result;
				$deepseek_raw = is_array($google_result[5] ?? null) ? $google_result[5] : $deepseek_raw;
				$last_err = (string)($google_result[4] ?? $last_err);
			}

			$openai_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : '';
			if ($openai_key !== '') {
				cbia_log("Google fallback unavailable/failed. Text fallback -> OpenAI.", 'WARN');
				$GLOBALS['cbia_force_text_provider'] = 'openai';
				$openai_result = cbia_openai_responses_call($prompt, $title_for_log, $tries, $max_output_override, $context);
				if ($prev_forced !== '') $GLOBALS['cbia_force_text_provider'] = $prev_forced;
				else unset($GLOBALS['cbia_force_text_provider']);
				$openai_result = cbia_merge_provider_attempts($openai_result, $deepseek_raw, $deepseek_model);
				if (!empty($openai_result[0])) return $openai_result;
				$deepseek_raw = is_array($openai_result[5] ?? null) ? $openai_result[5] : $deepseek_raw;
				$last_err = (string)($openai_result[4] ?? $last_err);
			}

			return [false, '', cbia_usage_empty(), $deepseek_model, $last_err, $deepseek_raw];
		}
		// CAMBIO: key OpenAI desde settings por proveedor
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : cbia_openai_api_key();
		if (!$api_key) {
			cbia_log(__('Missing OpenAI API key for text generation.', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('No API key', 'cbiastudio-blogflow-ai'), []];
		}

		$sequence = 0;
		foreach ($chain as $model) {
			if (!cbia_is_responses_model($model)) continue;

			for ($t = 1; $t <= 1; $t++) {
				$sequence++;
				$attempt_context = cbia_openai_text_attempt_context($context, $title_for_log, $sequence, $model, $model_preferred);
				if (!$ignore_stop && cbia_is_stop_requested()) {
					return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Stop enabled', 'cbiastudio-blogflow-ai'), []];
				}

				cbia_log(("OpenAI Responses: model={$model} attempt {$sequence}/" . count($chain) . " ") . ($title_for_log ? "| '{$title_for_log}'" : ''), 'INFO');

				$max_out = (int)($s['responses_max_output_tokens'] ?? 6000);
				$max_out_override = (int)$max_output_override;
				if ($max_out_override > 0) {
					$strict_override = !empty($context['strict_max_output_override']) || sanitize_key((string)($context['phase'] ?? '')) === 'configuration_test';
					$max_out = $strict_override ? $max_out_override : max($max_out, $max_out_override);
				}
				if (sanitize_key((string)($context['phase'] ?? '')) === 'configuration_test') {
					$max_out = max(16, min(64, $max_out));
				} elseif ($max_out < 1500) $max_out = 1500;
				if ($max_out > 12000) $max_out = 12000;
				$temperature = isset($s['openai_temperature']) ? (float)$s['openai_temperature'] : 0.7;
				if ($temperature < 0) $temperature = 0;
				if ($temperature > 2) $temperature = 2;

				$payload = [
					'model' => $model,
					'input' => $input,
					// Max output prudente (luego el prompt manda)
					'max_output_tokens' => $max_out,
				];
				if (cbia_openai_model_supports_temperature($model)) {
					$payload['temperature'] = $temperature;
				}
				$capabilities = cbia_openai_text_model_capabilities($model);
				if (!empty($capabilities['reasoning_effort_minimal'])) $payload['reasoning'] = array('effort' => 'minimal');
				if (!empty($capabilities['text_verbosity'])) $payload['text'] = array('verbosity' => 'high');

				$request_started = microtime(true);
				$resp = wp_remote_post('https://api.openai.com/v1/responses', [
					'headers' => cbia_http_headers_openai($api_key),
					'body'    => wp_json_encode($payload),
					'timeout' => cbia_openai_text_timeout($context),
				]);
				$elapsed_ms = (int)round((microtime(true) - $request_started) * 1000);

				if (is_wp_error($resp)) {
					$err = $resp->get_error_message();
					if (function_exists('cbia_mask_sensitive_log_text')) $err = cbia_mask_sensitive_log_text((string)$err);
					cbia_log(("HTTP error: {$err}"), 'ERROR');
					$attempt = array_merge($attempt_context, array('elapsed_ms' => $elapsed_ms, 'ok' => 0, 'status' => 'timeout', 'error' => (string)$err));
					$attempts[] = $attempt;
					cbia_openai_record_text_attempt($attempt_context, cbia_usage_empty(), $attempt);
					$last_err = (string)$err;
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code($resp);
				$request_id = sanitize_text_field((string)wp_remote_retrieve_header($resp, 'x-request-id'));
				$body = (string) wp_remote_retrieve_body($resp);
				$data = json_decode($body, true);
				if ($code >= 200 && $code < 300 && !is_array($data)) {
					$err = 'Invalid JSON response';
					$attempt = array_merge($attempt_context, array('elapsed_ms' => $elapsed_ms, 'http_code' => $code, 'request_id' => $request_id, 'ok' => 0, 'status' => 'error', 'error' => $err));
					$attempts[] = $attempt;
					cbia_openai_record_text_attempt($attempt_context, cbia_usage_empty(), $attempt);
					return [false, '', cbia_usage_empty(), $model, $err, cbia_attach_attempts_meta(array(), $attempts)];
				}

				if ($code < 200 || $code >= 300) {
					$msg = '';
					if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
					$msg = cbia_sanitize_provider_error('openai', $msg, $code);
					$err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
					cbia_log(("OpenAI error: {$err}"), 'ERROR');
					$usage_error = cbia_usage_from_responses_payload($data);
					$attempt = array_merge($attempt_context, $usage_error, array('elapsed_ms' => $elapsed_ms, 'http_code' => $code, 'request_id' => $request_id, 'ok' => 0, 'status' => 'error', 'error' => (string)$err));
					$attempts[] = $attempt;
					cbia_openai_record_text_attempt($attempt_context, $usage_error, $attempt);
					$last_err = (string)$err;
					if (!in_array($code, array(408, 429, 500, 502, 503, 504), true)) {
						return [false, '', $usage_error, $model, $last_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					$retry_after = (int)wp_remote_retrieve_header($resp, 'retry-after');
					if ($retry_after > 0 && $sequence < count($chain)) sleep(min(120, $retry_after));
					continue;
				}

				if (is_array($data) && !empty($data['error']['message'])) {
					$err = (string)$data['error']['message'];
					if (function_exists('cbia_mask_sensitive_log_text')) $err = cbia_mask_sensitive_log_text((string)$err);
					cbia_log(("OpenAI error payload: {$err}"), 'ERROR');
					$usage_error = cbia_usage_from_responses_payload($data);
					$attempt = array_merge($attempt_context, $usage_error, array('elapsed_ms' => $elapsed_ms, 'http_code' => $code, 'request_id' => $request_id, 'ok' => 0, 'status' => 'error', 'error' => (string)$err));
					$attempts[] = $attempt;
					cbia_openai_record_text_attempt($attempt_context, $usage_error, $attempt);
					$last_err = (string)$err;
					if (stripos($err, 'incorrect api key') !== false || stripos($err, 'unauthorized') !== false || stripos($err, 'forbidden') !== false) {
						return [false, '', $usage_error, $model, $last_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					return [false, '', $usage_error, $model, $last_err, cbia_attach_attempts_meta($data, $attempts)];
				}

				$text = cbia_extract_text_from_responses_payload($data);
				$usage = cbia_usage_from_responses_payload($data);
				$response_status = sanitize_key((string)($data['status'] ?? 'completed'));
				$incomplete_reason = sanitize_key((string)($data['incomplete_details']['reason'] ?? ''));
				$completion_status = cbia_normalize_openai_completion_status($response_status, $incomplete_reason);

				if ($text === '') {
					cbia_log(("Response without text (model={$model})"), 'ERROR');
					$attempt = array_merge($attempt_context, $usage, array('elapsed_ms' => $elapsed_ms, 'http_code' => $code, 'request_id' => $request_id, 'ok' => 0, 'status' => $response_status ?: 'error', 'status_reason' => $incomplete_reason, 'error' => 'Response without text'));
					$attempts[] = $attempt;
					cbia_openai_record_text_attempt($attempt_context, $usage, $attempt);
					$last_err = 'Response without text';
					return [false, '', $usage, $model, $last_err, cbia_attach_attempts_meta($data, $attempts)];
				}

				cbia_openai_log_text_success($model, $usage, false);

				$completed = $completion_status === 'complete';
				$attempt = array_merge($attempt_context, $usage, array('elapsed_ms' => $elapsed_ms, 'http_code' => $code, 'request_id' => $request_id, 'ok' => $completed ? 1 : 0, 'status' => $completed ? 'success' : $completion_status, 'status_reason' => $incomplete_reason, 'completion_status' => $completion_status, 'provider_status' => $response_status, 'provider_incomplete_reason' => $incomplete_reason));
				cbia_openai_record_text_attempt($attempt_context, $usage, $attempt);
				$data['_cbia_request_meta'] = array_merge($attempt_context, array('provider' => 'openai', 'model_requested' => (string)$model_preferred, 'model_effective' => (string)$model, 'fallback_from' => ((string)$model !== (string)$model_preferred ? (string)$model_preferred : ''), 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'status' => $completed ? 'success' : $completion_status, 'status_reason' => $incomplete_reason, 'completion_status' => $completion_status, 'provider_status' => $response_status, 'provider_incomplete_reason' => $incomplete_reason, 'finish_reason' => $completion_status === 'output_limit' ? 'max_tokens' : ($completion_status === 'complete' ? 'stop' : $completion_status)));
				return [$completed, $text, $usage, $model, $completed ? '' : ('Incomplete response' . ($incomplete_reason ? ': ' . $incomplete_reason : '')), cbia_attach_attempts_meta($data, $attempts)];
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
					'stream' => true,
				];
				if (cbia_openai_model_supports_temperature($model)) {
					$payload['temperature'] = $temperature;
				}

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

				cbia_openai_log_text_success($model, $last_usage, true);
				return [true, $acc_text, $last_usage, $model, '', $last_event];
			}
		}
		return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', $global_last_error, []];
	}
}
/* =========================================================
   ================== OPENAI: IMÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂGENES ======================
   ========================================================= */

if (!function_exists('cbia_openai_image_request_fields')) {
	function cbia_openai_image_request_fields($quality, $size) {
		$requested_quality = strtolower(trim((string)$quality));
		if (!in_array($requested_quality, array('auto', 'low', 'medium', 'high'), true)) $requested_quality = 'auto';
		$effective_quality = $requested_quality === 'auto' ? null : $requested_quality;
		$requested_size = function_exists('cbia_usage_normalize_image_size') ? cbia_usage_normalize_image_size($size, true) : (string)$size;
		$effective_size = $requested_size !== 'auto' ? $requested_size : null;
		return array(
			'requested_quality' => $requested_quality,
			'effective_quality' => $effective_quality,
			'quality_requested' => $requested_quality,
			'quality_effective' => $effective_quality,
			'quality' => $effective_quality !== null ? $effective_quality : $requested_quality,
			'requested_size' => $requested_size,
			'effective_size' => $effective_size,
			'size' => $effective_size !== null ? $effective_size : (string)$requested_size,
		);
	}
}

if (!function_exists('cbia_openai_finalize_image_meta')) {
	function cbia_openai_finalize_image_meta($model, $request_fields, $response_fields, $extra = array()) {
		$meta = array_merge((array)$request_fields, (array)$response_fields, (array)$extra);
		$effective_quality = $meta['effective_quality'] ?? ($meta['quality_effective'] ?? null);
		$effective_size = $meta['effective_size'] ?? ($meta['size'] ?? null);
		$estimated_micro = null;
		if ($effective_quality !== null && class_exists('CBIA_Image_Pricing_Service')) {
			$estimated_micro = CBIA_Image_Pricing_Service::get_price_micro_usd((string)$model, (string)$effective_quality, (string)$effective_size);
		}
		$meta['estimated_micro_usd'] = $estimated_micro;
		if (function_exists('cbia_costes_calculate_row')) {
			$cost = cbia_costes_calculate_row(array_merge($meta, array('type' => 'image', 'model' => (string)$model, 'model_effective' => (string)$model, 'ok' => 1)));
			foreach ($cost as $key => $value) $meta[$key] = $value;
		}
		return $meta;
	}
}

if (!function_exists('cbia_openai_log_text_success')) {
	function cbia_openai_log_text_success($model, $usage, $stream = false) {
		$cost = function_exists('cbia_costes_calculate_row')
			? cbia_costes_calculate_row(array_merge((array)$usage, array(
				'type' => 'text',
				'provider' => 'openai',
				'model' => (string)$model,
				'model_effective' => (string)$model,
				'ok' => 1,
			)))
			: array();
		$exact_cost_usd = isset($cost['cost_micro_usd']) && (string)($cost['cost_status'] ?? '') === 'exact'
			? rtrim(rtrim(number_format(((int)$cost['cost_micro_usd']) / 1000000, 6, '.', ''), '0'), '.')
			: 'unknown';
		cbia_log(sprintf(
			'OpenAI Responses%1$s OK: model=%2$s tokens_in=%3$d cached_input_tokens=%4$d tokens_out=%5$d reasoning_tokens=%6$d cost_status=%7$s exact_cost_usd=%8$s',
			$stream ? ' STREAM' : '',
			(string)$model,
			(int)($usage['input_tokens'] ?? 0),
			(int)($usage['cached_input_tokens'] ?? 0),
			(int)($usage['output_tokens'] ?? 0),
			(int)($usage['reasoning_tokens'] ?? 0),
			(string)($cost['cost_status'] ?? 'unknown'),
			$exact_cost_usd
		), 'INFO');
	}
}

if (!function_exists('cbia_openai_log_image_success')) {
	function cbia_openai_log_image_success($section_label, $attach_id, $model, $image_meta, $http_code, $request_id = '') {
		$effective_quality = $image_meta['effective_quality'] !== null ? (string)$image_meta['effective_quality'] : 'unknown';
		$effective_size = $image_meta['effective_size'] !== null ? (string)$image_meta['effective_size'] : 'unknown';
		$estimated_output_usd = $image_meta['estimated_micro_usd'] !== null
			? rtrim(rtrim(number_format(((int)$image_meta['estimated_micro_usd']) / 1000000, 6, '.', ''), '0'), '.')
			: 'unknown';
		$exact_cost_usd = isset($image_meta['cost_micro_usd']) && (string)($image_meta['cost_status'] ?? '') === 'exact'
			? rtrim(rtrim(number_format(((int)$image_meta['cost_micro_usd']) / 1000000, 6, '.', ''), '0'), '.')
			: 'unknown';

		cbia_log(sprintf(
			'AI image OK: section=%1$s attach_id=%2$d model=%3$s requested_quality=%4$s effective_quality=%5$s requested_size=%6$s effective_size=%7$s input_tokens=%8$d cached_input_tokens=%9$d text_input_tokens=%10$d image_input_tokens=%11$d output_tokens=%12$d image_output_tokens=%13$d cost_status=%14$s exact_cost_usd=%15$s estimated_output_usd=%16$s HTTP=%17$d%18$s',
			(string)$section_label,
			(int)$attach_id,
			(string)$model,
			(string)($image_meta['requested_quality'] ?? 'auto'),
			$effective_quality,
			(string)($image_meta['requested_size'] ?? 'auto'),
			$effective_size,
			(int)($image_meta['input_tokens'] ?? 0),
			(int)($image_meta['cached_input_tokens'] ?? 0),
			(int)($image_meta['text_input_tokens'] ?? ($image_meta['input_text_tokens'] ?? 0)),
			(int)($image_meta['image_input_tokens'] ?? ($image_meta['input_image_tokens'] ?? 0)),
			(int)($image_meta['output_tokens'] ?? 0),
			(int)($image_meta['image_output_tokens'] ?? ($image_meta['output_image_tokens'] ?? 0)),
			(string)($image_meta['cost_status'] ?? 'unknown'),
			$exact_cost_usd,
			$estimated_output_usd,
			(int)$http_code,
			$request_id !== '' ? ' request_id=' . sanitize_text_field((string)$request_id) : ''
		), 'INFO');
	}
}

if (!function_exists('cbia_openai_store_image_response_meta')) {
	function cbia_openai_store_image_response_meta($attach_id, $meta, $image_type) {
		$attach_id = (int)$attach_id;
		if ($attach_id <= 0) return;
		$requested_quality = (string)($meta['requested_quality'] ?? 'auto');
		$effective_quality = $meta['effective_quality'] ?? null;
		$requested_size = (string)($meta['requested_size'] ?? '');
		$effective_size = $meta['effective_size'] ?? null;
		update_post_meta($attach_id, '_cbia_image_requested_quality', $requested_quality);
		update_post_meta($attach_id, '_cbia_image_quality', $effective_quality !== null ? (string)$effective_quality : $requested_quality);
		if ($effective_quality !== null) update_post_meta($attach_id, '_cbia_image_effective_quality', (string)$effective_quality);
		else delete_post_meta($attach_id, '_cbia_image_effective_quality');
		update_post_meta($attach_id, '_cbia_image_requested_size', $requested_size);
		update_post_meta($attach_id, '_cbia_image_size', $effective_size !== null ? (string)$effective_size : $requested_size);
		if ($effective_size !== null) update_post_meta($attach_id, '_cbia_image_effective_size', (string)$effective_size);
		else delete_post_meta($attach_id, '_cbia_image_effective_size');
		if (!empty($meta['output_format'])) update_post_meta($attach_id, '_cbia_image_output_format', (string)$meta['output_format']);
		if (!empty($meta['background'])) update_post_meta($attach_id, '_cbia_image_background', (string)$meta['background']);
		update_post_meta($attach_id, '_cbia_image_type', $image_type);
	}
}

if (!function_exists('cbia_image_batch_auth_guard_get')) {
	function cbia_image_batch_auth_guard_get(): array {
		$guard = get_option('cbia_image_batch_auth_guard', array());
		return is_array($guard) ? $guard : array();
	}
	function cbia_image_batch_auth_guard_begin(): void {
		update_option('cbia_image_batch_auth_guard', array('active'=>1,'blocked'=>0,'skipped'=>0,'started_at'=>time()), false);
	}
	function cbia_image_batch_auth_guard_block($request_id = ''): void {
		$guard = cbia_image_batch_auth_guard_get();
		if (empty($guard['active'])) return;
		$guard['blocked'] = 1; $guard['blocked_at'] = time(); $guard['request_id'] = sanitize_text_field((string)$request_id);
		update_option('cbia_image_batch_auth_guard', $guard, false);
		cbia_log('OpenAI image authentication failed with HTTP 401. Remaining image calls in this batch are disabled and will stay pending.', 'ERROR');
	}
	function cbia_image_batch_auth_guard_skip($section, $model): ?array {
		$guard = cbia_image_batch_auth_guard_get();
		if (empty($guard['active']) || empty($guard['blocked'])) return null;
		$guard['skipped'] = max(0, (int)($guard['skipped'] ?? 0)) + 1;
		update_option('cbia_image_batch_auth_guard', $guard, false);
		$error = __('Image request skipped after an OpenAI authentication failure in this batch.', 'cbiastudio-blogflow-ai');
		cbia_log(sprintf('AI image skipped: section=%s reason=batch_auth_401 cost=0.', (string)$section), 'WARN');
		$meta = array('type'=>'image','provider'=>'openai','model'=>(string)$model,'section'=>(string)$section,'request_sent'=>0,'billable'=>0,'cost_micro_usd'=>0,'cost_status'=>'exact','cost_source'=>'local_batch_auth_guard','result_status'=>'skipped_auth_guard','error_type'=>'authentication','status'=>'skipped_auth_guard','ok'=>0);
		return array(false, 0, (string)$model, $error, cbia_attach_attempts_meta($meta, array($meta)));
	}
	function cbia_image_batch_auth_guard_finish(): array {
		$guard = cbia_image_batch_auth_guard_get();
		delete_option('cbia_image_batch_auth_guard');
		return $guard;
	}
}

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
			$guard_model = function_exists('cbia_get_image_model_for_provider') ? cbia_get_image_model_for_provider('openai', '') : '';
			$guard_skip = cbia_image_batch_auth_guard_skip($section, $guard_model);
			if (is_array($guard_skip)) return $guard_skip;
			// CAMBIO: key OpenAI desde settings por proveedor
			$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : cbia_openai_api_key();
			if (!$api_key) {
				$error = __('No API key', 'cbiastudio-blogflow-ai');
				$model = function_exists('cbia_get_image_model_for_provider') ? cbia_get_image_model_for_provider('openai', '') : '';
				return [false, 0, $model, $error, cbia_local_blocked_attempt_meta('image', 'openai', $model, 'missing_api_key', $error, $section)];
			}
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
		$image_type = class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_image_type($section, $idx) : ((int)$idx > 0 ? 'content' : 'featured');
		foreach (cbia_image_model_chain('openai', $preferred_model) as $model) {
			$quality = function_exists('cbia_get_image_quality') ? cbia_get_image_quality($section, $idx) : 'auto';
			$request_config = class_exists('CBIA_Image_Pricing_Service')
				? CBIA_Image_Pricing_Service::prepare_api_payload($model, $prompt, $size, $quality, array('n' => 1))
				: array('payload' => array('model' => $model, 'prompt' => $prompt, 'n' => 1, 'size' => $size), 'model' => $model, 'quality' => $quality, 'size' => $size, 'warning' => '');
			$model = (string)$request_config['model'];
			$quality = (string)$request_config['quality'];
			$size = (string)$request_config['size'];
			$estimated_micro = class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_price_micro_usd($model, $quality, $size) : null;
			$estimated_label = null === $estimated_micro ? 'variable' : CBIA_Image_Pricing_Service::format_usd($estimated_micro);
			$request_fields = cbia_openai_image_request_fields($quality, $size);
			if (!empty($request_config['warning'])) cbia_log((string)$request_config['warning'], 'WARN');
			$tries = 2;
			for ($t = 1; $t <= $tries; $t++) {
				if (cbia_is_stop_requested()) return [false, 0, $model, __('Stop enabled', 'cbiastudio-blogflow-ai')];

				$delay = function_exists('cbia_get_image_request_delay') ? cbia_get_image_request_delay() : 0;
				if ($delay > 0) sleep($delay);

				/* translators: 1: image model, 2: section label, 3: current attempt, 4: total attempts. */
				cbia_log(sprintf('AI image: model=%1$s requested_quality=%2$s requested_size=%3$s type=%4$s section=%5$s estimated_output=%6$s attempt %7$d/%8$d', (string)$model, (string)$quality, (string)$size, (string)$image_type, (string)$section_label, (string)$estimated_label, (int)$t, (int)$tries), 'INFO');

				$payload = (array)$request_config['payload'];

				$request_started = microtime(true);
				$resp = wp_remote_post('https://api.openai.com/v1/images/generations', [
					'headers' => cbia_http_headers_openai($api_key),
					'body'    => wp_json_encode($payload),
					'timeout' => cbia_image_api_timeout_seconds(),
				]);
				$elapsed_ms = (int)round((microtime(true) - $request_started) * 1000);

				if (is_wp_error($resp)) {
					$http_err = cbia_sanitize_provider_error('openai', $resp->get_error_message());
					if (strpos($http_err, 'cURL error 28') !== false) {
						$http_err .= sprintf(' (timeout=%ss, download_timeout=%ss)', (string)cbia_image_api_timeout_seconds(), (string)cbia_image_download_timeout_seconds());
					}
					cbia_log((__('AI image HTTP error: ', 'cbiastudio-blogflow-ai')) . $http_err, 'ERROR');
					$attempts[] = array_merge($request_fields, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => $http_err));
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code($resp);
				$request_id = sanitize_text_field((string)wp_remote_retrieve_header($resp, 'x-request-id'));
				$body = (string) wp_remote_retrieve_body($resp);
				$data = json_decode($body, true);
				$image_usage = function_exists('cbia_usage_from_images_payload') ? cbia_usage_from_images_payload($data, $quality, $size) : $request_fields;

				if ($code < 200 || $code >= 300) {
					$msg = '';
					if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
					$msg = cbia_sanitize_provider_error('openai', $msg, $code);
					$http_err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
					/* translators: %d is the HTTP status code returned by the image API. */
					cbia_log((sprintf(__('AI image HTTP %d error', 'cbiastudio-blogflow-ai'), (int)$code)) . ($msg ? " | {$msg}" : '') . ($request_id ? " | request_id={$request_id}" : ''), 'ERROR');
					$failure_cost = in_array($code, array(401, 403), true) ? array('request_sent' => 1, 'billable' => 0, 'cost_micro_usd' => 0, 'cost_status' => 'exact', 'cost_source' => 'provider_rejected_before_generation', 'result_status' => 'authentication_error', 'error_type' => 'authentication', 'status' => 'authentication_error') : array();
					$attempts[] = array_merge($request_fields, $image_usage, $failure_cost, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => $http_err));
					if ($code === 401) cbia_image_batch_auth_guard_block($request_id);
					if (in_array($code, array(401, 403, 404), true)) {
						return [false, 0, $model, $http_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				if (is_array($data) && !empty($data['error']['message'])) {
					$payload_err = cbia_sanitize_provider_error('openai', (string)$data['error']['message'], $code);
					cbia_log((__('AI image payload error: ', 'cbiastudio-blogflow-ai')) . $payload_err, 'ERROR');
					$attempts[] = array_merge($request_fields, $image_usage, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => $payload_err));
					if (stripos($payload_err, 'incorrect api key') !== false || stripos($payload_err, 'unauthorized') !== false || stripos($payload_err, 'forbidden') !== false) {
						return [false, 0, $model, $payload_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				$bytes = '';
				if (!empty($data['data'][0]['b64_json'])) {
					$bytes = base64_decode((string)$data['data'][0]['b64_json'], true);
				} elseif (!empty($data['data'][0]['url'])) {
					$img = wp_remote_get((string)$data['data'][0]['url'], ['timeout' => cbia_image_download_timeout_seconds()]);
					if (!is_wp_error($img) && (int)wp_remote_retrieve_response_code($img) === 200) {
						$bytes = (string)wp_remote_retrieve_body($img);
					}
				}

				if (!is_string($bytes) || $bytes === '') {
					/* translators: %s is the image model name. */
					cbia_log((sprintf(__('AI image: response without bytes (model=%s)', 'cbiastudio-blogflow-ai'), (string)$model)), 'ERROR');
					$attempts[] = array_merge($request_fields, $image_usage, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => __('Response without bytes', 'cbiastudio-blogflow-ai')));
					continue;
				}

				list($attach_id, $uerr) = cbia_upload_image_to_media($bytes, $title, $section, $alt);
				if (!$attach_id) {
					/* translators: %s is the upload error message from WordPress media handling. */
					cbia_log((sprintf(__('AI image: upload to Media Library failed: %s', 'cbiastudio-blogflow-ai'), (string)$uerr)), 'ERROR');
					$attempts[] = array_merge($request_fields, $image_usage, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => (string)$uerr, 'billable' => 1));
					continue;
				}

				$image_meta = cbia_openai_finalize_image_meta($model, $request_fields, $image_usage, array('provider' => 'openai', 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'fallback_from' => ((string)$model !== (string)$preferred_model ? (string)$preferred_model : ''), 'image_type' => $image_type, 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms));
				cbia_openai_log_image_success($section_label, $attach_id, $model, $image_meta, $code, $request_id);
				cbia_openai_store_image_response_meta((int)$attach_id, $image_meta, $image_type);
				return [true, (int)$attach_id, $model, '', cbia_attach_attempts_meta($image_meta, $attempts)];
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
		$guard_model = function_exists('cbia_get_image_model_for_provider') ? cbia_get_image_model_for_provider('openai', '') : '';
		$guard_skip = cbia_image_batch_auth_guard_skip($section, $guard_model);
		if (is_array($guard_skip)) return $guard_skip;
		// CAMBIO: key OpenAI desde settings por proveedor
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : cbia_openai_api_key();
		if (!$api_key) {
			$error = __('No API key', 'cbiastudio-blogflow-ai');
			$model = function_exists('cbia_get_image_model_for_provider') ? cbia_get_image_model_for_provider('openai', '') : '';
			return [false, 0, $model, $error, cbia_local_blocked_attempt_meta('image', 'openai', $model, 'missing_api_key', $error, $section)];
		}
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
		$image_type = class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_image_type($section, $idx) : ((int)$idx > 0 ? 'content' : 'featured');
		foreach (cbia_image_model_chain('openai', $preferred_model) as $model) {
			$quality = function_exists('cbia_get_image_quality') ? cbia_get_image_quality($section, $idx) : 'auto';
			$request_config = class_exists('CBIA_Image_Pricing_Service')
				? CBIA_Image_Pricing_Service::prepare_api_payload($model, (string)$prompt, $size, $quality, array('n' => 1))
				: array('payload' => array('model' => $model, 'prompt' => (string)$prompt, 'n' => 1, 'size' => $size), 'model' => $model, 'quality' => $quality, 'size' => $size, 'warning' => '');
			$model = (string)$request_config['model']; $quality = (string)$request_config['quality']; $size = (string)$request_config['size'];
			$estimated_micro = class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_price_micro_usd($model, $quality, $size) : null;
			$estimated_label = null === $estimated_micro ? 'variable' : CBIA_Image_Pricing_Service::format_usd($estimated_micro);
			$request_fields = cbia_openai_image_request_fields($quality, $size);
			if (!empty($request_config['warning'])) cbia_log((string)$request_config['warning'], 'WARN');
			$tries = 2;
			for ($t = 1; $t <= $tries; $t++) {
				if (cbia_is_stop_requested()) return [false, 0, $model, __('Stop enabled', 'cbiastudio-blogflow-ai')];

				$delay = function_exists('cbia_get_image_request_delay') ? cbia_get_image_request_delay() : 0;
				if ($delay > 0) sleep($delay);

				/* translators: 1: image model, 2: section label, 3: current attempt, 4: total attempts. */
				cbia_log(sprintf('AI image: model=%1$s requested_quality=%2$s requested_size=%3$s type=%4$s section=%5$s estimated_output=%6$s attempt %7$d/%8$d', (string)$model, (string)$quality, (string)$size, (string)$image_type, (string)$section_label, (string)$estimated_label, (int)$t, (int)$tries), 'INFO');

				$payload = (array)$request_config['payload'];

				$request_started = microtime(true);
				$resp = wp_remote_post('https://api.openai.com/v1/images/generations', [
					'headers' => cbia_http_headers_openai($api_key),
					'body'    => wp_json_encode($payload),
					'timeout' => cbia_image_api_timeout_seconds(),
				]);
				$elapsed_ms = (int)round((microtime(true) - $request_started) * 1000);

				if (is_wp_error($resp)) {
					$http_err = (string)$resp->get_error_message();
					if (strpos($http_err, 'cURL error 28') !== false) {
						$http_err .= sprintf(' (timeout=%ss, download_timeout=%ss)', (string)cbia_image_api_timeout_seconds(), (string)cbia_image_download_timeout_seconds());
					}
					cbia_log((__('AI image HTTP error: ', 'cbiastudio-blogflow-ai')) . $http_err, 'ERROR');
					$attempts[] = array_merge($request_fields, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => $http_err));
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code($resp);
				$request_id = sanitize_text_field((string)wp_remote_retrieve_header($resp, 'x-request-id'));
				$body = (string) wp_remote_retrieve_body($resp);
				$data = json_decode($body, true);
				$image_usage = function_exists('cbia_usage_from_images_payload') ? cbia_usage_from_images_payload($data, $quality, $size) : $request_fields;

				if ($code < 200 || $code >= 300) {
					$msg = '';
					if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
					$http_err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
					/* translators: %d is the HTTP status code returned by the image API. */
					cbia_log((sprintf(__('AI image HTTP %d error', 'cbiastudio-blogflow-ai'), (int)$code)) . ($msg ? " | {$msg}" : '') . ($request_id ? " | request_id={$request_id}" : ''), 'ERROR');
					$attempts[] = array_merge($request_fields, $image_usage, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => $http_err));
					if ($code === 401) cbia_image_batch_auth_guard_block($request_id);
					if (in_array($code, array(401, 403, 404), true)) {
						return [false, 0, $model, $http_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				if (is_array($data) && !empty($data['error']['message'])) {
					$payload_err = (string)$data['error']['message'];
					cbia_log((__('AI image payload error: ', 'cbiastudio-blogflow-ai')) . $payload_err, 'ERROR');
					$attempts[] = array_merge($request_fields, $image_usage, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => $payload_err));
					if (stripos($payload_err, 'incorrect api key') !== false || stripos($payload_err, 'unauthorized') !== false || stripos($payload_err, 'forbidden') !== false) {
						return [false, 0, $model, $payload_err, cbia_attach_attempts_meta(array(), $attempts)];
					}
					continue;
				}

				$bytes = '';
				if (!empty($data['data'][0]['b64_json'])) {
					$bytes = base64_decode((string)$data['data'][0]['b64_json'], true);
				} elseif (!empty($data['data'][0]['url'])) {
					$img = wp_remote_get((string)$data['data'][0]['url'], ['timeout' => cbia_image_download_timeout_seconds()]);
					if (!is_wp_error($img) && (int)wp_remote_retrieve_response_code($img) === 200) {
						$bytes = (string)wp_remote_retrieve_body($img);
					}
				}

				if (!is_string($bytes) || $bytes === '') {
					/* translators: %s is the image model name. */
					cbia_log((sprintf(__('AI image: response without bytes (model=%s)', 'cbiastudio-blogflow-ai'), (string)$model)), 'ERROR');
					$attempts[] = array_merge($request_fields, $image_usage, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => __('Response without bytes', 'cbiastudio-blogflow-ai')));
					continue;
				}

				list($attach_id, $uerr) = cbia_upload_image_to_media($bytes, $title, $section, $alt);
				if (!$attach_id) {
					/* translators: %s is the upload error message from WordPress media handling. */
					cbia_log((sprintf(__('AI image: upload to Media Library failed: %s', 'cbiastudio-blogflow-ai'), (string)$uerr)), 'ERROR');
					$attempts[] = array_merge($request_fields, $image_usage, array('type' => 'image', 'provider' => 'openai', 'section' => (string)$section, 'image_type' => $image_type, 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'model' => (string)$model, 'http_code' => $code, 'request_id' => $request_id, 'attempt' => (int)$t, 'elapsed_ms' => $elapsed_ms, 'ok' => 0, 'error' => (string)$uerr, 'billable' => 1));
					continue;
				}

				$image_meta = cbia_openai_finalize_image_meta($model, $request_fields, $image_usage, array('provider' => 'openai', 'model_requested' => (string)$preferred_model, 'model_effective' => (string)$model, 'fallback_from' => ((string)$model !== (string)$preferred_model ? (string)$preferred_model : ''), 'image_type' => $image_type, 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms));
				cbia_openai_log_image_success($section_label, $attach_id, $model, $image_meta, $code, $request_id);
				cbia_openai_store_image_response_meta((int)$attach_id, $image_meta, $image_type);
				return [true, (int)$attach_id, $model, '', cbia_attach_attempts_meta($image_meta, $attempts)];
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
	function cbia_google_generate_content_call($prompt, $system = '', $tries = 2, $max_output_override = 0, $context = array()) {
		$attempts = array();
		$context = is_array($context) ? $context : array();
		$cfg = cbia_get_provider_config('google');
		// CAMBIO: key y modelo segun settings de texto
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('google') : (string)($cfg['api_key'] ?? '');
		if ($api_key === '') {
			cbia_log(__('Missing Google API key for text generation.', 'cbiastudio-blogflow-ai'), 'ERROR');
			return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], '', __('No API key (Google)', 'cbiastudio-blogflow-ai'), []];
		}

		$model = !empty($context['model'])
			? sanitize_text_field((string)$context['model'])
			: (function_exists('cbia_get_text_model_for_provider')
			? cbia_get_text_model_for_provider('google', 'gemini-2.5-flash')
			: cbia_get_provider_model('google', 'gemini-2.5-flash'));
		$base_url = rtrim((string)($cfg['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/');
		$api_version = trim((string)($cfg['api_version'] ?? 'v1beta'), '/');

		$max_out = (int)$max_output_override > 0 ? (int)$max_output_override : (int)(cbia_get_settings()['responses_max_output_tokens'] ?? 6000);
		if (sanitize_key((string)($context['phase'] ?? '')) === 'configuration_test') $max_out = max(16, min(64, $max_out));
		elseif ($max_out < 256) $max_out = 256;
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
			if (empty($context['ignore_stop']) && cbia_is_stop_requested()) {
				return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Stop enabled', 'cbiastudio-blogflow-ai'), []];
			}

			/* translators: 1: text model name, 2: current attempt, 3: total attempts. */
			cbia_log((sprintf(__('Google Gemini: model=%1$s attempt %2$d/%3$d', 'cbiastudio-blogflow-ai'), (string)$model, (int)$t, (int)$tries)), 'INFO');

			$started = microtime(true);
			$resp = wp_remote_post($url, [
				'headers' => [
					'Content-Type' => 'application/json',
					'x-goog-api-key' => $api_key,
				],
				'body'    => wp_json_encode($payload),
				'timeout' => sanitize_key((string)($context['phase'] ?? '')) === 'configuration_test' ? 30 : 60,
			]);
			$elapsed_ms = max(0, (int)round((microtime(true) - $started) * 1000));

			if (is_wp_error($resp)) {
				$err = (string)$resp->get_error_message();
				if (function_exists('cbia_mask_sensitive_log_text')) $err = cbia_mask_sensitive_log_text($err);
				$is_timeout = stripos($err, 'timeout') !== false || stripos($err, 'timed out') !== false || stripos($err, 'cURL error 28') !== false;
				cbia_log(("Google Gemini HTTP error: ") . $err, 'ERROR');
				$attempts[] = array('type' => 'text', 'provider' => 'google', 'model' => (string)$model, 'model_requested' => (string)$model, 'model_effective' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'status' => $is_timeout ? 'timeout' : 'error', 'timeout' => $is_timeout ? 1 : 0, 'request_sent' => 1, 'elapsed_ms' => $elapsed_ms, 'error' => $err);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code($resp);
			$request_id = sanitize_text_field((string)wp_remote_retrieve_header($resp, 'x-request-id'));
			$body = (string) wp_remote_retrieve_body($resp);
			$data = json_decode($body, true);

			if ($code < 200 || $code >= 300) {
				$msg = '';
				if (is_array($data) && !empty($data['error']['message'])) $msg = (string)$data['error']['message'];
				if (function_exists('cbia_mask_sensitive_log_text')) $msg = cbia_mask_sensitive_log_text($msg);
				$err = "HTTP {$code}" . ($msg ? " | {$msg}" : '');
				cbia_log(("Google Gemini error: {$err}"), 'ERROR');
				$attempts[] = array('type' => 'text', 'provider' => 'google', 'model' => (string)$model, 'model_requested' => (string)$model, 'model_effective' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'status' => 'error', 'http_code' => $code, 'request_id' => $request_id, 'request_sent' => 1, 'elapsed_ms' => $elapsed_ms, 'error' => (string)$err);
				continue;
			}

			if (!is_array($data)) {
				cbia_log(("Google Gemini: invalid response"), 'ERROR');
				$attempts[] = array('type' => 'text', 'provider' => 'google', 'model' => (string)$model, 'model_requested' => (string)$model, 'model_effective' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'status' => 'error', 'http_code' => $code, 'request_id' => $request_id, 'request_sent' => 1, 'elapsed_ms' => $elapsed_ms, 'error' => 'Invalid response');
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
				$attempts[] = array('type' => 'text', 'provider' => 'google', 'model' => (string)$model, 'model_requested' => (string)$model, 'model_effective' => (string)$model, 'attempt' => (int)$t, 'ok' => 0, 'status' => 'error', 'http_code' => $code, 'request_id' => $request_id, 'request_sent' => 1, 'elapsed_ms' => $elapsed_ms, 'error' => 'Response without text');
				continue;
			}

			$usage = ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0];
			if (!empty($data['usageMetadata'])) {
				$usage['input_tokens'] = (int)($data['usageMetadata']['promptTokenCount'] ?? 0);
				$usage['output_tokens'] = (int)($data['usageMetadata']['candidatesTokenCount'] ?? 0);
				$usage['total_tokens'] = (int)($data['usageMetadata']['totalTokenCount'] ?? 0);
			}

			$finish_reason = sanitize_key((string)($data['candidates'][0]['finishReason'] ?? 'stop'));
			$completion_status = cbia_normalize_chat_completion_status($finish_reason);
			$completed = $completion_status === 'complete';
			cbia_log(("Google Gemini OK: model={$model} tokens_in=") . (int)$usage['input_tokens'] . " tokens_out=" . (int)$usage['output_tokens'] . " completion_status={$completion_status}", 'INFO');
			$data['_cbia_request_meta'] = array_merge($usage, array('provider' => 'google', 'model_requested' => $model, 'model_effective' => $model, 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'attempt' => $t, 'status' => $completed ? 'success' : $completion_status, 'finish_reason' => $finish_reason, 'completion_status' => $completion_status, 'provider_status' => $finish_reason, 'provider_incomplete_reason' => $completed ? '' : $finish_reason));
			return [$completed, $text, $usage, $model, $completed ? '' : 'Incomplete response: ' . $finish_reason, cbia_attach_attempts_meta($data, $attempts)];
		}

		return [false, '', ['input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0], $model, __('Could not get a response.', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts)];
	}
}

if (!function_exists('cbia_deepseek_chat_call')) {
	/**
	 * DeepSeek V4 chat completions.
	 * Returns [ok, text, usage, effective_model, err, raw].
	 */
	function cbia_deepseek_chat_call($prompt, $system = '', $tries = 2, $max_output_override = 0, $context = array()) {
		$attempts = array();
		$context = is_array($context) ? $context : array();
		$cfg = cbia_get_provider_config('deepseek');
		$api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('deepseek') : (string)($cfg['api_key'] ?? '');
		if ($api_key === '') {
			cbia_log(__('Missing DeepSeek API key for text generation.', 'cbiastudio-blogflow-ai'), 'ERROR');
			$error = __('No API key (DeepSeek)', 'cbiastudio-blogflow-ai');
			$model = function_exists('cbia_get_text_model_for_provider') ? cbia_get_text_model_for_provider('deepseek', 'deepseek-v4-flash') : 'deepseek-v4-flash';
			return array(false, '', cbia_usage_empty(), $model, $error, cbia_local_blocked_attempt_meta('text', 'deepseek', $model, 'missing_api_key', $error));
		}

		$requested_model = !empty($context['model'])
			? sanitize_text_field((string)$context['model'])
			: (function_exists('cbia_get_text_model_for_provider')
			? cbia_get_text_model_for_provider('deepseek', 'deepseek-v4-flash')
			: cbia_get_provider_model('deepseek', 'deepseek-v4-flash'));
		$config = cbia_deepseek_get_runtime_config($requested_model);
		$thinking_override = sanitize_key((string)($context['thinking_override'] ?? ''));
		if (in_array($thinking_override, array('disabled', 'enabled'), true)) $config['thinking'] = $thinking_override;
		$effort_override = sanitize_key((string)($context['reasoning_effort_override'] ?? ''));
		if (in_array($effort_override, array('low', 'medium', 'high'), true)) $config['reasoning_effort'] = $effort_override;
		$model = (string)$config['model_effective'];
		$base_url = rtrim((string)($cfg['base_url'] ?? 'https://api.deepseek.com'), '/');
		$url = $base_url . '/chat/completions';

		$settings = cbia_get_settings();
		$max_out = (int)$max_output_override > 0 ? (int)$max_output_override : (int)($settings['responses_max_output_tokens'] ?? 6000);
		$max_out = sanitize_key((string)($context['phase'] ?? '')) === 'configuration_test' ? max(32, min(12000, $max_out)) : max(256, min(12000, $max_out));
		$messages = array();
		if ($system !== '') $messages[] = array('role' => 'system', 'content' => (string)$system);
		$messages[] = array('role' => 'user', 'content' => (string)$prompt);
		$phase = sanitize_key((string)($context['phase'] ?? ''));
		$payload = cbia_deepseek_build_payload($config, $messages, $max_out, (float)($settings['openai_temperature'] ?? 0.7), $phase === 'configuration_test' ? 32 : 256);
		$timeout = cbia_deepseek_timeout_seconds($config['thinking'], $model);
		if ($phase === 'expand') $timeout = max(150, $timeout);
		$tries = max(1, (int)$tries);
		$last_error = __('Could not get a response.', 'cbiastudio-blogflow-ai');
		$last_usage = cbia_usage_empty();
		$last_raw = array();

		for ($t = 1; $t <= $tries; $t++) {
			if (empty($context['ignore_stop']) && cbia_is_stop_requested()) {
				return array(false, '', cbia_usage_empty(), $model, __('Stop enabled', 'cbiastudio-blogflow-ai'), cbia_attach_attempts_meta(array(), $attempts));
			}

			cbia_log(sprintf('DeepSeek: model=%s thinking=%s effort=%s attempt %d/%d', $model, $config['thinking'], $config['thinking'] === 'enabled' ? $config['reasoning_effort'] : 'n/a', $t, $tries), 'INFO');
			$started = microtime(true);
			$resp = wp_remote_post($url, array(
				'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key),
				'body' => wp_json_encode($payload),
				'timeout' => $timeout,
			));
			$elapsed_ms = max(0, (int)round((microtime(true) - $started) * 1000));

			if (is_wp_error($resp)) {
				$raw_error = (string)$resp->get_error_message();
				$error = function_exists('cbia_mask_sensitive_log_text') ? cbia_mask_sensitive_log_text($raw_error) : sanitize_text_field($raw_error);
				$is_timeout = stripos($raw_error, 'timed out') !== false || stripos($raw_error, 'timeout') !== false || stripos($raw_error, 'cURL error 28') !== false;
				$last_error = $error;
				$attempts[] = array('type' => 'text', 'provider' => 'deepseek', 'model' => $model, 'model_requested' => (string)$config['model_requested'], 'model_effective' => $model, 'thinking' => (string)$config['thinking'], 'reasoning_effort' => (string)$config['reasoning_effort'], 'attempt' => $t, 'ok' => 0, 'status' => $is_timeout ? 'timeout' : 'error', 'timeout' => $is_timeout ? 1 : 0, 'elapsed_ms' => $elapsed_ms, 'error' => $error);
				cbia_log('DeepSeek HTTP error: ' . $error, 'ERROR');
				if (!$is_timeout || $t >= $tries) break;
				cbia_deepseek_wait_before_retry(array(), $t);
				continue;
			}

			$code = (int)wp_remote_retrieve_response_code($resp);
			$body = (string)wp_remote_retrieve_body($resp);
			$request_id = sanitize_text_field((string)wp_remote_retrieve_header($resp, 'x-request-id'));
			$data = json_decode($body, true);
			$usage = cbia_deepseek_parse_usage($data);
			$last_usage = $usage;

			if ($code < 200 || $code >= 300) {
				$message = is_array($data) && !empty($data['error']['message']) ? (string)$data['error']['message'] : '';
				if (function_exists('cbia_sanitize_provider_error')) {
					$message = cbia_sanitize_provider_error('deepseek', $message, $code);
				} elseif (in_array($code, array(401, 403), true)) {
					$message = 'DeepSeek authentication failed. Verify the API key in settings.';
				} elseif (function_exists('cbia_mask_sensitive_log_text')) {
					$message = cbia_mask_sensitive_log_text($message);
				}
				$last_error = 'HTTP ' . $code . ($message !== '' ? ' | ' . $message : '');
				$auth_meta = in_array($code, array(401, 402, 403), true) ? array('request_sent'=>1,'billable'=>0,'cost_micro_usd'=>0,'cost_status'=>'exact','cost_source'=>'provider_rejected_before_generation','result_status'=>$code === 402 ? 'billing_error' : 'authentication_error','error_type'=>$code === 402 ? 'billing' : 'authentication','status'=>$code === 402 ? 'billing_error' : 'authentication_error') : array();
				$attempts[] = array_merge($usage, $auth_meta, array('type' => 'text', 'provider' => 'deepseek', 'model' => $model, 'model_requested' => (string)$config['model_requested'], 'model_effective' => $model, 'thinking' => (string)$config['thinking'], 'reasoning_effort' => (string)$config['reasoning_effort'], 'attempt' => $t, 'ok' => 0, 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'error' => $last_error));
				cbia_log('DeepSeek error: ' . $last_error, 'ERROR');
				if (!cbia_deepseek_is_retryable_http_code($code) || $t >= $tries) break;
				if (!cbia_deepseek_wait_before_retry($resp, $t)) break;
				continue;
			}

			if (!is_array($data)) {
				$last_error = __('DeepSeek returned invalid JSON.', 'cbiastudio-blogflow-ai');
				$attempts[] = array('type' => 'text', 'provider' => 'deepseek', 'model' => $model, 'model_requested' => (string)$config['model_requested'], 'model_effective' => $model, 'thinking' => (string)$config['thinking'], 'reasoning_effort' => (string)$config['reasoning_effort'], 'attempt' => $t, 'ok' => 0, 'status' => 'error', 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'error' => $last_error);
				cbia_log($last_error, 'ERROR');
				break;
			}

			$text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
			$finish_reason = sanitize_key((string)($data['choices'][0]['finish_reason'] ?? ''));
			$completion_status = cbia_normalize_chat_completion_status($finish_reason);
			$reasoning_present = trim((string)($data['choices'][0]['message']['reasoning_content'] ?? '')) !== '';
			if (isset($data['choices'][0]['message']['reasoning_content'])) unset($data['choices'][0]['message']['reasoning_content']);
			if ($finish_reason === 'insufficient_system_resource') {
				$last_error = __('DeepSeek temporarily lacked resources to complete the response.', 'cbiastudio-blogflow-ai');
				$meta = array_merge($usage, array('provider' => 'deepseek', 'model_requested' => (string)$config['model_requested'], 'model_effective' => $model, 'thinking' => (string)$config['thinking'], 'reasoning_effort' => (string)$config['reasoning_effort'], 'attempt' => $t, 'ok' => 0, 'status' => 'temporary_error', 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'finish_reason' => $finish_reason, 'content_present' => $text !== '' ? 1 : 0, 'reasoning_content_present' => $reasoning_present ? 1 : 0, 'max_output_tokens' => $max_out, 'error' => $last_error));
				$attempts[] = array_merge(array('type' => 'text'), $meta);
				$data['_cbia_request_meta'] = $meta;
				$last_usage = $usage;
				$last_raw = $data;
				if ($t < $tries) {
					cbia_log('DeepSeek temporary resource limit; retrying with the same model.', 'WARN');
					cbia_deepseek_wait_before_retry($resp, $t);
					continue;
				}
				return array(false, '', $usage, $model, $last_error, cbia_attach_attempts_meta($data, $attempts));
			}
			if ($text === '') {
				$last_error = __('DeepSeek returned an empty content response.', 'cbiastudio-blogflow-ai');
				$status = $reasoning_present ? 'chat_incomplete' : 'chat_empty_content';
				$meta = array_merge($usage, array('provider' => 'deepseek', 'model_requested' => (string)$config['model_requested'], 'model_effective' => $model, 'thinking' => (string)$config['thinking'], 'reasoning_effort' => (string)$config['reasoning_effort'], 'attempt' => $t, 'ok' => 0, 'status' => $status, 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'finish_reason' => $finish_reason, 'content_present' => 0, 'reasoning_content_present' => $reasoning_present ? 1 : 0, 'max_output_tokens' => $max_out, 'error' => $last_error));
				$attempts[] = array_merge(array('type' => 'text'), $meta);
				$data['_cbia_request_meta'] = $meta;
				$last_raw = $data;
				cbia_log($last_error, 'ERROR');
				if ($phase === 'configuration_test') return array(false, '', $usage, $model, $last_error, cbia_attach_attempts_meta($data, $attempts));
				if ($t >= $tries) break;
				cbia_deepseek_wait_before_retry($resp, $t);
				continue;
			}

			$data['_cbia_request_meta'] = array_merge($usage, array('provider' => 'deepseek', 'model_requested' => (string)$config['model_requested'], 'model_effective' => $model, 'thinking' => (string)$config['thinking'], 'reasoning_effort' => (string)$config['reasoning_effort'], 'http_code' => $code, 'request_id' => $request_id, 'elapsed_ms' => $elapsed_ms, 'attempt' => $t, 'timeout' => 0, 'finish_reason' => $finish_reason, 'completion_status' => $completion_status, 'provider_status' => $finish_reason, 'provider_incomplete_reason' => $completion_status === 'complete' ? '' : $finish_reason, 'content_present' => 1, 'reasoning_content_present' => $reasoning_present ? 1 : 0, 'max_output_tokens' => $max_out));
			cbia_log(sprintf('DeepSeek OK: model=%s tokens_in=%d cache_hit=%d cache_miss=%d completion_tokens=%d reasoning_tokens=%d visible_output_tokens_estimated=%d finish_reason=%s', $model, $usage['input_tokens'], $usage['cache_hit_tokens'], $usage['cache_miss_tokens'], $usage['completion_tokens'], $usage['reasoning_tokens'], $usage['visible_output_tokens_estimated'], $finish_reason ?: 'unknown'), 'INFO');
			return array($completion_status === 'complete' || $completion_status === 'unknown', $text, $usage, $model, $completion_status === 'output_limit' ? 'Incomplete response: output limit' : '', cbia_attach_attempts_meta($data, $attempts));
		}

		return array(false, '', $last_usage, $model, $last_error, cbia_attach_attempts_meta($last_raw, $attempts));
	}
}
