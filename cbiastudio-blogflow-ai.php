<?php
/**
 * Plugin Name: CBIAStudio BlogFlow with AI
 * Description: Base edition of CBIAStudio BlogFlow with AI for WordPress.
 * Version: 2.2.1
 * Text Domain: cbiastudio-blogflow-ai
 * Domain Path: /languages
 *
 * Author: CBIA Studio
 * Requires at least: 6.9.2
 * Requires PHP: 8.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!defined('CBIA_BASE_VERSION')) define('CBIA_BASE_VERSION', '2.2.1');
if (!defined('CBIA_BASE_PLUGIN_FILE')) define('CBIA_BASE_PLUGIN_FILE', __FILE__);
if (!defined('CBIA_BASE_PLUGIN_DIR')) define('CBIA_BASE_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('CBIA_BASE_PLUGIN_URL')) define('CBIA_BASE_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('CBIA_BASE_INCLUDES_DIR')) define('CBIA_BASE_INCLUDES_DIR', CBIA_BASE_PLUGIN_DIR . 'includes/');
if (!defined('CBIA_TEXT_DOMAIN')) define('CBIA_TEXT_DOMAIN', 'cbiastudio-blogflow-ai');
if (!defined('CBIA_EDITION')) define('CBIA_EDITION', 'base');
if (!defined('CBIA_PRO_UPGRADE_URL_DEFAULT')) define('CBIA_PRO_UPGRADE_URL_DEFAULT', 'https://cbia-studio.lemonsqueezy.com/checkout');

// Compatibilidad con el core compartido (nombres histÃƒÂ³ricos)
if (!defined('CBIA_VERSION')) define('CBIA_VERSION', CBIA_BASE_VERSION);
if (!defined('CBIA_PLUGIN_FILE')) define('CBIA_PLUGIN_FILE', CBIA_BASE_PLUGIN_FILE);
if (!defined('CBIA_PLUGIN_DIR')) define('CBIA_PLUGIN_DIR', CBIA_BASE_PLUGIN_DIR);
if (!defined('CBIA_PLUGIN_URL')) define('CBIA_PLUGIN_URL', CBIA_BASE_PLUGIN_URL);
if (!defined('CBIA_INCLUDES_DIR')) define('CBIA_INCLUDES_DIR', CBIA_BASE_INCLUDES_DIR);
if (!defined('CBIA_PRO_VERSION')) define('CBIA_PRO_VERSION', CBIA_VERSION);
if (!defined('CBIA_PRO_PLUGIN_FILE')) define('CBIA_PRO_PLUGIN_FILE', CBIA_PLUGIN_FILE);
if (!defined('CBIA_PRO_PLUGIN_DIR')) define('CBIA_PRO_PLUGIN_DIR', CBIA_PLUGIN_DIR);
if (!defined('CBIA_PRO_PLUGIN_URL')) define('CBIA_PRO_PLUGIN_URL', CBIA_PLUGIN_URL);
if (!defined('CBIA_PRO_INCLUDES_DIR')) define('CBIA_PRO_INCLUDES_DIR', CBIA_INCLUDES_DIR);
if (!defined('CBIA_OPTION_SETTINGS')) define('CBIA_OPTION_SETTINGS', 'cbia_settings');
if (!defined('CBIA_OPTION_LOG')) define('CBIA_OPTION_LOG', 'cbia_activity_log');
if (!defined('CBIA_OPTION_LOG_COUNTER')) define('CBIA_OPTION_LOG_COUNTER', 'cbia_log_counter');
if (!defined('CBIA_OPTION_STOP')) define('CBIA_OPTION_STOP', 'cbia_stop_generation');
if (!defined('CBIA_OPTION_CHECKPOINT')) define('CBIA_OPTION_CHECKPOINT', 'cbia_checkpoint');

require_once CBIA_BASE_INCLUDES_DIR . 'lifecycle.php';
register_deactivation_hook(__FILE__, array('CBIA_Lifecycle', 'clear_scheduled_events'));

add_filter('cbia_pro_upgrade_url', function ($url) {
	return CBIA_PRO_UPGRADE_URL_DEFAULT;
}, 10, 1);

add_action('init', function () {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Keep explicit loading for non-dotorg/private distributions.
	load_plugin_textdomain(
		'cbiastudio-blogflow-ai',
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);
}, 1);

// Bootstrap nueva estructura (v3.0), diferido para evitar carga de i18n demasiado temprana.
add_action('plugins_loaded', function () {
	$cbia_bootstrap = CBIA_INCLUDES_DIR . 'core/bootstrap.php';
	if (file_exists($cbia_bootstrap)) {
		require_once $cbia_bootstrap;
	}
	if (function_exists('cbia_register_core_hooks')) {
		cbia_register_core_hooks();
	}
}, 5);

// Registrar loader nuevo after init so admin labels can use translations safely.
add_action('init', function () {
	if (class_exists('CBIA_Loader') && function_exists('cbia_container')) {
		$container = cbia_container();
		$router = $container ? $container->get('admin_router') : null;
		$scheduler = $container ? $container->get('scheduler') : null;
		$loader = new CBIA_Loader($router, $scheduler);
		$loader->register();
	}
}, 2);

/**
 * Helpers globales (evitar duplicados)
 */

if (!function_exists('cbia_now_mysql')) {
	function cbia_now_mysql(): string {
		return current_time('mysql'); // respeta TZ WP
	}
}

if (!function_exists('cbia_date_mysql_from_ts')) {
	function cbia_date_mysql_from_ts(int $ts): string {
		return gmdate('Y-m-d H:i:s', $ts + (get_option('gmt_offset') * HOUR_IN_SECONDS));
	}
}

if (!function_exists('cbia_log')) {
	/**
	 * Redacta posibles secretos en mensajes de log.
	 */
	function cbia_mask_sensitive_log_text(string $text): string {
		$text = (string)$text;
		if ($text === '') return $text;
		$text = preg_replace('/(Incorrect API key provided:\s*)([^\r\n]+)/i', '$1[REDACTED]', $text);
		$text = preg_replace('/(\bapi[_\s-]?key\s*(?:provided)?\s*[:=]\s*)([^\s,;]+)/i', '$1[REDACTED]', $text);
		$text = preg_replace('/(\bAuthorization\s*[:=]\s*)([^\r\n,;]+)/i', '$1[REDACTED]', $text);
		$text = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+/i', 'Bearer [REDACTED]', $text);
		$text = preg_replace('/("(?:api_key|access_token|token|cookie)"\s*:\s*")[^"]+("?)/i', '$1[REDACTED]$2', $text);
		$text = preg_replace('/([?&](?:key|api_key)=)([^&\s]+)/i', '$1[REDACTED]', $text);
		$text = preg_replace('/\bsk-[A-Za-z0-9_\-]{10,}\b/', 'sk-[REDACTED]', $text);
		return (string)$text;
	}

	/**
	 * Log general en option CBIA_OPTION_LOG (texto plano acumulado)
	 */
	function cbia_log(string $message, string $level = 'INFO'): void {
		if (function_exists('cbia_fix_mojibake')) {
			$message = (string) cbia_fix_mojibake($message);
		}
		// Internal logs are stored as-is; UI strings are translated at render time.
		$message = function_exists('cbia_mask_sensitive_log_text')
			? cbia_mask_sensitive_log_text((string)$message)
			: (string)$message;
		$level = strtoupper(trim($level ?: 'INFO'));
		$line = '[' . cbia_now_mysql() . '][' . $level . '] ' . $message;
		$log = (string) get_option(CBIA_OPTION_LOG, '');
		$log = $log ? ($log . "\n" . $line) : $line;

		// Mantener el log con un tamaÃƒÂ±o razonable (ÃƒÂºltimos ~2000 lÃƒÂ­neas)
		$lines = explode("\n", $log);
		if (count($lines) > 2000) {
			$lines = array_slice($lines, -2000);
			$log = implode("\n", $lines);
		}

		update_option(CBIA_OPTION_LOG, $log, false);

		$cnt = (int) get_option(CBIA_OPTION_LOG_COUNTER, 0);
		update_option(CBIA_OPTION_LOG_COUNTER, $cnt + 1, false);

		wp_cache_delete(CBIA_OPTION_LOG, 'options');
		wp_cache_delete(CBIA_OPTION_LOG_COUNTER, 'options');
	}
}

if (!function_exists('cbia_get_log')) {
	/**
	 * Lee log general
	 */
	function cbia_get_log(): array {
		$log = (string) get_option(CBIA_OPTION_LOG, '');
		if (function_exists('cbia_fix_mojibake')) {
			$log = cbia_fix_mojibake($log);
		}
		$counter = (int) get_option(CBIA_OPTION_LOG_COUNTER, 0);
		return ['log' => $log, 'counter' => $counter];
	}
}

if (!function_exists('cbia_clear_log')) {
	/**
	 * Borra log general
	 */
	function cbia_clear_log(): void {
		delete_option(CBIA_OPTION_LOG);
		delete_option(CBIA_OPTION_LOG_COUNTER);
		wp_cache_delete(CBIA_OPTION_LOG, 'options');
		wp_cache_delete(CBIA_OPTION_LOG_COUNTER, 'options');
	}
}

if (!function_exists('cbia_get_default_settings')) {
	/**
	 * Defaults globales del plugin
	 */
	function cbia_get_default_settings(): array {
		return [
			// OpenAI
			'openai_consent'        => 0,
			'openai_model'          => 'gpt-5-mini',
			'openai_temperature'    => 0.7,
			'deepseek_thinking_mode' => 'disabled',
			'deepseek_reasoning_effort' => 'high',
			// CAMBIO: proveedor/modelo texto/imagen
			'text_provider'         => 'openai',
			'text_model'            => '',
			'image_provider'        => 'openai',
			'image_model'           => 'gpt-image-2',
			'image_quality'          => 'auto',
			'featured_image_quality' => 'inherit',
			'content_image_quality'  => 'inherit',
			// CAMBIO: Google Imagen (Vertex AI)
			'google_project_id'     => '',
			'google_location'       => '',
			'google_service_account_json' => '',

			// Longitud / imÃƒÂ¡genes
			'post_length_variant'   => 'medium',
			'images_limit'          => 1,
			// CAMBIO: prompt recomendado/legado (compatibilidad)
			'blog_prompt_mode'      => 'recommended',
			'blog_prompt_profile'   => 'discover_editorial',
			'include_faq'           => 1,
			'include_practical_examples' => 0,
			'search_intent_strength' => 'balanced',
			'blog_prompt_custom_instructions' => '',
			'blog_prompt_editable'  => '',
			'legacy_full_prompt'    => '',
			'prompt_single_all'     => "Write an HTML blog article (without <h1>) about: {title}\nInclude image markers like [IMAGE: description].",
			'prompt_img_intro'      => '',
			'prompt_img_body'       => '',
			'prompt_img_conclusion' => '',
			'prompt_img_faq'        => '',
			'post_language'         => 'english',
			'responses_max_output_tokens' => 6000,
			'image_request_delay'   => 2,

			// CategorÃƒÂ­as/Tags
			'default_category'      => 'News',
			'keywords_to_categories'=> "", // lines: "Category: kw1, kw2"
			'default_tags'          => "", // tags permitidas separadas por comas

			// Blog scheduling / cron fill
			'enable_cron_fill'      => 0,

			// In-content image styles (not featured)
			'content_images_banner_enabled' => 1,
			'content_images_banner_css' =>
				"img.cbia-banner {\n" .
				"  width: 100%;\n" .
				"  height: 350px !important;\n" .
				"  object-fit: cover !important;\n" .
				"  object-position: 50% 60% !important;\n" .
				"  display: block !important;\n" .
				"  margin: 15px 0 !important;\n" .
				"  transition: transform 0.3s ease !important;\n" .
				"}",
		];
	}
}

if (!function_exists('cbia_get_settings')) {
	/**
	 * Devuelve settings mergeando defaults + guardados (sin borrar campos de otros tabs)
	 */
	function cbia_get_settings(): array {
		$defaults = cbia_get_default_settings();
		$stored = get_option(CBIA_OPTION_SETTINGS, []);
		if (!is_array($stored)) $stored = [];
		$merged = array_replace_recursive($defaults, $stored);
		$internal_images_enabled = function_exists('cbia_cap_enabled')
			? cbia_cap_enabled('internal_images')
			: (defined('CBIA_EDITION')
				? strtolower((string) CBIA_EDITION) === 'pro'
				: defined('CBIA_PRO_VERSION'));
		if (!$internal_images_enabled) {
			$merged['images_limit'] = 1; // featured only
		}
		return $merged;
	}
}

if (!function_exists('cbia_update_settings_merge')) {
	/**
	 * Merge seguro (no destruye otros campos).
	 */
	function cbia_update_settings_merge(array $partial): array {
		$current = get_option(CBIA_OPTION_SETTINGS, []);
		if (!is_array($current)) $current = [];
		foreach (array('openai_api_key', 'google_api_key', 'deepseek_api_key', 'google_service_account_json') as $secret_key) {
			if (!array_key_exists($secret_key, $partial)) continue;
			if ($secret_key === 'google_service_account_json') {
				if (trim((string)$partial[$secret_key]) === '' && !empty($current[$secret_key])) unset($partial[$secret_key]);
				continue;
			}
			$provider = str_replace('_api_key', '', $secret_key);
			if (function_exists('cbia_store_provider_api_key')) {
				cbia_store_provider_api_key($provider, $partial[$secret_key]);
				unset($partial[$secret_key]);
				$current = get_option(CBIA_OPTION_SETTINGS, []);
				if (!is_array($current)) $current = [];
				continue;
			}
			$result = function_exists('cbia_sanitize_provider_api_key') ? cbia_sanitize_provider_api_key($provider, $partial[$secret_key]) : array('valid' => trim((string)$partial[$secret_key]) !== '', 'value' => trim((string)$partial[$secret_key]));
			if (empty($result['valid'])) unset($partial[$secret_key]);
			else $partial[$secret_key] = (string)$result['value'];
		}
		$merged = array_replace_recursive($current, $partial);
		update_option(CBIA_OPTION_SETTINGS, $merged, false);
		return $merged;
	}
}

/**
 * Activation: ensure base options exist
 */
register_activation_hook(__FILE__, function () {
	if (get_option(CBIA_OPTION_SETTINGS, null) === null) {
		update_option(CBIA_OPTION_SETTINGS, cbia_get_default_settings(), false);
	}
	if (get_option(CBIA_OPTION_LOG, null) === null) {
		update_option(CBIA_OPTION_LOG, '', false);
	}
	if (get_option(CBIA_OPTION_LOG_COUNTER, null) === null) {
		update_option(CBIA_OPTION_LOG_COUNTER, 0, false);
	}
	if (get_option(CBIA_OPTION_STOP, null) === null) {
		update_option(CBIA_OPTION_STOP, 0, false);
	}
	if (get_option(CBIA_OPTION_CHECKPOINT, null) === null) {
		update_option(CBIA_OPTION_CHECKPOINT, [], false);
	}
});

/**
 * Load core modules (no legacy)
 */
$cbia_modules = [
	CBIA_INCLUDES_DIR . 'engine/engine.php',
];

foreach ($cbia_modules as $cbia_module_file) {
	if (file_exists($cbia_module_file)) {
		require_once $cbia_module_file;
	} else {
		// No romper el admin: solo log
		cbia_log('Missing core module: ' . basename($cbia_module_file), 'ERROR');
	}
}

// Registrar hooks core (notices, AJAX, assets)
if (function_exists('cbia_register_core_hooks')) {
	cbia_register_core_hooks();
}

/**
 * Admin: menÃƒÂº + tabs
 */
add_action('admin_menu', function () {
	if (class_exists('CBIA_Admin_Router')) {
		// El router nuevo registra su propio menÃƒÂº.
		return;
	}
	add_menu_page(
		'CBIAStudio BlogFlow with AI',
		'CBIAStudio BlogFlow with AI',
		'manage_options',
		'cbia',
		'cbia_render_admin_page',
		'dashicons-edit-page',
		56
	);
});

if (!function_exists('cbia_get_admin_tabs')) {
	function cbia_get_admin_tabs(): array {
		return [
			'config'   => ['label' => 'Configuration', 'render' => 'cbia_render_tab_config'],
			'blog'     => ['label' => 'Blog',          'render' => 'cbia_render_tab_blog'],
			'oldposts' => ['label' => 'Update old posts', 'render' => 'cbia_render_tab_oldposts'],
			'costes'   => ['label' => 'Costs',         'render' => 'cbia_render_tab_costes'],
			'yoast'    => ['label' => 'Yoast',         'render' => 'cbia_render_tab_yoast'],
		];
	}
}

if (!function_exists('cbia_get_current_tab')) {
	function cbia_get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin tab selector in URL.
		$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'config';
		$tabs = cbia_get_admin_tabs();
		return isset($tabs[$tab]) ? $tab : 'config';
	}
}

if (!function_exists('cbia_render_admin_page')) {
	function cbia_render_admin_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'cbiastudio-blogflow-ai'));
		}

		$tabs = cbia_get_admin_tabs();
		$current = cbia_get_current_tab();
		$current_tab = $tabs[$current] ?? null;

		echo '<div class="wrap">';
		echo '<h1>CBIAStudio BlogFlow with AI <small style="font-weight:normal;opacity:.7;">v' . esc_html(CBIA_VERSION) . '</small></h1>';
		echo '<h2 class="nav-tab-wrapper">';

		foreach ($tabs as $tab_key => $tab_data) {
			$label = $tab_data['label'] ?? $tab_key;
			$url = admin_url('admin.php?page=cbia&tab=' . $tab_key);
			$active = $tab_key === $current ? ' nav-tab-active' : '';
			echo '<a href="' . esc_url($url) . '" class="' . esc_attr('nav-tab' . $active) . '">' . esc_html($label) . '</a>';
		}

		echo '</h2>';

		if ($current_tab && isset($current_tab['render']) && is_callable($current_tab['render'])) {
			call_user_func($current_tab['render']);
		} else {
			echo '<p>' . esc_html__('This tab could not be loaded.', 'cbiastudio-blogflow-ai') . '</p>';
		}

		echo '</div>';
	}
}
