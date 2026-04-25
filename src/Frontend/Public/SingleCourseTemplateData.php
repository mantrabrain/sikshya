<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Addons\Addons;
use Sikshya\Licensing\Pro;

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Services\Settings;
use Sikshya\Presentation\Models\SingleCoursePageModel;
use Sikshya\Services\Frontend\SingleCoursePageService;

/**
 * View-model for {@see templates/single-course.php} (no business logic in the template file).
 *
 * @package Sikshya\Frontend\Public
 */
final class SingleCourseTemplateData
{
    /**
     * Deprecated: templates should consume {@see SingleCoursePageModel}.
     */
    public static function forPost(\WP_Post $post): SingleCoursePageModel
    {
        return SingleCoursePageService::forPost($post);
    }

    /**
     * Legacy array payload for hooks/filters.
     *
     * @return array<string, mixed>
     */
    public static function legacyArrayForPost(\WP_Post $post): array
    {
        $course_id = (int) $post->ID;
        $pricing = function_exists('sikshya_get_course_pricing') ? sikshya_get_course_pricing($course_id) : [
            'price' => null,
            'sale_price' => null,
            'currency' => 'USD',
            'effective' => null,
            'on_sale' => false,
        ];

        $uid = get_current_user_id();
        $repo = new EnrollmentRepository();
        $enrolled = $uid > 0 && $repo->findByUserAndCourse($uid, $course_id) !== null;

        $is_paid = null !== $pricing['effective'] && (float) $pricing['effective'] > 0;

        $can_admin_enroll_without_purchase = false;
        if ($uid > 0 && !$enrolled && $is_paid && function_exists('sikshya_current_user_can_admin_enroll_without_purchase')) {
            $can_admin_enroll_without_purchase = sikshya_current_user_can_admin_enroll_without_purchase();
        }

        $primary = 'login';
        if ($enrolled) {
            $primary = 'continue';
        } elseif ($is_paid) {
            $primary = 'cart';
        } elseif (is_user_logged_in()) {
            $primary = 'enroll_free';
        }

        $duration_raw = function_exists('sikshya_first_nonempty_post_meta')
            ? (string) sikshya_first_nonempty_post_meta($course_id, ['_sikshya_duration', '_sikshya_course_duration', 'sikshya_course_duration'])
            : '';
        $duration = $duration_raw !== '' ? $duration_raw : null;
        $duration_hours = $duration !== null && is_numeric($duration) ? (float) $duration : null;

        $difficulty = function_exists('sikshya_first_nonempty_post_meta')
            ? (string) sikshya_first_nonempty_post_meta($course_id, ['_sikshya_difficulty', '_sikshya_course_difficulty', 'sikshya_course_level'])
            : '';

        $author = get_userdata((int) $post->post_author);
        $curriculum = function_exists('sikshya_get_course_curriculum_public')
            ? sikshya_get_course_curriculum_public($course_id)
            : [];

        $category_trail = self::categoryBreadcrumbTrail($course_id);
        $tag_pills = self::tagPills($course_id);
        $learning_outcomes = self::stringListFromMeta(get_post_meta($course_id, '_sikshya_learning_outcomes', true));
        $course_highlights = self::stringListFromMeta(get_post_meta($course_id, '_sikshya_course_highlights', true));
        $course_faq = self::faqFromMeta(get_post_meta($course_id, '_sikshya_course_faq', true));
        $video_url_raw = get_post_meta($course_id, '_sikshya_video_url', true);
        $video_url = is_string($video_url_raw) ? trim($video_url_raw) : '';
        $featured_thumb = get_the_post_thumbnail_url($course_id, 'large') ?: '';
        $video_preview = self::videoPreview($video_url !== '' ? $video_url : null, $featured_thumb);

        $short_desc = get_post_meta($course_id, '_sikshya_short_description', true);
        $subtitle = '';
        if (is_string($short_desc) && $short_desc !== '') {
            $subtitle = $short_desc;
        } elseif ($post->post_excerpt !== '') {
            $subtitle = (string) $post->post_excerpt;
        }

        $lang_code = get_post_meta($course_id, '_sikshya_language', true);
        $language_label = self::languageLabel(is_string($lang_code) ? $lang_code : '');

        $target_audience = get_post_meta($course_id, '_sikshya_target_audience', true);
        $target_audience_html = is_string($target_audience) && $target_audience !== '' ? wp_kses_post($target_audience) : '';

        $instructor_profiles = self::instructorProfiles($course_id, $post);
        $lead_user = null;
        if ($instructor_profiles !== []) {
            $lead_user = get_userdata((int) $instructor_profiles[0]['id']);
        }
        if (!$lead_user instanceof \WP_User) {
            $lead_user = $author instanceof \WP_User ? $author : null;
        }

        $curriculum_stats = self::curriculumStats($curriculum);
        $includes_lines = self::buildIncludesLines($duration_hours, $curriculum_stats, $course_highlights);
        $first_content_url = self::firstContentUrl($curriculum, $course_id);

        // Bundle support: detect via meta, build included-course list.
        $is_bundle = sanitize_key((string) get_post_meta($course_id, '_sikshya_course_type', true)) === 'bundle';
        $bundle_courses = [];
        if ($is_bundle) {
            $raw_ids = get_post_meta($course_id, '_sikshya_bundle_course_ids', true);
            if (is_string($raw_ids) && $raw_ids !== '') {
                $dec = json_decode($raw_ids, true);
                $raw_ids = is_array($dec) ? $dec : [];
            }
            foreach ((array) $raw_ids as $bid) {
                $bid = (int) $bid;
                if ($bid <= 0) {
                    continue;
                }
                $bp = get_post($bid);
                if (!$bp instanceof \WP_Post) {
                    continue;
                }
                $bundle_courses[] = [
                    'id'    => $bid,
                    'post'  => $bp,
                    'title' => get_the_title($bp),
                    'thumb' => get_the_post_thumbnail_url($bid, 'thumbnail') ?: '',
                    'url'   => get_permalink($bid) ?: '',
                ];
            }
        }

        $discount_percent = null;
        if (!empty($pricing['on_sale']) && isset($pricing['price'], $pricing['sale_price'])) {
            $reg = (float) $pricing['price'];
            $sale = (float) $pricing['sale_price'];
            if ($reg > 0.00001 && $sale < $reg) {
                $discount_percent = (int) max(0, min(99, round(100 - ($sale / $reg) * 100)));
            }
        }

        $last_updated = get_the_modified_date(Settings::getRaw('date_format'), $post);

        $data = [
            'course_id' => $course_id,
            'post' => $post,
            'pricing' => $pricing,
            'is_paid' => $is_paid,
            'is_enrolled' => $enrolled,
            'can_admin_enroll_without_purchase' => $can_admin_enroll_without_purchase,
            'primary_action' => $primary,
            'curriculum' => $curriculum,
            'curriculum_stats' => $curriculum_stats,
            'duration' => $duration,
            'duration_hours' => $duration_hours,
            'difficulty' => $difficulty !== '' ? $difficulty : null,
            'instructor' => $lead_user,
            'instructor_profiles' => $instructor_profiles,
            'cart_flash' => self::cartFlashFromRequest(),
            'category_trail' => $category_trail,
            'tag_pills' => $tag_pills,
            'subtitle' => $subtitle,
            'learning_outcomes' => $learning_outcomes,
            'course_highlights' => $course_highlights,
            'course_faq' => $course_faq,
            'video_url' => $video_url !== '' ? $video_url : null,
            'video_preview' => $video_preview,
            'featured_image_url' => $featured_thumb !== '' ? $featured_thumb : null,
            'language_label' => $language_label,
            'target_audience_html' => $target_audience_html,
            'last_updated' => $last_updated,
            'discount_percent' => $discount_percent,
            'includes_lines'  => $includes_lines,
            'is_bundle'       => $is_bundle,
            'bundle_courses'  => $bundle_courses,
            'money_back_text' => (string) apply_filters(
                'sikshya_course_money_back_text',
                __('30-day money-back guarantee', 'sikshya'),
                $course_id
            ),
            'urls' => [
                'cart' => PublicPageUrls::url('cart'),
                'checkout' => PublicPageUrls::url('checkout'),
                'learn' => PublicPageUrls::learnForCourse($course_id),
                'learn_first' => $first_content_url,
                'account' => PublicPageUrls::url('account'),
                'courses_archive' => get_post_type_archive_link(PostTypes::COURSE) ?: '',
                'login' => wp_login_url(get_permalink($course_id)),
            ],
        ];

        // `reviews_vm` is populated by the `course_reviews` Pro addon when active
        // (see SikshyaPro\Frontend\ProReviewTemplateHooks). Default keeps the
        // template silent when the addon is disabled.
        $data['reviews_vm'] = ['enabled' => false];
        $data['rest'] = [
            'url' => esc_url_raw(rest_url('sikshya/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
        ];

        return apply_filters('sikshya_single_course_template_data', $data, $post);
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    private static function categoryBreadcrumbTrail(int $course_id): array
    {
        $terms = wp_get_post_terms($course_id, Taxonomies::COURSE_CATEGORY);
        if (is_wp_error($terms) || $terms === []) {
            return [];
        }

        $term = $terms[0];
        $chain = [];
        $ancestors = array_reverse(get_ancestors((int) $term->term_id, Taxonomies::COURSE_CATEGORY));
        foreach ($ancestors as $tid) {
            $t = get_term((int) $tid, Taxonomies::COURSE_CATEGORY);
            if ($t && !is_wp_error($t)) {
                $link = get_term_link($t);
                $chain[] = [
                    'name' => $t->name,
                    'url' => is_wp_error($link) ? '' : (string) $link,
                ];
            }
        }
        $link = get_term_link($term);
        $chain[] = [
            'name' => $term->name,
            'url' => is_wp_error($link) ? '' : (string) $link,
        ];

        return $chain;
    }

    /**
     * First curriculum item deep link into Learn UI.
     *
     * @param array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}> $curriculum
     */
    private static function firstContentUrl(array $curriculum, int $course_id): string
    {
        foreach ($curriculum as $block) {
            foreach ($block['contents'] ?? [] as $p) {
                if (!$p instanceof \WP_Post) {
                    continue;
                }
                $pt = (string) $p->post_type;
                $slug = (string) $p->post_name;
                if ($slug === '') {
                    continue;
                }
                if ($pt === PostTypes::LESSON) {
                    return PublicPageUrls::learnContentForPost($p);
                }
                if ($pt === PostTypes::QUIZ) {
                    return PublicPageUrls::learnContentForPost($p);
                }
                if ($pt === PostTypes::ASSIGNMENT) {
                    return PublicPageUrls::learnContentForPost($p);
                }
            }
        }

        return PublicPageUrls::learnForCourse($course_id);
    }

    /**
     * @return array<int, string>
     */
    private static function tagPills(int $course_id): array
    {
        $tags = wp_get_post_terms($course_id, Taxonomies::COURSE_TAG, ['fields' => 'names']);
        if (is_wp_error($tags) || !is_array($tags)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $tags)));
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private static function stringListFromMeta($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $lines = preg_split("/\r\n|\n|\r/", $raw);
                if (!is_array($lines)) {
                    $lines = [];
                }

                return array_values(
                    array_filter(
                        array_map(
                            static function ($line) {
                                return sanitize_text_field((string) $line);
                            },
                            $lines
                        )
                    )
                );
            }
        }
        if (!is_array($raw)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static function ($v) {
                        return sanitize_text_field((string) $v);
                    },
                    $raw
                )
            )
        );
    }

    /**
     * @param mixed $raw
     * @return array<int, array{question: string, answer: string}>
     */
    private static function faqFromMeta($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = isset($row['question']) ? sanitize_text_field((string) $row['question']) : '';
            $a = isset($row['answer']) ? wp_kses_post((string) $row['answer']) : '';
            if ($q !== '' && $a !== '') {
                $out[] = ['question' => $q, 'answer' => $a];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{id: int, name: string, bio: string, avatar_url: string, profile_url: string}>
     */
    private static function instructorProfiles(int $course_id, \WP_Post $post): array
    {
        $raw = get_post_meta($course_id, '_sikshya_instructors', true);
        $ids = [];
        if (is_array($raw)) {
            $ids = array_map('intval', $raw);
        } elseif (is_numeric($raw)) {
            $ids = [(int) $raw];
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $profiles = [];
        foreach ($ids as $uid) {
            $u = get_userdata($uid);
            if ($u instanceof \WP_User) {
                $profiles[] = self::userToProfile($u);
            }
        }
        if ($profiles === []) {
            $author = get_userdata((int) $post->post_author);
            if ($author instanceof \WP_User) {
                $profiles[] = self::userToProfile($author);
            }
        }

        return $profiles;
    }

    /**
     * @return array{id: int, name: string, bio: string, avatar_url: string, profile_url: string}
     */
    private static function userToProfile(\WP_User $u): array
    {
        $bio = get_the_author_meta('description', $u->ID);
        $bio = is_string($bio) ? $bio : '';

        $avatar = get_avatar_url($u->ID, ['size' => 196]);

        return [
            'id' => (int) $u->ID,
            'name' => $u->display_name,
            'bio' => $bio,
            'avatar_url' => is_string($avatar) ? $avatar : '',
            'profile_url' => get_author_posts_url($u->ID),
        ];
    }

    private static function languageLabel(string $code): string
    {
        $map = [
            'en' => __('English', 'sikshya'),
            'es' => __('Spanish', 'sikshya'),
            'fr' => __('French', 'sikshya'),
            'de' => __('German', 'sikshya'),
            'it' => __('Italian', 'sikshya'),
            'pt' => __('Portuguese', 'sikshya'),
            'other' => __('Other', 'sikshya'),
        ];

        return $map[$code] ?? '';
    }

    /**
     * @param array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}> $curriculum
     * @return array{chapters: int, items: int, lessons: int, quizzes: int, assignments: int}
     */
    private static function curriculumStats(array $curriculum): array
    {
        $chapters = count($curriculum);
        $lessons = 0;
        $quizzes = 0;
        $assignments = 0;
        $items = 0;
        foreach ($curriculum as $block) {
            foreach ($block['contents'] ?? [] as $p) {
                if (!$p instanceof \WP_Post) {
                    continue;
                }
                ++$items;
                $t = $p->post_type;
                if ($t === PostTypes::LESSON) {
                    ++$lessons;
                } elseif ($t === PostTypes::QUIZ) {
                    ++$quizzes;
                } elseif ($t === PostTypes::ASSIGNMENT) {
                    ++$assignments;
                }
            }
        }

        return [
            'chapters' => $chapters,
            'items' => $items,
            'lessons' => $lessons,
            'quizzes' => $quizzes,
            'assignments' => $assignments,
        ];
    }

    /**
     * @param array{chapters: int, items: int, lessons: int, quizzes: int, assignments: int} $stats
     * @param array<int, string>                                                            $highlights
     * @return array<int, string>
     */
    private static function buildIncludesLines(?float $duration_hours, array $stats, array $highlights): array
    {
        $lines = [];
        if (null !== $duration_hours && $duration_hours > 0) {
            $lines[] = sprintf(
                /* translators: %s: formatted hours, e.g. 12.5 */
                __('%s hours on-demand video', 'sikshya'),
                number_format_i18n($duration_hours, 1)
            );
        } elseif ($stats['items'] > 0) {
            $lines[] = sprintf(
                /* translators: %s: number of curriculum items */
                __('%s lessons & activities', 'sikshya'),
                number_format_i18n((int) $stats['items'])
            );
        }

        $lines[] = __('Full lifetime access', 'sikshya');
        $lines[] = __('Access on mobile and desktop', 'sikshya');

        foreach ($highlights as $h) {
            if ($h !== '') {
                $lines[] = $h;
            }
        }

        return array_values(array_unique($lines));
    }

    /**
     * @return array{kind: string, watch_url: string, thumb_url: string, embed_url: string}|null
     */
    private static function videoPreview(?string $url, string $fallback_thumb): ?array
    {
        if ($url === null || $url === '') {
            if ($fallback_thumb === '') {
                return null;
            }

            return [
                'kind' => 'image',
                'watch_url' => '',
                'thumb_url' => $fallback_thumb,
                'embed_url' => '',
            ];
        }

        if (preg_match('/youtu\.be\/([^?&]+)/', $url, $m)
            || preg_match('/[?&]v=([^&]+)/', $url, $m)
            || preg_match('/youtube\.com\/embed\/([^?&]+)/', $url, $m)
        ) {
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $m[1]);
            if ($id === '') {
                return [
                    'kind' => 'link',
                    'watch_url' => $url,
                    'thumb_url' => $fallback_thumb !== '' ? $fallback_thumb : '',
                    'embed_url' => '',
                ];
            }

            return [
                'kind' => 'youtube',
                'watch_url' => $url,
                'thumb_url' => 'https://img.youtube.com/vi/' . rawurlencode($id) . '/hqdefault.jpg',
                'embed_url' => 'https://www.youtube.com/embed/' . rawurlencode($id) . '?rel=0',
            ];
        }

        if (preg_match('#vimeo\.com\/(?:video\/)?(\d+)#', $url, $m)) {
            return [
                'kind' => 'vimeo',
                'watch_url' => $url,
                'thumb_url' => $fallback_thumb !== '' ? $fallback_thumb : '',
                'embed_url' => 'https://player.vimeo.com/video/' . rawurlencode($m[1]),
            ];
        }

        return [
            'kind' => 'link',
            'watch_url' => $url,
            'thumb_url' => $fallback_thumb !== '' ? $fallback_thumb : '',
            'embed_url' => '',
        ];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private static function cartFlashFromRequest(): ?array
    {
        return CartFlashResolver::fromRequest();
    }
}
