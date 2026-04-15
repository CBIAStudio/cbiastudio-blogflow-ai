<?php
/**
 * Old Posts service (batch actions, AI, images).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!class_exists('CBIA_Oldposts_Service')) {
    class CBIA_Oldposts_Service {
        private $repo;

        public function __construct($repo = null) {
            $this->repo = $repo;
        }

        private function internal_images_enabled() {
            if (function_exists('cbia_cap_enabled')) {
                return (bool) cbia_cap_enabled('internal_images');
            }
            return true;
        }

        private function clamp_images_limit($value) {
            $limit = max(1, min(3, (int) $value));
            if (!$this->internal_images_enabled()) {
                return 1;
            }
            return $limit;
        }

        private function enforce_internal_images_caps_on_run(array $run) {
            if ($this->internal_images_enabled()) {
                return $run;
            }

            $run['images_limit'] = 1;
            $run['reprocess_images'] = 0;
            $run['do_images_reset'] = 0;
            $run['force_images_reset'] = 0;
            $run['do_images_content_only'] = 0;
            $run['force_images_content_only'] = 0;

            return $run;
        }

        public function get_settings() {
            if ($this->repo && method_exists($this->repo, 'get_settings')) {
                return $this->repo->get_settings();
            }
            if (function_exists('cbia_oldposts_get_settings')) {
                return cbia_oldposts_get_settings();
            }
            $s = get_option('cbia_oldposts_settings', array());
            return is_array($s) ? $s : array();
        }

        public function save_settings($settings) {
            if ($this->repo && method_exists($this->repo, 'save_settings')) {
                return $this->repo->save_settings($settings);
            }
            if (is_array($settings)) {
                update_option('cbia_oldposts_settings', $settings);
                return true;
            }
            return false;
        }

        public function handle_post($settings) {
            if (!is_admin() || !current_user_can('manage_options')) {
                return $settings;
            }

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

                        $settings['images_limit']    = isset($u['images_limit']) ? $this->clamp_images_limit($u['images_limit']) : $this->clamp_images_limit((int)$settings['images_limit']);
                        $settings['post_ids']        = isset($u['post_ids']) ? implode(',', cbia_oldposts_parse_ids_csv(sanitize_text_field((string)$u['post_ids']))) : (string)$settings['post_ids'];
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

                        if (!$this->internal_images_enabled()) {
                            $settings['reprocess_images'] = 0;
                            $settings['do_images_reset'] = 0;
                            $settings['force_images_reset'] = 0;
                            $settings['do_images_content_only'] = 0;
                            $settings['force_images_content_only'] = 0;
                        }

                        update_option(cbia_oldposts_settings_key(), $settings);
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'cbiastudio-blogflow-ai') . '</p></div>';
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

                        // Base comÃºn para ejecuciones (normal o rÃ¡pida)
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

                            $run_base['images_limit']    = isset($u['run_images_limit']) ? $this->clamp_images_limit($u['run_images_limit']) : $this->clamp_images_limit((int)$settings['images_limit']);

                            // Filtros avanzados (acepta run_* y nombres simples)
                            $run_post_ids_raw = isset($u['run_post_ids'])
                                ? sanitize_text_field((string)$u['run_post_ids'])
                                : (isset($u['post_ids']) ? sanitize_text_field((string)$u['post_ids']) : (string)($settings['post_ids'] ?? ''));
                            $run_base['post_ids'] = cbia_oldposts_parse_ids_csv($run_post_ids_raw);
                            $run_base['category_id'] = isset($u['run_category_id'])
                                ? (int)$u['run_category_id']
                                : (isset($u['category_id']) ? (int)$u['category_id'] : (int)($settings['category_id'] ?? 0));
                            $run_base['author_id'] = isset($u['run_author_id'])
                                ? (int)$u['run_author_id']
                                : (isset($u['author_id']) ? (int)$u['author_id'] : (int)($settings['author_id'] ?? 0));
                            $run_base['dry_run'] = !empty($u['run_dry_run']) || !empty($u['dry_run']) ? 1 : 0;
                            $run_base = $this->enforce_internal_images_caps_on_run($run_base);
                        }

                        if ($action === 'filter_oldposts_picker') {
                            $settings['batch_size']      = isset($u['run_batch_size']) ? max(1, min(200, (int)$u['run_batch_size'])) : (int)$settings['batch_size'];
                            $settings['filter_mode']     = in_array(($u['run_filter_mode'] ?? ''), array('all', 'range', 'older'), true)
                                ? (string)$u['run_filter_mode']
                                : 'all';
                            $settings['older_than_days'] = isset($u['run_older_than_days']) ? max(1, (int)$u['run_older_than_days']) : (int)$settings['older_than_days'];
                            $settings['date_from']       = cbia_oldposts_sanitize_ymd($u['run_date_from'] ?? $settings['date_from']);
                            $settings['date_to']         = cbia_oldposts_sanitize_ymd($u['run_date_to'] ?? $settings['date_to']);
                            $settings['images_limit']    = isset($u['run_images_limit']) ? $this->clamp_images_limit($u['run_images_limit']) : $this->clamp_images_limit((int)$settings['images_limit']);
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

                            // Overrides bÃ¡sicos siempre visibles
                            $run['batch_size']      = isset($u['run_batch_size']) ? max(1, min(200, (int)$u['run_batch_size'])) : (int)$settings['batch_size'];
                            $run['scope']           = !empty($u['run_scope_plugin']) ? 'plugin' : 'all';

                            $run['filter_mode']     = in_array(($u['run_filter_mode'] ?? ''), array('all', 'range', 'older'), true)
                                ? (string)$u['run_filter_mode']
                                : 'all';
                            $run['older_than_days'] = isset($u['run_older_than_days']) ? max(1, (int)$u['run_older_than_days']) : (int)$settings['older_than_days'];
                            $run['date_from']       = cbia_oldposts_sanitize_ymd($u['run_date_from'] ?? $settings['date_from']);
                            $run['date_to']         = cbia_oldposts_sanitize_ymd($u['run_date_to'] ?? $settings['date_to']);

                            $run['images_limit']    = isset($u['run_images_limit']) ? $this->clamp_images_limit($u['run_images_limit']) : $this->clamp_images_limit((int)$settings['images_limit']);

                            // Si el usuario activa personalizaciÃ³n, entonces sÃ­ aplicamos overrides de acciones.
                            $custom = !empty($u['run_custom_actions']) ? true : false;

                            if ($custom) {
                                $run['do_note']           = !empty($u['run_do_note']) ? 1 : 0;
                                $run['reprocess_text']    = !empty($u['run_reprocess_text']) ? 1 : 0;
                                $run['reprocess_images']  = !empty($u['run_reprocess_images']) ? 1 : 0;
                                $run['reprocess_meta']    = !empty($u['run_reprocess_meta']) ? 1 : 0;
                                $run['force_note']        = ($run['do_note'] && !empty($run['reprocess_meta'])) ? 1 : 0;

                                $run['do_yoast_metadesc'] = !empty($u['run_do_yoast_metadesc']) ? 1 : 0;
                                $run['do_yoast_focuskw']  = !empty($u['run_do_yoast_focuskw']) ? 1 : 0;
                                $run['do_yoast_title']    = !empty($u['run_do_yoast_title']) ? 1 : 0;
                                $run['force_yoast']       = ((!empty($run['do_yoast_metadesc']) || !empty($run['do_yoast_focuskw']) || !empty($run['do_yoast_title'])) && !empty($run['reprocess_meta'])) ? 1 : 0;

                                $run['do_yoast_reindex']  = !empty($u['run_do_yoast_reindex']) ? 1 : 0;

                                $run['do_title']          = !empty($u['run_do_title']) ? 1 : 0;
                                $run['force_title']       = ($run['do_title'] && !empty($run['reprocess_text'])) ? 1 : 0;

                                $run['do_content']        = !empty($u['run_do_content']) ? 1 : 0;
                                $run['force_content']     = ($run['do_content'] && !empty($run['reprocess_text'])) ? 1 : 0;
                                $run['do_content_no_images']    = !empty($u['run_do_content_no_images']) ? 1 : 0;
                                $run['force_content_no_images'] = ($run['do_content_no_images'] && !empty($run['reprocess_text'])) ? 1 : 0;

                                $run['do_images_reset']    = !empty($u['run_do_images_reset']) ? 1 : 0;
                                $run['force_images_reset'] = ($run['do_images_reset'] && !empty($run['reprocess_images'])) ? 1 : 0;
                                $run['clear_featured']     = !empty($u['run_clear_featured']) ? 1 : 0;
                                $run['do_images_content_only']    = !empty($u['run_do_images_content_only']) ? 1 : 0;
                                $run['force_images_content_only'] = ($run['do_images_content_only'] && !empty($run['reprocess_images'])) ? 1 : 0;
                                $run['do_featured_only']          = !empty($u['run_do_featured_only']) ? 1 : 0;
                                $run['force_featured_only']       = ($run['do_featured_only'] && !empty($run['reprocess_images'])) ? 1 : 0;
                                $run['featured_remove_old']       = !empty($u['run_featured_remove_old']) ? 1 : 0;

                                $run['do_categories']     = !empty($u['run_do_categories']) ? 1 : 0;
                                $run['force_categories']  = ($run['do_categories'] && !empty($run['reprocess_meta'])) ? 1 : 0;

                                $run['do_tags']           = !empty($u['run_do_tags']) ? 1 : 0;
                                $run['force_tags']        = ($run['do_tags'] && !empty($run['reprocess_meta'])) ? 1 : 0;
                            }

                            $run = $this->enforce_internal_images_caps_on_run($run);
                            cbia_oldposts_run_batch_v3($run);

                            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Batch executed. Check the log.', 'cbiastudio-blogflow-ai') . '</p></div>';
                        }

                        // Acciones rÃ¡pidas (sobrescriben acciones, respetan filtros)
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
                            } elseif ($action === 'run_quick_yoast_reindex') {
                                $run['do_yoast_reindex'] = 1;
                            } elseif ($action === 'run_quick_featured') {
                                $run['do_featured_only']    = 1;
                                $run['force_featured_only'] = 1;
                                $run['featured_remove_old'] = !empty($u['run_featured_remove_old']) ? 1 : 0;
                        } elseif ($action === 'run_quick_images_only') {
                            $run['do_images_content_only']    = 1;
                            $run['force_images_content_only'] = !empty($u['run_reprocess_images']) ? 1 : 0;
                        } elseif ($action === 'run_quick_content_only') {
                            $run['do_content_no_images']    = 1;
                            $run['force_content_no_images'] = !empty($u['run_reprocess_text']) ? 1 : 0;
                        }

                            $run = $this->enforce_internal_images_caps_on_run($run);
                            cbia_oldposts_run_batch_v3($run);
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

        public function run_batch($options) {
            if (function_exists('cbia_oldposts_run_batch_v3')) {
                return cbia_oldposts_run_batch_v3($options);
            }
            return array(0, 0, 0, 0);
        }

        public function get_log() {
            if (function_exists('cbia_oldposts_get_log')) {
                return cbia_oldposts_get_log();
            }
            return (string)get_option('cbia_oldposts_log', '');
        }
    }
}
