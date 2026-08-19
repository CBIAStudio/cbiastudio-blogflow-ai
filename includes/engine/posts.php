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

if (!function_exists('cbia_count_visible_words')) {
	function cbia_count_visible_words($html): int {
		$html = (string)$html;
		$html = preg_replace('/<!--[\s\S]*?-->/', ' ', $html);
		$html = preg_replace('/<(script|style|noscript)\b[^>]*>[\s\S]*?<\/\1>/iu', ' ', $html);
		$html = preg_replace('/<[^>]+(?:hidden\b|aria-hidden\s*=\s*["\']?true|style\s*=\s*["\'][^"\']*display\s*:\s*none)[^>]*>[\s\S]*?<\/[^>]+>/iu', ' ', $html);
		$html = preg_replace('/\[(?:IMAGE|IMAGEN)(?:_PENDING)?\s*:[^\]]*\]/iu', ' ', $html);
		$plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
		if ($plain === '') return 0;
		if (!preg_match_all('/[\p{L}\p{N}]+/u', $plain, $matches)) return 0;
		return count((array)($matches[0] ?? array()));
	}
}

if (!function_exists('cbia_count_words_from_html')) {
	function cbia_count_words_from_html($html): int {
		return cbia_count_visible_words($html);
	}
}

if (!function_exists('cbia_get_length_policy')) {
	function cbia_get_length_policy($variant, array $settings = array()): array {
		$variant = sanitize_key((string)$variant);
		if (!in_array($variant, array('short', 'medium', 'long'), true)) $variant = 'medium';
		$policies = array(
			'short' => array('nominal_min_words' => 950, 'nominal_max_words' => 1100, 'first_pass_preferred_min' => 1000, 'first_pass_preferred_max' => 1150, 'critical_min_words' => 650),
			'medium' => array('nominal_min_words' => 1800, 'nominal_max_words' => 2000, 'first_pass_preferred_min' => 1900, 'first_pass_preferred_max' => 2050, 'critical_min_words' => 1200),
			'long' => array('nominal_min_words' => 2000, 'nominal_max_words' => 2200, 'first_pass_preferred_min' => 2100, 'first_pass_preferred_max' => 2250, 'critical_min_words' => 1350),
		);
		$policy = $policies[$variant];
		$policy['variant'] = $variant;
		$policy['tolerance_percent'] = 20;
		$policy['effective_min_words'] = max(1, (int)floor($policy['nominal_min_words'] * 0.80));
		return (array)apply_filters('cbia_text_length_policy', $policy, $variant, $settings);
	}
}

if (!function_exists('cbia_get_soft_length_floor_words')) {
	function cbia_get_soft_length_floor_words($min_words): int {
		$min_words = (int)$min_words;
		return max(1, (int)floor($min_words * 0.80));
	}
}

if (!function_exists('cbia_get_effective_minimum_words')) {
	function cbia_get_effective_minimum_words($variant, array $settings = array()): int {
		$policy = cbia_get_length_policy($variant, $settings);
		return max(1, (int)$policy['effective_min_words']);
	}
}

if (!function_exists('cbia_get_first_pass_preferred_range')) {
	function cbia_get_first_pass_preferred_range($variant, array $settings = array()): array {
		$policy = cbia_get_length_policy($variant, $settings);
		return array((int)$policy['first_pass_preferred_min'], (int)$policy['first_pass_preferred_max']);
	}
}

if (!function_exists('cbia_validate_generated_article_html')) {
	function cbia_validate_generated_article_html($html): array {
		$html = trim((string)$html);
		$issues = array();
		if ($html === '') $issues[] = 'empty_content';
		if (preg_match('/\{\{[^}]*$|\[\[(?!cbia)[^\]]*$|\[(?:TODO|PLACEHOLDER|CONTINUE|INCOMPLETE)\b[^\]]*\]?/iu', $html)) $issues[] = 'unresolved_placeholder';
		foreach (array('h2', 'h3', 'p', 'ul', 'ol', 'li') as $tag) {
			$opens = preg_match_all('/<' . $tag . '\b[^>]*>/iu', $html, $unused);
			$closes = preg_match_all('/<\/' . $tag . '\s*>/iu', $html, $unused);
			if ($opens !== $closes) { $issues[] = 'invalid_html'; break; }
		}
		$plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
		if ($plain !== '' && preg_match('/(?:\b(?:and|or|but|because|that|with|y|o|pero|porque|que|con)|[,\-:])\s*$/iu', $plain)) $issues[] = 'abrupt_ending';
		return array('valid' => empty($issues), 'issues' => array_values(array_unique($issues)));
	}
}

if (!function_exists('cbia_decide_text_expansion')) {
	function cbia_decide_text_expansion($visible_words, array $policy, $completion_status, array $validation, $words_removed = 0): array {
		$visible_words = (int)$visible_words;
		$effective = (int)($policy['effective_min_words'] ?? 1);
		$critical = max(1, (int)($policy['critical_min_words'] ?? $effective));
		$status = sanitize_key((string)$completion_status);
		$issues = (array)($validation['issues'] ?? array());
		$reason = 'none';
		if ($status === 'output_limit') $reason = 'output_limit_reached';
		elseif ($status === 'incomplete') $reason = 'provider_incomplete';
		elseif (in_array('invalid_html', $issues, true) || in_array('empty_content', $issues, true)) $reason = 'invalid_html';
		elseif (in_array('unresolved_placeholder', $issues, true)) $reason = 'invalid_html';
		elseif (in_array('abrupt_ending', $issues, true)) $reason = 'abrupt_ending';
		elseif ($visible_words < $critical && (int)$words_removed > 0) $reason = 'content_removed_below_critical_minimum';
		elseif ($visible_words < $critical) $reason = 'below_critical_minimum';
		$required = $reason !== 'none';
		$nominal_met = $visible_words >= (int)($policy['nominal_min_words'] ?? 0);
		$accepted = !$required && $visible_words >= $critical && !in_array($status, array('content_filter', 'provider_error'), true);
		return array(
			'first_pass_nominal_target_met' => $nominal_met,
			'first_pass_accepted' => $accepted,
			'accepted_with_tolerance' => $accepted && !$nominal_met,
			'accepted_below_tolerance' => $accepted && $visible_words < $effective,
			'review_recommended' => $accepted && $visible_words < $effective,
			'expansion_required' => $required,
			'expansion_reason' => $reason,
		);
	}
}

if (!function_exists('cbia_get_effective_length_floor_words')) {
	function cbia_get_effective_length_floor_words($min_words, array $settings = array()): int {
		$soft_floor = cbia_get_soft_length_floor_words((int)$min_words);
		return max(1, (int)apply_filters('cbia_effective_length_floor_words', $soft_floor, $settings));
	}
}

if (!function_exists('cbia_truncate_html_block_to_words')) {
	function cbia_truncate_html_block_to_words($block, $max_words): string {
		$max_words = (int)$max_words;
		if ($max_words <= 0) return '';
		$plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string)$block)));
		if ($plain === '') return '';
		preg_match_all('/[\p{L}\p{N}\']+/u', $plain, $m);
		$words = (array)($m[0] ?? array());
		if (empty($words)) return '';
		if (count($words) <= $max_words) return (string)$block;
		$snippet = implode(' ', array_slice($words, 0, $max_words));
		return '<p>' . esc_html($snippet) . '...</p>';
	}
}

if (!function_exists('cbia_enforce_length_ceiling_html')) {
	function cbia_enforce_length_ceiling_html($html, $max_words, $include_faq = false): string {
		$html = (string)$html;
		$max_words = (int)$max_words;
		if ($max_words <= 0) return $html;
		$current = cbia_count_words_from_html($html);
		if ($current <= $max_words) return $html;

		$faq_pattern = '/<h2[^>]*>\s*(FAQ|Preguntas frecuentes|Preguntas Frecuentes|Frequently Asked Questions|Questions? ?FAQs?|FAQs)\s*<\/h2>/i';
		if ($include_faq && preg_match($faq_pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
			$faq_start = (int)$m[0][1];
			$before = trim((string)substr($html, 0, $faq_start));
			$faq = trim((string)substr($html, $faq_start));
			$faq_words = cbia_count_words_from_html($faq);
			if ($faq_words > 0 && $faq_words < (int)floor($max_words * 0.45)) {
				$budget_before = max(350, $max_words - $faq_words);
				$trim_before = cbia_enforce_length_ceiling_html($before, $budget_before, false);
				$merged = trim($trim_before . "\n\n" . $faq);
				if (cbia_count_words_from_html($merged) <= $max_words) return $merged;
			}
		}

		$pattern = '/(<div\b[^>]*cbia-inline-image-wrap[^>]*>[\s\S]*?<\/div>|<h[2-3]\b[^>]*>[\s\S]*?<\/h[2-3]>|<p\b[^>]*>[\s\S]*?<\/p>|<ul\b[^>]*>[\s\S]*?<\/ul>|<ol\b[^>]*>[\s\S]*?<\/ol>)/iu';
		preg_match_all($pattern, $html, $matches);
		$blocks = (array)($matches[0] ?? array());
		if (empty($blocks)) return $html;

		$kept = array();
		$count = 0;
		foreach ($blocks as $block) {
			$block = trim((string)$block);
			if ($block === '') continue;
			$words = cbia_count_words_from_html($block);
			if ($words <= 0) {
				$kept[] = $block;
				continue;
			}
			if ($count + $words <= $max_words) {
				$kept[] = $block;
				$count += $words;
				continue;
			}
			$remaining = $max_words - $count;
			if ($remaining >= 35) {
				$partial = cbia_truncate_html_block_to_words($block, $remaining);
				if ($partial !== '') $kept[] = $partial;
			}
			break;
		}

		$out = trim(implode("\n", $kept));
		return $out !== '' ? $out : $html;
	}
}

if (!function_exists('cbia_pick_length_target_words')) {
	function cbia_pick_length_target_words($variant, $include_faq = false): array {
		$policy = cbia_get_length_policy($variant, array('include_faq' => $include_faq ? 1 : 0));
		return array((int)$policy['nominal_min_words'], (int)$policy['nominal_max_words']);
	}
}

if (!function_exists('cbia_estimate_output_tokens_for_length_target')) {
	function cbia_estimate_output_tokens_for_length_target($min_words, $max_words, $language = '', $include_faq = false, $include_examples = false, $provider = '', $model = '', $thinking = ''): int {
		$max_words = max((int)$max_words, (int)$min_words, 1);
		$is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish((string)$language);
		$ratio = $is_spanish ? 2.1 : 1.9;
		$base = (int)ceil($max_words * $ratio);
		$padding = 420;
		if ($include_faq) $padding += 220;
		if ($include_examples) $padding += 180;
		$estimate = $base + $padding;
		$provider = sanitize_key((string)($provider !== '' ? $provider : (function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai')));
		$model = sanitize_text_field((string)($model !== '' ? $model : (function_exists('cbia_get_text_model_for_provider') ? cbia_get_text_model_for_provider($provider, '') : '')));
		if ($thinking === '' && $provider === 'deepseek' && function_exists('cbia_deepseek_get_runtime_config')) {
			$thinking = (string)cbia_deepseek_get_runtime_config($model)['thinking'];
		}
		if ($provider === 'deepseek' && $model === 'deepseek-v4-flash' && $thinking === 'disabled' && !$include_faq && (int)$min_words === 1800 && (int)$max_words === 2000) {
			$estimate = max($estimate, 5200);
		}
		if ($provider === 'deepseek' && $thinking === 'enabled') {
			$estimate = (int)ceil($estimate * 1.25);
		}
		if ($estimate < 1500) $estimate = 1500;
		if ($estimate > 12000) $estimate = 12000;
		return $estimate;
	}
}

if (!function_exists('cbia_resolve_text_token_budget')) {
	function cbia_resolve_text_token_budget(array $settings, $min_words, $max_words, $language = '', $include_faq = false, $include_examples = false, $provider = '', $model = '', $thinking = ''): array {
		$configured = max(256, min(12000, (int)($settings['responses_max_output_tokens'] ?? 6000)));
		$calculated = cbia_estimate_output_tokens_for_length_target($min_words, $max_words, $language, $include_faq, $include_examples, $provider, $model, $thinking);
		$effective = max($configured, $calculated);
		return array(
			'configured' => $configured,
			'calculated' => $calculated,
			'effective' => min(12000, $effective),
			'source' => $calculated > $configured ? 'calculated_length_requirement' : 'configured_limit',
			'provider' => sanitize_key((string)$provider),
			'model' => sanitize_text_field((string)$model),
			'thinking' => sanitize_key((string)$thinking),
			'faq' => $include_faq ? 1 : 0,
			'examples' => $include_examples ? 1 : 0,
		);
	}
}

if (!function_exists('cbia_post_extract_example_topics')) {
	function cbia_post_extract_example_topics($html, $title, $needed = 3): array {
		$needed = max(3, (int)$needed);
		$topics = array();
		if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/iu', (string)$html, $matches)) {
			foreach ((array)$matches[1] as $raw_heading) {
				$heading = trim(wp_strip_all_tags((string)$raw_heading));
				if ($heading === '') continue;
				if (preg_match('/(faq|preguntas frecuentes|practical examples|ejemplos practicos)/iu', $heading)) continue;
				$topics[] = sanitize_text_field($heading);
				if (count($topics) >= $needed) break;
			}
		}

		$fallback = sanitize_text_field(trim(wp_strip_all_tags((string)$title)));
		if ($fallback === '') $fallback = 'Aplicacion operativa';
		if (empty($topics)) $topics = array_fill(0, $needed, $fallback);
		while (count($topics) < $needed) $topics[] = $fallback;
		return array_slice($topics, 0, $needed);
	}
}

if (!function_exists('cbia_ensure_practical_examples_html')) {
	function cbia_ensure_practical_examples_html($html, $title, $language, $length_variant = 'medium') {
		$html = (string)$html;
		$length_variant = sanitize_key((string)$length_variant);
		$required_scenarios = ($length_variant === 'long') ? 4 : 3;
		$min_words_per_scenario = ($length_variant === 'short') ? 45 : 65;
		$plain = strtolower(wp_strip_all_tags($html));
		$example_hits = preg_match_all('/\b(por ejemplo|ejemplo|caso practico|caso real|escenario|example|for example|use case|real-world|scenario)\b/u', $plain, $m);
		$has_examples_heading = (bool)preg_match('/<h[23][^>]*>[^<]*(ejemplos|casos practicos|practical examples|use cases)[^<]*<\/h[23]>/iu', $html);
		$scenario_matches = array();
		$scenario_count = preg_match_all('/<h3[^>]*>[^<]*(escenario|scenario)\s*[0-9][^<]*<\/h3>([\s\S]*?)(?=<h3\b|<h2\b|$)/iu', $html, $scenario_matches, PREG_SET_ORDER);
		$valid_blocks = 0;
		if ($scenario_count && is_array($scenario_matches)) {
			foreach ($scenario_matches as $row) {
				$segment_plain = wp_strip_all_tags((string)($row[0] ?? ''));
				preg_match_all('/\p{L}[\p{L}\p{N}\-_]*/u', $segment_plain, $wm);
				if (count((array)($wm[0] ?? array())) >= $min_words_per_scenario) {
					$valid_blocks++;
				}
			}
		}
		if ($has_examples_heading && $example_hits >= $required_scenarios && $valid_blocks >= $required_scenarios) return $html;

		$is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
		$topics = cbia_post_extract_example_topics($html, $title, $required_scenarios);
		$block = '';
		if ($is_spanish) {
			$block .= "<h2>Ejemplos practicos aplicados</h2>\n";
			$block .= "<h3>Escenario 1: " . esc_html($topics[0]) . "</h3>\n";
			$block .= "<p><strong>Contexto real:</strong> en un evento con invitados de perfiles distintos, este punto permite detectar que parte del publico participa poco o se queda fuera de la dinamica principal.</p>\n";
			$block .= "<p><strong>Aplicacion:</strong> se prepara una accion sencilla con responsable, momento de activacion y criterio de seguimiento para que el fotomaton no sea solo decoracion, sino un recurso medible dentro de la experiencia.</p>\n";
			$block .= "<p><strong>Resultado esperado:</strong> mas participacion visible, mejores recuerdos compartidos y una lectura mas clara de que elementos funcionaron realmente durante el evento.</p>\n";
			$block .= "<h3>Escenario 2: " . esc_html($topics[1]) . "</h3>\n";
			$block .= "<p><strong>Contexto real:</strong> la organizacion quiere contenido espontaneo, pero tambien necesita mantener una estetica coherente con la boda, la marca o la celebracion.</p>\n";
			$block .= "<p><strong>Aplicacion:</strong> se define antes del evento un fondo, una iluminacion y una plantilla de entrega alineados con el estilo elegido; despues se revisan las fotos mas usadas para saber que formato genero mas respuesta.</p>\n";
			$block .= "<p><strong>Resultado esperado:</strong> imagenes mas naturales sin perder coherencia visual, mayor tasa de comparticion y menos material descartado por falta de calidad.</p>\n";
			$block .= "<h3>Escenario 3: " . esc_html($topics[2]) . "</h3>\n";
			$block .= "<p><strong>Contexto real:</strong> al finalizar el evento hay muchas fotos, pero pocas decisiones claras sobre cuales guardar, compartir o reutilizar.</p>\n";
			$block .= "<p><strong>Aplicacion:</strong> se clasifica el material por grupos, momentos y nivel de espontaneidad; las mejores piezas se usan en albumes, redes o comunicacion posterior con permiso de los participantes.</p>\n";
			$block .= "<p><strong>Resultado esperado:</strong> mejor aprovechamiento del contenido, recuerdos mas utiles para los asistentes y una vida posterior mas larga para la celebracion.</p>\n";
			if ($required_scenarios > 3) {
				$block .= "<h3>Escenario 4: " . esc_html($topics[3]) . "</h3>\n";
				$block .= "<p><strong>Contexto real:</strong> en eventos largos, la participacion baja si el fotomaton no tiene momentos de reactivacion.</p>\n";
				$block .= "<p><strong>Aplicacion:</strong> se programan pequenas llamadas a la accion en momentos concretos, como despues del banquete, durante una pausa o al inicio del baile, usando grupos diferentes cada vez.</p>\n";
				$block .= "<p><strong>Resultado esperado:</strong> flujo de uso mas constante, mas variedad de fotos y menos dependencia de un unico pico de actividad.</p>\n";
			}
		} else {
			$block .= "<h2>Practical examples</h2>\n";
			$block .= "<h3>Scenario 1: " . esc_html($topics[0]) . "</h3>\n";
			$block .= "<p><strong>Real context:</strong> an event mixes different guest profiles and some people are less active in the main flow.</p>\n";
			$block .= "<p><strong>Application:</strong> define an activation moment, one owner, and a simple participation goal so the photo booth works as a measurable experience, not just decoration.</p>\n";
			$block .= "<p><strong>Expected result:</strong> more visible participation, stronger shared memories, and a clearer view of what worked during the event.</p>\n";
			$block .= "<h3>Scenario 2: " . esc_html($topics[1]) . "</h3>\n";
			$block .= "<p><strong>Real context:</strong> the organizer wants spontaneous content without losing visual consistency.</p>\n";
			$block .= "<p><strong>Application:</strong> define backdrop, lighting, and output template before the event; afterwards, review which images were saved or shared most often.</p>\n";
			$block .= "<p><strong>Expected result:</strong> natural images with stronger visual coherence, higher sharing rate, and less unusable material.</p>\n";
			$block .= "<h3>Scenario 3: " . esc_html($topics[2]) . "</h3>\n";
			$block .= "<p><strong>Real context:</strong> after the event there are many images but no clear decision about what to save, publish, or reuse.</p>\n";
			$block .= "<p><strong>Application:</strong> classify the material by group, moment, and spontaneity level; use the strongest pieces in albums, social posts, or follow-up communication with permission.</p>\n";
			$block .= "<p><strong>Expected result:</strong> better content use, more useful memories for guests, and a longer afterlife for the event.</p>\n";
			if ($required_scenarios > 3) {
				$block .= "<h3>Scenario 4: " . esc_html($topics[3]) . "</h3>\n";
				$block .= "<p><strong>Real context:</strong> in longer events, participation drops if the photo booth has no reactivation moments.</p>\n";
				$block .= "<p><strong>Application:</strong> schedule small calls to action after dinner, during a pause, or at the start of the dance, using different groups each time.</p>\n";
				$block .= "<p><strong>Expected result:</strong> steadier usage, more varied photos, and less dependence on a single activity peak.</p>\n";
			}
		}
		if (preg_match('/<h2[^>]*>\s*(FAQ|Preguntas frecuentes|Preguntas Frecuentes|Frequently Asked Questions|Questions? ?FAQs?|FAQs)\s*<\/h2>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
			$pos = (int)$match[0][1];
			return substr($html, 0, $pos) . $block . substr($html, $pos);
		}
		return $html . "\n" . $block;
	}
}

if (!function_exists('cbia_extract_article_headings')) {
	function cbia_extract_article_headings($html): array {
		$out = array();
		if (preg_match_all('/<h[23]\b[^>]*>([\s\S]*?)<\/h[23]>/iu', (string)$html, $matches)) {
			foreach ((array)$matches[1] as $heading) {
				$heading = sanitize_text_field(trim(wp_strip_all_tags((string)$heading)));
				if ($heading !== '') $out[] = $heading;
			}
		}
		return array_slice(array_values(array_unique($out)), 0, 14);
	}
}

if (!function_exists('cbia_insert_incremental_expansion')) {
	function cbia_insert_incremental_expansion($html, $fragment): array {
		$html = trim((string)$html);
		$fragment = trim(cbia_fix_bracket_headings(cbia_strip_h1_to_h2(cbia_strip_document_wrappers((string)$fragment))));
		$validation = cbia_validate_generated_article_html($fragment);
		if ($fragment === '' || empty($validation['valid']) || preg_match('/<h1\b/iu', $fragment)) return array(false, $html, 'invalid_html');
		$lower = static function ($value) { return function_exists('mb_strtolower') ? mb_strtolower((string)$value) : strtolower((string)$value); };
		$existing = array_map($lower, cbia_extract_article_headings($html));
		foreach (cbia_extract_article_headings($fragment) as $heading) {
			if (in_array($lower($heading), $existing, true)) return array(false, $html, 'duplicate_heading');
		}
		$last_h2 = strripos($html, '<h2');
		if ($last_h2 === false) return array(false, $html, 'no_safe_location');
		$merged = trim(substr($html, 0, $last_h2)) . "\n\n" . $fragment . "\n\n" . ltrim(substr($html, $last_h2));
		return array(true, $merged, 'inserted');
	}
}

if (!function_exists('cbia_expand_text_to_length_target')) {
	function cbia_expand_text_to_length_target($title, $html, array $settings, $min_words, $max_words, &$expansion_calls = null, &$expansion_status = null, $force_completion = false, $expansion_reason = 'below_critical_minimum') {
		$current = (string)$html;
		$variant = sanitize_key((string)($settings['post_length_variant'] ?? 'medium'));
		$policy = cbia_get_length_policy($variant, $settings);
		$effective_min_words = (int)$policy['effective_min_words'];
		$critical_min_words = max(1, (int)($policy['critical_min_words'] ?? $effective_min_words));
		$current_words = cbia_count_visible_words($current);
		$deficit = max(0, $effective_min_words - $current_words);
		$language = (string)($settings['post_language'] ?? 'English');
		$is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
		$include_examples = !empty($settings['include_practical_examples']);
		$include_faq = !empty($settings['include_faq']);
		$validation = cbia_validate_generated_article_html($current);
		$h2_count = preg_match_all('/<h2\b[^>]*>/iu', $current, $unused_headings);
		$incremental = !$force_completion && !empty($validation['valid']) && $deficit > 0 && $deficit <= 600 && $h2_count >= 2;
		$type = $incremental ? 'incremental' : 'full';
		$target_words = min((int)$policy['nominal_max_words'], max($effective_min_words + 70, 1600, $current_words + $deficit + 90));
		$profile = sanitize_key((string)($settings['blog_prompt_profile'] ?? 'discover_editorial'));
		if ($profile === 'auto_by_title' && function_exists('cbia_detect_prompt_profile_from_title')) $profile = cbia_detect_prompt_profile_from_title($title);
		$headings = cbia_extract_article_headings($current);
		$prompt = '';

		if ($incremental) {
			$plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($current)));
			$opening = function_exists('mb_substr') ? mb_substr($plain, 0, 650) : substr($plain, 0, 650);
			$ending = function_exists('mb_substr') ? mb_substr($plain, -650) : substr($plain, -650);
			if ($is_spanish) {
				$prompt .= "Devuelve UNICAMENTE un fragmento HTML adicional para un articulo ya existente.\n";
				$prompt .= "Anade aproximadamente " . ($deficit + 90) . " palabras utiles para superar {$effective_min_words} y acercar el total a {$target_words}. No repitas ideas ni encabezados.\n";
				$prompt .= "Usa uno o dos apartados <h2>/<h3> con parrafos sustanciales. No incluyas introduccion, conclusion ni <h1>.\n";
			} else {
				$prompt .= "Return ONLY an additional HTML fragment for an existing article.\n";
				$prompt .= "Add about " . ($deficit + 90) . " useful words to exceed {$effective_min_words} and bring the total near {$target_words}. Do not repeat ideas or headings.\n";
				$prompt .= "Use one or two <h2>/<h3> sections with substantial paragraphs. Do not include an introduction, conclusion, or <h1>.\n";
			}
			if ($include_faq) $prompt .= $is_spanish ? "No dupliques la FAQ existente.\n" : "Do not duplicate the existing FAQ.\n";
			if ($include_examples) $prompt .= $is_spanish ? "Integra aplicaciones concretas sin duplicar el modulo practico.\n" : "Add concrete application without duplicating the practical module.\n";
			$prompt .= "PROFILE: {$profile}\nTITLE: " . sanitize_text_field((string)$title) . "\nHEADINGS: " . wp_json_encode($headings) . "\nOPENING CONTEXT: {$opening}\nENDING CONTEXT: {$ending}";
			$max_output = min(2200, max(900, (int)ceil(($deficit + 160) * 2.2)));
		} else {
			$prompt .= $is_spanish ? "Reescribe y completa este articulo HTML en el mismo idioma.\n" : "Rewrite and complete this HTML article in the same language.\n";
			$prompt .= $is_spanish
				? "Entrega un articulo completo de aproximadamente {$target_words} palabras visibles, sin relleno ni repeticiones. Conserva las ideas validas y repara cualquier final o HTML incompleto.\n"
				: "Deliver a complete article of about {$target_words} visible words, without padding or repetition. Preserve valid ideas and repair any incomplete ending or HTML.\n";
			$prompt .= $is_spanish ? "Usa <h2>, <h3>, <p>, <ul> y <li>; no uses <h1>. Devuelve solo HTML.\n" : "Use <h2>, <h3>, <p>, <ul>, and <li>; do not use <h1>. Return HTML only.\n";
			$prompt .= "PROFILE: {$profile}\nREASON: " . sanitize_key((string)$expansion_reason) . "\nCURRENT HTML:\n" . $current;
			$max_output = min(5200, max(3000, (int)ceil($target_words * 2.2)));
		}

		cbia_log(sprintf('Text expansion request: remote_text_request=2/2 type=%s reason=%s current_words=%d effective_minimum=%d deficit=%d target=%d.', $type, sanitize_key((string)$expansion_reason), $current_words, $effective_min_words, $deficit, $target_words), 'INFO');
		list($ok_expand, $expanded_html, $usage_expand, $model_expand, $err_expand, $raw_expand) = cbia_openai_responses_call($prompt, $title, 1, $max_output, array(
			'context' => 'blog_text_expand', 'phase' => 'expand', 'allow_fallback' => false,
			'strict_max_output_override' => true, 'remote_text_request' => 2, 'remote_text_request_limit' => 2,
			'defer_usage_recording' => !empty($settings['_cbia_defer_usage_recording']),
		));
		$expand_meta = is_array($raw_expand['_cbia_request_meta'] ?? null) ? $raw_expand['_cbia_request_meta'] : array();
		if (is_array($expansion_calls)) {
			$expansion_calls[] = array('context' => 'blog_text_expand', 'model' => (string)$model_expand, 'usage' => is_array($usage_expand) ? $usage_expand : cbia_usage_empty(), 'ok' => $ok_expand ? 1 : 0, 'error' => (string)($err_expand ?: ''), 'attempts' => function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($raw_expand) : array(), 'meta' => array_merge($expand_meta, array('expansion_type' => $type)), 'prompt' => $prompt);
		}
		if (!$ok_expand || trim((string)$expanded_html) === '') {
			$expansion_status = array('ok' => false, 'result' => 'failed', 'type' => $type, 'error' => (string)$err_expand);
			return $current;
		}

		if ($incremental) {
			list($inserted, $candidate, $insert_reason) = cbia_insert_incremental_expansion($current, $expanded_html);
			if (!$inserted) {
				$expansion_status = array('ok' => false, 'result' => 'invalid_html', 'type' => 'incremental', 'merge_reason' => $insert_reason);
				return $current;
			}
			$current = $candidate;
		} else {
			$current = cbia_fix_bracket_headings(cbia_strip_h1_to_h2(cbia_strip_document_wrappers((string)$expanded_html)));
		}
		$expanded_words = cbia_count_visible_words($current);
		$completion_status = sanitize_key((string)($expand_meta['completion_status'] ?? 'unknown'));
		$final_validation = cbia_validate_generated_article_html($current);
		$ok = $expanded_words >= $critical_min_words && !in_array($completion_status, array('incomplete', 'output_limit', 'content_filter', 'provider_error'), true) && !empty($final_validation['valid']);
		$expansion_status = array(
			'ok' => $ok, 'result' => $ok ? 'sufficient' : ($expanded_words < $critical_min_words ? 'still_below_critical_minimum' : 'invalid_html'),
			'type' => $type, 'words' => $expanded_words, 'effective_minimum_words' => $effective_min_words, 'critical_minimum_words' => $critical_min_words,
			'completion_status' => $completion_status,
		);
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

		$postarr = cbia_sanitize_ai_post_data([
			'post_type'    => 'post',
			'post_title'   => $title,
			'post_content' => $final_html,
			'post_author'  => cbia_pick_post_author_id(),
		]);
		$title = $postarr['post_title'];
		$final_html = $postarr['post_content'];
		$post_date_is_past = false;
		if ($post_date_mysql) {
			try {
				$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());
				$post_dt = new DateTime((string)$post_date_mysql, $tz);
				$now_dt = new DateTime(current_time('mysql'), $tz);
				$post_date_is_past = $post_dt < $now_dt;
			} catch (Exception $e) {
				$post_date_is_past = false;
			}
		}

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
			$postarr['post_status']   = $post_date_is_past ? 'publish' : 'future';
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
		if (function_exists('cbia_record_post_prompt_profile')) {
			cbia_record_post_prompt_profile($post_id, $title, (array)$s);
		}

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
		$tags = function_exists('cbia_pick_tags_for_post')
			? cbia_pick_tags_for_post($title, $final_html, 7)
			: cbia_pick_tags_from_content_allowed($title, $final_html, 7);
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

if (!function_exists('cbia_record_blog_generation_cost_rows')) {
	function cbia_record_blog_generation_cost_rows($post_id, $title, $text_prompt, array $text_call, array $text_attempts, array $image_calls, array $expansion_calls = array(), $status_reason = ''): void {
		if (!function_exists('cbia_costes_record_usage')) {
			return;
		}

		$post_id = (int)$post_id;
		$title = trim((string)$title);
		$status_reason = sanitize_text_field((string)$status_reason);
		$base_meta = array(
			'title' => $title,
			'status_reason' => $status_reason,
			'batch_id' => sanitize_text_field((string)($GLOBALS['cbia_usage_batch_id'] ?? '')),
		);

		$recorded_text_attempts = 0;
		if (!empty($text_attempts) && function_exists('cbia_costes_record_failed_attempts')) {
			$recorded_text_attempts = cbia_costes_record_failed_attempts($post_id, $text_attempts, array(
				'type' => 'text',
				'prompt' => (string)$text_prompt,
				'title' => $title,
				'context' => 'blog_text',
				'status_reason' => $status_reason,
			));
		}

		$text_usage = isset($text_call['usage']) && is_array($text_call['usage']) ? $text_call['usage'] : cbia_usage_empty();
		$text_model = (string)($text_call['model'] ?? '');
		$text_meta = isset($text_call['meta']) && is_array($text_call['meta']) ? $text_call['meta'] : array();
		if (!empty($text_call['ok']) || !$recorded_text_attempts) {
			cbia_costes_record_usage($post_id, array_merge($base_meta, $text_meta, array(
				'type' => 'text',
				'model' => $text_model,
				'input_tokens' => (int)($text_usage['input_tokens'] ?? 0),
				'output_tokens' => (int)($text_usage['output_tokens'] ?? 0),
				'cached_input_tokens' => (int)($text_usage['cached_input_tokens'] ?? 0),
				'cache_creation_input_tokens' => (int)($text_usage['cache_creation_input_tokens'] ?? 0),
				'cache_creation_5m_input_tokens' => (int)($text_usage['cache_creation_5m_input_tokens'] ?? ($text_usage['cache_creation_input_tokens'] ?? 0)),
				'cache_creation_1h_input_tokens' => (int)($text_usage['cache_creation_1h_input_tokens'] ?? 0),
				'cache_read_input_tokens' => (int)($text_usage['cache_read_input_tokens'] ?? ($text_usage['cached_input_tokens'] ?? 0)),
				'cached_tokens_reported' => !empty($text_usage['cached_tokens_reported']) ? 1 : 0,
				'ok' => !empty($text_call['ok']) ? 1 : 0,
				'error' => (string)($text_call['error'] ?? ''),
				'context' => (string)($text_call['context'] ?? 'blog_text'),
			)));
		}

		foreach ($expansion_calls as $ec) {
			if (!is_array($ec)) continue;
			$recorded_expand_attempts = 0;
			if (!empty($ec['attempts']) && function_exists('cbia_costes_record_failed_attempts')) {
				$recorded_expand_attempts = cbia_costes_record_failed_attempts($post_id, (array)$ec['attempts'], array(
					'type' => 'text',
					'prompt' => (string)($ec['prompt'] ?? ''),
					'title' => $title,
					'context' => 'blog_text_expand',
					'status_reason' => $status_reason,
				));
			}
			$usage = isset($ec['usage']) && is_array($ec['usage']) ? $ec['usage'] : cbia_usage_empty();
			$call_meta = isset($ec['meta']) && is_array($ec['meta']) ? $ec['meta'] : array();
			if (!empty($ec['ok']) || !$recorded_expand_attempts) {
				cbia_costes_record_usage($post_id, array_merge($base_meta, $call_meta, array(
					'type' => 'text',
					'model' => (string)($ec['model'] ?? ''),
					'input_tokens' => (int)($usage['input_tokens'] ?? 0),
					'output_tokens' => (int)($usage['output_tokens'] ?? 0),
					'cached_input_tokens' => (int)($usage['cached_input_tokens'] ?? 0),
					'cache_creation_input_tokens' => (int)($usage['cache_creation_input_tokens'] ?? 0),
					'cache_creation_5m_input_tokens' => (int)($usage['cache_creation_5m_input_tokens'] ?? ($usage['cache_creation_input_tokens'] ?? 0)),
					'cache_creation_1h_input_tokens' => (int)($usage['cache_creation_1h_input_tokens'] ?? 0),
					'cache_read_input_tokens' => (int)($usage['cache_read_input_tokens'] ?? ($usage['cached_input_tokens'] ?? 0)),
					'cached_tokens_reported' => !empty($usage['cached_tokens_reported']) ? 1 : 0,
					'ok' => !empty($ec['ok']) ? 1 : 0,
					'error' => (string)($ec['error'] ?? ''),
					'context' => 'blog_text_expand',
				)));
			}
		}

		foreach ($image_calls as $ic) {
			if (!is_array($ic)) continue;
			$recorded_attempts = 0;
			if (!empty($ic['attempts']) && function_exists('cbia_costes_record_failed_attempts')) {
				$recorded_attempts = cbia_costes_record_failed_attempts($post_id, (array)$ic['attempts'], array(
					'type' => 'image',
					'prompt' => (string)($ic['prompt'] ?? ''),
					'section' => (string)($ic['section'] ?? ''),
					'title' => $title,
					'context' => 'blog_image',
					'status_reason' => $status_reason,
				));
			}
			if (!empty($ic['ok']) || !$recorded_attempts) {
				$image_meta = isset($ic['meta']) && is_array($ic['meta']) ? $ic['meta'] : array();
				cbia_costes_record_usage($post_id, array_merge($base_meta, $image_meta, array(
					'type' => 'image',
					'model' => (string)($ic['model'] ?? ''),
					'input_tokens' => (int)($image_meta['input_tokens'] ?? 0),
					'output_tokens' => (int)($image_meta['output_tokens'] ?? 0),
					'cached_input_tokens' => (int)($image_meta['cached_input_tokens'] ?? 0),
					'ok' => !empty($ic['ok']) ? 1 : 0,
					'error' => (string)($ic['error'] ?? ''),
					'section' => (string)($ic['section'] ?? ''),
					'attach_id' => (int)($ic['attach_id'] ?? 0),
					'context' => 'blog_image',
				)));
			}
		}
	}
}

if (!function_exists('cbia_add_inbound_link_for_new_post')) {
	function cbia_add_inbound_link_for_new_post($post_id): bool {
		$post_id = (int)$post_id;
		if ($post_id <= 0) return false;
		$target = get_post($post_id);
		if (!$target || $target->post_type !== 'post') return false;

		$permalink = get_permalink($post_id);
		if (!$permalink) return false;
		$title = get_the_title($post_id);
		if ($title === '') return false;

		global $wpdb;
		if ($wpdb) {
			$like = '%' . $wpdb->esc_like($permalink) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fast inbound-link existence check for the newly created post.
			$existing = (int)$wpdb->get_var($wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE ID <> %d AND post_type='post' AND post_status IN ('publish','future') AND post_content LIKE %s LIMIT 1",
				$post_id,
				$like
			));
			if ($existing > 0) return false;
		}

		$cat_ids = wp_get_post_categories($post_id, array('fields' => 'ids'));
		$args = array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'post__not_in' => array($post_id),
			'orderby' => 'date',
			'order' => 'DESC',
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => false,
		);
		if (is_array($cat_ids) && !empty($cat_ids)) {
			$args['category__in'] = array_values(array_map('intval', $cat_ids));
		}
		$sources = get_posts($args);
		if (empty($sources) && !empty($args['category__in'])) {
			unset($args['category__in']);
			$sources = get_posts($args);
		}
		$source_id = !empty($sources[0]) ? (int)$sources[0] : 0;
		if ($source_id <= 0) return false;

		$source = get_post($source_id);
		if (!$source || strpos((string)$source->post_content, 'cbia-inbound-link:' . $post_id) !== false) return false;
		if (strpos((string)$source->post_content, (string)$permalink) !== false) return false;

		$s = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
		$lang = (string)($s['post_language'] ?? 'English');
		$is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($lang);
		$label = $is_spanish ? 'Tambien puede interesarte' : 'You may also like';
		$link = sprintf(
			"\n\n<p><!-- cbia-inbound-link:%d -->%s: <a href=\"%s\">%s</a>.</p>",
			(int)$post_id,
			esc_html($label),
			esc_url($permalink),
			esc_html($title)
		);
		$updated = wp_update_post(array(
			'ID' => $source_id,
			'post_content' => (string)$source->post_content . $link,
		), true);
		if (is_wp_error($updated) || !$updated) return false;
		clean_post_cache($source_id);
		if (function_exists('cbia_yoast_try_reindex_post')) {
			cbia_yoast_try_reindex_post($source_id);
			cbia_yoast_try_reindex_post($post_id);
		}
		cbia_log(sprintf('Inbound internal link added: source post %d -> target post %d.', (int)$source_id, (int)$post_id), 'INFO');
		return true;
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
		$expansion_calls = array();

		$length_variant = sanitize_key((string)($s['post_length_variant'] ?? 'medium'));
		$length_policy = cbia_get_length_policy($length_variant, $s);
		$min_words = (int)$length_policy['nominal_min_words'];
		$max_words = (int)$length_policy['nominal_max_words'];

		// 1) Prompt
		$prompt = cbia_build_prompt_for_title($title);
		$text_prompt = $prompt;

		// 2) OpenAI texto (6 valores)
		$text_provider = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai';
		$text_model = function_exists('cbia_get_text_model_for_provider') ? cbia_get_text_model_for_provider($text_provider, '') : '';
		$thinking = $text_provider === 'deepseek' && function_exists('cbia_deepseek_get_runtime_config') ? (string)cbia_deepseek_get_runtime_config($text_model)['thinking'] : '';
		$initial_budget = cbia_resolve_text_token_budget($s,
			(int)$min_words,
			(int)$max_words,
			(string)($s['post_language'] ?? 'English'),
			!empty($s['include_faq']),
			!empty($s['include_practical_examples']),
			$text_provider,
			$text_model,
			$thinking
		);
		$initial_max_out = (int)$initial_budget['effective'];
		cbia_log(sprintf(
			'Text length policy: length=%s nominal_range=%d-%d tolerance_minimum=%d critical_minimum=%d tolerance_percent=%d preferred_first_pass_range=%d-%d provider=%s model=%s faq_enabled=%s examples_enabled=%s.',
			$length_variant, $min_words, $max_words, (int)$length_policy['effective_min_words'], (int)$length_policy['critical_min_words'], (int)$length_policy['tolerance_percent'],
			(int)$length_policy['first_pass_preferred_min'], (int)$length_policy['first_pass_preferred_max'],
			(string)$text_provider, (string)$text_model, !empty($s['include_faq']) ? 'yes' : 'no', !empty($s['include_practical_examples']) ? 'yes' : 'no'
		), 'INFO');
		cbia_log(sprintf('Text token budget: configured=%d recommended_budget=%d effective=%d source=%s.', (int)$initial_budget['configured'], (int)$initial_budget['calculated'], (int)$initial_budget['effective'], (string)$initial_budget['source']), 'INFO');
		cbia_log('Text generation request: remote_text_request=1/2 phase=initial.', 'INFO');
		list($ok, $text_html, $usage, $model_used, $err, $raw) = cbia_openai_responses_call($prompt, $title, 1, $initial_max_out, array(
			'context' => 'blog_text', 'phase' => 'initial', 'allow_fallback' => false,
			'remote_text_request' => 1, 'remote_text_request_limit' => 2, 'defer_usage_recording' => true,
		));
		$text_attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($raw) : array();
		$text_call = array(
			'context' => 'blog_text',
			'model'   => (string)$model_used,
			'usage'   => is_array($usage) ? $usage : cbia_usage_empty(),
			'ok'      => $ok ? 1 : 0,
			'error'   => (string)($err ?: ''),
			'meta'    => is_array($raw['_cbia_request_meta'] ?? null) ? $raw['_cbia_request_meta'] : array(),
		);
		$initial_meta = (array)$text_call['meta'];
		$completion_status = sanitize_key((string)($initial_meta['completion_status'] ?? 'unknown'));
		$provider_status = sanitize_key((string)($initial_meta['provider_status'] ?? ''));
		$provider_incomplete_reason = sanitize_key((string)($initial_meta['provider_incomplete_reason'] ?? ''));
		if (in_array($completion_status, array('content_filter', 'provider_error'), true)) {
			cbia_log(sprintf("Text generation stopped for '%s': completion_status=%s. No images will be generated.", (string)$title, $completion_status), 'ERROR');
			cbia_record_blog_generation_cost_rows(0, $title, $text_prompt, $text_call, $text_attempts, $image_calls, $expansion_calls, $completion_status);
			return array('ok'=>false,'post_id'=>0,'error'=>'Provider completion status: ' . $completion_status);
		}
		if (!$ok && trim((string)$text_html) === '') {
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
			cbia_record_blog_generation_cost_rows(0, $title, $text_prompt, $text_call, $text_attempts, $image_calls, $expansion_calls, 'text_generation_failed');
			return ['ok'=>false,'post_id'=>0,'error'=>$err ?: 'Text generation failed'];
		}
		if (cbia_is_stop_requested()) {
				cbia_log(sprintf("STOP detected after text generation on '%s'. Post creation canceled.", (string)$title), 'INFO');
			cbia_record_blog_generation_cost_rows(0, $title, $text_prompt, $text_call, $text_attempts, $image_calls, $expansion_calls, 'stopped_after_text');
			return ['ok'=>false,'post_id'=>0,'error'=>'STOP enabled'];
		}

		$text_html = cbia_strip_document_wrappers($text_html);
		$text_html = cbia_strip_h1_to_h2($text_html);

		// Corrige encabezados escritos como [h2]...[/h2] / [h3]...[/h3] a HTML real
		$text_html = cbia_fix_bracket_headings($text_html);
		// Normaliza el título de FAQ según idioma/config
		$faq_enabled = function_exists('cbia_runtime_include_faq_enabled') ? cbia_runtime_include_faq_enabled($s) : true;
		$words_before_faq_cleanup = cbia_count_words_from_html($text_html);
		$words_after_faq_cleanup = $words_before_faq_cleanup;
		if (!$faq_enabled && function_exists('cbia_strip_faq_section')) {
			$before_faq_html = $text_html;
			$text_html = cbia_strip_faq_section($text_html);
			$words_after_faq_cleanup = cbia_count_words_from_html($text_html);
			$faq_was_removed = trim((string)$before_faq_html) !== trim((string)$text_html);
			cbia_log(sprintf(
				'FAQ cleanup by settings: detected=%s words_before=%d words_after=%d words_removed=%d.',
				$faq_was_removed ? 'yes' : 'no',
				(int)$words_before_faq_cleanup,
				(int)$words_after_faq_cleanup,
				max(0, (int)$words_before_faq_cleanup - (int)$words_after_faq_cleanup)
			), 'INFO');
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
		$examples_words_removed = 0;
		if (!empty($s['include_practical_examples'])) {
			$text_html = cbia_ensure_practical_examples_html($text_html, $title, (string)($s['post_language'] ?? 'English'), $length_variant);
		} elseif (function_exists('cbia_strip_practical_examples_section')) {
			$before_examples = $text_html;
			$before_examples_words = cbia_count_words_from_html($before_examples);
			$text_html = cbia_strip_practical_examples_section($text_html);
			$after_examples_words = cbia_count_words_from_html($text_html);
			$examples_words_removed = max(0, $before_examples_words - $after_examples_words);
			cbia_log(sprintf('Practical examples cleanup: enabled=no module_detected=%s words_before=%d words_after=%d words_removed=%d.', trim($before_examples) !== trim($text_html) ? 'yes' : 'no', $before_examples_words, $after_examples_words, max(0, $before_examples_words - $after_examples_words)), 'INFO');
		}

		$current_words = cbia_count_visible_words($text_html);
		$first_pass_words = (int)$current_words;
		$effective_min_words = (int)$length_policy['effective_min_words'];
		$critical_min_words = max(1, (int)($length_policy['critical_min_words'] ?? $effective_min_words));
		$faq_words_removed = max(0, (int)$words_before_faq_cleanup - (int)$words_after_faq_cleanup);
		$content_words_removed = $faq_words_removed + (int)$examples_words_removed;
		$first_validation = cbia_validate_generated_article_html($text_html);
		$first_decision = cbia_decide_text_expansion($first_pass_words, $length_policy, $completion_status, $first_validation, $content_words_removed);
		$first_pass_success = !empty($first_decision['first_pass_accepted']);
		$first_pass_nominal_target_met = !empty($first_decision['first_pass_nominal_target_met']);
		$accepted_with_tolerance = !empty($first_decision['accepted_with_tolerance']);
		$accepted_below_tolerance = !empty($first_decision['accepted_below_tolerance']);
		$expansion_required = !empty($first_decision['expansion_required']);
		$expansion_reason = sanitize_key((string)$first_decision['expansion_reason']);
		$expansion_result = 'not_used';
		$expansion_type = 'none';
		$expansion_used = false;
		$words_missing = max(0, $effective_min_words - $first_pass_words);
		$expansion_failed = false;
		if ($expansion_required) {
			$expansion_used = true;
			$expansion_status = array();
			$s['_cbia_defer_usage_recording'] = 1;
			$text_html = cbia_expand_text_to_length_target($title, $text_html, $s, $min_words, $max_words, $expansion_calls, $expansion_status, in_array($completion_status, array('incomplete', 'output_limit'), true), $expansion_reason);
			$expansion_result = sanitize_key((string)($expansion_status['result'] ?? 'failed'));
			$expansion_type = sanitize_key((string)($expansion_status['type'] ?? 'full'));
			$expansion_failed = empty($expansion_status['ok']);
			if (!empty($s['include_practical_examples'])) {
				$text_html = cbia_ensure_practical_examples_html($text_html, $title, (string)($s['post_language'] ?? 'English'), $length_variant);
			} elseif (function_exists('cbia_strip_practical_examples_section')) {
				$text_html = cbia_strip_practical_examples_section($text_html);
			}
		} elseif ($current_words < $min_words) {
			cbia_log(sprintf(
				"Length accepted without expansion on '%s': %d words (critical minimum=%d, tolerance minimum=%d, nominal minimum=%d, review_recommended=%s).",
				(string)$title,
				(int)$current_words,
				(int)$critical_min_words,
				(int)$effective_min_words,
				(int)$min_words,
				$current_words < $effective_min_words ? 'yes' : 'no'
			), 'INFO');
		}
		$text_provider = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai';
		cbia_log(sprintf(
			'Text first-pass result: provider=%s model=%s visible_words=%d nominal_minimum=%d effective_minimum=%d nominal_target_met=%s accepted=%s accepted_with_tolerance=%s completion_status=%s provider_status=%s provider_incomplete_reason=%s tokens_output=%d reasoning_tokens=%d faq_words_removed=%d examples_words_removed=%d expansion_required=%s expansion_reason=%s.',
			(string)$text_provider,
			(string)$model_used,
			(int)$first_pass_words,
			(int)$min_words,
			(int)$effective_min_words,
			$first_pass_nominal_target_met ? 'yes' : 'no', $first_pass_success ? 'yes' : 'no', $accepted_with_tolerance ? 'yes' : 'no',
			(string)$completion_status, (string)($provider_status ?: 'unknown'), (string)($provider_incomplete_reason ?: 'none'),
			(int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0), (int)($usage['reasoning_tokens'] ?? 0),
			(int)$faq_words_removed, (int)$examples_words_removed, $expansion_required ? 'yes' : 'no', (string)$expansion_reason
		), 'INFO');
		$text_call['meta'] = array_merge((array)($text_call['meta'] ?? array()), array(
			'provider' => sanitize_key((string)$text_provider),
			'first_pass_success' => $first_pass_success ? 1 : 0,
			'first_pass_words' => (int)$first_pass_words,
			'first_pass_nominal_target_met' => $first_pass_nominal_target_met ? 1 : 0,
			'accepted_with_tolerance' => $accepted_with_tolerance ? 1 : 0,
			'accepted_below_tolerance' => $accepted_below_tolerance ? 1 : 0,
			'length_review_recommended' => !empty($first_decision['review_recommended']) ? 1 : 0,
			'expansion_used' => $expansion_used ? 1 : 0,
			'expansion_required' => $expansion_required ? 1 : 0,
			'final_words_before_expansion' => (int)$first_pass_words,
			'words_missing' => (int)$words_missing,
			'expansion_reason' => $expansion_reason,
			'expansion_result' => $expansion_result,
			'expansion_type' => $expansion_type,
			'faq_enabled' => $faq_enabled ? 1 : 0,
			'completion_status' => (string)$completion_status,
			'provider_status' => (string)$provider_status,
			'provider_incomplete_reason' => (string)$provider_incomplete_reason,
			'completion_tokens' => (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0),
			'reasoning_tokens' => (int)($usage['reasoning_tokens'] ?? 0),
			'visible_output_tokens_estimated' => (int)($usage['visible_output_tokens_estimated'] ?? $usage['output_tokens'] ?? 0),
			'faq_words_removed' => (int)$faq_words_removed,
			'examples_words_removed' => (int)$examples_words_removed,
		));
		$text_html = cbia_enforce_length_ceiling_html($text_html, (int)$max_words, !empty($s['include_faq']));
		if (!empty($s['include_practical_examples'])) {
			$text_html = cbia_ensure_practical_examples_html($text_html, $title, (string)($s['post_language'] ?? 'English'), $length_variant);
			$examples_ceiling = (int)$max_words + (($length_variant === 'long') ? 320 : 240);
			if (cbia_count_words_from_html($text_html) > $examples_ceiling) {
				$text_html = cbia_enforce_length_ceiling_html($text_html, $examples_ceiling, !empty($s['include_faq']));
				$text_html = cbia_ensure_practical_examples_html($text_html, $title, (string)($s['post_language'] ?? 'English'), $length_variant);
			}
		}
		$current_words = cbia_count_words_from_html($text_html);
		cbia_log(sprintf("Final text length on '%s': %d words.", (string)$title, (int)$current_words), 'INFO');
		$final_validation = cbia_validate_generated_article_html($text_html);
		if ($current_words < $critical_min_words || $expansion_failed || empty($final_validation['valid'])) {
			list($draft_ok, $draft_id, $draft_error) = cbia_create_post_in_wp_engine($title, $text_html, 0, $post_date_mysql, 'draft');
			$review_reason = $expansion_failed ? 'needs_manual_review_expansion' : (empty($final_validation['valid']) ? 'needs_manual_review_html' : 'needs_manual_review_length');
			cbia_record_blog_generation_cost_rows($draft_ok ? (int)$draft_id : 0, $title, $text_prompt, $text_call, $text_attempts, $image_calls, $expansion_calls, $review_reason);
			cbia_log(sprintf("Text rejected before images on '%s': %d words, critical minimum %d, tolerance minimum %d (nominal %d). Draft=%d.", (string)$title, (int)$current_words, (int)$critical_min_words, (int)$effective_min_words, (int)$min_words, (int)$draft_id), 'ERROR');
			return array('ok' => false, 'post_id' => $draft_ok ? (int)$draft_id : 0, 'error' => $draft_ok ? $review_reason : ($draft_error ?: 'insufficient_length'));
		}
		$final_words = (int)$current_words;
		$final_nominal_target_met = $final_words >= $min_words;
		$final_accepted_with_tolerance = !$final_nominal_target_met && $final_words >= $effective_min_words;
		$final_accepted_below_tolerance = $final_words >= $critical_min_words && $final_words < $effective_min_words;
		$text_call['meta']['final_words'] = $final_words;
		$text_call['meta']['final_nominal_target_met'] = $final_nominal_target_met ? 1 : 0;
		$text_call['meta']['final_accepted_with_tolerance'] = $final_accepted_with_tolerance ? 1 : 0;
		$text_call['meta']['final_accepted_below_tolerance'] = $final_accepted_below_tolerance ? 1 : 0;
		$text_call['meta']['total_remote_text_requests'] = 1 + count($expansion_calls);
		cbia_log(sprintf('Text final result: final_words=%d accepted=yes nominal_target_met=%s accepted_with_tolerance=%s accepted_below_tolerance=%s review_recommended=%s expansion_used=%s expansion_type=%s expansion_reason=%s expansion_result=%s total_remote_text_requests=%d.', $final_words, $final_nominal_target_met ? 'yes' : 'no', $final_accepted_with_tolerance ? 'yes' : 'no', $final_accepted_below_tolerance ? 'yes' : 'no', $final_accepted_below_tolerance ? 'yes' : 'no', $expansion_used ? 'yes' : 'no', $expansion_type, $expansion_reason, $expansion_result, 1 + count($expansion_calls)), 'INFO');

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

            if (function_exists('cbia_rebalance_internal_image_markers')) {
                $text_html = cbia_rebalance_internal_image_markers($text_html, $title, $internal_limit);
                cbia_log(sprintf("Markers rebalanced for internal slots: %d", (int)$internal_limit), 'INFO');
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
				'meta' => is_array($img_meta) ? $img_meta : array(),
                'prompt' => $prompt,
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
			cbia_record_blog_generation_cost_rows(0, $title, $text_prompt, $text_call, $text_attempts, $image_calls, $expansion_calls, 'stopped_before_post_save');
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
			'meta' => is_array($featured_meta) ? $featured_meta : array(),
            'prompt' => $prompt_featured,
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
			cbia_record_blog_generation_cost_rows(0, $title, $text_prompt, $text_call, $text_attempts, $image_calls, $expansion_calls, 'post_insert_failed');
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

		// Guardar uso real (texto + imagenes) en sistema de costes
		cbia_record_blog_generation_cost_rows($post_id, $title, $text_prompt, $text_call, $text_attempts, $image_calls, $expansion_calls, 'post_created');

		if (function_exists('cbia_add_inbound_link_for_new_post')) {
			cbia_add_inbound_link_for_new_post((int)$post_id);
		}

		// Hook final
		do_action('cbia_after_post_created', $post_id);
		$initial_cost_row = function_exists('cbia_costes_calculate_row') ? cbia_costes_calculate_row(array_merge((array)$usage, array('type' => 'text', 'provider' => $text_provider, 'model' => $model_used, 'ok' => 1, 'request_sent' => 1))) : array('cost_micro_usd' => null);
		$expansion_usage = !empty($expansion_calls[0]['usage']) && is_array($expansion_calls[0]['usage']) ? $expansion_calls[0]['usage'] : array();
		$expansion_cost_row = $expansion_used && function_exists('cbia_costes_calculate_row') ? cbia_costes_calculate_row(array_merge($expansion_usage, array('type' => 'text', 'provider' => $text_provider, 'model' => (string)($expansion_calls[0]['model'] ?? $model_used), 'ok' => !empty($expansion_calls[0]['ok']) ? 1 : 0, 'request_sent' => 1))) : array('cost_micro_usd' => 0);
		$initial_cost_micro = is_numeric($initial_cost_row['cost_micro_usd'] ?? null) ? (int)$initial_cost_row['cost_micro_usd'] : 0;
		$expansion_cost_micro = is_numeric($expansion_cost_row['cost_micro_usd'] ?? null) ? (int)$expansion_cost_row['cost_micro_usd'] : 0;

		// Registro en uso legacy (no eliminar por compatibilidad)
		cbia_usage_append_call($post_id, 'blog_text', (string)$text_call['model'], (array)$text_call['usage'], [
			'ok' => 1,
			'err'=> '',
		]);
		foreach ($image_calls as $ic) {
			cbia_image_append_call($post_id, (string)($ic['section'] ?? ''), (string)($ic['model'] ?? ''), !empty($ic['ok']), (int)($ic['attach_id'] ?? 0), (string)($ic['error'] ?? ''));
		}

			cbia_log(sprintf("Post creado OK: ID %d | '%s'", (int)$post_id, (string)$title), 'INFO');

		return ['ok'=>true,'post_id'=>(int)$post_id,'error'=>'','efficiency'=>array(
			'first_pass_words'=>(int)$first_pass_words,
			'final_words'=>(int)$final_words,
			'first_pass_accepted'=>$first_pass_success ? 1 : 0,
			'first_pass_nominal_target_met'=>$first_pass_nominal_target_met ? 1 : 0,
			'accepted_with_tolerance'=>$accepted_with_tolerance ? 1 : 0,
			'expansion_used'=>$expansion_used ? 1 : 0,
			'expansion_reason'=>(string)$expansion_reason,
			'expansion_result'=>(string)$expansion_result,
			'expansion_type'=>(string)$expansion_type,
			'failed_length'=>0,
			'initial_tokens_input'=>(int)($usage['input_tokens'] ?? 0),
			'initial_tokens_output'=>(int)($usage['output_tokens'] ?? 0),
			'expansion_tokens_input'=>!empty($expansion_calls[0]['usage']) ? (int)($expansion_calls[0]['usage']['input_tokens'] ?? 0) : 0,
			'expansion_tokens_output'=>!empty($expansion_calls[0]['usage']) ? (int)($expansion_calls[0]['usage']['output_tokens'] ?? 0) : 0,
			'initial_request_cost_micro_usd'=>$initial_cost_micro,
			'expansion_cost_micro_usd'=>$expansion_cost_micro,
			'total_text_cost_micro_usd'=>$initial_cost_micro + $expansion_cost_micro,
			'reasoning_tokens'=>(int)($usage['reasoning_tokens'] ?? 0),
			'completion_status'=>(string)$completion_status,
			'hit_token_limit'=>$completion_status === 'output_limit' ? 1 : 0,
			'total_remote_text_requests'=>1 + count($expansion_calls),
			'thinking'=>(string)($thinking ?: 'n/a'),
		)];
	}
}
