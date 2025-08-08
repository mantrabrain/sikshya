<?php
/**
 * Course Catalog Template
 *
 * @package Sikshya
 */

get_header(); ?>

<div class="sikshya-course-catalog">
    <!-- Hero Section -->
    <section class="sikshya-hero">
        <div class="sikshya-container">
            <div class="sikshya-hero-content">
                <h1 class="sikshya-hero-title"><?php _e('Discover Amazing Courses', 'sikshya'); ?></h1>
                <p class="sikshya-hero-subtitle"><?php _e('Learn from the best instructors and advance your skills', 'sikshya'); ?></p>
                
                <!-- Search Form -->
                <form class="sikshya-search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <input type="hidden" name="post_type" value="sikshya_course">
                    <div class="sikshya-search-input-group">
                        <input type="text" name="s" placeholder="<?php _e('Search courses...', 'sikshya'); ?>" value="<?php echo esc_attr(get_search_query()); ?>" class="sikshya-search-input">
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
                <h2 class="sikshya-section-title"><?php _e('Featured Courses', 'sikshya'); ?></h2>
                <p class="sikshya-section-subtitle"><?php _e('Handpicked courses from our top instructors', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-course-grid sikshya-course-grid--featured">
                <?php foreach ($featured_courses as $course): ?>
                    <?php $this->renderCourseCard($course, 'featured'); ?>
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
                            <?php printf(_n('%s course found', '%s courses found', count($courses), 'sikshya'), number_format_i18n(count($courses))); ?>
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
                        <?php foreach ($courses as $course): ?>
                            <?php $this->renderCourseCard($course); ?>
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
                        <h3 class="sikshya-no-courses-title"><?php _e('No courses found', 'sikshya'); ?></h3>
                        <p class="sikshya-no-courses-text"><?php _e('Try adjusting your search criteria or browse our featured courses.', 'sikshya'); ?></p>
                        <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="sikshya-button sikshya-button--primary">
                            <?php _e('Browse All Courses', 'sikshya'); ?>
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
                <h2 class="sikshya-section-title"><?php _e('Popular Courses', 'sikshya'); ?></h2>
                <p class="sikshya-section-subtitle"><?php _e('Most enrolled courses by our students', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-course-grid sikshya-course-grid--popular">
                <?php foreach ($popular_courses as $course): ?>
                    <?php $this->renderCourseCard($course, 'popular'); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php
/**
 * Render course card
 */
function renderCourseCard($course, $type = 'default') {
    $course_id = $course->ID;
    $course_price = get_post_meta($course_id, '_sikshya_price', true);
    $course_sale_price = get_post_meta($course_id, '_sikshya_sale_price', true);
    $course_duration = get_post_meta($course_id, '_sikshya_duration', true);
    $course_difficulty = get_post_meta($course_id, '_sikshya_difficulty', true);
    $course_instructor = get_userdata($course->post_author);
    $course_thumbnail = get_the_post_thumbnail_url($course_id, 'medium');
    $course_categories = get_the_terms($course_id, 'sikshya_course_category');
    ?>
    
    <article class="sikshya-course-card sikshya-course-card--<?php echo esc_attr($type); ?>">
        <div class="sikshya-course-card-image">
            <?php if ($course_thumbnail): ?>
                <img src="<?php echo esc_url($course_thumbnail); ?>" alt="<?php echo esc_attr($course->post_title); ?>" class="sikshya-course-thumbnail">
            <?php else: ?>
                <div class="sikshya-course-placeholder">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14,2 14,8 20,8"></polyline>
                    </svg>
                </div>
            <?php endif; ?>
            
            <?php if ($course_sale_price && $course_sale_price < $course_price): ?>
                <div class="sikshya-course-badge sikshya-course-badge--sale">
                    <?php _e('Sale', 'sikshya'); ?>
                </div>
            <?php endif; ?>
            
            <?php if (get_post_meta($course_id, '_sikshya_featured', true)): ?>
                <div class="sikshya-course-badge sikshya-course-badge--featured">
                    <?php _e('Featured', 'sikshya'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sikshya-course-card-content">
            <?php if ($course_categories && !is_wp_error($course_categories)): ?>
                <div class="sikshya-course-categories">
                    <?php foreach (array_slice($course_categories, 0, 2) as $category): ?>
                        <span class="sikshya-course-category"><?php echo esc_html($category->name); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3 class="sikshya-course-title">
                <a href="<?php echo esc_url(get_permalink($course_id)); ?>">
                    <?php echo esc_html($course->post_title); ?>
                </a>
            </h3>

            <p class="sikshya-course-excerpt">
                <?php echo esc_html(wp_trim_words($course->post_excerpt ?: $course->post_content, 15)); ?>
            </p>

            <div class="sikshya-course-meta">
                <?php if ($course_instructor): ?>
                    <div class="sikshya-course-instructor">
                        <span class="sikshya-course-instructor-label"><?php _e('By', 'sikshya'); ?></span>
                        <span class="sikshya-course-instructor-name"><?php echo esc_html($course_instructor->display_name); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($course_duration): ?>
                    <div class="sikshya-course-duration">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12,6 12,12 16,14"></polyline>
                        </svg>
                        <span><?php echo esc_html($course_duration); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($course_difficulty): ?>
                    <div class="sikshya-course-difficulty">
                        <span class="sikshya-difficulty-badge sikshya-difficulty-badge--<?php echo esc_attr($course_difficulty); ?>">
                            <?php echo esc_html(ucfirst($course_difficulty)); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sikshya-course-footer">
                <div class="sikshya-course-price">
                    <?php if ($course_sale_price && $course_sale_price < $course_price): ?>
                        <span class="sikshya-course-price-original"><?php echo esc_html(sikshya_format_price($course_price)); ?></span>
                        <span class="sikshya-course-price-current"><?php echo esc_html(sikshya_format_price($course_sale_price)); ?></span>
                    <?php elseif ($course_price): ?>
                        <span class="sikshya-course-price-current"><?php echo esc_html(sikshya_format_price($course_price)); ?></span>
                    <?php else: ?>
                        <span class="sikshya-course-price-free"><?php _e('Free', 'sikshya'); ?></span>
                    <?php endif; ?>
                </div>

                <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--small">
                    <?php _e('View Course', 'sikshya'); ?>
                </a>
            </div>
        </div>
    </article>
    <?php
}
?>

<?php get_footer(); ?> 