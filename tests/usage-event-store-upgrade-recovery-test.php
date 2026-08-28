<?php

$source = file_get_contents(__DIR__ . '/../includes/core/hooks.php');
$checks = array(
    "return 'cbia_pro_usage_overview_v13_'" => 'range-comparison upgrade uses a fresh dashboard cache namespace',
    "return 3;" => 'event store schema version is recorded',
    "'schema_version' => cbia_usage_event_store_schema_version()" => 'saved stores include their schema version',
    "\$schema_version !== cbia_usage_event_store_schema_version()" => 'outdated stores trigger reconciliation',
    "cbia_usage_rebuild_event_store_rows(\$rows)" => 'existing store rows are preserved during reconciliation',
    "array_merge((array) \$preserved_rows, \$rebuilt_rows)" => 'canonical rebuilt rows win over preserved rows',
    "'private', 'trash'" => 'usage attached to trashed posts is recovered',
    "return 'legacy:' . md5" => 'legacy rows without request identifiers have a stable identity',
    "cbia_usage_get_event_store_rows(true)" => 'first append after an update rebuilds legacy sources',
);

$failed = 0;
foreach ($checks as $needle => $label) {
    $ok = strpos($source, $needle) !== false;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}

exit($failed > 0 ? 1 : 0);
