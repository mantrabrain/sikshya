<?php

/**
 * Composes learner-facing lesson HTML (video/audio embeds + prose) for the Learn shell.
 *
 * @package Sikshya\Frontend\Site
 */

namespace Sikshya\Frontend\Site;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lesson body HTML for templates/single-lesson.php — embeds meta-driven media, then filtered post_content.
 */
final class LessonLearnContent
{
    /**
     * Sanitize learner lesson HTML allowing safe video embeds not covered by wp_kses_post('post').
     *
     * @param string $html Raw HTML fragment.
     * @return string Sanitized HTML.
     */
    public static function ksesLessonBody(string $html): string
    {
        $allowed = wp_kses_allowed_html('post');

        $allowed['iframe'] = [
            'src' => true,
            'width' => true,
            'height' => true,
            'frameborder' => true,
            'allow' => true,
            'allowfullscreen' => true,
            'loading' => true,
            'title' => true,
            'referrerpolicy' => true,
            'sandbox' => true,
            'style' => true,
            'class' => true,
            'name' => true,
        ];

        $allowed['video'] = [
            'src' => true,
            'controls' => true,
            'preload' => true,
            'playsinline' => true,
            'width' => true,
            'height' => true,
            'poster' => true,
            'class' => true,
            'title' => true,
        ];

        $allowed['source'] = [
            'src' => true,
            'type' => true,
        ];

        /** @var array<string, array<string, bool>> $allowed */
        return wp_kses($html, $allowed);
    }

    /**
     * Whether the lesson has displayable body content (prose or primary media).
     *
     * @param \WP_Post $lesson_post       Lesson post.
     * @param string   $lesson_type_key Sanitized {@see _sikshya_lesson_type} value.
     */
    public static function hasRenderableBody(\WP_Post $lesson_post, string $lesson_type_key): bool
    {
        if (trim((string) $lesson_post->post_content) !== '') {
            return true;
        }

        return self::primaryMediaMarkup((int) $lesson_post->ID, $lesson_type_key) !== '';
    }

    /**
     * Primary media block + filtered post_content (transcript, notes, etc.).
     *
     * @param string $lesson_type_key Sanitized {@see _sikshya_lesson_type} value.
     */
    /**
     * Video/audio embed markup only (no filtered post_content).
     *
     * @param string $lesson_type_key Sanitized {@see _sikshya_lesson_type} value.
     */
    public static function primaryMediaHtml(\WP_Post $lesson_post, string $lesson_type_key): string
    {
        return self::primaryMediaMarkup((int) $lesson_post->ID, $lesson_type_key);
    }

    /**
     * Lesson prose HTML after running post_content through {@see 'the_content'}.
     */
    public static function filteredPostContentHtml(\WP_Post $lesson_post): string
    {
        return (string) apply_filters('the_content', (string) $lesson_post->post_content);
    }

    public static function composedBodyHtml(\WP_Post $lesson_post, string $lesson_type_key): string
    {
        return self::primaryMediaHtml($lesson_post, $lesson_type_key)
            . self::filteredPostContentHtml($lesson_post);
    }

    /**
     * @param int    $lesson_id       Lesson post ID.
     * @param string $lesson_type_key Sanitized {@see _sikshya_lesson_type} value.
     */
    private static function primaryMediaMarkup(int $lesson_id, string $lesson_type_key): string
    {
        switch ($lesson_type_key) {
            case 'video':
                return self::videoBlock($lesson_id);
            case 'audio':
                return self::audioBlock($lesson_id);
            default:
                /*
                 * Imports and legacy lessons sometimes retain a hosted video URL but never set
                 * `_sikshya_lesson_type` to `video` — still surface the player on Learn.
                 */
                if ($lesson_type_key === '' || $lesson_type_key === 'text') {
                    return self::videoBlock($lesson_id);
                }

                return '';
        }
    }

    private static function videoBlock(int $lesson_id): string
    {
        $url = self::firstNonEmptyMeta(
            $lesson_id,
            ['_sikshya_lesson_video_url', 'sikshya_lesson_video_url']
        );
        if ($url === '') {
            return '';
        }

        $embed = self::embedFromVideoUrl($url, $lesson_id);
        if ($embed === '') {
            return '';
        }

        return '<div class="sikshya-lesson-embed sikshya-lesson-embed--video">' . $embed . '</div>';
    }

    private static function audioBlock(int $lesson_id): string
    {
        $url = self::firstNonEmptyMeta(
            $lesson_id,
            [
                '_sikshya_lesson_audio_url',
                'sikshya_lesson_audio_url',
            ]
        );
        if ($url === '') {
            return '';
        }

        $title = sprintf(
            /* translators: %s: lesson title */
            __('Audio: %s', 'sikshya'),
            get_the_title($lesson_id)
        );

        return '<div class="sikshya-lesson-embed sikshya-lesson-embed--audio">'
            . '<audio controls playsinline preload="metadata" src="'
            . esc_url($url)
            . '" title="'
            . esc_attr($title)
            . '"></audio></div>';
    }

    /**
     * @param list<string> $meta_keys
     */
    private static function firstNonEmptyMeta(int $post_id, array $meta_keys): string
    {
        foreach ($meta_keys as $key) {
            $raw = get_post_meta($post_id, $key, true);
            if ($raw !== null && $raw !== '') {
                if (is_numeric($raw)) {
                    return trim((string) $raw);
                }
                if (is_string($raw)) {
                    $t = trim($raw);
                    if ($t !== '') {
                        return $t;
                    }
                }
            }
        }

        return '';
    }

    private static function embedFromVideoUrl(string $url, int $lesson_id): string
    {
        $url = esc_url_raw($url);
        if ($url === '') {
            return '';
        }

        $embed_url = self::youtubeOrVimeoEmbedUrl($url);
        if ($embed_url !== '') {
            $title = sprintf(
                /* translators: %s: lesson title */
                __('Video: %s', 'sikshya'),
                get_the_title($lesson_id)
            );

            return sprintf(
                '<iframe src="%1$s" title="%2$s" width="640" height="360" loading="lazy" frameborder="0"'
                . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
                . ' allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
                esc_url($embed_url),
                esc_attr($title)
            );
        }

        if (self::isDirectVideoFileUrl($url)) {
            $title = sprintf(
                /* translators: %s: lesson title */
                __('Video: %s', 'sikshya'),
                get_the_title($lesson_id)
            );

            return sprintf(
                '<video controls playsinline preload="metadata" src="%s" title="%s"></video>',
                esc_url($url),
                esc_attr($title)
            );
        }

        if (function_exists('wp_oembed_get')) {
            $html = wp_oembed_get(
                $url,
                [
                    'width' => 640,
                    'height' => 360,
                    // Allow registered oEmbed providers (e.g. Loom, Wistia) not handled above.
                    'discover' => true,
                ]
            );
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }

        return sprintf(
            '<p class="sikshya-zeroMargin"><a class="sikshya-link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
            esc_url($url),
            esc_html(__('Open video in a new tab', 'sikshya'))
        );
    }

    private static function youtubeOrVimeoEmbedUrl(string $url): string
    {
        if (
            preg_match('~youtube\.com/shorts/([^/?&#]+)~i', $url, $m)
            || preg_match('/youtu\.be\/([^?&]+)/', $url, $m)
            || preg_match('/[?&]v=([^&]+)/', $url, $m)
            || preg_match('/youtube\.com\/embed\/([^?&]+)/', $url, $m)
        ) {
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $m[1]);

            return $id !== ''
                ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?rel=0'
                : '';
        }

        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . rawurlencode((string) $m[1]);
        }

        return '';
    }

    private static function isDirectVideoFileUrl(string $url): bool
    {
        $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?? '');
        $ext  = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm', 'ogg', 'ogv', 'm4v'], true);
    }
}
