<?php
declare(strict_types=1);

// No provider, database, or asset writes are needed for this regression.
$root = dirname(__DIR__);
require_once $root . '/includes/ui.php';
$checks = 0;
$failures = [];
function asset_check(bool $condition, string $message): void
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) $failures[] = $message;
}

$asset = 'assets/js/exams.js';
$expectedVersion = substr(hash_file('sha256', $root . '/' . $asset), 0, 16);
$versioned = $asset . '?v=' . $expectedVersion;
asset_check(nisha_asset_url($asset) === $versioned, 'Exam runner version follows file contents, independent of file timestamps.');
asset_check(nisha_asset_url($asset) === nisha_asset_url($asset), 'Unchanged contents keep a deterministic URL.');
asset_check(nisha_asset_url($versioned) === $versioned, 'Repeated versioning is idempotent.');
asset_check(nisha_asset_url($asset . '?v=outdated&mode=review#answers') === $asset . '?mode=review&v=' . $expectedVersion . '#answers', 'An old version is replaced while query parameters and fragments are preserved.');
asset_check(nisha_asset_url($asset . '?mode=review&%76=old#answers') === $asset . '?mode=review&v=' . $expectedVersion . '#answers', 'Encoded version parameter names cannot duplicate a version.');
foreach ([
    'https://example.com/assets/js/exams.js',
    '//example.com/assets/js/exams.js',
    'assets/../includes/ui.php',
    'assets/js/../../config/config.php',
    'assets/js/../../../assets/js/exams.js',
    'assets/js/%2e%2e/exams.js',
    'assets/js/missing-asset.js',
    'api/complete-exam.php',
    'assets/images/favicon.svg',
    'data:text/javascript,alert(1)',
] as $unversioned) {
    asset_check(nisha_asset_url($unversioned) === $unversioned, 'Do not resolve unsupported or unsafe path: ' . $unversioned);
}

// Render the same includes every page uses, including page-specific assets.
$extraStyles = ['assets/css/exams.css', 'assets/css/ai-review.css'];
$extraScripts = ['assets/js/ai-review.js', 'assets/js/exams.js'];
ob_start();
require $root . '/includes/header.php';
require $root . '/includes/footer.php';
$markup = (string) ob_get_clean();
preg_match_all('~<(?:script\b[^>]*\bsrc|link\b[^>]*\bhref)="([^"]+\.(?:css|js)(?:\?[^"]*)?)"~', $markup, $matches);
asset_check(count($matches[1]) === 9, 'Header and footer emit all nine shared/page-specific CSS and JavaScript URLs.');
foreach ($matches[1] as $escapedUrl) {
    $url = html_entity_decode($escapedUrl, ENT_QUOTES, 'UTF-8');
    $path = (string) parse_url($url, PHP_URL_PATH);
    $expected = $path . '?v=' . substr(hash_file('sha256', $root . '/' . $path), 0, 16);
    asset_check($url === $expected, 'Rendered local asset uses its current content version: ' . $path);
}
$initPosition = strpos($markup, 'assets/js/theme-init.js?');
$stylePosition = strpos($markup, 'assets/css/style.css?');
$themePosition = strpos($markup, 'assets/js/theme.js?');
$sitePosition = strpos($markup, 'assets/js/site.js?');
$aiPosition = strpos($markup, 'assets/js/ai-review.js?');
$examPosition = strpos($markup, 'assets/js/exams.js?');
asset_check($initPosition !== false && $stylePosition !== false && $initPosition < $stylePosition, 'Theme initialization still precedes CSS.');
asset_check($themePosition !== false && $sitePosition !== false && $themePosition < $sitePosition, 'Shared deferred script order is preserved.');
asset_check($aiPosition !== false && $examPosition !== false && $aiPosition < $examPosition, 'AI review script still loads before the exam runner.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", array_map(static fn (string $failure): string => 'FAIL: ' . $failure, $failures)) . "\n");
    exit(1);
}
echo "PASS: $checks asset-versioning checks.\n";
