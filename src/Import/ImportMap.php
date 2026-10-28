<?php

declare(strict_types=1);

namespace Sikshya\Import;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads and writes of the source → Sikshya mapping.
 *
 * @package Sikshya\Import
 */
final class ImportMap
{
    private string $source;

    public function __construct(string $source)
    {
        $this->source = sanitize_key($source);
    }

    /**
     * The Sikshya id already created for a source object, or 0.
     *
     * Returns 0 — rather than the stale id — when the Sikshya post has since
     * been deleted, so a re-run recreates it instead of writing children onto
     * a post that is not there.
     */
    public function find(string $objectType, int $sourceId): int
    {
        global $wpdb;

        $t = ImportMapTable::name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT sikshya_id FROM {$t} WHERE source = %s AND object_type = %s AND source_id = %d",
            $this->source,
            sanitize_key($objectType),
            $sourceId
        ));

        if ($id <= 0) {
            return 0;
        }

        if (in_array($objectType, self::postObjectTypes(), true) && get_post($id) === null) {
            $this->forget($objectType, $sourceId);

            return 0;
        }

        return $id;
    }

    public function remember(string $objectType, int $sourceId, int $sikshyaId, string $runId = ''): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
            'INSERT INTO ' . ImportMapTable::name() . ' (source, object_type, source_id, sikshya_id, run_id)
             VALUES (%s, %s, %d, %d, %s)
             ON DUPLICATE KEY UPDATE sikshya_id = VALUES(sikshya_id), run_id = VALUES(run_id)',
            $this->source,
            sanitize_key($objectType),
            $sourceId,
            $sikshyaId,
            $runId
        ));
    }

    public function forget(string $objectType, int $sourceId): void
    {
        global $wpdb;

        $wpdb->delete(
            ImportMapTable::name(),
            ['source' => $this->source, 'object_type' => sanitize_key($objectType), 'source_id' => $sourceId],
            ['%s', '%s', '%d']
        );
    }

    /** @return array<string, int> object_type => rows imported */
    public function counts(): array
    {
        global $wpdb;

        $t = ImportMapTable::name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT object_type, COUNT(*) AS n FROM {$t} WHERE source = %s GROUP BY object_type", $this->source),
            ARRAY_A
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(string) $row['object_type']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Object types that are WordPress posts, and so can be checked for
     * existence. Enrolments and completions are rows, not posts.
     *
     * @return array<int, string>
     */
    private static function postObjectTypes(): array
    {
        return ['course', 'chapter', 'lesson', 'quiz', 'question'];
    }
}
