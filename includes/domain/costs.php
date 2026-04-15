<?php
/**
 * CBIA - Costes (estimaciÃƒÂ³n + cÃƒÂ¡lculo post-hoc)
 * v12 (FIX: imÃƒÂ¡genes con precio fijo + botÃƒÂ³n "solo coste real" + tokens reales en log)
 *
 * Archivo: includes/domain/costs.php
 *
 * OBJETIVO:
 * - EstimaciÃƒÂ³n sencilla por post: TEXTO + IMÃƒÂGENES + SEO (si hay llamadas de relleno Yoast/SEO)
 * - CÃƒÂ¡lculo REAL post-hoc: suma el coste POR CADA LLAMADA guardada en _cbia_usage_rows,
 *   respetando el modelo real usado en cada llamada (texto vs imagen vs seo) y su tabla de precios.
 *
 * IMPORTANTE:
 * - Para que el cÃƒÂ¡lculo REAL funcione, el engine/yoast debe llamar a:
 *   cbia_costes_record_usage($post_id, [...])
 *   en CADA llamada a OpenAI (texto / imagen / seo).
 *
 * - Este archivo NO Ã¢â‚¬Å“adivinaÃ¢â‚¬Â tokens reales de imÃƒÂ¡genes si no se registran. Solo estima si faltan.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================
   ===================== SETTINGS (COSTES) ==================
   ========================================================= */
if (!function_exists('cbia_costes_settings_key')) {
    function cbia_costes_settings_key() { return 'cbia_costes_settings'; }
}

if (!function_exists('cbia_costes_get_settings')) {
    function cbia_costes_get_settings() {
        $s = get_option(cbia_costes_settings_key(), array());
        $defaults = array(
            'usd_to_eur' => 0.92,
            'cached_input_ratio' => 0.0,
            'use_image_flat_pricing' => 1,
            'image_flat_usd_mini' => 0.011,
            'image_flat_usd_full' => 0.042,
            'image_flat_usd_openai_mini' => 0.011,
            'image_flat_usd_openai_full' => 0.042,
            'image_flat_usd_imagen3' => 0.040,
            'image_flat_usd_imagen4' => 0.040,
            'responses_fixed_usd_per_call' => 0.0,
            'real_adjust_multiplier' => 1.0,
            'count_failed_attempt_costs' => 1,
            'failed_text_input_ratio' => 1.0,
            'failed_text_output_ratio' => 0.0,
            'failed_image_flat_ratio' => 0.35,
            'image_calls_per_post' => 0,
            'image_model' => 'gpt-image-1-mini',
        );
        $s = is_array($s) ? $s : array();
        return array_merge($defaults, $s);
    }
}

if (!function_exists('cbia_costes_register_settings')) {
    if (!function_exists('cbia_costes_sanitize_settings')) {
        function cbia_costes_sanitize_settings($value) {
            return is_array($value) ? $value : array();
        }
    }
    function cbia_costes_register_settings() {
        register_setting('cbia_costes_settings_group', cbia_costes_settings_key(), array(
            'type' => 'array',
            'sanitize_callback' => 'cbia_costes_sanitize_settings',
            'default' => array(),
        ));
    }
    add_action('admin_init', 'cbia_costes_register_settings');
}

/* =========================================================
   ========================= LOG ============================
   ========================================================= */
if (!function_exists('cbia_costes_log_key')) {
    function cbia_costes_log_key() { return 'cbia_costes_log'; }
}
if (!function_exists('cbia_costes_log')) {
    function cbia_costes_log($msg) {
        if (function_exists('cbia_log')) {
            cbia_log((string)$msg, 'INFO');
            return;
        }
        $log = get_option(cbia_costes_log_key(), '');
        $ts  = current_time('mysql');
        $log .= "[{$ts}] COSTES: {$msg}\n";
        if (strlen($log) > 250000) $log = substr($log, -250000);
        update_option(cbia_costes_log_key(), $log);
        if (function_exists('error_log')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Optional mirror to PHP error log for diagnostics.
            error_log('[CBIA-COSTES] ' . $msg);
        }
    }
}
if (!function_exists('cbia_costes_log_get')) {
    function cbia_costes_log_get() {
        if (function_exists('cbia_get_log')) {
            $payload = cbia_get_log();
            return is_array($payload) ? (string)($payload['log'] ?? '') : (string)$payload;
        }
        return (string)get_option(cbia_costes_log_key(), '');
    }
}
if (!function_exists('cbia_costes_log_clear')) {
    function cbia_costes_log_clear() {
        if (function_exists('cbia_clear_log')) {
            cbia_clear_log();
            return;
        }
        delete_option(cbia_costes_log_key());
    }
}

/* =========================================================
   ===================== TABLA DE PRECIOS ===================
   Valores en USD por 1.000.000 tokens (1M)
   SOLO modelos usados en el plugin (segÃƒÂºn tu Config actual):
   - Texto/SEO: gpt-4.1*, gpt-5*, gpt-5.1, gpt-5.2
   - Imagen: gpt-image-1, gpt-image-1-mini
   ========================================================= */
if (!function_exists('cbia_costes_price_table_usd_per_million')) {
    function cbia_costes_price_table_usd_per_million() {
        // input, cached_input, output  (USD por 1M tokens)
        return array(
            // TEXTO / SEO
            'gpt-4.1'       => array('in'=>2.00,  'cin'=>0.50,  'out'=>8.00),
            'gpt-4.1-mini'  => array('in'=>0.40,  'cin'=>0.10,  'out'=>1.60),
            'gpt-4.1-nano'  => array('in'=>0.10,  'cin'=>0.025, 'out'=>0.40),

            'gpt-5'         => array('in'=>1.25,  'cin'=>0.125, 'out'=>10.00),
            'gpt-5-chat-latest' => array('in'=>1.25, 'cin'=>0.125, 'out'=>10.00),
            'gpt-5-mini'    => array('in'=>0.25,  'cin'=>0.025, 'out'=>2.00),
            'gpt-5-nano'    => array('in'=>0.05,  'cin'=>0.005, 'out'=>0.40),
            'gpt-5-codex'   => array('in'=>1.25,  'cin'=>0.125, 'out'=>10.00),

            'gpt-5.1'       => array('in'=>1.25,  'cin'=>0.125, 'out'=>10.00),
            'gpt-5.1-chat-latest' => array('in'=>1.25, 'cin'=>0.125, 'out'=>10.00),
            'gpt-5.1-mini'  => array('in'=>0.25,  'cin'=>0.025, 'out'=>2.00),
            'gpt-5.1-codex' => array('in'=>1.25,  'cin'=>0.125, 'out'=>10.00),
            'gpt-5.1-codex-max' => array('in'=>1.25, 'cin'=>0.125, 'out'=>10.00),
            'gpt-5.2'       => array('in'=>1.75,  'cin'=>0.175, 'out'=>14.00),
            'gpt-5.2-chat-latest' => array('in'=>1.75, 'cin'=>0.175, 'out'=>14.00),
            'gpt-5.2-codex' => array('in'=>1.75, 'cin'=>0.175, 'out'=>14.00),

            // GOOGLE GEMINI (standard pricing, <=200k prompt tokens)
            'gemini-2.5-pro'        => array('in'=>1.25, 'cin'=>0.125, 'out'=>10.00),
            'gemini-2.5-flash'      => array('in'=>0.30, 'cin'=>0.03,  'out'=>2.50),
            'gemini-2.5-flash-lite' => array('in'=>0.10, 'cin'=>0.01,  'out'=>0.40),

            // DEEPSEEK
            'deepseek-chat'     => array('in'=>0.28, 'cin'=>0.028, 'out'=>0.42),
            'deepseek-reasoner' => array('in'=>0.28, 'cin'=>0.028, 'out'=>0.42),

            // IMAGEN (solo para estimaciÃƒÂ³n basada en tokens; por defecto usaremos tarifa fija)
            'gpt-image-1'       => array('in'=>10.00, 'cin'=>2.50, 'out'=>40.00),
            'gpt-image-1-mini'  => array('in'=>2.50,  'cin'=>0.25, 'out'=>8.00),
        );
    }
}

/* =========================================================
   ======= PRECIOS FIJOS POR IMAGEN (USD por generaciÃƒÂ³n) ===
   ========================================================= */
if (!function_exists('cbia_costes_image_flat_price_usd')) {
    function cbia_costes_image_flat_price_usd($model, $cost_settings) {
        $model = (string)$model;
        $openai_mini = isset($cost_settings['image_flat_usd_openai_mini'])
            ? (float)$cost_settings['image_flat_usd_openai_mini']
            : (isset($cost_settings['image_flat_usd_mini']) ? (float)$cost_settings['image_flat_usd_mini'] : 0.011);
        $openai_full = isset($cost_settings['image_flat_usd_openai_full'])
            ? (float)$cost_settings['image_flat_usd_openai_full']
            : (isset($cost_settings['image_flat_usd_full']) ? (float)$cost_settings['image_flat_usd_full'] : 0.042);
        $imagen3 = isset($cost_settings['image_flat_usd_imagen3'])
            ? (float)$cost_settings['image_flat_usd_imagen3']
            : (isset($cost_settings['image_flat_usd_mini']) ? (float)$cost_settings['image_flat_usd_mini'] : 0.040);
        $imagen4 = isset($cost_settings['image_flat_usd_imagen4'])
            ? (float)$cost_settings['image_flat_usd_imagen4']
            : 0.040;
        if ($model === 'gpt-image-1-mini') return $openai_mini;
        if ($model === 'gpt-image-1') return $openai_full;
        if ($model === 'imagen-3.0-generate-002') return $imagen3;
        if ($model === 'imagen-4.0-generate-001') return $imagen4;
        // fallback: si no reconocemos el modelo, usar el mas conservador
        return $openai_mini;
    }
}

if (!function_exists('cbia_costes_get_supported_image_models')) {
    function cbia_costes_get_supported_image_models() {
        return array(
            'gpt-image-1-mini',
            'gpt-image-1',
            'imagen-3.0-generate-002',
            'imagen-4.0-generate-001',
        );
    }
}

if (!function_exists('cbia_costes_get_current_text_model')) {
    function cbia_costes_get_current_text_model($cbia_settings = array()) {
        $provider = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : 'openai';
        $model = function_exists('cbia_get_text_model_for_provider')
            ? (string)cbia_get_text_model_for_provider((string)$provider, '')
            : '';
        if ($model === '' && !empty($cbia_settings['openai_model'])) {
            $model = (string)$cbia_settings['openai_model'];
        }
        if ($model === '' && function_exists('cbia_providers_get_recommended_text_model')) {
            $model = (string)cbia_providers_get_recommended_text_model((string)$provider);
        }
        return (string)$model;
    }
}

if (!function_exists('cbia_costes_get_current_image_model')) {
    function cbia_costes_get_current_image_model($cbia_settings = array()) {
        $provider = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : 'openai';
        $model = function_exists('cbia_get_image_model_for_provider')
            ? (string)cbia_get_image_model_for_provider((string)$provider, '')
            : '';
        if ($model === '' && !empty($cbia_settings['image_model'])) {
            $model = (string)$cbia_settings['image_model'];
        }
        if ($model === '' && function_exists('cbia_providers_get_recommended_image_model')) {
            $model = (string)cbia_providers_get_recommended_image_model((string)$provider);
        }
        return (string)$model;
    }
}

/* =========================================================
   ============== ESTIMACIÃƒâ€œN: palabras -> tokens ============
   ========================================================= */
if (!function_exists('cbia_costes_words_for_variant')) {
    function cbia_costes_words_for_variant($variant) {
        $variant = (string)$variant;
        if ($variant === 'short') return 1000;
        if ($variant === 'long')  return 2200;
        return 1700;
    }
}

if (!function_exists('cbia_costes_count_words')) {
    function cbia_costes_count_words($text) {
        $txt = wp_strip_all_tags((string)$text);
        $txt = preg_replace('/\s+/u', ' ', $txt);
        $txt = trim($txt);
        if ($txt === '') return 0;
        return count(preg_split('/\s+/u', $txt));
    }
}

if (!function_exists('cbia_costes_words_to_tokens')) {
    function cbia_costes_words_to_tokens($words, $tokens_per_word = 1.30) {
        $w = max(0, (float)$words);
        $tpw = max(0.5, min(2.0, (float)$tokens_per_word));
        return (int)ceil($w * $tpw);
    }
}

if (!function_exists('cbia_costes_estimate_input_tokens')) {
    function cbia_costes_estimate_input_tokens($title, $settings_cbia, $tokens_per_word, $input_overhead_tokens) {
        $prompt = isset($settings_cbia['prompt_single_all']) ? (string)$settings_cbia['prompt_single_all'] : '';
        $words_prompt = cbia_costes_count_words($prompt);
        $words_title  = cbia_costes_count_words((string)$title);

        $tokens = cbia_costes_words_to_tokens($words_prompt + $words_title, $tokens_per_word);
        $tokens += (int)max(0, (int)$input_overhead_tokens);
        return $tokens;
    }
}

if (!function_exists('cbia_costes_estimate_output_tokens')) {
    function cbia_costes_estimate_output_tokens($settings_cbia, $tokens_per_word) {
        $variant = $settings_cbia['post_length_variant'] ?? 'medium';
        $words = cbia_costes_words_for_variant($variant);
        return cbia_costes_words_to_tokens($words, $tokens_per_word);
    }
}

if (!function_exists('cbia_costes_estimate_image_prompt_input_tokens_per_call')) {
    function cbia_costes_estimate_image_prompt_input_tokens_per_call($settings_cbia, $tokens_per_word, $per_image_overhead_words) {
        $p_intro = (string)($settings_cbia['prompt_img_intro'] ?? '');
        $p_body  = (string)($settings_cbia['prompt_img_body'] ?? '');
        $p_conc  = (string)($settings_cbia['prompt_img_conclusion'] ?? '');
        $p_faq   = (string)($settings_cbia['prompt_img_faq'] ?? '');

        $sum_words = 0;
        $sum_words += max(10, cbia_costes_count_words($p_intro));
        $sum_words += max(10, cbia_costes_count_words($p_body));
        $sum_words += max(10, cbia_costes_count_words($p_conc));
        $sum_words += max(10, cbia_costes_count_words($p_faq));

        $avg_words = (int)ceil($sum_words / 4);
        $avg_words += (int)max(0, (int)$per_image_overhead_words);

        return cbia_costes_words_to_tokens($avg_words, $tokens_per_word);
    }
}

/* =========================================================
   ===================== CÃƒÂLCULO DE COSTE ===================
   ========================================================= */
if (!function_exists('cbia_costes_calc_cost_eur')) {
    /**
     * @param string $model
     * @param int $in_tokens
     * @param int $out_tokens
     * @param float $usd_to_eur
     * @param float $cached_input_ratio 0..1 parte de input que se cobra como cached_input
     * @return array [eur_total, eur_in, eur_out]
     */
    function cbia_costes_calc_cost_eur($model, $in_tokens, $out_tokens, $usd_to_eur, $cached_input_ratio = 0.0) {
        $table = cbia_costes_price_table_usd_per_million();
        $model = (string)$model;

        if (!isset($table[$model])) return array(null, null, null);

        $p = $table[$model];
        $usd_in_per_m  = (float)$p['in'];
        $usd_cin_per_m = (float)$p['cin'];
        $usd_out_per_m = (float)$p['out'];

        $in_tokens  = max(0, (int)$in_tokens);
        $out_tokens = max(0, (int)$out_tokens);

        $ratio = (float)$cached_input_ratio;
        if ($ratio < 0) $ratio = 0;
        if ($ratio > 1) $ratio = 1;

        $in_cached = (int)floor($in_tokens * $ratio);
        $in_normal = $in_tokens - $in_cached;

        $usd_in  = ($in_normal / 1000000.0) * $usd_in_per_m;
        $usd_in += ($in_cached / 1000000.0) * $usd_cin_per_m;

        $usd_out = ($out_tokens / 1000000.0) * $usd_out_per_m;

        $usd_total = $usd_in + $usd_out;

        $eur_total = $usd_total * (float)$usd_to_eur;
        $eur_in    = $usd_in    * (float)$usd_to_eur;
        $eur_out   = $usd_out   * (float)$usd_to_eur;

        return array($eur_total, $eur_in, $eur_out);
    }
}

/* =========================================================
   ====== GUARDAR USAGE REAL POR POST (engine debe llamar) ===
   ========================================================= */
if (!function_exists('cbia_costes_record_usage')) {
    /**
     * Guarda una fila de usage por llamada.
     *
     * type: 'text' | 'image' | 'seo' (seo se trata como texto a nivel de pricing)
     * model: modelo real usado
     * input_tokens / output_tokens: tokens reales
     * cached_input_tokens: si lo tienes (si no, 0)
     */
    function cbia_costes_record_usage($post_id, $usage) {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return false;
        if (!is_array($usage)) return false;

        $type  = isset($usage['type']) ? (string)$usage['type'] : 'text';
        $model = isset($usage['model']) ? (string)$usage['model'] : '';
        $in_t  = isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : 0;
        $out_t = isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : 0;
        $cin_t = isset($usage['cached_input_tokens']) ? (int)$usage['cached_input_tokens'] : 0;
        $ok    = !empty($usage['ok']) ? 1 : 0;
        $attempt = isset($usage['attempt']) ? (int)$usage['attempt'] : 1;
        $err   = isset($usage['error']) ? (string)$usage['error'] : '';

        // normaliza type
        $type = strtolower(trim($type));
        if ($type !== 'image' && $type !== 'seo') $type = 'text';

        $row = array(
            'ts' => current_time('mysql'),
            'type' => $type,
            'model' => $model,
            'in' => max(0, $in_t),
            'cin' => max(0, $cin_t),
            'out' => max(0, $out_t),
            'ok' => $ok,
            'attempt' => max(1, $attempt),
            'error' => $err,
        );
        if (isset($usage['section'])) {
            $row['section'] = sanitize_key((string)$usage['section']);
        }
        if (isset($usage['attach_id'])) {
            $row['attach_id'] = max(0, (int)$usage['attach_id']);
        }
        if (isset($usage['bill_in'])) {
            $row['bill_in'] = max(0, (int)$usage['bill_in']);
        }
        if (isset($usage['bill_out'])) {
            $row['bill_out'] = max(0, (int)$usage['bill_out']);
        }
        if (isset($usage['bill_flat_usd'])) {
            $row['bill_flat_usd'] = max(0.0, (float)$usage['bill_flat_usd']);
        }
        if (isset($usage['billing_mode'])) {
            $row['billing_mode'] = sanitize_key((string)$usage['billing_mode']);
        }

        $key = '_cbia_usage_rows';
        $rows = get_post_meta($post_id, $key, true);
        if (!is_array($rows)) $rows = array();
        $rows[] = $row;

        if (count($rows) > 200) $rows = array_slice($rows, -200);

        update_post_meta($post_id, $key, $rows);
        update_post_meta($post_id, '_cbia_usage_last_ts', $row['ts']);
        update_post_meta($post_id, '_cbia_usage_last_model', $model);
        /**
         * Notify listeners that a normalized usage row has been recorded.
         *
         * @param int   $post_id Post ID.
         * @param array $row     Normalized stored row.
         */
        do_action('cbia_usage_row_recorded', $post_id, $row);

        return true;
    }
}

/* =========================================================
   ========= REAL: calcular coste por post sumando filas =====
   ========================================================= */
if (!function_exists('cbia_costes_get_usage_rows_for_post')) {
    function cbia_costes_get_legacy_usage_rows_for_post($post_id) {
        $post_id = (int)$post_id;
        $rows = array();
        $text_rows_count = 0;

        $raw_usage = get_post_meta($post_id, '_cbia_usage_calls', true);
        $usage_calls = is_array($raw_usage) ? $raw_usage : json_decode((string)$raw_usage, true);
        if (is_array($usage_calls)) {
            foreach ($usage_calls as $item) {
                if (!is_array($item)) continue;
                $context = strtolower(trim((string)($item['context'] ?? 'text')));
                $type = (strpos($context, 'seo') !== false) ? 'seo' : 'text';
                $rows[] = array(
                    'ts' => (string)($item['ts'] ?? current_time('mysql')),
                    'type' => $type,
                    'model' => (string)($item['model'] ?? ''),
                    'in' => max(0, (int)($item['input_tokens'] ?? 0)),
                    'cin' => max(0, (int)($item['cached_input_tokens'] ?? 0)),
                    'out' => max(0, (int)($item['output_tokens'] ?? 0)),
                    'ok' => isset($item['ok']) ? (!empty($item['ok']) ? 1 : 0) : 1,
                    'attempt' => max(1, (int)($item['attempt'] ?? 1)),
                    'error' => (string)($item['err'] ?? ($item['error'] ?? '')),
                );
                $text_rows_count++;
            }
        }

        $raw_images = get_post_meta($post_id, '_cbia_image_calls', true);
        $image_calls = is_array($raw_images) ? $raw_images : json_decode((string)$raw_images, true);
        if (is_array($image_calls)) {
            foreach ($image_calls as $item) {
                if (!is_array($item)) continue;
                $rows[] = array(
                    'ts' => (string)($item['ts'] ?? current_time('mysql')),
                    'type' => 'image',
                    'model' => (string)($item['model'] ?? ''),
                    'in' => 0,
                    'cin' => 0,
                    'out' => 0,
                    'ok' => !empty($item['ok']) ? 1 : 0,
                    'attempt' => 1,
                    'error' => (string)($item['error'] ?? ''),
                    'section' => sanitize_key((string)($item['section'] ?? '')),
                    'attach_id' => (int)($item['attach_id'] ?? 0),
                );
            }
        }

        if ($text_rows_count === 0) {
            $sum_in = (int)get_post_meta($post_id, '_cbia_tokens_input_sum', true);
            $sum_out = (int)get_post_meta($post_id, '_cbia_tokens_output_sum', true);
            if ($sum_in > 0 || $sum_out > 0) {
                $cbia_settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
                $fallback_model = (string)get_post_meta($post_id, '_cbia_usage_last_model', true);
                if ($fallback_model === '' && function_exists('cbia_costes_get_current_text_model')) {
                    $fallback_model = (string)cbia_costes_get_current_text_model($cbia_settings);
                }
                $fallback_ts = (string)get_post_meta($post_id, '_cbia_usage_last_ts', true);
                if ($fallback_ts === '') {
                    $fallback_ts = (string)get_post_field('post_modified', $post_id);
                }
                if ($fallback_ts === '') {
                    $fallback_ts = current_time('mysql');
                }

                $rows[] = array(
                    'ts' => $fallback_ts,
                    'type' => 'text',
                    'model' => $fallback_model,
                    'in' => $sum_in,
                    'cin' => 0,
                    'out' => $sum_out,
                    'ok' => 1,
                    'attempt' => 1,
                    'error' => '',
                    'legacy_aggregate' => 1,
                );
            }
        }

        return is_array($rows) ? $rows : array();
    }

    function cbia_costes_get_usage_rows_for_post($post_id) {
        $post_id = (int)$post_id;
        $rows = get_post_meta($post_id, '_cbia_usage_rows', true);
        $rows = is_array($rows) ? $rows : array();
        $legacy_rows = cbia_costes_get_legacy_usage_rows_for_post($post_id);

        if (empty($rows)) {
            return $legacy_rows;
        }
        if (empty($legacy_rows)) {
            return $rows;
        }

        $merged = array();
        $seen = array();
        foreach (array_merge($rows, $legacy_rows) as $row) {
            if (!is_array($row)) continue;
            $hash = md5(wp_json_encode(array(
                (string)($row['ts'] ?? ''),
                (string)($row['type'] ?? ''),
                (string)($row['model'] ?? ''),
                (int)($row['in'] ?? 0),
                (int)($row['cin'] ?? 0),
                (int)($row['out'] ?? 0),
                (int)($row['ok'] ?? 0),
                (int)($row['attempt'] ?? 1),
                (string)($row['section'] ?? ''),
                (int)($row['attach_id'] ?? 0),
                (int)($row['bill_in'] ?? 0),
                (int)($row['bill_out'] ?? 0),
                (float)($row['bill_flat_usd'] ?? 0.0),
                (string)($row['billing_mode'] ?? ''),
                (string)($row['error'] ?? ''),
            )));
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;
            $merged[] = $row;
        }

        usort($merged, function ($a, $b) {
            $ats = strtotime((string)($a['ts'] ?? '')) ?: 0;
            $bts = strtotime((string)($b['ts'] ?? '')) ?: 0;
            return $ats <=> $bts;
        });

        return $merged;
    }
}

if (!function_exists('cbia_costes_get_attempts_from_meta')) {
    function cbia_costes_get_attempts_from_meta($meta) {
        if (!is_array($meta)) return array();
        if (!empty($meta['_cbia_attempts']) && is_array($meta['_cbia_attempts'])) {
            return $meta['_cbia_attempts'];
        }
        if (!empty($meta['attempts']) && is_array($meta['attempts'])) {
            return $meta['attempts'];
        }
        return array();
    }
}

if (!function_exists('cbia_costes_estimate_prompt_tokens')) {
    function cbia_costes_estimate_prompt_tokens($prompt, $cost_settings = array()) {
        $prompt = trim((string)$prompt);
        $tokens_per_word = isset($cost_settings['tokens_per_word']) ? (float)$cost_settings['tokens_per_word'] : 1.30;
        if ($tokens_per_word <= 0) $tokens_per_word = 1.30;
        $words = function_exists('cbia_costes_count_words') ? cbia_costes_count_words($prompt) : str_word_count(wp_strip_all_tags($prompt));
        if ($words <= 0) $words = 1;
        return max(1, (int)ceil($words * $tokens_per_word));
    }
}

if (!function_exists('cbia_costes_should_bill_failed_attempt')) {
    function cbia_costes_should_bill_failed_attempt($attempt) {
        if (!is_array($attempt)) return false;
        if (!empty($attempt['ok'])) return false;
        if (isset($attempt['billable'])) return !empty($attempt['billable']);

        $error = strtolower(trim((string)($attempt['error'] ?? '')));
        if ($error === '') return false;

        if (preg_match('/http\s+(\d{3})/i', $error, $m)) {
            $code = (int)$m[1];
            if ($code >= 500 || $code === 408) return true;
            return false;
        }

        $billable_needles = array(
            'curl error 28',
            'timed out',
            'timeout=',
            'respuesta sin texto',
            'respuesta invalida',
            'respuesta sin bytes',
            'respuesta sin imagen',
            'empty response',
            'truncated',
            'upload',
            'media library',
        );
        foreach ($billable_needles as $needle) {
            if (strpos($error, $needle) !== false) return true;
        }

        $non_billable_needles = array(
            'no hay api key',
            'api key',
            'consentimiento',
            'proveedor de imagen no soportado',
            'modelo no soportado',
            'invalid model',
            'does not exist',
            'not found',
            'unauthorized',
            'forbidden',
            'rate limit',
            'quota',
            'insufficient_quota',
            'permission',
        );
        foreach ($non_billable_needles as $needle) {
            if (strpos($error, $needle) !== false) return false;
        }

        return false;
    }
}

if (!function_exists('cbia_costes_record_failed_attempts')) {
    function cbia_costes_record_failed_attempts($post_id, $attempts, $args = array()) {
        $post_id = (int)$post_id;
        if ($post_id <= 0 || !is_array($attempts) || empty($attempts)) return 0;

        $args = is_array($args) ? $args : array();
        $type_default = isset($args['type']) ? strtolower(trim((string)$args['type'])) : 'text';
        if ($type_default !== 'image' && $type_default !== 'seo') $type_default = 'text';
        $prompt = isset($args['prompt']) ? (string)$args['prompt'] : '';
        $section_default = isset($args['section']) ? sanitize_key((string)$args['section']) : '';

        $cost_settings = cbia_costes_get_settings();
        $cbia_settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
        $tokens_per_word = isset($cost_settings['tokens_per_word']) ? (float)$cost_settings['tokens_per_word'] : 1.30;
        if ($tokens_per_word <= 0) $tokens_per_word = 1.30;
        $input_overhead_tokens = isset($cost_settings['input_overhead_tokens']) ? (int)$cost_settings['input_overhead_tokens'] : 350;
        if ($input_overhead_tokens < 0) $input_overhead_tokens = 0;

        $failed_text_input_ratio = isset($cost_settings['failed_text_input_ratio']) ? (float)$cost_settings['failed_text_input_ratio'] : 1.0;
        $failed_text_output_ratio = isset($cost_settings['failed_text_output_ratio']) ? (float)$cost_settings['failed_text_output_ratio'] : 0.0;
        $failed_image_flat_ratio = isset($cost_settings['failed_image_flat_ratio']) ? (float)$cost_settings['failed_image_flat_ratio'] : 0.35;

        if ($failed_text_input_ratio < 0) $failed_text_input_ratio = 0.0;
        if ($failed_text_input_ratio > 1) $failed_text_input_ratio = 1.0;
        if ($failed_text_output_ratio < 0) $failed_text_output_ratio = 0.0;
        if ($failed_text_output_ratio > 1) $failed_text_output_ratio = 1.0;
        if ($failed_image_flat_ratio < 0) $failed_image_flat_ratio = 0.0;
        if ($failed_image_flat_ratio > 1) $failed_image_flat_ratio = 1.0;

        $recorded = 0;
        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) continue;
            if (!empty($attempt['ok'])) continue;
            if (!cbia_costes_should_bill_failed_attempt($attempt)) continue;

            $type = isset($attempt['type']) ? strtolower(trim((string)$attempt['type'])) : $type_default;
            if ($type !== 'image' && $type !== 'seo') $type = 'text';
            $model = sanitize_text_field((string)($attempt['model'] ?? ''));
            if ($model === '') continue;

            $row = array(
                'type' => $type,
                'model' => $model,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cached_input_tokens' => 0,
                'ok' => 0,
                'attempt' => max(1, (int)($attempt['attempt'] ?? 1)),
                'error' => sanitize_text_field((string)($attempt['error'] ?? '')),
                'billing_mode' => 'failed_heuristic',
            );

            if ($type === 'image') {
                $section = isset($attempt['section']) ? sanitize_key((string)$attempt['section']) : $section_default;
                if ($section !== '') $row['section'] = $section;
                if (!empty($cost_settings['use_image_flat_pricing'])) {
                    $flat_usd = (float)cbia_costes_image_flat_price_usd($model, $cost_settings);
                    $row['bill_flat_usd'] = round(max(0.0, $flat_usd * $failed_image_flat_ratio), 6);
                } else {
                    $est_in = $prompt !== ''
                        ? cbia_costes_estimate_prompt_tokens($prompt, $cost_settings)
                        : cbia_costes_estimate_image_prompt_input_tokens_per_call($cbia_settings, $tokens_per_word, (int)($cost_settings['per_image_overhead_words'] ?? 18));
                    $row['bill_in'] = max(0, (int)$est_in);
                    $row['bill_out'] = 0;
                }
            } else {
                $est_in = $prompt !== ''
                    ? cbia_costes_estimate_prompt_tokens($prompt, $cost_settings)
                    : cbia_costes_estimate_input_tokens('{title}', $cbia_settings, $tokens_per_word, $input_overhead_tokens);
                $est_out = ($type === 'seo')
                    ? max(0, (int)($cost_settings['seo_output_tokens_per_call'] ?? 180))
                    : cbia_costes_estimate_output_tokens($cbia_settings, $tokens_per_word);

                $row['bill_in'] = max(0, (int)ceil($est_in * $failed_text_input_ratio));
                $row['bill_out'] = max(0, (int)ceil($est_out * $failed_text_output_ratio));
            }

            cbia_costes_record_usage($post_id, $row);
            $recorded++;
        }

        return $recorded;
    }
}

if (!function_exists('cbia_costes_calc_row_eur')) {
    function cbia_costes_calc_row_eur($row, $cost_settings, $table = null) {
        if (!is_array($row)) return null;
        if (!is_array($table)) {
            $table = cbia_costes_price_table_usd_per_million();
        }

        $type = isset($row['type']) ? strtolower(trim((string)($row['type'] ?? 'text'))) : 'text';
        if ($type !== 'image' && $type !== 'seo') $type = 'text';

        $model = isset($row['model']) ? (string)$row['model'] : '';
        $in = max(0, (int)($row['in'] ?? 0));
        $cin = max(0, (int)($row['cin'] ?? 0));
        $out = max(0, (int)($row['out'] ?? 0));
        $ok = !empty($row['ok']) ? 1 : 0;
        $bill_in = max(0, (int)($row['bill_in'] ?? 0));
        $bill_out = max(0, (int)($row['bill_out'] ?? 0));
        $bill_flat_usd = isset($row['bill_flat_usd']) ? (float)$row['bill_flat_usd'] : 0.0;

        $usd_to_eur = (float)($cost_settings['usd_to_eur'] ?? 0.92);
        $fallback_ratio = (float)($cost_settings['cached_input_ratio'] ?? 0.0);
        $use_image_flat = !empty($cost_settings['use_image_flat_pricing']);
        $resp_fixed_usd = (float)($cost_settings['responses_fixed_usd_per_call'] ?? 0.0);
        $count_failed_attempt_costs = !empty($cost_settings['count_failed_attempt_costs']);

        if (!$ok && !$count_failed_attempt_costs) {
            return null;
        }

        if ($type === 'image' && $use_image_flat) {
            if (!$ok && $bill_flat_usd <= 0) return null;
            $flat_usd = $ok ? (float)cbia_costes_image_flat_price_usd($model, $cost_settings) : $bill_flat_usd;
            return (float)$flat_usd * $usd_to_eur;
        }

        if ($model === '' || !isset($table[$model])) {
            return null;
        }

        if (!$ok && ($bill_in > 0 || $bill_out > 0)) {
            $in = $bill_in;
            $out = $bill_out;
            $cin = 0;
        }

        $ratio = $fallback_ratio;
        if ($in > 0 && $cin > 0) {
            $ratio = min(1.0, max(0.0, $cin / (float)max(1, $in)));
        }

        list($eur) = cbia_costes_calc_cost_eur($model, $in, $out, $usd_to_eur, $ratio);
        if ($eur === null) {
            return null;
        }

        $eur = (float)$eur;
        if (($type === 'text' || $type === 'seo') && $resp_fixed_usd > 0) {
            $eur += $resp_fixed_usd * $usd_to_eur;
        }

        return $eur;
    }
}

/* =========================================================
   ===== AJUSTE AUTOMÃƒÂTICO POR MODELO (opcional) ============
   ========================================================= */
if (!function_exists('cbia_costes_pick_primary_text_model')) {
    function cbia_costes_pick_primary_text_model($rows, $cbia_settings = array()) {
        $counts = array();
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $type = isset($r['type']) ? strtolower(trim((string)$r['type'])) : 'text';
                if ($type !== 'text' && $type !== 'seo') continue;
                $model = isset($r['model']) ? (string)$r['model'] : '';
                if ($model === '') continue;
                if (!isset($counts[$model])) $counts[$model] = 0;
                $counts[$model]++;
            }
        }

        if (!empty($counts)) {
            arsort($counts);
            $top = array_key_first($counts);
            if (is_string($top) && $top !== '') return $top;
        }

        $fallback = cbia_costes_get_current_text_model($cbia_settings);
        return $fallback;
    }
}

if (!function_exists('cbia_costes_get_model_multiplier')) {
    function cbia_costes_get_model_multiplier($model, $cost_settings) {
        // Desactivado: para evitar ajustes ocultos por modelo, el calculo oficial
        // usa solo tarifas base + el multiplicador global visible en UI.
        return 1.0;
    }
}
if (!function_exists('cbia_costes_calc_real_for_post')) {
    /**
     * Devuelve:
     * [
     *   'eur' => float,
     *   'calls' => int,
     *   'fails' => int,
     *   'by_type' => ['text'=>['eur'=>..,'calls'=>..], 'seo'=>..., 'image'=>...],
     *   'by_model' => ['gpt-4.1-mini'=>['eur'=>..,'calls'=>..], ...],
     * ]
     * o null si no hay filas.
     */
    function cbia_costes_calc_real_for_post($post_id, $cost_settings, $cbia_settings = array()) {
        $rows = cbia_costes_get_usage_rows_for_post((int)$post_id);
        if (empty($rows)) return null;

        $table = cbia_costes_price_table_usd_per_million();
        $real_mult = (float)($cost_settings['real_adjust_multiplier'] ?? 1.0);

        // Ajuste automÃƒÂ¡tico por modelo (solo si el multiplicador global estÃƒÂ¡ en 1.0)
        $primary_text_model = cbia_costes_pick_primary_text_model($rows, $cbia_settings);
        $model_mult = cbia_costes_get_model_multiplier($primary_text_model, $cost_settings);

        $sum_eur = 0.0;
        $calls = 0;
        $fails = 0;
        $sum_in_tokens = 0; // para log
        $sum_out_tokens = 0; // para log

        $by_type = array(
            'text' => array('eur'=>0.0,'calls'=>0),
            'seo'  => array('eur'=>0.0,'calls'=>0),
            'image'=> array('eur'=>0.0,'calls'=>0),
        );
        $by_model = array();

        foreach ($rows as $r) {
            if (!is_array($r)) continue;

            $type = isset($r['type']) ? strtolower(trim((string)$r['type'])) : 'text';
            if ($type !== 'image' && $type !== 'seo') $type = 'text';

            $model = isset($r['model']) ? (string)$r['model'] : '';
            $in    = isset($r['in']) ? (int)$r['in'] : 0;
            $cin   = isset($r['cin']) ? (int)$r['cin'] : 0;
            $out   = isset($r['out']) ? (int)$r['out'] : 0;
            $ok    = !empty($r['ok']) ? 1 : 0;

            $calls++;
            if (!$ok) $fails++;

            // Tokens acumulados para log (texto/seo). Para imagen normalmente 0.
            $sum_in_tokens += (int)$in;
            $sum_out_tokens += (int)$out;

            // IMÃƒÂGENES: si estÃƒÂ¡ activa la tarifa plana, sumar por generaciÃƒÂ³n OK
            $eur = cbia_costes_calc_row_eur($r, $cost_settings, $table);
            if ($eur === null) continue;

            $sum_eur += (float)$eur;

            if (!isset($by_type[$type])) $by_type[$type] = array('eur'=>0.0,'calls'=>0);
            $by_type[$type]['eur'] += (float)$eur;
            $by_type[$type]['calls']++;

            if (!isset($by_model[$model])) $by_model[$model] = array('eur'=>0.0,'calls'=>0);
            $by_model[$model]['eur'] += (float)$eur;
            $by_model[$model]['calls']++;
        }

        // AÃƒÂ±adir sobrecoste fijo por llamada de texto/SEO (en USD)
        // Multiplicador de ajuste final
        $final_mult = (float)$real_mult;
        if (($final_mult === 1.0 || $final_mult <= 0) && $model_mult > 0 && $model_mult != 1.0) {
            $final_mult = (float)$model_mult;
        }
        if ($final_mult > 0 && $final_mult != 1.0) {
            $sum_eur *= $final_mult;
        }

        return array(
            'eur' => (float)$sum_eur,
            'calls' => (int)$calls,
            'fails' => (int)$fails,
            'by_type' => $by_type,
            'by_model' => $by_model,
            'in_tokens' => (int)$sum_in_tokens,
            'out_tokens' => (int)$sum_out_tokens,
        );
    }
}

/* =========================================================
   ===================== ESTIMACIÃƒâ€œN POR POST =================
   Incluye: TEXTO + IMAGEN + SEO
   ========================================================= */
if (!function_exists('cbia_costes_estimate_for_post')) {
    function cbia_costes_estimate_for_post($post_id, $cost_settings, $cbia_settings) {
        $table = cbia_costes_price_table_usd_per_million();

        $title = get_the_title((int)$post_id);
        if (!$title) $title = '{title}';

        // Modelos
        $model_text = cbia_costes_get_current_text_model($cbia_settings);
        if (!isset($table[$model_text])) $model_text = 'gpt-4.1-mini';

        $model_seo = (string)($cost_settings['seo_model'] ?? $model_text);
        if (!isset($table[$model_seo])) $model_seo = $model_text;

        $model_img = (string)($cost_settings['image_model'] ?? '');
        if ($model_img === '') {
            $model_img = cbia_costes_get_current_image_model($cbia_settings);
        }
        if ($model_img === '') $model_img = 'gpt-image-1-mini';

        // Llamadas por post
        $text_calls = max(1, (int)($cost_settings['text_calls_per_post'] ?? 1));

        $img_calls  = (int)($cost_settings['image_calls_per_post'] ?? 0);
        if ($img_calls <= 0) {
            $img_calls = isset($cbia_settings['images_limit']) ? (int)$cbia_settings['images_limit'] : 3;
        }
        $img_calls = max(0, min(20, $img_calls));

        $seo_calls = max(0, (int)($cost_settings['seo_calls_per_post'] ?? 0));
        $seo_calls = min(20, $seo_calls);

        $usd_to_eur = (float)($cost_settings['usd_to_eur'] ?? 0.92);
        $cached_ratio = (float)($cost_settings['cached_input_ratio'] ?? 0.0);

        /* ===== TEXTO ===== */
        $in_text  = cbia_costes_estimate_input_tokens($title, $cbia_settings, (float)$cost_settings['tokens_per_word'], (int)$cost_settings['input_overhead_tokens']);
        $out_text = cbia_costes_estimate_output_tokens($cbia_settings, (float)$cost_settings['tokens_per_word']);

        $in_text  = (int)ceil($in_text  * (float)$cost_settings['mult_text']);
        $out_text = (int)ceil($out_text * (float)$cost_settings['mult_text']);

        $in_text_total  = $in_text  * $text_calls;
        $out_text_total = $out_text * $text_calls;

        list($eur_text, $eur_in_text, $eur_out_text) =
            cbia_costes_calc_cost_eur($model_text, $in_text_total, $out_text_total, $usd_to_eur, $cached_ratio);
        if ($eur_text === null) return null;

        /* ===== IMAGEN ===== */
        $in_img_per_call = cbia_costes_estimate_image_prompt_input_tokens_per_call($cbia_settings, (float)$cost_settings['tokens_per_word'], (int)$cost_settings['per_image_overhead_words']);
        $out_img_per_call = max(0, (int)($cost_settings['image_output_tokens_per_call'] ?? 0));

        $in_img_per_call  = (int)ceil($in_img_per_call  * (float)$cost_settings['mult_image']);
        $out_img_per_call = (int)ceil($out_img_per_call * (float)$cost_settings['mult_image']);

        $in_img_total  = $in_img_per_call  * $img_calls;
        $out_img_total = $out_img_per_call * $img_calls;

        $use_image_flat = !empty($cost_settings['use_image_flat_pricing']);
        if ($use_image_flat) {
            $usd_flat = (float)cbia_costes_image_flat_price_usd($model_img, $cost_settings);
            $eur_img = $img_calls * $usd_flat * $usd_to_eur;
            $eur_in_img = 0.0; $eur_out_img = 0.0;
        } else {
            list($eur_img, $eur_in_img, $eur_out_img) =
                cbia_costes_calc_cost_eur($model_img, $in_img_total, $out_img_total, $usd_to_eur, $cached_ratio);
            if ($eur_img === null) $eur_img = 0.0;
        }

        /* ===== SEO ===== */
        $seo_in_per_call  = max(0, (int)($cost_settings['seo_input_tokens_per_call'] ?? 0));
        $seo_out_per_call = max(0, (int)($cost_settings['seo_output_tokens_per_call'] ?? 0));

        $seo_in_per_call  = (int)ceil($seo_in_per_call  * (float)$cost_settings['mult_seo']);
        $seo_out_per_call = (int)ceil($seo_out_per_call * (float)$cost_settings['mult_seo']);

        $seo_in_total  = $seo_in_per_call  * $seo_calls;
        $seo_out_total = $seo_out_per_call * $seo_calls;

        $eur_seo = 0.0;
        if ($seo_calls > 0 && ($seo_in_total > 0 || $seo_out_total > 0)) {
            list($eur_seo_calc, $eur_in_seo, $eur_out_seo) =
                cbia_costes_calc_cost_eur($model_seo, $seo_in_total, $seo_out_total, $usd_to_eur, $cached_ratio);
            if ($eur_seo_calc !== null) $eur_seo = (float)$eur_seo_calc;
        }

        return (float)$eur_text + (float)$eur_img + (float)$eur_seo;
    }
}

/* =========================================================
   ============ CÃƒÂLCULO ÃƒÅ¡LTIMOS POSTS (real/estimado) =======
   ========================================================= */
if (!function_exists('cbia_costes_calc_last_posts')) {
    function cbia_costes_calc_last_posts($n, $only_cbia, $use_est_if_missing, $cost_settings, $cbia_settings) {
        $n = max(1, min(200, (int)$n));

        $args = array(
            'post_type'      => 'post',
            'posts_per_page' => $n,
            'post_status'    => array('publish','future','draft','pending'),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );

        if ($only_cbia) {
            $args['meta_query'] = array(
                array('key' => '_cbia_created', 'value' => '1', 'compare' => '=')
            );
        }

        $q = new WP_Query($args);
        $ids = !empty($q->posts) ? $q->posts : array();
        if (empty($ids)) return null;

        $total_eur = 0.0;
        $real_posts = 0;
        $est_posts  = 0;

        $real_calls = 0;
        $real_fails = 0;
        $tok_in_sum = 0;
        $tok_out_sum = 0;

        foreach ($ids as $post_id) {
            $post_id = (int)$post_id;

            // 1) REAL: suma por filas (modelo real por llamada)
            $real = cbia_costes_calc_real_for_post($post_id, $cost_settings, $cbia_settings);
            if (is_array($real)) {
                $total_eur += (float)$real['eur'];
                $real_posts++;
                $real_calls += (int)$real['calls'];
                $real_fails += (int)$real['fails'];
                $tok_in_sum += (int)$real['in_tokens'];
                $tok_out_sum += (int)$real['out_tokens'];
                continue;
            }

            // 2) ESTIMACIÃƒâ€œN
            if ($use_est_if_missing) {
                $est = cbia_costes_estimate_for_post($post_id, $cost_settings, $cbia_settings);
                if ($est !== null) {
                    $total_eur += (float)$est;
                    $est_posts++;
                }
            }
        }

        return array(
            'posts' => count($ids),
            'real_posts' => $real_posts,
            'est_posts' => $est_posts,
            'eur_total' => $total_eur,
            'real_calls' => $real_calls,
            'real_fails' => $real_fails,
            'tokens_in' => $tok_in_sum,
            'tokens_out'=> $tok_out_sum,
        );
    }
}

/* =========================================================
   ================== POST HANDLER (COSTES) ================
   ========================================================= */
if (!function_exists('cbia_costes_handle_post')) {
    function cbia_costes_handle_post($cost, $cbia, $defaults, $table, $model_text_current) {
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) {
                $service = $container->get('costs_service');
                if ($service && method_exists($service, 'handle_post')) {
                    return $service->handle_post($cost, $cbia, $defaults, $table, $model_text_current);
                }
            }
        }
        $notice = '';
        $calibration_info = null;

        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD']))) : '';
        if ($request_method !== 'POST') {
            return array($cost, $notice, $calibration_info);
        }

        $u = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();

        if (!empty($u['cbia_form']) && $u['cbia_form'] === 'costes_settings' && check_admin_referer('cbia_costes_settings_nonce')) {
            $cost['usd_to_eur'] = isset($u['usd_to_eur']) ? (float)str_replace(',', '.', (string)$u['usd_to_eur']) : $cost['usd_to_eur'];
            if ($cost['usd_to_eur'] <= 0) $cost['usd_to_eur'] = 0.92;

            $cost['tokens_per_word'] = isset($u['tokens_per_word']) ? (float)str_replace(',', '.', (string)$u['tokens_per_word']) : $cost['tokens_per_word'];
            if ($cost['tokens_per_word'] < 0.5) $cost['tokens_per_word'] = 0.5;
            if ($cost['tokens_per_word'] > 2.0) $cost['tokens_per_word'] = 2.0;

            $cost['input_overhead_tokens'] = isset($u['input_overhead_tokens']) ? (int)$u['input_overhead_tokens'] : (int)$cost['input_overhead_tokens'];
            if ($cost['input_overhead_tokens'] < 0) $cost['input_overhead_tokens'] = 0;
            if ($cost['input_overhead_tokens'] > 5000) $cost['input_overhead_tokens'] = 5000;

            $cost['per_image_overhead_words'] = isset($u['per_image_overhead_words']) ? (int)$u['per_image_overhead_words'] : (int)$cost['per_image_overhead_words'];
            if ($cost['per_image_overhead_words'] < 0) $cost['per_image_overhead_words'] = 0;
            if ($cost['per_image_overhead_words'] > 300) $cost['per_image_overhead_words'] = 300;

            $cost['cached_input_ratio'] = isset($u['cached_input_ratio']) ? (float)str_replace(',', '.', (string)$u['cached_input_ratio']) : (float)$cost['cached_input_ratio'];
            if ($cost['cached_input_ratio'] < 0) $cost['cached_input_ratio'] = 0;
            if ($cost['cached_input_ratio'] > 1) $cost['cached_input_ratio'] = 1;

            // Tarifa fija por imagen
            $cost['use_image_flat_pricing'] = !empty($u['use_image_flat_pricing']) ? 1 : 0;
            $cost['image_flat_usd_openai_mini'] = isset($u['image_flat_usd_openai_mini']) ? (float)str_replace(',', '.', (string)$u['image_flat_usd_openai_mini']) : (float)($cost['image_flat_usd_openai_mini'] ?? ($cost['image_flat_usd_mini'] ?? 0.011));
            if ($cost['image_flat_usd_openai_mini'] < 0) $cost['image_flat_usd_openai_mini'] = 0.0;
            $cost['image_flat_usd_openai_full'] = isset($u['image_flat_usd_openai_full']) ? (float)str_replace(',', '.', (string)$u['image_flat_usd_openai_full']) : (float)($cost['image_flat_usd_openai_full'] ?? ($cost['image_flat_usd_full'] ?? 0.042));
            if ($cost['image_flat_usd_openai_full'] < 0) $cost['image_flat_usd_openai_full'] = 0.0;
            $cost['image_flat_usd_imagen3'] = isset($u['image_flat_usd_imagen3']) ? (float)str_replace(',', '.', (string)$u['image_flat_usd_imagen3']) : (float)($cost['image_flat_usd_imagen3'] ?? ($cost['image_flat_usd_mini'] ?? 0.040));
            if ($cost['image_flat_usd_imagen3'] < 0) $cost['image_flat_usd_imagen3'] = 0.0;
            $cost['image_flat_usd_imagen4'] = isset($u['image_flat_usd_imagen4']) ? (float)str_replace(',', '.', (string)$u['image_flat_usd_imagen4']) : (float)($cost['image_flat_usd_imagen4'] ?? 0.040);
            if ($cost['image_flat_usd_imagen4'] < 0) $cost['image_flat_usd_imagen4'] = 0.0;
            // Compatibilidad con claves antiguas.
            $cost['image_flat_usd_mini'] = $cost['image_flat_usd_openai_mini'];
            $cost['image_flat_usd_full'] = $cost['image_flat_usd_openai_full'];

            $cost['mult_text'] = isset($u['mult_text']) ? (float)str_replace(',', '.', (string)$u['mult_text']) : (float)$cost['mult_text'];
            if ($cost['mult_text'] < 1.0) $cost['mult_text'] = 1.0;
            if ($cost['mult_text'] > 5.0) $cost['mult_text'] = 5.0;

            $cost['mult_image'] = isset($u['mult_image']) ? (float)str_replace(',', '.', (string)$u['mult_image']) : (float)$cost['mult_image'];
            if ($cost['mult_image'] < 1.0) $cost['mult_image'] = 1.0;
            if ($cost['mult_image'] > 5.0) $cost['mult_image'] = 5.0;

            $cost['mult_seo'] = isset($u['mult_seo']) ? (float)str_replace(',', '.', (string)$u['mult_seo']) : (float)$cost['mult_seo'];
            if ($cost['mult_seo'] < 1.0) $cost['mult_seo'] = 1.0;
            if ($cost['mult_seo'] > 5.0) $cost['mult_seo'] = 5.0;

            // Ajustes finos
            $cost['responses_fixed_usd_per_call'] = isset($u['responses_fixed_usd_per_call']) ? (float)str_replace(',', '.', (string)$u['responses_fixed_usd_per_call']) : (float)$cost['responses_fixed_usd_per_call'];
            if ($cost['responses_fixed_usd_per_call'] < 0) $cost['responses_fixed_usd_per_call'] = 0.0;
            $cost['real_adjust_multiplier'] = isset($u['real_adjust_multiplier']) ? (float)str_replace(',', '.', (string)$u['real_adjust_multiplier']) : (float)$cost['real_adjust_multiplier'];
            if ($cost['real_adjust_multiplier'] < 0.5) $cost['real_adjust_multiplier'] = 0.5;
            if ($cost['real_adjust_multiplier'] > 1.5) $cost['real_adjust_multiplier'] = 1.5;
            $cost['count_failed_attempt_costs'] = !empty($u['count_failed_attempt_costs']) ? 1 : 0;
            $cost['failed_text_input_ratio'] = isset($u['failed_text_input_ratio']) ? (float)str_replace(',', '.', (string)$u['failed_text_input_ratio']) : (float)($cost['failed_text_input_ratio'] ?? 1.0);
            if ($cost['failed_text_input_ratio'] < 0) $cost['failed_text_input_ratio'] = 0.0;
            if ($cost['failed_text_input_ratio'] > 1) $cost['failed_text_input_ratio'] = 1.0;
            $cost['failed_text_output_ratio'] = isset($u['failed_text_output_ratio']) ? (float)str_replace(',', '.', (string)$u['failed_text_output_ratio']) : (float)($cost['failed_text_output_ratio'] ?? 0.0);
            if ($cost['failed_text_output_ratio'] < 0) $cost['failed_text_output_ratio'] = 0.0;
            if ($cost['failed_text_output_ratio'] > 1) $cost['failed_text_output_ratio'] = 1.0;
            $cost['failed_image_flat_ratio'] = isset($u['failed_image_flat_ratio']) ? (float)str_replace(',', '.', (string)$u['failed_image_flat_ratio']) : (float)($cost['failed_image_flat_ratio'] ?? 0.35);
            if ($cost['failed_image_flat_ratio'] < 0) $cost['failed_image_flat_ratio'] = 0.0;
            if ($cost['failed_image_flat_ratio'] > 1) $cost['failed_image_flat_ratio'] = 1.0;

            // nÃ‚Âº llamadas texto/imagen
            $cost['text_calls_per_post'] = isset($u['text_calls_per_post']) ? (int)$u['text_calls_per_post'] : (int)$cost['text_calls_per_post'];
            if ($cost['text_calls_per_post'] < 1) $cost['text_calls_per_post'] = 1;
            if ($cost['text_calls_per_post'] > 20) $cost['text_calls_per_post'] = 20;

            $cost['image_calls_per_post'] = isset($u['image_calls_per_post']) ? (int)$u['image_calls_per_post'] : (int)$cost['image_calls_per_post'];
            if ($cost['image_calls_per_post'] < 0) $cost['image_calls_per_post'] = 0;
            if ($cost['image_calls_per_post'] > 20) $cost['image_calls_per_post'] = 20;

            // modelo imagen (proveedores soportados)
            $im = isset($u['image_model']) ? sanitize_text_field((string)$u['image_model']) : (string)$cost['image_model'];
            $allowed_image_models = function_exists('cbia_costes_get_supported_image_models')
                ? cbia_costes_get_supported_image_models()
                : array('gpt-image-1-mini', 'gpt-image-1');
            if (!in_array($im, $allowed_image_models, true)) {
                $im = cbia_costes_get_current_image_model($cbia);
                if ($im === '') $im = 'gpt-image-1-mini';
            }
            $cost['image_model'] = $im;

            // output tokens por imagen
            $cost['image_output_tokens_per_call'] = isset($u['image_output_tokens_per_call']) ? (int)$u['image_output_tokens_per_call'] : (int)$cost['image_output_tokens_per_call'];
            if ($cost['image_output_tokens_per_call'] < 0) $cost['image_output_tokens_per_call'] = 0;
            if ($cost['image_output_tokens_per_call'] > 50000) $cost['image_output_tokens_per_call'] = 50000;

            // SEO settings
            $cost['seo_calls_per_post'] = isset($u['seo_calls_per_post']) ? (int)$u['seo_calls_per_post'] : (int)$cost['seo_calls_per_post'];
            if ($cost['seo_calls_per_post'] < 0) $cost['seo_calls_per_post'] = 0;
            if ($cost['seo_calls_per_post'] > 20) $cost['seo_calls_per_post'] = 20;

            $seo_model = isset($u['seo_model']) ? sanitize_text_field((string)$u['seo_model']) : (string)$cost['seo_model'];
            if ($seo_model === '' || !isset($table[$seo_model])) $seo_model = $model_text_current;
            $cost['seo_model'] = $seo_model;

            $cost['seo_input_tokens_per_call'] = isset($u['seo_input_tokens_per_call']) ? (int)$u['seo_input_tokens_per_call'] : (int)$cost['seo_input_tokens_per_call'];
            if ($cost['seo_input_tokens_per_call'] < 0) $cost['seo_input_tokens_per_call'] = 0;
            if ($cost['seo_input_tokens_per_call'] > 50000) $cost['seo_input_tokens_per_call'] = 50000;

            $cost['seo_output_tokens_per_call'] = isset($u['seo_output_tokens_per_call']) ? (int)$u['seo_output_tokens_per_call'] : (int)$cost['seo_output_tokens_per_call'];
            if ($cost['seo_output_tokens_per_call'] < 0) $cost['seo_output_tokens_per_call'] = 0;
            if ($cost['seo_output_tokens_per_call'] > 50000) $cost['seo_output_tokens_per_call'] = 50000;

            update_option(cbia_costes_settings_key(), $cost);
            $notice = 'saved';
            cbia_costes_log(__('Settings saved.', 'cbiastudio-blogflow-ai'));
        }

        if (!empty($u['cbia_form']) && $u['cbia_form'] === 'costes_actions' && check_admin_referer('cbia_costes_actions_nonce')) {
            $action = isset($u['cbia_action']) ? sanitize_text_field((string)$u['cbia_action']) : '';

            if ($action === 'clear_log') {
                cbia_costes_log_clear();
                cbia_costes_log(__('Log cleared manually.', 'cbiastudio-blogflow-ai'));
                $notice = 'log';
            }

            if ($action === 'calc_last') {
                $n = isset($u['calc_last_n']) ? (int)$u['calc_last_n'] : 20;
                $n = max(1, min(200, $n));

                $only_cbia = !empty($u['calc_only_cbia']) ? true : false;
                $use_est_if_missing = !empty($u['calc_estimate_if_missing']) ? true : false;

                $sum = cbia_costes_calc_last_posts($n, $only_cbia, $use_est_if_missing, $cost, $cbia);
                if ($sum) {
                    cbia_costes_log(
                        sprintf(
                            /* translators: 1: posts window size, 2: total posts, 3: posts with real usage, 4: posts with estimate fallback, 5: real calls, 6: failed calls, 7: input tokens, 8: output tokens, 9: total EUR. */
                            __('Last %1$d calculation: posts=%2$d real=%3$d est=%4$d real_calls=%5$d real_fails=%6$d tokens_in=%7$d tokens_out=%8$d total EUR=%9$s', 'cbiastudio-blogflow-ai'),
                            $n,
                            (int)$sum['posts'],
                            (int)$sum['real_posts'],
                            (int)$sum['est_posts'],
                            (int)$sum['real_calls'],
                            (int)$sum['real_fails'],
                            (int)$sum['tokens_in'],
                            (int)$sum['tokens_out'],
                            number_format((float)$sum['eur_total'], 4, ',', '.')
                        )
                    );
                } else {
                    /* translators: %d is the posts window size used in the calculation. */
                    cbia_costes_log(sprintf(__('Last %d calculation: no results.', 'cbiastudio-blogflow-ai'), $n));
                }
                $notice = 'calc';
            }

            if ($action === 'calc_last_real') {
                $n = isset($u['calc_last_n']) ? (int)$u['calc_last_n'] : 20;
                $n = max(1, min(200, $n));
                $only_cbia = !empty($u['calc_only_cbia']) ? true : false;
                $sum = cbia_costes_calc_last_posts($n, $only_cbia, false, $cost, $cbia);
                if ($sum) {
                    cbia_costes_log(
                        sprintf(
                            /* translators: 1: posts window size, 2: total posts, 3: posts with real usage, 4: real calls, 5: failed calls, 6: input tokens, 7: output tokens, 8: total EUR. */
                            __('REAL-only last %1$d calculation: posts=%2$d real=%3$d real_calls=%4$d real_fails=%5$d tokens_in=%6$d tokens_out=%7$d total EUR=%8$s', 'cbiastudio-blogflow-ai'),
                            $n,
                            (int)$sum['posts'],
                            (int)$sum['real_posts'],
                            (int)$sum['real_calls'],
                            (int)$sum['real_fails'],
                            (int)$sum['tokens_in'],
                            (int)$sum['tokens_out'],
                            number_format((float)$sum['eur_total'], 4, ',', '.')
                        )
                    );
                } else {
                    /* translators: %d is the posts window size used in the REAL-only calculation. */
                    cbia_costes_log(sprintf(__('REAL-only last %d calculation: no results.', 'cbiastudio-blogflow-ai'), $n));
                }
                $notice = 'calc';
            }

            if ($action === 'calibrate_real') {
                $n = isset($u['calc_last_n']) ? (int)$u['calc_last_n'] : 20;
                $n = max(1, min(200, $n));
                $only_cbia = !empty($u['calc_only_cbia']) ? true : false;

                $actual_eur = isset($u['calibrate_actual_eur']) ? (float)str_replace(',', '.', (string)$u['calibrate_actual_eur']) : 0.0;
                if ($actual_eur > 0) {
                    $sum = cbia_costes_calc_last_posts($n, $only_cbia, false, $cost, $cbia);
                    if ($sum && !empty($sum['eur_total'])) {
                        $estimated = (float)$sum['eur_total'];
                        $suggested = $estimated > 0 ? ($actual_eur / $estimated) : 1.0;
                        if ($suggested < 0.5) $suggested = 0.5;
                        if ($suggested > 1.5) $suggested = 1.5;

                        $cost['real_adjust_multiplier'] = $suggested;
                        update_option(cbia_costes_settings_key(), $cost);

                        $calibration_info = array(
                            'actual_eur' => $actual_eur,
                            'estimated_eur' => $estimated,
                            'suggested' => $suggested,
                        );

                        cbia_costes_log(
                            sprintf(
                                /* translators: 1: billing total in EUR entered by the user, 2: calculated real total in EUR, 3: resulting multiplier. */
                                __('REAL calibration applied: billing=%1$s EUR real_calc=%2$s EUR mult=%3$s', 'cbiastudio-blogflow-ai'),
                                $actual_eur,
                                $estimated,
                                number_format($suggested, 4, ',', '.')
                            )
                        );
                        $notice = 'saved';
                    } else {
                        cbia_costes_log(__('REAL calibration: insufficient data to calculate.', 'cbiastudio-blogflow-ai'));
                        $notice = 'calc';
                    }
                }
            }
        }

        return array($cost, $notice, $calibration_info);
    }
}

/* =========================================================
   ===================== UI TAB: COSTES =====================
   ========================================================= */
if (!function_exists('cbia_render_tab_costes')) {
    function cbia_render_tab_costes(){
        if (!current_user_can('manage_options')) return;

        $view = (defined('CBIA_INCLUDES_DIR') ? CBIA_INCLUDES_DIR . 'admin/views/costs.php' : __DIR__ . '/../admin/views/costs.php');
        if (file_exists($view)) {
            include $view;
            return;
        }

        echo '<p>' . esc_html__('Could not load Costs.', 'cbiastudio-blogflow-ai') . '</p>';
    }
}

/* ------------------------- FIN includes/domain/costs.php ------------------------- */


