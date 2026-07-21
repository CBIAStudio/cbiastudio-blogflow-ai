<?php
// File: includes/engine/blog.php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * TAB BLOG (v9.2 FIX)
 *
 * FIXES (lo que te fallaba):
 * 1) Si cbia_log_message NO estÃ¡ cargada (por orden de includes),
 *    este archivo ahora trae un logger â€œfallbackâ€ para que SIEMPRE haya log.
 * 2) El botÃ³n "Crear Blogs" ahora ARRANCA de verdad:
 *    - Ejecuta 1 tanda inmediata (para que veas log al instante)
 *    - y re-encola el evento para continuar en background si queda cola.
 * 3) Log â€œen vivoâ€:
 *    - AÃ±ade contador anti-cache y nocache_headers()
 *    - Endpoint wp_ajax_cbia_get_log se registra ahora en core/hooks.php.
 *
 * IMPORTANTE:
 * - Si tu hosting tiene WP-CRON bloqueado, al menos verÃ¡s la primera tanda,
 *   y podrÃ¡s re-lanzar con el botÃ³n para seguir.
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

        if (function_exists('error_log')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Mirrors plugin log to PHP error log when available.
            error_log('[CBIA] ' . trim($message));
        }
    }
}

// cbia_clear_log y cbia_get_log viven en el nÃºcleo.

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
        $datetime = trim((string)$datetime);
        if ($datetime !== '') {
            update_option('_cbia_last_scheduled_at', $datetime, false);
        } else {
            delete_option('_cbia_last_scheduled_at');
        }
        wp_cache_delete('_cbia_last_scheduled_at', 'options');
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
if (!function_exists('cbia_checkpoint_has_pending_queue')) {
    function cbia_checkpoint_has_pending_queue($cp = null): bool {
        if ($cp === null) {
            $cp = cbia_checkpoint_get();
        }
        if (!is_array($cp) || empty($cp['running']) || empty($cp['queue']) || !is_array($cp['queue'])) {
            return false;
        }
        $idx = max(0, (int)($cp['idx'] ?? 0));
        return $idx < count((array)$cp['queue']);
    }
}
if (!function_exists('cbia_blog_generation_lock_key')) {
    function cbia_blog_generation_lock_key() {
        return 'cbia_blog_generation_lock';
    }
}
if (!function_exists('cbia_blog_generation_get_lock')) {
    function cbia_blog_generation_get_lock(): array {
        $existing = get_option(cbia_blog_generation_lock_key(), array());
        return is_array($existing) ? $existing : array();
    }
}
if (!function_exists('cbia_blog_generation_lock_ttl')) {
    function cbia_blog_generation_lock_ttl() {
        $ttl = (int)apply_filters('cbia_blog_generation_lock_ttl', 15 * MINUTE_IN_SECONDS);
        if ($ttl < 60) $ttl = 60;
        return $ttl;
    }
}
if (!function_exists('cbia_blog_generation_acquire_lock')) {
    function cbia_blog_generation_acquire_lock($run_id = '') {
        $run_id = trim((string)$run_id);
        if ($run_id === '') {
            $run_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('cbia-blog-', true);
        }
        $key = cbia_blog_generation_lock_key();
        $ttl = cbia_blog_generation_lock_ttl();
        $now = time();
        $existing = get_option($key, array());
        if (is_array($existing) && !empty($existing['locked_at'])) {
            $locked_at = (int)$existing['locked_at'];
            if ($locked_at > 0 && ($now - $locked_at) < $ttl) {
                return false;
            }
        }
        update_option($key, array(
            'run_id' => $run_id,
            'locked_at' => $now,
        ), false);
        return true;
    }
}
if (!function_exists('cbia_blog_generation_release_lock')) {
    function cbia_blog_generation_release_lock($run_id = '') {
        $key = cbia_blog_generation_lock_key();
        $existing = get_option($key, array());
        if (!is_array($existing) || empty($existing)) {
            return;
        }
        $existing_run_id = (string)($existing['run_id'] ?? '');
        $run_id = trim((string)$run_id);
        if ($run_id !== '' && $existing_run_id !== '' && $existing_run_id !== $run_id) {
            return;
        }
        delete_option($key);
    }
}
if (!function_exists('cbia_pro_normalize_csv_url')) {
    function cbia_pro_normalize_csv_url($url){
        $url = trim((string)$url);
        if ($url === '') return '';
        if (preg_match('#^https?://drive\.google\.com/file/d/([^/]+)/#i', $url, $m)) {
            return 'https://drive.google.com/uc?export=download&id=' . rawurlencode($m[1]);
        }
        if (preg_match('#^https?://docs\.google\.com/spreadsheets/d/([^/]+)/#i', $url, $m)) {
            return 'https://docs.google.com/spreadsheets/d/' . rawurlencode($m[1]) . '/export?format=csv';
        }
        return $url;
    }
}
if (!function_exists('cbia_pro_is_public_ip')) {
    function cbia_pro_is_public_ip($ip) {
        $ip = trim((string)$ip);
        if ($ip === '') return false;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
if (!function_exists('cbia_pro_validate_remote_csv_url')) {
    function cbia_pro_validate_remote_csv_url($url){
        $url = cbia_pro_normalize_csv_url($url);
        if ($url === '' || !function_exists('wp_http_validate_url') || !wp_http_validate_url($url)) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts)) return '';
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, array('http', 'https'), true)) return '';
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || in_array($host, array('localhost', '127.0.0.1', '::1'), true)) return '';
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return cbia_pro_is_public_ip($host) ? $url : '';
        }
        $ips = @gethostbynamel($host);
        if (!is_array($ips) || empty($ips)) return '';
        foreach ($ips as $ip) {
            if (!cbia_pro_is_public_ip($ip)) return '';
        }
        return $url;
    }
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
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD']))) : '';
        if ($request_method !== 'POST') return '';

        $post_unslashed = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
        $saved_notice = '';

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());

        if (!empty($post_unslashed['cbia_form']) && $post_unslashed['cbia_form'] === 'blog_save' && check_admin_referer('cbia_blog_save_nonce')) {
            $mode = (string)($post_unslashed['title_input_mode'] ?? 'manual');
            $settings['title_input_mode'] = in_array($mode, array('manual','csv'), true) ? $mode : 'manual';

            $settings['manual_titles'] = (string)($post_unslashed['manual_titles'] ?? '');
            $settings['csv_url'] = trim((string)($post_unslashed['csv_url'] ?? ''));

            $dt_local = trim((string)($post_unslashed['first_publication_datetime_local'] ?? ''));
            if ($dt_local !== '') {
                $dt_local = str_replace('T',' ', $dt_local);
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $dt_local)) $dt_local .= ':00';
                $settings['first_publication_datetime'] = $dt_local;
            } else {
                $settings['first_publication_datetime'] = '';
            }

            $settings['publication_interval'] = max(1, intval($post_unslashed['publication_interval'] ?? 5));
            if (array_key_exists('blog_posts_per_event', $post_unslashed)) {
                $settings['blog_posts_per_event'] = max(1, min(5, intval($post_unslashed['blog_posts_per_event'] ?? 1)));
            }
            $settings['enable_cron_fill'] = !empty($post_unslashed['enable_cron_fill']) ? 1 : 0;

            update_option('cbia_settings', $settings, false);

            cbia_log_message("[INFO] Blog: settings saved (titles + automation).");
            $saved_notice = 'saved';
        }

        if (!empty($post_unslashed['cbia_form']) && $post_unslashed['cbia_form'] === 'blog_actions' && check_admin_referer('cbia_blog_actions_nonce')) {
            $action = sanitize_text_field((string)($post_unslashed['cbia_action'] ?? ''));

            if ($action === 'test_config') {
                if (function_exists('cbia_run_test_configuration')) cbia_run_test_configuration();
                else cbia_log_message('[WARN] Missing cbia_run_test_configuration().');
                $saved_notice = 'test';

            } elseif ($action === 'stop_generation') {
                cbia_set_stop_flag(true);
                wp_clear_scheduled_hook('cbia_generation_event');
                cbia_log_message("[INFO] Stop enabled by user.");
                $saved_notice = 'stop';

            } elseif ($action === 'fill_pending_imgs') {
                cbia_set_stop_flag(false);
                if (function_exists('cbia_run_fill_pending_images')) cbia_run_fill_pending_images(10);
                else cbia_log_message('[WARN] Missing cbia_run_fill_pending_images().');
                $saved_notice = 'pending';

            } elseif ($action === 'clear_checkpoint') {
                cbia_checkpoint_clear();
                cbia_blog_generation_release_lock();
                delete_option('_cbia_last_scheduled_at');
                cbia_log_message("[INFO] Checkpoint cleared + generation lock + _cbia_last_scheduled_at reset.");
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
            if (function_exists('cbia_cap_enabled') && !cbia_cap_enabled('runtime_advanced')) {
                $arr = array_slice(array_values($arr), 0, 1);
            }
            cbia_log_message("[INFO] Titles loaded manually: ".count($arr));
            return $arr;
        }

        if ($mode === 'csv') {
            $csv_url = trim((string)($settings['csv_url'] ?? ''));
            $csv_url = cbia_pro_validate_remote_csv_url($csv_url);
            if ($csv_url === '') {
                cbia_log_message("[ERROR] CSV mode: invalid or blocked URL.");
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
            foreach ($lines as $idx => $line) {
                $line = trim((string)$line);
                if ($line === '') continue;
                $row = str_getcsv($line);
                $first = trim((string)($row[0] ?? ''));
                if ($first === '') continue;
                $first_l = strtolower($first);
                if ($idx === 0 && in_array($first_l, array('title', 'titulo', 'título'), true)) continue;
                $out[] = $first;
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
                cbia_log_message("[INFO] Post '{$t}' already exists. Skipped (queue).");
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
    function cbia_compute_next_datetime($interval_days, $last_override = null){
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $first_dt = trim((string)($settings['first_publication_datetime'] ?? ''));
        $last = ($last_override === null) ? cbia_get_last_scheduled_at() : trim((string)$last_override);
        $normalize_candidate = function ($candidate, $source_label) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') return '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $candidate)) {
                $candidate .= ':00';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $candidate)) {
                return '';
            }
            try {
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());
                $dt = new DateTime($candidate, $tz);
                $now = new DateTime(current_time('mysql'), $tz);
                if ($dt < $now) {
                    cbia_log_message("[WARN] Past publication date from {$source_label}: {$candidate}. WordPress will publish immediately using that historical date.");
                }
                return $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                cbia_log_message("[ERROR] Error validating publication date: ".$e->getMessage());
                return '';
            }
        };

        if ($last === '') {
            if ($first_dt !== '') {
                return $normalize_candidate($first_dt, 'first_publication_datetime');
            }
            return '';
        }

        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());
            $dt = new DateTime($last, $tz);
            $dt->modify('+' . max(1, (int)$interval_days) . ' day');
            return $normalize_candidate($dt->format('Y-m-d H:i:s'), 'last_scheduled_at');
        } catch (Exception $e) {
            cbia_log_message("[ERROR] Error calculating next date: ".$e->getMessage());
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
            cbia_log_message("[INFO] Event queued in {$delay_seconds}s.");
        } else {
            cbia_log_message("[DEBUG] Event already queued (not duplicated).");
        }
    }
}

/* =========================================================
   =================== BATCH con CHECKPOINT =================
   ========================================================= */
if (!function_exists('cbia_blog_should_pause_queue_on_error')) {
    function cbia_blog_should_pause_queue_on_error($error): bool {
        $error_l = strtolower(trim((string)$error));
        if ($error_l === '') return true;
        if (in_array($error_l, array('already exists', 'empty title'), true)) return false;
        return true;
    }
}

if (!function_exists('cbia_create_all_posts_checkpointed')) {
    function cbia_create_all_posts_checkpointed($incoming_titles=null, $max_per_run = 1){

        if (!function_exists('cbia_create_single_blog_post')) {
            cbia_log_message("[ERROR] Missing cbia_create_single_blog_post() (engine). Check includes/engine/engine.php and its include.");
            return array('done'=>true,'processed'=>0);
        }

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        $interval_days = max(1, intval($settings['publication_interval'] ?? 5));

        $cp = cbia_checkpoint_get();

        $can_resume_checkpoint = !$incoming_titles
            && !empty($cp)
            && isset($cp['queue'])
            && is_array($cp['queue'])
            && (!empty($cp['running']) || !empty($cp['paused_error']));

        if ($can_resume_checkpoint) {
            cbia_log_message("[INFO] Resuming from checkpoint: ".count($cp['queue'])." in queue, idx=".intval($cp['idx'] ?? 0).".");
            $queue = $cp['queue'];
            $idx   = intval($cp['idx'] ?? 0);
            $cp['running'] = true;
            unset($cp['paused_error'], $cp['last_error_title'], $cp['last_error_at']);
            cbia_checkpoint_save($cp);
        } else {
            $titles = $incoming_titles ?? cbia_get_titles();
            if (empty($titles)) {
                cbia_log_message("[INFO] No titles. End.");
                return array('done'=>true,'processed'=>0);
            }
            $queue = cbia_prepare_queue_from_titles($titles);
            $idx = 0;
            cbia_set_last_scheduled_at('');
            $cp = array('queue'=>$queue,'idx'=>$idx,'created_total'=>0,'running'=>true,'last_scheduled_at'=>'');
            cbia_checkpoint_save($cp);
            cbia_log_message("[INFO] Checkpoint created. Starting batch... queue=".count($queue));
        }

        if (empty($queue)) {
            cbia_log_message("[INFO] No new titles. End.");
            cbia_checkpoint_clear();
            return array('done'=>true,'processed'=>0);
        }

        $max_per_run = function_exists('cbia_blog_get_posts_per_event')
            ? cbia_blog_get_posts_per_event(array('blog_posts_per_event' => $max_per_run))
            : max(1, min(5, (int)$max_per_run));
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

            $schedule_cursor = isset($cp['last_scheduled_at']) ? (string)$cp['last_scheduled_at'] : cbia_get_last_scheduled_at();
            $next_dt = cbia_compute_next_datetime($interval_days, $schedule_cursor);

            $result = array('ok' => false, 'post_id' => 0, 'error' => '');
            if ($next_dt === '') {
                cbia_log_message("[INFO] Creating post: {$title} | Published now");
                $result = cbia_create_single_blog_post($title, null);
                if (is_array($result) && !empty($result['ok'])) {
                    $post_id = (int)($result['post_id'] ?? 0);
                    $now_local = current_time('mysql');
                    $cp['last_scheduled_at'] = $now_local;
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
                    $cp['last_scheduled_at'] = $next_dt;
                    cbia_set_last_scheduled_at($next_dt);
                    $cp['created_total']++;
                } else {
                    $err = is_array($result) ? (string)($result['error'] ?? '') : '';
                    cbia_log_message("[ERROR] Could not schedule '{$title}'." . ($err !== '' ? " {$err}" : ''));
                }
            }

            if (!is_array($result) || empty($result['ok'])) {
                $err = is_array($result) ? (string)($result['error'] ?? '') : 'unknown_error';
                if (cbia_blog_should_pause_queue_on_error($err)) {
                    $cp['idx'] = $i;
                    $cp['running'] = false;
                    $cp['paused_error'] = $err;
                    $cp['last_error_title'] = $title;
                    $cp['last_error_at'] = current_time('mysql');
                    cbia_checkpoint_save($cp);
                    cbia_log_message("[WARN] Queue paused at idx={$i}/" . count((array)$queue) . " after blocking error: {$err}. Fix the issue and press Run batch again to resume the same title.");
                    return array('done'=>true,'processed'=>$processed_this_run,'paused'=>true,'error'=>$err);
                }
            }

            $cp['idx'] = $i + 1;
            cbia_checkpoint_save($cp);

            $processed_this_run++;

            if ($processed_this_run >= $max_per_run) {
                cbia_log_message("[INFO] Batch completed: processed_this_run={$processed_this_run}. Will continue in the next event.");
                break;
            }
        }

        $queue_count = count((array)($cp['queue'] ?? array()));
        $idx_now = intval($cp['idx'] ?? 0);

        if (cbia_check_stop_flag()) {
            $cp['running'] = false;
            cbia_checkpoint_save($cp);
            cbia_log_message("[INFO] STOP detected. Queue paused at idx={$idx_now}/{$queue_count}.");
            return array('done'=>true,'processed'=>$processed_this_run,'stopped'=>true);
        }

        if ($queue_count > 0 && $idx_now >= $queue_count) {
            cbia_log_message("[INFO] Queue finished. Total created: ".intval($cp['created_total']));
            $cp['running'] = false;
            cbia_checkpoint_save($cp);
            cbia_checkpoint_clear();
            return array('done'=>true,'processed'=>$processed_this_run);
        }

        cbia_log_message("[INFO] Queue pending. Checkpoint idx={$idx_now}/{$queue_count}. Total created=".intval($cp['created_total']));
        return array('done'=>false,'processed'=>$processed_this_run);
    }
}

if (!function_exists('cbia_blog_get_posts_per_event')) {
    function cbia_blog_get_posts_per_event($settings = null) {
        if (!is_array($settings)) {
            $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : (array)get_option('cbia_settings', array());
        }
        $value = isset($settings['blog_posts_per_event']) ? (int)$settings['blog_posts_per_event'] : 1;
        return max(1, min(5, $value));
    }
}

/* =========================================================
   =================== ACTION: RUN GENERATION ===============
   ========================================================= */
if (!function_exists('cbia_run_generate_blogs')) {
    function cbia_run_generate_blogs($max_per_run = 1){
        cbia_log_message("[DEBUG] cbia_run_generate_blogs called.");
        cbia_log_message("[INFO] Starting blog generation (checkpoint)...");

        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
        // Blog always generates a featured image; images_limit only controls the total count.
        $images_requested = true;
        if (function_exists('cbia_generation_preflight')) {
            $preflight = cbia_generation_preflight((array)$settings, $images_requested);
            if (empty($preflight['ok'])) {
                $error = (array)($preflight['errors'][0] ?? array('code' => 'local_validation', 'message' => 'Generation preflight failed.'));
                if (function_exists('cbia_record_local_preflight_failure')) cbia_record_local_preflight_failure($error, $preflight, 'blog_preflight');
                cbia_log_message('[ERROR] Blog preflight blocked before generation: ' . sanitize_text_field((string)($error['message'] ?? 'unknown')));
                return array('done' => true, 'processed' => 0, 'blocked' => true, 'error' => sanitize_key((string)($error['code'] ?? 'local_validation')));
            }
        }

        $max_per_run = function_exists('cbia_blog_get_posts_per_event')
            ? cbia_blog_get_posts_per_event(array('blog_posts_per_event' => $max_per_run))
            : max(1, min(5, (int)$max_per_run));
        $run_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('cbia-blog-', true);
        if (!cbia_blog_generation_acquire_lock($run_id)) {
            $lock = cbia_blog_generation_get_lock();
            $age = !empty($lock['locked_at']) ? max(0, time() - (int)$lock['locked_at']) : 0;
            cbia_log_message("[WARN] Blog batch already running. This start was ignored. lock_age={$age}s.");
            if (cbia_checkpoint_has_pending_queue() && !wp_next_scheduled('cbia_generation_event')) {
                cbia_schedule_generation_event(8, false);
                cbia_log_message("[INFO] Existing checkpoint is pending; scheduler was re-queued after locked START.");
            }
            return array('done'=>false,'processed'=>0,'locked'=>true);
        }

        try {
            $GLOBALS['cbia_usage_batch_id'] = $run_id;
            $result = cbia_create_all_posts_checkpointed(null, $max_per_run);
        } finally {
            unset($GLOBALS['cbia_usage_batch_id']);
            cbia_blog_generation_release_lock($run_id);
        }

        if (is_array($result) && empty($result['done'])) {
            cbia_schedule_generation_event(8, true);
        } elseif (is_array($result) && !empty($result['paused'])) {
            cbia_log_message("[INFO] Process paused by blocking error. No further events queued.");
        } else {
            cbia_log_message("[INFO] Process finished (no pending queue).");
        }

        cbia_log_message("[INFO] Call finished (if checkpoint is active, it will resume).");
        return $result;
    }
}

/* =========================================================
   =================== EVENT: RUN GENERATION ===============
   ========================================================= */
if (!has_action('cbia_generation_event')) {
    add_action('cbia_generation_event', function () {
        cbia_log_message('[INFO] Running batch in event (background)...');
        $max_per_run = function_exists('cbia_blog_get_posts_per_event') ? cbia_blog_get_posts_per_event() : 1;
        cbia_log_message('[INFO] Background event chunk size: ' . (int)$max_per_run . ' post(s).');
        cbia_run_generate_blogs($max_per_run);
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

        echo '<p>Could not load Blog.</p>';
    }
}
