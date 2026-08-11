<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['opts'] = array();
$GLOBALS['meta'] = array();
function add_action() {}
function do_action() {}
function apply_filters($tag, $value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function current_time($type) { return $type === 'mysql' ? '2026-07-17 12:00:00' : time(); }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; }
function get_option($key, $default = false) { return $GLOBALS['opts'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['opts'][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['meta'][$post_id][$key] ?? ($single ? '' : array()); }
function update_post_meta($post_id, $key, $value) { $GLOBALS['meta'][$post_id][$key] = $value; return true; }
function cbia_get_settings() { return array(); }

require dirname(__DIR__) . '/includes/engine/models.php';
require dirname(__DIR__) . '/includes/engine/usage.php';
require dirname(__DIR__) . '/includes/support/provider-model-catalog.php';
require dirname(__DIR__) . '/includes/domain/costs.php';

$count = 0;
function ok_case($condition, $name) {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$name}");
}
function source_has($path, $needle) { return strpos(file_get_contents($path), $needle) !== false; }

$root = dirname(__DIR__);
$openai = $root . '/includes/engine/openai.php';
$posts = $root . '/includes/engine/posts.php';
$preview = $root . '/includes/services/article-preview-service.php';
$blog = $root . '/includes/engine/blog.php';
$oldposts = $root . '/includes/engine/oldposts.php';
$base = $root . '/includes/engine/base.php';

ok_case(source_has($openai, 'return [$completed, $text'), 'completed response returns in one call');
ok_case(source_has($openai, "array(408, 429, 500, 502, 503, 504)"), 'timeout/transient has one fallback');
ok_case(source_has($posts, "'remote_text_request' => 1") && source_has($posts, "'remote_text_request' => 2"), 'maximum two requests per article');
ok_case(count(cbia_openai_text_attempt_chain('gpt-5-mini')) <= 2, 'does not traverse ten models');
ok_case(source_has($openai, 'cbia_openai_record_text_attempt($attempt_context, $usage, $attempt)'), 'success saved immediately');
ok_case(source_has($openai, "'status' => 'timeout'"), 'timeout saved immediately');
ok_case(source_has($preview, "'context' => 'preview_text'"), 'preview without post keeps usage');
ok_case(substr_count(file_get_contents($openai), 'cbia_openai_responses_call(') >= 3, 'direct wrapper remains compatible');
ok_case(source_has($openai, "'request_id' => \$request_id"), 'request id stored');
ok_case(source_has($openai, "saved=' . (\$saved ? 'yes' : 'no')"), 'usage save failure logged');

$attempt = array('type'=>'text','provider'=>'openai','model'=>'gpt-5-mini','model_effective'=>'gpt-5-mini','attempt_id'=>'fixture-a1','input_tokens'=>10,'output_tokens'=>5,'cached_tokens_reported'=>1,'ok'=>1);
cbia_costes_record_usage(0, $attempt);
cbia_costes_record_usage(0, $attempt);
$orphan_key = cbia_costes_orphan_usage_key();
$orphan_is_idempotent = count($GLOBALS['opts'][$orphan_key]) === 1;
cbia_costes_record_usage(7, $attempt);
ok_case($orphan_is_idempotent && count($GLOBALS['opts'][$orphan_key]) === 0 && count($GLOBALS['meta'][7]['_cbia_usage_rows']) === 1, 'idempotency and temporary row move');

$reported = cbia_usage_from_responses_payload(array('usage'=>array('input_tokens'=>10,'output_tokens'=>5,'input_tokens_details'=>array('cached_tokens'=>0))));
$missing = cbia_usage_from_responses_payload(array('usage'=>array('input_tokens'=>10,'output_tokens'=>5)));
ok_case($reported['cached_tokens_reported'] === 1, 'cache reported');
ok_case($missing['cached_tokens_reported'] === 0, 'cache not reported');
$exact = cbia_costes_calculate_row(array('type'=>'text','model'=>'gpt-5-mini','in'=>10,'out'=>5,'cached_tokens_reported'=>1));
$estimated = cbia_costes_calculate_row(array('type'=>'text','model'=>'gpt-5-mini','in'=>10,'out'=>5));
$unknown = cbia_costes_calculate_row(array('type'=>'text','model'=>'gpt-5-mini','in'=>0,'out'=>0));
ok_case($exact['cost_status'] === 'exact', 'exact cost');
ok_case($estimated['cost_status'] === 'estimated', 'estimated cost');
ok_case($unknown['cost_status'] === 'unknown', 'unknown cost');
ok_case(source_has($openai, "'completed'"), 'response completed');
ok_case(source_has($openai, "'incomplete_details'"), 'response incomplete');
ok_case(source_has($posts, 'first_pass_accepted'), 'initial article sufficient');
ok_case(source_has($posts, 'below_effective_minimum'), 'initial article short');
ok_case(source_has($posts, "'result' => \$ok ? 'sufficient'"), 'expansion sufficient');
ok_case(source_has($posts, "'still_below_minimum'"), 'expansion insufficient');
ok_case(source_has($posts, "'strict_max_output_override' => true"), 'expansion output budget constrained');
ok_case(source_has($blog, 'cbia_checkpoint_save'), 'checkpoint retained');
ok_case(source_has($posts, 'needs_manual_review_length'), 'extremely short article not published');
ok_case(strpos(file_get_contents($posts), 'needs_manual_review_length') < strpos(file_get_contents($posts), '// 3) Procesar marcadores de imagen'), 'rejected article creates no images');
ok_case(source_has($posts, "'context' => 'blog_text'"), 'Blog route');
ok_case(source_has($blog, 'wp_schedule_single_event'), 'Cron route');
ok_case(source_has($preview, 'preview_length_insufficient'), 'Preview route');
ok_case(source_has($oldposts, 'cbia_openai_responses_call'), 'Oldposts route');
ok_case(source_has($base, 'test_config'), 'Config test route');
ok_case(is_file($root . '/includes/support/deepseek-v4.php'), 'DeepSeek intact');
ok_case(source_has($openai, 'cbia_generate_image_openai'), 'OpenAI Images intact');
ok_case(source_has($root . '/includes/domain/costs.php', 'cbia_deepseek_calculate_cost'), 'DeepSeek Usage intact');

if ($count !== 34) throw new RuntimeException("Expected 34 cases, got {$count}");
echo "openai-text-usage-single-pass: 34/34 OK\n";
