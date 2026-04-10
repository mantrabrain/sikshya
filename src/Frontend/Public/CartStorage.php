<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;

/**
 * Persists course IDs for the storefront cart (user meta + guest cookie).
 *
 * @package Sikshya\Frontend\Public
 */
final class CartStorage
{
    private const USER_META = 'sikshya_cart_course_ids';

    public static function cookieName(): string
    {
        return 'sikshya_cart_v1';
    }

    /**
     * @return array<int, int>
     */
    public static function getCourseIds(): array
    {
        $ids = [];
        $uid = get_current_user_id();
        if ($uid > 0) {
            $raw = get_user_meta($uid, self::USER_META, true);
            if (is_array($raw)) {
                foreach ($raw as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        } elseif (!empty($_COOKIE[self::cookieName()])) {
            $parts = explode(',', sanitize_text_field(wp_unslash((string) $_COOKIE[self::cookieName()])));
            foreach ($parts as $p) {
                $id = (int) $p;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, int> $ids
     */
    public static function setGuestIds(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $val = implode(',', $ids);
        $secure = is_ssl();
        $path = COOKIEPATH ?: '/';
        $domain = (string) COOKIE_DOMAIN;
        $expires = time() + DAY_IN_SECONDS * 30;

        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::cookieName(), $val, [
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain !== '' ? $domain : '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(self::cookieName(), $val, $expires, $path, $domain, $secure, true);
        }

        $_COOKIE[self::cookieName()] = $val;
    }

    /**
     * @param array<int, int> $ids
     */
    public static function setIds(array $ids): void
    {
        $uid = get_current_user_id();
        if ($uid <= 0) {
            self::setGuestIds($ids);

            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        update_user_meta($uid, self::USER_META, $ids);
    }

    public static function addCourse(int $course_id): bool
    {
        if ($course_id <= 0 || get_post_type($course_id) !== PostTypes::COURSE) {
            return false;
        }
        $ids = self::getCourseIds();
        if (in_array($course_id, $ids, true)) {
            return false;
        }
        $ids[] = $course_id;
        if (count($ids) > 50) {
            return false;
        }
        self::setIds($ids);

        return true;
    }

    public static function removeCourse(int $course_id): bool
    {
        $ids = array_values(array_diff(self::getCourseIds(), [$course_id]));
        self::setIds($ids);

        return true;
    }

    public static function clear(): void
    {
        self::setIds([]);
    }
}
