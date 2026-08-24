<?php
define('ABSPATH', __DIR__ . '/');

if (!function_exists('mb_substr')) { function mb_substr($value, $start, $length = null) { return $length === null ? substr($value, $start) : substr($value, $start, $length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower($value) { return strtolower($value); } }
if (!function_exists('mb_strpos')) { function mb_strpos($haystack, $needle) { return strpos($haystack, $needle); } }
if (!function_exists('mb_strlen')) { function mb_strlen($value) { return strlen($value); } }

function remove_accents($value) {
    return strtr((string)$value, array(
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'ñ' => 'n', 'Ñ' => 'N',
    ));
}
function wp_strip_all_tags($value) { return strip_tags((string)$value); }

require dirname(__DIR__) . '/includes/engine/taxonomy.php';

$checks = 0;
function category_classifier_check($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException("Case {$checks} failed: {$message}");
}

category_classifier_check(cbia_category_comparable_score_margin() === 15, 'the comparable-score margin is explicit and testable');

$mapping = "Brands and producers: Padron, Davidoff, Arturo Fuente\n"
    . "Production: fermentation, aging, rolling\n"
    . "Tobacco and regions: tobacco, Nicaragua, wrapper";
$repeated_content = str_repeat('Fermentation changes tobacco during production. ', 20);

$brand_result = cbia_rank_categories_by_mapping(
    $mapping,
    'How fermentation transforms Padron tobacco',
    $repeated_content
);
category_classifier_check($brand_result[0] === 'Brands and producers', 'a central proper-name title entity beats repeated generic content keywords');

$process_result = cbia_rank_categories_by_mapping(
    $mapping,
    'How fermentation changes natural sugars in tobacco',
    str_repeat('The tobacco changes during fermentation. ', 8)
);
category_classifier_check($process_result[0] === 'Production', 'the process category wins when no central named entity is present');

$tie_result = cbia_rank_categories_by_mapping(
    "Configured priority: beta\nSecond priority: alpha",
    'A neutral heading',
    'Alpha and beta are discussed once.'
);
category_classifier_check($tie_result[0] === 'Configured priority', 'configured line order resolves comparable scores');

$strong_result = cbia_rank_categories_by_mapping(
    "Weak high priority: generic\nOther: secondary\nStrong low priority: Acme",
    'A practical guide to Acme systems',
    str_repeat('Generic generic generic generic generic. ', 50)
);
category_classifier_check($strong_result[0] === 'Strong low priority', 'a strong title entity beats a weak higher-priority content match');

for ($iteration = 0; $iteration < 25; $iteration++) {
    category_classifier_check(
        cbia_rank_categories_by_mapping($mapping, 'How fermentation transforms Padron tobacco', $repeated_content) === $brand_result,
        'identical inputs always return the same ordered result'
    );
}

$fallback = cbia_rank_category_candidates(array(
    array('name' => 'Zulu', 'normalized_name' => 'zulu', 'score' => 100),
    array('name' => 'Alpha', 'normalized_name' => 'alpha', 'score' => 100),
));
category_classifier_check($fallback[0]['name'] === 'Alpha', 'missing configured priorities fall back to normalized category name');

$accent_result = cbia_rank_categories_by_mapping(
    "Coffee: cafe\nTravel: viaje",
    'A guide to CAFÉ culture',
    ''
);
category_classifier_check($accent_result[0] === 'Coffee', 'Unicode accents are normalized and matching is case-insensitive');

$case_result = cbia_rank_categories_by_mapping("Mixed case: DeEpSeEk", 'Using deepseek safely', '');
category_classifier_check($case_result[0] === 'Mixed case', 'keyword matching is case-insensitive');

$stuffing_result = cbia_rank_categories_by_mapping(
    "Stuffed: generic\nCentral: Northstar",
    'An introduction to Northstar workflows',
    str_repeat('generic ', 200)
);
category_classifier_check($stuffing_result[0] === 'Central', 'bounded frequency prevents keyword stuffing from dominating title relevance');

$source = file_get_contents(dirname(__DIR__) . '/includes/engine/taxonomy.php');
category_classifier_check(strpos($source, 'wp_remote_') === false, 'classification adds no remote or AI API call');
category_classifier_check(strpos($source, 'Marche e produttori') === false, 'the classifier contains no vertical-specific category names');

echo "category-classifier: {$checks}/{$checks} OK\n";
