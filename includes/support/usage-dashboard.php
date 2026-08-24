<?php
/**
 * Canonical rolling-month aggregation for the Usage dashboard.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('cbia_usage_month_window_now')) {
    function cbia_usage_month_window_now($now = null) {
        if ($now instanceof DateTimeImmutable) return $now;
        if ($now instanceof DateTime) return DateTimeImmutable::createFromMutable($now);
        if ($now instanceof DateTimeInterface) {
            return new DateTimeImmutable($now->format('Y-m-d H:i:s'), $now->getTimezone());
        }
        if (function_exists('current_datetime')) return current_datetime();

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        return new DateTimeImmutable('now', $timezone);
    }
}

if (!function_exists('cbia_usage_general_range_window')) {
    function cbia_usage_general_range_window($days, $now_ts = null) {
        $days = (int) $days;
        if (!in_array($days, array(7, 30, 90, 365, 730), true)) $days = 365;
        if ($now_ts === null) {
            $now_ts = function_exists('current_time') ? current_time('timestamp') : time();
        }
        $now_ts = (int) $now_ts;
        $since_ts = $now_ts - (($days - 1) * DAY_IN_SECONDS);
        $date_fn = function_exists('wp_date') ? 'wp_date' : 'gmdate';
        return array(
            'days' => $days,
            'now_ts' => $now_ts,
            'since_ts' => $since_ts,
            'since_day' => (string) call_user_func($date_fn, 'Y-m-d', $since_ts),
            'end_day' => (string) call_user_func($date_fn, 'Y-m-d', $now_ts),
        );
    }
}

if (!function_exists('cbia_usage_row_in_general_range')) {
    function cbia_usage_row_in_general_range($row, $general_range) {
        if (!is_array($row) || !is_array($general_range)) return false;
        $sort_ts = (int) ($row['sort_ts'] ?? 0);
        if ($sort_ts > 0) return $sort_ts >= (int) ($general_range['since_ts'] ?? 0);
        $day_value = (string) ($row['day'] ?? '');
        return $day_value === '' || $day_value >= (string) ($general_range['since_day'] ?? '');
    }
}

if (!function_exists('cbia_usage_rolling_month_keys')) {
    function cbia_usage_rolling_month_keys($now = null) {
        $month_start = cbia_usage_month_window_now($now)->modify('first day of this month')->setTime(0, 0, 0);
        $keys = array();
        for ($offset = 11; $offset >= 0; $offset--) {
            $keys[] = $month_start->modify('-' . $offset . ' months')->format('Y-m');
        }
        return $keys;
    }
}

if (!function_exists('cbia_usage_monthly_empty_bucket')) {
    function cbia_usage_monthly_empty_bucket($month) {
        return array(
            'month' => (string) $month,
            'calls' => 0,
            'text_calls' => 0,
            'image_calls' => 0,
            'seo_calls' => 0,
            'text_cost_eur' => 0.0,
            'image_cost_eur' => 0.0,
            'seo_cost_eur' => 0.0,
            'cost_eur' => 0.0,
        );
    }
}

if (!function_exists('cbia_usage_monthly_series_for_keys')) {
    function cbia_usage_monthly_series_for_keys($monthly_map, $month_keys) {
        $series = array();
        foreach ((array) $month_keys as $month_key) {
            $item = isset($monthly_map[$month_key]) && is_array($monthly_map[$month_key])
                ? $monthly_map[$month_key]
                : array();
            $bucket = cbia_usage_monthly_empty_bucket($month_key);
            foreach (array('calls', 'text_calls', 'image_calls', 'seo_calls') as $field) {
                $bucket[$field] = (int) ($item[$field] ?? 0);
            }
            foreach (array('text_cost_eur', 'image_cost_eur', 'seo_cost_eur', 'cost_eur') as $field) {
                $bucket[$field] = round((float) ($item[$field] ?? 0), 6);
            }
            $series[] = $bucket;
        }
        return $series;
    }
}

if (!function_exists('cbia_usage_fill_rolling_month_series')) {
    function cbia_usage_fill_rolling_month_series($monthly_map, $now = null) {
        return cbia_usage_monthly_series_for_keys((array) $monthly_map, cbia_usage_rolling_month_keys($now));
    }
}

if (!function_exists('cbia_usage_monthly_aggregates_init')) {
    function cbia_usage_monthly_aggregates_init($now = null) {
        $month_keys = cbia_usage_rolling_month_keys($now);
        return array(
            'month_keys' => $month_keys,
            'allowed_months' => array_fill_keys($month_keys, true),
            'all' => array(),
            'by_model' => array(),
            'by_provider' => array(),
            'by_provider_model' => array(),
        );
    }
}

if (!function_exists('cbia_usage_monthly_add_to_map')) {
    function cbia_usage_monthly_add_to_map(&$monthly_map, $month, $type, $cost_value) {
        if (!isset($monthly_map[$month])) $monthly_map[$month] = cbia_usage_monthly_empty_bucket($month);
        $monthly_map[$month]['calls'] += 1;
        $call_field = in_array($type, array('image', 'seo'), true) ? $type . '_calls' : 'text_calls';
        $cost_field = in_array($type, array('image', 'seo'), true) ? $type . '_cost_eur' : 'text_cost_eur';
        $monthly_map[$month][$call_field] += 1;
        if ($cost_value !== null && $cost_value !== '' && is_numeric($cost_value)) {
            $monthly_map[$month]['cost_eur'] += (float) $cost_value;
            $monthly_map[$month][$cost_field] += (float) $cost_value;
        }
    }
}

if (!function_exists('cbia_usage_monthly_aggregates_add')) {
    function cbia_usage_monthly_aggregates_add(&$aggregates, $row) {
        if (!is_array($row)) return;
        $month = (string) ($row['month'] ?? '');
        if (!isset($aggregates['allowed_months'][$month])) return;

        $type = strtolower(trim((string) ($row['type'] ?? 'text')));
        if (!in_array($type, array('text', 'image', 'seo'), true)) $type = 'text';
        $provider = strtolower(trim((string) ($row['provider'] ?? 'unknown')));
        $provider = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $provider), '-');
        if ($provider === '') $provider = 'unknown';
        $model = trim((string) ($row['model'] ?? 'unknown'));
        if ($model === '') $model = 'unknown';
        $cost_value = $row['cost_eur'] ?? null;

        cbia_usage_monthly_add_to_map($aggregates['all'], $month, $type, $cost_value);
        if (!isset($aggregates['by_model'][$model])) $aggregates['by_model'][$model] = array();
        cbia_usage_monthly_add_to_map($aggregates['by_model'][$model], $month, $type, $cost_value);
        if (!isset($aggregates['by_provider'][$provider])) $aggregates['by_provider'][$provider] = array();
        cbia_usage_monthly_add_to_map($aggregates['by_provider'][$provider], $month, $type, $cost_value);
        if (!isset($aggregates['by_provider_model'][$provider])) $aggregates['by_provider_model'][$provider] = array();
        if (!isset($aggregates['by_provider_model'][$provider][$model])) $aggregates['by_provider_model'][$provider][$model] = array();
        cbia_usage_monthly_add_to_map($aggregates['by_provider_model'][$provider][$model], $month, $type, $cost_value);
    }
}

if (!function_exists('cbia_usage_monthly_aggregates_finalize')) {
    function cbia_usage_monthly_aggregates_finalize($aggregates) {
        $month_keys = (array) ($aggregates['month_keys'] ?? array());
        $by_model = array();
        foreach ((array) ($aggregates['by_model'] ?? array()) as $model => $map) {
            $by_model[$model] = cbia_usage_monthly_series_for_keys($map, $month_keys);
        }
        $by_provider = array();
        foreach ((array) ($aggregates['by_provider'] ?? array()) as $provider => $map) {
            $by_provider[$provider] = cbia_usage_monthly_series_for_keys($map, $month_keys);
        }
        $by_provider_model = array();
        foreach ((array) ($aggregates['by_provider_model'] ?? array()) as $provider => $models) {
            $by_provider_model[$provider] = array();
            foreach ((array) $models as $model => $map) {
                $by_provider_model[$provider][$model] = cbia_usage_monthly_series_for_keys($map, $month_keys);
            }
        }

        return array(
            'series' => cbia_usage_monthly_series_for_keys((array) ($aggregates['all'] ?? array()), $month_keys),
            'by_model' => $by_model,
            'by_provider' => $by_provider,
            'by_provider_model' => $by_provider_model,
            'start_month' => (string) ($month_keys[0] ?? ''),
            'end_month' => (string) ($month_keys[count($month_keys) - 1] ?? ''),
        );
    }
}

if (!function_exists('cbia_usage_build_rolling_month_aggregates')) {
    function cbia_usage_build_rolling_month_aggregates($rows, $now = null) {
        $aggregates = cbia_usage_monthly_aggregates_init($now);
        foreach ((array) $rows as $row) cbia_usage_monthly_aggregates_add($aggregates, $row);
        return cbia_usage_monthly_aggregates_finalize($aggregates);
    }
}
