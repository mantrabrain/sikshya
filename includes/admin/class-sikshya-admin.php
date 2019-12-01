<?php

class Sikshya_Admin
{
    public function __construct()
    {

        $this->includes();
        $this->hooks();

    }

    public function includes()
    {
        include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin-assets.php';
        include_once SIKSHYA_PATH . '/includes/admin/class-sikshya-admin-menu.php';
        include_once SIKSHYA_PATH . '/includes/about/class-sikshya-about.php';
    }

    public function hooks()
    {
        add_action('current_screen', array($this, 'setup_screen'));
        add_action('check_ajax_referer', array($this, 'setup_screen'));
    }

    public function setup_screen()
    {

        $screen_id = false;

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            $screen_id = isset($screen, $screen->id) ? $screen->id : '';
        }

        if (!empty($_REQUEST['screen'])) { // WPCS: input var ok.
            $screen_id = sanitize_text_field($_REQUEST['screen']);
        }

        switch ($screen_id) {
            case 'edit-' . SIKSHYA_LESSONS_CUSTOM_POST_TYPE:
                include_once 'list-tables/class-sikshya-admin-list-table-lessons.php';
                new Sikshya_Admin_List_Table_Lessons();
                break;
            case 'edit-' . SIKSHYA_ORDERS_CUSTOM_POST_TYPE:
                include_once 'list-tables/class-sikshya-admin-list-table-orders.php';
                new Sikshya_Admin_List_Table_Orders();
                break;

        }

        // Ensure the table handler is only loaded once. Prevents multiple loads if a plugin calls check_ajax_referer many times.
        remove_action('current_screen', array($this, 'setup_screen'));
        remove_action('check_ajax_referer', array($this, 'setup_screen'));
    }

}

new Sikshya_Admin();