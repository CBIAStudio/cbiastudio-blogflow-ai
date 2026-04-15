<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if (!defined('ABSPATH')) { exit; }

// Yoast tab view (extracted from legacy cbia-yoast.php)

if (!current_user_can('manage_options')) return;

$batch = 50; $offset = 0; $force = false; $only_cbia = true;

$service = isset($cbia_yoast_service) ? $cbia_yoast_service : null;

if ($service && method_exists($service, 'handle_post')) {
    list($batch, $offset, $force, $only_cbia) = $service->handle_post($batch, $offset, $force, $only_cbia);
} elseif (function_exists('cbia_yoast_handle_post')) {
    list($batch, $offset, $force, $only_cbia) = cbia_yoast_handle_post($batch, $offset, $force, $only_cbia);
}

$log = $service && method_exists($service, 'get_log')
    ? $service->get_log()
    : cbia_yoast_log_get();
?>
<div class="wrap">
<h2>Yoast</h2>

<?php
$yoast_active = defined('WPSEO_VERSION');
$faq_block_available = false;
if (function_exists('cbia_yoast_faq_block_available')) {
    $faq_block_available = cbia_yoast_faq_block_available();
} elseif (class_exists('WP_Block_Type_Registry')) {
    $registry = WP_Block_Type_Registry::get_instance();
    $faq_block_available = is_object($registry) && $registry->is_registered('yoast/faq-block');
}
?>

<p>
<strong>FAQ Schema (Yoast)</strong>:
<?php
if (!$yoast_active) {
    echo '<span style="color:#b70000;">Yoast is not active.</span> Install/activate Yoast SEO to use the FAQ block.';
} elseif ($faq_block_available) {
    echo '<span style="color:#1e7e34;font-weight:600;">FAQ block available.</span> The FAQ section will be converted automatically to a Yoast block.';
} else {
    echo '<span style="color:#b70000;">FAQ block NOT available.</span> Make sure Gutenberg is enabled and Yoast blocks are available.';
}
?>
</p>

<p>
<strong>Traffic light</strong> here means filling:
<code>_yoast_wpseo_linkdex</code> (SEO) and <code>_yoast_wpseo_content_score</code> (Readability),
so it no longer appears gray in the list.
</p>

<form method="post" action="">
<?php wp_nonce_field('cbia_yoast_nonce_action', 'cbia_yoast_nonce'); ?>

<h3>Batch Actions</h3>

<table class="form-table">
<tr>
<th>Batch size</th>
<td><input type="number" name="cbia_yoast_batch" min="1" max="500" value="<?php echo esc_attr($batch); ?>" style="width:110px;" /></td>
</tr>
<tr>
<th>Offset</th>
<td><input type="number" name="cbia_yoast_offset" min="0" value="<?php echo esc_attr($offset); ?>" style="width:110px;" /></td>
</tr>
<tr>
<th>Options</th>
<td>
<label style="display:inline-block;margin-right:18px;">
<input type="checkbox" name="cbia_yoast_force" value="1" <?php checked($force); ?> />
Force (rewrite metas/scores even if they already exist)
</label>
<label style="display:inline-block;margin-right:18px;">
<input type="checkbox" name="cbia_yoast_include_unmarked" value="1" <?php checked(!$only_cbia); ?> />
Include non-CBIA posts (without <code>_cbia_created</code>)
</label>
</td>
</tr>
</table>

<p>
<button type="submit" class="button button-secondary" name="cbia_yoast_action" value="metas">Recalculate metas only</button>
<button type="submit" class="button button-secondary" name="cbia_yoast_action" value="semaphore" style="margin-left:8px;">Update traffic light only</button>
<button type="submit" class="button button-primary" name="cbia_yoast_action" value="both" style="margin-left:8px;">Metas + traffic light</button>
<button type="submit" class="button" name="cbia_yoast_action" value="clear_log" style="margin-left:8px;">Clear log</button>
</p>

<hr/>

<h3>Mark Old Posts as CBIA</h3>
<p>This adds <code>_cbia_created=1</code> to posts that do not have it, so they are included in default batches.</p>

<table class="form-table">
<tr>
<th>From (optional)</th>
<td><input type="datetime-local" name="cbia_yoast_date_from" value="" /></td>
</tr>
<tr>
<th>To (optional)</th>
<td><input type="datetime-local" name="cbia_yoast_date_to" value="" /></td>
</tr>
<tr>
<th>CBIA signals only</th>
<td>
<label>
<input type="checkbox" name="cbia_yoast_only_signals" value="1" />
Mark only when signals are detected (FAQ JSON-LD / pending markers / content markers)
</label>
</td>
</tr>
</table>

<p>
<button type="submit" class="button button-primary" name="cbia_yoast_action" value="mark_legacy">Mark old posts as CBIA</button>
</p>

<hr/>

<h3>Yoast Log</h3>
<textarea rows="14" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea($log); ?></textarea>
</form>
</div>
