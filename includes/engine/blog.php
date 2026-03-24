<?php
// File: includes/engine/blog.php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * TAB BLOG (v9.2 FIX)
 *
 * FIXES (lo que te fallaba):
 * 1) Si cbia_log_message NO estÃƒÆ’Ã‚Â¡ cargada (por orden de includes),
 *    este archivo ahora trae un logger ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œfallbackÃƒÂ¢Ã¢â€šÂ¬Ã‚Â para que SIEMPRE haya log.
 * 2) El botÃƒÆ’Ã‚Â³n "Crear Blogs" ahora ARRANCA de verdad:
 *    - Ejecuta 1 tanda inmediata (para que veas log al instante)
 *    - y re-encola el evento para continuar en background si queda cola.
 * 3) Log ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œen vivoÃƒÂ¢Ã¢â€šÂ¬Ã‚Â:
 *    - AÃƒÆ’Ã‚Â±ade contador anti-cache y nocache_headers()
 *    - Endpoint wp_ajax_cbia_get_log se registra ahora en core/hooks.php.
 *
 * IMPORTANTE:
 * - Si tu hosting tiene WP-CRON bloqueado, al menos verÃƒÆ’Ã‚Â¡s la primera tanda,
 *   y podrÃƒÆ’Ã‚Â¡s re-lanzar con el botÃƒÆ’Ã‚Â³n para seguir.
 */

/* =========================================================
   =================== FALLBACK LOG (SI FALTA) ==============
   ========================================================= */
if (!function_exists('cbia_log_message')) {
    function cbia_log_message($message) {
        $message = (string)$message;
        $level = 'INFO';
        if (preg_match('/^\s*\[(DEBUG|INFO|WARN|WARNING|ERROR)\]\s*/i', $message, $m)) {
            $level = strtoupper($m[1]);
            if ($level === 'WARNING') $level = 'WARN';
            $message = preg_replace('/^\s*\[(DEBUG|INFO|WARN|WARNING|ERROR)\]\s*/i', '', $message);
        }
        if (function_exists('cbia_log')) {
            cbia_log((string)$message, $level);
            return;
        }

        $ts = current_time('mysql');
        $line = "[{$ts}] {$message}\n";

        $log = (string)get_option(cbia_log_key(), '');
        $log .= $line;
        if (strlen($log) > 250000) $log = substr($log, -250000);

        update_option(cbia_log_key(), $log, false);

        // contador anti-cache
        $c = (int)get_option(cbia_log_counter_key(), 0);
        update_option(cbia_log_counter_key(), $c + 1, false);

        wp_cache_delete(cbia_log_key(), 'options');
        wp_cache_delete(cbia_log_counter_key(), 'options');

    }
}

// cbia_clear_log y cbia_get_log viven en el nÃƒÆ’Ã‚Âºcleo.

/* =========================================================
   =================== STOP FLAG (fallback) =================
   ========================================================= */
if (!function_exists('cbia_stop_flag_key')) {
    function cbia_stop_flag_key(){
        if (defined('CBIA_OPTION_STOP')) return CBIA_OPTION_STOP;
        return 'cbia_stop_generation';
    }
}
if (!function_exists('cbia_set_stop_flag')) {
    function cbia_set_stop_flag($on) {
        update_option(cbia_stop_flag_key(), $on ? 1 : 0, false);
        wp_cache_delete(cbia_stop_flag_key(), 'options');
    }
}
if (!function_exists('cbia_check_stop_flag')) {
    function cbia_check_stop_flag() {
        return !empty(get_option(cbia_stop_flag_key(), 0));
    }
}

/* =========================================================
   =================== HELPERS: LAST SCHEDULED =============
   ========================================================= */
if (!function_exists('cbia_get_last_scheduled_at')) {
    function cbia_get_last_scheduled_at() {
        return (string) get_option('_cbia_last_scheduled_at', '');
    }
}
if (!function_exists('cbia_set_last_scheduled_at')) {
    function cbia_set_last_scheduled_at($datetime) {
        if ($datetime) update_option('_cbia_last_scheduled_at', $datetime, false);
    }
}

/* =========================================================
   =================== HELPERS: CHECKPOINT =================
   ========================================================= */
if (!function_exists('cbia_checkpoint_clear')) {
    function cbia_checkpoint_clear(){ delete_option('cbia_checkpoint'); }
}
if (!function_exists('cbia_checkpoint_get')) {
    function cbia_checkpoint_get(){
        $cp = get_option('cbia_checkpoint', array());
        return is_array($cp) ? $cp : array();
    }
}
if (!function_exists('cbia_checkpoint_save')) {
    function cbia_checkpoint_save($cp){ update_option('cbia_checkpoint', $cp, false); }
}

/* =========================================================
   =================== POST HANDLER (BLOG TAB) ==============
   ========================================================= */
if (!function_exists('cbia_blog_handle_post')) {
    function cbia_blog_handle_post() {
        if (function_exists('cbia_container')) {
            $container = cbia_container();
            if ($container) {
                $service = $container->get('blog_service');
                if ($service && method_exists($service, 'handle_post')) {
                    return $service->handle_post();
                }
            }
        }
        if (!is_admin() || !current_user_can('manage_options')) return '';
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') return '';

        $post_unslashed = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
        $saved_notice = '';

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());

        if (!empty($post_unslashed['cbia_form']) && $post_unslashed['cbia_form'] === 'blog_save' && check_admin_referer('cbia_blog_save_nonce')) {
            $mode = sanitize_key((string)($post_unslashed['title_input_mode'] ?? 'manual'));
            $settings['title_input_mode'] = in_array($mode, array('manual','csv'), true) ? $mode : 'manual';

            $settings['manual_titles'] = sanitize_textarea_field((string)($post_unslashed['manual_titles'] ?? ''));
            $settings['csv_url'] = esc_url_raw(trim((string)($post_unslashed['csv_url'] ?? '')));

            $dt_local = sanitize_text_field(trim((string)($post_unslashed['first_publication_datetime_local'] ?? '')));
            if ($dt_local !== '') {
                $dt_local = str_replace('T',' ', $dt_local);
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $dt_local)) $dt_local .= ':00';
                $settings['first_publication_datetime'] = $dt_local;
            } else {
                $settings['first_publication_datetime'] = '';
            }

            $settings['publication_interval'] = max(1, intval($post_unslashed['publication_interval'] ?? 5));
            $settings['enable_cron_fill'] = !empty($post_unslashed['enable_cron_fill']) ? 1 : 0;

            update_option('cbia_settings', $settings, false);

            cbia_log_message("[INFO] Blog: configuraciÃƒÆ’Ã‚Â³n guardada (tÃƒÆ’Ã‚Â­tulos + automatizaciÃƒÆ’Ã‚Â³n).");
            $saved_notice = 'guardado';
        }

        if (!empty($post_unslashed['cbia_form']) && $post_unslashed['cbia_form'] === 'blog_actions' && check_admin_referer('cbia_blog_actions_nonce')) {
            $action = sanitize_text_field((string)($post_unslashed['cbia_action'] ?? ''));

            if ($action === 'test_config') {
                if (function_exists('cbia_run_test_configuration')) cbia_run_test_configuration();
                else cbia_log_message('[WARN] Missing cbia_run_test_configuration().');
                $saved_notice = 'test';

            } elseif ($action === 'stop_generation') {
                cbia_set_stop_flag(true);
                cbia_log_message("[INFO] Stop enabled by user.");
                $saved_notice = 'stop';

            } elseif ($action === 'fill_pending_imgs') {
                cbia_set_stop_flag(false);
                if (function_exists('cbia_run_fill_pending_images')) cbia_run_fill_pending_images(10);
                else cbia_log_message('[WARN] Missing cbia_run_fill_pending_images().');
                $saved_notice = 'pending';

            } elseif ($action === 'clear_checkpoint') {
                cbia_checkpoint_clear();
                delete_option('_cbia_last_scheduled_at');
                cbia_log_message("[INFO] Checkpoint cleared + _cbia_last_scheduled_at reset.");
                $saved_notice = 'checkpoint';

            } elseif ($action === 'clear_log') {
                cbia_clear_log();
                cbia_log_message("[INFO] Log cleared manually.");
                $saved_notice = 'log';
            }
        }

        return $saved_notice;
    }
}

/* =========================================================
   =================== GET TITLES (manual/CSV) =============
   ========================================================= */
if (!function_exists('cbia_get_titles')) {
    function cbia_get_titles(){
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $mode = $settings['title_input_mode'] ?? 'manual';

        if ($mode === 'manual') {
            $manual = (string)($settings['manual_titles'] ?? '');
            $arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $manual)));
            cbia_log_message("[INFO] Manual titles loaded: ".count($arr));
            return $arr;
        }

        if ($mode === 'csv') {
            $csv_url = trim((string)($settings['csv_url'] ?? ''));
            if ($csv_url === '') {
                cbia_log_message("[ERROR] CSV mode: missing URL.");
                return array();
            }

            $resp = wp_remote_get($csv_url, array('timeout' => 25));
            if (is_wp_error($resp)) {
                cbia_log_message("[ERROR] CSV error: ".$resp->get_error_message());
                return array();
            }
            $body = (string) wp_remote_retrieve_body($resp);
            $lines = preg_split('/\r\n|\r|\n/', $body);

            $out = array();
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (stripos($line, 'titulo') !== false || stripos($line, 'tÃƒÆ’Ã‚Â­tulo') !== false) continue;
                $out[] = $line;
            }
            $out = array_values(array_unique(array_filter(array_map('trim', $out))));
            cbia_log_message("[INFO] Titles loaded from CSV: ".count($out));
            return $out;
        }

        cbia_log_message("[ERROR] Invalid title input mode.");
        return array();
    }
}

/* =========================================================
   =================== PREPARE QUEUE ========================
   ========================================================= */
if (!function_exists('cbia_prepare_queue_from_titles')) {
    function cbia_prepare_queue_from_titles($titles){
        $queue = array();
        foreach ((array)$titles as $t) {
            $t = trim((string)$t);
            if ($t === '') continue;

            if (function_exists('cbia_post_exists_by_title') && cbia_post_exists_by_title($t)) {
                cbia_log_message("[INFO] El post '{$t}' already exists. Skipped (queue).");
                continue;
            }

            $queue[] = $t;
        }
        $queue = array_values(array_unique($queue));
        return $queue;
    }
}

/* =========================================================
   =================== COMPUTE NEXT DATETIME ===============
   ========================================================= */
if (!function_exists('cbia_compute_next_datetime')) {
    function cbia_compute_next_datetime($interval_days){
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $first_dt = trim((string)($settings['first_publication_datetime'] ?? ''));
        $last = cbia_get_last_scheduled_at();

        if ($last === '') {
            if ($first_dt !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $first_dt)) return $first_dt;
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $first_dt)) return $first_dt . ':00';
            }
            return '';
        }

        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());
            $dt = new DateTime($last, $tz);
            $dt->modify('+' . max(1, (int)$interval_days) . ' day');
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            cbia_log_message("[ERROR] Error calculando prÃƒÆ’Ã‚Â³xima fecha: ".$e->getMessage());
            return '';
        }
    }
}

/* =========================================================
   =================== EVENT SCHEDULING HELPERS =============
   ========================================================= */
if (!function_exists('cbia_schedule_generation_event')) {
    function cbia_schedule_generation_event($delay_seconds = 5, $force = false){
        $delay_seconds = max(1, (int)$delay_seconds);

        if ($force) {
            wp_clear_scheduled_hook('cbia_generation_event');
        }

        if (!wp_next_scheduled('cbia_generation_event')) {
            wp_schedule_single_event(time() + $delay_seconds, 'cbia_generation_event');
            cbia_log_message("[INFO] Event enqueued in {$delay_seconds}s.");
        } else {
            cbia_log_message("[DEBUG] Event already in queue (not duplicated).");
        }
    }
}

/* =========================================================
   =================== BATCH con CHECKPOINT =================
   ========================================================= */
if (!function_exists('cbia_create_all_posts_checkpointed')) {
    function cbia_create_all_posts_checkpointed($incoming_titles=null, $max_per_run = 1){

        if (!function_exists('cbia_create_single_blog_post')) {
            cbia_log_message("[ERROR] Missing cbia_create_single_blog_post() (engine). Check includes/engine/engine.php and its include.");
            return array('done'=>true,'processed'=>0);
        }

        cbia_set_stop_flag(false);

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $interval_days = max(1, intval($settings['publication_interval'] ?? 5));

        $cp = cbia_checkpoint_get();

        if (!$incoming_titles && !empty($cp) && !empty($cp['running']) && isset($cp['queue']) && is_array($cp['queue'])) {
            cbia_log_message("[INFO] Resuming from checkpoint: ".count($cp['queue'])." en cola, idx=".intval($cp['idx'] ?? 0).".");
            $queue = $cp['queue'];
            $idx   = intval($cp['idx'] ?? 0);
        } else {
            $titles = $incoming_titles ?? cbia_get_titles();
            if (empty($titles)) {
                cbia_log_message("[INFO] Sin tÃƒÆ’Ã‚Â­tulos. Fin.");
                return array('done'=>true,'processed'=>0);
            }
            $queue = cbia_prepare_queue_from_titles($titles);
            $idx = 0;
            $cp = array('queue'=>$queue,'idx'=>$idx,'created_total'=>0,'running'=>true);
            cbia_checkpoint_save($cp);
            cbia_log_message("[INFO] Checkpoint created. Starting batch... queue=".count($queue));
        }

        if (empty($queue)) {
            cbia_log_message("[INFO] No hay tÃƒÆ’Ã‚Â­tulos nuevos. Fin.");
            cbia_checkpoint_clear();
            return array('done'=>true,'processed'=>0);
        }

        $max_per_run = max(1, (int)$max_per_run);
        $processed_this_run = 0;

        foreach ($queue as $i => $title) {

            if (cbia_check_stop_flag()) {
                cbia_log_message("[INFO] Stopped during batch (STOP).");
                break;
            }

            if ($i < $idx) continue;

            $title = trim((string)$title);
            if ($title === '') {
                $cp['idx'] = $i + 1;
                cbia_checkpoint_save($cp);
                continue;
            }

            $next_dt = cbia_compute_next_datetime($interval_days);

            if ($next_dt === '') {
                cbia_log_message("[INFO] Creating post: {$title} | Published now");
                $result = cbia_create_single_blog_post($title, null);
                if (is_array($result) && !empty($result['ok'])) {
                    $post_id = (int)($result['post_id'] ?? 0);
                    $now_local = current_time('mysql');
                    cbia_set_last_scheduled_at($now_local);
                    $cp['created_total']++;
                } else {
                    $err = is_array($result) ? (string)($result['error'] ?? '') : '';
                    cbia_log_message("[ERROR] Could not create '{$title}'." . ($err !== '' ? " {$err}" : ''));
                }
            } else {
                cbia_log_message("[INFO] Creating post: {$title} | Scheduled: {$next_dt}");
                $result = cbia_create_single_blog_post($title, $next_dt);
                if (is_array($result) && !empty($result['ok'])) {
                    $post_id = (int)($result['post_id'] ?? 0);
                    cbia_set_last_scheduled_at($next_dt);
                    $cp['created_total']++;
                } else {
                    $err = is_array($result) ? (string)($result['error'] ?? '') : '';
                    cbia_log_message("[ERROR] Could not schedule '{$title}'." . ($err !== '' ? " {$err}" : ''));
                }
            }

            $cp['idx'] = $i + 1;
            cbia_checkpoint_save($cp);

            $processed_this_run++;

            if ($processed_this_run >= $max_per_run) {
                cbia_log_message("[INFO] Batch completed: processed_this_run={$processed_this_run}. Se continuarÃƒÆ’Ã‚Â¡ en el siguiente evento.");
                break;
            }
        }

        $queue_count = count((array)($cp['queue'] ?? array()));
        $idx_now = intval($cp['idx'] ?? 0);

        if ($queue_count > 0 && $idx_now >= $queue_count) {
            cbia_log_message("[INFO] Queue finished. Total created: ".intval($cp['created_total']));
            $cp['running'] = false;
            cbia_checkpoint_save($cp);
            cbia_checkpoint_clear();
            return array('done'=>true,'processed'=>$processed_this_run);
        }

        cbia_log_message("[INFO] Queue pending. Checkpoint idx={$idx_now}/{$queue_count}. Total creados=".intval($cp['created_total']));
        return array('done'=>false,'processed'=>$processed_this_run);
    }
}

/* =========================================================
   =================== ACTION: RUN GENERATION ===============
   ========================================================= */
if (!function_exists('cbia_run_generate_blogs')) {
    function cbia_run_generate_blogs($max_per_run = 1){
        cbia_log_message("[DEBUG] cbia_run_generate_blogs llamada.");
        cbia_log_message("[INFO] Iniciando creaciÃƒÂ³n de blog (modo single)...");

        $titles = cbia_get_titles();
        if (empty($titles)) {
            cbia_log_message("[INFO] No titles. End.");
            return array('done' => true, 'processed' => 0);
        }

        $title = trim((string) ($titles[0] ?? ''));
        if ($title === '') {
            cbia_log_message("[INFO] Empty title. End.");
            return array('done' => true, 'processed' => 0);
        }

        cbia_checkpoint_clear();
        $result = cbia_create_all_posts_checkpointed(array($title), 1);
        cbia_checkpoint_clear();

        cbia_log_message("[INFO] Call finished (single mode).");
        return $result;
    }
}

/* =========================================================
   =================== EVENT: RUN GENERATION ===============
   ========================================================= */
if (!has_action('cbia_generation_event')) {
    add_action('cbia_generation_event', function () {
        cbia_log_message('[INFO] Running batch in event (background)ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦');
        cbia_run_generate_blogs(1);
        cbia_log_message('[INFO] Background event finished.');
    });
}

/* =========================================================
   ======================= TAB BLOG UI ======================
   ========================================================= */
if (!function_exists('cbia_render_tab_blog')) {
    function cbia_render_tab_blog(){
        if (!current_user_can('manage_options')) return;

        $view = (defined('CBIA_INCLUDES_DIR') ? CBIA_INCLUDES_DIR . 'admin/views/blog.php' : __DIR__ . '/../admin/views/blog.php');
        if (file_exists($view)) {
            include $view;
            return;
        }

        echo '<p>No se pudo cargar Blog.</p>';
    }
}
