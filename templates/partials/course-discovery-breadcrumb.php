<?php
/**
 * Breadcrumb trail for course archive / taxonomy / category index pages.
 *
 * @package Sikshya
 *
 * @var array<int, array{label: string, url?: string}> $items Each item: label (required), url (omit for current page).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($items) || !is_array($items)) {
    return;
}
?>

<nav class="sikshya-discovery-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
    <?php
    $last_index = count($items) - 1;
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = isset($item['label']) ? (string) $item['label'] : '';
        if ($label === '') {
            continue;
        }
        if ($index > 0) {
            echo '<span class="sikshya-discovery-breadcrumb__sep" aria-hidden="true">›</span>';
        }
        $url = isset($item['url']) ? (string) $item['url'] : '';
        if ($url !== '' && $index < $last_index) {
            printf(
                '<a class="sikshya-discovery-breadcrumb__link" href="%s">%s</a>',
                esc_url($url),
                esc_html($label)
            );
        } else {
            printf(
                '<span class="sikshya-discovery-breadcrumb__current">%s</span>',
                esc_html($label)
            );
        }
    }
    ?>
</nav>
