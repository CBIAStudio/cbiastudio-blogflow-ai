<?php
define('ABSPATH', __DIR__ . '/');
define('DOING_CRON', true);

$GLOBALS['ai_security_inserted'] = array();
$GLOBALS['ai_security_updated'] = array();
$GLOBALS['ai_security_kses_calls'] = 0;
$GLOBALS['ai_security_capability_calls'] = 0;
$GLOBALS['ai_security_unfiltered_html'] = true;

function sanitize_text_field($value) {
    $value = strip_tags((string)$value);
    $value = preg_replace('/[\r\n\t ]+/', ' ', $value);
    return trim($value);
}
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
function wp_kses_post($html) {
    $GLOBALS['ai_security_kses_calls']++;
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
function current_user_can($capability) {
    $GLOBALS['ai_security_capability_calls']++;
    return $GLOBALS['ai_security_unfiltered_html'];
}
function cbia_get_settings() { return array('default_category' => ''); }
function cbia_strip_document_wrappers($html) { return (string)$html; }
function cbia_strip_h1_to_h2($html) { return preg_replace('/<\/?h1\b[^>]*>/i', '', (string)$html); }
function cbia_pick_post_author_id() { return 1; }
function cbia_determine_categories_by_mapping($title, $html) { return array(); }
function cbia_ensure_category_exists($category) { return 0; }
function cbia_pick_tags_for_post($title, $html, $limit) { return array(); }
function cbia_generate_meta_description($title, $html) { return sanitize_text_field($title); }
function cbia_generate_focus_keyphrase($title, $html) { return sanitize_text_field($title); }
function wp_insert_post($postarr, $wp_error = false) {
    $GLOBALS['ai_security_inserted'][] = $postarr;
    return count($GLOBALS['ai_security_inserted']);
}
function wp_update_post($postarr, $wp_error = false) {
    $GLOBALS['ai_security_updated'][] = $postarr;
    return (int)($postarr['ID'] ?? 1);
}
function is_wp_error($value) { return false; }
function update_post_meta($post_id, $key, $value) { return true; }
function wp_set_post_categories($post_id, $categories, $append = false) { return true; }
function wp_set_post_tags($post_id, $tags, $append = false) { return true; }
function set_post_thumbnail($post_id, $thumbnail_id) { return true; }
function apply_filters($hook, $value) { return $value; }
function current_time($type) { return $type === 'mysql' ? '2026-08-19 12:00:00' : time(); }

require dirname(__DIR__) . '/includes/support/sanitize.php';
require dirname(__DIR__) . '/includes/engine/posts.php';

$count = 0;
function ai_security_check($condition, $message) {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$message}");
}

$malicious_title = '<script>alert(1)</script><img src=x onerror=alert(2)>Artículo legítimo — Café 東京';
$malicious_html = '<p>Contenido válido <strong>con énfasis</strong>.</p>'
    . '<h2>Sección</h2><h3>Detalle</h3>'
    . '<ul><li>Uno</li><li>Dos</li></ul><ol><li>Tres</li></ol>'
    . '<a href="https://example.com/path">HTTPS</a>'
    . '<a href="javascript:alert(1)" onclick="alert(2)">X</a>'
    . '<img src="https://example.com/image.jpg" alt="Imagen" onerror="alert(3)">'
    . '<table><tbody><tr><td>Dato</td></tr></tbody></table>'
    . '<blockquote>Quote</blockquote>[gallery ids="1,2"]'
    . '<script>alert(4)</script>'
    . '<svg onload="alert(5)"></svg>'
    . '<iframe srcdoc="<script>alert(6)</script>"></iframe>'
    . '<p onclick="alert(7)" onmouseover="alert(8)" style="background:url(javascript:alert(9))">Fin</p>';

list($ok, $post_id) = cbia_create_post_in_wp_engine($malicious_title, $malicious_html, 0, '', 'draft');
ai_security_check($ok && $post_id === 1, 'the real post creation sink persists the provider response');
$stored = $GLOBALS['ai_security_inserted'][0];
$body = (string)$stored['post_content'];
$title = (string)$stored['post_title'];

ai_security_check(stripos($body, '<script') === false, 'script elements are removed from body');
ai_security_check(stripos($body, 'onerror') === false, 'onerror is removed');
ai_security_check(stripos($body, 'onclick') === false, 'onclick is removed');
ai_security_check(stripos($body, 'onload') === false && stripos($body, 'onmouseover') === false, 'other event handlers are removed');
ai_security_check(stripos($body, 'javascript:') === false, 'javascript URLs are removed');
ai_security_check(stripos($body, 'srcdoc') === false, 'srcdoc is removed');
ai_security_check(stripos($body, '<svg') === false && stripos($body, '<iframe') === false, 'SVG and iframe vectors are removed');
ai_security_check(strpos($body, '<p>Contenido válido <strong>con énfasis</strong>.</p>') !== false, 'legitimate paragraph HTML is preserved');
ai_security_check(strpos($body, '<ul><li>Uno</li><li>Dos</li></ul>') !== false && strpos($body, '<ol><li>Tres</li></ol>') !== false, 'lists are preserved');
ai_security_check(strpos($body, '<h2>Sección</h2>') !== false && strpos($body, '<h3>Detalle</h3>') !== false, 'headings are preserved');
ai_security_check(strpos($body, 'href="https://example.com/path"') !== false, 'legitimate HTTPS links are preserved');
ai_security_check(strpos($body, 'src="https://example.com/image.jpg"') !== false && strpos($body, 'alt="Imagen"') !== false, 'legitimate images are preserved');
ai_security_check(strpos($body, '<table>') !== false && strpos($body, '<blockquote>Quote</blockquote>') !== false, 'tables and blockquotes are preserved');
ai_security_check(strpos($body, '[gallery ids="1,2"]') !== false, 'shortcode text is preserved');
ai_security_check(strpos($body, '<h2>Sección</h2><h3>Detalle</h3>') !== false, 'FAQ-compatible heading structure is preserved');
ai_security_check(strpos($title, '<') === false && stripos($title, 'onerror') === false, 'title is stored as plain safe text');
ai_security_check(strpos($title, 'Artículo legítimo — Café 東京') !== false, 'legitimate Unicode title characters are preserved');
ai_security_check($GLOBALS['ai_security_capability_calls'] === 0, 'unfiltered_html is never consulted as a sanitizer bypass');
ai_security_check($GLOBALS['ai_security_kses_calls'] === 1, 'body is sanitized once at the main persistence boundary');

cbia_create_post_in_wp_engine('Cron <b>seguro</b>', '<p>Cron</p><img src=x onerror=alert(1)><script>alert(2)</script>', 0, '', 'draft');
$cron_stored = $GLOBALS['ai_security_inserted'][1];
ai_security_check(defined('DOING_CRON') && DOING_CRON && stripos($cron_stored['post_content'], 'onerror') === false && stripos($cron_stored['post_content'], '<script') === false, 'cron uses the same sanitized creation sink');

$update = cbia_sanitize_ai_post_data(array(
    'ID' => 77,
    'post_title' => '<img src=x onerror=alert(1)>Actualización segura',
    'post_content' => '<p>Válido</p><a href="javascript:alert(1)" onclick="alert(2)">X</a>',
));
wp_update_post($update, true);
$updated = $GLOBALS['ai_security_updated'][0];
ai_security_check(strpos($updated['post_title'], '<') === false, 'update title uses the same plain-text policy');
ai_security_check(stripos($updated['post_content'], 'javascript:') === false && stripos($updated['post_content'], 'onclick') === false, 'update content uses the same KSES policy');

$excerpt = cbia_sanitize_ai_post_data(array('post_excerpt' => '<script>x</script>Resumen Unicode ñ'));
ai_security_check(strpos($excerpt['post_excerpt'], '<') === false && strpos($excerpt['post_excerpt'], 'Resumen Unicode ñ') !== false, 'optional AI excerpt is plain text when present');

$root = dirname(__DIR__);
$helper_source = file_get_contents($root . '/includes/support/sanitize.php');
$posts_source = file_get_contents($root . '/includes/engine/posts.php');
$preview_source = file_get_contents($root . '/includes/services/article-preview-service.php');
$oldposts_source = file_get_contents($root . '/includes/engine/oldposts.php');
$hooks_source = file_get_contents($root . '/includes/core/hooks.php');
$blog_source = file_get_contents($root . '/includes/engine/blog.php');
ai_security_check(strpos($helper_source, 'return wp_kses_post((string)$content);') !== false, 'central content policy delegates to wp_kses_post');
ai_security_check(strpos($helper_source, 'sanitize_text_field((string)$title)') !== false, 'central title policy delegates to sanitize_text_field');
ai_security_check(strpos($helper_source, 'current_user_can') === false, 'central policy has no capability bypass');
ai_security_check(strpos($posts_source, '$postarr = cbia_sanitize_ai_post_data([') !== false, 'main wp_insert_post payload crosses the trust boundary');
ai_security_check(substr_count($preview_source, 'cbia_sanitize_ai_post_data(array(') >= 2, 'preview insert and update payloads cross the trust boundary');
ai_security_check(strpos($oldposts_source, '$new_title = cbia_sanitize_ai_post_title((string)$text);') !== false, 'Old Posts AI title update is protected');
ai_security_check(strpos($oldposts_source, '$final_html = cbia_sanitize_ai_post_content($final_html);') !== false, 'Old Posts AI content update is protected');
ai_security_check(strpos($hooks_source, '$safe_fields = cbia_sanitize_ai_post_data(array(') !== false, 'AI Composer final persistence is protected');
ai_security_check(strpos($blog_source, "add_action('cbia_generation_event'") !== false && strpos($blog_source, 'cbia_run_generate_blogs(') !== false, 'WP-Cron enters the shared generation path');

echo "ai-generated-post-content-security: {$count}/{$count} OK\n";
