<?php

/**
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Database\Repositories\OrderRepository;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates a numbered invoice record on successful payment (order fulfilled → paid).
 *
 * Invoice data is stored in {@see OrderRepository} row `meta` JSON under `invoice`.
 */
final class InvoiceIssuanceService
{
    private const COUNTER_OPTION = 'sikshya_invoice_next_seq';

    private OrderRepository $orders;

    public function __construct(?OrderRepository $orders = null)
    {
        $this->orders = $orders ?? new OrderRepository();
    }

    /**
     * @param object $order Snapshot passed to {@see 'sikshya_order_fulfilled'} (may be stale vs DB).
     */
    public function maybeIssueForFulfilledOrder(int $order_id, $order): void
    {
        $order_id = max(0, $order_id);
        if ($order_id <= 0 || !$this->orders->tableExists()) {
            return;
        }

        if (!Settings::isTruthy(Settings::get('auto_generate_invoices', false))) {
            return;
        }

        $fresh = $this->orders->findById($order_id);
        if (!$fresh || (string) ($fresh->status ?? '') !== 'paid') {
            return;
        }

        $meta = $this->decodeMeta($fresh->meta ?? null);
        if (isset($meta['invoice']) && is_array($meta['invoice']) && !empty($meta['invoice']['number'])) {
            return;
        }

        $number = $this->allocateInvoiceNumber();
        if ($number === '') {
            return;
        }

        $issued_at = current_time('mysql');
        $meta['invoice'] = [
            'number' => $number,
            'issued_at' => $issued_at,
            'issued_at_gmt' => current_time('mysql', true),
        ];

        $this->orders->updateOrder($order_id, ['meta' => $meta]);

        /**
         * After an invoice row is written to order meta.
         *
         * @param int                 $order_id
         * @param array<string,mixed> $invoice  Invoice meta (`number`, `issued_at`, …)
         * @param object              $fresh    Latest order row.
         */
        do_action('sikshya_invoice_issued', $order_id, (array) $meta['invoice'], $fresh);
    }

    /**
     * @param mixed $meta_json
     *
     * @return array<string, mixed>
     */
    private function decodeMeta($meta_json): array
    {
        if (!is_string($meta_json) || $meta_json === '') {
            return [];
        }
        $decoded = json_decode($meta_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function allocateInvoiceNumber(): string
    {
        $prefix = (string) Settings::get('invoice_prefix', 'INV-');
        $prefix = $prefix !== '' ? $prefix : 'INV-';

        // Monotonic sequence (best-effort).
        $seq = (int) Settings::getRaw(self::COUNTER_OPTION, 1);
        if ($seq < 1) {
            $seq = 1;
        }
        $next = $seq + 1;
        Settings::setRaw(self::COUNTER_OPTION, $next);

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
