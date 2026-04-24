<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;

/**
 * Persists course IDs for the storefront cart (user meta + guest cookie).
 * Optional {@see self::BUNDLE_META_KEY} when the cart matches a Pro course bundle.
 *
 * @package Sikshya\Frontend\Public
 */
final class CartStorage
{
    private const USER_META = 'sikshya_cart_course_ids';

    private const BUNDLE_META = 'sikshya_cart_bundle_id';

    private const BUNDLE_COOKIE = 'sikshya_bundle_cart_v1';

    public static function cookieName(): string
    {
        return 'sikshya_cart_v1';
    }

    /**
     * When > 0, checkout should price the cart as that bundle (Pro) if course IDs still match.
     */
    public static function getBundleId(): int
    {
        $uid = get_current_user_id();
        if ($uid > 0) {
            $raw = get_user_meta($uid, self::BUNDLE_META, true);

            return max(0, (int) $raw);
        }
        if (!empty($_COOKIE[self::BUNDLE_COOKIE])) {
            return max(0, (int) sanitize_text_field(wp_unslash((string) $_COOKIE[self::BUNDLE_COOKIE])));
        }

        return 0;
    }

    /**
     * @param array<int, int> $ids
     */
    private static function persistIds(array $ids): void
    {
        $uid = get_current_user_id();
        if ($uid <= 0) {
            self::setGuestIds($ids);

            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        update_user_meta($uid, self::USER_META, $ids);
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

    private static function persistBundleId(int $bundle_id): void
    {
        $bundle_id = max(0, $bundle_id);
        $uid = get_current_user_id();
        if ($uid > 0) {
            if ($bundle_id <= 0) {
                delete_user_meta($uid, self::BUNDLE_META);
            } else {
                update_user_meta($uid, self::BUNDLE_META, $bundle_id);
            }

            return;
        }

        $secure = is_ssl();
        $path = COOKIEPATH ?: '/';
        $domain = (string) COOKIE_DOMAIN;
        $expires = time() + DAY_IN_SECONDS * 30;
        $val = $bundle_id > 0 ? (string) $bundle_id : '';

        if ($bundle_id <= 0) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie(self::BUNDLE_COOKIE, '', [
                    'expires' => time() - 3600,
                    'path' => $path,
                    'domain' => $domain !== '' ? $domain : '',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie(self::BUNDLE_COOKIE, '', time() - 3600, $path, $domain, $secure, true);
            }
            unset($_COOKIE[self::BUNDLE_COOKIE]);

            return;
        }

        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::BUNDLE_COOKIE, $val, [
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain !== '' ? $domain : '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(self::BUNDLE_COOKIE, $val, $expires, $path, $domain, $secure, true);
        }

        $_COOKIE[self::BUNDLE_COOKIE] = $val;
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
     * Replace cart contents; clears bundle association (not a one-click bundle purchase).
     *
     * @param array<int, int> $ids
     */
    public static function setIds(array $ids): void
    {
        self::persistBundleId(0);
        self::persistIds($ids);
    }

    /**
     * Set cart to a fixed list of courses and mark checkout as a Pro bundle purchase.
     *
     * @param array<int, int> $ids
     */
    public static function setBundleCart(array $ids, int $bundle_id): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        self::persistIds($ids);
        self::persistBundleId(max(0, $bundle_id));
    }

    /**
     * @param array<int, int> $ids
     */
    public static function setBundleIdOnly(int $bundle_id): void
    {
        self::persistBundleId($bundle_id);
    }

    /**
     * @param array<int, int> $ids
     */
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
        self::persistBundleId(0);
        self::persistIds($ids);

        return true;
    }

    public static function removeCourse(int $course_id): bool
    {
        $ids = array_values(array_diff(self::getCourseIds(), [$course_id]));
        self::persistBundleId(0);
        self::persistIds($ids);

        return true;
    }

    public static function clear(): void
    {
        self::persistBundleId(0);
        self::persistIds([]);
    }
}
