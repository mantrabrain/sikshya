<?php

declare(strict_types=1);

namespace Sikshya\Import;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One LMS Sikshya can import from.
 *
 * A source reads; it never writes to the plugin it is reading. The old site
 * stays exactly as it was, which is what makes a migration something a nervous
 * administrator is willing to try: if the result is wrong, they switch back.
 *
 * @package Sikshya\Import
 */
interface ImportSourceInterface
{
    /** Stable key, e.g. `tutor`. */
    public function id(): string;

    /** Human name, e.g. "Tutor LMS". */
    public function label(): string;

    /**
     * Whether this source's data is present on the site.
     *
     * Detection looks for *data*, not for an active plugin: the commonest
     * migration is "I deactivated the old plugin and now want its courses",
     * and a check for the plugin being active would report nothing to import.
     */
    public function detect(): bool;

    /**
     * How much there is, by object type.
     *
     * @return array<string, int>
     */
    public function counts(): array;

    /**
     * Source course ids, oldest first, after `$afterId`.
     *
     * @return array<int, int>
     */
    public function courseIds(int $afterId, int $limit): array;

    /**
     * Everything needed to recreate one course.
     *
     * @return array<string, mixed>|null
     */
    public function readCourse(int $courseId): ?array;
}
