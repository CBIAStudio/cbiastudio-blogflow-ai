<?php
/**
 * Admin router: register tabs and menus.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!class_exists('CBIA_Admin_Router')) {
    class CBIA_Admin_Router {
        private $tabs = array();
        private function branding() {
            $is_pro = function_exists('cbia_cap_enabled')
                ? cbia_cap_enabled('oldposts')
                : (defined('CBIA_EDITION') && strtolower((string) CBIA_EDITION) === 'pro');
            return array(
                'is_pro' => (bool) $is_pro,
                'name' => $is_pro ? 'CBIAStudio BlogFlow Pro' : 'CBIAStudio BlogFlow with AI',
                'title' => $is_pro
                    ? 'CBIASTUDIO BLOGFLOW <span class="cbia-badge-pro">PRO</span>'
                    : 'CBIASTUDIO BLOGFLOW <span class="cbia-badge-base">AI</span>',
            );
        }

        public function register_tab($key, $label, $callback, $priority = 10) {
            $this->tabs[$key] = array(
                'label' => $label,
                'callback' => $callback,
                'priority' => (int)$priority,
            );
        }

        public function register_tab_object($tab_object) {
            if (!is_object($tab_object)) return;
            if (!method_exists($tab_object, 'get_key')) return;
            if (!method_exists($tab_object, 'get_label')) return;
            if (!method_exists($tab_object, 'render')) return;

            $key = (string)$tab_object->get_key();
            $label = (string)$tab_object->get_label();
            $callback = array($tab_object, 'render');
            $priority = method_exists($tab_object, 'get_priority') ? (int)$tab_object->get_priority() : 10;

            if ($key === '' || $label === '') return;
            $this->register_tab($key, $label, $callback, $priority);
        }

        public function register() {
            add_action('admin_menu', array($this, 'on_admin_menu'));
        }

        public function on_admin_menu() {
            $branding = $this->branding();
            add_menu_page(
                $branding['name'],
                $branding['name'],
                'manage_options',
                'cbia',
                array($this, 'render_page'),
                'dashicons-edit-page',
                56
            );

            $tabs = $this->get_tabs();
            if (empty($tabs)) {
                return;
            }

            $first_key = array_key_first($tabs);
            foreach ($tabs as $key => $tab) {
                $label = (string)($tab['label'] ?? $key);
                $menu_slug = ($key === $first_key) ? 'cbia' : 'cbia&tab=' . $key;
                $page_title = $branding['name'] . ' - ' . $label;

                add_submenu_page(
                    'cbia',
                    $page_title,
                    $label,
                    'manage_options',
                    $menu_slug,
                    array($this, 'render_page')
                );
            }
        }

        public function get_tabs() {
            if (!empty($this->tabs)) {
                $tabs = $this->tabs;
                $tabs = apply_filters('cbia_admin_tabs', $tabs);
                uasort($tabs, function($a, $b) {
                    return (int)($a['priority'] ?? 10) <=> (int)($b['priority'] ?? 10);
                });
                return $tabs;
            }

            if (function_exists('cbia_get_admin_tabs')) {
                $legacy = cbia_get_admin_tabs();
                if (is_array($legacy)) {
                    $tabs = array();
                    foreach ($legacy as $key => $tab) {
                        $tabs[$key] = array(
                            'label' => (string)($tab['label'] ?? $key),
                            'callback' => $tab['render'] ?? null,
                        );
                    }
                    $tabs = apply_filters('cbia_admin_tabs', $tabs);
                    uasort($tabs, function($a, $b) {
                        return (int)($a['priority'] ?? 10) <=> (int)($b['priority'] ?? 10);
                    });
                    return $tabs;
                }
            }

            $tabs = apply_filters('cbia_admin_tabs', array());
            uasort($tabs, function($a, $b) {
                return (int)($a['priority'] ?? 10) <=> (int)($b['priority'] ?? 10);
            });
            return $tabs;
        }

        public function get_current_tab($tabs) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab navigation
            $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'config';
            // Backward compatibility: old "costes" route now lives inside "usage".
            if ($tab === 'costes' && isset($tabs['usage'])) {
                return 'usage';
            }
            if (isset($tabs[$tab])) return $tab;
            return array_key_first($tabs) ?: 'config';
        }

        public function render_page() {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to view this page.', 'cbiastudio-blogflow-ai'));
            }

            $tabs = $this->get_tabs();
            if (empty($tabs)) {
                echo '<div class="wrap"><p>' . esc_html__('No tabs are registered.', 'cbiastudio-blogflow-ai') . '</p></div>';
                return;
            }

            $current = $this->get_current_tab($tabs);

            
            $branding = $this->branding();
            $logo_header = plugins_url('assets/images/ai-blog-builder-ico.svg', CBIA_PRO_PLUGIN_FILE);
            echo '<div class="wrap cbia-shell">';
            echo '<div class="cbia-header">';
            echo '<div class="cbia-brand">';
            echo '<img class="cbia-logo" src="' . esc_url($logo_header) . '" alt="' . esc_attr($branding['name']) . '" />';
            echo '<div class="cbia-title">' . wp_kses_post($branding['title']) . '</div>';
            echo '</div>';
            echo '<div class="cbia-version">v' . esc_html(defined('CBIA_VERSION') ? CBIA_VERSION : '2.0.2') . '</div>';
            echo '</div>';

            echo '<div class="cbia-layout">';
            echo '<aside class="cbia-sidebar">';
            echo '<ul class="cbia-sidebar-nav">';

           
            foreach ($tabs as $key => $t) {
                $url = admin_url('admin.php?page=cbia&tab=' . $key);
                $cls = 'cbia-nav-link' . ($key === $current ? ' is-active' : '');
                $icon_map = array(
                    'config'    => 'dashicons-admin-generic',
                    'blog'      => 'dashicons-edit-page',
                    'oldposts'  => 'dashicons-update',
                    'costs'     => 'dashicons-money-alt',
                    'yoast'     => 'dashicons-chart-bar',
                    'usage'     => 'dashicons-chart-area',
                );
                $icon = $icon_map[$key] ?? 'dashicons-admin-generic';
                $item_cls = 'cbia-nav-item' . ($key === $current ? ' is-current' : '');
                $label = (string) ($t['label'] ?? $key);
                $is_oldposts_locked = ($key === 'oldposts' && function_exists('cbia_cap_enabled') && !cbia_cap_enabled('oldposts'));
                $label_html = '<span class="cbia-nav-text-label">' . esc_html($label) . '</span>';
                if ($is_oldposts_locked) {
                    $label_html .= '<span class="cbia-nav-pro-pill">' . esc_html__('PRO', 'cbiastudio-blogflow-ai') . '</span>';
                }
                echo '<li class="' . esc_attr($item_cls) . '">';
                echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '"><span class="dashicons ' . esc_attr($icon) . '"></span><span class="cbia-nav-text">' . wp_kses($label_html, array('span' => array('class' => true))) . '</span></a>';

                if ($key === 'blog' && $current === 'blog') {
                    $blog_base = admin_url('admin.php?page=cbia&tab=blog');
                    $section_links = array(
                        '#cbia-blog-section-titles'  => __('Titles', 'cbiastudio-blogflow-ai'),
                        '#cbia-blog-section-config'  => __('Configuration', 'cbiastudio-blogflow-ai'),
                        '#cbia-blog-section-ops'     => __('Queue and status', 'cbiastudio-blogflow-ai'),
                        '#cbia-blog-section-actions' => __('Actions', 'cbiastudio-blogflow-ai'),
                        '#cbia-blog-section-log'     => __('Log', 'cbiastudio-blogflow-ai'),
                    );
                    echo '<ul class="cbia-nav-submenu" aria-label="' . esc_attr__('Blog sections', 'cbiastudio-blogflow-ai') . '">';
                    foreach ($section_links as $fragment => $label) {
                        echo '<li><a class="cbia-nav-submenu-link" href="' . esc_url($blog_base . $fragment) . '">' . esc_html($label) . '</a></li>';
                    }
                    echo '</ul>';
                }

                echo '</li>';
            }
            echo '</ul>';
            echo '</aside>';
            echo '<main class="cbia-main">';
            echo '<div class="cbia-content-card">';

            do_action('cbia_admin_before_render', $current, $tabs);

            $render = $tabs[$current]['callback'] ?? null;
            if (is_callable($render)) {
                call_user_func($render);
            } else {
                echo '<p>' . esc_html__('Could not load this tab.', 'cbiastudio-blogflow-ai') . '</p>';
                if (function_exists('cbia_log')) {
                    cbia_log(sprintf('Could not load tab: %s', (string)$current), 'ERROR');
                }
            }

            do_action('cbia_admin_after_render', $current, $tabs);
            echo '</div>'; // content card
            echo '</main>';
            echo '</div>'; // layout
            echo '</div>'; // wrap
        }
    }
}
