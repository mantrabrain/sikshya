<?php
/**
 * Sikshya_Metabox
 *
 * @package Sikshya
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Sikshya Metabox Class.
 *
 * @class Sikshya
 */
class Sikshya_Metabox
{


    /**
     * The single instance of the class.
     *
     * @var Sikshya
     * @since 1.0.0
     */
    protected static $_instance = null;


    public $course;

    public $section;

    public $lesson;

    /**
     * Main Sikshya Instance.
     *
     * Ensures only one instance of Sikshya is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return Sikshya - Sikshya_Metabox
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     * Sikshya Constructor.
     */
    public function __construct()
    {
        $this->init();
    }


    /**
     * Hook into actions and filters.
     *
     * @since 1.0.0
     */
    private function init()
    {

        $this->course = new Sikshya_Metabox_Course();
        $this->section = new Sikshya_Metabox_Section();
        $this->section = new Sikshya_Metabox_Lesson();

    }


}

return Sikshya_Metabox::instance();
