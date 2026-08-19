<?php
define('ABSPATH', __DIR__ . '/');

$GLOBALS['snapshot_test_post'] = null;
$GLOBALS['snapshot_test_saved'] = null;
$GLOBALS['snapshot_test_updates'] = array();
$GLOBALS['snapshot_test_nonce_calls'] = 0;
$GLOBALS['snapshot_test_capabilities'] = array();
$GLOBALS['snapshot_test_kses_calls'] = 0;

class WP_Post {
    public $ID = 0;
    public $post_type = 'post';
    public $post_title = '';
    public $post_content = '';
}

class WP_Term {
    public $term_id = 0;
    public $name = '';
}

class CBIA_Snapshot_Test_Response extends RuntimeException {
    public $success;
    public $data;
    public $status;

    public function __construct($success, $data, $status) {
        parent::__construct('JSON response');
        $this->success = (bool)$success;
        $this->data = $data;
        $this->status = (int)$status;
    }
}

function absint($value) { return abs((int)$value); }
function sanitize_text_field($value) {
    $value = strip_tags((string)$value);
    $value = preg_replace('/[\r\n\t ]+/', ' ', $value);
    return trim($value);
}
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
function wp_trim_words($value, $count, $more = null) {
    $words = preg_split('/\s+/', trim((string)$value));
    if (count($words) <= (int)$count) return implode(' ', $words);
    return implode(' ', array_slice($words, 0, (int)$count)) . (string)$more;
}
function wp_kses_post($html) {
    $GLOBALS['snapshot_test_kses_calls']++;
    $html = (string)$html;
    $html = preg_replace('#<(script|svg|iframe)\b[^>]*>.*?</\1\s*>#is', '', $html);
    $html = preg_replace('#</?(script|svg|iframe)\b[^>]*>#is', '', $html);
    $html = preg_replace('/\s(?:on[a-z0-9_-]+|srcdoc|style)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace_callback(
        '/\s(href|src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
        function ($match) {
            $value = isset($match[2]) && $match[2] !== ''
                ? $match[2]
                : (isset($match[3]) && $match[3] !== '' ? $match[3] : (string)($match[4] ?? ''));
            return preg_match('/^\s*(?:javascript|vbscript|data):/i', $value) ? '' : $match[0];
        },
        $html
    );
    return strip_tags(
        $html,
        '<p><h2><h3><h4><ul><ol><li><strong><em><blockquote><a><img><table><thead><tbody><tfoot><tr><th><td><br><figure><figcaption><code><pre>'
    );
}
function esc_url_raw($value) {
    $value = trim((string)$value);
    return preg_match('#^https?://#i', $value) ? $value : '';
}
function cbia_cap_enabled($capability) { return $capability === 'internal_images'; }
function cbia_prompt_get_profile_options() { return array('discover_editorial' => 'Editorial'); }
function cbia_trim_metadesc($value, $limit) { return substr(sanitize_text_field($value), 0, (int)$limit); }
function cbia_ai_composer_normalize_title_value($value, $post = null) { return sanitize_text_field($value); }
function cbia_ai_composer_normalize_language_value($value) {
    return strtolower((string)$value) === 'english' ? 'English' : 'Spanish';
}
function cbia_ai_composer_normalize_language_code($value) {
    return strtolower((string)$value) === 'en' ? 'en' : 'es';
}
function get_post($post_id) { return absint($post_id) === 42 ? $GLOBALS['snapshot_test_post'] : null; }
function get_post_thumbnail_id($post_id) { return absint($post_id) === 42 ? 7 : 0; }
function wp_get_attachment_image_url($attachment_id, $size) { return 'https://example.com/featured.jpg'; }
function wp_get_attachment_url($attachment_id) { return 'https://example.com/featured-full.jpg'; }
function get_post_meta($post_id, $key, $single = false) {
    if (absint($post_id) === 42 && $key === '_cbia_ai_composer_snapshot') return $GLOBALS['snapshot_test_saved'];
    if (absint($post_id) === 7 && $key === '_wp_attachment_image_alt') return '<img onerror=alert(1)>Featured';
    if ($key === '_yoast_wpseo_focuskw') return '<script>x</script>Focus';
    if ($key === '_yoast_wpseo_metadesc') return '';
    return '';
}
function cbia_ai_composer_extract_internal_images_from_html($html, $limit) { return array(); }
function wp_get_post_categories($post_id, $args = array()) { return array(5, 0, 5); }
function wp_get_post_terms($post_id, $taxonomy) {
    $term = new WP_Term();
    $term->term_id = 9;
    $term->name = '<b>Tag</b>';
    return array($term);
}
function is_wp_error($value) { return false; }
function cbia_get_settings() {
    return array(
        'image_format_internal_1' => 'banner_1536x1024',
        'post_language' => 'Spanish',
        'post_length_variant' => 'medium',
        'images_limit' => 3,
    );
}
function wp_unslash($value) { return $value; }
function __($value, $domain = null) { return (string)$value; }
function check_ajax_referer($action) {
    if ($action !== 'cbia_ajax_nonce') throw new RuntimeException('Unexpected nonce action');
    $GLOBALS['snapshot_test_nonce_calls']++;
    return true;
}
function current_user_can($capability) {
    $GLOBALS['snapshot_test_capabilities'][] = (string)$capability;
    return true;
}
function update_post_meta($post_id, $key, $value) {
    $GLOBALS['snapshot_test_updates'][] = array($post_id, $key, $value);
    return true;
}
function wp_send_json_success($data = null, $status_code = null) {
    throw new CBIA_Snapshot_Test_Response(true, $data, $status_code ?: 200);
}
function wp_send_json_error($data = null, $status_code = null) {
    throw new CBIA_Snapshot_Test_Response(false, $data, $status_code ?: 200);
}

$snapshot_test_count = 0;
function snapshot_test_check($condition, $message) {
    global $snapshot_test_count;
    $snapshot_test_count++;
    if (!$condition) throw new RuntimeException("Case {$snapshot_test_count} failed: {$message}");
}

function snapshot_test_extract_function($source, $target) {
    $tokens = token_get_all($source);
    $total = count($tokens);
    for ($i = 0; $i < $total; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $name = '';
        for ($j = $i + 1; $j < $total; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
                break;
            }
            if ($tokens[$j] === '(') break;
        }
        if ($name !== $target) continue;

        $output = '';
        $started = false;
        $depth = 0;
        for ($j = $i; $j < $total; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $output .= $text;
            if ($text === '{') {
                $started = true;
                $depth++;
            } elseif ($text === '}' && $started) {
                $depth--;
                if ($depth === 0) return $output;
            }
        }
    }
    throw new RuntimeException("Function {$target} not found");
}

function snapshot_test_load($saved_snapshot) {
    $GLOBALS['snapshot_test_saved'] = $saved_snapshot;
    $GLOBALS['snapshot_test_updates'] = array();
    $_POST = array('post_id' => '42');
    try {
        cbia_ajax_ai_composer_load_snapshot();
    } catch (CBIA_Snapshot_Test_Response $response) {
        return $response;
    }
    throw new RuntimeException('The AJAX endpoint did not emit a response');
}

function snapshot_test_html_is_safe($html) {
    return stripos($html, '<script') === false
        && stripos($html, 'onerror') === false
        && stripos($html, 'onclick') === false
        && stripos($html, 'onload') === false
        && stripos($html, 'onmouseover') === false
        && stripos($html, 'javascript:') === false
        && stripos($html, 'srcdoc') === false
        && stripos($html, '<svg') === false
        && stripos($html, '<iframe') === false;
}

$root = dirname(__DIR__);
$hooks_source = file_get_contents($root . '/includes/core/hooks.php');
$admin_js_source = file_get_contents($root . '/assets/js/admin.js');
$sanitizer_source = snapshot_test_extract_function($hooks_source, 'cbia_ai_composer_sanitize_snapshot');
$builder_source = snapshot_test_extract_function($hooks_source, 'cbia_ai_composer_build_snapshot_from_post');
$load_source = snapshot_test_extract_function($hooks_source, 'cbia_ajax_ai_composer_load_snapshot');
eval($sanitizer_source);
eval($builder_source);
eval($load_source);

$legitimate_html = '<p>Contenido legitimo <strong>Cafe Tokyo</strong>.</p>'
    . '<h2>Seccion</h2><ul><li>Uno</li><li>Dos</li></ul>'
    . '<a href="https://example.com/path">HTTPS</a>'
    . '<img src="https://example.com/image.jpg" alt="Imagen">'
    . '<table><tbody><tr><td>Dato</td></tr></tbody></table>';
$malicious_html = $legitimate_html
    . '<script>alert(1)</script>'
    . '<img src=x onerror=alert(2)>'
    . '<a href="javascript:alert(3)" onclick="alert(4)">X</a>'
    . '<svg onload="alert(5)"></svg>'
    . '<iframe srcdoc="<script>alert(6)</script>"></iframe>'
    . '<p onmouseover="alert(7)" style="background:url(javascript:alert(8))">Fin</p>';

$post = new WP_Post();
$post->ID = 42;
$post->post_title = '<img src=x onerror=alert(9)>Titulo seguro';
$post->post_content = $malicious_html;
$GLOBALS['snapshot_test_post'] = $post;

$saved_snapshot = array(
    'version' => 2,
    'post_id' => 42,
    'title' => '<script>alert(10)</script>Titulo seguro',
    'controls' => array(
        'length' => 'medium',
        'internal_images' => 2,
        'language' => 'Spanish',
        'internal_style' => 'banner',
        'prompt_profile' => 'discover_editorial',
        'include_faq' => 1,
        'include_practical_examples' => 0,
    ),
    'final_html' => $malicious_html,
    'preview_html' => $malicious_html,
    'featured' => array('url' => 'javascript:alert(11)', 'attach_id' => '7', 'desc' => '<b>Featured</b>'),
    'featured_attach_id' => '7',
    'internal_images' => array(
        array('idx' => '1', 'url' => 'https://example.com/internal.jpg', 'attach_id' => '8', 'desc' => '<b>Internal</b>', 'prompt' => '<script>x</script>Prompt', 'section' => 'Body'),
        array('idx' => '2', 'url' => 'javascript:alert(12)', 'attach_id' => '9'),
    ),
    'focus_keyphrase' => '<img onerror=alert(13)>Focus',
    'meta_description' => '<script>alert(14)</script>Description',
    'category_ids' => array('5', '0', '5'),
    'tag_ids' => array('9', '0', '9'),
    'tag_names' => array('<b>Tag</b>', '<script>x</script>Second'),
    'preview_token' => '<img onerror=alert(15)>token',
    'word_count' => '99',
    'updated_at' => '123',
);

$built_response = snapshot_test_load(null);
snapshot_test_check($built_response->success && $built_response->status === 200, 'a missing snapshot is rebuilt successfully');
$built = $built_response->data['snapshot'];
snapshot_test_check(snapshot_test_html_is_safe($built['final_html']), 'rebuilt final_html is safe');
snapshot_test_check(snapshot_test_html_is_safe($built['preview_html']), 'rebuilt preview_html is safe');
snapshot_test_check($built['final_html'] === $built['preview_html'], 'rebuilt final and preview HTML use the same policy');
snapshot_test_check(strpos($built['final_html'], '<p>Contenido legitimo <strong>Cafe Tokyo</strong>.</p>') !== false, 'legitimate paragraphs and emphasis survive rebuilding');
snapshot_test_check(strpos($built['final_html'], '<h2>Seccion</h2>') !== false && strpos($built['final_html'], '<ul><li>Uno</li><li>Dos</li></ul>') !== false, 'headings and lists survive rebuilding');
snapshot_test_check(strpos($built['final_html'], 'href="https://example.com/path"') !== false && strpos($built['final_html'], 'src="https://example.com/image.jpg"') !== false, 'legitimate links and images survive rebuilding');
snapshot_test_check(strpos($built['final_html'], '<table>') !== false, 'legitimate tables survive rebuilding');
snapshot_test_check(count($GLOBALS['snapshot_test_updates']) === 1, 'the rebuilt snapshot is persisted once');
snapshot_test_check($GLOBALS['snapshot_test_updates'][0][2] === $built, 'the persisted rebuilt snapshot is the sanitized response');
snapshot_test_check($built_response->data['source'] === 'server' && $built_response->data['post_id'] === 42, 'the endpoint response contract is preserved');

$saved_response = snapshot_test_load($saved_snapshot);
snapshot_test_check($saved_response->success, 'an existing array snapshot loads successfully');
$saved = $saved_response->data['snapshot'];
snapshot_test_check(snapshot_test_html_is_safe($saved['final_html']), 'existing final_html is safe');
snapshot_test_check(snapshot_test_html_is_safe($saved['preview_html']), 'existing preview_html is safe');
snapshot_test_check($saved['final_html'] === $built['final_html'] && $saved['preview_html'] === $built['preview_html'], 'new and existing snapshots have HTML policy parity');
snapshot_test_check(count($GLOBALS['snapshot_test_updates']) === 0, 'an existing snapshot is not rewritten during a read');
snapshot_test_check(strpos($saved['title'], '<') === false && stripos($saved['title'], 'onerror') === false, 'snapshot title is plain text');
snapshot_test_check($saved['featured']['url'] === '', 'unsafe featured URLs are removed');
snapshot_test_check(count($saved['internal_images']) === 1 && $saved['internal_images'][0]['url'] === 'https://example.com/internal.jpg', 'only safe internal image URLs survive');
snapshot_test_check(strpos($saved['focus_keyphrase'], '<') === false && strpos($saved['meta_description'], '<') === false, 'SEO fields are plain text');
snapshot_test_check($saved['category_ids'] === array(5) && $saved['tag_ids'] === array(9), 'taxonomy identifiers are normalized');

$legacy_response = snapshot_test_load(json_encode($saved_snapshot));
snapshot_test_check($legacy_response->success, 'a legacy serialized snapshot loads successfully');
$legacy = $legacy_response->data['snapshot'];
snapshot_test_check(snapshot_test_html_is_safe($legacy['final_html']) && snapshot_test_html_is_safe($legacy['preview_html']), 'legacy HTML is safe');
snapshot_test_check($legacy['final_html'] === $saved['final_html'] && $legacy['preview_html'] === $saved['preview_html'], 'legacy and array snapshots have policy parity');
snapshot_test_check(count($GLOBALS['snapshot_test_updates']) === 0, 'legacy reads do not rewrite stored data');

snapshot_test_check($GLOBALS['snapshot_test_nonce_calls'] === 3, 'every load route verifies the AJAX nonce');
snapshot_test_check($GLOBALS['snapshot_test_capabilities'] === array('edit_post', 'edit_post', 'edit_post'), 'every load route verifies edit_post capability');
snapshot_test_check(!in_array('unfiltered_html', $GLOBALS['snapshot_test_capabilities'], true), 'unfiltered_html is never used as a sanitizer bypass');
snapshot_test_check($GLOBALS['snapshot_test_kses_calls'] >= 8, 'both HTML fields cross wp_kses_post on all routes');

$json = json_encode(array($built, $saved, $legacy));
snapshot_test_check(snapshot_test_html_is_safe($json), 'serialized server snapshots contain no executable XSS vectors');
snapshot_test_check(strpos($sanitizer_source, "wp_kses_post((string)\$raw_snapshot['final_html'])") !== false, 'central helper sanitizes final_html');
snapshot_test_check(strpos($sanitizer_source, "wp_kses_post((string)\$raw_snapshot['preview_html'])") !== false, 'central helper sanitizes preview_html');
snapshot_test_check(strpos($builder_source, 'return cbia_ai_composer_sanitize_snapshot($snapshot);') !== false, 'post builder always returns the centralized sanitized snapshot');
snapshot_test_check(strpos($builder_source, "function_exists('cbia_ai_composer_sanitize_snapshot')") === false, 'post builder has no raw fallback');

$initial_sanitize_pos = strpos($hooks_source, '$initial_snapshot = cbia_ai_composer_sanitize_snapshot($initial_snapshot);');
$initial_json_pos = strpos($hooks_source, '$initial_snapshot_json = (string) wp_json_encode($initial_snapshot);');
snapshot_test_check($initial_sanitize_pos !== false && $initial_json_pos !== false && $initial_sanitize_pos < $initial_json_pos, 'metabox initial snapshot is sanitized before JSON serialization');
$load_build_pos = strpos($load_source, '$snapshot = cbia_ai_composer_build_snapshot_from_post(');
$load_sanitize_pos = strpos($load_source, '$snapshot = cbia_ai_composer_sanitize_snapshot($snapshot);');
$load_send_pos = strpos($load_source, 'wp_send_json_success(array(');
snapshot_test_check(strpos($load_source, '$snapshot_built_from_post = empty($snapshot);') !== false, 'load endpoint explicitly tracks rebuilt snapshots');
snapshot_test_check($load_build_pos !== false && $load_sanitize_pos > $load_build_pos && $load_send_pos > $load_sanitize_pos, 'load endpoint converges on the sanitizer before JSON output');
snapshot_test_check(strpos($load_source, "check_ajax_referer('cbia_ajax_nonce');") !== false, 'load endpoint keeps its nonce check');
snapshot_test_check(strpos($load_source, "current_user_can('edit_post', \$post_id)") !== false, 'load endpoint keeps its object capability check');
snapshot_test_check(strpos($admin_js_source, 'applyComposerSnapshot') !== false && strpos($admin_js_source, '.innerHTML =') !== false, 'client innerHTML sink remains downstream of the protected server snapshot');

echo "ai-composer-snapshot-security: {$snapshot_test_count}/{$snapshot_test_count} OK\n";
