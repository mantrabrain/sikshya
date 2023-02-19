<?php
/**
 * About page of Mantrabrain Theme
 *
 * @package Mantrabrain
 * @subpackage Mantrabrain
 * @since 1.0.6
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Sikshya_About')) :

    class Sikshya_About
    {
        const ADMIN_PAGE = 'sikshya';

        /**
         * Constructor.
         */
        public function __construct()
        {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'about_style'));
        }

        /**
         * Add admin menu.
         */
        public function admin_menu()
        {


            add_submenu_page(
                'edit.php?post_type=sik_courses',
                __('Sikshya About', 'sikshya'),
                __('About', 'sikshya'),
                'administrator',
                'sikshya-about',
                array($this, 'about_screen')
            );
        }

        /**
         * Enqueue styles.
         */
        public function about_style($hook)
        {


            if ('sikshya_page_sikshya-about' != $hook) {
                return;
            }

            wp_enqueue_style('sikshya-about-style', plugins_url('/about.css', __FILE__), array(), SIKSHYA_VERSION);
        }


        /**
         * Welcome screen page.
         */
        public function about_screen()
        {
            ?>

            <div class="sik-about-header">
                <div class="sik-container sikshya-flex">

                    <div class="sik-product-title">
                        <h2><span class="sik-icon dashicons-before dashicons-welcome-learn-more"></span>
                            <span><?php echo __('Sikshya LMS', 'sikshya') ?></span>
                            <span class="sik-version"><?php echo esc_html(SIKSHYA_VERSION); ?></span>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="sik-about-content">
                <div class="sik-container">
                    <div id="poststuff">
                        <div id="post-body" class="columns-2">
                            <div id="post-body-content">
                                <!-- All WordPress Notices below header -->
                                <h1 class="screen-reader-text"><?php echo __('Sikshya LMS', 'sikshya') ?></h1>

                                <div class="postbox">
                                    <h2 class="hndle"><span><?php echo __('About Sikshya LMS', 'sikshya') ?></span></h2>
                                    <div class="sik-about-content-item">

                                        <p><?php
                                            echo($this->get_description()) ?></p>


                                    </div>
                                </div>

                                <div class="postbox">
                                    <h2 class="hndle"><span><?php echo('Available Shortcodes') ?></span></h2>
                                    <div class="sik-about-content-item">
                                        <code>[sikshya_registration]</code><br/><br/>
                                        <code>[sikshya_account]</code><br/><br/>
                                        <code>[sikshya_login]</code><br/><br/>

                                    </div>
                                </div>


                            </div>
                            <div class="postbox-container sik-sidebar" id="postbox-container-1">
                                <div id="side-sortables">
                                    <?php foreach ($this->get_sidebar_args() as $sidebar) { ?>
                                        <div class="postbox">
                                            <h2 class="hndle">
                                                <span class="<?php echo esc_attr($sidebar['icon']) ?>"></span>
                                                <span><?php echo esc_html($sidebar['title']) ?></span></h2>
                                            <div class="inside">
                                                <p><?php echo esc_html($sidebar['description']) ?></p>
                                                <a href="<?php echo esc_url($sidebar['link']) ?>" target="_blank"
                                                   rel="noopener"><?php echo esc_html($sidebar['link_title']) ?> »</a>
                                            </div>
                                        </div>
                                    <?php } ?>

                                </div>
                            </div>
                        </div>
                        <!-- /post-body -->
                        <br class="clear">
                    </div>
                </div>
            </div>

            <?php


        }

        private function get_sidebar_args()
        {
            return array(
                array(
                    'icon' => 'dashicons dashicons-facebook-alt',
                    'title' => __('Mantra Brain Community', 'sikshya'),
                    'description' => __('Join our facebook community group so that you can post question and help each other.', 'sikshya'),
                    'link_title' => __('Join our facebook community group', 'sikshya'),
                    'link' => 'https://www.facebook.com/groups/mantrabraincommunity'
                ),
                array(
                    'icon' => 'dashicons dashicons-welcome-write-blog',
                    'title' => __('Post question', 'sikshya'),
                    'description' => __('You can post question about mantrabrain product from here.', 'sikshya'),
                    'link_title' => __('Submit your query', 'sikshya'),
                    'link' => 'https://mantrabrain.com/contact-us/'
                )

            );

        }

        private function get_description()
        {
            $sikshya_plugin_data = get_plugin_data(SIKSHYA_FILE);

            return isset($sikshya_plugin_data['Description']) ? $sikshya_plugin_data['Description'] : '';
        }
    }

endif;

return new Sikshya_About();
