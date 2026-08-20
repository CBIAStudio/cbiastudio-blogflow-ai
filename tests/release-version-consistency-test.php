<?php

$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/cbiastudio-blogflow-ai.php');
$readme = file_get_contents($root . '/readme.txt');
$project_readme = file_get_contents($root . '/README.md');
$expected = '2.2.3';

$count = 0;
function release_consistency_check($condition, string $message): void {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException("Case {$count} failed: {$message}");
}

function release_consistency_readme_version(string $contents): string {
    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    preg_match('/^# .* v([0-9]+\.[0-9]+\.[0-9]+)$/m', $contents, $matches);

    return $matches[1] ?? '';
}

preg_match('/^[ \t]*\*[ \t]*Version:[ \t]*([^\s]+)/m', $plugin, $header_match);
preg_match("/define\(\s*'CBIA_BASE_VERSION'\s*,\s*'([^']+)'\s*\)/", $plugin, $constant_match);
preg_match('/^Stable tag:[ \t]*([^\s]+)/m', $readme, $stable_match);

$header = $header_match[1] ?? '';
$constant = $constant_match[1] ?? '';
$stable = $stable_match[1] ?? '';
$documented = release_consistency_readme_version($project_readme);

$readme_fixtures = [
    ['LF with the expected version', "# CBIAStudio BlogFlow with AI (WordPress) v2.2.3\n", true],
    ['CRLF with the expected version', "# CBIAStudio BlogFlow with AI (WordPress) v2.2.3\r\n", true],
    ['CRLF with an incorrect version', "# CBIAStudio BlogFlow with AI (WordPress) v2.2.2\r\n", false],
];

foreach ($readme_fixtures as [$description, $contents, $should_match]) {
    $matches = release_consistency_readme_version($contents) === $expected;
    if ($matches !== $should_match) {
        throw new RuntimeException("README fixture failed: {$description}");
    }
}

release_consistency_check($header === $expected, 'plugin header matches the release version');
release_consistency_check($constant === $expected, 'CBIA_BASE_VERSION matches the release version');
release_consistency_check($stable === $expected, 'Stable tag matches the release version');
release_consistency_check($documented === $expected, 'README version matches the release version');
release_consistency_check($header === $constant && $constant === $stable, 'official version sources are consistent');

echo 'release-version-consistency-crlf: ' . count($readme_fixtures) . '/' . count($readme_fixtures) . " OK\n";
echo "release-version-consistency: {$count}/{$count} OK\n";
