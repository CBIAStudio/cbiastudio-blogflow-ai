<?php
/**
 * Core hooks (admin notices, AJAX, assets).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_is_plugin_admin_page')) {
    function cbia_is_plugin_admin_page(): bool {
        if (!is_admin()) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen detection.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page === 'cbia') {
            return true;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen) {
                $screen_id = (string) ($screen->id ?? '');
                $screen_base = (string) ($screen->base ?? '');
                if ($screen_id === 'toplevel_page_cbia' || $screen_base === 'toplevel_page_cbia') {
                    return true;
                }
                if (strpos($screen_id, 'cbia') !== false || strpos($screen_base, 'cbia') !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('cbia_is_pro_edition')) {
    function cbia_is_pro_edition(): bool {
        if (defined('CBIA_EDITION')) {
            return strtolower((string) CBIA_EDITION) === 'pro';
        }
        if (defined('CBIA_PRO_VERSION')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('cbia_get_capabilities')) {
    function cbia_get_capabilities(): array {
        static $caps = null;
        if ($caps !== null) {
            return $caps;
        }

        $is_pro = cbia_is_pro_edition();
        $defaults = array(
            'composer' => true,
            'internal_images' => $is_pro,
            'oldposts' => $is_pro,
            'usage_advanced' => $is_pro,
            'costs_advanced' => $is_pro,
            'runtime_advanced' => $is_pro,
            'auto_prompt_profile' => $is_pro,
        );

        $caps = apply_filters('cbia_capabilities', $defaults);
        $caps = is_array($caps) ? array_merge($defaults, $caps) : $defaults;
        return $caps;
    }
}

if (!function_exists('cbia_cap_enabled')) {
    function cbia_cap_enabled(string $cap): bool {
        $caps = cbia_get_capabilities();
        return !empty($caps[$cap]);
    }
}

if (!function_exists('cbia_add_prompt_profile_posts_column')) {
    function cbia_add_prompt_profile_posts_column(array $columns): array {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'author') {
                $new['cbia_prompt_profile'] = __('Blog profile', 'cbiastudio-blogflow-ai');
            }
        }
        if (!isset($new['cbia_prompt_profile'])) {
            $new['cbia_prompt_profile'] = __('Blog profile', 'cbiastudio-blogflow-ai');
        }
        return $new;
    }
}

if (!function_exists('cbia_render_prompt_profile_posts_column')) {
    function cbia_render_prompt_profile_posts_column($column, $post_id): void {
        if ($column !== 'cbia_prompt_profile') return;

        $configured = sanitize_key((string)get_post_meta((int)$post_id, '_cbia_prompt_profile_configured', true));
        $resolved = sanitize_key((string)get_post_meta((int)$post_id, '_cbia_prompt_profile_resolved', true));

        if ($configured === '' && $resolved === '') {
            echo '&mdash;';
            return;
        }

        $label_fn = function_exists('cbia_prompt_profile_label') ? 'cbia_prompt_profile_label' : null;
        $resolved_label = $label_fn ? (string)call_user_func($label_fn, $resolved) : $resolved;
        $configured_label = $label_fn ? (string)call_user_func($label_fn, $configured) : $configured;

        if ($configured === 'auto_by_title') {
            echo '<span class="cbia-post-profile cbia-post-profile-auto">' . esc_html__('Auto', 'cbiastudio-blogflow-ai') . ' &rarr; ' . esc_html($resolved_label !== '' ? $resolved_label : $resolved) . '</span>';
            return;
        }

        echo '<span class="cbia-post-profile">' . esc_html($resolved_label !== '' ? $resolved_label : ($configured_label !== '' ? $configured_label : $resolved)) . '</span>';
    }
}

if (!function_exists('cbia_enforce_internal_images_cap_on_payload')) {
    function cbia_enforce_internal_images_cap_on_payload(array $payload): array {
        if (cbia_cap_enabled('internal_images')) {
            return $payload;
        }
        $payload['images_limit'] = 1; // featured only
        if (isset($payload['internal_images'])) {
            $payload['internal_images'] = 0;
        }
        return $payload;
    }
}

if (!function_exists('cbia_register_core_hooks')) {
    function cbia_register_core_hooks() {
        // Admin notices: Yoast SEO status (only in plugin page)
        if (!has_action('admin_notices', 'cbia_admin_notice_yoast')) {
            add_action('admin_notices', 'cbia_admin_notice_yoast');
        }
        // Admin scripts (inline helpers)
        if (!has_action('admin_enqueue_scripts', 'cbia_admin_enqueue_inline')) {
            add_action('admin_enqueue_scripts', 'cbia_admin_enqueue_inline');
        }
        if (!has_filter('admin_body_class', 'cbia_admin_body_class')) {
            add_filter('admin_body_class', 'cbia_admin_body_class');
        }
        if (!has_filter('manage_post_posts_columns', 'cbia_add_prompt_profile_posts_column')) {
            add_filter('manage_post_posts_columns', 'cbia_add_prompt_profile_posts_column');
        }
        if (!has_action('manage_post_posts_custom_column', 'cbia_render_prompt_profile_posts_column')) {
            add_action('manage_post_posts_custom_column', 'cbia_render_prompt_profile_posts_column', 10, 2);
        }
        if (!has_action('in_admin_header', 'cbia_suppress_external_admin_notices')) {
            add_action('in_admin_header', 'cbia_suppress_external_admin_notices', 1);
        }
        if (!has_action('add_meta_boxes', 'cbia_register_ai_composer_metabox')) {
            add_action('add_meta_boxes', 'cbia_register_ai_composer_metabox', 10, 2);
        }
        if (!has_action('edit_form_after_title', 'cbia_render_ai_composer_trigger')) {
            add_action('edit_form_after_title', 'cbia_render_ai_composer_trigger', 20);
        }
        if (!has_action('edit_form_after_editor', 'cbia_render_ai_composer_fallback_container')) {
            add_action('edit_form_after_editor', 'cbia_render_ai_composer_fallback_container', 20);
        }
        if (!has_action('admin_footer-post.php', 'cbia_render_ai_composer_footer_fallback')) {
            add_action('admin_footer-post.php', 'cbia_render_ai_composer_footer_fallback', 20);
        }
        if (!has_action('admin_footer-post-new.php', 'cbia_render_ai_composer_footer_fallback')) {
            add_action('admin_footer-post-new.php', 'cbia_render_ai_composer_footer_fallback', 20);
        }
        // Add posts list button via admin JS (no inline styles)

        // AJAX handlers
        if (!has_action('wp_ajax_cbia_get_log', 'cbia_ajax_get_log')) {
            add_action('wp_ajax_cbia_get_log', 'cbia_ajax_get_log');
        }
        if (!has_action('wp_ajax_cbia_clear_log', 'cbia_ajax_clear_log')) {
            add_action('wp_ajax_cbia_clear_log', 'cbia_ajax_clear_log');
        }
        if (!has_action('wp_ajax_cbia_set_stop', 'cbia_ajax_set_stop')) {
            add_action('wp_ajax_cbia_set_stop', 'cbia_ajax_set_stop');
        }
        if (!has_action('wp_ajax_cbia_get_checkpoint_status', 'cbia_ajax_get_checkpoint_status')) {
            add_action('wp_ajax_cbia_get_checkpoint_status', 'cbia_ajax_get_checkpoint_status');
        }
        if (!has_action('wp_ajax_cbia_start_generation', 'cbia_ajax_start_generation')) {
            add_action('wp_ajax_cbia_start_generation', 'cbia_ajax_start_generation');
        }
        if (cbia_cap_enabled('oldposts')) {
            if (!has_action('wp_ajax_cbia_clear_oldposts_log', 'cbia_ajax_clear_oldposts_log')) {
                add_action('wp_ajax_cbia_clear_oldposts_log', 'cbia_ajax_clear_oldposts_log');
            }
            if (!has_action('wp_ajax_cbia_get_oldposts_log', 'cbia_ajax_get_oldposts_log')) {
                add_action('wp_ajax_cbia_get_oldposts_log', 'cbia_ajax_get_oldposts_log');
            }
            if (!has_action('wp_ajax_cbia_oldposts_prepare_run', 'cbia_ajax_oldposts_prepare_run')) {
                add_action('wp_ajax_cbia_oldposts_prepare_run', 'cbia_ajax_oldposts_prepare_run');
            }
            if (!has_action('wp_ajax_cbia_oldposts_run_chunk', 'cbia_ajax_oldposts_run_chunk')) {
                add_action('wp_ajax_cbia_oldposts_run_chunk', 'cbia_ajax_oldposts_run_chunk');
            }
            if (!has_action('wp_ajax_cbia_oldposts_get_run_state', 'cbia_ajax_oldposts_get_run_state')) {
                add_action('wp_ajax_cbia_oldposts_get_run_state', 'cbia_ajax_oldposts_get_run_state');
            }
            if (!has_action('wp_ajax_cbia_oldposts_tick_run', 'cbia_ajax_oldposts_tick_run')) {
                add_action('wp_ajax_cbia_oldposts_tick_run', 'cbia_ajax_oldposts_tick_run');
            }
            if (!has_action('wp_ajax_cbia_oldposts_cards_snapshot', 'cbia_ajax_oldposts_cards_snapshot')) {
                add_action('wp_ajax_cbia_oldposts_cards_snapshot', 'cbia_ajax_oldposts_cards_snapshot');
            }
            if (!has_action('cbia_oldposts_process_background_run', 'cbia_oldposts_process_background_run')) {
                add_action('cbia_oldposts_process_background_run', 'cbia_oldposts_process_background_run');
            }
        }
        if (!has_action('wp_ajax_cbia_get_costes_log', 'cbia_ajax_get_costes_log')) {
            add_action('wp_ajax_cbia_get_costes_log', 'cbia_ajax_get_costes_log');
        }
        if (!has_action('wp_ajax_cbia_get_img_prompt', 'cbia_ajax_get_img_prompt')) {
            add_action('wp_ajax_cbia_get_img_prompt', 'cbia_ajax_get_img_prompt');
        }
        if (!has_action('wp_ajax_cbia_save_img_prompt_override', 'cbia_ajax_save_img_prompt_override')) {
            add_action('wp_ajax_cbia_save_img_prompt_override', 'cbia_ajax_save_img_prompt_override');
        }
        if (!has_action('wp_ajax_cbia_regen_image', 'cbia_ajax_regen_image')) {
            add_action('wp_ajax_cbia_regen_image', 'cbia_ajax_regen_image');
        }
        if (!has_action('wp_ajax_cbia_sync_models', 'cbia_ajax_sync_models')) {
            add_action('wp_ajax_cbia_sync_models', 'cbia_ajax_sync_models');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_save_api_key', 'cbia_ajax_ai_composer_save_api_key')) {
            add_action('wp_ajax_cbia_ai_composer_save_api_key', 'cbia_ajax_ai_composer_save_api_key');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_test_api_key', 'cbia_ajax_ai_composer_test_api_key')) {
            add_action('wp_ajax_cbia_ai_composer_test_api_key', 'cbia_ajax_ai_composer_test_api_key');
        }
        if (!has_action('wp_ajax_cbia_provider_connection_save', 'cbia_ajax_provider_connection_save')) {
            add_action('wp_ajax_cbia_provider_connection_save', 'cbia_ajax_provider_connection_save');
        }
        if (!has_action('wp_ajax_cbia_provider_connection_test', 'cbia_ajax_provider_connection_test')) {
            add_action('wp_ajax_cbia_provider_connection_test', 'cbia_ajax_provider_connection_test');
        }
        if (!has_action('wp_ajax_cbia_provider_connection_disconnect', 'cbia_ajax_provider_connection_disconnect')) {
            add_action('wp_ajax_cbia_provider_connection_disconnect', 'cbia_ajax_provider_connection_disconnect');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_load_snapshot', 'cbia_ajax_ai_composer_load_snapshot')) {
            add_action('wp_ajax_cbia_ai_composer_load_snapshot', 'cbia_ajax_ai_composer_load_snapshot');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_save_snapshot', 'cbia_ajax_ai_composer_save_snapshot')) {
            add_action('wp_ajax_cbia_ai_composer_save_snapshot', 'cbia_ajax_ai_composer_save_snapshot');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_regenerate_image_slot', 'cbia_ajax_ai_composer_regenerate_image_slot')) {
            add_action('wp_ajax_cbia_ai_composer_regenerate_image_slot', 'cbia_ajax_ai_composer_regenerate_image_slot');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_apply_yoast_meta', 'cbia_ajax_ai_composer_apply_yoast_meta')) {
            add_action('wp_ajax_cbia_ai_composer_apply_yoast_meta', 'cbia_ajax_ai_composer_apply_yoast_meta');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_apply_terms', 'cbia_ajax_ai_composer_apply_terms')) {
            add_action('wp_ajax_cbia_ai_composer_apply_terms', 'cbia_ajax_ai_composer_apply_terms');
        }
        if (!has_action('wp_ajax_cbia_ai_composer_apply_full_post', 'cbia_ajax_ai_composer_apply_full_post')) {
            add_action('wp_ajax_cbia_ai_composer_apply_full_post', 'cbia_ajax_ai_composer_apply_full_post');
        }
        if (!has_action('wp_ajax_cbia_preview_article', 'cbia_ajax_preview_article')) {
            add_action('wp_ajax_cbia_preview_article', 'cbia_ajax_preview_article');
        }
        if (!has_action('wp_ajax_cbia_preview_article_stream', 'cbia_ajax_preview_article_stream')) {
            add_action('wp_ajax_cbia_preview_article_stream', 'cbia_ajax_preview_article_stream');
        }
        if (!has_action('wp_ajax_cbia_create_post_from_preview', 'cbia_ajax_create_post_from_preview')) {
            add_action('wp_ajax_cbia_create_post_from_preview', 'cbia_ajax_create_post_from_preview');
        }
        if (!has_action('wp_ajax_cbia_cancel_preview', 'cbia_ajax_cancel_preview')) {
            add_action('wp_ajax_cbia_cancel_preview', 'cbia_ajax_cancel_preview');
        }
        if (!has_action('wp_ajax_cbia_test_csv_titles', 'cbia_ajax_test_csv_titles')) {
            add_action('wp_ajax_cbia_test_csv_titles', 'cbia_ajax_test_csv_titles');
        }
        if (!has_action('admin_post_cbia_usage_export', 'cbia_admin_post_usage_export')) {
            add_action('admin_post_cbia_usage_export', 'cbia_admin_post_usage_export');
        }
        if (!has_action('wp_ajax_cbia_usage_overview_data', 'cbia_ajax_usage_overview_data')) {
            add_action('wp_ajax_cbia_usage_overview_data', 'cbia_ajax_usage_overview_data');
        }
        if (!has_action('cbia_usage_row_recorded', 'cbia_usage_store_append_row')) {
            add_action('cbia_usage_row_recorded', 'cbia_usage_store_append_row', 10, 2);
        }
// Historical usage is never rewritten automatically. Recalculation is an explicit dry-run workflow.
        if (!has_action('admin_init', 'cbia_maybe_migrate_provider_credentials')) {
            add_action('admin_init', 'cbia_maybe_migrate_provider_credentials', 2);
        }

        // Frontend styles for banner images
        if (!has_action('wp_head', 'cbia_pro_output_banner_css')) {
            add_action('wp_head', 'cbia_pro_output_banner_css', 999);
        }
        // Admin/editor styles for banner images (preview + editor)
        if (!has_action('admin_head', 'cbia_pro_output_banner_css_admin')) {
            add_action('admin_head', 'cbia_pro_output_banner_css_admin', 20);
        }
    }
}

if (!function_exists('cbia_admin_post_usage_export')) {
    function cbia_admin_post_usage_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'cbiastudio-blogflow-ai'));
        }
        check_admin_referer('cbia_usage_export');

        $days = isset($_GET['usage_days']) ? absint(wp_unslash((string) $_GET['usage_days'])) : 7;
        if (!in_array($days, array(7, 30, 90, 730), true)) $days = 7;
        $model_filter = isset($_GET['usage_model']) ? sanitize_text_field(wp_unslash((string) $_GET['usage_model'])) : '';
        $since_ts = time() - ($days * DAY_IN_SECONDS);

        $rows = array();
        $query = new WP_Query(array(
            'post_type'      => 'post',
            'post_status'    => array('publish', 'future', 'draft', 'pending'),
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        $ids = !empty($query->posts) ? $query->posts : array();
        foreach ($ids as $post_id) {
            if (!function_exists('cbia_costes_get_usage_rows_for_post')) break;
            $usage_rows = cbia_costes_get_usage_rows_for_post((int) $post_id);
            if (empty($usage_rows) || !is_array($usage_rows)) continue;
            foreach ($usage_rows as $r) {
                if (!is_array($r)) continue;
                $ts = isset($r['ts']) ? strtotime((string) $r['ts']) : 0;
                if ($ts && $ts < $since_ts) continue;
                $model = (string) ($r['model'] ?? '');
                if ($model_filter !== '' && $model_filter !== $model) {
                    continue;
                }
                $rows[] = array(
                    'post_id' => (int) $post_id,
                    'ts' => (string) ($r['ts'] ?? ''),
                    'type' => (string) ($r['type'] ?? ''),
                    'model' => $model,
                    'in' => (int) ($r['in'] ?? 0),
                    'out' => (int) ($r['out'] ?? 0),
                    'cin' => (int) ($r['cin'] ?? 0),
                    'ok' => !empty($r['ok']) ? 1 : 0,
                );
            }
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ai-blog-builder-usage.csv');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to output.
        $out = fopen('php://output', 'w');
        fputcsv($out, array('post_id','ts','type','model','in','out','cached_in','ok'));
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming CSV to output.
        fclose($out);
        exit;
    }
}

if (!function_exists('cbia_get_usage_dashboard_payload')) {
    function cbia_get_usage_dashboard_payload($days = 30, $requested_model = '') {
        $allowed_days = array(7, 30, 90, 730);
        $days = (int) $days;
        if (!in_array($days, $allowed_days, true)) {
            $days = 30;
        }

        $requested_model = sanitize_text_field((string) $requested_model);
        $since_ts = time() - ($days * DAY_IN_SECONDS);
        $cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
        $cost_table = function_exists('cbia_costes_price_table_usd_per_million') ? cbia_costes_price_table_usd_per_million() : array();

        $cache_ttl = (int) apply_filters('cbia_pro_usage_cache_ttl', 15 * MINUTE_IN_SECONDS);
        if ($cache_ttl < 0) {
            $cache_ttl = 0;
        }
        $recent_rows_limit = (int) apply_filters('cbia_pro_usage_recent_rows_limit', 5000);
        if ($recent_rows_limit < 20) {
            $recent_rows_limit = 20;
        }

        $cache_key = 'cbia_pro_usage_overview_v7_' . get_current_blog_id() . '_' . (int) $days;
        $cached_usage = $cache_ttl > 0 ? get_transient($cache_key) : false;
        $cached_has_data = is_array($cached_usage)
            && (
                !empty($cached_usage['recent_rows'])
                || !empty($cached_usage['model_options'])
                || !empty($cached_usage['summaries_by_model'])
                || !empty($cached_usage['total_rows'])
            );
        if ($cached_has_data) {
            return array(
                'rows' => is_array($cached_usage['recent_rows'] ?? null) ? $cached_usage['recent_rows'] : array(),
                'totalRows' => (int) ($cached_usage['total_rows'] ?? 0),
                'rowsLimited' => !empty($cached_usage['rows_limited']),
                'recentRowsLimit' => $recent_rows_limit,
                'dailySeries' => is_array($cached_usage['daily_series'] ?? null) ? $cached_usage['daily_series'] : array(),
                'monthlySeries' => is_array($cached_usage['monthly_series'] ?? null) ? $cached_usage['monthly_series'] : array(),
                'summariesByModel' => is_array($cached_usage['summaries_by_model'] ?? null) ? $cached_usage['summaries_by_model'] : array(),
                'modelOptions' => is_array($cached_usage['model_options'] ?? null) ? $cached_usage['model_options'] : array(),
                'defaultModel' => $requested_model,
                'periodDays' => $days,
            );
        }

        $available_models = array();
        $log_rows = array();
        $author_names = array();
        $daily_buckets = array();
        $monthly_buckets = array();

        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => 500,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => '_cbia_usage_rows', 'compare' => 'EXISTS'),
                array('key' => '_cbia_usage_calls', 'compare' => 'EXISTS'),
                array('key' => '_cbia_image_calls', 'compare' => 'EXISTS'),
                array('key' => '_cbia_tokens_total_sum', 'compare' => 'EXISTS'),
            ),
        ));

        $post_ids = !empty($query->posts) ? $query->posts : array();
        foreach ($post_ids as $post_id) {
            if (!function_exists('cbia_costes_get_usage_rows_for_post')) {
                break;
            }

            $legacy_image_calls = array();
            $raw_legacy_images = get_post_meta((int) $post_id, '_cbia_image_calls', true);
            if (is_string($raw_legacy_images) && $raw_legacy_images !== '') {
                $decoded_legacy_images = json_decode($raw_legacy_images, true);
                if (is_array($decoded_legacy_images)) {
                    $legacy_image_calls = $decoded_legacy_images;
                }
            } elseif (is_array($raw_legacy_images)) {
                $legacy_image_calls = $raw_legacy_images;
            }

            $usage_rows = cbia_costes_get_usage_rows_for_post((int) $post_id);
            if (empty($usage_rows) || !is_array($usage_rows)) {
                continue;
            }

            $post_multiplier = 1.0;
            $real_adjust_multiplier = isset($cost_settings['real_adjust_multiplier']) ? (float) $cost_settings['real_adjust_multiplier'] : 1.0;
            if ($real_adjust_multiplier > 0 && $real_adjust_multiplier !== 1.0) {
                $post_multiplier = $real_adjust_multiplier;
            }

            $post_id = (int) $post_id;
            $post_title = get_the_title($post_id);
            if ($post_title === '') {
                $post_title = 'Post #' . $post_id;
            }

            $author_id = (int) get_post_field('post_author', $post_id);
            if (!isset($author_names[$author_id])) {
                $user_obj = $author_id > 0 ? get_user_by('id', $author_id) : false;
                $author_names[$author_id] = $user_obj ? (string) $user_obj->display_name : ('User #' . $author_id);
            }

            foreach ($usage_rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $ts_raw = isset($row['ts']) ? (string) $row['ts'] : '';
                $ts = $ts_raw !== '' ? strtotime($ts_raw) : 0;

                $type = isset($row['type']) ? strtolower(trim((string) $row['type'])) : 'text';
                if (!in_array($type, array('text', 'image', 'seo'), true)) {
                    $type = 'text';
                }

                $model = (string) ($row['model'] ?? '');
                if ($model === '') {
                    $model = 'unknown';
                }

                $attach_id = (int) ($row['attach_id'] ?? 0);
                $section = sanitize_key((string) ($row['section'] ?? ''));
                if ($type === 'image' && $section === '' && !empty($legacy_image_calls)) {
                    foreach ($legacy_image_calls as $legacy_image_call) {
                        if (!is_array($legacy_image_call)) {
                            continue;
                        }
                        $legacy_attach = isset($legacy_image_call['attach_id']) ? (int) $legacy_image_call['attach_id'] : 0;
                        $legacy_model = isset($legacy_image_call['model']) ? (string) $legacy_image_call['model'] : '';
                        $legacy_ts = isset($legacy_image_call['ts']) ? (string) $legacy_image_call['ts'] : '';
                        $same_attach = $attach_id > 0 && $legacy_attach > 0 && $attach_id === $legacy_attach;
                        $same_signature = !$same_attach && $legacy_model !== '' && $legacy_model === $model && $legacy_ts !== '' && $legacy_ts === $ts_raw;
                        if ($same_attach || $same_signature) {
                            $section = sanitize_key((string) ($legacy_image_call['section'] ?? ''));
                            if ($section !== '') {
                                break;
                            }
                        }
                    }
                }

                $section_label = '';
                $section_detail = '';
                if ($type === 'image') {
                    if ($section === 'featured' || $section === 'intro') {
                        $section_label = 'featured';
                        $section_detail = __('Featured', 'cbiastudio-blogflow-ai');
                    } elseif ($section !== '') {
                        $section_label = 'internal';
                        $section_detail = __('Internal', 'cbiastudio-blogflow-ai') . ' Ã‚Â· ' . ucfirst(str_replace(array('_', '-'), ' ', $section));
                    }
                }

                $available_models[$model] = true;

                $tokens_in = (int) ($row['in'] ?? 0);
                $tokens_out = (int) ($row['out'] ?? 0);
                $tokens_total = $tokens_in + $tokens_out;
                $is_ok = !empty($row['ok']);
                $row_cost_eur = null;
                if (function_exists('cbia_costes_calc_row_eur')) {
                    $row_cost_eur = cbia_costes_calc_row_eur($row, $cost_settings, $cost_table);
                    if ($row_cost_eur !== null && $post_multiplier !== 1.0) {
                        $row_cost_eur = (float) $row_cost_eur * $post_multiplier;
                    }
                    if ($row_cost_eur !== null) {
                        $row_cost_eur = round((float) $row_cost_eur, 6);
                    }
                }

                $day_value = '';
                if ($ts) {
                    $day_value = gmdate('Y-m-d', $ts);
                } elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $ts_raw, $m_day)) {
                    $day_value = (string) $m_day[0];
                }

                $month_value = '';
                if ($ts) {
                    $month_value = gmdate('Y-m', $ts);
                } elseif (preg_match('/\d{4}-\d{2}/', $ts_raw, $m_month)) {
                    $month_value = (string) $m_month[0];
                }

                if ($month_value !== '') {
                    if (!isset($monthly_buckets[$month_value])) {
                        $monthly_buckets[$month_value] = array(
                            'month' => $month_value,
                            'calls' => 0,
                            'text_calls' => 0,
                            'image_calls' => 0,
                            'seo_calls' => 0,
                            'text_cost_eur' => 0.0,
                            'image_cost_eur' => 0.0,
                            'seo_cost_eur' => 0.0,
                            'cost_eur' => 0.0,
                        );
                    }
                    $monthly_buckets[$month_value]['calls'] += 1;
                    if ($type === 'image') {
                        $monthly_buckets[$month_value]['image_calls'] += 1;
                    } elseif ($type === 'seo') {
                        $monthly_buckets[$month_value]['seo_calls'] += 1;
                    } else {
                        $monthly_buckets[$month_value]['text_calls'] += 1;
                    }
                    if ($row_cost_eur !== null) {
                        $monthly_buckets[$month_value]['cost_eur'] += (float) $row_cost_eur;
                        if ($type === 'image') {
                            $monthly_buckets[$month_value]['image_cost_eur'] += (float) $row_cost_eur;
                        } elseif ($type === 'seo') {
                            $monthly_buckets[$month_value]['seo_cost_eur'] += (float) $row_cost_eur;
                        } else {
                            $monthly_buckets[$month_value]['text_cost_eur'] += (float) $row_cost_eur;
                        }
                    }
                }

                if ($ts && $ts < $since_ts) {
                    continue;
                }

                $log_rows[] = array(
                    'post_id' => $post_id,
                    'post_title' => (string) $post_title,
                    'post_edit_url' => (string) get_edit_post_link($post_id, ''),
                    'ts' => $ts_raw,
                    'day' => $day_value,
                    'month' => $month_value,
                    'type' => $type,
                    'type_label' => $type === 'image' ? __('Image', 'cbiastudio-blogflow-ai') : ($type === 'seo' ? __('SEO', 'cbiastudio-blogflow-ai') : __('Text', 'cbiastudio-blogflow-ai')),
                    'section' => $section,
                    'section_label' => $section_label,
                    'section_detail' => $section_detail,
                    'attach_id' => $attach_id,
                    'model' => $model,
                    'tokens_in' => $tokens_in,
                    'tokens_out' => $tokens_out,
                    'tokens_total' => $tokens_total,
                    'token_metrics_applicable' => $type !== 'image',
                    'cached_in' => (int) ($row['cin'] ?? 0),
                    'cost_eur' => $row_cost_eur,
                    'ok' => $is_ok ? 1 : 0,
                    'status_label' => $is_ok ? 'OK' : 'Error',
                    'user_id' => $author_id,
                    'user_name' => (string) $author_names[$author_id],
                    'source_label' => (string) $post_title,
                    'message_preview' => $type === 'image'
                        ? __('Image generation', 'cbiastudio-blogflow-ai') . ($section_detail !== '' ? ' (' . $section_detail . ')' : '')
                        : ($type === 'seo' ? __('SEO / metadata process', 'cbiastudio-blogflow-ai') : __('Text generation', 'cbiastudio-blogflow-ai')),
                );

                if ($day_value !== '') {
                    if (!isset($daily_buckets[$day_value])) {
                        $daily_buckets[$day_value] = array(
                            'day' => $day_value,
                            'calls' => 0,
                            'text' => 0,
                            'image' => 0,
                            'seo' => 0,
                            'textCalls' => 0,
                            'imageCalls' => 0,
                            'seoCalls' => 0,
                        );
                    }
                    $daily_buckets[$day_value]['calls'] += 1;
                    if (isset($daily_buckets[$day_value][$type])) {
                        $daily_buckets[$day_value][$type] += $type === 'image' ? 1 : $tokens_total;
                    }
                    $count_key = $type . 'Calls';
                    if (isset($daily_buckets[$day_value][$count_key])) {
                        $daily_buckets[$day_value][$count_key] += 1;
                    }
                }
            }
        }

        usort($log_rows, function ($a, $b) {
            $ats = strtotime((string) ($a['ts'] ?? '')) ?: 0;
            $bts = strtotime((string) ($b['ts'] ?? '')) ?: 0;
            return $bts <=> $ats;
        });

        ksort($daily_buckets, SORT_STRING);
        $daily_series = array_values(array_map(static function ($item) {
            $item['totalTokens'] = (int) (($item['text'] ?? 0) + ($item['image'] ?? 0) + ($item['seo'] ?? 0));
            return $item;
        }, $daily_buckets));

        ksort($monthly_buckets, SORT_STRING);
        $monthly_series = array_values(array_map(static function ($item) {
            $item['cost_eur'] = round((float) ($item['cost_eur'] ?? 0), 6);
            return $item;
        }, $monthly_buckets));

        $model_options = array_keys($available_models);
        sort($model_options, SORT_STRING);
        $recent_rows = array_slice($log_rows, 0, $recent_rows_limit);

        $summary_rows = array(
            '__all__' => array(
                'total_calls' => 0,
                'post_ids' => array(),
                'user_ids' => array(),
                'total_tokens' => 0,
                'total_cost' => 0.0,
                'known_cost_events' => 0,
                'unknown_cost_events' => 0,
                'cost_status_counts' => array('exact' => 0, 'estimated' => 0, 'unknown' => 0, 'official_reconciled' => 0),
                'daily' => array(),
                'monthly' => array(),
                'type_counts' => array('text' => 0, 'image' => 0, 'seo' => 0),
            ),
        );

        foreach ($log_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $model_key = (string) ($row['model'] ?? 'unknown');
            if ($model_key === '') {
                $model_key = 'unknown';
            }
            if (!isset($summary_rows[$model_key])) {
                $summary_rows[$model_key] = array(
                    'total_calls' => 0,
                    'post_ids' => array(),
                    'user_ids' => array(),
                    'total_tokens' => 0,
                    'total_cost' => 0.0,
                    'daily' => array(),
                    'monthly' => array(),
                    'type_counts' => array('text' => 0, 'image' => 0, 'seo' => 0),
                );
            }

            foreach (array('__all__', $model_key) as $summary_key) {
                $summary_rows[$summary_key]['total_calls'] += 1;
                $post_id_value = (int) ($row['post_id'] ?? 0);
                if ($post_id_value > 0) {
                    $summary_rows[$summary_key]['post_ids'][$post_id_value] = true;
                }
                $user_id_value = (int) ($row['user_id'] ?? 0);
                if ($user_id_value > 0) {
                    $summary_rows[$summary_key]['user_ids'][$user_id_value] = true;
                }
                $summary_rows[$summary_key]['total_tokens'] += (int) ($row['tokens_total'] ?? 0);
                $row_cost_value = $row['cost_eur'] ?? null;
                if ($row_cost_value !== null && $row_cost_value !== '' && is_numeric($row_cost_value)) {
                    $summary_rows[$summary_key]['total_cost'] += (float) $row_cost_value;
                }
                $row_type = (string) ($row['type'] ?? 'text');
                if (!isset($summary_rows[$summary_key]['type_counts'][$row_type])) {
                    $summary_rows[$summary_key]['type_counts'][$row_type] = 0;
                }
                $summary_rows[$summary_key]['type_counts'][$row_type] += 1;

                $day_key = (string) ($row['day'] ?? '');
                if ($day_key !== '') {
                    if (!isset($summary_rows[$summary_key]['daily'][$day_key])) {
                        $summary_rows[$summary_key]['daily'][$day_key] = array(
                            'day' => $day_key,
                            'calls' => 0,
                            'text' => 0,
                            'image' => 0,
                            'seo' => 0,
                            'textCalls' => 0,
                            'imageCalls' => 0,
                            'seoCalls' => 0,
                        );
                    }
                    $summary_rows[$summary_key]['daily'][$day_key]['calls'] += 1;
                    if ($row_type === 'image') {
                        $summary_rows[$summary_key]['daily'][$day_key]['image'] += 1;
                        $summary_rows[$summary_key]['daily'][$day_key]['imageCalls'] += 1;
                    } elseif ($row_type === 'seo') {
                        $summary_rows[$summary_key]['daily'][$day_key]['seo'] += (int) ($row['tokens_total'] ?? 0);
                        $summary_rows[$summary_key]['daily'][$day_key]['seoCalls'] += 1;
                    } else {
                        $summary_rows[$summary_key]['daily'][$day_key]['text'] += (int) ($row['tokens_total'] ?? 0);
                        $summary_rows[$summary_key]['daily'][$day_key]['textCalls'] += 1;
                    }
                }

                $month_key = (string) ($row['month'] ?? '');
                if ($month_key !== '') {
                    if (!isset($summary_rows[$summary_key]['monthly'][$month_key])) {
                        $summary_rows[$summary_key]['monthly'][$month_key] = array(
                            'month' => $month_key,
                            'calls' => 0,
                            'text_calls' => 0,
                            'image_calls' => 0,
                            'seo_calls' => 0,
                            'text_cost_eur' => 0.0,
                            'image_cost_eur' => 0.0,
                            'seo_cost_eur' => 0.0,
                            'cost_eur' => 0.0,
                        );
                    }
                    $summary_rows[$summary_key]['monthly'][$month_key]['calls'] += 1;
                    if ($row_type === 'image') {
                        $summary_rows[$summary_key]['monthly'][$month_key]['image_calls'] += 1;
                    } elseif ($row_type === 'seo') {
                        $summary_rows[$summary_key]['monthly'][$month_key]['seo_calls'] += 1;
                    } else {
                        $summary_rows[$summary_key]['monthly'][$month_key]['text_calls'] += 1;
                    }
                    if ($row_cost_value !== null && $row_cost_value !== '' && is_numeric($row_cost_value)) {
                        $row_cost_float = (float) $row_cost_value;
                        $summary_rows[$summary_key]['monthly'][$month_key]['cost_eur'] += $row_cost_float;
                        if ($row_type === 'image') {
                            $summary_rows[$summary_key]['monthly'][$month_key]['image_cost_eur'] += $row_cost_float;
                        } elseif ($row_type === 'seo') {
                            $summary_rows[$summary_key]['monthly'][$month_key]['seo_cost_eur'] += $row_cost_float;
                        } else {
                            $summary_rows[$summary_key]['monthly'][$month_key]['text_cost_eur'] += $row_cost_float;
                        }
                    }
                }
            }
        }

        $summaries_by_model = array();
        foreach ($summary_rows as $summary_key => $summary) {
            ksort($summary['daily'], SORT_STRING);
            ksort($summary['monthly'], SORT_STRING);
            $normalized_daily = array_values(array_map(static function ($item) {
                $item['totalTokens'] = (int) (($item['text'] ?? 0) + ($item['image'] ?? 0) + ($item['seo'] ?? 0));
                return $item;
            }, $summary['daily']));
            $normalized_monthly = array_values(array_map(static function ($item) {
                $item['cost_eur'] = round((float) ($item['cost_eur'] ?? 0), 6);
                $item['text_cost_eur'] = round((float) ($item['text_cost_eur'] ?? 0), 6);
                $item['image_cost_eur'] = round((float) ($item['image_cost_eur'] ?? 0), 6);
                $item['seo_cost_eur'] = round((float) ($item['seo_cost_eur'] ?? 0), 6);
                return $item;
            }, $summary['monthly']));
            $post_count = count($summary['post_ids']);
            $total_calls = (int) ($summary['total_calls'] ?? 0);
            $summaries_by_model[$summary_key] = array(
                'totalCalls' => $total_calls,
                'uniquePosts' => $post_count,
                'uniqueUsers' => count($summary['user_ids']),
                'totalTokens' => (int) ($summary['total_tokens'] ?? 0),
                'avgTokens' => $total_calls > 0 ? (int) round(((int) ($summary['total_tokens'] ?? 0)) / $total_calls) : 0,
                'totalCost' => round((float) ($summary['total_cost'] ?? 0), 6),
                'avgCostPerPost' => $post_count > 0 ? round(((float) ($summary['total_cost'] ?? 0)) / $post_count, 6) : 0.0,
                'typeCounts' => array(
                    'text' => (int) ($summary['type_counts']['text'] ?? 0),
                    'image' => (int) ($summary['type_counts']['image'] ?? 0),
                    'seo' => (int) ($summary['type_counts']['seo'] ?? 0),
                ),
                'dailySeries' => $normalized_daily,
                'monthlySeries' => $normalized_monthly,
            );
        }

        $openai_temperature_raw = isset($_POST['openai_temperature'])
            ? sanitize_text_field((string) wp_unslash($_POST['openai_temperature']))
            : '';
        $payload = array(
            'rows' => $recent_rows,
            'totalRows' => count($log_rows),
            'rowsLimited' => count($log_rows) > count($recent_rows),
            'recentRowsLimit' => $recent_rows_limit,
            'dailySeries' => $daily_series,
            'monthlySeries' => $monthly_series,
            'summariesByModel' => $summaries_by_model,
            'modelOptions' => $model_options,
            'defaultModel' => $requested_model,
            'periodDays' => $days,
        );

        if (
            $cache_ttl > 0
            && (
                !empty($recent_rows)
                || !empty($model_options)
                || !empty($summaries_by_model)
                || count($log_rows) > 0
            )
        ) {
            set_transient($cache_key, array(
                'recent_rows' => $recent_rows,
                'total_rows' => count($log_rows),
                'rows_limited' => count($log_rows) > count($recent_rows),
                'daily_series' => $daily_series,
                'monthly_series' => $monthly_series,
                'model_options' => $model_options,
                'summaries_by_model' => $summaries_by_model,
            ), $cache_ttl);
        }

        return $payload;
    }
}

if (!function_exists('cbia_usage_overview_cache_key')) {
    function cbia_usage_overview_cache_key($days) {
        return 'cbia_pro_usage_overview_v11_' . get_current_blog_id() . '_' . (int) $days;
    }
}

if (!function_exists('cbia_usage_event_store_option_key')) {
    function cbia_usage_event_store_option_key() {
        return 'cbia_pro_usage_event_store_v2';
    }
}

if (!function_exists('cbia_usage_event_store_limits')) {
    function cbia_usage_event_store_limits() {
        $max_rows = (int) apply_filters('cbia_pro_usage_store_max_rows', 40000);
        if ($max_rows < 5000) {
            $max_rows = 5000;
        }
        $retention_days = (int) apply_filters('cbia_pro_usage_store_retention_days', 730);
        if ($retention_days < 120) {
            $retention_days = 120;
        }
        return array(
            'max_rows' => $max_rows,
            'retention_days' => $retention_days,
        );
    }
}

if (!function_exists('cbia_usage_invalidate_dashboard_cache')) {
    function cbia_usage_invalidate_dashboard_cache() {
        foreach (array(7, 30, 90, 730) as $days) {
            delete_transient(cbia_usage_overview_cache_key($days));
            delete_transient('cbia_pro_usage_overview_v9_' . get_current_blog_id() . '_' . (int) $days);
            delete_transient('cbia_pro_usage_overview_v8_' . get_current_blog_id() . '_' . (int) $days);
        }
    }
}

if (!function_exists('cbia_usage_dashboard_format_row')) {
    function cbia_usage_dashboard_format_row($post_id, $row, $cost_settings = null, $cost_table = null, $real_adjust_multiplier = null) {
        if (!is_array($row)) {
            return null;
        }

        static $post_titles = array();
        static $author_ids = array();
        static $author_names = array();

        $post_id = (int) $post_id;
        $post_title = '';
        $author_id = 0;
        $author_name = '';
        if ($post_id > 0) {
            if (!isset($post_titles[$post_id])) {
                $title = get_the_title($post_id);
                if ($title === '') {
                    $title = 'Post #' . $post_id;
                }
                $post_titles[$post_id] = (string) $title;
            }
            if (!isset($author_ids[$post_id])) {
                $author_ids[$post_id] = (int) get_post_field('post_author', $post_id);
            }
            $post_title = (string) $post_titles[$post_id];
            $author_id = (int) $author_ids[$post_id];
            if (!isset($author_names[$author_id])) {
                $user_obj = $author_id > 0 ? get_user_by('id', $author_id) : false;
                $author_names[$author_id] = $user_obj ? (string) $user_obj->display_name : ('User #' . $author_id);
            }
            $author_name = (string) ($author_names[$author_id] ?? '');
        }

        $type = strtolower(trim((string) ($row['type'] ?? 'text')));
        if (!in_array($type, array('text', 'image', 'seo'), true)) {
            $type = 'text';
        }

        $model = (string) ($row['model'] ?? '');
        if ($model === '') {
            $model = 'unknown';
        }

        $ts_raw = (string) ($row['ts'] ?? '');
        if ($ts_raw === '') {
            $ts_raw = current_time('mysql');
        }
        $ts = strtotime($ts_raw);
        if (!$ts) {
            $ts = 0;
        }

        $day_value = '';
        if (preg_match('/\d{4}-\d{2}-\d{2}/', $ts_raw, $m_day)) {
            $day_value = (string) $m_day[0];
        } elseif ($ts > 0) {
            $day_value = wp_date('Y-m-d', $ts);
        }
        $month_value = '';
        if (preg_match('/\d{4}-\d{2}/', $ts_raw, $m_month)) {
            $month_value = (string) $m_month[0];
        } elseif ($ts > 0) {
            $month_value = wp_date('Y-m', $ts);
        }

        $section = sanitize_key((string) ($row['section'] ?? ''));
        $section_label = '';
        $section_detail = '';
        if ($type === 'image') {
            if ($section === 'featured' || $section === 'intro') {
                $section_label = 'featured';
                $section_detail = __('Featured', 'cbiastudio-blogflow-ai');
            } elseif ($section !== '') {
                $section_label = 'internal';
                $section_detail = __('Internal', 'cbiastudio-blogflow-ai') . ' - ' . ucfirst(str_replace(array('_', '-'), ' ', $section));
            }
        }

        $tokens_in = max(0, (int) ($row['in'] ?? 0));
        $tokens_out = max(0, (int) ($row['out'] ?? 0));
        $tokens_total = $tokens_in + $tokens_out;

        if ($cost_settings === null) {
            $cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
        }
        if ($cost_table === null) {
            $cost_table = function_exists('cbia_costes_price_table_usd_per_million') ? cbia_costes_price_table_usd_per_million() : array();
        }
        if ($real_adjust_multiplier === null) {
            $real_adjust_multiplier = isset($cost_settings['real_adjust_multiplier']) ? (float) $cost_settings['real_adjust_multiplier'] : 1.0;
        }
        if ($real_adjust_multiplier <= 0) {
            $real_adjust_multiplier = 1.0;
        }

        $cost = function_exists('cbia_costes_calculate_row') ? cbia_costes_calculate_row($row, $cost_settings) : array();
        $cost_micro_usd = isset($cost['cost_micro_usd']) && is_numeric($cost['cost_micro_usd']) ? (int)$cost['cost_micro_usd'] : null;
        $row_cost_usd = $cost_micro_usd === null ? null : round($cost_micro_usd / 1000000, 6);

        return array(
            'post_id' => $post_id,
            'post_title' => $post_title !== '' ? $post_title : sanitize_text_field((string)($row['title'] ?? __('Unassigned API attempt', 'cbiastudio-blogflow-ai'))),
            'post_edit_url' => $post_id > 0 ? (string) get_edit_post_link($post_id, '') : '',
            'ts' => $ts_raw,
            'sort_ts' => $ts > 0 ? $ts : 0,
            'day' => $day_value,
            'month' => $month_value,
            'type' => $type,
            'type_label' => $type === 'image' ? __('Image', 'cbiastudio-blogflow-ai') : ($type === 'seo' ? __('SEO', 'cbiastudio-blogflow-ai') : __('Text', 'cbiastudio-blogflow-ai')),
            'section' => $section,
            'section_label' => $section_label,
            'section_detail' => $section_detail,
            'attach_id' => (int) ($row['attach_id'] ?? 0),
            'model' => $model,
            'provider' => sanitize_key((string)($row['provider'] ?? 'openai')),
            'model_requested' => sanitize_text_field((string)($row['model_requested'] ?? $model)),
            'model_effective' => sanitize_text_field((string)($row['model_effective'] ?? $model)),
            'quality' => $type === 'image' ? sanitize_key((string) ($row['effective_quality'] ?? ($row['quality_effective'] ?? ($row['quality'] ?? 'auto')))) : '',
            'quality_label' => $type === 'image' && class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_quality_label($row['effective_quality'] ?? ($row['quality_effective'] ?? ($row['quality'] ?? 'auto'))) : '',
            'requested_quality' => $type === 'image' ? sanitize_key((string)($row['requested_quality'] ?? ($row['quality_requested'] ?? ($row['quality'] ?? 'auto')))) : '',
            'effective_quality' => $type === 'image' ? sanitize_key((string)($row['effective_quality'] ?? ($row['quality_effective'] ?? ''))) : '',
            'requested_quality_label' => $type === 'image' && class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_quality_label($row['requested_quality'] ?? ($row['quality_requested'] ?? ($row['quality'] ?? 'auto'))) : '',
            'effective_quality_label' => $type === 'image' && !empty($row['effective_quality'] ?? ($row['quality_effective'] ?? '')) && class_exists('CBIA_Image_Pricing_Service') ? CBIA_Image_Pricing_Service::get_quality_label($row['effective_quality'] ?? $row['quality_effective']) : '',
            'size' => $type === 'image' ? sanitize_text_field((string) ($row['effective_size'] ?? ($row['size'] ?? ''))) : '',
            'requested_size' => $type === 'image' ? sanitize_text_field((string)($row['requested_size'] ?? ($row['size'] ?? ''))) : '',
            'effective_size' => $type === 'image' ? sanitize_text_field((string)($row['effective_size'] ?? '')) : '',
            'output_format' => $type === 'image' ? sanitize_key((string)($row['output_format'] ?? '')) : '',
            'background' => $type === 'image' ? sanitize_key((string)($row['background'] ?? '')) : '',
            'image_type' => $type === 'image' ? sanitize_key((string) ($row['image_type'] ?? '')) : '',
            'tokens_in' => $tokens_in,
            'tokens_out' => $tokens_out,
            'tokens_total' => $tokens_total,
            'token_metrics_applicable' => $type !== 'image' || ($tokens_total > 0),
            'cached_in' => (int) ($row['cin'] ?? 0),
            'reasoning_tokens' => (int)($row['reasoning_tokens'] ?? 0),
            'cost_micro_usd' => $cost_micro_usd,
            'cost_usd' => $row_cost_usd,
            'cost_eur' => $row_cost_usd,
            'cost_status' => sanitize_key((string)($cost['cost_status'] ?? 'unknown')),
            'cost_source' => sanitize_key((string)($cost['cost_source'] ?? 'unavailable')),
            'cost_reason' => sanitize_key((string)($cost['cost_reason'] ?? 'insufficient_usage_data')),
            'cost_currency' => 'USD',
            'pricing_version' => sanitize_text_field((string)($cost['pricing_version'] ?? '')),
            'pricing_verified_at' => sanitize_text_field((string)($cost['pricing_verified_at'] ?? '')),
            'http_code' => (int)($row['http_code'] ?? 0),
            'request_id' => sanitize_text_field((string)($row['request_id'] ?? '')),
            'attempt_id' => sanitize_text_field((string)($row['attempt_id'] ?? '')),
            'attempt' => max(1, (int)($row['attempt'] ?? 1)),
            'parent_attempt' => max(0, (int)($row['parent_attempt'] ?? 0)),
            'elapsed_ms' => max(0, (int)($row['elapsed_ms'] ?? 0)),
            'batch_id' => sanitize_text_field((string)($row['batch_id'] ?? ($row['run_id'] ?? ''))),
            'fallback_from' => sanitize_text_field((string)($row['fallback_from'] ?? '')),
            'ok' => !empty($row['ok']) ? 1 : 0,
            'status' => sanitize_key((string)($row['status'] ?? (!empty($row['ok']) ? 'success' : 'error'))),
            'status_label' => !empty($row['ok']) ? 'OK' : (sanitize_key((string)($row['status'] ?? 'error')) === 'timeout' ? 'Timeout' : 'Error'),
            'user_id' => $author_id,
            'user_name' => $author_name,
            'source_label' => $post_title !== '' ? $post_title : ('Post #' . $post_id),
            'message_preview' => $type === 'image'
                ? __('Image generation', 'cbiastudio-blogflow-ai') . ($section_detail !== '' ? ' (' . $section_detail . ')' : '')
                : ($type === 'seo' ? __('SEO / metadata process', 'cbiastudio-blogflow-ai') : __('Text generation', 'cbiastudio-blogflow-ai')),
        );
    }
}

if (!function_exists('cbia_usage_event_store_schema_version')) {
    function cbia_usage_event_store_schema_version() {
        return 3;
    }
}

if (!function_exists('cbia_usage_event_row_identity')) {
    function cbia_usage_event_row_identity($row) {
        if (!is_array($row)) {
            return '';
        }
        $attempt_id = sanitize_text_field((string) ($row['attempt_id'] ?? ''));
        if ($attempt_id !== '') {
            return 'attempt:' . $attempt_id;
        }
        $request_id = sanitize_text_field((string) ($row['request_id'] ?? ''));
        if ($request_id !== '') {
            return 'request:' . $request_id;
        }
        return 'legacy:' . md5(wp_json_encode(array(
            (int) ($row['post_id'] ?? 0),
            (string) ($row['ts'] ?? ''),
            (string) ($row['type'] ?? ''),
            (string) ($row['provider'] ?? ''),
            (string) ($row['model'] ?? ''),
            (int) ($row['tokens_in'] ?? 0),
            (int) ($row['cached_in'] ?? 0),
            (int) ($row['tokens_out'] ?? 0),
            (string) ($row['section'] ?? ''),
            (int) ($row['attach_id'] ?? 0),
            (int) ($row['attempt'] ?? 1),
            (int) ($row['parent_attempt'] ?? 0),
            (int) ($row['http_code'] ?? 0),
            (string) ($row['status'] ?? ''),
        )));
    }
}

if (!function_exists('cbia_usage_compact_event_rows')) {
    function cbia_usage_compact_event_rows($rows) {
        if (!is_array($rows)) {
            return array();
        }
        $limits = cbia_usage_event_store_limits();
        $min_ts = time() - ((int) $limits['retention_days'] * DAY_IN_SECONDS);
        $clean = array();
        $identity_indexes = array();
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $sort_ts = isset($row['sort_ts']) ? (int) $row['sort_ts'] : 0;
            if ($sort_ts <= 0 && !empty($row['ts'])) {
                $sort_ts = strtotime((string) $row['ts']) ?: 0;
                $row['sort_ts'] = $sort_ts;
            }
            if ($sort_ts > 0 && $sort_ts < $min_ts) continue;
            if (empty($row['model'])) $row['model'] = 'unknown';
            $identity = cbia_usage_event_row_identity($row);
            if ($identity !== '' && isset($identity_indexes[$identity])) {
                $existing_index = (int) $identity_indexes[$identity];
                $existing = $clean[$existing_index];
                $merged = array_merge($existing, $row);
                if ((int) ($existing['post_id'] ?? 0) > 0 && (int) ($row['post_id'] ?? 0) <= 0) {
                    foreach (array('post_id', 'post_title', 'post_edit_url', 'user_id', 'user_name', 'source_label') as $post_key) {
                        $merged[$post_key] = $existing[$post_key] ?? ($merged[$post_key] ?? '');
                    }
                }
                $clean[$existing_index] = $merged;
                continue;
            }
            $clean[] = $row;
            if ($identity !== '') {
                $identity_indexes[$identity] = count($clean) - 1;
            }
        }
        usort($clean, static function ($a, $b) {
            $bts = (int) ($b['sort_ts'] ?? 0);
            $ats = (int) ($a['sort_ts'] ?? 0);
            if ($bts === $ats) {
                return strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? ''));
            }
            return $bts <=> $ats;
        });
        if (count($clean) > (int) $limits['max_rows']) {
            $clean = array_slice($clean, 0, (int) $limits['max_rows']);
        }
        return $clean;
    }
}

if (!function_exists('cbia_usage_save_event_store_rows')) {
    function cbia_usage_save_event_store_rows($rows) {
        $payload = array(
            'rows' => cbia_usage_compact_event_rows($rows),
            'updated_at' => time(),
            'schema_version' => cbia_usage_event_store_schema_version(),
        );
        update_option(cbia_usage_event_store_option_key(), $payload, false);
        cbia_usage_invalidate_dashboard_cache();
        return $payload['rows'];
    }
}

if (!function_exists('cbia_usage_rebuild_event_store_rows')) {
    function cbia_usage_rebuild_event_store_rows($preserved_rows = array()) {
        $cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
        $cost_table = function_exists('cbia_costes_price_table_usd_per_million') ? cbia_costes_price_table_usd_per_million() : array();
        $real_adjust_multiplier = isset($cost_settings['real_adjust_multiplier']) ? (float) $cost_settings['real_adjust_multiplier'] : 1.0;
        if ($real_adjust_multiplier <= 0) $real_adjust_multiplier = 1.0;

        $rebuilt_rows = array();
        $paged = 1;
        $per_page = 250;
        do {
            $q = new WP_Query(array(
                'post_type' => 'post',
                'post_status' => array('publish', 'future', 'draft', 'pending', 'private', 'trash'),
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'fields' => 'ids',
                'no_found_rows' => true,
                'orderby' => 'ID',
                'order' => 'DESC',
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key' => '_cbia_usage_rows', 'compare' => 'EXISTS'),
                    array('key' => '_cbia_usage_calls', 'compare' => 'EXISTS'),
                    array('key' => '_cbia_image_calls', 'compare' => 'EXISTS'),
                ),
            ));
            $ids = !empty($q->posts) && is_array($q->posts) ? $q->posts : array();
            foreach ($ids as $post_id) {
                if (!function_exists('cbia_costes_get_usage_rows_for_post')) break;
                $usage_rows = cbia_costes_get_usage_rows_for_post((int) $post_id);
                if (empty($usage_rows) || !is_array($usage_rows)) continue;
                foreach ($usage_rows as $usage_row) {
                    $formatted = cbia_usage_dashboard_format_row((int) $post_id, $usage_row, $cost_settings, $cost_table, $real_adjust_multiplier);
                    if ($formatted) $rebuilt_rows[] = $formatted;
                }
            }
            $paged++;
            $has_more = count($ids) === $per_page;
            wp_reset_postdata();
        } while ($has_more);

        $orphan_rows = function_exists('cbia_costes_get_orphan_usage_rows') ? cbia_costes_get_orphan_usage_rows() : array();
        foreach ((array)$orphan_rows as $usage_row) {
            $formatted = cbia_usage_dashboard_format_row(0, $usage_row, $cost_settings, $cost_table, $real_adjust_multiplier);
            if ($formatted) $rebuilt_rows[] = $formatted;
        }
        // Keep store-only rows (for example attempts whose post was permanently deleted)
        // while canonical post meta wins when the same event exists in both sources.
        return cbia_usage_save_event_store_rows(array_merge((array) $preserved_rows, $rebuilt_rows));
    }
}

if (!function_exists('cbia_usage_get_event_store_rows')) {
    function cbia_usage_get_event_store_rows($build_if_missing = true) {
        $opt = get_option(cbia_usage_event_store_option_key(), array());
        $rows = is_array($opt) && !empty($opt['rows']) && is_array($opt['rows']) ? $opt['rows'] : array();
        $schema_version = is_array($opt) ? (int) ($opt['schema_version'] ?? 0) : 0;
        if ($schema_version !== cbia_usage_event_store_schema_version()) {
            return cbia_usage_rebuild_event_store_rows($rows);
        }
        if (!empty($rows)) {
            $compacted = cbia_usage_compact_event_rows($rows);
            if ($compacted !== $rows) {
                return cbia_usage_save_event_store_rows($compacted);
            }
            return $compacted;
        }
        if (!$build_if_missing) {
            return array();
        }
        return cbia_usage_rebuild_event_store_rows();
    }
}

if (!function_exists('cbia_usage_store_append_row')) {
    function cbia_usage_store_append_row($post_id, $row) {
        if (!is_array($row)) return;
        $formatted = cbia_usage_dashboard_format_row((int) $post_id, $row);
        if (!$formatted) return;
        // On the first event after an update, rebuild legacy sources before append.
        $rows = cbia_usage_get_event_store_rows(true);
        $identity = cbia_usage_event_row_identity($formatted);
        $replaced = false;
        if ($identity !== '') {
            foreach ($rows as $index => $stored) {
                if (cbia_usage_event_row_identity($stored) === $identity) {
                    $rows[$index] = array_merge((array) $stored, $formatted);
                    $replaced = true;
                    break;
                }
            }
        }
        if (!$replaced) {
            $rows[] = $formatted;
        }
        cbia_usage_save_event_store_rows($rows);
    }
}

if (!function_exists('cbia_usage_migrate_openai_image_high_costs')) {
    function cbia_usage_migrate_openai_image_high_costs() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $target_version = 'openai_image_high_20260619_v1';
        if ((string) get_option('cbia_usage_cost_recalc_version', '') === $target_version) {
            return;
        }

        $lock_key = 'cbia_usage_cost_recalc_lock';
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        $posts_scanned = 0;
        $posts_updated = 0;
        $rows_updated = 0;
        $cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
        $failed_ratio = isset($cost_settings['failed_image_flat_ratio']) ? (float) $cost_settings['failed_image_flat_ratio'] : 0.35;
        if ($failed_ratio < 0) $failed_ratio = 0.0;
        if ($failed_ratio > 1) $failed_ratio = 1.0;

        $paged = 1;
        $per_page = 250;
        do {
            $q = new WP_Query(array(
                'post_type' => 'post',
                'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'fields' => 'ids',
                'no_found_rows' => true,
                'orderby' => 'ID',
                'order' => 'DESC',
                'meta_query' => array(
                    array('key' => '_cbia_usage_rows', 'compare' => 'EXISTS'),
                ),
            ));
            $ids = !empty($q->posts) && is_array($q->posts) ? $q->posts : array();
            foreach ($ids as $post_id) {
                $post_id = (int) $post_id;
                $posts_scanned++;
                $rows = get_post_meta($post_id, '_cbia_usage_rows', true);
                if (!is_array($rows) || empty($rows)) {
                    continue;
                }

                $changed = false;
                foreach ($rows as $idx => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $type = strtolower(trim((string) ($row['type'] ?? '')));
                    if ($type !== 'image') {
                        continue;
                    }
                    $model = strtolower(trim((string) ($row['model'] ?? '')));
                    if ($model === '' || strpos($model, 'gpt-image') === false) {
                        continue;
                    }

                    $flat_usd = function_exists('cbia_costes_image_flat_price_usd')
                        ? (float) cbia_costes_image_flat_price_usd($model, $cost_settings, 'high', '1536x1024')
                        : 0.0;
                    if ($flat_usd <= 0) {
                        continue;
                    }

                    $new_row = $row;
                    $new_row['image_quality'] = 'high';
                    $new_row['image_size_estimate'] = '1536x1024';
                    $new_row['cost_pricing_version'] = $target_version;

                    if (empty($new_row['ok'])) {
                        $new_row['bill_flat_usd'] = round(max(0.0, $flat_usd * $failed_ratio), 6);
                    } elseif (isset($new_row['bill_flat_usd'])) {
                        unset($new_row['bill_flat_usd']);
                    }

                    if ($new_row !== $row) {
                        $rows[$idx] = $new_row;
                        $changed = true;
                        $rows_updated++;
                    }
                }

                if ($changed) {
                    update_post_meta($post_id, '_cbia_usage_rows', $rows);
                    $posts_updated++;
                }
            }
            $paged++;
            $has_more = count($ids) === $per_page;
            wp_reset_postdata();
        } while ($has_more);

        if (function_exists('cbia_usage_rebuild_event_store_rows')) {
            cbia_usage_rebuild_event_store_rows();
        }
        if (function_exists('cbia_usage_invalidate_dashboard_cache')) {
            cbia_usage_invalidate_dashboard_cache();
        }
        foreach (array(7, 30, 90, 730) as $days) {
            foreach (array('v5', 'v7', 'v8', 'v9') as $ver) {
                delete_transient('cbia_pro_usage_overview_' . $ver . '_' . get_current_blog_id() . '_' . (int) $days);
            }
        }

        update_option('cbia_usage_cost_recalc_version', $target_version, false);
        update_option('cbia_usage_cost_recalc_last', array(
            'version' => $target_version,
            'time' => current_time('mysql'),
            'posts_scanned' => $posts_scanned,
            'posts_updated' => $posts_updated,
            'rows_updated' => $rows_updated,
        ), false);
        delete_transient($lock_key);
    }
}

if (!function_exists('cbia_usage_fill_daily_series')) {
    function cbia_usage_fill_daily_series($daily_map, $days) {
        $out = array();
        $days = max(1, (int) $days);
        $now_ts = current_time('timestamp');
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day_key = wp_date('Y-m-d', $now_ts - ($offset * DAY_IN_SECONDS));
            $item = isset($daily_map[$day_key]) && is_array($daily_map[$day_key]) ? $daily_map[$day_key] : array();
            $out[] = array(
                'day' => $day_key,
                'calls' => (int) ($item['calls'] ?? 0),
                'text' => (int) ($item['text'] ?? 0),
                'image' => (int) ($item['image'] ?? 0),
                'seo' => (int) ($item['seo'] ?? 0),
                'textCalls' => (int) ($item['textCalls'] ?? 0),
                'imageCalls' => (int) ($item['imageCalls'] ?? 0),
                'seoCalls' => (int) ($item['seoCalls'] ?? 0),
                'totalTokens' => (int) (($item['text'] ?? 0) + ($item['image'] ?? 0) + ($item['seo'] ?? 0)),
            );
        }
        return $out;
    }
}

if (!function_exists('cbia_usage_fill_yearly_month_series')) {
    function cbia_usage_fill_yearly_month_series($monthly_map) {
        $year = (int) wp_date('Y', current_time('timestamp'));
        $out = array();
        for ($month = 1; $month <= 12; $month++) {
            $month_key = sprintf('%04d-%02d', $year, $month);
            $item = isset($monthly_map[$month_key]) && is_array($monthly_map[$month_key]) ? $monthly_map[$month_key] : array();
            $out[] = array(
                'month' => $month_key,
                'calls' => (int) ($item['calls'] ?? 0),
                'text_calls' => (int) ($item['text_calls'] ?? 0),
                'image_calls' => (int) ($item['image_calls'] ?? 0),
                'seo_calls' => (int) ($item['seo_calls'] ?? 0),
                'text_cost_eur' => round((float) ($item['text_cost_eur'] ?? 0), 6),
                'image_cost_eur' => round((float) ($item['image_cost_eur'] ?? 0), 6),
                'seo_cost_eur' => round((float) ($item['seo_cost_eur'] ?? 0), 6),
                'cost_eur' => round((float) ($item['cost_eur'] ?? 0), 6),
            );
        }
        return $out;
    }
}

if (!function_exists('cbia_usage_build_payload_from_store')) {
    function cbia_usage_build_payload_from_store($rows, $days, $requested_model, $recent_rows_limit) {
        $now_ts = current_time('timestamp');
        $since_ts = $now_ts - (($days - 1) * DAY_IN_SECONDS);
        $since_day = wp_date('Y-m-d', $since_ts);
        $period_end_day = wp_date('Y-m-d', $now_ts);
        $init_summary = static function () {
            return array(
                'total_calls' => 0,
                'post_ids' => array(),
                'user_ids' => array(),
                'total_tokens' => 0,
                'total_cost' => 0.0,
                'known_cost_events' => 0,
                'unknown_cost_events' => 0,
                'cost_status_counts' => array(
                    'exact' => 0,
                    'estimated' => 0,
                    'unknown' => 0,
                    'official_reconciled' => 0,
                ),
                'daily' => array(),
                'monthly' => array(),
                'type_counts' => array(
                    'text' => 0,
                    'image' => 0,
                    'seo' => 0,
                ),
            );
        };

        $available_models = array();
        $recent_rows = array();
        $total_rows = 0;
        $daily_buckets = array();
        $monthly_buckets = array();
        $summary_rows = array('__all__' => $init_summary());

        foreach ((array) $rows as $row) {
            if (!is_array($row)) continue;
            $sort_ts = (int) ($row['sort_ts'] ?? 0);
            $day_value = (string) ($row['day'] ?? '');
            $is_in_period = true;
            if ($sort_ts > 0 && $sort_ts < $since_ts) {
                $is_in_period = false;
            } elseif ($sort_ts <= 0 && $day_value !== '' && $day_value < $since_day) {
                $is_in_period = false;
            }
            if (!$is_in_period) continue;

            $model = (string) ($row['model'] ?? 'unknown');
            if ($model === '') $model = 'unknown';
            $type = strtolower(trim((string) ($row['type'] ?? 'text')));
            if (!in_array($type, array('text', 'image', 'seo'), true)) $type = 'text';
            $month_value = (string) ($row['month'] ?? '');
            $tokens_total = (int) ($row['tokens_total'] ?? 0);
            $cost_value = $row['cost_eur'] ?? null;
            $post_id = (int) ($row['post_id'] ?? 0);
            $user_id = (int) ($row['user_id'] ?? 0);

            $available_models[$model] = true;
            $total_rows++;
            if (count($recent_rows) < $recent_rows_limit) {
                $recent_rows[] = $row;
            }

            if ($day_value !== '') {
                if (!isset($daily_buckets[$day_value])) {
                    $daily_buckets[$day_value] = array('calls' => 0, 'text' => 0, 'image' => 0, 'seo' => 0, 'textCalls' => 0, 'imageCalls' => 0, 'seoCalls' => 0);
                }
                $daily_buckets[$day_value]['calls'] += 1;
                if ($type === 'image') {
                    $daily_buckets[$day_value]['image'] += 1;
                    $daily_buckets[$day_value]['imageCalls'] += 1;
                } elseif ($type === 'seo') {
                    $daily_buckets[$day_value]['seo'] += $tokens_total;
                    $daily_buckets[$day_value]['seoCalls'] += 1;
                } else {
                    $daily_buckets[$day_value]['text'] += $tokens_total;
                    $daily_buckets[$day_value]['textCalls'] += 1;
                }
            }

            if ($month_value !== '') {
                if (!isset($monthly_buckets[$month_value])) {
                    $monthly_buckets[$month_value] = array(
                        'calls' => 0, 'text_calls' => 0, 'image_calls' => 0, 'seo_calls' => 0,
                        'text_cost_eur' => 0.0, 'image_cost_eur' => 0.0, 'seo_cost_eur' => 0.0, 'cost_eur' => 0.0,
                    );
                }
                $monthly_buckets[$month_value]['calls'] += 1;
                if ($type === 'image') $monthly_buckets[$month_value]['image_calls'] += 1;
                elseif ($type === 'seo') $monthly_buckets[$month_value]['seo_calls'] += 1;
                else $monthly_buckets[$month_value]['text_calls'] += 1;
                if ($cost_value !== null && $cost_value !== '' && is_numeric($cost_value)) {
                    $cost_f = (float) $cost_value;
                    $monthly_buckets[$month_value]['cost_eur'] += $cost_f;
                    if ($type === 'image') $monthly_buckets[$month_value]['image_cost_eur'] += $cost_f;
                    elseif ($type === 'seo') $monthly_buckets[$month_value]['seo_cost_eur'] += $cost_f;
                    else $monthly_buckets[$month_value]['text_cost_eur'] += $cost_f;
                }
            }

            if (!isset($summary_rows[$model])) $summary_rows[$model] = $init_summary();
            foreach (array('__all__', $model) as $summary_key) {
                $summary_rows[$summary_key]['total_calls'] += 1;
                if ($post_id > 0) $summary_rows[$summary_key]['post_ids'][$post_id] = true;
                if ($user_id > 0) $summary_rows[$summary_key]['user_ids'][$user_id] = true;
                $summary_rows[$summary_key]['total_tokens'] += $tokens_total;
                if ($cost_value !== null && $cost_value !== '' && is_numeric($cost_value)) {
                    $summary_rows[$summary_key]['total_cost'] += (float) $cost_value;
                    $summary_rows[$summary_key]['known_cost_events'] += 1;
                } else {
                    $summary_rows[$summary_key]['unknown_cost_events'] += 1;
                }
                $cost_status = sanitize_key((string)($row['cost_status'] ?? 'unknown'));
                if (!isset($summary_rows[$summary_key]['cost_status_counts'][$cost_status])) $cost_status = 'unknown';
                $summary_rows[$summary_key]['cost_status_counts'][$cost_status] += 1;
                if (!isset($summary_rows[$summary_key]['type_counts'][$type])) {
                    $summary_rows[$summary_key]['type_counts'][$type] = 0;
                }
                $summary_rows[$summary_key]['type_counts'][$type] += 1;

                if ($day_value !== '') {
                    if (!isset($summary_rows[$summary_key]['daily'][$day_value])) {
                        $summary_rows[$summary_key]['daily'][$day_value] = array('calls' => 0, 'text' => 0, 'image' => 0, 'seo' => 0, 'textCalls' => 0, 'imageCalls' => 0, 'seoCalls' => 0);
                    }
                    $summary_rows[$summary_key]['daily'][$day_value]['calls'] += 1;
                    if ($type === 'image') {
                        $summary_rows[$summary_key]['daily'][$day_value]['image'] += 1;
                        $summary_rows[$summary_key]['daily'][$day_value]['imageCalls'] += 1;
                    } elseif ($type === 'seo') {
                        $summary_rows[$summary_key]['daily'][$day_value]['seo'] += $tokens_total;
                        $summary_rows[$summary_key]['daily'][$day_value]['seoCalls'] += 1;
                    } else {
                        $summary_rows[$summary_key]['daily'][$day_value]['text'] += $tokens_total;
                        $summary_rows[$summary_key]['daily'][$day_value]['textCalls'] += 1;
                    }
                }

                if ($month_value !== '') {
                    if (!isset($summary_rows[$summary_key]['monthly'][$month_value])) {
                        $summary_rows[$summary_key]['monthly'][$month_value] = array(
                            'calls' => 0, 'text_calls' => 0, 'image_calls' => 0, 'seo_calls' => 0,
                            'text_cost_eur' => 0.0, 'image_cost_eur' => 0.0, 'seo_cost_eur' => 0.0, 'cost_eur' => 0.0,
                        );
                    }
                    $summary_rows[$summary_key]['monthly'][$month_value]['calls'] += 1;
                    if ($type === 'image') $summary_rows[$summary_key]['monthly'][$month_value]['image_calls'] += 1;
                    elseif ($type === 'seo') $summary_rows[$summary_key]['monthly'][$month_value]['seo_calls'] += 1;
                    else $summary_rows[$summary_key]['monthly'][$month_value]['text_calls'] += 1;
                    if ($cost_value !== null && $cost_value !== '' && is_numeric($cost_value)) {
                        $cost_f = (float) $cost_value;
                        $summary_rows[$summary_key]['monthly'][$month_value]['cost_eur'] += $cost_f;
                        if ($type === 'image') $summary_rows[$summary_key]['monthly'][$month_value]['image_cost_eur'] += $cost_f;
                        elseif ($type === 'seo') $summary_rows[$summary_key]['monthly'][$month_value]['seo_cost_eur'] += $cost_f;
                        else $summary_rows[$summary_key]['monthly'][$month_value]['text_cost_eur'] += $cost_f;
                    }
                }
            }
        }

        $daily_series = cbia_usage_fill_daily_series($daily_buckets, $days);
        $monthly_series = cbia_usage_fill_yearly_month_series($monthly_buckets);
        $model_options = array_keys($available_models);
        sort($model_options, SORT_STRING);

        $summaries_by_model = array();
        foreach ($summary_rows as $summary_key => $summary) {
            $post_count = count((array) $summary['post_ids']);
            $calls = (int) ($summary['total_calls'] ?? 0);
            $summaries_by_model[$summary_key] = array(
                'totalCalls' => $calls,
                'uniquePosts' => $post_count,
                'uniqueUsers' => count((array) $summary['user_ids']),
                'totalTokens' => (int) ($summary['total_tokens'] ?? 0),
                'avgTokens' => $calls > 0 ? (int) round(((int) ($summary['total_tokens'] ?? 0)) / $calls) : 0,
                'totalCost' => round((float) ($summary['total_cost'] ?? 0), 6),
                'avgCostPerPost' => $post_count > 0 ? round(((float) ($summary['total_cost'] ?? 0)) / $post_count, 6) : 0.0,
                'knownCostEvents' => (int)($summary['known_cost_events'] ?? 0),
                'unknownCostEvents' => (int)($summary['unknown_cost_events'] ?? 0),
                'costCoveragePercent' => $calls > 0 ? round(100 * ((int)($summary['known_cost_events'] ?? 0)) / $calls, 1) : 0,
                'costStatusCounts' => (array)($summary['cost_status_counts'] ?? array()),
                'typeCounts' => array(
                    'text' => (int) ($summary['type_counts']['text'] ?? 0),
                    'image' => (int) ($summary['type_counts']['image'] ?? 0),
                    'seo' => (int) ($summary['type_counts']['seo'] ?? 0),
                ),
                'dailySeries' => cbia_usage_fill_daily_series((array) ($summary['daily'] ?? array()), $days),
                'monthlySeries' => cbia_usage_fill_yearly_month_series((array) ($summary['monthly'] ?? array())),
            );
        }

        foreach ($recent_rows as &$recent_row) {
            unset($recent_row['sort_ts']);
        }
        unset($recent_row);

        return array(
            'rows' => $recent_rows,
            'totalRows' => $total_rows,
            'rowsLimited' => $total_rows > count($recent_rows),
            'recentRowsLimit' => $recent_rows_limit,
            'dailySeries' => $daily_series,
            'monthlySeries' => $monthly_series,
            'summariesByModel' => $summaries_by_model,
            'modelOptions' => $model_options,
            'defaultModel' => $requested_model,
            'periodDays' => $days,
            'periodStartDay' => $since_day,
            'periodEndDay' => $period_end_day,
        );
    }
}

if (!function_exists('cbia_get_usage_dashboard_payload_fast')) {
    function cbia_get_usage_dashboard_payload_fast($days = 30, $requested_model = '') {
        $allowed_days = array(7, 30, 90, 730);
        $days = (int) $days;
        if (!in_array($days, $allowed_days, true)) {
            $days = 30;
        }

        $requested_model = sanitize_text_field((string) $requested_model);
        $now_ts = current_time('timestamp');
        $since_ts = $now_ts - (($days - 1) * DAY_IN_SECONDS);
        $since_day = wp_date('Y-m-d', $since_ts);
        $period_end_day = wp_date('Y-m-d', $now_ts);
        $cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
        $cost_table = function_exists('cbia_costes_price_table_usd_per_million') ? cbia_costes_price_table_usd_per_million() : array();
        $real_adjust_multiplier = isset($cost_settings['real_adjust_multiplier']) ? (float) $cost_settings['real_adjust_multiplier'] : 1.0;
        if ($real_adjust_multiplier <= 0) {
            $real_adjust_multiplier = 1.0;
        }

        $cache_ttl = (int) apply_filters('cbia_pro_usage_cache_ttl', 15 * MINUTE_IN_SECONDS);
        if ($cache_ttl < 0) {
            $cache_ttl = 0;
        }
        $recent_rows_limit = (int) apply_filters('cbia_pro_usage_recent_rows_limit', 5000);
        if ($recent_rows_limit < 20) {
            $recent_rows_limit = 20;
        }

        $cache_key = cbia_usage_overview_cache_key($days);
        $cached_usage = $cache_ttl > 0 ? get_transient($cache_key) : false;
        $cached_has_payload = is_array($cached_usage) && array_key_exists('total_rows', $cached_usage);
        if ($cached_has_payload) {
            return array(
                'rows' => is_array($cached_usage['recent_rows'] ?? null) ? $cached_usage['recent_rows'] : array(),
                'totalRows' => (int) ($cached_usage['total_rows'] ?? 0),
                'rowsLimited' => !empty($cached_usage['rows_limited']),
                'recentRowsLimit' => $recent_rows_limit,
                'dailySeries' => is_array($cached_usage['daily_series'] ?? null) ? $cached_usage['daily_series'] : array(),
                'monthlySeries' => is_array($cached_usage['monthly_series'] ?? null) ? $cached_usage['monthly_series'] : array(),
                'summariesByModel' => is_array($cached_usage['summaries_by_model'] ?? null) ? $cached_usage['summaries_by_model'] : array(),
                'modelOptions' => is_array($cached_usage['model_options'] ?? null) ? $cached_usage['model_options'] : array(),
                'defaultModel' => $requested_model,
                'periodDays' => $days,
                'periodStartDay' => (string) ($cached_usage['period_start_day'] ?? $since_day),
                'periodEndDay' => (string) ($cached_usage['period_end_day'] ?? $period_end_day),
            );
        }

        $store_rows = cbia_usage_get_event_store_rows(true);
        if (!empty($store_rows)) {
            $payload = cbia_usage_build_payload_from_store($store_rows, $days, $requested_model, $recent_rows_limit);
            if ($cache_ttl > 0) {
                set_transient($cache_key, array(
                    'recent_rows' => is_array($payload['rows'] ?? null) ? $payload['rows'] : array(),
                    'total_rows' => (int) ($payload['totalRows'] ?? 0),
                    'rows_limited' => !empty($payload['rowsLimited']),
                    'daily_series' => is_array($payload['dailySeries'] ?? null) ? $payload['dailySeries'] : array(),
                    'monthly_series' => is_array($payload['monthlySeries'] ?? null) ? $payload['monthlySeries'] : array(),
                    'model_options' => is_array($payload['modelOptions'] ?? null) ? $payload['modelOptions'] : array(),
                    'summaries_by_model' => is_array($payload['summariesByModel'] ?? null) ? $payload['summariesByModel'] : array(),
                    'period_start_day' => (string) ($payload['periodStartDay'] ?? $since_day),
                    'period_end_day' => (string) ($payload['periodEndDay'] ?? $period_end_day),
                ), $cache_ttl);
            }
            return $payload;
        }

        $init_summary = static function () {
            return array(
                'total_calls' => 0,
                'post_ids' => array(),
                'user_ids' => array(),
                'total_tokens' => 0,
                'total_cost' => 0.0,
                'daily' => array(),
                'monthly' => array(),
                'type_counts' => array(
                    'text' => 0,
                    'image' => 0,
                    'seo' => 0,
                ),
            );
        };
        $push_recent_row = static function (&$rows, $row, $sort_ts, $seq, $limit) {
            $row['__sort_ts'] = (int) $sort_ts;
            $row['__seq'] = (int) $seq;
            $rows[] = $row;
            if (count($rows) <= $limit) {
                return;
            }
            $oldest_idx = 0;
            $oldest_sort = (int) ($rows[0]['__sort_ts'] ?? 0);
            $oldest_seq = (int) ($rows[0]['__seq'] ?? 0);
            $count = count($rows);
            for ($i = 1; $i < $count; $i++) {
                $candidate_sort = (int) ($rows[$i]['__sort_ts'] ?? 0);
                $candidate_seq = (int) ($rows[$i]['__seq'] ?? 0);
                if ($candidate_sort < $oldest_sort || ($candidate_sort === $oldest_sort && $candidate_seq < $oldest_seq)) {
                    $oldest_idx = $i;
                    $oldest_sort = $candidate_sort;
                    $oldest_seq = $candidate_seq;
                }
            }
            unset($rows[$oldest_idx]);
            $rows = array_values($rows);
        };

        $available_models = array();
        $author_names = array();
        $recent_rows = array();
        $total_rows = 0;
        $row_seq = 0;
        $daily_buckets = array();
        $monthly_buckets = array();
        $summary_rows = array(
            '__all__' => $init_summary(),
        );

        $query_page = 1;
        $query_limit = 200;
        do {
            $query = new WP_Query(array(
                'post_type' => 'post',
                'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
                'posts_per_page' => $query_limit,
                'paged' => $query_page,
                'fields' => 'ids',
                'no_found_rows' => true,
                'orderby' => 'ID',
                'order' => 'DESC',
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key' => '_cbia_usage_rows', 'compare' => 'EXISTS'),
                    array('key' => '_cbia_usage_calls', 'compare' => 'EXISTS'),
                    array('key' => '_cbia_image_calls', 'compare' => 'EXISTS'),
                    array('key' => '_cbia_tokens_total_sum', 'compare' => 'EXISTS'),
                ),
            ));
            $post_ids = !empty($query->posts) && is_array($query->posts) ? $query->posts : array();

            foreach ($post_ids as $post_id) {
                if (!function_exists('cbia_costes_get_usage_rows_for_post')) {
                    break;
                }
                $usage_rows = cbia_costes_get_usage_rows_for_post((int) $post_id);
                if (empty($usage_rows) || !is_array($usage_rows)) {
                    continue;
                }

                $post_id = (int) $post_id;
                $post_context_loaded = false;
                $post_title = '';
                $author_id = 0;
                $legacy_image_calls = null;

                foreach ($usage_rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $ts_raw = isset($row['ts']) ? (string) $row['ts'] : '';
                    $ts = $ts_raw !== '' ? strtotime($ts_raw) : 0;

                    $type = isset($row['type']) ? strtolower(trim((string) $row['type'])) : 'text';
                    if (!in_array($type, array('text', 'image', 'seo'), true)) {
                        $type = 'text';
                    }

                    $model = (string) ($row['model'] ?? '');
                    if ($model === '') {
                        $model = 'unknown';
                    }

                    $attach_id = (int) ($row['attach_id'] ?? 0);
                    $section = sanitize_key((string) ($row['section'] ?? ''));
                    if ($type === 'image' && $section === '') {
                        if ($legacy_image_calls === null) {
                            $legacy_image_calls = array();
                            $raw_legacy_images = get_post_meta((int) $post_id, '_cbia_image_calls', true);
                            if (is_string($raw_legacy_images) && $raw_legacy_images !== '') {
                                $decoded_legacy_images = json_decode($raw_legacy_images, true);
                                if (is_array($decoded_legacy_images)) {
                                    $legacy_image_calls = $decoded_legacy_images;
                                }
                            } elseif (is_array($raw_legacy_images)) {
                                $legacy_image_calls = $raw_legacy_images;
                            }
                        }
                        if (!empty($legacy_image_calls)) {
                            foreach ($legacy_image_calls as $legacy_image_call) {
                                if (!is_array($legacy_image_call)) {
                                    continue;
                                }
                                $legacy_attach = isset($legacy_image_call['attach_id']) ? (int) $legacy_image_call['attach_id'] : 0;
                                $legacy_model = isset($legacy_image_call['model']) ? (string) $legacy_image_call['model'] : '';
                                $legacy_ts = isset($legacy_image_call['ts']) ? (string) $legacy_image_call['ts'] : '';
                                $same_attach = $attach_id > 0 && $legacy_attach > 0 && $attach_id === $legacy_attach;
                                $same_signature = !$same_attach && $legacy_model !== '' && $legacy_model === $model && $legacy_ts !== '' && $legacy_ts === $ts_raw;
                                if ($same_attach || $same_signature) {
                                    $section = sanitize_key((string) ($legacy_image_call['section'] ?? ''));
                                    if ($section !== '') {
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    $section_label = '';
                    $section_detail = '';
                    if ($type === 'image') {
                        if ($section === 'featured' || $section === 'intro') {
                            $section_label = 'featured';
                            $section_detail = __('Featured', 'cbiastudio-blogflow-ai');
                        } elseif ($section !== '') {
                            $section_label = 'internal';
                            $section_detail = __('Internal', 'cbiastudio-blogflow-ai') . ' - ' . ucfirst(str_replace(array('_', '-'), ' ', $section));
                        }
                    }

                    $tokens_in = (int) ($row['in'] ?? 0);
                    $tokens_out = (int) ($row['out'] ?? 0);
                    $tokens_total = $tokens_in + $tokens_out;
                    $is_ok = !empty($row['ok']);
                    $row_cost_eur = null;
                    if (function_exists('cbia_costes_calc_row_eur')) {
                        $row_cost_eur = cbia_costes_calc_row_eur($row, $cost_settings, $cost_table);
                        if ($row_cost_eur !== null && $real_adjust_multiplier !== 1.0) {
                            $row_cost_eur = (float) $row_cost_eur * $real_adjust_multiplier;
                        }
                        if ($row_cost_eur !== null) {
                            $row_cost_eur = round((float) $row_cost_eur, 6);
                        }
                    }

                    $day_value = '';
                    if (preg_match('/\d{4}-\d{2}-\d{2}/', $ts_raw, $m_day)) {
                        $day_value = (string) $m_day[0];
                    } elseif ($ts) {
                        $day_value = wp_date('Y-m-d', $ts);
                    }
                    $month_value = '';
                    if (preg_match('/\d{4}-\d{2}/', $ts_raw, $m_month)) {
                        $month_value = (string) $m_month[0];
                    } elseif ($ts) {
                        $month_value = wp_date('Y-m', $ts);
                    }

                    $is_in_period = true;
                    if ($ts > 0 && $ts < $since_ts) {
                        $is_in_period = false;
                    } elseif ($ts <= 0 && $day_value !== '' && $day_value < $since_day) {
                        $is_in_period = false;
                    }
                    if (!$is_in_period) {
                        continue;
                    }

                    if (!$post_context_loaded) {
                        $post_title = get_the_title($post_id);
                        if ($post_title === '') {
                            $post_title = 'Post #' . $post_id;
                        }
                        $author_id = (int) get_post_field('post_author', $post_id);
                        if (!isset($author_names[$author_id])) {
                            $user_obj = $author_id > 0 ? get_user_by('id', $author_id) : false;
                            $author_names[$author_id] = $user_obj ? (string) $user_obj->display_name : ('User #' . $author_id);
                        }
                        $post_context_loaded = true;
                    }

                    $available_models[$model] = true;
                    $total_rows++;
                    $row_seq++;

                    $row_entry = array(
                        'post_id' => $post_id,
                        'post_title' => (string) $post_title,
                        'post_edit_url' => (string) get_edit_post_link($post_id, ''),
                        'ts' => $ts_raw,
                        'day' => $day_value,
                        'month' => $month_value,
                        'type' => $type,
                        'type_label' => $type === 'image' ? __('Image', 'cbiastudio-blogflow-ai') : ($type === 'seo' ? __('SEO', 'cbiastudio-blogflow-ai') : __('Text', 'cbiastudio-blogflow-ai')),
                        'section' => $section,
                        'section_label' => $section_label,
                        'section_detail' => $section_detail,
                        'attach_id' => $attach_id,
                        'model' => $model,
                        'tokens_in' => $tokens_in,
                        'tokens_out' => $tokens_out,
                        'tokens_total' => $tokens_total,
                        'token_metrics_applicable' => $type !== 'image',
                        'cached_in' => (int) ($row['cin'] ?? 0),
                        'cost_eur' => $row_cost_eur,
                        'ok' => $is_ok ? 1 : 0,
                        'status_label' => $is_ok ? 'OK' : 'Error',
                        'user_id' => $author_id,
                        'user_name' => (string) $author_names[$author_id],
                        'source_label' => (string) $post_title,
                        'message_preview' => $type === 'image'
                            ? __('Image generation', 'cbiastudio-blogflow-ai') . ($section_detail !== '' ? ' (' . $section_detail . ')' : '')
                            : ($type === 'seo' ? __('SEO / metadata process', 'cbiastudio-blogflow-ai') : __('Text generation', 'cbiastudio-blogflow-ai')),
                    );

                    $sort_ts = $ts > 0 ? $ts : 0;
                    if ($sort_ts <= 0 && $day_value !== '') {
                        $sort_ts = strtotime($day_value . ' 23:59:59');
                        if (!$sort_ts) {
                            $sort_ts = 0;
                        }
                    }
                    $push_recent_row($recent_rows, $row_entry, (int) $sort_ts, $row_seq, $recent_rows_limit);

                    if ($day_value !== '') {
                        if (!isset($daily_buckets[$day_value])) {
                            $daily_buckets[$day_value] = array(
                                'day' => $day_value,
                                'calls' => 0,
                                'text' => 0,
                                'image' => 0,
                                'seo' => 0,
                                'textCalls' => 0,
                                'imageCalls' => 0,
                                'seoCalls' => 0,
                            );
                        }
                        $daily_buckets[$day_value]['calls'] += 1;
                        if (isset($daily_buckets[$day_value][$type])) {
                            $daily_buckets[$day_value][$type] += $type === 'image' ? 1 : $tokens_total;
                        }
                        $count_key = $type . 'Calls';
                        if (isset($daily_buckets[$day_value][$count_key])) {
                            $daily_buckets[$day_value][$count_key] += 1;
                        }
                    }

                    if ($month_value !== '') {
                        if (!isset($monthly_buckets[$month_value])) {
                            $monthly_buckets[$month_value] = array(
                                'month' => $month_value,
                                'calls' => 0,
                                'text_calls' => 0,
                                'image_calls' => 0,
                                'seo_calls' => 0,
                                'text_cost_eur' => 0.0,
                                'image_cost_eur' => 0.0,
                                'seo_cost_eur' => 0.0,
                                'cost_eur' => 0.0,
                            );
                        }
                        $monthly_buckets[$month_value]['calls'] += 1;
                        if ($type === 'image') {
                            $monthly_buckets[$month_value]['image_calls'] += 1;
                        } elseif ($type === 'seo') {
                            $monthly_buckets[$month_value]['seo_calls'] += 1;
                        } else {
                            $monthly_buckets[$month_value]['text_calls'] += 1;
                        }
                        if ($row_cost_eur !== null && $row_cost_eur !== '' && is_numeric($row_cost_eur)) {
                            $row_cost_float = (float) $row_cost_eur;
                            $monthly_buckets[$month_value]['cost_eur'] += $row_cost_float;
                            if ($type === 'image') {
                                $monthly_buckets[$month_value]['image_cost_eur'] += $row_cost_float;
                            } elseif ($type === 'seo') {
                                $monthly_buckets[$month_value]['seo_cost_eur'] += $row_cost_float;
                            } else {
                                $monthly_buckets[$month_value]['text_cost_eur'] += $row_cost_float;
                            }
                        }
                    }

                    if (!isset($summary_rows[$model])) {
                        $summary_rows[$model] = $init_summary();
                    }
                    foreach (array('__all__', $model) as $summary_key) {
                        $summary_rows[$summary_key]['total_calls'] += 1;
                        if ($post_id > 0) {
                            $summary_rows[$summary_key]['post_ids'][$post_id] = true;
                        }
                        if ($author_id > 0) {
                            $summary_rows[$summary_key]['user_ids'][$author_id] = true;
                        }
                        $summary_rows[$summary_key]['total_tokens'] += $tokens_total;
                        if ($row_cost_eur !== null && $row_cost_eur !== '' && is_numeric($row_cost_eur)) {
                            $summary_rows[$summary_key]['total_cost'] += (float) $row_cost_eur;
                        }
                        if (!isset($summary_rows[$summary_key]['type_counts'][$type])) {
                            $summary_rows[$summary_key]['type_counts'][$type] = 0;
                        }
                        $summary_rows[$summary_key]['type_counts'][$type] += 1;

                        if ($day_value !== '') {
                            if (!isset($summary_rows[$summary_key]['daily'][$day_value])) {
                                $summary_rows[$summary_key]['daily'][$day_value] = array(
                                    'day' => $day_value,
                                    'calls' => 0,
                                    'text' => 0,
                                    'image' => 0,
                                    'seo' => 0,
                                    'textCalls' => 0,
                                    'imageCalls' => 0,
                                    'seoCalls' => 0,
                                );
                            }
                            $summary_rows[$summary_key]['daily'][$day_value]['calls'] += 1;
                            if ($type === 'image') {
                                $summary_rows[$summary_key]['daily'][$day_value]['image'] += 1;
                                $summary_rows[$summary_key]['daily'][$day_value]['imageCalls'] += 1;
                            } elseif ($type === 'seo') {
                                $summary_rows[$summary_key]['daily'][$day_value]['seo'] += $tokens_total;
                                $summary_rows[$summary_key]['daily'][$day_value]['seoCalls'] += 1;
                            } else {
                                $summary_rows[$summary_key]['daily'][$day_value]['text'] += $tokens_total;
                                $summary_rows[$summary_key]['daily'][$day_value]['textCalls'] += 1;
                            }
                        }

                        if ($month_value !== '') {
                            if (!isset($summary_rows[$summary_key]['monthly'][$month_value])) {
                                $summary_rows[$summary_key]['monthly'][$month_value] = array(
                                    'month' => $month_value,
                                    'calls' => 0,
                                    'text_calls' => 0,
                                    'image_calls' => 0,
                                    'seo_calls' => 0,
                                    'text_cost_eur' => 0.0,
                                    'image_cost_eur' => 0.0,
                                    'seo_cost_eur' => 0.0,
                                    'cost_eur' => 0.0,
                                );
                            }
                            $summary_rows[$summary_key]['monthly'][$month_value]['calls'] += 1;
                            if ($type === 'image') {
                                $summary_rows[$summary_key]['monthly'][$month_value]['image_calls'] += 1;
                            } elseif ($type === 'seo') {
                                $summary_rows[$summary_key]['monthly'][$month_value]['seo_calls'] += 1;
                            } else {
                                $summary_rows[$summary_key]['monthly'][$month_value]['text_calls'] += 1;
                            }
                            if ($row_cost_eur !== null && $row_cost_eur !== '' && is_numeric($row_cost_eur)) {
                                $row_cost_float = (float) $row_cost_eur;
                                $summary_rows[$summary_key]['monthly'][$month_value]['cost_eur'] += $row_cost_float;
                                if ($type === 'image') {
                                    $summary_rows[$summary_key]['monthly'][$month_value]['image_cost_eur'] += $row_cost_float;
                                } elseif ($type === 'seo') {
                                    $summary_rows[$summary_key]['monthly'][$month_value]['seo_cost_eur'] += $row_cost_float;
                                } else {
                                    $summary_rows[$summary_key]['monthly'][$month_value]['text_cost_eur'] += $row_cost_float;
                                }
                            }
                        }
                    }
                }
            }

            $query_page++;
            $has_more = count($post_ids) === $query_limit;
            wp_reset_postdata();
        } while ($has_more);

        usort($recent_rows, static function ($a, $b) {
            $bts = (int) ($b['__sort_ts'] ?? 0);
            $ats = (int) ($a['__sort_ts'] ?? 0);
            if ($bts === $ats) {
                return (int) ($b['__seq'] ?? 0) <=> (int) ($a['__seq'] ?? 0);
            }
            return $bts <=> $ats;
        });
        foreach ($recent_rows as &$recent_row) {
            unset($recent_row['__sort_ts'], $recent_row['__seq']);
        }
        unset($recent_row);

        ksort($daily_buckets, SORT_STRING);
        $daily_series = array_values(array_map(static function ($item) {
            $item['totalTokens'] = (int) (($item['text'] ?? 0) + ($item['image'] ?? 0) + ($item['seo'] ?? 0));
            return $item;
        }, $daily_buckets));

        ksort($monthly_buckets, SORT_STRING);
        $monthly_series = array_values(array_map(static function ($item) {
            $item['cost_eur'] = round((float) ($item['cost_eur'] ?? 0), 6);
            $item['text_cost_eur'] = round((float) ($item['text_cost_eur'] ?? 0), 6);
            $item['image_cost_eur'] = round((float) ($item['image_cost_eur'] ?? 0), 6);
            $item['seo_cost_eur'] = round((float) ($item['seo_cost_eur'] ?? 0), 6);
            return $item;
        }, $monthly_buckets));

        $model_options = array_keys($available_models);
        sort($model_options, SORT_STRING);

        $summaries_by_model = array();
        foreach ($summary_rows as $summary_key => $summary) {
            ksort($summary['daily'], SORT_STRING);
            ksort($summary['monthly'], SORT_STRING);
            $normalized_daily = array_values(array_map(static function ($item) {
                $item['totalTokens'] = (int) (($item['text'] ?? 0) + ($item['image'] ?? 0) + ($item['seo'] ?? 0));
                return $item;
            }, $summary['daily']));
            $normalized_monthly = array_values(array_map(static function ($item) {
                $item['cost_eur'] = round((float) ($item['cost_eur'] ?? 0), 6);
                $item['text_cost_eur'] = round((float) ($item['text_cost_eur'] ?? 0), 6);
                $item['image_cost_eur'] = round((float) ($item['image_cost_eur'] ?? 0), 6);
                $item['seo_cost_eur'] = round((float) ($item['seo_cost_eur'] ?? 0), 6);
                return $item;
            }, $summary['monthly']));
            $post_count = count($summary['post_ids']);
            $total_calls = (int) ($summary['total_calls'] ?? 0);
            $summaries_by_model[$summary_key] = array(
                'totalCalls' => $total_calls,
                'uniquePosts' => $post_count,
                'uniqueUsers' => count($summary['user_ids']),
                'totalTokens' => (int) ($summary['total_tokens'] ?? 0),
                'avgTokens' => $total_calls > 0 ? (int) round(((int) ($summary['total_tokens'] ?? 0)) / $total_calls) : 0,
                'totalCost' => round((float) ($summary['total_cost'] ?? 0), 6),
                'avgCostPerPost' => $post_count > 0 ? round(((float) ($summary['total_cost'] ?? 0)) / $post_count, 6) : 0.0,
                'typeCounts' => array(
                    'text' => (int) ($summary['type_counts']['text'] ?? 0),
                    'image' => (int) ($summary['type_counts']['image'] ?? 0),
                    'seo' => (int) ($summary['type_counts']['seo'] ?? 0),
                ),
                'dailySeries' => $normalized_daily,
                'monthlySeries' => $normalized_monthly,
            );
        }

        $payload = array(
            'rows' => $recent_rows,
            'totalRows' => $total_rows,
            'rowsLimited' => $total_rows > count($recent_rows),
            'recentRowsLimit' => $recent_rows_limit,
            'dailySeries' => $daily_series,
            'monthlySeries' => $monthly_series,
            'summariesByModel' => $summaries_by_model,
            'modelOptions' => $model_options,
            'defaultModel' => $requested_model,
            'periodDays' => $days,
            'periodStartDay' => $since_day,
            'periodEndDay' => $period_end_day,
        );

        if ($cache_ttl > 0) {
            set_transient($cache_key, array(
                'recent_rows' => $recent_rows,
                'total_rows' => $total_rows,
                'rows_limited' => $total_rows > count($recent_rows),
                'daily_series' => $daily_series,
                'monthly_series' => $monthly_series,
                'model_options' => $model_options,
                'summaries_by_model' => $summaries_by_model,
                'period_start_day' => $since_day,
                'period_end_day' => $period_end_day,
            ), $cache_ttl);
        }

        return $payload;
    }
}

if (!function_exists('cbia_ajax_usage_overview_data')) {
    if (!function_exists('cbia_get_usage_dashboard_payload_basic')) {
        function cbia_get_usage_dashboard_payload_basic($days = 30, $requested_model = '') {
            $days = (int) $days;
            if (!in_array($days, array(7, 30, 90, 730), true)) {
                $days = 30;
            }
            $period_start = gmdate('Y-m-d', time() - ($days * DAY_IN_SECONDS));
            $period_end = gmdate('Y-m-d');

            return array(
                'rows' => array(),
                'totalRows' => 0,
                'rowsLimited' => false,
                'recentRowsLimit' => 20,
                'dailySeries' => array(),
                'monthlySeries' => array(),
                'summariesByModel' => array(
                    '__all__' => array(
                        'totalCalls' => 0,
                        'uniquePosts' => 0,
                        'uniqueUsers' => 0,
                        'totalTokens' => 0,
                        'avgTokens' => 0,
                        'totalCost' => 0.0,
                        'avgCostPerPost' => 0.0,
                        'typeCounts' => array('text' => 0, 'image' => 0, 'seo' => 0),
                        'dailySeries' => array(),
                        'monthlySeries' => array(),
                    ),
                ),
                'modelOptions' => array(),
                'defaultModel' => (string) $requested_model,
                'periodDays' => $days,
                'periodStartDay' => $period_start,
                'periodEndDay' => $period_end,
            );
        }
    }

    function cbia_ajax_usage_overview_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }
        check_ajax_referer('cbia_usage_overview', 'nonce');

        $days = isset($_REQUEST['days']) ? absint(wp_unslash((string) $_REQUEST['days'])) : 30;
        $requested_model = isset($_REQUEST['usage_model']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['usage_model'])) : '';

        $can_view_costs = false;
        if (function_exists('cbia_cap_enabled') && cbia_cap_enabled('costs_advanced')) {
            $can_view_costs = function_exists('cbia_is_pro_edition') ? cbia_is_pro_edition() : false;
        }
        $apply_usage_caps = static function ($payload) use ($can_view_costs) {
            $data = is_array($payload) ? $payload : array();
            $data['canViewCosts'] = $can_view_costs ? 1 : 0;
            if ($can_view_costs) {
                return $data;
            }

            if (!empty($data['rows']) && is_array($data['rows'])) {
                foreach ($data['rows'] as $idx => $row) {
                    if (!is_array($row)) continue;
                    $row['cost_eur'] = null;
                    $data['rows'][$idx] = $row;
                }
            }
            if (!empty($data['monthlySeries']) && is_array($data['monthlySeries'])) {
                foreach ($data['monthlySeries'] as $idx => $row) {
                    if (!is_array($row)) continue;
                    $row['text_cost_eur'] = 0.0;
                    $row['image_cost_eur'] = 0.0;
                    $row['seo_cost_eur'] = 0.0;
                    $row['cost_eur'] = 0.0;
                    $data['monthlySeries'][$idx] = $row;
                }
            }
            if (!empty($data['summariesByModel']) && is_array($data['summariesByModel'])) {
                foreach ($data['summariesByModel'] as $key => $summary) {
                    if (!is_array($summary)) continue;
                    $summary['totalCost'] = 0.0;
                    $summary['avgCostPerPost'] = 0.0;
                    if (!empty($summary['monthlySeries']) && is_array($summary['monthlySeries'])) {
                        foreach ($summary['monthlySeries'] as $idx => $mrow) {
                            if (!is_array($mrow)) continue;
                            $mrow['text_cost_eur'] = 0.0;
                            $mrow['image_cost_eur'] = 0.0;
                            $mrow['seo_cost_eur'] = 0.0;
                            $mrow['cost_eur'] = 0.0;
                            $summary['monthlySeries'][$idx] = $mrow;
                        }
                    }
                    $data['summariesByModel'][$key] = $summary;
                }
            }
            return $data;
        };

        if (function_exists('cbia_cap_enabled') && !cbia_cap_enabled('usage_advanced')) {
            if (function_exists('cbia_get_usage_dashboard_payload_fast')) {
                wp_send_json_success($apply_usage_caps(cbia_get_usage_dashboard_payload_fast($days, $requested_model)));
            }
            wp_send_json_success($apply_usage_caps(cbia_get_usage_dashboard_payload_basic($days, $requested_model)));
        }

        if (function_exists('cbia_get_usage_dashboard_payload_fast')) {
            wp_send_json_success($apply_usage_caps(cbia_get_usage_dashboard_payload_fast($days, $requested_model)));
        }
        wp_send_json_success($apply_usage_caps(cbia_get_usage_dashboard_payload($days, $requested_model)));
    }
}

// Button injected via admin.js (no inline CSS/JS)

if (!function_exists('cbia_admin_notice_yoast')) {
    function cbia_admin_notice_yoast() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (!function_exists('get_current_screen')) return;

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_cbia') return;

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $yoast_plugin = 'wordpress-seo/wp-seo.php';
        $yoast_path = WP_PLUGIN_DIR . '/wordpress-seo/wp-seo.php';
        $installed = file_exists($yoast_path);

        if (is_plugin_active($yoast_plugin)) {
            echo '<div class="notice cbia-notice notice-success is-dismissible"><p>' . esc_html__('Yoast SEO detected and active.', 'cbiastudio-blogflow-ai') . '</p></div>';
            return;
        }

        if ($installed) {
            if (current_user_can('activate_plugins')) {
                $activate_url = wp_nonce_url(
                    self_admin_url('plugins.php?action=activate&plugin=' . rawurlencode($yoast_plugin)),
                    'activate-plugin_' . $yoast_plugin
                );
                $msg = 'Yoast SEO is installed but inactive. <a href="' . esc_url($activate_url) . '">Activate now</a>.';
            } else {
                $msg = 'Yoast SEO is installed but inactive.';
            }
            echo '<div class="notice cbia-notice notice-warning is-dismissible"><p>' . wp_kses($msg, ['a' => ['href' => []]]) . '</p></div>';
            return;
        }

        if (current_user_can('install_plugins')) {
            $install_url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=wordpress-seo'),
                'install-plugin_wordpress-seo'
            );
            $msg = 'Yoast SEO is not installed. <a href="' . esc_url($install_url) . '">Install Yoast SEO</a>.';
        } else {
            $msg = 'Yoast SEO is not installed.';
        }

        echo '<div class="notice cbia-notice notice-warning is-dismissible"><p>' . wp_kses($msg, ['a' => ['href' => []]]) . '</p></div>';
    }
}

if (!function_exists('cbia_admin_enqueue_inline')) {
    function cbia_admin_enqueue_inline($hook) {
        $is_plugin_page = ($hook === 'toplevel_page_cbia');
        $is_posts_list = ($hook === 'edit.php');
        $is_ai_composer = in_array($hook, array('post-new.php', 'post.php'), true);
        if (!$is_plugin_page && !$is_posts_list && !$is_ai_composer) return;

        // Some environments/plugins deregister the core heartbeat handle, which
        // then breaks wp-auth-check on admin screens in WP 6.9.1+.
        if (!wp_script_is('heartbeat', 'registered')) {
            $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
            $heartbeat_deps = array('jquery');
            if (wp_script_is('wp-hooks', 'registered')) {
                $heartbeat_deps[] = 'wp-hooks';
            }
            wp_register_script(
                'heartbeat',
                includes_url('js/heartbeat' . $suffix . '.js'),
                $heartbeat_deps,
                false,
                true
            );
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('heartbeat');
        wp_enqueue_style('dashicons');
        $css_path = CBIA_PRO_PLUGIN_DIR . 'assets/css/admin.css';
        $js_path  = CBIA_PRO_PLUGIN_DIR . 'assets/js/admin.js';
        $css_ver  = CBIA_VERSION . (file_exists($css_path) ? '.' . filemtime($css_path) : '');
        $js_ver   = CBIA_VERSION . (file_exists($js_path) ? '.' . filemtime($js_path) : '');

        wp_enqueue_style(
            'abb-admin',
            plugins_url('assets/css/admin.css', CBIA_PRO_PLUGIN_FILE),
            array(),
            $css_ver
        );
        wp_enqueue_script(
            'abb-admin',
            plugins_url('assets/js/admin.js', CBIA_PRO_PLUGIN_FILE),
            array('jquery'),
            $js_ver,
            true
        );
        if ($is_ai_composer) {
            wp_enqueue_media();
        }
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('cbia_ajax_nonce');

        // UX change: remove extra "Anadir entrada con IA" button in posts list.
        $enable_button = false;

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
        $default_language = function_exists('cbia_ai_composer_normalize_language_value')
            ? cbia_ai_composer_normalize_language_value(isset($settings['post_language']) ? (string)$settings['post_language'] : 'English')
            : cbia_ai_composer_normalize_language_code(isset($settings['post_language']) ? (string)$settings['post_language'] : 'es');
        $default_length = isset($settings['post_length_variant']) ? sanitize_key((string)$settings['post_length_variant']) : 'medium';
        if (!in_array($default_length, array('short', 'medium', 'long'), true)) $default_length = 'medium';
        $default_images = isset($settings['images_limit']) ? absint($settings['images_limit']) : 3;
        if ($default_images < 1) $default_images = 1;
        $text_provider = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai';
        $image_provider = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : 'openai';
        $text_api_ok = function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key((string)$text_provider) !== '') : true;
        $image_api_ok = function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key((string)$image_provider) !== '') : true;

        wp_localize_script('abb-admin', 'CBIAAdmin', array(
            'ajaxUrl' => $ajax_url,
            'nonce'   => $nonce,
            'addPostButton' => array(
                'enabled' => $enable_button,
                'url' => '',
                'label' => '',
            ),
            'aiComposer' => array(
                'enabled' => $is_ai_composer,
                'defaultLength' => $default_length,
                'defaultLanguage' => $default_language,
                'defaultImagesLimit' => $default_images,
                'textProvider' => (string)$text_provider,
                'imageProvider' => (string)$image_provider,
                'textApiConfigured' => (bool)$text_api_ok,
                'imageApiConfigured' => (bool)$image_api_ok,
                'settingsUrl' => admin_url('admin.php?page=cbia&tab=config'),
            ),
        ));

        $js = "(function($){\n"
            . "  window.CBIA = window.CBIA || {};\n"
            . "  CBIA.ajaxUrl = " . wp_json_encode($ajax_url) . ";\n"
            . "  CBIA.nonce = " . wp_json_encode($nonce) . ";\n"
            . "  CBIA.fetchLog = function(targetSelector){\n"
            . "    return $.post(CBIA.ajaxUrl, {action:'cbia_get_log', _ajax_nonce: CBIA.nonce})\n"
            . "      .done(function(res){\n"
            . "        if(res && res.success && res.data){ $(targetSelector).val(res.data.log || ''); }\n"
            . "      });\n"
            . "  };\n"
            . "  CBIA.clearLog = function(targetSelector){\n"
            . "    return $.post(CBIA.ajaxUrl, {action:'cbia_clear_log', _ajax_nonce: CBIA.nonce})\n"
            . "      .done(function(res){ if(res && res.success){ $(targetSelector).val(''); } });\n"
            . "  };\n"
            . "  CBIA.setStop = function(stop){\n"
            . "    return $.post(CBIA.ajaxUrl, {action:'cbia_set_stop', stop: stop ? 1 : 0, _ajax_nonce: CBIA.nonce});\n"
            . "  };\n"
            . "})(jQuery);\n";

        wp_add_inline_script('jquery', $js, 'after');

    }
}

if (!function_exists('cbia_pro_sanitize_custom_css')) {
    function cbia_pro_sanitize_custom_css($css) {
        $css = (string)$css;
        if ($css === '') return '';

        $css = wp_kses_no_null($css, array('slash_zero' => 'keep'));
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/<\/?style\b[^>]*>/i', '', $css);
        $css = preg_replace('/@import\b/i', '', $css);
        $css = preg_replace('/expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding|data\s*:/i', '', $css);

        $sanitized = '';
        if (preg_match_all('/([^{]+)\{([^}]*)\}/', $css, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $selector = trim((string)$match[1]);
                $selector = preg_replace('/[^a-zA-Z0-9\-\_\.\#\s\:\,\>\+\~\*\[\]\=\"\'\(\)]/', '', $selector);
                $rules = trim((string)safecss_filter_attr((string)$match[2]));
                if ($selector === '' || $rules === '') {
                    continue;
                }
                $sanitized .= $selector . '{' . $rules . '}';
            }
        }

        return trim($sanitized);
    }
}

if (!function_exists('cbia_admin_body_class')) {
    function cbia_admin_body_class($classes) {
        if (!cbia_is_plugin_admin_page()) {
            return (string) $classes;
        }

        $classes = trim((string) $classes);
        return trim($classes . ' cbia-hide-external-notices');
    }
}

if (!function_exists('cbia_is_internal_notice_callback')) {
    function cbia_is_internal_notice_callback($callback): bool {
        if (is_string($callback)) {
            return strpos($callback, 'cbia_') === 0;
        }

        if (is_array($callback) && isset($callback[1])) {
            $method = (string) $callback[1];
            if (strpos($method, 'cbia_') === 0) {
                return true;
            }

            $target = $callback[0] ?? '';
            if (is_object($target)) {
                $target = get_class($target);
            }
            $target = strtolower((string) $target);
            return strpos($target, 'cbia') !== false;
        }

        return false;
    }
}

if (!function_exists('cbia_filter_notice_hook_callbacks')) {
    function cbia_filter_notice_hook_callbacks($tag) {
        global $wp_filter;

        if (empty($wp_filter[$tag]) || !($wp_filter[$tag] instanceof WP_Hook)) {
            return;
        }

        $callbacks_map = (array)($wp_filter[$tag]->callbacks ?? array());
        foreach ($callbacks_map as $priority => $callbacks) {
            if (!is_array($callbacks)) {
                continue;
            }
            foreach ($callbacks as $callback_data) {
                $callback = $callback_data['function'] ?? null;
                if (cbia_is_internal_notice_callback($callback)) {
                    continue;
                }
                if ($callback !== null) {
                    remove_action($tag, $callback, (int)$priority);
                }
            }
        }
    }
}

if (!function_exists('cbia_suppress_external_admin_notices')) {
    function cbia_suppress_external_admin_notices() {
        static $done = false;

        if ($done || !cbia_is_plugin_admin_page()) {
            return;
        }
        $done = true;

        foreach (array('admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices') as $tag) {
            cbia_filter_notice_hook_callbacks($tag);
        }
    }
}

if (!function_exists('cbia_pro_output_banner_css')) {
    function cbia_pro_output_banner_css() {
        if (is_admin()) return;

        if (!function_exists('cbia_get_settings')) return;
        $settings = cbia_get_settings();
        $css = cbia_pro_sanitize_custom_css($settings['content_images_banner_css'] ?? '');
        if ($css === '' && function_exists('cbia_get_default_settings')) {
            $defaults = cbia_get_default_settings();
            $css = cbia_pro_sanitize_custom_css($defaults['content_images_banner_css'] ?? '');
        }
        if ($css === '') return;

        if (!wp_style_is('cbia-pro-banner-css-inline', 'registered')) {
            wp_register_style('cbia-pro-banner-css-inline', false, array(), CBIA_PRO_VERSION);
        }
        wp_enqueue_style('cbia-pro-banner-css-inline');
        wp_add_inline_style('cbia-pro-banner-css-inline', $css);
    }
}

if (!function_exists('cbia_is_ai_composer_context')) {
    function cbia_is_ai_composer_context() {
        if (!is_admin()) return false;

        // If we are already on the post editor/new-post screen for "post",
        // enable composer even when query args are missing/rewritten.
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && in_array((string)$screen->base, array('post', 'post-new'), true) && (string)$screen->post_type === 'post') {
                return true;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin launcher flags.
        $flag = isset($_GET['cbia_ai']) ? sanitize_key((string)wp_unslash($_GET['cbia_ai'])) : '';
        if (!in_array($flag, array('1', 'true', 'yes'), true)) return false;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin launcher flags.
        $post_type = isset($_GET['post_type']) ? sanitize_key((string)wp_unslash($_GET['post_type'])) : '';
        if ($post_type === '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin launcher flags.
            $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
            if ($post_id > 0) $post_type = get_post_type($post_id);
        }
        return ($post_type === '' || $post_type === 'post');
    }
}

if (!function_exists('cbia_ai_composer_extract_img_attr')) {
    function cbia_ai_composer_extract_img_attr($img_tag, $attr) {
        $attr = preg_quote((string)$attr, '/');
        if (preg_match('/\b' . $attr . '\s*=\s*("|\')([^"\']*)\1/i', (string)$img_tag, $m)) {
            return (string)$m[2];
        }
        return '';
    }
}

if (!function_exists('cbia_ai_composer_extract_internal_images_from_html')) {
    function cbia_ai_composer_extract_internal_images_from_html($html, $max = 3) {
        $rows = array();
        $seen = array();
        $max = absint($max);
        if ($max <= 0) $max = 3;
        if (!preg_match_all('/<img\b[^>]*>/i', (string)$html, $matches) || empty($matches[0])) {
            return $rows;
        }
        $idx = 1;
        foreach ((array)$matches[0] as $img_tag) {
            if ($idx > $max) break;
            $src = esc_url_raw(cbia_ai_composer_extract_img_attr($img_tag, 'src'));
            if ($src === '') continue;
            $src_no_query = preg_replace('/\?.*$/', '', (string)$src);
            if ($src_no_query === '') continue;
            if (isset($seen[$src_no_query])) continue;
            $seen[$src_no_query] = 1;

            $class_attr = strtolower((string)cbia_ai_composer_extract_img_attr($img_tag, 'class'));
            $mode = (strpos($class_attr, 'cbia-banner') !== false) ? 'banner' : 'normal';
            $alt = sanitize_text_field((string)cbia_ai_composer_extract_img_attr($img_tag, 'alt'));
            $title_attr = sanitize_text_field((string)cbia_ai_composer_extract_img_attr($img_tag, 'title'));
            $desc = $alt !== '' ? $alt : $title_attr;
            if ($desc === '') $desc = sprintf('Internal image %d', (int)$idx);

            $attach_id = (int)attachment_url_to_postid($src_no_query);
            if ($attach_id <= 0) {
                $attach_id = (int)attachment_url_to_postid($src);
            }

            $rows[] = array(
                'type' => 'internal',
                'idx' => (int)$idx,
                'attach_id' => (int)$attach_id,
                'url' => (string)$src,
                'alt' => (string)$alt,
                'prompt' => (string)$desc,
                'desc' => (string)$desc,
                'mode' => (string)$mode,
            );
            $idx++;
        }
        return $rows;
    }
}

if (!function_exists('cbia_ai_composer_build_snapshot_from_post')) {
    function cbia_ai_composer_build_snapshot_from_post($post_id, $default_language = 'Spanish', $default_internal_style = 'banner', $default_length = 'medium', $default_internal_images = 2) {
        $post_id = absint($post_id);
        if ($post_id <= 0) return array();
        $post = get_post($post_id);
        if (!($post instanceof WP_Post) || $post->post_type !== 'post') return array();

        $title = function_exists('cbia_ai_composer_normalize_title_value')
            ? cbia_ai_composer_normalize_title_value((string)$post->post_title, $post)
            : sanitize_text_field((string)$post->post_title);
        $html = (string)$post->post_content;
        $featured_id = (int)get_post_thumbnail_id($post_id);
        $featured_url = $featured_id > 0 ? (string)wp_get_attachment_image_url($featured_id, 'large') : '';
        if ($featured_url === '' && $featured_id > 0) {
            $featured_url = (string)wp_get_attachment_url($featured_id);
        }
        $featured_alt = $featured_id > 0 ? sanitize_text_field((string)get_post_meta($featured_id, '_wp_attachment_image_alt', true)) : '';

        $internal_images_enabled = cbia_cap_enabled('internal_images');
        $internal_images = cbia_ai_composer_extract_internal_images_from_html($html, 3);
        $internal_count = count($internal_images);
        if (!$internal_images_enabled) {
            $internal_images = array();
            $internal_count = 0;
            $default_internal_images = 0;
        }
        $style_from_content = $default_internal_style;
        if (!empty($internal_images) && !empty($internal_images[0]['mode'])) {
            $style_from_content = in_array((string)$internal_images[0]['mode'], array('banner', 'normal'), true)
                ? (string)$internal_images[0]['mode']
                : $default_internal_style;
        }

        $cat_ids = wp_get_post_categories($post_id, array('fields' => 'ids'));
        if (!is_array($cat_ids)) $cat_ids = array();
        $cat_ids = array_values(array_map('absint', $cat_ids));
        $cat_ids = array_values(array_filter($cat_ids));

        $tag_objs = wp_get_post_terms($post_id, 'post_tag');
        $tag_ids = array();
        $tag_names = array();
        if (!is_wp_error($tag_objs) && is_array($tag_objs)) {
            foreach ($tag_objs as $term) {
                if (!($term instanceof WP_Term)) continue;
                $tag_ids[] = (int)$term->term_id;
                $name = sanitize_text_field((string)$term->name);
                if ($name !== '') $tag_names[] = $name;
            }
        }
        $tag_ids = array_values(array_unique(array_filter(array_map('absint', $tag_ids))));
        $tag_names = array_values(array_unique(array_filter(array_map('sanitize_text_field', $tag_names))));

        $focus = sanitize_text_field((string)get_post_meta($post_id, '_yoast_wpseo_focuskw', true));
        if ($focus === '') $focus = $title;
        $metad = sanitize_text_field((string)get_post_meta($post_id, '_yoast_wpseo_metadesc', true));
        if ($metad === '') {
            $metad = wp_trim_words(wp_strip_all_tags($html), 28, '...');
        }
        if (function_exists('cbia_trim_metadesc')) {
            $metad = cbia_trim_metadesc($metad, 139);
        }

        $plain = trim((string)wp_strip_all_tags($html));
        $word_count = 0;
        if ($plain !== '') {
            if (preg_match_all('/[\p{L}\p{N}\']+/u', $plain, $wm)) {
                $word_count = count((array)$wm[0]);
            } else {
                $word_count = (int)str_word_count($plain);
            }
        }

        $plain_content = trim((string)wp_strip_all_tags($html));
        $has_meaningful_content = ($plain_content !== '');
        $default_length = sanitize_key((string)$default_length);
        if (!in_array($default_length, array('short', 'medium', 'long'), true)) {
            $default_length = 'medium';
        }
        $default_internal_images = max(0, min(3, absint($default_internal_images)));

        $snapshot = array(
            'version' => 2,
            'post_id' => (int)$post_id,
            'title' => (string)$title,
            'controls' => array(
                'length' => $default_length,
                'internal_images' => $internal_images_enabled
                    ? ($has_meaningful_content ? (int)min(3, max(0, $internal_count)) : (int)$default_internal_images)
                    : 0,
                'language' => function_exists('cbia_ai_composer_normalize_language_value')
                    ? cbia_ai_composer_normalize_language_value((string)$default_language)
                    : cbia_ai_composer_normalize_language_code((string)$default_language),
                'internal_style' => sanitize_key((string)$style_from_content),
            ),
            'final_html' => (string)$html,
            'preview_html' => (string)$html,
            'featured' => array(
                'type' => 'featured',
                'idx' => 0,
                'attach_id' => (int)$featured_id,
                'url' => (string)$featured_url,
                'alt' => (string)$featured_alt,
                'prompt' => (string)$featured_alt,
                'desc' => (string)$featured_alt,
            ),
            'featured_attach_id' => (int)$featured_id,
            'internal_images' => $internal_images_enabled ? $internal_images : array(),
            'focus_keyphrase' => (string)$focus,
            'meta_description' => (string)$metad,
            'category_ids' => $cat_ids,
            'tag_ids' => $tag_ids,
            'tag_names' => $tag_names,
            'preview_token' => '',
            'word_count' => (int)$word_count,
            'updated_at' => time(),
        );

        return function_exists('cbia_ai_composer_sanitize_snapshot')
            ? cbia_ai_composer_sanitize_snapshot($snapshot)
            : $snapshot;
    }
}

if (!function_exists('cbia_ai_composer_normalize_language_code')) {
    function cbia_ai_composer_normalize_language_code($value) {
        $raw = strtolower(trim((string)$value));
        if ($raw === '') return 'es';
        if (in_array($raw, array('es', 'es-es', 'espanol', 'espaÃƒÂ±ol', 'spanish', 'castellano'), true)) return 'es';
        if (in_array($raw, array('en', 'en-us', 'en-gb', 'english', 'ingles', 'inglÃƒÂ©s'), true)) return 'en';
        return 'es';
    }
}

if (!function_exists('cbia_register_ai_composer_metabox')) {
    if (!function_exists('cbia_ai_composer_normalize_language_value')) {
        function cbia_ai_composer_normalize_language_value($value) {
            $raw = strtolower(trim((string)$value));
            if ($raw === '') return 'Spanish';

            $map = array(
                'es' => 'Spanish', 'es-es' => 'Spanish', 'espanol' => 'Spanish', 'espaÃƒÆ’Ã‚Â±ol' => 'Spanish', 'espaÃƒÂ±ol' => 'Spanish', 'spanish' => 'Spanish', 'castellano' => 'Spanish',
                'pt' => 'Portuguese', 'pt-pt' => 'Portuguese', 'pt-br' => 'Portuguese', 'portugues' => 'Portuguese', 'portuguese' => 'Portuguese',
                'en' => 'English', 'en-us' => 'English', 'en-gb' => 'English', 'english' => 'English', 'ingles' => 'English', 'inglÃƒÆ’Ã‚Â©s' => 'English', 'inglÃƒÂ©s' => 'English',
                'fr' => 'French', 'fr-fr' => 'French', 'french' => 'French', 'frances' => 'French', 'francÃƒÆ’Ã‚Â©s' => 'French', 'francÃƒÂ©s' => 'French',
                'it' => 'Italian', 'it-it' => 'Italian', 'italian' => 'Italian', 'italiano' => 'Italian',
                'de' => 'German', 'de-de' => 'German', 'german' => 'German', 'aleman' => 'German', 'alemÃƒÆ’Ã‚Â¡n' => 'German', 'alemÃƒÂ¡n' => 'German',
                'nl' => 'Dutch', 'nl-nl' => 'Dutch', 'dutch' => 'Dutch', 'holandes' => 'Dutch', 'holandÃƒÆ’Ã‚Â©s' => 'Dutch', 'holandÃƒÂ©s' => 'Dutch',
                'sv' => 'Swedish', 'sv-se' => 'Swedish', 'swedish' => 'Swedish', 'sueco' => 'Swedish',
                'da' => 'Danish', 'da-dk' => 'Danish', 'danish' => 'Danish', 'danes' => 'Danish', 'danÃƒÂ©s' => 'Danish',
                'no' => 'Norwegian', 'nb' => 'Norwegian', 'nn' => 'Norwegian', 'norwegian' => 'Norwegian', 'noruego' => 'Norwegian',
                'fi' => 'Finnish', 'fi-fi' => 'Finnish', 'finnish' => 'Finnish', 'fines' => 'Finnish', 'finÃƒÂ©s' => 'Finnish',
                'pl' => 'Polish', 'pl-pl' => 'Polish', 'polish' => 'Polish', 'polaco' => 'Polish',
                'cs' => 'Czech', 'cs-cz' => 'Czech', 'czech' => 'Czech', 'checo' => 'Czech',
                'sk' => 'Slovak', 'sk-sk' => 'Slovak', 'slovak' => 'Slovak', 'eslovaco' => 'Slovak',
                'hu' => 'Hungarian', 'hu-hu' => 'Hungarian', 'hungarian' => 'Hungarian', 'hungaro' => 'Hungarian', 'hÃƒÂºngaro' => 'Hungarian',
                'ro' => 'Romanian', 'ro-ro' => 'Romanian', 'romanian' => 'Romanian', 'rumano' => 'Romanian',
                'bg' => 'Bulgarian', 'bg-bg' => 'Bulgarian', 'bulgarian' => 'Bulgarian', 'bulgaro' => 'Bulgarian', 'bÃƒÂºlgaro' => 'Bulgarian',
                'el' => 'Greek', 'el-gr' => 'Greek', 'greek' => 'Greek', 'griego' => 'Greek',
                'hr' => 'Croatian', 'hr-hr' => 'Croatian', 'croatian' => 'Croatian', 'croata' => 'Croatian',
                'sl' => 'Slovenian', 'sl-si' => 'Slovenian', 'slovenian' => 'Slovenian', 'esloveno' => 'Slovenian',
                'et' => 'Estonian', 'et-ee' => 'Estonian', 'estonian' => 'Estonian', 'estonio' => 'Estonian',
                'lv' => 'Latvian', 'lv-lv' => 'Latvian', 'latvian' => 'Latvian', 'leton' => 'Latvian', 'letÃƒÂ³n' => 'Latvian',
                'lt' => 'Lithuanian', 'lt-lt' => 'Lithuanian', 'lithuanian' => 'Lithuanian', 'lituano' => 'Lithuanian',
                'ga' => 'Irish', 'ga-ie' => 'Irish', 'irish' => 'Irish', 'irlandes' => 'Irish', 'irlandÃƒÂ©s' => 'Irish',
                'mt' => 'Maltese', 'mt-mt' => 'Maltese', 'maltese' => 'Maltese', 'maltes' => 'Maltese', 'maltÃƒÂ©s' => 'Maltese',
                'rm' => 'Romansh', 'rm-ch' => 'Romansh', 'romansh' => 'Romansh', 'romanche' => 'Romansh',
            );

            return isset($map[$raw]) ? $map[$raw] : 'Spanish';
        }
    }

    if (!function_exists('cbia_ai_composer_get_language_options')) {
        function cbia_ai_composer_get_language_options() {
            return array(
                'Spanish'    => __('Spanish', 'cbiastudio-blogflow-ai'),
                'Portuguese' => __('Portuguese', 'cbiastudio-blogflow-ai'),
                'English'    => __('English', 'cbiastudio-blogflow-ai'),
                'French'     => __('French', 'cbiastudio-blogflow-ai'),
                'Italian'    => __('Italian', 'cbiastudio-blogflow-ai'),
                'German'     => __('German', 'cbiastudio-blogflow-ai'),
                'Dutch'      => __('Dutch', 'cbiastudio-blogflow-ai'),
                'Swedish'    => __('Swedish', 'cbiastudio-blogflow-ai'),
                'Danish'     => __('Danish', 'cbiastudio-blogflow-ai'),
                'Norwegian'  => __('Norwegian', 'cbiastudio-blogflow-ai'),
                'Finnish'    => __('Finnish', 'cbiastudio-blogflow-ai'),
                'Polish'     => __('Polish', 'cbiastudio-blogflow-ai'),
                'Czech'      => __('Czech', 'cbiastudio-blogflow-ai'),
                'Slovak'     => __('Slovak', 'cbiastudio-blogflow-ai'),
                'Hungarian'  => __('Hungarian', 'cbiastudio-blogflow-ai'),
                'Romanian'   => __('Romanian', 'cbiastudio-blogflow-ai'),
                'Bulgarian'  => __('Bulgarian', 'cbiastudio-blogflow-ai'),
                'Greek'      => __('Greek', 'cbiastudio-blogflow-ai'),
                'Croatian'   => __('Croatian', 'cbiastudio-blogflow-ai'),
                'Slovenian'  => __('Slovenian', 'cbiastudio-blogflow-ai'),
                'Estonian'   => __('Estonian', 'cbiastudio-blogflow-ai'),
                'Latvian'    => __('Latvian', 'cbiastudio-blogflow-ai'),
                'Lithuanian' => __('Lithuanian', 'cbiastudio-blogflow-ai'),
                'Irish'      => __('Irish', 'cbiastudio-blogflow-ai'),
                'Maltese'    => __('Maltese', 'cbiastudio-blogflow-ai'),
                'Romansh'    => __('Romansh', 'cbiastudio-blogflow-ai'),
            );
        }
    }

    if (!function_exists('cbia_ai_composer_normalize_title_value')) {
        function cbia_ai_composer_normalize_title_value($value, $post = null) {
            $title = sanitize_text_field((string)$value);
            $title_lc = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
            $auto_titles = array('', 'auto draft', 'auto-draft', 'borrador automatico', 'borrador automÃƒÂ¡tico');
            if ($post instanceof WP_Post && $post->post_status === 'auto-draft') {
                return '';
            }
            if (in_array($title_lc, $auto_titles, true)) {
                return '';
            }
            return $title;
        }
    }

    function cbia_register_ai_composer_metabox($post_type, $post) {
        if ($post_type !== 'post') return;
        add_meta_box(
            'cbia_ai_composer_box',
            'CBIAStudio AI Composer',
            'cbia_render_ai_composer_metabox',
            'post',
            'normal',
            'high'
        );
    }
}

if (!function_exists('cbia_render_ai_composer_trigger')) {
    function cbia_render_ai_composer_trigger($post) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'post') return;
        echo '<div id="cbia-ai-trigger-anchor" class="cbia-ai-trigger-row" style="margin:10px 0 6px;display:none;">';
        echo '<button type="button" class="button button-primary cbia-ai-launch-btn" id="cbia-ai-composer-open-server" style="display:none;">' . esc_html__('Create with AI', 'cbiastudio-blogflow-ai') . '</button>';
        echo '</div>';
    }
}

if (!function_exists('cbia_render_ai_composer_fallback_container')) {
    function cbia_render_ai_composer_fallback_container($post) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'post') return;
        // If metabox callback didn't render for any reason, render composer markup here.
        if (empty($GLOBALS['cbia_ai_composer_rendered'])) {
            cbia_render_ai_composer_metabox($post);
        }
    }
}

if (!function_exists('cbia_render_ai_composer_footer_fallback')) {
    function cbia_render_ai_composer_footer_fallback() {
        if (!is_admin()) return;
        if (!function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen || !in_array((string)$screen->base, array('post', 'post-new'), true) || (string)$screen->post_type !== 'post') {
            return;
        }

        // 1) Guarantee the trigger button exists in DOM (fallback for themes/plugins overriding editor markup).
        $fallback_js = <<<'JS'
(function(){
  function getComposerModals(){
    var nodes=[];
    try{ nodes=Array.prototype.slice.call(document.querySelectorAll('#cbia-ai-composer-modal,.cbia-composer-modal')); }catch(_eList){ nodes=[]; }
    return nodes.filter(function(node,index,arr){ return !!node && arr.indexOf(node)===index; });
  }
  function isComposerOpen(){
    var nodes=getComposerModals();
    if(!nodes.length) return false;
    for(var i=0;i<nodes.length;i++){
      var m=nodes[i];
      if(m.style.display && m.style.display!=='none') return true;
      try{ if(window.getComputedStyle(m).display!=='none') return true; }catch(_e0){}
    }
    return false;
  }
  function openComposer(){
    var m=document.getElementById('cbia-ai-composer-modal');
    if(m){
      try{ if(m.parentNode!==document.body){ document.body.appendChild(m); } }catch(_e){}
      var rootsInModal=document.querySelectorAll('#cbia-ai-composer');
      for(var r=0;r<rootsInModal.length;r++){
        if(m.contains(rootsInModal[r])){ rootsInModal[r].style.display=''; }
      }
      m.style.display='flex';document.body.classList.add('cbia-modal-open');try{m.focus();}catch(_e2){}return;
    }
    var c=document.getElementById('cbia-ai-composer');
    if(c){c.style.display='';c.scrollIntoView({behavior:'smooth',block:'start'});}
  }
  function closeComposer(){
    var nodes=getComposerModals();
    for(var i=0;i<nodes.length;i++){
      nodes[i].style.display='none';
    }
    var roots=document.querySelectorAll('#cbia-ai-composer');
    for(var j=0;j<roots.length;j++){
      var insideModal=false;
      for(var k=0;k<nodes.length;k++){
        if(nodes[k].contains(roots[j])){ insideModal=true; break; }
      }
      if(!insideModal){ roots[j].style.display='none'; }
    }
    if(document.body){document.body.classList.remove('cbia-modal-open');}
  }
  window.CBIA_AI_OPEN_MODAL=function(e){if(e&&e.preventDefault)e.preventDefault();openComposer();return false;};
  window.CBIA_AI_CLOSE_MODAL=function(e){if(e&&e.preventDefault)e.preventDefault();closeComposer();return false;};
  if(document.body && !document.body.getAttribute('data-cbia-composer-delegated-fallback')){
    document.body.setAttribute('data-cbia-composer-delegated-fallback','1');
    document.addEventListener('click',function(e){
      var t=e.target;
      if(!t||!t.closest)return;
      var trigger=t.closest('#cbia-ai-composer-open,#cbia-ai-composer-open-server,.cbia-ai-launch-btn');
      if(!trigger)return;
      e.preventDefault();
      openComposer();
    },true);
  }
  if(document.body && !document.body.getAttribute('data-cbia-composer-close-fallback')){
    document.body.setAttribute('data-cbia-composer-close-fallback','1');
    function maybeClose(e){
      var t=e.target;
      if(!t)return;
      var el=(t.nodeType===1)?t:(t.parentElement||null);
      if(!el||!el.closest)return;
      var closeBtn=el.closest('#cbia-ai-composer-close,.cbia-composer-modal .cbia-modal-close');
      if(closeBtn){
        e.preventDefault();
        e.stopPropagation();
        closeComposer();
        return;
      }
      var modal=el.closest ? el.closest('#cbia-ai-composer-modal,.cbia-composer-modal') : null;
      if(modal && el===modal){
        e.preventDefault();
        e.stopPropagation();
      }
    }
    function maybeCloseByKey(e){
      var key=e&&typeof e.key!=='undefined'?e.key:'';
      var keyCode=e&&typeof e.keyCode!=='undefined'?e.keyCode:0;
      if(key!=='Escape' && key!=='Esc' && keyCode!==27) return;
      if(!isComposerOpen()) return;
      e.preventDefault();
      e.stopPropagation();
      closeComposer();
    }
    document.addEventListener('click',maybeClose,true);
    document.addEventListener('pointerdown',maybeClose,true);
    document.addEventListener('mousedown',maybeClose,true);
    document.addEventListener('keydown',maybeCloseByKey,true);
    window.addEventListener('keyup',maybeCloseByKey,true);
    window.addEventListener('keydown',maybeCloseByKey,true);
  }
  function getElementorRef(){
    var ref=document.querySelector('#elementor-switch-mode-button')
      || document.querySelector('.elementor-edit-with-button')
      || document.querySelector('a[href*="action=elementor"],a[href*="elementor"],button[data-cta-link*="elementor"]')
      || document.querySelector('a[href*="elementor"],button[href*="elementor"],[data-elementor-open="1"]');
    if(ref) return ref.closest ? (ref.closest('a,button') || ref) : ref;
    var nodes=document.querySelectorAll('button,a');
    for(var i=0;i<nodes.length;i++){
      var t=String(nodes[i].textContent||'').trim().toLowerCase();
      if(t.indexOf('elementor')>=0)return nodes[i];
    }
    return null;
  }
  function getWriteForMeRef(){
    var nodes=document.querySelectorAll('.interface-interface-skeleton__header button,.interface-interface-skeleton__header a,.edit-post-header button,.edit-post-header a,.editor-header button,.editor-header a');
    for(var i=0;i<nodes.length;i++){
      var t=String(nodes[i].textContent||'').trim().toLowerCase();
      if(t.indexOf('write for me')>=0)return nodes[i];
    }
    return null;
  }
  function getFallbackRef(){
    return document.querySelector('.edit-post-header-toolbar__left')
      || document.querySelector('.interface-interface-skeleton__header .editor-header')
      || document.querySelector('.editor-header__settings')
      || document.querySelector('.editor-post-title__block')
      || document.querySelector('.interface-interface-skeleton__content');
  }
  function placeAsFirstHeaderAction(btn,container){
    if(!btn||!container)return false;
    var firstAction=container.querySelector('button,a');
    if(firstAction&&firstAction!==btn){container.insertBefore(btn,firstAction);return true;}
    if(container.firstElementChild!==btn){
      if(typeof container.prepend==='function')container.prepend(btn);
      else container.insertBefore(btn,container.firstChild);
      return true;
    }
    return true;
  }
  function normalizeLauncherContainer(container){
    if(!container||!container.style)return;
    try{
      var computed=window.getComputedStyle(container);
      if(!computed||computed.display==='block'||computed.display==='inline'||computed.display==='inline-block'){
        container.style.display='inline-flex';
      }
    }catch(_eStyle){
      container.style.display='inline-flex';
    }
    container.style.alignItems='center';
    container.style.flexDirection='row';
    container.style.gap='8px';
    container.style.flexWrap='nowrap';
  }
  function bindOpen(btn){
    if(!btn||btn.getAttribute('data-cbia-bound'))return;
    btn.setAttribute('data-cbia-bound','1');
    btn.addEventListener('click',function(e){if(window.CBIA_AI_OPEN_MODAL){window.CBIA_AI_OPEN_MODAL(e);}else{e.preventDefault();openComposer();}});
  }
  function place(){
    var serverBtn=document.getElementById('cbia-ai-composer-open-server');
    var btn=document.getElementById('cbia-ai-composer-open')||serverBtn;
    var ref=getElementorRef();
    var writeRef=getWriteForMeRef();
    var fallback=getFallbackRef();
    if(!btn){
      btn=document.createElement('button');
      btn.type='button';
      btn.id='cbia-ai-composer-open';
      btn.textContent='Create with AI';
    }
    if(ref){
      btn.className=((ref.className||'').trim()||'button button-primary')+' cbia-ai-launch-btn';
      btn.style.display='inline-flex';
      btn.style.verticalAlign='middle';
      btn.style.marginRight='8px';
      btn.style.marginLeft='0';
      btn.style.marginBottom='0';
      btn.style.whiteSpace='nowrap';
      btn.style.flex='0 0 auto';
      btn.style.display='';
      if(btn.parentNode && btn.parentNode.id==='cbia-ai-trigger-anchor'){ btn.parentNode.style.display=''; }
      bindOpen(btn);
      normalizeLauncherContainer(ref.parentNode);
      if(btn.parentNode!==ref.parentNode){ref.parentNode.insertBefore(btn,ref);}
      else if(btn.nextElementSibling!==ref){ref.insertAdjacentElement('beforebegin',btn);}
      return true;
    }
    if(writeRef){
      btn.className=(writeRef.className||'components-button is-secondary')+' cbia-ai-launch-btn cbia-ai-launch-btn-gb';
      btn.style.display='';
      if(btn.parentNode && btn.parentNode.id==='cbia-ai-trigger-anchor'){ btn.parentNode.style.display=''; }
      bindOpen(btn);
      normalizeLauncherContainer(writeRef.parentNode);
      if(btn.parentNode!==writeRef.parentNode){writeRef.parentNode.insertBefore(btn,writeRef);}
      else if(btn.nextElementSibling!==writeRef){writeRef.insertAdjacentElement('beforebegin',btn);}
      return true;
    }
    if(fallback){
      btn.className='components-button is-secondary cbia-ai-launch-btn cbia-ai-launch-btn-gb';
      btn.style.display='';
      if(btn.parentNode && btn.parentNode.id==='cbia-ai-trigger-anchor'){ btn.parentNode.style.display=''; }
      bindOpen(btn);
      normalizeLauncherContainer(fallback);
      if(btn.parentNode!==fallback){fallback.appendChild(btn);}
      placeAsFirstHeaderAction(btn,fallback);
      return true;
    }
    if(serverBtn){
      bindOpen(serverBtn);
      serverBtn.style.display='';
      if(serverBtn.parentNode && serverBtn.parentNode.id==='cbia-ai-trigger-anchor'){ serverBtn.parentNode.style.display=''; }
      return true;
    }
    return false;
  }
  if(place())return;
  var n=0;
  var t=setInterval(function(){n++;if(place()||n>40)clearInterval(t);},250);
})();
JS;
        wp_add_inline_script('abb-admin', $fallback_js, 'after');

        // 2) Guarantee modal/composer markup exists even if metabox hooks were skipped.
        if (empty($GLOBALS['cbia_ai_composer_rendered'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin post context.
            $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
            $post = $post_id ? get_post($post_id) : null;
            if (!($post instanceof WP_Post) && function_exists('get_default_post_to_edit')) {
                $post = get_default_post_to_edit('post', true);
            }
            if (!($post instanceof WP_Post)) {
                $post = (object) array(
                    'ID' => 0,
                    'post_type' => 'post',
                );
            }
            cbia_render_ai_composer_metabox($post);
        }
    }
}

if (!function_exists('cbia_render_ai_composer_after_title')) {
    function cbia_render_ai_composer_after_title($post) {
        if (!($post instanceof WP_Post)) return;
        if ($post->post_type !== 'post') return;
        if (!cbia_is_ai_composer_context()) return;
        cbia_render_ai_composer_metabox($post);
    }
}

if (!function_exists('cbia_render_ai_composer_metabox')) {
    function cbia_render_ai_composer_metabox($post) {
        $GLOBALS['cbia_ai_composer_rendered'] = 1;
        $internal_images_enabled = cbia_cap_enabled('internal_images');
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
        $default_images_total = isset($settings['images_limit']) ? absint($settings['images_limit']) : 3;
        if ($default_images_total < 1) $default_images_total = 1;
        if ($default_images_total > 4) $default_images_total = 4;
        $default_internal_images = max(0, $default_images_total - 1);
        if (!$internal_images_enabled) {
            $default_internal_images = 0;
        }
        $default_internal_format = (string)($settings['image_format_internal_1'] ?? 'banner_1536x1024');
        $default_internal_style = (strpos($default_internal_format, 'banner') !== false) ? 'banner' : 'normal';
        $default_length = isset($settings['post_length_variant']) ? sanitize_key((string)$settings['post_length_variant']) : 'medium';
        if (!in_array($default_length, array('short', 'medium', 'long'), true)) $default_length = 'medium';
        $default_prompt_profile = sanitize_key((string)($settings['blog_prompt_profile'] ?? 'discover_editorial'));
        $prompt_profile_options = function_exists('cbia_prompt_get_profile_options') ? cbia_prompt_get_profile_options() : array();
        if (!array_key_exists($default_prompt_profile, $prompt_profile_options)) {
            $default_prompt_profile = 'discover_editorial';
        }
        $default_include_faq = array_key_exists('include_faq', (array)$settings) ? !empty($settings['include_faq']) : true;
        $default_include_examples = array_key_exists('include_practical_examples', (array)$settings) ? !empty($settings['include_practical_examples']) : false;
        $default_language = function_exists('cbia_ai_composer_normalize_language_value')
            ? cbia_ai_composer_normalize_language_value(isset($settings['post_language']) ? (string)$settings['post_language'] : 'English')
            : cbia_ai_composer_normalize_language_code(isset($settings['post_language']) ? (string)$settings['post_language'] : 'es');
        $default_temp = isset($settings['openai_temperature']) ? (float)$settings['openai_temperature'] : 0.7;
        $composer_nonce = wp_create_nonce('cbia_ajax_nonce');
        $text_provider = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai';
        $image_provider = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : 'openai';
        $text_api_ok = function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key((string)$text_provider) !== '') : true;
        $image_api_ok = function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key((string)$image_provider) !== '') : true;
		$providers_supported = array('openai', 'google', 'deepseek', 'anthropic');
        $provider_key_state = array();
        $text_model_lists = array();
        $image_model_lists = array();
        $current_text_models = array();
        $current_image_models = array();
        foreach ($providers_supported as $pkey) {
            $provider_key_state[$pkey] = function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key($pkey) !== '') : false;
            $text_model_lists[$pkey] = function_exists('cbia_providers_get_text_model_list') ? cbia_providers_get_text_model_list($pkey) : array();
            $image_model_lists[$pkey] = function_exists('cbia_providers_get_image_model_list') ? cbia_providers_get_image_model_list($pkey) : array();
            $current_text_models[$pkey] = function_exists('cbia_get_text_model_for_provider')
                ? (string)cbia_get_text_model_for_provider($pkey, '')
                : '';
            $current_image_models[$pkey] = function_exists('cbia_get_image_model_for_provider')
                ? (string)cbia_get_image_model_for_provider($pkey, '')
                : '';
        }
        $text_model_lists_json = (string) wp_json_encode($text_model_lists);
        $image_model_lists_json = (string) wp_json_encode($image_model_lists);
        $current_text_models_json = (string) wp_json_encode($current_text_models);
        $current_image_models_json = (string) wp_json_encode($current_image_models);
        $provider_key_state_json = (string) wp_json_encode($provider_key_state);
        $composer_post_id = isset($post->ID) ? absint($post->ID) : 0;
        $initial_snapshot = array();
        if ($composer_post_id > 0) {
            $post_snapshot = cbia_ai_composer_build_snapshot_from_post(
                $composer_post_id,
                (string)$default_language,
                (string)$default_internal_style,
                (string)$default_length,
                (int)$default_internal_images
            );
            $post_has_saved_state =
                trim((string)get_post_field('post_content', $composer_post_id)) !== ''
                || (int)get_post_thumbnail_id($composer_post_id) > 0
                || !empty(wp_get_post_categories($composer_post_id, array('fields' => 'ids')))
                || !empty(wp_get_post_terms($composer_post_id, 'post_tag', array('fields' => 'ids')))
                || trim((string)get_post_meta($composer_post_id, '_yoast_wpseo_metadesc', true)) !== '';
            $snapshot_raw = get_post_meta($composer_post_id, '_cbia_ai_composer_snapshot', true);
            if (is_array($snapshot_raw)) {
                $initial_snapshot = $snapshot_raw;
            } elseif (is_string($snapshot_raw) && $snapshot_raw !== '') {
                $decoded = json_decode($snapshot_raw, true);
                if (is_array($decoded)) {
                    $initial_snapshot = $decoded;
                }
            }
            if ($post_has_saved_state || empty($initial_snapshot)) {
                $initial_snapshot = $post_snapshot;
            }
        }
        $initial_snapshot_json = (string) wp_json_encode($initial_snapshot);
        ?>
        <div id="cbia-ai-composer-modal" class="cbia-modal cbia-composer-modal" style="display:none;" tabindex="0">
            <div class="cbia-modal-inner cbia-composer-modal-inner">
                <div class="cbia-modal-header">
                    <h3><?php echo esc_html__('Create with AI', 'cbiastudio-blogflow-ai'); ?></h3>
                    <button type="button" class="button-link cbia-modal-close" id="cbia-ai-composer-close" aria-label="Close">&times;</button>
                </div>
        <div id="cbia-ai-composer" class="cbia-ai-composer"
             data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
             data-nonce="<?php echo esc_attr($composer_nonce); ?>"
             data-default-length="<?php echo esc_attr((string)$default_length); ?>"
             data-default-language="<?php echo esc_attr((string)$default_language); ?>"
             data-default-images-limit="<?php echo esc_attr((string)$default_images_total); ?>"
             data-text-provider="<?php echo esc_attr((string)$text_provider); ?>"
             data-image-provider="<?php echo esc_attr((string)$image_provider); ?>"
             data-text-model-lists="<?php echo esc_attr($text_model_lists_json); ?>"
             data-image-model-lists="<?php echo esc_attr($image_model_lists_json); ?>"
             data-current-text-models="<?php echo esc_attr($current_text_models_json); ?>"
             data-current-image-models="<?php echo esc_attr($current_image_models_json); ?>"
             data-provider-key-state="<?php echo esc_attr($provider_key_state_json); ?>"
             data-cap-internal-images="<?php echo $internal_images_enabled ? '1' : '0'; ?>"
             data-current-post-id="<?php echo esc_attr((string)$composer_post_id); ?>"
             data-initial-snapshot="<?php echo esc_attr($initial_snapshot_json); ?>"
             data-text-api-configured="<?php echo $text_api_ok ? '1' : '0'; ?>"
             data-image-api-configured="<?php echo $image_api_ok ? '1' : '0'; ?>"
             data-settings-url="<?php echo esc_url(admin_url('admin.php?page=cbia&tab=config')); ?>">
            <p class="description"><?php echo esc_html__('Generate content with AI and then insert it directly into this post.', 'cbiastudio-blogflow-ai'); ?></p>
            <p>
                <label for="cbia-ai-title"><strong><?php echo esc_html__('Title', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                <input type="text" id="cbia-ai-title" class="widefat" value="<?php echo esc_attr(function_exists('cbia_ai_composer_normalize_title_value') ? cbia_ai_composer_normalize_title_value((string)get_the_title($post), $post) : (string)get_the_title($post)); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" />
            </p>
            <div class="cbia-ai-controls">
                <p class="cbia-ai-col-length">
                     <label for="cbia-ai-length"><strong><?php echo esc_html__('Blog text length', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <select id="cbia-ai-length">
                        <option value="short" <?php selected($default_length, 'short'); ?>><?php echo esc_html__('Short', 'cbiastudio-blogflow-ai'); ?></option>
                        <option value="medium" <?php selected($default_length, 'medium'); ?>><?php echo esc_html__('Medium', 'cbiastudio-blogflow-ai'); ?></option>
                        <option value="long" <?php selected($default_length, 'long'); ?>><?php echo esc_html__('Long', 'cbiastudio-blogflow-ai'); ?></option>
                    </select>
                    <span class="description cbia-ai-control-help" id="cbia-ai-length-help"><?php echo esc_html__('Target words will update based on selected length.', 'cbiastudio-blogflow-ai'); ?></span>
                </p>
                <p class="cbia-ai-col-images">
                    <label for="cbia-ai-images"><strong><?php echo esc_html__('Number of internal images', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <select id="cbia-ai-images" <?php disabled(!$internal_images_enabled); ?>>
                        <?php for ($i = 0; $i <= ($internal_images_enabled ? 3 : 0); $i++): ?>
                            <option value="<?php echo esc_attr((string)$i); ?>" <?php selected($default_internal_images, $i); ?>><?php echo esc_html((string)$i); ?></option>
                        <?php endfor; ?>
                    </select>
                    <?php if (!$internal_images_enabled): ?>
                        <span class="description"><?php echo esc_html__('Internal images are available in Pro only.', 'cbiastudio-blogflow-ai'); ?></span>
                    <?php endif; ?>
                </p>
                <p class="cbia-ai-control cbia-ai-control-goal cbia-ai-col-goal">
                    <label for="cbia-ai-prompt-profile">
                        <span class="dashicons dashicons-controls-forward"></span>
                        <strong><?php echo esc_html__('Article goal', 'cbiastudio-blogflow-ai'); ?></strong>
                    </label><br />
                    <select id="cbia-ai-prompt-profile">
                        <?php if (function_exists('cbia_is_auto_prompt_profile_enabled') && cbia_is_auto_prompt_profile_enabled()) : ?>
                            <option value="auto_by_title" <?php selected($default_prompt_profile, 'auto_by_title'); ?>><?php echo esc_html__('Auto by title', 'cbiastudio-blogflow-ai'); ?></option>
                        <?php endif; ?>
                        <option value="discover_editorial" <?php selected($default_prompt_profile, 'discover_editorial'); ?>><?php echo esc_html__('Discover / Editorial', 'cbiastudio-blogflow-ai'); ?></option>
                        <option value="seo_balanced" <?php selected($default_prompt_profile, 'seo_balanced'); ?>><?php echo esc_html__('SEO Standard', 'cbiastudio-blogflow-ai'); ?></option>
                        <option value="how_to" <?php selected($default_prompt_profile, 'how_to'); ?>><?php echo esc_html__('How-to / Practical Guide', 'cbiastudio-blogflow-ai'); ?></option>
                    </select>
                    <span class="description cbia-ai-control-help"><?php echo esc_html((function_exists('cbia_is_auto_prompt_profile_enabled') && cbia_is_auto_prompt_profile_enabled()) ? __('Automatically chooses Editorial, SEO Balanced or How-to from each title.', 'cbiastudio-blogflow-ai') : __('Defines the writing objective. Recommended for most posts: SEO Standard.', 'cbiastudio-blogflow-ai')); ?></span>
                </p>
                <p class="cbia-ai-control cbia-ai-control-toggle cbia-ai-col-faq">
                    <label for="cbia-ai-include-faq" class="cbia-ai-toggle-label">
                        <input type="checkbox" id="cbia-ai-include-faq" value="1" <?php checked($default_include_faq); ?> />
                        <span class="dashicons dashicons-editor-help"></span>
                        <span class="cbia-ai-toggle-copy">
                            <strong><?php echo esc_html__('Include FAQ', 'cbiastudio-blogflow-ai'); ?></strong>
                            <small><?php echo esc_html__('Adds a final FAQ block with Q&A.', 'cbiastudio-blogflow-ai'); ?></small>
                        </span>
                    </label>
                </p>
                <p class="cbia-ai-control cbia-ai-control-toggle cbia-ai-col-examples">
                    <label for="cbia-ai-include-examples" class="cbia-ai-toggle-label">
                        <input type="checkbox" id="cbia-ai-include-examples" value="1" <?php checked($default_include_examples); ?> />
                        <span class="dashicons dashicons-lightbulb"></span>
                        <span class="cbia-ai-toggle-copy">
                            <strong><?php echo esc_html__('Include practical examples', 'cbiastudio-blogflow-ai'); ?></strong>
                            <small><?php echo esc_html__('Adds realistic scenarios and actionable examples.', 'cbiastudio-blogflow-ai'); ?></small>
                        </span>
                    </label>
                </p>
                <p class="cbia-ai-col-language">
                    <label for="cbia-ai-language"><strong><?php echo esc_html__('Language', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <select id="cbia-ai-language">
                        <?php foreach (cbia_ai_composer_get_language_options() as $lang_value => $lang_label) : ?>
                            <option value="<?php echo esc_attr($lang_value); ?>" <?php selected($default_language, $lang_value); ?>><?php echo esc_html($lang_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="cbia-ai-col-style">
                    <label for="cbia-ai-internal-style"><strong><?php echo esc_html__('Internal images style', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <select id="cbia-ai-internal-style" <?php disabled(!$internal_images_enabled); ?>>
                        <option value="banner" <?php selected($default_internal_style, 'banner'); ?>>Banner (cbia-banner)</option>
                        <option value="normal" <?php selected($default_internal_style, 'normal'); ?>>Normal</option>
                    </select>
                </p>
                <p class="cbia-ai-col-temperature">
                    <label for="cbia-ai-temperature"><strong><?php echo esc_html__('Temperature (global)', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <input type="number" id="cbia-ai-temperature" min="0" max="2" step="0.1" value="<?php echo esc_attr((string)$default_temp); ?>" />
                    <span class="description"><?php echo esc_html__('Applied to this generation (0 = more deterministic, 1+ = more creative).', 'cbiastudio-blogflow-ai'); ?></span>
                </p>
            </div>
            <p class="cbia-ai-actions">
                <button type="button" class="button button-primary" id="cbia-ai-generate"><?php echo esc_html__('Generate with AI', 'cbiastudio-blogflow-ai'); ?></button>
                <button type="button" class="button" id="cbia-ai-improve-text"><?php echo esc_html__('Improve text with AI', 'cbiastudio-blogflow-ai'); ?></button>
                <button type="button" class="button" id="cbia-ai-insert" disabled><?php echo esc_html__('Insert into editor', 'cbiastudio-blogflow-ai'); ?></button>
                <button type="button" class="button" id="cbia-ai-config-text" style="display:none;"><?php echo esc_html__('Configure text API', 'cbiastudio-blogflow-ai'); ?></button>
                <button type="button" class="button" id="cbia-ai-config-image" style="display:none;"><?php echo esc_html__('Configure image API', 'cbiastudio-blogflow-ai'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cbia&tab=config')); ?>" class="button" id="cbia-ai-open-settings" style="display:none;"><?php echo esc_html__('Go to CBIA Settings', 'cbiastudio-blogflow-ai'); ?></a>
                <span id="cbia-ai-status" class="description"><?php echo esc_html__('Ready to generate. Enter a title and click "Generate with AI".', 'cbiastudio-blogflow-ai'); ?></span>
                <span id="cbia-ai-word-count" class="description" style="margin-left:8px;"><?php echo esc_html__('0 words', 'cbiastudio-blogflow-ai'); ?></span>
            </p>
            <p class="cbia-ai-actions">
                <span id="cbia-ai-chip-text" class="cbia-ai-chip">Texto: -</span>
                <span id="cbia-ai-chip-image" class="cbia-ai-chip"><?php echo esc_html__('Images: -', 'cbiastudio-blogflow-ai'); ?></span>
                <label style="margin-left:10px;">
                    <input type="checkbox" id="cbia-ai-allow-no-image" value="1" /> <?php echo esc_html__('Generate without images if the image API is missing.', 'cbiastudio-blogflow-ai'); ?>
                </label>
            </p>
            <p id="cbia-ai-summary" class="description"></p>
            <div class="cbia-ai-preview-layout">
                <div class="cbia-ai-featured-wrap">
                    <div class="cbia-ai-image-manager">
                        <div class="cbia-ai-image-manager-head">
                            <strong><?php echo esc_html__('Preview images (featured + internal)', 'cbiastudio-blogflow-ai'); ?></strong>
                        </div>
                        <div id="cbia-ai-image-cards" class="cbia-ai-image-cards">
                            <div class="cbia-ai-image-card is-featured" data-slot-type="featured" data-slot-idx="0">
                                <div class="cbia-ai-image-thumb"><em><?php echo esc_html__('Featured pending', 'cbiastudio-blogflow-ai'); ?></em></div>
                                <div class="cbia-ai-image-meta">
                                    <strong><?php echo esc_html__('Featured', 'cbiastudio-blogflow-ai'); ?></strong>
                                    <button type="button" class="button button-small cbia-ai-image-change"><?php echo esc_html__('Regenerate image', 'cbiastudio-blogflow-ai'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="cbia-ai-image-manager-actions">
                            <button type="button" class="button" id="cbia-ai-complete-missing"><?php echo esc_html__('Complete missing', 'cbiastudio-blogflow-ai'); ?></button>
                            <span class="description"><?php echo esc_html__('Generates only missing images/metadata and applies them to the post.', 'cbiastudio-blogflow-ai'); ?></span>
                        </div>
                    </div>
                </div>
                <div id="cbia-ai-preview" class="cbia-ai-preview"><em><?php echo esc_html__('No preview yet.', 'cbiastudio-blogflow-ai'); ?></em></div>
            </div>
                    </div>
            </div>
        </div>
        <div id="cbia-ai-image-modal" class="cbia-modal" style="display:none;">
            <div class="cbia-modal-inner" style="max-width:640px;">
                <div class="cbia-modal-header">
                    <h3 id="cbia-ai-image-title"><?php echo esc_html__('Regenerate AI image', 'cbiastudio-blogflow-ai'); ?></h3>
                    <button type="button" class="button-link cbia-modal-close" id="cbia-ai-image-close" aria-label="Close">&times;</button>
                </div>
                <p class="description" id="cbia-ai-image-help"><?php echo esc_html__('Enter a prompt to regenerate only this slot.', 'cbiastudio-blogflow-ai'); ?></p>
                <p>
                    <label for="cbia-ai-image-prompt"><strong><?php echo esc_html__('Image prompt', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <textarea id="cbia-ai-image-prompt" class="widefat" rows="4"></textarea>
                </p>
                <div class="cbia-modal-actions">
                    <button type="button" class="button button-primary" id="cbia-ai-image-regenerate"><?php echo esc_html__('Regenerate with AI', 'cbiastudio-blogflow-ai'); ?></button>
                    <button type="button" class="button" id="cbia-ai-image-library"><?php echo esc_html__('Use from library', 'cbiastudio-blogflow-ai'); ?></button>
                    <button type="button" class="button" id="cbia-ai-image-cancel"><?php echo esc_html__('Cancel', 'cbiastudio-blogflow-ai'); ?></button>
                    <span id="cbia-ai-image-status" class="description"></span>
                </div>
            </div>
        </div>
        <div id="cbia-ai-key-modal" class="cbia-modal" style="display:none;">
            <div class="cbia-modal-inner" style="max-width:520px;">
                <div class="cbia-modal-header">
                    <h3 id="cbia-ai-key-title"><?php echo esc_html__('Configure API key', 'cbiastudio-blogflow-ai'); ?></h3>
                    <button type="button" class="button-link cbia-modal-close" id="cbia-ai-key-close" aria-label="Close">&times;</button>
                </div>
                <p class="description" id="cbia-ai-key-help"></p>
                <p>
                    <label for="cbia-ai-key-provider"><strong><?php echo esc_html__('Provider', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <select id="cbia-ai-key-provider" class="widefat">
                        <option value="openai">OpenAI</option>
                        <option value="google">Google</option>
                        <option value="deepseek">DeepSeek</option>
                    </select>
                </p>
                <p>
                    <label for="cbia-ai-key-model"><strong><?php echo esc_html__('Model', 'cbiastudio-blogflow-ai'); ?></strong></label><br />
                    <select id="cbia-ai-key-model" class="widefat"></select>
                </p>
                <p>
                    <label for="cbia-ai-key-input"><strong>API key</strong></label><br />
                    <input type="text" id="cbia-ai-key-input" class="widefat cbia-secret-input" value="" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" />
                </p>
                <div class="cbia-modal-actions">
                    <button type="button" class="button" id="cbia-ai-key-test"><?php echo esc_html__('Test connection', 'cbiastudio-blogflow-ai'); ?></button>
                    <button type="button" class="button button-primary" id="cbia-ai-key-save"><?php echo esc_html__('Save key', 'cbiastudio-blogflow-ai'); ?></button>
                    <button type="button" class="button" id="cbia-ai-key-cancel"><?php echo esc_html__('Cancel', 'cbiastudio-blogflow-ai'); ?></button>
                    <span id="cbia-ai-key-status" class="description"></span>
                </div>
            </div>
        </div>
        
        <?php
    }
}

if (!function_exists('cbia_pro_output_banner_css_admin')) {
    function cbia_pro_output_banner_css_admin() {
        if (!function_exists('cbia_get_settings')) return;
        $settings = cbia_get_settings();
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen) {
            $is_plugin_screen = (strpos((string)$screen->id, 'ai-blog-builder') !== false) || (strpos((string)$screen->id, 'cbia') !== false);
            $is_post_editor = in_array((string)$screen->base, array('post', 'post-new'), true);
            if (!$is_plugin_screen && !$is_post_editor) {
                return;
            }
        }

        $css = cbia_pro_sanitize_custom_css($settings['content_images_banner_css'] ?? '');
        if ($css === '' && function_exists('cbia_get_default_settings')) {
            $defaults = cbia_get_default_settings();
            $css = cbia_pro_sanitize_custom_css($defaults['content_images_banner_css'] ?? '');
        }
        if ($css === '') return;
        if (!wp_style_is('cbia-pro-banner-css-admin-inline', 'registered')) {
            wp_register_style('cbia-pro-banner-css-admin-inline', false, array(), CBIA_PRO_VERSION);
        }
        wp_enqueue_style('cbia-pro-banner-css-admin-inline');
        wp_add_inline_style('cbia-pro-banner-css-admin-inline', $css);
    }
}

if (!function_exists('cbia_ajax_preview_article')) {
    function cbia_ajax_preview_article() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }
        if (function_exists('cbia_set_stop_flag')) {
            cbia_set_stop_flag(false);
        }
        $openai_temperature_raw = isset($_POST['openai_temperature'])
            ? sanitize_text_field((string) wp_unslash($_POST['openai_temperature']))
            : '';
        $blog_prompt_custom_raw = isset($_POST['blog_prompt_custom_instructions'])
            ? (string) wp_unslash($_POST['blog_prompt_custom_instructions'])
            : '';

        $composer_mode = isset($_POST['composer_mode']) ? absint(wp_unslash($_POST['composer_mode'])) : 0;
        $payload = array(
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'preview_mode' => isset($_POST['preview_mode']) ? sanitize_key((string)wp_unslash($_POST['preview_mode'])) : 'fast',
            'composer_mode' => $composer_mode,
            'current_post_id' => isset($_POST['current_post_id']) ? absint(wp_unslash($_POST['current_post_id'])) : 0,
            'images_limit' => isset($_POST['images_limit']) ? absint(wp_unslash($_POST['images_limit'])) : 3,
            'internal_image_style' => isset($_POST['internal_image_style']) ? sanitize_key((string)wp_unslash($_POST['internal_image_style'])) : '',
            'skip_images' => isset($_POST['skip_images']) ? absint(wp_unslash($_POST['skip_images'])) : 0,
            'post_language' => isset($_POST['post_language']) ? sanitize_text_field(wp_unslash($_POST['post_language'])) : '',
            'blog_prompt_mode' => isset($_POST['blog_prompt_mode']) ? sanitize_key((string)wp_unslash($_POST['blog_prompt_mode'])) : '',
            'blog_prompt_profile' => isset($_POST['blog_prompt_profile']) ? sanitize_key((string)wp_unslash($_POST['blog_prompt_profile'])) : 'discover_editorial',
            'post_length_variant' => isset($_POST['post_length_variant']) ? sanitize_key((string)wp_unslash($_POST['post_length_variant'])) : 'medium',
            'include_faq' => isset($_POST['include_faq']) ? absint(wp_unslash($_POST['include_faq'])) : ($composer_mode ? 0 : 1),
            'include_practical_examples' => isset($_POST['include_practical_examples']) ? absint(wp_unslash($_POST['include_practical_examples'])) : 0,
            'openai_temperature' => ($openai_temperature_raw !== '') ? (float) str_replace(',', '.', $openai_temperature_raw) : null,
            'blog_prompt_custom_instructions' => function_exists('cbia_prompt_sanitize_custom_instructions')
                ? cbia_prompt_sanitize_custom_instructions($blog_prompt_custom_raw)
                : sanitize_textarea_field($blog_prompt_custom_raw),
            'blog_prompt_editable' => isset($_POST['blog_prompt_editable']) ? sanitize_textarea_field(wp_unslash($_POST['blog_prompt_editable'])) : '',
            'legacy_full_prompt' => isset($_POST['legacy_full_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['legacy_full_prompt'])) : '',
        );
        $payload = cbia_enforce_internal_images_cap_on_payload($payload);
        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $service = $container->get('article_preview_service');
        }
        if (!$service && class_exists('CBIA_Article_Preview_Service')) {
            $service = new CBIA_Article_Preview_Service();
        }
        if (!$service || !method_exists($service, 'generate')) {
            wp_send_json_error(array('message' => __('Preview service unavailable', 'cbiastudio-blogflow-ai')), 500);
        }

        $result = $service->generate($payload);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success($result);
    }
}

if (!function_exists('cbia_sse_emit')) {
    function cbia_sse_emit($event, array $payload) {
        $safe_event = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$event);
        echo 'event: ' . esc_html((string)$safe_event) . "\n";
        echo 'data: ' . wp_json_encode($payload) . "\n\n";
        // Padding frame helps some proxy/server stacks flush each SSE chunk immediately.
        echo esc_html(':' . str_repeat(' ', 2048)) . "\n\n";
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
        }
        @flush();
    }
}

if (!function_exists('cbia_ajax_preview_article_stream')) {
    function cbia_ajax_preview_article_stream() {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE streaming needs output buffering disabled.
        @ini_set('zlib.output_compression', '0');
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE streaming needs output buffering disabled.
        @ini_set('output_buffering', 'off');
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE streaming needs output buffering disabled.
        @ini_set('implicit_flush', '1');
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE streaming needs output buffering disabled.
        @ini_set('display_errors', '0');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE debug fallback in WP_DEBUG.
            @ini_set('log_errors', '1');
        }
        nocache_headers();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');

        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            cbia_sse_emit('preview_error', array('message' => __('Unauthorized.', 'cbiastudio-blogflow-ai')));
            cbia_sse_emit('cbia_error', array('message' => __('Unauthorized.', 'cbiastudio-blogflow-ai')));
            exit;
        }
        if (function_exists('cbia_set_stop_flag')) {
            cbia_set_stop_flag(false);
        }

        $openai_temperature_raw = isset($_GET['openai_temperature'])
            ? sanitize_text_field((string) wp_unslash($_GET['openai_temperature']))
            : '';
        $blog_prompt_custom_raw = isset($_GET['blog_prompt_custom_instructions'])
            ? (string) wp_unslash($_GET['blog_prompt_custom_instructions'])
            : '';
        $composer_mode = isset($_GET['composer_mode']) ? absint(wp_unslash($_GET['composer_mode'])) : 0;
        $payload = array(
            'title' => isset($_GET['title']) ? sanitize_text_field(wp_unslash($_GET['title'])) : '',
            'preview_mode' => isset($_GET['preview_mode']) ? sanitize_key((string)wp_unslash($_GET['preview_mode'])) : 'fast',
            'composer_mode' => $composer_mode,
            'current_post_id' => isset($_GET['current_post_id']) ? absint(wp_unslash($_GET['current_post_id'])) : 0,
            'images_limit' => isset($_GET['images_limit']) ? absint(wp_unslash($_GET['images_limit'])) : 3,
            'internal_image_style' => isset($_GET['internal_image_style']) ? sanitize_key((string)wp_unslash($_GET['internal_image_style'])) : '',
            'skip_images' => isset($_GET['skip_images']) ? absint(wp_unslash($_GET['skip_images'])) : 0,
            'post_language' => isset($_GET['post_language']) ? sanitize_text_field(wp_unslash($_GET['post_language'])) : '',
            'blog_prompt_mode' => isset($_GET['blog_prompt_mode']) ? sanitize_key((string)wp_unslash($_GET['blog_prompt_mode'])) : '',
            'blog_prompt_profile' => isset($_GET['blog_prompt_profile']) ? sanitize_key((string)wp_unslash($_GET['blog_prompt_profile'])) : 'discover_editorial',
            'post_length_variant' => isset($_GET['post_length_variant']) ? sanitize_key((string)wp_unslash($_GET['post_length_variant'])) : 'medium',
            'include_faq' => isset($_GET['include_faq']) ? absint(wp_unslash($_GET['include_faq'])) : ($composer_mode ? 0 : 1),
            'include_practical_examples' => isset($_GET['include_practical_examples']) ? absint(wp_unslash($_GET['include_practical_examples'])) : 0,
            'openai_temperature' => ($openai_temperature_raw !== '') ? (float) str_replace(',', '.', $openai_temperature_raw) : null,
            // En SSE aceptamos solo instrucciones compactas para no crecer demasiado la URL.
            'blog_prompt_custom_instructions' => function_exists('cbia_prompt_sanitize_custom_instructions')
                ? cbia_prompt_sanitize_custom_instructions($blog_prompt_custom_raw)
                : sanitize_textarea_field($blog_prompt_custom_raw),
            'blog_prompt_editable' => '',
            'legacy_full_prompt' => '',
        );
        $payload = cbia_enforce_internal_images_cap_on_payload($payload);

        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $service = $container->get('article_preview_service');
        }
        if (!$service && class_exists('CBIA_Article_Preview_Service')) {
            $service = new CBIA_Article_Preview_Service();
        }
        if (!$service) {
            cbia_sse_emit('preview_error', array('message' => __('Preview service unavailable.', 'cbiastudio-blogflow-ai')));
            cbia_sse_emit('cbia_error', array('message' => __('Preview service unavailable.', 'cbiastudio-blogflow-ai')));
            exit;
        }

        cbia_sse_emit('cbia_status', array('message' => __('Starting preview...', 'cbiastudio-blogflow-ai')));
        cbia_sse_emit('cbia_ping', array('ts' => time()));
        if (method_exists($service, 'generate_stream')) {
            $result = $service->generate_stream($payload, function($event, $data) {
                cbia_sse_emit($event, is_array($data) ? $data : array());
            });
        } else {
            $result = $service->generate($payload);
        }

        if (is_wp_error($result)) {
            cbia_sse_emit('preview_error', array('message' => $result->get_error_message()));
            cbia_sse_emit('cbia_error', array('message' => $result->get_error_message()));
            exit;
        }
        cbia_sse_emit('preview_done', array(
            'ok' => 1,
            'preview_token' => is_array($result) ? (string)($result['preview_token'] ?? '') : '',
            'post_id' => is_array($result) ? (int)($result['post_id'] ?? 0) : 0,
            'word_count' => is_array($result) ? (int)($result['word_count'] ?? 0) : 0,
            'result' => $result
        ));
        cbia_sse_emit('cbia_done', array('result' => $result));
        exit;
    }
}

if (!function_exists('cbia_ajax_cancel_preview')) {
    function cbia_ajax_cancel_preview() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }
        $token = isset($_POST['preview_token']) ? sanitize_text_field(wp_unslash($_POST['preview_token'])) : '';
        if ($token === '') {
            wp_send_json_error(array('message' => __('Preview token is required.', 'cbiastudio-blogflow-ai')), 400);
        }

        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $service = $container->get('article_preview_service');
        }
        if (!$service && class_exists('CBIA_Article_Preview_Service')) {
            $service = new CBIA_Article_Preview_Service();
        }
        if (!$service || !method_exists($service, 'cancel_preview')) {
            wp_send_json_error(array('message' => __('Preview service unavailable', 'cbiastudio-blogflow-ai')), 500);
        }

        $result = $service->cancel_preview($token);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }
        wp_send_json_success($result);
    }
}

if (!function_exists('cbia_ajax_parse_csv_titles')) {
    function cbia_ajax_parse_csv_titles($body) {
        $lines = preg_split('/\r\n|\r|\n/', (string)$body);
        $titles = array();
        foreach ((array)$lines as $idx => $line) {
            $line = trim((string)$line);
            if ($line === '') continue;
            $row = str_getcsv($line);
            $first = trim((string)($row[0] ?? ''));
            if ($first === '') continue;
            $first_l = strtolower($first);
            if ($idx === 0 && in_array($first_l, array('title', 'titulo', 'tÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­tulo'), true)) continue;
            $titles[] = $first;
        }
        return array_values(array_unique(array_filter(array_map('trim', $titles))));
    }
}

if (!function_exists('cbia_ajax_normalize_csv_url')) {
    function cbia_ajax_normalize_csv_url($url) {
        if (function_exists('cbia_pro_validate_remote_csv_url')) {
            return cbia_pro_validate_remote_csv_url($url);
        }
        $url = trim((string)$url);
        if ($url === '') return '';
        return (function_exists('wp_http_validate_url') && wp_http_validate_url($url)) ? $url : '';
    }
}

if (!function_exists('cbia_ajax_test_csv_titles')) {
    function cbia_ajax_test_csv_titles() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }
        $csv_url_raw = isset($_POST['csv_url']) ? sanitize_text_field(wp_unslash((string)$_POST['csv_url'])) : '';
        if ($csv_url_raw === '') {
            wp_send_json_error(array('message' => 'CSV URL is required.'), 400);
        }
        $csv_url = cbia_ajax_normalize_csv_url($csv_url_raw);
        if ($csv_url === '') {
            wp_send_json_error(array('message' => 'CSV URL is invalid or blocked.'), 400);
        }
        $resp = wp_remote_get($csv_url, array('timeout' => 20));
        if (is_wp_error($resp)) {
            wp_send_json_error(array('message' => $resp->get_error_message()), 400);
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code >= 400) {
            wp_send_json_error(array('message' => 'HTTP ' . $code), 400);
        }
        $body = (string)wp_remote_retrieve_body($resp);
        $titles = cbia_ajax_parse_csv_titles($body);
        wp_send_json_success(array(
            'normalized_url' => $csv_url,
            'total' => count($titles),
            'titles' => array_slice($titles, 0, 10),
        ));
    }
}

if (!function_exists('cbia_provider_connection_label')) {
    function cbia_provider_connection_label(string $provider): string {
        $provider = sanitize_key($provider);
        $definition = function_exists('cbia_providers_get_provider') ? cbia_providers_get_provider($provider) : array();
		$fallbacks = array('openai' => 'OpenAI', 'google' => 'Google', 'deepseek' => 'DeepSeek', 'anthropic' => 'Anthropic');
        return sanitize_text_field((string)($definition['label'] ?? ($fallbacks[$provider] ?? $provider)));
    }
}

if (!function_exists('cbia_provider_connection_public_state')) {
    function cbia_provider_connection_public_state(string $provider): array {
        $status = cbia_get_provider_connection_status($provider);
        $labels = array(
            'not_configured' => __('Not configured', 'cbiastudio-blogflow-ai'),
            'not_tested' => __('Connection not checked', 'cbiastudio-blogflow-ai'),
            'verified' => __('Connection verified', 'cbiastudio-blogflow-ai'),
            'authentication_error' => __('Key rejected', 'cbiastudio-blogflow-ai'),
            'configuration_error' => __('Provider URL must be corrected', 'cbiastudio-blogflow-ai'),
        );
        $state = isset($labels[$status['status']]) ? (string)$status['status'] : 'not_tested';
        $definition = function_exists('cbia_providers_get_provider') ? cbia_providers_get_provider($provider) : array();
        $base_check = cbia_validate_provider_base_url($provider, (string)($definition['base_url'] ?? ''), null, false);
        if (empty($base_check['valid'])) $state = 'configuration_error';
        return array(
            'provider' => sanitize_key($provider),
            'configured' => !empty($status['configured']),
            'status' => $state,
            'statusLabel' => $labels[$state],
            'lastSuccess' => (string)($status['last_success'] ?? ''),
            'lastSuccessLabel' => !empty($status['last_success']) ? sprintf(__('Last successful check: %s', 'cbiastudio-blogflow-ai'), (string)$status['last_success']) : '',
            'modelsCount' => isset($status['models_count']) ? (int)$status['models_count'] : null,
            'modelsCountLabel' => isset($status['models_count']) ? sprintf(__('Models returned by the provider: %d', 'cbiastudio-blogflow-ai'), (int)$status['models_count']) : '',
            'connectLabel' => !empty($status['configured']) ? __('Update key', 'cbiastudio-blogflow-ai') : __('Connect', 'cbiastudio-blogflow-ai'),
            'disconnectLabel' => __('Disconnect', 'cbiastudio-blogflow-ai'),
            'placeholder' => !empty($status['configured']) ? __('Enter a new key to replace the current one', 'cbiastudio-blogflow-ai') : sprintf(__('Enter your %s API key', 'cbiastudio-blogflow-ai'), cbia_provider_connection_label($provider)),
        );
    }
}

if (!function_exists('cbia_test_provider_connection')) {
    function cbia_test_provider_connection(string $provider): array {
        $provider = sanitize_key($provider);
        if (!in_array($provider, cbia_supported_credential_providers(), true)) return array('ok' => false, 'code' => 'invalid_provider', 'message' => __('Invalid provider.', 'cbiastudio-blogflow-ai'));
        $key = cbia_get_provider_api_key($provider);
        if ($key === '') return array('ok' => false, 'code' => 'missing_key', 'message' => __('That provider has no saved API key.', 'cbiastudio-blogflow-ai'));

        $cfg = function_exists('cbia_providers_get_provider') ? cbia_providers_get_provider($provider) : array();
        $base_url = rtrim((string)($cfg['base_url'] ?? ''), '/');
        $api_version = trim((string)($cfg['api_version'] ?? ''), '/');
        $url = '';
        $args = array('timeout' => 20, 'headers' => array());
        if ($provider === 'openai') {
            $url = ($base_url !== '' ? $base_url : 'https://api.openai.com') . '/' . ($api_version !== '' ? $api_version : 'v1') . '/models';
        } elseif ($provider === 'google') {
            $url = ($base_url !== '' ? $base_url : 'https://generativelanguage.googleapis.com') . '/' . ($api_version !== '' ? $api_version : 'v1beta') . '/models';
        } elseif ($provider === 'deepseek') {
            $url = ($base_url !== '' ? $base_url : 'https://api.deepseek.com') . '/' . ($api_version !== '' ? $api_version : 'v1') . '/models';
		} elseif ($provider === 'anthropic') {
			$url = ($base_url !== '' ? $base_url : 'https://api.anthropic.com') . '/' . ($api_version !== '' ? $api_version : 'v1') . '/models';
        }
        $response = cbia_provider_safe_remote_get($provider, $url, $args, $key);
        if (is_wp_error($response)) return array('ok' => false, 'code' => 'network_error', 'message' => cbia_sanitize_provider_error($provider, $response->get_error_message()));
        $http_code = (int)wp_remote_retrieve_response_code($response);
        $data = json_decode((string)wp_remote_retrieve_body($response), true);
        if ($http_code < 200 || $http_code >= 300) {
            $auth_error = in_array($http_code, array(401, 403), true);
            return array(
                'ok' => false,
                'code' => $auth_error ? 'authentication_error' : 'http_error',
                'message' => $auth_error ? sprintf(__('%s rejected the configured API key.', 'cbiastudio-blogflow-ai'), cbia_provider_connection_label($provider)) : sprintf(__('The provider connection failed with HTTP %d.', 'cbiastudio-blogflow-ai'), $http_code),
            );
        }
        if (!is_array($data)) return array('ok' => false, 'code' => 'invalid_response', 'message' => __('The provider returned an invalid response.', 'cbiastudio-blogflow-ai'));
        $count = $provider === 'google' ? count((array)($data['models'] ?? array())) : count((array)($data['data'] ?? array()));
        return array('ok' => true, 'code' => 'verified', 'message' => __('Connection verified.', 'cbiastudio-blogflow-ai'), 'models_count' => $count);
    }
}

if (!function_exists('cbia_ajax_provider_connection_save')) {
    function cbia_ajax_provider_connection_save() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        $provider = isset($_POST['provider']) ? sanitize_key((string)wp_unslash($_POST['provider'])) : '';
        if (!isset($_POST['api_key'])) wp_send_json_error(array('message' => __('API key is required.', 'cbiastudio-blogflow-ai')), 400);
        $candidate = (string)wp_unslash($_POST['api_key']);
        if (!in_array($provider, cbia_supported_credential_providers(), true)) wp_send_json_error(array('message' => __('Invalid provider.', 'cbiastudio-blogflow-ai')), 400);
        $result = cbia_save_provider_api_key($provider, $candidate);
        if (empty($result['valid'])) wp_send_json_error(array('message' => cbia_provider_api_key_error_message($provider, (string)$result['code'])), 400);
        wp_send_json_success(array('message' => sprintf(__('The %s API key was saved successfully.', 'cbiastudio-blogflow-ai'), cbia_provider_connection_label($provider)), 'connection' => cbia_provider_connection_public_state($provider)));
    }
}

if (!function_exists('cbia_ajax_provider_connection_test')) {
    function cbia_ajax_provider_connection_test() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        $provider = isset($_POST['provider']) ? sanitize_key((string)wp_unslash($_POST['provider'])) : '';
        if (!in_array($provider, cbia_supported_credential_providers(), true)) wp_send_json_error(array('message' => __('Invalid provider.', 'cbiastudio-blogflow-ai')), 400);
        $result = cbia_test_provider_connection($provider);
        cbia_mark_provider_test_result($provider, array(
            'status' => !empty($result['ok']) ? 'verified' : (($result['code'] ?? '') === 'authentication_error' ? 'authentication_error' : 'not_tested'),
            'models_count' => $result['models_count'] ?? null,
        ));
        $payload = array('message' => (string)$result['message'], 'connection' => cbia_provider_connection_public_state($provider));
        if (empty($result['ok'])) wp_send_json_error($payload, 400);
        wp_send_json_success($payload);
    }
}

if (!function_exists('cbia_ajax_provider_connection_disconnect')) {
    function cbia_ajax_provider_connection_disconnect() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        $provider = isset($_POST['provider']) ? sanitize_key((string)wp_unslash($_POST['provider'])) : '';
        if (!in_array($provider, cbia_supported_credential_providers(), true)) wp_send_json_error(array('message' => __('Invalid provider.', 'cbiastudio-blogflow-ai')), 400);
        cbia_delete_provider_api_key($provider);
        wp_send_json_success(array('message' => sprintf(__('%s was disconnected.', 'cbiastudio-blogflow-ai'), cbia_provider_connection_label($provider)), 'connection' => cbia_provider_connection_public_state($provider)));
    }
}

if (!function_exists('cbia_ajax_ai_composer_save_api_key')) {
    function cbia_ajax_ai_composer_save_api_key() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }
        $provider = isset($_POST['provider']) ? sanitize_key((string)wp_unslash($_POST['provider'])) : '';
        $key = isset($_POST['api_key']) ? (string)wp_unslash($_POST['api_key']) : '';
        $scope = isset($_POST['scope']) ? sanitize_key((string)wp_unslash($_POST['scope'])) : '';
        $model = isset($_POST['model']) ? sanitize_text_field((string)wp_unslash($_POST['model'])) : '';
        $use_existing_key = isset($_POST['use_existing_key']) ? absint(wp_unslash($_POST['use_existing_key'])) : 0;
		if (!in_array($provider, array('openai', 'google', 'deepseek', 'anthropic'), true)) {
            wp_send_json_error(array('message' => __('Invalid provider.', 'cbiastudio-blogflow-ai')), 400);
        }
        if ($scope === 'image' && !in_array($provider, array('openai', 'google'), true)) {
            wp_send_json_error(array('message' => __('The image provider does not support image generation.', 'cbiastudio-blogflow-ai')), 400);
        }
        if ($scope === 'image' && $provider === 'openai' && $model !== '' && class_exists('CBIA_Image_Pricing_Service')) {
            $model = CBIA_Image_Pricing_Service::validate_model($model);
        }
        if ($key !== '') {
            $key_result = cbia_sanitize_provider_api_key($provider, $key);
            if (empty($key_result['valid'])) {
                wp_send_json_error(array('message' => cbia_provider_api_key_error_message($provider, (string)$key_result['code'])), 400);
            }
            $key = (string)$key_result['value'];
        } elseif (!$use_existing_key) {
            wp_send_json_error(array('message' => __('API key cannot be empty.', 'cbiastudio-blogflow-ai')), 400);
        }

        if ($key !== '') {
            $stored = cbia_store_provider_api_key($provider, $key);
            if (empty($stored['valid'])) wp_send_json_error(array('message' => cbia_provider_api_key_error_message($provider, (string)$stored['code'])), 400);
        } elseif ($use_existing_key && cbia_get_provider_api_key($provider) === '') {
            wp_send_json_error(array('message' => __('That provider has no saved API key.', 'cbiastudio-blogflow-ai')), 400);
        }

        $text_provider = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai';
        $image_provider = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : 'openai';
        $text_ok = function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key((string)$text_provider) !== '') : true;
        $image_ok = function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key((string)$image_provider) !== '') : true;
        $provider_key_state = array(
            'openai' => function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key('openai') !== '') : false,
            'google' => function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key('google') !== '') : false,
            'deepseek' => function_exists('cbia_get_provider_api_key') ? (cbia_get_provider_api_key('deepseek') !== '') : false,
        );

        wp_send_json_success(array(
            'message' => __('API key saved.', 'cbiastudio-blogflow-ai'),
            'provider' => $provider,
            'scope' => $scope,
            'textProvider' => (string)$text_provider,
            'imageProvider' => (string)$image_provider,
            'textModel' => function_exists('cbia_get_text_model_for_provider') ? (string)cbia_get_text_model_for_provider((string)$text_provider, '') : '',
            'imageModel' => function_exists('cbia_get_image_model_for_provider') ? (string)cbia_get_image_model_for_provider((string)$image_provider, '') : '',
            'textApiConfigured' => (bool)$text_ok,
            'imageApiConfigured' => (bool)$image_ok,
            'providerKeyState' => $provider_key_state,
        ));
    }
}

if (!function_exists('cbia_ajax_ai_composer_test_api_key')) {
    function cbia_ajax_ai_composer_test_api_key() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }
        $provider = isset($_POST['provider']) ? sanitize_key((string)wp_unslash($_POST['provider'])) : '';
        $scope = isset($_POST['scope']) ? sanitize_key((string)wp_unslash($_POST['scope'])) : 'text';
        $key = isset($_POST['api_key']) ? (string)wp_unslash($_POST['api_key']) : '';
        $use_existing_key = isset($_POST['use_existing_key']) ? absint(wp_unslash($_POST['use_existing_key'])) : 0;
		if (!in_array($provider, array('openai', 'google', 'deepseek', 'anthropic'), true)) {
            wp_send_json_error(array('message' => __('Invalid provider.', 'cbiastudio-blogflow-ai')), 400);
        }
        if ($scope === 'image' && !in_array($provider, array('openai', 'google'), true)) {
            wp_send_json_error(array('message' => __('The image provider does not support image generation.', 'cbiastudio-blogflow-ai')), 400);
        }
        if ($key === '' && $use_existing_key) {
            if (function_exists('cbia_get_provider_api_key')) {
                $key = (string)cbia_get_provider_api_key($provider);
            } else {
                $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
                $map = array(
                    'openai' => 'openai_api_key',
                    'google' => 'google_api_key',
                    'deepseek' => 'deepseek_api_key',
					'anthropic' => 'anthropic_api_key',
                );
                $field = isset($map[$provider]) ? $map[$provider] : '';
                if ($field !== '') {
                    $key = (string)($settings[$field] ?? '');
                }
            }
        }
        if ($key !== '') {
            $key_result = cbia_sanitize_provider_api_key($provider, $key);
            if (empty($key_result['valid'])) {
                wp_send_json_error(array('message' => cbia_provider_api_key_error_message($provider, (string)$key_result['code'])), 400);
            }
            $key = (string)$key_result['value'];
        }
        if ($key === '') {
            wp_send_json_error(array('message' => __('API key cannot be empty.', 'cbiastudio-blogflow-ai')), 400);
        }

        $cfg = function_exists('cbia_providers_get_provider') ? cbia_providers_get_provider($provider) : array();
        if (!is_array($cfg)) $cfg = array();
        $cfg['api_key'] = $key;
        $base_url = rtrim((string)($cfg['base_url'] ?? ''), '/');
        $api_version = trim((string)($cfg['api_version'] ?? ''), '/');
        $url = '';
        $args = array('timeout' => 20, 'headers' => array());
        if ($provider === 'openai') {
            $base_url = $base_url !== '' ? $base_url : 'https://api.openai.com';
            $api_version = $api_version !== '' ? $api_version : 'v1';
            $url = $base_url . '/' . $api_version . '/models';
        } elseif ($provider === 'google') {
            $base_url = $base_url !== '' ? $base_url : 'https://generativelanguage.googleapis.com';
            $api_version = $api_version !== '' ? $api_version : 'v1beta';
            $url = $base_url . '/' . $api_version . '/models';
        } elseif ($provider === 'deepseek') {
            $base_url = $base_url !== '' ? $base_url : 'https://api.deepseek.com';
            $api_version = $api_version !== '' ? $api_version : 'v1';
            $url = $base_url . '/' . $api_version . '/models';
        }
        if ($url === '') {
            wp_send_json_error(array('message' => __('Could not build provider validation URL.', 'cbiastudio-blogflow-ai')), 400);
        }
        $resp = cbia_provider_safe_remote_get($provider, $url, $args, $key);
        if (is_wp_error($resp)) {
            wp_send_json_error(array('message' => cbia_sanitize_provider_error($provider, $resp->get_error_message())), 400);
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if ($code < 200 || $code >= 300) {
            $message = 'HTTP ' . $code;
            if (is_array($data)) {
                if (!empty($data['error']['message'])) {
                    $message .= ' | ' . sanitize_text_field((string)$data['error']['message']);
                } elseif (!empty($data['message'])) {
                    $message .= ' | ' . sanitize_text_field((string)$data['message']);
                }
            }
            wp_send_json_error(array('message' => 'HTTP ' . $code . ' | ' . cbia_sanitize_provider_error($provider, $message, $code)), 400);
        }
        $count = 0;
        if ($provider === 'openai' || $provider === 'deepseek' || $provider === 'anthropic') {
            $count = (is_array($data) && !empty($data['data']) && is_array($data['data'])) ? count($data['data']) : 0;
        } elseif ($provider === 'google') {
            $count = (is_array($data) && !empty($data['models']) && is_array($data['models'])) ? count($data['models']) : 0;
        }
        wp_send_json_success(array(
            'message' => __('Connection verified.', 'cbiastudio-blogflow-ai'),
            'count' => (int)$count,
            'provider' => $provider,
            'scope' => $scope,
        ));
    }
}

if (!function_exists('cbia_ajax_ai_composer_load_snapshot')) {
    function cbia_ajax_ai_composer_load_snapshot() {
        check_ajax_referer('cbia_ajax_nonce');

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid post.', 'cbiastudio-blogflow-ai')), 400);
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'cbiastudio-blogflow-ai')), 403);
        }

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
        $default_internal_format = (string)($settings['image_format_internal_1'] ?? 'banner_1536x1024');
        $default_internal_style = (strpos($default_internal_format, 'banner') !== false) ? 'banner' : 'normal';
        $default_language = function_exists('cbia_ai_composer_normalize_language_value')
            ? cbia_ai_composer_normalize_language_value(isset($settings['post_language']) ? (string)$settings['post_language'] : 'English')
            : cbia_ai_composer_normalize_language_code(isset($settings['post_language']) ? (string)$settings['post_language'] : 'es');
        $default_length = isset($settings['post_length_variant']) ? sanitize_key((string)$settings['post_length_variant']) : 'medium';
        if (!in_array($default_length, array('short', 'medium', 'long'), true)) $default_length = 'medium';
        $default_images_total = isset($settings['images_limit']) ? absint($settings['images_limit']) : 3;
        if ($default_images_total < 1) $default_images_total = 1;
        if ($default_images_total > 4) $default_images_total = 4;
        $default_internal_images = max(0, $default_images_total - 1);

        $snapshot_raw = get_post_meta($post_id, '_cbia_ai_composer_snapshot', true);
        $snapshot = array();
        if (is_array($snapshot_raw)) {
            $snapshot = $snapshot_raw;
        } elseif (is_string($snapshot_raw) && trim($snapshot_raw) !== '') {
            $decoded = json_decode((string)$snapshot_raw, true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        if (empty($snapshot)) {
            $snapshot = cbia_ai_composer_build_snapshot_from_post(
                $post_id,
                (string)$default_language,
                (string)$default_internal_style,
                (string)$default_length,
                (int)$default_internal_images
            );
            if (!empty($snapshot)) {
                update_post_meta($post_id, '_cbia_ai_composer_snapshot', $snapshot);
            }
        } else {
            $snapshot = cbia_ai_composer_sanitize_snapshot($snapshot);
        }

        if (empty($snapshot)) {
            wp_send_json_error(array('message' => 'Could not load snapshot.'), 500);
        }

        wp_send_json_success(array(
            'snapshot' => $snapshot,
            'source' => 'server',
            'post_id' => $post_id,
        ));
    }
}

if (!function_exists('cbia_ai_composer_sanitize_snapshot')) {
    function cbia_ai_composer_sanitize_snapshot($raw_snapshot) {
        if (is_string($raw_snapshot)) {
            $decoded = json_decode((string)$raw_snapshot, true);
            $raw_snapshot = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($raw_snapshot)) {
            return array();
        }

        $out = array();
        $out['version'] = isset($raw_snapshot['version']) ? absint($raw_snapshot['version']) : 1;
        $out['post_id'] = isset($raw_snapshot['post_id']) ? absint($raw_snapshot['post_id']) : 0;
        $out['title'] = isset($raw_snapshot['title']) ? sanitize_text_field((string)$raw_snapshot['title']) : '';

        $controls = isset($raw_snapshot['controls']) && is_array($raw_snapshot['controls']) ? $raw_snapshot['controls'] : array();
        $length = isset($controls['length']) ? sanitize_key((string)$controls['length']) : 'medium';
        if (!in_array($length, array('short', 'medium', 'long'), true)) {
            $length = 'medium';
        }
        $internal_images = isset($controls['internal_images']) ? absint($controls['internal_images']) : 2;
        if ($internal_images > 3) $internal_images = 3;
        if (!cbia_cap_enabled('internal_images')) {
            $internal_images = 0;
        }
        $language = function_exists('cbia_ai_composer_normalize_language_value')
            ? cbia_ai_composer_normalize_language_value(isset($controls['language']) ? (string)$controls['language'] : 'Spanish')
            : cbia_ai_composer_normalize_language_code(isset($controls['language']) ? (string)$controls['language'] : 'es');
        $internal_style = isset($controls['internal_style']) ? sanitize_key((string)$controls['internal_style']) : 'banner';
        if (!in_array($internal_style, array('banner', 'normal'), true)) {
            $internal_style = 'banner';
        }
        $prompt_profile = isset($controls['prompt_profile']) ? sanitize_key((string)$controls['prompt_profile']) : 'discover_editorial';
        $prompt_profile_options = function_exists('cbia_prompt_get_profile_options') ? cbia_prompt_get_profile_options() : array();
        if (!array_key_exists($prompt_profile, $prompt_profile_options)) {
            $prompt_profile = 'discover_editorial';
        }
        $include_faq = isset($controls['include_faq']) ? absint($controls['include_faq']) : 1;
        $include_examples = isset($controls['include_practical_examples']) ? absint($controls['include_practical_examples']) : 0;
        $out['controls'] = array(
            'length' => $length,
            'internal_images' => $internal_images,
            'language' => $language,
            'internal_style' => $internal_style,
            'prompt_profile' => $prompt_profile,
            'include_faq' => $include_faq ? 1 : 0,
            'include_practical_examples' => $include_examples ? 1 : 0,
        );

        $out['final_html'] = isset($raw_snapshot['final_html']) ? wp_kses_post((string)$raw_snapshot['final_html']) : '';
        $out['preview_html'] = isset($raw_snapshot['preview_html']) ? wp_kses_post((string)$raw_snapshot['preview_html']) : '';

        $featured = isset($raw_snapshot['featured']) && is_array($raw_snapshot['featured']) ? $raw_snapshot['featured'] : array();
        $featured_url = isset($featured['url']) ? esc_url_raw((string)$featured['url']) : '';
        $out['featured'] = array(
            'url' => $featured_url,
            'attach_id' => isset($featured['attach_id']) ? absint($featured['attach_id']) : 0,
            'desc' => isset($featured['desc']) ? sanitize_text_field((string)$featured['desc']) : '',
        );
        $out['featured_attach_id'] = isset($raw_snapshot['featured_attach_id']) ? absint($raw_snapshot['featured_attach_id']) : $out['featured']['attach_id'];

        $internal_images_out = array();
        if (isset($raw_snapshot['internal_images']) && is_array($raw_snapshot['internal_images'])) {
            foreach ($raw_snapshot['internal_images'] as $row) {
                if (!is_array($row)) continue;
                $url = isset($row['url']) ? esc_url_raw((string)$row['url']) : '';
                if ($url === '') continue;
                $idx = isset($row['idx']) ? absint($row['idx']) : 0;
                $internal_images_out[] = array(
                    'idx' => $idx,
                    'url' => $url,
                    'attach_id' => isset($row['attach_id']) ? absint($row['attach_id']) : 0,
                    'desc' => isset($row['desc']) ? sanitize_text_field((string)$row['desc']) : '',
                    'prompt' => isset($row['prompt']) ? sanitize_textarea_field((string)$row['prompt']) : '',
                    'section' => isset($row['section']) ? sanitize_key((string)$row['section']) : '',
                );
            }
        }
        $out['internal_images'] = cbia_cap_enabled('internal_images') ? $internal_images_out : array();

        $out['focus_keyphrase'] = isset($raw_snapshot['focus_keyphrase']) ? sanitize_text_field((string)$raw_snapshot['focus_keyphrase']) : '';
        $meta_description = isset($raw_snapshot['meta_description']) ? sanitize_text_field((string)$raw_snapshot['meta_description']) : '';
        $out['meta_description'] = function_exists('cbia_trim_metadesc') ? cbia_trim_metadesc($meta_description, 139) : $meta_description;

        $category_ids = array();
        if (isset($raw_snapshot['category_ids']) && is_array($raw_snapshot['category_ids'])) {
            foreach ($raw_snapshot['category_ids'] as $cid) {
                $id = absint($cid);
                if ($id > 0) $category_ids[] = $id;
            }
        }
        $out['category_ids'] = array_values(array_unique($category_ids));

        $tag_ids = array();
        if (isset($raw_snapshot['tag_ids']) && is_array($raw_snapshot['tag_ids'])) {
            foreach ($raw_snapshot['tag_ids'] as $tid) {
                $id = absint($tid);
                if ($id > 0) $tag_ids[] = $id;
            }
        }
        $out['tag_ids'] = array_values(array_unique($tag_ids));

        $tag_names = array();
        if (isset($raw_snapshot['tag_names']) && is_array($raw_snapshot['tag_names'])) {
            foreach ($raw_snapshot['tag_names'] as $name_raw) {
                $name = sanitize_text_field((string)$name_raw);
                if ($name !== '') $tag_names[] = $name;
            }
        }
        $out['tag_names'] = array_values(array_unique($tag_names));

        $out['preview_token'] = isset($raw_snapshot['preview_token']) ? sanitize_text_field((string)$raw_snapshot['preview_token']) : '';
        $out['word_count'] = isset($raw_snapshot['word_count']) ? absint($raw_snapshot['word_count']) : 0;
        $out['updated_at'] = isset($raw_snapshot['updated_at']) ? absint($raw_snapshot['updated_at']) : time();

        return $out;
    }
}

if (!function_exists('cbia_ajax_ai_composer_save_snapshot')) {
    function cbia_ajax_ai_composer_save_snapshot() {
        check_ajax_referer('cbia_ajax_nonce');

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ($post_id > 0) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
            }
        } elseif (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Snapshot is sanitized by cbia_ai_composer_sanitize_snapshot() below.
        $snapshot_raw = isset($_POST['snapshot']) ? wp_unslash($_POST['snapshot']) : '';
        if (!is_string($snapshot_raw) || trim($snapshot_raw) === '') {
            wp_send_json_error(array('message' => __('Empty snapshot.', 'cbiastudio-blogflow-ai')), 400);
        }

        $snapshot = cbia_ai_composer_sanitize_snapshot($snapshot_raw);
        if (empty($snapshot)) {
            wp_send_json_error(array('message' => __('Invalid snapshot.', 'cbiastudio-blogflow-ai')), 400);
        }
        $snapshot['post_id'] = $post_id > 0 ? $post_id : 0;

        if ($post_id > 0) {
            update_post_meta($post_id, '_cbia_ai_composer_snapshot', $snapshot);
        }

        wp_send_json_success(array(
            'saved' => 1,
            'post_id' => $post_id,
        ));
    }
}

if (!function_exists('cbia_ajax_ai_composer_regenerate_image_slot')) {
    function cbia_ajax_ai_composer_regenerate_image_slot() {
        check_ajax_referer('cbia_ajax_nonce');

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ($post_id > 0) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
            }
        } elseif (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }

        $slot_type = isset($_POST['slot_type']) ? sanitize_key((string)wp_unslash($_POST['slot_type'])) : '';
        if (!in_array($slot_type, array('featured', 'internal'), true)) {
            wp_send_json_error(array('message' => __('Invalid slot.', 'cbiastudio-blogflow-ai')), 400);
        }
        if ($slot_type === 'internal' && !cbia_cap_enabled('internal_images')) {
            wp_send_json_error(array('message' => __('Internal images are available in Pro only.', 'cbiastudio-blogflow-ai')), 403);
        }

        $slot_idx = isset($_POST['slot_idx']) ? absint(wp_unslash($_POST['slot_idx'])) : 0;
        if ($slot_type === 'internal') {
            if ($slot_idx < 1) $slot_idx = 1;
            if ($slot_idx > 3) $slot_idx = 3;
        } else {
            $slot_idx = 0;
        }

        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field((string)wp_unslash($_POST['prompt'])) : '';
        $prompt = trim($prompt);
        if ($prompt === '') {
            wp_send_json_error(array('message' => 'Empty prompt.'), 400);
        }

        $title = isset($_POST['title']) ? sanitize_text_field((string)wp_unslash($_POST['title'])) : '';
        if ($title === '' && $post_id > 0) {
            $title = sanitize_text_field((string)get_the_title($post_id));
        }
        if ($title === '') {
            $title = 'Generated image';
        }

        $section = isset($_POST['section']) ? sanitize_key((string)wp_unslash($_POST['section'])) : '';
        if ($slot_type === 'featured') {
            $section = 'intro';
        }
        if ($section === '') {
            $section = 'body';
        }
        if (!in_array($section, array('intro', 'body', 'faq', 'conclusion', 'featured'), true)) {
            $section = ($slot_type === 'featured') ? 'intro' : 'body';
        }

        $internal_image_style = isset($_POST['internal_image_style']) ? sanitize_key((string)wp_unslash($_POST['internal_image_style'])) : 'banner';
        if (!in_array($internal_image_style, array('banner', 'normal'), true)) {
            $internal_image_style = 'banner';
        }

        if (!function_exists('cbia_generate_image_openai_with_prompt')) {
            wp_send_json_error(array('message' => 'Image engine unavailable.'), 500);
        }

        $alt = ($slot_type === 'featured')
            ? $title
            : sprintf('Internal image %d: %s', (int)$slot_idx, $title);

        list($ok, $attach_id, $model_used, $err, $meta) = cbia_generate_image_openai_with_prompt(
            $prompt,
            $section,
            $title,
            $alt,
            $slot_idx
        );
        $attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($meta) : array();

        if (!$ok || (int)$attach_id <= 0) {
            $recorded_attempts = 0;
            if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
                $recorded_attempts = cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'image', 'section' => $section, 'context' => 'composer_image'));
            }
            if (!$recorded_attempts && function_exists('cbia_costes_record_usage')) {
                cbia_costes_record_usage($post_id, array_merge(is_array($meta) ? $meta : array(), array(
                    'type' => 'image', 'model' => (string)$model_used, 'ok' => 0, 'error' => (string)$err,
                    'section' => (string)$section, 'idx' => (int)$slot_idx, 'context' => 'composer_image',
                )));
            }
            wp_send_json_error(array(
                'message' => $err !== '' ? $err : 'Could not regenerate image.',
            ), 500);
        }

        $attach_id = (int)$attach_id;
        if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
            cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'image', 'section' => $section, 'context' => 'composer_image'));
        }
        if (function_exists('cbia_costes_record_usage')) {
            cbia_costes_record_usage($post_id, array_merge(is_array($meta) ? $meta : array(), array(
                'type' => 'image', 'model' => (string)$model_used, 'ok' => 1, 'error' => '',
                'section' => (string)$section, 'idx' => (int)$slot_idx, 'attach_id' => $attach_id, 'context' => 'composer_image',
            )));
        }
        $url = wp_get_attachment_url($attach_id);
        if (!$url) {
            wp_send_json_error(array('message' => 'Generated image has no valid URL.'), 500);
        }

        if ($post_id > 0) {
            update_post_meta($attach_id, '_cbia_keep_preview_media', '1');
            wp_update_post(array(
                'ID' => $attach_id,
                'post_parent' => $post_id,
            ));
        }

        $class_attr = ($slot_type === 'internal' && $internal_image_style === 'banner')
            ? ' class="cbia-banner lazyloaded"'
            : '';
        $slot_attr = ($slot_type === 'internal' && $slot_idx > 0)
            ? ' data-cbia-slot="' . (int)$slot_idx . '"'
            : '';
        $html = '<div class="cbia-inline-image-wrap"' . $slot_attr . ' style="margin:30px 0;">'
            . '<img decoding="async" loading="lazy"' . $class_attr . ' src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" style="display:block;width:100%;height:auto;margin:20px 0;" />'
            . '</div>';

        if (function_exists('cbia_log')) {
            cbia_log(sprintf(
                'Composer image slot regenerated: post=%d slot=%s idx=%d attach_id=%d model=%s',
                (int)$post_id,
                (string)$slot_type,
                (int)$slot_idx,
                (int)$attach_id,
                (string)$model_used
            ), 'INFO');
        }

        wp_send_json_success(array(
            'slot_type' => $slot_type,
            'idx' => (int)$slot_idx,
            'section' => (string)$section,
            'url' => esc_url_raw($url),
            'attach_id' => (int)$attach_id,
            'desc' => (string)$alt,
            'prompt' => (string)$prompt,
            'model' => (string)$model_used,
            'html' => (string)$html,
        ));
    }
}

if (!function_exists('cbia_trim_metadesc')) {
    function cbia_trim_metadesc($value, $max = 139) {
        $text = trim((string)$value);
        $max = (int)$max;
        if ($text === '' || $max < 10) return $text;
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len <= $max) return $text;
        $baseMax = max(1, $max - 3);
        $base = function_exists('mb_substr') ? mb_substr($text, 0, $baseMax) : substr($text, 0, $baseMax);
        $base = trim((string)$base);
        $soft = preg_replace('/\s+\S*$/u', '', $base);
        $soft = trim((string)$soft);
        $chosen = (function_exists('mb_strlen') ? mb_strlen($soft) : strlen($soft)) >= 40 ? $soft : $base;
        $chosen = rtrim((string)$chosen, ". \t\n\r\0\x0B");
        return $chosen . '...';
    }
}

if (!function_exists('cbia_ajax_ai_composer_apply_yoast_meta')) {
    function cbia_ajax_ai_composer_apply_yoast_meta() {
        check_ajax_referer('cbia_ajax_nonce');
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }
        $featured_attach_id = isset($_POST['featured_attach_id']) ? absint(wp_unslash($_POST['featured_attach_id'])) : 0;

        $focus = isset($_POST['focus_keyphrase']) ? sanitize_text_field((string)wp_unslash($_POST['focus_keyphrase'])) : '';
        $metad = isset($_POST['meta_description']) ? sanitize_text_field((string)wp_unslash($_POST['meta_description'])) : '';
        $post_title_hint = isset($_POST['post_title_hint']) ? sanitize_text_field((string)wp_unslash($_POST['post_title_hint'])) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wp_kses_post() in the next line.
        $content_html_raw = isset($_POST['content_html']) ? (string)wp_unslash($_POST['content_html']) : '';
        $content_html = wp_kses_post($content_html_raw);
        if ($metad !== '') {
            $metad = cbia_trim_metadesc($metad, 139);
        }
        $seo_title = isset($_POST['seo_title']) ? sanitize_text_field((string)wp_unslash($_POST['seo_title'])) : '';
        $og_title = isset($_POST['og_title']) ? sanitize_text_field((string)wp_unslash($_POST['og_title'])) : '';
        $og_desc = isset($_POST['og_description']) ? sanitize_text_field((string)wp_unslash($_POST['og_description'])) : '';
        $tw_title = isset($_POST['tw_title']) ? sanitize_text_field((string)wp_unslash($_POST['tw_title'])) : '';
        $tw_desc = isset($_POST['tw_description']) ? sanitize_text_field((string)wp_unslash($_POST['tw_description'])) : '';
        $primary_category = isset($_POST['primary_category']) ? absint(wp_unslash($_POST['primary_category'])) : 0;

        // Server-side fallback to avoid empty Yoast metadesc when editor payload misses it.
        if ($metad === '') {
            $post_obj = get_post($post_id);
            $title_for_meta = '';
            if ($post_obj instanceof WP_Post) {
                $title_for_meta = (string)$post_obj->post_title;
            }
            if ($title_for_meta === '' && $post_title_hint !== '') {
                $title_for_meta = $post_title_hint;
            }
            $content_for_meta = '';
            if ($content_html !== '') {
                $content_for_meta = $content_html;
            } elseif ($post_obj instanceof WP_Post) {
                $content_for_meta = (string)$post_obj->post_content;
            }
            if ($post_obj instanceof WP_Post) {
                if (function_exists('cbia_generate_meta_description')) {
                    $metad = (string)cbia_generate_meta_description(
                        (string)$title_for_meta,
                        (string)$content_for_meta
                    );
                }
                if ($metad === '' && $content_for_meta !== '') {
                    $metad = wp_trim_words(wp_strip_all_tags((string)$content_for_meta), 28, '...');
                }
                if ($metad === '' && $title_for_meta !== '') {
                    $metad = wp_trim_words($title_for_meta, 20, '...');
                }
                if ($metad !== '') {
                    $metad = cbia_trim_metadesc($metad, 139);
                }
            }
        }
        if ($focus === '') {
            $post_obj = isset($post_obj) && ($post_obj instanceof WP_Post) ? $post_obj : get_post($post_id);
            if ($post_obj instanceof WP_Post) {
                $focus = sanitize_text_field((string)$post_obj->post_title);
            } elseif ($post_title_hint !== '') {
                $focus = sanitize_text_field($post_title_hint);
            }
        }
        if ($seo_title === '') {
            $post_obj = isset($post_obj) && ($post_obj instanceof WP_Post) ? $post_obj : get_post($post_id);
            if ($post_obj instanceof WP_Post) {
                $seo_title = sanitize_text_field((string)$post_obj->post_title);
            } elseif ($post_title_hint !== '') {
                $seo_title = sanitize_text_field($post_title_hint);
            }
        }
        if ($og_title === '') $og_title = $seo_title;
        if ($tw_title === '') $tw_title = $seo_title;
        if ($og_desc === '') $og_desc = $metad;
        if ($tw_desc === '') $tw_desc = $metad;

        $written = array();
        $meta_report = array();
        $meta_ok = true;
        $short = function ($value, $max = 60) {
            $s = trim((string)$value);
            if ($s === '') return '';
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                if (mb_strlen($s) > $max) return (string)mb_substr($s, 0, $max) . '...';
                return $s;
            }
            if (strlen($s) > $max) return substr($s, 0, $max) . '...';
            return $s;
        };
        $verify_meta = function ($meta_key, $expected) use ($post_id, &$meta_report, &$meta_ok, $short) {
            $stored = (string)get_post_meta($post_id, $meta_key, true);
            $ok = ((string)$stored === (string)$expected);
            $meta_report[$meta_key] = array(
                'ok' => $ok ? 1 : 0,
                'len' => function_exists('mb_strlen') ? (int)mb_strlen((string)$stored) : (int)strlen((string)$stored),
                'sample' => $short($stored, 48),
            );
            if (!$ok) $meta_ok = false;
        };
        if ($focus !== '') {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus);
            update_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', $focus);
            $written[] = 'focus';
            $verify_meta('_yoast_wpseo_focuskw', $focus);
            $verify_meta('_yoast_wpseo_focuskw_text_input', $focus);
        }
        if ($metad !== '') {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $metad);
            $written[] = 'metadesc';
            $verify_meta('_yoast_wpseo_metadesc', $metad);
        }
        if ($seo_title !== '') {
            update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
            $written[] = 'title';
            $verify_meta('_yoast_wpseo_title', $seo_title);
        }
        if ($og_title !== '') {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $og_title);
            $verify_meta('_yoast_wpseo_opengraph-title', $og_title);
        }
        if ($og_desc !== '') {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $og_desc);
            $verify_meta('_yoast_wpseo_opengraph-description', $og_desc);
        }
        if ($tw_title !== '') {
            update_post_meta($post_id, '_yoast_wpseo_twitter-title', $tw_title);
            $verify_meta('_yoast_wpseo_twitter-title', $tw_title);
        }
        if ($tw_desc !== '') {
            update_post_meta($post_id, '_yoast_wpseo_twitter-description', $tw_desc);
            $verify_meta('_yoast_wpseo_twitter-description', $tw_desc);
        }
        if ($primary_category > 0) {
            update_post_meta($post_id, '_yoast_wpseo_primary_category', $primary_category);
            $written[] = 'primary_cat';
            $verify_meta('_yoast_wpseo_primary_category', (string)$primary_category);
        }
        if (function_exists('cbia_yoast_try_reindex_post')) {
            cbia_yoast_try_reindex_post((int)$post_id);
        } else {
            do_action('wpseo_save_postdata', (int)$post_id);
            do_action('wpseo_save_post', (int)$post_id);
        }

        if (function_exists('cbia_log')) {
            // Avoid log spam when the frontend retries the same payload.
            $log_fingerprint = md5(
                (string)$post_id . '|' .
                (string)$focus . '|' .
                (string)$metad . '|' .
                (string)$seo_title . '|' .
                (string)$primary_category
            );
            $log_key = 'cbia_yoast_sync_log_' . (int)$post_id . '_' . $log_fingerprint;
            if (!get_transient($log_key)) {
                set_transient($log_key, 1, 30);
                cbia_log(sprintf(
                    'Composer Yoast sync: post=%d status=%s keys=%s metadesc_chars=%d',
                    (int)$post_id,
                    $meta_ok ? 'ok' : 'partial',
                    !empty($written) ? implode(',', $written) : 'none',
                    function_exists('mb_strlen') ? (int)mb_strlen((string)$metad) : (int)strlen((string)$metad)
                ), 'INFO');
                $focus_len = isset($meta_report['_yoast_wpseo_focuskw']['len']) ? (int)$meta_report['_yoast_wpseo_focuskw']['len'] : 0;
                $metad_len = isset($meta_report['_yoast_wpseo_metadesc']['len']) ? (int)$meta_report['_yoast_wpseo_metadesc']['len'] : 0;
                $title_len = isset($meta_report['_yoast_wpseo_title']['len']) ? (int)$meta_report['_yoast_wpseo_title']['len'] : 0;
                $primary_cat = isset($meta_report['_yoast_wpseo_primary_category']['sample']) ? (string)$meta_report['_yoast_wpseo_primary_category']['sample'] : '-';
                $focus_ok = !empty($meta_report['_yoast_wpseo_focuskw']['ok']) ? 'ok' : 'fail';
                $metad_ok = !empty($meta_report['_yoast_wpseo_metadesc']['ok']) ? 'ok' : 'fail';
                $title_ok = !empty($meta_report['_yoast_wpseo_title']['ok']) ? 'ok' : 'fail';
                cbia_log(sprintf(
                    'Composer Yoast details: focus=%s(%dc) | metadesc=%s(%d/139) | title=%s(%dc) | primary_cat=%s',
                    (string)$focus_ok,
                    (int)$focus_len,
                    (string)$metad_ok,
                    (int)$metad_len,
                    (string)$title_ok,
                    (int)$title_len,
                    (string)$primary_cat
                ), $meta_ok ? 'INFO' : 'WARN');
            }
        }

        wp_send_json_success(array(
            'post_id' => $post_id,
            'status' => $meta_ok ? 'ok' : 'partial',
            'meta_report' => $meta_report,
        ));
    }
}

if (!function_exists('cbia_ajax_ai_composer_apply_terms')) {
    function cbia_ajax_ai_composer_apply_terms() {
        check_ajax_referer('cbia_ajax_nonce');
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }

        $cat_ids = array();
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every element is sanitized with absint() below.
            $raw = wp_unslash($_POST['category_ids']);
            foreach ($raw as $v) {
                $id = absint($v);
                if ($id > 0) $cat_ids[] = $id;
            }
        }
        $cat_ids = array_values(array_unique($cat_ids));

        $tag_ids = array();
        if (isset($_POST['tag_ids']) && is_array($_POST['tag_ids'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every element is sanitized with absint() below.
            $raw = wp_unslash($_POST['tag_ids']);
            foreach ($raw as $v) {
                $id = absint($v);
                if ($id > 0) $tag_ids[] = $id;
            }
        }
        $tag_ids = array_values(array_unique($tag_ids));

        $tag_names = array();
        if (isset($_POST['tag_names']) && is_array($_POST['tag_names'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every element is sanitized with sanitize_text_field() below.
            $raw = wp_unslash($_POST['tag_names']);
            foreach ($raw as $name_raw) {
                $name = sanitize_text_field((string)$name_raw);
                if ($name !== '') $tag_names[] = $name;
            }
        }
        $tag_names = array_values(array_unique($tag_names));
        $featured_attach_id = isset($_POST['featured_attach_id']) ? absint(wp_unslash($_POST['featured_attach_id'])) : 0;

        if (!empty($cat_ids)) {
            wp_set_post_categories($post_id, $cat_ids, false);
            update_post_meta($post_id, '_yoast_wpseo_primary_category', (int)$cat_ids[0]);
        }
        if (!empty($tag_ids)) {
            wp_set_post_terms($post_id, $tag_ids, 'post_tag', false);
        } elseif (!empty($tag_names)) {
            wp_set_post_terms($post_id, $tag_names, 'post_tag', false);
        }
        if ($featured_attach_id > 0) {
            set_post_thumbnail($post_id, $featured_attach_id);
            update_post_meta($featured_attach_id, '_cbia_keep_preview_media', '1');
            wp_update_post(array(
                'ID' => (int)$featured_attach_id,
                'post_parent' => (int)$post_id,
            ));
        }
        clean_object_term_cache((int)$post_id, 'post');
        if (function_exists('cbia_yoast_try_reindex_post')) {
            cbia_yoast_try_reindex_post((int)$post_id);
        }

        if (function_exists('cbia_log')) {
            $cats_txt = empty($cat_ids) ? '-' : implode(',', array_map('intval', $cat_ids));
            $tags_txt = empty($tag_ids) ? '-' : implode(',', array_map('intval', $tag_ids));
            $log_fingerprint = md5((string)$post_id . '|' . (string)$cats_txt . '|' . (string)$tags_txt . '|' . (string)$featured_attach_id);
            $log_key = 'cbia_terms_sync_log_' . (int)$post_id . '_' . $log_fingerprint;
            if (!get_transient($log_key)) {
                set_transient($log_key, 1, 30);
                cbia_log(sprintf(
                    'Composer terms/media sync: post=%d cats=%d(%s) tags=%d(%s) featured=%d',
                    (int)$post_id,
                    count($cat_ids),
                    (string)$cats_txt,
                    count($tag_ids),
                    (string)$tags_txt,
                    (int)$featured_attach_id
                ), 'INFO');
            }
        }

        wp_send_json_success(array(
            'post_id' => (int)$post_id,
            'categories' => array_values(array_map('intval', wp_get_post_categories($post_id, array('fields' => 'ids')))),
            'tags' => array_values(array_map('intval', wp_get_post_terms($post_id, 'post_tag', array('fields' => 'ids')))),
            'tag_names' => array_values(array_map('sanitize_text_field', wp_get_post_terms($post_id, 'post_tag', array('fields' => 'names')))),
            'featured_attach_id' => (int)get_post_thumbnail_id($post_id),
        ));
    }
}

if (!function_exists('cbia_ajax_ai_composer_apply_full_post')) {
    function cbia_ajax_ai_composer_apply_full_post() {
        check_ajax_referer('cbia_ajax_nonce');

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field((string)wp_unslash($_POST['title'])) : '';
        $html = isset($_POST['content_html']) ? wp_kses_post((string)wp_unslash($_POST['content_html'])) : '';
        $featured_attach_id = isset($_POST['featured_attach_id']) ? absint(wp_unslash($_POST['featured_attach_id'])) : 0;
        $focus = isset($_POST['focus_keyphrase']) ? sanitize_text_field((string)wp_unslash($_POST['focus_keyphrase'])) : '';
        $metad = isset($_POST['meta_description']) ? sanitize_text_field((string)wp_unslash($_POST['meta_description'])) : '';
        $post_language = isset($_POST['post_language']) ? sanitize_text_field((string)wp_unslash($_POST['post_language'])) : '';
        $include_faq = isset($_POST['include_faq']) ? absint(wp_unslash($_POST['include_faq'])) : 1;
        $internal_image_style = isset($_POST['internal_image_style']) ? sanitize_key((string)wp_unslash($_POST['internal_image_style'])) : '';
        if (!in_array($internal_image_style, array('banner', 'normal'), true)) {
            $internal_image_style = '';
        }
        $internal_images_enabled = cbia_cap_enabled('internal_images');

        if ($title === '' || $html === '') {
            wp_send_json_error(array('message' => __('Title or content is missing.', 'cbiastudio-blogflow-ai')), 400);
        }

        if ($post_language !== '' && function_exists('cbia_normalize_faq_heading_for_language')) {
            $html = cbia_normalize_faq_heading_for_language($html, $post_language);
        } elseif (function_exists('cbia_normalize_faq_heading')) {
            $html = cbia_normalize_faq_heading($html);
        }
        if (!$include_faq && function_exists('cbia_strip_faq_section')) {
            $html = cbia_strip_faq_section($html);
        }

        if ($internal_image_style !== '' && $internal_images_enabled) {
            $html = preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($internal_image_style) {
                $img = (string)$m[0];
                $has_class = preg_match('/\sclass=("|\')([^"\']*)\1/i', $img, $cm);
                $classes = array();
                if ($has_class) {
                    $classes = preg_split('/\s+/', trim((string)$cm[2]));
                    if (!is_array($classes)) $classes = array();
                }
                $classes = array_values(array_filter(array_map('sanitize_html_class', $classes)));
                $classes = array_values(array_filter($classes));
                $classes = array_values(array_diff($classes, array('cbia-banner', 'lazyloaded')));
                if ($internal_image_style === 'banner') {
                    $classes[] = 'cbia-banner';
                    $classes[] = 'lazyloaded';
                }
                $classes = array_values(array_unique(array_filter($classes)));
                $class_attr = empty($classes) ? '' : (' class="' . esc_attr(implode(' ', $classes)) . '"');
                if ($has_class) {
                    $img = preg_replace('/\sclass=("|\')[^"\']*\1/i', $class_attr, $img, 1);
                } elseif ($class_attr !== '') {
                    $img = preg_replace('/<img\b/i', '<img' . $class_attr, $img, 1);
                }
                return $img;
            }, $html);
        }
        if (!$internal_images_enabled) {
            $html = preg_replace('/<img\b[^>]*>/i', '', (string)$html);
        }

        if ($post_id > 0) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
            }
            $update_postarr = array(
                'ID' => $post_id,
                'post_title' => $title,
                'post_content' => $html,
            );
            $existing_post = get_post((int)$post_id);
            if ($existing_post instanceof WP_Post && $existing_post->post_status === 'auto-draft') {
                $update_postarr['post_status'] = 'draft';
            }
            $result = wp_update_post($update_postarr, true);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            }
        } else {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
            }
            $created = wp_insert_post(array(
                'post_type' => 'post',
                'post_status' => 'draft',
                'post_title' => $title,
                'post_content' => $html,
                'post_author' => get_current_user_id(),
            ), true);
            if (is_wp_error($created) || !$created) {
                $err = is_wp_error($created) ? $created->get_error_message() : __('Could not create draft.', 'cbiastudio-blogflow-ai');
                wp_send_json_error(array('message' => $err), 500);
            }
            $post_id = (int)$created;
        }

        // Hard guarantee for title persistence (some editor integrations can overwrite late).
        $stored_title = (string)get_the_title((int)$post_id);
        if ($stored_title !== $title) {
            wp_update_post(array(
                'ID' => (int)$post_id,
                'post_title' => $title,
            ));
            $stored_title = (string)get_the_title((int)$post_id);
            if ($stored_title !== $title) {
                global $wpdb;
                if ($wpdb) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct update to preserve exact post title when wp_update_post normalizes entities.
                    $wpdb->update(
                        $wpdb->posts,
                        array('post_title' => $title),
                        array('ID' => (int)$post_id),
                        array('%s'),
                        array('%d')
                    );
                    clean_post_cache((int)$post_id);
                }
            }
        }

        if ($featured_attach_id <= 0 && preg_match('/<img[^>]+src=("|\')([^"\']+)\\1/i', (string)$html, $featured_match)) {
            $fallback_src = esc_url_raw((string)($featured_match[2] ?? ''));
            if ($fallback_src !== '') {
                $fallback_src = preg_replace('/\\?.*$/', '', (string)$fallback_src);
                $featured_attach_id = (int)attachment_url_to_postid($fallback_src);
            }
        }

        if ($featured_attach_id > 0) {
            set_post_thumbnail($post_id, $featured_attach_id);
            update_post_meta($featured_attach_id, '_cbia_keep_preview_media', '1');
            wp_update_post(array(
                'ID' => (int)$featured_attach_id,
                'post_parent' => (int)$post_id,
            ));
        }

        // Persist internal preview images as real media attachments attached to this post.
        if ($internal_images_enabled && preg_match_all('/<img[^>]+src=("|\')([^"\']+)\\1/i', (string)$html, $img_matches) && !empty($img_matches[2])) {
            foreach ((array)$img_matches[2] as $src_url) {
                $src = esc_url_raw((string)$src_url);
                if ($src !== '') {
                    $src = preg_replace('/\\?.*$/', '', (string)$src);
                }
                if ($src === '') continue;
                $img_attach_id = (int)attachment_url_to_postid($src);
                if ($img_attach_id <= 0) continue;
                update_post_meta($img_attach_id, '_cbia_keep_preview_media', '1');
                wp_update_post(array(
                    'ID' => (int)$img_attach_id,
                    'post_parent' => (int)$post_id,
                ));
            }
        }

        $cat_ids = array();
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every element is sanitized with absint() below.
            foreach (wp_unslash($_POST['category_ids']) as $v) {
                $id = absint($v);
                if ($id > 0) $cat_ids[] = $id;
            }
            $cat_ids = array_values(array_unique($cat_ids));
            if (!empty($cat_ids)) {
                wp_set_post_categories($post_id, $cat_ids, false);
            }
        }

        $tag_ids = array();
        if (isset($_POST['tag_ids']) && is_array($_POST['tag_ids'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every element is sanitized with absint() below.
            foreach (wp_unslash($_POST['tag_ids']) as $v) {
                $id = absint($v);
                if ($id > 0) $tag_ids[] = $id;
            }
            $tag_ids = array_values(array_unique($tag_ids));
        }
        $tag_names = array();
        if (isset($_POST['tag_names']) && is_array($_POST['tag_names'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every element is sanitized with sanitize_text_field() below.
            foreach (wp_unslash($_POST['tag_names']) as $name_raw) {
                $name = sanitize_text_field((string)$name_raw);
                if ($name !== '') $tag_names[] = $name;
            }
            $tag_names = array_values(array_unique($tag_names));
        }

        if (!empty($tag_ids)) {
            wp_set_post_terms($post_id, $tag_ids, 'post_tag', false);
        } elseif (!empty($tag_names)) {
            wp_set_post_terms($post_id, $tag_names, 'post_tag', false);
        } elseif (function_exists('cbia_pick_tags_for_post')) {
            $tag_names = (array)cbia_pick_tags_for_post($title, $html, 7);
            $tag_names = array_values(array_unique(array_filter(array_map('sanitize_text_field', $tag_names))));
            if (!empty($tag_names)) {
                wp_set_post_terms($post_id, $tag_names, 'post_tag', false);
            }
        } elseif (function_exists('cbia_pick_tags_from_content_allowed')) {
            $tag_names = (array)cbia_pick_tags_from_content_allowed($title, $html, 7);
            $tag_names = array_values(array_unique(array_filter(array_map('sanitize_text_field', $tag_names))));
            if (!empty($tag_names)) {
                wp_set_post_terms($post_id, $tag_names, 'post_tag', false);
            }
        }

        if (empty($cat_ids) && function_exists('cbia_determine_categories_by_mapping')) {
            $cat_names = cbia_determine_categories_by_mapping($title, $html);
            if (empty($cat_names) && function_exists('cbia_get_settings')) {
                $s = cbia_get_settings();
                $default_cat = trim((string)($s['default_category'] ?? ''));
                if ($default_cat !== '') $cat_names = array($default_cat);
            }
            if (!empty($cat_names) && function_exists('cbia_ensure_category_exists')) {
                foreach ((array)$cat_names as $cat_name) {
                    $cid = cbia_ensure_category_exists((string)$cat_name);
                    if ($cid) $cat_ids[] = (int)$cid;
                }
                $cat_ids = array_values(array_unique(array_filter($cat_ids)));
                if (!empty($cat_ids)) {
                    wp_set_post_categories($post_id, $cat_ids, false);
                }
            }
        }
        if (empty($cat_ids)) {
            $fallback_cat = (int)get_option('default_category', 0);
            if ($fallback_cat > 0) {
                $cat_ids = array($fallback_cat);
                wp_set_post_categories($post_id, $cat_ids, false);
            }
        }

        $stored_tag_ids = wp_get_post_terms($post_id, 'post_tag', array('fields' => 'ids'));
        if (!is_array($stored_tag_ids) || empty($stored_tag_ids)) {
            $fallback_tag_names = function_exists('cbia_pick_tags_for_post') ? (array)cbia_pick_tags_for_post($title, $html, 7) : array();
            $fallback_tag_names = array_values(array_unique(array_filter(array_map('sanitize_text_field', $fallback_tag_names))));
            if (!empty($fallback_tag_names)) {
                wp_set_post_terms($post_id, $fallback_tag_names, 'post_tag', false);
            }
        }

        $stored_cat_ids = wp_get_post_categories($post_id, array('fields' => 'ids'));
        if (is_array($stored_cat_ids) && !empty($stored_cat_ids)) {
            $cat_ids = array_values(array_map('intval', $stored_cat_ids));
        }

        if ($focus === '') {
            $focus = $title;
        }
        if ($metad === '') {
            if (function_exists('cbia_generate_meta_description')) {
                $metad = (string)cbia_generate_meta_description($title, $html);
            }
            if ($metad === '') {
                $metad = wp_trim_words(wp_strip_all_tags($html), 28, '...');
            }
        }
        $metad = cbia_trim_metadesc($metad, 139);

        update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus);
        update_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', $focus);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $metad);
        update_post_meta($post_id, '_yoast_wpseo_title', $title);
        update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $title);
        update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $metad);
        update_post_meta($post_id, '_yoast_wpseo_twitter-title', $title);
        update_post_meta($post_id, '_yoast_wpseo_twitter-description', $metad);
        if (!empty($cat_ids)) {
            update_post_meta($post_id, '_yoast_wpseo_primary_category', (int)$cat_ids[0]);
        }
        clean_object_term_cache((int)$post_id, 'post');
        if (function_exists('cbia_yoast_try_reindex_post')) {
            cbia_yoast_try_reindex_post((int)$post_id);
        } else {
            do_action('wpseo_save_postdata', (int)$post_id);
            do_action('wpseo_save_post', (int)$post_id);
        }

        if (function_exists('cbia_log')) {
            $focus_len = function_exists('mb_strlen') ? (int)mb_strlen((string)$focus) : (int)strlen((string)$focus);
            $title_len = function_exists('mb_strlen') ? (int)mb_strlen((string)$title) : (int)strlen((string)$title);
            $metadesc_len = function_exists('mb_strlen') ? (int)mb_strlen((string)$metad) : (int)strlen((string)$metad);
            $primary_cat = !empty($cat_ids) ? (int)$cat_ids[0] : 0;
            $final_tag_ids = wp_get_post_terms($post_id, 'post_tag', array('fields' => 'ids'));
            if (!is_array($final_tag_ids)) $final_tag_ids = array();
            $final_tag_ids = array_values(array_map('intval', $final_tag_ids));
            $final_cat_ids = wp_get_post_categories($post_id, array('fields' => 'ids'));
            if (!is_array($final_cat_ids)) $final_cat_ids = array();
            $final_cat_ids = array_values(array_map('intval', $final_cat_ids));
            $featured_final = (int)get_post_thumbnail_id($post_id);
            $cats_txt = empty($final_cat_ids) ? '-' : implode(',', $final_cat_ids);
            $tags_txt = empty($final_tag_ids) ? '-' : implode(',', $final_tag_ids);
            $log_line = sprintf(
                'Composer apply OK | post=%d | featured=%d->%d | cats=%s | tags=%s | yoast_focus=%dc | yoast_metadesc=%d/139 | yoast_title=%dc | yoast_primary_cat=%d',
                (int)$post_id,
                (int)$featured_attach_id,
                (int)$featured_final,
                (string)$cats_txt,
                (string)$tags_txt,
                (int)$focus_len,
                (int)$metadesc_len,
                (int)$title_len,
                (int)$primary_cat
            );

            // Avoid noisy duplicates when frontend sends repeated identical apply requests.
            $log_fingerprint = md5(
                (string)$post_id . '|' .
                (string)$featured_attach_id . '|' .
                (string)$featured_final . '|' .
                (string)$cats_txt . '|' .
                (string)$tags_txt . '|' .
                (string)$focus_len . '|' .
                (string)$metadesc_len . '|' .
                (string)$title_len . '|' .
                (string)$primary_cat
            );
            $log_key = 'cbia_apply_full_post_log_' . (int)$post_id . '_' . $log_fingerprint;
            if (!get_transient($log_key)) {
                set_transient($log_key, 1, 20);
                cbia_log($log_line, 'INFO');
            }
        }

        wp_send_json_success(array(
            'post_id' => (int)$post_id,
            'title' => function_exists('cbia_ai_composer_normalize_title_value')
                ? cbia_ai_composer_normalize_title_value((string)get_the_title((int)$post_id), get_post((int)$post_id))
                : (string)get_the_title((int)$post_id),
            'content_html' => (string)get_post_field('post_content', (int)$post_id),
            'edit_url' => get_edit_post_link((int)$post_id, ''),
            'meta_description' => $metad,
            'focus_keyphrase' => $focus,
            'featured_attach_id' => (int)get_post_thumbnail_id($post_id),
            'category_ids' => array_values(array_map('intval', wp_get_post_categories($post_id, array('fields' => 'ids')))),
            'tag_ids' => array_values(array_map('intval', wp_get_post_terms($post_id, 'post_tag', array('fields' => 'ids')))),
            'tag_names' => array_values(array_map('sanitize_text_field', wp_get_post_terms($post_id, 'post_tag', array('fields' => 'names')))),
        ));
    }
}

if (!function_exists('cbia_ajax_get_log')) {
    function cbia_ajax_get_log() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        nocache_headers();
        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $service = $container->get('log_service');
        }
        if ($service && method_exists($service, 'get_log')) {
            wp_send_json_success($service->get_log('global'));
        }
        if (function_exists('cbia_get_log')) {
            $payload = cbia_get_log();
            wp_send_json_success($payload);
        }
        wp_send_json_success(['log' => '', 'counter' => 0]);
    }
}

if (!function_exists('cbia_ajax_create_post_from_preview')) {
    function cbia_ajax_create_post_from_preview() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }

        $token = isset($_POST['preview_token']) ? sanitize_text_field(wp_unslash($_POST['preview_token'])) : '';
        if ($token === '') {
            wp_send_json_error(array('message' => __('Preview token is required.', 'cbiastudio-blogflow-ai')), 400);
        }
        $overrides = array(
            'title' => isset($_POST['edited_title']) ? sanitize_text_field(wp_unslash($_POST['edited_title'])) : '',
            'html' => isset($_POST['edited_html']) ? wp_kses_post(wp_unslash($_POST['edited_html'])) : '',
            'post_status' => isset($_POST['post_status']) ? sanitize_key((string)wp_unslash($_POST['post_status'])) : 'publish',
            'post_date_local' => isset($_POST['post_date_local']) ? sanitize_text_field(wp_unslash($_POST['post_date_local'])) : '',
        );

        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $service = $container->get('article_preview_service');
        }
        if (!$service && class_exists('CBIA_Article_Preview_Service')) {
            $service = new CBIA_Article_Preview_Service();
        }
        if (!$service || !method_exists($service, 'create_post_from_token')) {
            wp_send_json_error(array('message' => __('Preview service unavailable', 'cbiastudio-blogflow-ai')), 500);
        }

        $result = $service->create_post_from_token($token, $overrides);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success($result);
    }
}

if (!function_exists('cbia_ajax_clear_log')) {
    function cbia_ajax_clear_log() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        if (function_exists('cbia_clear_log')) {
            cbia_clear_log();
        }
        wp_send_json_success(['ok' => 1]);
    }
}

if (!function_exists('cbia_ajax_clear_oldposts_log')) {
    function cbia_ajax_clear_oldposts_log() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        $cleared = false;
        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) {
                $service = $container->get('log_service');
            }
        }
        if ($service && method_exists($service, 'clear_log')) {
            $cleared = (bool) $service->clear_log('oldposts');
        } elseif (function_exists('cbia_oldposts_clear_log')) {
            cbia_oldposts_clear_log();
            $cleared = true;
        } elseif (function_exists('cbia_clear_log')) {
            cbia_clear_log();
            $cleared = true;
        }

        wp_send_json_success(['ok' => $cleared ? 1 : 0]);
    }
}

if (!function_exists('cbia_ajax_set_stop')) {
    function cbia_ajax_set_stop() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        $stop = isset($_POST['stop']) ? (int) $_POST['stop'] : 0;
        if (function_exists('cbia_set_stop_flag')) {
            cbia_set_stop_flag($stop === 1);
        }
        if ($stop === 1) {
            $state = function_exists('cbia_oldposts_get_active_run_state') ? cbia_oldposts_get_active_run_state() : array();
            if (!empty($state) && !empty($state['run_id']) && function_exists('cbia_oldposts_is_state_active') && cbia_oldposts_is_state_active($state)) {
                $state['status'] = 'stopping';
                $state['message'] = 'stop_requested';
                $state['updated_at'] = current_time('mysql');
                if (function_exists('cbia_oldposts_save_active_run_state')) {
                    cbia_oldposts_save_active_run_state($state);
                }
            }
            wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
            wp_clear_scheduled_hook('cbia_generation_event');
        }
        if (function_exists('cbia_log')) {
            cbia_log($stop === 1 ? 'STOP enabled (generation paused).' : 'STOP disabled (generation resumed).', 'INFO');
        }
        wp_send_json_success(['stop' => $stop === 1 ? 1 : 0]);
    }
}

if (!function_exists('cbia_ajax_get_checkpoint_status')) {
    function cbia_ajax_get_checkpoint_status() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        nocache_headers();
        if (!function_exists('cbia_checkpoint_get')) {
            wp_send_json_success(array(
                'status' => __('idle', 'cbiastudio-blogflow-ai'),
                'last' => __('(no records)', 'cbiastudio-blogflow-ai'),
                'running' => false,
                'pending' => false,
                'idx' => 0,
                'total' => 0,
                'lock_age' => 0,
                'next_event' => 0,
            ));
        }

        $blog_service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $blog_service = $container->get('blog_service');
        }

        if ($blog_service && method_exists($blog_service, 'get_checkpoint_status')) {
            $data = $blog_service->get_checkpoint_status();
            wp_send_json_success($data);
        }

        $cp = cbia_checkpoint_get();
        $queue = (array)($cp['queue'] ?? array());
        $idx = max(0, intval($cp['idx'] ?? 0));
        $total = count($queue);
        $running = !empty($cp) && !empty($cp['running']);
        $paused_error = !empty($cp['paused_error']) ? (string)$cp['paused_error'] : '';
        $pending = $running && $total > 0 && $idx < $total;
        if ($paused_error !== '') {
            $status = sprintf(
                /* translators: 1: current checkpoint index, 2: total queued posts, 3: error message */
                __('PAUSED | idx %1$d of %2$d | %3$s', 'cbiastudio-blogflow-ai'),
                $idx,
                $total,
                $paused_error
            );
        } else {
            $status = (!empty($cp) && !empty($cp['running']))
            ? sprintf(
                /* translators: 1: current checkpoint index, 2: total queued posts */
                __('RUNNING | idx %1$d of %2$d', 'cbiastudio-blogflow-ai'),
                $idx,
                $total
            )
            : __('idle', 'cbiastudio-blogflow-ai');
        }
        $last_dt = function_exists('cbia_get_last_scheduled_at')
            ? (cbia_get_last_scheduled_at() ?: __('(no records)', 'cbiastudio-blogflow-ai'))
            : __('(no records)', 'cbiastudio-blogflow-ai');
        $lock = function_exists('cbia_blog_generation_get_lock') ? cbia_blog_generation_get_lock() : array();
        $lock_age = (!empty($lock['locked_at'])) ? max(0, time() - (int)$lock['locked_at']) : 0;
        $next_event = function_exists('wp_next_scheduled') ? (int)wp_next_scheduled('cbia_generation_event') : 0;
        wp_send_json_success(array(
            'status' => $status,
            'last' => $last_dt,
            'running' => $running,
            'pending' => $pending,
            'idx' => $idx,
            'total' => $total,
            'paused_error' => $paused_error,
            'lock_age' => $lock_age,
            'next_event' => $next_event,
        ));
    }
}

if (!function_exists('cbia_ajax_start_generation')) {
    function cbia_ajax_start_generation() {
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD']))) : '';
        if ($request_method !== 'POST') wp_send_json_error(['msg' => 'method_not_allowed'], 405);
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);
        $post_unslashed = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();

        if (!function_exists('cbia_set_stop_flag')) {
            wp_send_json_error(['msg' => __('cbia_set_stop_flag unavailable', 'cbiastudio-blogflow-ai')], 500);
        }

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $settings_before_start = $settings;
        $settings_updated = false;

        if (array_key_exists('title_input_mode', $post_unslashed)) {
            $mode = sanitize_key((string)($post_unslashed['title_input_mode'] ?? 'manual'));
            $mode = in_array($mode, array('manual', 'csv'), true) ? $mode : 'manual';
            $settings['title_input_mode'] = $mode;
            $settings_updated = true;
        }
        if (array_key_exists('manual_titles', $post_unslashed)) {
            $settings['manual_titles'] = sanitize_textarea_field((string)($post_unslashed['manual_titles'] ?? ''));
            $settings_updated = true;
        }
        if (array_key_exists('csv_url', $post_unslashed)) {
            $csv_raw = sanitize_text_field((string)($post_unslashed['csv_url'] ?? ''));
            if (function_exists('cbia_ajax_normalize_csv_url')) {
                $settings['csv_url'] = cbia_ajax_normalize_csv_url($csv_raw);
            } else {
                $settings['csv_url'] = esc_url_raw($csv_raw);
            }
            $settings_updated = true;
        }
        if (array_key_exists('first_publication_datetime_local', $post_unslashed)) {
            $dt_local = sanitize_text_field(trim((string)($post_unslashed['first_publication_datetime_local'] ?? '')));
            if ($dt_local !== '') {
                $dt_local = str_replace('T', ' ', $dt_local);
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $dt_local)) {
                    $dt_local .= ':00';
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $dt_local)) {
                    $settings['first_publication_datetime'] = $dt_local;
                }
            } else {
                $settings['first_publication_datetime'] = '';
            }
            $settings_updated = true;
        }
        if (array_key_exists('publication_interval', $post_unslashed)) {
            $settings['publication_interval'] = max(1, intval($post_unslashed['publication_interval'] ?? 5));
            $settings_updated = true;
        }
        if (array_key_exists('blog_posts_per_event', $post_unslashed)) {
            $settings['blog_posts_per_event'] = max(1, min(5, intval($post_unslashed['blog_posts_per_event'] ?? 1)));
            $settings_updated = true;
        }
        $simple_runtime_fields = array(
            'post_length_variant' => 'key',
            'post_language' => 'text',
            'images_limit' => 'int',
            'image_style' => 'text',
            'default_author_id' => 'absint',
            'default_category' => 'text',
            'keywords_to_categories' => 'textarea',
            'default_tags' => 'text',
            'blog_prompt_profile' => 'key',
            'search_intent_strength' => 'key',
            'blog_prompt_mode' => 'key',
            'blog_prompt_mode_ui' => 'key',
            'blog_prompt_mode_state' => 'key',
            'blog_prompt_editable' => 'textarea',
            'legacy_full_prompt' => 'textarea',
            'blog_prompt_custom_instructions' => 'textarea',
        );
        foreach ($simple_runtime_fields as $field => $kind) {
            if (!array_key_exists($field, $post_unslashed)) continue;
            $target_field = ($field === 'blog_prompt_mode_ui' || $field === 'blog_prompt_mode_state') ? 'blog_prompt_mode' : $field;
            $raw_value = $post_unslashed[$field];
            if ($kind === 'int') {
                $settings[$target_field] = max(0, intval($raw_value));
            } elseif ($kind === 'absint') {
                $settings[$target_field] = absint($raw_value);
            } elseif ($kind === 'textarea') {
                $settings[$target_field] = sanitize_textarea_field((string)$raw_value);
            } elseif ($kind === 'key') {
                $settings[$target_field] = sanitize_key((string)$raw_value);
            } else {
                $settings[$target_field] = sanitize_text_field((string)$raw_value);
            }
            $settings_updated = true;
        }
        foreach (array('include_faq', 'include_practical_examples') as $flag_field) {
            if (array_key_exists($flag_field, $post_unslashed)) {
                $settings[$flag_field] = !empty($post_unslashed[$flag_field]) ? 1 : 0;
                $settings_updated = true;
            }
        }
        if (!empty($settings['blog_prompt_mode'])) {
            $settings['blog_prompt_mode'] = in_array((string)$settings['blog_prompt_mode'], array('recommended', 'legacy'), true) ? (string)$settings['blog_prompt_mode'] : 'recommended';
            $settings['blog_prompt_legacy_enabled'] = ($settings['blog_prompt_mode'] === 'legacy') ? 1 : 0;
        }
        if ($settings_updated) {
            $runtime_keys = array_fill_keys(array_merge(
                array(
                    'title_input_mode', 'manual_titles', 'csv_url', 'first_publication_datetime',
                    'publication_interval', 'blog_posts_per_event', 'include_faq',
                    'include_practical_examples', 'blog_prompt_legacy_enabled',
                ),
                array_keys($simple_runtime_fields)
            ), true);
            $runtime_keys['blog_prompt_mode'] = true;
            unset($runtime_keys['blog_prompt_mode_ui'], $runtime_keys['blog_prompt_mode_state']);
            $runtime_settings = array_intersect_key($settings, $runtime_keys);
            if (function_exists('cbia_update_settings_merge')) {
                cbia_update_settings_merge($runtime_settings);
            } else {
                $stored_settings = get_option('cbia_settings', array());
                $stored_settings = is_array($stored_settings) ? $stored_settings : array();
                update_option('cbia_settings', array_replace($stored_settings, $runtime_settings), false);
            }
            $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array_replace((array)$settings_before_start, $runtime_settings);
            if (function_exists('cbia_log_message')) {
                cbia_log_message('[INFO] START AJAX: runtime settings synced from current Blog form values.');
            }
        }
        // Blog always generates a featured image; images_limit only controls the total count.
        $images_requested = true;
        if (function_exists('cbia_generation_preflight')) {
            $preflight = cbia_generation_preflight((array)$settings, $images_requested);
            if (empty($preflight['ok'])) {
                $error = (array)($preflight['errors'][0] ?? array('code' => 'local_validation', 'message' => 'Generation preflight failed.'));
                if (function_exists('cbia_record_local_preflight_failure')) cbia_record_local_preflight_failure($error, $preflight, 'blog_start_preflight');
                wp_send_json_error(array(
                    'msg' => sanitize_text_field((string)($error['message'] ?? 'Generation preflight failed.')),
                    'error_type' => sanitize_key((string)($error['code'] ?? 'local_validation')),
                    'preflight' => $preflight,
                ), 400);
            }
        }
        if (function_exists('cbia_checkpoint_get') && function_exists('cbia_checkpoint_clear')) {
            $cp = cbia_checkpoint_get();
            $should_clear_checkpoint = false;
            if (!empty($cp) && !empty($cp['queue']) && is_array($cp['queue'])) {
                if (array_key_exists('manual_titles', $post_unslashed)) {
                    $submitted_titles = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($post_unslashed['manual_titles'] ?? ''))))));
                    $queued_titles = array_values(array_map('strval', (array)$cp['queue']));
                    if ($submitted_titles !== $queued_titles) {
                        $should_clear_checkpoint = true;
                    }
                }
                if (array_key_exists('csv_url', $post_unslashed) && (string)($settings_before_start['csv_url'] ?? '') !== (string)($settings['csv_url'] ?? '')) {
                    $should_clear_checkpoint = true;
                }
                if (array_key_exists('first_publication_datetime_local', $post_unslashed) && (string)($settings_before_start['first_publication_datetime'] ?? '') !== (string)($settings['first_publication_datetime'] ?? '')) {
                    $should_clear_checkpoint = true;
                }
                if (array_key_exists('publication_interval', $post_unslashed) && (int)($settings_before_start['publication_interval'] ?? 5) !== (int)($settings['publication_interval'] ?? 5)) {
                    $should_clear_checkpoint = true;
                }
            }
            if ($should_clear_checkpoint) {
                cbia_checkpoint_clear();
                if (function_exists('cbia_set_last_scheduled_at')) cbia_set_last_scheduled_at('');
                if (function_exists('cbia_log_message')) cbia_log_message('[INFO] START AJAX: old checkpoint cleared because current Blog form changed.');
            }
        }

        cbia_set_stop_flag(false);

        $blog_service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $blog_service = $container->get('blog_service');
        }

        if (!$blog_service && !function_exists('cbia_run_generate_blogs')) {
            wp_send_json_error(['msg' => __('cbia_run_generate_blogs unavailable', 'cbiastudio-blogflow-ai')], 500);
        }
        if (function_exists('cbia_log_message')) {
            cbia_log_message('[INFO] START AJAX received.');
        }

        // Ejecuta una tanda inmediata para que haya log visible.
        $max_per_run = function_exists('cbia_blog_get_posts_per_event') ? cbia_blog_get_posts_per_event($settings) : max(1, min(5, intval($settings['blog_posts_per_event'] ?? 1)));
        if (function_exists('cbia_log_message')) {
            cbia_log_message('[INFO] START: running first immediate chunk (chunk_size=' . (int)$max_per_run . ') to avoid "nothing happens".');
        }
        try {
            $result = $blog_service
                ? $blog_service->run_generate_blogs($max_per_run)
                : cbia_run_generate_blogs($max_per_run);
        } catch (Throwable $e) {
            if (function_exists('cbia_log_message')) {
                cbia_log_message('[ERROR] START AJAX exception: ' . $e->getMessage());
            }
            wp_send_json_error(['msg' => 'start_generation_exception', 'error' => $e->getMessage()], 500);
        }

        // No encolar aqui: cbia_run_generate_blogs ya gestiona el scheduling si queda cola.
        if (function_exists('cbia_log_message')) {
            cbia_log_message(empty($result['done'])
                ? '[INFO] START: queue still pending, scheduler will continue.'
                : '[INFO] START: no pending queue left.'
            );
        }

        wp_send_json_success(['ok' => 1, 'result' => $result]);
    }
}

if (!function_exists('cbia_ajax_get_oldposts_log')) {
    function cbia_ajax_get_oldposts_log() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        nocache_headers();
        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $service = $container->get('log_service');
        }
        if ($service && method_exists($service, 'get_log')) {
            wp_send_json_success($service->get_log('oldposts'));
        }
        if (function_exists('cbia_oldposts_get_log')) {
            wp_send_json_success(cbia_oldposts_get_log());
        }
        if (function_exists('cbia_get_log')) {
            wp_send_json_success(cbia_get_log());
        }
        wp_send_json_success(['log' => '', 'counter' => 0]);
    }
}

if (!function_exists('cbia_oldposts_build_run_args_from_request')) {
    function cbia_oldposts_build_run_args_from_request($request = array()) {
        $request = is_array($request) ? $request : array();
        $settings = function_exists('cbia_oldposts_get_settings')
            ? cbia_oldposts_get_settings()
            : get_option('cbia_oldposts_settings', array());
        $settings = is_array($settings) ? $settings : array();
        $action = sanitize_text_field((string)($request['cbia_action'] ?? 'run_oldposts'));

        $run = $settings;
        $run['batch_size'] = isset($request['run_batch_size']) ? max(1, min(200, (int)$request['run_batch_size'])) : (int)($settings['batch_size'] ?? 20);
        $run['scope'] = !empty($request['run_scope_plugin']) ? 'plugin' : 'all';
        $run['filter_mode'] = in_array(($request['run_filter_mode'] ?? ''), array('all', 'range', 'older'), true)
            ? (string)$request['run_filter_mode']
            : 'all';
        $run['older_than_days'] = isset($request['run_older_than_days']) ? max(1, (int)$request['run_older_than_days']) : (int)($settings['older_than_days'] ?? 180);
        $run['date_from'] = function_exists('cbia_oldposts_sanitize_ymd')
            ? cbia_oldposts_sanitize_ymd($request['run_date_from'] ?? ($settings['date_from'] ?? ''))
            : sanitize_text_field((string)($request['run_date_from'] ?? ''));
        $run['date_to'] = function_exists('cbia_oldposts_sanitize_ymd')
            ? cbia_oldposts_sanitize_ymd($request['run_date_to'] ?? ($settings['date_to'] ?? ''))
            : sanitize_text_field((string)($request['run_date_to'] ?? ''));
        $run['images_limit'] = isset($request['run_images_limit']) ? max(1, min(3, (int)$request['run_images_limit'])) : (int)($settings['images_limit'] ?? 3);

        $run_post_ids_raw = isset($request['run_post_ids'])
            ? sanitize_text_field((string)$request['run_post_ids'])
            : (isset($request['post_ids']) ? sanitize_text_field((string)$request['post_ids']) : (string)($settings['post_ids'] ?? ''));
        $run['post_ids'] = function_exists('cbia_oldposts_parse_ids_csv')
            ? cbia_oldposts_parse_ids_csv($run_post_ids_raw)
            : array_values(array_filter(array_map('absint', explode(',', $run_post_ids_raw))));
        $run['category_id'] = isset($request['run_category_id'])
            ? (int)$request['run_category_id']
            : (isset($request['category_id']) ? (int)$request['category_id'] : (int)($settings['category_id'] ?? 0));
        $run['author_id'] = isset($request['run_author_id'])
            ? (int)$request['run_author_id']
            : (isset($request['author_id']) ? (int)$request['author_id'] : (int)($settings['author_id'] ?? 0));
        $run['dry_run'] = !empty($request['run_dry_run']) || !empty($request['dry_run']) ? 1 : 0;

        $run['run_post_length_variant'] = sanitize_key((string)($request['run_post_length_variant'] ?? ($settings['run_post_length_variant'] ?? 'medium')));
        if (!in_array($run['run_post_length_variant'], array('short', 'medium', 'long'), true)) {
            $run['run_post_length_variant'] = 'medium';
        }
        $run['run_text_provider'] = sanitize_key((string)($request['run_text_provider'] ?? ($settings['run_text_provider'] ?? 'openai')));
        if ($run['run_text_provider'] === '') {
            $run['run_text_provider'] = 'openai';
        }
        $run['run_text_model'] = sanitize_text_field((string)($request['run_text_model'] ?? ($settings['run_text_model'] ?? '')));
        $run['run_image_provider'] = sanitize_key((string)($request['run_image_provider'] ?? ($settings['run_image_provider'] ?? 'openai')));
        if (
            $run['run_image_provider'] === ''
            || (function_exists('cbia_providers_supports_image') && !cbia_providers_supports_image($run['run_image_provider']))
        ) {
            $run['run_image_provider'] = 'openai';
        }
        $run['run_image_model'] = sanitize_text_field((string)($request['run_image_model'] ?? ($settings['run_image_model'] ?? '')));

        $settings['run_post_length_variant'] = $run['run_post_length_variant'];
        $settings['run_text_provider'] = $run['run_text_provider'];
        $settings['run_text_model'] = $run['run_text_model'];
        $settings['run_image_provider'] = $run['run_image_provider'];
        $settings['run_image_model'] = $run['run_image_model'];
        update_option(cbia_oldposts_settings_key(), $settings);

        if ($action === 'run_oldposts') {
            if (!empty($request['run_custom_actions'])) {
                $run['do_note'] = !empty($request['run_do_note']) ? 1 : 0;
                $run['force_note'] = !empty($run['do_note']) ? 1 : 0;
                $run['do_yoast_metadesc'] = !empty($request['run_do_yoast_metadesc']) ? 1 : 0;
                $run['do_yoast_focuskw'] = !empty($request['run_do_yoast_focuskw']) ? 1 : 0;
                $run['do_yoast_title'] = !empty($request['run_do_yoast_title']) ? 1 : 0;
                $run['force_yoast'] = (!empty($run['do_yoast_metadesc']) || !empty($run['do_yoast_focuskw']) || !empty($run['do_yoast_title'])) ? 1 : 0;
                $run['do_yoast_reindex'] = !empty($request['run_do_yoast_reindex']) ? 1 : 0;
                $run['do_title'] = !empty($request['run_do_title']) ? 1 : 0;
                $run['force_title'] = !empty($run['do_title']) ? 1 : 0;
                $run['do_content'] = !empty($request['run_do_content']) ? 1 : 0;
                $run['force_content'] = !empty($run['do_content']) ? 1 : 0;
                $run['do_content_no_images'] = !empty($request['run_do_content_no_images']) ? 1 : 0;
                $run['force_content_no_images'] = !empty($run['do_content_no_images']) ? 1 : 0;
                $run['do_images_reset'] = !empty($request['run_do_images_reset']) ? 1 : 0;
                $run['force_images_reset'] = !empty($run['do_images_reset']) ? 1 : 0;
                $run['clear_featured'] = !empty($request['run_clear_featured']) ? 1 : 0;
                $run['do_images_content_only'] = !empty($request['run_do_images_content_only']) ? 1 : 0;
                $run['force_images_content_only'] = !empty($run['do_images_content_only']) ? 1 : 0;
                $run['do_featured_only'] = !empty($request['run_do_featured_only']) ? 1 : 0;
                $run['force_featured_only'] = !empty($run['do_featured_only']) ? 1 : 0;
                $run['featured_remove_old'] = !empty($request['run_featured_remove_old']) ? 1 : 0;
                $run['do_categories'] = !empty($request['run_do_categories']) ? 1 : 0;
                $run['force_categories'] = !empty($run['do_categories']) ? 1 : 0;
                $run['do_tags'] = !empty($request['run_do_tags']) ? 1 : 0;
                $run['force_tags'] = !empty($run['do_tags']) ? 1 : 0;
            }
            if (!cbia_cap_enabled('internal_images')) {
                $run['do_images_reset'] = 0;
                $run['force_images_reset'] = 0;
                $run['do_images_content_only'] = 0;
                $run['force_images_content_only'] = 0;
                $run['images_limit'] = 1;
            }
            return $run;
        }

        $action_keys = array(
            'do_note','force_note',
            'do_yoast_metadesc','do_yoast_focuskw','do_yoast_title','force_yoast','do_yoast_reindex',
            'do_title','force_title',
            'do_content','force_content',
            'do_content_no_images','force_content_no_images',
            'do_images_reset','force_images_reset','clear_featured',
            'do_images_content_only','force_images_content_only',
            'do_featured_only','force_featured_only','featured_remove_old',
            'do_categories','force_categories',
            'do_tags','force_tags',
        );
        foreach ($action_keys as $key) {
            $run[$key] = 0;
        }

        if ($action === 'run_quick_yoast_metas') {
            $run['do_yoast_metadesc'] = 1;
            $run['do_yoast_focuskw'] = 1;
            $run['do_yoast_title'] = 1;
            $run['force_yoast'] = 1;
        } elseif ($action === 'run_quick_yoast_reindex') {
            $run['do_yoast_reindex'] = 1;
        } elseif ($action === 'run_quick_featured') {
            $run['do_featured_only'] = 1;
            $run['force_featured_only'] = 1;
            $run['featured_remove_old'] = !empty($request['run_featured_remove_old']) ? 1 : 0;
        } elseif ($action === 'run_quick_images_only') {
            $run['do_images_content_only'] = 1;
            $run['force_images_content_only'] = 1;
        } elseif ($action === 'run_quick_content_only') {
            $run['do_content_no_images'] = 1;
            $run['force_content_no_images'] = 1;
        }

        if (!cbia_cap_enabled('internal_images')) {
            $run['do_images_reset'] = 0;
            $run['force_images_reset'] = 0;
            $run['do_images_content_only'] = 0;
            $run['force_images_content_only'] = 0;
            $run['images_limit'] = 1;
        }

        return $run;
    }
}

if (!function_exists('cbia_oldposts_get_queue_ids_for_run')) {
    function cbia_oldposts_get_queue_ids_for_run($run = array()) {
        if (!function_exists('cbia_oldposts_build_query_args')) {
            return array();
        }
        $run = is_array($run) ? $run : array();
        $args = cbia_oldposts_build_query_args(
            (int)($run['batch_size'] ?? 20),
            (string)($run['scope'] ?? 'all'),
            (string)($run['filter_mode'] ?? 'all'),
            (int)($run['older_than_days'] ?? 180),
            (string)($run['date_from'] ?? ''),
            (string)($run['date_to'] ?? ''),
            $run['post_ids'] ?? array(),
            (int)($run['category_id'] ?? 0),
            (int)($run['author_id'] ?? 0),
            true
        );
        $args['fields'] = 'ids';
        $args['no_found_rows'] = true;
        $query = new WP_Query($args);
        return !empty($query->posts) && is_array($query->posts)
            ? array_values(array_filter(array_map('absint', $query->posts)))
            : array();
    }
}

if (!function_exists('cbia_oldposts_pick_chunk_size')) {
    function cbia_oldposts_pick_chunk_size($run = array()) {
        $run = is_array($run) ? $run : array();
        $heavy = !empty($run['do_content'])
            || !empty($run['do_content_no_images'])
            || !empty($run['do_title'])
            || !empty($run['do_images_reset'])
            || !empty($run['do_images_content_only'])
            || !empty($run['do_featured_only']);
        return $heavy ? 1 : 5;
    }
}

if (!function_exists('cbia_oldposts_log_run_start')) {
    function cbia_oldposts_log_run_start($run = array(), $queue_ids = array()) {
        if (!function_exists('cbia_oldposts_log_message')) {
            return;
        }
        $run = is_array($run) ? $run : array();
        $queue_ids = is_array($queue_ids) ? array_values(array_filter(array_map('absint', $queue_ids))) : array();
        $ids_txt = !empty($queue_ids) ? implode(',', array_slice($queue_ids, 0, 20)) : '(auto)';
        if (!empty($queue_ids) && count($queue_ids) > 20) {
            $ids_txt .= ',...';
        }
        cbia_oldposts_log_message(
            sprintf(
                /* translators: 1: batch size, 2: scope, 3: filter mode, 4: older-than days, 5: from date, 6: to date, 7: images limit, 8: selected IDs, 9: category ID, 10: author ID, 11: dry-run flag. */
                __('START v3 AJAX | batch=%1$d | scope=%2$s | filter=%3$s | older_than_days=%4$d | from=%5$s | to=%6$s | images_limit=%7$d | ids=%8$s | category=%9$d | author=%10$d | dry_run=%11$s', 'cbiastudio-blogflow-ai'),
                (int)($run['batch_size'] ?? 20),
                (string)($run['scope'] ?? 'all'),
                (string)($run['filter_mode'] ?? 'all'),
                (int)($run['older_than_days'] ?? 180),
                (string)($run['date_from'] ?? ''),
                (string)($run['date_to'] ?? ''),
                (int)($run['images_limit'] ?? 3),
                $ids_txt,
                (int)($run['category_id'] ?? 0),
                (int)($run['author_id'] ?? 0),
                (!empty($run['dry_run']) ? 'YES' : 'NO')
            )
        );
        cbia_oldposts_log_message(
            sprintf(
                /* translators: 1-24 are YES/NO flags for enabled actions and force modes in oldposts batch execution. */
                __('ACTIONS | note=%1$s(force=%2$s) | yoast(metadesc=%3$s,focuskw=%4$s,title=%5$s,force=%6$s) | yoast_reindex=%7$s | titleIA=%8$s(force=%9$s) | contentIA=%10$s(force=%11$s) | contentIA_noimg=%12$s(force=%13$s) | images_reset=%14$s(force=%15$s,clear_featured=%16$s) | images_content_only=%17$s(force=%18$s) | featured_only=%19$s(force=%20$s,remove_old=%21$s) | categories=%22$s(force=%23$s) | tags=%24$s(force=%25$s)', 'cbiastudio-blogflow-ai'),
                (!empty($run['do_note']) ? 'YES' : 'NO'),
                (!empty($run['force_note']) ? 'YES' : 'NO'),
                (!empty($run['do_yoast_metadesc']) ? 'YES' : 'NO'),
                (!empty($run['do_yoast_focuskw']) ? 'YES' : 'NO'),
                (!empty($run['do_yoast_title']) ? 'YES' : 'NO'),
                (!empty($run['force_yoast']) ? 'YES' : 'NO'),
                (!empty($run['do_yoast_reindex']) ? 'YES' : 'NO'),
                (!empty($run['do_title']) ? 'YES' : 'NO'),
                (!empty($run['force_title']) ? 'YES' : 'NO'),
                (!empty($run['do_content']) ? 'YES' : 'NO'),
                (!empty($run['force_content']) ? 'YES' : 'NO'),
                (!empty($run['do_content_no_images']) ? 'YES' : 'NO'),
                (!empty($run['force_content_no_images']) ? 'YES' : 'NO'),
                (!empty($run['do_images_reset']) ? 'YES' : 'NO'),
                (!empty($run['force_images_reset']) ? 'YES' : 'NO'),
                (!empty($run['clear_featured']) ? 'YES' : 'NO'),
                (!empty($run['do_images_content_only']) ? 'YES' : 'NO'),
                (!empty($run['force_images_content_only']) ? 'YES' : 'NO'),
                (!empty($run['do_featured_only']) ? 'YES' : 'NO'),
                (!empty($run['force_featured_only']) ? 'YES' : 'NO'),
                (!empty($run['featured_remove_old']) ? 'YES' : 'NO'),
                (!empty($run['do_categories']) ? 'YES' : 'NO'),
                (!empty($run['force_categories']) ? 'YES' : 'NO'),
                (!empty($run['do_tags']) ? 'YES' : 'NO'),
                (!empty($run['force_tags']) ? 'YES' : 'NO')
            )
        );
        if (!empty($run['post_ids'])) {
            cbia_oldposts_log_message(__('NOTE: specific IDs were provided. Date/category/author filters are ignored.', 'cbiastudio-blogflow-ai'));
        }
    }
}

if (!function_exists('cbia_oldposts_active_run_key')) {
    function cbia_oldposts_active_run_key() {
        return 'cbia_oldposts_active_run_state';
    }
}

if (!function_exists('cbia_oldposts_get_active_run_state')) {
    function cbia_oldposts_get_active_run_state() {
        $state = get_option(cbia_oldposts_active_run_key(), array());
        return is_array($state) ? $state : array();
    }
}

if (!function_exists('cbia_oldposts_save_active_run_state')) {
    function cbia_oldposts_save_active_run_state($state = array()) {
        update_option(cbia_oldposts_active_run_key(), is_array($state) ? $state : array(), false);
    }
}

if (!function_exists('cbia_oldposts_background_lock_key')) {
    function cbia_oldposts_background_lock_key() {
        return 'cbia_oldposts_background_lock';
    }
}

if (!function_exists('cbia_oldposts_acquire_background_lock')) {
    function cbia_oldposts_acquire_background_lock($run_id = '') {
        $key = cbia_oldposts_background_lock_key();
        $now = time();
        $ttl = 300;
        $existing = get_option($key, array());
        if (is_array($existing) && !empty($existing['locked_at'])) {
            $locked_at = (int)$existing['locked_at'];
            if ($locked_at > 0 && ($now - $locked_at) < $ttl) {
                return false;
            }
        }
        delete_option($key);
        return add_option($key, array(
            'run_id' => sanitize_text_field((string)$run_id),
            'locked_at' => $now,
        ), '', 'no');
    }
}

if (!function_exists('cbia_oldposts_release_background_lock')) {
    function cbia_oldposts_release_background_lock($run_id = '') {
        $key = cbia_oldposts_background_lock_key();
        $existing = get_option($key, array());
        if (!is_array($existing) || empty($existing)) {
            return;
        }
        $existing_run_id = sanitize_text_field((string)($existing['run_id'] ?? ''));
        $run_id = sanitize_text_field((string)$run_id);
        if ($run_id !== '' && $existing_run_id !== '' && $existing_run_id !== $run_id) {
            return;
        }
        delete_option($key);
    }
}

if (!function_exists('cbia_oldposts_extract_ui_state_from_request')) {
    function cbia_oldposts_extract_ui_state_from_request($request = array()) {
        $request = is_array($request) ? $request : array();
        $checkbox_fields = array(
            'run_scope_plugin',
            'run_dry_run',
            'run_do_note',
            'run_do_yoast_metadesc',
            'run_do_yoast_focuskw',
            'run_do_yoast_title',
            'run_do_yoast_reindex',
            'run_do_title',
            'run_do_content',
            'run_do_content_no_images',
            'run_do_images_reset',
            'run_clear_featured',
            'run_do_images_content_only',
            'run_do_featured_only',
            'run_featured_remove_old',
            'run_do_categories',
            'run_do_tags',
            'run_reprocess_text',
            'run_reprocess_images',
            'run_reprocess_meta',
        );
        $text_fields = array(
            'run_batch_size',
            'run_filter_mode',
            'run_older_than_days',
            'run_date_from',
            'run_date_to',
            'run_images_limit',
            'run_post_ids',
            'run_category_id',
            'run_author_id',
            'run_post_length_variant',
            'run_text_provider',
            'run_text_model',
            'run_image_provider',
            'run_image_model',
        );

        $fields = array();
        foreach ($checkbox_fields as $field) {
            $fields[$field] = !empty($request[$field]) ? 1 : 0;
        }
        foreach ($text_fields as $field) {
            $fields[$field] = isset($request[$field]) ? sanitize_text_field((string)$request[$field]) : '';
        }

        return $fields;
    }
}

if (!function_exists('cbia_oldposts_is_state_active')) {
    function cbia_oldposts_is_state_active($state = array()) {
        $status = is_array($state) ? (string)($state['status'] ?? '') : '';
        return in_array($status, array('queued', 'running', 'stopping'), true);
    }
}

if (!function_exists('cbia_oldposts_public_run_state')) {
    function cbia_oldposts_public_run_state($state = array()) {
        $state = is_array($state) ? $state : array();
        if (empty($state)) {
            return array();
        }

        $public = array(
            'run_id' => sanitize_text_field((string)($state['run_id'] ?? '')),
            'action' => sanitize_text_field((string)($state['action'] ?? 'run_oldposts')),
            'status' => sanitize_text_field((string)($state['status'] ?? 'idle')),
            'created_at' => sanitize_text_field((string)($state['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string)($state['updated_at'] ?? '')),
            'finished_at' => sanitize_text_field((string)($state['finished_at'] ?? '')),
            'message' => sanitize_text_field((string)($state['message'] ?? '')),
            'total' => max(0, (int)($state['total'] ?? 0)),
            'offset' => max(0, (int)($state['offset'] ?? 0)),
            'processed' => max(0, (int)($state['processed'] ?? 0)),
            'ok' => max(0, (int)($state['ok'] ?? 0)),
            'skipped' => max(0, (int)($state['skipped'] ?? 0)),
            'fail' => max(0, (int)($state['fail'] ?? 0)),
            'chunk_size' => max(1, (int)($state['chunk_size'] ?? 1)),
            'last_chunk_ids' => array_values(array_filter(array_map('absint', (array)($state['last_chunk_ids'] ?? array())))),
            'processed_ids' => array_values(array_filter(array_map('absint', (array)($state['processed_ids'] ?? array())))),
            'active' => cbia_oldposts_is_state_active($state) ? 1 : 0,
            'ui_state' => is_array($state['ui_state'] ?? null) ? $state['ui_state'] : array(),
        );
        $public['remaining'] = max(0, $public['total'] - $public['processed']);

        return $public;
    }
}

if (!function_exists('cbia_oldposts_schedule_background_run')) {
    function cbia_oldposts_schedule_background_run($delay = 1) {
        $delay = max(0, (int)$delay);
        if (!wp_next_scheduled('cbia_oldposts_process_background_run')) {
            wp_schedule_single_event(time() + $delay, 'cbia_oldposts_process_background_run');
        }
        if (function_exists('spawn_cron')) {
            @spawn_cron(time() + $delay);
        }
    }
}

if (!function_exists('cbia_oldposts_process_background_run')) {
    function cbia_oldposts_process_background_run() {
        $state = cbia_oldposts_get_active_run_state();
        if (empty($state) || empty($state['run_id'])) {
            return;
        }
        $run_id = sanitize_text_field((string)($state['run_id'] ?? ''));
        if (!cbia_oldposts_acquire_background_lock($run_id)) {
            return;
        }
        try {
            $now_ts = time();
            $status_now = (string)($state['status'] ?? '');
            $running_heartbeat = (int)($state['worker_heartbeat'] ?? 0);
            if ($status_now === 'running' && $running_heartbeat > 0 && (($now_ts - $running_heartbeat) < 900)) {
                return;
            }
            if (!function_exists('cbia_oldposts_run_batch_with_overrides')) {
                $state['status'] = 'error';
                $state['message'] = 'runner_unavailable';
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                $state['worker_heartbeat'] = 0;
                cbia_oldposts_save_active_run_state($state);
                return;
            }
            if (function_exists('cbia_check_stop_flag') && cbia_check_stop_flag()) {
                $state['status'] = 'stopped';
                $state['message'] = 'stop_requested';
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                $state['worker_heartbeat'] = 0;
                cbia_oldposts_save_active_run_state($state);
                wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
                return;
            }

            $queue_ids = array_values(array_filter(array_map('absint', (array)($state['queue_ids'] ?? array()))));
            $total = count($queue_ids);
            $offset = max(0, (int)($state['offset'] ?? 0));
            $chunk_size = max(1, (int)($state['chunk_size'] ?? 1));

            if ($offset >= $total) {
                $state['status'] = 'completed';
                $state['message'] = 'completed';
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                if (is_array($state['ui_state'] ?? null)) {
                    $state['ui_state']['run_post_ids'] = '';
                }
                $state['worker_heartbeat'] = 0;
                cbia_oldposts_save_active_run_state($state);
                wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
                return;
            }

            $chunk_ids = array_slice($queue_ids, $offset, $chunk_size);
            if (empty($chunk_ids)) {
                $state['status'] = 'completed';
                $state['message'] = 'completed';
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                if (is_array($state['ui_state'] ?? null)) {
                    $state['ui_state']['run_post_ids'] = '';
                }
                $state['worker_heartbeat'] = 0;
                cbia_oldposts_save_active_run_state($state);
                wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
                return;
            }

            $state['status'] = 'running';
            $state['message'] = 'running';
            $state['updated_at'] = current_time('mysql');
            $state['last_chunk_ids'] = $chunk_ids;
            $state['worker_heartbeat'] = $now_ts;
            cbia_oldposts_save_active_run_state($state);

            $run = is_array($state['run'] ?? null) ? $state['run'] : array();
            $run['post_ids'] = $chunk_ids;
            $run['batch_size'] = count($chunk_ids);
            $run['suppress_batch_header'] = 1;
            $run['suppress_batch_footer'] = 1;

            if (function_exists('cbia_oldposts_log_message')) {
                /* translators: 1: comma-separated post IDs in the chunk, 2: chunk size. */
                cbia_oldposts_log_message(sprintf(__('TRAMO AJAX | ids=%1$s | size=%2$d', 'cbiastudio-blogflow-ai'), implode(',', $chunk_ids), count($chunk_ids)));
            }

            try {
                $result = cbia_oldposts_run_batch_with_overrides($run);
                $state['processed'] = max(0, (int)($state['processed'] ?? 0)) + (int)($result[0] ?? 0);
                $state['ok'] = max(0, (int)($state['ok'] ?? 0)) + (int)($result[1] ?? 0);
                $state['skipped'] = max(0, (int)($state['skipped'] ?? 0)) + (int)($result[2] ?? 0);
                $state['fail'] = max(0, (int)($state['fail'] ?? 0)) + (int)($result[3] ?? 0);
                $state['offset'] = $offset + count($chunk_ids);
                $state['updated_at'] = current_time('mysql');
                $processed_ids = array_values(array_filter(array_map('absint', (array)($state['processed_ids'] ?? array()))));
                $processed_ids = array_values(array_unique(array_merge($processed_ids, $chunk_ids)));
                $state['processed_ids'] = $processed_ids;

                if (function_exists('cbia_oldposts_log_message')) {
                    cbia_oldposts_log_message(sprintf(
                        /* translators: 1: comma-separated post IDs, 2: processed count, 3: ok count, 4: skipped count, 5: failed count. */
                        __('FIN TRAMO AJAX | ids=%1$s | processed=%2$d | ok=%3$d | skip=%4$d | fail=%5$d', 'cbiastudio-blogflow-ai'),
                        implode(',', $chunk_ids),
                        (int)($result[0] ?? 0),
                        (int)($result[1] ?? 0),
                        (int)($result[2] ?? 0),
                        (int)($result[3] ?? 0)
                    ));
                }
            } catch (Throwable $e) {
                $state['status'] = 'error';
                $state['message'] = sanitize_text_field($e->getMessage());
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                $state['worker_heartbeat'] = 0;
                if (function_exists('cbia_oldposts_log_message')) {
                    cbia_oldposts_log_message('[ERROR] Background oldposts chunk failed: ' . $e->getMessage());
                }
                cbia_oldposts_save_active_run_state($state);
                wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
                return;
            }

            if (function_exists('cbia_check_stop_flag') && cbia_check_stop_flag()) {
                $state['status'] = 'stopped';
                $state['message'] = 'stop_requested';
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                $state['worker_heartbeat'] = 0;
                cbia_oldposts_save_active_run_state($state);
                wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
                return;
            }

            if ((int)$state['offset'] >= $total) {
                $state['status'] = 'completed';
                $state['message'] = 'completed';
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                if (is_array($state['ui_state'] ?? null)) {
                    $state['ui_state']['run_post_ids'] = '';
                }
                $state['worker_heartbeat'] = 0;
                cbia_oldposts_save_active_run_state($state);
                wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
                return;
            }

            $state['status'] = 'queued';
            $state['message'] = 'queued';
            $state['worker_heartbeat'] = 0;
            cbia_oldposts_save_active_run_state($state);
            cbia_oldposts_schedule_background_run(1);
        } finally {
            cbia_oldposts_release_background_lock($run_id);
        }
    }
}

if (!function_exists('cbia_ajax_oldposts_prepare_run')) {
    function cbia_ajax_oldposts_prepare_run() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);

        $request = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
        $manual_chunk_mode = !empty($request['manual_chunk_mode']);
        $action = sanitize_text_field((string)($request['cbia_action'] ?? 'run_oldposts'));
        $allowed_actions = array('run_oldposts', 'run_quick_yoast_metas', 'run_quick_yoast_reindex', 'run_quick_featured', 'run_quick_images_only', 'run_quick_content_only');
        if (!in_array($action, $allowed_actions, true)) {
            wp_send_json_error(array('msg' => __('Unsupported action', 'cbiastudio-blogflow-ai')), 400);
        }

        $existing_state = cbia_oldposts_get_active_run_state();
        if (cbia_oldposts_is_state_active($existing_state)) {
            wp_send_json_error(array(
                'msg' => __('There is already an old posts batch running. Wait for it to finish or stop it first.', 'cbiastudio-blogflow-ai'),
                'state' => cbia_oldposts_public_run_state($existing_state),
            ), 409);
        }

        $run = cbia_oldposts_build_run_args_from_request($request);
        $queue_ids = cbia_oldposts_get_queue_ids_for_run($run);
        $chunk_size = min(max(1, cbia_oldposts_pick_chunk_size($run)), max(1, count($queue_ids)));
        if (function_exists('cbia_set_stop_flag')) {
            cbia_set_stop_flag(false);
        }
        cbia_oldposts_log_run_start($run, $queue_ids);

        if ($manual_chunk_mode) {
            wp_send_json_success(array(
                'ids' => $queue_ids,
                'total' => count($queue_ids),
                'chunk_size' => $chunk_size,
                'state' => array(),
            ));
        }

        $state = array(
            'run_id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('cbia-oldposts-', true),
            'action' => $action,
            'status' => empty($queue_ids) ? 'completed' : 'queued',
            'message' => empty($queue_ids) ? 'empty_queue' : 'queued',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'finished_at' => empty($queue_ids) ? current_time('mysql') : '',
            'total' => count($queue_ids),
            'offset' => 0,
            'processed' => 0,
            'ok' => 0,
            'skipped' => 0,
            'fail' => 0,
            'chunk_size' => $chunk_size,
            'queue_ids' => $queue_ids,
            'last_chunk_ids' => array(),
            'processed_ids' => array(),
            'worker_heartbeat' => 0,
            'run' => $run,
            'ui_state' => cbia_oldposts_extract_ui_state_from_request($request),
        );
        cbia_oldposts_save_active_run_state($state);

        wp_clear_scheduled_hook('cbia_oldposts_process_background_run');
        cbia_oldposts_release_background_lock();
        if (!empty($queue_ids)) {
            cbia_oldposts_schedule_background_run(1);
        }

        wp_send_json_success(array(
            'ids' => $queue_ids,
            'total' => count($queue_ids),
            'chunk_size' => $chunk_size,
            'state' => cbia_oldposts_public_run_state($state),
        ));
    }
}

if (!function_exists('cbia_ajax_oldposts_run_chunk')) {
    function cbia_ajax_oldposts_run_chunk() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        if (!function_exists('cbia_oldposts_run_batch_with_overrides')) {
            wp_send_json_error(array('msg' => __('Old posts runner unavailable', 'cbiastudio-blogflow-ai')), 500);
        }
        if (function_exists('cbia_check_stop_flag') && cbia_check_stop_flag()) {
            wp_send_json_success(array(
                'stopped' => 1,
                'result' => array('processed' => 0, 'ok' => 0, 'skipped' => 0, 'fail' => 0),
            ));
        }

        $request = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
        $run = cbia_oldposts_build_run_args_from_request($request);
        $chunk_ids_raw = isset($request['run_post_ids']) ? $request['run_post_ids'] : ($request['chunk_ids'] ?? '');
        if (is_array($chunk_ids_raw)) {
            $chunk_ids = array_values(array_filter(array_map('absint', $chunk_ids_raw)));
        } else {
            $chunk_ids = function_exists('cbia_oldposts_parse_ids_csv')
                ? cbia_oldposts_parse_ids_csv((string)$chunk_ids_raw)
                : array_values(array_filter(array_map('absint', explode(',', (string)$chunk_ids_raw))));
        }
        if (empty($chunk_ids)) {
            wp_send_json_error(array('msg' => __('Chunk without valid IDs', 'cbiastudio-blogflow-ai')), 400);
        }

        $run['post_ids'] = $chunk_ids;
        $run['batch_size'] = count($chunk_ids);
        $run['suppress_batch_header'] = 1;
        $run['suppress_batch_footer'] = 1;
        if (function_exists('cbia_oldposts_log_message')) {
            /* translators: 1: comma-separated post IDs in the chunk, 2: chunk size. */
            cbia_oldposts_log_message(sprintf(__('TRAMO AJAX | ids=%1$s | size=%2$d', 'cbiastudio-blogflow-ai'), implode(',', $chunk_ids), count($chunk_ids)));
        }
        $result = cbia_oldposts_run_batch_with_overrides($run);
        if (function_exists('cbia_oldposts_log_message')) {
            cbia_oldposts_log_message(sprintf(
                /* translators: 1: comma-separated post IDs, 2: processed count, 3: ok count, 4: skipped count, 5: failed count. */
                __('FIN TRAMO AJAX | ids=%1$s | processed=%2$d | ok=%3$d | skip=%4$d | fail=%5$d', 'cbiastudio-blogflow-ai'),
                implode(',', $chunk_ids),
                (int)($result[0] ?? 0),
                (int)($result[1] ?? 0),
                (int)($result[2] ?? 0),
                (int)($result[3] ?? 0)
            ));
        }

        wp_send_json_success(array(
            'chunk_ids' => $chunk_ids,
            'stopped' => function_exists('cbia_check_stop_flag') && cbia_check_stop_flag() ? 1 : 0,
            'result' => array(
                'processed' => (int)($result[0] ?? 0),
                'ok' => (int)($result[1] ?? 0),
                'skipped' => (int)($result[2] ?? 0),
                'fail' => (int)($result[3] ?? 0),
            ),
        ));
    }
}

if (!function_exists('cbia_ajax_oldposts_get_run_state')) {
    function cbia_ajax_oldposts_get_run_state() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }

        $state = cbia_oldposts_get_active_run_state();
        if (cbia_oldposts_is_state_active($state) && (string)($state['status'] ?? '') === 'queued') {
            cbia_oldposts_schedule_background_run(1);
        }
        wp_send_json_success(array(
            'state' => cbia_oldposts_public_run_state($state),
        ));
    }
}

if (!function_exists('cbia_ajax_oldposts_tick_run')) {
    function cbia_ajax_oldposts_tick_run() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }

        @ignore_user_abort(true);
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Long-running oldposts background chunk.
        @set_time_limit(0);

        $state = cbia_oldposts_get_active_run_state();
        if (cbia_oldposts_is_state_active($state)) {
            cbia_oldposts_process_background_run();
            $state = cbia_oldposts_get_active_run_state();
        }

        wp_send_json_success(array(
            'state' => cbia_oldposts_public_run_state($state),
        ));
    }
}

if (!function_exists('cbia_oldposts_card_internal_count')) {
    function cbia_oldposts_card_internal_count($post_id = 0) {
        $post_id = absint($post_id);
        if ($post_id <= 0) return 0;
        $post_obj = get_post($post_id);
        if (!$post_obj) return 0;

        $content = (string)$post_obj->post_content;
        $internal_marker_count = 0;
        if (preg_match_all('/\[(?:IMAGE|IMAGEN)(?:_(?:PENDING|PENDIENTE))?\s*:\s*[^\]]+\]/i', $content, $m_markers)) {
            $internal_marker_count = count((array)$m_markers[0]);
        }
        $internal_managed_img_count = 0;
        if (preg_match_all('/<img\b[^>]*data-cbia-attach=("|\')\d+\1[^>]*>/i', $content, $m_internal_imgs)) {
            $internal_managed_img_count = count((array)$m_internal_imgs[0]);
        }
        // Source of truth for cards: current post content only.
        $internal_content_count = max($internal_marker_count, $internal_managed_img_count);
        return max(0, (int)$internal_content_count);
    }
}

if (!function_exists('cbia_ajax_oldposts_cards_snapshot')) {
    function cbia_ajax_oldposts_cards_snapshot() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        }

        $request = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
        $raw_ids = $request['post_ids'] ?? '';
        if (is_array($raw_ids)) {
            $ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));
        } else {
            $raw_ids = (string)$raw_ids;
            if (function_exists('cbia_oldposts_parse_ids_csv')) {
                $ids = cbia_oldposts_parse_ids_csv($raw_ids);
            } else {
                $ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $raw_ids)))));
            }
        }
        if (empty($ids)) {
            wp_send_json_success(array('rows' => array()));
        }

        $rows = array();
        foreach ($ids as $post_id) {
            $post_id = absint($post_id);
            if ($post_id <= 0) continue;
            if (!get_post($post_id)) continue;
            $thumb = get_the_post_thumbnail_url($post_id, 'medium');
            $rows[] = array(
                'id' => $post_id,
                'thumb' => $thumb ? esc_url_raw((string)$thumb) : '',
                'has_featured' => has_post_thumbnail($post_id) ? 1 : 0,
                'internal_count' => cbia_oldposts_card_internal_count($post_id),
                'seo_score' => (($score = get_post_meta($post_id, '_yoast_wpseo_linkdex', true)) !== '' && is_numeric($score)) ? max(0, min(100, (int)$score)) : null,
                'readability_score' => (($score = get_post_meta($post_id, '_yoast_wpseo_content_score', true)) !== '' && is_numeric($score)) ? max(0, min(100, (int)$score)) : null,
            );
        }

        wp_send_json_success(array('rows' => $rows));
    }
}

if (!function_exists('cbia_ajax_get_costes_log')) {
    function cbia_ajax_get_costes_log() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        nocache_headers();
        $service = null;
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) $service = $container->get('log_service');
        }
        if ($service && method_exists($service, 'get_log')) {
            wp_send_json_success($service->get_log('costes'));
        }
        if (function_exists('cbia_costes_log_get')) {
            wp_send_json_success(cbia_costes_log_get());
        }
        if (function_exists('cbia_get_log')) {
            wp_send_json_success(cbia_get_log());
        }
        wp_send_json_success(['log' => '', 'counter' => 0]);
    }
}

if (!function_exists('cbia_ajax_get_img_prompt')) {
    function cbia_ajax_get_img_prompt() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $type = isset($_POST['type']) ? sanitize_key((string)$_POST['type']) : '';
        $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;

        if ($type !== 'featured' && $type !== 'internal') {
            wp_send_json_error(['message' => 'Invalid parameters'], 400);
        }

        if ($post_id <= 0) {
            if (!function_exists('cbia_get_image_prompt_template')) {
                wp_send_json_error(['message' => 'Function unavailable'], 500);
            }
            $tpl = cbia_get_image_prompt_template($type, $idx);
            wp_send_json_success([
                'prompt' => (string)$tpl,
                'has_override' => 0,
            ]);
        }
        $title = get_the_title($post_id);
        $img_descs = function_exists('cbia_get_post_image_descs')
            ? cbia_get_post_image_descs($post_id)
            : array('featured' => array('desc' => '', 'section' => 'intro', 'attach_id' => 0), 'internal' => array());

        $desc = '';
        if ($type === 'featured') {
            $desc = (string)($img_descs['featured']['desc'] ?? '');
        } else {
            if ($idx < 1) $idx = 1;
            if (!empty($img_descs['internal'][$idx - 1]['desc'])) {
                $desc = (string)$img_descs['internal'][$idx - 1]['desc'];
            }
        }
        if ($desc === '') $desc = (string)$title;

        $has_override = function_exists('cbia_get_img_prompt_override')
            ? (cbia_get_img_prompt_override($post_id, $type, $idx) !== '')
            : false;

        $prompt = function_exists('cbia_build_image_prompt_for_post')
            ? cbia_build_image_prompt_for_post($post_id, $type, $desc, $idx)
            : '';

        wp_send_json_success([
            'prompt' => (string)$prompt,
            'has_override' => $has_override ? 1 : 0,
        ]);
    }
}

if (!function_exists('cbia_ajax_save_img_prompt_override')) {
    function cbia_ajax_save_img_prompt_override() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $type = isset($_POST['type']) ? sanitize_key((string)$_POST['type']) : '';
        $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash((string)$_POST['prompt'])) : '';

        if ($type !== 'featured' && $type !== 'internal') {
            wp_send_json_error(['message' => 'Invalid parameters'], 400);
        }

        $prompt = trim($prompt);
        if (function_exists('sanitize_textarea_field')) {
            $prompt = sanitize_textarea_field($prompt);
        }

        if ($post_id <= 0) {
            if (!function_exists('cbia_update_settings_merge')) {
                wp_send_json_error(['message' => 'Function unavailable'], 500);
            }
            $partial = [];
            if ($type === 'featured') {
                $partial['prompt_img_featured'] = $prompt;
            } else {
                if ($idx >= 1) {
                    $partial['prompt_img_internal_' . $idx] = $prompt;
                } else {
                    $partial['prompt_img_internal'] = $prompt;
                }
            }
            cbia_update_settings_merge($partial);
            wp_send_json_success(['ok' => 1]);
        }

        if (!function_exists('cbia_set_img_prompt_override')) {
            wp_send_json_error(['message' => 'Function unavailable'], 500);
        }

        cbia_set_img_prompt_override($post_id, $type, $idx, $prompt);
        wp_send_json_success(['ok' => 1]);
    }
}

if (!function_exists('cbia_ajax_regen_image')) {
    function cbia_ajax_regen_image() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $type = isset($_POST['type']) ? sanitize_key((string)$_POST['type']) : '';
        $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;

        if ($post_id <= 0 || ($type !== 'featured' && $type !== 'internal')) {
            wp_send_json_error(['message' => 'Invalid parameters'], 400);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post not found'], 404);
        }

        $title = get_the_title($post_id);
        $img_descs = function_exists('cbia_get_post_image_descs')
            ? cbia_get_post_image_descs($post_id)
            : array('featured' => array('desc' => '', 'section' => 'intro', 'attach_id' => 0), 'internal' => array());

        $desc = '';
        $section = 'body';
        $old_attach = 0;

        if ($type === 'featured') {
            $desc = (string)($img_descs['featured']['desc'] ?? '');
            $section = 'intro';
            $old_attach = (int)($img_descs['featured']['attach_id'] ?? 0);
        } else {
            if ($idx < 1) $idx = 1;
            if (!empty($img_descs['internal'][$idx - 1])) {
                $desc = (string)($img_descs['internal'][$idx - 1]['desc'] ?? '');
                $section = (string)($img_descs['internal'][$idx - 1]['section'] ?? 'body');
                $old_attach = (int)($img_descs['internal'][$idx - 1]['attach_id'] ?? 0);
            }
        }

        if ($desc === '') $desc = (string)$title;
        $prompt = function_exists('cbia_build_image_prompt_for_post')
            ? cbia_build_image_prompt_for_post($post_id, $type, $desc, $idx)
            : '';

        $alt = function_exists('cbia_sanitize_alt_from_desc') ? cbia_sanitize_alt_from_desc($desc) : '';
        if ($alt === '') $alt = function_exists('cbia_sanitize_alt_from_desc') ? cbia_sanitize_alt_from_desc($title) : '';

        list($ok, $attach_id, $model, $err, $meta) = cbia_generate_image_openai_with_prompt($prompt, $section, $title, $alt, $idx);
        $attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($meta) : array();
        if (!$ok || !$attach_id) {
            if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
                cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'image', 'prompt' => $prompt, 'section' => $section));
            }
            cbia_log(sprintf('Regenerate image: failed post %d (%s %d): %s', (int)$post_id, (string)$type, (int)$idx, (string)($err ?: '')), 'ERROR');
            wp_send_json_error(['message' => $err ?: 'Could not generate image'], 500);
        }

        if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
            cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'image', 'prompt' => $prompt, 'section' => $section));
        }

        // Registrar usage imagen (costes + agregados)
        if (function_exists('cbia_costes_record_usage')) {
            cbia_costes_record_usage($post_id, array_merge(is_array($meta) ? $meta : array(), array(
                'type' => 'image',
                'model' => (string)$model,
                'input_tokens' => (int)($meta['input_tokens'] ?? 0),
                'output_tokens' => (int)($meta['output_tokens'] ?? 0),
                'cached_input_tokens' => (int)($meta['cached_input_tokens'] ?? 0),
                'ok' => 1,
                'error' => '',
                'section' => (string)$section,
                'attach_id' => (int)$attach_id,
            )));
        }
        if (function_exists('cbia_image_append_call')) {
            cbia_image_append_call($post_id, $section, (string)$model, true, (int)$attach_id, '');
        }

        if ($type === 'featured') {
            set_post_thumbnail($post_id, (int)$attach_id);
            wp_update_post(array(
                'ID' => (int)$attach_id,
                'post_parent' => (int)$post_id,
            ));
            $img_descs['featured']['desc'] = (string)$desc;
            $img_descs['featured']['section'] = 'intro';
            $img_descs['featured']['attach_id'] = (int)$attach_id;
            cbia_set_post_image_descs($post_id, $img_descs);

            cbia_log(sprintf('Regenerate image: featured OK post %d attach_id=%d', (int)$post_id, (int)$attach_id), 'INFO');
            wp_send_json_success(['ok' => 1, 'attach_id' => (int)$attach_id]);
        }

        // Interna: reemplazar en contenido
        $html = (string)$post->post_content;
        $url = wp_get_attachment_url((int)$attach_id);
        $img_tag = cbia_build_content_img_tag_with_meta($url, $alt, $section, (int)$attach_id, $idx);

        $replaced = false;
        if ($old_attach > 0) {
            $replaced = cbia_replace_img_by_attach_id($html, $old_attach, $img_tag);
        }

        if (!$replaced) {
            $desc_clean = cbia_sanitize_alt_from_desc($desc);
            $token = '[IMAGE_PENDING: ' . $desc_clean . ']';
            $replaced = cbia_replace_pending_marker($html, $token, $img_tag);
            if (!$replaced) {
                $legacy_token = '[IMAGEN_PENDIENTE: ' . $desc_clean . ']';
                $replaced = cbia_replace_pending_marker($html, $legacy_token, $img_tag);
            }
        }

        if ($replaced) {
            $html = cbia_cleanup_post_html($html);
            wp_update_post(['ID' => $post_id, 'post_content' => $html]);
        }

        if (!empty($img_descs['internal'][$idx - 1])) {
            $img_descs['internal'][$idx - 1]['desc'] = (string)$desc;
            $img_descs['internal'][$idx - 1]['section'] = (string)$section;
            $img_descs['internal'][$idx - 1]['attach_id'] = (int)$attach_id;
        } else {
            $img_descs['internal'][] = array(
                'desc' => (string)$desc,
                'section' => (string)$section,
                'attach_id' => (int)$attach_id,
            );
        }
        cbia_set_post_image_descs($post_id, $img_descs);

        // Actualiza pendientes si existian
        $list_raw = get_post_meta($post_id, '_cbia_pending_images_list', true);
        $list = [];
        if ($list_raw) {
            $tmp = json_decode((string)$list_raw, true);
            if (is_array($tmp)) $list = $tmp;
        }
        if (!empty($list)) {
            foreach ($list as &$it) {
                if (!is_array($it)) continue;
                if (($it['desc'] ?? '') === cbia_sanitize_alt_from_desc($desc) && ($it['status'] ?? '') === 'pending') {
                    $it['status'] = 'done';
                    $it['attach_id'] = (int)$attach_id;
                    $it['last_error'] = '';
                    break;
                }
            }
            unset($it);
            update_post_meta($post_id, '_cbia_pending_images_list', wp_json_encode($list));
            $left = cbia_extract_pending_markers($html);
            update_post_meta($post_id, '_cbia_pending_images', (string)count($left));
        }

        cbia_log(sprintf('Regenerate image: internal OK post %d idx=%d attach_id=%d', (int)$post_id, (int)$idx, (int)$attach_id), 'INFO');
        wp_send_json_success([
            'ok' => 1,
            'attach_id' => (int)$attach_id,
            'replaced' => $replaced ? 1 : 0,
        ]);
    }
}

if (!function_exists('cbia_ajax_sync_models')) {
    function cbia_ajax_sync_models() {
        check_ajax_referer('cbia_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Unauthorized', 'cbiastudio-blogflow-ai')], 403);

        $provider = isset($_POST['provider']) ? sanitize_key((string)$_POST['provider']) : '';
        if ($provider === '') {
            if (function_exists('cbia_providers_get_current_provider')) {
                $provider = cbia_providers_get_current_provider();
            }
        }
        if ($provider === '') {
            wp_send_json_error(['message' => __('Invalid provider', 'cbiastudio-blogflow-ai')], 400);
        }

        if (!function_exists('cbia_providers_sync_models')) {
            wp_send_json_error(['message' => 'Sync unavailable'], 500);
        }

        $result = cbia_providers_sync_models($provider);
        if (!empty($result['ok'])) {
            if (function_exists('cbia_providers_get_model_sync_meta')) {
                $meta_all = cbia_providers_get_model_sync_meta();
                if (isset($meta_all[$provider]) && is_array($meta_all[$provider])) {
                    $result['meta'] = $meta_all[$provider];
                }
            }
            if (function_exists('cbia_log')) {
                cbia_log(sprintf('Model sync OK provider=%s count=%d source=%s', (string)$provider, (int)$result['count'], (string)$result['source']), 'INFO');
            }
            wp_send_json_success($result);
        }

        if (function_exists('cbia_log')) {
            cbia_log(sprintf('Model sync failed provider=%s err=%s', (string)$provider, (string)($result['error'] ?? '')), 'WARN');
        }
        wp_send_json_error(['message' => 'Could not sync models', 'result' => $result], 500);
    }
}
if (!function_exists('cbia_ajax_usage_recalculate_history')) {
    function cbia_ajax_usage_recalculate_history() {
        check_ajax_referer('cbia_usage_overview');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => __('Unauthorized', 'cbiastudio-blogflow-ai')), 403);
        if (!function_exists('cbia_costes_recalculate_history')) wp_send_json_error(array('message' => __('Recalculation unavailable.', 'cbiastudio-blogflow-ai')), 500);
        $apply = !empty($_POST['apply']);
        if ($apply && sanitize_text_field((string)($_POST['confirm'] ?? '')) !== 'RECALCULATE') wp_send_json_error(array('message' => __('Explicit confirmation is required.', 'cbiastudio-blogflow-ai')), 400);
        wp_send_json_success(cbia_costes_recalculate_history($apply));
    }
    add_action('wp_ajax_cbia_usage_recalculate_history', 'cbia_ajax_usage_recalculate_history');
}
