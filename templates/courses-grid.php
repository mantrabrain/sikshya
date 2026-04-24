<?php
/**
 * Template for displaying courses in a grid layout
 *
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get courses
$args = array(
    'post_type' => PostTypes::COURSE,
    'post_status' => 'publish',
    'posts_per_page' => 12,
    'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
);

$courses_query = new WP_Query($args);
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
                <?php
                $categories = get_terms(array(
                    'taxonomy' => 'sikshya_course_category',
                    'hide_empty' => true,
                ));
                
                foreach ($categories as $category) {
                    echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                }
                ?>
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
            <?php while ($courses_query->have_posts()) : $courses_query->the_post(); ?>
                <?php
                $course_id = get_the_ID();
                $pricing = sikshya_get_course_pricing($course_id);
                $price = $pricing['price'];
                $sale_price = $pricing['sale_price'];
                $course_currency = $pricing['currency'];
                $duration = sikshya_first_nonempty_post_meta($course_id, ['_sikshya_duration', '_sikshya_course_duration', 'sikshya_course_duration']);
                $difficulty = sikshya_first_nonempty_post_meta($course_id, ['_sikshya_difficulty', '_sikshya_course_difficulty', 'sikshya_course_level']);
                $featured = get_post_meta($course_id, '_sikshya_featured', true);
                $enrollment_count = get_post_meta($course_id, '_sikshya_enrollment_count', true) ?: 0;
                $rating = get_post_meta($course_id, '_sikshya_rating', true) ?: 0;
                
                $categories = wp_get_post_terms($course_id, 'sikshya_course_category', array('fields' => 'names'));
                $category_slugs = wp_get_post_terms($course_id, 'sikshya_course_category', array('fields' => 'slugs'));
                ?>
                
                <div class="sikshya-card sikshya-course-card" data-category="<?php echo esc_attr(implode(' ', $category_slugs)); ?>">
                    
                    <!-- Course Image -->
                    <?php if (has_post_thumbnail()) : ?>
                        <img src="<?php echo get_the_post_thumbnail_url($course_id, 'medium'); ?>" 
                             alt="<?php echo esc_attr(get_the_title()); ?>" 
                             class="sikshya-course-card__image">
                    <?php else : ?>
                        <div class="sikshya-course-card__image sikshya-course-card__image--placeholder">
                            <span><?php _e('No Image', 'sikshya'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Course Badge -->
                    <?php if ($featured) : ?>
                        <span class="sikshya-course-card__badge sikshya-course-card__badge--featured">
                            <?php _e('Featured', 'sikshya'); ?>
                        </span>
                    <?php elseif ($price === null || (float) $price <= 0) : ?>
                        <span class="sikshya-course-card__badge sikshya-course-card__badge--free">
                            <?php _e('Free', 'sikshya'); ?>
                        </span>
                    <?php else : ?>
                        <span class="sikshya-course-card__badge sikshya-course-card__badge--premium">
                            <?php _e('Premium', 'sikshya'); ?>
                        </span>
                    <?php endif; ?>

                    <?php
                    /**
                     * Extra badges on a course listing card (bundle membership, subscription only, multi-instructor count).
                     *
                     * @param int $course_id
                     */
                    do_action('sikshya_course_card_badges', (int) $course_id);
                    ?>
                    
                    <!-- Course Content -->
                    <div class="sikshya-course-card__content">
                        
                        <!-- Course Title -->
                        <h3 class="sikshya-course-card__title">
                            <a href="<?php echo get_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                        
                        <!-- Course Excerpt -->
                        <div class="sikshya-course-card__excerpt">
                            <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                        </div>
                        
                        <!-- Course Meta -->
                        <div class="sikshya-course-card__meta">
                            <?php if ($duration) : ?>
                                <div class="sikshya-course-card__meta-item">
                                    <span class="dashicons dashicons-clock"></span>
                                    <span><?php echo esc_html($duration); ?> <?php _e('hours', 'sikshya'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($difficulty) : ?>
                                <div class="sikshya-course-card__meta-item">
                                    <span class="dashicons dashicons-chart-line"></span>
                                    <span><?php echo esc_html(ucfirst($difficulty)); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($enrollment_count > 0) : ?>
                                <div class="sikshya-course-card__meta-item">
                                    <span class="dashicons dashicons-groups"></span>
                                    <span><?php echo number_format($enrollment_count); ?> <?php _e('students', 'sikshya'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($rating > 0) : ?>
                                <div class="sikshya-course-card__meta-item">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <span><?php echo number_format($rating, 1); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php
                        /**
                         * Extra meta row inside a course listing card (instructor list, prerequisites pill, etc.).
                         *
                         * @param int $course_id
                         */
                        do_action('sikshya_course_card_meta', (int) $course_id);
                        ?>

                        <!-- Course Categories -->
                        <?php if (!empty($categories)) : ?>
                            <div class="sikshya-course-card__categories">
                                <?php foreach (array_slice($categories, 0, 2) as $category) : ?>
                                    <span class="sikshya-course-card__category"><?php echo esc_html($category); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($categories) > 2) : ?>
                                    <span class="sikshya-course-card__category">+<?php echo count($categories) - 2; ?> more</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Course Price -->
                        <div class="sikshya-course-card__price">
                            <?php if ($pricing['on_sale'] && $price !== null && $sale_price !== null) : ?>
                                <span class="sikshya-course-card__price--sale">
                                    <?php echo wp_kses_post(sikshya_format_price((float) $sale_price, $course_currency)); ?>
                                </span>
                                <span class="sikshya-course-card__price--original">
                                    <?php echo wp_kses_post(sikshya_format_price((float) $price, $course_currency)); ?>
                                </span>
                            <?php elseif ($price !== null && (float) $price > 0) : ?>
                                <span class="sikshya-course-card__price--regular">
                                    <?php echo wp_kses_post(sikshya_format_price((float) $price, $course_currency)); ?>
                                </span>
                            <?php else : ?>
                                <span class="sikshya-course-card__price--free">
                                    <?php _e('Free', 'sikshya'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Course Actions -->
                        <div class="sikshya-course-card__actions">
                            <?php if (sikshya_is_user_enrolled(get_current_user_id(), $course_id)) : ?>
                                <a href="<?php echo get_permalink(); ?>" class="sikshya-btn sikshya-btn--success">
                                    <?php _e('Continue Learning', 'sikshya'); ?>
                                </a>
                            <?php else : ?>
                                <button class="sikshya-btn sikshya-btn--primary sikshya-btn--enroll" 
                                        data-course-id="<?php echo esc_attr($course_id); ?>">
                                    <?php _e('Enroll Now', 'sikshya'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <button class="sikshya-btn sikshya-btn--outline sikshya-btn--favorite" 
                                    data-course-id="<?php echo esc_attr($course_id); ?>"
                                    data-tooltip="<?php _e('Add to favorites', 'sikshya'); ?>">
                                <span class="dashicons dashicons-heart"></span>
                            </button>
                            
                            <button class="sikshya-btn sikshya-btn--outline sikshya-btn--share" 
                                    data-course-id="<?php echo esc_attr($course_id); ?>"
                                    data-tooltip="<?php _e('Share course', 'sikshya'); ?>">
                                <span class="dashicons dashicons-share"></span>
                            </button>
                        </div>
                        
                    </div>
                    
                </div>
                
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($courses_query->max_num_pages > 1) : ?>
            <div class="sikshya-pagination">
                <?php
                echo paginate_links(array(
                    'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                    'format' => '?paged=%#%',
                    'current' => max(1, get_query_var('paged')),
                    'total' => $courses_query->max_num_pages,
                    'prev_text' => __('&laquo; Previous', 'sikshya'),
                    'next_text' => __('Next &raquo;', 'sikshya'),
                    'type' => 'list',
                ));
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
            <a href="<?php echo esc_url(remove_query_arg(array('category', 'difficulty', 'search'))); ?>" 
               class="sikshya-btn sikshya-btn--primary">
                <?php _e('View All Courses', 'sikshya'); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <?php wp_reset_postdata(); ?>
    
</div>

<?php
/**
 * Helper: check if user has an enrollment row for this course (any active progress state).
 */
function sikshya_is_user_enrolled($user_id, $course_id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sikshya_enrollments';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
        return false;
    }

    $table_sql = esc_sql($table_name);
    $enrollment = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM `{$table_sql}` WHERE user_id = %d AND course_id = %d AND status IN ('active','enrolled','completed') LIMIT 1",
            $user_id,
            $course_id
        )
    );

    return !empty($enrollment);
}
?>