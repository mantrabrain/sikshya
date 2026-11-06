<?php

declare(strict_types=1);

namespace Sikshya\Api;

use Sikshya\Constants\PostTypes;
use Sikshya\Services\Settings;
use WP_Post;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps paid course material out of the core `wp/v2` responses.
 *
 * Sikshya registers its post meta with `show_in_rest` so the block editor and
 * the React admin can read and write it. That flag is about the *write* path,
 * but WordPress reads it as "publish this field to anyone who may read the
 * post" — and for a published post that is everybody. `auth_callback` does not
 * change it: core consults that when writing, not when rendering a response.
 *
 * The result was that `GET /wp-json/wp/v2/sik_question` handed an anonymous
 * visitor `_sikshya_question_correct_answer` for every question on the site,
 * and `GET /wp-json/wp/v2/sik_lesson` handed over lesson bodies, video URLs,
 * transcripts, live-class join links and SCORM launch URLs — while the course
 * page and the Learn shell correctly refused the same visitor. The plugin's
 * own enrolment check never ran, because these are core's routes, not ours.
 *
 * The guard is **default-deny**: every meta key beginning with `_` is removed
 * from a response unless the requester may edit that post, or the key is on a
 * short allowlist of things already printed on the public course page. Listing
 * what to hide would leave each new meta key public until somebody remembered
 * it, which is how this arose in the first place.
 *
 * @package Sikshya\Api
 */
final class RestReadGuard
{
    /**
     * Private meta that is public information anyway — the course page prints
     * it to anonymous visitors, so hiding it here would break catalogue reads
     * without protecting anything.
     *
     * @var string[]
     */
    private const PUBLIC_COURSE_META = [
        '_sikshya_course_price',
        '_sikshya_course_sale_price',
        '_sikshya_price',
        '_sikshya_sale_price',
    ];

    /** Types whose body is course material rather than a sales page. */
    private const MATERIAL_TYPES = [
        PostTypes::LESSON,
        PostTypes::QUIZ,
        PostTypes::QUESTION,
        PostTypes::ASSIGNMENT,
    ];

    public static function register(): void
    {
        foreach (self::guardedTypes() as $type) {
            add_filter("rest_prepare_{$type}", [self::class, 'filterItem'], 10, 2);
        }

        add_filter('rest_endpoints', [self::class, 'gateInternalRoutes']);
    }

    /**
     * @return string[]
     */
    private static function guardedTypes(): array
    {
        return [
            PostTypes::COURSE,
            PostTypes::LESSON,
            PostTypes::QUIZ,
            PostTypes::QUESTION,
            PostTypes::ASSIGNMENT,
            PostTypes::CHAPTER,
        ];
    }

    /**
     * Strip private meta — and the body of paid material — for anyone who
     * cannot edit the post.
     *
     * @param mixed $response
     * @param mixed $post
     * @return mixed
     */
    public static function filterItem($response, $post)
    {
        if (!$response instanceof WP_REST_Response || !$post instanceof WP_Post) {
            return $response;
        }

        if (self::mayEdit($post)) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        if (isset($data['meta']) && is_array($data['meta'])) {
            /**
             * Private meta keys that stay readable without edit access.
             *
             * @param string[] $allowed  Allowlisted meta keys.
             * @param string   $type     Post type being prepared.
             */
            $allowed = (array) apply_filters(
                'sikshya_rest_public_meta_keys',
                $post->post_type === PostTypes::COURSE ? self::PUBLIC_COURSE_META : [],
                $post->post_type
            );

            foreach (array_keys($data['meta']) as $key) {
                $key = (string) $key;
                if (strncmp($key, '_', 1) === 0 && !in_array($key, $allowed, true)) {
                    unset($data['meta'][$key]);
                }
            }
        }

        if (in_array($post->post_type, self::MATERIAL_TYPES, true) && !self::isOpenToEveryone($post)) {
            foreach (['content', 'excerpt'] as $field) {
                if (isset($data[$field]) && is_array($data[$field])) {
                    foreach (['rendered', 'raw'] as $shape) {
                        if (array_key_exists($shape, $data[$field])) {
                            $data[$field][$shape] = '';
                        }
                    }
                }
            }
        }

        $response->set_data($data);

        return $response;
    }

    /**
     * Questions are registered `public => false` — nothing renders one on the
     * front of the site, so there is no reader to keep happy. Their titles are
     * the questions themselves, so stripping meta alone would still hand over
     * a site's whole question bank; the route is closed to non-authors instead.
     *
     * @param array<string, mixed> $routes
     * @return array<string, mixed>
     */
    public static function gateInternalRoutes($routes)
    {
        if (!is_array($routes)) {
            return $routes;
        }

        $object = get_post_type_object(PostTypes::QUESTION);
        if (!$object) {
            return $routes;
        }

        // `rest_base` is literally `false` unless a type sets one, and core
        // then falls back to the type's name. Treating the empty value as
        // "no route to guard" silently skipped the gate.
        $rest_base = is_string($object->rest_base) && $object->rest_base !== ''
            ? $object->rest_base
            : $object->name;

        $namespace = is_string($object->rest_namespace ?? null) && $object->rest_namespace !== ''
            ? $object->rest_namespace
            : 'wp/v2';
        $base = '/' . $namespace . '/' . $rest_base;

        foreach ([$base, $base . '/(?P<id>[\d]+)'] as $route) {
            if (!isset($routes[$route]) || !is_array($routes[$route])) {
                continue;
            }

            foreach ($routes[$route] as $index => $handler) {
                if (!is_array($handler) || !isset($handler['methods'])) {
                    continue;
                }
                // Only reads: creating and updating already carry core's checks.
                $methods = is_array($handler['methods'])
                    ? array_keys(array_filter($handler['methods']))
                    : array_map('trim', explode(',', (string) $handler['methods']));
                if (!in_array('GET', array_map('strtoupper', $methods), true)) {
                    continue;
                }

                $previous = $handler['permission_callback'] ?? null;
                $routes[$route][$index]['permission_callback'] = static function ($request) use ($previous, $object) {
                    if (!current_user_can($object->cap->edit_posts)) {
                        return new \WP_Error(
                            'sikshya_rest_forbidden',
                            __('Sorry, you are not allowed to read questions.', 'sikshya'),
                            ['status' => is_user_logged_in() ? 403 : 401]
                        );
                    }

                    return is_callable($previous) ? $previous($request) : true;
                };
            }
        }

        return $routes;
    }

    private static function mayEdit(WP_Post $post): bool
    {
        $object = get_post_type_object($post->post_type);
        if (!$object || !isset($object->cap->edit_post)) {
            return false;
        }

        return current_user_can($object->cap->edit_post, $post->ID);
    }

    /**
     * A step an author has deliberately opened as a preview. Questions and
     * assignments are never open, whatever meta they happen to carry.
     */
    private static function isOpenToEveryone(WP_Post $post): bool
    {
        if (!in_array($post->post_type, [PostTypes::LESSON, PostTypes::QUIZ], true)) {
            return false;
        }

        return Settings::isTruthy(get_post_meta($post->ID, '_sikshya_is_free', true));
    }
}
