<?php
/**
 * Template for displaying courses in a grid layout
 *
 * Data is prepared in {@see \Sikshya\Frontend\Public\CoursesGridTemplateData}; this file is markup-only.
 *
 * @package Sikshya
 * @since 1.0.0
 */

use Sikshya\Services\Frontend\CoursesGridPageService;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$page_model = CoursesGridPageService::forBrowseGrid();
$courses_query = $page_model->getCoursesQuery();
$filter_categories = $page_model->getFilterCategories();

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
?>

<div class="sikshya-public sikshya-courses-browse">
<div class="sikshya-container">
    
    <!-- Header -->
    <div class="sikshya-courses-header">
        <h1 class="sikshya-heading sikshya-heading--h1">
            <?php
            echo esc_html(sprintf(
                /* translators: %s: plural label (e.g. Courses) */
                __('Browse %s', 'sikshya'),
                $label_courses
            ));
            ?>
        </h1>
        <p class="sikshya-courses-subtitle">
            <?php
            echo esc_html(sprintf(
                /* translators: 1: plural label (e.g. courses), 2: plural label (e.g. instructors) */
                __('Discover amazing %1$s from expert %2$s', 'sikshya'),
                strtolower($label_courses),
                strtolower(function_exists('sikshya_label_plural') ? sikshya_label_plural('instructor', 'instructors', __('Instructors', 'sikshya'), 'frontend') : __('Instructors', 'sikshya'))
            ));
            ?>
        </p>
    </div>

    <!-- Filters and Search -->
    <div class="sikshya-courses-filters">
        <div class="sikshya-search-wrapper">
            <input type="text" class="sikshya-form__input sikshya-search" placeholder="<?php echo esc_attr(sprintf(__('Search %s…', 'sikshya'), strtolower($label_courses))); ?>">
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
            <h3>
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: plural label (e.g. Courses) */
                    __('No %s found', 'sikshya'),
                    $label_courses
                ));
                ?>
            </h3>
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: plural label (e.g. courses) */
                    __('Sorry, no %s match your criteria. Please try adjusting your filters.', 'sikshya'),
                    strtolower($label_courses)
                ));
                ?>
            </p>
            <a href="<?php echo esc_url(remove_query_arg(['category', 'difficulty', 'search'])); ?>" 
               class="sikshya-btn sikshya-btn--primary">
                <?php echo esc_html(sprintf(__('View all %s', 'sikshya'), strtolower($label_courses))); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <?php wp_reset_postdata(); ?>

</div>
</div>
