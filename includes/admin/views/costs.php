<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Costs tab view (extracted from legacy cbia-costes.php)

if (!current_user_can('manage_options')) return;

$cbia = cbia_get_settings();
$cbia_service = isset($cbia_costs_service) ? $cbia_costs_service : null;
$cbia_cost = $cbia_service && method_exists($cbia_service, 'get_settings')
    ? $cbia_service->get_settings()
    : cbia_costes_get_settings();

$cbia_defaults = array(
    'usd_to_eur' => 0.92,
    'tokens_per_word' => 1.30,
    'input_overhead_tokens' => 350,
    'per_image_overhead_words' => 18,
    'cached_input_ratio' => 0.0, // 0..1
    // Images: use fixed pricing per generation (recommended)
    'use_image_flat_pricing' => 1,
    'image_flat_usd_mini' => 0.011,
    'image_flat_usd_full' => 0.042,
    'image_flat_usd_openai_mini' => 0.011,
    'image_flat_usd_openai_full' => 0.042,
    'image_flat_usd_imagen3' => 0.040,
    'image_flat_usd_imagen4' => 0.040,
    // Fine tuning
    'responses_fixed_usd_per_call' => 0.000,
    'real_adjust_multiplier' => 1.00,
    // Automatic model adjustment (only if REAL multiplier is 1.0)
    'real_adjust_multiplier_by_model' => array(),

    // Multipliers to approximate failures/retries
    'mult_text'  => 1.00,
    'mult_image' => 1.00,
    'mult_seo'   => 1.00,

    // Calls per post (estimate)
    'text_calls_per_post'  => 1,
    'image_calls_per_post' => 0, // 0 => usa images_limit

// Image model
    'image_model' => 'gpt-image-1-mini',

    // Output tokens per image call (optional)
    'image_output_tokens_per_call' => 0,

    // SEO (relleno Yoast / metas / etc)
    'seo_calls_per_post' => 0,
    'seo_model' => '',
    'seo_input_tokens_per_call' => 320,
    'seo_output_tokens_per_call' => 180,
);
$cbia_cost = array_merge($cbia_defaults, $cbia_cost);

$cbia_table = cbia_costes_price_table_usd_per_million();
$cbia_image_provider_current = function_exists('cbia_get_image_provider') ? (string)cbia_get_image_provider() : 'openai';

$cbia_model_text_current = function_exists('cbia_costes_get_current_text_model')
    ? (string)cbia_costes_get_current_text_model($cbia)
    : (isset($cbia['openai_model']) ? (string)$cbia['openai_model'] : 'gpt-4.1-mini');
if (!isset($cbia_table[$cbia_model_text_current])) $cbia_model_text_current = 'gpt-4.1-mini';

$cbia_model_img_current = isset($cbia_cost['image_model']) ? (string)$cbia_cost['image_model'] : '';
if ($cbia_model_img_current === '' && function_exists('cbia_costes_get_current_image_model')) {
    $cbia_model_img_current = (string)cbia_costes_get_current_image_model($cbia);
}
if ($cbia_model_img_current === '') $cbia_model_img_current = 'gpt-image-1-mini';

$cbia_model_seo_current = (string)($cbia_cost['seo_model'] ?? '');
if ($cbia_model_seo_current === '' || !isset($cbia_table[$cbia_model_seo_current])) $cbia_model_seo_current = $cbia_model_text_current;

$cbia_notice = '';
$cbia_calibration_info = null;

/* ===== Handle POST ===== */

if ($cbia_service && method_exists($cbia_service, 'handle_post')) {
    list($cbia_cost, $cbia_notice, $cbia_calibration_info) = $cbia_service->handle_post($cbia_cost, $cbia, $cbia_defaults, $cbia_table, $cbia_model_text_current);
} elseif (function_exists('cbia_costes_handle_post')) {
    list($cbia_cost, $cbia_notice, $cbia_calibration_info) = cbia_costes_handle_post($cbia_cost, $cbia, $cbia_defaults, $cbia_table, $cbia_model_text_current);
}


// refrescar
$cbia_cost_latest = $cbia_service && method_exists($cbia_service, 'get_settings')
    ? $cbia_service->get_settings()
    : cbia_costes_get_settings();
$cbia_cost = array_merge($cbia_defaults, $cbia_cost_latest);
$cbia_log  = $cbia_service && method_exists($cbia_service, 'get_log')
    ? $cbia_service->get_log()
    : cbia_costes_log_get();

$cbia_model_text_current = function_exists('cbia_costes_get_current_text_model')
    ? (string)cbia_costes_get_current_text_model($cbia)
    : (isset($cbia['openai_model']) ? (string)$cbia['openai_model'] : 'gpt-4.1-mini');
if (!isset($cbia_table[$cbia_model_text_current])) $cbia_model_text_current = 'gpt-4.1-mini';

$cbia_model_img_current = isset($cbia_cost['image_model']) ? (string)$cbia_cost['image_model'] : '';
if ($cbia_model_img_current === '' && function_exists('cbia_costes_get_current_image_model')) {
    $cbia_model_img_current = (string)cbia_costes_get_current_image_model($cbia);
}
if ($cbia_model_img_current === '') $cbia_model_img_current = 'gpt-image-1-mini';

$cbia_model_seo_current = (string)($cbia_cost['seo_model'] ?? '');
if ($cbia_model_seo_current === '' || !isset($cbia_table[$cbia_model_seo_current])) $cbia_model_seo_current = $cbia_model_text_current;

// Effective adjustment applied right now (visible in UI)
$cbia_applied_mult = (float)($cbia_cost['real_adjust_multiplier'] ?? 1.0);
$cbia_applied_source = 'global';
if ($cbia_applied_mult <= 0) $cbia_applied_mult = 1.0;
if ($cbia_applied_mult == 1.0 && function_exists('cbia_costes_get_model_multiplier')) {
    $cbia_model_mult = (float)cbia_costes_get_model_multiplier($cbia_model_text_current, $cbia_cost);
    if ($cbia_model_mult > 0 && $cbia_model_mult != 1.0) {
        $cbia_applied_mult = $cbia_model_mult;
        $cbia_applied_source = 'model';
    }
}
// Calls per post
$cbia_text_calls = max(1, (int)$cbia_cost['text_calls_per_post']);
$cbia_img_calls  = (int)$cbia_cost['image_calls_per_post'];

if ($cbia_img_calls <= 0) {
    $cbia_img_calls = isset($cbia['images_limit']) ? (int)$cbia['images_limit'] : 3;
}
$cbia_img_calls = max(0, min(20, $cbia_img_calls));

$cbia_seo_calls = max(0, (int)$cbia_cost['seo_calls_per_post']);
$cbia_seo_calls = min(20, $cbia_seo_calls);

// Estimated TEXT tokens per call
$cbia_in_tokens_text_per_call  = cbia_costes_estimate_input_tokens('{title}', $cbia, (float)$cbia_cost['tokens_per_word'], (int)$cbia_cost['input_overhead_tokens']);
$cbia_out_tokens_text_per_call = cbia_costes_estimate_output_tokens($cbia, (float)$cbia_cost['tokens_per_word']);

// Image: input per call, configurable output
$cbia_in_tokens_img_per_call   = cbia_costes_estimate_image_prompt_input_tokens_per_call($cbia, (float)$cbia_cost['tokens_per_word'], (int)$cbia_cost['per_image_overhead_words']);
$cbia_out_tokens_img_per_call  = max(0, (int)$cbia_cost['image_output_tokens_per_call']);

// SEO: configurable tokens per call
$cbia_in_tokens_seo_per_call   = max(0, (int)$cbia_cost['seo_input_tokens_per_call']);
$cbia_out_tokens_seo_per_call  = max(0, (int)$cbia_cost['seo_output_tokens_per_call']);

// Retry multipliers
$cbia_in_tokens_text_per_call_m  = (int)ceil($cbia_in_tokens_text_per_call  * (float)$cbia_cost['mult_text']);
$cbia_out_tokens_text_per_call_m = (int)ceil($cbia_out_tokens_text_per_call * (float)$cbia_cost['mult_text']);

$cbia_in_tokens_img_per_call_m   = (int)ceil($cbia_in_tokens_img_per_call   * (float)$cbia_cost['mult_image']);
$cbia_out_tokens_img_per_call_m  = (int)ceil($cbia_out_tokens_img_per_call  * (float)$cbia_cost['mult_image']);

$cbia_in_tokens_seo_per_call_m   = (int)ceil($cbia_in_tokens_seo_per_call   * (float)$cbia_cost['mult_seo']);
$cbia_out_tokens_seo_per_call_m  = (int)ceil($cbia_out_tokens_seo_per_call  * (float)$cbia_cost['mult_seo']);

// Totals per post
$cbia_in_tokens_text_total  = $cbia_in_tokens_text_per_call_m  * $cbia_text_calls;
$cbia_out_tokens_text_total = $cbia_out_tokens_text_per_call_m * $cbia_text_calls;

$cbia_in_tokens_img_total   = $cbia_in_tokens_img_per_call_m   * $cbia_img_calls;
$cbia_out_tokens_img_total  = $cbia_out_tokens_img_per_call_m  * $cbia_img_calls;

$cbia_in_tokens_seo_total   = $cbia_in_tokens_seo_per_call_m   * $cbia_seo_calls;
$cbia_out_tokens_seo_total  = $cbia_out_tokens_seo_per_call_m  * $cbia_seo_calls;

// Estimated costs by block
list($cbia_eur_total_text, $cbia_eur_in_text, $cbia_eur_out_text) =
    cbia_costes_calc_cost_eur($cbia_model_text_current, $cbia_in_tokens_text_total, $cbia_out_tokens_text_total, (float)$cbia_cost['usd_to_eur'], (float)$cbia_cost['cached_input_ratio']);

list($cbia_eur_total_img, $cbia_eur_in_img, $cbia_eur_out_img) =
    cbia_costes_calc_cost_eur($cbia_model_img_current, $cbia_in_tokens_img_total, $cbia_out_tokens_img_total, (float)$cbia_cost['usd_to_eur'], (float)$cbia_cost['cached_input_ratio']);

$cbia_eur_total_seo = 0.0; $cbia_eur_in_seo = 0.0; $cbia_eur_out_seo = 0.0;
if ($cbia_seo_calls > 0 && ($cbia_in_tokens_seo_total > 0 || $cbia_out_tokens_seo_total > 0)) {
    list($cbia_eur_total_seo_tmp, $cbia_eur_in_seo_tmp, $cbia_eur_out_seo_tmp) =
        cbia_costes_calc_cost_eur($cbia_model_seo_current, $cbia_in_tokens_seo_total, $cbia_out_tokens_seo_total, (float)$cbia_cost['usd_to_eur'], (float)$cbia_cost['cached_input_ratio']);
    if ($cbia_eur_total_seo_tmp !== null) {
        $cbia_eur_total_seo = (float)$cbia_eur_total_seo_tmp;
        $cbia_eur_in_seo = (float)$cbia_eur_in_seo_tmp;
        $cbia_eur_out_seo = (float)$cbia_eur_out_seo_tmp;
    }
}

$cbia_eur_total_est = null;
if ($cbia_eur_total_text !== null && $cbia_eur_total_img !== null) {
    $cbia_eur_total_est = (float)$cbia_eur_total_text + (float)$cbia_eur_total_img + (float)$cbia_eur_total_seo;
}

// Notices
if ($cbia_notice === 'saved') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Costs settings saved.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($cbia_notice === 'log') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Log cleared.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($cbia_notice === 'calc') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Calculation executed. Review the log.', 'cbiastudio-blogflow-ai') . '</p></div>';
}

if (is_array($cbia_calibration_info)) {
    $cbia_actual_eur = (float)($cbia_calibration_info['actual_eur'] ?? 0);
    $cbia_estimated_eur = (float)($cbia_calibration_info['estimated_eur'] ?? 0);
    $cbia_suggested = (float)($cbia_calibration_info['suggested'] ?? 1);
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('Calibration applied.', 'cbiastudio-blogflow-ai') . '</strong> ' .
    'Billing: ' . esc_html(number_format($cbia_actual_eur, 4, ',', '.')) . ' EUR | ' .
    'Calculated real: ' . esc_html(number_format($cbia_estimated_eur, 4, ',', '.')) . ' EUR | ' .
    'Multiplier: <code>' . esc_html(number_format($cbia_suggested, 4, ',', '.')) . '</code></p></div>';
}
?>
<div class="wrap" style="padding-left:0;">
<h2><?php echo esc_html__('Costs', 'cbiastudio-blogflow-ai'); ?></h2>
<div class="notice notice-info" style="margin:8px 0 16px 0;">
<p style="margin:6px 0;">
<strong><?php echo esc_html__('Effective REAL multiplier:', 'cbiastudio-blogflow-ai'); ?></strong>
<code><?php echo esc_html(number_format((float)$cbia_applied_mult, 4, ',', '.')); ?>x</code>
<?php if ($cbia_applied_source === 'model') : ?>
<span class="description">(by model: <?php echo esc_html($cbia_model_text_current); ?>)</span>
<?php else : ?>
<span class="description">(by global setting)</span>
<?php endif; ?>
</p>
<p style="margin:6px 0;" class="description">No hidden model-specific multiplier is applied anymore. Only the visible global REAL multiplier affects the calculation.</p>
</div>
<h3><?php echo esc_html__('Quick estimate (based on current config)', 'cbiastudio-blogflow-ai'); ?></h3>
<table class="widefat striped" style="max-width:980px;">
<tbody>
<tr>
<td style="width:280px;"><strong>TEXT model (Config)</strong></td>
<td>
<code><?php echo esc_html($cbia_model_text_current); ?></code>
</td>
</tr>
<tr>
<td><strong>IMAGE model (Costs)</strong></td>
<td><code><?php echo esc_html($cbia_model_img_current); ?></code></td>
</tr>
<tr>
<td><strong>SEO model (Costs)</strong></td>
<td><code><?php echo esc_html($cbia_model_seo_current); ?></code></td>
</tr>
<tr>
<td><strong>Text calls per post</strong></td>
<td><code><?php echo esc_html((int)$cbia_text_calls); ?></code></td>
</tr>
<tr>
<td><strong>Image calls per post</strong></td>
<td><code><?php echo esc_html((int)$cbia_img_calls); ?></code></td>
</tr>
<tr>
<td><strong>SEO calls per post</strong></td>
<td><code><?php echo esc_html((int)$cbia_seo_calls); ?></code></td>
</tr>
<tr>
<td><strong>Input tokens TEXTO (total post)</strong></td>
<td><code><?php echo esc_html((int)$cbia_in_tokens_text_total); ?></code></td>
</tr>
<tr>
<td><strong>Output tokens TEXTO (total post)</strong></td>
<td><code><?php echo esc_html((int)$cbia_out_tokens_text_total); ?></code></td>
</tr>
<tr>
<td><strong>Input tokens IMAGEN (total post)</strong></td>
<td><code><?php echo esc_html((int)$cbia_in_tokens_img_total); ?></code></td>
</tr>
<tr>
<td><strong>Output tokens IMAGEN (total post)</strong></td>
<td><code><?php echo esc_html((int)$cbia_out_tokens_img_total); ?></code> <span class="description">(if set to 0, only input is estimated)</span></td>
</tr>
<tr>
<td><strong>Input tokens SEO (total post)</strong></td>
<td><code><?php echo esc_html((int)$cbia_in_tokens_seo_total); ?></code></td>
</tr>
<tr>
<td><strong>Output tokens SEO (total post)</strong></td>
<td><code><?php echo esc_html((int)$cbia_out_tokens_seo_total); ?></code></td>
</tr>
<tr>
<td><strong>Estimated cost (TEXT)</strong></td>
<td>
<?php
echo ($cbia_eur_total_text === null)
? '<span style="color:#b70000;">Model not found in table</span>'
: '<strong>' . esc_html(number_format((float)$cbia_eur_total_text, 4, ',', '.')) . ' EUR</strong> <span class="description">(in ' . number_format((float)$cbia_eur_in_text, 4, ',', '.') . ' EUR | out ' . number_format((float)$cbia_eur_out_text, 4, ',', '.') . ' EUR)</span>';
?>
</td>
</tr>
<tr>
<td><strong>Estimated cost (IMAGES)</strong></td>
<td>
<?php
echo ($cbia_eur_total_img === null)
? '<span style="color:#b70000;">Model not found in table</span>'
: '<strong>' . esc_html(number_format((float)$cbia_eur_total_img, 4, ',', '.')) . ' EUR</strong> <span class="description">(in ' . number_format((float)$cbia_eur_in_img, 4, ',', '.') . ' EUR | out ' . number_format((float)$cbia_eur_out_img, 4, ',', '.') . ' EUR)</span>';
?>
</td>
</tr>
<tr>
<td><strong>Estimated cost (SEO)</strong></td>
<td>
<strong><?php echo esc_html(number_format((float)$cbia_eur_total_seo, 4, ',', '.')); ?> EUR</strong>
<span class="description">(in <?php echo esc_html(number_format((float)$cbia_eur_in_seo, 4, ',', '.')); ?> EUR | out <?php echo esc_html(number_format((float)$cbia_eur_out_seo, 4, ',', '.')); ?> EUR)</span>
</td>
</tr>
<tr>
<td><strong>Total estimated cost</strong></td>
<td>
<?php
echo ($cbia_eur_total_est === null)
? '<span style="color:#b70000;">Could not estimate (model not in table)</span>'
: '<strong style="font-size:16px;">' . esc_html(number_format((float)$cbia_eur_total_est, 4, ',', '.')) . ' EUR</strong>';
?>
</td>
</tr>
</tbody>
</table>

<hr/>

<h3><?php echo esc_html__('Configuration', 'cbiastudio-blogflow-ai'); ?></h3>
<form method="post" action="" autocomplete="off">
<input type="hidden" name="cbia_form" value="costes_settings" />
<?php wp_nonce_field('cbia_costes_settings_nonce'); ?>

<table class="form-table" style="max-width:980px;">
<tr>
<th>USD to EUR conversion</th>
<td>
<input type="number" step="0.01" min="0.5" max="1.5" name="usd_to_eur" value="<?php echo esc_attr((string)$cbia_cost['usd_to_eur']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Tokens per word (approx.)</th>
<td>
<input type="number" step="0.01" min="0.5" max="2" name="tokens_per_word" value="<?php echo esc_attr((string)$cbia_cost['tokens_per_word']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Input overhead (tokens) per text call</th>
<td>
<input type="number" min="0" max="5000" name="input_overhead_tokens" value="<?php echo esc_attr((int)$cbia_cost['input_overhead_tokens']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Image overhead (words) per call</th>
<td>
<input type="number" min="0" max="300" name="per_image_overhead_words" value="<?php echo esc_attr((int)$cbia_cost['per_image_overhead_words']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Ratio cached input (0..1)</th>
<td>
<input type="number" step="0.05" min="0" max="1" name="cached_input_ratio" value="<?php echo esc_attr((string)$cbia_cost['cached_input_ratio']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Fixed surcharge per TEXT/SEO call (USD)</th>
<td>
<input type="number" step="0.001" min="0" max="0.050" name="responses_fixed_usd_per_call" value="<?php echo esc_attr((string)$cbia_cost['responses_fixed_usd_per_call']); ?>" style="width:120px;" />
<p class="description">Fine-tuning to match real billing (applies to each text/SEO call).</p>
</td>
</tr>
<tr>
<th>Retry multiplier (text)</th>
<td>
<input type="number" step="0.05" min="1" max="5" name="mult_text" value="<?php echo esc_attr((string)$cbia_cost['mult_text']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Retry multiplier (images)</th>
<td>
<input type="number" step="0.05" min="1" max="5" name="mult_image" value="<?php echo esc_attr((string)$cbia_cost['mult_image']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Images: use fixed price per generation</th>
<td>
<label><input type="checkbox" name="use_image_flat_pricing" value="1" <?php checked(!empty($cbia_cost['use_image_flat_pricing'])); ?> /> Enable (recommended). Closer to real billing.</label>
<p class="description">When enabled, estimate and REAL calculation use fixed price per image, ignoring image tokens.</p>
</td>
</tr>
<tr>
<th>Retry multiplier (SEO)</th>
<td>
<input type="number" step="0.05" min="1" max="5" name="mult_seo" value="<?php echo esc_attr((string)$cbia_cost['mult_seo']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Total multiplier adjustment (REAL)</th>
<td>
<input type="number" step="0.01" min="0.5" max="1.5" name="real_adjust_multiplier" value="<?php echo esc_attr((string)$cbia_cost['real_adjust_multiplier']); ?>" style="width:120px;" />
<p class="description">Multiplies the real total. Useful to compensate small conversion/rounding differences.</p>
</td>
</tr>
<tr>
<th>Number of TEXT calls per post</th>
<td>
<input type="number" min="1" max="20" name="text_calls_per_post" value="<?php echo esc_attr((int)$cbia_cost['text_calls_per_post']); ?>" style="width:120px;" />
<p class="description">If your engine makes more than one text call, increase it here.</p>
</td>
</tr>
<tr>
<th>Number of IMAGE calls per post</th>
<td>
<input type="number" min="0" max="20" name="image_calls_per_post" value="<?php echo esc_attr((int)$cbia_cost['image_calls_per_post']); ?>" style="width:120px;" />
<p class="description">If set to 0, <code>images_limit</code> from Config is used.</p>
</td>
</tr>
<tr>
<th>Image model</th>
<td>
<select name="image_model" class="abb-select" style="width:240px;">
<option value="gpt-image-1-mini" <?php selected($cbia_model_img_current, 'gpt-image-1-mini'); ?>>gpt-image-1-mini</option>
<option value="gpt-image-1" <?php selected($cbia_model_img_current, 'gpt-image-1'); ?>>gpt-image-1</option>
<option value="imagen-3.0-generate-002" <?php selected($cbia_model_img_current, 'imagen-3.0-generate-002'); ?>>imagen-3.0-generate-002</option>
<option value="imagen-4.0-generate-001" <?php selected($cbia_model_img_current, 'imagen-4.0-generate-001'); ?>>imagen-4.0-generate-001</option>
</select>
<p class="description">Current image provider: <code><?php echo esc_html($cbia_image_provider_current); ?></code>. Use this field as an override if you want to fine-tune estimation manually.</p>
<p class="description">Fixed prices per image (USD): OpenAI mini <input type="number" step="0.001" min="0" name="image_flat_usd_openai_mini" value="<?php echo esc_attr((string)$cbia_cost['image_flat_usd_openai_mini']); ?>" style="width:90px;" /> &nbsp;OpenAI full <input type="number" step="0.001" min="0" name="image_flat_usd_openai_full" value="<?php echo esc_attr((string)$cbia_cost['image_flat_usd_openai_full']); ?>" style="width:90px;" /></p>
<p class="description">Google Imagen 3 <input type="number" step="0.001" min="0" name="image_flat_usd_imagen3" value="<?php echo esc_attr((string)$cbia_cost['image_flat_usd_imagen3']); ?>" style="width:90px;" /> &nbsp;Google Imagen 4 <input type="number" step="0.001" min="0" name="image_flat_usd_imagen4" value="<?php echo esc_attr((string)$cbia_cost['image_flat_usd_imagen4']); ?>" style="width:90px;" /></p>
</td>
</tr>
<tr>
<th>Output tokens per image call (optional)</th>
<td>
<input type="number" min="0" max="50000" name="image_output_tokens_per_call" value="<?php echo esc_attr((int)$cbia_cost['image_output_tokens_per_call']); ?>" style="width:120px;" />
<p class="description">If set to 0, estimation mostly counts input tokens.</p>
</td>
</tr>
<tr><th colspan="2"><hr/></th></tr>
<tr>
<th>Number of SEO calls per post</th>
<td>
<input type="number" min="0" max="20" name="seo_calls_per_post" value="<?php echo esc_attr((int)$cbia_cost['seo_calls_per_post']); ?>" style="width:120px;" />
<p class="description">If your Yoast/SEO fill makes OpenAI calls (meta, keyphrase, etc), set them here for estimation.</p>
</td>
</tr>
<tr>
<th>SEO model</th>
<td>
<select name="seo_model" class="abb-select" style="width:240px;">
<?php
$cbia_seo_candidates = array_values(array_filter(array_keys($cbia_table), static function ($model) {
    return strpos((string)$model, 'gpt-image-') !== 0 && strpos((string)$model, 'imagen-') !== 0;
}));
sort($cbia_seo_candidates, SORT_STRING);
foreach ($cbia_seo_candidates as $m) {
    if (!isset($cbia_table[$m])) continue;
    echo '<option value="' . esc_attr($m) . '" ' . selected($cbia_model_seo_current, $m, false) . '>' . esc_html($m) . '</option>';
}
?>
</select>
<p class="description">If unsure, keep the same as text model.</p>
</td>
</tr>
<tr>
<th>Input tokens per SEO call</th>
<td>
<input type="number" min="0" max="50000" name="seo_input_tokens_per_call" value="<?php echo esc_attr((int)$cbia_cost['seo_input_tokens_per_call']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Output tokens per SEO call</th>
<td>
<input type="number" min="0" max="50000" name="seo_output_tokens_per_call" value="<?php echo esc_attr((int)$cbia_cost['seo_output_tokens_per_call']); ?>" style="width:120px;" />
</td>
</tr>
</table>

<p>
<button type="submit" class="button button-primary"><?php echo esc_html__('Save costs settings', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</form>

<hr/>

<h3><?php echo esc_html__('Actions (post-hoc)', 'cbiastudio-blogflow-ai'); ?></h3>
<form method="post" action="" autocomplete="off">
<input type="hidden" name="cbia_form" value="costes_actions" />
<?php wp_nonce_field('cbia_costes_actions_nonce'); ?>

<table class="form-table" style="max-width:980px;">
<tr>
<th>Calculate last N posts</th>
<td>
<input type="number" name="calc_last_n" min="1" max="200" value="20" style="width:120px;" />
<label style="margin-left:14px;">
<input type="checkbox" name="calc_only_cbia" value="1" checked />
Only plugin posts (<code>_cbia_created=1</code>)
</label>
<label style="margin-left:14px;">
<input type="checkbox" name="calc_estimate_if_missing" value="1" checked />
If there is no real usage, use estimation
</label>
</td>
</tr>
<tr>
<th>Calibrate using real billing (EUR)</th>
<td>
<input type="number" name="calibrate_actual_eur" step="0.01" min="0" placeholder="Ej: 1.84" style="width:120px;" />
<span class="description" style="margin-left:8px;">Enter real spend for those N posts and we adjust REAL multiplier automatically.</span>
</td>
</tr>
</table>

<p>
<button type="submit" class="button button-primary" name="cbia_action" value="calc_last">Calculate</button>
<button type="submit" class="button" name="cbia_action" value="calc_last_real" style="margin-left:8px;"><?php echo esc_html__('Calculate REAL only', 'cbiastudio-blogflow-ai'); ?></button>
<button type="submit" class="button button-secondary" name="cbia_action" value="calibrate_real" style="margin-left:8px;">Calibrate REAL from billing</button>
<button type="submit" class="button button-secondary" name="cbia_action" value="clear_log" style="margin-left:8px;">Clear log</button>
</p>
</form>

<h3><?php echo esc_html__('Costs log', 'cbiastudio-blogflow-ai'); ?></h3>
<textarea id="cbia-costes-log" rows="14" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea($cbia_log); ?></textarea>

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {
    const logBox = document.getElementById('cbia-costes-log');
                function refreshLog(){
                    if (typeof ajaxurl === 'undefined') return;
                    const nonce = (window.CBIA && CBIA.nonce) ? CBIA.nonce : '';
                    const url = ajaxurl + '?action=cbia_get_costes_log' + (nonce ? '&_ajax_nonce=' + encodeURIComponent(nonce) : '');
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
<?php wp_add_inline_script('abb-admin', (string) ob_get_clean(), 'after'); ?>
</div>
