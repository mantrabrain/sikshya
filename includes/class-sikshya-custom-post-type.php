<?php
/**
 * Sikshya_Custom_Post_Type
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
class Sikshya_Custom_Post_Type
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
	 * @var Sikshya_Custom_Post_Type_Course
	 * @since 1.0.0
	 */
	public $course;

	/**
	 * The single instance of the class.
	 *
	 * @var Sikshya_Custom_Post_Type_Lesson
	 * @since 1.0.0
	 */
	public $lesson;

	/**
	 * The single instance of the class.
	 *
	 * @var Sikshya_Custom_Post_Type_Section
	 * @since 1.0.0
	 */
	public $section;

	/**
	 * The single instance of the class.
	 *
	 * @var Sikshya_Custom_Post_Type_Quiz
	 * @since 1.0.0
	 */
	public $quiz;

	/**
	 * The single instance of the class.
	 *
	 * @var Sikshya_Custom_Post_Type_Question
	 * @since 1.0.0
	 */
	public $question;


	/**
	 * The single instance of the class.
	 *
	 * @var Sikshya_Custom_Post_Type_Order
	 * @since 1.0.0
	 */
	public $order;

	/**
	 * The single instance of the class.
	 *
	 * @var Sikshya_Custom_Post_Type_Students
	 * @since 1.0.0
	 */
	public $student;


	/**
	 * Main Sikshya Instance.
	 *
	 * Ensures only one instance of Sikshya is loaded or can be loaded.
	 *
	 * @return Sikshya_Custom_Post_Type - Sikshya_Custom_Post_Type
	 * @since 1.0.0
	 * @static
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	public function maybe_flush_rewrite_rules()
	{
		if ('yes' === get_option('sikshya_queue_flush_rewrite_rules')) {
			update_option('sikshya_queue_flush_rewrite_rules', 'no');
			$this->flush_rewrite_rules();
		}
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	public function load()
	{

		$this->course = new Sikshya_Custom_Post_Type_Course();
		$this->lesson = new Sikshya_Custom_Post_Type_Lesson();
		$this->section = new Sikshya_Custom_Post_Type_Section();
		$this->quiz = new Sikshya_Custom_Post_Type_Quiz();
		$this->question = new Sikshya_Custom_Post_Type_Question();
		$this->order = new Sikshya_Custom_Post_Type_Order();
		$this->student = new Sikshya_Custom_Post_Type_Students();

	}

	/**
	 * Flush rewrite rules.
	 */
	public function flush_rewrite_rules()
	{
		flush_rewrite_rules();
	}

	public function hooks()
	{
		add_action('sikshya_flush_rewrite_rules', array($this, 'flush_rewrite_rules'));
		add_action('sikshya_after_register_post_type', array($this, 'maybe_flush_rewrite_rules'));


	}

	public function init_cpt()
	{
		$this->course->init();
		$this->lesson->init();
		$this->section->init();
		$this->quiz->init();
		$this->question->init();
		$this->order->init();
		$this->student->init();


	}

	public function init()
	{
		$this->hooks();
		$this->load();
		$this->init_cpt();

	}

}

Sikshya_Custom_Post_Type::instance()->init();
