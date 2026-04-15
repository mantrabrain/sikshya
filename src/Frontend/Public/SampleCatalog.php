<?php

namespace Sikshya\Frontend\Public;

/**
 * Lightweight access to bundled sample catalog JSON.
 *
 * Used only for UI mock metadata in learn/player pages.
 */
final class SampleCatalog
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = defined('SIKSHYA_PLUGIN_DIR')
            ? rtrim((string) SIKSHYA_PLUGIN_DIR, '/') . '/sample-data/sample-lms.json'
            : '';

        $data = [];
        if ($path !== '' && file_exists($path)) {
            if (function_exists('wp_json_file_decode')) {
                $decoded = wp_json_file_decode($path, ['associative' => true]);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } else {
                $raw = (string) file_get_contents($path);
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        self::$cache = $data;
        return self::$cache;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findCourseByTitle(string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $data = self::load();
        $courses = isset($data['courses']) && is_array($data['courses']) ? $data['courses'] : [];
        foreach ($courses as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (isset($c['title']) && (string) $c['title'] === $title) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Find a content item (lesson/quiz/assignment) by title in a given course.
     *
     * @return array<string, mixed>|null
     */
    public static function findContentByTitleInCourse(array $course, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $chapters = isset($course['chapters']) && is_array($course['chapters']) ? $course['chapters'] : [];
        foreach ($chapters as $ch) {
            if (!is_array($ch)) {
                continue;
            }
            $contents = isset($ch['contents']) && is_array($ch['contents']) ? $ch['contents'] : [];
            foreach ($contents as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (isset($item['title']) && (string) $item['title'] === $title) {
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Deterministic mock UI metadata (rating/students/updated/language).
     *
     * @return array{rating: float, rating_count: int, students: int, updated: string, language: string}
     */
    public static function mockUiMeta(string $seed): array
    {
        $seed = $seed !== '' ? $seed : 'sikshya';
        $n = (int) sprintf('%u', crc32($seed));

        $rating = 4.2 + (($n % 60) / 100); // 4.20 → 4.79
        $rating = round(min(4.8, max(4.2, $rating)), 1);

        $rating_count = 800 + ($n % 250000);
        $students = 2000 + ($n % 950000);

        $langs = ['English', 'Nepali', 'Hindi', 'Spanish', 'Portuguese'];
        $language = $langs[$n % count($langs)];

        $days_ago = 5 + ($n % 420);
        $updated = gmdate('F Y', time() - ($days_ago * DAY_IN_SECONDS));

        return [
            'rating' => (float) $rating,
            'rating_count' => (int) $rating_count,
            'students' => (int) $students,
            'updated' => (string) $updated,
            'language' => (string) $language,
        ];
    }
}

