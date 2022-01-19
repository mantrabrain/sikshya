<?php

class Sikshya_Setup_Wizard
{

	/** @var string Currenct Step */
	private $step = '';

	/** @var array Steps for the setup wizard */
	private $steps = array();

	/**
	 * Hook in tabs.
	 */
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


		$this->steps = array(
			'introduction' => array(
				'name' => __('Introduction', 'sikshya'),
				'view' => array($this, 'setup_step_introduction'),
				'handler' => ''
			),

			'final' => array(
				'name' => __('Final!', 'sikshya'),
				'view' => array($this, 'setup_final_ready'),
				'handler' => ''
			)
		);

		$this->step = isset($_GET['step']) ? sanitize_key($_GET['step']) : current(array_keys($this->steps));

		if (!isset($this->steps[$this->step])) {
			$all_steps_key = array_keys($this->steps);
			$this->step = $all_steps_key[0];
		}


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
				'all_pages' => $all_updated_pages

			)
		);


		if (!empty($_POST['save_step']) && isset($this->steps[$this->step]['handler'])) {
			call_user_func($this->steps[$this->step]['handler']);
		}

		ob_start();
		$this->setup_wizard_header();
		$this->setup_wizard_steps();
		$this->setup_wizard_content();
		$this->setup_wizard_footer();
		exit;
	}

	public function get_next_step_link()
	{
		$keys = array_keys($this->steps);
		return add_query_arg('step', $keys[array_search($this->step, array_keys($this->steps)) + 1], remove_query_arg('translation_updated'));
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
		<body class="sikshya-setup wp-core-ui">
		<?php
	}

	/**
	 * Setup Wizard Footer
	 */
	public function setup_wizard_footer()
	{
		?>
		<?php if ('next_steps' === $this->step) : ?>
		<a class="sikshya-return-to-dashboard"
		   href="<?php echo esc_url(admin_url()); ?>"><?php _e('Return to the WordPress Dashboard', 'sikshya'); ?></a>
	<?php endif; ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Output the steps
	 */
	public function setup_wizard_steps()
	{
		return;
		$output_steps = $this->steps;

		?>
		<ol class="sikshya-setup-steps">
			<?php foreach ($output_steps as $step_key => $step) : ?>
				<li class="<?php
				if ($step_key === $this->step) {
					echo 'active';
				} elseif (array_search($this->step, array_keys($this->steps)) > array_search($step_key, array_keys($this->steps))) {
					echo 'done';
				}
				?>">
					<a href="<?php echo admin_url('index.php?page=sikshya-setup&step=' . $step_key); ?>"><?php echo esc_html($step['name']); ?></a>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * Output the content for the current step
	 */
	public function setup_wizard_content()
	{
		echo '<div class="sikshya-setup-content">';
		echo '<div id="sikshya-setup-element"></div>';
		if (isset($this->steps[$this->step])) {
//			call_user_func($this->steps[$this->step]['view']);
		}
		echo '</div>';
	}

	public function next_step_buttons()
	{
		?>
		<p class="sikshya-setup-actions step">
			<input type="submit" class="button-primary button button-large button-next"
				   value="<?php esc_attr_e('Continue', 'sikshya'); ?>" name="save_step"/>
			<a href="<?php echo esc_url($this->get_next_step_link()); ?>"
			   class="button button-large button-next"><?php _e('Skip this step', 'sikshya'); ?></a>
			<?php wp_nonce_field('sikshya-setup'); ?>
		</p>
		<?php
	}

	/**
	 * Introduction step
	 */
	public function setup_step_introduction()
	{
		?>
		<h1><?php _e('Welcome to Complete Travel & Tour Booking System – Sikshya!', 'sikshya'); ?></h1>
		<p><?php _e('Thank you for choosing Sikshya plugin for your travel & tour booking site. This setup wizard will help you configure the basic settings of the plugin. <strong>It’s completely optional and shouldn’t take longer than one minutes.</strong>', 'sikshya'); ?></p>
		<p><?php _e('No time right now? If you don’t want to go through the wizard, you can skip and return to the WordPress dashboard.', 'sikshya'); ?></p>
		<p class="sikshya-setup-actions step">
			<a href="<?php echo esc_url($this->get_next_step_link()); ?>"
			   class="button-primary button button-large button-next"><?php _e('Let\'s Go!', 'sikshya'); ?></a>
			<a href="<?php echo esc_url(admin_url('edit.php?post_type=tour&page=sikshya-dashboard')); ?>"
			   class="button button-large"><?php _e('Not right now', 'sikshya'); ?></a>
		</p>
		<?php
	}

	public function setup_step_general()
	{
		?>
		<h1><?php _e('General Settings', 'sikshya'); ?></h1>

		<form method="post">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="sikshya_currency"><?php _e('Currency', 'sikshya'); ?></label></th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_currency',
							'type' => 'select',
							'value' => get_option('sikshya_currency', 'USD'),
							'options' => sikshya_get_currencies(),
							'help' => __('Currency symbol for sikshya plugin.', 'sikshya'),
						]); ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><label
							for="sikshya_currency_position"><?php _e('Currency Position', 'sikshya'); ?></label></th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_currency_position',
							'type' => 'select',
							'value' => get_option('sikshya_currency_position', 'left'),
							'options' => sikshya_get_currency_positions(),
							'help' => __('Currency symbol position.', 'sikshya'),
						]); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label
							for="sikshya_thousand_separator"><?php _e('Thousand Separator', 'sikshya'); ?></label></th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_thousand_separator',
							'type' => 'text',
							'value' => get_option('sikshya_thousand_separator', ','),
							'help' => __('Thousand separator for price.', 'sikshya'),
						]); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label
							for="sikshya_price_number_decimals"><?php _e('Number of decimals', 'sikshya'); ?></label>
					</th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_price_number_decimals',
							'type' => 'number',
							'value' => get_option('sikshya_price_number_decimals', 2),
							'help' => __('Number of decimals shown in price.', 'sikshya'),
						]); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label
							for="sikshya_decimal_separator"><?php _e('Decimal Separator', 'sikshya'); ?></label>
					</th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_decimal_separator',
							'type' => 'text',
							'value' => get_option('sikshya_decimal_separator', '.'),
							'help' => __('Decimal separator for price..', 'sikshya'),
						]); ?>
					</td>
				</tr>
			</table>

			<?php $this->next_step_buttons(); ?>
		</form>
		<?php
	}

	public function setup_step_general_save()
	{
		check_admin_referer('sikshya-setup');

		$sikshya_currency = sanitize_text_field($_POST['sikshya_currency']);
		$currency_position = sanitize_text_field($_POST['sikshya_currency_position']);
		$all_currency_positions = sikshya_get_currency_positions();
		$currency_position = isset($all_currency_positions[$currency_position]) ? $currency_position : 'left';
		$thousand_separator = sanitize_text_field($_POST['sikshya_thousand_separator']);
		$decimals = absint($_POST['sikshya_price_number_decimals']);
		$decimal_separator = sanitize_text_field($_POST['sikshya_decimal_separator']);

		$all_currencies = array_keys(sikshya_get_currencies());

		if (in_array($sikshya_currency, $all_currencies)) {

			update_option('sikshya_currency', $sikshya_currency);
		}
		update_option('sikshya_currency', $sikshya_currency);
		update_option('sikshya_currency_position', $currency_position);
		update_option('sikshya_thousand_separator', $thousand_separator);
		update_option('sikshya_price_number_decimals', $decimals);
		update_option('sikshya_decimal_separator', $decimal_separator);


		wp_redirect(esc_url_raw($this->get_next_step_link()));
		exit;
	}


	public function setup_step_pages()
	{
		?>

		<h1><?php _e('Pages Options', 'sikshya'); ?></h1>

		<form method="post">

			<table class="form-table">
				<tr>
					<th scope="row"><label for="sikshya_cart_page"><?php _e('Cart Page', 'sikshya'); ?></label></th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_cart_page',
							'type' => 'single_select_page',
							'value' => get_option('sikshya_cart_page', ''),
							'help' => __('Cart page for sikshya plugin.', 'sikshya'),
						]); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sikshya_checkout_page"><?php _e('Checkout Page', 'sikshya'); ?></label>
					</th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_checkout_page',
							'type' => 'single_select_page',
							'value' => get_option('sikshya_checkout_page', ''),
							'help' => __('Checkout page for sikshya plugin.', 'sikshya'),
						]); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label
							for="sikshya_my_account_page"><?php _e('My Account Page', 'sikshya'); ?></label>
					</th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_my_account_page',
							'type' => 'single_select_page',
							'value' => get_option('sikshya_my_account_page', ''),
							'help' => __('My Account page for sikshya plugin.', 'sikshya'),
						]); ?>
					</td>
				</tr>
			</table>

			<?php $this->next_step_buttons(); ?>
		</form>
		<?php
	}

	/**
	 * Module setup step save
	 * @since 1.3.4
	 *
	 * Add project manager plugin
	 * @since 1.4.2
	 */
	public function setup_step_pages_save()
	{
		check_admin_referer('sikshya-setup');

		$sikshya_cart_page = absint($_POST['sikshya_cart_page']);

		$sikshya_checkout_page = absint($_POST['sikshya_checkout_page']);

		$sikshya_my_account_page = absint($_POST['sikshya_my_account_page']);


		if ((int)($sikshya_cart_page) > 0) {

			update_option('sikshya_cart_page', $sikshya_cart_page);
		}
		if ((int)($sikshya_checkout_page) > 0) {

			update_option('sikshya_checkout_page', $sikshya_checkout_page);
		}
		if ((int)($sikshya_my_account_page) > 0) {

			update_option('sikshya_my_account_page', $sikshya_my_account_page);
		}

		wp_redirect(esc_url_raw($this->get_next_step_link()));
		exit;
	}

	public function setup_step_design()
	{
		?>
		<h1><?php _e('Design Setup', 'sikshya'); ?></h1>
		<form method="post">

			<table class="form-table">
				<tr>
					<th scope="row"><label
							for="sikshya_page_container_class"><?php _e('Container Class', 'sikshya'); ?></label></th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_page_container_class',
							'type' => 'text',
							'value' => get_option('sikshya_page_container_class', ''),
							'help' => __('Container class for all page templates for sikshya plugin.', 'sikshya'),
						]); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label
							for="sikshya_setting_layouts_single_tour_tab_layout"><?php _e('Tab Layout for tour page	', 'sikshya'); ?></label>
					</th>

					<td>
						<?php sikshya_html_form_input([
							'name' => 'sikshya_setting_layouts_single_tour_tab_layout',
							'type' => 'select',
							'value' => get_option('sikshya_setting_layouts_single_tour_tab_layout', ''),
							'help' => __('Tab layout for single tour page.', 'sikshya'),
							'options' => array(
								'' => __('Tab Style Layout', 'sikshya'),
								'heading_and_content' => __('Heading & Content Style Tab', 'sikshya'),
							)
						]); ?>
					</td>
				</tr>
			</table>

			<?php $this->next_step_buttons(); ?>
		</form>
		<?php

	}

	public function setup_step_design_save()
	{
		check_admin_referer('sikshya-setup');

		$sikshya_page_container_class = sanitize_text_field($_POST['sikshya_page_container_class']);

		$sikshya_setting_layouts_single_tour_tab_layout = sanitize_text_field($_POST['sikshya_setting_layouts_single_tour_tab_layout']);

		update_option('sikshya_page_container_class', $sikshya_page_container_class);
		update_option('sikshya_setting_layouts_single_tour_tab_layout', $sikshya_setting_layouts_single_tour_tab_layout);

		wp_redirect(esc_url_raw($this->get_next_step_link()));
		exit;
	}

	public function setup_step_miscellaneous()
	{
		?>
		<h1><?php _e('Miscellaneous Setup', 'sikshya'); ?></h1>
		<form method="post">
			<?php
			$guest_checkout = get_option('sikshya_enable_guest_checkout', 'yes');
			?>

			<table class="form-table">

				<tr>
					<th scope="row"><label
							for="sikshya_enable_guest_checkout"><?php _e('Enable Guest Checkout', 'sikshya'); ?></label>
					</th>

					<td class="updated">
						<input type="checkbox" name="sikshya_enable_guest_checkout" id="sikshya_enable_guest_checkout"
							   class="switch-input"
							<?php echo 'yes' == $guest_checkout ? 'checked' : ''; ?> value="1">
						<label for="share_essentials" class="switch-label">
							<span class="toggle--on">On</span>
							<span class="toggle--off">Off</span>
						</label>
						<span class="description">
                            This option allows you to checkout without login. User will not created if you tick this option. <a
								href="https://docs.mantrabrain.com/sikshya-wordpress-plugin/sikshya-settings/"
								target="_blank">Read Documentation</a>
                        </span>

					</td>


				</tr>

			</table>

			<?php $this->next_step_buttons(); ?>
		</form>
		<?php

	}


	public function setup_step_miscellaneous_save()
	{
		check_admin_referer('sikshya-setup');

		$sikshya_enable_guest_checkout = isset($_POST['sikshya_enable_guest_checkout']) ? absint($_POST['sikshya_enable_guest_checkout']) : 0;

		$checkout_val = $sikshya_enable_guest_checkout == 1 ? 'yes' : 'no';

		update_option('sikshya_enable_guest_checkout', $checkout_val);

		wp_redirect(esc_url_raw($this->get_next_step_link()));
		exit;
	}

	public function setup_step_themes()
	{
		?>
		<h1 style="text-align: center;font-weight: bold;text-transform: uppercase;color: #18d0ab;"><?php _e('Compatible Themes for Sikshya Plugin', 'sikshya'); ?></h1>
		<form method="post">
			<?php
			//$compatible_themes = apply_filters('sikshya_must_compatible_themes', array());

			$compatible_themes = array(
				array(
					'slug' => 'yatri',
					'title' => __('Yatri', 'sikshya'),
					'demo_url' => 'https://wpyatri.com',
					'is_free' => true,
					'screenshot' => 'https://raw.githubusercontent.com/mantrabrain/yatri/master/screenshot.png',
					'landing_page' => 'https://wpyatri.com/?ref=sikshyasetup',
					'is_installable' => false,
					'download_link' => 'https://downloads.wordpress.org/theme/yatri.zip'
				)
			)
			?>
			<div class="theme-browser content-filterable rendered wpclearfix">
				<div class="themes wpclearfix">
					<?php foreach ($compatible_themes as $theme) {
						$theme_slug = isset($theme['slug']) ? $theme['slug'] : '';
						$screenshot = isset($theme['screenshot']) ? $theme['screenshot'] : '';
						$title = isset($theme['title']) ? $theme['title'] : '';
						$demo_url = isset($theme['demo_url']) ? $theme['demo_url'] : '';
						$is_installable = isset($theme['is_installable']) ? $theme['is_installable'] : false;
						$landing_page = isset($theme['landing_page']) ? $theme['landing_page'] : '';
						$download_link = isset($theme['download_link']) ? $theme['download_link'] : '';
						?>
						<div class="theme" tabindex="0"
							 aria-describedby="<?php echo esc_attr($theme_slug) ?>-action <?php echo esc_attr($theme_slug) ?>-name"
							 data-slug="<?php echo esc_attr($theme_slug) ?>">

							<div class="theme-screenshot">
								<img src="<?php echo esc_attr($screenshot); ?>" alt="<?php echo esc_attr($title); ?>">
							</div>

							<span class="more-details"
								  onclick="window.open('<?php echo esc_url($landing_page); ?>','_blank');"
								  data-details-link="<?php echo esc_url($landing_page); ?>"><?php echo __('Details &amp; Preview', 'sikshya'); ?></span>


							<div class="theme-id-container">
								<h3 class="theme-name"><?php echo esc_html($title) ?></h3>
								<div class="theme-actions">
									<a href="<?php echo esc_attr($download_link); ?>"
									   class="button button-primary theme-install"
									   data-name="<?php echo esc_attr($title) ?>"
									   data-slug="<?php echo esc_attr($theme_slug) ?>"
									   data-installable="<?php echo absint($is_installable) ?>"
									   aria-label="Install <?php echo esc_html($title) ?>"><?php echo __('Download', 'sikshya'); ?></a>
									<a href="<?php echo esc_attr($demo_url); ?>" target="_blank"
									   class="button preview install-theme-preview"><?php echo __('Preview', 'sikshya'); ?></a>

								</div>
							</div>
						</div>
					<?php } ?>
				</div>
			</div>
			<div class="wpclearfix"></div>

			<?php $this->next_step_buttons(); ?>
		</form>

		<?php
	}


	public function setup_step_themes_save()
	{
		wp_redirect(esc_url_raw($this->get_next_step_link()));
		exit;
	}

	public function setup_final_ready()
	{
		?>

		<div class="final-step">
			<h1><?php _e('Your Site is Ready!', 'sikshya'); ?></h1>

			<div class="sikshya-setup-next-steps">
				<div class="sikshya-setup-next-steps-last">
					<h2><?php _e('Next Steps &rarr;', 'sikshya'); ?></h2>


					<a class="button button-primary button-large"
					   href="<?php echo esc_url(admin_url('edit.php?post_type=tour&page=sikshya-dashboard')); ?>">
						<?php _e('Go to Dashboard!', 'sikshya'); ?>
					</a>
					<button class="button button-primary button-large sikshya-import-dummy-data"
							href="<?php echo esc_url(admin_url('edit.php?post_type=tour&page=sikshya-dashboard')); ?>">
						<?php _e('Import Sample Data', 'sikshya'); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}

return new Sikshya_Setup_Wizard();
