<?php
/**
 * CBIA - Old Posts (ActualizaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n avanzada de posts antiguos)
 * v3 (UI limpia + acciones sin duplicidad + Yoast por campos)
 *
 * UX:
 * - Se guardan "Acciones por defecto" (presets).
 * - En "EjecuciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n", por defecto usa esos presets SIN duplicar checkboxes.
 * - BotÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n/checkbox "Personalizar esta ejecuciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n" muestra overrides puntuales.
 *
 * Acciones soportadas:
 * - Nota "Actualizado el..." (sin tocar post_date)
 * - Yoast: metadesc / focuskw / title (por separado) + forzar
 * - Yoast reindex best-effort (si existe cbia_yoast_try_reindex_post)
 * - TÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­tulo con IA (SEO/CTR) + forzar
 * - Contenido con IA + forzar
 * - ImÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡genes: reset pendientes + forzar + opcional quitar destacada
 * - CategorÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­as (mapping plugin) + forzar
 * - Etiquetas (lista permitida plugin) + forzar
 *
 * Filtrado:
 * - MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s antiguos que X dÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­as (post_date_gmt)
 * - Rango de fechas (post_date_gmt)
 *
 * Archivo: includes/engine/oldposts.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================
   ===================== LOG INDEPENDIENTE ==================
   ========================================================= */
if (!function_exists('cbia_oldposts_log_key')) {
    function cbia_oldposts_log_key() { return 'cbia_oldposts_log'; }
}
if (!function_exists('cbia_oldposts_fix_mojibake')) {
    /**
     * Corrige mojibake comÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn en mensajes de log sin tocar la lÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³gica.
     */
    function cbia_oldposts_fix_mojibake($text) {
        $text = (string)$text;
        if ($text === '') return $text;

        $fixed = $text;
        if (function_exists('cbia_fix_mojibake')) {
            $fixed = cbia_fix_mojibake($fixed);
        }

        // Intento adicional: UTF-8 leÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­do como Latin-1/Windows-1252.
        if (function_exists('mb_convert_encoding') && preg_match('/[\x{00C3}\x{00C2}\x{00E2}]/u', $fixed)) {
            $try = @mb_convert_encoding($fixed, 'UTF-8', 'Windows-1252');
            if (is_string($try) && $try !== '') {
                $fixed = $try;
            }
        }

        return $fixed;
    }
}
if (!function_exists('cbia_oldposts_log_message')) {
    function cbia_oldposts_log_message($message) {
        $message = cbia_oldposts_fix_mojibake($message);
        // Evita duplicados consecutivos (muy ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºtil con mojibake/lineas repetidas).
        static $last_message = null;
        if ($last_message !== null && (string)$last_message === (string)$message) {
            return;
        }
        $last_message = (string)$message;
        if (function_exists('cbia_log')) {
            cbia_log((string)$message, 'INFO');
            return;
        }
        $log = get_option(cbia_oldposts_log_key(), '');
        $ts  = current_time('mysql');
        $log .= "[{$ts}] {$message}\n";
        if (strlen($log) > 250000) $log = substr($log, -250000);
        update_option(cbia_oldposts_log_key(), $log);
    }
}
if (!function_exists('cbia_oldposts_clear_log')) {
    function cbia_oldposts_clear_log() {
        if (function_exists('cbia_clear_log')) {
            cbia_clear_log();
        }
        delete_option(cbia_oldposts_log_key());
        wp_cache_delete(cbia_oldposts_log_key(), 'options');
    }
}
if (!function_exists('cbia_oldposts_get_log')) {
    function cbia_oldposts_get_log() {
        if (function_exists('cbia_get_log')) {
            $payload = cbia_get_log();
            $text = is_array($payload) ? (string)($payload['log'] ?? '') : (string)$payload;
            return function_exists('cbia_fix_mojibake') ? cbia_fix_mojibake($text) : $text;
        }
        $text = (string)get_option(cbia_oldposts_log_key(), '');
        return function_exists('cbia_fix_mojibake') ? cbia_fix_mojibake($text) : $text;
    }
}

/* =========================================================
   =================== STOP FLAG (fallback) =================
   ========================================================= */
if (!function_exists('cbia_stop_flag_key')) {
    function cbia_stop_flag_key() {
        if (defined('CBIA_OPTION_STOP')) return CBIA_OPTION_STOP;
        return 'cbia_stop_generation';
    }
}
if (!function_exists('cbia_set_stop_flag')) {
    function cbia_set_stop_flag($value = true) {
        update_option(cbia_stop_flag_key(), $value ? 1 : 0, false);
        wp_cache_delete(cbia_stop_flag_key(), 'options');
    }
}
if (!function_exists('cbia_check_stop_flag')) {
    function cbia_check_stop_flag() { return get_option(cbia_stop_flag_key(), 0) == 1; }
}

/* =========================================================
   ================ SETTINGS (independientes) ===============
   ========================================================= */
if (!function_exists('cbia_oldposts_settings_key')) {
    function cbia_oldposts_settings_key() { return 'cbia_oldposts_settings'; }
}
if (!function_exists('cbia_oldposts_get_settings')) {
    function cbia_oldposts_get_settings() {
        $s = get_option(cbia_oldposts_settings_key(), array());
        return is_array($s) ? $s : array();
    }
}
if (!function_exists('cbia_oldposts_register_settings')) {
    if (!function_exists('cbia_oldposts_sanitize_settings')) {
        function cbia_oldposts_sanitize_settings($value) {
            return is_array($value) ? $value : array();
        }
    }
    function cbia_oldposts_register_settings() {
        register_setting('cbia_oldposts_settings_group', cbia_oldposts_settings_key(), array(
            'type' => 'array',
            'sanitize_callback' => 'cbia_oldposts_sanitize_settings',
            'default' => array(),
        ));
    }
    add_action('admin_init', 'cbia_oldposts_register_settings');
}

/* =========================================================
   ==================== HELPERS VARIOS ======================
   ========================================================= */
if (!function_exists('cbia_oldposts_sanitize_ymd')) {
    function cbia_oldposts_sanitize_ymd($ymd) {
        $ymd = preg_replace('/[^0-9\-]/', '', (string)$ymd);
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $ymd)) return $ymd;
        return '';
    }
}
if (!function_exists('cbia_oldposts_parse_ids_csv')) {
    /**
     * Convierte "1,2, 3\n4" en [1,2,3,4]
     */
    function cbia_oldposts_parse_ids_csv($raw) {
        $raw = (string)$raw;
        if ($raw === '') return array();
        $raw = str_replace(array("\r", "\n", "\t", ";"), ',', $raw);
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $ids = array();
        foreach ($parts as $p) {
            $id = (int)$p;
            if ($id > 0) $ids[$id] = $id;
        }
        return array_values($ids);
    }
}
if (!function_exists('cbia_oldposts_normalize_title_text')) {
    function cbia_oldposts_normalize_title_text($title) {
        $title = wp_specialchars_decode((string)$title, ENT_QUOTES);
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        return trim((string)$title);
    }
}
if (!function_exists('cbia_oldposts_is_elementor_post')) {
    function cbia_oldposts_is_elementor_post($post_id) {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return false;
        $edit_mode = (string)get_post_meta($post_id, '_elementor_edit_mode', true);
        $data = get_post_meta($post_id, '_elementor_data', true);
        if ($edit_mode !== '') return true;
        if (is_string($data) && trim($data) !== '') return true;
        if (is_array($data) && !empty($data)) return true;
        return false;
    }
}
if (!function_exists('cbia_oldposts_remove_h1')) {
    function cbia_oldposts_remove_h1($html) {
        $html = (string)$html;
        $html2 = preg_replace('/<h1\b[^>]*>.*?<\/h1>\s*/is', '', $html, 1);
        return (string)$html2;
    }
}
if (!function_exists('cbia_oldposts_has_any_image_marker')) {
    function cbia_oldposts_has_any_image_marker($html) {
        return (bool)preg_match('/\[(IMAGE|IMAGEN)(?:_(?:PENDING|PENDIENTE))?\s*:\s*[^\]]+\]/i', (string)$html);
    }
}
if (!function_exists('cbia_oldposts_extract_image_markers_any')) {
    function cbia_oldposts_extract_image_markers_any($html) {
        $html = (string)$html;
        $markers = array();
        if (preg_match_all('/\[(?:IMAGE|IMAGEN)(?:_(?:PENDING|PENDIENTE))?\s*:\s*([^\]]+)\]/i', $html, $m)) {
            foreach ((array)$m[1] as $desc) {
                $desc = trim((string)$desc);
                if ($desc === '') continue;
                $markers[] = $desc;
            }
        }
        $markers = array_values(array_unique($markers));
        return $markers;
    }
}
if (!function_exists('cbia_oldposts_mark_all_as_pending')) {
    function cbia_oldposts_mark_all_as_pending($html) {
        $html = (string)$html;
        $html = preg_replace('/\[(?:IMAGE|IMAGEN)\s*:\s*([^\]]+)\]/i', '[IMAGE_PENDING: $1]', $html);
        return $html;
    }
}
if (!function_exists('cbia_oldposts_insert_missing_internal_markers')) {
    function cbia_oldposts_insert_missing_internal_markers($html, $title, $images_limit) {
        $html = (string)$html;
        $title = (string)$title;
        $images_limit = max(0, min(3, (int)$images_limit));
        if ($images_limit <= 0) {
            return $html;
        }
        if (function_exists('cbia_normalize_image_markers')) {
            $html = cbia_normalize_image_markers($html);
        }
        if (function_exists('cbia_force_insert_markers')) {
            $html = cbia_force_insert_markers($html, $title, $images_limit);
        }
        return cbia_oldposts_mark_all_as_pending($html);
    }
}
if (!function_exists('cbia_oldposts_enforce_pending_limit')) {
    /**
     * Enforce a hard cap of pending internal image markers in HTML.
     * Returns normalized HTML and provides the resulting pending list by reference.
     */
    function cbia_oldposts_enforce_pending_limit($html, $images_limit, &$pending_list = array()) {
        $html = (string)$html;
        $images_limit = max(0, min(3, (int)$images_limit));
        $pending_list = array();

        if ($images_limit <= 0) {
            $html = preg_replace('/\[(?:IMAGE|IMAGEN)(?:_(?:PENDING|PENDIENTE))?\s*:\s*[^\]]+\]/i', '', $html);
            return $html;
        }

        $kept = 0;
        $html = preg_replace_callback(
            '/\[(?:IMAGE|IMAGEN)(?:_(?:PENDING|PENDIENTE))?\s*:\s*([^\]]+)\]/i',
            function ($m) use (&$kept, $images_limit, &$pending_list) {
                if ($kept >= $images_limit) {
                    return '';
                }
                $desc = trim((string)($m[1] ?? ''));
                if ($desc === '') {
                    return '';
                }
                $kept++;
                $pending_list[] = $desc;
                return '[IMAGE_PENDING: ' . $desc . ']';
            },
            $html
        );

        $pending_list = array_values(array_filter(array_map('trim', (array)$pending_list)));
        return $html;
    }
}
if (!function_exists('cbia_oldposts_set_pending_images_meta')) {
    function cbia_oldposts_set_pending_images_meta($post_id, $pending_list) {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return;

        $pending_list = is_array($pending_list) ? $pending_list : array();
        $pending_list = array_values(array_unique(array_filter(array_map('trim', $pending_list))));

        $pending_count = count($pending_list);
        update_post_meta($post_id, '_cbia_pending_images', (string)$pending_count);
        update_post_meta($post_id, '_cbia_pending_images_list', wp_json_encode($pending_list));
        update_post_meta($post_id, '_cbia_oldposts_images_reset_at', current_time('mysql'));
    }
}

/* =========================================================
   ==================== HELPERS SEO/YOAST ===================
   ========================================================= */
if (!function_exists('cbia_oldposts_first_paragraph_text')) {
    function cbia_oldposts_first_paragraph_text($html) {
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', (string)$html, $m)) return wp_strip_all_tags($m[1]);
        return wp_strip_all_tags((string)$html);
    }
}
if (!function_exists('cbia_oldposts_generate_meta_description_fallback')) {
    function cbia_oldposts_generate_meta_description_fallback($title, $content) {
        $base = cbia_oldposts_first_paragraph_text((string)$content);
        $t = trim(wp_strip_all_tags((string)$title));
        if ($t !== '') {
            $pattern = '/^' . preg_quote($t, '/') . '\s*[:\-ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â]?\s*/iu';
            $base = preg_replace($pattern, '', $base);
        }
        $desc = trim(mb_substr((string)$base, 0, 155));
        if ($desc !== '' && !preg_match('/[.!?]$/u', $desc)) $desc .= '...';
        return $desc;
    }
}
if (!function_exists('cbia_oldposts_generate_focus_keyphrase_fallback')) {
    function cbia_oldposts_generate_focus_keyphrase_fallback($title) {
        $words = preg_split('/\s+/', wp_strip_all_tags((string)$title));
        return trim(implode(' ', array_slice((array)$words, 0, 4)));
    }
}
if (!function_exists('cbia_oldposts_generate_yoast_title_fallback')) {
    function cbia_oldposts_generate_yoast_title_fallback($title) {
        $t = trim(wp_strip_all_tags((string)$title));
        // Yoast title suele aceptar variables, pero aquÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­ dejamos un tÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­tulo simple.
        return $t;
    }
}

/**
 * Recalcular metas Yoast por campos:
 * - metadesc
 * - focuskw
 * - title (SEO title Yoast)
 */
if (!function_exists('cbia_oldposts_recalc_yoast_fields')) {
    function cbia_oldposts_recalc_yoast_fields($post_id, $do_metadesc=true, $do_focuskw=true, $do_title=true, $force=false) {
        $post = get_post($post_id);
        if (!$post) return false;

        $title   = cbia_oldposts_normalize_title_text(get_the_title($post_id));
        $content = (string)$post->post_content;

        $did = false;

        if ($do_metadesc) {
            $metadesc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            if ($force || $metadesc === '' || $metadesc === null) {
                if (function_exists('cbia_generate_meta_description')) {
                    $md = cbia_generate_meta_description($title, $content);
                } else {
                    $md = cbia_oldposts_generate_meta_description_fallback($title, $content);
                }
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $md);
                $did = true;
            }
        }

        if ($do_focuskw) {
            $focuskw  = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            if ($force || $focuskw === '' || $focuskw === null) {
                if (function_exists('cbia_generate_focus_keyphrase')) {
                    $fk = cbia_generate_focus_keyphrase($title, $content);
                } else {
                    $fk = cbia_oldposts_generate_focus_keyphrase_fallback($title);
                }
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $fk);
                $did = true;
            }
        }

        if ($do_title) {
            $yt = get_post_meta($post_id, '_yoast_wpseo_title', true);
            if ($force || $yt === '' || $yt === null) {
                // Si tienes una funciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n propia, ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºsala; si no, fallback
                if (function_exists('cbia_generate_yoast_title')) {
                    $new_yt = cbia_generate_yoast_title($title, $content);
                } else {
                    $new_yt = cbia_oldposts_generate_yoast_title_fallback($title);
                }
                update_post_meta($post_id, '_yoast_wpseo_title', $new_yt);
                $did = true;
            }
        }

        if ($did) {
            update_post_meta($post_id, '_cbia_oldposts_yoast_fields_refreshed', current_time('mysql'));
            clean_post_cache($post_id);

            // Best-effort hooks Yoast
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- External Yoast hook.
            do_action('wpseo_save_postdata', $post_id);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- External Yoast hook.
            do_action('wpseo_save_post', $post_id);
        }

        return $did;
    }
}

if (!function_exists('cbia_oldposts_yoast_plan')) {
    function cbia_oldposts_yoast_plan($post_id, $do_metadesc=true, $do_focuskw=true, $do_title=true, $force=false) {
        $plan = array(
            'selected'      => array(),
            'will_update'   => array(),
            'skip_existing' => array(),
            'force'         => !empty($force),
        );

        $fields = array(
            'metadesc' => array(
                'enabled' => !empty($do_metadesc),
                'meta'    => '_yoast_wpseo_metadesc',
            ),
            'focuskw' => array(
                'enabled' => !empty($do_focuskw),
                'meta'    => '_yoast_wpseo_focuskw',
            ),
            'title' => array(
                'enabled' => !empty($do_title),
                'meta'    => '_yoast_wpseo_title',
            ),
        );

        foreach ($fields as $label => $cfg) {
            if (empty($cfg['enabled'])) continue;
            $plan['selected'][] = $label;

            if (!empty($force)) {
                $plan['will_update'][] = $label;
                continue;
            }

            $current = get_post_meta($post_id, $cfg['meta'], true);
            if ($current === '' || $current === null) {
                $plan['will_update'][] = $label;
            } else {
                $plan['skip_existing'][] = $label;
            }
        }

        return $plan;
    }
}

/* =========================================================
   =================== NOTA "ACTUALIZADO" ===================
   ========================================================= */
if (!function_exists('cbia_oldposts_build_note_html')) {
    function cbia_oldposts_build_note_html($date_ymd) {
        $date_ymd = preg_replace('/[^0-9\-]/', '', (string)$date_ymd);
        if ($date_ymd === '') $date_ymd = current_time('Y-m-d');
        return '<p><em>' . esc_html__('Updated on', 'cbiastudio-blogflow-ai') . ' ' . esc_html($date_ymd) . '</em></p>' . "\n";
    }
}
if (!function_exists('cbia_oldposts_has_note')) {
    function cbia_oldposts_has_note($content) {
        return (bool)preg_match('/<p>\s*<em>\s*(?:updated\s+on|actualizado\s+el)\s+[0-9]{4}\-[0-9]{2}\-[0-9]{2}\s*<\/em>\s*<\/p>/i', (string)$content);
    }
}
if (!function_exists('cbia_oldposts_add_updated_note')) {
    function cbia_oldposts_add_updated_note($post_id, $date_ymd, $force=false) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') return false;

        $date_ymd = cbia_oldposts_sanitize_ymd($date_ymd);
        if ($date_ymd === '') $date_ymd = current_time('Y-m-d');

        $already = get_post_meta($post_id, '_cbia_updated_note_date', true);
        if (!$force && $already !== '') return 'skipped';

        $content = (string)$post->post_content;

        if (!$force && cbia_oldposts_has_note($content)) {
            update_post_meta($post_id, '_cbia_updated_note_date', $date_ymd);
            return 'skipped';
        }

        if ($force && cbia_oldposts_has_note($content)) {
            $new_note = cbia_oldposts_build_note_html($date_ymd);
            $new_content = preg_replace(
                '/<p>\s*<em>\s*(?:updated\s+on|actualizado\s+el)\s+[0-9]{4}\-[0-9]{2}\-[0-9]{2}\s*<\/em>\s*<\/p>\s*/i',
                $new_note,
                $content,
                1
            );
            wp_update_post(array('ID'=>$post_id, 'post_content'=>$new_content));
            update_post_meta($post_id, '_cbia_updated_note_date', $date_ymd);
            update_post_meta($post_id, '_cbia_oldposts_note_refreshed', current_time('mysql'));
            /* translators: 1: post ID, 2: date in Y-m-d format. */
            cbia_oldposts_log_message(sprintf(__('Update note (force) refreshed on post %1$d (%2$s).', 'cbiastudio-blogflow-ai'), $post_id, $date_ymd));
            return true;
        }

        $note = cbia_oldposts_build_note_html($date_ymd);
        $trimmed_content = trim((string)$content);
        $new  = $trimmed_content === '' ? $note : ($trimmed_content . "\n\n" . $note);

        wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => $new,
        ));

        update_post_meta($post_id, '_cbia_updated_note_date', $date_ymd);
        update_post_meta($post_id, '_cbia_oldposts_note_added', current_time('mysql'));
        /* translators: 1: post ID, 2: date in Y-m-d format. */
        cbia_oldposts_log_message(sprintf(__('Update note added on post %1$d (%2$s).', 'cbiastudio-blogflow-ai'), $post_id, $date_ymd));

        return true;
    }
}

/* =========================================================
   ================= CATEGORÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂAS / ETIQUETAS =================
   ========================================================= */
if (!function_exists('cbia_oldposts_parse_keywords_to_categories')) {
    function cbia_oldposts_parse_keywords_to_categories($raw) {
        if (function_exists('cbia_parse_keywords_to_categories')) return cbia_parse_keywords_to_categories($raw);

        $raw = (string)$raw;
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $map = array();

        foreach ((array)$lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, ':') === false) continue;
            list($cat, $rest) = array_map('trim', explode(':', $line, 2));
            if ($cat === '' || $rest === '') continue;

            $kws = array();
            foreach (explode(',', $rest) as $kw) {
                $kw = trim((string)$kw);
                if ($kw !== '') $kws[] = mb_strtolower($kw);
            }
            if (!empty($kws)) $map[$cat] = $kws;
        }
        return $map;
    }
}
if (!function_exists('cbia_oldposts_ensure_category_id')) {
    function cbia_oldposts_ensure_category_id($name) {
        if (function_exists('cbia_ensure_category_id')) return cbia_ensure_category_id($name);

        $name = trim((string)$name);
        if ($name === '') return 0;

        $term = get_term_by('name', $name, 'category');
        if ($term && !is_wp_error($term)) return (int)$term->term_id;

        $new_id = wp_create_category($name);
        return is_wp_error($new_id) ? 0 : (int)$new_id;
    }
}
if (!function_exists('cbia_oldposts_assign_categories_only')) {
    function cbia_oldposts_assign_categories_only($post_id, $title, $content_html, $force=false) {
        if (!function_exists('cbia_get_settings')) {
            cbia_oldposts_log_message(__("[WARN] cbia_get_settings() is unavailable. Dynamic categories cannot be applied.", 'cbiastudio-blogflow-ai'));
            return false;
        }

        if (!$force) {
            $done = get_post_meta($post_id, '_cbia_oldposts_categories_done', true);
            if ($done !== '') return 'skipped';
        }

        $s = cbia_get_settings();
        $default_cat = trim((string)($s['default_category'] ?? 'News'));
        if ($default_cat === '') $default_cat = 'News';

        $map_raw = (string)($s['keywords_to_categories'] ?? '');
        $map = cbia_oldposts_parse_keywords_to_categories($map_raw);

        $hay = mb_strtolower(wp_strip_all_tags((string)$title . ' ' . (string)$content_html));

        $picked = array();
        foreach ($map as $cat => $kws) {
            foreach ((array)$kws as $kw) {
                if ($kw === '') continue;
                if (mb_strpos($hay, (string)$kw) !== false) {
                    $picked[] = (string)$cat;
                    break;
                }
            }
            if (count($picked) >= 3) break;
        }

        if (empty($picked)) $picked = array($default_cat);

        $cat_ids = array();
        foreach ($picked as $cname) {
            $cid = cbia_oldposts_ensure_category_id($cname);
            if ($cid > 0) $cat_ids[] = $cid;
        }
        if (empty($cat_ids)) {
            $cid = cbia_oldposts_ensure_category_id($default_cat);
            if ($cid > 0) $cat_ids[] = $cid;
        }

        if (!empty($cat_ids)) {
            wp_set_post_categories($post_id, $cat_ids, false);
            update_post_meta($post_id, '_cbia_oldposts_categories_done', current_time('mysql'));
            return true;
        }

        return false;
    }
}
if (!function_exists('cbia_oldposts_assign_tags_only')) {
    function cbia_oldposts_assign_tags_only($post_id, $title, $content_html, $force=false) {
        if (!function_exists('cbia_get_settings')) {
            cbia_oldposts_log_message(__("[WARN] cbia_get_settings() is unavailable. Dynamic tags cannot be applied.", 'cbiastudio-blogflow-ai'));
            return false;
        }

        if (!$force) {
            $done = get_post_meta($post_id, '_cbia_oldposts_tags_done', true);
            if ($done !== '') return 'skipped';
        }

        $s = cbia_get_settings();
        $allowed = trim((string)($s['default_tags'] ?? ''));
        $allowed_tags = array();
        if ($allowed !== '') {
            foreach (explode(',', $allowed) as $t) {
                $t = trim((string)$t);
                if ($t !== '') $allowed_tags[] = $t;
            }
        }

        $hay = mb_strtolower(wp_strip_all_tags((string)$title . ' ' . (string)$content_html));

        $chosen_tags = array();
        if (!empty($allowed_tags)) {
            foreach ($allowed_tags as $tag) {
                $needle = mb_strtolower($tag);
                if ($needle !== '' && mb_strpos($hay, $needle) !== false) {
                    $chosen_tags[] = $tag;
                }
                if (count($chosen_tags) >= 7) break;
            }
            if (empty($chosen_tags)) {
                $chosen_tags = array_slice($allowed_tags, 0, 5);
            }
        }

        if (!empty($chosen_tags)) {
            wp_set_post_tags($post_id, $chosen_tags, false);
            update_post_meta($post_id, '_cbia_oldposts_tags_done', current_time('mysql'));
            return true;
        }

        return false;
    }
}

/* =========================================================
   ======================= IA: TÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­TULO =======================
   ========================================================= */
if (!function_exists('cbia_oldposts_ai_optimize_title')) {
    function cbia_oldposts_ai_optimize_title($post_id, $force=false) {
        $post = get_post($post_id);
        if (!$post) return false;

        if (!$force) {
            $done = get_post_meta($post_id, '_cbia_oldposts_title_done', true);
            if ($done !== '') return 'skipped';
        }

        if (!function_exists('cbia_openai_responses_call') || !function_exists('cbia_pick_model')) {
            cbia_oldposts_log_message(__("[ERROR] AI engine is missing (cbia_openai_responses_call / cbia_pick_model). Title cannot be optimized.", 'cbiastudio-blogflow-ai'));
            return false;
        }

        $old_title = cbia_oldposts_normalize_title_text(get_the_title($post_id));
        $content   = (string)$post->post_content;

        $prompt = "Optimize this title for SEO and CTR while preserving the same search intent and topic.\n".
                  "Return ONLY the final title, without quotes, lists, or explanations.\n\n".
                  "Current title: {$old_title}\n\n".
                  "Context (excerpt): ".mb_substr(wp_strip_all_tags($content), 0, 600);

        $provider = function_exists('cbia_get_current_provider_key') ? cbia_get_current_provider_key() : 'openai';
        $model = function_exists('cbia_get_text_model_for_provider')
            ? cbia_get_text_model_for_provider((string)$provider, cbia_pick_model())
            : cbia_pick_model();
        list($ok, $text, $usage, $model_used, $err, $raw) = cbia_openai_responses_call($prompt, 'OLDPOSTS_TITLE', 2);
        $attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($raw) : array();
        if (!$ok && !empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
            cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'seo', 'prompt' => $prompt));
        }

        if (!$ok) {
            /* translators: 1: post ID, 2: provider error message. */
            cbia_oldposts_log_message(sprintf(__('[ERROR] AI title failed on post %1$d: %2$s', 'cbiastudio-blogflow-ai'), $post_id, (string)$err));
            return false;
        }

        $new_title = trim(wp_strip_all_tags((string)$text));
        $new_title = preg_replace('/\s+/', ' ', $new_title);

        if ($new_title === '' || mb_strlen($new_title) < 12) {
            /* translators: %d is the post ID. */
            cbia_oldposts_log_message(sprintf(__("[WARN] AI returned an invalid title on post %d. Skipped.", 'cbiastudio-blogflow-ai'), $post_id));
            return false;
        }

        if (mb_strtolower($new_title) === mb_strtolower($old_title)) {
            update_post_meta($post_id, '_cbia_oldposts_title_done', current_time('mysql'));
            /* translators: %d is the post ID. */
            cbia_oldposts_log_message(sprintf(__("[INFO] AI title unchanged on post %d.", 'cbiastudio-blogflow-ai'), $post_id));
            return 'skipped';
        }

        wp_update_post(array(
            'ID'         => $post_id,
            'post_title' => $new_title,
        ));

        update_post_meta($post_id, '_cbia_oldposts_title_done', current_time('mysql'));
        update_post_meta($post_id, '_cbia_oldposts_title_old', $old_title);
        update_post_meta($post_id, '_cbia_oldposts_title_new', $new_title);

        if (function_exists('cbia_costes_record_usage')) {
            if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
                cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'seo', 'prompt' => $prompt));
            }
            cbia_costes_record_usage($post_id, array(
                'type' => 'seo',
                'model' => (string)$model_used,
                'input_tokens' => (int)($usage['input_tokens'] ?? 0),
                'output_tokens' => (int)($usage['output_tokens'] ?? 0),
                'cached_input_tokens' => (int)($usage['cached_input_tokens'] ?? 0),
                'cache_hit_tokens' => (int)($usage['cache_hit_tokens'] ?? 0),
                'cache_miss_tokens' => (int)($usage['cache_miss_tokens'] ?? 0),
                'cache_breakdown_available' => !empty($usage['cache_breakdown_available']) ? 1 : 0,
                'reasoning_tokens' => (int)($usage['reasoning_tokens'] ?? 0),
                'ok' => 1,
                'error' => '',
            ));
        }
        if (function_exists('cbia_usage_append_call')) {
            cbia_usage_append_call($post_id, 'oldposts_title', (string)$model_used, (array)$usage, array(
                'ok' => 1,
                'err' => '',
            ));
        }

        /* translators: 1: post ID, 2: old title, 3: new title. */
        cbia_oldposts_log_message(sprintf(__("[OK] Title updated on post %1\$d: '%2\$s' => '%3\$s'", 'cbiastudio-blogflow-ai'), $post_id, (string)$old_title, (string)$new_title));
        return true;
    }
}

/* =========================================================
   ======================= IA: CONTENIDO ====================
   ========================================================= */
if (!function_exists('cbia_oldposts_ai_regenerate_content')) {
    function cbia_oldposts_ai_regenerate_content($post_id, $images_limit=3, $force=false, $skip_images=false) {
        $post = get_post($post_id);
        if (!$post) return false;

        if (!$force) {
            $done = get_post_meta($post_id, '_cbia_oldposts_content_done', true);
            if ($done !== '') {
                /* translators: %d is the post ID. */
                cbia_oldposts_log_message(sprintf(__("[SKIP] AI content omitted for post %d | reason=already_processed | suggestion=enable 'Reprocess text'.", 'cbiastudio-blogflow-ai'), $post_id));
                return 'skipped';
            }
        }

        if (!function_exists('cbia_openai_responses_call') || !function_exists('cbia_build_prompt_for_title') || !function_exists('cbia_pick_model')) {
            cbia_oldposts_log_message(__("[ERROR] AI engine is missing (cbia_openai_responses_call/cbia_build_prompt_for_title/cbia_pick_model). Content cannot be regenerated.", 'cbiastudio-blogflow-ai'));
            return false;
        }

        $title = cbia_oldposts_normalize_title_text(get_the_title($post_id));
        $s = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $images_limit = max(1, min(3, (int)$images_limit));
        if ($images_limit <= 0) $images_limit = max(1, min(3, (int)($s['images_limit'] ?? 3)));

        if (cbia_check_stop_flag()) {
            /* translators: %d is the post ID. */
            cbia_oldposts_log_message(sprintf(__("[INFO] Stop flag is active: content will not be regenerated on post %d.", 'cbiastudio-blogflow-ai'), $post_id));
            return false;
        }

        $prompt = cbia_build_prompt_for_title($title);

        $provider = function_exists('cbia_get_current_provider_key') ? cbia_get_current_provider_key() : 'openai';
        $model = function_exists('cbia_get_text_model_for_provider')
            ? cbia_get_text_model_for_provider((string)$provider, cbia_pick_model())
            : cbia_pick_model();
        /* translators: 1: post ID, 2: text model, 3: internal images limit. */
        cbia_oldposts_log_message(sprintf(__('[INFO] AI content: calling OpenAI on post %1$d model=%2$s images_limit=%3$d...', 'cbiastudio-blogflow-ai'), $post_id, $model, $images_limit));

        list($ok, $text, $usage, $model_used, $err, $raw) = cbia_openai_responses_call($prompt, 'OLDPOSTS_CONTENT', 2);
        $attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($raw) : array();
        if (!$ok && !empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
            cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'text', 'prompt' => $prompt));
        }

        if (!$ok) {
            /* translators: 1: post ID, 2: provider error message. */
            cbia_oldposts_log_message(sprintf(__('[ERROR] AI content failed on post %1$d: %2$s', 'cbiastudio-blogflow-ai'), $post_id, $err));
            return false;
        }

        $html = (string)$text;
        if (function_exists('cbia_normalize_image_markers')) {
            $html = cbia_normalize_image_markers($html);
        }
        $html = cbia_oldposts_remove_h1($html);

        $pending_list = array();
        if (!empty($skip_images)) {
            // Modo "solo contenido": elimina cualquier marcador de imagen.
            $final_html = preg_replace('/\[(?:IMAGE|IMAGEN)(?:_(?:PENDING|PENDIENTE))?\s*:\s*[^\]]+\]/i', '', $html);
            $pending_list = array();
        } elseif (function_exists('cbia_replace_markers_with_pending')) {
            $final_html = cbia_replace_markers_with_pending($html, $images_limit, $pending_list);
        } else {
            $final_html = cbia_oldposts_mark_all_as_pending($html);
            $pending_list = cbia_oldposts_extract_image_markers_any($final_html);
        }
        if (empty($skip_images) && empty($pending_list) && $images_limit > 0) {
            $final_html = cbia_oldposts_insert_missing_internal_markers($final_html, $title, $images_limit);
            $pending_list = cbia_oldposts_extract_image_markers_any($final_html);
        }
        $final_html = cbia_oldposts_enforce_pending_limit($final_html, $images_limit, $pending_list);
        if (function_exists('cbia_cleanup_post_html')) {
            $final_html = cbia_cleanup_post_html($final_html);
        }

        $updated = wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => $final_html,
        ), true);
        if (is_wp_error($updated)) {
            /* translators: 1: post ID, 2: wp_update_post error message. */
            cbia_oldposts_log_message(sprintf(__('[ERROR] AI content could not be saved for post %1$d: %2$s', 'cbiastudio-blogflow-ai'), $post_id, $updated->get_error_message()));
            return false;
        }
        clean_post_cache($post_id);

        cbia_oldposts_set_pending_images_meta($post_id, $pending_list);
        if (empty($skip_images)) {
            delete_post_meta($post_id, '_cbia_oldposts_images_done');
            delete_post_meta($post_id, '_cbia_oldposts_images_content_done');
            delete_post_meta($post_id, '_cbia_oldposts_featured_done');
        }

        if (function_exists('cbia_costes_record_usage')) {
            if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
                cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'text', 'prompt' => $prompt));
            }
            cbia_costes_record_usage($post_id, array(
                'type' => 'text',
                'model' => (string)$model_used,
                'input_tokens' => (int)($usage['input_tokens'] ?? 0),
                'output_tokens' => (int)($usage['output_tokens'] ?? 0),
                'cached_input_tokens' => (int)($usage['cached_input_tokens'] ?? 0),
                'cache_hit_tokens' => (int)($usage['cache_hit_tokens'] ?? 0),
                'cache_miss_tokens' => (int)($usage['cache_miss_tokens'] ?? 0),
                'cache_breakdown_available' => !empty($usage['cache_breakdown_available']) ? 1 : 0,
                'reasoning_tokens' => (int)($usage['reasoning_tokens'] ?? 0),
                'ok' => 1,
                'error' => '',
            ));
        }
        if (function_exists('cbia_usage_append_call')) {
            cbia_usage_append_call($post_id, 'oldposts_content', (string)$model_used, (array)$usage, array(
                'ok' => 1,
                'err' => '',
            ));
        }

        update_post_meta($post_id, '_cbia_oldposts_content_done', current_time('mysql'));
        if (!empty($skip_images)) {
            update_post_meta($post_id, '_cbia_oldposts_content_noimg_done', current_time('mysql'));
            /* translators: %d is the post ID. */
            cbia_oldposts_log_message(sprintf(__("[OK] Content regenerated (without images) on post %d.", 'cbiastudio-blogflow-ai'), $post_id));
        } else {
            /* translators: 1: post ID, 2: number of pending internal images. */
            cbia_oldposts_log_message(sprintf(__('[OK] Content regenerated on post %1$d. Pending images=%2$d', 'cbiastudio-blogflow-ai'), $post_id, count($pending_list)));
        }

        if (cbia_oldposts_is_elementor_post($post_id)) {
            /* translators: %d is the post ID. */
            cbia_oldposts_log_message(sprintf(__('[WARN] Post %d uses Elementor. post_content was updated, but the visible output may still come from _elementor_data.', 'cbiastudio-blogflow-ai'), $post_id));
        }
        return true;
    }
}

/* =========================================================
   =================== IMÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂGENES: RESET ======================
   ========================================================= */
if (!function_exists('cbia_oldposts_images_reset_pending')) {
    function cbia_oldposts_images_reset_pending($post_id, $images_limit=3, $force=false, $clear_featured=false) {
        $post = get_post($post_id);
        if (!$post) return false;

        if (!$force) {
            $done = get_post_meta($post_id, '_cbia_oldposts_images_done', true);
            if ($done !== '') {
                /* translators: %d is the post ID. */
                cbia_oldposts_log_message(sprintf(__("[SKIP] Image reset omitted for post %d | reason=already_processed | suggestion=enable 'Reprocess images'.", 'cbiastudio-blogflow-ai'), $post_id));
                return 'skipped';
            }
        }

        $s = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $images_limit = max(1, min(3, (int)$images_limit));
        if ($images_limit <= 0) $images_limit = max(1, min(3, (int)($s['images_limit'] ?? 3)));

        $content = (string)$post->post_content;
        if (function_exists('cbia_normalize_image_markers')) {
            $content = cbia_normalize_image_markers($content);
        }

        if (!cbia_oldposts_has_any_image_marker($content)) {
            $content = cbia_oldposts_insert_missing_internal_markers($content, cbia_oldposts_normalize_title_text(get_the_title($post_id)), $images_limit);
        }

        if (!cbia_oldposts_has_any_image_marker($content)) {
            if ($clear_featured) {
                delete_post_thumbnail($post_id);
                update_post_meta($post_id, '_cbia_oldposts_images_done', current_time('mysql'));
                /* translators: %d is the post ID. */
                cbia_oldposts_log_message(sprintf(__("[OK] Image reset: no markers found, but featured image was removed on post %d.", 'cbiastudio-blogflow-ai'), $post_id));
                return true;
            }
            /* translators: %d is the post ID. */
            cbia_oldposts_log_message(sprintf(__("[INFO] Image reset: no markers found on post %d. SKIP.", 'cbiastudio-blogflow-ai'), $post_id));
            return 'skipped';
        }

        $new_content = cbia_oldposts_mark_all_as_pending($content);
        $new_content = cbia_oldposts_enforce_pending_limit($new_content, $images_limit, $pending_list);

        wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => $new_content,
        ));
        clean_post_cache($post_id);

        cbia_oldposts_set_pending_images_meta($post_id, $pending_list);

        if ($clear_featured) {
            delete_post_thumbnail($post_id);
        }

        update_post_meta($post_id, '_cbia_oldposts_images_done', current_time('mysql'));
        cbia_oldposts_log_message(
            sprintf(
                /* translators: 1: post ID, 2: pending markers count, 3: optional suffix about featured image removal. */
                __('[OK] Image reset on post %1$d. Pending=%2$d%3$s', 'cbiastudio-blogflow-ai'),
                $post_id,
                count($pending_list),
                $clear_featured ? " | featured removed" : ""
            )
        );

        return true;
    }
}

/* =========================================================
   ============ IMÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂGENES: SOLO CONTENIDO (reset) ============
   ========================================================= */
if (!function_exists('cbia_oldposts_images_reset_content_only')) {
    function cbia_oldposts_images_reset_content_only($post_id, $images_limit=3, $force=false) {
        $post = get_post($post_id);
        if (!$post) return false;

        if (!$force) {
            $done = get_post_meta($post_id, '_cbia_oldposts_images_content_done', true);
            if ($done !== '') {
                /* translators: %d is the post ID. */
                cbia_oldposts_log_message(sprintf(__("[SKIP] Internal images omitted for post %d | reason=already_processed | suggestion=enable 'Reprocess images'.", 'cbiastudio-blogflow-ai'), $post_id));
                return 'skipped';
            }
        }

        // Reutiliza el reset existente, pero sin tocar destacada.
        $r = cbia_oldposts_images_reset_pending($post_id, $images_limit, true, false);
        if ($r === true) {
            update_post_meta($post_id, '_cbia_oldposts_images_content_done', current_time('mysql'));
            /* translators: %d is the post ID. */
            cbia_oldposts_log_message(sprintf(__("[OK] Images (content only) reset on post %d.", 'cbiastudio-blogflow-ai'), $post_id));
            return true;
        }
        return $r;
    }
}

if (!function_exists('cbia_oldposts_generate_internal_images')) {
    function cbia_oldposts_generate_internal_images($post_id, $images_limit = 3) {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return 0;
        $images_limit = max(0, min(3, (int)$images_limit));
        if ($images_limit <= 0) return 0;
        if (!function_exists('cbia_fill_pending_images_for_post')) {
            cbia_oldposts_log_message(__("[ERROR] cbia_fill_pending_images_for_post() is unavailable. Internal images cannot be generated.", 'cbiastudio-blogflow-ai'));
            return 0;
        }
        $filled = (int) cbia_fill_pending_images_for_post($post_id, $images_limit, array(
            'fill_featured' => false,
        ));
        /* translators: 1: post ID, 2: generated internal images count. */
        cbia_oldposts_log_message(sprintf(__('[INFO] Internal images generated on post %1$d: %2$d', 'cbiastudio-blogflow-ai'), $post_id, (int)$filled));
        update_post_meta($post_id, '_cbia_oldposts_images_content_done', current_time('mysql'));
        return $filled;
    }
}

/* =========================================================
   ============ IMAGEN DESTACADA: SOLO DESTACADA ============
   ========================================================= */
if (!function_exists('cbia_oldposts_regenerate_featured_image')) {
    function cbia_oldposts_regenerate_featured_image($post_id, $force=false, $remove_old=false) {
        $post = get_post($post_id);
        if (!$post) return false;

        if (!$force) {
            $done = get_post_meta($post_id, '_cbia_oldposts_featured_done', true);
            if ($done !== '') {
                /* translators: %d is the post ID. */
                cbia_oldposts_log_message(sprintf(__("[SKIP] Featured image omitted for post %d | reason=already_processed | suggestion=enable 'Reprocess images'.", 'cbiastudio-blogflow-ai'), $post_id));
                return 'skipped';
            }
        }

        if (!function_exists('cbia_generate_image_openai')) {
            cbia_oldposts_log_message(__("[ERROR] cbia_generate_image_openai() is unavailable. Featured image cannot be regenerated.", 'cbiastudio-blogflow-ai'));
            return false;
        }

        $title = cbia_oldposts_normalize_title_text(get_the_title($post_id));
        $content = (string)$post->post_content;

        // Intentamos usar el primer marcador si existe; si no, usamos el tÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­tulo.
        $desc = $title;
        $markers = cbia_oldposts_extract_image_markers_any($content);
        if (!empty($markers) && !empty($markers[0])) {
            $desc = (string)$markers[0];
        }

        if ($remove_old) {
            delete_post_thumbnail($post_id);
        }

        list($ok, $attach_id, $model, $err, $meta) = cbia_generate_image_openai($desc, 'intro', $title);
        $attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($meta) : array();
        if (!$ok && !empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
            cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'image', 'prompt' => $desc, 'section' => 'intro'));
        }
        if ($ok && $attach_id) {
        if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
            cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'image', 'prompt' => $desc, 'section' => 'intro'));
        }
            set_post_thumbnail($post_id, (int)$attach_id);
            wp_update_post(array(
                'ID' => (int)$attach_id,
                'post_parent' => (int)$post_id,
            ));
            update_post_meta($post_id, '_cbia_oldposts_featured_done', current_time('mysql'));
            update_post_meta($post_id, '_cbia_oldposts_featured_attach_id', (int)$attach_id);
            /* translators: 1: post ID, 2: attachment ID. */
            cbia_oldposts_log_message(sprintf(__('[OK] Featured image regenerated on post %1$d (attach_id=%2$d).', 'cbiastudio-blogflow-ai'), $post_id, (int)$attach_id));

        if (function_exists('cbia_costes_record_usage')) {
            cbia_costes_record_usage($post_id, array_merge(is_array($meta) ? $meta : array(), array(
                'type' => 'image',
                'model' => (string)$model,
                'input_tokens' => (int)($meta['input_tokens'] ?? 0),
                'output_tokens' => (int)($meta['output_tokens'] ?? 0),
                'cached_input_tokens' => (int)($meta['cached_input_tokens'] ?? 0),
                'ok' => 1,
                'error' => '',
                'section' => 'intro',
                'attach_id' => (int)$attach_id,
            )));
        }

            if (function_exists('cbia_image_append_call')) {
                cbia_image_append_call($post_id, 'intro', (string)$model, true, (int)$attach_id, '');
            }

            return true;
        }

        /* translators: 1: post ID, 2: provider error message. */
        cbia_oldposts_log_message(sprintf(__('[ERROR] Featured image failed on post %1$d: %2$s', 'cbiastudio-blogflow-ai'), $post_id, (string)($err ?: '')));
        if (function_exists('cbia_costes_record_usage') && empty($attempts)) {
            cbia_costes_record_usage($post_id, array(
                'type' => 'image',
                'model' => (string)$model,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cached_input_tokens' => 0,
                'ok' => 0,
                'error' => (string)($err ?: ''),
                'section' => 'intro',
            ));
        }
        if (function_exists('cbia_image_append_call')) {
            cbia_image_append_call($post_id, 'intro', (string)$model, false, 0, (string)($err ?: ''));
        }
        return false;
    }
}

/* =========================================================
   =================== QUERY (por fechas) ===================
   ========================================================= */
if (!function_exists('cbia_oldposts_build_query_args')) {
    function cbia_oldposts_build_query_args($batch_size, $scope, $filter_mode, $older_than_days, $date_from, $date_to, $post_ids=array(), $category_id=0, $author_id=0, $dry_run=false) {
        $batch_size = max(1, min(200, (int)$batch_size));
        $scope = ($scope === 'plugin') ? 'plugin' : 'all';

        $args = array(
            'post_type'      => 'post',
            'posts_per_page' => $batch_size,
            'post_status'    => array('publish', 'future', 'draft', 'pending'),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $post_ids = is_array($post_ids) ? array_values(array_filter(array_map('intval', $post_ids))) : array();
        $category_id = (int)$category_id;
        $author_id = (int)$author_id;
        $dry_run = !empty($dry_run);

        if ($dry_run) {
            $args['fields'] = 'ids';
            $args['no_found_rows'] = true;
        }

        if (!empty($post_ids)) {
            // Si hay IDs concretos, priorizamos eso y evitamos sorpresas con fechas.
            $args['post__in'] = $post_ids;
            $args['orderby'] = 'post__in';
        }

        $filter_mode = in_array($filter_mode, array('all', 'range', 'older'), true)
            ? $filter_mode
            : 'all';

        if (empty($post_ids) && $filter_mode === 'range') {
            $from = cbia_oldposts_sanitize_ymd($date_from);
            $to   = cbia_oldposts_sanitize_ymd($date_to);

            $date_query = array();
            if ($from !== '') {
                $date_query[] = array(
                    'column'    => 'post_date_gmt',
                    'after'     => $from . ' 00:00:00',
                    'inclusive' => true,
                );
            }
            if ($to !== '') {
                $date_query[] = array(
                    'column'    => 'post_date_gmt',
                    'before'    => $to . ' 23:59:59',
                    'inclusive' => true,
                );
            }
            if (!empty($date_query)) $args['date_query'] = $date_query;

        } elseif (empty($post_ids) && $filter_mode === 'older') {
            $older_than_days = max(1, (int)$older_than_days);
            $cutoff_gmt = gmdate('Y-m-d H:i:s', time() - ($older_than_days * DAY_IN_SECONDS));
            $args['date_query'] = array(
                array(
                    'column'    => 'post_date_gmt',
                    'before'    => $cutoff_gmt,
                    'inclusive' => true,
                )
            );
        }

        if ($scope === 'plugin') {
            $args['meta_query'] = array(
                array('key' => '_cbia_created', 'value' => '1', 'compare' => '='),
            );
        }

        if (empty($post_ids) && $category_id > 0) {
            $args['cat'] = $category_id;
        }
        if (empty($post_ids) && $author_id > 0) {
            $args['author'] = $author_id;
        }

        return $args;
    }
}

if (!function_exists('cbia_oldposts_collect_runtime_overrides')) {
    function cbia_oldposts_collect_runtime_overrides($opts = array()) {
        $opts = is_array($opts) ? $opts : array();
        $out = array();

        $tpl = sanitize_key((string)($opts['run_post_length_variant'] ?? ''));
        if (in_array($tpl, array('short', 'medium', 'long'), true)) {
            $out['post_length_variant'] = $tpl;
        }

        $text_provider = sanitize_key((string)($opts['run_text_provider'] ?? ''));
        if ($text_provider !== '') {
            $out['text_provider'] = $text_provider;
        }
        $text_model = sanitize_text_field((string)($opts['run_text_model'] ?? ''));
        if ($text_model !== '') {
            $out['text_model'] = $text_model;
        }

        $image_provider = sanitize_key((string)($opts['run_image_provider'] ?? ''));
        if ($image_provider !== '') {
            $out['image_provider'] = $image_provider;
        }
        $image_model = sanitize_text_field((string)($opts['run_image_model'] ?? ''));
        if ($image_model !== '') {
            $out['image_model'] = $image_model;
        }

        return $out;
    }
}

if (!function_exists('cbia_oldposts_run_batch_with_overrides')) {
    function cbia_oldposts_run_batch_with_overrides($opts = array()) {
        $overrides = cbia_oldposts_collect_runtime_overrides($opts);
        $prev = isset($GLOBALS['cbia_runtime_settings_overrides']) && is_array($GLOBALS['cbia_runtime_settings_overrides'])
            ? $GLOBALS['cbia_runtime_settings_overrides']
            : null;
        $prev_disable_fallback = !empty($GLOBALS['cbia_disable_text_model_fallback']);

        if (!empty($overrides)) {
            $GLOBALS['cbia_runtime_settings_overrides'] = is_array($prev)
                ? array_replace_recursive($prev, $overrides)
                : $overrides;

            cbia_oldposts_log_message(sprintf(
                /* translators: 1: post length template, 2: text provider, 3: text model, 4: image provider, 5: image model. */
                __('RUNTIME profile | template=%1$s | text=%2$s/%3$s | image=%4$s/%5$s', 'cbiastudio-blogflow-ai'),
                (string)($overrides['post_length_variant'] ?? '-'),
                (string)($overrides['text_provider'] ?? '-'),
                (string)($overrides['text_model'] ?? '-'),
                (string)($overrides['image_provider'] ?? '-'),
                (string)($overrides['image_model'] ?? '-')
            ));
        }

        $GLOBALS['cbia_disable_text_model_fallback'] = 1;

        try {
            return cbia_oldposts_run_batch_v3($opts);
        } finally {
            if (is_array($prev)) {
                $GLOBALS['cbia_runtime_settings_overrides'] = $prev;
            } else {
                unset($GLOBALS['cbia_runtime_settings_overrides']);
            }
            if ($prev_disable_fallback) {
                $GLOBALS['cbia_disable_text_model_fallback'] = 1;
            } else {
                unset($GLOBALS['cbia_disable_text_model_fallback']);
            }
        }
    }
}

/* =========================================================
   ================== PROCESO POR LOTES (v3) =================
   ========================================================= */
if (!function_exists('cbia_oldposts_run_batch_v3')) {
    function cbia_oldposts_run_batch_v3($opts = array()) {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Needed for long-running oldposts batches.
            @set_time_limit(0);
        }
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Needed for long-running oldposts batches.
        @ini_set('max_execution_time', '0');

        $defaults = array(
            'batch_size'         => 20,
            'scope'              => 'all',
            'filter_mode'        => 'all',
            'older_than_days'    => 180,
            'date_from'          => '',
            'date_to'            => '',
            'images_limit'       => 3,
            'post_ids'           => array(),
            'category_id'        => 0,
            'author_id'          => 0,
            'dry_run'            => 0,

            'do_note'            => 0,
            'force_note'         => 0,
            'reprocess_text'     => 0,
            'reprocess_images'   => 0,
            'reprocess_meta'     => 0,

            // Yoast por campos
            'do_yoast_metadesc'  => 0,
            'do_yoast_focuskw'   => 0,
            'do_yoast_title'     => 0,
            'force_yoast'        => 0,

            'do_yoast_reindex'   => 0,

            'do_title'           => 0,
            'force_title'        => 0,

            'do_content'         => 0,
            'force_content'      => 0,
            // Variante: contenido sin tocar imÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡genes
            'do_content_no_images'    => 0,
            'force_content_no_images' => 0,

            'do_images_reset'    => 0,
            'force_images_reset' => 0,
            'clear_featured'     => 0,
            // Variante: solo imÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡genes del contenido (sin destacada)
            'do_images_content_only'    => 0,
            'force_images_content_only' => 0,

            // Solo imagen destacada
            'do_featured_only'   => 0,
            'force_featured_only'=> 0,
            'featured_remove_old'=> 0,

            'do_categories'      => 0,
            'force_categories'   => 0,

            'do_tags'            => 0,
            'force_tags'         => 0,
            'suppress_batch_header' => 0,
            'suppress_batch_footer' => 0,
        );
        $opts = array_merge($defaults, is_array($opts) ? $opts : array());

        $batch_size      = max(1, min(200, (int)$opts['batch_size']));
        $scope           = ($opts['scope'] === 'plugin') ? 'plugin' : 'all';
        $filter_mode     = in_array(($opts['filter_mode'] ?? ''), array('all', 'range', 'older'), true)
            ? (string)$opts['filter_mode']
            : 'all';
        $older_than_days = max(1, (int)$opts['older_than_days']);
        $date_from       = (string)$opts['date_from'];
        $date_to         = (string)$opts['date_to'];
        $images_limit    = max(1, min(3, (int)$opts['images_limit']));
        $post_ids        = is_array($opts['post_ids']) ? $opts['post_ids'] : cbia_oldposts_parse_ids_csv($opts['post_ids'] ?? '');
        $post_ids        = array_values(array_filter(array_map('intval', $post_ids)));
        $category_id     = (int)($opts['category_id'] ?? 0);
        $author_id       = (int)($opts['author_id'] ?? 0);
        $dry_run         = !empty($opts['dry_run']) ? 1 : 0;

        $date_ymd = current_time('Y-m-d');
        $log_batch_header = empty($opts['suppress_batch_header']);
        $log_batch_footer = empty($opts['suppress_batch_footer']);

        $ids_txt = !empty($post_ids) ? implode(',', array_slice($post_ids, 0, 20)) : '';
        if ($ids_txt !== '' && count($post_ids) > 20) $ids_txt .= ',ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦';
        if ($log_batch_header) {
        cbia_oldposts_log_message(
            sprintf(
                /* translators: 1: batch size, 2: scope, 3: filter mode, 4: older-than days, 5: from date, 6: to date, 7: images limit, 8: selected IDs summary, 9: category ID, 10: author ID, 11: dry-run flag. */
                __('START v3 | batch=%1$d | scope=%2$s | filter=%3$s | older_than_days=%4$d | from=%5$s | to=%6$s | images_limit=%7$d | ids=%8$s | category=%9$d | author=%10$d | dry_run=%11$s', 'cbiastudio-blogflow-ai'),
                $batch_size,
                $scope,
                $filter_mode,
                $older_than_days,
                $date_from,
                $date_to,
                $images_limit,
                (!empty($post_ids) ? $ids_txt : '(auto)'),
                $category_id,
                $author_id,
                ($dry_run ? 'YES' : 'NO')
            )
        );

        cbia_oldposts_log_message(
            sprintf(
                /* translators: 1-25 are YES/NO flags for selected actions and force modes in oldposts execution. */
                __('ACTIONS | note=%1$s(force=%2$s) | yoast(metadesc=%3$s,focuskw=%4$s,title=%5$s,force=%6$s) | yoast_reindex=%7$s | titleIA=%8$s(force=%9$s) | contentIA=%10$s(force=%11$s) | contentIA_noimg=%12$s(force=%13$s) | images_reset=%14$s(force=%15$s,clear_featured=%16$s) | images_content_only=%17$s(force=%18$s) | featured_only=%19$s(force=%20$s,remove_old=%21$s) | categories=%22$s(force=%23$s) | tags=%24$s(force=%25$s)', 'cbiastudio-blogflow-ai'),
                (!empty($opts['do_note']) ? 'YES' : 'NO'),
                (!empty($opts['force_note']) ? 'YES' : 'NO'),
                (!empty($opts['do_yoast_metadesc']) ? 'YES' : 'NO'),
                (!empty($opts['do_yoast_focuskw']) ? 'YES' : 'NO'),
                (!empty($opts['do_yoast_title']) ? 'YES' : 'NO'),
                (!empty($opts['force_yoast']) ? 'YES' : 'NO'),
                (!empty($opts['do_yoast_reindex']) ? 'YES' : 'NO'),
                (!empty($opts['do_title']) ? 'YES' : 'NO'),
                (!empty($opts['force_title']) ? 'YES' : 'NO'),
                (!empty($opts['do_content']) ? 'YES' : 'NO'),
                (!empty($opts['force_content']) ? 'YES' : 'NO'),
                (!empty($opts['do_content_no_images']) ? 'YES' : 'NO'),
                (!empty($opts['force_content_no_images']) ? 'YES' : 'NO'),
                (!empty($opts['do_images_reset']) ? 'YES' : 'NO'),
                (!empty($opts['force_images_reset']) ? 'YES' : 'NO'),
                (!empty($opts['clear_featured']) ? 'YES' : 'NO'),
                (!empty($opts['do_images_content_only']) ? 'YES' : 'NO'),
                (!empty($opts['force_images_content_only']) ? 'YES' : 'NO'),
                (!empty($opts['do_featured_only']) ? 'YES' : 'NO'),
                (!empty($opts['force_featured_only']) ? 'YES' : 'NO'),
                (!empty($opts['featured_remove_old']) ? 'YES' : 'NO'),
                (!empty($opts['do_categories']) ? 'YES' : 'NO'),
                (!empty($opts['force_categories']) ? 'YES' : 'NO'),
                (!empty($opts['do_tags']) ? 'YES' : 'NO'),
                (!empty($opts['force_tags']) ? 'YES' : 'NO')
            )
        );

        if (!empty($post_ids)) {
            cbia_oldposts_log_message(__("NOTE: specific IDs were provided. Date/category/author filters are ignored.", 'cbiastudio-blogflow-ai'));
        }
        }

        $args = cbia_oldposts_build_query_args($batch_size, $scope, $filter_mode, $older_than_days, $date_from, $date_to, $post_ids, $category_id, $author_id, $dry_run);

        $q = new WP_Query($args);
        if (!$q->have_posts()) {
            cbia_oldposts_log_message(__("No posts matched the current filters.", 'cbiastudio-blogflow-ai'));
            return array(0,0,0,0); // processed, ok, skipped, fail
        }

        if (!empty($dry_run)) {
            $ids = is_array($q->posts) ? $q->posts : array();
            $count = count($ids);
            /* translators: %d is the number of posts matching the dry-run query. */
            cbia_oldposts_log_message(sprintf(__("DRY RUN: %d posts would be processed (no changes).", 'cbiastudio-blogflow-ai'), $count));

            $max_list = min(20, $count);
            for ($i = 0; $i < $max_list; $i++) {
                $pid = (int)$ids[$i];
                $t = cbia_oldposts_normalize_title_text(get_the_title($pid));
                cbia_oldposts_log_message("DRY RUN: post {$pid} | '" . (string)$t . "'");
            }

            // Coste aproximado si hay acciones IA
            $needs_ai = (!empty($opts['do_content']) || !empty($opts['do_title']) || !empty($opts['do_content_no_images']));
            if ($needs_ai && function_exists('cbia_costes_estimate_for_post')) {
                $cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
                $cbia_settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
                $sum_est = 0.0;
                foreach ($ids as $pid) {
                    $est = cbia_costes_estimate_for_post((int)$pid, $cost_settings, $cbia_settings);
                    if ($est !== null) $sum_est += (float)$est;
                }
                /* translators: %s is the estimated total cost formatted as decimal text. */
                cbia_oldposts_log_message(sprintf(__("DRY RUN: estimated AI cost (approx) ~ %s EUR", 'cbiastudio-blogflow-ai'), number_format((float)$sum_est, 4, '.', ',')));
            }

            return array($count, 0, $count, 0);
        }

        $processed=0; $ok=0; $sk=0; $fail=0;

        while ($q->have_posts()) {
            $q->the_post();
            $pid = get_the_ID();
            $processed++;

            if (cbia_check_stop_flag()) {
                cbia_oldposts_log_message(__("Stopped by user during batch processing.", 'cbiastudio-blogflow-ai'));
                break;
            }

            $post = get_post($pid);
            if (!$post) { $fail++; continue; }

            $title   = cbia_oldposts_normalize_title_text(get_the_title($pid));
            $content = (string)$post->post_content;

            cbia_oldposts_log_message("---- Post {$pid} | '{$title}' ----");

            $did_any = false;
            $did_fail = false;
            $did_skip_all = true;
            $failed_actions = array();
            $yoast_scores_done = false;
            $content_refreshed_with_images = false;

            // 1) TÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­TULO (IA)
            if (!empty($opts['do_title'])) {
                $r = cbia_oldposts_ai_optimize_title($pid, !empty($opts['force_title']));
                if ($r === true) { $did_any = true; $did_skip_all=false; }
                elseif ($r === 'skipped') { /* */ }
                else { $did_fail = true; $failed_actions[] = 'AI title'; }
                $title = cbia_oldposts_normalize_title_text(get_the_title($pid));
            }

            // 2) CONTENIDO (IA)
            if (!empty($opts['do_content'])) {
                $r = cbia_oldposts_ai_regenerate_content($pid, $images_limit, !empty($opts['force_content']));
                if ($r === true) {
                    $did_any = true; $did_skip_all=false;
                    $content_refreshed_with_images = true;
                    $post = get_post($pid);
                    $content = $post ? (string)$post->post_content : $content;
                } elseif ($r === 'skipped') {
                    // no
                } else {
                    $did_fail = true;
                    $failed_actions[] = 'AI content';
                }
            }

            // 2.1) CONTENIDO (IA) SIN IMÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­GENES
            if (!empty($opts['do_content_no_images'])) {
                $r = cbia_oldposts_ai_regenerate_content($pid, $images_limit, !empty($opts['force_content_no_images']), true);
                if ($r === true) {
                    $did_any = true; $did_skip_all=false;
                    $post = get_post($pid);
                    $content = $post ? (string)$post->post_content : $content;
                } elseif ($r === 'skipped') {
                    // no
                } else {
                    $did_fail = true;
                    $failed_actions[] = 'AI content without images';
                }
            }

            // 3) NOTA
            if (!empty($opts['do_note'])) {
                $r = cbia_oldposts_add_updated_note($pid, $date_ymd, !empty($opts['force_note']));
                if ($r === true) {
                    $did_any = true; $did_skip_all=false;
                    $post = get_post($pid);
                    $content = $post ? (string)$post->post_content : $content;
                } elseif ($r === 'skipped') {
                    // no
                } else {
                    $did_fail = true;
                    $failed_actions[] = 'update note';
                }
            }

            // 4) IMÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­GENES reset
            if (!empty($opts['do_images_reset'])) {
                $r = cbia_oldposts_images_reset_pending($pid, $images_limit, !empty($opts['force_images_reset']), !empty($opts['clear_featured']));
                if ($r === true) {
                    $did_any = true; $did_skip_all=false;
                    $post = get_post($pid);
                    $content = $post ? (string)$post->post_content : $content;
                } elseif ($r === 'skipped') {
                    // no
                } else {
                    $did_fail = true;
                    $failed_actions[] = 'prepare images for regeneration';
                }
            }

            // 4.1) IMÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂGENES: solo contenido (sin tocar destacada)
            if (!empty($opts['do_images_content_only'])) {
                if ($content_refreshed_with_images) {
                    /* translators: %d is the post ID. */
                    cbia_oldposts_log_message(sprintf(__("[INFO] Internal images will use the freshly regenerated content for post %d.", 'cbiastudio-blogflow-ai'), $pid));
                    $filled = cbia_oldposts_generate_internal_images($pid, $images_limit);
                    if ($filled > 0) {
                        $did_any = true; $did_skip_all=false;
                    } else {
                        $did_fail = true;
                        $failed_actions[] = 'internal images';
                    }
                    $post = get_post($pid);
                    $content = $post ? (string)$post->post_content : $content;
                } else {
                    $r = cbia_oldposts_images_reset_content_only($pid, $images_limit, !empty($opts['force_images_content_only']));
                    if ($r === true) {
                        $did_any = true; $did_skip_all=false;
                        $post = get_post($pid);
                        $content = $post ? (string)$post->post_content : $content;
                        $filled = cbia_oldposts_generate_internal_images($pid, $images_limit);
                        if ($filled > 0) {
                            $did_any = true; $did_skip_all=false;
                            $post = get_post($pid);
                            $content = $post ? (string)$post->post_content : $content;
                        }
                    } elseif ($r === 'skipped') {
                        // no
                    } else {
                        $did_fail = true;
                        $failed_actions[] = 'internal images';
                    }
                }
            }

            // 4.2) IMAGEN DESTACADA: solo destacada
            if (!empty($opts['do_featured_only'])) {
                $force_featured = !empty($opts['force_featured_only']) || $content_refreshed_with_images;
                if ($content_refreshed_with_images && empty($opts['force_featured_only'])) {
                    /* translators: %d is the post ID. */
                    cbia_oldposts_log_message(sprintf(__("[INFO] Featured image will use the freshly regenerated content for post %d.", 'cbiastudio-blogflow-ai'), $pid));
                }
                $r = cbia_oldposts_regenerate_featured_image($pid, $force_featured, !empty($opts['featured_remove_old']));
                if ($r === true) {
                    $did_any = true; $did_skip_all=false;
                } elseif ($r === 'skipped') {
                    // no
                } else {
                    $did_fail = true;
                    $failed_actions[] = 'featured image';
                }
            }
            // 5) CATEGORÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­AS
            if (!empty($opts['do_categories'])) {
                $r = cbia_oldposts_assign_categories_only($pid, $title, $content, !empty($opts['force_categories']));
                if ($r === true) {
                    $did_any = true;
                    $did_skip_all=false;
                    /* translators: %d is the post ID. */
                    cbia_oldposts_log_message(sprintf(__("[OK] Categories applied on post %d.", 'cbiastudio-blogflow-ai'), $pid));
                } elseif ($r === 'skipped') {
                    /* */ 
                } else {
                    /* translators: %d is the post ID. */
                    cbia_oldposts_log_message(sprintf(__("[WARN] Categories were not applied on post %d.", 'cbiastudio-blogflow-ai'), $pid));
                }
            }

            // 6) ETIQUETAS
            if (!empty($opts['do_tags'])) {
                $r = cbia_oldposts_assign_tags_only($pid, $title, $content, !empty($opts['force_tags']));
                if ($r === true) {
                    $did_any = true;
                    $did_skip_all=false;
                    /* translators: %d is the post ID. */
                    cbia_oldposts_log_message(sprintf(__("[OK] Tags applied on post %d.", 'cbiastudio-blogflow-ai'), $pid));
                } elseif ($r === 'skipped') {
                    /* */ 
                } else {
                    /* translators: %d is the post ID. */
                    cbia_oldposts_log_message(sprintf(__("[WARN] Tags were not applied on post %d.", 'cbiastudio-blogflow-ai'), $pid));
                }
            }

            // 7) YOAST CAMPOS
            $do_any_yoast = (!empty($opts['do_yoast_metadesc']) || !empty($opts['do_yoast_focuskw']) || !empty($opts['do_yoast_title']));
            if ($do_any_yoast) {
                $yoast_plan = cbia_oldposts_yoast_plan(
                    $pid,
                    !empty($opts['do_yoast_metadesc']),
                    !empty($opts['do_yoast_focuskw']),
                    !empty($opts['do_yoast_title']),
                    !empty($opts['force_yoast'])
                );
                $r = cbia_oldposts_recalc_yoast_fields(
                    $pid,
                    !empty($opts['do_yoast_metadesc']),
                    !empty($opts['do_yoast_focuskw']),
                    !empty($opts['do_yoast_title']),
                    !empty($opts['force_yoast'])
                );
                if ($r) {
                    $did_any = true;
                    $did_skip_all=false;
                    $changed = !empty($yoast_plan['will_update']) ? implode(',', $yoast_plan['will_update']) : implode(',', $yoast_plan['selected']);
                    /* translators: 1: post ID, 2: comma-separated Yoast fields, 3: optional suffix with execution mode. */
                    cbia_oldposts_log_message(sprintf(__('[OK] Yoast updated on post %1$d | fields=%2$s%3$s', 'cbiastudio-blogflow-ai'), $pid, $changed, (!empty($yoast_plan['force']) ? " | mode=force" : "")));
                } else {
                    if (!empty($yoast_plan['skip_existing'])) {
                        /* translators: 1: post ID, 2: comma-separated Yoast fields that already existed. */
                        cbia_oldposts_log_message(sprintf(__('[SKIP] Yoast unchanged on post %1$d | already_exists=%2$s', 'cbiastudio-blogflow-ai'), $pid, implode(',', $yoast_plan['skip_existing'])));
                    } else {
                        /* translators: %d is the post ID. */
                        cbia_oldposts_log_message(sprintf(__("[SKIP] Yoast unchanged on post %d.", 'cbiastudio-blogflow-ai'), $pid));
                    }
                }
                if (function_exists('cbia_yoast_update_semaphore_scores')) {
                    list($did_scores, $seo_score, $read_score) = cbia_yoast_update_semaphore_scores($pid, true);
                    if ($seo_score !== null && $read_score !== null) {
                        $yoast_scores_done = true;
                        $did_any = true;
                        $did_skip_all = false;
                        /* translators: 1: post ID, 2: SEO score, 3: readability score, 4: optional suffix when existing scores are reused. */
                        cbia_oldposts_log_message(sprintf(__('[OK] Yoast traffic lights updated post %1$d | SEO=%2$d | READ=%3$d%4$s', 'cbiastudio-blogflow-ai'), $pid, (int)$seo_score, (int)$read_score, ($did_scores ? "" : " | using existing scores")));
                    }
                }
            }

            // 8) YOAST REINDEX best effort
            if (!empty($opts['do_yoast_reindex'])) {
                if (!$yoast_scores_done && function_exists('cbia_yoast_update_semaphore_scores')) {
                    list($did_scores, $seo_score, $read_score) = cbia_yoast_update_semaphore_scores($pid, true);
                    if ($seo_score !== null && $read_score !== null) {
                        $yoast_scores_done = true;
                        $did_any = true;
                        $did_skip_all = false;
                        /* translators: 1: post ID, 2: SEO score, 3: readability score, 4: optional suffix when existing scores are reused. */
                        cbia_oldposts_log_message(sprintf(__('[OK] Yoast traffic lights updated post %1$d | SEO=%2$d | READ=%3$d%4$s', 'cbiastudio-blogflow-ai'), $pid, (int)$seo_score, (int)$read_score, ($did_scores ? "" : " | using existing scores")));
                    }
                }
                if (function_exists('cbia_yoast_try_reindex_post')) {
                    $r = cbia_yoast_try_reindex_post($pid);
                    if ($r) {
                        $did_any = true;
                        $did_skip_all=false;
                        /* translators: %d is the post ID. */
                        cbia_oldposts_log_message(sprintf(__("[OK] Yoast reindex applied post %d.", 'cbiastudio-blogflow-ai'), $pid));
                    } else {
                        /* translators: %d is the post ID. */
                        cbia_oldposts_log_message(sprintf(__("[SKIP] Yoast reindex skipped post %d | reason=callback_false", 'cbiastudio-blogflow-ai'), $pid));
                    }
                } else {
                    /* translators: %d is the post ID. */
                    cbia_oldposts_log_message(sprintf(__("[SKIP] Yoast reindex skipped post %d | reason=function_not_available", 'cbiastudio-blogflow-ai'), $pid));
                }
            }

            if ($did_fail) {
                $fail++;
                $failed_actions = array_values(array_unique(array_filter($failed_actions)));
                $failed_summary = !empty($failed_actions) ? " | failed=" . implode(', ', $failed_actions) : '';
                if ($did_any) {
                    /* translators: 1: post ID, 2: optional failure summary suffix. */
                    cbia_oldposts_log_message(sprintf(__('RESULT post %1$d: PARTIAL FAILURE (some changes were applied, but some actions failed).%2$s', 'cbiastudio-blogflow-ai'), $pid, $failed_summary));
                } else {
                    /* translators: 1: post ID, 2: optional failure summary suffix. */
                    cbia_oldposts_log_message(sprintf(__('RESULT post %1$d: FULL FAILURE (no main action was completed).%2$s', 'cbiastudio-blogflow-ai'), $pid, $failed_summary));
                }
            } elseif ($did_skip_all && !$did_any) {
                $sk++;
                /* translators: %d is the post ID. */
                cbia_oldposts_log_message(sprintf(__("RESULT post %d: SKIP (nothing to do / already done).", 'cbiastudio-blogflow-ai'), $pid));
            } else {
                $ok++;
                /* translators: %d is the post ID. */
                cbia_oldposts_log_message(sprintf(__("RESULT post %d: OK (changes applied).", 'cbiastudio-blogflow-ai'), $pid));
            }
        }

        wp_reset_postdata();
        if ($log_batch_footer) {
            /* translators: 1: processed posts, 2: successful posts, 3: skipped posts, 4: failed posts. */
            cbia_oldposts_log_message(sprintf(__('END v3 | processed=%1$d | ok=%2$d | skipped=%3$d | fail=%4$d', 'cbiastudio-blogflow-ai'), $processed, $ok, $sk, $fail));
        }

        return array($processed, $ok, $sk, $fail);
    }
}

/* =========================================================
   ===================== UI TAB: OLDPOSTS ===================
   ========================================================= */
if (!function_exists('cbia_oldposts_handle_post')) {
    function cbia_oldposts_handle_post($settings) {
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) {
                $service = $container->get('oldposts_service');
                if ($service && method_exists($service, 'handle_post')) {
                    return $service->handle_post($settings);
                }
            }
        }
        if (!is_admin() || !current_user_can('manage_options')) return $settings;

        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD']))) : '';
        if ($request_method === 'POST') {
    
            // Guardar presets
            if (isset($_POST['cbia_form']) && $_POST['cbia_form'] === 'oldposts_settings') {
                if (check_admin_referer('cbia_oldposts_settings_nonce')) {
                    $u = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
    
                    $settings['batch_size']      = isset($u['batch_size']) ? max(1, min(200, (int)$u['batch_size'])) : (int)$settings['batch_size'];
                    $settings['scope']           = (!empty($u['scope']) && $u['scope'] === 'plugin') ? 'plugin' : 'all';
    
                    $settings['filter_mode']     = in_array(($u['filter_mode'] ?? ''), array('all', 'range', 'older'), true)
                        ? (string)$u['filter_mode']
                        : 'all';
                    $settings['older_than_days'] = isset($u['older_than_days']) ? max(1, (int)$u['older_than_days']) : (int)$settings['older_than_days'];
                    $settings['date_from']       = cbia_oldposts_sanitize_ymd($u['date_from'] ?? '');
                    $settings['date_to']         = cbia_oldposts_sanitize_ymd($u['date_to'] ?? '');
    
                    $settings['images_limit']    = isset($u['images_limit']) ? max(1, min(3, (int)$u['images_limit'])) : (int)$settings['images_limit'];
                    $settings['post_ids']        = isset($u['post_ids']) ? implode(',', cbia_oldposts_parse_ids_csv($u['post_ids'])) : (string)$settings['post_ids'];
                    $settings['category_id']     = isset($u['category_id']) ? (int)$u['category_id'] : (int)$settings['category_id'];
                    $settings['author_id']       = isset($u['author_id']) ? (int)$u['author_id'] : (int)$settings['author_id'];
                    $settings['dry_run']         = !empty($u['dry_run']) ? 1 : 0;
    
                    $settings['do_note']         = !empty($u['do_note']) ? 1 : 0;
                    $settings['force_note']      = !empty($u['force_note']) ? 1 : 0;
                    $settings['reprocess_text']  = !empty($u['reprocess_text']) ? 1 : 0;
                    $settings['reprocess_images']= !empty($u['reprocess_images']) ? 1 : 0;
                    $settings['reprocess_meta']  = !empty($u['reprocess_meta']) ? 1 : 0;
    
                    $settings['do_yoast_metadesc'] = !empty($u['do_yoast_metadesc']) ? 1 : 0;
                    $settings['do_yoast_focuskw']  = !empty($u['do_yoast_focuskw']) ? 1 : 0;
                    $settings['do_yoast_title']    = !empty($u['do_yoast_title']) ? 1 : 0;
                    $settings['force_yoast']       = !empty($u['force_yoast']) ? 1 : 0;
    
                    $settings['do_yoast_reindex']  = !empty($u['do_yoast_reindex']) ? 1 : 0;
    
                    $settings['do_title']        = !empty($u['do_title']) ? 1 : 0;
                    $settings['force_title']     = !empty($u['force_title']) ? 1 : 0;
    
                    $settings['do_content']      = !empty($u['do_content']) ? 1 : 0;
                    $settings['force_content']   = !empty($u['force_content']) ? 1 : 0;
                    $settings['do_content_no_images']    = !empty($u['do_content_no_images']) ? 1 : 0;
                    $settings['force_content_no_images'] = !empty($u['force_content_no_images']) ? 1 : 0;
    
                    $settings['do_images_reset']    = !empty($u['do_images_reset']) ? 1 : 0;
                    $settings['force_images_reset'] = !empty($u['force_images_reset']) ? 1 : 0;
                    $settings['clear_featured']     = !empty($u['clear_featured']) ? 1 : 0;
                    $settings['do_images_content_only']    = !empty($u['do_images_content_only']) ? 1 : 0;
                    $settings['force_images_content_only'] = !empty($u['force_images_content_only']) ? 1 : 0;
                    $settings['do_featured_only']          = !empty($u['do_featured_only']) ? 1 : 0;
                    $settings['force_featured_only']       = !empty($u['force_featured_only']) ? 1 : 0;
                    $settings['featured_remove_old']       = !empty($u['featured_remove_old']) ? 1 : 0;
    
                    $settings['do_categories']   = !empty($u['do_categories']) ? 1 : 0;
                    $settings['force_categories']= !empty($u['force_categories']) ? 1 : 0;
    
                    $settings['do_tags']         = !empty($u['do_tags']) ? 1 : 0;
                    $settings['force_tags']      = !empty($u['force_tags']) ? 1 : 0;
    
                    update_option(cbia_oldposts_settings_key(), $settings);
                    echo '<div class="notice notice-success is-dismissible"><p>ConfiguraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n guardada.</p></div>';
                }
            }
    
            // Acciones
            if (isset($_POST['cbia_form']) && $_POST['cbia_form'] === 'oldposts_actions') {
                if (check_admin_referer('cbia_oldposts_actions_nonce')) {
                    $u = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
                    $action = sanitize_text_field($u['cbia_action'] ?? '');
    
                    $run_actions = array(
                        'run_oldposts',
                        'run_quick_yoast_metas',
                        'run_quick_yoast_reindex',
                        'run_quick_featured',
                        'run_quick_images_only',
                        'run_quick_content_only',
                    );
    
                    // Base comÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn para ejecuciones (normal o rÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡pida)
                    $run_base = $settings;
                    if (in_array($action, $run_actions, true)) {
                        cbia_set_stop_flag(false);
                        $run_base['batch_size']      = isset($u['run_batch_size']) ? max(1, min(200, (int)$u['run_batch_size'])) : (int)$settings['batch_size'];
                        $run_base['scope']           = !empty($u['run_scope_plugin']) ? 'plugin' : 'all';
    
                        $run_base['filter_mode']     = in_array(($u['run_filter_mode'] ?? ''), array('all', 'range', 'older'), true)
                            ? (string)$u['run_filter_mode']
                            : 'all';
                        $run_base['older_than_days'] = isset($u['run_older_than_days']) ? max(1, (int)$u['run_older_than_days']) : (int)$settings['older_than_days'];
                        $run_base['date_from']       = cbia_oldposts_sanitize_ymd($u['run_date_from'] ?? $settings['date_from']);
                        $run_base['date_to']         = cbia_oldposts_sanitize_ymd($u['run_date_to'] ?? $settings['date_to']);
    
                        $run_base['images_limit']    = isset($u['run_images_limit']) ? max(1, min(3, (int)$u['run_images_limit'])) : (int)$settings['images_limit'];
    
                        // Filtros avanzados (acepta run_* y nombres simples)
                        $run_base['post_ids'] = cbia_oldposts_parse_ids_csv(
                            $u['run_post_ids']
                                ?? $u['post_ids']
                                ?? ($settings['post_ids'] ?? '')
                        );
                        $run_base['category_id'] = isset($u['run_category_id'])
                            ? (int)$u['run_category_id']
                            : (isset($u['category_id']) ? (int)$u['category_id'] : (int)($settings['category_id'] ?? 0));
                        $run_base['author_id'] = isset($u['run_author_id'])
                            ? (int)$u['run_author_id']
                            : (isset($u['author_id']) ? (int)$u['author_id'] : (int)($settings['author_id'] ?? 0));
                        $run_base['dry_run'] = !empty($u['run_dry_run']) || !empty($u['dry_run']) ? 1 : 0;

                        $run_base['run_post_length_variant'] = sanitize_key((string)($u['run_post_length_variant'] ?? ($settings['run_post_length_variant'] ?? 'medium')));
                        if (!in_array($run_base['run_post_length_variant'], array('short', 'medium', 'long'), true)) {
                            $run_base['run_post_length_variant'] = 'medium';
                        }

                        $run_base['run_text_provider'] = sanitize_key((string)($u['run_text_provider'] ?? ($settings['run_text_provider'] ?? 'openai')));
                        if ($run_base['run_text_provider'] === '') $run_base['run_text_provider'] = 'openai';
                        $run_base['run_text_model'] = sanitize_text_field((string)($u['run_text_model'] ?? ($settings['run_text_model'] ?? '')));

                        $run_base['run_image_provider'] = sanitize_key((string)($u['run_image_provider'] ?? ($settings['run_image_provider'] ?? 'openai')));
                        if (
                            $run_base['run_image_provider'] === ''
                            || (function_exists('cbia_providers_supports_image') && !cbia_providers_supports_image($run_base['run_image_provider']))
                        ) {
                            $run_base['run_image_provider'] = 'openai';
                        }
                        $run_base['run_image_model'] = sanitize_text_field((string)($u['run_image_model'] ?? ($settings['run_image_model'] ?? '')));

                        // Persist latest runtime profile for UI preload.
                        $settings['run_post_length_variant'] = $run_base['run_post_length_variant'];
                        $settings['run_text_provider'] = $run_base['run_text_provider'];
                        $settings['run_text_model'] = $run_base['run_text_model'];
                        $settings['run_image_provider'] = $run_base['run_image_provider'];
                        $settings['run_image_model'] = $run_base['run_image_model'];
                        update_option(cbia_oldposts_settings_key(), $settings);
                    }

                    if ($action === 'filter_oldposts_picker') {
                        $settings['batch_size']      = isset($u['run_batch_size']) ? max(1, min(200, (int)$u['run_batch_size'])) : (int)$settings['batch_size'];
                        $settings['filter_mode']     = in_array(($u['run_filter_mode'] ?? ''), array('all', 'range', 'older'), true)
                            ? (string)$u['run_filter_mode']
                            : 'all';
                        $settings['older_than_days'] = isset($u['run_older_than_days']) ? max(1, (int)$u['run_older_than_days']) : (int)$settings['older_than_days'];
                        $settings['date_from']       = cbia_oldposts_sanitize_ymd($u['run_date_from'] ?? $settings['date_from']);
                        $settings['date_to']         = cbia_oldposts_sanitize_ymd($u['run_date_to'] ?? $settings['date_to']);
                        $settings['images_limit']    = isset($u['run_images_limit']) ? max(1, min(3, (int)$u['run_images_limit'])) : (int)$settings['images_limit'];
                        $settings['post_ids']        = implode(',', cbia_oldposts_parse_ids_csv($u['run_post_ids'] ?? ($settings['post_ids'] ?? '')));
                        $settings['category_id']     = isset($u['run_category_id']) ? (int)$u['run_category_id'] : (int)$settings['category_id'];
                        $settings['author_id']       = isset($u['run_author_id']) ? (int)$u['run_author_id'] : (int)$settings['author_id'];

                        update_option(cbia_oldposts_settings_key(), $settings);
                        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Filter applied to the visual selector.', 'cbiastudio-blogflow-ai') . '</p></div>';
                    }
    
                    if ($action === 'run_oldposts') {
                        cbia_set_stop_flag(false);
    
                        // Base: presets
                        $run = $run_base;
    
                        // Overrides bÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡sicos siempre visibles
                        $run['batch_size']      = isset($u['run_batch_size']) ? max(1, min(200, (int)$u['run_batch_size'])) : (int)$settings['batch_size'];
                        $run['scope']           = !empty($u['run_scope_plugin']) ? 'plugin' : 'all';
    
                        $run['filter_mode']     = in_array(($u['run_filter_mode'] ?? ''), array('all', 'range', 'older'), true)
                            ? (string)$u['run_filter_mode']
                            : 'all';
                        $run['older_than_days'] = isset($u['run_older_than_days']) ? max(1, (int)$u['run_older_than_days']) : (int)$settings['older_than_days'];
                        $run['date_from']       = cbia_oldposts_sanitize_ymd($u['run_date_from'] ?? $settings['date_from']);
                        $run['date_to']         = cbia_oldposts_sanitize_ymd($u['run_date_to'] ?? $settings['date_to']);
    
                        $run['images_limit']    = isset($u['run_images_limit']) ? max(1, min(3, (int)$u['run_images_limit'])) : (int)$settings['images_limit'];
    
                        // Si el usuario activa personalizaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n, entonces sÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­ aplicamos overrides de acciones.
                        $custom = !empty($u['run_custom_actions']) ? true : false;

                            if ($custom) {
                                $run['do_note']           = !empty($u['run_do_note']) ? 1 : 0;
                                $run['reprocess_text']    = !empty($u['run_reprocess_text']) ? 1 : 0;
                                $run['reprocess_images']  = !empty($u['run_reprocess_images']) ? 1 : 0;
                                $run['reprocess_meta']    = !empty($u['run_reprocess_meta']) ? 1 : 0;
                                $run['force_note']        = !empty($run['do_note']) ? 1 : 0;

                                $run['do_yoast_metadesc'] = !empty($u['run_do_yoast_metadesc']) ? 1 : 0;
                                $run['do_yoast_focuskw']  = !empty($u['run_do_yoast_focuskw']) ? 1 : 0;
                                $run['do_yoast_title']    = !empty($u['run_do_yoast_title']) ? 1 : 0;
                                $run['force_yoast']       = (!empty($run['do_yoast_metadesc']) || !empty($run['do_yoast_focuskw']) || !empty($run['do_yoast_title'])) ? 1 : 0;

                                $run['do_yoast_reindex']  = !empty($u['run_do_yoast_reindex']) ? 1 : 0;

                                $run['do_title']          = !empty($u['run_do_title']) ? 1 : 0;
                                $run['force_title']       = !empty($run['do_title']) ? 1 : 0;

                                $run['do_content']        = !empty($u['run_do_content']) ? 1 : 0;
                                $run['force_content']     = !empty($run['do_content']) ? 1 : 0;
                                $run['do_content_no_images']    = !empty($u['run_do_content_no_images']) ? 1 : 0;
                                $run['force_content_no_images'] = !empty($run['do_content_no_images']) ? 1 : 0;

                                $run['do_images_reset']    = !empty($u['run_do_images_reset']) ? 1 : 0;
                                $run['force_images_reset'] = !empty($run['do_images_reset']) ? 1 : 0;
                                $run['clear_featured']     = !empty($u['run_clear_featured']) ? 1 : 0;
                                $run['do_images_content_only']    = !empty($u['run_do_images_content_only']) ? 1 : 0;
                                $run['force_images_content_only'] = !empty($run['do_images_content_only']) ? 1 : 0;
                                $run['do_featured_only']          = !empty($u['run_do_featured_only']) ? 1 : 0;
                                $run['force_featured_only']       = !empty($run['do_featured_only']) ? 1 : 0;
                                $run['featured_remove_old']       = !empty($u['run_featured_remove_old']) ? 1 : 0;

                                $run['do_categories']     = !empty($u['run_do_categories']) ? 1 : 0;
                                $run['force_categories']  = !empty($run['do_categories']) ? 1 : 0;

                                $run['do_tags']           = !empty($u['run_do_tags']) ? 1 : 0;
                                $run['force_tags']        = !empty($run['do_tags']) ? 1 : 0;
                            }
    
                        if (function_exists('cbia_cap_enabled') && !cbia_cap_enabled('internal_images')) {
                            $run['do_images_reset'] = 0;
                            $run['force_images_reset'] = 0;
                            $run['do_images_content_only'] = 0;
                            $run['force_images_content_only'] = 0;
                            $run['images_limit'] = 1;
                        }

                        cbia_oldposts_run_batch_with_overrides($run);
    
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Batch executed. Check the log.', 'cbiastudio-blogflow-ai') . '</p></div>';
                    }
    
                    // Acciones rÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡pidas (sobrescriben acciones, respetan filtros)
                    if (in_array($action, $run_actions, true) && $action !== 'run_oldposts') {
                        $run = $run_base;
    
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
                        foreach ($action_keys as $k) { $run[$k] = 0; }
    
                        if ($action === 'run_quick_yoast_metas') {
                            $run['do_yoast_metadesc'] = 1;
                            $run['do_yoast_focuskw']  = 1;
                            $run['do_yoast_title']    = 1;
                            $run['force_yoast']       = 1;
                        } elseif ($action === 'run_quick_yoast_reindex') {
                            $run['do_yoast_reindex'] = 1;
                        } elseif ($action === 'run_quick_featured') {
                            $run['do_featured_only']    = 1;
                            $run['force_featured_only'] = 1;
                            $run['featured_remove_old'] = !empty($u['run_featured_remove_old']) ? 1 : 0;
                        } elseif ($action === 'run_quick_images_only') {
                            $run['do_images_content_only']    = 1;
                            $run['force_images_content_only'] = 1;
                        } elseif ($action === 'run_quick_content_only') {
                            $run['do_content_no_images']    = 1;
                            $run['force_content_no_images'] = 1;
                        }
    
                        if (function_exists('cbia_cap_enabled') && !cbia_cap_enabled('internal_images')) {
                            $run['do_images_reset'] = 0;
                            $run['force_images_reset'] = 0;
                            $run['do_images_content_only'] = 0;
                            $run['force_images_content_only'] = 0;
                            $run['images_limit'] = 1;
                        }

                        cbia_oldposts_run_batch_with_overrides($run);
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Quick action executed. Check the log.', 'cbiastudio-blogflow-ai') . '</p></div>';
                    }
    
                    if ($action === 'stop') {
                        cbia_set_stop_flag(true);
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Stop enabled.', 'cbiastudio-blogflow-ai') . '</p></div>';
                    }
    
                    if ($action === 'clear_log') {
                        cbia_oldposts_clear_log();
                        cbia_oldposts_log_message(__('Log cleared manually.', 'cbiastudio-blogflow-ai'));
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Log cleared.', 'cbiastudio-blogflow-ai') . '</p></div>';
                    }
                }
            }
        }

        return $settings;
    }
}


/* ------------------------- FIN includes/engine/oldposts.php ------------------------- */
