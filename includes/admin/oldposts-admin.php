<?php
/**
 * Admin UI for Old Posts tab.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!class_exists('CBIA_Oldposts_Admin')) {
    class CBIA_Oldposts_Admin implements CBIA_Admin_Tab {
        private $service;

        public function __construct($service) {
            $this->service = $service;
        }

        public function get_key() {
            return 'oldposts';
        }

        public function get_label() {
            return __('Update older posts', 'cbiastudio-blogflow-ai');
        }

        public function get_priority() {
            return 30;
        }

        public function render() {
            if (function_exists('cbia_cap_enabled') && !cbia_cap_enabled('oldposts')) {
                $upgrade_url_default = defined('CBIA_PRO_UPGRADE_URL_DEFAULT')
                    ? (string) CBIA_PRO_UPGRADE_URL_DEFAULT
                    : 'https://cbia-studio.lemonsqueezy.com/checkout';
                $upgrade_url = apply_filters('cbia_pro_upgrade_url', $upgrade_url_default);
                echo '<section class="cbia-usage-pro-cta-card cbia-oldposts-pro-cta-card">';
                echo '<div class="cbia-usage-pro-cta-head">';
                echo '<h3>' . esc_html__('Update older posts', 'cbiastudio-blogflow-ai') . '</h3>';
                echo '<a class="cbia-badge-pro cbia-badge-pro-link cbia-pro-upgrade-link" href="' . esc_url($upgrade_url) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Upgrade to Pro', 'cbiastudio-blogflow-ai') . '">' . esc_html__('PRO', 'cbiastudio-blogflow-ai') . '</a>';
                echo '</div>';
                echo '<p>'
                    . esc_html__('This module is designed to refresh existing content in bulk with full control over filters, runtime and regeneration scope.', 'cbiastudio-blogflow-ai')
                    . ' '
                    . esc_html__('Activate Pro to process old posts safely without editing each entry manually.', 'cbiastudio-blogflow-ai')
                    . '</p>';
                echo '<div class="cbia-usage-pro-cta-grid">';
                echo '<div class="cbia-usage-pro-pill">' . esc_html__('Visual execution cards + filters', 'cbiastudio-blogflow-ai') . '</div>';
                echo '<div class="cbia-usage-pro-pill">' . esc_html__('Reprocess text, images, SEO and terms', 'cbiastudio-blogflow-ai') . '</div>';
                echo '<div class="cbia-usage-pro-pill">' . esc_html__('Live logs with Stop / Clear / Resume', 'cbiastudio-blogflow-ai') . '</div>';
                echo '<div class="cbia-usage-pro-pill">' . esc_html__('Batch runtime with checkpoint control', 'cbiastudio-blogflow-ai') . '</div>';
                echo '<div class="cbia-usage-pro-pill">' . esc_html__('Featured + internal image handling', 'cbiastudio-blogflow-ai') . '</div>';
                echo '<div class="cbia-usage-pro-pill">' . esc_html__('Safer updates for large post libraries', 'cbiastudio-blogflow-ai') . '</div>';
                echo '</div>';
                echo '<div class="cbia-usage-pro-cta-actions">';
                echo '<a class="button button-primary cbia-pro-upgrade-link" href="' . esc_url($upgrade_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Upgrade to Pro', 'cbiastudio-blogflow-ai') . '</a>';
                echo '</div>';
                echo '</section>';
                return;
            }
            $view = CBIA_INCLUDES_DIR . 'admin/views/oldposts.php';
            if (file_exists($view)) {
                // Expose service to the view (gradual migration).
                $cbia_oldposts_service = $this->service;
                include $view;
                return;
            }
            if (function_exists('cbia_render_tab_oldposts')) {
                cbia_render_tab_oldposts();
                return;
            }
            echo '<p>' . esc_html__('Could not load Update older posts.', 'cbiastudio-blogflow-ai') . '</p>';
        }
    }
}
