<?php
/**
 * Admin UI for Usage tab (wrapper).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!class_exists('CBIA_Usage_Admin')) {
    class CBIA_Usage_Admin implements CBIA_Admin_Tab {
        private $costs_service;

        public function __construct($costs_service = null) {
            $this->costs_service = $costs_service;
        }

        public function get_key() {
            return 'usage';
        }

        public function get_label() {
            return 'Usage';
        }

        public function get_priority() {
            return 45;
        }

        public function render() {
            $view = CBIA_INCLUDES_DIR . 'admin/views/usage.php';
            if (file_exists($view)) {
                $cbia_costs_service = $this->costs_service;
                include $view;
                return;
            }
            echo '<p>' . esc_html__('Could not load Usage.', 'cbiastudio-blogflow-ai') . '</p>';
        }
    }
}

