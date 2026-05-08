<?php
/**
 * Verifies gettext-style calls have enough arguments (avoids naive per-line grep false positives).
 *
 * @package Sikshya
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$exclude = ['vendor', 'node_modules'];

$walk = static function (string $dir) use (&$walk, $exclude): iterable {
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (in_array($item, $exclude, true)) {
                continue;
            }
            yield from $walk($path);
        } elseif (is_file($path) && str_ends_with($path, '.php')) {
            yield $path;
        }
    }
};

/**
 * @return array{0: int, 1: int}|null [argCount, closeParenIndex in tokens]
 */
function sikshya_count_call_args(array $tokens, int $openParenIndex): ?array
{
    $depth = 1;
    $argCount = 1;
    $j = $openParenIndex + 1;
    $len = count($tokens);
    while ($j < $len && $depth > 0) {
        $t = $tokens[$j];
        if ($t === '(') {
            ++$depth;
        } elseif ($t === ')') {
            --$depth;
            if ($depth === 0) {
                return [$argCount, $j];
            }
        } elseif ($t === ',' && $depth === 1) {
            ++$argCount;
        }
        ++$j;
    }

    return null;
}

$requiredArgs = [
    '__' => 2,
    '_e' => 2,
    'esc_html__' => 2,
    'esc_attr__' => 2,
    'esc_html_e' => 2,
    'esc_attr_e' => 2,
    'esc_attr_x' => 3,
    '_x' => 3,
    '_ex' => 3,
    '_n' => 4,
    '_nx' => 4,
];

$errors = [];

foreach ($walk($root) as $file) {
    $code = file_get_contents($file);
    if ($code === false) {
        continue;
    }
    $tokens = token_get_all($code);
    for ($i = 0, $c = count($tokens); $i < $c; ++$i) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING) {
            continue;
        }
        $name = $tokens[$i][1];
        if (!isset($requiredArgs[$name])) {
            continue;
        }
        $j = $i + 1;
        while ($j < $c && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            ++$j;
        }
        if ($j >= $c || $tokens[$j] !== '(') {
            continue;
        }
        $counted = sikshya_count_call_args($tokens, $j);
        if ($counted === null) {
            continue;
        }
        [$argCount] = $counted;
        if ($argCount < $requiredArgs[$name]) {
            $line = is_array($tokens[$i]) ? $tokens[$i][2] : 0;
            $rel = ltrim(str_replace($root, '', $file), '/');
            $errors[] = sprintf('%s:%d: %s() has %d argument(s), needs at least %d', $rel, $line, $name, $argCount, $requiredArgs[$name]);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "i18n text-domain / argument count issues:\n" . implode("\n", $errors) . "\n");
    exit(1);
}

echo "i18n gettext calls: argument counts OK\n";
