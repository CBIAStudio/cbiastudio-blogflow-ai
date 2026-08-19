<?php
define('ABSPATH', __DIR__ . '/');

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;
        public function __construct($code, $message, $data = array()) {
            $this->code = (string)$code;
            $this->message = (string)$message;
            $this->data = $data;
        }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
        public function get_error_data() { return $this->data; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value) { return $value instanceof WP_Error; }
}
if (!function_exists('__')) {
    function __($text, $domain = '') { return $text; }
}

require dirname(__DIR__) . '/includes/support/provider-http-security.php';
$GLOBALS['security_options'] = array();
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['security_options']) ? $GLOBALS['security_options'][$key] : $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['security_options'][$key] = $value; return true; }
require dirname(__DIR__) . '/includes/integrations/providers.php';

$count = 0;
function security_check($condition, $message) {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$message}");
}

$public_resolver = function ($host) {
    return array('93.184.216.34', '2606:4700:4700::1111');
};

$invalid_urls = array(
    'http://api.example.com',
    'http://127.0.0.1',
    'https://127.0.0.1',
    'https://localhost',
    'https://localhost:443',
    'https://[::1]',
    'https://10.0.0.1',
    'https://172.16.0.1',
    'https://192.168.1.1',
    'https://169.254.169.254',
    'ftp://example.com',
    'file:///etc/passwd',
    'https://user:pass@example.com',
    'https://api.openai.com.attacker.example',
    'https://openai.com.attacker.example',
    'https://api.openai.com@attacker.example',
    'https://user:pass@api.openai.com',
    'https://api.openai.com:8443',
    'https://api.openai.com/v1',
    'https://api.openai.com?target=localhost',
    'https://api.openai.com/#fragment',
    '',
    'not-a-url',
    'https://',
);
foreach ($invalid_urls as $url) {
    $result = cbia_validate_provider_base_url('openai', $url, $public_resolver);
    security_check(empty($result['valid']), "base URL must be rejected: {$url}");
}

$official = array(
    'openai' => 'https://api.openai.com',
    'google' => 'https://generativelanguage.googleapis.com',
    'deepseek' => 'https://api.deepseek.com',
    'anthropic' => 'https://api.anthropic.com',
);
foreach ($official as $provider => $url) {
    $result = cbia_validate_provider_base_url($provider, $url, $public_resolver);
    security_check(!empty($result['valid']) && $result['url'] === $url, "official {$provider} host must be allowed");
}
security_check(!empty(cbia_validate_provider_base_url('openai', 'https://api.openai.com:443', $public_resolver)['valid']), 'explicit HTTPS port 443 is allowed');

$GLOBALS['security_options']['cbia_provider_settings'] = array('providers' => array('openai' => array('base_url' => 'https://api.openai.com')));
cbia_providers_save_settings(array('providers' => array('openai' => array('base_url' => 'https://127.0.0.1', 'model' => 'gpt-test'))));
$stored_settings = get_option('cbia_provider_settings', array());
security_check(($stored_settings['providers']['openai']['base_url'] ?? '') === 'https://api.openai.com', 'invalid submitted base URL is not persisted');
security_check(($stored_settings['providers']['openai']['model'] ?? '') === 'gpt-test', 'an invalid base URL does not block unrelated settings');
$GLOBALS['security_options']['cbia_provider_settings']['providers']['openai']['base_url'] = 'https://127.0.0.1';
cbia_providers_save_settings(array('current_provider' => 'openai'));
security_check(get_option('cbia_provider_settings')['providers']['openai']['base_url'] === 'https://127.0.0.1', 'legacy unsafe value is preserved for recovery instead of silently deleted');

$dns_cases = array(
    array('127.0.0.1'),
    array('10.20.30.40'),
    array('169.254.169.254'),
    array('::1'),
    array('fc00::1'),
    array('fe80::1'),
    array('93.184.216.34', '10.0.0.1'),
);
foreach ($dns_cases as $ips) {
    $resolver = function ($host) use ($ips) { return $ips; };
    $result = cbia_validate_provider_base_url('openai', 'https://api.openai.com', $resolver);
    security_check(empty($result['valid']), 'every resolved IP must be public');
}
security_check(cbia_provider_ip_is_public('8.8.8.8'), 'public IPv4 is classified as public');
security_check(cbia_provider_ip_is_public('2606:4700:4700::1111'), 'public IPv6 is classified as public');
foreach (array('127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.1.1', '169.254.169.254', '::1', 'fc00::1', 'fe80::1') as $ip) {
    security_check(!cbia_provider_ip_is_public($ip), "non-public IP must be rejected: {$ip}");
}

$secret = 'test-provider-secret-not-real';
$calls = array();
$transport = function ($url, $args) use (&$calls) {
    $calls[] = array('url' => $url, 'args' => $args);
    return array('response' => array('code' => 200), 'body' => '{}');
};
$blocked = cbia_provider_safe_remote_get('openai', 'https://127.0.0.1/v1/models', array(), $secret, $public_resolver, $transport);
security_check(is_wp_error($blocked), 'invalid destination returns WP_Error');
security_check(count($calls) === 0, 'invalid destination never invokes transport');

$allowed = cbia_provider_safe_remote_get('openai', 'https://api.openai.com/v1/models', array('headers' => array('Authorization' => 'attacker-value'), 'sslverify' => false, 'reject_unsafe_urls' => false, 'redirection' => 5), $secret, $public_resolver, $transport);
security_check(!is_wp_error($allowed) && count($calls) === 1, 'valid destination invokes transport once');
$sent = $calls[0]['args'];
security_check(($sent['headers']['Authorization'] ?? '') === 'Bearer ' . $secret, 'credential is attached only to the authorized request');
security_check(!empty($sent['sslverify']) && !empty($sent['reject_unsafe_urls']) && $sent['redirection'] === 0, 'safe transport flags are enforced');
security_check(($sent['method'] ?? '') === 'GET', 'GET wrapper fixes the request method');

foreach (array('https://127.0.0.1/latest/meta-data/', 'https://169.254.169.254/latest/meta-data/') as $location) {
    $redirect_calls = array();
    $redirect_transport = function ($url, $args) use (&$redirect_calls, $location) {
        $redirect_calls[] = array('url' => $url, 'args' => $args);
        return array('response' => array('code' => 302), 'headers' => array('location' => $location));
    };
    cbia_provider_safe_remote_get('openai', 'https://api.openai.com/v1/models', array('redirection' => 5), $secret, $public_resolver, $redirect_transport);
    security_check(count($redirect_calls) === 1, 'redirect response is not followed');
    security_check($redirect_calls[0]['args']['redirection'] === 0, 'redirects remain disabled for credentialed requests');
}

$resolution = 0;
$rebind_resolver = function ($host) use (&$resolution) {
    $resolution++;
    return $resolution === 1 ? array('93.184.216.34') : array('127.0.0.1');
};
$rebind_calls = array();
$rebind_transport = function ($url, $args) use (&$rebind_calls) {
    $rebind_calls[] = $url;
    return array('response' => array('code' => 200));
};
$first = cbia_provider_safe_remote_post('openai', 'https://api.openai.com/v1/responses', array(), $secret, $rebind_resolver, $rebind_transport);
$second = cbia_provider_safe_remote_post('openai', 'https://api.openai.com/v1/responses', array(), $secret, $rebind_resolver, $rebind_transport);
security_check(!is_wp_error($first) && is_wp_error($second), 'DNS is revalidated for every request');
security_check($resolution === 2 && count($rebind_calls) === 1, 'rebinding to loopback is blocked before transport');

echo "provider-base-url-security: {$count}/{$count} OK\n";
