<?php

class Sikshya_Setup_Wizard
{


	public function __construct()
	{

		// if we are here, we assume we don't need to run the wizard again
		// and the user doesn't need to be redirected here
		update_option('sikshya_setup_wizard_ran', '1');

		if (apply_filters('sikshya_enable_setup_wizard', true) && current_user_can('manage_options')) {

			add_action('admin_menu', array($this, 'admin_menus'));
			add_action('admin_init', array($this, 'setup_wizard'));

		}
	}

	/**
	 * Add admin menus/screens.
	 */
	public function admin_menus()
	{
		add_dashboard_page('', '', 'manage_options', 'sikshya-setup', '');
	}

	/**
	 * Show the setup wizard
	 */
	public function setup_wizard()
	{

		if (empty($_GET['page']) || 'sikshya-setup' !== $_GET['page']) {
			return;
		}


		$steps = array(

			array(
				'label' => __('Welcome', 'sikshya'),
				'id' => 'welcome'
			),
			array(
				'label' => __('General', 'sikshya'),
				'id' => 'general'
			),
			array(
				'label' => __('Pages', 'sikshya'),
				'id' => 'pages'
			),
			array(
				'label' => __('Themes', 'sikshya'),
				'id' => 'themes'
			),
			array(
				'label' => __('Finish', 'sikshya'),
				'id' => 'finish'
			),



		);

		$setup_dependency = file_exists(SIKSHYA_PATH . '/assets/build/js/setup.asset.php') ? include_once(SIKSHYA_PATH . '/assets/build/js/setup.asset.php') : array();

		$setup_dependency['dependencies'] = isset($setup_dependency['dependencies']) ? $setup_dependency['dependencies'] : array();

		$setup_dependency['version'] = isset($setup_dependency['version']) ? sanitize_text_field($setup_dependency['version']) : SIKSHYA_VERSION;

		wp_enqueue_script('sikshya-setup', SIKSHYA_ASSETS_URL . '/build/js/setup.js', $setup_dependency['dependencies'], $setup_dependency['version']);

		wp_enqueue_style('sikshya-setup', SIKSHYA_ASSETS_URL . '/build/style-setup.css', array(), $setup_dependency['version']);

		$all_pages = get_pages();
		$all_updated_pages = wp_list_pluck($all_pages, 'post_title', 'ID');

		wp_localize_script('sikshya-setup', 'sikshyaSetup',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'admin_url' => admin_url('index.php'),
				'course_page_url' => admin_url('edit.php?post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE),
				'rest_namespace' => 'sikshya',
				'rest_version' => 'v1',
				'currencies' => sikshya_get_currencies(),
				'currency_symbol_type' => sikshya_get_currency_symbol_type(),
				'currency_positions' => sikshya_get_currency_positions(),
				'all_pages' => $all_updated_pages,
				'steps' => $steps

			)
		);


		$this->setup_wizard_header();
		$this->setup_wizard_content();
		$this->setup_wizard_footer();
		exit;
	}


	/**
	 * Setup Wizard Header
	 */
	public function setup_wizard_header()
	{
		?>
		<!DOCTYPE html>
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width"/>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<title><?php _e('Sikshya &rsaquo; Setup Wizard', 'sikshya'); ?></title>
			<?php wp_print_scripts('sikshya-setup'); ?>
			<?php do_action('admin_print_styles'); ?>
			<?php //do_action('admin_head');
			?>
		</head>
		<body class="sikshya-setup">
		<?php
	}

	/**
	 * Setup Wizard Footer
	 */
	public function setup_wizard_footer()
	{
		?>
		</body>
		</html>
		<?php
	}


	/**
	 * Output the content for the current step
	 */
	public function setup_wizard_content()
	{
		echo '<div class="sikshya-setup-content">';
		echo '<div id="sikshya-setup-element"></div>';

		echo '</div>';
	}
}

return new Sikshya_Setup_Wizard();
