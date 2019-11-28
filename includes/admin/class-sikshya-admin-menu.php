<?php

class Sikshya_Admin_Menu
{
    const ADMIN_PAGE = 'sikshya';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'admin_menu'));

    }

    public function admin_menu()
    {
        add_menu_page(
            __('Sikshya', 'sikshya')
            , __('Sikshya', 'sikshya'),
            'administrator',
            'sikshya', null,
            'dashicons-welcome-learn-more', 2);
        add_submenu_page('sikshya', __('Categories', 'sikshya'), __('Categories', 'sikshya'),
            'administrator', 'edit-tags.php?taxonomy=course-category&post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE, null);

        add_submenu_page('sikshya', __('Tags', 'sikshya'), __('Tags', 'sikshya'),
            'administrator', 'edit-tags.php?taxonomy=course-tag&post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE, null);


        add_submenu_page(
            'sikshya',
            __('Sikshya Settings', 'sikshya'),
            __('Settings', 'sikshya'),
            'administrator',
            self::ADMIN_PAGE,
            array($this, 'setting_page')
        );

    }

    public function setting_page()
    {
        
    }


}

new Sikshya_Admin_Menu();