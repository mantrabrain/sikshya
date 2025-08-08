<?php

namespace Sikshya\Database;

use Sikshya\Core\Plugin;

/**
 * Database Management Class
 *
 * @package Sikshya\Database
 */
class Database
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Create all database tables
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Enrollments table
        $sql_enrollments = "CREATE TABLE {$wpdb->prefix}sikshya_enrollments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'enrolled',
            enrolled_date datetime NOT NULL,
            completed_date datetime NULL,
            payment_method varchar(100) NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            transaction_id varchar(255) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY status (status),
            KEY enrolled_date (enrolled_date),
            UNIQUE KEY unique_enrollment (user_id, course_id)
        ) $charset_collate;";

        // Progress table
        $sql_progress = "CREATE TABLE {$wpdb->prefix}sikshya_progress (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            lesson_id bigint(20) NULL,
            quiz_id bigint(20) NULL,
            status varchar(50) NOT NULL DEFAULT 'in_progress',
            percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            time_spent int(11) NOT NULL DEFAULT 0,
            completed_date datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY lesson_id (lesson_id),
            KEY quiz_id (quiz_id),
            KEY status (status),
            UNIQUE KEY unique_progress (user_id, course_id, lesson_id, quiz_id)
        ) $charset_collate;";

        // Certificates table
        $sql_certificates = "CREATE TABLE {$wpdb->prefix}sikshya_certificates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            certificate_number varchar(100) NOT NULL,
            issued_date datetime NOT NULL,
            expiry_date datetime NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            download_url varchar(500) NULL,
            certificate_data longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY certificate_number (certificate_number),
            KEY status (status),
            UNIQUE KEY unique_certificate (user_id, course_id)
        ) $charset_collate;";

        // Payments table
        $sql_payments = "CREATE TABLE {$wpdb->prefix}sikshya_payments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            payment_method varchar(100) NOT NULL,
            transaction_id varchar(255) NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            payment_date datetime NOT NULL,
            refund_date datetime NULL,
            refund_amount decimal(10,2) NULL,
            gateway_response longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY transaction_id (transaction_id),
            KEY status (status),
            KEY payment_date (payment_date)
        ) $charset_collate;";

        // Quiz attempts table
        $sql_quiz_attempts = "CREATE TABLE {$wpdb->prefix}sikshya_quiz_attempts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            attempt_number int(11) NOT NULL DEFAULT 1,
            score decimal(5,2) NOT NULL DEFAULT 0.00,
            total_questions int(11) NOT NULL DEFAULT 0,
            correct_answers int(11) NOT NULL DEFAULT 0,
            time_taken int(11) NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL DEFAULT 'in_progress',
            started_at datetime NOT NULL,
            completed_at datetime NULL,
            answers_data longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY course_id (course_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";

        // Quiz questions table
        $sql_quiz_questions = "CREATE TABLE {$wpdb->prefix}sikshya_quiz_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            question_text longtext NOT NULL,
            question_type varchar(50) NOT NULL DEFAULT 'multiple_choice',
            options longtext NULL,
            correct_answer longtext NULL,
            points int(11) NOT NULL DEFAULT 1,
            order_number int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id),
            KEY question_type (question_type),
            KEY order_number (order_number)
        ) $charset_collate;";

        // Lesson content table
        $sql_lesson_content = "CREATE TABLE {$wpdb->prefix}sikshya_lesson_content (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            lesson_id bigint(20) NOT NULL,
            content_type varchar(50) NOT NULL DEFAULT 'text',
            content_data longtext NOT NULL,
            file_url varchar(500) NULL,
            duration int(11) NOT NULL DEFAULT 0,
            order_number int(11) NOT NULL DEFAULT 0,
            is_required tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lesson_id (lesson_id),
            KEY content_type (content_type),
            KEY order_number (order_number)
        ) $charset_collate;";

        // User achievements table
        $sql_achievements = "CREATE TABLE {$wpdb->prefix}sikshya_achievements (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            achievement_type varchar(100) NOT NULL,
            achievement_name varchar(255) NOT NULL,
            description text NULL,
            badge_url varchar(500) NULL,
            earned_date datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY achievement_type (achievement_type),
            KEY earned_date (earned_date)
        ) $charset_collate;";

        // Notifications table
        $sql_notifications = "CREATE TABLE {$wpdb->prefix}sikshya_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            related_id bigint(20) NULL,
            related_type varchar(50) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Course reviews table
        $sql_reviews = "CREATE TABLE {$wpdb->prefix}sikshya_reviews (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            rating int(11) NOT NULL,
            review_text text NULL,
            is_approved tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY rating (rating),
            KEY is_approved (is_approved),
            UNIQUE KEY unique_review (user_id, course_id)
        ) $charset_collate;";

        // Execute all SQL statements
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_enrollments);
        dbDelta($sql_progress);
        dbDelta($sql_certificates);
        dbDelta($sql_payments);
        dbDelta($sql_quiz_attempts);
        dbDelta($sql_quiz_questions);
        dbDelta($sql_lesson_content);
        dbDelta($sql_achievements);
        dbDelta($sql_notifications);
        dbDelta($sql_reviews);

        // Update database version
        update_option('sikshya_db_version', '1.0.0');
    }

    /**
     * Get table name with prefix
     */
    public function getTableName(string $table): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sikshya_' . $table;
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table);
        $result = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        return $result === $table_name;
    }

    /**
     * Get database version
     */
    public function getVersion(): string
    {
        return get_option('sikshya_db_version', '0.0.0');
    }
} 