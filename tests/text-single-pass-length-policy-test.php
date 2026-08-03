<?php
define('ABSPATH', __DIR__ . '/');
function apply_filters($tag, $value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string)$value)); }
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($value) { return json_encode($value); }
function get_option($key, $default = false) { return $default; }
function cbia_is_stop_requested() { return false; }
function cbia_fix_bracket_headings($value) { return (string)$value; }
function cbia_strip_h1_to_h2($value) { return str_replace(array('<h1>', '</h1>'), array('<h2>', '</h2>'), (string)$value); }
function cbia_strip_document_wrappers($value) { return (string)$value; }

require dirname(__DIR__) . '/includes/engine/posts.php';
require dirname(__DIR__) . '/includes/engine/prompt.php';
require dirname(__DIR__) . '/includes/engine/openai.php';

$count = 0;
function verify_case($condition, $name) {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$name}");
}
function article_with_words($count, $valid = true) {
    $words = array_fill(0, $count, 'word');
    return '<h2>Heading</h2><p>' . implode(' ', $words) . '.</p>' . ($valid ? '<h2>Closing</h2><p>Complete ending.</p>' : '<h2>Broken');
}

$policy = cbia_get_length_policy('medium');
verify_case($policy['nominal_min_words'] === 1800 && $policy['nominal_max_words'] === 2000, 'Medium nominal range');
verify_case($policy['effective_min_words'] === 1530 && $policy['tolerance_percent'] === 15, 'Medium effective floor');
verify_case($policy['first_pass_preferred_min'] === 1650 && $policy['first_pass_preferred_max'] === 1850, 'Preferred first-pass range');
verify_case(cbia_get_length_policy('short')['nominal_min_words'] === 950, 'Short preserved');
verify_case(cbia_get_length_policy('long')['nominal_min_words'] === 2000, 'Long preserved');

foreach (array(1120, 1529) as $words) {
    $decision = cbia_decide_text_expansion($words, $policy, 'complete', array('valid'=>true,'issues'=>array()));
    verify_case($decision['expansion_required'] && $decision['expansion_reason'] === 'below_effective_minimum', "{$words} expands");
}
foreach (array(1530, 1600, 1799) as $words) {
    $decision = cbia_decide_text_expansion($words, $policy, 'complete', array('valid'=>true,'issues'=>array()));
    verify_case($decision['first_pass_accepted'] && $decision['accepted_with_tolerance'] && !$decision['expansion_required'], "{$words} accepted with tolerance");
}
foreach (array(1800, 2000) as $words) {
    $decision = cbia_decide_text_expansion($words, $policy, 'complete', array('valid'=>true,'issues'=>array()));
    verify_case($decision['first_pass_accepted'] && $decision['first_pass_nominal_target_met'] && !$decision['accepted_with_tolerance'], "{$words} nominally accepted");
}
$truncated = cbia_decide_text_expansion(1700, $policy, 'output_limit', array('valid'=>true,'issues'=>array()));
verify_case($truncated['expansion_required'] && $truncated['expansion_reason'] === 'output_limit_reached', 'Truncated output expands');
$broken = cbia_decide_text_expansion(1800, $policy, 'complete', cbia_validate_generated_article_html(article_with_words(1800, false)));
verify_case($broken['expansion_required'] && $broken['expansion_reason'] === 'invalid_html', 'Broken HTML rejected');
$placeholder = cbia_decide_text_expansion(1600, $policy, 'complete', cbia_validate_generated_article_html('<h2>Heading</h2><p>{{TODO</p>'));
verify_case($placeholder['expansion_required'], 'Incomplete placeholder rejected');

$opts = array('post_length_variant'=>'medium','include_faq'=>0,'include_practical_examples'=>0,'search_intent_strength'=>'balanced');
foreach (array('how_to','discover_editorial','seo_balanced') as $profile) {
    $prompt = strtolower(cbia_build_prompt_profile_block($profile, $opts, 'English'));
    verify_case(strpos($prompt, '1650-1850') !== false, "{$profile} gets central target");
    verify_case(strpos($prompt, 'faq') === false && strpos($prompt, 'frequently asked') === false, "{$profile} omits disabled FAQ");
    verify_case(strpos($prompt, 'practical examples') === false, "{$profile} omits disabled examples");
}
$faq_prompt = strtolower(cbia_build_prompt_profile_block('how_to', array_merge($opts, array('include_faq'=>1)), 'English'));
$examples_prompt = strtolower(cbia_build_prompt_profile_block('how_to', array_merge($opts, array('include_practical_examples'=>1)), 'English'));
verify_case(strpos($faq_prompt, 'faq') !== false, 'Enabled FAQ appears');
verify_case(strpos($examples_prompt, 'practical') !== false, 'Enabled practical examples appear');

verify_case(cbia_normalize_openai_completion_status('completed', '') === 'complete', 'OpenAI completed normalized');
verify_case(cbia_normalize_openai_completion_status('incomplete', 'max_output_tokens') === 'output_limit', 'OpenAI output limit normalized');
verify_case(cbia_normalize_openai_completion_status('incomplete', '') === 'incomplete', 'OpenAI incomplete normalized');
verify_case(cbia_normalize_openai_completion_status('incomplete', 'content_filter') === 'content_filter', 'OpenAI content filter normalized');
verify_case(cbia_normalize_openai_completion_status('mystery', '') === 'unknown', 'Unknown status preserved');

list($inserted, $merged) = cbia_insert_incremental_expansion('<h2>Body</h2><p>Existing.</p><h2>Closing</h2><p>End.</p>', '<h2>Added depth</h2><p>Useful new detail.</p>');
verify_case($inserted && strpos($merged, 'Added depth') < strpos($merged, 'Closing'), 'Incremental fragment inserted before closing');

$posts_source = file_get_contents(dirname(__DIR__) . '/includes/engine/posts.php');
verify_case(strpos($posts_source, "'remote_text_request' => 1") !== false && strpos($posts_source, "'remote_text_request' => 2") !== false, 'Two-request global budget annotated');
verify_case(strpos($posts_source, "'strict_max_output_override' => true") !== false, 'Expansion uses strict output budget');
verify_case(strpos($posts_source, "'result' => 'sufficient'") === false, 'Result is not used as expansion reason');

$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/text-single-pass-cases.json'), true);
verify_case($fixture['observed_short']['expected_reason'] === 'below_effective_minimum', 'Observed fixture retained');
verify_case($fixture['accepted_with_tolerance']['expected_remote_text_requests'] === 1, 'Tolerance fixture uses one request');

echo "text-single-pass-length-policy: {$count}/{$count} OK\n";
