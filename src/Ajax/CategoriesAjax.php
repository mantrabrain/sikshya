<?php

namespace Sikshya\Ajax;

use Sikshya\Constants\Taxonomies;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Categories AJAX Handler
 *
 * @package Sikshya\Ajax
 */
class CategoriesAjax
{
    /**
     * Initialize AJAX handlers
     *
     * @param bool $register_hooks When false, REST-only mode (no wp_ajax_*).
     */
    public function __construct(bool $register_hooks = true)
    {
        if ($register_hooks && (!defined('SIKSHYA_LEGACY_AJAX') || SIKSHYA_LEGACY_AJAX)) {
            $this->initHooks();
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        add_action('wp_ajax_sikshya_save_category', [$this, 'handleSaveCategory']);
        add_action('wp_ajax_sikshya_delete_category', [$this, 'handleDeleteCategory']);
    }

    /**
     * Handle save category AJAX request
     */
    public function handleSaveCategory(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_admin_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        $term_id = intval($_POST['term_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $slug = sanitize_title($_POST['slug'] ?? '');
        $parent = intval($_POST['parent'] ?? 0);
        $image_id = intval($_POST['image'] ?? 0);

        // Validate required fields
        if (empty($name)) {
            wp_send_json_error(['message' => 'Category name is required']);
            return;
        }

        // Prepare term data
        $term_data = [
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
            'parent' => $parent
        ];

        try {
            if ($term_id > 0) {
                // Update existing category
                $result = wp_update_term($term_id, Taxonomies::COURSE_CATEGORY, $term_data);

                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                    return;
                }

                $term_id = $result['term_id'];
            } else {
                // Create new category
                $result = wp_insert_term($name, Taxonomies::COURSE_CATEGORY, $term_data);

                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                    return;
                }

                $term_id = $result['term_id'];
            }

            // Save image meta
            if ($image_id > 0) {
                update_term_meta($term_id, 'category_image', $image_id);
            } else {
                delete_term_meta($term_id, 'category_image');
            }

            // Get updated category data
            $category = get_term($term_id, Taxonomies::COURSE_CATEGORY);

            if (is_wp_error($category)) {
                wp_send_json_error(['message' => 'Failed to retrieve category data']);
                return;
            }

            wp_send_json_success([
                'message' => $term_id > 0 ? 'Category updated successfully' : 'Category created successfully',
                'category' => [
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent' => $category->parent,
                    'image_id' => get_term_meta($category->term_id, 'category_image', true)
                ]
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle delete category AJAX request
     */
    public function handleDeleteCategory(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_admin_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        $term_id = intval($_POST['term_id'] ?? 0);

        if ($term_id <= 0) {
            wp_send_json_error(['message' => 'Invalid category ID']);
            return;
        }

        try {
            // Get category name for response
            $category = get_term($term_id, Taxonomies::COURSE_CATEGORY);
            $category_name = $category && !is_wp_error($category) ? $category->name : 'Unknown';

            // Delete the category
            $result = wp_delete_term($term_id, Taxonomies::COURSE_CATEGORY);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
                return;
            }

            wp_send_json_success([
                'message' => "Category '{$category_name}' deleted successfully"
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
}
