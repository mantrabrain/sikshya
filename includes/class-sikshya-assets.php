<?php

class Sikshya_Assets
{
	public function __construct()
	{
		add_action('wp_enqueue_scripts', array($this, 'assets'));

	}

	public function assets()
	{
		wp_register_script('jbox-js', SIKSHYA_ASSETS_URL . '/vendor/jbox/dist/jBox.all.min.js', array(), SIKSHYA_VERSION);
		wp_register_style('jbox-css', SIKSHYA_ASSETS_URL . '/vendor/jbox/dist/jBox.all.min.css', array(), SIKSHYA_VERSION);


		//wp_enqueue_script('jbox-js');
		wp_enqueue_style('jbox-css');
		wp_enqueue_style('dashicons');

		wp_enqueue_style(SIKSHYA_COURSES_CUSTOM_POST_TYPE . '-sikshya', SIKSHYA_ASSETS_URL . '/css/sikshya.css', false, SIKSHYA_VERSION);
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('sikshya-main', SIKSHYA_ASSETS_URL . '/js/sikshya.js', array('jquery', 'jbox-js'), SIKSHYA_VERSION);

		wp_localize_script('sikshya-main', 'SikshyaJS', array(
			'admin_ajax' => admin_url('admin-ajax.php')
		));

		// Enqueue styles
		wp_enqueue_style('sikshya-font-awesome-style', SIKSHYA_ASSETS_URL . '/vendor/font-awesome/css/font-awesome.css', array(), SIKSHYA_VERSION);

	}
}

new Sikshya_Assets();
