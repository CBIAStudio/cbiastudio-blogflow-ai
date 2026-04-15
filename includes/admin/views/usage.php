<?php
/**
 * Unified Usage tab (Usage metrics + Costs section).
 */
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

if (!defined('ABSPATH')) {
    exit;
}

$allowed_days = array(7, 30, 90);
$days = isset($_GET['usage_days']) ? absint(wp_unslash((string) $_GET['usage_days'])) : 30;
if (!in_array($days, $allowed_days, true)) {
    $days = 30;
}

$requested_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'usage';
$posted_form = isset($_POST['cbia_form']) ? sanitize_key(wp_unslash((string) $_POST['cbia_form'])) : '';
$default_section = ($requested_tab === 'costes' || $posted_form === 'costes_settings' || $posted_form === 'costes_actions') ? 'costs' : 'overview';
$usage_section = isset($_GET['usage_section']) ? sanitize_key(wp_unslash((string) $_GET['usage_section'])) : $default_section;
if (!in_array($usage_section, array('overview', 'costs'), true)) {
    $usage_section = 'overview';
}
$is_pro_edition = defined('CBIA_EDITION') && strtolower((string) CBIA_EDITION) === 'pro';
$usage_advanced_enabled = function_exists('cbia_cap_enabled') ? cbia_cap_enabled('usage_advanced') : $is_pro_edition;
$costs_advanced_enabled = function_exists('cbia_cap_enabled') ? cbia_cap_enabled('costs_advanced') : $is_pro_edition;
if (!$costs_advanced_enabled && $usage_section === 'costs') {
    $usage_section = 'overview';
}

$requested_model = isset($_GET['usage_model']) ? sanitize_text_field(wp_unslash((string) $_GET['usage_model'])) : '';
if (false) {
$since_ts = time() - ($days * DAY_IN_SECONDS);
$cbia_settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
$cost_settings = function_exists('cbia_costes_get_settings') ? cbia_costes_get_settings() : array();
$cost_table = function_exists('cbia_costes_price_table_usd_per_million') ? cbia_costes_price_table_usd_per_million() : array();

$cache_ttl = (int) apply_filters('cbia_pro_usage_cache_ttl', 15 * MINUTE_IN_SECONDS);
if ($cache_ttl < 0) {
    $cache_ttl = 0;
}
$recent_rows_limit = (int) apply_filters('cbia_pro_usage_recent_rows_limit', 120);
if ($recent_rows_limit < 20) {
    $recent_rows_limit = 20;
}
$cache_key = 'cbia_pro_usage_overview_v5_' . get_current_blog_id() . '_' . (int) $days;
$cached_usage = $cache_ttl > 0 ? get_transient($cache_key) : false;

if (is_array($cached_usage)) {
    $log_rows = is_array($cached_usage['log_rows'] ?? null) ? $cached_usage['log_rows'] : array();
    $recent_rows = is_array($cached_usage['recent_rows'] ?? null) ? $cached_usage['recent_rows'] : array();
    $daily_series = is_array($cached_usage['daily_series'] ?? null) ? $cached_usage['daily_series'] : array();
    $monthly_series = is_array($cached_usage['monthly_series'] ?? null) ? $cached_usage['monthly_series'] : array();
    $model_options = is_array($cached_usage['model_options'] ?? null) ? $cached_usage['model_options'] : array();
    $summaries_by_model = is_array($cached_usage['summaries_by_model'] ?? null) ? $cached_usage['summaries_by_model'] : array();
} else {
    $available_models = array();
    $log_rows = array();
    $recent_rows = array();
    $author_names = array();
    $daily_buckets = array();
    $monthly_buckets = array();

$query = new WP_Query(array(
    'post_type' => 'post',
    'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
    'posts_per_page' => 500,
    'fields' => 'ids',
    'no_found_rows' => true,
    'orderby' => 'modified',
    'order' => 'DESC',
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => '_cbia_usage_rows',
            'compare' => 'EXISTS',
        ),
        array(
            'key' => '_cbia_usage_calls',
            'compare' => 'EXISTS',
        ),
        array(
            'key' => '_cbia_image_calls',
            'compare' => 'EXISTS',
        ),
        array(
            'key' => '_cbia_tokens_total_sum',
            'compare' => 'EXISTS',
        ),
    ),
));

$post_ids = !empty($query->posts) ? $query->posts : array();
foreach ($post_ids as $post_id) {
    if (!function_exists('cbia_costes_get_usage_rows_for_post')) {
        break;
    }

    $legacy_image_calls = array();
    $raw_legacy_images = get_post_meta((int) $post_id, '_cbia_image_calls', true);
    if (is_string($raw_legacy_images) && $raw_legacy_images !== '') {
        $decoded_legacy_images = json_decode($raw_legacy_images, true);
        if (is_array($decoded_legacy_images)) {
            $legacy_image_calls = $decoded_legacy_images;
        }
    } elseif (is_array($raw_legacy_images)) {
        $legacy_image_calls = $raw_legacy_images;
    }

    $usage_rows = cbia_costes_get_usage_rows_for_post((int) $post_id);
    if (empty($usage_rows) || !is_array($usage_rows)) {
        continue;
    }

    $post_multiplier = 1.0;
    $real_adjust_multiplier = isset($cost_settings['real_adjust_multiplier']) ? (float) $cost_settings['real_adjust_multiplier'] : 1.0;
    if ($real_adjust_multiplier > 0 && $real_adjust_multiplier !== 1.0) {
        $post_multiplier = $real_adjust_multiplier;
    }

    $post_id = (int) $post_id;
    $post_title = get_the_title($post_id);
    if ($post_title === '') {
        $post_title = 'Post #' . $post_id;
    }

    $author_id = (int) get_post_field('post_author', $post_id);
    if (!isset($author_names[$author_id])) {
        $user_obj = $author_id > 0 ? get_user_by('id', $author_id) : false;
        $author_names[$author_id] = $user_obj ? (string) $user_obj->display_name : ('User #' . $author_id);
    }

    foreach ($usage_rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $ts_raw = isset($row['ts']) ? (string) $row['ts'] : '';
        $ts = $ts_raw !== '' ? strtotime($ts_raw) : 0;

        $type = isset($row['type']) ? strtolower(trim((string) $row['type'])) : 'text';
        if (!in_array($type, array('text', 'image', 'seo'), true)) {
            $type = 'text';
        }
        $model = (string) ($row['model'] ?? '');
        if ($model === '') {
            $model = 'unknown';
        }
        $attach_id = (int) ($row['attach_id'] ?? 0);
        $section = sanitize_key((string) ($row['section'] ?? ''));
        if ($type === 'image' && $section === '' && !empty($legacy_image_calls)) {
            foreach ($legacy_image_calls as $legacy_image_call) {
                if (!is_array($legacy_image_call)) {
                    continue;
                }
                $legacy_attach = isset($legacy_image_call['attach_id']) ? (int) $legacy_image_call['attach_id'] : 0;
                $legacy_model = isset($legacy_image_call['model']) ? (string) $legacy_image_call['model'] : '';
                $legacy_ts = isset($legacy_image_call['ts']) ? (string) $legacy_image_call['ts'] : '';
                $same_attach = $attach_id > 0 && $legacy_attach > 0 && $attach_id === $legacy_attach;
                $same_signature = !$same_attach
                    && $legacy_model !== ''
                    && $legacy_model === $model
                    && $legacy_ts !== ''
                    && $legacy_ts === $ts_raw;
                if ($same_attach || $same_signature) {
                    $section = sanitize_key((string) ($legacy_image_call['section'] ?? ''));
                    if ($section !== '') {
                        break;
                    }
                }
            }
        }
        $section_label = '';
        $section_detail = '';
        if ($type === 'image') {
            if ($section === 'featured' || $section === 'intro') {
                $section_label = 'featured';
                $section_detail = __('Featured', 'cbiastudio-blogflow-ai');
            } elseif ($section !== '') {
                $section_label = 'internal';
                $section_detail = 'Internal Â· ' . ucfirst(str_replace(array('_', '-'), ' ', $section));
            }
        }

        $available_models[$model] = true;

        $tokens_in = (int) ($row['in'] ?? 0);
        $tokens_out = (int) ($row['out'] ?? 0);
        $tokens_total = $tokens_in + $tokens_out;
        $is_ok = !empty($row['ok']);
        $row_cost_eur = null;
        if (function_exists('cbia_costes_calc_row_eur')) {
            $row_cost_eur = cbia_costes_calc_row_eur($row, $cost_settings, $cost_table);
            if ($row_cost_eur !== null && $post_multiplier !== 1.0) {
                $row_cost_eur = (float) $row_cost_eur * $post_multiplier;
            }
            if ($row_cost_eur !== null) {
                $row_cost_eur = round((float) $row_cost_eur, 6);
            }
        }

        $day_value = '';
        if ($ts) {
            $day_value = gmdate('Y-m-d', $ts);
        } elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $ts_raw, $m_day)) {
            $day_value = (string) $m_day[0];
        }
        $month_value = '';
        if ($ts) {
            $month_value = gmdate('Y-m', $ts);
        } elseif (preg_match('/\d{4}-\d{2}/', $ts_raw, $m_month)) {
            $month_value = (string) $m_month[0];
        }

        if ($month_value !== '') {
            if (!isset($monthly_buckets[$month_value])) {
                $monthly_buckets[$month_value] = array(
                    'month' => $month_value,
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
            $monthly_buckets[$month_value]['calls'] += 1;
            if ($type === 'image') {
                $monthly_buckets[$month_value]['image_calls'] += 1;
            } elseif ($type === 'seo') {
                $monthly_buckets[$month_value]['seo_calls'] += 1;
            } else {
                $monthly_buckets[$month_value]['text_calls'] += 1;
            }
            if ($row_cost_eur !== null) {
                $monthly_buckets[$month_value]['cost_eur'] += (float) $row_cost_eur;
                if ($type === 'image') {
                    $monthly_buckets[$month_value]['image_cost_eur'] += (float) $row_cost_eur;
                } elseif ($type === 'seo') {
                    $monthly_buckets[$month_value]['seo_cost_eur'] += (float) $row_cost_eur;
                } else {
                    $monthly_buckets[$month_value]['text_cost_eur'] += (float) $row_cost_eur;
                }
            }
        }

        if ($ts && $ts < $since_ts) {
            continue;
        }

        $log_rows[] = array(
            'post_id' => $post_id,
            'post_title' => (string) $post_title,
            'post_edit_url' => (string) get_edit_post_link($post_id, ''),
            'ts' => $ts_raw,
            'day' => $day_value,
            'month' => $month_value,
            'type' => $type,
            'type_label' => $type === 'image'
                ? __('Image', 'cbiastudio-blogflow-ai')
                : ($type === 'seo' ? __('SEO', 'cbiastudio-blogflow-ai') : __('Text', 'cbiastudio-blogflow-ai')),
            'section' => $section,
            'section_label' => $section_label,
            'section_detail' => $section_detail,
            'attach_id' => $attach_id,
            'model' => $model,
            'tokens_in' => $tokens_in,
            'tokens_out' => $tokens_out,
            'tokens_total' => $tokens_total,
            'token_metrics_applicable' => $type !== 'image',
            'cached_in' => (int) ($row['cin'] ?? 0),
            'cost_eur' => $row_cost_eur,
            'ok' => $is_ok ? 1 : 0,
            'status_label' => $is_ok ? 'OK' : 'Error',
            'user_id' => $author_id,
            'user_name' => (string) $author_names[$author_id],
            'source_label' => (string) $post_title,
            'message_preview' => $type === 'image'
                ? __('Image generation', 'cbiastudio-blogflow-ai') . ($section_detail !== '' ? ' (' . $section_detail . ')' : '')
                : ($type === 'seo' ? __('SEO / metadata process', 'cbiastudio-blogflow-ai') : __('Text generation', 'cbiastudio-blogflow-ai')),
        );

        if ($day_value !== '') {
            if (!isset($daily_buckets[$day_value])) {
                $daily_buckets[$day_value] = array(
                    'calls' => 0,
                    'text' => 0,
                    'image' => 0,
                    'seo' => 0,
                    'textCalls' => 0,
                    'imageCalls' => 0,
                    'seoCalls' => 0,
                    'day' => $day_value,
                );
            }
            $daily_buckets[$day_value]['calls'] += 1;
            if (isset($daily_buckets[$day_value][$type])) {
                $daily_buckets[$day_value][$type] += ($type === 'image')
                    ? 1
                    : $tokens_total;
            }
            $count_key = $type . 'Calls';
            if (isset($daily_buckets[$day_value][$count_key])) {
                $daily_buckets[$day_value][$count_key] += 1;
            }
        }
    }
}

usort($log_rows, function ($a, $b) {
    $ats = strtotime((string) ($a['ts'] ?? '')) ?: 0;
    $bts = strtotime((string) ($b['ts'] ?? '')) ?: 0;
    return $bts <=> $ats;
});
ksort($daily_buckets, SORT_STRING);
$daily_series = array_values(array_map(static function ($item) {
    $item['totalTokens'] = (int) ($item['text'] + $item['image'] + $item['seo']);
    return $item;
}, $daily_buckets));
ksort($monthly_buckets, SORT_STRING);
$monthly_series = array_values(array_map(static function ($item) {
    $item['cost_eur'] = round((float) ($item['cost_eur'] ?? 0), 6);
    return $item;
}, $monthly_buckets));

$model_options = array_keys($available_models);
sort($model_options, SORT_STRING);
$recent_rows = array_slice($log_rows, 0, $recent_rows_limit);

$init_model_summary = static function () {
    return array(
        'total_calls' => 0,
        'post_ids' => array(),
        'user_ids' => array(),
        'total_tokens' => 0,
        'total_cost' => 0.0,
        'daily' => array(),
        'monthly' => array(),
        'type_counts' => array(
            'text' => 0,
            'image' => 0,
            'seo' => 0,
        ),
    );
};
$summary_key_all = '__all__';
$summary_rows = array($summary_key_all => $init_model_summary());

foreach ($log_rows as $row) {
    if (!is_array($row)) {
        continue;
    }

    $model_key = (string) ($row['model'] ?? 'unknown');
    if ($model_key === '') {
        $model_key = 'unknown';
    }
    if (!isset($summary_rows[$model_key])) {
        $summary_rows[$model_key] = $init_model_summary();
    }

    foreach (array($summary_key_all, $model_key) as $summary_key) {
        $summary_rows[$summary_key]['total_calls'] += 1;

        $post_id_value = (int) ($row['post_id'] ?? 0);
        if ($post_id_value > 0) {
            $summary_rows[$summary_key]['post_ids'][$post_id_value] = true;
        }

        $user_id_value = (int) ($row['user_id'] ?? 0);
        if ($user_id_value > 0) {
            $summary_rows[$summary_key]['user_ids'][$user_id_value] = true;
        }

        $summary_rows[$summary_key]['total_tokens'] += (int) ($row['tokens_total'] ?? 0);

        $row_cost_value = $row['cost_eur'] ?? null;
        if ($row_cost_value !== null && $row_cost_value !== '' && is_numeric($row_cost_value)) {
            $summary_rows[$summary_key]['total_cost'] += (float) $row_cost_value;
        }

        $row_type = (string) ($row['type'] ?? 'text');
        if (!isset($summary_rows[$summary_key]['type_counts'][$row_type])) {
            $summary_rows[$summary_key]['type_counts'][$row_type] = 0;
        }
        $summary_rows[$summary_key]['type_counts'][$row_type] += 1;

        $day_key = (string) ($row['day'] ?? '');
        if ($day_key !== '') {
            if (!isset($summary_rows[$summary_key]['daily'][$day_key])) {
                $summary_rows[$summary_key]['daily'][$day_key] = array(
                    'day' => $day_key,
                    'calls' => 0,
                    'text' => 0,
                    'image' => 0,
                    'seo' => 0,
                    'textCalls' => 0,
                    'imageCalls' => 0,
                    'seoCalls' => 0,
                );
            }
            $summary_rows[$summary_key]['daily'][$day_key]['calls'] += 1;
            if ($row_type === 'image') {
                $summary_rows[$summary_key]['daily'][$day_key]['image'] += 1;
                $summary_rows[$summary_key]['daily'][$day_key]['imageCalls'] += 1;
            } elseif ($row_type === 'seo') {
                $summary_rows[$summary_key]['daily'][$day_key]['seo'] += (int) ($row['tokens_total'] ?? 0);
                $summary_rows[$summary_key]['daily'][$day_key]['seoCalls'] += 1;
            } else {
                $summary_rows[$summary_key]['daily'][$day_key]['text'] += (int) ($row['tokens_total'] ?? 0);
                $summary_rows[$summary_key]['daily'][$day_key]['textCalls'] += 1;
            }
        }

        $month_key = (string) ($row['month'] ?? '');
        if ($month_key !== '') {
            if (!isset($summary_rows[$summary_key]['monthly'][$month_key])) {
                $summary_rows[$summary_key]['monthly'][$month_key] = array(
                    'month' => $month_key,
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
            $summary_rows[$summary_key]['monthly'][$month_key]['calls'] += 1;
            if ($row_type === 'image') {
                $summary_rows[$summary_key]['monthly'][$month_key]['image_calls'] += 1;
            } elseif ($row_type === 'seo') {
                $summary_rows[$summary_key]['monthly'][$month_key]['seo_calls'] += 1;
            } else {
                $summary_rows[$summary_key]['monthly'][$month_key]['text_calls'] += 1;
            }
            if ($row_cost_value !== null && $row_cost_value !== '' && is_numeric($row_cost_value)) {
                $row_cost_float = (float) $row_cost_value;
                $summary_rows[$summary_key]['monthly'][$month_key]['cost_eur'] += $row_cost_float;
                if ($row_type === 'image') {
                    $summary_rows[$summary_key]['monthly'][$month_key]['image_cost_eur'] += $row_cost_float;
                } elseif ($row_type === 'seo') {
                    $summary_rows[$summary_key]['monthly'][$month_key]['seo_cost_eur'] += $row_cost_float;
                } else {
                    $summary_rows[$summary_key]['monthly'][$month_key]['text_cost_eur'] += $row_cost_float;
                }
            }
        }
    }
}

$summaries_by_model = array();
foreach ($summary_rows as $summary_key => $summary) {
    ksort($summary['daily'], SORT_STRING);
    ksort($summary['monthly'], SORT_STRING);
    $normalized_daily = array_values(array_map(static function ($item) {
        $item['totalTokens'] = (int) (($item['text'] ?? 0) + ($item['image'] ?? 0) + ($item['seo'] ?? 0));
        return $item;
    }, $summary['daily']));
    $normalized_monthly = array_values(array_map(static function ($item) {
        $item['cost_eur'] = round((float) ($item['cost_eur'] ?? 0), 6);
        $item['text_cost_eur'] = round((float) ($item['text_cost_eur'] ?? 0), 6);
        $item['image_cost_eur'] = round((float) ($item['image_cost_eur'] ?? 0), 6);
        $item['seo_cost_eur'] = round((float) ($item['seo_cost_eur'] ?? 0), 6);
        return $item;
    }, $summary['monthly']));
    $post_count = count($summary['post_ids']);
    $total_calls = (int) ($summary['total_calls'] ?? 0);
    $summaries_by_model[$summary_key] = array(
        'totalCalls' => $total_calls,
        'uniquePosts' => $post_count,
        'uniqueUsers' => count($summary['user_ids']),
        'totalTokens' => (int) ($summary['total_tokens'] ?? 0),
        'avgTokens' => $total_calls > 0 ? (int) round(((int) ($summary['total_tokens'] ?? 0)) / $total_calls) : 0,
        'totalCost' => round((float) ($summary['total_cost'] ?? 0), 6),
        'avgCostPerPost' => $post_count > 0 ? round(((float) ($summary['total_cost'] ?? 0)) / $post_count, 6) : 0.0,
        'typeCounts' => array(
            'text' => (int) ($summary['type_counts']['text'] ?? 0),
            'image' => (int) ($summary['type_counts']['image'] ?? 0),
            'seo' => (int) ($summary['type_counts']['seo'] ?? 0),
        ),
        'dailySeries' => $normalized_daily,
        'monthlySeries' => $normalized_monthly,
    );
}

$daily_series = $summaries_by_model[$summary_key_all]['dailySeries'] ?? $daily_series;
$monthly_series = $summaries_by_model[$summary_key_all]['monthlySeries'] ?? $monthly_series;
    if ($cache_ttl > 0) {
        set_transient($cache_key, array(
            'log_rows' => $log_rows,
            'recent_rows' => $recent_rows,
            'daily_series' => $daily_series,
            'monthly_series' => $monthly_series,
            'model_options' => $model_options,
            'summaries_by_model' => $summaries_by_model,
        ), $cache_ttl);
    }
}

if (empty($recent_rows) && !empty($log_rows)) {
    $recent_rows = array_slice($log_rows, 0, $recent_rows_limit);
}
if (!isset($summaries_by_model) || !is_array($summaries_by_model)) {
    $summaries_by_model = array();
}
}

$recent_rows_limit = (int) apply_filters('cbia_pro_usage_recent_rows_limit', 120);
if ($recent_rows_limit < 20) {
    $recent_rows_limit = 20;
}
$log_rows = array();
$recent_rows = array();
$daily_series = array();
$monthly_series = array();
$summaries_by_model = array();
$model_options = array();

$provider_settings = function_exists('cbia_providers_get_settings') ? cbia_providers_get_settings() : array();
$providers_all = function_exists('cbia_providers_get_all') ? cbia_providers_get_all() : array();
$provider_key = function_exists('cbia_providers_get_current_provider') ? cbia_providers_get_current_provider() : 'openai';
$provider = $providers_all[$provider_key] ?? ($providers_all['openai'] ?? array('label' => 'OpenAI', 'models' => array('gpt-4.1-mini')));
$provider_label = (string) ($provider['label'] ?? $provider_key);
$provider_logo = plugins_url('assets/images/providers/' . $provider_key . '.svg', CBIA_PRO_PLUGIN_FILE);
$provider_cfg = function_exists('cbia_providers_get_provider') ? cbia_providers_get_provider($provider_key) : array();
$current_model = (string) ($provider_cfg['model'] ?? ($provider['models'][0] ?? 'gpt-4.1-mini'));

$export_url = wp_nonce_url(
    admin_url('admin-post.php?action=cbia_usage_export&usage_days=' . (int) $days . '&usage_model=' . rawurlencode((string) $requested_model)),
    'cbia_usage_export'
);

$base_tab_url = admin_url('admin.php?page=cbia&tab=usage');
$overview_url = add_query_arg(
    array(
        'usage_section' => 'overview',
        'usage_days' => $days,
        'usage_model' => $requested_model,
    ),
    $base_tab_url
);
$costs_url = add_query_arg(
    array(
        'usage_section' => 'costs',
        'usage_days' => $days,
        'usage_model' => $requested_model,
    ),
    $base_tab_url
);
$pro_upgrade_url_default = defined('CBIA_PRO_UPGRADE_URL_DEFAULT')
    ? (string) CBIA_PRO_UPGRADE_URL_DEFAULT
    : 'https://cbia-studio.lemonsqueezy.com/checkout';
$pro_upgrade_url = apply_filters('cbia_pro_upgrade_url', $pro_upgrade_url_default);

$usage_cache_ttl = (int) apply_filters('cbia_pro_usage_cache_ttl', 15 * MINUTE_IN_SECONDS);
if ($usage_cache_ttl < 0) {
    $usage_cache_ttl = 0;
}
$dashboard_payload = array();
if (!$usage_advanced_enabled) {
    $dashboard_payload = function_exists('cbia_get_usage_dashboard_payload_fast')
        ? cbia_get_usage_dashboard_payload_fast($days, $requested_model)
        : (function_exists('cbia_get_usage_dashboard_payload_basic')
            ? cbia_get_usage_dashboard_payload_basic($days, $requested_model)
            : array());
} else {
    $dashboard_payload = function_exists('cbia_get_usage_dashboard_payload_fast')
        ? cbia_get_usage_dashboard_payload_fast($days, $requested_model)
        : array(
            'rows' => $recent_rows,
            'totalRows' => count($log_rows),
            'rowsLimited' => count($log_rows) > count($recent_rows),
            'recentRowsLimit' => $recent_rows_limit,
            'dailySeries' => $daily_series,
            'monthlySeries' => $monthly_series,
            'summariesByModel' => $summaries_by_model,
            'modelOptions' => $model_options,
            'defaultModel' => $requested_model,
            'periodDays' => $days,
        );
}

$dashboard_payload['defaultModel'] = $requested_model;
$dashboard_payload['periodDays'] = $days;
$dashboard_payload['usdToEur'] = isset($cost_settings['usd_to_eur']) ? (float) $cost_settings['usd_to_eur'] : 0.92;
$dashboard_payload['lazyLoad'] = false;
$dashboard_payload['ajaxUrl'] = admin_url('admin-ajax.php');
$dashboard_payload['ajaxNonce'] = wp_create_nonce('cbia_usage_overview');
$dashboard_payload['i18n'] = array(
    'loadingData' => __('Loading real usage data...', 'cbiastudio-blogflow-ai'),
    'loadingHint' => __('Charts and table will fill in automatically in a few seconds.', 'cbiastudio-blogflow-ai'),
    'loadingLogs' => __('Loading logs...', 'cbiastudio-blogflow-ai'),
    'loadingDetail' => __('Preparing detail view...', 'cbiastudio-blogflow-ai'),
    'loadErrorMeta' => __('Could not load usage data right now.', 'cbiastudio-blogflow-ai'),
    'loadErrorRow' => __('Could not load usage data.', 'cbiastudio-blogflow-ai'),
    'loadErrorDetail' => __('Could not load usage data.', 'cbiastudio-blogflow-ai'),
);
?>
<div class="cbia-usage-page">
    <div class="cbia-usage-header">
        <div>
            <h2><?php echo esc_html__('Usage', 'cbiastudio-blogflow-ai'); ?></h2>
            <p class="description">
                <?php
                $cbia_usage_header_desc = $usage_advanced_enabled
                    ? __('Unified view of real usage and cost settings, with charts, per-call detail and economic KPIs.', 'cbiastudio-blogflow-ai')
                    : __('Base mode shows essential usage activity. Advanced analytics and cost intelligence are available in Pro.', 'cbiastudio-blogflow-ai');
                echo esc_html($cbia_usage_header_desc);
                ?>
            </p>
        </div>
        <div class="cbia-usage-header-actions">
            <a class="button <?php echo $usage_section === 'overview' ? 'button-primary' : ''; ?>" href="<?php echo esc_url($overview_url); ?>">Overview</a>
            <?php if ($costs_advanced_enabled) : ?>
                <a class="button <?php echo $usage_section === 'costs' ? 'button-primary' : ''; ?>" href="<?php echo esc_url($costs_url); ?>"><?php echo esc_html__('Settings', 'cbiastudio-blogflow-ai'); ?></a>
            <?php else : ?>
                <button type="button" class="button" disabled aria-disabled="true"><?php echo esc_html__('Cost settings (Pro)', 'cbiastudio-blogflow-ai'); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($usage_section === 'overview') : ?>
        <?php if (!$usage_advanced_enabled) : ?>
            <section class="cbia-usage-pro-cta-card">
                <div class="cbia-usage-pro-cta-head">
                    <h3><?php echo esc_html__('Advanced Usage in Pro', 'cbiastudio-blogflow-ai'); ?></h3>
                    <span class="cbia-badge-pro">PRO</span>
                </div>
                <p>
                    <?php echo esc_html__('Usage in base mode focuses on essential activity: calls, posts, users and operational events.', 'cbiastudio-blogflow-ai'); ?>
                    <?php echo esc_html__('Upgrade to Pro to unlock advanced analytics, full event detail and real cost intelligence.', 'cbiastudio-blogflow-ai'); ?>
                </p>
                <div class="cbia-usage-pro-cta-grid">
                    <div class="cbia-usage-pro-pill"><?php echo esc_html__('Advanced usage charts', 'cbiastudio-blogflow-ai'); ?></div>
                    <div class="cbia-usage-pro-pill"><?php echo esc_html__('Per-call deep detail', 'cbiastudio-blogflow-ai'); ?></div>
                    <div class="cbia-usage-pro-pill"><?php echo esc_html__('Cost tracking and KPIs', 'cbiastudio-blogflow-ai'); ?></div>
                </div>
                <div class="cbia-usage-pro-cta-actions">
                    <a class="button button-primary cbia-pro-upgrade-link" href="<?php echo esc_url($pro_upgrade_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html__('Upgrade to Pro', 'cbiastudio-blogflow-ai'); ?>
                    </a>
                </div>
            </section>
        <?php endif; ?>
        <?php if (!$costs_advanced_enabled) : ?>
            <div class="notice notice-info inline">
                <p><?php echo esc_html__('Cost panels and cost settings are locked in base mode and available only in Pro.', 'cbiastudio-blogflow-ai'); ?></p>
            </div>
        <?php endif; ?>
        <div class="cbia-usage-current-context">
            <div class="cbia-usage-context-pill">
                <img src="<?php echo esc_url($provider_logo); ?>" alt="<?php echo esc_attr($provider_label); ?>" />
                <span><strong><?php echo esc_html($provider_label); ?></strong> <?php echo esc_html__('active', 'cbiastudio-blogflow-ai'); ?></span>
            </div>
            <div class="cbia-usage-context-pill">
                <span><?php echo esc_html__('Current model', 'cbiastudio-blogflow-ai'); ?></span>
                <code><?php echo esc_html($current_model); ?></code>
            </div>
            <div class="cbia-usage-context-pill">
                <span><?php echo esc_html__('Period', 'cbiastudio-blogflow-ai'); ?></span>
                <strong><?php echo esc_html((int) $days); ?> <?php echo esc_html__('days', 'cbiastudio-blogflow-ai'); ?></strong>
            </div>
        </div>

        <div
            id="cbia-usage-dashboard"
            class="cbia-usage-dashboard<?php echo !empty($dashboard_payload['lazyLoad']) ? ' is-loading' : ''; ?>"
            data-export-url="<?php echo esc_url($export_url); ?>"
            aria-busy="<?php echo !empty($dashboard_payload['lazyLoad']) ? 'true' : 'false'; ?>"
        >
            <script type="application/json" id="cbia-usage-data"><?php echo wp_json_encode($dashboard_payload); ?></script>
            <div class="cbia-usage-loading-banner" id="cbia-usage-loading-banner" <?php echo !empty($dashboard_payload['lazyLoad']) ? '' : 'hidden'; ?>>
                <span class="spinner is-active" aria-hidden="true"></span>
                <div class="cbia-usage-loading-copy">
                    <strong id="cbia-usage-loading-title"><?php echo esc_html__('Loading real usage data...', 'cbiastudio-blogflow-ai'); ?></strong>
                    <span id="cbia-usage-loading-hint"><?php echo esc_html__('Charts and table will fill in automatically in a few seconds.', 'cbiastudio-blogflow-ai'); ?></span>
                </div>
            </div>

            <div class="cbia-usage-kpis">
                <div class="cbia-usage-kpi cbia-usage-kpi-calls">
                    <div class="cbia-usage-kpi-icon"><span class="dashicons dashicons-chart-line"></span></div>
                    <div class="cbia-usage-kpi-copy">
                        <span class="cbia-usage-kpi-label"><?php echo esc_html__('Calls', 'cbiastudio-blogflow-ai'); ?></span>
                        <strong id="cbia-usage-kpi-calls" class="cbia-usage-kpi-value">0</strong>
                    </div>
                </div>
                <div class="cbia-usage-kpi cbia-usage-kpi-posts">
                    <div class="cbia-usage-kpi-icon"><span class="dashicons dashicons-edit-page"></span></div>
                    <div class="cbia-usage-kpi-copy">
                        <span class="cbia-usage-kpi-label"><?php echo esc_html__('Posts', 'cbiastudio-blogflow-ai'); ?></span>
                        <strong id="cbia-usage-kpi-posts" class="cbia-usage-kpi-value">0</strong>
                    </div>
                </div>
                <div class="cbia-usage-kpi cbia-usage-kpi-users">
                    <div class="cbia-usage-kpi-icon"><span class="dashicons dashicons-admin-users"></span></div>
                    <div class="cbia-usage-kpi-copy">
                        <span class="cbia-usage-kpi-label"><?php echo esc_html__('Unique users', 'cbiastudio-blogflow-ai'); ?></span>
                        <strong id="cbia-usage-kpi-users" class="cbia-usage-kpi-value">0</strong>
                    </div>
                </div>
                <div class="cbia-usage-kpi cbia-usage-kpi-avg">
                    <div class="cbia-usage-kpi-icon"><span class="dashicons dashicons-clock"></span></div>
                    <div class="cbia-usage-kpi-copy">
                        <span class="cbia-usage-kpi-label"><?php echo esc_html__('Avg tokens / call', 'cbiastudio-blogflow-ai'); ?></span>
                        <strong id="cbia-usage-kpi-avg" class="cbia-usage-kpi-value">0</strong>
                    </div>
                </div>
                <?php if ($costs_advanced_enabled) : ?>
                <div class="cbia-usage-kpi cbia-usage-kpi-cost-total">
                    <div class="cbia-usage-kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="cbia-usage-kpi-copy">
                        <span class="cbia-usage-kpi-label"><?php echo esc_html__('Total cost', 'cbiastudio-blogflow-ai'); ?></span>
                        <strong id="cbia-usage-kpi-cost-total">$0.00</strong>
                    </div>
                </div>
                <div class="cbia-usage-kpi cbia-usage-kpi-cost-blog">
                    <div class="cbia-usage-kpi-icon"><span class="dashicons dashicons-chart-pie"></span></div>
                    <div class="cbia-usage-kpi-copy">
                        <span class="cbia-usage-kpi-label"><?php echo esc_html__('Avg cost / blog', 'cbiastudio-blogflow-ai'); ?></span>
                        <strong id="cbia-usage-kpi-cost-blog">$0.00</strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="cbia-usage-chart-note">
                <?php echo esc_html($costs_advanced_enabled
                    ? __('Charts are built from usage events stored by the plugin on each post. Each panel measures a different thing: calls, split by type or cost.', 'cbiastudio-blogflow-ai')
                    : __('Charts are built from usage events stored by the plugin on each post. This view focuses on calls and event activity in base mode.', 'cbiastudio-blogflow-ai')); ?>
            </div>

            <div class="cbia-usage-chart-grid">
                <section class="cbia-usage-panel -activity">
                    <div class="cbia-usage-panel-head">
                        <div class="cbia-usage-panel-title">
                            <h3><?php echo esc_html__('Calls per day', 'cbiastudio-blogflow-ai'); ?></h3>
                            <p id="cbia-usage-activity-hint"><?php echo esc_html__('Y axis: number of AI events recorded per day in the current filter.', 'cbiastudio-blogflow-ai'); ?></p>
                        </div>
                    </div>
                    <div class="cbia-usage-chart-wrap">
                        <div class="cbia-usage-chart-skeleton" aria-hidden="true">
                            <span class="is-low"></span>
                            <span class="is-mid"></span>
                            <span class="is-high"></span>
                            <span class="is-mid"></span>
                            <span class="is-low"></span>
                        </div>
                        <canvas id="cbia-usage-activity-chart" height="240"></canvas>
                        <div class="cbia-usage-empty" id="cbia-usage-activity-empty" hidden><?php echo esc_html__('No data available.', 'cbiastudio-blogflow-ai'); ?></div>
                    </div>
                </section>

                <section class="cbia-usage-panel -type">
                    <div class="cbia-usage-panel-head">
                        <div class="cbia-usage-panel-title">
                            <h3><?php echo esc_html__('Events by type', 'cbiastudio-blogflow-ai'); ?></h3>
                            <p id="cbia-usage-type-hint"><?php echo esc_html__('Y axis: number of text and image calls in the current filter. Period: loading...', 'cbiastudio-blogflow-ai'); ?></p>
                        </div>
                        <div class="cbia-usage-legend">
                            <span class="cbia-usage-legend-item is-text"><i></i><?php echo esc_html__('Text', 'cbiastudio-blogflow-ai'); ?></span>
                            <span class="cbia-usage-legend-item is-image"><i></i><?php echo esc_html__('Image', 'cbiastudio-blogflow-ai'); ?></span>
                        </div>
                    </div>
                    <div class="cbia-usage-chart-wrap">
                        <div class="cbia-usage-chart-skeleton" aria-hidden="true">
                            <span class="is-low"></span>
                            <span class="is-mid"></span>
                            <span class="is-high"></span>
                            <span class="is-mid"></span>
                            <span class="is-low"></span>
                        </div>
                        <canvas id="cbia-usage-type-chart" height="240"></canvas>
                        <div class="cbia-usage-empty" id="cbia-usage-type-empty" hidden><?php echo esc_html__('No data available.', 'cbiastudio-blogflow-ai'); ?></div>
                    </div>
                </section>

                <?php if ($costs_advanced_enabled) : ?>
                <section class="cbia-usage-panel -monthly">
                    <div class="cbia-usage-panel-head">
                        <div class="cbia-usage-panel-title">
                            <h3><?php echo esc_html__('Real cost by month', 'cbiastudio-blogflow-ai'); ?></h3>
                            <p id="cbia-usage-monthly-hint"><?php echo esc_html__('Y axis: dollars (USD). Full Jan-Dec timeline of the current year, including months with zero data.', 'cbiastudio-blogflow-ai'); ?></p>
                        </div>
                        <div class="cbia-usage-legend">
                            <span class="cbia-usage-legend-item is-text"><i></i><?php echo esc_html__('Text', 'cbiastudio-blogflow-ai'); ?></span>
                            <span class="cbia-usage-legend-item is-image"><i></i><?php echo esc_html__('Image', 'cbiastudio-blogflow-ai'); ?></span>
                        </div>
                    </div>
                    <div class="cbia-usage-chart-wrap">
                        <div class="cbia-usage-chart-skeleton" aria-hidden="true">
                            <span class="is-low"></span>
                            <span class="is-mid"></span>
                            <span class="is-high"></span>
                            <span class="is-mid"></span>
                            <span class="is-low"></span>
                        </div>
                        <canvas id="cbia-usage-monthly-chart" height="240"></canvas>
                        <div class="cbia-usage-empty" id="cbia-usage-monthly-empty" hidden><?php echo esc_html__('No monthly history available.', 'cbiastudio-blogflow-ai'); ?></div>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <div class="cbia-usage-filters">
                <form method="get" action="" id="cbia-usage-period-form" class="cbia-usage-filter-inline">
                    <input type="hidden" name="page" value="cbia" />
                    <input type="hidden" name="tab" value="usage" />
                    <input type="hidden" name="usage_section" value="overview" />
                    <input type="hidden" name="usage_model" id="cbia-usage-model-hidden" value="<?php echo esc_attr($requested_model); ?>" />
                    <select id="cbia-usage-days" name="usage_days" class="abb-select" aria-label="<?php echo esc_attr__('Period', 'cbiastudio-blogflow-ai'); ?>">
                        <?php foreach ($allowed_days as $period_days) : ?>
                            <?php // translators: %d is the selected period length in days. ?>
                            <option value="<?php echo esc_attr($period_days); ?>" <?php selected($days, $period_days); ?>><?php echo esc_html(sprintf(__('Last %d days', 'cbiastudio-blogflow-ai'), $period_days)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <select id="cbia-usage-model-filter" class="abb-select" aria-label="<?php echo esc_attr__('Model', 'cbiastudio-blogflow-ai'); ?>">
                    <option value=""><?php echo esc_html__('All models', 'cbiastudio-blogflow-ai'); ?></option>
                    <?php foreach ($model_options as $model_name) : ?>
                        <option value="<?php echo esc_attr($model_name); ?>" <?php selected($requested_model, $model_name); ?>><?php echo esc_html($model_name); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="cbia-usage-type-filter" class="abb-select" aria-label="<?php echo esc_attr__('Type', 'cbiastudio-blogflow-ai'); ?>">
                    <option value=""><?php echo esc_html__('All types', 'cbiastudio-blogflow-ai'); ?></option>
                    <option value="text"><?php echo esc_html__('Text', 'cbiastudio-blogflow-ai'); ?></option>
                    <option value="image"><?php echo esc_html__('Image', 'cbiastudio-blogflow-ai'); ?></option>
                    <option value="seo">SEO</option>
                </select>

                <input
                    type="search"
                    id="cbia-usage-search"
                    class="regular-text"
                    placeholder="<?php echo esc_attr__('Search logs', 'cbiastudio-blogflow-ai'); ?>"
                    aria-label="<?php echo esc_attr__('Search logs', 'cbiastudio-blogflow-ai'); ?>"
                />

                <a class="button button-secondary" id="cbia-usage-export" href="<?php echo esc_url($export_url); ?>"><?php echo esc_html__('Export CSV', 'cbiastudio-blogflow-ai'); ?></a>
            </div>

            <div class="cbia-usage-main-grid">
                <section class="cbia-usage-panel cbia-usage-table-panel">
                    <div class="cbia-usage-panel-head">
                        <h3><?php echo esc_html__('Usage events', 'cbiastudio-blogflow-ai'); ?></h3>
                    </div>
                    <p class="description" id="cbia-usage-table-meta">
                        <?php echo esc_html__('Loading the latest events sample for faster access...', 'cbiastudio-blogflow-ai'); ?>
                    </p>
                    <div class="cbia-usage-table-wrap">
                        <div class="cbia-usage-table-skeleton" id="cbia-usage-table-skeleton" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <table class="widefat striped cbia-usage-events-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Date', 'cbiastudio-blogflow-ai'); ?></th>
                                    <th><?php echo esc_html__('User', 'cbiastudio-blogflow-ai'); ?></th>
                                    <th>Source</th>
                                    <th><?php echo esc_html__('Type', 'cbiastudio-blogflow-ai'); ?></th>
                                    <th><?php echo esc_html__('Tokens', 'cbiastudio-blogflow-ai'); ?></th>
                                    <th><?php echo esc_html__('Cost', 'cbiastudio-blogflow-ai'); ?></th>
                                    <th><?php echo esc_html__('Model', 'cbiastudio-blogflow-ai'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="cbia-usage-table-body">
                                <tr>
                                    <td colspan="7" class="cbia-usage-table-placeholder"><?php echo esc_html__('Loading logs...', 'cbiastudio-blogflow-ai'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="cbia-usage-panel cbia-usage-detail-panel" id="cbia-usage-detail">
                    <div class="cbia-usage-detail-skeleton" id="cbia-usage-detail-skeleton" aria-hidden="true">
                        <span class="is-title"></span>
                        <span></span>
                        <span></span>
                        <span class="is-wide"></span>
                        <span></span>
                        <span class="is-wide"></span>
                    </div>
                    <div class="cbia-usage-detail-empty"><?php echo esc_html__('Select an event to see the detail.', 'cbiastudio-blogflow-ai'); ?></div>
                </aside>
            </div>
        </div>
    <?php else : ?>
        <?php
        $cbia_costs_embedded = true;
        $costs_view = CBIA_INCLUDES_DIR . 'admin/views/costs.php';
        if (file_exists($costs_view)) {
            include $costs_view;
        } else {
            echo '<p>' . esc_html__('The costs section could not be loaded.', 'cbiastudio-blogflow-ai') . '</p>';
        }
        ?>
    <?php endif; ?>
</div>
