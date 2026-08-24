<?php
define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/includes/support/usage-dashboard.php';

$checks = 0;
function usage_dashboard_check($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException("Case {$checks} failed: {$message}");
}
function usage_dashboard_cost($series) {
    return array_sum(array_map(static function ($row) { return (float)($row['cost_eur'] ?? 0); }, (array)$series));
}

$madrid = new DateTimeZone('Europe/Madrid');
$now = new DateTimeImmutable('2026-08-24 10:00:00', $madrid);
$keys = cbia_usage_rolling_month_keys($now);
usage_dashboard_check(count($keys) === 12, 'the rolling window always has exactly twelve month keys');
usage_dashboard_check($keys[0] === '2025-09' && $keys[11] === '2026-08', 'the current month and previous eleven calendar months are used');
usage_dashboard_check($keys === array_values(array_unique($keys)), 'month keys are unique and chronological');

$rows = array(
    array('month' => '2025-09', 'type' => 'text', 'provider' => 'openai', 'model' => 'alpha', 'cost_eur' => 4.0),
    array('month' => '2026-04', 'type' => 'seo', 'provider' => 'openai', 'model' => 'beta', 'cost_eur' => 3.0),
    array('month' => '2026-08', 'type' => 'text', 'provider' => 'openai', 'model' => 'alpha', 'cost_eur' => 1.0),
    array('month' => '2026-08', 'type' => 'image', 'provider' => 'google', 'model' => 'alpha', 'cost_eur' => 2.0),
    array('month' => '2025-08', 'type' => 'text', 'provider' => 'openai', 'model' => 'old', 'cost_eur' => 99.0),
);
$aggregates = cbia_usage_build_rolling_month_aggregates($rows, $now);
usage_dashboard_check(count($aggregates['series']) === 12, 'aggregation returns twelve buckets');
usage_dashboard_check($aggregates['start_month'] === '2025-09' && $aggregates['end_month'] === '2026-08', 'backend publishes the canonical monthly range');
$zero_months = array_filter($aggregates['series'], static function ($row) { return (int)$row['calls'] === 0; });
usage_dashboard_check(count($zero_months) === 9, 'months without usage are zero-filled rather than omitted');
usage_dashboard_check(abs(usage_dashboard_cost($aggregates['series']) - 10.0) < 0.000001, 'events outside the rolling window are excluded');
usage_dashboard_check(abs(usage_dashboard_cost($aggregates['by_provider']['openai']) - 8.0) < 0.000001, 'provider aggregates preserve the twelve-month window');
usage_dashboard_check(abs(usage_dashboard_cost($aggregates['by_provider']['google']) - 2.0) < 0.000001, 'a second provider has an independent series');
usage_dashboard_check(abs(usage_dashboard_cost($aggregates['by_model']['alpha']) - 7.0) < 0.000001, 'model aggregates preserve the twelve-month window');
usage_dashboard_check(abs(usage_dashboard_cost($aggregates['by_provider_model']['openai']['alpha']) - 5.0) < 0.000001, 'combined provider/model filtering is pre-aggregated');

foreach (array(7, 365, 730) as $general_usage_range) {
    $again = cbia_usage_build_rolling_month_aggregates($rows, $now);
    usage_dashboard_check($again['series'] === $aggregates['series'], "general range {$general_usage_range} does not alter monthly aggregation");
}

$january = new DateTimeImmutable('2026-01-15 12:00:00', $madrid);
$january_keys = cbia_usage_rolling_month_keys($january);
usage_dashboard_check($january_keys[0] === '2025-02' && $january_keys[10] === '2025-12' && $january_keys[11] === '2026-01', 'the rolling window crosses December to January in order');

$same_instant_madrid = new DateTimeImmutable('2026-01-01 00:30:00', $madrid);
$same_instant_utc = $same_instant_madrid->setTimezone(new DateTimeZone('UTC'));
usage_dashboard_check(cbia_usage_rolling_month_keys($same_instant_madrid)[11] === '2026-01', 'site-local January is respected at a month boundary');
usage_dashboard_check(cbia_usage_rolling_month_keys($same_instant_utc)[11] === '2025-12', 'the same instant demonstrates that timezone controls the month boundary');

$source = file_get_contents(dirname(__DIR__) . '/includes/support/usage-dashboard.php');
usage_dashboard_check(strpos($source, '$days') === false, 'the canonical monthly aggregation has no general-day dependency');

$root = dirname(__DIR__);
$hooks_source = file_get_contents($root . '/includes/core/hooks.php');
$view_source = file_get_contents($root . '/includes/admin/views/usage.php');
$js_source = file_get_contents($root . '/assets/js/admin.js');
$css_source = file_get_contents($root . '/assets/css/admin.css');
$bootstrap_source = file_get_contents($root . '/includes/core/bootstrap.php');
$store_start = strpos($hooks_source, "function cbia_usage_build_payload_from_store(");
$store_end = strpos($hooks_source, "function cbia_get_usage_dashboard_payload_fast(", $store_start);
$store_source = substr($hooks_source, $store_start, $store_end - $store_start);
usage_dashboard_check(strpos($view_source, "array(7, 30, 90, 365, 730)") !== false && strpos($view_source, ": 365;") !== false, 'the general Usage selector defaults to 365 and retains 730');
usage_dashboard_check(strpos($view_source, "__('Last year'") !== false && strpos($view_source, "__('Last 2 years'") !== false, '365 and 730 have descriptive labels');
usage_dashboard_check(strpos($view_source, 'cbia-usage-filters') < strpos($view_source, 'cbia-usage-kpis') && strpos($view_source, 'cbia-usage-filters') < strpos($view_source, 'cbia-usage-chart-grid'), 'filters are rendered before every affected KPI and chart');
usage_dashboard_check(strpos($store_source, 'cbia_usage_build_rolling_month_aggregates') < strpos($store_source, 'if (!$is_in_period) continue;'), 'rolling aggregation is separated before the general-day cutoff');
usage_dashboard_check(strpos($hooks_source, 'cbia_pro_usage_overview_v12_') !== false && strpos($hooks_source, 'array(7, 30, 90, 365, 730)') !== false, 'the cache and backend accept the new default range');
usage_dashboard_check(strpos($js_source, 'monthlySeriesByProviderModel') !== false && strpos($js_source, 'for (var m = 1; m <= 12; m++)') === false, 'JavaScript selects backend provider/model series without rebuilding the month window');
usage_dashboard_check(strpos($js_source, 'periodDays || 365') !== false && strpos($js_source, 'periodDays || 30') === false, 'JavaScript uses the 365-day general default');
usage_dashboard_check(strpos($css_source, '.cbia-usage-filters') !== false && strpos($css_source, 'flex-wrap: wrap;') !== false && strpos($css_source, '@media (max-width: 780px)') !== false, 'existing responsive filter styles cover desktop, tablet, and mobile wrapping');
usage_dashboard_check(strpos($bootstrap_source, "support/usage-dashboard.php") < strpos($bootstrap_source, "core/hooks.php"), 'the canonical monthly helper loads before dashboard hooks');

echo "usage-dashboard-range: {$checks}/{$checks} OK\n";
