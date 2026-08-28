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

if (!function_exists('cbia_usage_range_granularity')) {
    function cbia_usage_range_granularity($days, $range_key = '') {
        $days = max(1, (int) $days);
        if ($range_key === '12m' || $days > 180) return 'month';
        if ($days > 45) return 'week';
        return 'day';
    }
}

if (!function_exists('cbia_usage_parse_site_timestamp')) {
    function cbia_usage_parse_site_timestamp($value) {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) return (int) $value;
        $raw = trim((string) $value);
        if ($raw === '') return 0;
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        if (preg_match('/(?:z|[+-]\d{2}:?\d{2})$/i', $raw)) {
            try {
                return (new DateTimeImmutable($raw))->getTimestamp();
            } catch (Exception $exception) {
                return 0;
            }
        }
        foreach (array('Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d') as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $raw, $timezone);
            if ($parsed && $parsed->format($format) === $raw) return $parsed->getTimestamp();
        }
        try {
            return (new DateTimeImmutable($raw, $timezone))->getTimestamp();
        } catch (Exception $exception) {
            return 0;
        }
    }
}

if (!function_exists('cbia_usage_resolve_range')) {
    function cbia_usage_resolve_range($range_key = '30d', $from_day = '', $to_day = '', $now = null) {
        $now_dt = cbia_usage_month_window_now($now);
        $timezone = $now_dt->getTimezone();
        $today = $now_dt->setTime(0, 0, 0);
        $range_key = strtolower(trim((string) $range_key));
        $quick_days = array('7d' => 7, '30d' => 30, '90d' => 90, '12m' => 365);
        if (!isset($quick_days[$range_key]) && $range_key !== 'custom') $range_key = '30d';

        $start = null;
        $end = null;
        if ($range_key === 'custom') {
            $from = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $from_day, $timezone);
            $to = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $to_day, $timezone);
            if ($from && $to && $from->format('Y-m-d') === (string) $from_day && $to->format('Y-m-d') === (string) $to_day && $from <= $to) {
                $start = $from->setTime(0, 0, 0);
                $end = $to->setTime(0, 0, 0);
            } else {
                $range_key = '30d';
            }
        }

        if ($range_key !== 'custom') {
            $days = $quick_days[$range_key];
            $end = $today;
            $start = $today->modify('-' . ($days - 1) . ' days');
        }

        $days = (int) $start->diff($end)->format('%a') + 1;
        if ($days > 730) {
            $days = 730;
            $start = $end->modify('-729 days');
        }
        $previous_end = $start->modify('-1 day');
        $previous_start = $previous_end->modify('-' . ($days - 1) . ' days');

        return array(
            'key' => $range_key,
            'days' => $days,
            'now_ts' => $now_dt->getTimestamp(),
            'since_ts' => $start->getTimestamp(),
            'end_ts' => $end->setTime(23, 59, 59)->getTimestamp(),
            'since_day' => $start->format('Y-m-d'),
            'end_day' => $end->format('Y-m-d'),
            'previous_since_ts' => $previous_start->getTimestamp(),
            'previous_end_ts' => $previous_end->setTime(23, 59, 59)->getTimestamp(),
            'previous_since_day' => $previous_start->format('Y-m-d'),
            'previous_end_day' => $previous_end->format('Y-m-d'),
            'granularity' => cbia_usage_range_granularity($days, $range_key),
        );
    }
}

if (!function_exists('cbia_usage_general_range_window')) {
    function cbia_usage_general_range_window($days, $now_ts = null) {
        $days = (int) $days;
        $keys = array(7 => '7d', 30 => '30d', 90 => '90d', 365 => '12m');
        $range_key = isset($keys[$days]) ? $keys[$days] : '30d';
        $now = null;
        if ($now_ts !== null) {
            $now = new DateTimeImmutable('@' . (int) $now_ts);
            if (function_exists('wp_timezone')) $now = $now->setTimezone(wp_timezone());
        }
        return cbia_usage_resolve_range($range_key, '', '', $now);
    }
}

if (!function_exists('cbia_usage_row_in_general_range')) {
    function cbia_usage_row_in_general_range($row, $general_range) {
        if (!is_array($row) || !is_array($general_range)) return false;
        $day_value = (string) ($row['day'] ?? '');
        if ($day_value !== '') {
            return $day_value >= (string) ($general_range['since_day'] ?? '')
                && $day_value <= (string) ($general_range['end_day'] ?? '9999-12-31');
        }
        $sort_ts = (int) ($row['sort_ts'] ?? 0);
        if ($sort_ts > 0) {
            return $sort_ts >= (int) ($general_range['since_ts'] ?? 0)
                && $sort_ts <= (int) ($general_range['end_ts'] ?? PHP_INT_MAX);
        }
        return true;
    }
}

if (!function_exists('cbia_usage_row_in_previous_range')) {
    function cbia_usage_row_in_previous_range($row, $general_range) {
        if (!is_array($row) || !is_array($general_range)) return false;
        $day_value = (string) ($row['day'] ?? '');
        if ($day_value !== '') {
            return $day_value >= (string) ($general_range['previous_since_day'] ?? '')
                && $day_value <= (string) ($general_range['previous_end_day'] ?? '');
        }
        $sort_ts = (int) ($row['sort_ts'] ?? 0);
        if ($sort_ts > 0) {
            return $sort_ts >= (int) ($general_range['previous_since_ts'] ?? 0)
                && $sort_ts <= (int) ($general_range['previous_end_ts'] ?? 0);
        }
        return false;
    }
}

if (!function_exists('cbia_usage_metric_comparison')) {
    function cbia_usage_metric_comparison($current, $previous) {
        $current = (float) $current;
        $previous = (float) $previous;
        if ($previous <= 0) {
            return array('status' => $current > 0 ? 'new_activity' : 'no_comparison', 'percent' => null);
        }
        $percent = round((($current - $previous) / abs($previous)) * 100, 1);
        return array(
            'status' => $percent > 0 ? 'increased' : ($percent < 0 ? 'decreased' : 'unchanged'),
            'percent' => $percent,
        );
    }
}

if (!function_exists('cbia_usage_scrub_cost_intelligence')) {
    function cbia_usage_scrub_cost_intelligence($payload) {
        $data = is_array($payload) ? $payload : array();
        $data['canViewCosts'] = 0;
        unset($data['usdToEur'], $data['costControl']);
        foreach ((array) ($data['rows'] ?? array()) as $index => $row) {
            if (!is_array($row)) continue;
            $row['cost_eur'] = null;
            foreach (array('cost_usd', 'cost_micro_usd', 'cost_currency', 'cost_status', 'cost_source', 'cost_reason', 'pricing_version', 'pricing_verified_at', 'official_cost_eur', 'official_cost_usd') as $field) {
                unset($row[$field]);
            }
            $data['rows'][$index] = $row;
        }
        $scrub_series = static function ($series) {
            if (!is_array($series)) return array();
            foreach ($series as $index => $row) {
                if (!is_array($row)) continue;
                foreach (array('text_cost_eur', 'image_cost_eur', 'seo_cost_eur', 'cost_eur', 'textCost', 'imageCost', 'seoCost', 'cost') as $field) {
                    if (array_key_exists($field, $row)) $row[$field] = 0.0;
                }
                $series[$index] = $row;
            }
            return $series;
        };
        $data['dailySeries'] = $scrub_series($data['dailySeries'] ?? array());
        $data['monthlySeries'] = $scrub_series($data['monthlySeries'] ?? array());
        foreach (array('summariesByModel', 'previousSummariesByModel') as $group) {
            foreach ((array) ($data[$group] ?? array()) as $key => $summary) {
                if (!is_array($summary)) continue;
                $summary['totalCost'] = 0.0;
                $summary['avgCostPerPost'] = 0.0;
                $summary['knownCostEvents'] = 0;
                $summary['unknownCostEvents'] = 0;
                $summary['costCoveragePercent'] = 0.0;
                $summary['costStatusCounts'] = array();
                $summary['dailySeries'] = $scrub_series($summary['dailySeries'] ?? array());
                $summary['monthlySeries'] = $scrub_series($summary['monthlySeries'] ?? array());
                $data[$group][$key] = $summary;
            }
        }
        $data['monthlySeriesByProvider'] = array();
        $data['monthlySeriesByProviderModel'] = array();
        return $data;
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
