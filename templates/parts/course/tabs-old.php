<?php
$tabs = sikshya_get_course_tabs();

?>

<?php if (empty($tabs)) {
    return;
} ?>

<div id="sikshya-course-tabs" class="sikshya-course-tabs">

    <ul class="sikshya-nav-tabs course-nav-tabs">

        <?php foreach ($tabs as $key => $tab) { ?>

            <?php $classes = array('course-nav course-nav-tab-' . esc_attr($key));
            if (!empty($tab['active']) && $tab['active']) {
                $classes[] = 'active default';
            } ?>

            <li class="<?php echo join(' ', $classes); ?>">
                <a href="?tab=<?php echo esc_attr($tab['id']); ?>"
                   data-tab="#<?php echo esc_attr($tab['id']); ?>"><?php echo $tab['title']; ?></a>
            </li>

        <?php } ?>

    </ul>

    <?php foreach ($tabs as $key => $tab) {

        ?>

        <div class="course-tab-panel-<?php echo esc_attr($key); ?> course-tab-panel<?php echo !empty($tab['active']) && $tab['active'] ? ' active' : ''; ?>"
             id="<?php echo esc_attr($tab['id']); ?>">

            <?php
            if (apply_filters('sikshya_allow_display_tab_section', true, $key, $tab)) {
                if (is_callable($tab['callback']) && !empty($tab['active'])) {
                    call_user_func($tab['callback'], $key, $tab);
                } else {
                    /**
                     * @since 3.0.0
                     */
                    do_action('sikshya-course-tab-content', $key, $tab);
                }
            }
            ?>

        </div>

    <?php } ?>

</div>