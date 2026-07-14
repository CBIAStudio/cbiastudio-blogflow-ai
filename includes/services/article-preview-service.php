<?php
/**
 * Article preview service (no post creation).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!class_exists('CBIA_Article_Preview_Service')) {
    class CBIA_Article_Preview_Service {
        public function generate(array $payload) {
            return $this->generate_internal($payload, null);
        }

        public function generate_stream(array $payload, callable $emit) {
            return $this->generate_internal($payload, $emit);
        }

        private function generate_internal(array $payload, $emit = null) {
            $prev_ignore_stop = !empty($GLOBALS['cbia_ignore_stop']);
            $prev_runtime_overrides = isset($GLOBALS['cbia_runtime_settings_overrides']) ? $GLOBALS['cbia_runtime_settings_overrides'] : null;
            $GLOBALS['cbia_ignore_stop'] = !empty($payload['ignore_stop']) ? true : false;
            try {
            $title = trim((string)($payload['title'] ?? ''));
            if ($title === '') {
                return new WP_Error('missing_title', __('You must provide a title to preview.', 'cbiastudio-blogflow-ai'));
            }
            $composer_mode = !empty($payload['composer_mode']);
            $current_post_id = isset($payload['current_post_id']) ? absint($payload['current_post_id']) : 0;
            if ($composer_mode) {
                $existing_id = (int)$this->find_existing_post_by_title($title);
                if ($existing_id > 0 && $existing_id !== $current_post_id) {
                    return new WP_Error(
                        'duplicate_title',
                        sprintf(
                            /* translators: %s: post title */
                            __('The post "%s" already exists.', 'cbiastudio-blogflow-ai'),
                            $title
                        )
                    );
                }
            }
            $this->emit($emit, 'cbia_status', array('message' => __('Preparing prompt...', 'cbiastudio-blogflow-ai')));

            $settings = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
            // Preview siempre con imagenes (modo completo) y usando el mismo flujo base que crear blog.
            $preview_mode = 'full';
            $this->emit($emit, 'preview_start', array(
                'title' => $title,
                'preview_mode' => $preview_mode,
            ));

            $images_limit = isset($payload['images_limit']) ? (int)$payload['images_limit'] : (int)($settings['images_limit'] ?? 3);
            $skip_images = !empty($payload['skip_images']);
            if ($skip_images) {
                $images_limit = 0;
            } else {
                if ($images_limit < 1) $images_limit = 1;
                if ($images_limit > 4) $images_limit = 4;
            }
            $internal_image_style = isset($payload['internal_image_style']) ? sanitize_key((string)$payload['internal_image_style']) : '';
            if (!in_array($internal_image_style, array('banner', 'normal'), true)) {
                $internal_image_style = '';
            }
            $user_id = get_current_user_id();
            $cleanup_warnings = $this->cleanup_previous_preview_media($user_id);

            if (isset($payload['post_language'])) {
                $settings['post_language'] = sanitize_text_field((string)$payload['post_language']);
            }
            if (isset($payload['blog_prompt_mode'])) {
                $mode = sanitize_key((string)$payload['blog_prompt_mode']);
                if (in_array($mode, array('recommended', 'legacy'), true)) {
                    $settings['blog_prompt_mode'] = $mode;
                }
            }
            if (array_key_exists('blog_prompt_editable', $payload)) {
                $editable = (string)$payload['blog_prompt_editable'];
                $settings['blog_prompt_editable'] = function_exists('cbia_prompt_sanitize_editable_block')
                    ? cbia_prompt_sanitize_editable_block($editable)
                    : sanitize_textarea_field($editable);
            }
            if (array_key_exists('blog_prompt_profile', $payload)) {
                $profile = sanitize_key((string)$payload['blog_prompt_profile']);
                $profile_options = function_exists('cbia_prompt_get_profile_options') ? cbia_prompt_get_profile_options() : array();
                if (array_key_exists($profile, $profile_options)) {
                    $settings['blog_prompt_profile'] = $profile;
                }
            }
            if (array_key_exists('post_length_variant', $payload)) {
                $length_variant = sanitize_key((string)$payload['post_length_variant']);
                if (!in_array($length_variant, array('short', 'medium', 'long'), true)) {
                    $length_variant = 'medium';
                }
                $settings['post_length_variant'] = $length_variant;
            }
            if (array_key_exists('include_faq', $payload)) {
                $settings['include_faq'] = !empty($payload['include_faq']) ? 1 : 0;
            }
            if (array_key_exists('include_practical_examples', $payload)) {
                $settings['include_practical_examples'] = !empty($payload['include_practical_examples']) ? 1 : 0;
            }
            if (array_key_exists('openai_temperature', $payload) && $payload['openai_temperature'] !== null && $payload['openai_temperature'] !== '') {
                $temperature = (float)$payload['openai_temperature'];
                if ($temperature < 0) $temperature = 0;
                if ($temperature > 2) $temperature = 2;
                $settings['openai_temperature'] = $temperature;
            }
            if (array_key_exists('search_intent_strength', $payload)) {
                $strength = sanitize_key((string)$payload['search_intent_strength']);
                if (in_array($strength, array('soft', 'balanced', 'strong'), true)) {
                    $settings['search_intent_strength'] = $strength;
                }
            }
            if (array_key_exists('blog_prompt_custom_instructions', $payload)) {
                $settings['blog_prompt_custom_instructions'] = function_exists('cbia_prompt_sanitize_custom_instructions')
                    ? cbia_prompt_sanitize_custom_instructions((string)$payload['blog_prompt_custom_instructions'])
                    : sanitize_textarea_field((string)$payload['blog_prompt_custom_instructions']);
            }
            // Composer length setting must always become a strict prompt rule.
            if ($composer_mode) {
                $variant = sanitize_key((string)($settings['post_length_variant'] ?? 'medium'));
                if (!in_array($variant, array('short', 'medium', 'long'), true)) {
                    $variant = 'medium';
                }
                $length_instruction = $this->build_length_instruction(
                    $variant,
                    (string)($settings['post_language'] ?? 'English'),
                    !empty($settings['include_faq'])
                );
                $existing_custom = trim((string)($settings['blog_prompt_custom_instructions'] ?? ''));
                if ($existing_custom !== '') {
                    $settings['blog_prompt_custom_instructions'] = trim($length_instruction . "\n" . $existing_custom);
                } else {
                    $settings['blog_prompt_custom_instructions'] = $length_instruction;
                }
            }
            if (!isset($GLOBALS['cbia_runtime_settings_overrides']) || !is_array($GLOBALS['cbia_runtime_settings_overrides'])) {
                $GLOBALS['cbia_runtime_settings_overrides'] = array();
            }
            $runtime_language = (string)($settings['post_language'] ?? 'English');
            if (function_exists('cbia_ai_composer_normalize_language_value')) {
                $runtime_language = (string)cbia_ai_composer_normalize_language_value($runtime_language);
            }
            $settings['post_language'] = $runtime_language;
            $runtime_faq_heading = (string)($settings['faq_heading_custom'] ?? '');
            if ($composer_mode || array_key_exists('post_language', $payload)) {
                if (function_exists('cbia_get_faq_heading_for_language')) {
                    $runtime_faq_heading = (string)cbia_get_faq_heading_for_language($runtime_language);
                } else {
                    $runtime_faq_heading = '';
                }
            }
            $GLOBALS['cbia_runtime_settings_overrides'] = array_replace_recursive(
                (array)$GLOBALS['cbia_runtime_settings_overrides'],
                array(
                    'openai_temperature' => (float)($settings['openai_temperature'] ?? 0.7),
                    'post_language' => $runtime_language,
                    'faq_heading_custom' => $runtime_faq_heading,
                )
            );
            if (array_key_exists('legacy_full_prompt', $payload)) {
                $settings['legacy_full_prompt'] = sanitize_textarea_field((string)$payload['legacy_full_prompt']);
            }

            $this->emit($emit, 'cbia_status', array('message' => 'Generating content...'));

            $prompt = $this->build_prompt($title, $settings);
            list($ok, $text_html, $usage, $model_used, $err, $raw) = cbia_openai_responses_call($prompt, $title, 2);
            $text_attempts = function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($raw) : array();
            if (!$ok) {
                return new WP_Error('preview_generation_failed', $err ?: 'Could not generate preview.');
            }

            $text_html = cbia_strip_document_wrappers((string)$text_html);
            $text_html = cbia_strip_h1_to_h2($text_html);
            $text_html = cbia_fix_bracket_headings($text_html);
            $faq_enabled = function_exists('cbia_runtime_include_faq_enabled') ? cbia_runtime_include_faq_enabled($settings) : true;
            $preview_language = (string)($settings['post_language'] ?? 'English');
            if (!$faq_enabled && function_exists('cbia_strip_faq_section')) {
                $text_html = cbia_strip_faq_section($text_html);
            } elseif (function_exists('cbia_normalize_faq_heading_for_language')) {
                $text_html = cbia_normalize_faq_heading_for_language($text_html, $preview_language);
            } else {
                $text_html = cbia_normalize_faq_heading($text_html);
            }
            $length_variant = sanitize_key((string)($settings['post_length_variant'] ?? 'medium'));
            if (!in_array($length_variant, array('short', 'medium', 'long'), true)) {
                $length_variant = 'medium';
            }
            if (!empty($settings['include_practical_examples'])) {
                $text_html = $this->ensure_practical_examples_block(
                    $text_html,
                    $title,
                    (string)($settings['post_language'] ?? 'English'),
                    $length_variant
                );
            }
            if (function_exists('cbia_pick_length_target_words') && function_exists('cbia_count_words_from_html') && function_exists('cbia_expand_text_to_length_target')) {
                list($min_words, $max_words) = cbia_pick_length_target_words($length_variant, !empty($settings['include_faq']));
                $current_words = (int) cbia_count_words_from_html((string) $text_html);
                $soft_floor = $this->get_soft_length_floor((int)$min_words);
                if ($current_words < (int) $soft_floor) {
                    $text_html = cbia_expand_text_to_length_target($title, (string)$text_html, (array)$settings, (int)$min_words, (int)$max_words);
                    if (!empty($settings['include_practical_examples'])) {
                        $text_html = $this->ensure_practical_examples_block(
                            (string)$text_html,
                            $title,
                            (string)($settings['post_language'] ?? 'English'),
                            $length_variant
                        );
                    }
                }
                $text_html = $this->enforce_length_ceiling((string)$text_html, (int)$max_words, !empty($settings['include_faq']));
            }
            if (function_exists('cbia_log')) {
                cbia_log(sprintf("AI preview text OK: HTML generated for '%s'", (string)$title), 'INFO');
            }

            // Empieza a renderizar en cuanto llega el HTML (antes de esperar imagenes).
            $display_html = $this->normalize_preview_html_for_display($text_html);
            $this->emit_text_progress($emit, $display_html);
            $this->emit($emit, 'word_count', array('count' => $this->word_count($text_html)));
            $this->emit($emit, 'cbia_content', array('html' => $display_html));

            if (!$skip_images) {
                $this->emit($emit, 'cbia_status', array('message' => 'Rendering preview images...'));
                $this->emit($emit, 'featured_image_status', array(
                    'status' => 'pending',
                    'message' => 'Generating featured image...',
                ));
            } else {
                $this->emit($emit, 'cbia_status', array('message' => 'No-image mode enabled for this preview.'));
            }

            $featured_attach_id = 0;
            $featured_model = '';
            $featured_url = '';
            $warnings = array();
            $usage_image_calls = array();
            if ($preview_mode === 'full' && !$skip_images) {
                $featured_desc = function_exists('cbia_sanitize_image_short_desc')
                    ? cbia_sanitize_image_short_desc($title)
                    : $title;
                if ($featured_desc === '') $featured_desc = $title;
                $featured_prompt = function_exists('cbia_build_image_prompt_for_post')
                    ? cbia_build_image_prompt_for_post(0, 'featured', $featured_desc, 0)
                    : $featured_desc;
                $featured_alt = function_exists('cbia_sanitize_alt_from_desc')
                    ? cbia_sanitize_alt_from_desc($featured_desc)
                    : $featured_desc;
                list($ok_featured, $featured_attach_id, $featured_model, $featured_err, $featured_meta) = cbia_generate_image_openai_with_prompt(
                    $featured_prompt,
                    'featured',
                    $title,
                    $featured_alt,
                    0
                );
                if ($ok_featured && $featured_attach_id) {
                    $featured_url = (string)wp_get_attachment_url((int)$featured_attach_id);
                    $usage_image_calls[] = array(
                        'section' => 'featured',
                        'model' => (string)$featured_model,
                        'ok' => 1,
                        'attach_id' => (int)$featured_attach_id,
                        'error' => '',
                        'attempts' => function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($featured_meta) : array(),
                        'meta' => is_array($featured_meta) ? $featured_meta : array(),
                    );
                    $this->emit($emit, 'featured_image_status', array(
                        'status' => 'done',
                        'url' => $featured_url,
                        'attach_id' => (int)$featured_attach_id,
                        'message' => 'Featured image ready.',
                    ));
                } else {
                    $warnings[] = 'Could not generate featured image: ' . (string)($featured_err ?: 'unknown error');
                    $usage_image_calls[] = array(
                        'section' => 'featured',
                        'model' => (string)$featured_model,
                        'ok' => 0,
                        'attach_id' => 0,
                        'error' => (string)($featured_err ?: ''),
                        'attempts' => function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($featured_meta) : array(),
                        'meta' => is_array($featured_meta) ? $featured_meta : array(),
                    );
                    $this->emit($emit, 'featured_image_status', array(
                        'status' => 'error',
                        'message' => 'Could not generate featured image.',
                    ));
                }
            }

            $rendered = $this->render_markers($text_html, $title, $images_limit, $preview_mode, $emit, $internal_image_style);
            $final_html = cbia_cleanup_post_html((string)($rendered['html'] ?? $text_html));
            if ($faq_enabled && function_exists('cbia_normalize_faq_heading_for_language')) {
                $final_html = cbia_normalize_faq_heading_for_language((string)$final_html, $preview_language);
            } elseif ($faq_enabled && function_exists('cbia_normalize_faq_heading')) {
                $final_html = cbia_normalize_faq_heading((string)$final_html);
            }
            $display_html = $this->normalize_preview_html_for_display($final_html);
            $this->emit($emit, 'word_count', array('count' => $this->word_count($final_html)));
            $this->emit($emit, 'cbia_content', array('html' => $display_html));

            if ($preview_mode === 'full') {
                $temp_ids = (array)($rendered['temp_attachment_ids'] ?? array());
                if ($featured_attach_id > 0) $temp_ids[] = (int)$featured_attach_id;
                $this->remember_preview_media($user_id, $temp_ids);
            }

            $this->emit($emit, 'cbia_status', array('message' => 'Calculating metadata...'));

            $excerpt = wp_trim_words(wp_strip_all_tags($final_html), 35, '...');
            $meta = function_exists('cbia_generate_meta_description')
                ? cbia_generate_meta_description($title, $final_html)
                : '';
            $focus = function_exists('cbia_generate_focus_keyphrase')
                ? cbia_generate_focus_keyphrase($title, $final_html)
                : '';
            $tags = function_exists('cbia_pick_tags_for_post')
                ? cbia_pick_tags_for_post($title, $final_html, 7)
                : (function_exists('cbia_pick_tags_from_content_allowed')
                    ? cbia_pick_tags_from_content_allowed($title, $final_html, 7)
                    : array());
            $seo = array(
                'excerpt' => $excerpt,
                'meta_description' => $meta,
                'focus_keyphrase' => $focus,
                'tags' => $tags,
            );
            $draft_id = 0;
            $preview_token = '';
            $category_ids = array();
            $tag_ids = array();

            if ($composer_mode) {
                $category_ids = $this->resolve_category_ids($title, $final_html);
                $tag_ids = $this->resolve_tag_ids($tags);
                if ($current_post_id > 0) {
                    $this->persist_preview_usage($current_post_id, array(
                        'usage' => is_array($usage) ? $usage : array(),
                        'text_model' => (string)$model_used,
                        'text_attempts' => $text_attempts,
                        'image_calls' => array_merge(
                            $usage_image_calls,
                            $this->normalize_preview_image_calls((array)($rendered['images'] ?? array()))
                        ),
                    ));
                }
            } else {
                $draft_id = $this->upsert_preview_draft($title, $final_html, (int)$featured_attach_id, $seo, $settings);
                if (is_wp_error($draft_id) || !$draft_id) {
                    $err_msg = is_wp_error($draft_id) ? $draft_id->get_error_message() : 'Could not create preview draft.';
                    return new WP_Error('preview_draft_failed', $err_msg);
                }
                $draft_id = (int)$draft_id;

                $category_ids = wp_get_post_categories((int)$draft_id, array('fields' => 'ids'));
                if (!is_array($category_ids)) $category_ids = array();
                $category_ids = array_values(array_map('intval', $category_ids));
                $tag_ids = wp_get_post_terms((int)$draft_id, 'post_tag', array('fields' => 'ids'));
                if (!is_array($tag_ids)) $tag_ids = array();
                $tag_ids = array_values(array_map('intval', $tag_ids));

                $preview_token = $this->store_preview_payload($user_id, array(
                    'title' => $title,
                    'html' => $final_html,
                    'featured_attach_id' => (int)$featured_attach_id,
                    'post_id' => $draft_id,
                    'category_ids' => $category_ids,
                    'tag_ids' => $tag_ids,
                    'usage' => is_array($usage) ? $usage : array(),
                    'text_model' => (string)$model_used,
                    'text_attempts' => $text_attempts,
                    'image_calls' => array_merge(
                        $usage_image_calls,
                        $this->normalize_preview_image_calls((array)($rendered['images'] ?? array()))
                    ),
                ));
                update_post_meta($draft_id, '_cbia_preview_token', (string)$preview_token);
            }

            $this->emit($emit, 'seo_payload', $seo);

            $images = (array)($rendered['images'] ?? array());
            if ($featured_url) {
                array_unshift($images, array(
                    'url' => (string)$featured_url,
                    'attach_id' => (int)$featured_attach_id,
                    'section' => 'featured',
                    'status' => 'done',
                ));
            }
            $warnings = array_values(array_filter(array_merge(
                $cleanup_warnings,
                $warnings,
                (array)($rendered['warnings'] ?? array())
            )));

            return array(
                'title' => $title,
                'preview_html' => $display_html,
                'raw_html' => $final_html,
                'excerpt' => $excerpt,
                'meta_description' => $meta,
                'focus_keyphrase' => $focus,
                'tags' => $tags,
                'category_ids' => $category_ids,
                'tag_ids' => $tag_ids,
                'images' => $images,
                'featured_attach_id' => (int)$featured_attach_id,
                'warnings' => $warnings,
                'preview_mode' => $preview_mode,
                'preview_token' => $preview_token,
                'post_id' => $draft_id,
                'word_count' => $this->word_count($final_html),
                'text_model' => (string)$model_used,
                'usage' => is_array($usage) ? $usage : array(),
                'text_attempts' => $text_attempts,
                'image_calls' => array_merge(
                    $usage_image_calls,
                    $this->normalize_preview_image_calls((array)($rendered['images'] ?? array()))
                ),
            );
            } finally {
                $GLOBALS['cbia_ignore_stop'] = $prev_ignore_stop;
                if ($prev_runtime_overrides === null) {
                    unset($GLOBALS['cbia_runtime_settings_overrides']);
                } else {
                    $GLOBALS['cbia_runtime_settings_overrides'] = $prev_runtime_overrides;
                }
            }
        }

        public function create_post_from_token($token, array $overrides = array()) {
            $token = trim((string)$token);
            if ($token === '') {
                return new WP_Error('missing_preview_token', 'Preview token is required.');
            }
            $user_id = get_current_user_id();
            $create_lock_key = 'cbia_preview_create_lock_' . $user_id . '_' . md5($token);
            if (get_transient($create_lock_key)) {
                return new WP_Error('preview_in_progress', 'Preview creation is already in progress.');
            }
            set_transient($create_lock_key, 1, 30);

            $payload = $this->get_preview_payload($user_id, $token);
            if (empty($payload) || !is_array($payload)) {
                delete_transient($create_lock_key);
                return new WP_Error('invalid_preview_token', 'Preview is no longer available. Generate a new one.');
            }

            $title = trim((string)($payload['title'] ?? ''));
            $html = (string)($payload['html'] ?? '');
            $featured_attach_id = (int)($payload['featured_attach_id'] ?? 0);
            if (!empty($overrides)) {
                if (array_key_exists('title', $overrides)) {
                    $title = trim((string)$overrides['title']);
                }
                if (array_key_exists('html', $overrides)) {
                    $html = (string)$overrides['html'];
                }
            }
            if ($title === '' || $html === '') {
                delete_transient($create_lock_key);
                return new WP_Error('invalid_preview_payload', 'Incomplete preview payload to create post.');
            }
            $post_status = sanitize_key((string)($overrides['post_status'] ?? 'publish'));
            if (!in_array($post_status, array('publish', 'draft', 'future'), true)) {
                $post_status = 'publish';
            }
            $post_date_mysql = '';
            if ($post_status === 'future') {
                $raw_date = trim((string)($overrides['post_date_local'] ?? ''));
                if ($raw_date === '') {
                    delete_transient($create_lock_key);
                    return new WP_Error('missing_schedule_date', 'Provide a date/time to schedule.');
                }
                $raw_date = str_replace('T', ' ', $raw_date);
                $dt = date_create_from_format('Y-m-d H:i', $raw_date, wp_timezone());
                if (!$dt) {
                    $dt = date_create($raw_date, wp_timezone());
                }
                if (!$dt) {
                    delete_transient($create_lock_key);
                    return new WP_Error('invalid_schedule_date', 'Invalid schedule date/time.');
                }
                $post_date_mysql = $dt->format('Y-m-d H:i:s');
            }

            $post_id = (int)($payload['post_id'] ?? 0);
            if ($post_id && $this->is_preview_draft($post_id)) {
                $update = array(
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_content' => $html,
                    'post_status' => $post_status,
                );
                if ($post_status === 'future') {
                    $update['post_date'] = $post_date_mysql;
                    $update['post_date_gmt'] = get_gmt_from_date($post_date_mysql);
                }
                $updated_id = wp_update_post($update, true);
                if (is_wp_error($updated_id) || !$updated_id) {
                    $err = is_wp_error($updated_id) ? $updated_id->get_error_message() : 'wp_update_post_failed';
                    delete_transient($create_lock_key);
                    return new WP_Error('create_post_failed', $err ?: 'Could not update post from preview.');
                }
                $this->apply_post_meta_tax($post_id, $title, $html, $featured_attach_id, array());
                $this->persist_preview_usage($post_id, $payload);
                delete_post_meta($post_id, '_cbia_preview_draft');
                delete_post_meta($post_id, '_cbia_preview_token');
            } else {
                if (!function_exists('cbia_create_post_in_wp_engine')) {
                    delete_transient($create_lock_key);
                    return new WP_Error('missing_create_engine', 'Post creation engine is not available.');
                }
                if (function_exists('cbia_post_exists_by_title') && cbia_post_exists_by_title($title)) {
                    delete_transient($create_lock_key);
                    return new WP_Error('duplicate_title', "Post '{$title}' already exists.");
                }
                list($ok_post, $created_id, $post_err) = cbia_create_post_in_wp_engine($title, $html, $featured_attach_id, $post_date_mysql);
                if (!$ok_post || !$created_id) {
                    delete_transient($create_lock_key);
                    return new WP_Error('create_post_failed', $post_err ?: 'Could not create post from preview.');
                }
                $post_id = (int)$created_id;
                if ($post_status === 'draft') {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_status' => 'draft',
                    ));
                }
                $this->persist_preview_usage($post_id, $payload);
            }
            $this->delete_preview_payload($user_id, $token);
            delete_transient($create_lock_key);
            $preview_url = '';
            if (in_array($post_status, array('draft', 'future'), true)) {
                $preview_url = (string) get_preview_post_link((int) $post_id);
            }
            return array(
                'post_id' => (int)$post_id,
                'edit_url' => get_edit_post_link((int)$post_id, ''),
                'preview_url' => $preview_url,
                'message' => $post_status === 'future'
                    ? 'Post scheduled from preview successfully.'
                    : ($post_status === 'draft'
                        ? 'Draft created from preview successfully.'
                        : 'Post created from preview successfully.'),
            );
        }

        public function cancel_preview($token) {
            $token = trim((string)$token);
            if ($token === '') {
                return new WP_Error('missing_preview_token', 'Preview token is required.');
            }
            $user_id = get_current_user_id();
            $payload = $this->get_preview_payload($user_id, $token);
            if (empty($payload) || !is_array($payload)) {
                return new WP_Error('invalid_preview_token', 'Preview is no longer available.');
            }
            $post_id = (int)($payload['post_id'] ?? 0);
            $this->delete_preview_payload($user_id, $token);
            if ($post_id && $this->is_preview_draft($post_id)) {
                $this->delete_preview_attachments($post_id);
                wp_delete_post($post_id, true);
            }
            $this->cleanup_previous_preview_media($user_id);
            return array('ok' => 1);
        }

        private function delete_preview_attachments($post_id): void {
            $attachments = get_children(array(
                'post_parent' => (int)$post_id,
                'post_type' => 'attachment',
                'post_status' => 'any',
                'numberposts' => -1,
            ));
            if (empty($attachments)) return;
            foreach ($attachments as $att) {
                if (!empty($att->ID)) {
                    wp_delete_attachment((int)$att->ID, true);
                }
            }
        }

        private function normalize_preview_html_for_display(string $html): string {
            $out = (string)$html;
            $out = preg_replace('/\\[(?:IMAGE_PENDING|IMAGEN_PENDIENTE):\\s*([^\\]]+)\\]/i', '[IMAGE: $1]', $out);
            return $out;
        }

        private function is_preview_draft($post_id): bool {
            $post_id = (int)$post_id;
            if ($post_id <= 0) return false;
            $flag = get_post_meta($post_id, '_cbia_preview_draft', true);
            return (string)$flag === '1';
        }

        private function find_existing_post_by_title($title) {
            global $wpdb;
            $title = (string)$title;
            if ($title === '') return 0;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact title collision check for preview draft reuse.
            $found = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_title=%s AND post_status IN ('publish','future','draft','pending','private') LIMIT 1",
                $title
            ));
            return (int)$found;
        }

        private function upsert_preview_draft($title, $final_html, $featured_attach_id, array $seo, array $settings = array()) {
            $title = trim((string)$title);
            if ($title === '') {
                return new WP_Error('missing_title', 'Empty title to create draft.');
            }

            $final_html = cbia_strip_document_wrappers((string)$final_html);
            $final_html = cbia_strip_h1_to_h2($final_html);

            $existing_id = $this->find_existing_post_by_title($title);
            if ($existing_id && !$this->is_preview_draft($existing_id)) {
                return new WP_Error('duplicate_title', "Post '{$title}' already exists.");
            }

            $postarr = array(
                'post_type' => 'post',
                'post_title' => $title,
                'post_content' => $final_html,
                'post_status' => 'draft',
                'post_author' => function_exists('cbia_pick_post_author_id') ? cbia_pick_post_author_id() : get_current_user_id(),
            );

            if ($existing_id) {
                $postarr['ID'] = (int)$existing_id;
                $post_id = wp_update_post($postarr, true);
            } else {
                $post_id = wp_insert_post($postarr, true);
            }

            if (is_wp_error($post_id) || !$post_id) {
                $err = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post_failed';
                return new WP_Error('preview_draft_failed', $err);
            }

            $post_id = (int)$post_id;
            update_post_meta($post_id, '_cbia_preview_draft', '1');
            if (function_exists('cbia_record_post_prompt_profile')) {
                cbia_record_post_prompt_profile($post_id, $title, $settings);
            }
            $this->apply_post_meta_tax($post_id, $title, $final_html, (int)$featured_attach_id, $seo);
            return $post_id;
        }

        private function apply_post_meta_tax($post_id, $title, $final_html, $featured_attach_id, array $seo) {
            $post_id = (int)$post_id;
            if (!$post_id) return;

            $cats = function_exists('cbia_determine_categories_by_mapping')
                ? cbia_determine_categories_by_mapping($title, $final_html)
                : array();
            if (empty($cats)) {
                $s = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
                $default_cat = trim((string)($s['default_category'] ?? 'News'));
                if ($default_cat !== '') $cats = array($default_cat);
            }
            $cat_ids = array();
            if (!empty($cats) && function_exists('cbia_ensure_category_exists')) {
                foreach ($cats as $c) {
                    $id = cbia_ensure_category_exists($c);
                    if ($id) $cat_ids[] = $id;
                }
            }
            if (!empty($cat_ids)) {
                wp_set_post_categories($post_id, $cat_ids, false);
                update_post_meta($post_id, '_yoast_wpseo_primary_category', (int)$cat_ids[0]);
            }

            $tags = !empty($seo['tags']) ? $seo['tags'] : (function_exists('cbia_pick_tags_for_post')
                ? cbia_pick_tags_for_post($title, $final_html, 7)
                : (function_exists('cbia_pick_tags_from_content_allowed')
                    ? cbia_pick_tags_from_content_allowed($title, $final_html, 7)
                    : array()));
            if (!empty($tags)) {
                wp_set_post_tags($post_id, $tags, false);
            }

            if ($featured_attach_id) {
                set_post_thumbnail($post_id, (int)$featured_attach_id);
                wp_update_post(array(
                    'ID' => (int)$featured_attach_id,
                    'post_parent' => (int)$post_id,
                ));
            }

            $metad = (string)($seo['meta_description'] ?? '');
            if ($metad === '' && function_exists('cbia_generate_meta_description')) {
                $metad = cbia_generate_meta_description($title, $final_html);
            }
            $focus = (string)($seo['focus_keyphrase'] ?? '');
            if ($focus === '' && function_exists('cbia_generate_focus_keyphrase')) {
                $focus = cbia_generate_focus_keyphrase($title, $final_html);
            }
            $yoast_title = wp_strip_all_tags((string)$title);

            update_post_meta($post_id, '_yoast_wpseo_metadesc', $metad);
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus);
            update_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', $focus);
            update_post_meta($post_id, '_yoast_wpseo_title', $yoast_title);
            update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $yoast_title);
            update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $metad);
            update_post_meta($post_id, '_yoast_wpseo_twitter-title', $yoast_title);
            update_post_meta($post_id, '_yoast_wpseo_twitter-description', $metad);
            $extra_yoast_meta = apply_filters('cbia_yoast_extra_meta', array(), $post_id, $title, $final_html, $cat_ids);
            if (is_array($extra_yoast_meta) && !empty($extra_yoast_meta)) {
                $allowed = array(
                    '_yoast_wpseo_canonical',
                    '_yoast_wpseo_meta-robots-noindex',
                    '_yoast_wpseo_meta-robots-nofollow',
                    '_yoast_wpseo_schema_page_type',
                );
                foreach ($allowed as $meta_key) {
                    if (!array_key_exists($meta_key, $extra_yoast_meta)) continue;
                    update_post_meta($post_id, $meta_key, (string)$extra_yoast_meta[$meta_key]);
                }
            }

            update_post_meta($post_id, '_cbia_created', '1');
            update_post_meta($post_id, '_cbia_created_at', current_time('mysql'));
        }

        private function normalize_preview_image_calls(array $images): array {
            $rows = array();
            foreach ($images as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $rows[] = array(
                    'section' => sanitize_key((string)($item['section'] ?? 'internal')),
                    'model' => sanitize_text_field((string)($item['model'] ?? '')),
                    'ok' => !empty($item['ok']) ? 1 : 0,
                    'attach_id' => (int)($item['attach_id'] ?? 0),
                    'error' => sanitize_text_field((string)($item['error'] ?? '')),
                    'attempts' => is_array($item['attempts'] ?? null) ? (array)$item['attempts'] : array(),
                    'meta' => is_array($item['meta'] ?? null) ? (array)$item['meta'] : array(),
                );
            }
            return $rows;
        }

        private function persist_preview_usage($post_id, array $payload): void {
            $post_id = (int)$post_id;
            if ($post_id <= 0) {
                return;
            }

            $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : array();
            $text_model = sanitize_text_field((string)($payload['text_model'] ?? ''));
            $text_attempts = is_array($payload['text_attempts'] ?? null) ? $payload['text_attempts'] : array();

            if ($text_model !== '' && function_exists('cbia_costes_record_usage')) {
                if (!empty($text_attempts) && function_exists('cbia_costes_record_failed_attempts')) {
                    cbia_costes_record_failed_attempts($post_id, $text_attempts, array('type' => 'text'));
                }
                cbia_costes_record_usage($post_id, array(
                    'type' => 'text',
                    'model' => $text_model,
                    'input_tokens' => (int)($usage['input_tokens'] ?? 0),
                    'output_tokens' => (int)($usage['output_tokens'] ?? 0),
                    'cached_input_tokens' => (int)($usage['cached_input_tokens'] ?? 0),
                    'ok' => 1,
                ));
            }
            if ($text_model !== '' && function_exists('cbia_usage_append_call')) {
                cbia_usage_append_call($post_id, 'blog_text', $text_model, $usage, array(
                    'ok' => 1,
                    'err' => '',
                ));
            }

            $image_calls = is_array($payload['image_calls'] ?? null) ? $payload['image_calls'] : array();
            foreach ($image_calls as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $model = sanitize_text_field((string)($item['model'] ?? ''));
                $section = sanitize_key((string)($item['section'] ?? 'internal'));
                $ok = !empty($item['ok']) ? 1 : 0;
                $attach_id = (int)($item['attach_id'] ?? 0);
                $error = sanitize_text_field((string)($item['error'] ?? ''));
                $attempts = is_array($item['attempts'] ?? null) ? $item['attempts'] : array();
                $meta = is_array($item['meta'] ?? null) ? $item['meta'] : array();

                $recorded_attempts = 0;
                if (!empty($attempts) && function_exists('cbia_costes_record_failed_attempts')) {
                    $recorded_attempts = cbia_costes_record_failed_attempts($post_id, $attempts, array('type' => 'image', 'section' => $section));
                }
                if (function_exists('cbia_costes_record_usage') && ($ok || !$recorded_attempts)) {
                    cbia_costes_record_usage($post_id, array_merge($meta, array(
                        'type' => 'image',
                        'model' => $model,
                        'input_tokens' => (int)($meta['input_tokens'] ?? 0),
                        'output_tokens' => (int)($meta['output_tokens'] ?? 0),
                        'cached_input_tokens' => (int)($meta['cached_input_tokens'] ?? 0),
                        'ok' => $ok,
                        'error' => $error,
                        'section' => $section,
                        'attach_id' => $attach_id,
                    )));
                }
                if (function_exists('cbia_image_append_call')) {
                    cbia_image_append_call($post_id, $section, $model, $ok, $attach_id, $error);
                }
            }
        }

        private function resolve_category_ids(string $title, string $final_html): array {
            $cats = function_exists('cbia_determine_categories_by_mapping')
                ? cbia_determine_categories_by_mapping($title, $final_html)
                : array();
            if (empty($cats)) {
                $s = function_exists('cbia_get_settings') ? cbia_get_settings() : array();
                $default_cat = trim((string)($s['default_category'] ?? 'News'));
                if ($default_cat !== '') $cats = array($default_cat);
            }
            $cat_ids = array();
            if (!empty($cats) && function_exists('cbia_ensure_category_exists')) {
                foreach ($cats as $c) {
                    $id = cbia_ensure_category_exists($c);
                    if ($id) $cat_ids[] = (int)$id;
                }
            }
            return array_values(array_unique(array_filter($cat_ids)));
        }

        private function resolve_tag_ids(array $tags): array {
            $tag_ids = array();
            foreach ($tags as $tag_name) {
                $name = trim((string)$tag_name);
                if ($name === '') continue;
                $term = get_term_by('name', $name, 'post_tag');
                if (!$term || is_wp_error($term)) {
                    $created = wp_insert_term($name, 'post_tag');
                    if (is_wp_error($created) || empty($created['term_id'])) {
                        continue;
                    }
                    $tag_ids[] = (int)$created['term_id'];
                } else {
                    $tag_ids[] = (int)$term->term_id;
                }
            }
            return array_values(array_unique(array_filter($tag_ids)));
        }

        private function preview_media_meta_key(): string {
            return '_cbia_preview_temp_media_ids';
        }

        private function cleanup_previous_preview_media($user_id): array {
            $warnings = array();
            $user_id = (int)$user_id;
            if ($user_id <= 0) return $warnings;

            $stored = get_user_meta($user_id, $this->preview_media_meta_key(), true);
            $ids = is_array($stored) ? $stored : array();
            if (empty($ids)) return $warnings;

            foreach ($ids as $raw_id) {
                $attach_id = (int)$raw_id;
                if ($attach_id <= 0) continue;
                if (get_post_type($attach_id) !== 'attachment') continue;
                $keep = (string)get_post_meta($attach_id, '_cbia_keep_preview_media', true);
                if ($keep === '1') {
                    continue;
                }
                $deleted = wp_delete_attachment($attach_id, true);
                if (!$deleted) {
                $warnings[] = 'Could not clean temporary preview attachment ID ' . $attach_id . '.';
                }
            }
            delete_user_meta($user_id, $this->preview_media_meta_key());
            return $warnings;
        }

        private function remember_preview_media($user_id, array $ids): void {
            $user_id = (int)$user_id;
            if ($user_id <= 0) return;
            $clean = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if (empty($clean)) {
                delete_user_meta($user_id, $this->preview_media_meta_key());
                return;
            }
            update_user_meta($user_id, $this->preview_media_meta_key(), $clean);
        }

        private function preview_transient_key($user_id, $token): string {
            return 'cbia_preview_payload_' . (int)$user_id . '_' . sanitize_key((string)$token);
        }

        private function store_preview_payload($user_id, array $payload): string {
            $user_id = (int)$user_id;
            if ($user_id <= 0) return '';
            $token = wp_generate_password(20, false, false);
            $key = $this->preview_transient_key($user_id, $token);
            set_transient($key, $payload, 2 * HOUR_IN_SECONDS);
            return $token;
        }

        private function get_preview_payload($user_id, $token) {
            $user_id = (int)$user_id;
            if ($user_id <= 0) return array();
            $key = $this->preview_transient_key($user_id, $token);
            $data = get_transient($key);
            return is_array($data) ? $data : array();
        }

        private function delete_preview_payload($user_id, $token): void {
            $user_id = (int)$user_id;
            if ($user_id <= 0) return;
            $key = $this->preview_transient_key($user_id, $token);
            delete_transient($key);
        }

        private function build_prompt($title, array $settings) {
            $idioma_post = trim((string)($settings['post_language'] ?? 'English'));
            $mode = function_exists('cbia_prompt_get_mode')
                ? cbia_prompt_get_mode($settings)
                : sanitize_key((string)($settings['blog_prompt_mode'] ?? 'recommended'));

            if ($mode === 'legacy') {
                $template = function_exists('cbia_prompt_get_legacy_template')
                    ? cbia_prompt_get_legacy_template($settings)
                    : (string)($settings['legacy_full_prompt'] ?? ($settings['prompt_single_all'] ?? ''));
            } else {
                $template = function_exists('cbia_prompt_build_recommended_template_from_settings')
                    ? cbia_prompt_build_recommended_template_from_settings($settings, $idioma_post, $title)
                    : (string)($settings['prompt_single_all'] ?? '');
            }

            $template = str_replace('{title}', (string)$title, (string)$template);
            $template = str_replace('{IDIOMA_POST}', (string)$idioma_post, (string)$template);
            return (string)$template;
        }

        private function build_length_instruction($variant, $language, $include_faq = false) {
            $variant = sanitize_key((string)$variant);
            if (!in_array($variant, array('short', 'medium', 'long'), true)) {
                $variant = 'medium';
            }
            $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
            if ($variant === 'short') {
                return $is_spanish
                    ? 'PRIORIDAD ABSOLUTA DE LONGITUD (sustituye cualquier otro rango): entre 950 y 1100 palabras reales. Minimo 950. Reparte el texto asi: apertura 180-240 palabras; cada bloque principal 220-280; cierre 100-160.'
                    : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): between 950 and 1100 real words. Minimum 950. Split as: opening 180-240 words; each main block 220-280; closing 100-160.';
            }
            if ($variant === 'long') {
                if ($include_faq) {
                    return $is_spanish
                        ? 'PRIORIDAD ABSOLUTA DE LONGITUD (sustituye cualquier otro rango): entre 2000 y 2200 palabras reales. Minimo 2000. Con FAQ activa, reparte asi: apertura 240-320; cada bloque principal 300-380; cada respuesta FAQ 80-110 palabras; cierre 140-220.'
                        : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): between 2000 and 2200 real words. Minimum 2000. With FAQ enabled, split as: opening 240-320; each main block 300-380; each FAQ answer 80-110 words; closing 140-220.';
                }
                return $is_spanish
                    ? 'PRIORIDAD ABSOLUTA DE LONGITUD (sustituye cualquier otro rango): entre 2000 y 2200 palabras reales. Minimo 2000. Reparte el texto asi: apertura 280-360 palabras; cada bloque principal 460-560; cierre 180-260.'
                    : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): between 2000 and 2200 real words. Minimum 2000. Split as: opening 280-360 words; each main block 460-560; closing 180-260.';
            }
            if ($include_faq) {
                return $is_spanish
                    ? 'PRIORIDAD ABSOLUTA DE LONGITUD (sustituye cualquier otro rango): entre 1800 y 2000 palabras reales. Minimo 1800. Con FAQ activa, reparte asi: apertura 220-300; cada bloque principal 280-360; cada respuesta FAQ 75-105 palabras; cierre 130-190.'
                    : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): between 1800 and 2000 real words. Minimum 1800. With FAQ enabled, split as: opening 220-300; each main block 280-360; each FAQ answer 75-105 words; closing 130-190.';
            }
            return $is_spanish
                ? 'PRIORIDAD ABSOLUTA DE LONGITUD (sustituye cualquier otro rango): entre 1800 y 2000 palabras reales. Minimo 1800. Reparte el texto asi: apertura 260-340 palabras; cada bloque principal 420-520; cierre 150-220.'
                : 'ABSOLUTE LENGTH PRIORITY (overrides any previous range): between 1800 and 2000 real words. Minimum 1800. Split as: opening 260-340 words; each main block 420-520; closing 150-220.';
        }

        private function get_soft_length_floor(int $min_words): int {
            $slack = max(120, (int)floor($min_words * 0.08));
            return max(1, $min_words - $slack);
        }

        private function enforce_length_ceiling(string $html, int $max_words, bool $include_faq): string {
            if ($max_words <= 0 || !function_exists('cbia_count_words_from_html')) {
                return $html;
            }
            $current_words = (int)cbia_count_words_from_html($html);
            if ($current_words <= $max_words) {
                return $html;
            }

            $faq_pattern = '/<h2[^>]*>\s*(FAQ|Preguntas frecuentes|Preguntas Frecuentes|Frequently Asked Questions|Questions? ?FAQs?|FAQs)\s*<\/h2>/i';
            if ($include_faq && preg_match($faq_pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
                $faq_start = (int)$m[0][1];
                $before = trim((string)substr($html, 0, $faq_start));
                $faq = trim((string)substr($html, $faq_start));
                $faq_words = (int)cbia_count_words_from_html($faq);
                if ($faq_words > 0 && $faq_words < (int)floor($max_words * 0.45)) {
                    $budget_before = max(350, $max_words - $faq_words);
                    $before_trimmed = $this->truncate_html_to_word_limit($before, $budget_before);
                    $merged = trim($before_trimmed . "\n\n" . $faq);
                    if ((int)cbia_count_words_from_html($merged) <= $max_words) {
                        return $merged;
                    }
                }
            }

            return $this->truncate_html_to_word_limit($html, $max_words);
        }

        private function truncate_html_to_word_limit(string $html, int $max_words): string {
            if ($max_words <= 0 || !function_exists('cbia_count_words_from_html')) {
                return $html;
            }
            if ((int)cbia_count_words_from_html($html) <= $max_words) {
                return $html;
            }

            $pattern = '/(<div\b[^>]*cbia-inline-image-wrap[^>]*>[\s\S]*?<\/div>|<h[2-3]\b[^>]*>[\s\S]*?<\/h[2-3]>|<p\b[^>]*>[\s\S]*?<\/p>|<ul\b[^>]*>[\s\S]*?<\/ul>|<ol\b[^>]*>[\s\S]*?<\/ol>)/iu';
            preg_match_all($pattern, $html, $matches);
            $blocks = (array)($matches[0] ?? array());
            if (empty($blocks)) {
                return $html;
            }

            $kept = array();
            $count = 0;
            foreach ($blocks as $block) {
                $block = trim((string)$block);
                if ($block === '') continue;
                $block_words = (int)cbia_count_words_from_html($block);
                if ($block_words <= 0) {
                    $kept[] = $block;
                    continue;
                }
                if ($count + $block_words <= $max_words) {
                    $kept[] = $block;
                    $count += $block_words;
                    continue;
                }
                $remaining = $max_words - $count;
                if ($remaining >= 35) {
                    $partial = $this->truncate_block_preserving_html($block, $remaining);
                    if ($partial !== '') {
                        $kept[] = $partial;
                    }
                }
                break;
            }

            $out = trim(implode("\n", $kept));
            return $out !== '' ? $out : $html;
        }

        private function truncate_block_preserving_html(string $block, int $max_words): string {
            if ($max_words <= 0) return '';
            $plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($block)));
            if ($plain === '') return '';
            preg_match_all('/[\p{L}\p{N}\']+/u', $plain, $m);
            $words = (array)($m[0] ?? array());
            if (empty($words)) return '';
            if (count($words) <= $max_words) return $block;

            $snippet = implode(' ', array_slice($words, 0, $max_words));
            if (preg_match('/^<p\b/i', $block)) {
                return '<p>' . esc_html($snippet) . '...</p>';
            }
            return '<p>' . esc_html($snippet) . '...</p>';
        }

        private function ensure_practical_examples_block($html, $title, $language, $length_variant = 'medium') {
            $html = (string)$html;
            $length_variant = sanitize_key((string)$length_variant);
            if (!in_array($length_variant, array('short', 'medium', 'long'), true)) {
                $length_variant = 'medium';
            }
            $plain = strtolower(wp_strip_all_tags($html));
            $example_hits = preg_match_all('/\b(por ejemplo|ejemplo|caso practico|caso real|escenario|example|for example|use case|real-world|scenario)\b/u', $plain, $m);
            $required_scenarios = ($length_variant === 'short') ? 3 : 4;
            $min_words_per_scenario = ($length_variant === 'short') ? 55 : 85;
            $has_examples_heading = (bool)preg_match('/<h[23][^>]*>[^<]*(ejemplos|casos practicos|practical examples|use cases)[^<]*<\/h[23]>/iu', $html);
            $scenario_matches = array();
            $scenario_count = preg_match_all('/<h3[^>]*>[^<]*(escenario|scenario)\s*[0-9][^<]*<\/h3>([\s\S]*?)(?=<h3\b|<h2\b|$)/iu', $html, $scenario_matches, PREG_SET_ORDER);
            $quality_ok = false;
            if ($scenario_count && is_array($scenario_matches)) {
                $valid_blocks = 0;
                foreach ($scenario_matches as $row) {
                    $segment_plain = wp_strip_all_tags((string)($row[0] ?? ''));
                    preg_match_all('/\p{L}[\p{L}\p{N}\-_]*/u', $segment_plain, $wm);
                    $segment_words = count((array)($wm[0] ?? array()));
                    if ($segment_words >= $min_words_per_scenario) {
                        $valid_blocks++;
                    }
                }
                $quality_ok = ($valid_blocks >= $required_scenarios);
            }
            if ($has_examples_heading && $example_hits >= 4 && $quality_ok) {
                return $html;
            }

            $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
            $topics = $this->extract_example_topics($html, $title, $required_scenarios);

            $block = '';
            if ($is_spanish) {
                $block .= "<h2>Ejemplos practicos aplicados</h2>\n";
                $block .= "<h3>Escenario 1: " . esc_html($topics[0]) . "</h3>\n";
                $block .= "<p><strong>Contexto real:</strong> durante el cierre semanal aparecen dos senales repetidas (desviaciones, errores o retrasos) que se estaban normalizando y no se escalaban a tiempo.</p>\n";
                $block .= "<p><strong>Aplicacion paso a paso:</strong> se crea una revision de 20 minutos con 3 responsables fijos, checklist de 8 puntos y un criterio de escalado (si se repite 2 veces en 7 dias, se abre accion correctiva).</p>\n";
                $block .= "<p><strong>Resultado medible (30 dias):</strong> reduccion del tiempo de deteccion, menos incidencias recurrentes y respuesta mas consistente entre turnos/equipos.</p>\n";
                $block .= "<h3>Escenario 2: " . esc_html($topics[1]) . "</h3>\n";
                $block .= "<p><strong>Contexto real:</strong> el equipo acumula casos aislados sin patron visible y las decisiones se toman por urgencia, no por impacto real.</p>\n";
                $block .= "<p><strong>Aplicacion paso a paso:</strong> cada incidencia se documenta con fecha, impacto operativo, coste estimado, causa probable y accion correctiva; se revisa en una reunion quincenal con matriz impacto/esfuerzo.</p>\n";
                $block .= "<p><strong>Resultado medible (30 dias):</strong> priorizacion objetiva, menos reprocesos y mayor capacidad para anticipar puntos de fallo.</p>\n";
                $block .= "<h3>Escenario 3: " . esc_html($topics[2]) . "</h3>\n";
                $block .= "<p><strong>Contexto real:</strong> situaciones parecidas se resuelven de forma distinta segun la persona, lo que genera variabilidad y errores evitables.</p>\n";
                $block .= "<p><strong>Aplicacion paso a paso:</strong> se implanta un protocolo corto de 5 pasos (detectar, validar, actuar, registrar, verificar) con doble comprobacion en los puntos criticos y retro semanal.</p>\n";
                $block .= "<p><strong>Resultado medible (30 dias):</strong> mayor consistencia operativa, menos omisiones y mejor coordinacion interdepartamental.</p>\n";
                if ($required_scenarios > 3) {
                    $block .= "<h3>Escenario 4: " . esc_html($topics[3]) . "</h3>\n";
                    $block .= "<p><strong>Contexto real:</strong> cuando el volumen sube, el equipo prioriza la velocidad y se pierde control de calidad en pasos clave.</p>\n";
                    $block .= "<p><strong>Aplicacion paso a paso:</strong> se define un umbral de carga para activar un modo de contingencia con tareas redistribuidas, checklist abreviado y control de cierre obligatorio.</p>\n";
                    $block .= "<p><strong>Resultado medible (30 dias):</strong> se mantiene la productividad sin disparar errores de calidad ni retrabajo al final del ciclo.</p>\n";
                }
            } else {
                $block .= "<h2>Practical examples</h2>\n";
                $block .= "<h3>Scenario 1: " . esc_html($topics[0]) . "</h3>\n";
                $block .= "<p><strong>Real context:</strong> two recurring signals appear in weekly execution and are being normalized instead of escalated early.</p>\n";
                $block .= "<p><strong>Step-by-step application:</strong> run a 20-minute weekly review with 3 owners, an 8-point checklist, and one escalation rule (if repeated twice in 7 days, open a corrective action).</p>\n";
                $block .= "<p><strong>30-day measurable result:</strong> faster detection, fewer repeated incidents, and more consistent response quality.</p>\n";
                $block .= "<h3>Scenario 2: " . esc_html($topics[1]) . "</h3>\n";
                $block .= "<p><strong>Real context:</strong> isolated issues accumulate without a clear pattern and decisions are made by urgency, not impact.</p>\n";
                $block .= "<p><strong>Step-by-step application:</strong> document each case with date, impact, estimated cost, likely root cause, corrective action, and closure date; review bi-weekly with an impact/effort matrix.</p>\n";
                $block .= "<p><strong>30-day measurable result:</strong> objective prioritization, less rework, and better anticipation of future failures.</p>\n";
                $block .= "<h3>Scenario 3: " . esc_html($topics[2]) . "</h3>\n";
                $block .= "<p><strong>Real context:</strong> similar situations are solved differently across shifts or teams, which creates avoidable variance.</p>\n";
                $block .= "<p><strong>Step-by-step application:</strong> deploy a 5-step protocol (detect, validate, act, log, verify) with cross-checks on critical points and a weekly retro.</p>\n";
                $block .= "<p><strong>30-day measurable result:</strong> stronger consistency, fewer omissions, and better team coordination.</p>\n";
                if ($required_scenarios > 3) {
                    $block .= "<h3>Scenario 4: " . esc_html($topics[3]) . "</h3>\n";
                    $block .= "<p><strong>Real context:</strong> when workload peaks, speed is prioritized and quality checks are skipped.</p>\n";
                    $block .= "<p><strong>Step-by-step application:</strong> define a load threshold that triggers a contingency mode with redistributed tasks, a shortened checklist, and mandatory closure validation.</p>\n";
                    $block .= "<p><strong>30-day measurable result:</strong> productivity remains stable without a spike in quality defects or end-of-cycle rework.</p>\n";
                }
            }

            if (preg_match('/<h2[^>]*>\\s*(FAQ|Preguntas frecuentes|Preguntas Frecuentes|Frequently Asked Questions|Questions? ?FAQs?|FAQs)\\s*<\\/h2>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
                $pos = (int)$match[0][1];
                return substr($html, 0, $pos) . $block . substr($html, $pos);
            }

            return $html . "\n" . $block;
        }

        private function extract_example_topics($html, $title, $needed = 3) {
            $needed = max(3, (int)$needed);
            $topics = array();
            if (preg_match_all('/<h[23][^>]*>(.*?)<\\/h[23]>/iu', (string)$html, $matches)) {
                foreach ((array)$matches[1] as $raw_heading) {
                    $heading = trim(wp_strip_all_tags((string)$raw_heading));
                    if ($heading === '') continue;
                    if (preg_match('/(faq|preguntas frecuentes|practical examples|ejemplos practicos)/iu', $heading)) continue;
                    $topics[] = $heading;
                    if (count($topics) >= $needed) break;
                }
            }

            $fallback = trim(wp_strip_all_tags((string)$title));
            if ($fallback === '') $fallback = 'Aplicacion operativa';
            if (empty($topics)) {
                $topics = array_fill(0, $needed, $fallback);
            }
            while (count($topics) < $needed) {
                $topics[] = $fallback;
            }

            return array_slice(array_map('sanitize_text_field', $topics), 0, $needed);
        }

        private function render_markers($html, $title, $images_limit, $preview_mode, $emit = null, $internal_image_style = '') {
            $warnings = array();
            $images = array();
            $temp_attachment_ids = array();
            $internal_limit = max(0, (int)$images_limit - 1);

            if (function_exists('cbia_normalize_image_markers')) {
                $html = cbia_normalize_image_markers((string)$html);
            }

            $markers_all = cbia_extract_image_markers((string)$html);
            if ($internal_limit <= 0) {
                foreach ($markers_all as $mk) {
                    $html = cbia_remove_marker_from_html($html, $mk['full']);
                }
                return array('html' => $html, 'images' => $images, 'warnings' => $warnings, 'temp_attachment_ids' => $temp_attachment_ids);
            }

            if (count($markers_all) < $internal_limit && function_exists('cbia_force_insert_markers')) {
                $html = cbia_force_insert_markers($html, (string)$title, $internal_limit);
                $markers_all = cbia_extract_image_markers($html);
            }
            if (count($markers_all) > $internal_limit) {
                $extra = array_slice($markers_all, $internal_limit);
                foreach ($extra as $mk) {
                    $html = cbia_remove_marker_from_html($html, $mk['full']);
                }
                $markers_all = cbia_extract_image_markers($html);
            }

            $markers = array_slice($markers_all, 0, $internal_limit);
            foreach ($markers as $idx => $mk) {
                $i = $idx + 1;
                $desc = (string)($mk['short_desc'] ?? '');
                if ($desc === '') $desc = cbia_sanitize_image_short_desc((string)($mk['desc'] ?? ''));
                if ($desc === '') $desc = (string)$title;
                $section = cbia_detect_marker_section($html, (int)$mk['pos'], false);
                $this->emit($emit, 'cbia_image', array(
                    'idx' => $i,
                    'section' => $section,
                    'desc' => $desc,
                    'status' => 'processing',
                ));

                if ($preview_mode === 'full') {
                    $prompt = cbia_build_image_prompt_for_post(0, 'internal', $desc, $i);
                    $alt = cbia_sanitize_alt_from_desc($desc);
                    list($ok_img, $attach_id, $img_model, $img_err, $img_meta) = cbia_generate_image_openai_with_prompt($prompt, $section, $title, $alt, $i);
                    if ($ok_img && $attach_id) {
                        $url = wp_get_attachment_url((int)$attach_id);
                        $img_tag = cbia_build_content_img_tag($url, $alt, $section, $i);
                        if ($internal_image_style === 'banner') {
                            if (strpos($img_tag, 'cbia-banner') === false) {
                                if (preg_match('/class=("|\')([^"\']*)\1/i', $img_tag)) {
                                    $img_tag = preg_replace('/class=("|\')([^"\']*)\1/i', 'class="$2 cbia-banner lazyloaded"', $img_tag, 1);
                                } else {
                                    $img_tag = preg_replace('/<img\s+/i', '<img class="cbia-banner lazyloaded" ', $img_tag, 1);
                                }
                            }
                        } elseif ($internal_image_style === 'normal') {
                            $img_tag = preg_replace_callback('/\sclass=("|\')([^"\']*)\1/i', function($m) {
                                $classes = preg_split('/\s+/', trim((string)$m[2]));
                                if (!is_array($classes)) $classes = array();
                                $classes = array_values(array_filter($classes, function($c){
                                    return $c !== 'cbia-banner' && $c !== 'lazyloaded';
                                }));
                                return empty($classes) ? '' : ' class="' . esc_attr(implode(' ', $classes)) . '"';
                            }, $img_tag, 1);
                        }
                        $img_markup = '<div class="cbia-inline-image-wrap" style="margin:18px 0;">' . $img_tag . '</div>';
                        $html = cbia_replace_first_occurrence($html, $mk['full'], $img_markup);
                        $images[] = array(
                            'idx' => $i,
                            'section' => $section,
                            'desc' => $desc,
                            'ok' => 1,
                            'model' => (string)$img_model,
                            'url' => (string)$url,
                            'attach_id' => (int)$attach_id,
                            'attempts' => function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($img_meta) : array(),
                            'meta' => is_array($img_meta) ? $img_meta : array(),
                        );
                        $temp_attachment_ids[] = (int)$attach_id;
                        $this->emit($emit, 'cbia_content', array('html' => $html));
                        $this->emit($emit, 'cbia_image', array(
                            'idx' => $i,
                            'section' => $section,
                            'desc' => $desc,
                            'status' => 'done',
                            'ok' => 1,
                            'url' => (string)$url,
                        ));
                        continue;
                    }
                    $warnings[] = 'Could not generate internal image ' . $i . ': ' . (string)($img_err ?: 'unknown error');
                    $images[] = array(
                        'idx' => $i,
                        'section' => $section,
                        'desc' => $desc,
                        'ok' => 0,
                        'model' => (string)$img_model,
                        'error' => (string)($img_err ?: ''),
                        'attempts' => function_exists('cbia_costes_get_attempts_from_meta') ? cbia_costes_get_attempts_from_meta($img_meta) : array(),
                        'meta' => is_array($img_meta) ? $img_meta : array(),
                    );
                    $this->emit($emit, 'cbia_image', array(
                        'idx' => $i,
                        'section' => $section,
                        'desc' => $desc,
                        'status' => 'error',
                        'ok' => 0,
                        'error' => (string)($img_err ?: 'unknown error'),
                    ));
                }

                $placeholder = '<div class="cbia-preview-image" data-section="' . esc_attr($section) . '"><strong>[Preview internal image ' . $i . ']</strong> ' . esc_html($desc) . '</div>';
                $html = cbia_replace_first_occurrence($html, $mk['full'], $placeholder);
                $images[] = array(
                    'idx' => $i,
                    'section' => $section,
                    'desc' => $desc,
                    'ok' => 1,
                    'mode' => 'placeholder',
                );
                $this->emit($emit, 'cbia_content', array('html' => $html));
                $this->emit($emit, 'cbia_image', array(
                    'idx' => $i,
                    'section' => $section,
                    'desc' => $desc,
                    'status' => 'done',
                    'ok' => 1,
                    'mode' => 'placeholder',
                ));
            }

            return array('html' => $html, 'images' => $images, 'warnings' => $warnings, 'temp_attachment_ids' => $temp_attachment_ids);
        }

        private function emit($emit, $event, array $payload): void {
            if (!is_callable($emit)) return;
            call_user_func($emit, (string)$event, $payload);
        }

        private function emit_text_progress($emit, string $html): void {
            if (!is_callable($emit)) return;
            $buffer = '';
            $step = 0;
            if (!preg_match_all('/(<[^>]+>|[^<]+)/u', $html, $segments)) return;
            foreach (($segments[0] ?? array()) as $seg) {
                if ($seg === '') continue;
                // Keep tags intact; split only visible text into smaller "typing" chunks.
                if ($seg[0] === '<') {
                    $buffer .= $seg;
                } else {
                    $tokens = preg_split('/(\s+)/u', $seg, -1, PREG_SPLIT_DELIM_CAPTURE);
                    if (!is_array($tokens)) $tokens = array($seg);
                    foreach ($tokens as $token) {
                        if ($token === '') continue;
                        $buffer .= $token;
                        $step++;
                        if ($step % 4 === 0) {
                            $this->emit($emit, 'text_progress', array(
                                'html' => $buffer,
                                'word_count' => $this->word_count($buffer),
                            ));
                            if ($step % 12 === 0) {
                                $this->emit($emit, 'word_count', array('count' => $this->word_count($buffer)));
                            }
                        }
                    }
                }
            }
            $this->emit($emit, 'text_progress', array(
                'html' => $buffer,
                'word_count' => $this->word_count($buffer),
            ));
        }

        private function word_count(string $html): int {
            $plain = trim(wp_strip_all_tags($html));
            if ($plain === '') return 0;
            if (!preg_match_all('/[\p{L}\p{N}]+/u', $plain, $matches)) {
                return 0;
            }
            return count($matches[0]);
        }

        private function pick_featured_image(array $images): array {
            foreach ($images as $row) {
                if (!is_array($row)) continue;
                if (!empty($row['url'])) return $row;
            }
            return array();
        }
    }
}
