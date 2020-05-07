<?php
/**
 * Sikshya_Taxonomy
 *
 * @package Sikshya
 * @since   1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Sikshya Metabox Class.
 *
 * @class Sikshya
 */
class Sikshya_Taxonomy
{


    /**
     * The single instance of the class.
     *
     * @var Sikshya
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * The single instance of the class.
     *
     * @var Sikshya_Taxonomy_Course_Category
     * @since 1.0.0
     */
    public $course_taxonomy;


  /**
     * The single instance of the class.
     *
     * @var Sikshya_Taxonomy_Course_Tag
     * @since 1.0.0
     */
    public $sik_course_tag;


    /**
     * Main Sikshya Instance.
     *
     * Ensures only one instance of Sikshya is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return Sikshya - Sikshya_Taxonomy
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     * Hook into actions and filters.
     *
     * @since 1.0.0
     */
    public function load()
    {

        $this->course_taxonomy = new Sikshya_Taxonomy_Course_Category();
        $this->sik_course_tag= new Sikshya_Taxonomy_Course_Tag();


    }

    public function init_taxonomy()
    {
        $this->course_taxonomy->init();
        $this->sik_course_tag->init();
    }

    public function init()
    {
        $this->load();
        $this->init_taxonomy();

    }


}

Sikshya_Taxonomy::instance()->init();
