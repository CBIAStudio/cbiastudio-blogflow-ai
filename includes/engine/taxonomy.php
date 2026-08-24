<?php
/**
 * Categories and tags helpers.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_normalize_for_match')) {
	function cbia_normalize_for_match($str) {
		$str = strtr((string)$str, [
			'‘' => "'", '’' => "'", 'ʼ' => "'", '＇' => "'",
			'‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-', '−' => '-',
		]);
		$str = remove_accents($str);
		$str = mb_strtolower($str);
		return $str;
	}
}

if (!function_exists('cbia_category_match_pattern')) {
	function cbia_category_match_pattern($normalized_keyword) {
		$normalized_keyword = trim((string)$normalized_keyword);
		if ($normalized_keyword === '') return '';
		return '/(?<![\\p{L}\\p{N}])' . preg_quote($normalized_keyword, '/') . '(?![\\p{L}\\p{N}])/iu';
	}
}

if (!function_exists('cbia_category_comparable_score_margin')) {
	function cbia_category_comparable_score_margin() {
		// Within this band the administrator's mapping order is the deterministic tiebreaker.
		return 15;
	}
}

if (!function_exists('cbia_category_title_entity_signal')) {
	function cbia_category_title_entity_signal($normalized_keyword, $plain_title) {
		preg_match_all('/[\\p{L}\\p{N}]+/u', (string)$normalized_keyword, $keyword_matches);
		preg_match_all('/[\\p{L}\\p{N}]+/u', (string)$plain_title, $title_matches);
		$keyword_tokens = $keyword_matches[0] ?? [];
		$title_tokens = $title_matches[0] ?? [];
		$keyword_count = count($keyword_tokens);

		if ($keyword_count < 1 || count($title_tokens) < $keyword_count) return false;

		$normalized_title_tokens = array_map('cbia_normalize_for_match', $title_tokens);
		for ($offset = 0, $limit = count($title_tokens) - $keyword_count; $offset <= $limit; $offset++) {
			$matches = true;
			for ($index = 0; $index < $keyword_count; $index++) {
				if ($normalized_title_tokens[$offset + $index] !== $keyword_tokens[$index]) {
					$matches = false;
					break;
				}
			}
			if (!$matches) continue;

			for ($index = 0; $index < $keyword_count; $index++) {
				$absolute_index = $offset + $index;
				if ($absolute_index > 0 && preg_match('/^\\p{Lu}/u', $title_tokens[$absolute_index])) return true;
			}
		}

		return false;
	}
}

if (!function_exists('cbia_category_keyword_score')) {
	function cbia_category_keyword_score($keyword, $plain_title, $normalized_title, $normalized_excerpt, $normalized_content) {
		$normalized_keyword = cbia_normalize_for_match(trim((string)$keyword));
		$pattern = cbia_category_match_pattern($normalized_keyword);
		if ($pattern === '') return 0;

		$score = 0;
		$title_match = [];
		if (preg_match($pattern, $normalized_title, $title_match, PREG_OFFSET_CAPTURE)) {
			$score += 100; // Exact title phrase: strongest base signal.
			$title_length = max(1, mb_strlen($normalized_title));
			$title_offset = mb_strlen(substr($normalized_title, 0, (int)$title_match[0][1]));
			$score += (int)round(25 * (1 - min(1, $title_offset / $title_length)));

			preg_match_all('/[\\p{L}\\p{N}]+/u', $normalized_keyword, $keyword_words);
			if (count($keyword_words[0] ?? []) > 1) $score += 25;
			if (cbia_category_title_entity_signal($normalized_keyword, $plain_title)) $score += 85;
		}

		$excerpt_count = preg_match_all($pattern, $normalized_excerpt, $unused_excerpt_matches);
		if ($excerpt_count > 0) $score += 18 + (4 * min(2, $excerpt_count));

		$content_count = preg_match_all($pattern, $normalized_content, $unused_content_matches);
		if ($content_count > 0) $score += 10 + (4 * min(3, $content_count));

		return $score;
	}
}

if (!function_exists('cbia_rank_category_candidates')) {
	function cbia_rank_category_candidates($candidates, $comparable_margin = 15) {
		$comparable_margin = max(0, (int)$comparable_margin);
		usort($candidates, function($left, $right) {
			$score_compare = ((int)$right['score']) <=> ((int)$left['score']);
			if ($score_compare !== 0) return $score_compare;
			$left_priority = isset($left['priority']) ? (int)$left['priority'] : PHP_INT_MAX;
			$right_priority = isset($right['priority']) ? (int)$right['priority'] : PHP_INT_MAX;
			if ($left_priority !== $right_priority) return $left_priority <=> $right_priority;
			return strcmp((string)$left['normalized_name'], (string)$right['normalized_name']);
		});

		$ranked = [];
		$total = count($candidates);
		$cursor = 0;
		while ($cursor < $total) {
			$band_top_score = (int)$candidates[$cursor]['score'];
			$band = [];
			while ($cursor < $total && ($band_top_score - (int)$candidates[$cursor]['score']) <= $comparable_margin) {
				$band[] = $candidates[$cursor];
				$cursor++;
			}
			usort($band, function($left, $right) {
				$left_priority = isset($left['priority']) ? (int)$left['priority'] : PHP_INT_MAX;
				$right_priority = isset($right['priority']) ? (int)$right['priority'] : PHP_INT_MAX;
				if ($left_priority !== $right_priority) return $left_priority <=> $right_priority;
				$score_compare = ((int)$right['score']) <=> ((int)$left['score']);
				if ($score_compare !== 0) return $score_compare;
				return strcmp((string)$left['normalized_name'], (string)$right['normalized_name']);
			});
			$ranked = array_merge($ranked, $band);
		}

		return $ranked;
	}
}

if (!function_exists('cbia_rank_categories_by_mapping')) {
	function cbia_rank_categories_by_mapping($mapping, $title, $content_html, $max_categories = 3) {
		$plain_title = wp_strip_all_tags((string)$title);
		$plain_content = mb_substr(wp_strip_all_tags((string)$content_html), 0, 4000);
		$normalized_title = cbia_normalize_for_match($plain_title);
		$normalized_content = cbia_normalize_for_match($plain_content);
		$normalized_excerpt = mb_substr($normalized_content, 0, 800);
		$lines = array_values(array_filter(array_map('trim', explode("\n", (string)$mapping))));
		$category_map = [];

		foreach ($lines as $priority => $line) {
			$parts = explode(':', $line, 2);
			if (count($parts) !== 2) continue;
			$category_name = trim($parts[0]);
			$normalized_name = cbia_normalize_for_match($category_name);
			if ($normalized_name === '') continue;

			if (!isset($category_map[$normalized_name])) {
				$category_map[$normalized_name] = [
					'name' => $category_name,
					'normalized_name' => $normalized_name,
					'priority' => $priority,
					'keywords' => [],
				];
			}

			$keywords = array_filter(array_map('trim', explode(',', $parts[1])));
			foreach ($keywords as $keyword) {
				$normalized_keyword = cbia_normalize_for_match($keyword);
				if ($normalized_keyword === '' || isset($category_map[$normalized_name]['keywords'][$normalized_keyword])) continue;
				// Keep matching work bounded even when an imported mapping is unexpectedly large.
				if (count($category_map[$normalized_name]['keywords']) >= 50) break;
				$category_map[$normalized_name]['keywords'][$normalized_keyword] = $keyword;
			}
		}

		$candidates = [];
		foreach ($category_map as $category) {
			$keyword_scores = [];
			foreach ($category['keywords'] as $keyword) {
				$keyword_score = cbia_category_keyword_score($keyword, $plain_title, $normalized_title, $normalized_excerpt, $normalized_content);
				if ($keyword_score > 0) $keyword_scores[] = $keyword_score;
			}
			if (empty($keyword_scores)) continue;
			rsort($keyword_scores, SORT_NUMERIC);
			$category['score'] = (int)$keyword_scores[0];
			if (isset($keyword_scores[1])) $category['score'] += (int)round($keyword_scores[1] * 0.25);
			unset($category['keywords']);
			$candidates[] = $category;
		}

		$ranked = cbia_rank_category_candidates($candidates, cbia_category_comparable_score_margin());
		$names = array_map(function($candidate) { return $candidate['name']; }, $ranked);
		return array_slice($names, 0, max(1, (int)$max_categories));
	}
}

if (!function_exists('cbia_slugify')) {
	function cbia_slugify($text) {
		$text = remove_accents((string)$text);
		$text = strtolower($text);
		$text = preg_replace('/[^a-z0-9]+/', '-', $text);
		$text = preg_replace('/-+/', '-', $text);
		return trim(mb_substr($text, 0, 190), '-');
	}
}

if (!function_exists('cbia_ensure_category_exists')) {
	function cbia_ensure_category_exists($cat_name) {
		$cat_name = trim((string)$cat_name);
		if ($cat_name === '') return 0;

		$existing = term_exists($cat_name, 'category');
		if ($existing) return is_array($existing) ? (int)$existing['term_id'] : (int)$existing;

		$slug = cbia_slugify($cat_name);
		if ($slug === '') $slug = 'cat-' . wp_generate_password(6, false);

		$created = wp_insert_term(mb_substr($cat_name, 0, 180), 'category', ['slug' => $slug]);
		if (is_wp_error($created)) {
			cbia_log(sprintf("Error creando categoria '%s': %s", (string)$cat_name, (string)$created->get_error_message()), 'ERROR');
			return 0;
		}
		return (int)$created['term_id'];
	}
}

if (!function_exists('cbia_determine_categories_by_mapping')) {
	function cbia_determine_categories_by_mapping($title, $content_html) {
		$s = cbia_get_settings();
		$mapping = (string)($s['keywords_to_categories'] ?? '');
		return cbia_rank_categories_by_mapping($mapping, $title, $content_html, 3);
	}
}

if (!function_exists('cbia_get_allowed_tags_list')) {
	function cbia_get_allowed_tags_list() {
		$s = cbia_get_settings();
		$tags_string = (string)($s['default_tags'] ?? '');
		$arr = array_filter(array_map('trim', explode(',', $tags_string)));
		$arr = array_values(array_unique($arr));
		return array_slice($arr, 0, 50); // lista permitida (luego asignamos mÃ¡x 7)
	}
}

if (!function_exists('cbia_pick_tags_from_content_allowed')) {
	function cbia_pick_tags_from_content_allowed($title, $content_html, $max = 7) {
		$allowed = cbia_get_allowed_tags_list();
		if (empty($allowed)) return [];

		$hay = cbia_normalize_for_match($title . ' ' . wp_strip_all_tags((string)$content_html));
		$matched = [];

		foreach ($allowed as $tag) {
			$tn = cbia_normalize_for_match($tag);
			if ($tn === '') continue;
			// match simple por substring
			if (mb_strpos($hay, $tn) !== false) {
				$matched[] = $tag;
			}
			if (count($matched) >= $max) break;
		}

		// fallback si no matchea: primeras (pero mÃ¡ximo 7)
		if (empty($matched)) {
			$matched = array_slice($allowed, 0, $max);
		}

		return array_slice(array_values(array_unique($matched)), 0, $max);
	}
}

if (!function_exists('cbia_tag_stopwords')) {
	function cbia_tag_stopwords() {
		return array(
			'de','del','la','las','el','los','y','o','u','en','con','sin','para','por','a','al','como','que','se','su','sus','un','una','unos','unas','lo','le','les',
			'the','and','for','with','without','from','that','this','these','those','your','you','our','their','into','onto','over','under','about','using'
		);
	}
}

if (!function_exists('cbia_pick_tags_from_text_fallback')) {
	function cbia_pick_tags_from_text_fallback($title, $content_html, $max = 7) {
		$max = max(1, (int)$max);
		$title = trim(wp_strip_all_tags((string)$title));
		$content_text = trim(wp_strip_all_tags((string)$content_html));
		$corpus = trim($title . ' ' . mb_substr($content_text, 0, 2200));
		if ($corpus === '') return array();

		preg_match_all('/\p{L}[\p{L}\p{N}\-]{2,}/u', (string)$corpus, $m);
		$tokens = array_map('trim', (array)($m[0] ?? array()));
		if (empty($tokens)) return array();

		$stop = array_fill_keys(array_map('mb_strtolower', cbia_tag_stopwords()), true);
		$freq = array();
		foreach ($tokens as $token) {
			$lower = mb_strtolower((string)$token);
			if (isset($stop[$lower])) continue;
			if (mb_strlen($lower) < 4) continue;
			if (!isset($freq[$lower])) $freq[$lower] = 0;
			$freq[$lower]++;
		}
		arsort($freq);

		$picked = array();
		foreach ($freq as $token => $hits) {
			if ($hits <= 0) continue;
			$label = sanitize_text_field((string)$token);
			if ($label === '') continue;
			$picked[] = $label;
			if (count($picked) >= $max) break;
		}

		if (empty($picked) && $title !== '') {
			preg_match_all('/\p{L}[\p{L}\p{N}\-]{2,}/u', (string)$title, $tm);
			foreach ((array)($tm[0] ?? array()) as $token) {
				$token = sanitize_text_field((string)$token);
				if ($token === '') continue;
				if (mb_strlen($token) < 4) continue;
				$picked[] = $token;
				if (count($picked) >= $max) break;
			}
		}

		return array_slice(array_values(array_unique($picked)), 0, $max);
	}
}

if (!function_exists('cbia_pick_tags_for_post')) {
	function cbia_pick_tags_for_post($title, $content_html, $max = 7) {
		$max = max(1, (int)$max);
		$allowed_tags = cbia_pick_tags_from_content_allowed($title, $content_html, $max);
		if (!empty($allowed_tags)) {
			return array_slice(array_values(array_unique(array_map('sanitize_text_field', (array)$allowed_tags))), 0, $max);
		}
		$fallback = cbia_pick_tags_from_text_fallback($title, $content_html, $max);
		return array_slice(array_values(array_unique(array_map('sanitize_text_field', (array)$fallback))), 0, $max);
	}
}
