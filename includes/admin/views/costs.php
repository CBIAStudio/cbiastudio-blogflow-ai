<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Costs tab view (extracted from legacy cbia-costes.php)

if (!current_user_can('manage_options')) return;

$cbia_costs_embedded = !empty($cbia_costs_embedded);

$cbia = cbia_get_settings();
$service = isset($cbia_costs_service) ? $cbia_costs_service : null;
$cost = $service && method_exists($service, 'get_settings')
    ? $service->get_settings()
    : cbia_costes_get_settings();

$defaults = array(
    'usd_to_eur' => 0.92,
    'tokens_per_word' => 1.30,
    'input_overhead_tokens' => 350,
    'per_image_overhead_words' => 18,
    'cached_input_ratio' => 0.0, // 0..1
    // ImÃ¡genes: usar precio fijo por generaciÃ³n (recomendado)
    'use_image_flat_pricing' => 1,
    'image_flat_usd_mini' => 0.052,
    'image_flat_usd_full' => 0.250,
    'image_flat_usd_openai_mini' => 0.052,
    'image_flat_usd_openai_full' => 0.250,
    'image_flat_usd_openai_v2' => 0.165,
    'image_flat_usd_imagen3' => 0.040,
    'image_flat_usd_imagen4' => 0.040,
    // Ajustes finos
    'responses_fixed_usd_per_call' => 0.000,
    'real_adjust_multiplier' => 1.00,
    'count_failed_attempt_costs' => 1,
    'failed_text_input_ratio' => 1.0,
    'failed_text_output_ratio' => 0.0,
    'failed_image_flat_ratio' => 0.35,
    // Ajuste automÃ¡tico por modelo (solo si el multiplicador REAL estÃ¡ en 1.0)
    'real_adjust_multiplier_by_model' => array(),

    // Multiplicadores para aproximar fallos/reintentos
    'mult_text'  => 1.00,
    'mult_image' => 1.00,
    'mult_seo'   => 1.00,

    // llamadas por post (estimaciÃ³n)
    'text_calls_per_post'  => 1,
    'image_calls_per_post' => 0, // 0 => usa images_limit

    // modelo imagen
    'image_model' => 'gpt-image-2',

    // output tokens por llamada de imagen (opcional)
    'image_output_tokens_per_call' => 0,

    // SEO (relleno Yoast / metas / etc)
    'seo_calls_per_post' => 0,
    'seo_model' => '',
    'seo_input_tokens_per_call' => 320,
    'seo_output_tokens_per_call' => 180,
);
$cost = array_merge($defaults, $cost);

$table = cbia_costes_price_table_usd_per_million();

$text_provider_current = function_exists('cbia_get_text_provider') ? (string)cbia_get_text_provider() : 'openai';
$image_provider_current = function_exists('cbia_get_image_provider') ? (string)cbia_get_image_provider() : 'openai';
$model_text_current = function_exists('cbia_costes_get_current_text_model')
    ? (string)cbia_costes_get_current_text_model($cbia)
    : (isset($cbia['openai_model']) ? (string)$cbia['openai_model'] : 'gpt-5-mini');
if (!isset($table[$model_text_current])) $model_text_current = 'gpt-5-mini';

$model_img_current = isset($cost['image_model']) ? (string)$cost['image_model'] : '';
if ($model_img_current === '' && function_exists('cbia_costes_get_current_image_model')) {
    $model_img_current = (string)cbia_costes_get_current_image_model($cbia);
}
if ($model_img_current === '') $model_img_current = 'gpt-image-2';

$model_seo_current = (string)($cost['seo_model'] ?? '');
if ($model_seo_current === '' || !isset($table[$model_seo_current])) $model_seo_current = $model_text_current;

$notice = '';
$calibration_info = null;

/* ===== Handle POST ===== */

if ($service && method_exists($service, 'handle_post')) {
    list($cost, $notice, $calibration_info) = $service->handle_post($cost, $cbia, $defaults, $table, $model_text_current);
} elseif (function_exists('cbia_costes_handle_post')) {
    list($cost, $notice, $calibration_info) = cbia_costes_handle_post($cost, $cbia, $defaults, $table, $model_text_current);
}


// refrescar
$cost_latest = $service && method_exists($service, 'get_settings')
    ? $service->get_settings()
    : cbia_costes_get_settings();
$cost = array_merge($defaults, $cost_latest);
$log  = $service && method_exists($service, 'get_log')
    ? $service->get_log()
    : cbia_costes_log_get();

$model_text_current = function_exists('cbia_costes_get_current_text_model')
    ? (string)cbia_costes_get_current_text_model($cbia)
    : (isset($cbia['openai_model']) ? (string)$cbia['openai_model'] : 'gpt-5-mini');
if (!isset($table[$model_text_current])) $model_text_current = 'gpt-5-mini';

$model_img_current = isset($cost['image_model']) ? (string)$cost['image_model'] : '';
if ($model_img_current === '' && function_exists('cbia_costes_get_current_image_model')) {
    $model_img_current = (string)cbia_costes_get_current_image_model($cbia);
}
if ($model_img_current === '') $model_img_current = 'gpt-image-2';

$model_seo_current = (string)($cost['seo_model'] ?? '');
if ($model_seo_current === '' || !isset($table[$model_seo_current])) $model_seo_current = $model_text_current;

// Ajuste efectivo aplicado ahora mismo (UX: hacerlo visible)
$applied_mult = (float)($cost['real_adjust_multiplier'] ?? 1.0);
$applied_source = 'global';
if ($applied_mult <= 0) $applied_mult = 1.0;
if ($applied_mult == 1.0 && function_exists('cbia_costes_get_model_multiplier')) {
    $model_mult = (float)cbia_costes_get_model_multiplier($model_text_current, $cost);
    if ($model_mult > 0 && $model_mult != 1.0) {
        $applied_mult = $model_mult;
        $applied_source = 'modelo';
    }
}
// llamadas por post
$text_calls = max(1, (int)$cost['text_calls_per_post']);
$img_calls  = (int)$cost['image_calls_per_post'];

if ($img_calls <= 0) {
    $img_calls = isset($cbia['images_limit']) ? (int)$cbia['images_limit'] : 3;
}
$img_calls = max(0, min(20, $img_calls));

$seo_calls = max(0, (int)$cost['seo_calls_per_post']);
$seo_calls = min(20, $seo_calls);

// EstimaciÃ³n tokens TEXTO por llamada
$in_tokens_text_per_call  = cbia_costes_estimate_input_tokens('{title}', $cbia, (float)$cost['tokens_per_word'], (int)$cost['input_overhead_tokens']);
$out_tokens_text_per_call = cbia_costes_estimate_output_tokens($cbia, (float)$cost['tokens_per_word']);

// Imagen: input por llamada, output configurable
$in_tokens_img_per_call   = cbia_costes_estimate_image_prompt_input_tokens_per_call($cbia, (float)$cost['tokens_per_word'], (int)$cost['per_image_overhead_words']);
$out_tokens_img_per_call  = max(0, (int)$cost['image_output_tokens_per_call']);

// SEO: tokens por llamada configurables
$in_tokens_seo_per_call   = max(0, (int)$cost['seo_input_tokens_per_call']);
$out_tokens_seo_per_call  = max(0, (int)$cost['seo_output_tokens_per_call']);

// Multiplicadores reintentos
$in_tokens_text_per_call_m  = (int)ceil($in_tokens_text_per_call  * (float)$cost['mult_text']);
$out_tokens_text_per_call_m = (int)ceil($out_tokens_text_per_call * (float)$cost['mult_text']);

$in_tokens_img_per_call_m   = (int)ceil($in_tokens_img_per_call   * (float)$cost['mult_image']);
$out_tokens_img_per_call_m  = (int)ceil($out_tokens_img_per_call  * (float)$cost['mult_image']);

$in_tokens_seo_per_call_m   = (int)ceil($in_tokens_seo_per_call   * (float)$cost['mult_seo']);
$out_tokens_seo_per_call_m  = (int)ceil($out_tokens_seo_per_call  * (float)$cost['mult_seo']);

// Totales por post
$in_tokens_text_total  = $in_tokens_text_per_call_m  * $text_calls;
$out_tokens_text_total = $out_tokens_text_per_call_m * $text_calls;

$in_tokens_img_total   = $in_tokens_img_per_call_m   * $img_calls;
$out_tokens_img_total  = $out_tokens_img_per_call_m  * $img_calls;

$in_tokens_seo_total   = $in_tokens_seo_per_call_m   * $seo_calls;
$out_tokens_seo_total  = $out_tokens_seo_per_call_m  * $seo_calls;

// Costes estimados por bloque
list($eur_total_text, $eur_in_text, $eur_out_text) =
    cbia_costes_calc_cost_eur($model_text_current, $in_tokens_text_total, $out_tokens_text_total, (float)$cost['usd_to_eur'], (float)$cost['cached_input_ratio']);

list($eur_total_img, $eur_in_img, $eur_out_img) =
    cbia_costes_calc_cost_eur($model_img_current, $in_tokens_img_total, $out_tokens_img_total, (float)$cost['usd_to_eur'], (float)$cost['cached_input_ratio']);

$eur_total_seo = 0.0; $eur_in_seo = 0.0; $eur_out_seo = 0.0;
if ($seo_calls > 0 && ($in_tokens_seo_total > 0 || $out_tokens_seo_total > 0)) {
    list($eur_total_seo_tmp, $eur_in_seo_tmp, $eur_out_seo_tmp) =
        cbia_costes_calc_cost_eur($model_seo_current, $in_tokens_seo_total, $out_tokens_seo_total, (float)$cost['usd_to_eur'], (float)$cost['cached_input_ratio']);
    if ($eur_total_seo_tmp !== null) {
        $eur_total_seo = (float)$eur_total_seo_tmp;
        $eur_in_seo = (float)$eur_in_seo_tmp;
        $eur_out_seo = (float)$eur_out_seo_tmp;
    }
}

$eur_total_est = null;
if ($eur_total_text !== null && $eur_total_img !== null) {
    $eur_total_est = (float)$eur_total_text + (float)$eur_total_img + (float)$eur_total_seo;
}

// Notices
if ($notice === 'saved') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cost settings saved.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($notice === 'log') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Log cleared.', 'cbiastudio-blogflow-ai') . '</p></div>';
} elseif ($notice === 'calc') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Calculation completed. Check the log.', 'cbiastudio-blogflow-ai') . '</p></div>';
}

if (is_array($calibration_info)) {
    $actual_eur = (float)($calibration_info['actual_eur'] ?? 0);
    $estimated_eur = (float)($calibration_info['estimated_eur'] ?? 0);
    $suggested = (float)($calibration_info['suggested'] ?? 1);
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('Calibration applied.', 'cbiastudio-blogflow-ai') . '</strong> ' .
    esc_html__('Billing:', 'cbiastudio-blogflow-ai') . ' ' . esc_html(number_format($actual_eur, 4, ',', '.')) . ' â‚¬ | ' .
    esc_html__('Calculated real:', 'cbiastudio-blogflow-ai') . ' ' . esc_html(number_format($estimated_eur, 4, ',', '.')) . ' â‚¬ | ' .
    esc_html__('Multiplier:', 'cbiastudio-blogflow-ai') . ' <code>' . esc_html(number_format($suggested, 4, ',', '.')) . '</code></p></div>';
}
?>
<?php if (!$cbia_costs_embedded): ?>
<div class="wrap" style="padding-left:0;">
<h2><?php echo esc_html__('Cost settings', 'cbiastudio-blogflow-ai'); ?></h2>
<?php else: ?>
<h3 style="margin:0 0 12px 0;"><?php echo esc_html__('Cost settings', 'cbiastudio-blogflow-ai'); ?></h3>
<?php endif; ?>
<div class="notice notice-info" style="margin:8px 0 16px 0;">
<p style="margin:6px 0;"><strong><?php echo esc_html__('Current calculation mode:', 'cbiastudio-blogflow-ai'); ?></strong> <code>REAL ONLY</code> uses modern rows in <code>_cbia_usage_rows</code>, supports legacy data from <code>_cbia_usage_calls</code> and <code>_cbia_image_calls</code>, and falls back to legacy token totals only if per-call details are missing.</p>
<p style="margin:6px 0;"><strong><?php echo esc_html__('Scope:', 'cbiastudio-blogflow-ai'); ?></strong> "<?php echo esc_html__('Last N posts', 'cbiastudio-blogflow-ai'); ?>" checks exactly the last <code>N</code> posts by WordPress date. Increase <code>N</code> or run multiple batches if you need more history.</p>
<p style="margin:6px 0;"><strong><?php echo esc_html__('Base rates:', 'cbiastudio-blogflow-ai'); ?></strong> OpenAI image output prices are calculated from the central model, quality, and size catalog verified on <code>2026-07-10</code>. Google Vertex AI, DeepSeek, and text-token rates keep their existing configuration.</p>
<p style="margin:6px 0;"><strong><?php echo esc_html__('Adjustment rule:', 'cbiastudio-blogflow-ai'); ?></strong> No hidden per-model multiplier is applied. Only the visible field <code><?php echo esc_html__('Total multiplier adjustment (REAL)', 'cbiastudio-blogflow-ai'); ?></code> affects the result.</p>
<p style="margin:6px 0;"><strong><?php echo esc_html__('Failed attempts:', 'cbiastudio-blogflow-ai'); ?></strong> Realistic mode only charges for plausibly billable failures (timeouts, 5xx errors, empty responses, or local failure after provider response). Immediate rejections such as <code>401/403/404</code>, invalid model, or missing API key are not billed.</p>
</div>
<div class="notice notice-info" style="margin:8px 0 16px 0;">
<p style="margin:6px 0;">
<strong><?php echo esc_html__('Effective REAL adjustment:', 'cbiastudio-blogflow-ai'); ?></strong>
<code><?php echo esc_html(number_format((float)$applied_mult, 4, ',', '.')); ?>Ã—</code>
<?php if ($applied_source === 'modelo') : ?>
<span class="description">(<?php echo esc_html__('by model:', 'cbiastudio-blogflow-ai'); ?> <?php echo esc_html($model_text_current); ?>)</span>
<?php else : ?>
<span class="description">(<?php echo esc_html__('by global adjustment', 'cbiastudio-blogflow-ai'); ?>)</span>
<?php endif; ?>
</p>
</div>

<h3><?php echo esc_html__('Quick estimate (based on current config)', 'cbiastudio-blogflow-ai'); ?></h3>
<table class="widefat striped" style="max-width:980px;">
<tbody>
<tr>
<td style="width:280px;"><strong>TEXT model (Config)</strong></td>
<td>
<code><?php echo esc_html($model_text_current); ?></code>
</td>
</tr>
<tr>
<td><strong>IMAGE model (Costs)</strong></td>
<td><code><?php echo esc_html($model_img_current); ?></code></td>
</tr>
<tr>
<td><strong>SEO model (Costs)</strong></td>
<td><code><?php echo esc_html($model_seo_current); ?></code></td>
</tr>
<tr>
<td><strong>Text calls per post</strong></td>
<td><code><?php echo esc_html((int)$text_calls); ?></code></td>
</tr>
<tr>
<td><strong>Image calls per post</strong></td>
<td><code><?php echo esc_html((int)$img_calls); ?></code></td>
</tr>
<tr>
<td><strong>SEO calls per post</strong></td>
<td><code><?php echo esc_html((int)$seo_calls); ?></code></td>
</tr>
<tr>
<td><strong>TEXT input tokens (post total)</strong></td>
<td><code><?php echo esc_html((int)$in_tokens_text_total); ?></code></td>
</tr>
<tr>
<td><strong>TEXT output tokens (post total)</strong></td>
<td><code><?php echo esc_html((int)$out_tokens_text_total); ?></code></td>
</tr>
<tr>
<td><strong>IMAGE input tokens (post total)</strong></td>
<td><code><?php echo esc_html((int)$in_tokens_img_total); ?></code></td>
</tr>
<tr>
<td><strong>IMAGE output tokens (post total)</strong></td>
<td><code><?php echo esc_html((int)$out_tokens_img_total); ?></code> <span class="description"><?php echo esc_html__('(if set to 0, only input tokens are estimated)', 'cbiastudio-blogflow-ai'); ?></span></td>
</tr>
<tr>
<td><strong>SEO input tokens (post total)</strong></td>
<td><code><?php echo esc_html((int)$in_tokens_seo_total); ?></code></td>
</tr>
<tr>
<td><strong>SEO output tokens (post total)</strong></td>
<td><code><?php echo esc_html((int)$out_tokens_seo_total); ?></code></td>
</tr>
<tr>
<td><strong>Estimated cost (TEXT)</strong></td>
<td>
<?php
echo ($eur_total_text === null)
? '<span style="color:#b70000;">Model not found in table</span>'
: '<strong>' . esc_html(number_format((float)$eur_total_text, 4, ',', '.')) . ' â‚¬</strong> <span class="description">(in ' . number_format((float)$eur_in_text, 4, ',', '.') . ' â‚¬ | out ' . number_format((float)$eur_out_text, 4, ',', '.') . ' â‚¬)</span>';
?>
</td>
</tr>
<tr>
<td><strong>Estimated cost (IMAGES)</strong></td>
<td>
<?php
echo ($eur_total_img === null)
? '<span style="color:#b70000;">Model not found in table</span>'
: '<strong>' . esc_html(number_format((float)$eur_total_img, 4, ',', '.')) . ' â‚¬</strong> <span class="description">(in ' . number_format((float)$eur_in_img, 4, ',', '.') . ' â‚¬ | out ' . number_format((float)$eur_out_img, 4, ',', '.') . ' â‚¬)</span>';
?>
</td>
</tr>
<tr>
<td><strong>Estimated cost (SEO)</strong></td>
<td>
<strong><?php echo esc_html(number_format((float)$eur_total_seo, 4, ',', '.')); ?> â‚¬</strong>
<span class="description">(in <?php echo esc_html(number_format((float)$eur_in_seo, 4, ',', '.')); ?> â‚¬ | out <?php echo esc_html(number_format((float)$eur_out_seo, 4, ',', '.')); ?> â‚¬)</span>
</td>
</tr>
<tr>
<td><strong>Total estimated cost</strong></td>
<td>
<?php
echo ($eur_total_est === null)
? '<span style="color:#b70000;">Could not estimate (model not in table)</span>'
: '<strong style="font-size:16px;">' . esc_html(number_format((float)$eur_total_est, 4, ',', '.')) . ' â‚¬</strong>';
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
<input type="number" step="0.01" min="0.5" max="1.5" name="usd_to_eur" value="<?php echo esc_attr((string)$cost['usd_to_eur']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th><?php echo esc_html__('Tokens per word (approx)', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="number" step="0.01" min="0.5" max="2" name="tokens_per_word" value="<?php echo esc_attr((string)$cost['tokens_per_word']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th><?php echo esc_html__('Input overhead (tokens) per text call', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="number" min="0" max="5000" name="input_overhead_tokens" value="<?php echo esc_attr((int)$cost['input_overhead_tokens']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th><?php echo esc_html__('Image overhead (words) per call', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="number" min="0" max="300" name="per_image_overhead_words" value="<?php echo esc_attr((int)$cost['per_image_overhead_words']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Ratio cached input (0..1)</th>
<td>
<input type="number" step="0.05" min="0" max="1" name="cached_input_ratio" value="<?php echo esc_attr((string)$cost['cached_input_ratio']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Fixed surcharge per TEXT/SEO call (USD)</th>
<td>
<input type="number" step="0.001" min="0" max="0.050" name="responses_fixed_usd_per_call" value="<?php echo esc_attr((string)$cost['responses_fixed_usd_per_call']); ?>" style="width:120px;" />
<p class="description"><?php echo esc_html__('Fine-tune adjustment to better match real billing (applied to each text/SEO call).', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th>Retry multiplier (text)</th>
<td>
<input type="number" step="0.05" min="1" max="5" name="mult_text" value="<?php echo esc_attr((string)$cost['mult_text']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Retry multiplier (images)</th>
<td>
<input type="number" step="0.05" min="1" max="5" name="mult_image" value="<?php echo esc_attr((string)$cost['mult_image']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Images: use fixed price per generation</th>
<td>
<label><input type="checkbox" name="use_image_flat_pricing" value="1" <?php checked(!empty($cost['use_image_flat_pricing'])); ?> /> Enable (recommended). Closer to real billing.</label>
<p class="description">If enabled, estimate and REAL calculation use fixed pricing per image, ignoring image tokens.</p>
</td>
</tr>
<tr>
<th>Retry multiplier (SEO)</th>
<td>
<input type="number" step="0.05" min="1" max="5" name="mult_seo" value="<?php echo esc_attr((string)$cost['mult_seo']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th><?php echo esc_html__('Total multiplier adjustment (REAL)', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<input type="number" step="0.01" min="0.5" max="1.5" name="real_adjust_multiplier" value="<?php echo esc_attr((string)$cost['real_adjust_multiplier']); ?>" style="width:120px;" />
<p class="description">Multiplies the real total. Useful to compensate small conversion/rounding differences.</p>
</td>
</tr>
<tr>
<th>Count failed attempts in REAL cost</th>
<td>
<label><input type="checkbox" name="count_failed_attempt_costs" value="1" <?php checked(!empty($cost['count_failed_attempt_costs'])); ?> /> Enable realistic mode for retries and failures.</label>
<p class="description"><?php echo esc_html__('Only counts plausibly billable failures: timeout, 5xx, empty response, or local failure after provider response.', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th>Billed input ratio in failed text/SEO calls</th>
<td>
<input type="number" step="0.05" min="0" max="1" name="failed_text_input_ratio" value="<?php echo esc_attr((string)$cost['failed_text_input_ratio']); ?>" style="width:120px;" />
<p class="description"><?php echo esc_html__('1.00 = full prompt counted; 0.50 = half of the prompt counted.', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th>Billed output ratio in failed text/SEO calls</th>
<td>
<input type="number" step="0.05" min="0" max="1" name="failed_text_output_ratio" value="<?php echo esc_attr((string)$cost['failed_text_output_ratio']); ?>" style="width:120px;" />
<p class="description"><?php echo esc_html__('Usually best left at 0 unless you want to account for truncated responses.', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th>Fixed-price ratio in failed image calls</th>
<td>
<input type="number" step="0.05" min="0" max="1" name="failed_image_flat_ratio" value="<?php echo esc_attr((string)$cost['failed_image_flat_ratio']); ?>" style="width:120px;" />
<p class="description"><?php echo esc_html__('0.35 = applies 35% of fixed image price when an attempt fails.', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th># TEXT calls per post</th>
<td>
<input type="number" min="1" max="20" name="text_calls_per_post" value="<?php echo esc_attr((int)$cost['text_calls_per_post']); ?>" style="width:120px;" />
<p class="description">If your engine performs more than 1 text call, increase it here.</p>
</td>
</tr>
<tr>
<th># IMAGE calls per post</th>
<td>
<input type="number" min="0" max="20" name="image_calls_per_post" value="<?php echo esc_attr((int)$cost['image_calls_per_post']); ?>" style="width:120px;" />
<p class="description"><?php echo wp_kses_post(__('If set to 0, it uses <code>images_limit</code> from Configuration.', 'cbiastudio-blogflow-ai')); ?></p>
</td>
</tr>
<tr>
<th>Image model for calculation</th>
<td>
<select name="image_model" class="abb-select" style="width:240px;">
<option value="gpt-image-2" <?php selected($model_img_current, 'gpt-image-2'); ?>>gpt-image-2</option>
<option value="gpt-image-1-mini" <?php selected($model_img_current, 'gpt-image-1-mini'); ?>>gpt-image-1-mini</option>
<option value="gpt-image-1" <?php selected($model_img_current, 'gpt-image-1'); ?>>gpt-image-1</option>
<option value="imagen-3.0-generate-002" <?php selected($model_img_current, 'imagen-3.0-generate-002'); ?>>imagen-3.0-generate-002</option>
<option value="imagen-4.0-generate-001" <?php selected($model_img_current, 'imagen-4.0-generate-001'); ?>>imagen-4.0-generate-001</option>
</select>
<p class="description">Active image provider: <code><?php echo esc_html($image_provider_current); ?></code>. This field acts as an estimate override if you want a manual fine tune.</p>
<p class="description"><strong><?php echo esc_html__('OpenAI image pricing:', 'cbiastudio-blogflow-ai'); ?></strong> <?php echo esc_html__('Managed automatically from the central model, quality, and size catalog. Change image quality in Configuration.', 'cbiastudio-blogflow-ai'); ?></p>
<p class="description">Google Imagen 3 <input type="number" step="0.001" min="0" name="image_flat_usd_imagen3" value="<?php echo esc_attr((string)$cost['image_flat_usd_imagen3']); ?>" style="width:90px;" /> &nbsp;Google Imagen 4 <input type="number" step="0.001" min="0" name="image_flat_usd_imagen4" value="<?php echo esc_attr((string)$cost['image_flat_usd_imagen4']); ?>" style="width:90px;" /></p>
<p class="description">Current practical reference for Google Imagen: Imagen 3 = 0.04 USD per image, Imagen 4 = 0.04 USD per image, Imagen 4 Ultra = 0.06 USD per image. Adjust this here if your real flow uses another variant.</p>
</td>
</tr>
<tr>
<th>Output tokens per image call (optional)</th>
<td>
<input type="number" min="0" max="50000" name="image_output_tokens_per_call" value="<?php echo esc_attr((int)$cost['image_output_tokens_per_call']); ?>" style="width:120px;" />
<p class="description">If set to 0, the estimate will mostly account for input tokens.</p>
</td>
</tr>
<tr><th colspan="2"><hr/></th></tr>
<tr>
<th># SEO calls per post</th>
<td>
<input type="number" min="0" max="20" name="seo_calls_per_post" value="<?php echo esc_attr((int)$cost['seo_calls_per_post']); ?>" style="width:120px;" />
<p class="description">If your Yoast/SEO fill flow makes OpenAI calls (meta, keyphrase, etc.), set them here for estimation.</p>
</td>
</tr>
<tr>
<th><?php echo esc_html__('SEO model', 'cbiastudio-blogflow-ai'); ?></th>
<td>
<select name="seo_model" class="abb-select" style="width:240px;">
<?php
$seo_candidates = array_values(array_filter(array_keys($table), static function ($model) {
    return strpos((string)$model, 'gpt-image-') !== 0 && strpos((string)$model, 'imagen-') !== 0;
}));
sort($seo_candidates, SORT_STRING);
foreach ($seo_candidates as $m) {
    if (!isset($table[$m])) continue;
    echo '<option value="' . esc_attr($m) . '" ' . selected($model_seo_current, $m, false) . '>' . esc_html($m) . '</option>';
}
?>
</select>
<p class="description"><?php echo esc_html__('If unsure, keep the same model as text.', 'cbiastudio-blogflow-ai'); ?></p>
</td>
</tr>
<tr>
<th>Input tokens per SEO call</th>
<td>
<input type="number" min="0" max="50000" name="seo_input_tokens_per_call" value="<?php echo esc_attr((int)$cost['seo_input_tokens_per_call']); ?>" style="width:120px;" />
</td>
</tr>
<tr>
<th>Output tokens per SEO call</th>
<td>
<input type="number" min="0" max="50000" name="seo_output_tokens_per_call" value="<?php echo esc_attr((int)$cost['seo_output_tokens_per_call']); ?>" style="width:120px;" />
</td>
</tr>
</table>

<p>
<button type="submit" class="button button-primary"><?php echo esc_html__('Save cost settings', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</form>

<hr/>

<h3><?php echo esc_html__('Actions (post-hoc)', 'cbiastudio-blogflow-ai'); ?></h3>
<p class="description" style="max-width:980px;"><code>Calculate</code> mixes real and estimate when usage rows are missing. <code>Calculate real only</code> sums only per-post registered calls, so it can return zero if those posts have no stored details.</p>
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
Use estimate when real usage is missing
</label>
</td>
</tr>
<tr>
<th>Calibrate with real billing (â‚¬)</th>
<td>
<input type="number" name="calibrate_actual_eur" step="0.01" min="0" placeholder="Ej: 1.84" style="width:120px;" />
<span class="description" style="margin-left:8px;">Enter the real spend for those N posts and we will auto-adjust the REAL multiplier.</span>
</td>
</tr>
</table>

<p>
<button type="submit" class="button button-primary" name="cbia_action" value="calc_last"><?php echo esc_html__('Calculate', 'cbiastudio-blogflow-ai'); ?></button>
<button type="submit" class="button" name="cbia_action" value="calc_last_real" style="margin-left:8px;"><?php echo esc_html__('Calculate real only', 'cbiastudio-blogflow-ai'); ?></button>
<button type="submit" class="button button-secondary" name="cbia_action" value="calibrate_real" style="margin-left:8px;"><?php echo esc_html__('Calibrate REAL from billing', 'cbiastudio-blogflow-ai'); ?></button>
<button type="submit" class="button button-secondary" name="cbia_action" value="clear_log" style="margin-left:8px;"><?php echo esc_html__('Clear log', 'cbiastudio-blogflow-ai'); ?></button>
</p>
</form>

<h3><?php echo esc_html__('Costs log', 'cbiastudio-blogflow-ai'); ?></h3>
<textarea id="cbia-costes-log" rows="14" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea($log); ?></textarea>

<?php
$cbia_pro_costs_log_js = "document.addEventListener('DOMContentLoaded', function() {\n"
    . "const logBox = document.getElementById('cbia-costes-log');\n"
    . "function refreshLog(){\n"
    . "  if (typeof ajaxurl === 'undefined') return;\n"
    . "  const nonce = (window.CBIA && CBIA.nonce) ? CBIA.nonce : '';\n"
    . "  const url = ajaxurl + '?action=cbia_get_costes_log' + (nonce ? '&_ajax_nonce=' + encodeURIComponent(nonce) : '');\n"
    . "  fetch(url, { credentials: 'same-origin' })\n"
    . "    .then(r => r.json())\n"
    . "    .then(data => {\n"
    . "      if (data && data.success && logBox) {\n"
    . "        if (data.data && typeof data.data === 'object' && data.data.log) {\n"
    . "          logBox.value = data.data.log || '';\n"
    . "        } else {\n"
    . "          logBox.value = data.data || '';\n"
    . "        }\n"
    . "        logBox.scrollTop = logBox.scrollHeight;\n"
    . "      }\n"
    . "    })\n"
    . "    .catch(() => {});\n"
    . "}\n"
    . "setInterval(refreshLog, 3000);\n"
    . "});";
wp_add_inline_script('abb-admin', $cbia_pro_costs_log_js, 'after');
?>
<?php if (!$cbia_costs_embedded): ?>
</div>
<?php endif; ?>
