<?php
/**
 * Course Category Archive Template
 *
 * @package Sikshya
 */

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

get_header(); ?>

<div class="sikshya-course-category-archive">
    <!-- Category Header -->
    <section class="sikshya-category-header">
        <div class="sikshya-container">
            <div class="sikshya-category-breadcrumb">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="sikshya-breadcrumb-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <?php _e('Home', 'sikshya'); ?>
                </a>
                <span class="sikshya-breadcrumb-separator">/</span>
                <a href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE)); ?>" class="sikshya-breadcrumb-link">
                    <?php _e('Courses', 'sikshya'); ?>
                </a>
                <span class="sikshya-breadcrumb-separator">/</span>
                <span class="sikshya-breadcrumb-current"><?php single_term_title(); ?></span>
            </div>
            
            <div class="sikshya-category-info">
                <div class="sikshya-category-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div class="sikshya-category-content">
                    <h1 class="sikshya-category-title"><?php single_term_title(); ?></h1>
                    <?php if (term_description()): ?>
                        <div class="sikshya-category-description">
                            <?php echo term_description(); ?>
                        </div>
                    <?php endif; ?>
                    <div class="sikshya-category-stats">
                        <span class="sikshya-category-course-count">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <?php 
                            $course_count = $wp_query->found_posts;
                            printf(
                                _n('%d Course', '%d Courses', $course_count, 'sikshya'),
                                $course_count
                            );
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters and Search -->
    <section class="sikshya-category-filters">
        <div class="sikshya-container">
            <div class="sikshya-filters-wrapper">
                <div class="sikshya-search-wrapper">
                    <form class="sikshya-search-form" method="get" action="">
                        <input type="hidden" name="post_type" value="<?php echo esc_attr(PostTypes::COURSE); ?>">
                        <input type="hidden" name="<?php echo esc_attr(Taxonomies::COURSE_CATEGORY); ?>" value="<?php echo esc_attr(get_queried_object()->slug); ?>">
                        <div class="sikshya-search-input-group">
                            <input type="text" name="s" placeholder="<?php _e('Search courses in this category...', 'sikshya'); ?>" value="<?php echo esc_attr(get_search_query()); ?>" class="sikshya-search-input">
                            <button type="submit" class="sikshya-search-button">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="sikshya-filter-options">
                    <div class="sikshya-filter-group">
                        <label for="sikshya-difficulty-filter" class="sikshya-filter-label"><?php _e('Difficulty', 'sikshya'); ?></label>
                        <select id="sikshya-difficulty-filter" class="sikshya-filter-select" onchange="filterCourses()">
                            <option value=""><?php _e('All Levels', 'sikshya'); ?></option>
                            <option value="beginner"><?php _e('Beginner', 'sikshya'); ?></option>
                            <option value="intermediate"><?php _e('Intermediate', 'sikshya'); ?></option>
                            <option value="advanced"><?php _e('Advanced', 'sikshya'); ?></option>
                        </select>
                    </div>
                    
                    <div class="sikshya-filter-group">
                        <label for="sikshya-sort-filter" class="sikshya-filter-label"><?php _e('Sort By', 'sikshya'); ?></label>
                        <select id="sikshya-sort-filter" class="sikshya-filter-select" onchange="filterCourses()">
                            <option value="date"><?php _e('Newest First', 'sikshya'); ?></option>
                            <option value="title"><?php _e('Alphabetical', 'sikshya'); ?></option>
                            <option value="popularity"><?php _e('Most Popular', 'sikshya'); ?></option>
                            <option value="rating"><?php _e('Highest Rated', 'sikshya'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Courses Grid -->
    <section class="sikshya-category-courses">
        <div class="sikshya-container">
            <?php if (have_posts()): ?>
                <div class="sikshya-course-grid">
                    <?php while (have_posts()): the_post(); ?>
                        <article class="sikshya-course-card">
                            <div class="sikshya-course-thumbnail">
                                <?php if (has_post_thumbnail()): ?>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('medium', ['class' => 'sikshya-course-image']); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php the_permalink(); ?>" class="sikshya-course-placeholder">
                                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="sikshya-course-badge">
                                    <?php 
                                    $difficulty = get_the_terms(get_the_ID(), Taxonomies::DIFFICULTY);
                                    if ($difficulty && !is_wp_error($difficulty)) {
                                        $difficulty_class = 'sikshya-difficulty-' . $difficulty[0]->slug;
                                        echo '<span class="sikshya-difficulty-badge ' . esc_attr($difficulty_class) . '">' . esc_html($difficulty[0]->name) . '</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="sikshya-course-content">
                                <div class="sikshya-course-meta">
                                    <span class="sikshya-course-instructor">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        <?php echo esc_html(get_the_author()); ?>
                                    </span>
                                    <span class="sikshya-course-date">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        <?php echo get_the_date(); ?>
                                    </span>
                                </div>
                                
                                <h3 class="sikshya-course-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h3>
                                
                                <div class="sikshya-course-excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?>
                                </div>
                                
                                <div class="sikshya-course-footer">
                                    <div class="sikshya-course-stats">
                                        <span class="sikshya-course-rating">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                            </svg>
                                            <?php 
                                            $rating = get_post_meta(get_the_ID(), 'course_rating', true);
                                            echo $rating ? number_format($rating, 1) : '4.5';
                                            ?>
                                        </span>
                                        <span class="sikshya-course-students">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <?php 
                                            $students = get_post_meta(get_the_ID(), 'course_enrollments', true);
                                            echo $students ? number_format($students) : '0';
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="sikshya-course-price">
                                        <?php 
                                        $price = get_post_meta(get_the_ID(), 'course_price', true);
                                        $sale_price = get_post_meta(get_the_ID(), 'course_sale_price', true);
                                        
                                        if ($sale_price && $sale_price < $price) {
                                            echo '<span class="sikshya-price-current">$' . number_format($sale_price, 2) . '</span>';
                                            echo '<span class="sikshya-price-original">$' . number_format($price, 2) . '</span>';
                                        } elseif ($price && $price > 0) {
                                            echo '<span class="sikshya-price">$' . number_format($price, 2) . '</span>';
                                        } else {
                                            echo '<span class="sikshya-price-free">' . __('FREE', 'sikshya') . '</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <div class="sikshya-pagination">
                    <?php
                    echo paginate_links([
                        'prev_text' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"></polyline></svg> ' . __('Previous', 'sikshya'),
                        'next_text' => __('Next', 'sikshya') . ' <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"></polyline></svg>',
                        'mid_size' => 2,
                        'end_size' => 1,
                    ]);
                    ?>
                </div>
            <?php else: ?>
                <div class="sikshya-no-courses">
                    <div class="sikshya-no-courses-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="sikshya-no-courses-title"><?php _e('No Courses Found', 'sikshya'); ?></h3>
                    <p class="sikshya-no-courses-message">
                        <?php printf(
                            __('Sorry, no courses found in the "%s" category.', 'sikshya'),
                            single_term_title('', false)
                        ); ?>
                    </p>
                    <a href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE)); ?>" class="sikshya-btn sikshya-btn-primary">
                        <?php _e('Browse All Courses', 'sikshya'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
function filterCourses() {
    const difficulty = document.getElementById('sikshya-difficulty-filter').value;
    const sort = document.getElementById('sikshya-sort-filter').value;
    
    const url = new URL(window.location);
    if (difficulty) {
        url.searchParams.set('difficulty', difficulty);
    } else {
        url.searchParams.delete('difficulty');
    }
    
    if (sort) {
        url.searchParams.set('orderby', sort);
    } else {
        url.searchParams.delete('orderby');
    }
    
    window.location.href = url.toString();
}
</script>

<?php get_footer(); ?>
