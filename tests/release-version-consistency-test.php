<?php

$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/cbiastudio-blogflow-ai.php');
$readme = file_get_contents($root . '/readme.txt');
$project_readme = file_get_contents($root . '/README.md');
$expected = '2.2.2';

$count = 0;
function release_consistency_check($condition, string $message): void {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$message}");
}

preg_match('/^[ \t]*\*[ \t]*Version:[ \t]*([^\s]+)/m', $plugin, $header_match);
preg_match("/define\(\s*'CBIA_BASE_VERSION'\s*,\s*'([^']+)'\s*\)/", $plugin, $constant_match);
preg_match('/^Stable tag:[ \t]*([^\s]+)/m', $readme, $stable_match);
preg_match('/^# .* v([0-9]+\.[0-9]+\.[0-9]+)$/m', $project_readme, $readme_match);

$header = $header_match[1] ?? '';
$constant = $constant_match[1] ?? '';
$stable = $stable_match[1] ?? '';
$documented = $readme_match[1] ?? '';

release_consistency_check($header === $expected, 'plugin header matches the release version');
release_consistency_check($constant === $expected, 'CBIA_BASE_VERSION matches the release version');
release_consistency_check($stable === $expected, 'Stable tag matches the release version');
release_consistency_check($documented === $expected, 'README version matches the release version');
release_consistency_check($header === $constant && $constant === $stable, 'official version sources are consistent');

echo "release-version-consistency: {$count}/{$count} OK\n";
