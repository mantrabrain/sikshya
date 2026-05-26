<?php
/**
 * Course tag archive — same course cards as /courses, simple 3-column grid.
 *
 * @package Sikshya
 */

use Sikshya\Constants\PostTypes;

sikshya_get_header();

$ctx = \Sikshya\Frontend\Site\ArchiveContextTemplateData::fromWpQuery();
$found = (int) $ctx['found'];
$max_pages = (int) $ctx['max_pages'];
$paged = (int) $ctx['paged'];

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');

$courses_archive_url = get_post_type_archive_link(PostTypes::COURSE);

$breadcrumb_items = [
    [
        'label' => __('Home', 'sikshya'),
        'url' => home_url('/'),
    ],
    [
        'label' => $label_courses,
        'url' => is_string($courses_archive_url) ? $courses_archive_url : '',
    ],
    [
        'label' => (string) single_term_title('', false),
    ],
];
?>

<div class="sikshya-public sikshya-archive-courses sikshya-taxonomy-courses sikshya-taxonomy-courses--tag sik-f-scope">
    <header class="sikshya-course-lp__masthead">
        <div class="sikshya-container sikshya-container--course sikshya-course-lp__masthead-inner">
            <div class="sikshya-taxonomy-courses__header sikshya-archive-courses__header">
                <?php
                $items = $breadcrumb_items;
                require __DIR__ . '/partials/course-discovery-breadcrumb.php';
                ?>

                <h1 class="sikshya-archive-courses__title"><?php single_term_title(); ?></h1>

                <?php if (term_description()) : ?>
                    <div class="sikshya-archive-courses__desc sikshya-taxonomy-courses__description">
                        <?php echo term_description(); ?>
                    </div>
                <?php endif; ?>

                <p class="sikshya-archive-courses__results" role="status">
                    <?php
                    echo esc_html(sprintf(
                        _n('%1$d %2$s found', '%1$d %3$s found', $found, 'sikshya'),
                        (int) $found,
                        strtolower($label_course),
                        strtolower($label_courses)
                    ));
                    ?>
                </p>
            </div>
        </div>
    </header>

    <div class="sikshya-container">
        <?php require __DIR__ . '/partials/course-cart-flash.php'; ?>

        <?php if (have_posts()) : ?>
            <div class="sikshya-course-grid sikshya-course-grid--cols-3">
                <?php
                while (have_posts()) :
                    the_post();
                    sikshya_render_course_card(get_post(), 'default');
                endwhile;
                ?>
            </div>

            <?php if ($max_pages > 1) : ?>
                <nav class="sikshya-pagination" aria-label="<?php esc_attr_e('Tag pagination', 'sikshya'); ?>">
                    <?php
                    $links = paginate_links(
                        [
                            'total' => $max_pages,
                            'current' => $paged,
                            'mid_size' => 2,
                            'end_size' => 1,
                            'prev_text' => __('Previous', 'sikshya'),
                            'next_text' => __('Next', 'sikshya'),
                            'type' => 'list',
                            'add_args' => sikshya_course_taxonomy_get_preserved_query_args(),
                        ]
                    );
                    if (!empty($links)) {
                        echo wp_kses_post($links);
                    }
                    ?>
                </nav>
            <?php endif; ?>
        <?php else : ?>
            <div class="sikshya-archive-courses__empty">
                <p class="sikshya-archive-courses__empty-text">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: plural label (e.g. courses), 2: tag name */
                        __('No %1$s tagged "%2$s" yet.', 'sikshya'),
                        strtolower($label_courses),
                        (string) single_term_title('', false)
                    ));
                    ?>
                </p>
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_post_type_archive_link(PostTypes::COURSE)); ?>">
                    <?php echo esc_html(sprintf(__('Browse all %s', 'sikshya'), strtolower($label_courses))); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
sikshya_get_footer();
