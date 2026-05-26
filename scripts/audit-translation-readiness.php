<?php
/**
 * Reports translation readiness: PHP textdomain checks + admin React string extraction hints.
 *
 * Usage: php scripts/audit-translation-readiness.php
 *
 * @package Sikshya
 */

declare(strict_types=1);

$root = dirname(__DIR__);

echo "Sikshya translation readiness audit\n";
echo str_repeat('=', 40) . "\n\n";

// 1) PHP gettext argument counts.
echo "1) PHP gettext argument counts\n";
passthru('php ' . escapeshellarg($root . '/scripts/check-i18n-textdomain.php'), $phpExit);
echo "\n";

// 2) Admin React: count __ / _n / _x in client/src.
$clientSrc = $root . '/client/src';
$reactI18nFiles = 0;
$reactI18nCalls = 0;
$tsxFiles = 0;

$walk = static function (string $dir) use (&$walk, &$reactI18nFiles, &$reactI18nCalls, &$tsxFiles): void {
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $walk($path);
            continue;
        }
        if (!is_file($path) || !preg_match('/\.(tsx?|jsx?)$/', $item)) {
            continue;
        }
        ++$tsxFiles;
        $src = (string) file_get_contents($path);
        $n = preg_match_all('/\b(__|_n|_x|sprintf)\s*\(/', $src, $m);
        if ($n > 0) {
            ++$reactI18nFiles;
            $reactI18nCalls += $n;
        }
    }
};

if (is_dir($clientSrc)) {
    $walk($clientSrc);
}

echo "2) Admin React (client/src)\n";
echo "   TS/TSX files: {$tsxFiles}\n";
echo "   Files with __/_n/_x/sprintf: {$reactI18nFiles}\n";
echo "   i18n call sites (approx): {$reactI18nCalls}\n";
if ($reactI18nCalls < 50) {
    echo "   Note: Most admin UI strings are still literal English in React.\n";
    echo "         Wrap user-facing copy with import { __ } from '../lib/i18n' and run npm run makepot.\n";
}
echo "   wp_set_script_translations: sikshya-react-admin (AdminAssetsService)\n";
echo "   Sidebar labels: translated in PHP (ReactAdminConfig::navigationItems)\n";
echo "   JS extraction: npm run build or npm run extract-js-pot → languages/sikshya-js.pot\n";
echo "   Full catalog: npm run makepot (merges JS fragment into sikshya.pot, needs wp-cli)\n";
echo "   Verify translate() literals: npm run i18n:check\n\n";

// 3) POT file age.
$pot = $root . '/languages/sikshya.pot';
echo "3) Template catalog\n";
if (is_file($pot)) {
    $mtime = date('Y-m-d H:i:s', (int) filemtime($pot));
    $lines = count(file($pot, FILE_IGNORE_NEW_LINES) ?: []);
    echo "   languages/sikshya.pot — {$lines} lines, modified {$mtime}\n";
    echo "   Regenerate: npm run makepot (requires wp-cli)\n";
} else {
    echo "   Missing languages/sikshya.pot — run npm run makepot\n";
}
echo "\n";

// 4) Learn / frontend JS hardcoded hints (sample).
echo "4) Learn player — spot-check hardcoded JS strings\n";
$learnFiles = [
    'templates/single-lesson.php',
    'templates/single-quiz.php',
    'assets/js/quiz-taker.js',
    'assets/js/frontend.js',
];
foreach ($learnFiles as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        continue;
    }
    $src = (string) file_get_contents($path);
    $hits = [];
    if (preg_match_all("/textContent\s*=\s*'([^']+)'/", $src, $m)) {
        foreach ($m[1] as $s) {
            if (preg_match('/^[A-Z]/', $s)) {
                $hits[] = $s;
            }
        }
    }
    $hits = array_unique(array_slice($hits, 0, 5));
    echo '   ' . $rel;
    if ($hits === []) {
        echo " — OK (no obvious literal textContent)\n";
    } else {
        echo ' — literals: ' . implode(', ', $hits) . "\n";
    }
}

echo "\nDone.\n";
exit($phpExit === 0 ? 0 : 1);
