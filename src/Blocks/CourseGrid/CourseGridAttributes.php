<?php

declare(strict_types=1);

namespace Sikshya\Blocks\CourseGrid;

/**
 * Normalises raw sikshya/course-grid attributes into a trusted shape.
 *
 * block.json types are advisory: a saved post can carry anything, and the
 * REST preview endpoint hands the render callback whatever the editor sent.
 * Every value the renderer and the query builder read comes through here, so
 * neither has to defend itself.
 *
 * @package Sikshya\Blocks\CourseGrid
 */
final class CourseGridAttributes
{
    public const LAYOUTS = ['grid', 'list', 'carousel-less'];
    public const CARD_STYLES = ['card', 'bordered', 'minimal', 'overlay'];
    public const ORDERBY = ['date', 'title', 'price', 'menu_order', 'rand', 'popular'];
    public const RATIOS = ['16-9', '4-3', '3-2', '1-1', 'auto'];
    public const PRICE_FILTERS = ['any', 'free', 'paid'];

    public const MAX_PER_PAGE = 48;
    public const MAX_COLUMNS = 6;

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $out = [];

        $out['perPage'] = self::intRange($raw['perPage'] ?? 6, 1, self::MAX_PER_PAGE, 6);
        $out['columns'] = self::intRange($raw['columns'] ?? 3, 1, self::MAX_COLUMNS, 3);
        $out['columnsTablet'] = self::intRange($raw['columnsTablet'] ?? 2, 1, self::MAX_COLUMNS, 2);
        $out['columnsMobile'] = self::intRange($raw['columnsMobile'] ?? 1, 1, self::MAX_COLUMNS, 1);

        $out['layout'] = self::enum($raw['layout'] ?? 'grid', self::LAYOUTS, 'grid');
        if ($out['layout'] === 'carousel-less') {
            // Reserved value from an earlier draft of the block; treat as grid
            // rather than rendering nothing for anyone who saved it.
            $out['layout'] = 'grid';
        }
        $out['cardStyle'] = self::enum($raw['cardStyle'] ?? 'card', self::CARD_STYLES, 'card');
        $out['aspectRatio'] = self::enum($raw['aspectRatio'] ?? '16-9', self::RATIOS, '16-9');

        $out['orderBy'] = self::enum($raw['orderBy'] ?? 'date', self::ORDERBY, 'date');
        $out['order'] = strtoupper((string) ($raw['order'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';

        $out['categories'] = self::ids($raw['categories'] ?? []);
        $out['tags'] = self::ids($raw['tags'] ?? []);
        $out['instructors'] = self::ids($raw['instructors'] ?? []);
        $out['includeIds'] = self::ids($raw['includeIds'] ?? []);
        $out['excludeIds'] = self::ids($raw['excludeIds'] ?? []);

        $out['priceFilter'] = self::enum($raw['priceFilter'] ?? 'any', self::PRICE_FILTERS, 'any');
        $out['enrolledOnly'] = self::bool($raw['enrolledOnly'] ?? false);
        $out['excludeEnrolled'] = self::bool($raw['excludeEnrolled'] ?? false);

        $out['showThumbnail'] = self::bool($raw['showThumbnail'] ?? true);
        $out['showCategory'] = self::bool($raw['showCategory'] ?? true);
        $out['showExcerpt'] = self::bool($raw['showExcerpt'] ?? true);
        $out['showInstructor'] = self::bool($raw['showInstructor'] ?? true);
        $out['showRating'] = self::bool($raw['showRating'] ?? true);
        $out['showPrice'] = self::bool($raw['showPrice'] ?? true);
        $out['showMeta'] = self::bool($raw['showMeta'] ?? true);
        $out['showDifficulty'] = self::bool($raw['showDifficulty'] ?? true);
        $out['showProgress'] = self::bool($raw['showProgress'] ?? true);
        $out['showButton'] = self::bool($raw['showButton'] ?? true);

        $out['excerptLength'] = self::intRange($raw['excerptLength'] ?? 18, 4, 60, 18);
        $out['buttonLabel'] = self::text($raw['buttonLabel'] ?? '', 60);
        $out['emptyMessage'] = self::text($raw['emptyMessage'] ?? '', 200);

        $out['pagination'] = self::bool($raw['pagination'] ?? false);
        $out['queryId'] = self::intRange($raw['queryId'] ?? 0, 0, 999, 0);

        return $out;
    }

    /**
     * @param mixed $value
     */
    private static function intRange($value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $int = (int) $value;
        if ($int < $min) {
            return $min;
        }
        if ($int > $max) {
            return $max;
        }

        return $int;
    }

    /**
     * @param mixed    $value
     * @param string[] $allowed
     */
    private static function enum($value, array $allowed, string $default): string
    {
        if (!is_string($value)) {
            return $default;
        }

        $value = sanitize_key($value);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * @param mixed $value
     */
    private static function bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return !in_array(strtolower($value), ['', '0', 'false', 'no'], true);
        }

        return (bool) $value;
    }

    /**
     * Accepts an array, or a comma-separated string (what the shortcode bridge sends).
     *
     * @param mixed $value
     * @return int[]
     */
    private static function ids($value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : (preg_split('/[\s,]+/', $value) ?: []);
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $entry) {
            // The editor's term/post pickers can hand back objects.
            if (is_array($entry)) {
                $entry = $entry['id'] ?? $entry['value'] ?? 0;
            }
            if (!is_numeric($entry)) {
                continue;
            }
            $id = (int) $entry;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        // array_values: JSON-encoding a sparse map would produce an object.
        return array_values($ids);
    }

    /**
     * @param mixed $value
     */
    private static function text($value, int $maxLength): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = sanitize_text_field($value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
    }
}
