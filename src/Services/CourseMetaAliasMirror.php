<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

/**
 * Keeps the course meta alias pairs in sync whenever either side is written.
 *
 * A course's price, duration and difficulty have each been stored under two
 * different meta keys across the plugin's history. {@see \Sikshya\Migration\Steps\MirrorCourseAliases}
 * reconciles them once, as a legacy migration step — nothing kept them in sync
 * afterwards, and both keys in every pair are independently writable through
 * the REST meta API, so the two could silently drift apart on any site.
 *
 * Drift was not cosmetic. Readers disagreed about which key was authoritative:
 * checkout resolved the price through `sikshya_get_course_pricing()` (which
 * consults every alias) while the enrolment paywall read a single key, so a
 * course priced through the "wrong" alias looked free and could be enrolled in
 * without paying. That specific bug is fixed at the reader, but the underlying
 * split-brain in the data remained: a stale `_sikshya_price = 0` still beats a
 * live `_sikshya_course_price = 49`, because "0" is a legitimate non-empty
 * value and therefore wins the first-non-empty lookup.
 *
 * Rather than change read precedence — which would silently re-price existing
 * courses on live sites — this mirrors at the point of writing, so the pair can
 * never disagree in the first place. Whichever key is written wins, and its
 * partner is updated to match.
 *
 * @package Sikshya\Services
 */
final class CourseMetaAliasMirror
{
    /**
     * Pairs are mirrored in both directions; writing either side updates the other.
     *
     * Kept deliberately identical to {@see \Sikshya\Migration\Steps\MirrorCourseAliases::ALIAS_PAIRS}
     * so a one-off migration and the runtime mirror can never disagree.
     *
     * @var array<int, array{0:string,1:string}>
     */
    private const ALIAS_PAIRS = [
        ['_sikshya_price', '_sikshya_course_price'],
        ['_sikshya_duration', '_sikshya_course_duration'],
        ['_sikshya_difficulty', '_sikshya_course_level'],
    ];

    /**
     * Guards against the re-entrancy that mirroring would otherwise cause:
     * writing the partner key fires the same hooks again.
     */
    private static bool $mirroring = false;

    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('added_post_meta', [self::class, 'onMetaWritten'], 10, 4);
        add_action('updated_post_meta', [self::class, 'onMetaWritten'], 10, 4);
    }

    /**
     * @param int    $meta_id    Unused; present to match the hook signature.
     * @param int    $post_id    Post the meta belongs to.
     * @param string $meta_key   Key that was written.
     * @param mixed  $meta_value Value that was written.
     */
    public static function onMetaWritten($meta_id, $post_id, $meta_key, $meta_value): void
    {
        if (self::$mirroring) {
            return;
        }

        $partner = self::partnerKey((string) $meta_key);
        if ($partner === null) {
            return;
        }

        $post_id = (int) $post_id;
        if ($post_id <= 0 || get_post_type($post_id) !== PostTypes::COURSE) {
            return;
        }

        // Only scalars are mirrored. These keys hold a price, a duration or a
        // difficulty; anything else is not ours to copy.
        if (is_array($meta_value) || is_object($meta_value)) {
            return;
        }

        $existing = get_post_meta($post_id, $partner, true);
        if ((string) $existing === (string) $meta_value) {
            return;
        }

        self::$mirroring = true;
        try {
            update_post_meta($post_id, $partner, $meta_value);
        } finally {
            self::$mirroring = false;
        }
    }

    /**
     * The other key in the pair, or null when the key is not aliased.
     */
    private static function partnerKey(string $key): ?string
    {
        foreach (self::ALIAS_PAIRS as [$a, $b]) {
            if ($key === $a) {
                return $b;
            }
            if ($key === $b) {
                return $a;
            }
        }

        return null;
    }
}
