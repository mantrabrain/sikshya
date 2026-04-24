<?php

namespace Sikshya\Database;

use Sikshya\Core\Plugin;
use Sikshya\Services\Settings;

/**
 * Database Management Class
 *
 * @package Sikshya\Database
 */
class Database
{
    /**
     * Bump when schema or migrations change (incremental upgrades via maybeUpgrade).
     */
    public const SCHEMA_VERSION = '1.5.0';

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

        dbDelta($this->getEnrollmentsCreateSql());
        dbDelta($sql_progress);
        dbDelta($this->getCertificatesCreateSql());
        dbDelta($this->getAssignmentSubmissionsCreateSql());
        dbDelta($sql_payments);
        dbDelta($sql_quiz_attempts);
        dbDelta($sql_quiz_questions);
        dbDelta($sql_lesson_content);
        dbDelta($sql_achievements);
        dbDelta($sql_notifications);
        dbDelta($sql_reviews);

        dbDelta($this->getQuizAttemptItemsCreateSql());
        dbDelta($this->getOrdersCreateSql());
        dbDelta($this->getOrderItemsCreateSql());
        dbDelta($this->getCouponsCreateSql());
        dbDelta($this->getCouponRedemptionsCreateSql());
    }

    /**
     * Run incremental migrations after updates (safe to call on every request; version-gated).
     */
    public function maybeUpgrade(): void
    {
        $current = Settings::getRaw('sikshya_db_version', '0');
        if (version_compare((string) $current, '1.1.0', '<')) {
            $this->migrateTo110();
            Settings::setRaw('sikshya_db_version', '1.1.0');
            $current = '1.1.0';
        }
        if (version_compare((string) $current, '1.2.0', '<')) {
            $this->migrateTo120();
            Settings::setRaw('sikshya_db_version', '1.2.0');
            $current = '1.2.0';
        }
        if (version_compare((string) $current, '1.3.0', '<')) {
            $this->migrateTo130();
            Settings::setRaw('sikshya_db_version', '1.3.0');
            $current = '1.3.0';
        }
        if (version_compare((string) $current, '1.4.0', '<')) {
            $this->migrateTo140();
            Settings::setRaw('sikshya_db_version', '1.4.0');
            $current = '1.4.0';
        }
        if (version_compare((string) $current, '1.5.0', '<')) {
            $this->migrateTo150();
            Settings::setRaw('sikshya_db_version', '1.5.0');
            $current = '1.5.0';
        }
    }

    private function migrateTo110(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($this->getQuizAttemptItemsCreateSql());
        dbDelta($this->getEnrollmentsCreateSql());
        dbDelta($this->getCertificatesCreateSql());
    }

    private function migrateTo120(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($this->getOrdersCreateSql());
        dbDelta($this->getOrderItemsCreateSql());
        dbDelta($this->getCouponsCreateSql());
        dbDelta($this->getCouponRedemptionsCreateSql());
    }

    private function migrateTo130(): void
    {
        // Hook point for future additive migrations.
    }

    private function migrateTo140(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($this->getOrdersCreateSql());

        global $wpdb;
        $table = $wpdb->prefix . 'sikshya_orders';
        $repo = new \Sikshya\Database\Repositories\OrderRepository();
        $ids = $wpdb->get_col("SELECT id FROM {$table} WHERE public_token IS NULL OR public_token = ''");
        if (!is_array($ids)) {
            return;
        }
        foreach ($ids as $id) {
            $repo->ensurePublicToken((int) $id);
        }
    }

    private function migrateTo150(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($this->getAssignmentSubmissionsCreateSql());
    }


    private function getEnrollmentsCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_enrollments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'enrolled',
            enrolled_date datetime NOT NULL,
            completed_date datetime NULL,
            payment_method varchar(100) NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            transaction_id varchar(255) NULL,
            progress decimal(5,2) NOT NULL DEFAULT 0.00,
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY status (status),
            KEY enrolled_date (enrolled_date),
            UNIQUE KEY unique_enrollment (user_id, course_id)
        ) $charset_collate;";
    }

    private function getCertificatesCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_certificates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            certificate_number varchar(100) NOT NULL,
            issued_date datetime NOT NULL,
            expiry_date datetime NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            download_url varchar(500) NULL,
            certificate_data longtext NULL,
            template_post_id bigint(20) NULL,
            verification_code varchar(64) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY certificate_number (certificate_number),
            KEY verification_code (verification_code),
            KEY status (status),
            UNIQUE KEY unique_certificate (user_id, course_id)
        ) $charset_collate;";
    }

    private function getAssignmentSubmissionsCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_assignment_submissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assignment_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            content longtext NULL,
            attachment_ids longtext NULL,
            status varchar(32) NOT NULL DEFAULT 'submitted',
            grade decimal(8,2) NULL,
            feedback longtext NULL,
            submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            graded_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY assignment_id (assignment_id),
            KEY course_id (course_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY submitted_at (submitted_at),
            UNIQUE KEY unique_submission (assignment_id, user_id)
        ) $charset_collate;";
    }

    private function getQuizAttemptItemsCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_quiz_attempt_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attempt_id bigint(20) NOT NULL,
            question_id bigint(20) NOT NULL,
            answer longtext NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            points_earned decimal(8,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attempt_id (attempt_id),
            KEY question_id (question_id)
        ) $charset_collate;";
    }

    private function getOrdersCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_orders (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            status varchar(32) NOT NULL DEFAULT 'pending',
            currency varchar(3) NOT NULL DEFAULT 'USD',
            subtotal decimal(12,2) NOT NULL DEFAULT 0.00,
            discount_total decimal(12,2) NOT NULL DEFAULT 0.00,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            gateway varchar(32) NOT NULL DEFAULT '',
            gateway_intent_id varchar(255) NULL,
            public_token varchar(32) NULL,
            coupon_id bigint(20) NULL,
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY gateway_intent_id (gateway_intent_id),
            UNIQUE KEY public_token (public_token)
        ) $charset_collate;";
    }

    private function getOrderItemsCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_order_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(12,2) NOT NULL DEFAULT 0.00,
            line_total decimal(12,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY course_id (course_id)
        ) $charset_collate;";
    }

    private function getCouponsCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_coupons (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(64) NOT NULL,
            discount_type varchar(16) NOT NULL DEFAULT 'percent',
            discount_value decimal(12,2) NOT NULL DEFAULT 0.00,
            max_uses int(11) NOT NULL DEFAULT 0,
            used_count int(11) NOT NULL DEFAULT 0,
            expires_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status)
        ) $charset_collate;";
    }

    private function getCouponRedemptionsCreateSql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}sikshya_coupon_redemptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            redeemed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY order_id (order_id)
        ) $charset_collate;";
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
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $result === $table_name;
    }

    /**
     * Get database version
     */
    public function getVersion(): string
    {
        return (string) Settings::getRaw('sikshya_db_version', '0.0.0');
    }
}
