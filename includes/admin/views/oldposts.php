<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Oldposts tab view (extracted from legacy cbia-oldposts.php)

if (!current_user_can('manage_options')) return;

$service = isset($cbia_oldposts_service) ? $cbia_oldposts_service : null;
$settings = $service && method_exists($service, 'get_settings')
    ? $service->get_settings()
    : cbia_oldposts_get_settings();
$internal_images_enabled = function_exists('cbia_cap_enabled') ? cbia_cap_enabled('internal_images') : true;
$cbia_global_settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
$main_images_total = isset($cbia_global_settings['images_limit']) ? (int)$cbia_global_settings['images_limit'] : 3;
$main_images_internal = max(1, min(3, $main_images_total - 1));

// Defaults (presets)
$defaults = array(
    'batch_size'         => 20,
    'scope'              => 'all',

    'filter_mode'        => 'all',
    'older_than_days'    => 180,
    'date_from'          => '',
    'date_to'            => '',

    'images_limit'       => $main_images_internal,
    'run_post_length_variant' => 'medium',
    'run_text_provider'  => 'openai',
    'run_text_model'     => '',
    'run_image_provider' => 'openai',
    'run_image_model'    => '',
    'post_ids'           => '',
    'category_id'        => 0,
    'author_id'          => 0,
    'dry_run'            => 0,

    // BÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡sico recomendado (lo que dices que casi siempre usarÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s)
    'do_note'            => 0,
    'force_note'         => 0,
    'reprocess_text'     => 0,
    'reprocess_images'   => 0,
    'reprocess_meta'     => 0,

    'do_yoast_metadesc'  => 0,
    'do_yoast_focuskw'   => 0,
    'do_yoast_title'     => 0,
    'force_yoast'        => 0,

    'do_yoast_reindex'   => 0,

    'do_title'           => 0,
    'force_title'        => 0,

    'do_content'         => 0,
    'force_content'      => 0,
    'do_content_no_images'    => 0,
    'force_content_no_images' => 0,

    'do_images_reset'    => 0,
    'force_images_reset' => 0,
    'clear_featured'     => 0,
    'do_images_content_only'    => 0,
    'force_images_content_only' => 0,
    'do_featured_only'          => 0,
    'force_featured_only'       => 0,
    'featured_remove_old'       => 0,

    'do_categories'      => 0,
    'force_categories'   => 0,

    'do_tags'            => 0,
    'force_tags'         => 0,
);
$settings = array_merge($defaults, is_array($settings) ? $settings : array());
$settings['images_limit'] = max(1, min(3, (int)($settings['images_limit'] ?? $main_images_internal)));
if (!$internal_images_enabled) {
    $settings['images_limit'] = 1;
    $settings['do_images_reset'] = 0;
    $settings['do_images_content_only'] = 0;
}

// MigraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n suave desde v2 si existÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­an keys antiguas
if (isset($settings['do_yoast_metas']) && !isset($settings['do_yoast_metadesc'])) {
    $val = !empty($settings['do_yoast_metas']) ? 1 : 0;
    $settings['do_yoast_metadesc'] = $val;
    $settings['do_yoast_focuskw']  = $val;
}
if (isset($settings['force_yoast_metas']) && !isset($settings['force_yoast'])) {
    $settings['force_yoast'] = !empty($settings['force_yoast_metas']) ? 1 : 0;
}
if (!isset($settings['reprocess_text'])) {
    $settings['reprocess_text'] = (!empty($settings['force_content']) || !empty($settings['force_content_no_images']) || !empty($settings['force_title'])) ? 1 : 0;
}
if (!isset($settings['reprocess_images'])) {
    $settings['reprocess_images'] = (!empty($settings['force_images_reset']) || !empty($settings['force_images_content_only']) || !empty($settings['force_featured_only'])) ? 1 : 0;
}
if (!isset($settings['reprocess_meta'])) {
    $settings['reprocess_meta'] = (!empty($settings['force_note']) || !empty($settings['force_yoast']) || !empty($settings['force_categories']) || !empty($settings['force_tags'])) ? 1 : 0;
}

$legacy_auto_on = array(
    'do_note',
    'do_yoast_metadesc',
    'do_yoast_focuskw',
    'do_yoast_reindex',
    'do_content',
    'do_images_reset',
    'do_categories',
    'do_tags',
);
$legacy_auto_off = array(
    'do_yoast_title',
    'do_title',
    'do_content_no_images',
    'do_images_content_only',
    'do_featured_only',
    'dry_run',
    'reprocess_text',
    'reprocess_images',
    'reprocess_meta',
    'clear_featured',
    'featured_remove_old',
);
$should_reset_legacy_actions = true;
foreach ($legacy_auto_on as $key) {
    if (empty($settings[$key])) {
        $should_reset_legacy_actions = false;
        break;
    }
}
if ($should_reset_legacy_actions) {
    foreach ($legacy_auto_off as $key) {
        if (!empty($settings[$key])) {
            $should_reset_legacy_actions = false;
            break;
        }
    }
}
if ($should_reset_legacy_actions) {
    foreach (array_merge($legacy_auto_on, $legacy_auto_off) as $key) {
        $settings[$key] = 0;
    }
}

// Handle POST

if ($service && method_exists($service, 'handle_post')) {
    $settings = $service->handle_post($settings);
} elseif (function_exists('cbia_oldposts_handle_post')) {
    $settings = cbia_oldposts_handle_post($settings);
}


$log = $service && method_exists($service, 'get_log')
    ? $service->get_log()
    : cbia_oldposts_get_log();

// Selector visual: dataset base de posts
$selected_picker_ids = array();
if (!empty($settings['post_ids'])) {
    if (function_exists('cbia_oldposts_parse_ids_csv')) {
        $selected_picker_ids = cbia_oldposts_parse_ids_csv((string)$settings['post_ids']);
    } else {
        $raw_ids = explode(',', (string)$settings['post_ids']);
        foreach ($raw_ids as $raw_id) {
            $id = absint(trim((string)$raw_id));
            if ($id > 0) $selected_picker_ids[] = $id;
        }
        $selected_picker_ids = array_values(array_unique($selected_picker_ids));
    }
}

$picker_limit = max(1, min(200, (int)($settings['batch_size'] ?? 20)));
$picker_query_args = function_exists('cbia_oldposts_build_query_args')
    ? cbia_oldposts_build_query_args(
        $picker_limit,
        'all',
        (string)($settings['filter_mode'] ?? 'all'),
        (int)($settings['older_than_days'] ?? 180),
        (string)($settings['date_from'] ?? ''),
        (string)($settings['date_to'] ?? ''),
        array(),
        (int)($settings['category_id'] ?? 0),
        (int)($settings['author_id'] ?? 0),
        true
    )
    : array(
        'post_type'      => 'post',
        'post_status'    => array('publish', 'future', 'draft', 'pending'),
        'posts_per_page' => $picker_limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    );
$picker_query_args['fields'] = 'ids';
$picker_query_args['no_found_rows'] = true;
$picker_query = new WP_Query($picker_query_args);
$picker_rows = array();
$picker_category_options = array();
$picker_author_options = array();
if (!empty($picker_query->posts) && is_array($picker_query->posts)) {
    foreach ($picker_query->posts as $picker_post_id) {
        $picker_post_id = absint($picker_post_id);
        if ($picker_post_id <= 0) continue;

        $post_obj = get_post($picker_post_id);
        if (!$post_obj) continue;

        $picker_title = wp_specialchars_decode((string)get_the_title($picker_post_id), ENT_QUOTES);
        $picker_title = html_entity_decode($picker_title, ENT_QUOTES, 'UTF-8');
        if ($picker_title === '') $picker_title = __('(Untitled)', 'cbiastudio-blogflow-ai');

        $picker_status = get_post_status($picker_post_id);
        $picker_thumb = get_the_post_thumbnail_url($picker_post_id, 'medium');
        $picker_lang = (string)get_post_meta($picker_post_id, '_cbia_post_language', true);
        if ($picker_lang === '') {
            $picker_lang = (string)get_post_meta($picker_post_id, 'post_language', true);
        }
        $picker_lang = strtolower(trim($picker_lang));
        if ($picker_lang === '') $picker_lang = 'auto';
        $picker_seo_raw = get_post_meta($picker_post_id, '_yoast_wpseo_linkdex', true);
        $picker_readability_raw = get_post_meta($picker_post_id, '_yoast_wpseo_content_score', true);
        $picker_seo_score = ($picker_seo_raw !== '' && is_numeric($picker_seo_raw)) ? max(0, min(100, (int)$picker_seo_raw)) : null;
        $picker_readability_score = ($picker_readability_raw !== '' && is_numeric($picker_readability_raw)) ? max(0, min(100, (int)$picker_readability_raw)) : null;

        $content = (string)$post_obj->post_content;
        $is_elementor = false;
        if (function_exists('cbia_oldposts_is_elementor_post')) {
            $is_elementor = cbia_oldposts_is_elementor_post($picker_post_id);
        } else {
            $edit_mode = (string)get_post_meta($picker_post_id, '_elementor_edit_mode', true);
            $elementor_data = get_post_meta($picker_post_id, '_elementor_data', true);
            $is_elementor = ($edit_mode !== '') || (is_string($elementor_data) && trim($elementor_data) !== '') || (is_array($elementor_data) && !empty($elementor_data));
        }
        $internal_marker_count = 0;
        if (preg_match_all('/\[(?:IMAGE|IMAGEN)(?:_(?:PENDING|PENDIENTE))?\s*:\s*[^\]]+\]/i', $content, $m_markers)) {
            $internal_marker_count = count((array)$m_markers[0]);
        }
        $internal_managed_img_count = 0;
        if (preg_match_all('/<img\b[^>]*data-cbia-attach=("|\')\d+\1[^>]*>/i', $content, $m_internal_imgs)) {
            $internal_managed_img_count = count((array)$m_internal_imgs[0]);
        }
        // Source of truth for card count: current post content only.
        $internal_image_count = max($internal_marker_count, $internal_managed_img_count);
        $has_internal_images = $internal_image_count > 0;
        $picker_author_id = absint((int)$post_obj->post_author);
        $picker_author_label = $picker_author_id > 0 ? (string)get_the_author_meta('display_name', $picker_author_id) : '';
        if ($picker_author_id > 0 && $picker_author_label !== '') {
            $picker_author_options[$picker_author_id] = $picker_author_label;
        }
        $picker_category_ids = wp_get_post_categories($picker_post_id, array('fields' => 'ids'));
        $picker_category_ids = is_array($picker_category_ids) ? array_values(array_filter(array_map('absint', $picker_category_ids))) : array();
        foreach ($picker_category_ids as $cat_id) {
            if ($cat_id <= 0) continue;
            $cat_obj = get_category($cat_id);
            if ($cat_obj && !is_wp_error($cat_obj)) {
                $picker_category_options[$cat_id] = (string)$cat_obj->name;
            }
        }

        $picker_rows[] = array(
            'id'           => $picker_post_id,
            'title'        => $picker_title,
            'status'       => (string)$picker_status,
            'date'         => get_the_date('Y-m-d', $picker_post_id),
            'thumb'        => $picker_thumb ? (string)$picker_thumb : '',
            'lang'         => $picker_lang,
            'seo_score'    => $picker_seo_score,
            'readability_score' => $picker_readability_score,
            'author_id'    => $picker_author_id,
            'author_label' => $picker_author_label,
            'category_ids' => $picker_category_ids,
            'has_featured' => has_post_thumbnail($picker_post_id),
            'has_internal' => $has_internal_images,
            'internal_count'=> $internal_image_count,
            'is_elementor' => $is_elementor,
            'selected'     => in_array($picker_post_id, $selected_picker_ids, true),
        );
    }
}
if (!empty($picker_category_options)) {
    asort($picker_category_options, SORT_NATURAL | SORT_FLAG_CASE);
}
if (!empty($picker_author_options)) {
    asort($picker_author_options, SORT_NATURAL | SORT_FLAG_CASE);
}

// Runtime overrides (execution-only profile)
$cbia_cfg = $cbia_global_settings;
$provider_defaults = function_exists('cbia_providers_defaults') ? cbia_providers_defaults() : array();
$provider_rows = isset($provider_defaults['providers']) && is_array($provider_defaults['providers'])
    ? $provider_defaults['providers']
    : array(
        'openai'   => array(),
        'google'   => array(),
        'deepseek' => array(),
    );
$provider_labels = array();
foreach ($provider_rows as $pkey => $pcfg) {
    $provider_labels[$pkey] = ucfirst((string)$pkey);
}
if (!isset($provider_labels['openai'])) $provider_labels['openai'] = 'OpenAI';
if (!isset($provider_labels['google'])) $provider_labels['google'] = 'Google';
if (!isset($provider_labels['deepseek'])) $provider_labels['deepseek'] = 'DeepSeek';
$image_provider_labels = array();
foreach ($provider_labels as $pkey => $plabel) {
    if (function_exists('cbia_providers_supports_image') && !cbia_providers_supports_image((string)$pkey)) {
        continue;
    }
    $image_provider_labels[$pkey] = $plabel;
}

// Keep old-posts internal image default in sync with Settings tab selector
// (Settings stores total images including featured; old-posts input stores internals only).
if (isset($cbia_cfg['images_limit'])) {
    $settings['images_limit'] = max(1, min(3, ((int)$cbia_cfg['images_limit']) - 1));
}

$tpl_default = sanitize_key((string)($settings['run_post_length_variant'] ?? ($cbia_cfg['post_length_variant'] ?? 'medium')));
if (!in_array($tpl_default, array('short','medium','long'), true)) $tpl_default = 'medium';

$text_provider_default = sanitize_key((string)($cbia_cfg['text_provider'] ?? ($settings['run_text_provider'] ?? 'openai')));
if ($text_provider_default === '' || !isset($provider_labels[$text_provider_default])) $text_provider_default = 'openai';

$image_provider_default = sanitize_key((string)($cbia_cfg['image_provider'] ?? ($settings['run_image_provider'] ?? 'openai')));
if ($image_provider_default === '' || !isset($image_provider_labels[$image_provider_default])) $image_provider_default = 'openai';

$text_model_lists = array();
$image_model_lists = array();
foreach ($provider_labels as $pkey => $plabel) {
    $txt = function_exists('cbia_providers_get_text_model_list') ? cbia_providers_get_text_model_list((string)$pkey) : array();
    $img = function_exists('cbia_providers_get_image_model_list') ? cbia_providers_get_image_model_list((string)$pkey) : array();
    $txt = is_array($txt) ? array_values(array_filter(array_map('strval', $txt))) : array();
    $img = is_array($img) ? array_values(array_filter(array_map('strval', $img))) : array();

    if (empty($txt) && function_exists('cbia_providers_get_recommended_text_model')) {
        $txt = array((string)cbia_providers_get_recommended_text_model((string)$pkey));
    }
    if (empty($img) && function_exists('cbia_providers_get_recommended_image_model')) {
        $recommended_image_model = (string)cbia_providers_get_recommended_image_model((string)$pkey);
        if ($recommended_image_model !== '') {
            $img = array($recommended_image_model);
        }
    }
    $text_model_lists[$pkey] = array_values(array_unique(array_filter($txt)));
    $image_model_lists[$pkey] = array_values(array_unique(array_filter($img)));
}

$text_model_default = (string)($cbia_cfg['text_model'] ?? '');
if ($text_model_default === '') {
    $text_model_default = (string)($settings['run_text_model'] ?? '');
}
if ($text_model_default === '' && function_exists('cbia_get_text_model_for_provider')) {
    $text_model_default = (string)cbia_get_text_model_for_provider((string)$text_provider_default, '');
}
if ($text_model_default === '' && !empty($text_model_lists[$text_provider_default][0])) {
    $text_model_default = (string)$text_model_lists[$text_provider_default][0];
}

$image_model_default = (string)($cbia_cfg['image_model'] ?? '');
if ($image_model_default === '') {
    $image_model_default = (string)($settings['run_image_model'] ?? '');
}
if ($image_model_default === '' && function_exists('cbia_get_image_model_for_provider')) {
    $image_model_default = (string)cbia_get_image_model_for_provider((string)$image_provider_default, '');
}
if ($image_model_default === '' && !empty($image_model_lists[$image_provider_default][0])) {
    $image_model_default = (string)$image_model_lists[$image_provider_default][0];
}

$text_model_lists_json = (string) wp_json_encode($text_model_lists);
$image_model_lists_json = (string) wp_json_encode($image_model_lists);
$provider_labels_json = (string) wp_json_encode($provider_labels);
$cbia_main_settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
$cbia_provider_settings = function_exists('cbia_providers_get_settings') ? cbia_providers_get_settings() : array();
$provider_key_state = array();
foreach (array_keys($provider_labels) as $pkey) {
    $main_key = (string)($cbia_main_settings[$pkey . '_api_key'] ?? '');
    $providers_key = '';
    if (!empty($cbia_provider_settings['providers'][$pkey]['api_key'])) {
        $providers_key = (string)$cbia_provider_settings['providers'][$pkey]['api_key'];
    }
    // OJO: aqui no usamos fallback legacy global (api_key unico),
    // para que la UI detecte correctamente falta de clave por proveedor.
    $provider_key_state[$pkey] = ($main_key !== '' || $providers_key !== '');
}
$provider_key_state_json = (string) wp_json_encode($provider_key_state);
$active_run_state = function_exists('cbia_oldposts_public_run_state')
    ? cbia_oldposts_public_run_state(function_exists('cbia_oldposts_get_active_run_state') ? cbia_oldposts_get_active_run_state() : array())
    : array();
$active_run_state_json = (string) wp_json_encode($active_run_state);
$oldposts_js_i18n = array(
    'activeSuffix' => __('active', 'cbiastudio-blogflow-ai'),
    'noModels' => __('(no models)', 'cbiastudio-blogflow-ai'),
    'selectedCount' => __('selected', 'cbiastudio-blogflow-ai'),
    'textProviderLabel' => __('text', 'cbiastudio-blogflow-ai'),
    'imageProviderLabel' => __('image', 'cbiastudio-blogflow-ai'),
    'missingApiFor' => __('Missing API key for:', 'cbiastudio-blogflow-ai'),
    'runBlockedUntilConfigured' => __('Run batch is blocked until it is configured in Settings.', 'cbiastudio-blogflow-ai'),
    'providerWithoutKey' => __('There is a provider without an API key:', 'cbiastudio-blogflow-ai'),
    'providerWarningNonBlocking' => __('It does not block execution unless you enable actions that need it.', 'cbiastudio-blogflow-ai'),
    'summaryLabel' => __('Summary:', 'cbiastudio-blogflow-ai'),
    'allPosts' => __('all posts', 'cbiastudio-blogflow-ai'),
    'idsPrefix' => __('IDs:', 'cbiastudio-blogflow-ai'),
    'categoryPrefix' => __('category #', 'cbiastudio-blogflow-ai'),
    'authorPrefix' => __('author #', 'cbiastudio-blogflow-ai'),
    'dryRunTag' => __('DRY RUN', 'cbiastudio-blogflow-ai'),
    'templatePrefix' => __('template=', 'cbiastudio-blogflow-ai'),
    'textPrefix' => __('text=', 'cbiastudio-blogflow-ai'),
    'imagePrefix' => __('image=', 'cbiastudio-blogflow-ai'),
    'actionNoAi' => __('no AI actions', 'cbiastudio-blogflow-ai'),
    'actionContent' => __('AI content', 'cbiastudio-blogflow-ai'),
    'actionContentNoImages' => __('AI content (no images)', 'cbiastudio-blogflow-ai'),
    'actionTitle' => __('AI title', 'cbiastudio-blogflow-ai'),
    'actionImagesReset' => __('image reset', 'cbiastudio-blogflow-ai'),
    'actionImagesContentOnly' => __('content images only', 'cbiastudio-blogflow-ai'),
    'actionFeaturedOnly' => __('featured image only', 'cbiastudio-blogflow-ai'),
    'confirmAiCredits' => __('AI actions that may consume credits will run. Continue?', 'cbiastudio-blogflow-ai'),
    'responseNotJson' => __('Non-JSON response from AJAX', 'cbiastudio-blogflow-ai'),
    'ajaxTimeout' => __('AJAX timeout', 'cbiastudio-blogflow-ai'),
    'preparingQueue' => __('Preparing queue...', 'cbiastudio-blogflow-ai'),
    'queueEmpty' => __('There are no posts matching the current conditions.', 'cbiastudio-blogflow-ai'),
    'queueCreated' => __('Background queue created. Processing will continue even if you leave this tab.', 'cbiastudio-blogflow-ai'),
    'batchRunning' => __('Background batch running. Processed {processed}/{total} | ok={ok} | skip={skipped} | fail={fail}', 'cbiastudio-blogflow-ai'),
    'batchQueued' => __('Background queue waiting to continue. Processed {processed}/{total} | ok={ok} | skip={skipped} | fail={fail}', 'cbiastudio-blogflow-ai'),
    'batchCompleted' => __('Batch completed. Processed={processed} | ok={ok} | skip={skipped} | fail={fail}', 'cbiastudio-blogflow-ai'),
    'batchStopped' => __('Batch stopped. Processed={processed} | ok={ok} | skip={skipped} | fail={fail}', 'cbiastudio-blogflow-ai'),
    'batchError' => __('Background batch error: {message}', 'cbiastudio-blogflow-ai'),
    'stopRequested' => __('Stop requested. The current chunk will finish and the queue will stop.', 'cbiastudio-blogflow-ai'),
    'runningElsewhere' => __('There is already a background batch running. This tab has been reattached to it.', 'cbiastudio-blogflow-ai'),
    'serverError' => __('Unknown error', 'cbiastudio-blogflow-ai'),
    'sortDescTitle' => __('Descending order (newest first)', 'cbiastudio-blogflow-ai'),
    'sortAscTitle' => __('Ascending order (oldest first)', 'cbiastudio-blogflow-ai'),
    'noFeaturedImage' => __('No featured image', 'cbiastudio-blogflow-ai'),
    'badgeFeatured' => __('featured', 'cbiastudio-blogflow-ai'),
    'badgeNoFeatured' => __('no featured', 'cbiastudio-blogflow-ai'),
    'badgeNoInternals' => __('no internals', 'cbiastudio-blogflow-ai'),
    'badgeInternalOne' => __('{count} internal', 'cbiastudio-blogflow-ai'),
    'badgeInternalMany' => __('{count} internals', 'cbiastudio-blogflow-ai'),
    'seoScore' => __('SEO score', 'cbiastudio-blogflow-ai'),
    'readabilityScore' => __('Readability score', 'cbiastudio-blogflow-ai'),
    'scoreUnavailable' => __('Not available', 'cbiastudio-blogflow-ai'),
);
$oldposts_js_i18n_json = (string) wp_json_encode($oldposts_js_i18n);

?>
<div class="wrap" style="padding-left:0;">
    <h2><?php echo esc_html__('Update older posts', 'cbiastudio-blogflow-ai'); ?></h2>

    <h3><?php echo esc_html__('Visual execution', 'cbiastudio-blogflow-ai'); ?></h3>
    <p class="description" style="max-width:1280px;"><?php echo esc_html__('Select posts in cards, configure template/models and run only what you need.', 'cbiastudio-blogflow-ai'); ?></p>

    <form method="post" action="" autocomplete="off">
        <input type="hidden" name="cbia_form" value="oldposts_actions" />
        <input type="hidden" name="run_custom_actions" value="1" />
        <?php wp_nonce_field('cbia_oldposts_actions_nonce'); ?>

        <div
            id="cbia-oldv2-shell"
            class="cbia-oldv2-shell"
            data-provider-labels="<?php echo esc_attr($provider_labels_json); ?>"
            data-text-model-lists="<?php echo esc_attr($text_model_lists_json); ?>"
            data-image-model-lists="<?php echo esc_attr($image_model_lists_json); ?>"
            data-provider-key-state="<?php echo esc_attr($provider_key_state_json); ?>"
            data-active-run-state="<?php echo esc_attr($active_run_state_json); ?>"
            data-js-i18n="<?php echo esc_attr($oldposts_js_i18n_json); ?>"
        >
            <div class="cbia-oldv2-main">
                <div class="cbia-oldv2-card">
                    <div class="cbia-oldv2-filterbar">
                        <div class="cbia-oldv2-filter-panel">
                            <div class="cbia-oldv2-filter-row -execution" style="margin-bottom:8px;">
                                <label class="cbia-oldv2-field"><?php echo esc_html__('Batch size', 'cbiastudio-blogflow-ai'); ?>
                                    <input type="number" name="run_batch_size" min="1" max="200" value="<?php echo esc_attr((int)$settings['batch_size']); ?>" class="cbia-oldv2-input" />
                                </label>
                                <label class="cbia-oldv2-field"><?php echo esc_html__('Internal images (content, max 3)', 'cbiastudio-blogflow-ai'); ?>
                                    <input type="number" name="run_images_limit" min="1" max="3" value="<?php echo esc_attr((int)$settings['images_limit']); ?>" class="cbia-oldv2-input" <?php disabled(!$internal_images_enabled); ?> />
                                </label>
                                <div class="cbia-oldv2-range-inline">
                                    <label><input type="radio" name="run_filter_mode" value="all" <?php checked($settings['filter_mode'], 'all'); ?> /> <?php echo esc_html__('No date filter', 'cbiastudio-blogflow-ai'); ?></label>
                                    <label><input type="radio" name="run_filter_mode" value="older" <?php checked($settings['filter_mode'], 'older'); ?> /> <?php echo esc_html__('Older than', 'cbiastudio-blogflow-ai'); ?></label>
                                    <div id="cbia_run_filter_older" style="<?php echo esc_attr($settings['filter_mode'] === 'older' ? '' : 'display:none;'); ?>">
                                        <input type="number" name="run_older_than_days" min="1" value="<?php echo esc_attr((int)$settings['older_than_days']); ?>" style="width:90px;" /> <?php echo esc_html__('days', 'cbiastudio-blogflow-ai'); ?>
                                    </div>
                                    <label><input type="radio" name="run_filter_mode" value="range" <?php checked($settings['filter_mode'], 'range'); ?> /> <?php echo esc_html__('Date range', 'cbiastudio-blogflow-ai'); ?></label>
                                    <div id="cbia_run_filter_range" style="<?php echo esc_attr($settings['filter_mode'] === 'range' ? '' : 'display:none;'); ?>">
                                        <input type="date" name="run_date_from" value="<?php echo esc_attr((string)$settings['date_from']); ?>" />
                                        <input type="date" name="run_date_to" value="<?php echo esc_attr((string)$settings['date_to']); ?>" />
                                    </div>
                                </div>
                            </div>

                            <div class="cbia-oldv2-filter-row">
                                <input type="search" id="cbia-oldposts-search" class="cbia-oldv2-input" placeholder="<?php echo esc_attr__('Search by title or ID...', 'cbiastudio-blogflow-ai'); ?>" />
                                <select id="cbia-oldposts-status-filter" class="cbia-oldv2-select" aria-label="<?php echo esc_attr__('Filter by status', 'cbiastudio-blogflow-ai'); ?>">
                                    <option value=""><?php echo esc_html__('All statuses', 'cbiastudio-blogflow-ai'); ?></option>
                                    <option value="publish"><?php echo esc_html__('Published', 'cbiastudio-blogflow-ai'); ?></option>
                                    <option value="future"><?php echo esc_html__('Scheduled', 'cbiastudio-blogflow-ai'); ?></option>
                                    <option value="draft"><?php echo esc_html__('Drafts', 'cbiastudio-blogflow-ai'); ?></option>
                                    <option value="pending"><?php echo esc_html__('Pending', 'cbiastudio-blogflow-ai'); ?></option>
                                    <option value="private"><?php echo esc_html__('Private', 'cbiastudio-blogflow-ai'); ?></option>
                                </select>
                                <select id="cbia-oldposts-category-filter" class="cbia-oldv2-select" aria-label="<?php echo esc_attr__('Filter by category', 'cbiastudio-blogflow-ai'); ?>">
                                    <option value="0"><?php echo esc_html__('All categories', 'cbiastudio-blogflow-ai'); ?></option>
                                    <?php foreach ($picker_category_options as $cat_id => $cat_name): ?>
                                        <option value="<?php echo esc_attr((string)$cat_id); ?>" <?php selected((int)($settings['category_id'] ?? 0), (int)$cat_id); ?>><?php echo esc_html((string)$cat_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="cbia-oldposts-author-filter" class="cbia-oldv2-select" aria-label="<?php echo esc_attr__('Filter by author', 'cbiastudio-blogflow-ai'); ?>">
                                    <option value="0"><?php echo esc_html__('All authors', 'cbiastudio-blogflow-ai'); ?></option>
                                    <?php foreach ($picker_author_options as $author_id => $author_name): ?>
                                        <option value="<?php echo esc_attr((string)$author_id); ?>" <?php selected((int)($settings['author_id'] ?? 0), (int)$author_id); ?>><?php echo esc_html((string)$author_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="cbia-oldv2-sort-wrap">
                                    <select id="cbia-oldposts-sort-by" class="cbia-oldv2-select" aria-label="<?php echo esc_attr__('Sort by', 'cbiastudio-blogflow-ai'); ?>">
                                        <option value="date"><?php echo esc_html__('Date', 'cbiastudio-blogflow-ai'); ?></option>
                                        <option value="title"><?php echo esc_html__('Title', 'cbiastudio-blogflow-ai'); ?></option>
                                        <option value="id">ID</option>
                                    </select>
                                    <button type="button" class="button cbia-oldv2-sort-arrow" id="cbia-oldposts-sort-dir" data-dir="desc" title="<?php echo esc_attr__('Descending order (latest first)', 'cbiastudio-blogflow-ai'); ?>"></button>
                                </div>
                                <button type="submit" class="button" id="cbia-oldposts-apply-filters" name="cbia_action" value="filter_oldposts_picker"><?php echo esc_html__('Filter', 'cbiastudio-blogflow-ai'); ?></button>
                            </div>

                            <div class="cbia-oldv2-toolbar" style="margin-top:8px;">
                                <button type="button" class="button" id="cbia-oldposts-select-visible"><?php echo esc_html__('Select visible', 'cbiastudio-blogflow-ai'); ?></button>
                                <button type="button" class="button" id="cbia-oldposts-clear-all"><?php echo esc_html__('Clear selection', 'cbiastudio-blogflow-ai'); ?></button>
                                <span id="cbia-oldposts-selected-count" class="cbia-oldv2-muted"><?php echo esc_html__('0 selected', 'cbiastudio-blogflow-ai'); ?></span>
                            </div>
                            <input type="hidden" name="run_category_id" id="cbia_run_category_id" value="<?php echo esc_attr((string)((int)($settings['category_id'] ?? 0))); ?>" />
                            <input type="hidden" name="run_author_id" id="cbia_run_author_id" value="<?php echo esc_attr((string)((int)($settings['author_id'] ?? 0))); ?>" />
                        </div>
                    </div>

                            <p class="cbia-oldv2-helper"><?php echo esc_html__('Sort by date/title/ID with the asc/desc arrow and combine visual filters by status, category and author. Internal images do not filter cards: they only define how many internal images will be generated when you run the batch.', 'cbiastudio-blogflow-ai'); ?></p>
                    <div id="cbia-oldposts-elementor-warning" class="cbia-oldv2-inline-warning" style="display:none;"></div>

                    <label class="cbia-oldv2-field" style="display:block;margin-bottom:10px;">
                                <span><?php echo esc_html__('Specific IDs (synced with cards):', 'cbiastudio-blogflow-ai'); ?></span>
                                <input type="text" name="run_post_ids" id="cbia-oldposts-post-ids" value="<?php echo esc_attr((string)($settings['post_ids'] ?? '')); ?>" placeholder="<?php echo esc_attr__('123,456', 'cbiastudio-blogflow-ai'); ?>" class="cbia-oldv2-input" style="max-width:520px;" />
                    </label>

                    <?php if (!empty($picker_rows)): ?>
                        <div class="cbia-oldv2-grid" id="cbia-oldposts-picker-grid">
                            <?php foreach ($picker_rows as $row): ?>
                                <?php
                                $row_id = absint($row['id']);
                                $row_title = (string)$row['title'];
                                $row_status = sanitize_key((string)$row['status']);
                                $row_date = sanitize_text_field((string)$row['date']);
                                $row_lang = sanitize_text_field((string)$row['lang']);
                                $row_seo_score = isset($row['seo_score']) && is_numeric($row['seo_score']) ? max(0, min(100, (int)$row['seo_score'])) : null;
                                $row_readability_score = isset($row['readability_score']) && is_numeric($row['readability_score']) ? max(0, min(100, (int)$row['readability_score'])) : null;
                                $row_seo_state = $row_seo_score === null ? 'none' : ($row_seo_score >= 71 ? 'good' : ($row_seo_score >= 41 ? 'ok' : 'bad'));
                                $row_readability_state = $row_readability_score === null ? 'none' : ($row_readability_score >= 71 ? 'good' : ($row_readability_score >= 41 ? 'ok' : 'bad'));
                                $row_thumb = (string)$row['thumb'];
                                $row_selected = !empty($row['selected']);
                                $row_author_id = absint((int)($row['author_id'] ?? 0));
                                $row_author_label = sanitize_text_field((string)($row['author_label'] ?? ''));
                                $row_category_ids = isset($row['category_ids']) && is_array($row['category_ids']) ? array_values(array_filter(array_map('absint', $row['category_ids']))) : array();
                                $row_category_csv = implode(',', $row_category_ids);
                                $row_date_ts = strtotime($row_date);
                                if ($row_date_ts === false) {
                                    $row_date_ts = 0;
                                }
                                ?>
                                <div
                                    class="<?php echo esc_attr('cbia-oldv2-post' . ($row_selected ? ' is-selected' : '')); ?>"
                                    data-post-id="<?php echo esc_attr((string)$row_id); ?>"
                                    data-post-title="<?php echo esc_attr(strtolower($row_title)); ?>"
                                    data-post-status="<?php echo esc_attr($row_status); ?>"
                                    data-post-date="<?php echo esc_attr($row_date); ?>"
                                    data-post-date-ts="<?php echo esc_attr((string)$row_date_ts); ?>"
                                    data-post-author-id="<?php echo esc_attr((string)$row_author_id); ?>"
                                    data-post-categories="<?php echo esc_attr($row_category_csv); ?>"
                                    data-post-has-featured="<?php echo esc_attr(!empty($row['has_featured']) ? '1' : '0'); ?>"
                                    data-post-has-internal="<?php echo esc_attr(!empty($row['has_internal']) ? '1' : '0'); ?>"
                                    data-post-internal-count="<?php echo esc_attr((string) absint((int)($row['internal_count'] ?? 0))); ?>"
                                    data-post-elementor="<?php echo esc_attr(!empty($row['is_elementor']) ? '1' : '0'); ?>"
                                    data-post-seo-score="<?php echo esc_attr($row_seo_score === null ? '' : (string)$row_seo_score); ?>"
                                    data-post-readability-score="<?php echo esc_attr($row_readability_score === null ? '' : (string)$row_readability_score); ?>"
                                >
                                    <div class="cbia-oldv2-thumb">
                                        <?php if ($row_thumb !== ''): ?>
                                            <img src="<?php echo esc_url($row_thumb); ?>" alt="" loading="lazy" class="cbia-oldv2-thumb-img" />
                                        <?php else: ?>
                                            <span class="cbia-oldv2-thumb-empty"><?php echo esc_html__('No featured image', 'cbiastudio-blogflow-ai'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cbia-oldv2-meta">
                                        <input type="checkbox" class="cbia-oldposts-check" value="<?php echo esc_attr((string)$row_id); ?>" <?php checked($row_selected, true); ?> />
                                        <div class="cbia-oldv2-title"><?php echo esc_html($row_title); ?></div>
                                        <div class="cbia-oldv2-scores" aria-label="<?php echo esc_attr__('Yoast scores', 'cbiastudio-blogflow-ai'); ?>">
                                            <span class="cbia-oldv2-score is-<?php echo esc_attr($row_seo_state); ?>" data-cbia-score="seo" title="<?php echo esc_attr(sprintf('%s: %s', __('SEO score', 'cbiastudio-blogflow-ai'), $row_seo_score === null ? __('Not available', 'cbiastudio-blogflow-ai') : $row_seo_score . '/100')); ?>">
                                                <span class="cbia-oldv2-score-dot" aria-hidden="true"></span><span>SEO</span><strong class="cbia-oldv2-score-value"><?php echo esc_html($row_seo_score === null ? '–' : (string)$row_seo_score); ?></strong>
                                            </span>
                                            <span class="cbia-oldv2-score is-<?php echo esc_attr($row_readability_state); ?>" data-cbia-score="readability" title="<?php echo esc_attr(sprintf('%s: %s', __('Readability score', 'cbiastudio-blogflow-ai'), $row_readability_score === null ? __('Not available', 'cbiastudio-blogflow-ai') : $row_readability_score . '/100')); ?>">
                                                <span class="cbia-oldv2-score-dot" aria-hidden="true"></span><span><?php echo esc_html__('Readability', 'cbiastudio-blogflow-ai'); ?></span><strong class="cbia-oldv2-score-value"><?php echo esc_html($row_readability_score === null ? '–' : (string)$row_readability_score); ?></strong>
                                            </span>
                                        </div>
                                        <div class="cbia-oldv2-badges">
                                            <span class="cbia-oldv2-badge">#<?php echo esc_html((string)$row_id); ?></span>
                                            <span class="cbia-oldv2-badge"><?php echo esc_html($row_status); ?></span>
                                            <span class="cbia-oldv2-badge"><?php echo esc_html($row_lang); ?></span>
                                            <span class="cbia-oldv2-badge"><?php echo esc_html($row_date); ?></span>
                                            <?php if ($row_author_label !== ''): ?>
                                                <span class="cbia-oldv2-badge"><?php echo esc_html($row_author_label); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($row['is_elementor'])): ?>
                                                <span class="cbia-oldv2-badge -elementor"><?php echo esc_html__('Elementor', 'cbiastudio-blogflow-ai'); ?></span>
                                            <?php endif; ?>
                                            <span class="cbia-oldv2-badge cbia-oldv2-badge-featured"><?php echo !empty($row['has_featured']) ? esc_html__('featured', 'cbiastudio-blogflow-ai') : esc_html__('no featured', 'cbiastudio-blogflow-ai'); ?></span>
                                            <span class="cbia-oldv2-badge cbia-oldv2-badge-internal">
                                                <?php
                                                if (!empty($row['has_internal'])) {
                                                    $row_internal_count = absint((int)($row['internal_count'] ?? 0));
                                                    // translators: %d is the number of internal images currently detected for this post.
                                                    echo esc_html(sprintf(_n('%d internal', '%d internals', $row_internal_count, 'cbiastudio-blogflow-ai'), $row_internal_count));
                                                } else {
                                                    echo esc_html__('no internals', 'cbiastudio-blogflow-ai');
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="cbia-oldv2-muted"><?php echo esc_html__('No posts to display.', 'cbiastudio-blogflow-ai'); ?></p>
                    <?php endif; ?>
                </div>

                <div id="cbia-oldposts-summary" class="notice notice-info" style="margin:8px 0 12px;display:none;"></div>
                <div id="cbia-oldposts-runtime-status" class="notice notice-warning" style="margin:8px 0 12px;display:none;"></div>

                <p>
                    <button type="submit" class="button button-primary" name="cbia_action" value="run_oldposts"><?php echo esc_html__('Run batch', 'cbiastudio-blogflow-ai'); ?></button>
                    <button type="submit" class="button" name="cbia_action" value="stop" style="margin-left:8px;background:#b70000;color:#fff;"><?php echo esc_html__('Stop', 'cbiastudio-blogflow-ai'); ?></button>
                    <button type="submit" class="button button-secondary" name="cbia_action" value="clear_log" style="margin-left:8px;"><?php echo esc_html__('Clear log', 'cbiastudio-blogflow-ai'); ?></button>
                </p>
            </div>

            <aside class="cbia-oldv2-side">
                <div class="cbia-oldv2-card -accent">
                    <h4><?php echo esc_html__('Template and models', 'cbiastudio-blogflow-ai'); ?></h4>
                    <details class="cbia-oldv2-dd" open>
                        <summary>
                            <span class="cbia-oldv2-dd-label">Template</span>
                            <span id="cbia-v2-current-template" class="cbia-oldv2-dd-value"></span>
                        </summary>
                        <div class="cbia-oldv2-dd-body">
                            <div class="cbia-oldv2-field">
                                <label for="cbia_run_post_length_variant"><?php echo esc_html__('Template', 'cbiastudio-blogflow-ai'); ?></label>
                                <select id="cbia_run_post_length_variant" class="cbia-oldv2-select" name="run_post_length_variant">
                                    <option value="short" <?php selected($tpl_default, 'short'); ?>><?php echo esc_html__('Short (~1000)', 'cbiastudio-blogflow-ai'); ?></option>
                                    <option value="medium" <?php selected($tpl_default, 'medium'); ?>><?php echo esc_html__('Medium (1800-2000)', 'cbiastudio-blogflow-ai'); ?></option>
                                    <option value="long" <?php selected($tpl_default, 'long'); ?>><?php echo esc_html__('Long (2000-2200)', 'cbiastudio-blogflow-ai'); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>
                    <details class="cbia-oldv2-dd" open>
                        <summary>
                            <span class="cbia-oldv2-dd-label"><?php echo esc_html__('AI text', 'cbiastudio-blogflow-ai'); ?></span>
                            <span id="cbia-v2-current-text" class="cbia-oldv2-dd-value"></span>
                        </summary>
                        <div class="cbia-oldv2-dd-body">
                            <div class="cbia-oldv2-field">
                                <label for="cbia_run_text_provider"><?php echo esc_html__('Text provider', 'cbiastudio-blogflow-ai'); ?></label>
                                <select id="cbia_run_text_provider" class="cbia-oldv2-select" name="run_text_provider">
                                    <?php foreach ($provider_labels as $pkey => $plabel): ?>
                                        <option value="<?php echo esc_attr((string)$pkey); ?>" <?php selected($text_provider_default, (string)$pkey); ?>><?php echo esc_html((string)$plabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="cbia-oldv2-field">
                                <label for="cbia_run_text_model"><?php echo esc_html__('Text model', 'cbiastudio-blogflow-ai'); ?></label>
                                <select id="cbia_run_text_model" class="cbia-oldv2-select" name="run_text_model">
                                    <?php foreach (($text_model_lists[$text_provider_default] ?? array()) as $m): ?>
                                        <option value="<?php echo esc_attr((string)$m); ?>" <?php selected($text_model_default, (string)$m); ?>><?php echo esc_html((string)$m); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </details>
                    <details class="cbia-oldv2-dd">
                        <summary>
                            <span class="cbia-oldv2-dd-label"><?php echo esc_html__('AI image', 'cbiastudio-blogflow-ai'); ?></span>
                            <span id="cbia-v2-current-image" class="cbia-oldv2-dd-value"></span>
                        </summary>
                        <div class="cbia-oldv2-dd-body">
                            <div class="cbia-oldv2-field">
                                <label for="cbia_run_image_provider"><?php echo esc_html__('Image provider', 'cbiastudio-blogflow-ai'); ?></label>
                                <select id="cbia_run_image_provider" class="cbia-oldv2-select" name="run_image_provider">
                                    <?php foreach ($image_provider_labels as $pkey => $plabel): ?>
                                        <option value="<?php echo esc_attr((string)$pkey); ?>" <?php selected($image_provider_default, (string)$pkey); ?>><?php echo esc_html((string)$plabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="cbia-oldv2-field">
                                <label for="cbia_run_image_model"><?php echo esc_html__('Image model', 'cbiastudio-blogflow-ai'); ?></label>
                                <select id="cbia_run_image_model" class="cbia-oldv2-select" name="run_image_model">
                                    <?php foreach (($image_model_lists[$image_provider_default] ?? array()) as $m): ?>
                                        <option value="<?php echo esc_attr((string)$m); ?>" <?php selected($image_model_default, (string)$m); ?>><?php echo esc_html((string)$m); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </details>
                </div>
                <div id="cbia-oldv2-key-warning" class="cbia-oldv2-card" style="display:none;border-color:#dba617;background:#fff8e5;">
                    <h4 style="margin-bottom:6px;"><?php echo esc_html__('Missing API key', 'cbiastudio-blogflow-ai'); ?></h4>
                    <p id="cbia-oldv2-key-warning-text" class="cbia-oldv2-mini" style="margin:0 0 8px;"></p>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=cbia&tab=config')); ?>"><?php echo esc_html__('Open settings', 'cbiastudio-blogflow-ai'); ?></a>
                </div>

                <div class="cbia-oldv2-card">
                    <details class="cbia-oldv2-section" open>
                        <summary>
                            <strong><?php echo esc_html__('What to update', 'cbiastudio-blogflow-ai'); ?></strong>
                            <span id="cbia-v2-count-main" class="cbia-oldv2-count"><?php echo esc_html__('0 active', 'cbiastudio-blogflow-ai'); ?></span>
                        </summary>
                        <div class="cbia-oldv2-section-body cbia-oldv2-switch-list">
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('AI content', 'cbiastudio-blogflow-ai'); ?> <span class="cbia-oldv2-help" title="<?php echo esc_attr__('Regenerates the article body. It does not recalculate Yoast, categories or tags unless you enable them separately.', 'cbiastudio-blogflow-ai'); ?>" aria-label="<?php echo esc_attr__('Regenerates the article body. It does not recalculate Yoast, categories or tags unless you enable them separately.', 'cbiastudio-blogflow-ai'); ?>">i</span></strong><span><?php echo esc_html__('Body and structure only. Yoast, categories and tags are handled separately.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_content" value="1" <?php checked((int)$settings['do_content'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Text without images', 'cbiastudio-blogflow-ai'); ?> <span class="cbia-oldv2-help" title="<?php echo esc_attr__('Regenerates only the text and keeps the current post images.', 'cbiastudio-blogflow-ai'); ?>" aria-label="<?php echo esc_attr__('Regenerates only the text and keeps the current post images.', 'cbiastudio-blogflow-ai'); ?>">i</span></strong><span><?php echo esc_html__('Text only, keeps current images.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_content_no_images" value="1" <?php checked((int)$settings['do_content_no_images'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Internal images', 'cbiastudio-blogflow-ai'); ?> <span class="cbia-oldv2-help" title="<?php echo esc_attr__('Generates or regenerates only the images inside the content.', 'cbiastudio-blogflow-ai'); ?>" aria-label="<?php echo esc_attr__('Generates or regenerates only the images inside the content.', 'cbiastudio-blogflow-ai'); ?>">i</span></strong><span><?php echo esc_html__('Regenerates internal slots only.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_images_content_only" value="1" <?php checked((int)$settings['do_images_content_only'], 1); ?> <?php disabled(!$internal_images_enabled); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Featured image', 'cbiastudio-blogflow-ai'); ?> <span class="cbia-oldv2-help" title="<?php echo esc_attr__('Generates or replaces only the featured image for the post.', 'cbiastudio-blogflow-ai'); ?>" aria-label="<?php echo esc_attr__('Generates or replaces only the featured image for the post.', 'cbiastudio-blogflow-ai'); ?>">i</span></strong><span><?php echo esc_html__('Regenerates featured image only.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_featured_only" value="1" <?php checked((int)$settings['do_featured_only'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Prepare images for regeneration', 'cbiastudio-blogflow-ai'); ?> <span class="cbia-oldv2-help" title="<?php echo esc_attr__('It does not generate new images by itself. It converts internal markers into pending images so they can be regenerated in this batch.', 'cbiastudio-blogflow-ai'); ?>" aria-label="<?php echo esc_attr__('It does not generate new images by itself. It converts internal markers into pending images so they can be regenerated in this batch.', 'cbiastudio-blogflow-ai'); ?>">i</span></strong><span><?php echo esc_html__('Reopens internal pending images for regeneration.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_images_reset" value="1" <?php checked((int)$settings['do_images_reset'], 1); ?> <?php disabled(!$internal_images_enabled); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('AI title', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Optimizes the SEO title.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_title" value="1" <?php checked((int)$settings['do_title'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Yoast meta description', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Description used for the snippet.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_yoast_metadesc" value="1" <?php checked((int)$settings['do_yoast_metadesc'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Yoast keyphrase', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Target keyphrase.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_yoast_focuskw" value="1" <?php checked((int)$settings['do_yoast_focuskw'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Yoast title', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Yoast SEO title.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_yoast_title" value="1" <?php checked((int)$settings['do_yoast_title'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Yoast reindex', 'cbiastudio-blogflow-ai'); ?> <span class="cbia-oldv2-help" title="<?php echo esc_attr__('Asks Yoast to rebuild its indexes and traffic lights after updating the post.', 'cbiastudio-blogflow-ai'); ?>" aria-label="<?php echo esc_attr__('Asks Yoast to rebuild its indexes and traffic lights after updating the post.', 'cbiastudio-blogflow-ai'); ?>">i</span></strong><span><?php echo esc_html__('Reindexes Yoast metadata.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_yoast_reindex" value="1" <?php checked((int)$settings['do_yoast_reindex'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Categories', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Recalculates categories.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_categories" value="1" <?php checked((int)$settings['do_categories'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Tags', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Recalculates tags.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_tags" value="1" <?php checked((int)$settings['do_tags'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Update note', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Inserts or refreshes the update note.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_do_note" value="1" <?php checked((int)$settings['do_note'], 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        <label class="cbia-oldv2-switch-row"><span class="cbia-oldv2-switch-copy"><strong><?php echo esc_html__('Dry run', 'cbiastudio-blogflow-ai'); ?></strong><span><?php echo esc_html__('Simulation only, no changes.', 'cbiastudio-blogflow-ai'); ?></span></span><span class="cbia-oldv2-switch-wrap"><input type="checkbox" name="run_dry_run" value="1" <?php checked((int)($settings['dry_run'] ?? 0), 1); ?> /><span class="cbia-oldv2-switch-ui"></span></span></label>
                        </div>
                    </details>
                </div>
            </aside>
        </div>
    </form>
    <h3><?php echo esc_html__('Log', 'cbiastudio-blogflow-ai'); ?></h3>
    <textarea id="cbia-oldposts-log" rows="14" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea($log); ?></textarea>

    <?php ob_start(); ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Arreglo defensivo de mojibake (texto mal decodificado).
        // No toca la lÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³gica: solo corrige legibilidad en la UI.
        function tryDecodeLatin1ToUtf8(str) {
            try {
                // PatrÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n tÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­pico: UTF-8 leÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­do como Latin-1.
                return decodeURIComponent(escape(str));
            } catch (e) {
                return str;
            }
        }
        function fixMojibakeInTextNodes(root) {
            if (!root || !root.ownerDocument) return;
            const doc = root.ownerDocument;
            const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            const suspicious = /[\u00C3\u00C2\u00E2]/;
            let node;
            while ((node = walker.nextNode())) {
                const original = node.nodeValue;
                if (!original || !suspicious.test(original)) continue;
                let fixed = tryDecodeLatin1ToUtf8(original);
                // Algunos fragmentos estÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡n doblemente rotos.
                if (fixed !== original && suspicious.test(fixed)) {
                    fixed = tryDecodeLatin1ToUtf8(fixed);
                }
                if (fixed && fixed !== original) {
                    node.nodeValue = fixed;
                }
            }
        }
        const wrap = document.querySelector('.wrap');
        if (wrap) {
            fixMojibakeInTextNodes(wrap);
        }

        function bindFilterToggles(prefix){
            const olderBox = document.getElementById(prefix + '_filter_older');
            const rangeBox = document.getElementById(prefix + '_filter_range');
            const name = (prefix === 'cbia_old') ? 'filter_mode' : 'run_filter_mode';
            const radios = document.querySelectorAll('input[name="'+name+'"]');
            radios.forEach(r => r.addEventListener('change', function(){
                if(this.value === 'range'){
                    if(olderBox) olderBox.style.display='none';
                    if(rangeBox) rangeBox.style.display='';
                } else if (this.value === 'older') {
                    if(olderBox) olderBox.style.display='';
                    if(rangeBox) rangeBox.style.display='none';
                } else {
                    if(olderBox) olderBox.style.display='none';
                    if(rangeBox) rangeBox.style.display='none';
                }
            }));
        }
        bindFilterToggles('cbia_run');
        function hookDateFilter(prefix){
            bindFilterToggles(prefix);
        }

        // Selector visual de posts -> sync con run_post_ids
        const pickerGrid = document.getElementById('cbia-oldposts-picker-grid');
        const pickerSearch = document.getElementById('cbia-oldposts-search');
        const pickerStatusFilter = document.getElementById('cbia-oldposts-status-filter');
        const pickerCategoryFilter = document.getElementById('cbia-oldposts-category-filter');
        const pickerAuthorFilter = document.getElementById('cbia-oldposts-author-filter');
        const pickerSortBy = document.getElementById('cbia-oldposts-sort-by');
        const pickerSortDir = document.getElementById('cbia-oldposts-sort-dir');
        const pickerApplyFilters = document.getElementById('cbia-oldposts-apply-filters');
        const runCategorySelect = document.getElementById('cbia_run_category_id');
        const runAuthorSelect = document.getElementById('cbia_run_author_id');
        const pickerSelectVisible = document.getElementById('cbia-oldposts-select-visible');
        const pickerClearAll = document.getElementById('cbia-oldposts-clear-all');
        const pickerCount = document.getElementById('cbia-oldposts-selected-count');
        const elementorWarning = document.getElementById('cbia-oldposts-elementor-warning');
        const idsInput = document.querySelector('input[name="run_post_ids"]');
        const shell = document.getElementById('cbia-oldv2-shell');
        const runTextProvider = document.getElementById('cbia_run_text_provider');
        const runTextModel = document.getElementById('cbia_run_text_model');
        const runImageProvider = document.getElementById('cbia_run_image_provider');
        const runImageModel = document.getElementById('cbia_run_image_model');
        const runTemplate = document.getElementById('cbia_run_post_length_variant');
        const runOlderDaysInput = document.querySelector('input[name="run_older_than_days"]');
        const runDateFromInput = document.querySelector('input[name="run_date_from"]');
        const runDateToInput = document.querySelector('input[name="run_date_to"]');
        const currentTemplate = document.getElementById('cbia-v2-current-template');
        const currentText = document.getElementById('cbia-v2-current-text');
        const currentImage = document.getElementById('cbia-v2-current-image');
        const countMain = document.getElementById('cbia-v2-count-main');
        const keyWarning = document.getElementById('cbia-oldv2-key-warning');
        const keyWarningText = document.getElementById('cbia-oldv2-key-warning-text');
        let updateSummary = null;
        let actionsFormRef = null;
        let runBatchBtn = null;

        const parseAttrJson = (attrName, fallback = {}) => {
            if (!shell) return fallback;
            const raw = shell.getAttribute(attrName) || '';
            if (!raw) return fallback;
            try { return JSON.parse(raw); } catch (e) { return fallback; }
        };
        const providerLabels = parseAttrJson('data-provider-labels', {});
        const textModelLists = parseAttrJson('data-text-model-lists', {});
        const imageModelLists = parseAttrJson('data-image-model-lists', {});
        const providerKeyState = parseAttrJson('data-provider-key-state', {});
        const activeRunStateBoot = parseAttrJson('data-active-run-state', {});
        const i18n = parseAttrJson('data-js-i18n', {});
        const t = function(key, replacements) {
            let text = String(Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : key);
            if (replacements && typeof replacements === 'object') {
                Object.keys(replacements).forEach(function(name) {
                    text = text.replace(new RegExp('\\{' + name + '\\}', 'g'), String(replacements[name]));
                });
            }
            return text;
        };
        const uiStateStorageKey = 'cbia_oldposts_ui_state_v3';
        const uiLegacyStateStorageKeys = ['cbia_oldposts_ui_state_v2'];
        const filterStateStorageKey = 'cbia_oldposts_filter_state_v1';
        const uiStateFields = [
            'run_batch_size','run_scope_plugin','run_images_limit',
            'run_post_ids','run_category_id','run_author_id','run_dry_run','run_post_length_variant','run_text_provider','run_text_model',
            'run_image_provider','run_image_model','run_do_note','run_do_yoast_metadesc','run_do_yoast_focuskw','run_do_yoast_title',
            'run_do_yoast_reindex','run_do_title','run_do_content','run_do_content_no_images','run_do_images_reset',
            'run_do_images_content_only','run_do_featured_only','run_do_categories','run_do_tags'
        ];
        const filterStateFields = ['run_filter_mode', 'run_older_than_days', 'run_date_from', 'run_date_to'];
        const mainActionNames = ['run_do_content','run_do_content_no_images','run_do_images_content_only','run_do_featured_only','run_do_images_reset','run_do_title','run_do_yoast_metadesc','run_do_yoast_focuskw','run_do_yoast_title','run_do_yoast_reindex','run_do_categories','run_do_tags','run_do_note','run_dry_run'];

        function selectedText(el) {
            if (!el || !el.options || !el.options.length) return '';
            return String(el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '').trim();
        }
        function checkedCount(names) {
            if (!actionsFormRef || !Array.isArray(names)) return 0;
            return names.reduce((acc, name) => {
                const el = actionsFormRef.querySelector('[name="' + name + '"]');
                return acc + ((el && el.checked) ? 1 : 0);
            }, 0);
        }
        function updateCompactSidebar() {
            if (currentTemplate && runTemplate) currentTemplate.textContent = selectedText(runTemplate) || '-';
            if (currentText && runTextProvider && runTextModel) {
                const p = selectedText(runTextProvider) || String(runTextProvider.value || '');
                const m = selectedText(runTextModel) || String(runTextModel.value || '');
                currentText.textContent = p + (m ? (' / ' + m) : '');
            }
            if (currentImage && runImageProvider && runImageModel) {
                const p = selectedText(runImageProvider) || String(runImageProvider.value || '');
                const m = selectedText(runImageModel) || String(runImageModel.value || '');
                currentImage.textContent = p + (m ? (' / ' + m) : '');
            }
            if (countMain) countMain.textContent = checkedCount(mainActionNames) + ' ' + t('activeSuffix');
        }
        function providerHasKey(provider) {
            const p = String(provider || '').trim();
            if (!p) return true;
            return !!providerKeyState[p];
        }
        function isCheckedAction(name) {
            if (!actionsFormRef) return false;
            const el = actionsFormRef.querySelector('[name="' + name + '"]');
            return !!(el && el.checked);
        }
        // Override de warning para bloquear Ejecutar lote solo cuando las acciones activas
        // realmente requieren proveedor sin clave.
        function updateProviderKeyWarning() {
            if (!keyWarning || !keyWarningText) return;
            const tp = runTextProvider ? String(runTextProvider.value || '') : '';
            const ip = runImageProvider ? String(runImageProvider.value || '') : '';

            const needText =
                isCheckedAction('run_do_content') ||
                isCheckedAction('run_do_content_no_images') ||
                isCheckedAction('run_do_title') ||
                isCheckedAction('run_do_yoast_metadesc') ||
                isCheckedAction('run_do_yoast_focuskw') ||
                isCheckedAction('run_do_yoast_title') ||
                isCheckedAction('run_do_categories') ||
                isCheckedAction('run_do_tags');
            const needImage =
                isCheckedAction('run_do_images_content_only') ||
                isCheckedAction('run_do_featured_only') ||
                (isCheckedAction('run_do_content') && !isCheckedAction('run_do_content_no_images'));

            const missingSelected = [];
            if (tp && !providerHasKey(tp)) missingSelected.push(t('textProviderLabel') + ' (' + (providerLabels[tp] || tp) + ')');
            if (ip && !providerHasKey(ip) && ip !== tp) missingSelected.push(t('imageProviderLabel') + ' (' + (providerLabels[ip] || ip) + ')');

            const missingExec = [];
            if (needText && tp && !providerHasKey(tp)) missingExec.push(t('textProviderLabel') + ' (' + (providerLabels[tp] || tp) + ')');
            if (needImage && ip && !providerHasKey(ip) && !(needText && ip === tp && !providerHasKey(tp))) {
                missingExec.push(t('imageProviderLabel') + ' (' + (providerLabels[ip] || ip) + ')');
            }

            if (runBatchBtn) {
                runBatchBtn.disabled = missingExec.length > 0;
                runBatchBtn.title = missingExec.length > 0 ? (t('missingApiFor') + ' ' + missingExec.join(', ')) : '';
            }

            if (!missingSelected.length) {
                keyWarning.style.display = 'none';
                keyWarningText.textContent = '';
                return;
            }
            keyWarning.style.display = '';
            if (missingExec.length > 0) {
                keyWarningText.textContent = t('missingApiFor') + ' ' + missingExec.join(', ') + '. ' + t('runBlockedUntilConfigured');
            } else {
                keyWarningText.textContent = t('providerWithoutKey') + ' ' + missingSelected.join(', ') + '. ' + t('providerWarningNonBlocking');
            }
        }

        function rebuildModelSelect(providerSelect, modelSelect, lists) {
            if (!providerSelect || !modelSelect) return;
            const provider = String(providerSelect.value || '').trim();
            const keep = String(modelSelect.value || '').trim();
            const models = Array.isArray(lists[provider]) ? lists[provider] : [];
            modelSelect.innerHTML = '';
            if (!models.length) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = t('noModels');
                modelSelect.appendChild(opt);
                return;
            }
            models.forEach((m) => {
                const opt = document.createElement('option');
                opt.value = String(m);
                opt.textContent = String(m);
                modelSelect.appendChild(opt);
            });
            const hasKeep = models.indexOf(keep) >= 0;
            modelSelect.value = hasKeep ? keep : String(models[0]);
            updateCompactSidebar();
        }

        function parseIdsCsv(raw){
            const out = [];
            String(raw || '').split(',').forEach(function(part){
                const v = parseInt(String(part || '').trim(), 10);
                if (!isNaN(v) && v > 0 && out.indexOf(v) < 0) out.push(v);
            });
            return out;
        }
        function collectUiState() {
            if (!actionsFormRef) return {};
            const state = {};
            uiStateFields.forEach(function(name) {
                const el = actionsFormRef.querySelector('[name="' + name + '"]');
                if (!el) return;
                state[name] = (el.type === 'checkbox') ? (el.checked ? 1 : 0) : String(el.value || '');
            });
            return state;
        }
        function persistUiState() {
            try {
                window.localStorage.setItem(uiStateStorageKey, JSON.stringify(collectUiState()));
            } catch (e) {}
        }
        function collectFilterState() {
            if (!actionsFormRef) return {};
            const state = {};
            filterStateFields.forEach(function(name) {
                const input = actionsFormRef.querySelector('[name="' + name + '"]');
                if (!input) return;
                if (name === 'run_filter_mode') {
                    const checked = actionsFormRef.querySelector('input[name="run_filter_mode"]:checked');
                    state[name] = checked ? String(checked.value || '') : '';
                } else {
                    state[name] = String(input.value || '');
                }
            });
            return state;
        }
        function persistFilterState() {
            try {
                window.localStorage.setItem(filterStateStorageKey, JSON.stringify(collectFilterState()));
            } catch (e) {}
        }
        function readPersistedFilterState() {
            try {
                const raw = window.localStorage.getItem(filterStateStorageKey);
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        }
        function applyFilterState(state) {
            if (!actionsFormRef || !state || typeof state !== 'object') return;
            if (Object.prototype.hasOwnProperty.call(state, 'run_filter_mode')) {
                const mode = String(state.run_filter_mode || '');
                if (mode) {
                    const radio = actionsFormRef.querySelector('input[name="run_filter_mode"][value="' + mode + '"]');
                    if (radio) radio.checked = true;
                }
            }
            ['run_older_than_days', 'run_date_from', 'run_date_to'].forEach(function(name){
                if (!Object.prototype.hasOwnProperty.call(state, name)) return;
                const input = actionsFormRef.querySelector('[name="' + name + '"]');
                if (!input) return;
                input.value = String(state[name] == null ? '' : state[name]);
            });
            hookDateFilter('cbia_run');
        }
        document.querySelectorAll('input[name="run_filter_mode"]').forEach(function(radio){
            radio.addEventListener('change', persistFilterState);
        });
        if (runOlderDaysInput) {
            runOlderDaysInput.addEventListener('input', persistFilterState);
            runOlderDaysInput.addEventListener('change', persistFilterState);
        }
        if (runDateFromInput) runDateFromInput.addEventListener('change', persistFilterState);
        if (runDateToInput) runDateToInput.addEventListener('change', persistFilterState);
        function readPersistedUiState() {
            try {
                uiLegacyStateStorageKeys.forEach(function(legacyKey){
                    window.localStorage.removeItem(legacyKey);
                });
                const raw = window.localStorage.getItem(uiStateStorageKey);
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        }
        function applyUiState(state) {
            if (!actionsFormRef || !state || typeof state !== 'object') return;
            uiStateFields.forEach(function(name) {
                if (!Object.prototype.hasOwnProperty.call(state, name)) return;
                const el = actionsFormRef.querySelector('[name="' + name + '"]');
                if (!el) return;
                if (el.type === 'checkbox') {
                    el.checked = !!state[name];
                } else {
                    el.value = String(state[name] == null ? '' : state[name]);
                }
            });
            if (runTextProvider && runTextModel) rebuildModelSelect(runTextProvider, runTextModel, textModelLists);
            if (runImageProvider && runImageModel) rebuildModelSelect(runImageProvider, runImageModel, imageModelLists);
            if (state.run_text_model && runTextModel) runTextModel.value = String(state.run_text_model);
            if (state.run_image_model && runImageModel) runImageModel.value = String(state.run_image_model);
            syncVisualFiltersFromExecution();
            syncPickerFromIdsInput();
            applyPickerFiltersAndSort();
            if (typeof updateSummary === 'function') updateSummary();
            updateCompactSidebar();
            updateProviderKeyWarning();
            persistUiState();
        }
        function updatePickerCount(){
            if (!pickerCount || !pickerGrid) return;
            const selected = pickerGrid.querySelectorAll('.cbia-oldposts-check:checked').length;
            pickerCount.textContent = selected + ' ' + t('selectedCount');
        }
        function updateElementorWarning(){
            if (!pickerGrid || !elementorWarning) return;
            const selectedCards = Array.from(pickerGrid.querySelectorAll('.cbia-oldposts-check:checked')).map(function(chk){
                return chk.closest('.cbia-oldv2-post');
            }).filter(Boolean);
            const elementorCount = selectedCards.filter(function(card){
                return String(card.getAttribute('data-post-elementor') || '0') === '1';
            }).length;
            if (elementorCount <= 0) {
                elementorWarning.style.display = 'none';
                elementorWarning.innerHTML = '';
                return;
            }
            const attentionLabel = <?php echo wp_json_encode(__('Attention:', 'cbiastudio-blogflow-ai')); ?>;
            const singleEntry = <?php echo wp_json_encode(__('entry selected uses Elementor.', 'cbiastudio-blogflow-ai')); ?>;
            const pluralEntry = <?php echo wp_json_encode(__('entries selected use Elementor.', 'cbiastudio-blogflow-ai')); ?>;
            const detailText = <?php echo wp_json_encode(__('In those cases, Update older posts may regenerate post_content, while the visible output still comes from the Elementor layout.', 'cbiastudio-blogflow-ai')); ?>;
            elementorWarning.innerHTML =
                '<strong>' + attentionLabel + '</strong> ' +
                elementorCount + ' ' + (elementorCount === 1 ? singleEntry : pluralEntry) + ' ' +
                detailText;
            elementorWarning.style.display = '';
        }
        function syncIdsFromPicker(){
            if (!pickerGrid || !idsInput) return;
            const ids = [];
            pickerGrid.querySelectorAll('.cbia-oldposts-check:checked').forEach(function(chk){
                const id = parseInt(chk.value || '0', 10);
                if (!isNaN(id) && id > 0 && ids.indexOf(id) < 0) ids.push(id);
            });
            idsInput.value = ids.join(',');
            updatePickerCount();
            updateElementorWarning();
            if (typeof updateSummary === 'function') updateSummary();
            persistUiState();
        }
        function syncPickerFromIdsInput(){
            if (!pickerGrid || !idsInput) return;
            const ids = parseIdsCsv(idsInput.value || '');
            pickerGrid.querySelectorAll('.cbia-oldv2-post').forEach(function(card){
                const chk = card.querySelector('.cbia-oldposts-check');
                if (!chk) return;
                const id = parseInt(chk.value || '0', 10);
                const on = ids.indexOf(id) >= 0;
                chk.checked = on;
                card.classList.toggle('is-selected', on);
            });
            updatePickerCount();
            updateElementorWarning();
            persistUiState();
        }
        function clearPickerSelection() {
            if (idsInput) idsInput.value = '';
            if (pickerGrid) {
                pickerGrid.querySelectorAll('.cbia-oldposts-check').forEach(function(chk){
                    chk.checked = false;
                });
                pickerGrid.querySelectorAll('.cbia-oldv2-post').forEach(function(card){
                    card.classList.remove('is-selected');
                });
            }
            updatePickerCount();
            updateElementorWarning();
            if (typeof updateSummary === 'function') updateSummary();
            persistUiState();
        }
        function cardHasCategory(card, categoryId){
            if (!card || !categoryId || categoryId === '0') return true;
            const csv = String(card.getAttribute('data-post-categories') || '');
            if (!csv) return false;
            return csv.split(',').map(s => String(s).trim()).indexOf(String(categoryId)) >= 0;
        }
        function syncExecutionFiltersFromVisual() {
            if (runCategorySelect && pickerCategoryFilter) {
                runCategorySelect.value = String(pickerCategoryFilter.value || '0');
            }
            if (runAuthorSelect && pickerAuthorFilter) {
                runAuthorSelect.value = String(pickerAuthorFilter.value || '0');
            }
            if (typeof updateSummary === 'function') updateSummary();
            persistUiState();
        }
        function syncVisualFiltersFromExecution() {
            if (runCategorySelect && pickerCategoryFilter) {
                pickerCategoryFilter.value = String(runCategorySelect.value || '0');
            }
            if (runAuthorSelect && pickerAuthorFilter) {
                pickerAuthorFilter.value = String(runAuthorSelect.value || '0');
            }
        }
        function applyPickerFiltersAndSort(){
            if (!pickerGrid) return;
            const q = pickerSearch ? String(pickerSearch.value || '').trim().toLowerCase() : '';
            const status = pickerStatusFilter ? String(pickerStatusFilter.value || '').trim() : '';
            const categoryId = pickerCategoryFilter ? String(pickerCategoryFilter.value || '').trim() : '';
            const authorId = pickerAuthorFilter ? String(pickerAuthorFilter.value || '').trim() : '';
            const sortBy = pickerSortBy ? String(pickerSortBy.value || 'date').trim() : 'date';
            const sortDir = pickerSortDir ? String(pickerSortDir.getAttribute('data-dir') || 'desc').trim() : 'desc';

            const cards = Array.from(pickerGrid.querySelectorAll('.cbia-oldv2-post'));
            cards.forEach(function(card){
                const title = String(card.getAttribute('data-post-title') || '').toLowerCase();
                const pid = String(card.getAttribute('data-post-id') || '').toLowerCase();
                const cardStatus = String(card.getAttribute('data-post-status') || '').trim();
                const cardAuthorId = String(card.getAttribute('data-post-author-id') || '').trim();

                const passQuery = !q || title.indexOf(q) >= 0 || pid.indexOf(q) >= 0;
                const passStatus = !status || status === cardStatus;
                const passCategory = cardHasCategory(card, categoryId);
                const passAuthor = !authorId || authorId === '0' || authorId === cardAuthorId;
                const show = passQuery && passStatus && passCategory && passAuthor;
                card.style.display = show ? '' : 'none';
            });

            cards.sort(function(a, b){
                let cmp = 0;
                if (sortBy === 'title') {
                    const at = String(a.getAttribute('data-post-title') || '');
                    const bt = String(b.getAttribute('data-post-title') || '');
                    cmp = at.localeCompare(bt);
                } else if (sortBy === 'id') {
                    const ai = parseInt(String(a.getAttribute('data-post-id') || '0'), 10) || 0;
                    const bi = parseInt(String(b.getAttribute('data-post-id') || '0'), 10) || 0;
                    cmp = ai - bi;
                } else {
                    const ad = parseInt(String(a.getAttribute('data-post-date-ts') || '0'), 10) || 0;
                    const bd = parseInt(String(b.getAttribute('data-post-date-ts') || '0'), 10) || 0;
                    cmp = ad - bd;
                }
                if (cmp === 0) {
                    const ai = parseInt(String(a.getAttribute('data-post-id') || '0'), 10) || 0;
                    const bi = parseInt(String(b.getAttribute('data-post-id') || '0'), 10) || 0;
                    cmp = ai - bi;
                }
                return sortDir === 'asc' ? cmp : -cmp;
            });
            cards.forEach(card => pickerGrid.appendChild(card));
        }

        if (pickerGrid) {
            pickerGrid.addEventListener('click', function(e){
                const postActionLink = e.target && e.target.closest ? e.target.closest('.cbia-oldv2-post-link') : null;
                const buttonLike = e.target && e.target.closest ? e.target.closest('button,a') : null;
                if (postActionLink || buttonLike) return;
                const directCheck = e.target && e.target.closest ? e.target.closest('.cbia-oldposts-check') : null;
                if (directCheck && pickerGrid.contains(directCheck)) {
                    window.setTimeout(function(){
                        const directCard = directCheck.closest('.cbia-oldv2-post');
                        if (directCard) directCard.classList.toggle('is-selected', !!directCheck.checked);
                        syncIdsFromPicker();
                    }, 0);
                    return;
                }
                const card = e.target && e.target.closest ? e.target.closest('.cbia-oldv2-post') : null;
                if (!card || !pickerGrid.contains(card)) return;
                const chk = card.querySelector('.cbia-oldposts-check');
                if (!chk) return;
                e.preventDefault();
                e.stopPropagation();
                chk.checked = !chk.checked;
                card.classList.toggle('is-selected', !!chk.checked);
                syncIdsFromPicker();
            }, true);
            pickerGrid.addEventListener('change', function(e){
                const chk = e.target && e.target.classList && e.target.classList.contains('cbia-oldposts-check') ? e.target : null;
                if (!chk) return;
                const card = chk.closest('.cbia-oldv2-post');
                if (card) card.classList.toggle('is-selected', !!chk.checked);
                syncIdsFromPicker();
            });
        }
        if (pickerSearch) pickerSearch.addEventListener('input', applyPickerFiltersAndSort);
        if (pickerStatusFilter) pickerStatusFilter.addEventListener('change', applyPickerFiltersAndSort);
        if (pickerCategoryFilter) pickerCategoryFilter.addEventListener('change', function() {
            syncExecutionFiltersFromVisual();
            applyPickerFiltersAndSort();
        });
        if (pickerAuthorFilter) pickerAuthorFilter.addEventListener('change', function() {
            syncExecutionFiltersFromVisual();
            applyPickerFiltersAndSort();
        });
        if (pickerSortBy) pickerSortBy.addEventListener('change', applyPickerFiltersAndSort);
        if (pickerApplyFilters) pickerApplyFilters.addEventListener('click', applyPickerFiltersAndSort);
        if (pickerSortDir) {
            pickerSortDir.addEventListener('click', function(){
                const current = String(pickerSortDir.getAttribute('data-dir') || 'desc');
                const next = current === 'desc' ? 'asc' : 'desc';
                pickerSortDir.setAttribute('data-dir', next);
                pickerSortDir.title = next === 'desc' ? t('sortDescTitle') : t('sortAscTitle');
                applyPickerFiltersAndSort();
            });
        }
        if (pickerSelectVisible && pickerGrid) {
            pickerSelectVisible.addEventListener('click', function(){
                pickerGrid.querySelectorAll('.cbia-oldv2-post').forEach(function(card){
                    if (card.style.display === 'none') return;
                    const chk = card.querySelector('.cbia-oldposts-check');
                    if (!chk) return;
                    chk.checked = true;
                    card.classList.add('is-selected');
                });
                syncIdsFromPicker();
            });
        }
        if (pickerClearAll && pickerGrid) {
            pickerClearAll.addEventListener('click', function(){
                pickerGrid.querySelectorAll('.cbia-oldposts-check').forEach(function(chk){
                    chk.checked = false;
                });
                pickerGrid.querySelectorAll('.cbia-oldv2-post').forEach(function(card){
                    card.classList.remove('is-selected');
                });
                syncIdsFromPicker();
            });
        }
        if (idsInput) {
            idsInput.addEventListener('change', syncPickerFromIdsInput);
            idsInput.addEventListener('keyup', syncPickerFromIdsInput);
        }
        if (runCategorySelect) {
            runCategorySelect.addEventListener('change', function() {
                syncVisualFiltersFromExecution();
                applyPickerFiltersAndSort();
            });
        }
        if (runAuthorSelect) {
            runAuthorSelect.addEventListener('change', function() {
                syncVisualFiltersFromExecution();
                applyPickerFiltersAndSort();
            });
        }
        syncVisualFiltersFromExecution();
        syncPickerFromIdsInput();
        applyPickerFiltersAndSort();
        updateElementorWarning();

        if (runTextProvider && runTextModel) {
            runTextProvider.addEventListener('change', function() {
                rebuildModelSelect(runTextProvider, runTextModel, textModelLists);
                if (typeof updateSummary === 'function') updateSummary();
                updateCompactSidebar();
                updateProviderKeyWarning();
            });
            rebuildModelSelect(runTextProvider, runTextModel, textModelLists);
        }
        if (runImageProvider && runImageModel) {
            runImageProvider.addEventListener('change', function() {
                rebuildModelSelect(runImageProvider, runImageModel, imageModelLists);
                if (typeof updateSummary === 'function') updateSummary();
                updateCompactSidebar();
                updateProviderKeyWarning();
            });
            rebuildModelSelect(runImageProvider, runImageModel, imageModelLists);
        }
        if (runTemplate) runTemplate.addEventListener('change', updateCompactSidebar);
        if (runTextModel) runTextModel.addEventListener('change', updateCompactSidebar);
        if (runImageModel) runImageModel.addEventListener('change', updateCompactSidebar);

        // Resumen + confirmacion antes de ejecutar
        const summary = document.getElementById('cbia-oldposts-summary');
        const runtimeStatus = document.getElementById('cbia-oldposts-runtime-status');
        actionsFormRef = summary ? summary.closest('form') : null;
        const actionsForm = actionsFormRef;
        runBatchBtn = actionsForm ? actionsForm.querySelector('button[name="cbia_action"][value="run_oldposts"]') : null;
        if (summary && actionsForm) {
            const nonce = (window.CBIA && CBIA.nonce) ? CBIA.nonce : '';
            const ajaxReady = (typeof ajaxurl !== 'undefined' && !!nonce);
            const runButtons = Array.from(actionsForm.querySelectorAll('button[name="cbia_action"]')).filter(function(btn){
                return btn && btn.value && (btn.value === 'run_oldposts' || btn.value.indexOf('run_quick_') === 0);
            });
            runButtons.forEach(function(btn){
                btn.addEventListener('click', function(){
                    actionsForm.setAttribute('data-last-submit-action', String(btn && btn.value ? btn.value : ''));
                });
            });
            const stopBtn = actionsForm.querySelector('button[name="cbia_action"][value="stop"]');
            let chunkRunActive = false;
            let bypassAjaxRunOnce = false;
            let chunkRunStopRequested = false;
            const processedRunIds = new Set();
            const isChecked = (name) => {
                const el = actionsForm.querySelector('[name="' + name + '"]');
                return !!el && !!el.checked;
            };
            const getValue = (name) => {
                const el = actionsForm.querySelector('[name="' + name + '"]');
                return el ? String(el.value || '').trim() : '';
            };
            const resolveSubmitAction = function(submitter) {
                let action = (submitter && submitter.value) ? String(submitter.value) : '';
                if (!action) action = String(actionsForm.getAttribute('data-last-submit-action') || '');
                if (!action) {
                    const active = document.activeElement;
                    if (active && active.form === actionsForm && active.name === 'cbia_action' && active.value) {
                        action = String(active.value);
                    }
                }
                return action;
            };
            const setRuntimeStatus = function(message, type) {
                if (!runtimeStatus) return;
                if (!message) {
                    runtimeStatus.style.display = 'none';
                    runtimeStatus.className = 'notice notice-warning';
                    runtimeStatus.innerHTML = '';
                    return;
                }
                runtimeStatus.style.display = '';
                runtimeStatus.className = 'notice ' + (type || 'notice-warning');
                runtimeStatus.innerHTML = '<p style="margin:0;">' + message + '</p>';
            };
            const setRunUiState = function(running) {
                chunkRunActive = !!running;
                runButtons.forEach(function(btn){
                    btn.disabled = !!running;
                });
                if (stopBtn) {
                    stopBtn.disabled = false;
                }
            };
            const formDataToParams = function(formData, ajaxAction) {
                const params = new URLSearchParams();
                formData.forEach(function(value, key){
                    params.append(key, value);
                });
                params.set('action', ajaxAction);
                if (nonce) params.set('_ajax_nonce', nonce);
                return params;
            };
            const parseAjaxResponse = function(rawText) {
                const text = String(rawText || '').trim();
                if (!text) return null;
                try {
                    return JSON.parse(text);
                } catch (e) {
                    const successIdx = text.indexOf('{"success"');
                    if (successIdx >= 0) {
                        try { return JSON.parse(text.slice(successIdx)); } catch (_e1) {}
                    }
                    const firstBrace = text.indexOf('{');
                    const lastBrace = text.lastIndexOf('}');
                    if (firstBrace >= 0 && lastBrace > firstBrace) {
                        try { return JSON.parse(text.slice(firstBrace, lastBrace + 1)); } catch (_e2) {}
                    }
                }
                return null;
            };
            const postAjax = function(ajaxAction, formData, timeoutMs) {
                if (!ajaxReady) {
                    return Promise.reject(new Error('ajax_not_ready'));
                }
                const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                const timer = controller ? window.setTimeout(function() {
                    controller.abort();
                }, Math.max(1000, parseInt(timeoutMs || 30000, 10) || 30000)) : null;
                return fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: formDataToParams(formData, ajaxAction).toString(),
                    signal: controller ? controller.signal : undefined
                }).then(function(response){
                    return response.text().then(function(text){
                        let data = parseAjaxResponse(text);
                        if (!data) {
                            const snippet = String(text || '').trim().slice(0, 260);
                            throw new Error(t('responseNotJson') + (snippet ? ': ' + snippet : ''));
                        }
                        if (!response.ok) {
                            const msg = data && data.data && (data.data.msg || data.data.message)
                                ? (data.data.msg || data.data.message)
                                : ('HTTP ' + response.status);
                            const err = new Error(String(msg));
                            err.data = data && data.data ? data.data : null;
                            throw err;
                        }
                        return data;
                    });
                }).catch(function(error){
                    if (error && error.name === 'AbortError') {
                        throw new Error(t('ajaxTimeout'));
                    }
                    throw error;
                }).finally(function(){
                    if (timer) window.clearTimeout(timer);
                });
            };
            const removeIdsFromPicker = function(ids) {
                const clean = Array.isArray(ids)
                    ? ids.map(function(id){ return parseInt(id || 0, 10) || 0; }).filter(function(id){ return id > 0; })
                    : [];
                if (!clean.length) return;
                const idMap = {};
                clean.forEach(function(id){ idMap[id] = true; });
                if (pickerGrid) {
                    pickerGrid.querySelectorAll('.cbia-oldv2-post').forEach(function(card){
                        const chk = card.querySelector('.cbia-oldposts-check');
                        if (!chk) return;
                        const id = parseInt(chk.value || '0', 10) || 0;
                        if (!idMap[id]) return;
                        chk.checked = false;
                        card.classList.remove('is-selected');
                    });
                }
                if (idsInput) {
                    const current = parseIdsCsv(idsInput.value || '');
                    const next = current.filter(function(id){ return !idMap[id]; });
                    idsInput.value = next.join(',');
                }
                updatePickerCount();
                updateElementorWarning();
                if (typeof updateSummary === 'function') updateSummary();
                persistUiState();
            };
            const collectRunFormData = function(action) {
                if (pickerGrid && idsInput) {
                    syncIdsFromPicker();
                }
                const formData = new FormData(actionsForm);
                formData.set('cbia_form', 'oldposts_actions');
                formData.set('cbia_action', action);
                formData.set('run_custom_actions', '1');
                if (idsInput) formData.set('run_post_ids', String(idsInput.value || ''));
                if (runCategorySelect) formData.set('run_category_id', String(runCategorySelect.value || '0'));
                if (runAuthorSelect) formData.set('run_author_id', String(runAuthorSelect.value || '0'));
                if (pickerCategoryFilter) formData.set('run_category_id', String(pickerCategoryFilter.value || formData.get('run_category_id') || '0'));
                if (pickerAuthorFilter) formData.set('run_author_id', String(pickerAuthorFilter.value || formData.get('run_author_id') || '0'));
                if (runTemplate) formData.set('run_post_length_variant', String(runTemplate.value || 'medium'));
                if (runTextProvider) formData.set('run_text_provider', String(runTextProvider.value || 'openai'));
                if (runTextModel) formData.set('run_text_model', String(runTextModel.value || ''));
                if (runImageProvider) formData.set('run_image_provider', String(runImageProvider.value || 'openai'));
                if (runImageModel) formData.set('run_image_model', String(runImageModel.value || ''));
                return formData;
            };
            const appendClientLog = function(message) {
                if (!logBox || !message) return;
                const stamp = new Date().toISOString().slice(0, 19).replace('T', ' ');
                const line = '[' + stamp + '][CLIENT] ' + String(message);
                logBox.value = (logBox.value ? logBox.value.replace(/\s*$/, '') + "\n" : '') + line + "\n";
                logBox.scrollTop = logBox.scrollHeight;
            };
            const formatStateMessage = function(state) {
                const safeState = state && typeof state === 'object' ? state : {};
                const payload = {
                    processed: parseInt(safeState.processed || 0, 10) || 0,
                    total: parseInt(safeState.total || 0, 10) || 0,
                    ok: parseInt(safeState.ok || 0, 10) || 0,
                    skipped: parseInt(safeState.skipped || 0, 10) || 0,
                    fail: parseInt(safeState.fail || 0, 10) || 0,
                    message: safeState.message || t('serverError')
                };
                switch (String(safeState.status || '')) {
                    case 'running':
                        return { message: t('batchRunning', payload), type: 'notice-warning' };
                    case 'queued':
                    case 'stopping':
                        return { message: t('batchQueued', payload), type: 'notice-warning' };
                    case 'completed':
                        return { message: t('batchCompleted', payload), type: 'notice-success' };
                    case 'stopped':
                        return { message: t('batchStopped', payload), type: 'notice-warning' };
                    case 'error':
                        return { message: t('batchError', payload), type: 'notice-error' };
                    default:
                        return { message: '', type: 'notice-warning' };
                }
            };
            let statePollTimer = null;
            let statePollInFlight = false;
            let workerTickInFlight = false;
            let workerTickLastAt = 0;
            let refreshedCardIds = new Set();
            let refreshedCardAt = new Map();
            const stopStatePolling = function() {
                if (statePollTimer) {
                    window.clearTimeout(statePollTimer);
                    statePollTimer = null;
                }
                statePollInFlight = false;
            };
            const updateCardFromSnapshot = function(card, row) {
                if (!card || !row || typeof row !== 'object') return;
                const hasFeatured = parseInt(row.has_featured || 0, 10) === 1;
                const internalCount = Math.max(0, parseInt(row.internal_count || 0, 10) || 0);
                const thumbUrl = String(row.thumb || '');
                const normalizeScore = function(value) {
                    if (value === null || value === undefined || value === '') return null;
                    const score = parseInt(value, 10);
                    return Number.isFinite(score) ? Math.max(0, Math.min(100, score)) : null;
                };
                const updateScore = function(kind, value, label) {
                    const score = normalizeScore(value);
                    const state = score === null ? 'none' : (score >= 71 ? 'good' : (score >= 41 ? 'ok' : 'bad'));
                    const item = card.querySelector('[data-cbia-score="' + kind + '"]');
                    if (!item) return;
                    item.classList.remove('is-good', 'is-ok', 'is-bad', 'is-none');
                    item.classList.add('is-' + state);
                    item.title = label + ': ' + (score === null ? t('scoreUnavailable') : score + '/100');
                    const valueNode = item.querySelector('.cbia-oldv2-score-value');
                    if (valueNode) valueNode.textContent = score === null ? '–' : String(score);
                };
                card.setAttribute('data-post-has-featured', hasFeatured ? '1' : '0');
                card.setAttribute('data-post-has-internal', internalCount > 0 ? '1' : '0');
                card.setAttribute('data-post-internal-count', String(internalCount));
                card.setAttribute('data-post-seo-score', row.seo_score === null || row.seo_score === undefined ? '' : String(row.seo_score));
                card.setAttribute('data-post-readability-score', row.readability_score === null || row.readability_score === undefined ? '' : String(row.readability_score));
                updateScore('seo', row.seo_score, t('seoScore'));
                updateScore('readability', row.readability_score, t('readabilityScore'));

                const thumbWrap = card.querySelector('.cbia-oldv2-thumb');
                if (thumbWrap) {
                    let img = thumbWrap.querySelector('.cbia-oldv2-thumb-img');
                    let empty = thumbWrap.querySelector('.cbia-oldv2-thumb-empty');
                    if (thumbUrl !== '') {
                        if (!img) {
                            img = document.createElement('img');
                            img.className = 'cbia-oldv2-thumb-img';
                            img.setAttribute('alt', '');
                            img.setAttribute('loading', 'lazy');
                            thumbWrap.insertBefore(img, thumbWrap.firstChild);
                        }
                        const src = thumbUrl + (thumbUrl.indexOf('?') >= 0 ? '&' : '?') + '_cbia_ts=' + Date.now();
                        img.setAttribute('src', src);
                        if (empty) empty.remove();
                    } else {
                        if (img) img.remove();
                        if (!empty) {
                            empty = document.createElement('span');
                            empty.className = 'cbia-oldv2-thumb-empty';
                            empty.textContent = t('noFeaturedImage');
                            thumbWrap.insertBefore(empty, thumbWrap.firstChild);
                        }
                    }
                }

                const featuredBadge = card.querySelector('.cbia-oldv2-badge-featured');
                if (featuredBadge) {
                    featuredBadge.textContent = hasFeatured ? t('badgeFeatured') : t('badgeNoFeatured');
                }
                const internalBadge = card.querySelector('.cbia-oldv2-badge-internal');
                if (internalBadge) {
                    if (internalCount <= 0) {
                        internalBadge.textContent = t('badgeNoInternals');
                    } else if (internalCount === 1) {
                        internalBadge.textContent = t('badgeInternalOne', { count: internalCount });
                    } else {
                        internalBadge.textContent = t('badgeInternalMany', { count: internalCount });
                    }
                }
            };
            const refreshCardsForIds = async function(ids) {
                if (!ajaxReady || !pickerGrid || !Array.isArray(ids) || !ids.length) return;
                const cleanIds = Array.from(new Set(ids.map(function(id){ return parseInt(id || 0, 10) || 0; }).filter(function(id){ return id > 0; })));
                if (!cleanIds.length) return;
                try {
                    const fd = new FormData();
                    fd.set('post_ids', cleanIds.join(','));
                    const res = await postAjax('cbia_oldposts_cards_snapshot', fd, 30000);
                    if (!res || !res.success || !res.data || !Array.isArray(res.data.rows)) return;
                    res.data.rows.forEach(function(row){
                        const rowId = parseInt((row && row.id) || 0, 10) || 0;
                        if (rowId <= 0) return;
                        const card = pickerGrid.querySelector('.cbia-oldv2-post[data-post-id="' + rowId + '"]');
                        if (!card) return;
                        updateCardFromSnapshot(card, row);
                    });
                } catch (_e) {}
            };
            const syncUiFromRunState = function(state) {
                if (!state || typeof state !== 'object') return;
                const selectedBeforeSync = idsInput ? parseIdsCsv(idsInput.value || '') : [];
                const stateProcessedIds = Array.isArray(state.processed_ids)
                    ? state.processed_ids.map(function(id){ return parseInt(id || 0, 10) || 0; }).filter(function(id){ return id > 0; })
                    : [];
                stateProcessedIds.forEach(function(id){
                    processedRunIds.add(id);
                });
                if (pickerGrid) {
                    pickerGrid.querySelectorAll('.cbia-oldv2-post').forEach(function(card){
                        card.classList.remove('is-running');
                    });
                    stateProcessedIds.forEach(function(id){
                        const doneCard = pickerGrid.querySelector('.cbia-oldv2-post[data-post-id="' + id + '"]');
                        if (doneCard) doneCard.classList.add('is-processed');
                    });
                }
                if (state.ui_state && typeof state.ui_state === 'object') {
                    applyUiState(state.ui_state);
                }
                const status = String(state.status || '');
                const message = String(state.message || '');
                const lastChunkIds = Array.isArray(state.last_chunk_ids) ? state.last_chunk_ids : [];
                const idsToRefresh = [];
                lastChunkIds.forEach(function(id){
                    const cleanId = parseInt(id || 0, 10) || 0;
                    if (cleanId > 0) {
                        processedRunIds.add(cleanId);
                        if (pickerGrid && status === 'running') {
                            const runningCard = pickerGrid.querySelector('.cbia-oldv2-post[data-post-id="' + cleanId + '"]');
                            if (runningCard) runningCard.classList.add('is-running');
                        }
                        // Refresh cards once the chunk is no longer running (when data is final).
                        if (status !== 'running') {
                            const nowTs = Date.now();
                            const lastTs = parseInt(refreshedCardAt.get(cleanId) || 0, 10) || 0;
                            if ((nowTs - lastTs) > 1000) {
                                refreshedCardAt.set(cleanId, nowTs);
                                refreshedCardIds.add(cleanId);
                                idsToRefresh.push(cleanId);
                            }
                        } else if (!refreshedCardIds.has(cleanId)) {
                            idsToRefresh.push(cleanId);
                        }
                    }
                });
                if (idsToRefresh.length) {
                    refreshCardsForIds(idsToRefresh);
                }
                const stateInfo = formatStateMessage(state);
                setRuntimeStatus(stateInfo.message, stateInfo.type);
                setRunUiState(!!state.active);
                const totalPlanned = Math.max(0, parseInt(state.total || 0, 10) || 0);
                if (!state.active && (status === 'completed' || message === 'completed') && totalPlanned > 0) {
                    const idsToFinalize = Array.from(new Set([]
                        .concat(selectedBeforeSync)
                        .concat(Array.from(processedRunIds))
                        .concat(stateProcessedIds)
                    ));
                    if (idsToFinalize.length > 0) {
                        refreshCardsForIds(idsToFinalize);
                    }
                    clearPickerSelection();
                    processedRunIds.clear();
                    refreshedCardIds.clear();
                    refreshedCardAt.clear();
                } else if (!state.active && status === 'stopped') {
                    if (processedRunIds.size > 0) {
                        refreshCardsForIds(Array.from(processedRunIds));
                    }
                    removeIdsFromPicker(Array.from(processedRunIds));
                    if (lastChunkIds.length) {
                        refreshCardsForIds(lastChunkIds);
                        removeIdsFromPicker(lastChunkIds);
                    }
                    processedRunIds.clear();
                    refreshedCardIds.clear();
                    refreshedCardAt.clear();
                }
                updateProviderKeyWarning();
            };
            const fetchRunState = async function() {
                const fd = new FormData();
                const res = await postAjax('cbia_oldposts_get_run_state', fd, 30000);
                if (!res || !res.success) {
                    throw new Error((res && res.data && (res.data.msg || res.data.message)) || t('serverError'));
                }
                return (res.data && res.data.state) ? res.data.state : {};
            };
            const tickRunState = async function() {
                const fd = new FormData();
                const res = await postAjax('cbia_oldposts_tick_run', fd, 480000);
                if (!res || !res.success) {
                    throw new Error((res && res.data && (res.data.msg || res.data.message)) || t('serverError'));
                }
                return (res.data && res.data.state) ? res.data.state : {};
            };
            const triggerWorkerTick = function(force) {
                if (!ajaxReady) return;
                const now = Date.now();
                if (workerTickInFlight && (now - workerTickLastAt) > 45000) {
                    workerTickInFlight = false;
                }
                if (workerTickInFlight) return;
                if (!force && (now - workerTickLastAt) < 2200) return;
                workerTickInFlight = true;
                workerTickLastAt = now;
                tickRunState()
                    .then(function(state){
                        if (state && typeof state === 'object') {
                            syncUiFromRunState(state);
                        }
                        refreshLog();
                    })
                    .catch(function(){
                        workerTickLastAt = 0;
                    })
                    .finally(function(){
                        workerTickInFlight = false;
                    });
            };
            const startStatePolling = function() {
                stopStatePolling();
                const tick = async function() {
                    if (statePollInFlight) return;
                    statePollInFlight = true;
                    try {
                        const state = await fetchRunState();
                        syncUiFromRunState(state);
                        refreshLog();
                        if (state && state.active && String(state.status || '') === 'queued') {
                            triggerWorkerTick(false);
                        }
                        if (!state || !state.active) {
                            stopStatePolling();
                            return;
                        }
                    } catch (e) {
                        try { refreshLog(); } catch (_e) {}
                    } finally {
                        statePollInFlight = false;
                    }
                    statePollTimer = window.setTimeout(tick, 1200);
                };
                tick();
            };
            const startBackgroundRun = async function(action, submitter) {
                chunkRunStopRequested = false;
                processedRunIds.clear();
                refreshedCardIds.clear();
                refreshedCardAt.clear();
                setRunUiState(true);
                setRuntimeStatus(t('preparingQueue'), 'notice-warning');
                appendClientLog('RUN REQUESTED | action=' + String(action || 'run_oldposts'));
                refreshLog();
                persistUiState();
                stopStatePolling();
                try {
                    if (pickerGrid && idsInput) {
                        syncIdsFromPicker();
                    }
                    const prepareData = collectRunFormData(action);
                    const prepareRes = await postAjax('cbia_oldposts_prepare_run', prepareData, 30000);
                    if (!prepareRes || !prepareRes.success) {
                        throw new Error((prepareRes && prepareRes.data && (prepareRes.data.msg || prepareRes.data.message)) || t('serverError'));
                    }
                    const queueIds = Array.isArray(prepareRes.data && prepareRes.data.ids) ? prepareRes.data.ids : [];
                    if (!queueIds.length) {
                        processedRunIds.clear();
                        setRunUiState(false);
                        setRuntimeStatus(t('queueEmpty'), 'notice-info');
                        refreshLog();
                        return;
                    }
                    const state = (prepareRes.data && prepareRes.data.state && typeof prepareRes.data.state === 'object')
                        ? prepareRes.data.state
                        : null;
                    if (state) {
                        syncUiFromRunState(state);
                    } else {
                        setRuntimeStatus(t('batchQueued', {
                            processed: 0,
                            total: queueIds.length,
                            ok: 0,
                            skipped: 0,
                            fail: 0
                        }), 'notice-warning');
                    }
                    startStatePolling();
                    window.setTimeout(refreshLog, 200);
                    window.setTimeout(function(){ triggerWorkerTick(true); }, 120);
                    window.setTimeout(function(){ triggerWorkerTick(true); }, 900);
                    window.setTimeout(function(){ triggerWorkerTick(true); }, 2200);
                } catch (error) {
                    const existingState = error && error.data && error.data.state ? error.data.state : null;
                    if (existingState) {
                        syncUiFromRunState(existingState);
                        startStatePolling();
                        setRuntimeStatus(t('runningElsewhere'), 'notice-warning');
                        refreshLog();
                        return;
                    }
                    const status = error && error.message ? String(error.message) : t('serverError');
                    setRuntimeStatus(t('batchError', { message: status }), 'notice-error');
                    setRunUiState(false);
                    refreshLog();
                    if (actionsForm) {
                        bypassAjaxRunOnce = true;
                        if (typeof actionsForm.requestSubmit === 'function' && submitter) {
                            actionsForm.requestSubmit(submitter);
                        } else if (typeof actionsForm.submit === 'function') {
                            actionsForm.submit();
                        }
                    }
                }
            };
            function computeFlags() {
                return {
                    doContent: isChecked('run_do_content'),
                    doContentNoImages: isChecked('run_do_content_no_images'),
                    doTitle: isChecked('run_do_title'),
                    doImagesReset: isChecked('run_do_images_reset'),
                    doImagesContentOnly: isChecked('run_do_images_content_only'),
                    doFeaturedOnly: isChecked('run_do_featured_only'),
                };
            }

            updateSummary = function() {
                const flags = computeFlags();
                const actions = [];
                if (flags.doContent) actions.push(t('actionContent'));
                if (flags.doContentNoImages) actions.push(t('actionContentNoImages'));
                if (flags.doTitle) actions.push(t('actionTitle'));
                if (flags.doImagesReset) actions.push(t('actionImagesReset'));
                if (flags.doImagesContentOnly) actions.push(t('actionImagesContentOnly'));
                if (flags.doFeaturedOnly) actions.push(t('actionFeaturedOnly'));
                if (actions.length === 0) actions.push(t('actionNoAi'));

                const ids = getValue('run_post_ids');
                const cat = getValue('run_category_id');
                const author = getValue('run_author_id');
                const dryRun = isChecked('run_dry_run');
                const tpl = getValue('run_post_length_variant') || 'medium';
                const textProvider = getValue('run_text_provider') || '';
                const textModel = getValue('run_text_model') || '';
                const imageProvider = getValue('run_image_provider') || '';
                const imageModel = getValue('run_image_model') || '';

                const filters = [];
                filters.push(t('allPosts'));
                if (ids) filters.push(t('idsPrefix') + ' ' + ids);
                if (!ids && cat && cat !== '0') filters.push(t('categoryPrefix') + cat);
                if (!ids && author && author !== '0') filters.push(t('authorPrefix') + author);
                if (dryRun) filters.push(t('dryRunTag'));

                const runtime = [];
                runtime.push(t('templatePrefix') + tpl);
                if (textProvider) runtime.push(t('textPrefix') + (providerLabels[textProvider] || textProvider) + (textModel ? ('/' + textModel) : ''));
                if (imageProvider) runtime.push(t('imagePrefix') + (providerLabels[imageProvider] || imageProvider) + (imageModel ? ('/' + imageModel) : ''));

                summary.style.display = '';
                summary.innerHTML =
                    '<p style="margin:0;"><strong>' + t('summaryLabel') + '</strong> ' +
                    actions.join(', ') +
                    ' | ' +
                    filters.join(' Â· ') +
                    ' | ' +
                    runtime.join(' | ') +
                    '</p>';
                summary.innerHTML = summary.innerHTML.replace(/Ã‚Â·/g, 'Â·');
            };
            actionsForm.addEventListener('change', updateSummary);
            actionsForm.addEventListener('change', updateCompactSidebar);
            actionsForm.addEventListener('change', updateProviderKeyWarning);
            actionsForm.addEventListener('change', persistUiState);
            updateSummary();
            updateCompactSidebar();
            updateProviderKeyWarning();

            const clearLogBtn = actionsForm.querySelector('button[name="cbia_action"][value="clear_log"]');
            if (clearLogBtn) {
                clearLogBtn.addEventListener('click', function(ev) {
                    if (typeof ajaxurl === 'undefined') return;
                    const nonce = (window.CBIA && CBIA.nonce) ? CBIA.nonce : '';
                    if (!nonce) return;
                    ev.preventDefault();
                    const payload = new URLSearchParams();
                    payload.set('action', 'cbia_clear_oldposts_log');
                    payload.set('_ajax_nonce', nonce);
                    clearLogBtn.disabled = true;
                    fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: payload.toString()
                    })
                    .then(r => r.json().catch(() => null))
                    .then(data => {
                        if (!data || !data.success) throw new Error('clear_failed');
                        if (logBox) logBox.value = '';
                        setTimeout(refreshLog, 120);
                    })
                    .catch(() => {
                        if (typeof actionsForm.requestSubmit === 'function') {
                            actionsForm.requestSubmit(clearLogBtn);
                        } else {
                            actionsForm.submit();
                        }
                    })
                    .finally(() => {
                        clearLogBtn.disabled = false;
                    });
                });
            }

            actionsForm.addEventListener('submit', function(e) {
                const submitter = e.submitter || document.activeElement;
                const action = resolveSubmitAction(submitter);
                if (action === 'filter_oldposts_picker') {
                    persistFilterState();
                    return;
                }
                if (action !== 'run_oldposts' && action.indexOf('run_quick_') !== 0) return;
                const flags = computeFlags();
                const aiRisk = flags.doContent || flags.doContentNoImages || flags.doTitle || flags.doImagesReset || flags.doImagesContentOnly || flags.doFeaturedOnly;
                if (aiRisk) {
                    const ok = window.confirm(t('confirmAiCredits'));
                    if (!ok) {
                        e.preventDefault();
                        return;
                    }
                }
                if (!ajaxReady) return;
                if (bypassAjaxRunOnce) {
                    bypassAjaxRunOnce = false;
                    return;
                }
                if (e.defaultPrevented) return;
                e.preventDefault();
                startBackgroundRun(action, submitter);
            });
            if (stopBtn) {
                stopBtn.addEventListener('click', function(ev) {
                    if (!chunkRunActive) return;
                    ev.preventDefault();
                    chunkRunStopRequested = true;
                    setRuntimeStatus(t('stopRequested'), 'notice-warning');
                    if (typeof ajaxurl === 'undefined') return;
                    const payload = new URLSearchParams();
                    payload.set('action', 'cbia_set_stop');
                    payload.set('stop', '1');
                    if (nonce) payload.set('_ajax_nonce', nonce);
                    fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: payload.toString()
                    }).then(function(){
                        refreshLog();
                    }).catch(() => {});
                });
            }
            const bootState = activeRunStateBoot && typeof activeRunStateBoot === 'object' ? activeRunStateBoot : {};
            const persistedStateRaw = readPersistedUiState();
            const persistedFilterState = readPersistedFilterState();
            const persistedState = Object.assign({}, persistedStateRaw);
            if (!bootState.active) {
                delete persistedState.run_text_provider;
                delete persistedState.run_text_model;
                delete persistedState.run_image_provider;
                delete persistedState.run_image_model;
            }
            if (bootState.ui_state && Object.keys(bootState.ui_state).length) {
                applyUiState(bootState.ui_state);
            } else if (Object.keys(persistedState).length) {
                applyUiState(persistedState);
            }
            if (!bootState.active && persistedFilterState && Object.keys(persistedFilterState).length) {
                applyFilterState(persistedFilterState);
            }
            if (bootState && (bootState.active || bootState.status)) {
                syncUiFromRunState(bootState);
                if (bootState.active) {
                    startStatePolling();
                }
            }
        }
        updateCompactSidebar();
        updateProviderKeyWarning();

        // Auto-refresh log
        const logBox = document.getElementById('cbia-oldposts-log');
                function refreshLog(){
                    if (typeof ajaxurl === 'undefined') return;
                    const nonce = (window.CBIA && CBIA.nonce) ? CBIA.nonce : '';
                    const url = ajaxurl
                        + '?action=cbia_get_oldposts_log'
                        + (nonce ? '&_ajax_nonce=' + encodeURIComponent(nonce) : '')
                        + '&_cbia_ts=' + Date.now();
                    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                        .then(r => r.text())
                        .then(raw => {
                            const textRaw = String(raw || '');
                            let data = null;
                            try {
                                data = textRaw ? JSON.parse(textRaw) : null;
                            } catch (e) {
                                const start = textRaw.indexOf('{"success"');
                                if (start >= 0) {
                                    const candidate = textRaw.slice(start);
                                    try {
                                        data = JSON.parse(candidate);
                                    } catch (_e) {
                                        data = null;
                                    }
                                }
                            }
                            if (data && data.success && logBox) {
                                const payload = data.data;
                                let text = '';
                                if (typeof payload === 'string') {
                                    text = payload;
                                } else if (payload && typeof payload === 'object') {
                                    if (typeof payload.log === 'string') {
                                        text = payload.log;
                                    } else if (typeof payload.message === 'string') {
                                        text = payload.message;
                                    }
                                }
                                logBox.value = text;
                                logBox.scrollTop = logBox.scrollHeight;
                                return;
                            }
                            if (logBox && textRaw) {
                                const plain = textRaw.replace(/<[^>]+>/g, '').trim();
                                if (plain && /\[(INFO|WARN|ERROR)\]/.test(plain)) {
                                    logBox.value = plain;
                                    logBox.scrollTop = logBox.scrollHeight;
                                }
                            }
                        })
                .catch(() => {});
        }
        refreshLog();
        setInterval(refreshLog, 1200);
    });
    <?php wp_add_inline_script('abb-admin', (string) ob_get_clean(), 'after'); ?>
</div>
