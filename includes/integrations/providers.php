<?php
/**
 * Providers registry (PRO).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_providers_defaults')) {
    function cbia_providers_defaults(): array {
        return array(
            'current_provider' => 'openai',
            'providers' => array(
                'openai' => array(
                    'label' => 'OpenAI',
                    'model' => 'gpt-5-mini',
                    // CAMBIO: modelo de imagen por proveedor (persistencia)
                    'image_model' => 'gpt-image-2',
                    'base_url' => 'https://api.openai.com',
                    'api_version' => 'v1',
                ),
                'google' => array(
                    'label' => 'Google (Gemini)',
                    'model' => 'gemini-2.5-flash',
                    // CAMBIO: modelo de imagen por proveedor (persistencia)
                    'image_model' => 'imagen-3.0-generate-002',
                    'base_url' => 'https://generativelanguage.googleapis.com',
                    'api_version' => 'v1beta',
                ),
                'deepseek' => array(
                    'label' => 'DeepSeek',
                    'model' => 'deepseek-v4-flash',
                    // CAMBIO: sin modelo de imagen por defecto
                    'image_model' => '',
                    'base_url' => 'https://api.deepseek.com',
                    'api_version' => '',
                ),
            ),
        );
    }
}

if (!function_exists('cbia_providers_get_settings')) {
    function cbia_providers_get_settings(): array {
        $settings = get_option('cbia_provider_settings', array());
        return is_array($settings) ? $settings : array();
    }
}

if (!function_exists('cbia_providers_save_settings')) {
    function cbia_providers_save_settings(array $settings): void {
        $current = get_option('cbia_provider_settings', array());
        $current = is_array($current) ? $current : array();
        if (!empty($settings['providers']) && is_array($settings['providers'])) {
            foreach ($settings['providers'] as $provider => $provider_settings) {
                if (!is_array($provider_settings)) continue;
                $new_key = (string)($provider_settings['api_key'] ?? '');
                $result = function_exists('cbia_sanitize_provider_api_key')
                    ? cbia_sanitize_provider_api_key((string)$provider, $new_key)
                    : array('valid' => $new_key !== '', 'value' => $new_key);
                if (!empty($result['valid'])) {
					if (function_exists('cbia_store_provider_api_key')) cbia_store_provider_api_key((string)$provider, (string)$result['value']);
                }
                unset($settings['providers'][$provider]['api_key']);
            }
        }
        update_option('cbia_provider_settings', array_replace_recursive($current, $settings), false);
    }
}

if (!function_exists('cbia_providers_get_all')) {
    function cbia_providers_get_all(): array {
        $defaults = cbia_providers_defaults();
        $stored = cbia_providers_get_settings();
        $merged = $defaults;

        if (isset($stored['current_provider'])) {
            $merged['current_provider'] = (string) $stored['current_provider'];
        } elseif (isset($stored['provider'])) {
            $merged['current_provider'] = (string) $stored['provider'];
        }

        if (isset($stored['providers']) && is_array($stored['providers'])) {
            foreach ($stored['providers'] as $key => $vals) {
                if (!isset($merged['providers'][$key]) || !is_array($vals)) continue;
                $merged['providers'][$key] = array_merge($merged['providers'][$key], $vals);
            }
        }

        return $merged;
    }
}

if (!function_exists('cbia_providers_get_model_lists_store')) {
    function cbia_providers_get_model_lists_store(): array {
        $stored = get_option('cbia_provider_model_lists', array());
        return is_array($stored) ? $stored : array();
    }
}

if (!function_exists('cbia_providers_get_model_sync_meta')) {
    function cbia_providers_get_model_sync_meta(): array {
        $stored = get_option('cbia_provider_model_sync_meta', array());
        return is_array($stored) ? $stored : array();
    }
}

if (!function_exists('cbia_providers_save_model_sync_meta')) {
    function cbia_providers_save_model_sync_meta(string $provider, array $meta): void {
        $provider = sanitize_key($provider);
        if ($provider === '') return;
        $store = cbia_providers_get_model_sync_meta();
        $store[$provider] = $meta;
        update_option('cbia_provider_model_sync_meta', $store, false);
    }
}

if (!function_exists('cbia_providers_save_model_list')) {
    function cbia_providers_save_model_list(string $provider, array $models): void {
        $provider = sanitize_key($provider);
        if ($provider === '') return;
        $store = cbia_providers_get_model_lists_store();
        $store[$provider] = array_values(array_unique(array_filter(array_map('trim', $models))));
        update_option('cbia_provider_model_lists', $store, false);
    }
}

if (!function_exists('cbia_providers_fetch_openai_models')) {
    function cbia_providers_fetch_openai_models(array $provider_cfg): array {
        $api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('openai') : (string)($provider_cfg['api_key'] ?? '');
        if ($api_key === '') return array();

        $base_url = rtrim((string)($provider_cfg['base_url'] ?? 'https://api.openai.com'), '/');
        $api_version = trim((string)($provider_cfg['api_version'] ?? 'v1'), '/');
        $url = $base_url . '/' . $api_version . '/models';

        $headers = function_exists('cbia_http_headers_openai')
            ? cbia_http_headers_openai($api_key)
            : array('Authorization' => 'Bearer ' . $api_key);

        $resp = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 30,
        ));
        if (is_wp_error($resp)) return array();

        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return array();

        $body = (string)wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['data']) || !is_array($data['data'])) return array();

        $models = array();
        foreach ($data['data'] as $row) {
            if (!is_array($row)) continue;
            $id = (string)($row['id'] ?? '');
            if ($id === '') continue;
            if (stripos($id, 'gpt-') !== 0 && stripos($id, 'o1-') !== 0) continue;
            $models[] = $id;
        }
        sort($models, SORT_STRING);
        return $models;
    }
}

if (!function_exists('cbia_providers_fetch_google_models')) {
    function cbia_providers_fetch_google_models(array $provider_cfg): array {
        $api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('google') : (string)($provider_cfg['api_key'] ?? '');
        if ($api_key === '') return array();

        $base_url = rtrim((string)($provider_cfg['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/');
        $api_version = trim((string)($provider_cfg['api_version'] ?? 'v1beta'), '/');
        $url = $base_url . '/' . $api_version . '/models';

        $resp = wp_remote_get($url, array(
            'headers' => array(
                'x-goog-api-key' => $api_key,
            ),
            'timeout' => 30,
        ));
        if (is_wp_error($resp)) return array();

        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return array();

        $body = (string)wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['models']) || !is_array($data['models'])) return array();

        $models = array();
        foreach ($data['models'] as $row) {
            if (!is_array($row)) continue;
            $name = (string)($row['name'] ?? '');
            if ($name === '') continue;
            // Filter to models that support generateContent when field exists.
            if (!empty($row['supportedGenerationMethods']) && is_array($row['supportedGenerationMethods'])) {
                if (!in_array('generateContent', $row['supportedGenerationMethods'], true)) continue;
            }
            if (strpos($name, 'models/') === 0) {
                $name = substr($name, strlen('models/'));
            }
            $models[] = $name;
        }
        $models = array_values(array_unique(array_filter(array_map('trim', $models))));
        sort($models, SORT_STRING);
        return $models;
    }
}

if (!function_exists('cbia_providers_fetch_deepseek_models')) {
    function cbia_providers_fetch_deepseek_models(array $provider_cfg): array {
        $api_key = function_exists('cbia_get_provider_api_key') ? cbia_get_provider_api_key('deepseek') : (string)($provider_cfg['api_key'] ?? '');
        if ($api_key === '') return array();

        $base_url = rtrim((string)($provider_cfg['base_url'] ?? 'https://api.deepseek.com'), '/');
        $api_version = trim((string)($provider_cfg['api_version'] ?? 'v1'), '/');
        $path = $api_version !== '' ? '/' . $api_version . '/models' : '/models';
        $url = $base_url . $path;

        $resp = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 30,
        ));
        if (is_wp_error($resp)) return array();

        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return array();

        $body = (string)wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['data']) || !is_array($data['data'])) return array();

        $models = array();
        foreach ($data['data'] as $row) {
            if (!is_array($row)) continue;
            $id = (string)($row['id'] ?? '');
            if ($id === '') continue;
            $models[] = $id;
        }
        $models = array_values(array_unique(array_filter(array_map('trim', $models))));
        sort($models, SORT_STRING);
        return $models;
    }
}

if (!function_exists('cbia_providers_sync_models')) {
    function cbia_providers_sync_models(string $provider): array {
        $provider = sanitize_key($provider);
        $list = cbia_providers_get_model_list($provider);
        $source = 'local';
        $error = '';

        cbia_providers_save_model_list($provider, $list);
        cbia_providers_save_model_sync_meta($provider, array(
            'ts' => function_exists('cbia_now_mysql') ? cbia_now_mysql() : current_time('mysql'),
            'source' => $source,
            'count' => count($list),
            'error' => $error,
        ));

        return array(
            'ok' => !empty($list),
            'provider' => $provider,
            'source' => $source,
            'count' => count($list),
            'error' => $error,
        );
    }
}

if (!function_exists('cbia_providers_get_current_provider')) {
    function cbia_providers_get_current_provider(): string {
        $all = cbia_providers_get_all();
        $current = (string) ($all['current_provider'] ?? 'openai');
        return $current !== '' ? $current : 'openai';
    }
}

if (!function_exists('cbia_providers_get_provider')) {
    function cbia_providers_get_provider(string $key): array {
        $all = cbia_providers_get_all();
        return $all['providers'][$key] ?? array();
    }
}

if (!function_exists('cbia_providers_get_model_list')) {
    function cbia_providers_get_model_list(string $provider): array {
        return function_exists('cbia_provider_catalog_model_ids')
            ? cbia_provider_catalog_model_ids($provider, 'text')
            : array();
    }
}

// CAMBIO: lista de modelos de texto por proveedor (alias de model_list actual)
if (!function_exists('cbia_providers_get_text_model_list')) {
    function cbia_providers_get_text_model_list(string $provider): array {
        return cbia_providers_get_model_list($provider);
    }
}

if (!function_exists('cbia_providers_get_image_model_list')) {
    /**
     * Modelos de imagen por proveedor.
     */
    function cbia_providers_get_image_model_list(string $provider): array {
        return function_exists('cbia_provider_catalog_model_ids')
            ? cbia_provider_catalog_model_ids($provider, 'image')
            : array();
    }
}

if (!function_exists('cbia_providers_get_image_capable_providers')) {
    function cbia_providers_get_image_capable_providers(): array {
        $providers = array();
        if (function_exists('cbia_provider_model_catalog')) {
            foreach (cbia_provider_model_catalog() as $provider => $definition) {
                if (in_array('image', (array)($definition['capabilities'] ?? array()), true)) $providers[] = (string)$provider;
            }
        }
        return $providers ?: array('openai', 'google');
    }
}

if (!function_exists('cbia_providers_supports_image')) {
    function cbia_providers_supports_image(string $provider): bool {
        return in_array(sanitize_key($provider), cbia_providers_get_image_capable_providers(), true);
    }
}

// CAMBIO: recomendados por proveedor (texto/imagen)
if (!function_exists('cbia_providers_get_recommended_text_model')) {
    function cbia_providers_get_recommended_text_model(string $provider): string {
        $provider = sanitize_key($provider);
        $recommended = function_exists('cbia_provider_catalog_recommended_model') ? cbia_provider_catalog_recommended_model($provider, 'text') : '';
        return $recommended !== '' ? $recommended : 'gpt-5-mini';
    }
}

if (!function_exists('cbia_providers_get_recommended_image_model')) {
    function cbia_providers_get_recommended_image_model(string $provider): string {
        $provider = sanitize_key($provider);
        if (!cbia_providers_supports_image($provider)) return '';
        return function_exists('cbia_provider_catalog_recommended_model') ? cbia_provider_catalog_recommended_model($provider, 'image') : 'gpt-image-2';
    }
}

if (!function_exists('cbia_providers_get_model_display')) {
    function cbia_providers_get_model_display(string $provider, string $model): array {
        $definition = function_exists('cbia_provider_catalog_model') ? cbia_provider_catalog_model($provider, $model) : array();
        return array(
            'label' => (string)($definition['display_name'] ?? $model),
            'group' => sanitize_key((string)($definition['group'] ?? 'current')),
            'status' => sanitize_key((string)($definition['status'] ?? '')),
            'recommended' => !empty($definition['recommended']),
        );
    }
}
