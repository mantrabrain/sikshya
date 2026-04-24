<?php
/**
 * Template for displaying courses in a grid layout
 *
 * Data is prepared in {@see \Sikshya\Frontend\Public\CoursesGridTemplateData}; this file is markup-only.
 *
 * @package Sikshya
 * @since 1.0.0
 */

use Sikshya\Frontend\Public\CoursesGridTemplateData;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$vm = CoursesGridTemplateData::forBrowseGrid();
$courses_query = $vm['courses_query'];
$filter_categories = $vm['filter_categories'];
?>

<div class="sikshya-container">
    
    <!-- Header -->
    <div class="sikshya-courses-header">
        <h1 class="sikshya-heading sikshya-heading--h1"><?php _e('Browse Courses', 'sikshya'); ?></h1>
        <p class="sikshya-courses-subtitle"><?php _e('Discover amazing courses from expert instructors', 'sikshya'); ?></p>
    </div>

    <!-- Filters and Search -->
    <div class="sikshya-courses-filters">
        <div class="sikshya-search-wrapper">
            <input type="text" class="sikshya-form__input sikshya-search" placeholder="<?php _e('Search courses...', 'sikshya'); ?>">
        </div>
        
        <div class="sikshya-filter-wrapper">
            <select class="sikshya-form__select sikshya-filter">
                <option value="all"><?php _e('All Categories', 'sikshya'); ?></option>
                <?php foreach ($filter_categories as $category) : ?>
                    <option value="<?php echo esc_attr($category->slug); ?>"><?php echo esc_html($category->name); ?></option>
                <?php endforeach; ?>
            </select>
            
            <select class="sikshya-form__select sikshya-filter">
                <option value="all"><?php _e('All Difficulties', 'sikshya'); ?></option>
                <option value="beginner"><?php _e('Beginner', 'sikshya'); ?></option>
                <option value="intermediate"><?php _e('Intermediate', 'sikshya'); ?></option>
                <option value="advanced"><?php _e('Advanced', 'sikshya'); ?></option>
            </select>
        </div>
    </div>

    <!-- Courses Grid -->
    <?php if ($courses_query->have_posts()) : ?>
        <div class="sikshya-courses-grid">
            <?php
            while ($courses_query->have_posts()) :
                $courses_query->the_post();
                sikshya_render_course_card(get_post(), 'default');
            endwhile;
            ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($courses_query->max_num_pages > 1) : ?>
            <div class="sikshya-pagination">
                <?php
                echo paginate_links([
                    'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                    'format' => '?paged=%#%',
                    'current' => max(1, (int) get_query_var('paged')),
                    'total' => $courses_query->max_num_pages,
                    'prev_text' => __('&laquo; Previous', 'sikshya'),
                    'next_text' => __('Next &raquo;', 'sikshya'),
                    'type' => 'list',
                ]);
                ?>
            </div>
        <?php endif; ?>
        
    <?php else : ?>
        <!-- No Courses Found -->
        <div class="sikshya-no-courses">
            <div class="sikshya-no-courses__icon">
                <span class="dashicons dashicons-book"></span>
            </div>
            <h3><?php _e('No Courses Found', 'sikshya'); ?></h3>
            <p><?php _e('Sorry, no courses match your criteria. Please try adjusting your filters.', 'sikshya'); ?></p>
            <a href="<?php echo esc_url(remove_query_arg(['category', 'difficulty', 'search'])); ?>" 
               class="sikshya-btn sikshya-btn--primary">
                <?php _e('View All Courses', 'sikshya'); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <?php wp_reset_postdata(); ?>
    
</div>
