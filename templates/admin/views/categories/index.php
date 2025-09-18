<?php
/**
 * Course Categories Management Page
 *
 * @package Sikshya
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use Sikshya\Constants\Taxonomies;

$categories = $data['categories'] ?? [];
$category_stats = $data['category_stats'] ?? [];
$taxonomy = $data['taxonomy'] ?? '';
$post_type = $data['post_type'] ?? '';
?>

<div class="sikshya-dashboard">
    <!-- Header -->
    <div class="sikshya-header">
        <div class="sikshya-header-title">
            <h1>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
                <?php echo esc_html($title); ?>
            </h1>
            <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="sikshya-main-content">
        <div class="sikshya-categories-layout">
            <!-- Left Sidebar - Add/Edit Form -->
            <div class="sikshya-categories-sidebar">
                <div class="sikshya-sidebar-card">
                    <div class="sikshya-sidebar-header">
                        <h2 id="sikshya-form-title"><?php _e('Add New Category', 'sikshya'); ?></h2>
                    </div>
                    
                    <div class="sikshya-sidebar-body">
                        <form id="sikshya-category-form">
                            <input type="hidden" id="sikshya-category-term-id" name="term_id" value="">
                            
                            <div class="sikshya-form-group">
                                <label for="sikshya-category-name" class="sikshya-form-label">
                                    <?php _e('Name', 'sikshya'); ?> <span class="sikshya-required">*</span>
                                </label>
                                <input type="text" id="sikshya-category-name" name="name" class="sikshya-form-input" required>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label for="sikshya-category-slug" class="sikshya-form-label">
                                    <?php _e('Slug', 'sikshya'); ?>
                                </label>
                                <input type="text" id="sikshya-category-slug" name="slug" class="sikshya-form-input">
                                <small class="sikshya-form-help">
                                    <?php _e('Leave empty to auto-generate from name', 'sikshya'); ?>
                                </small>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label for="sikshya-category-parent" class="sikshya-form-label">
                                    <?php _e('Parent Category', 'sikshya'); ?>
                                </label>
                                <select id="sikshya-category-parent" name="parent" class="sikshya-form-select">
                                    <option value="0"><?php _e('None', 'sikshya'); ?></option>
                                    <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo esc_attr($category->term_id); ?>">
                                                <?php echo esc_html($category->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label for="sikshya-category-description" class="sikshya-form-label">
                                    <?php _e('Description', 'sikshya'); ?>
                                </label>
                                <textarea id="sikshya-category-description" name="description" class="sikshya-form-textarea" rows="4"></textarea>
                            </div>
                            
                            <div class="sikshya-form-group">
                                <label for="sikshya-category-image" class="sikshya-form-label">
                                    <?php _e('Feature Image', 'sikshya'); ?>
                                </label>
                                <div class="sikshya-upload-area" id="sikshya-category-image-upload">
                                    <div class="sikshya-upload-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <strong><?php _e('Select Image', 'sikshya'); ?></strong>
                                    <small><?php _e('Click to open WordPress Media Library', 'sikshya'); ?></small>
                                    <input type="hidden" id="sikshya-category-image" name="image" value="">
                                </div>
                                <div class="sikshya-image-preview" id="sikshya-category-image-preview" style="display: none;">
                                    <img id="sikshya-category-image-preview-img" src="" alt="">
                                    <button type="button" class="sikshya-remove-image" id="sikshya-remove-category-image">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="sikshya-form-actions">
                                <button type="button" class="sikshya-btn sikshya-btn-primary sikshya-btn-header-style" id="sikshya-save-category">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    <span id="sikshya-save-btn-text"><?php _e('Add Category', 'sikshya'); ?></span>
                                </button>
                                <button type="button" class="sikshya-btn sikshya-btn-secondary" id="sikshya-cancel-edit" style="display: none;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                    <?php _e('Cancel', 'sikshya'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Content - Categories Table -->
            <div class="sikshya-categories-main">
                <div class="sikshya-categories-table-card">
                    <div class="sikshya-table-header">
                        <h2><?php _e('Categories', 'sikshya'); ?></h2>
                        <div class="sikshya-table-actions">
                            <span class="sikshya-table-count">
                                <?php 
                                $total_categories = is_wp_error($categories) ? 0 : count($categories);
                                printf(
                                    _n('%d category', '%d categories', $total_categories, 'sikshya'),
                                    $total_categories
                                );
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="sikshya-table-content">
                        <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                            <div class="sikshya-categories-table">
                                <table class="sikshya-table">
                                    <thead>
                                        <tr>
                                            <th class="sikshya-table-col-image"><?php _e('Image', 'sikshya'); ?></th>
                                            <th class="sikshya-table-col-name"><?php _e('Name', 'sikshya'); ?></th>
                                            <th class="sikshya-table-col-description"><?php _e('Description', 'sikshya'); ?></th>
                                            <th class="sikshya-table-col-count"><?php _e('Courses', 'sikshya'); ?></th>
                                            <th class="sikshya-table-col-actions"><?php _e('Actions', 'sikshya'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <?php 
                                            $category_image_id = get_term_meta($category->term_id, 'category_image', true);
                                            $category_image_url = '';
                                            if (!empty($category_image_id)) {
                                                $category_image_url = wp_get_attachment_image_url($category_image_id, 'thumbnail');
                                            }
                                            $category_parent = get_term_meta($category->term_id, 'parent', true);
                                            ?>
                                            <tr class="sikshya-table-row" data-category-id="<?php echo esc_attr($category->term_id); ?>">
                                                <td class="sikshya-table-col-image">
                                                    <div class="sikshya-category-image">
                                                        <?php if (!empty($category_image_url)): ?>
                                                            <img src="<?php echo esc_url($category_image_url); ?>" alt="<?php echo esc_attr($category->name); ?>" class="sikshya-category-thumbnail">
                                                        <?php else: ?>
                                                            <div class="sikshya-category-no-image">
                                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                                    <polyline points="21,15 16,10 5,21"></polyline>
                                                                </svg>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="sikshya-table-col-name">
                                                    <div class="sikshya-category-name-cell">
                                                        <strong><?php echo esc_html($category->name); ?></strong>
                                                        <span class="sikshya-category-slug"><?php echo esc_html($category->slug); ?></span>
                                                        <?php if (!empty($category_parent) && $category_parent != 0): ?>
                                                            <?php $parent_category = get_term($category_parent, Taxonomies::COURSE_CATEGORY); ?>
                                                            <?php if ($parent_category && !is_wp_error($parent_category)): ?>
                                                                <span class="sikshya-category-parent"><?php printf(__('Child of: %s', 'sikshya'), esc_html($parent_category->name)); ?></span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="sikshya-table-col-description">
                                                    <?php if (!empty($category->description)): ?>
                                                        <span class="sikshya-category-description"><?php echo esc_html($category->description); ?></span>
                                                    <?php else: ?>
                                                        <span class="sikshya-category-description sikshya-no-description">
                                                            <?php _e('No description', 'sikshya'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="sikshya-table-col-count">
                                                    <span class="sikshya-course-count">
                                                        <?php 
                                                        $course_count = $category_stats[$category->term_id]['course_count'] ?? 0;
                                                        echo esc_html($course_count);
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="sikshya-table-col-actions">
                                                    <div class="sikshya-table-actions">
                                                        <a href="<?php echo get_term_link($category); ?>" class="sikshya-btn sikshya-btn-sm sikshya-btn-secondary" 
                                                           target="_blank" title="<?php _e('View Category', 'sikshya'); ?>">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                                <circle cx="12" cy="12" r="3"></circle>
                                                            </svg>
                                                        </a>
                                                        <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-secondary sikshya-edit-category" 
                                                                data-category-id="<?php echo esc_attr($category->term_id); ?>"
                                                                data-category-name="<?php echo esc_attr($category->name); ?>"
                                                                data-category-description="<?php echo esc_attr($category->description); ?>"
                                                                data-category-slug="<?php echo esc_attr($category->slug); ?>"
                                                                data-category-parent="<?php echo esc_attr($category->parent); ?>"
                                                                data-category-image="<?php echo esc_attr($category_image_id); ?>"
                                                                title="<?php _e('Edit Category', 'sikshya'); ?>">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                            </svg>
                                                        </button>
                                                        <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-danger sikshya-delete-category" 
                                                                data-category-id="<?php echo esc_attr($category->term_id); ?>"
                                                                data-category-name="<?php echo esc_attr($category->name); ?>"
                                                                title="<?php _e('Delete Category', 'sikshya'); ?>">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polyline points="3,6 5,6 21,6"></polyline>
                                                                <path d="M19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="sikshya-empty-state">
                                <div class="sikshya-empty-state-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                    </svg>
                                </div>
                                <h3><?php _e('No categories found', 'sikshya'); ?></h3>
                                <p><?php _e('Create your first course category to organize your courses', 'sikshya'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
