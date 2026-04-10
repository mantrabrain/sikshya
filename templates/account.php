<?php
/**
 * Learner account — enrollments + orders from {@see \Sikshya\Frontend\Public\AccountTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\AccountTemplateData;
use Sikshya\Frontend\Public\PublicPageUrls;

$acc = AccountTemplateData::build();

get_header();

if ((int) $acc['user_id'] <= 0) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}
?>

<div class="sikshya-public sikshya-account">
    <div class="sikshya-container">
        <h1 class="sikshya-page-title"><?php esc_html_e('My learning', 'sikshya'); ?></h1>

        <section class="sikshya-account__section">
            <h2><?php esc_html_e('My courses', 'sikshya'); ?></h2>
            <?php if ($acc['enrollments'] === []) : ?>
                <p class="sikshya-muted"><?php esc_html_e('You are not enrolled in any courses yet.', 'sikshya'); ?></p>
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($acc['urls']['courses']); ?>"><?php esc_html_e('Browse courses', 'sikshya'); ?></a>
            <?php else : ?>
                <ul class="sikshya-account-list">
                    <?php foreach ($acc['enrollments'] as $row) : ?>
                        <?php
                        $cid = is_object($row) ? (int) ($row->course_id ?? 0) : (int) ($row['course_id'] ?? 0);
                        if ($cid <= 0) {
                            continue;
                        }
                        ?>
                        <li>
                            <a href="<?php echo esc_url(get_permalink($cid)); ?>"><?php echo esc_html(get_the_title($cid)); ?></a>
                            &nbsp;·&nbsp;
                            <a href="<?php echo esc_url(PublicPageUrls::learnForCourse($cid)); ?>"><?php esc_html_e('Learn', 'sikshya'); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="sikshya-account__section">
            <h2><?php esc_html_e('Orders', 'sikshya'); ?></h2>
            <?php if ($acc['orders'] === []) : ?>
                <p class="sikshya-muted"><?php esc_html_e('No orders yet.', 'sikshya'); ?></p>
            <?php else : ?>
                <ul class="sikshya-account-list">
                    <?php foreach ($acc['orders'] as $ord) : ?>
                        <li>
                            <a href="<?php echo esc_url(PublicPageUrls::orderView((int) $ord->id)); ?>">
                                <?php printf(esc_html__('Order #%d — %s', 'sikshya'), (int) $ord->id, esc_html((string) $ord->status)); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <p><a href="<?php echo esc_url($acc['urls']['cart']); ?>"><?php esc_html_e('View cart', 'sikshya'); ?></a></p>
    </div>
</div>

<?php
get_footer();
