<?php

$root = dirname(__DIR__);
$css = file_get_contents($root . '/assets/css/admin.css');
$js = file_get_contents($root . '/assets/js/admin.js');
$view = file_get_contents($root . '/includes/admin/views/usage.php');
$hooks = file_get_contents($root . '/includes/core/hooks.php');
$pot = file_get_contents($root . '/languages/cbiastudio-blogflow-ai.pot');
$po = file_get_contents($root . '/languages/cbiastudio-blogflow-ai-es_ES.po');
$mo = file_get_contents($root . '/languages/cbiastudio-blogflow-ai-es_ES.mo');
$domain = 'cbiastudio-blogflow-ai';

$count = 0;
function usage_polish_check($condition, $message) {
    global $count;
    $count++;
    if (!$condition) {
        throw new RuntimeException("Case {$count} failed: {$message}");
    }
}

function usage_polish_po_map($contents) {
    $messages = array();
    $msgid = '';
    $msgstr = '';
    $field = '';
    $has_entry = false;
    $lines = preg_split('/\r\n|\r|\n/', (string) $contents);
    $lines[] = '';

    foreach ($lines as $line) {
        if ($line === '') {
            if ($has_entry && $msgid !== '') {
                $messages[$msgid] = $msgstr;
            }
            $msgid = '';
            $msgstr = '';
            $field = '';
            $has_entry = false;
            continue;
        }

        if (strpos($line, 'msgid ') === 0) {
            $decoded = json_decode(substr($line, 6), true);
            $msgid = is_string($decoded) ? $decoded : '';
            $field = 'msgid';
            $has_entry = true;
            continue;
        }

        if (strpos($line, 'msgstr ') === 0) {
            $decoded = json_decode(substr($line, 7), true);
            $msgstr = is_string($decoded) ? $decoded : '';
            $field = 'msgstr';
            continue;
        }

        if (isset($line[0]) && $line[0] === '"') {
            $decoded = json_decode($line, true);
            if (!is_string($decoded)) {
                continue;
            }
            if ($field === 'msgid') {
                $msgid .= $decoded;
            } elseif ($field === 'msgstr') {
                $msgstr .= $decoded;
            }
        }
    }

    return $messages;
}

usage_polish_check((bool) preg_match('/\.cbia-usage-breakdown-grid\s*\{[^}]*align-items:\s*stretch;[^}]*grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\);/s', $css), 'desktop breakdown grid uses three stretching columns');
usage_polish_check(strpos($css, '.cbia-usage-breakdown-grid > .cbia-usage-panel') !== false, 'breakdown cards are constrained by their grid wrapper');
usage_polish_check(!preg_match('/^\.cbia-usage-panel\.\-(?:type|image-quality|image-role)\s*\{\s*grid-column:/m', $css), 'legacy global grid placement no longer affects breakdown cards');
usage_polish_check(strpos($css, '.cbia-usage-chart-grid > .cbia-usage-panel.-type') !== false, 'legacy placement is scoped to the legacy chart grid');
usage_polish_check((bool) preg_match('/@media\s*\(max-width:\s*1200px\)[\s\S]*?\.cbia-usage-breakdown-grid\s*\{[^}]*repeat\(2,\s*minmax\(0,\s*1fr\)\)/', $css), 'tablet breakdown uses two columns');
usage_polish_check((bool) preg_match('/@media\s*\(max-width:\s*782px\)[\s\S]*?\.cbia-usage-breakdown-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/', $css), 'mobile breakdown uses one column');
usage_polish_check(strpos($css, 'width: 33%') === false, 'breakdown layout has no fixed one-third card width');

$type_pos = strpos($view, 'class="cbia-usage-panel -type"');
$quality_pos = strpos($view, 'class="cbia-usage-panel -image-quality"');
$role_pos = strpos($view, 'class="cbia-usage-panel -image-role"');
usage_polish_check($type_pos !== false && $type_pos < $quality_pos && $quality_pos < $role_pos, 'breakdown DOM order is type, quality, role');
usage_polish_check(strpos($view, "esc_html__('Usage by type', '{$domain}')") !== false, 'Usage by type is localized in PHP');
usage_polish_check(strpos($view, "esc_html__('Image events ordered by the quality actually used.', '{$domain}')") !== false, 'quality description is localized in PHP');
usage_polish_check(strpos($view, "esc_html__('What the generated images were used for.', '{$domain}')") !== false, 'image function description is localized in PHP');
usage_polish_check(strpos($view, "'imageRole' => __('Image role', '{$domain}')") !== false, 'image function terminology uses the shared localized key');

$usage_start = strpos($js, 'function initUsageDashboard()');
$usage_end = strpos($js, 'function initUsageRecalculationActions()', $usage_start);
$usage_js = substr($js, $usage_start, $usage_end - $usage_start);
preg_match_all("/\bt\('([A-Za-z0-9_]+)'\)/", $usage_js, $js_key_matches);
preg_match_all("/'([A-Za-z0-9_]+)'\s*=>\s*__\(/", $view, $php_key_matches);
$capability_only_js_keys = array(
    'imageGeneration', 'textGeneration', 'noKnownCostDriver', 'currentConfiguration',
    'defaultImageQuality', 'internalImages', 'unknownCostPriority', 'imageCostPriority',
    'textCostPriority', 'automaticQualityNote', 'multipleImagesNote', 'balancedCostPriority',
);
$missing_js_keys = array_values(array_diff(array_unique($js_key_matches[1]), array_unique($php_key_matches[1]), $capability_only_js_keys));
usage_polish_check($missing_js_keys === array(), 'every dynamic JS label is supplied by the localized PHP payload: ' . implode(', ', $missing_js_keys));
usage_polish_check(strpos($usage_js, "'Usage by type'") === false, 'Usage by type is not hardcoded in JavaScript');
usage_polish_check(strpos($usage_js, "'Image events ordered by the quality actually used.'") === false, 'quality description is not hardcoded in JavaScript');
usage_polish_check(strpos($usage_js, "'What the generated images were used for.'") === false, 'image function description is not hardcoded in JavaScript');

$pot_messages = usage_polish_po_map($pot);
$po_messages = usage_polish_po_map($po);
preg_match_all("/(?:__|esc_html__|esc_attr__)\(\s*'((?:\\\\'|[^'])*)'\s*,\s*'{$domain}'/", $view . "\n" . $hooks, $source_matches);
$usage_msgids = array_unique(array_map(function ($message) {
    return str_replace("\\'", "'", $message);
}, $source_matches[1]));
$missing_pot = array();
$missing_po = array();
foreach ($usage_msgids as $msgid) {
    if (!array_key_exists($msgid, $pot_messages)) {
        $missing_pot[] = $msgid;
    }
    if (!isset($po_messages[$msgid]) || $po_messages[$msgid] === '') {
        $missing_po[] = $msgid;
    }
}
usage_polish_check($missing_pot === array(), 'all Usage PHP strings exist in POT: ' . implode(' | ', $missing_pot));
usage_polish_check($missing_po === array(), 'all Usage PHP strings have Spanish translations: ' . implode(' | ', $missing_po));
usage_polish_check(isset($po_messages['Usage by type']) && $po_messages['Usage by type'] === 'Uso por tipo', 'Spanish title translation is exact');
usage_polish_check(isset($po_messages['Image role']) && $po_messages['Image role'] === 'Función de la imagen', 'Spanish image function terminology is consistent');
usage_polish_check(strpos($mo, 'Uso por tipo') !== false && strpos($mo, 'Función de la imagen') !== false, 'compiled MO contains the critical Usage translations');

echo "usage-dashboard-polish-i18n: {$count}/{$count} OK\n";
