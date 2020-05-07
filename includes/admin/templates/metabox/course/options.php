<div class="sikshya-course-settings sikshya-tabs">
    <ul class="tab-nav">
        <?php foreach ($tabs as $tab_key => $tab_config) {
            $is_active = isset($tab_config['is_active']) ? (boolean)$tab_config['is_active'] : false;
            ?>
            <li data-id="<?php echo esc_attr($tab_key); ?>" class="<?php echo $is_active ? 'active' : ''; ?>"><a
                        href="#"><?php echo esc_html($tab_config['title']); ?></a></li>
        <?php } ?>
    </ul>
    <div class="tab-content">
        <?php foreach ($tabs as $tab_content_key => $tab_content_config) {
            $is_active_content = isset($tab_content_config['is_active']) ? (boolean)$tab_content_config['is_active'] : false;
            $class = 'tab-content-item ' . $tab_content_key;
            $class .= $is_active_content ? ' active' : '';
            ?>
            <section class="<?php echo esc_attr($class); ?>">
                <?php
                do_action('sikshya_course_tab_' . $tab_content_key);
                ?>
            </section>
        <?php } ?>
    </div>
</div>