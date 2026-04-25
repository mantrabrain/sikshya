<?php

/**
 * Read-only course presentation DTO. Built from a published {@see \WP_Post} in the service layer.
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class CourseModel
{
    private function __construct(
        private int $id,
        private \WP_Post $post
    ) {
    }

    public static function fromPost(\WP_Post $post): self
    {
        return new self((int) $post->ID, $post);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPost(): \WP_Post
    {
        return $this->post;
    }

    public function getTitle(): string
    {
        return (string) get_the_title($this->post);
    }

    public function getPermalink(): string
    {
        return (string) (get_permalink($this->post) ?: '');
    }

    public function getExcerptText(): string
    {
        $e = (string) $this->post->post_excerpt;
        if (trim($e) !== '') {
            return trim(wp_strip_all_tags($e));
        }
        if ((string) $this->post->post_content === '') {
            return '';
        }

        return trim(wp_trim_words(wp_strip_all_tags((string) $this->post->post_content), 55));
    }

    public function getContentHtml(): string
    {
        return (string) apply_filters('the_content', (string) $this->post->post_content);
    }

    public function getLargeThumbnailUrl(): string
    {
        return (string) (get_the_post_thumbnail_url($this->id, 'large') ?: '');
    }
}
