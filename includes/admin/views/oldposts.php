<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Oldposts tab view (extracted from legacy cbia-oldposts.php)

if (!function_exists('cbia_render_view_oldposts')) {
    function cbia_render_view_oldposts() {
if (!current_user_can('manage_options')) return;

$service = isset($cbia_oldposts_service) ? $cbia_oldposts_service : null;
$settings = $service && method_exists($service, 'get_settings')
    ? $service->get_settings()
    : cbia_oldposts_get_settings();

// Defaults (presets)
$defaults = array(
    'batch_size'         => 20,
    'scope'              => 'all',

    'filter_mode'        => 'older',
    'older_than_days'    => 180,
    'date_from'          => '',
    'date_to'            => '',

    'images_limit'       => 3,
    'post_ids'           => '',
    'category_id'        => 0,
    'author_id'          => 0,
    'dry_run'            => 0,

    // Recommended baseline (the setup you will usually use)
    'do_note'            => 1,
    'force_note'         => 0,

    'do_yoast_metadesc'  => 1,
    'do_yoast_focuskw'   => 1,
    'do_yoast_title'     => 0,
    'force_yoast'        => 0,

    'do_yoast_reindex'   => 1,

    'do_title'           => 0,
    'force_title'        => 0,

    'do_content'         => 1,
    'force_content'      => 0,
    'do_content_no_images'    => 0,
    'force_content_no_images' => 0,

    'do_images_reset'    => 1,
    'force_images_reset' => 0,
    'clear_featured'     => 0,
    'do_images_content_only'    => 0,
    'force_images_content_only' => 0,
    'do_featured_only'          => 0,
    'force_featured_only'       => 0,
    'featured_remove_old'       => 0,

    'do_categories'      => 1,
    'force_categories'   => 0,

    'do_tags'            => 1,
    'force_tags'         => 0,
);
$settings = array_merge($defaults, is_array($settings) ? $settings : array());

// Soft migration from v2 when old keys exist
if (isset($settings['do_yoast_metas']) && !isset($settings['do_yoast_metadesc'])) {
    $val = !empty($settings['do_yoast_metas']) ? 1 : 0;
    $settings['do_yoast_metadesc'] = $val;
    $settings['do_yoast_focuskw']  = $val;
}
if (isset($settings['force_yoast_metas']) && !isset($settings['force_yoast'])) {
    $settings['force_yoast'] = !empty($settings['force_yoast_metas']) ? 1 : 0;
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
$fm = $settings['filter_mode'];

// Default actions summary (UX helper)
$defaults_summary = array();
if (!empty($settings['do_note'])) $defaults_summary[] = 'Updated note';
if (!empty($settings['do_yoast_metadesc']) || !empty($settings['do_yoast_focuskw']) || !empty($settings['do_yoast_title'])) {
    $ys = array();
    if (!empty($settings['do_yoast_metadesc'])) $ys[] = 'metadesc';
    if (!empty($settings['do_yoast_focuskw']))  $ys[] = 'keyphrase';
    if (!empty($settings['do_yoast_title']))    $ys[] = 'seo title';
    $defaults_summary[] = 'Yoast: '.implode(', ', $ys);
}
if (!empty($settings['do_yoast_reindex'])) $defaults_summary[] = 'Yoast reindex';
if (!empty($settings['do_content'])) $defaults_summary[] = 'AI content';
if (!empty($settings['do_content_no_images'])) $defaults_summary[] = 'AI content (without images)';
if (!empty($settings['do_images_reset'])) $defaults_summary[] = 'Images pending';
if (!empty($settings['do_images_content_only'])) $defaults_summary[] = 'Content images';
if (!empty($settings['do_featured_only'])) $defaults_summary[] = 'Featured only';
if (!empty($settings['do_categories'])) $defaults_summary[] = 'Categories';
if (!empty($settings['do_tags'])) $defaults_summary[] = 'Tags';
if (!empty($settings['do_title'])) $defaults_summary[] = 'AI title';

$defaults_summary_text = !empty($defaults_summary) ? implode(' - ', $defaults_summary) : 'No default actions';

?>
<div class="wrap" style="padding-left:0;">
    <h2><?php echo esc_html__('Update old posts', 'cbiastudio-blogflow-ai'); ?></h2>

    <h3><?php echo esc_html__('Configuration (defaults)', 'cbiastudio-blogflow-ai'); ?></h3>
    <p class="description" style="max-width:980px;">
        <?php echo esc_html__('This defines your usual setup. During execution, you can use it as-is or customize only this run.', 'cbiastudio-blogflow-ai'); ?>
        <br><strong><?php echo esc_html__('What "Force" means:', 'cbiastudio-blogflow-ai'); ?></strong> <?php echo esc_html__('rerun the action even if it already exists / is marked as done.', 'cbiastudio-blogflow-ai'); ?>
    </p>

    <form method="post" action="" autocomplete="off">
        <input type="hidden" name="cbia_form" value="oldposts_settings" />
        <?php wp_nonce_field('cbia_oldposts_settings_nonce'); ?>

        <table class="form-table" style="max-width:980px;">
            <tr>
                <th><?php echo esc_html__('Scope', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <label style="margin-right:18px;">
                        <input type="radio" name="scope" value="all" <?php checked($settings['scope'], 'all'); ?> />
                        <?php echo esc_html__('All posts', 'cbiastudio-blogflow-ai'); ?>
                    </label>
                    <label>
                        <input type="radio" name="scope" value="plugin" <?php checked($settings['scope'], 'plugin'); ?> />
                        <?php echo esc_html__('Plugin posts only', 'cbiastudio-blogflow-ai'); ?> (<code>_cbia_created=1</code>)
                    </label>
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Batch size', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <input type="number" name="batch_size" min="1" max="200" value="<?php echo esc_attr((int)$settings['batch_size']); ?>" style="width:120px;" />
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Date filter', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <label style="margin-right:18px;">
                        <input type="radio" name="filter_mode" value="older" <?php checked($fm, 'older'); ?> />
                        <?php echo esc_html__('Older than (days)', 'cbiastudio-blogflow-ai'); ?>
                    </label>
                    <label>
                        <input type="radio" name="filter_mode" value="range" <?php checked($fm, 'range'); ?> />
                        <?php echo esc_html__('Range (from / to)', 'cbiastudio-blogflow-ai'); ?>
                    </label>

                    <div style="margin-top:10px;">
                        <div id="cbia_old_filter_older" style="<?php echo ($fm==='older'?'':'display:none;'); ?>">
                            <input type="number" name="older_than_days" min="1" value="<?php echo esc_attr((int)$settings['older_than_days']); ?>" style="width:120px;" />
                            <span class="description"><?php echo esc_html__('Example: 180', 'cbiastudio-blogflow-ai'); ?></span>
                        </div>

                        <div id="cbia_old_filter_range" style="<?php echo ($fm==='range'?'':'display:none;'); ?>">
                            <label style="margin-right:10px;">
                                <?php echo esc_html__('From:', 'cbiastudio-blogflow-ai'); ?>
                                <input type="date" name="date_from" value="<?php echo esc_attr((string)$settings['date_from']); ?>" />
                            </label>
                            <label>
                                <?php echo esc_html__('To:', 'cbiastudio-blogflow-ai'); ?>
                                <input type="date" name="date_to" value="<?php echo esc_attr((string)$settings['date_to']); ?>" />
                            </label>
                            <p class="description"><?php echo esc_html__('Uses post_date_gmt. If one side is empty, only the other limit is applied.', 'cbiastudio-blogflow-ai'); ?></p>
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Images (limit)', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <input type="number" name="images_limit" min="1" max="10" value="<?php echo esc_attr((int)$settings['images_limit']); ?>" style="width:120px;" />
                    <p class="description"><?php echo esc_html__('Used for content regeneration and/or pending images reset.', 'cbiastudio-blogflow-ai'); ?></p>
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Advanced filters', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <div style="margin-bottom:8px;">
                        <label>
                            <?php echo esc_html__('Specific IDs (optional):', 'cbiastudio-blogflow-ai'); ?>
                            <input
                                type="text"
                                name="run_post_ids"
                                value="<?php echo esc_attr((string)($settings['post_ids'] ?? '')); ?>"
                                placeholder="123,456"
                                style="width:420px;"
                            />
                        </label>
                        <p class="description" style="margin:4px 0 0;">
                            <?php echo esc_html__('If IDs are provided, date filters are ignored.', 'cbiastudio-blogflow-ai'); ?>
                        </p>
                    </div>

                    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                        <label>
                            <?php echo esc_html__('Category:', 'cbiastudio-blogflow-ai'); ?>
                            <?php
                            wp_dropdown_categories(array(
                                'taxonomy' => 'category',
                                'hide_empty' => false,
                                'name' => 'category_id',
                                'id' => 'cbia_category_id',
                                'selected' => (int)($settings['category_id'] ?? 0),
                                'show_option_all' => __('All', 'cbiastudio-blogflow-ai'),
                            ));
                            ?>
                        </label>

                        <label>
                            <?php echo esc_html__('Author:', 'cbiastudio-blogflow-ai'); ?>
                            <?php
                            wp_dropdown_users(array(
                                'name' => 'author_id',
                                'id' => 'cbia_author_id',
                                'selected' => (int)($settings['author_id'] ?? 0),
                                'show_option_all' => __('All', 'cbiastudio-blogflow-ai'),
                            ));
                            ?>
                        </label>

                        <label>
                            <input type="checkbox" name="dry_run" value="1" <?php checked((int)($settings['dry_run'] ?? 0), 1); ?> />
                            <?php echo esc_html__('Default dry run (list only)', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                    </div>
                </td>
            </tr>


            <tr>
                <th><?php echo esc_html__('Default actions', 'cbiastudio-blogflow-ai'); ?></th>
                <td style="padding-top:12px;">
                    <div style="padding:12px;border:1px solid #ddd;border-radius:8px;background:#fff;">
                        <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html__('Basic', 'cbiastudio-blogflow-ai'); ?></div>

                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_note" value="1" <?php checked((int)$settings['do_note'], 1); ?> />
                            <?php echo esc_html__('Add note "Updated on ..."', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_note" value="1" <?php checked((int)$settings['force_note'], 1); ?> />
                                    <?php echo esc_html__('Force (replace date if it already exists)', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Yoast SEO', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="do_yoast_metadesc" value="1" <?php checked((int)$settings['do_yoast_metadesc'], 1); ?> />
                            <?php echo esc_html__('Meta description', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="do_yoast_focuskw" value="1" <?php checked((int)$settings['do_yoast_focuskw'], 1); ?> />
                            <?php echo esc_html__('Keyphrase (focus keyword)', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="do_yoast_title" value="1" <?php checked((int)$settings['do_yoast_title'], 1); ?> />
                            <?php echo esc_html__('SEO title (Yoast title)', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin:8px 0;">
                            <input type="checkbox" name="force_yoast" value="1" <?php checked((int)$settings['force_yoast'], 1); ?> />
                            <?php echo esc_html__('Force Yoast (overwrite even if values exist)', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_yoast_reindex" value="1" <?php checked((int)$settings['do_yoast_reindex'], 1); ?> />
                            <?php echo esc_html__('Reindex / traffic lights (best effort)', 'cbiastudio-blogflow-ai'); ?>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Content and images', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_content" value="1" <?php checked((int)$settings['do_content'], 1); ?> />
                            <?php echo esc_html__('Regenerate content with AI', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_content" value="1" <?php checked((int)$settings['force_content'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_content_no_images" value="1" <?php checked((int)$settings['do_content_no_images'], 1); ?> />
                            <?php echo esc_html__('Regenerate content with AI (without images)', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_content_no_images" value="1" <?php checked((int)$settings['force_content_no_images'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_images_reset" value="1" <?php checked((int)$settings['do_images_reset'], 1); ?> />
                            <?php echo esc_html__('Images: mark as pending (reset)', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_images_reset" value="1" <?php checked((int)$settings['force_images_reset'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="clear_featured" value="1" <?php checked((int)$settings['clear_featured'], 1); ?> />
                                    <?php echo esc_html__('Remove featured image (not common)', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_images_content_only" value="1" <?php checked((int)$settings['do_images_content_only'], 1); ?> />
                            <?php echo esc_html__('Images: regenerate content images only', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_images_content_only" value="1" <?php checked((int)$settings['force_images_content_only'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_featured_only" value="1" <?php checked((int)$settings['do_featured_only'], 1); ?> />
                            <?php echo esc_html__('Featured image: regenerate featured only', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_featured_only" value="1" <?php checked((int)$settings['force_featured_only'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="featured_remove_old" value="1" <?php checked((int)$settings['featured_remove_old'], 1); ?> />
                                    <?php echo esc_html__('Remove previous featured image', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Taxonomies', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_categories" value="1" <?php checked((int)$settings['do_categories'], 1); ?> />
                            <?php echo esc_html__('Recalculate categories', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_categories" value="1" <?php checked((int)$settings['force_categories'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_tags" value="1" <?php checked((int)$settings['do_tags'], 1); ?> />
                            <?php echo esc_html__('Recalculate tags', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_tags" value="1" <?php checked((int)$settings['force_tags'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Optional', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="do_title" value="1" <?php checked((int)$settings['do_title'], 1); ?> />
                            <?php echo esc_html__('Optimize title with AI', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="force_title" value="1" <?php checked((int)$settings['force_title'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <p class="description" style="margin-top:10px;">
                            <?php echo esc_html__('Tip: if you regenerate content, you will usually also want categories/tags + Yoast.', 'cbiastudio-blogflow-ai'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        <p>
            <button type="submit" class="button button-primary"><?php echo esc_html__('Save configuration', 'cbiastudio-blogflow-ai'); ?></button>
        </p>
    </form>

    <hr />

    <h3><?php echo esc_html__('Execution', 'cbiastudio-blogflow-ai'); ?></h3>
    <p class="description" style="max-width:980px;">
        <?php echo esc_html__('By default, this runs with your saved default selection:', 'cbiastudio-blogflow-ai'); ?>
        <strong><?php echo esc_html($defaults_summary_text); ?></strong>
    </p>

    <form method="post" action="" autocomplete="off">
        <input type="hidden" name="cbia_form" value="oldposts_actions" />
        <?php wp_nonce_field('cbia_oldposts_actions_nonce'); ?>

        <table class="form-table" style="max-width:980px;">
            <tr>
                <th><?php echo esc_html__('Batch', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <input type="number" name="run_batch_size" min="1" max="200" value="<?php echo esc_attr((int)$settings['batch_size']); ?>" style="width:120px;" />
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Scope', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="run_scope_plugin" value="1" <?php checked($settings['scope'], 'plugin'); ?> />
                        <?php echo esc_html__('Process only', 'cbiastudio-blogflow-ai'); ?> <code>_cbia_created=1</code>
                    </label>
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Filter', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <label style="margin-right:18px;">
                        <input type="radio" name="run_filter_mode" value="older" <?php checked($settings['filter_mode'], 'older'); ?> />
                        <?php echo esc_html__('Older than (days)', 'cbiastudio-blogflow-ai'); ?>
                    </label>
                    <label>
                        <input type="radio" name="run_filter_mode" value="range" <?php checked($settings['filter_mode'], 'range'); ?> />
                        <?php echo esc_html__('Range', 'cbiastudio-blogflow-ai'); ?>
                    </label>

                    <div style="margin-top:10px;">
                        <div id="cbia_run_filter_older" style="<?php echo ($settings['filter_mode']==='older'?'':'display:none;'); ?>">
                            <input type="number" name="run_older_than_days" min="1" value="<?php echo esc_attr((int)$settings['older_than_days']); ?>" style="width:120px;" />
                        </div>

                        <div id="cbia_run_filter_range" style="<?php echo ($settings['filter_mode']==='range'?'':'display:none;'); ?>">
                            <label style="margin-right:10px;">
                                <?php echo esc_html__('From:', 'cbiastudio-blogflow-ai'); ?>
                                <input type="date" name="run_date_from" value="<?php echo esc_attr((string)$settings['date_from']); ?>" />
                            </label>
                            <label>
                                <?php echo esc_html__('To:', 'cbiastudio-blogflow-ai'); ?>
                                <input type="date" name="run_date_to" value="<?php echo esc_attr((string)$settings['date_to']); ?>" />
                            </label>
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Images (limit)', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <input type="number" name="run_images_limit" min="1" max="10" value="<?php echo esc_attr((int)$settings['images_limit']); ?>" style="width:120px;" />
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Advanced filters', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <div style="margin-bottom:8px;">
                        <label>
                            <?php echo esc_html__('Specific IDs (optional):', 'cbiastudio-blogflow-ai'); ?>
                            <input
                                type="text"
                                name="post_ids"
                                value="<?php echo esc_attr((string)($settings['post_ids'] ?? '')); ?>"
                                placeholder="123,456"
                                style="width:420px;"
                            />
                        </label>
                        <p class="description" style="margin:4px 0 0;">
                            <?php echo esc_html__('If IDs are provided, date filters are ignored.', 'cbiastudio-blogflow-ai'); ?>
                        </p>
                    </div>

                    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                        <label>
                            <?php echo esc_html__('Category:', 'cbiastudio-blogflow-ai'); ?>
                            <?php
                            wp_dropdown_categories(array(
                                'taxonomy' => 'category',
                                'hide_empty' => false,
                                'name' => 'run_category_id',
                                'id' => 'cbia_run_category_id',
                                'selected' => (int)($settings['category_id'] ?? 0),
                                'show_option_all' => __('All', 'cbiastudio-blogflow-ai'),
                            ));
                            ?>
                        </label>

                        <label>
                            <?php echo esc_html__('Author:', 'cbiastudio-blogflow-ai'); ?>
                            <?php
                            wp_dropdown_users(array(
                                'name' => 'run_author_id',
                                'id' => 'cbia_run_author_id',
                                'selected' => (int)($settings['author_id'] ?? 0),
                                'show_option_all' => __('All', 'cbiastudio-blogflow-ai'),
                            ));
                            ?>
                        </label>

                        <label>
                            <input type="checkbox" name="run_dry_run" value="1" <?php checked((int)($settings['dry_run'] ?? 0), 1); ?> />
                            <?php echo esc_html__('Dry run (list only)', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                    </div>
                </td>
            </tr>

            <tr>
                <th><?php echo esc_html__('Customize this run', 'cbiastudio-blogflow-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="run_custom_actions" id="cbia_run_custom_actions" value="1" />
                        <?php echo esc_html__('I want to choose actions different from my defaults (this run only)', 'cbiastudio-blogflow-ai'); ?>
                    </label>

                    <div id="cbia_run_custom_box" style="display:none;margin-top:12px;padding:12px;border:1px solid #ddd;border-radius:8px;background:#fff;">
                        <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html__('Actions (this run only)', 'cbiastudio-blogflow-ai'); ?></div>

                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="run_do_note" value="1" <?php checked((int)$settings['do_note'], 1); ?> />
                            <?php echo esc_html__('Note "Updated on ..."', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="run_force_note" value="1" <?php checked((int)$settings['force_note'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Yoast SEO', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="run_do_yoast_metadesc" value="1" <?php checked((int)$settings['do_yoast_metadesc'], 1); ?> />
                            <?php echo esc_html__('Meta description', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="run_do_yoast_focuskw" value="1" <?php checked((int)$settings['do_yoast_focuskw'], 1); ?> />
                            <?php echo esc_html__('Keyphrase (focus keyword)', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="run_do_yoast_title" value="1" <?php checked((int)$settings['do_yoast_title'], 1); ?> />
                            <?php echo esc_html__('SEO title (Yoast title)', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin:8px 0;">
                            <input type="checkbox" name="run_force_yoast" value="1" <?php checked((int)$settings['force_yoast'], 1); ?> />
                            <?php echo esc_html__('Force Yoast', 'cbiastudio-blogflow-ai'); ?>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="run_do_yoast_reindex" value="1" <?php checked((int)$settings['do_yoast_reindex'], 1); ?> />
                            <?php echo esc_html__('Reindex / traffic lights (best effort)', 'cbiastudio-blogflow-ai'); ?>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Content and images', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="run_do_content" value="1" <?php checked((int)$settings['do_content'], 1); ?> />
                            <?php echo esc_html__('Content (AI)', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="run_force_content" value="1" <?php checked((int)$settings['force_content'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="run_do_images_reset" value="1" <?php checked((int)$settings['do_images_reset'], 1); ?> />
                            <?php echo esc_html__('Images: reset pending', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="run_force_images_reset" value="1" <?php checked((int)$settings['force_images_reset'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="run_clear_featured" value="1" <?php checked((int)$settings['clear_featured'], 1); ?> />
                                    <?php echo esc_html__('Remove featured image', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Taxonomies', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="run_do_categories" value="1" <?php checked((int)$settings['do_categories'], 1); ?> />
                            <?php echo esc_html__('Categories', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="run_force_categories" value="1" <?php checked((int)$settings['force_categories'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="run_do_tags" value="1" <?php checked((int)$settings['do_tags'], 1); ?> />
                            <?php echo esc_html__('Tags', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="run_force_tags" value="1" <?php checked((int)$settings['force_tags'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <div style="font-weight:600;margin:12px 0 8px;"><?php echo esc_html__('Optional', 'cbiastudio-blogflow-ai'); ?></div>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="run_do_title" value="1" <?php checked((int)$settings['do_title'], 1); ?> />
                            <?php echo esc_html__('Title (AI)', 'cbiastudio-blogflow-ai'); ?>
                            <span style="margin-left:14px;">
                                <label>
                                    <input type="checkbox" name="run_force_title" value="1" <?php checked((int)$settings['force_title'], 1); ?> />
                                    <?php echo esc_html__('Force', 'cbiastudio-blogflow-ai'); ?>
                                </label>
                            </span>
                        </label>

                        <p class="description" style="margin-top:10px;">
                            <?php echo esc_html__('If you do not enable "Customize", your default actions will be used as-is.', 'cbiastudio-blogflow-ai'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        <div
            id="cbia-oldposts-summary"
            class="notice notice-info"
            style="max-width:980px;margin:8px 0 12px;display:none;"
            data-default-do-content="<?php echo (int)$settings['do_content']; ?>"
            data-default-do-content-no-images="<?php echo (int)($settings['do_content_no_images'] ?? 0); ?>"
            data-default-do-title="<?php echo (int)$settings['do_title']; ?>"
            data-default-do-images-reset="<?php echo (int)$settings['do_images_reset']; ?>"
            data-default-do-images-content-only="<?php echo (int)($settings['do_images_content_only'] ?? 0); ?>"
            data-default-do-featured-only="<?php echo (int)($settings['do_featured_only'] ?? 0); ?>"
        ></div>

        <div style="margin:6px 0 10px;">
            <span class="description" style="margin-right:8px;"><strong><?php echo esc_html__('Quick actions:', 'cbiastudio-blogflow-ai'); ?></strong></span>
            <button type="submit" class="button" name="cbia_action" value="run_quick_yoast_metas"><?php echo esc_html__('Yoast metas only', 'cbiastudio-blogflow-ai'); ?></button>
            <button type="submit" class="button" name="cbia_action" value="run_quick_yoast_reindex" style="margin-left:6px;"><?php echo esc_html__('Yoast reindex only', 'cbiastudio-blogflow-ai'); ?></button>
            <button type="submit" class="button" name="cbia_action" value="run_quick_featured" style="margin-left:6px;"><?php echo esc_html__('Featured only', 'cbiastudio-blogflow-ai'); ?></button>
            <button type="submit" class="button" name="cbia_action" value="run_quick_images_only" style="margin-left:6px;"><?php echo esc_html__('Content images only', 'cbiastudio-blogflow-ai'); ?></button>
            <button type="submit" class="button" name="cbia_action" value="run_quick_content_only" style="margin-left:6px;"><?php echo esc_html__('Content only (without images)', 'cbiastudio-blogflow-ai'); ?></button>

            <span style="margin-left:12px;">
                <label style="margin-right:8px;">
                    <input type="checkbox" name="run_featured_remove_old" value="1" />
                    <?php echo esc_html__('Remove previous featured image', 'cbiastudio-blogflow-ai'); ?>
                </label>
                <label style="margin-right:8px;">
                    <input type="checkbox" name="run_force_images_content_only" value="1" />
                    <?php echo esc_html__('Force images', 'cbiastudio-blogflow-ai'); ?>
                </label>
                <label>
                    <input type="checkbox" name="run_force_content_no_images" value="1" />
                    <?php echo esc_html__('Force content', 'cbiastudio-blogflow-ai'); ?>
                </label>
            </span>
        </div>

        <p>
            <button type="submit" class="button button-primary" name="cbia_action" value="run_oldposts">
                <?php echo esc_html__('Run batch', 'cbiastudio-blogflow-ai'); ?>
            </button>

            <button type="submit" class="button" name="cbia_action" value="stop" style="margin-left:8px;background:#b70000;color:#fff;">
                <?php echo esc_html__('Stop', 'cbiastudio-blogflow-ai'); ?>
            </button>

            <button type="submit" class="button button-secondary" name="cbia_action" value="clear_log" style="margin-left:8px;">
                <?php echo esc_html__('Clear log', 'cbiastudio-blogflow-ai'); ?>
            </button>
        </p>
    </form>

    <h3><?php echo esc_html__('Log', 'cbiastudio-blogflow-ai'); ?></h3>
    <textarea id="cbia-oldposts-log" rows="14" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea($log); ?></textarea>

    <?php
    $cbia_oldposts_js = <<<'JS'
    document.addEventListener('DOMContentLoaded', function() {
        // Defensive mojibake fix: only improves UI readability.
        function tryDecodeLatin1ToUtf8(str) {
            try {
                // Typical pattern: UTF-8 interpreted as Latin-1.
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
                // Some fragments are double-broken.
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
                }else{
                    if(olderBox) olderBox.style.display='';
                    if(rangeBox) rangeBox.style.display='none';
                }
            }));
        }
        bindFilterToggles('cbia_old');
        bindFilterToggles('cbia_run');

        const custom = document.getElementById('cbia_run_custom_actions');
        const box = document.getElementById('cbia_run_custom_box');
        if (custom && box) {
            custom.addEventListener('change', function(){
                box.style.display = this.checked ? '' : 'none';
            });
        }

        // Summary + confirmation before execution
        const summary = document.getElementById('cbia-oldposts-summary');
        const actionsForm = summary ? summary.closest('form') : null;
        if (summary && actionsForm) {
            const getDefaultFlag = (key) => {
                const v = summary.dataset[key];
                return v === '1';
            };
            const isChecked = (name) => {
                const el = actionsForm.querySelector('[name="' + name + '"]');
                return !!el && !!el.checked;
            };
            const getValue = (name) => {
                const el = actionsForm.querySelector('[name="' + name + '"]');
                return el ? String(el.value || '').trim() : '';
            };

            function computeFlags() {
                const customOn = isChecked('run_custom_actions');
                if (customOn) {
                    return {
                        doContent: isChecked('run_do_content'),
                        doContentNoImages: isChecked('run_do_content_no_images'),
                        doTitle: isChecked('run_do_title'),
                        doImagesReset: isChecked('run_do_images_reset'),
                        doImagesContentOnly: isChecked('run_do_images_content_only'),
                        doFeaturedOnly: isChecked('run_do_featured_only'),
                    };
                }
                return {
                    doContent: getDefaultFlag('defaultDoContent'),
                    doContentNoImages: getDefaultFlag('defaultDoContentNoImages'),
                    doTitle: getDefaultFlag('defaultDoTitle'),
                    doImagesReset: getDefaultFlag('defaultDoImagesReset'),
                    doImagesContentOnly: getDefaultFlag('defaultDoImagesContentOnly'),
                    doFeaturedOnly: getDefaultFlag('defaultDoFeaturedOnly'),
                };
            }

            function updateSummary() {
                const flags = computeFlags();
                const actions = [];
                if (flags.doContent) actions.push('AI content');
                if (flags.doContentNoImages) actions.push('AI content (without images)');
                if (flags.doTitle) actions.push('AI title');
                if (flags.doImagesReset) actions.push('reset images');
                if (flags.doImagesContentOnly) actions.push('content images only');
                if (flags.doFeaturedOnly) actions.push('featured only');
                if (actions.length === 0) actions.push('no AI actions');

                const scopePlugin = isChecked('run_scope_plugin');
                const ids = getValue('run_post_ids');
                const cat = getValue('run_category_id');
                const author = getValue('run_author_id');
                const dryRun = isChecked('run_dry_run');

                const filters = [];
                filters.push(scopePlugin ? 'plugin only' : 'all posts');
                if (ids) filters.push('IDs: ' + ids);
                if (!ids && cat && cat !== '0') filters.push('category #' + cat);
                if (!ids && author && author !== '0') filters.push('author #' + author);
                if (dryRun) filters.push('DRY RUN');

                summary.style.display = '';
                summary.innerHTML =
                    '<p style="margin:0;"><strong>Summary:</strong> ' +
                    actions.join(', ') +
                    ' | ' +
                    filters.join(' Â· ') +
                    '</p>';
            }

            actionsForm.addEventListener('change', updateSummary);
            updateSummary();

            actionsForm.addEventListener('submit', function(e) {
                const flags = computeFlags();
                const aiRisk = flags.doContent || flags.doContentNoImages || flags.doTitle || flags.doImagesReset || flags.doImagesContentOnly || flags.doFeaturedOnly;
                if (!aiRisk) return;
                const ok = window.confirm('AI actions that may consume credits will be executed. Continue?');
                if (!ok) e.preventDefault();
            });
        }

        // Auto-refresh log
        const logBox = document.getElementById('cbia-oldposts-log');
                function refreshLog(){
                    if (typeof ajaxurl === 'undefined') return;
                    const nonce = (window.CBIA && CBIA.nonce) ? CBIA.nonce : '';
                    const url = ajaxurl + '?action=cbia_get_oldposts_log' + (nonce ? '&_ajax_nonce=' + encodeURIComponent(nonce) : '');
                    fetch(url, { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(data => {
                            if(data && data.success && logBox){
                        if (data.data && typeof data.data === 'object' && data.data.log) {
                            logBox.value = data.data.log || '';
                        } else {
                            logBox.value = data.data || '';
                        }
                        logBox.scrollTop = logBox.scrollHeight;
                    }
                })
                .catch(() => {});
        }
        setInterval(refreshLog, 3000);
    });
JS;
    wp_add_inline_script('abb-admin', $cbia_oldposts_js, 'after');
    ?>
</div>
<?php
    }
}

cbia_render_view_oldposts();
