<?php
/**
 * Usage parsing and accumulation.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================
   ===================== EXTRACTOR TEXT =====================
   ========================================================= */

if (!function_exists('cbia_extract_text_from_responses_payload')) {
    function cbia_extract_text_from_responses_payload($data) {
        if (!is_array($data)) return '';

        // 1) Campo directo (algunos modelos lo incluyen)
        if (isset($data['output_text']) && is_string($data['output_text'])) {
            $txt = trim($data['output_text']);
            if ($txt !== '') return $txt;
        }

        $parts = array();

        // 2) Estructura habitual Responses: output[] -> content[]
        if (!empty($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $out) {
                if (!is_array($out)) continue;

                // Algunos payloads traen texto directo
                if (isset($out['output_text']) && is_string($out['output_text'])) {
                    $ot = trim($out['output_text']);
                    if ($ot !== '') $parts[] = $ot;
                }

                if (!empty($out['content']) && is_array($out['content'])) {
                    foreach ($out['content'] as $seg) {
                        if (is_string($seg)) {
                            $st = trim($seg);
                            if ($st !== '') $parts[] = $st;
                            continue;
                        }
                        if (!is_array($seg)) continue;

                        // Variantes típicas:
                        // - {type:"output_text", text:"..."}
                        // - {type:"output_text", text:{value:"..."}}
                        // - {type:"message", content:[{type:"output_text", text:"..."}]}
                        if (isset($seg['text'])) {
                            if (is_string($seg['text'])) {
                                $st = trim($seg['text']);
                                if ($st !== '') $parts[] = $st;
                            } elseif (is_array($seg['text']) && isset($seg['text']['value']) && is_string($seg['text']['value'])) {
                                $st = trim($seg['text']['value']);
                                if ($st !== '') $parts[] = $st;
                            }
                        }

                        if (!empty($seg['content']) && is_array($seg['content'])) {
                            foreach ($seg['content'] as $seg2) {
                                if (is_string($seg2)) {
                                    $st = trim($seg2);
                                    if ($st !== '') $parts[] = $st;
                                    continue;
                                }
                                if (!is_array($seg2)) continue;
                                if (isset($seg2['text'])) {
                                    if (is_string($seg2['text'])) {
                                        $st = trim($seg2['text']);
                                        if ($st !== '') $parts[] = $st;
                                    } elseif (is_array($seg2['text']) && isset($seg2['text']['value']) && is_string($seg2['text']['value'])) {
                                        $st = trim($seg2['text']['value']);
                                        if ($st !== '') $parts[] = $st;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $txt = trim(implode("\n", array_filter(array_map('trim', $parts))));
        if ($txt !== '') return $txt;

        // 3) Fallback legacy (Chat Completions-style)
        if (!empty($data['choices'][0]['message']['content'])) {
            $c = $data['choices'][0]['message']['content'];
            if (is_string($c)) {
                $c = trim($c);
                if ($c !== '') return $c;
            }
        }

        // 4) Último recurso: búsqueda recursiva de strings con claves típicas
        $acc = array();
        $max_depth = 6;
        $max_chars = 20000;

        $walker = function($node, $depth) use (&$walker, &$acc, $max_depth, $max_chars) {
            if ($depth > $max_depth) return;
            if (count($acc) > 200) return;

            if (is_string($node)) {
                $st = trim($node);
                if ($st !== '') $acc[] = $st;
                return;
            }

            if (!is_array($node)) return;

            foreach ($node as $k => $v) {
                $kk = is_string($k) ? strtolower($k) : '';
                if ($kk === 'output_text' || $kk === 'text' || $kk === 'content' || $kk === 'value') {
                    if (is_string($v)) {
                        $st = trim($v);
                        if ($st !== '') $acc[] = $st;
                        continue;
                    }
                    if (is_array($v) && isset($v['value']) && is_string($v['value'])) {
                        $st = trim($v['value']);
                        if ($st !== '') $acc[] = $st;
                        continue;
                    }
                }

                $walker($v, $depth + 1);

                // evita acumular demasiado
                $joined = implode("\n", $acc);
                if (strlen($joined) > $max_chars) return;
            }
        };

        $walker($data, 0);

        $txt = trim(implode("\n", array_filter(array_map('trim', $acc))));
        return $txt;
    }
}

if (!function_exists('cbia_usage_from_responses_payload')) {
    function cbia_usage_from_responses_payload($data) {
        $u = ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'total_tokens' => 0];

        if (!is_array($data)) return $u;

        if (!empty($data['usage']) && is_array($data['usage'])) {
            // OpenAI responses usage suele ser input_tokens / output_tokens / total_tokens
            $u['input_tokens']  = (int)($data['usage']['input_tokens'] ?? 0);
            $u['output_tokens'] = (int)($data['usage']['output_tokens'] ?? 0);
            $u['total_tokens']  = (int)($data['usage']['total_tokens'] ?? 0);
            $u['cached_input_tokens'] = (int)($data['usage']['input_tokens_details']['cached_tokens'] ?? 0);
            $u['reasoning_tokens'] = (int)($data['usage']['output_tokens_details']['reasoning_tokens'] ?? 0);

            // algunos payloads usan "total_tokens" solo
            if ($u['total_tokens'] <= 0) {
                $u['total_tokens'] = (int)($data['usage']['total_tokens'] ?? 0);
            }
        }

        return $u;
    }
}

if (!function_exists('cbia_usage_normalize_image_effective_quality')) {
    function cbia_usage_normalize_image_effective_quality($quality) {
        $quality = strtolower(trim((string)$quality));
        return in_array($quality, array('low', 'medium', 'high'), true) ? $quality : null;
    }
}

if (!function_exists('cbia_usage_normalize_image_size')) {
    function cbia_usage_normalize_image_size($size, $allow_auto = false) {
        $size = strtolower(trim((string)$size));
        if ($allow_auto && $size === 'auto') return 'auto';
        return preg_match('/^\d{2,5}x\d{2,5}$/', $size) ? $size : null;
    }
}

if (!function_exists('cbia_usage_from_images_payload')) {
    function cbia_usage_from_images_payload($data, $requested_quality = 'auto', $requested_size = '') {
        $requested_quality = strtolower(trim((string)$requested_quality));
        if (!in_array($requested_quality, array('auto', 'low', 'medium', 'high'), true)) $requested_quality = 'auto';
        $requested_size = cbia_usage_normalize_image_size($requested_size, true);
        $effective_quality = is_array($data) ? cbia_usage_normalize_image_effective_quality($data['quality'] ?? null) : null;
        if ($effective_quality === null && $requested_quality !== 'auto') $effective_quality = $requested_quality;
        $effective_size = is_array($data) ? cbia_usage_normalize_image_size($data['size'] ?? null) : null;
        if ($effective_size === null && $requested_size !== null && $requested_size !== 'auto') $effective_size = $requested_size;
        $output_format = is_array($data) ? strtolower(trim((string)($data['output_format'] ?? ''))) : '';
        $background = is_array($data) ? strtolower(trim((string)($data['background'] ?? ''))) : '';
        if (!preg_match('/^[a-z0-9_-]{1,32}$/', $output_format)) $output_format = '';
        if (!preg_match('/^[a-z0-9_-]{1,32}$/', $background)) $background = '';

        $u = array(
            'input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0,
            'image_input_tokens' => 0, 'text_input_tokens' => 0, 'image_output_tokens' => 0,
            'input_image_tokens' => 0, 'input_text_tokens' => 0, 'output_image_tokens' => 0,
            'cached_image_input_tokens' => 0, 'cached_text_input_tokens' => 0,
            'requested_quality' => $requested_quality,
            'effective_quality' => $effective_quality,
            'quality_requested' => $requested_quality,
            'quality_effective' => $effective_quality,
            'quality' => $effective_quality !== null ? $effective_quality : $requested_quality,
            'requested_size' => $requested_size,
            'effective_size' => $effective_size,
            'size' => $effective_size !== null ? $effective_size : (string)$requested_size,
            'output_format' => $output_format,
            'background' => $background,
        );
        if (!is_array($data) || empty($data['usage']) || !is_array($data['usage'])) return $u;
        $usage = $data['usage'];
        $details = is_array($usage['input_tokens_details'] ?? null) ? $usage['input_tokens_details'] : array();
        $output_details = is_array($usage['output_tokens_details'] ?? null) ? $usage['output_tokens_details'] : array();
        $u['input_tokens'] = max(0, (int)($usage['input_tokens'] ?? 0));
        $u['cached_input_tokens'] = max(0, (int)($details['cached_tokens'] ?? 0));
        $u['output_tokens'] = max(0, (int)($usage['output_tokens'] ?? 0));
        $u['image_input_tokens'] = max(0, (int)($details['image_tokens'] ?? 0));
        $u['text_input_tokens'] = max(0, (int)($details['text_tokens'] ?? 0));
        $u['image_output_tokens'] = max(0, (int)($output_details['image_tokens'] ?? $u['output_tokens']));
        $u['input_image_tokens'] = $u['image_input_tokens'];
        $u['input_text_tokens'] = $u['text_input_tokens'];
        $u['output_image_tokens'] = $u['image_output_tokens'];
        $u['cached_image_input_tokens'] = max(0, (int)($details['cached_image_tokens'] ?? 0));
        $u['cached_text_input_tokens'] = max(0, (int)($details['cached_text_tokens'] ?? 0));
        return $u;
    }
}

/* =========================================================
   ================== USAGE: ACUMULACIÓN ====================
   ========================================================= */

if (!function_exists('cbia_usage_empty')) {
    function cbia_usage_empty() {
        return array(
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'cache_hit_tokens' => 0,
            'cache_miss_tokens' => 0,
            'cache_breakdown_available' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'total_tokens' => 0,
        );
    }
}

if (!function_exists('cbia_usage_normalize')) {
    function cbia_usage_normalize($usage) {
        $u = cbia_usage_empty();
        if (is_array($usage)) {
            $u['input_tokens']  = (int)($usage['input_tokens'] ?? 0);
            $u['cached_input_tokens'] = (int)($usage['cached_input_tokens'] ?? ($usage['cache_hit_tokens'] ?? 0));
            $u['cache_hit_tokens'] = (int)($usage['cache_hit_tokens'] ?? $u['cached_input_tokens']);
            $u['cache_miss_tokens'] = (int)($usage['cache_miss_tokens'] ?? 0);
            $u['cache_breakdown_available'] = !empty($usage['cache_breakdown_available']) ? 1 : 0;
            $u['output_tokens'] = (int)($usage['output_tokens'] ?? 0);
            $u['reasoning_tokens'] = (int)($usage['reasoning_tokens'] ?? 0);
            $u['total_tokens']  = (int)($usage['total_tokens'] ?? 0);
        }
        foreach ($u as $key => $value) $u[$key] = max(0, (int)$value);
        if ($u['total_tokens'] <= 0) $u['total_tokens'] = $u['input_tokens'] + $u['output_tokens'];
        return $u;
    }
}

/**
 * Guarda cada llamada (texto) para poder calcular coste real luego.
 * - Meta: _cbia_usage_calls (JSON)
 * - Agregados: _cbia_tokens_input_sum / _cbia_tokens_output_sum / _cbia_tokens_total_sum
 */
if (!function_exists('cbia_usage_append_call')) {
    function cbia_usage_append_call($post_id, $context, $model, $usage, $extra = array()) {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return false;

        $u = cbia_usage_normalize($usage);

        $ctx = sanitize_key((string)$context);
        $mdl = sanitize_text_field((string)$model);

        $raw = get_post_meta($post_id, '_cbia_usage_calls', true);
        $list = array();
        if ($raw) {
            $tmp = json_decode((string)$raw, true);
            if (is_array($tmp)) $list = $tmp;
        }

        $item = array_merge(array(
            'ts'           => current_time('mysql'),
            'context'      => $ctx,
            'model'        => $mdl,
            'input_tokens' => (int)$u['input_tokens'],
            'cached_input_tokens' => (int)$u['cached_input_tokens'],
            'cache_hit_tokens' => (int)$u['cache_hit_tokens'],
            'cache_miss_tokens' => (int)$u['cache_miss_tokens'],
            'cache_breakdown_available' => (int)$u['cache_breakdown_available'],
            'output_tokens'=> (int)$u['output_tokens'],
            'reasoning_tokens' => (int)$u['reasoning_tokens'],
            'total_tokens' => (int)$u['total_tokens'],
        ), is_array($extra) ? $extra : array());

        $list[] = $item;

        // Mantener tamaño razonable
        if (count($list) > 200) $list = array_slice($list, -200);

        update_post_meta($post_id, '_cbia_usage_calls', wp_json_encode($list));

        // Agregados globales
        $in_sum  = (int)get_post_meta($post_id, '_cbia_tokens_input_sum', true);
        $out_sum = (int)get_post_meta($post_id, '_cbia_tokens_output_sum', true);
        $tot_sum = (int)get_post_meta($post_id, '_cbia_tokens_total_sum', true);

        $in_sum  += (int)$u['input_tokens'];
        $out_sum += (int)$u['output_tokens'];
        $tot_sum += (int)$u['total_tokens'];

        update_post_meta($post_id, '_cbia_tokens_input_sum', (string)$in_sum);
        update_post_meta($post_id, '_cbia_tokens_output_sum', (string)$out_sum);
        update_post_meta($post_id, '_cbia_tokens_total_sum', (string)$tot_sum);

        return true;
    }
}

/**
 * Guarda llamadas a imágenes (para coste por imagen y trazabilidad).
 * - Meta: _cbia_image_calls (JSON)
 * - Agregados: _cbia_images_total / _cbia_images_ok / _cbia_images_fail
 */
if (!function_exists('cbia_image_append_call')) {
    function cbia_image_append_call($post_id, $section, $model, $ok, $attach_id = 0, $err = '') {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return false;

        $raw = get_post_meta($post_id, '_cbia_image_calls', true);
        $list = array();
        if ($raw) {
            $tmp = json_decode((string)$raw, true);
            if (is_array($tmp)) $list = $tmp;
        }

        $list[] = array(
            'ts'        => current_time('mysql'),
            'section'   => sanitize_key((string)$section),
            'model'     => sanitize_text_field((string)$model),
            'ok'        => $ok ? 1 : 0,
            'attach_id' => (int)$attach_id,
            'error'     => sanitize_text_field((string)$err),
        );

        if (count($list) > 200) $list = array_slice($list, -200);

        update_post_meta($post_id, '_cbia_image_calls', wp_json_encode($list));

        $total = (int)get_post_meta($post_id, '_cbia_images_total', true);
        $okc   = (int)get_post_meta($post_id, '_cbia_images_ok', true);
        $fail  = (int)get_post_meta($post_id, '_cbia_images_fail', true);

        $total++;
        if ($ok) $okc++; else $fail++;

        update_post_meta($post_id, '_cbia_images_total', (string)$total);
        update_post_meta($post_id, '_cbia_images_ok', (string)$okc);
        update_post_meta($post_id, '_cbia_images_fail', (string)$fail);

        return true;
    }
}
