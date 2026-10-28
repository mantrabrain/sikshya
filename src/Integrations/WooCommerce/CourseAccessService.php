<?php

declare(strict_types=1);

namespace Sikshya\Integrations\WooCommerce;

use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Services\CourseService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Grants and revokes course access for a WooCommerce order.
 *
 * Three rules shape everything here.
 *
 * **Granting is idempotent.** WooCommerce moves an order through several
 * statuses and fires on each, gateways retry their callbacks, and shop staff
 * flip an order back and forth while sorting out a payment. Enrolling twice
 * has to be impossible, not merely unlikely.
 *
 * **Revoking is narrow.** A refund cancels only the enrolments *this order*
 * created, matched on the order's own transaction id. A learner who was also
 * enrolled by an administrator, or who bought the same course in a different
 * order, keeps that access — taking it away because of an unrelated refund is
 * the kind of mistake a customer notices from the outside.
 *
 * **Nothing is deleted.** A revoked enrolment becomes `cancelled`, so the
 * record that somebody once had access — and whatever progress they made —
 * survives. That is the same contract as deprovisioning a person.
 *
 * @package Sikshya\Integrations\WooCommerce
 */
final class CourseAccessService
{
    public const PAYMENT_METHOD = 'woocommerce';

    private EnrollmentRepository $enrolments;

    public function __construct(?EnrollmentRepository $enrolments = null)
    {
        $this->enrolments = $enrolments ?? new EnrollmentRepository();
    }

    /**
     * Enrol the buyer in everything the order grants.
     *
     * @return array{enrolled: array<int, int>, skipped: array<int, int>, user_id: int, notes: array<int, string>}
     */
    public function grant(OrderSnapshot $order): array
    {
        $notes = [];
        $userId = $order->userId;

        if ($userId <= 0) {
            $userId = $this->resolveGuest($order, $notes);
        }

        if ($userId <= 0) {
            return ['enrolled' => [], 'skipped' => $order->courseIds(), 'user_id' => 0, 'notes' => $notes];
        }

        $courseService = $this->courseService();
        $enrolled = [];
        $skipped = [];

        foreach ($order->courseIds(false) as $courseId) {
            $existing = $this->enrolments->findByUserAndCourse($userId, $courseId);

            if ($existing !== null) {
                if ((string) $existing->status === 'cancelled') {
                    // Bought again after a refund, or the shop reinstated the
                    // order. The row is reopened rather than replaced, so the
                    // learner's progress against it is still theirs.
                    $this->enrolments->update((int) $existing->id, [
                        'status' => 'enrolled',
                        'transaction_id' => $order->transactionId(),
                    ]);
                    $enrolled[] = $courseId;
                    continue;
                }

                $skipped[] = $courseId;
                continue;
            }

            if ($courseService === null) {
                $skipped[] = $courseId;
                continue;
            }

            try {
                $enrolmentId = $courseService->enrollUser($userId, $courseId, [
                    // The buyer has paid in WooCommerce; the price check here
                    // exists to stop *free* enrolment into a paid course, and
                    // this is the documented way past it.
                    'bypass_price_check' => true,
                    'payment_method' => self::PAYMENT_METHOD,
                    'transaction_id' => $order->transactionId(),
                ]);
            } catch (\Throwable $e) {
                $skipped[] = $courseId;
                $notes[] = $e->getMessage();
                continue;
            }

            if ($enrolmentId > 0) {
                $enrolled[] = $courseId;
            } else {
                $skipped[] = $courseId;
            }
        }

        if ($enrolled !== []) {
            /**
             * After a WooCommerce order has granted course access.
             *
             * @param int              $userId
             * @param array<int, int>  $enrolled Course ids.
             * @param OrderSnapshot    $order
             */
            do_action('sikshya_woocommerce_access_granted', $userId, $enrolled, $order);
        }

        return ['enrolled' => $enrolled, 'skipped' => $skipped, 'user_id' => $userId, 'notes' => $notes];
    }

    /**
     * Close the enrolments this order created.
     *
     * @return array{revoked: array<int, int>, kept: array<int, int>}
     */
    public function revoke(OrderSnapshot $order, bool $refundedLinesOnly = false): array
    {
        $userId = $order->userId > 0 ? $order->userId : $this->userIdForEmail($order->email);
        if ($userId <= 0) {
            return ['revoked' => [], 'kept' => []];
        }

        $courseIds = $refundedLinesOnly ? $this->refundedCourseIds($order) : $order->courseIds();
        $revoked = [];
        $kept = [];

        foreach ($courseIds as $courseId) {
            $existing = $this->enrolments->findByUserAndCourse($userId, $courseId);
            if ($existing === null) {
                continue;
            }

            if ((string) $existing->transaction_id !== $order->transactionId()) {
                // Access this order did not grant. Revoking it would take away
                // something a person was given for another reason entirely.
                $kept[] = $courseId;
                continue;
            }

            if ((string) $existing->status === 'cancelled') {
                continue;
            }

            $this->enrolments->update((int) $existing->id, ['status' => 'cancelled']);
            $revoked[] = $courseId;
        }

        if ($revoked !== []) {
            /**
             * After a WooCommerce refund or cancellation closed course access.
             *
             * @param int              $userId
             * @param array<int, int>  $revoked Course ids.
             * @param OrderSnapshot    $order
             */
            do_action('sikshya_woocommerce_access_revoked', $userId, $revoked, $order);
        }

        return ['revoked' => $revoked, 'kept' => $kept];
    }

    /** @return array<int, int> */
    private function refundedCourseIds(OrderSnapshot $order): array
    {
        $ids = [];
        foreach ($order->lines as $line) {
            if (empty($line['refunded'])) {
                continue;
            }
            $courseId = (int) ($line['course_id'] ?? 0);
            if ($courseId > 0) {
                $ids[$courseId] = $courseId;
            }
        }

        return array_values($ids);
    }

    /**
     * A buyer who checked out without an account.
     *
     * An account is created from the billing address, because the alternative
     * is a purchase that grants nothing: course access belongs to a person,
     * and WooCommerce is happy to take money from a guest. The password is
     * never chosen here — WordPress sends the new user a link to set their own.
     *
     * Sites that would rather refuse the enrolment can turn this off with the
     * `sikshya_woocommerce_create_account_for_guest` filter, and should then
     * require an account at checkout, or the money arrives and the course does
     * not.
     *
     * @param array<int, string> $notes
     */
    private function resolveGuest(OrderSnapshot $order, array &$notes): int
    {
        $email = sanitize_email($order->email);
        if ($email === '' || !is_email($email)) {
            $notes[] = __('The order has no customer account and no usable billing email, so nobody could be enrolled.', 'sikshya');

            return 0;
        }

        $existing = $this->userIdForEmail($email);
        if ($existing > 0) {
            // Bought as a guest with the email of an existing account. Giving
            // the course to that account is what the buyer expects; creating a
            // second account for the same address is not possible anyway.
            return $existing;
        }

        /**
         * Whether a guest order may create an account.
         *
         * @param bool          $allowed
         * @param OrderSnapshot $order
         */
        if (!apply_filters('sikshya_woocommerce_create_account_for_guest', true, $order)) {
            $notes[] = __('Guest account creation is switched off, so this order granted no access.', 'sikshya');

            return 0;
        }

        $login = $this->uniqueLogin($email);
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => wp_generate_password(24, true, true),
            'first_name' => sanitize_text_field($order->firstName),
            'last_name' => sanitize_text_field($order->lastName),
            'display_name' => trim(sanitize_text_field($order->firstName . ' ' . $order->lastName)),
            'role' => (string) apply_filters('sikshya_woocommerce_guest_role', 'sikshya_student', $order),
        ]);

        if (is_wp_error($userId)) {
            $notes[] = $userId->get_error_message();

            return 0;
        }

        // The buyer needs a way in, and they never chose a password.
        if (function_exists('wp_new_user_notification')) {
            wp_new_user_notification((int) $userId, null, 'user');
        }

        return (int) $userId;
    }

    private function uniqueLogin(string $email): string
    {
        $base = sanitize_user((string) strstr($email, '@', true), true);
        if ($base === '') {
            $base = 'customer';
        }

        $login = $base;

        /*
         * `username_exists()` returns an id or **false**, not null — so a
         * `!== null` test here never ends, and the first guest checkout spins
         * the request querying the users table until PHP gives up. Found by
         * the test suite hanging on exactly that, which is a cheaper way to
         * find it than a customer's checkout timing out.
         *
         * The ceiling is a second guard: a login this crowded means something
         * else is wrong, and a numeric suffix off the email is better than a
         * loop.
         */
        for ($suffix = 2; $suffix <= 50 && username_exists($login); $suffix++) {
            $login = $base . '-' . $suffix;
        }

        if (username_exists($login)) {
            $login = $base . '-' . wp_generate_password(6, false, false);
        }

        return $login;
    }

    private function userIdForEmail(string $email): int
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return 0;
        }

        $user = get_user_by('email', $email);

        return $user ? (int) $user->ID : 0;
    }

    private function courseService(): ?CourseService
    {
        $service = Plugin::getInstance()->getService('course');

        return $service instanceof CourseService ? $service : null;
    }
}
