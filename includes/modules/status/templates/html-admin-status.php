<?php

if (!defined('ABSPATH')) {
    exit;
}

$tab_exists = isset($tabs[$current_tab]) && has_action('sikshya_status_' . $current_tab) ? true : false;

$current_tab_label = isset($tabs[$current_tab]) ? $tabs[$current_tab] : '';

if (!$tab_exists) {
    wp_safe_redirect(admin_url('admin.php?page=sikshya-status'));
    exit;
}
?>
<div class="wrap sikshya">
    <nav class="nav-tab-wrapper sikshya-nav-tab-wrapper">
        <?php

        foreach ($tabs as $slug => $label) {
            echo '<a href="' . esc_html(admin_url('admin.php?page=sikshya-status&tab=' . esc_attr($slug))) . '" class="nav-tab ' . ($current_tab === $slug ? 'nav-tab-active' : '') . '">' . esc_html($label) . '</a>';
        }

        ?>
    </nav>
    <h1 class="screen-reader-text"><?php echo esc_html($current_tab_label); ?></h1>
    <?php

    self::show_messages();

    do_action('sikshya_status_' . $current_tab);

    ?>

</div>
