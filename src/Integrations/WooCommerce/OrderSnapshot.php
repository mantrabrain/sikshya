<?php

declare(strict_types=1);

namespace Sikshya\Integrations\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * What Sikshya needs to know about a WooCommerce order, as plain data.
 *
 * Every decision about granting or revoking access is made from one of these,
 * never from a `WC_Order`. That keeps the rules testable on a site without
 * WooCommerce installed — which is every development and CI environment here —
 * and leaves exactly one small class, {@see WooOrderReader}, that touches
 * WooCommerce's API at all.
 *
 * @package Sikshya\Integrations\WooCommerce
 */
final class OrderSnapshot
{
    public int $orderId;
    public int $userId;
    public string $email;
    public string $firstName;
    public string $lastName;
    public string $status;

    /**
     * Course ids this order grants, with the line item each came from.
     *
     * @var array<int, array{course_id: int, item_id: int, product_id: int, refunded: bool}>
     */
    public array $lines;

    /**
     * @param array<int, array{course_id: int, item_id: int, product_id: int, refunded: bool}> $lines
     */
    public function __construct(
        int $orderId,
        int $userId,
        string $email,
        string $firstName,
        string $lastName,
        string $status,
        array $lines
    ) {
        $this->orderId = $orderId;
        $this->userId = $userId;
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->status = $status;
        $this->lines = $lines;
    }

    /** @return array<int, int> */
    public function courseIds(bool $includeRefunded = true): array
    {
        $ids = [];
        foreach ($this->lines as $line) {
            if (!$includeRefunded && !empty($line['refunded'])) {
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
     * The identifier written onto an enrolment so a refund can find exactly
     * what this order granted — and nothing else.
     */
    public function transactionId(): string
    {
        return 'wc:' . $this->orderId;
    }
}
