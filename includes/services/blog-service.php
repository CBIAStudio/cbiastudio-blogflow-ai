<?php
/**
 * Blog generation service (wrapper around legacy helpers).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!class_exists('CBIA_Pro_Blog_Service')) {
    class CBIA_Pro_Blog_Service {
        private function normalize_csv_url($url): string {
            $url = esc_url_raw(trim((string)$url));
            if ($url === '') return '';

            if (preg_match('#^https?://drive\.google\.com/file/d/([^/]+)/#i', $url, $m)) {
                return 'https://drive.google.com/uc?export=download&id=' . rawurlencode($m[1]);
            }
            if (preg_match('#^https?://docs\.google\.com/spreadsheets/d/([^/]+)/#i', $url, $m)) {
                return 'https://docs.google.com/spreadsheets/d/' . rawurlencode($m[1]) . '/export?format=csv';
            }
            return $url;
        }

        private function normalize_post_language($value): string {
            $raw = trim((string)$value);
            if ($raw === '') return 'English';
            $legacy = array(
                'Espanol' => 'Spanish', 'espanol' => 'Spanish', 'espaÃ±ol' => 'Spanish',
                'Portugues' => 'Portuguese', 'Ingles' => 'English', 'Frances' => 'French',
                'espaÃ±ol' => 'Spanish',
                'italiano' => 'Italian', 'Aleman' => 'German', 'Holandes' => 'Dutch',
                'sueco' => 'Swedish', 'Danes' => 'Danish', 'noruego' => 'Norwegian',
                'Fines' => 'Finnish', 'polaco' => 'Polish', 'checo' => 'Czech',
                'eslovaco' => 'Slovak', 'Hungaro' => 'Hungarian', 'rumano' => 'Romanian',
                'Bulgaro' => 'Bulgarian', 'griego' => 'Greek', 'croata' => 'Croatian',
                'esloveno' => 'Slovenian', 'estonio' => 'Estonian', 'Leton' => 'Latvian',
                'lituano' => 'Lithuanian', 'Irlandes' => 'Irish', 'Maltes' => 'Maltese',
                'romanche' => 'Romansh',
            );
            return $legacy[$raw] ?? $raw;
        }

        public function get_settings() {
            if (function_exists('cbia_get_settings')) {
                return cbia_get_settings();
            }
            $settings = get_option('cbia_settings', array());
            return is_array($settings) ? $settings : array();
        }

        public function handle_post(): string {
            if (!is_admin() || !current_user_can('manage_options')) return '';
            $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD']))) : '';
            if ($request_method !== 'POST') return '';

            $post_unslashed = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();
            $saved_notice = '';

            $settings = $this->get_settings();

            if (!empty($post_unslashed['cbia_form']) && $post_unslashed['cbia_form'] === 'blog_save' && check_admin_referer('cbia_blog_save_nonce')) {
                $prompt_warnings = array();
                if (array_key_exists('title_input_mode', $post_unslashed)) {
                    $mode = (string)($post_unslashed['title_input_mode'] ?? 'manual');
                    $settings['title_input_mode'] = in_array($mode, array('manual','csv'), true) ? $mode : 'manual';
                }

                if (array_key_exists('manual_titles', $post_unslashed)) {
                    $manual_titles_raw = sanitize_textarea_field((string)($post_unslashed['manual_titles'] ?? ''));
                    if (function_exists('cbia_cap_enabled') && !cbia_cap_enabled('runtime_advanced')) {
                        $manual_titles_list = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $manual_titles_raw))));
                        $manual_titles_raw = isset($manual_titles_list[0]) ? (string)$manual_titles_list[0] : '';
                    }
                    $settings['manual_titles'] = $manual_titles_raw;
                }
                if (array_key_exists('csv_url', $post_unslashed)) {
                    $settings['csv_url'] = $this->normalize_csv_url((string)($post_unslashed['csv_url'] ?? ''));
                }

                if (array_key_exists('first_publication_datetime_local', $post_unslashed)) {
                    $dt_local = sanitize_text_field(trim((string)($post_unslashed['first_publication_datetime_local'] ?? '')));
                    if ($dt_local !== '') {
                        $dt_local = str_replace('T',' ', $dt_local);
                        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $dt_local)) $dt_local .= ':00';
                        $settings['first_publication_datetime'] = $dt_local;
                    } else {
                        $settings['first_publication_datetime'] = '';
                    }
                }

                if (array_key_exists('publication_interval', $post_unslashed)) {
                    $settings['publication_interval'] = max(1, intval($post_unslashed['publication_interval'] ?? 5));
                }
                if (array_key_exists('blog_posts_per_event', $post_unslashed)) {
                    $settings['blog_posts_per_event'] = max(1, min(5, intval($post_unslashed['blog_posts_per_event'] ?? 1)));
                }
                if (array_key_exists('enable_cron_fill', $post_unslashed)) {
                    $settings['enable_cron_fill'] = !empty($post_unslashed['enable_cron_fill']) ? 1 : 0;
                } elseif (array_key_exists('publication_interval', $post_unslashed) || array_key_exists('first_publication_datetime_local', $post_unslashed)) {
                    // Solo cuando se envia el formulario de programacion, el checkbox ausente significa desactivado.
                    $settings['enable_cron_fill'] = 0;
                }

                // Publicacion y clasificacion (migrado desde Configuracion)
                if (array_key_exists('default_author_id', $post_unslashed)) {
                    $settings['default_author_id'] = absint($post_unslashed['default_author_id'] ?? 0);
                }
                $previous_language = (string)($settings['post_language'] ?? 'English');
                if (array_key_exists('post_language', $post_unslashed)) {
                    $settings['post_language'] = $this->normalize_post_language(
                        sanitize_text_field((string)($post_unslashed['post_language'] ?? ($settings['post_language'] ?? 'English')))
                    );
                }
                $prompt_language = (string)($settings['post_language'] ?? 'English');
                if (array_key_exists('default_category', $post_unslashed)) {
                    $settings['default_category'] = sanitize_text_field((string)($post_unslashed['default_category'] ?? ($settings['default_category'] ?? 'News')));
                }
                if (array_key_exists('keywords_to_categories', $post_unslashed)) {
                    $settings['keywords_to_categories'] = sanitize_textarea_field((string)($post_unslashed['keywords_to_categories'] ?? ($settings['keywords_to_categories'] ?? '')));
                }
                if (array_key_exists('default_tags', $post_unslashed)) {
                    $settings['default_tags'] = sanitize_text_field((string)($post_unslashed['default_tags'] ?? ($settings['default_tags'] ?? '')));
                }

                // CAMBIO: Prompt del blog (recommended/legacy) con edicion parcial.
                // Prioridad robusta:
                // 1) radio UI (funciona incluso sin JS)
                // 2) campo canonico hidden
                // 3) compat campo legacy
                $prompt_mode_input = (string)($post_unslashed['blog_prompt_mode_ui'] ?? ($post_unslashed['blog_prompt_mode'] ?? ($post_unslashed['blog_prompt_mode_state'] ?? ($settings['blog_prompt_mode'] ?? 'recommended'))));
                $prompt_post_mode = sanitize_key($prompt_mode_input);
                if (!in_array($prompt_post_mode, array('recommended', 'legacy'), true)) $prompt_post_mode = 'recommended';
                if (array_key_exists('blog_prompt_mode_ui', $post_unslashed)) {
                    $settings['blog_prompt_mode'] = $prompt_post_mode;
                } elseif (array_key_exists('blog_prompt_mode', $post_unslashed)) {
                    $settings['blog_prompt_mode'] = $prompt_post_mode;
                } elseif (array_key_exists('blog_prompt_mode_state', $post_unslashed)) {
                    $settings['blog_prompt_mode'] = $prompt_post_mode;
                } else {
                    $prompt_post_mode = sanitize_key((string)($settings['blog_prompt_mode'] ?? 'recommended'));
                }
                $settings['blog_prompt_legacy_enabled'] = ($prompt_post_mode === 'legacy') ? 1 : 0;

                // Compatibilidad: preservar prompt historico como legacy_full_prompt.
                if (empty($settings['legacy_full_prompt'])) {
                    $legacy_seed = trim((string)($settings['prompt_single_all'] ?? ''));
                    if ($legacy_seed !== '') {
                        $settings['legacy_full_prompt'] = $legacy_seed;
                    }
                }

                if (array_key_exists('blog_prompt_editable', $post_unslashed) || !empty($post_unslashed['blog_prompt_restore'])) {
                    $default_editable = function_exists('cbia_prompt_recommended_editable_default_for_language')
                        ? cbia_prompt_recommended_editable_default_for_language($prompt_language)
                        : (function_exists('cbia_prompt_recommended_editable_default') ? cbia_prompt_recommended_editable_default() : '');
                    $editable_raw = (string)($post_unslashed['blog_prompt_editable'] ?? ($settings['blog_prompt_editable'] ?? $default_editable));
                    if (!empty($post_unslashed['blog_prompt_restore'])) {
                        $editable_raw = $default_editable !== '' ? $default_editable : $editable_raw;
                    }
                    // If user changed language and still had a default prompt, auto-switch default to new language.
                    if (
                        empty($post_unslashed['blog_prompt_restore'])
                        && array_key_exists('post_language', $post_unslashed)
                        && function_exists('cbia_prompt_recommended_editable_default_for_language')
                    ) {
                        $prev_default = cbia_prompt_recommended_editable_default_for_language($previous_language);
                        $new_default  = cbia_prompt_recommended_editable_default_for_language($prompt_language);
                        $en_default   = cbia_prompt_recommended_editable_default();
                        $es_default   = function_exists('cbia_prompt_recommended_editable_legacy_default') ? cbia_prompt_recommended_editable_legacy_default() : '';
                        if (
                            function_exists('cbia_prompt_text_equals')
                            && (
                                cbia_prompt_text_equals($editable_raw, $prev_default)
                                || cbia_prompt_text_equals($editable_raw, $new_default)
                                || cbia_prompt_text_equals($editable_raw, $en_default)
                                || ($es_default !== '' && cbia_prompt_text_equals($editable_raw, $es_default))
                            )
                        ) {
                            $editable_raw = $new_default;
                        }
                    }
                    if (function_exists('cbia_prompt_maybe_upgrade_legacy_editable')) {
                        $editable_raw = cbia_prompt_maybe_upgrade_legacy_editable($editable_raw, $prompt_language);
                    }
                    if (function_exists('cbia_prompt_sanitize_editable_block')) {
                        $editable_raw = cbia_prompt_sanitize_editable_block($editable_raw);
                    } else {
                        $editable_raw = sanitize_textarea_field($editable_raw);
                    }
                    $settings['blog_prompt_editable'] = $editable_raw;
                }

                if (array_key_exists('blog_prompt_profile', $post_unslashed)) {
                    $profile = sanitize_key((string)($post_unslashed['blog_prompt_profile'] ?? 'discover_editorial'));
                    $profile_options = function_exists('cbia_prompt_get_profile_options') ? cbia_prompt_get_profile_options() : array();
                    if (!array_key_exists($profile, $profile_options)) {
                        $profile = 'discover_editorial';
                    }
                    $settings['blog_prompt_profile'] = $profile;
                }
                if (array_key_exists('include_faq', $post_unslashed)) {
                    $settings['include_faq'] = !empty($post_unslashed['include_faq']) ? 1 : 0;
                } elseif (array_key_exists('blog_prompt_profile', $post_unslashed)) {
                    $settings['include_faq'] = 0;
                }
                if (array_key_exists('include_practical_examples', $post_unslashed)) {
                    $settings['include_practical_examples'] = !empty($post_unslashed['include_practical_examples']) ? 1 : 0;
                } elseif (array_key_exists('blog_prompt_profile', $post_unslashed)) {
                    $settings['include_practical_examples'] = 0;
                }
                if (array_key_exists('search_intent_strength', $post_unslashed)) {
                    $strength = sanitize_key((string)($post_unslashed['search_intent_strength'] ?? 'balanced'));
                    if (!in_array($strength, array('soft', 'balanced', 'strong'), true)) {
                        $strength = 'balanced';
                    }
                    $settings['search_intent_strength'] = $strength;
                }
                if (array_key_exists('blog_prompt_custom_instructions', $post_unslashed)) {
                    $custom = (string)($post_unslashed['blog_prompt_custom_instructions'] ?? '');
                    $settings['blog_prompt_custom_instructions'] = function_exists('cbia_prompt_sanitize_custom_instructions')
                        ? cbia_prompt_sanitize_custom_instructions($custom)
                        : sanitize_textarea_field($custom);
                }

                $legacy_input = (string)($post_unslashed['legacy_full_prompt'] ?? '');
                if ($legacy_input !== '') {
                    if (function_exists('cbia_prompt_clean_legacy_template')) {
                        $legacy_input = cbia_prompt_clean_legacy_template($legacy_input, $prompt_language);
                    } elseif (function_exists('cbia_fix_mojibake')) {
                        $legacy_input = (string)cbia_fix_mojibake($legacy_input);
                    }
                    $settings['legacy_full_prompt'] = sanitize_textarea_field($legacy_input);
                } elseif (empty($settings['legacy_full_prompt'])) {
                    $settings['legacy_full_prompt'] = '';
                }

                // CAMBIO: mantener compatibilidad con prompt_single_all.
                if ($prompt_post_mode === 'legacy') {
                    $settings['prompt_single_all'] = (string)($settings['legacy_full_prompt'] ?? '');
                    $legacy_effective = (string)($settings['legacy_full_prompt'] ?? '');
                    if (strpos($legacy_effective, '{title}') === false) {
                        $prompt_warnings[] = 'Advanced prompt: missing {title} variable.';
                    }
                    if (stripos($legacy_effective, '[IMAGE:') === false && stripos($legacy_effective, '[IMAGEN:') === false) {
                        $prompt_warnings[] = 'Advanced prompt: does not include [IMAGE: ...] markers.';
                    }
                } else {
                    if (function_exists('cbia_prompt_build_recommended_template_from_settings')) {
                        $settings['prompt_single_all'] = cbia_prompt_build_recommended_template_from_settings($settings, $prompt_language);
                    } elseif (function_exists('cbia_prompt_build_recommended_template')) {
                        $settings['prompt_single_all'] = cbia_prompt_build_recommended_template((string)($settings['blog_prompt_editable'] ?? ''), $prompt_language);
                    }
                }

                update_option('cbia_settings', $settings, false);

                if (function_exists('cbia_log_message')) {
                    cbia_log_message("[INFO] Blog: settings saved (titles + automation).");
                }
                if (!empty($prompt_warnings)) {
                    set_transient('cbia_blog_prompt_warnings', $prompt_warnings, 120);
                    $saved_notice = 'saved_warn';
                } else {
                    delete_transient('cbia_blog_prompt_warnings');
                    $saved_notice = 'saved';
                }
            }

            if (!empty($post_unslashed['cbia_form']) && $post_unslashed['cbia_form'] === 'blog_actions' && check_admin_referer('cbia_blog_actions_nonce')) {
                $action = sanitize_text_field((string)($post_unslashed['cbia_action'] ?? ''));

                if ($action === 'test_config') {
                    if (function_exists('cbia_run_test_configuration')) $GLOBALS['cbia_configuration_test_result'] = cbia_run_test_configuration();
                    else if (function_exists('cbia_log_message')) cbia_log_message('[WARN] Missing cbia_run_test_configuration().');
                    $saved_notice = 'test';

                } elseif ($action === 'stop_generation') {
                    if (function_exists('cbia_set_stop_flag')) cbia_set_stop_flag(true);
                    if (function_exists('cbia_log_message')) cbia_log_message("[INFO] Stop enabled by user.");
                    $saved_notice = 'stop';

                } elseif ($action === 'fill_pending_imgs') {
                    if (function_exists('cbia_set_stop_flag')) cbia_set_stop_flag(false);
                    if (function_exists('cbia_run_fill_pending_images')) cbia_run_fill_pending_images(10);
                    else if (function_exists('cbia_log_message')) cbia_log_message('[WARN] Missing cbia_run_fill_pending_images().');
                    $saved_notice = 'pending';

                } elseif ($action === 'clear_checkpoint') {
                    if (function_exists('cbia_checkpoint_clear')) cbia_checkpoint_clear();
                    delete_option('_cbia_last_scheduled_at');
                    if (function_exists('cbia_log_message')) cbia_log_message("[INFO] Checkpoint cleared + _cbia_last_scheduled_at reset.");
                    $saved_notice = 'checkpoint';

                } elseif ($action === 'clear_log') {
                    if (function_exists('cbia_clear_log')) cbia_clear_log();
                    if (function_exists('cbia_log_message')) cbia_log_message("[INFO] Log cleared manually.");
                    $saved_notice = 'log';
                }
            }

            return $saved_notice;
        }

        public function schedule_generation_event($delay_seconds = 5, $force = false) {
            if (function_exists('cbia_schedule_generation_event')) {
                return cbia_schedule_generation_event($delay_seconds, $force);
            }
            return false;
        }

        public function run_generate_blogs($max_per_run = 1) {
            if (function_exists('cbia_run_generate_blogs')) {
                return cbia_run_generate_blogs($max_per_run);
            }
            return array('done' => true);
        }

        public function get_last_scheduled_at() {
            if (function_exists('cbia_get_last_scheduled_at')) {
                return cbia_get_last_scheduled_at();
            }
            return (string)get_option('_cbia_last_scheduled_at', '');
        }

        public function set_last_scheduled_at($datetime) {
            if (function_exists('cbia_set_last_scheduled_at')) {
                return cbia_set_last_scheduled_at($datetime);
            }
            if ($datetime) {
                update_option('_cbia_last_scheduled_at', (string)$datetime, false);
            }
            return true;
        }

        public function get_log() {
            if (function_exists('cbia_get_log')) {
                return cbia_get_log();
            }
            return array('log' => '', 'counter' => 0);
        }

        public function get_checkpoint_status() {
            if (!function_exists('cbia_checkpoint_get')) {
                return array(
                    'status' => __('idle', 'cbiastudio-blogflow-ai'),
                    'last' => __('(no records)', 'cbiastudio-blogflow-ai'),
                    'running' => false,
                    'pending' => false,
                    'idx' => 0,
                    'total' => 0,
                    'lock_age' => 0,
                    'next_event' => 0,
                );
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
            $last = $this->get_last_scheduled_at();
            $last = $last ?: __('(no records)', 'cbiastudio-blogflow-ai');
            $lock = function_exists('cbia_blog_generation_get_lock') ? cbia_blog_generation_get_lock() : array();
            $lock_age = (!empty($lock['locked_at'])) ? max(0, time() - (int)$lock['locked_at']) : 0;
            $next_event = function_exists('wp_next_scheduled') ? (int)wp_next_scheduled('cbia_generation_event') : 0;
            return array(
                'status' => $status,
                'last' => $last,
                'running' => $running,
                'pending' => $pending,
                'idx' => $idx,
                'total' => $total,
                'paused_error' => $paused_error,
                'lock_age' => $lock_age,
                'next_event' => $next_event,
            );
        }
    }
}
