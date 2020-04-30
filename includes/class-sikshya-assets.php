<?php

class Sikshya_Assets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'assets'));

    }

    public function assets()
    {

        wp_enqueue_style('dashicons');

        wp_enqueue_style('sikshya-grid-style', SIKSHYA_ASSETS_URL . '/css/sikshya-grid.css', array(), SIKSHYA_VERSION);

        wp_enqueue_style(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-sikshya', SIKSHYA_ASSETS_URL . '/css/sikshya.css', false, SIKSHYA_VERSION);
        wp_enqueue_script('jquery-ui-core');
        //wp_enqueue_script('jquery-plugin', SIKSHYA_ASSETS_URL . '/public/js/vendor/jquery.plugin.min.js', array(), SIKSHYA_VERSION);
        wp_enqueue_script('jquery-countdown', SIKSHYA_ASSETS_URL . '/vendor/jquery.countdown.min.js', array(), SIKSHYA_VERSION);
        wp_enqueue_script('sikshya-main', SIKSHYA_ASSETS_URL . '/js/custom/sikshya.js', array('jquery'), SIKSHYA_VERSION);
        wp_enqueue_script('sikshya-video', SIKSHYA_ASSETS_URL . '/js/video.js', array(), SIKSHYA_VERSION);
        wp_enqueue_script('sikshya-countdown-js', SIKSHYA_ASSETS_URL . '/js/countdown.js', array(), SIKSHYA_VERSION);
        wp_enqueue_script('sikshya-tabs', SIKSHYA_ASSETS_URL . '/js/tabs.js', array(), SIKSHYA_VERSION);


        // Enqueue styles
        wp_enqueue_style('sikshya-font-awesome-style', SIKSHYA_ASSETS_URL . '/vendor/font-awesome/css/font-awesome.css', array(), SIKSHYA_VERSION);
        wp_enqueue_style('sikshya-ionicons-style', SIKSHYA_ASSETS_URL . '/vendor/ionicons//css/ionicons.min.css', array(), SIKSHYA_VERSION);
        
    }
}

new Sikshya_Assets();