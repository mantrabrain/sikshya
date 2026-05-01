<?php
/**
 * Course Catalog Template
 *
 * @package Sikshya
 */

use Sikshya\Constants\PostTypes;

sikshya_get_header(); ?>

<?php
$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
$label_instructors = function_exists('sikshya_label_plural') ? sikshya_label_plural('instructor', 'instructors', __('Instructors', 'sikshya'), 'frontend') : __('Instructors', 'sikshya');
$label_students = function_exists('sikshya_label_plural') ? sikshya_label_plural('student', 'students', __('Students', 'sikshya'), 'frontend') : __('Students', 'sikshya');
?>

<div class="sikshya-public sikshya-course-catalog">
    <!-- Hero Section -->
    <section class="sikshya-hero">
        <div class="sikshya-container">
            <div class="sikshya-hero-content">
                <h1 class="sikshya-hero-title"><?php _e('Discover Amazing Courses', 'sikshya'); ?></h1>
                <p class="sikshya-hero-subtitle">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: plural label (e.g. instructors) */
                        __('Learn from the best %s and advance your skills', 'sikshya'),
                        strtolower($label_instructors)
                    ));
                    ?>
                </p>
                
                <!-- Search Form -->
                <form class="sikshya-search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <input type="hidden" name="post_type" value="<?php echo esc_attr(PostTypes::COURSE); ?>">
                    <div class="sikshya-search-input-group">
                        <input type="text" name="s" placeholder="<?php echo esc_attr(sprintf(__('Search %s…', 'sikshya'), strtolower($label_courses))); ?>" value="<?php echo esc_attr(get_search_query()); ?>" class="sikshya-search-input">
                        <button type="submit" class="sikshya-search-button">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <?php if (!empty($featured_courses)): ?>
    <section class="sikshya-featured-courses">
        <div class="sikshya-container">
            <div class="sikshya-section-header">
                <h2 class="sikshya-section-title"><?php echo esc_html(sprintf(__('Featured %s', 'sikshya'), $label_courses)); ?></h2>
                <p class="sikshya-section-subtitle">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: plural label (e.g. courses), 2: plural label (e.g. instructors) */
                        __('Handpicked %1$s from our top %2$s', 'sikshya'),
                        strtolower($label_courses),
                        strtolower($label_instructors)
                    ));
                    ?>
                </p>
            </div>
            
            <div class="sikshya-course-grid sikshya-course-grid--featured">
                <?php foreach ($featured_courses as $course) : ?>
                    <?php sikshya_render_course_card($course, 'featured'); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="sikshya-container">
        <div class="sikshya-catalog-layout">
            <!-- Sidebar Filters -->
            <aside class="sikshya-sidebar">
                <div class="sikshya-filters">
                    <h3 class="sikshya-filters-title"><?php _e('Filters', 'sikshya'); ?></h3>
                    
                    <!-- Category Filter -->
                    <div class="sikshya-filter-group">
                        <h4 class="sikshya-filter-label"><?php _e('Categories', 'sikshya'); ?></h4>
                        <?php
                        $categories = get_terms([
                            'taxonomy' => 'sikshya_course_category',
                            'hide_empty' => true,
                        ]);
                        if ($categories && !is_wp_error($categories)):
                        ?>
                        <div class="sikshya-filter-options">
                            <?php foreach ($categories as $category): ?>
                                <label class="sikshya-filter-option">
                                    <input type="checkbox" name="category[]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, (array) $_GET['category'] ?? [])); ?>>
                                    <span class="sikshya-checkbox-custom"></span>
                                    <span class="sikshya-filter-text"><?php echo esc_html($category->name); ?></span>
                                    <span class="sikshya-filter-count">(<?php echo $category->count; ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Level Filter -->
                    <div class="sikshya-filter-group">
                        <h4 class="sikshya-filter-label"><?php _e('Level', 'sikshya'); ?></h4>
                        <div class="sikshya-filter-options">
                            <?php
                            $levels = ['beginner', 'intermediate', 'advanced'];
                            foreach ($levels as $level):
                            ?>
                                <label class="sikshya-filter-option">
                                    <input type="checkbox" name="level[]" value="<?php echo esc_attr($level); ?>" <?php checked(in_array($level, (array) $_GET['level'] ?? [])); ?>>
                                    <span class="sikshya-checkbox-custom"></span>
                                    <span class="sikshya-filter-text"><?php echo esc_html(ucfirst($level)); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Filter -->
                    <div class="sikshya-filter-group">
                        <h4 class="sikshya-filter-label"><?php _e('Price', 'sikshya'); ?></h4>
                        <div class="sikshya-filter-options">
                            <label class="sikshya-filter-option">
                                <input type="radio" name="price" value="free" <?php checked($_GET['price'] ?? '', 'free'); ?>>
                                <span class="sikshya-radio-custom"></span>
                                <span class="sikshya-filter-text"><?php _e('Free', 'sikshya'); ?></span>
                            </label>
                            <label class="sikshya-filter-option">
                                <input type="radio" name="price" value="paid" <?php checked($_GET['price'] ?? '', 'paid'); ?>>
                                <span class="sikshya-radio-custom"></span>
                                <span class="sikshya-filter-text"><?php _e('Paid', 'sikshya'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Apply Filters Button -->
                    <button type="button" class="sikshya-apply-filters" id="applyFilters">
                        <?php _e('Apply Filters', 'sikshya'); ?>
                    </button>
                </div>
            </aside>

            <!-- Main Course Grid -->
            <main class="sikshya-main-content">
                <!-- Sort Options -->
                <div class="sikshya-sort-bar">
                    <div class="sikshya-sort-left">
                        <span class="sikshya-results-count">
                            <?php
                            $n = count($courses);
                            echo esc_html(sprintf(
                                _n('%1$s %2$s found', '%1$s %3$s found', $n, 'sikshya'),
                                number_format_i18n($n),
                                strtolower($label_course),
                                strtolower($label_courses)
                            ));
                            ?>
                        </span>
                    </div>
                    
                    <div class="sikshya-sort-right">
                        <label for="sortCourses" class="sikshya-sort-label"><?php _e('Sort by:', 'sikshya'); ?></label>
                        <select id="sortCourses" class="sikshya-sort-select">
                            <option value="date"><?php _e('Latest', 'sikshya'); ?></option>
                            <option value="title"><?php _e('Title', 'sikshya'); ?></option>
                            <option value="popularity"><?php _e('Popularity', 'sikshya'); ?></option>
                            <option value="rating"><?php _e('Rating', 'sikshya'); ?></option>
                            <option value="price"><?php _e('Price', 'sikshya'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Course Grid -->
                <?php if (!empty($courses)): ?>
                    <div class="sikshya-course-grid">
                        <?php foreach ($courses as $course) : ?>
                            <?php sikshya_render_course_card($course); ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="sikshya-pagination">
                            <?php if ($pagination['has_previous']): ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $pagination['previous_page'])); ?>" class="sikshya-pagination-prev">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="15,18 9,12 15,6"></polyline>
                                    </svg>
                                    <?php _e('Previous', 'sikshya'); ?>
                                </a>
                            <?php endif; ?>

                            <div class="sikshya-pagination-numbers">
                                <?php
                                $start_page = max(1, $pagination['current_page'] - 2);
                                $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="<?php echo esc_url(add_query_arg('paged', $i)); ?>" class="sikshya-pagination-number <?php echo $i === $pagination['current_page'] ? 'sikshya-pagination-current' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>

                            <?php if ($pagination['has_next']): ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $pagination['next_page'])); ?>" class="sikshya-pagination-next">
                                    <?php _e('Next', 'sikshya'); ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9,18 15,12 9,6"></polyline>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sikshya-no-courses">
                        <div class="sikshya-no-courses-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10,9 9,9 8,9"></polyline>
                            </svg>
                        </div>
                        <h3 class="sikshya-no-courses-title"><?php echo esc_html(sprintf(__('No %s found', 'sikshya'), strtolower($label_courses))); ?></h3>
                        <p class="sikshya-no-courses-text"><?php _e('Try adjusting your search criteria or browse our featured courses.', 'sikshya'); ?></p>
                        <a href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/')); ?>" class="sikshya-button sikshya-button--primary">
                            <?php echo esc_html(sprintf(__('Browse all %s', 'sikshya'), strtolower($label_courses))); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Popular Courses Section -->
    <?php if (!empty($popular_courses)): ?>
    <section class="sikshya-popular-courses">
        <div class="sikshya-container">
            <div class="sikshya-section-header">
                <h2 class="sikshya-section-title"><?php echo esc_html(sprintf(__('Popular %s', 'sikshya'), $label_courses)); ?></h2>
                <p class="sikshya-section-subtitle">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: plural label (e.g. courses), 2: plural label (e.g. students) */
                        __('Most enrolled %1$s by our %2$s', 'sikshya'),
                        strtolower($label_courses),
                        strtolower($label_students)
                    ));
                    ?>
                </p>
            </div>
            
            <div class="sikshya-course-grid sikshya-course-grid--popular">
                <?php foreach ($popular_courses as $course) : ?>
                    <?php sikshya_render_course_card($course, 'popular'); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php sikshya_get_footer(); ?>
