<?php
/**
 * Image marker helpers.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_marker_extract_descs')) {
    function cbia_marker_extract_descs($html) {
        $descs = array();
        if (preg_match_all('/\[(?:IMAGE|IMAGEN)\s*:\s*([^\]]+)\]/iu', (string)$html, $m)) {
            foreach ((array)($m[1] ?? array()) as $desc) {
                $desc = trim((string)$desc);
                if ($desc !== '') {
                    $descs[] = $desc;
                }
            }
        }
        return $descs;
    }
}

if (!function_exists('cbia_marker_strip_all')) {
    function cbia_marker_strip_all($html) {
        return (string)preg_replace('/\[(?:IMAGE|IMAGEN)\s*:\s*[^\]]+\]/iu', '', (string)$html);
    }
}

if (!function_exists('cbia_marker_build_text')) {
    function cbia_marker_build_text($desc) {
        $desc = trim((string)$desc);
        if ($desc === '') {
            $desc = 'supporting visual for this section';
        }
        return "\n[IMAGE: {$desc}]\n";
    }
}

if (!function_exists('cbia_marker_build_fallback_desc')) {
    function cbia_marker_build_fallback_desc($slot, $total, $title) {
        $slot = max(1, (int)$slot);
        $total = max(1, (int)$total);
        $title = trim((string)$title);
        if ($slot === 1 && $total === 1) {
            return "core body visual aligned with '{$title}'";
        }
        if ($slot === 1) {
            return "early body visual introducing '{$title}'";
        }
        if ($slot === 2 && $total === 2) {
            return "body visual before FAQ for '{$title}'";
        }
        if ($slot === 2) {
            return "mid-body visual supporting '{$title}'";
        }
        return "FAQ visual summary for '{$title}'";
    }
}

if (!function_exists('cbia_marker_find_faq_heading_pos')) {
    function cbia_marker_find_faq_heading_pos($html) {
        if (preg_match('/<h2[^>]*>.*?(frequently\s+asked\s+questions|preguntas\s+frecuentes|faq).*?<\/h2>/iu', (string)$html, $m, PREG_OFFSET_CAPTURE)) {
            return (int)($m[0][1] ?? -1);
        }
        return -1;
    }
}

if (!function_exists('cbia_marker_insert_at_percent')) {
    function cbia_marker_insert_at_percent($html, $marker, $percent = 0.5, $upper_bound = -1) {
        $html = (string)$html;
        $marker = (string)$marker;
        $len = strlen($html);
        if ($len <= 0) {
            return $marker;
        }

        $percent = (float)$percent;
        if ($percent < 0.05) $percent = 0.05;
        if ($percent > 0.95) $percent = 0.95;
        $target = (int)floor($len * $percent);

        $maxPos = (int)$upper_bound;
        if ($maxPos <= 0 || $maxPos > $len) $maxPos = $len;

        $candidate = -1;
        if (preg_match_all('/<\/(p|ul|ol|blockquote|figure|h2|h3|div)>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ((array)$m[0] as $cap) {
                $endPos = (int)$cap[1] + strlen((string)$cap[0]);
                if ($endPos > $maxPos) break;
                if ($endPos >= $target) {
                    $candidate = $endPos;
                    break;
                }
                $candidate = $endPos;
            }
        }

        if ($candidate < 0) {
            $candidate = min($maxPos, $len);
        }

        return substr($html, 0, $candidate) . $marker . substr($html, $candidate);
    }
}

if (!function_exists('cbia_marker_insert_after_first_paragraph')) {
    function cbia_marker_insert_after_first_paragraph($html, $marker) {
        if (preg_match('/<p[^>]*>.*?<\/p>/is', (string)$html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = (int)$m[0][1] + strlen((string)$m[0][0]);
            return substr((string)$html, 0, $pos) . (string)$marker . substr((string)$html, $pos);
        }
        return cbia_marker_insert_at_percent((string)$html, (string)$marker, 0.26);
    }
}

if (!function_exists('cbia_marker_insert_before_faq_or_percent')) {
    function cbia_marker_insert_before_faq_or_percent($html, $marker, $percent = 0.72) {
        $faqPos = cbia_marker_find_faq_heading_pos((string)$html);
        if ($faqPos >= 0) {
            return substr((string)$html, 0, $faqPos) . (string)$marker . substr((string)$html, $faqPos);
        }
        return cbia_marker_insert_at_percent((string)$html, (string)$marker, (float)$percent);
    }
}

if (!function_exists('cbia_marker_target_percents')) {
    function cbia_marker_target_percents($limit) {
        $limit = max(0, min(4, (int)$limit));
        if ($limit === 1) return array(0.52);
        if ($limit === 2) return array(0.34, 0.66);
        if ($limit === 3) return array(0.25, 0.50, 0.75);
        if ($limit === 4) return array(0.20, 0.40, 0.60, 0.80);
        return array();
    }
}

if (!function_exists('cbia_rebalance_internal_image_markers')) {
    function cbia_rebalance_internal_image_markers($html, $title, $internal_limit) {
        $html = (string)$html;
        $title = trim((string)$title);
        $limit = max(0, min(4, (int)$internal_limit));
        $html_without_markers = cbia_marker_strip_all($html);
        if ($limit <= 0) {
            return $html_without_markers;
        }

        $descs = cbia_marker_extract_descs($html);
        $slot_descs = array();
        for ($i = 1; $i <= $limit; $i++) {
            $slot_descs[] = isset($descs[$i - 1]) && trim((string)$descs[$i - 1]) !== ''
                ? trim((string)$descs[$i - 1])
                : cbia_marker_build_fallback_desc($i, $limit, $title);
        }

        $rebuilt = $html_without_markers;
        $faq_pos = cbia_marker_find_faq_heading_pos($rebuilt);
        $upper = ($faq_pos >= 0) ? $faq_pos : -1;
        foreach (cbia_marker_target_percents($limit) as $index => $percent) {
            $marker = cbia_marker_build_text($slot_descs[$index] ?? cbia_marker_build_fallback_desc($index + 1, $limit, $title));
            $rebuilt = cbia_marker_insert_at_percent($rebuilt, $marker, (float)$percent, $upper);
            $faq_pos = cbia_marker_find_faq_heading_pos($rebuilt);
            $upper = ($faq_pos >= 0) ? $faq_pos : -1;
        }

        return $rebuilt;
    }
}

if (!function_exists('cbia_force_insert_markers')) {
    function cbia_force_insert_markers($html, $title, $internal_limit) {
        return cbia_rebalance_internal_image_markers($html, $title, $internal_limit);
    }
}
