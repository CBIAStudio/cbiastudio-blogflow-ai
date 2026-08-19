<?php
/**
 * Sanitization helpers.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_sanitize_textarea_preserve_lines')) {
    function cbia_sanitize_textarea_preserve_lines($value): string {
        $value = is_string($value) ? $value : '';
        $value = wp_unslash($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        return trim($value);
    }
}

if (!function_exists('cbia_sanitize_csv_tags')) {
    function cbia_sanitize_csv_tags($value): string {
        $value = cbia_sanitize_textarea_preserve_lines($value);
        $value = str_replace("\n", ",", $value);
        $value = preg_replace('/\s*,\s*/', ',', $value);
        $value = preg_replace('/,+/', ',', $value);
        $value = trim($value, " ,\t\n\r\0\x0B");
        return $value;
    }
}

if (!function_exists('cbia_sanitize_ai_post_title')) {
    /**
     * Convert an AI-generated post title into safe, single-line text.
     */
    function cbia_sanitize_ai_post_title($title): string {
        return trim(sanitize_text_field((string)$title));
    }
}

if (!function_exists('cbia_sanitize_ai_post_content')) {
    /**
     * Final trust boundary for AI-generated HTML before post persistence.
     */
    function cbia_sanitize_ai_post_content($content): string {
        return wp_kses_post((string)$content);
    }
}

if (!function_exists('cbia_sanitize_ai_post_data')) {
    /**
     * Sanitize only AI-generated post fields present in a persistence payload.
     *
     * This policy is unconditional: unfiltered_html never bypasses it.
     */
    function cbia_sanitize_ai_post_data(array $postarr): array {
        if (array_key_exists('post_title', $postarr)) {
            $postarr['post_title'] = cbia_sanitize_ai_post_title($postarr['post_title']);
        }
        if (array_key_exists('post_content', $postarr)) {
            $postarr['post_content'] = cbia_sanitize_ai_post_content($postarr['post_content']);
        }
        if (array_key_exists('post_excerpt', $postarr)) {
            $postarr['post_excerpt'] = sanitize_text_field((string)$postarr['post_excerpt']);
        }
        return $postarr;
    }
}
