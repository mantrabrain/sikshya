<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;
use Sikshya\Constants\Taxonomies;
use Sikshya\Constants\PostTypes;

/**
 * Course Categories Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class CourseCategoriesController
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Render course categories page
     */
    public function renderCourseCategoriesPage(): void
    {
        if (!current_user_can('manage_categories')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        ReactAdminView::render('course-categories', []);
    }

    /**
     * Handle AJAX requests for category management
     */
    public function handleAjaxRequest(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sikshya_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'sikshya')]);
        }

        if (!current_user_can('manage_categories')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'sikshya')]);
        }

        $action = $_POST['sub_action'] ?? '';
        $method = 'handle' . ucfirst($action);

        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            wp_send_json_error(['message' => __('Invalid action', 'sikshya')]);
        }
    }

    /**
     * Handle create category
     */
    private function handleCreateCategory(): void
    {
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $slug = sanitize_title($_POST['slug'] ?? $name);

        if (empty($name)) {
            wp_send_json_error(['message' => __('Category name is required', 'sikshya')]);
        }

        $result = wp_insert_term($name, Taxonomies::COURSE_CATEGORY, [
            'description' => $description,
            'slug' => $slug,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Category created successfully', 'sikshya'),
            'category' => get_term($result['term_id'], Taxonomies::COURSE_CATEGORY),
        ]);
    }

    /**
     * Handle update category
     */
    private function handleUpdateCategory(): void
    {
        $term_id = intval($_POST['term_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $slug = sanitize_title($_POST['slug'] ?? $name);

        if (empty($term_id) || empty($name)) {
            wp_send_json_error(['message' => __('Category ID and name are required', 'sikshya')]);
        }

        $result = wp_update_term($term_id, Taxonomies::COURSE_CATEGORY, [
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Category updated successfully', 'sikshya'),
            'category' => get_term($term_id, Taxonomies::COURSE_CATEGORY),
        ]);
    }

    /**
     * Handle delete category
     */
    private function handleDeleteCategory(): void
    {
        $term_id = intval($_POST['term_id'] ?? 0);

        if (empty($term_id)) {
            wp_send_json_error(['message' => __('Category ID is required', 'sikshya')]);
        }

        $result = wp_delete_term($term_id, Taxonomies::COURSE_CATEGORY);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Category deleted successfully', 'sikshya'),
        ]);
    }
}
