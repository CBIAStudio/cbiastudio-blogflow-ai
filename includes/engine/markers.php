<?php
/**
 * Image marker helpers.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_force_insert_markers')) {
    function cbia_force_insert_markers($html, $title, $internal_limit) {
        $current_markers = 0;
        if (preg_match_all('/\[(?:IMAGE|IMAGEN)\s*:[^\]]+\]/i', (string)$html, $matches)) {
            $current_markers = count((array)$matches[0]);
        }

        $missing = (int)$internal_limit - (int)$current_markers;
        if ($missing <= 0) {
            return (string)$html;
        }

        $inserted = 0;

        // Intro marker after first paragraph.
        if ($inserted < $missing && preg_match('/<p[^>]*>.*?<\/p>/is', (string)$html, $m, PREG_OFFSET_CAPTURE)) {
            $paragraph = (string)$m[0][0];
            $paragraph_len = strlen($paragraph);
            $paragraph_pos = (int)$m[0][1];
            $desc = preg_replace('/\s+/', ' ', wp_strip_all_tags($paragraph));
            $marker = "\n[IMAGE: {$desc}]\n";
            $html = substr((string)$html, 0, $paragraph_pos + $paragraph_len) . $marker . substr((string)$html, $paragraph_pos + $paragraph_len);
            $inserted++;
        }

        // FAQ marker before FAQ heading, when present.
        $faq_pos = -1;
        if (preg_match('/<h2[^>]*>.*?(frequently\s+asked\s+questions|preguntas\s+frecuentes|faq).*?<\/h2>/iu', (string)$html, $faq_match, PREG_OFFSET_CAPTURE)) {
            $faq_pos = (int)$faq_match[0][1];
        }
        if ($inserted < $missing && $faq_pos >= 0) {
            $marker = "\n[IMAGE: visual support for the FAQ section]\n";
            $html = substr((string)$html, 0, $faq_pos) . $marker . substr((string)$html, $faq_pos);
            $inserted++;
        }

        // Add at the end if markers are still missing.
        if ($inserted < $missing) {
            $desc = "closing visual aligned with the topic '{$title}'";
            $marker = "\n[IMAGE: {$desc}]\n";
            $html .= $marker;
            $inserted++;
        }

        return (string)$html;
    }
}
