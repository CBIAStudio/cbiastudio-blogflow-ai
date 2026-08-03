<?php
$root = dirname(__DIR__);
$view = file_get_contents($root . '/includes/admin/views/config.php');
$js = file_get_contents($root . '/assets/js/admin.js');
$css = file_get_contents($root . '/assets/css/admin.css');
$hooks = file_get_contents($root . '/includes/core/hooks.php');

$cases = 0;
function compact_connection_check($condition, $message) {
    global $cases;
    $cases++;
    if (!$condition) throw new RuntimeException("Case {$cases} failed: {$message}");
}

compact_connection_check(strpos($view, 'cbia-provider-connection-panel') !== false && strpos($view, " hidden>") !== false, 'connection details are collapsed by default');
compact_connection_check(strpos($view, 'data-action="open-editor"') !== false, 'primary action opens the credential editor');
compact_connection_check(strpos($view, 'data-action="toggle-details"') !== false, 'details toggle is present');
compact_connection_check(strpos($view, 'aria-expanded="false"') !== false && strpos($view, 'aria-controls=') !== false, 'toggle state is exposed to assistive technology');
compact_connection_check(strpos($view, 'value="" autocomplete="new-password"') !== false, 'stored credentials are not rendered');
compact_connection_check(strpos($view, 'aria-live="polite"') !== false && strpos($view, 'aria-atomic="true"') !== false, 'AJAX result is announced accessibly');
compact_connection_check(strpos($js, "root.querySelectorAll('.cbia-provider-connection-card.is-expanded')") !== false, 'opening one card closes the others');
compact_connection_check(strpos($js, "event.key !== 'Escape'") !== false, 'Escape closes an expanded card');
compact_connection_check(strpos($css, 'grid-template-columns: minmax(190px, .8fr)') !== false, 'desktop summary uses a compact horizontal row');
compact_connection_check(strpos($css, '@media (max-width: 600px)') !== false && strpos($css, '.cbia-provider-key-row { flex-direction: column; }') !== false, 'mobile layout stacks controls');
compact_connection_check(strpos($hooks, "__('Connection verified.'") !== false, 'test success is concise');
compact_connection_check(strpos($hooks, 'Valid connection. Detected models:') === false, 'raw model count is not the primary success message');

echo "provider-connections-compact-ui: {$cases}/{$cases} OK\n";
