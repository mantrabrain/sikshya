<?php
/**
 * Course Model
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Models;

use Sikshya\Constants\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Course
{
    /**
     * Course post object
     * 
     * @var \WP_Post|null
     */
    private $post;
    
    /**
     * Course meta data
     * 
     * @var array
     */
    private $meta = [];
    
    /**
     * Course ID
     * 
     * @var int
     */
    private $id;
    
    /**
     * Constructor
     * 
     * @param int|WP_Post $course_id_or_post
     */
    public function __construct($course_id_or_post = 0)
    {
        if ($course_id_or_post instanceof \WP_Post) {
            $this->post = $course_id_or_post;
            $this->id = $course_id_or_post->ID;
        } else {
            $this->id = (int) $course_id_or_post;
            if ($this->id > 0) {
                $this->post = get_post($this->id);
            }
        }
        
        if ($this->post) {
            $this->loadMeta();
        }
    }
    
    /**
     * Load course meta data
     * 
     * @return void
     */
    private function loadMeta(): void
    {
        if (!$this->post) {
            return;
        }
        
        $meta = get_post_meta($this->id);
        foreach ($meta as $key => $values) {
            if (is_array($values) && !empty($values)) {
                $this->meta[$key] = $values[0]; // Get first value
            }
        }
    }
    
    /**
     * Check if course exists
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return $this->post !== null && $this->post->post_type === PostTypes::COURSE;
    }
    
    /**
     * Get course ID
     * 
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
    
    /**
     * Get course title
     * 
     * @return string
     */
    public function getTitle(): string
    {
        return $this->post ? $this->post->post_title : '';
    }
    
    /**
     * Get course description/content
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return $this->post ? $this->post->post_content : '';
    }
    
    /**
     * Get course excerpt
     * 
     * @return string
     */
    public function getExcerpt(): string
    {
        return $this->post ? $this->post->post_excerpt : '';
    }
    
    /**
     * Get course slug
     * 
     * @return string
     */
    public function getSlug(): string
    {
        return $this->post ? $this->post->post_name : '';
    }
    
    /**
     * Get course status
     * 
     * @return string
     */
    public function getStatus(): string
    {
        return $this->post ? $this->post->post_status : '';
    }
    
    /**
     * Get course author ID
     * 
     * @return int
     */
    public function getAuthorId(): int
    {
        return $this->post ? $this->post->post_author : 0;
    }
    
    /**
     * Get course author name
     * 
     * @return string
     */
    public function getAuthorName(): string
    {
        if (!$this->post) {
            return '';
        }
        
        $author = get_userdata($this->post->post_author);
        return $author ? $author->display_name : '';
    }
    
    /**
     * Get course date
     * 
     * @param string $format
     * @return string
     */
    public function getDate(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->post ? get_the_date($format, $this->post) : '';
    }
    
    /**
     * Get course modified date
     * 
     * @param string $format
     * @return string
     */
    public function getModifiedDate(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->post ? get_the_modified_date($format, $this->post) : '';
    }
    
    /**
     * Get course permalink
     * 
     * @return string
     */
    public function getPermalink(): string
    {
        return $this->post ? get_permalink($this->post) : '';
    }
    
    /**
     * Get course edit link
     * 
     * @return string
     */
    public function getEditLink(): string
    {
        if (!$this->post) {
            return '';
        }
        
        $edit_link = get_edit_post_link($this->post);
        return $edit_link ? $edit_link : '';
    }
    
    /**
     * Get course preview link
     * 
     * @return string
     */
    public function getPreviewLink(): string
    {
        if (!$this->post) {
            return '';
        }
        
        $preview_link = get_preview_post_link($this->post);
        return $preview_link ? $preview_link : '';
    }
    
    /**
     * Get course thumbnail URL
     * 
     * @param string $size
     * @return string
     */
    public function getThumbnailUrl(string $size = 'medium'): string
    {
        if (!$this->post) {
            return '';
        }
        
        $thumbnail_id = get_post_thumbnail_id($this->post);
        if ($thumbnail_id) {
            $image = wp_get_attachment_image_src($thumbnail_id, $size);
            return $image ? $image[0] : '';
        }
        
        return '';
    }
    
    /**
     * Get course thumbnail HTML
     * 
     * @param string $size
     * @param array $attr
     * @return string
     */
    public function getThumbnailHtml(string $size = 'medium', array $attr = []): string
    {
        if (!$this->post) {
            return '';
        }
        
        return get_the_post_thumbnail($this->post, $size, $attr);
    }
    
    /**
     * Get course meta value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMeta(string $key, $default = '')
    {
        return $this->meta[$key] ?? $default;
    }
    
    /**
     * Set course meta value
     * 
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setMeta(string $key, $value): bool
    {
        if (!$this->post) {
            error_log('Sikshya Course Model: setMeta failed - no post object for key: ' . $key);
            return false;
        }
        
        error_log('Sikshya Course Model: setMeta called for key: ' . $key . ' with value: ' . print_r($value, true));
        
        $result = update_post_meta($this->id, $key, $value);
        error_log('Sikshya Course Model: update_post_meta result for key ' . $key . ': ' . var_export($result, true));
        
        // update_post_meta can return false if the value is the same as existing value
        // or if there's an actual error. We need to check if the meta was actually set
        $current_value = get_post_meta($this->id, $key, true);
        
        if ($result !== false || $current_value == $value) {
            $this->meta[$key] = $value;
            error_log('Sikshya Course Model: Meta updated successfully for key: ' . $key);
            return true;
        } else {
            error_log('Sikshya Course Model: Meta update failed for key: ' . $key);
            return false;
        }
    }
    
    /**
     * Delete course meta value
     * 
     * @param string $key
     * @return bool
     */
    public function deleteMeta(string $key): bool
    {
        if (!$this->post) {
            return false;
        }
        
        $result = delete_post_meta($this->id, $key);
        if ($result) {
            unset($this->meta[$key]);
        }
        
        return $result;
    }
    
    /**
     * Get all course meta
     * 
     * @return array
     */
    public function getAllMeta(): array
    {
        return $this->meta;
    }
    
    /**
     * Magic method to handle dynamic getters for meta fields
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        // Handle getter methods like getCoursePrice(), getTestField(), etc.
        if (strpos($name, 'get') === 0) {
            $field_name = lcfirst(substr($name, 3)); // Remove 'get' prefix
            
            // Special handling for title and description - get from post table
            if ($field_name === 'title') {
                return $this->getTitle();
            }
            
            if ($field_name === 'description') {
                return $this->getDescription();
            }
            
            // All other fields get from meta table
            return $this->getMeta($field_name, $arguments[0] ?? '');
        }
        
        // Handle setter methods like setCoursePrice(), setTestField(), etc.
        if (strpos($name, 'set') === 0) {
            $field_name = lcfirst(substr($name, 3)); // Remove 'set' prefix
            
            // Special handling for title and description - update post table
            if ($field_name === 'title') {
                if (!$this->post) {
                    return false;
                }
                $result = wp_update_post([
                    'ID' => $this->id,
                    'post_title' => $arguments[0] ?? ''
                ]);
                if (!is_wp_error($result)) {
                    $this->post = get_post($this->id);
                }
                return !is_wp_error($result);
            }
            
            if ($field_name === 'description') {
                if (!$this->post) {
                    return false;
                }
                $result = wp_update_post([
                    'ID' => $this->id,
                    'post_content' => $arguments[0] ?? ''
                ]);
                if (!is_wp_error($result)) {
                    $this->post = get_post($this->id);
                }
                return !is_wp_error($result);
            }
            
            // All other fields set in meta table
            return $this->setMeta($field_name, $arguments[0] ?? '');
        }
        
        throw new \BadMethodCallException("Method {$name} does not exist");
    }
    
    /**
     * Get course data as array
     * 
     * @return array
     */
    public function toArray(): array
    {
        if (!$this->post) {
            return [];
        }
        
        return [
            'id' => $this->id,
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'excerpt' => $this->getExcerpt(),
            'slug' => $this->getSlug(),
            'status' => $this->getStatus(),
            'author_id' => $this->getAuthorId(),
            'author_name' => $this->getAuthorName(),
            'date' => $this->getDate(),
            'modified_date' => $this->getModifiedDate(),
            'permalink' => $this->getPermalink(),
            'edit_link' => $this->getEditLink(),
            'preview_link' => $this->getPreviewLink(),
            'thumbnail_url' => $this->getThumbnailUrl(),
            'meta' => $this->getAllMeta(),
        ];
    }
    
    /**
     * Get course data as JSON
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
    
    /**
     * Create a new course
     * 
     * @param array $data
     * @return Course|null
     */
    public static function create(array $data): ?Course
    {
        $post_data = [
            'post_title' => sanitize_text_field($data['title'] ?? 'New Course'),
            'post_content' => wp_kses_post($data['description'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'post_name' => sanitize_title($data['slug'] ?? ''),
            'post_status' => sanitize_text_field($data['status'] ?? 'draft'),
            'post_type' => PostTypes::COURSE,
            'post_author' => get_current_user_id(),
        ];
        
        $course_id = wp_insert_post($post_data);
        
        if (is_wp_error($course_id)) {
            return null;
        }
        
        $course = new self($course_id);
        
        // Set meta fields
        if (isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                $course->setMeta($key, $value);
            }
        }
        
        return $course;
    }
    
    /**
     * Find course by ID
     * 
     * @param int $course_id
     * @return Course|null
     */
    public static function find(int $course_id): ?Course
    {
        $course = new self($course_id);
        return $course->exists() ? $course : null;
    }
    
    /**
     * Find course by slug
     * 
     * @param string $slug
     * @return Course|null
     */
    public static function findBySlug(string $slug): ?Course
    {
        $post = get_page_by_path($slug, OBJECT, PostTypes::COURSE);
        if (!$post) {
            return null;
        }
        
        $course = new self($post);
        return $course->exists() ? $course : null;
    }
    
    /**
     * Get all courses
     * 
     * @param array $args
     * @return Course[]
     */
    public static function all(array $args = []): array
    {
        $default_args = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $default_args);
        $posts = get_posts($args);
        
        $courses = [];
        foreach ($posts as $post) {
            $courses[] = new self($post);
        }
        
        return $courses;
    }
    
    /**
     * Get published courses
     * 
     * @param array $args
     * @return Course[]
     */
    public static function published(array $args = []): array
    {
        $args['post_status'] = 'publish';
        return self::all($args);
    }
    
    /**
     * Get draft courses
     * 
     * @param array $args
     * @return Course[]
     */
    public static function drafts(array $args = []): array
    {
        $args['post_status'] = 'draft';
        return self::all($args);
    }
    
    /**
     * Count courses
     * 
     * @param array $args
     * @return int
     */
    public static function count(array $args = []): int
    {
        $default_args = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'any',
        ];
        
        $args = wp_parse_args($args, $default_args);
        $query = new \WP_Query($args);
        
        return $query->found_posts;
    }

    /**
     * Delete course
     * 
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->post) {
            return false;
        }
        
        $result = wp_delete_post($this->id, true);
        if ($result) {
            $this->post = null;
            $this->meta = [];
        }
        
        return $result !== false;
    }
    
    /**
     * Update course
     * 
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        if (!$this->post) {
            return false;
        }
        
        $post_data = [
            'ID' => $this->id,
        ];
        
        if (isset($data['title'])) {
            $post_data['post_title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['description'])) {
            $post_data['post_content'] = wp_kses_post($data['description']);
        }
        
        if (isset($data['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($data['excerpt']);
        }
        
        if (isset($data['slug'])) {
            $post_data['post_name'] = sanitize_title($data['slug']);
        }
        
        if (isset($data['status'])) {
            $post_data['post_status'] = sanitize_text_field($data['status']);
        }
        
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return false;
        }
        
        // Update meta fields
        if (isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                $this->setMeta($key, $value);
            }
        }
        
        // Reload the post object
        $this->post = get_post($this->id);
        $this->loadMeta();
        
        return true;
    }
} 