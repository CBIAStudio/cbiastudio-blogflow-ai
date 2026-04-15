<?php
/**
 * Admin UI for Config tab (wrapper).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!class_exists('CBIA_Config_Admin')) {
    class CBIA_Config_Admin implements CBIA_Admin_Tab {
        private $service;

        public function __construct($service = null) {
            $this->service = $service;
        }

        public function get_key() {
            return 'config';
        }

        public function get_label() {
            return __('Settings', 'cbiastudio-blogflow-ai');
        }

        public function get_priority() {
            return 10;
        }

        public function render() {
            $view = CBIA_INCLUDES_DIR . 'admin/views/config.php';
            if (file_exists($view)) {
                // Expose service to the view (gradual migration).
                $cbia_settings_service = $this->service;
                include $view;
                return;
            }
            if (function_exists('cbia_render_tab_config')) {
                cbia_render_tab_config();
                return;
            }
            echo '<p>' . esc_html__('Could not load Settings.', 'cbiastudio-blogflow-ai') . '</p>';
        }
    }
}

