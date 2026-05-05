<?php
/**
 * Post creation pipeline.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================
   ============== CREAR POST (WP) + METAS/SEO ===============
   ========================================================= */

if (!function_exists('cbia_post_exists_by_title')) {
	function cbia_post_exists_by_title($title) {
		global $wpdb;
		$title = (string)$title;
		$normalized = trim(preg_replace('/\s+/', ' ', $title));
		$slug = sanitize_title($normalized);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact title/slug collision check before insert.
		$found = $wpdb->get_var($wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status IN ('publish','future','draft','pending','private') AND (post_title=%s OR post_title=%s OR post_name=%s) LIMIT 1",
			$title,
			$normalized !== '' ? $normalized : $title,
			$slug
		));
		return !empty($found);
	}
}

if (!function_exists('cbia_count_words_from_html')) {
	function cbia_count_words_from_html($html): int {
		$plain = trim(wp_strip_all_tags((string)$html));
		if ($plain === '') return 0;
		if (!preg_match_all('/[\p{L}\p{N}]+/u', $plain, $matches)) return 0;
		return count((array)($matches[0] ?? array()));
	}
}

if (!function_exists('cbia_pick_length_target_words')) {
	function cbia_pick_length_target_words($variant, $include_faq = false): array {
		$variant = sanitize_key((string)$variant);
		if (!in_array($variant, array('short', 'medium', 'long'), true)) {
			$variant = 'medium';
		}
		if ($variant === 'short') return array(950, 1100);
		if ($variant === 'long') return array(2000, 2200);
		return array(1800, 2000);
	}
}

if (!function_exists('cbia_estimate_output_tokens_for_length_target')) {
	function cbia_estimate_output_tokens_for_length_target($min_words, $max_words, $language = '', $include_faq = false, $include_examples = false): int {
		$max_words = max((int)$max_words, (int)$min_words, 1);
		$is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish((string)$language);
		$ratio = $is_spanish ? 2.1 : 1.9;
		$base = (int)ceil($max_words * $ratio);
		$padding = 420;
		if ($include_faq) $padding += 220;
		if ($include_examples) $padding += 180;
		$estimate = $base + $padding;
		if ($estimate < 1500) $estimate = 1500;
		if ($estimate > 12000) $estimate = 12000;
		return $estimate;
	}
}

if (!function_exists('cbia_post_extract_example_topics')) {
	function cbia_post_extract_example_topics($html, $title): array {
		$topics = array();
		if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/iu', (string)$html, $matches)) {
			foreach ((array)$matches[1] as $raw_heading) {
				$heading = trim(wp_strip_all_tags((string)$raw_heading));
				if ($heading === '') continue;
				if (preg_match('/(faq|preguntas frecuentes|practical examples|ejemplos practicos)/iu', $heading)) continue;
				$topics[] = sanitize_text_field($heading);
				if (count($topics) >= 3) break;
			}
		}

		$fallback = sanitize_text_field(trim(wp_strip_all_tags((string)$title)));
		if ($fallback === '') $fallback = 'Aplicacion operativa';
		if (empty($topics)) $topics = array($fallback, $fallback, $fallback);
		while (count($topics) < 3) $topics[] = $fallback;
		return array_slice($topics, 0, 3);
	}
}

if (!function_exists('cbia_ensure_practical_examples_html')) {
	function cbia_ensure_practical_examples_html($html, $title, $language) {
		$html = (string)$html;
		$plain = strtolower(wp_strip_all_tags($html));
		$example_hits = preg_match_all('/\b(por ejemplo|ejemplo|caso practico|caso real|escenario|example|for example|use case|real-world|scenario)\b/u', $plain, $m);
		$has_examples_heading = (bool)preg_match('/<h[23][^>]*>[^<]*(ejemplos|casos practicos|practical examples|use cases)[^<]*<\/h[23]>/iu', $html);
		$has_quality_examples = (bool)preg_match('/<h3[^>]*>[^<]*(escenario|scenario)\s*[0-9][^<]*<\/h3>[\s\S]{120,}/iu', $html);
		if ($has_examples_heading && $example_hits >= 3 && $has_quality_examples) return $html;

		$is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
		$topics = cbia_post_extract_example_topics($html, $title);
		$block = '';
		if ($is_spanish) {
			$block .= "<h2>Ejemplos practicos aplicados</h2>\n";
			$block .= "<h3>Escenario 1: " . esc_html($topics[0]) . "</h3>\n";
			$block .= "<p><strong>Contexto real:</strong> se detectan senales tempranas relacionadas con este bloque antes de que escalen en coste o impacto operativo.</p>\n";
			$block .= "<p><strong>Aplicacion:</strong> se define un protocolo corto con responsables, checklist y umbral de alerta para reaccionar en menos de 24 horas.</p>\n";
			$block .= "<p><strong>Resultado medible:</strong> menor repeticion de incidencias y mejor tiempo medio de respuesta.</p>\n";
			$block .= "<h3>Escenario 2: " . esc_html($topics[1]) . "</h3>\n";
			$block .= "<p><strong>Contexto real:</strong> hay problemas recurrentes sin trazabilidad clara para priorizar acciones.</p>\n";
			$block .= "<p><strong>Aplicacion:</strong> se documenta cada caso con fecha, impacto, causa probable y accion correctiva en un tablero unico.</p>\n";
			$block .= "<p><strong>Resultado medible:</strong> decisiones mas rapidas, mejor priorizacion y menos reprocesos.</p>\n";
			$block .= "<h3>Escenario 3: " . esc_html($topics[2]) . "</h3>\n";
			$block .= "<p><strong>Contexto real:</strong> distintos equipos responden de forma desigual y aumentan errores evitables.</p>\n";
			$block .= "<p><strong>Aplicacion:</strong> se implanta una secuencia operativa de 5 pasos con verificacion cruzada y revision semanal.</p>\n";
			$block .= "<p><strong>Resultado medible:</strong> mayor consistencia, menos omisiones y mejor coordinacion.</p>\n";
		} else {
			$block .= "<h2>Practical examples</h2>\n";
			$block .= "<h3>Scenario 1: " . esc_html($topics[0]) . "</h3>\n";
			$block .= "<p><strong>Real context:</strong> early signals are detected in this area before they become expensive incidents.</p>\n";
			$block .= "<p><strong>Application:</strong> define a short response protocol with owners, checklist, and escalation thresholds within 24 hours.</p>\n";
			$block .= "<p><strong>Measurable result:</strong> fewer repeated incidents and faster response time.</p>\n";
			$block .= "<h3>Scenario 2: " . esc_html($topics[1]) . "</h3>\n";
			$block .= "<p><strong>Real context:</strong> recurring issues lack traceability and clear prioritization criteria.</p>\n";
			$block .= "<p><strong>Application:</strong> log each case with date, impact, probable cause, and corrective action in one board.</p>\n";
			$block .= "<p><strong>Measurable result:</strong> faster decisions, better prioritization, and less rework.</p>\n";
			$block .= "<h3>Scenario 3: " . esc_html($topics[2]) . "</h3>\n";
			$block .= "<p><strong>Real context:</strong> teams react inconsistently to similar situations and create avoidable mistakes.</p>\n";
			$block .= "<p><strong>Application:</strong> implement a 5-step sequence with cross-checks and weekly feedback.</p>\n";
			$block .= "<p><strong>Measurable result:</strong> stronger consistency, fewer omissions, and better coordination.</p>\n";
		}
		if (preg_match('/<h2[^>]*>\s*(FAQ|Preguntas frecuentes|Preguntas Frecuentes|Questions? ?FAQs?|FAQs)\s*<\/h2>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
			$pos = (int)$match[0][1];
			return substr($html, 0, $pos) . $block . substr($html, $pos);
		}
		return $html . "\n" . $block;
	}
}

if (!function_exists('cbia_expand_text_to_length_target')) {
	function cbia_expand_text_to_length_target($title, $html, array $settings, $min_words, $max_words) {
		$current = (string)$html;
		$language = (string)($settings['post_language'] ?? 'English');
		$is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
		$include_examples = !empty($settings['include_practical_examples']);
		$include_faq = !empty($settings['include_faq']);
		$max_tries = 1;

		for ($attempt = 1; $attempt <= $max_tries; $attempt++) {
			if (cbia_is_stop_requested()) return $current;
			$current_words = cbia_count_words_from_html($current);
			if ($current_words >= (int)$min_words) return $current;
			$missing_words = max(0, (int)$min_words - (int)$current_words);
			$target_words = max((int)$min_words, min((int)$max_words, (int)$current_words + $missing_words + 120));

			$prompt = '';
			if ($is_spanish) {
				$prompt .= "Reescribe y amplia este articulo HTML en el mismo idioma.\n";
				$prompt .= "OBJETIVO OBLIGATORIO: entre {$min_words} y {$max_words} palabras reales (minimo {$min_words}).\n";
				$prompt .= "Estado actual: {$current_words} palabras. Faltan aprox {$missing_words}. Objetivo recomendado final: {$target_words} palabras.\n";
				$prompt .= "Mantener estructura con <h2>, <h3>, <p>, <ul>, <li>. No usar <h1>, <table>, <iframe> ni <blockquote>.\n";
				$prompt .= "Conservar idea y secciones; ampliar con mas detalle practico, pasos concretos, riesgos, criterios y ejemplos aplicados.\n";
				$prompt .= "No sobrepasar {$max_words} palabras. Si dudas, prioriza quedar entre {$min_words} y {$target_words}.\n";
				if ($include_examples) {
					$prompt .= "Incluir al menos 3 escenarios practicos concretos con subtitulo <h3> (Escenario 1/2/3), cada uno con contexto, accion y resultado medible.\n";
				}
				if ($include_faq) {
					$prompt .= "Mantener FAQ al final si ya existe.\n";
				}
				$prompt .= "Devuelve SOLO HTML.\n\nHTML ACTUAL:\n" . $current;
			} else {
				$prompt .= "Rewrite and expand this HTML article in the same language.\n";
				$prompt .= "MANDATORY TARGET: between {$min_words} and {$max_words} real words (minimum {$min_words}).\n";
				$prompt .= "Current state: {$current_words} words. Missing about {$missing_words}. Recommended final target: {$target_words} words.\n";
				$prompt .= "Keep structure with <h2>, <h3>, <p>, <ul>, <li>. Do not use <h1>, <table>, <iframe>, or <blockquote>.\n";
				$prompt .= "Preserve core sections and add more practical detail, decision criteria, risks, and applied examples.\n";
				$prompt .= "Do not exceed {$max_words} words. If unsure, stay between {$min_words} and {$target_words}.\n";
				if ($include_examples) {
					$prompt .= "Include at least 3 concrete practical scenarios with <h3> subtitles (Scenario 1/2/3), each with context, action, and measurable result.\n";
				}
				if ($include_faq) {
					$prompt .= "Keep FAQ at the end if it already exists.\n";
				}
				$prompt .= "Return HTML only.\n\nCURRENT HTML:\n" . $current;
			}

			$expand_max_out = cbia_estimate_output_tokens_for_length_target((int)$min_words, (int)$max_words, $language, $include_faq, $include_examples);
			list($ok_expand, $expanded_html, $usage_expand, $model_expand, $err_expand) = cbia_openai_responses_call($prompt, $title, 1, $expand_max_out);
			if (!$ok_expand || trim((string)$expanded_html) === '') {
				cbia_log(sprintf("Length expansion failed on '%s' (attempt %d): %s", (string)$title, (int)$attempt, (string)($err_expand ?: 'unknown')), 'WARN');
				continue;
			}
			$current = cbia_fix_bracket_headings(cbia_strip_h1_to_h2(cbia_strip_document_wrappers((string)$expanded_html)));
			cbia_log(sprintf(
				"Length expansion OK on '%s' (attempt %d): %d words using model %s.",
				(string)$title,
				(int)$attempt,
				(int)cbia_count_words_from_html($current),
				(string)$model_expand
			), 'INFO');
		}

		return $current;
	}
}

if (!function_exists('cbia_create_post_in_wp_engine')) {
	/**
	 * Crea el post y asigna:
	 * - featured (si se pasa)
	 * - yoast metadesc + focuskw (básico)
	 * - categorías y tags (reglas plugin)
	 */
	function cbia_create_post_in_wp_engine($title, $final_html, $featured_attach_id, $post_date_mysql, $force_status = '') {
		$s = cbia_get_settings();

		$final_html = cbia_strip_document_wrappers($final_html);
		$final_html = cbia_strip_h1_to_h2($final_html);

		$postarr = [
			'post_type'    => 'post',
			'post_title'   => $title,
			'post_content' => $final_html,
			'post_author'  => cbia_pick_post_author_id(),
		];

		if ($force_status === 'draft') {
			$postarr['post_status'] = 'draft';
		} elseif ($force_status === 'publish') {
			$postarr['post_status'] = 'publish';
		} elseif ($force_status === 'future') {
			if (!$post_date_mysql) {
				return [false, 0, 'post_date_missing'];
			}
			$postarr['post_status']   = 'future';
			$postarr['post_date']     = $post_date_mysql;
			$postarr['post_date_gmt'] = get_gmt_from_date($post_date_mysql);
		} elseif ($post_date_mysql) {
			$postarr['post_status']   = 'future';
			$postarr['post_date']     = $post_date_mysql;
			$postarr['post_date_gmt'] = get_gmt_from_date($post_date_mysql);
		} else {
			$postarr['post_status'] = 'publish';
		}

		$post_id = wp_insert_post($postarr, true);
		if (is_wp_error($post_id) || !$post_id) {
			$err = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post_failed';
			return [false, 0, $err];
		}

		$post_id = (int)$post_id;

		// Categorías
		$cats = cbia_determine_categories_by_mapping($title, $final_html);
		if (empty($cats)) {
			$default_cat = trim((string)($s['default_category'] ?? 'News'));
			if ($default_cat !== '') $cats = [$default_cat];
		}

		$cat_ids = [];
		foreach ($cats as $c) {
			$id = cbia_ensure_category_exists($c);
			if ($id) $cat_ids[] = $id;
		}
		if (!empty($cat_ids)) {
			wp_set_post_categories($post_id, $cat_ids, false);
			update_post_meta($post_id, '_yoast_wpseo_primary_category', (int)$cat_ids[0]);
		}

		// Tags (solo permitidas)
		$tags = cbia_pick_tags_from_content_allowed($title, $final_html, 7);
		if (!empty($tags)) {
			wp_set_post_tags($post_id, $tags, false);
		}

		// Featured
		if ($featured_attach_id) {
			set_post_thumbnail($post_id, (int)$featured_attach_id);
			wp_update_post(array(
				'ID' => (int)$featured_attach_id,
				'post_parent' => (int)$post_id,
			));
		}

		// Yoast básico (luego en módulo Yoast se mejora con hook)
		$metad = cbia_generate_meta_description($title, $final_html);
		$focus = cbia_generate_focus_keyphrase($title, $final_html);
		$yoast_title = wp_strip_all_tags((string)$title);
		update_post_meta($post_id, '_yoast_wpseo_metadesc', $metad);
		update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus);
		update_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', $focus);
		update_post_meta($post_id, '_yoast_wpseo_title', $yoast_title);
		update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $yoast_title);
		update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $metad);
		update_post_meta($post_id, '_yoast_wpseo_twitter-title', $yoast_title);
		update_post_meta($post_id, '_yoast_wpseo_twitter-description', $metad);
		$extra_yoast_meta = apply_filters('cbia_yoast_extra_meta', array(), $post_id, $title, $final_html, $cat_ids);
		if (is_array($extra_yoast_meta) && !empty($extra_yoast_meta)) {
			$allowed = array(
				'_yoast_wpseo_canonical',
				'_yoast_wpseo_meta-robots-noindex',
				'_yoast_wpseo_meta-robots-nofollow',
				'_yoast_wpseo_schema_page_type',
			);
			foreach ($allowed as $meta_key) {
				if (!array_key_exists($meta_key, $extra_yoast_meta)) continue;
				update_post_meta($post_id, $meta_key, (string)$extra_yoast_meta[$meta_key]);
			}
		}

		// Marcador plugin
		update_post_meta($post_id, '_cbia_created', '1');
		update_post_meta($post_id, '_cbia_created_at', current_time('mysql'));

		return [true, $post_id, ''];
	}
}

/* =========================================================
   =============== MAIN: CREATE SINGLE BLOG POST ============
   ========================================================= */

if (!function_exists('cbia_create_single_blog_post')) {
	/**
	 * Devuelve array:
	 * ['ok'=>bool,'post_id'=>int,'error'=>string]
	 */
	function cbia_create_single_blog_post($title, $post_date_mysql = '', $force_status = '') {
		cbia_try_unlimited_runtime();
		$title = trim((string)$title);
		if ($title === '') return ['ok'=>false,'post_id'=>0,'error'=>'Empty title'];

		if (cbia_is_stop_requested()) {
			return ['ok'=>false,'post_id'=>0,'error'=>'STOP enabled'];
		}

		if (cbia_post_exists_by_title($title)) {
				cbia_log(sprintf("Post '%s' already exists. Skipped.", (string)$title), 'INFO');
			return ['ok'=>false,'post_id'=>0,'error'=>'Already exists'];
		}

		$s = cbia_get_settings();
		$images_limit = (int)($s['images_limit'] ?? 3);
		if ($images_limit < 1) $images_limit = 1;
		if ($images_limit > 4) $images_limit = 4;

		// Tracking para costes reales (texto + imágenes)
		$image_calls = array();
		$text_call = array();

		$length_variant = sanitize_key((string)($s['post_length_variant'] ?? 'medium'));
		list($min_words, $max_words) = cbia_pick_length_target_words($length_variant, !empty($s['include_faq']));

		// 1) Prompt
		$prompt = cbia_build_prompt_for_title($title);

		// 2) OpenAI texto (6 valores)
		$initial_max_out = cbia_estimate_output_tokens_for_length_target(
			(int)$min_words,
			(int)$max_words,
			(string)($s['post_language'] ?? 'English'),
			!empty($s['include_faq']),
			!empty($s['include_practical_examples'])
		);
		cbia_log(sprintf(
			"Length target for '%s': %d-%d words | max_output_tokens budget=%d.",
			(string)$title,
			(int)$min_words,
			(int)$max_words,
			(int)$initial_max_out
		), 'INFO');
		list($ok, $text_html, $usage, $model_used, $err, $raw) = cbia_openai_responses_call($prompt, $title, 2, $initial_max_out);
		$text_attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($raw) : array();
		$text_call = array(
			'context' => 'blog_text',
			'model'   => (string)$model_used,
			'usage'   => is_array($usage) ? $usage : cbia_usage_empty(),
		);
		if (!$ok) {
				cbia_log(sprintf("Text generation failed for '%s': %s", (string)$title, (string)($err ?: 'unknown')), 'ERROR');
			// Si OpenAI devolvió usage pero no hay post, deja rastro en el log de costes.
			if (function_exists('cbia_costes_log')) {
				$uin  = (int)($usage['input_tokens'] ?? 0);
				$uout = (int)($usage['output_tokens'] ?? 0);
				$umod = (string)($model_used ?? '');
				if ($uin > 0 || $uout > 0) {
					cbia_costes_log("Uso sin post (fallo texto) title='{$title}' model={$umod} in={$uin} out={$uout} err=" . (string)($err ?: ''));
				}
			}
			return ['ok'=>false,'post_id'=>0,'error'=>$err ?: 'Text generation failed'];
		}
		if (cbia_is_stop_requested()) {
				cbia_log(sprintf("STOP detected after text generation on '%s'. Post creation canceled.", (string)$title), 'INFO');
			return ['ok'=>false,'post_id'=>0,'error'=>'STOP enabled'];
		}

		$text_html = cbia_strip_document_wrappers($text_html);
		$text_html = cbia_strip_h1_to_h2($text_html);

		// Corrige encabezados escritos como [h2]...[/h2] / [h3]...[/h3] a HTML real
		$text_html = cbia_fix_bracket_headings($text_html);
		// Normaliza el título de FAQ según idioma/config
		$faq_enabled = function_exists('cbia_runtime_include_faq_enabled') ? cbia_runtime_include_faq_enabled($s) : true;
		if (!$faq_enabled && function_exists('cbia_strip_faq_section')) {
			$text_html = cbia_strip_faq_section($text_html);
				cbia_log("FAQ removed by settings (include_faq=0).", 'INFO');
		} else {
			$text_html = cbia_normalize_faq_heading($text_html);
		// Si Yoast FAQ Block está disponible, convierte FAQs a bloque
		if (function_exists('cbia_convert_faq_to_yoast_block')) {
			list($text_html, $faq_block_ok, $faq_block_status) = cbia_convert_faq_to_yoast_block($text_html);
			if ($faq_block_ok) {
					cbia_log("Yoast FAQ: block inserted successfully", 'INFO');
			} elseif (!empty($faq_block_status)) {
					cbia_log(sprintf('FAQ Yoast: %s', (string)$faq_block_status), 'INFO');
			}
		}
		}
		if (!empty($s['include_practical_examples'])) {
			$text_html = cbia_ensure_practical_examples_html($text_html, $title, (string)($s['post_language'] ?? 'English'));
		}

		$current_words = cbia_count_words_from_html($text_html);
		$effective_min_words = (int)$min_words;
		// Optimización de coste: en Medium sin FAQ aceptamos un margen corto
		// para evitar una segunda llamada cuando el texto ya es suficientemente útil.
		if ($length_variant === 'medium' && empty($s['include_faq'])) {
			$effective_min_words = max(1650, (int)$min_words - 150);
		}
		if ($current_words < $effective_min_words) {
			cbia_log(sprintf(
				"Length below target on '%s': %d words (min=%d, effective_min=%d). Expanding content...",
				(string)$title,
				(int)$current_words,
				(int)$min_words,
				(int)$effective_min_words
			), 'WARN');
			$text_html = cbia_expand_text_to_length_target($title, $text_html, $s, (int)$min_words, (int)$max_words);
			if (!empty($s['include_practical_examples'])) {
				$text_html = cbia_ensure_practical_examples_html($text_html, $title, (string)($s['post_language'] ?? 'English'));
			}
			$current_words = cbia_count_words_from_html($text_html);
			cbia_log(sprintf("Final text length on '%s': %d words.", (string)$title, (int)$current_words), 'INFO');
		} elseif ($current_words < (int)$min_words) {
			cbia_log(sprintf(
				"Length accepted without expansion on '%s': %d words (soft threshold active, nominal min=%d).",
				(string)$title,
				(int)$current_words,
				(int)$min_words
			), 'INFO');
		}

			cbia_log(sprintf("AI text OK: generated HTML for '%s'", (string)$title), 'INFO');
        // 3) Procesar marcadores de imagen
        $internal_limit = max(0, $images_limit - 1);
        if (function_exists('cbia_normalize_image_markers')) {
            $text_html = cbia_normalize_image_markers($text_html);
        }

        $markers_all = cbia_extract_image_markers($text_html);
        $total_markers = count($markers_all);
	        cbia_log(sprintf("Markers detected: %d | internal images to generate: %d", (int)$total_markers, (int)$internal_limit), 'INFO');

        if ($internal_limit <= 0) {
            if ($total_markers > 0) {
                foreach ($markers_all as $mk) {
                    $text_html = cbia_remove_marker_from_html($text_html, $mk['full']);
                }
                $text_html = cbia_cleanup_post_html($text_html);
	                cbia_log("Markers removed (internal image limit = 0).", 'INFO');
            }
            $markers = [];
        } else {
            if ($total_markers < $internal_limit) {
                $text_html = cbia_force_insert_markers($text_html, $title, $internal_limit);
                $markers_all = cbia_extract_image_markers($text_html);
                $total_markers = count($markers_all);
	                cbia_log(sprintf("Markers after auto-insert: %d", (int)$total_markers), 'INFO');
            }

            if ($total_markers > $internal_limit) {
                $extra = array_slice($markers_all, $internal_limit);
                foreach ($extra as $mk) {
                    $text_html = cbia_remove_marker_from_html($text_html, $mk['full']);
                }
                $text_html = cbia_cleanup_post_html($text_html);
	                cbia_log(sprintf("Extra markers removed: %d", (int)count($extra)), 'INFO');
            }

            $markers = cbia_extract_image_markers($text_html);
            if (!empty($markers)) $markers = array_slice($markers, 0, $internal_limit);
        }

        $pending_list = [];
        $featured_attach_id = 0;
        $img_descs = array(
            'featured' => array('desc' => '', 'section' => 'intro', 'attach_id' => 0),
            'internal' => array(),
        );

        $GLOBALS['cbia_current_post_title_for_prompt'] = $title;
		$stopped_during_post = false;

        foreach ($markers as $i => $mk) {
            if (cbia_is_stop_requested()) {
	                cbia_log(sprintf("STOP during image generation on '%s'.", (string)$title), 'INFO');
				$stopped_during_post = true;
                break;
            }

            $desc = (string)($mk['desc'] ?? '');
            $short_desc = (string)($mk['short_desc'] ?? '');
            if ($short_desc === '') {
                $short_desc = cbia_sanitize_image_short_desc($desc);
            }
            if ($short_desc === '') {
                $short_desc = $title;
	                cbia_log("Image: empty SHORT_DESC in marker, using title as fallback", 'INFO');
            }

            $section = cbia_detect_marker_section($text_html, (int)$mk['pos'], false);
            $section_label = function_exists('cbia_section_label') ? cbia_section_label($section) : (string)$section;

            $prompt = cbia_build_image_prompt_for_post(0, 'internal', $short_desc, $i + 1);
            $alt = cbia_sanitize_alt_from_desc($short_desc);
            if ($alt === '') $alt = cbia_sanitize_alt_from_desc($title);

            list($img_ok, $attach_id, $img_model, $img_err, $img_meta) = cbia_generate_image_openai_with_prompt($prompt, $section, $title, $alt, $i + 1);
			if (cbia_is_stop_requested()) {
					cbia_log(sprintf("STOP detected after image call on '%s'.", (string)$title), 'INFO');
				$stopped_during_post = true;
				break;
			}
            $image_calls[] = [
                'context' => 'blog_image',
                'section' => $section,
                'model'   => (string)$img_model,
                'ok'      => $img_ok ? 1 : 0,
                'error'   => (string)($img_err ?: ''),
                'attach_id' => (int)$attach_id,
                'attempts' => function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($img_meta) : array(),
            ];

            $img_descs['internal'][] = array(
                'desc' => $short_desc,
                'section' => (string)$section,
                'attach_id' => (int)$attach_id,
            );

            if ($img_ok && $attach_id) {
                $url = wp_get_attachment_url((int)$attach_id);
                $img_tag = cbia_build_content_img_tag_with_meta($url, $alt, $section, (int)$attach_id, $i + 1);

                $text_html = cbia_replace_first_occurrence($text_html, $mk['full'], $img_tag);
	                cbia_log(sprintf("Image inserted in content: section=%s", (string)$section_label), 'INFO');
            } else {
                $desc_clean = cbia_sanitize_alt_from_desc($short_desc);
                $pending_list[] = [
                    'desc' => $desc_clean,
                    'section' => $section,
                    'model' => (string)$img_model,
                    'status' => 'pending',
                    'tries' => 0,
                    'last_error' => (string)($img_err ?: ''),
                    'attach_id' => 0,
                ];
                $placeholder = "<span class='cbia-img-pendiente' style='display:none'>[IMAGE_PENDING: {$desc_clean}]</span>";
                $text_html = cbia_replace_first_occurrence($text_html, $mk['full'], $placeholder);
	                cbia_log(sprintf("Pending image left in content: section=%s err=%s", (string)$section_label, (string)($img_err ?: 'unknown')), 'WARN');
            }
        }
		if ($stopped_during_post || cbia_is_stop_requested()) {
				cbia_log(sprintf("STOP: post '%s' aborted before featured image and save.", (string)$title), 'INFO');
			return ['ok'=>false,'post_id'=>0,'error'=>'STOP enabled'];
		}

        // Destacada siempre
        $featured_desc = $title;
        $img_descs['featured'] = array(
            'desc' => $featured_desc,
            'section' => 'intro',
            'attach_id' => 0,
        );
        $prompt_featured = cbia_build_image_prompt_for_post(0, 'featured', $featured_desc, 0);
        $alt_featured = cbia_sanitize_alt_from_desc($featured_desc);
        if ($alt_featured === '') $alt_featured = cbia_sanitize_alt_from_desc($title);

        list($ok, $attach_id, $m, $e, $featured_meta) = cbia_generate_image_openai_with_prompt($prompt_featured, 'intro', $title, $alt_featured);
        $image_calls[] = [
            'context' => 'blog_image',
            'section' => 'intro',
            'model'   => (string)$m,
            'ok'      => $ok ? 1 : 0,
            'error'   => (string)($e ?: ''),
            'attach_id' => (int)$attach_id,
            'attempts' => function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($featured_meta) : array(),
        ];
        if ($ok && $attach_id) {
            $featured_attach_id = (int)$attach_id;
            $img_descs['featured']['attach_id'] = (int)$featured_attach_id;
	            cbia_log(sprintf("Featured image OK: attach_id=%d", (int)$featured_attach_id), 'INFO');
        } else {
	            cbia_log(sprintf("Failed to generate featured image for '%s': %s", (string)$title, (string)($e ?: '')), 'ERROR');
        }

		// Limpieza de artefactos antes de guardar
        $text_html = cbia_cleanup_post_html($text_html);

		// Crear post en WP
		list($ok_post, $post_id, $post_err) = cbia_create_post_in_wp_engine($title, $text_html, $featured_attach_id, $post_date_mysql, $force_status);
		if (!$ok_post) {
				cbia_log(sprintf("Could not create post '%s': %s", (string)$title, (string)$post_err), 'ERROR');
			return ['ok'=>false,'post_id'=>0,'error'=>$post_err ?: 'Insert failed'];
		}

		// Guardar lista de pendientes
		if (!empty($pending_list)) {
			update_post_meta($post_id, '_cbia_pending_images_list', wp_json_encode($pending_list));
			update_post_meta($post_id, '_cbia_pending_images', (string)count($pending_list));
		} else {
			update_post_meta($post_id, '_cbia_pending_images', '0');
		}

        // Guardar descripciones usadas para prompts (featured + internas)
        update_post_meta($post_id, '_cbia_img_descs', wp_json_encode($img_descs));

		// Guardar uso real (texto + imágenes) en sistema de costes
		if (function_exists('cbia_costes_record_usage')) {
			if (!empty($text_attempts) && function_exists('cbia_costes_record_failed_attempts')) {
				cbia_costes_record_failed_attempts($post_id, $text_attempts, array(
					'type' => 'text',
					'prompt' => $prompt,
				));
			}
			// Texto
			cbia_costes_record_usage($post_id, array(
				'type' => 'text',
				'model' => (string)($text_call['model'] ?? ''),
				'input_tokens' => (int)($text_call['usage']['input_tokens'] ?? 0),
				'output_tokens' => (int)($text_call['usage']['output_tokens'] ?? 0),
				'cached_input_tokens' => 0,
				'ok' => 1,
			));
			// Imágenes
			foreach ($image_calls as $ic) {
				$recorded_attempts = 0;
				if (!empty($ic['attempts']) && function_exists('cbia_costes_record_failed_attempts')) {
					$recorded_attempts = cbia_costes_record_failed_attempts($post_id, (array)$ic['attempts'], array(
						'type' => 'image',
						'prompt' => isset($ic['context']) && $ic['context'] === 'blog_image' ? $prompt : '',
						'section' => (string)($ic['section'] ?? ''),
					));
				}
				if (!empty($ic['ok']) || !$recorded_attempts) {
					cbia_costes_record_usage($post_id, array(
						'type' => 'image',
						'model' => (string)($ic['model'] ?? ''),
						'input_tokens' => 0,
						'output_tokens' => 0,
						'cached_input_tokens' => 0,
						'ok' => !empty($ic['ok']) ? 1 : 0,
						'error' => (string)($ic['error'] ?? ''),
						'section' => (string)($ic['section'] ?? ''),
						'attach_id' => (int)($ic['attach_id'] ?? 0),
					));
				}
			}
		}

		// Hook final
		do_action('cbia_after_post_created', $post_id);

		// Registro en uso legacy (no eliminar por compatibilidad)
		cbia_usage_append_call($post_id, 'blog_text', (string)$text_call['model'], (array)$text_call['usage'], [
			'ok' => 1,
			'err'=> '',
		]);
		foreach ($image_calls as $ic) {
			cbia_image_append_call($post_id, (string)($ic['section'] ?? ''), (string)($ic['model'] ?? ''), !empty($ic['ok']), (int)($ic['attach_id'] ?? 0), (string)($ic['error'] ?? ''));
		}

			cbia_log(sprintf("Post creado OK: ID %d | '%s'", (int)$post_id, (string)$title), 'INFO');

		return ['ok'=>true,'post_id'=>(int)$post_id,'error'=>''];
	}
}
