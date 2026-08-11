<?php
/**
 * Base helpers for engine.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_get_settings')) {
    function cbia_get_settings() {
        $apply_runtime_overrides = static function(array $settings): array {
            if (!empty($GLOBALS['cbia_runtime_settings_overrides']) && is_array($GLOBALS['cbia_runtime_settings_overrides'])) {
                return array_replace_recursive($settings, $GLOBALS['cbia_runtime_settings_overrides']);
            }
            return $settings;
        };

        if (defined('CBIA_OPTION_SETTINGS')) {
            $stored = get_option(CBIA_OPTION_SETTINGS, []);
            if (!is_array($stored)) $stored = [];

            if (function_exists('cbia_get_default_settings')) {
                $defaults = cbia_get_default_settings();
                return $apply_runtime_overrides(array_replace_recursive($defaults, $stored));
            }

            return $apply_runtime_overrides($stored);
        }

        $s = get_option('cbia_settings', []);
        $s = is_array($s) ? $s : [];
        return $apply_runtime_overrides($s);
    }
}

if (!function_exists('cbia_log_counter_key')) {
    function cbia_log_counter_key(){
        if (defined('CBIA_OPTION_LOG_COUNTER')) return CBIA_OPTION_LOG_COUNTER;
        return 'cbia_log_counter';
    }
}

if (!function_exists('cbia_log_key')) {
    function cbia_log_key(){
        if (defined('CBIA_OPTION_LOG')) return CBIA_OPTION_LOG;
        return 'cbia_activity_log';
    }
}

if (!function_exists('cbia_mask_sensitive_log_text')) {
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
}

if (!function_exists('cbia_sanitize_provider_error')) {
    function cbia_sanitize_provider_error(string $provider, $message, int $http_code = 0): string {
        $provider = sanitize_key($provider);
        if (in_array($http_code, array(401, 403), true)) {
            if ($provider === 'openai') return __('The OpenAI API key was rejected. Save it again in Settings.', 'cbiastudio-blogflow-ai');
            return sprintf(__('The %s API key was rejected. Save it again in Settings.', 'cbiastudio-blogflow-ai'), ucfirst($provider ?: 'provider'));
        }
        $safe = cbia_mask_sensitive_log_text((string)$message);
        return $safe !== '' ? $safe : __('The provider returned an error.', 'cbiastudio-blogflow-ai');
    }
}

if (!function_exists('cbia_is_likely_openai_key')) {
    function cbia_is_likely_openai_key(string $key): bool {
        $key = trim((string)$key);
        if ($key === '') return false;
        return (bool)preg_match('/^sk-[A-Za-z0-9_\-]{20,}$/', $key);
    }
}

if (!function_exists('cbia_is_masked_api_key_value')) {
    function cbia_is_masked_api_key_value($value): bool {
        $value = trim((string)$value);
        if ($value === '') return false;
        return (bool)preg_match('/^(?:[*?xX\x{2022}\x{25CF}\x{2026}\s]|\\xE2\\x80\\xA2)+$/u', $value);
    }
}

if (!function_exists('cbia_normalize_submitted_api_key')) {
    function cbia_normalize_submitted_api_key($value, string $provider = ''): string {
        $result = cbia_sanitize_provider_api_key($provider, $value);
        return !empty($result['valid']) ? (string)$result['value'] : '';
    }
}

if (!function_exists('cbia_sanitize_provider_api_key')) {
    function cbia_sanitize_provider_api_key(string $provider, $candidate): array {
        $provider = sanitize_key($provider);
        if (!is_string($candidate)) {
            return array('valid' => false, 'value' => '', 'code' => 'not_string');
        }
        // Only ordinary boundary spaces and pasted CR/LF are safe to remove.
        $value = (string)preg_replace('/^[ \r\n]+|[ \r\n]+$/', '', $candidate);
        if ($value === '') return array('valid' => false, 'value' => '', 'code' => 'empty');
        if (cbia_is_masked_api_key_value($value)) return array('valid' => false, 'value' => '', 'code' => 'masked');
        if (strpos($value, '*') !== false) return array('valid' => false, 'value' => '', 'code' => 'masked');
        if (preg_match('/[\x00-\x20\x7F]/', $value)) return array('valid' => false, 'value' => '', 'code' => 'control_or_whitespace');
        if (strlen($value) < 8) return array('valid' => false, 'value' => '', 'code' => 'too_short');
        return array('valid' => true, 'value' => $value, 'code' => '');
    }
}

if (!function_exists('cbia_provider_api_keys_option_name')) {
    function cbia_provider_api_keys_option_name(): string {
        return 'cbia_provider_api_keys';
    }
}

if (!function_exists('cbia_supported_credential_providers')) {
    function cbia_supported_credential_providers(): array {
        return array('openai', 'google', 'deepseek', 'anthropic');
    }
}

if (!function_exists('cbia_provider_credentials_schema_option_name')) {
    function cbia_provider_credentials_schema_option_name(): string {
        return 'cbia_provider_credentials_schema_version';
    }
}

if (!function_exists('cbia_provider_connection_status_option_name')) {
    function cbia_provider_connection_status_option_name(): string {
        return 'cbia_provider_connection_status';
    }
}

if (!function_exists('cbia_get_provider_api_keys_store')) {
    function cbia_get_provider_api_keys_store(): array {
        $stored = get_option(cbia_provider_api_keys_option_name(), array());
        return is_array($stored) ? $stored : array();
    }
}

if (!function_exists('cbia_store_provider_api_key')) {
    /** Persist one provider credential without touching provider, model or prompt settings. */
    function cbia_store_provider_api_key(string $provider, $candidate): array {
        $provider = sanitize_key($provider);
        if (!in_array($provider, cbia_supported_credential_providers(), true)) {
            return array('valid' => false, 'value' => '', 'code' => 'invalid_provider');
        }

        $result = cbia_sanitize_provider_api_key($provider, $candidate);
        if (empty($result['valid'])) return $result;

        $value = (string)$result['value'];
        $vault = cbia_get_provider_api_keys_store();
        $vault[$provider] = $value;
        update_option(cbia_provider_api_keys_option_name(), $vault, false);

        cbia_mark_provider_test_result($provider, array('status' => 'not_tested', 'reset_success' => true));

        return $result;
    }
}

if (!function_exists('cbia_save_provider_api_key')) {
    function cbia_save_provider_api_key(string $provider, $candidate): array {
        return cbia_store_provider_api_key($provider, $candidate);
    }
}

if (!function_exists('cbia_get_provider_connection_statuses')) {
    function cbia_get_provider_connection_statuses(): array {
        $stored = get_option(cbia_provider_connection_status_option_name(), array());
        return is_array($stored) ? $stored : array();
    }
}

if (!function_exists('cbia_get_provider_connection_status')) {
    function cbia_get_provider_connection_status(string $provider): array {
        $provider = sanitize_key($provider);
        $configured = in_array($provider, cbia_supported_credential_providers(), true) && cbia_has_provider_api_key($provider);
        if (!$configured) {
            return array('status' => 'not_configured', 'configured' => false, 'verified' => false, 'last_checked' => '', 'last_success' => '', 'models_count' => null);
        }
        $statuses = cbia_get_provider_connection_statuses();
        $status = isset($statuses[$provider]) && is_array($statuses[$provider]) ? $statuses[$provider] : array();
        $state = sanitize_key((string)($status['status'] ?? 'not_tested'));
        if (!in_array($state, array('not_tested', 'verified', 'authentication_error'), true)) $state = 'not_tested';
        return array(
            'status' => $state,
            'configured' => true,
            'verified' => $state === 'verified',
            'last_checked' => sanitize_text_field((string)($status['last_checked'] ?? '')),
            'last_success' => sanitize_text_field((string)($status['last_success'] ?? '')),
            'models_count' => isset($status['models_count']) && is_numeric($status['models_count']) ? max(0, (int)$status['models_count']) : null,
        );
    }
}

if (!function_exists('cbia_mark_provider_test_result')) {
    function cbia_mark_provider_test_result(string $provider, array $result): bool {
        $provider = sanitize_key($provider);
        if (!in_array($provider, cbia_supported_credential_providers(), true)) return false;
        $state = sanitize_key((string)($result['status'] ?? 'not_tested'));
        if (!in_array($state, array('not_tested', 'verified', 'authentication_error'), true)) $state = 'not_tested';
        $statuses = cbia_get_provider_connection_statuses();
        $previous = isset($statuses[$provider]) && is_array($statuses[$provider]) ? $statuses[$provider] : array();
        $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        $statuses[$provider] = array(
            'status' => $state,
            'last_checked' => $state === 'not_tested' ? '' : $now,
            'last_success' => $state === 'verified'
                ? $now
                : (!empty($result['reset_success']) ? '' : sanitize_text_field((string)($previous['last_success'] ?? ''))),
            'models_count' => isset($result['models_count']) && is_numeric($result['models_count'])
                ? max(0, (int)$result['models_count'])
                : (!empty($result['reset_success']) ? null : ($previous['models_count'] ?? null)),
        );
        return (bool)update_option(cbia_provider_connection_status_option_name(), $statuses, false);
    }
}

if (!function_exists('cbia_delete_provider_api_key')) {
    function cbia_delete_provider_api_key(string $provider): bool {
        $provider = sanitize_key($provider);
        if (!in_array($provider, cbia_supported_credential_providers(), true)) return false;

        $vault = cbia_get_provider_api_keys_store();
        unset($vault[$provider]);
        update_option(cbia_provider_api_keys_option_name(), $vault, false);

        // Remove only legacy copies for this provider so fallback cannot reconnect it.
        $settings = get_option('cbia_settings', array());
        if (is_array($settings)) {
            unset($settings[$provider . '_api_key']);
            if ($provider === 'openai') unset($settings['api_key']);
            update_option('cbia_settings', $settings, false);
        }
        $provider_settings = get_option('cbia_provider_settings', array());
        if (is_array($provider_settings) && isset($provider_settings['providers'][$provider]) && is_array($provider_settings['providers'][$provider])) {
            unset($provider_settings['providers'][$provider]['api_key']);
            update_option('cbia_provider_settings', $provider_settings, false);
        }
        $statuses = cbia_get_provider_connection_statuses();
        unset($statuses[$provider]);
        update_option(cbia_provider_connection_status_option_name(), $statuses, false);
        return true;
    }
}

if (!function_exists('cbia_maybe_migrate_provider_credentials')) {
    function cbia_maybe_migrate_provider_credentials(): void {
        $target_version = 1;
        if ((int)get_option(cbia_provider_credentials_schema_option_name(), 0) >= $target_version) return;

        $vault = cbia_get_provider_api_keys_store();
        $settings = get_option('cbia_settings', array());
        $settings = is_array($settings) ? $settings : array();
        $provider_settings = get_option('cbia_provider_settings', array());
        $provider_settings = is_array($provider_settings) ? $provider_settings : array();

        foreach (cbia_supported_credential_providers() as $provider) {
            $current = cbia_sanitize_provider_api_key($provider, (string)($vault[$provider] ?? ''));
            if (!empty($current['valid'])) continue;
            $dedicated = cbia_sanitize_provider_api_key($provider, (string)($settings[$provider . '_api_key'] ?? ''));
            $nested = cbia_sanitize_provider_api_key($provider, (string)($provider_settings['providers'][$provider]['api_key'] ?? ''));
            $legacy = array('valid' => false, 'value' => '');
            if ($provider === 'openai') $legacy = cbia_sanitize_provider_api_key($provider, (string)($settings['api_key'] ?? ''));
            $candidate = !empty($dedicated['valid']) ? $dedicated : (!empty($nested['valid']) ? $nested : $legacy);
            if (!empty($candidate['valid'])) $vault[$provider] = (string)$candidate['value'];
        }
        update_option(cbia_provider_api_keys_option_name(), $vault, false);
        update_option(cbia_provider_credentials_schema_option_name(), $target_version, false);
    }
}

if (!function_exists('cbia_provider_api_key_error_message')) {
    function cbia_provider_api_key_error_message(string $provider, string $code): string {
        if ($code === 'masked') return __('The visual mask was not saved as an API key. The previous key was preserved.', 'cbiastudio-blogflow-ai');
        if ($code === 'empty') return __('The API key field was empty. The previous key was preserved.', 'cbiastudio-blogflow-ai');
        return sprintf(__('The %s API key contains spaces, control characters or a mask. The previous key was preserved.', 'cbiastudio-blogflow-ai'), ucfirst(sanitize_key($provider)));
    }
}

if (!function_exists('cbia_log')) {
    function cbia_log($message, $level = 'INFO') {
        if (function_exists('cbia_fix_mojibake')) {
            $message = cbia_fix_mojibake($message);
        }
        if (function_exists('cbia_mask_sensitive_log_text')) {
            $message = cbia_mask_sensitive_log_text((string)$message);
        }
        if (defined('CBIA_OPTION_LOG') && defined('CBIA_OPTION_LOG_COUNTER')) {
            $level = strtoupper(trim((string)$level ?: 'INFO'));
            $ts = function_exists('cbia_now_mysql') ? cbia_now_mysql() : current_time('mysql');
            $line = '[' . $ts . '][' . $level . '] ' . (string)$message;
            $log = (string) get_option(CBIA_OPTION_LOG, '');
            $log = $log ? ($log . "\n" . $line) : $line;

            if (strlen($log) > 250000) {
                $lines = explode("\n", $log);
                if (count($lines) > 2000) {
                    $lines = array_slice($lines, -2000);
                    $log = implode("\n", $lines);
                }
            }

            update_option(CBIA_OPTION_LOG, $log, false);

            $cnt = (int) get_option(CBIA_OPTION_LOG_COUNTER, 0);
            update_option(CBIA_OPTION_LOG_COUNTER, $cnt + 1, false);

            wp_cache_delete(CBIA_OPTION_LOG, 'options');
            wp_cache_delete(CBIA_OPTION_LOG_COUNTER, 'options');
            return;
        }

        $log = (string) get_option(cbia_log_key(), '');
        $ts  = current_time('mysql');
        $log .= "[{$ts}] [{$level}] {$message}\n";
        if (strlen($log) > 250000) $log = substr($log, -250000);

        update_option(cbia_log_key(), $log, false);

        // contador anti-cache para polling
        $cnt = (int) get_option(cbia_log_counter_key(), 0);
        update_option(cbia_log_counter_key(), $cnt + 1, false);

        // fuerza a no servir valores cacheados de options
        wp_cache_delete(cbia_log_key(), 'options');
        wp_cache_delete(cbia_log_counter_key(), 'options');
    }
}

if (!function_exists('cbia_get_log')) {
    function cbia_get_log() {
        if (defined('CBIA_OPTION_LOG') && defined('CBIA_OPTION_LOG_COUNTER')) {
            $log = (string) get_option(CBIA_OPTION_LOG, '');
            if (function_exists('cbia_fix_mojibake')) {
                $log = cbia_fix_mojibake($log);
            }
            $counter = (int) get_option(CBIA_OPTION_LOG_COUNTER, 0);
            return array('log' => $log, 'counter' => $counter);
        }

        $log = (string) get_option(cbia_log_key(), '');
        if (function_exists('cbia_fix_mojibake')) {
            $log = cbia_fix_mojibake($log);
        }
        $counter = (int) get_option(cbia_log_counter_key(), 0);
        return array('log' => $log, 'counter' => $counter);
    }
}

if (!function_exists('cbia_clear_log')) {
    function cbia_clear_log() {
        if (defined('CBIA_OPTION_LOG') && defined('CBIA_OPTION_LOG_COUNTER')) {
            delete_option(CBIA_OPTION_LOG);
            delete_option(CBIA_OPTION_LOG_COUNTER);
            wp_cache_delete(CBIA_OPTION_LOG, 'options');
            wp_cache_delete(CBIA_OPTION_LOG_COUNTER, 'options');
            return;
        }

        delete_option(cbia_log_key());
        delete_option(cbia_log_counter_key());
        wp_cache_delete(cbia_log_key(), 'options');
        wp_cache_delete(cbia_log_counter_key(), 'options');
    }
}

if (!function_exists('cbia_is_stop_requested')) {
    /**
     * Unified STOP flag reader (used by engine + AJAX).
     */
    function cbia_is_stop_requested(): bool {
        // Preview/manual flows can bypass STOP so users are never blocked from testing.
        if (!empty($GLOBALS['cbia_ignore_stop'])) {
            return false;
        }
        if (function_exists('cbia_check_stop_flag')) {
            return (bool)cbia_check_stop_flag();
        }
        if (function_exists('cbia_stop_flag_key')) {
            return !empty(get_option(cbia_stop_flag_key(), 0));
        }
        if (defined('CBIA_OPTION_STOP')) {
            return !empty(get_option(CBIA_OPTION_STOP, 0));
        }
        return false;
    }
}

if (!function_exists('cbia_openai_api_key')) {
    /**
     * API key accessor (kept for legacy compatibility).
     */
    function cbia_openai_api_key(): string {
        if (function_exists('cbia_get_provider_api_key')) return cbia_get_provider_api_key('openai');
        if (function_exists('cbia_get_settings')) {
            $settings = cbia_get_settings();
            return (string)($settings['openai_api_key'] ?? '');
        }
        if (defined('CBIA_OPTION_SETTINGS')) {
            $settings = get_option(CBIA_OPTION_SETTINGS, []);
            return is_array($settings) ? (string)($settings['openai_api_key'] ?? '') : '';
        }
        $settings = get_option('cbia_settings', []);
        return is_array($settings) ? (string)($settings['openai_api_key'] ?? '') : '';
    }
}

// CAMBIO: helpers de proveedor/modelo/keys (texto e imagen) con compatibilidad
if (!function_exists('cbia_get_legacy_api_key')) {
    function cbia_get_legacy_api_key(): string {
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];
        if (!empty($settings['api_key'])) return (string)$settings['api_key'];
        return '';
    }
}

if (!function_exists('cbia_get_provider_api_key')) {
    /**
     * Obtiene la API key segun proveedor con fallback a estructuras antiguas.
     */
    function cbia_get_provider_api_key(string $provider): string {
        $provider = sanitize_key($provider);
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];

        $vault = cbia_get_provider_api_keys_store();
        $vault_result = cbia_sanitize_provider_api_key($provider, (string)($vault[$provider] ?? ''));
        if (!empty($vault_result['valid'])) return (string)$vault_result['value'];

        // Compatibility fallbacks for installations upgraded from older releases.
        $map = array(
            'openai'  => (string)($settings['openai_api_key'] ?? ''),
            'google'  => (string)($settings['google_api_key'] ?? ''),
            'deepseek'=> (string)($settings['deepseek_api_key'] ?? ''),
			'anthropic'=> (string)($settings['anthropic_api_key'] ?? ''),
        );
        $main_key = (string)($map[$provider] ?? '');

        $provider_key = '';
        if (function_exists('cbia_providers_get_settings')) {
            $p = cbia_providers_get_settings();
            if (!empty($p['providers'][$provider]['api_key'])) {
                $provider_key = (string)$p['providers'][$provider]['api_key'];
            }
        }

        $main_result = cbia_sanitize_provider_api_key($provider, $main_key);
        if (!empty($main_result['valid'])) return (string)$main_result['value'];
        $provider_result = cbia_sanitize_provider_api_key($provider, $provider_key);
        if (!empty($provider_result['valid'])) return (string)$provider_result['value'];

        // The legacy generic key belonged to OpenAI. Never reuse it across providers.
        if ($provider === 'openai') {
            $legacy = cbia_get_legacy_api_key();
            $legacy_result = cbia_sanitize_provider_api_key($provider, $legacy);
            if (!empty($legacy_result['valid'])) return (string)$legacy_result['value'];
        }

        return '';
    }
}

if (!function_exists('cbia_has_provider_api_key')) {
    function cbia_has_provider_api_key(string $provider): bool {
        return cbia_get_provider_api_key($provider) !== '';
    }
}

if (!function_exists('cbia_image_provider_preflight')) {
    function cbia_image_provider_preflight(): array {
        $provider = function_exists('cbia_get_image_provider') ? sanitize_key(cbia_get_image_provider()) : 'openai';
        $model = function_exists('cbia_get_image_model_for_provider') ? (string)cbia_get_image_model_for_provider($provider, '') : '';
        $key = cbia_get_provider_api_key($provider);
        if ($provider === '' || $key === '') {
            return array('ok' => false, 'provider' => $provider ?: 'unknown', 'model' => $model, 'code' => 'invalid_image_api_key', 'message' => cbia_provider_api_key_error_message($provider ?: 'provider', 'invalid'));
        }
        return array('ok' => true, 'provider' => $provider, 'model' => $model, 'code' => '', 'message' => '');
    }
}

if (!function_exists('cbia_generation_preflight')) {
    function cbia_generation_preflight(array $settings, bool $images_requested = true): array {
        $text_provider = sanitize_key((string)($settings['text_provider'] ?? 'openai'));
        $image_provider = sanitize_key((string)($settings['image_provider'] ?? 'openai'));
        $text_model = function_exists('cbia_get_text_model_for_provider')
            ? cbia_get_text_model_for_provider($text_provider, '')
            : sanitize_text_field((string)($settings['text_model'] ?? ''));
        $image_model = function_exists('cbia_get_image_model_for_provider')
            ? cbia_get_image_model_for_provider($image_provider, '')
            : sanitize_text_field((string)($settings['image_model'] ?? ''));
        $errors = array();

        if ($text_provider === '' || !cbia_has_provider_api_key($text_provider)) {
            $errors[] = array(
                'code' => 'missing_text_api_key',
                'provider' => $text_provider ?: 'unknown',
                'message' => sprintf('Missing %s API key for text generation.', ucfirst($text_provider ?: 'selected provider')),
            );
        } elseif ($text_model === '') {
            $errors[] = array('code' => 'missing_text_model', 'provider' => $text_provider, 'message' => 'The selected text model is empty.');
        }

        if ($images_requested) {
            $supports_images = !function_exists('cbia_providers_supports_image') || cbia_providers_supports_image($image_provider);
            if (!$supports_images) {
                $errors[] = array('code' => 'unsupported_image_provider', 'provider' => $image_provider, 'message' => 'The selected provider does not support image generation.');
            } elseif ($image_provider === '' || !cbia_has_provider_api_key($image_provider)) {
                $errors[] = array(
                    'code' => 'missing_image_api_key',
                    'provider' => $image_provider ?: 'unknown',
                    'message' => sprintf('Missing %s API key for image generation.', ucfirst($image_provider ?: 'selected provider')),
                );
            } elseif ($image_model === '') {
                $errors[] = array('code' => 'missing_image_model', 'provider' => $image_provider, 'message' => 'The selected image model is empty.');
            }
        }

        return array(
            'ok' => empty($errors),
            'errors' => $errors,
            'text' => array('provider' => $text_provider, 'model' => $text_model, 'key_configured' => cbia_has_provider_api_key($text_provider)),
            'image' => array('provider' => $image_provider, 'model' => $image_model, 'key_configured' => cbia_has_provider_api_key($image_provider), 'requested' => $images_requested),
        );
    }
}

if (!function_exists('cbia_record_local_preflight_failure')) {
    function cbia_record_local_preflight_failure(array $error, array $preflight, string $context = 'generation_preflight'): bool {
        if (!function_exists('cbia_costes_record_usage')) return false;
        $code = sanitize_key((string)($error['code'] ?? 'local_validation'));
        $is_image = strpos($code, 'image') !== false;
        $scope = $is_image ? (array)($preflight['image'] ?? array()) : (array)($preflight['text'] ?? array());
        return (bool)cbia_costes_record_usage(0, array(
            'type' => $is_image ? 'image' : 'text',
            'provider' => sanitize_key((string)($scope['provider'] ?? ($error['provider'] ?? 'unknown'))),
            'model' => sanitize_text_field((string)($scope['model'] ?? '')),
            'ok' => 0,
            'status' => 'blocked_local',
            'result_status' => 'blocked_local',
            'error' => sanitize_text_field((string)($error['message'] ?? 'Local preflight blocked the request.')),
            'error_type' => strpos($code, 'api_key') !== false ? 'missing_api_key' : $code,
            'request_sent' => 0,
            'billable' => 0,
            'cost_micro_usd' => 0,
            'cost_status' => 'exact',
            'cost_source' => 'local_preflight',
            'context' => $context,
            'attempt_id' => 'preflight-' . substr(hash('sha256', $context . '|' . $code . '|' . microtime(true) . '|' . wp_generate_uuid4()), 0, 24),
        ));
    }
}

// CAMBIO: helpers para Google Imagen (Vertex AI)
if (!function_exists('cbia_get_google_project_id')) {
    function cbia_get_google_project_id(): string {
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];
        return (string)($settings['google_project_id'] ?? '');
    }
}

if (!function_exists('cbia_get_google_location')) {
    function cbia_get_google_location(): string {
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];
        return (string)($settings['google_location'] ?? '');
    }
}

if (!function_exists('cbia_get_google_service_account_json')) {
    function cbia_get_google_service_account_json(): string {
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];
        return (string)($settings['google_service_account_json'] ?? '');
    }
}

if (!function_exists('cbia_get_text_provider')) {
    function cbia_get_text_provider(): string {
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];
        $p = sanitize_key((string)($settings['text_provider'] ?? ''));
        if ($p !== '') return $p;
        if (function_exists('cbia_providers_get_current_provider')) {
            return cbia_providers_get_current_provider();
        }
        return 'openai';
    }
}

if (!function_exists('cbia_get_image_provider')) {
    function cbia_get_image_provider(): string {
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];
        $p = sanitize_key((string)($settings['image_provider'] ?? ''));
        if ($p !== '' && function_exists('cbia_providers_supports_image') && cbia_providers_supports_image($p)) {
            return $p;
        }
        return 'openai';
    }
}

if (!function_exists('cbia_get_text_model_for_provider')) {
    function cbia_get_text_model_for_provider(string $provider, string $fallback = ''): string {
        $provider = sanitize_key($provider);
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];

        // CAMBIO: modelo guardado para texto (solo si coincide proveedor)
        if (!empty($settings['text_provider']) && sanitize_key((string)$settings['text_provider']) === $provider) {
            $m = (string)($settings['text_model'] ?? '');
            if ($m !== '') return $m;
        }

        // Fallback legacy: openai_model
        if ($provider === 'openai' && !empty($settings['openai_model'])) {
            return (string)$settings['openai_model'];
        }

        // Fallback providers settings (pro)
        if (function_exists('cbia_providers_get_provider')) {
            $cfg = cbia_providers_get_provider($provider);
            if (!empty($cfg['model'])) return (string)$cfg['model'];
        }

        return $fallback;
    }
}

if (!function_exists('cbia_get_image_model_for_provider')) {
    function cbia_get_image_model_for_provider(string $provider, string $fallback = ''): string {
        $provider = sanitize_key($provider);
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : [];

        // CAMBIO: modelo guardado para imagen (solo si coincide proveedor)
        if (!empty($settings['image_provider']) && sanitize_key((string)$settings['image_provider']) === $provider) {
            $m = (string)($settings['image_model'] ?? '');
            if ($m !== '') return $m;
        }

        // Fallback legacy: image_model global
        if (!empty($settings['image_model']) && $provider === 'openai') {
            return (string)$settings['image_model'];
        }

        // Fallback providers settings (pro)
        if (function_exists('cbia_providers_get_provider')) {
            $cfg = cbia_providers_get_provider($provider);
            if (!empty($cfg['image_model'])) return (string)$cfg['image_model'];
        }

        return $fallback;
    }
}

if (!function_exists('cbia_openai_consent_ok')) {
    /**
     * User consent flag for OpenAI usage (required for external calls).
     */
    function cbia_openai_consent_ok(): bool {
        return true;
    }
}

if (!function_exists('cbia_http_headers_openai')) {
    /**
     * Build HTTP headers for OpenAI API calls.
     */
    function cbia_http_headers_openai(string $api_key): array {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];

        // Optional: Organization header if present in settings.
        if (function_exists('cbia_get_settings')) {
            $settings = cbia_get_settings();
            $org = trim((string)($settings['openai_org'] ?? ''));
            if ($org !== '') {
                $headers['OpenAI-Organization'] = $org;
            }
        }

        return $headers;
    }
}

if (!function_exists('cbia_deepseek_test_models_connection')) {
    function cbia_deepseek_test_models_connection($model): array {
        $cfg = function_exists('cbia_get_provider_config') ? cbia_get_provider_config('deepseek') : array();
        $key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('deepseek') : (string)($cfg['api_key'] ?? '');
        if ($key === '') return array('ok'=>false,'status'=>'missing_api_key','http_code'=>0,'request_sent'=>0,'model_available'=>false,'request_id'=>'','elapsed_ms'=>0);
        $base = rtrim((string)($cfg['base_url'] ?? 'https://api.deepseek.com'), '/');
        $started = microtime(true);
        $resp = wp_remote_get($base . '/models', array('timeout'=>30,'headers'=>array('Authorization'=>'Bearer ' . $key)));
        $elapsed = max(0, (int)round((microtime(true) - $started) * 1000));
        if (is_wp_error($resp)) {
            $message = function_exists('cbia_mask_sensitive_log_text') ? cbia_mask_sensitive_log_text($resp->get_error_message()) : sanitize_text_field($resp->get_error_message());
            $status = stripos($message, 'timeout') !== false || stripos($message, 'timed out') !== false || stripos($message, 'cURL error 28') !== false ? 'timeout' : 'transport_error';
            return array('ok'=>false,'status'=>$status,'http_code'=>0,'request_sent'=>1,'model_available'=>false,'request_id'=>'','elapsed_ms'=>$elapsed,'error'=>$message);
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $request_id = sanitize_text_field((string)wp_remote_retrieve_header($resp, 'x-request-id'));
        $data = json_decode((string)wp_remote_retrieve_body($resp), true);
        $status = $code === 401 || $code === 403 ? 'authentication_failed' : ($code === 402 ? 'billing_failed' : ($code === 429 ? 'rate_limited' : ($code >= 500 ? 'provider_error' : 'invalid_response')));
        if ($code < 200 || $code >= 300) return array('ok'=>false,'status'=>$status,'http_code'=>$code,'request_sent'=>1,'model_available'=>false,'request_id'=>$request_id,'elapsed_ms'=>$elapsed);
        if (!is_array($data) || !is_array($data['data'] ?? null)) return array('ok'=>false,'status'=>'invalid_response','http_code'=>$code,'request_sent'=>1,'model_available'=>false,'request_id'=>$request_id,'elapsed_ms'=>$elapsed);
        $ids = array();
        foreach ($data['data'] as $row) if (is_array($row) && !empty($row['id'])) $ids[] = (string)$row['id'];
        return array('ok'=>true,'status'=>'connection_ok','http_code'=>$code,'request_sent'=>1,'model_available'=>in_array((string)$model, $ids, true),'request_id'=>$request_id,'elapsed_ms'=>$elapsed);
    }
}

if (!function_exists('cbia_run_test_configuration')) {
    /**
     * Basic configuration test: validates settings and (optionally) performs a lightweight API call.
     * Returns an array with ok/error details for future UI use.
     */
    function cbia_run_test_configuration(bool $advanced = false): array {
        $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
        $provider = function_exists('cbia_get_text_provider') ? cbia_get_text_provider() : sanitize_key((string)($settings['text_provider'] ?? 'openai'));
        $model = function_exists('cbia_get_text_model_for_provider') ? cbia_get_text_model_for_provider($provider, '') : sanitize_text_field((string)($settings['text_model'] ?? ''));
        $image_provider = function_exists('cbia_get_image_provider') ? cbia_get_image_provider() : sanitize_key((string)($settings['image_provider'] ?? 'openai'));
        $image_model = function_exists('cbia_get_image_model_for_provider') ? cbia_get_image_model_for_provider($image_provider, '') : sanitize_text_field((string)($settings['image_model'] ?? ''));
        $key_configured = function_exists('cbia_has_provider_api_key') && cbia_has_provider_api_key($provider);
        $image_key_configured = function_exists('cbia_has_provider_api_key') && cbia_has_provider_api_key($image_provider);
        $stop_before = cbia_is_stop_requested();
		$endpoints = array('openai' => 'api.openai.com', 'deepseek' => 'api.deepseek.com', 'google' => 'generativelanguage.googleapis.com', 'anthropic' => 'api.anthropic.com');
        $endpoint = (string)($endpoints[$provider] ?? 'unknown');
        $thinking = sanitize_key((string)($settings['deepseek_thinking_mode'] ?? 'disabled'));
        if (!in_array($thinking, array('disabled', 'enabled'), true)) $thinking = 'disabled';

        $log = static function (string $message, string $level = 'INFO'): void {
            if (function_exists('cbia_log')) cbia_log($message, $level);
            elseif (function_exists('cbia_log_message')) cbia_log_message('[' . $level . '] ' . $message);
        };

        $log('TEST text: provider=' . $provider . ' model=' . $model . ' endpoint=' . $endpoint . ' api_key_configured=' . ($key_configured ? 'yes' : 'no') . ' fallback_allowed=no attempt=1/1 thinking=' . ($provider === 'deepseek' ? $thinking : 'n/a') . ' timeout=30 stop_flag_before=' . ($stop_before ? 'enabled' : 'disabled'));
        $log('TEST images: provider=' . $image_provider . ' model=' . $image_model . ' api_key_configured=' . ($image_key_configured ? 'yes' : 'no') . ' result=local_configuration_only paid_generation=no');

		$allowed_providers = array('openai', 'deepseek', 'google', 'anthropic');
        $allowed_models = function_exists('cbia_providers_get_text_model_list') ? (array)cbia_providers_get_text_model_list($provider) : array();
        $error_type = '';
        if (!in_array($provider, $allowed_providers, true)) $error_type = 'unsupported_provider';
        elseif ($model === '') $error_type = 'missing_model';
        elseif (!empty($allowed_models) && !in_array($model, $allowed_models, true)) $error_type = 'invalid_model';
        elseif (!$key_configured) $error_type = 'missing_api_key';
        elseif (!function_exists('cbia_openai_responses_call')) $error_type = 'client_unavailable';

        if ($error_type !== '') {
            $message = $error_type === 'missing_api_key'
                ? sprintf(__('Missing %s API key.', 'cbiastudio-blogflow-ai'), ucfirst($provider))
                : __('The selected text configuration is not valid.', 'cbiastudio-blogflow-ai');
            $saved = function_exists('cbia_costes_record_usage') ? (bool)cbia_costes_record_usage(0, array(
                'type' => 'text', 'provider' => $provider, 'model' => $model, 'model_requested' => $model, 'model_effective' => $model,
                'phase' => 'configuration_test', 'context' => 'configuration_test', 'ok' => 0, 'status' => 'blocked_local', 'result_status' => 'blocked_local',
                'error' => $message, 'error_type' => $error_type, 'request_sent' => 0, 'billable' => 0, 'cost_micro_usd' => 0,
                'cost_status' => 'exact', 'cost_source' => 'local_preflight', 'attempt_id' => 'configuration-test-' . substr(hash('sha256', microtime(true) . '|' . wp_generate_uuid4()), 0, 24),
            )) : false;
            $stop_after = cbia_is_stop_requested();
            $log('TEST text blocked: provider=' . $provider . ' model=' . $model . ' api_key_configured=' . ($key_configured ? 'yes' : 'no') . ' request_sent=no billable=no error_type=' . $error_type . ' usage_event_saved=' . ($saved ? 'yes' : 'no') . ' stop_flag_after=' . ($stop_after ? 'enabled' : 'disabled') . ' stop_flag_changed=' . ($stop_before !== $stop_after ? 'yes' : 'no'), 'ERROR');
            return array('ok' => false, 'error' => $error_type, 'message' => $message, 'text' => array('provider' => $provider, 'model' => $model), 'image' => array('provider' => $image_provider, 'model' => $image_model, 'key_configured' => $image_key_configured), 'stop_flag_changed' => $stop_before !== $stop_after);
        }

        $connection = array();
        if ($provider === 'deepseek') {
            $connection = cbia_deepseek_test_models_connection($model);
            $log('TEST DeepSeek connection: provider=deepseek model=' . $model . ' endpoint=api.deepseek.com HTTP=' . (int)($connection['http_code'] ?? 0) . ' selected_model_available=' . (!empty($connection['model_available']) ? 'yes' : 'no') . ' authentication_status=' . (!empty($connection['ok']) ? 'ok' : (string)($connection['status'] ?? 'unknown')) . ' request_id=' . (string)($connection['request_id'] ?? '') . ' elapsed_ms=' . (int)($connection['elapsed_ms'] ?? 0) . ' stop_flag_changed=no');
            if (empty($connection['ok'])) {
                $message = __('DeepSeek connection or authentication test failed.', 'cbiastudio-blogflow-ai');
                return array('ok'=>false,'error'=>(string)($connection['status'] ?? 'connection_error'),'message'=>$message,'connection'=>$connection,'text'=>array('provider'=>$provider,'model'=>$model),'image'=>array('provider'=>$image_provider,'model'=>$image_model,'key_configured'=>$image_key_configured),'stop_flag_changed'=>false);
            }
			if (empty($connection['model_available'])) return array('ok'=>false,'connection_ok'=>true,'error'=>'model_unavailable','message'=>__('DeepSeek connection and authentication are valid, but the selected model is not available.', 'cbiastudio-blogflow-ai'),'connection'=>$connection,'text'=>array('provider'=>$provider,'model'=>$model),'image'=>array('provider'=>$image_provider,'model'=>$image_model,'key_configured'=>$image_key_configured),'stop_flag_changed'=>false);
        }

        $context = array(
            'phase' => 'configuration_test', 'context' => 'configuration_test', 'provider' => $provider, 'model' => $model,
            'allow_fallback' => false, 'ignore_stop' => true, 'defer_usage_recording' => true, 'post_id' => 0,
            'thinking_override' => ($provider === 'deepseek' && !$advanced) ? 'disabled' : $thinking,
            'reasoning_effort_override' => $advanced ? sanitize_key((string)($settings['deepseek_reasoning_effort'] ?? 'high')) : '',
            'temporary_context_id' => 'configuration-test-' . substr(hash('sha256', microtime(true) . '|' . wp_generate_uuid4()), 0, 24),
        );
        $test_max = $advanced ? max(256, min(12000, (int)($settings['responses_max_output_tokens'] ?? 6000))) : 32;
        $res = cbia_openai_responses_call(__('Reply only with OK.', 'cbiastudio-blogflow-ai'), 'configuration_test', 1, $test_max, $context);
        $ok = is_array($res) && !empty($res[0]);
        $usage = is_array($res) ? (array)($res[2] ?? array()) : array();
        $model_effective = is_array($res) ? sanitize_text_field((string)($res[3] ?? $model)) : $model;
        $error = is_array($res) ? sanitize_text_field((string)($res[4] ?? '')) : 'unknown_error';
        $raw = is_array($res) && is_array($res[5] ?? null) ? $res[5] : array();
        $meta = is_array($raw['_cbia_request_meta'] ?? null) ? $raw['_cbia_request_meta'] : array();
        if (empty($meta) && !empty($raw['_cbia_attempts']) && is_array($raw['_cbia_attempts'])) {
            $attempt_rows = $raw['_cbia_attempts'];
            $meta = (array)end($attempt_rows);
        }
        $http_code = max(0, (int)($meta['http_code'] ?? 0));
        $elapsed_ms = max(0, (int)($meta['elapsed_ms'] ?? 0));
        $request_sent = $http_code > 0 || $elapsed_ms > 0 || !empty($meta['request_id']);
        $is_timeout = !empty($meta['timeout']) || stripos($error, 'timeout') !== false || stripos($error, 'timed out') !== false;
        $reasoning_present = !empty($meta['reasoning_content_present']);
        $empty_status = sanitize_key((string)($meta['status'] ?? ''));
        $result_status = $ok ? 'chat_ok' : ($reasoning_present && $http_code === 200 ? 'chat_incomplete' : ($empty_status === 'chat_empty_content' ? 'chat_empty_content' : ($is_timeout ? 'timeout' : 'provider_error')));
        $error_type = $ok ? '' : (in_array($result_status, array('chat_incomplete', 'chat_empty_content'), true) ? $result_status : ($http_code === 401 || $http_code === 403 ? 'authentication' : ($http_code === 402 ? 'billing' : ($is_timeout ? 'timeout' : ($http_code === 429 ? 'rate_limit' : 'provider_error')))));
        $provider_rejected = in_array($http_code, array(401, 402, 403), true);
        $usage_saved = function_exists('cbia_costes_record_usage') ? (bool)cbia_costes_record_usage(0, array_merge($usage, $meta, array(
            'type' => 'text', 'provider' => $provider, 'model' => $model_effective, 'model_requested' => $model, 'model_effective' => $model_effective,
            'phase' => 'configuration_test', 'context' => 'configuration_test', 'ok' => $ok ? 1 : 0, 'status' => $result_status, 'result_status' => $result_status,
            'error' => $ok ? '' : $error, 'error_type' => $error_type, 'request_sent' => $request_sent ? 1 : 0, 'billable' => $request_sent && !$provider_rejected ? 1 : 0,
            'cost_micro_usd' => $provider_rejected ? 0 : (int)($meta['cost_micro_usd'] ?? 0),
            'cost_status' => $provider_rejected ? 'exact' : ($is_timeout ? 'unknown' : 'estimated'), 'cost_source' => $provider_rejected ? 'provider_rejected_before_generation' : ($is_timeout ? 'provider_response_unknown' : 'plugin_catalog'),
            'attempt_id' => $context['temporary_context_id'] . '-a1', 'elapsed_ms' => $elapsed_ms, 'http_code' => $http_code,
        ))) : false;
        $stop_after = cbia_is_stop_requested();
        $changed = $stop_before !== $stop_after;
        if ($provider === 'deepseek') {
            $log('TEST DeepSeek chat: mode=' . ($advanced ? 'advanced' : 'basic') . ' thinking=' . (string)($context['thinking_override'] ?? $thinking) . ' effort=' . ($advanced ? (string)($context['reasoning_effort_override'] ?? '') : 'n/a') . ' attempt=1/1 fallback_allowed=no HTTP=' . $http_code . ' finish_reason=' . (string)($meta['finish_reason'] ?? '') . ' content_present=' . (!empty($meta['content_present']) ? 'yes' : 'no') . ' reasoning_content_present=' . ($reasoning_present ? 'yes' : 'no') . ' reasoning_tokens=' . (int)($usage['reasoning_tokens'] ?? 0) . ' result_status=' . $result_status . ' usage_event_saved=' . ($usage_saved ? 'yes' : 'no'));
        }

        if ($ok) {
            $message = sprintf(__('%1$s configured correctly. Model tested: %2$s.', 'cbiastudio-blogflow-ai'), ucfirst($provider), $model_effective);
            $log('TEST text OK: provider=' . $provider . ' model_requested=' . $model . ' model_effective=' . $model_effective . ' HTTP=' . $http_code . ' request_id=' . sanitize_text_field((string)($meta['request_id'] ?? '')) . ' tokens_input=' . (int)($usage['input_tokens'] ?? 0) . ' tokens_output=' . (int)($usage['output_tokens'] ?? 0) . ' cost_status=' . ($is_timeout ? 'unknown' : 'estimated') . ' elapsed_ms=' . $elapsed_ms . ' usage_event_saved=' . ($usage_saved ? 'yes' : 'no') . ' stop_flag_after=' . ($stop_after ? 'enabled' : 'disabled') . ' stop_flag_changed=' . ($changed ? 'yes' : 'no'));
        } else {
            $message = $error_type === 'chat_incomplete'
                ? __('DeepSeek responded correctly, but the reasoning test did not produce final content. The connection and API key are valid.', 'cbiastudio-blogflow-ai')
                : ($error_type === 'authentication'
                ? sprintf(__('%s rejected the API key.', 'cbiastudio-blogflow-ai'), ucfirst($provider))
                : sprintf(__('Could not validate %s.', 'cbiastudio-blogflow-ai'), ucfirst($provider)));
            $log('TEST text failed: provider=' . $provider . ' model=' . $model . ' HTTP=' . $http_code . ' error_type=' . $error_type . ' message=' . $error . ' usage_event_saved=' . ($usage_saved ? 'yes' : 'no') . ' stop_flag_after=' . ($stop_after ? 'enabled' : 'disabled') . ' stop_flag_changed=' . ($changed ? 'yes' : 'no'), 'ERROR');
        }

        return array('ok' => $ok, 'connection_ok' => $provider === 'deepseek' ? !empty($connection['ok']) : $ok, 'chat_status' => $result_status, 'advanced' => $advanced, 'error' => $ok ? '' : $error_type, 'message' => $message, 'http_code' => $http_code, 'usage_saved' => $usage_saved, 'connection' => $connection, 'text' => array('provider' => $provider, 'model' => $model_effective, 'usage' => $usage, 'finish_reason'=>(string)($meta['finish_reason'] ?? '')), 'image' => array('provider' => $image_provider, 'model' => $image_model, 'key_configured' => $image_key_configured, 'paid_generation' => false), 'stop_flag_changed' => $changed);
    }
}

if (!function_exists('cbia_fix_bracket_headings')) {
    /**
     * Convierte headings en formato [H2]...[/H2] a HTML válido.
     */
    function cbia_fix_bracket_headings($html): string {
        $text = (string)$html;
        // [H2]Título[/H2] => <h2>Título</h2>
        $text = preg_replace_callback('/\\[(H[1-6])\\]\\s*(.*?)\\s*\\[\\/\\1\\]/si', function ($m) {
            $tag = strtolower($m[1]);
            $content = trim((string)$m[2]);
            return '<' . $tag . '>' . $content . '</' . $tag . '>';
        }, $text);

        return $text;
    }
}

if (!function_exists('cbia_replace_first_occurrence')) {
    /**
     * Replace first occurrence of a substring.
     */
    function cbia_replace_first_occurrence($haystack, $needle, $replacement) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        $replacement = (string)$replacement;
        if ($needle === '') return $haystack;
        $pos = strpos($haystack, $needle);
        if ($pos === false) return $haystack;
        return substr($haystack, 0, $pos) . $replacement . substr($haystack, $pos + strlen($needle));
    }
}
