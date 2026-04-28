<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class CheckoutPageModel
{
    /**
     * @var array<string, mixed>
     */
    private array $vm;

    /**
     * @param array<string, mixed> $vm
     */
    private function __construct(array $vm)
    {
        $this->vm = $vm;
    }

    /**
     * @param array<string, mixed> $vm
     */
    public static function fromViewData(array $vm): self
    {
        return new self($vm);
    }

    /**
     * @return array<string, mixed>
     */
    public function toLegacyViewArray(): array
    {
        return $this->vm;
    }

    public function getUrls(): PublicUrlsModel
    {
        $u = $this->vm['urls'] ?? null;

        return is_array($u) ? PublicUrlsModel::fromRow($u) : new PublicUrlsModel();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLines(): array
    {
        $l = $this->vm['lines'] ?? null;

        return is_array($l) ? $l : [];
    }

    /**
     * @return int[]
     */
    public function getCourseIds(): array
    {
        $ids = $this->vm['course_ids'] ?? null;

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public function isEmpty(): bool
    {
        return !empty($this->vm['empty']);
    }

    public function getSubtotalHint(): float
    {
        return (float) ($this->vm['subtotal_hint'] ?? 0.0);
    }

    public function getCurrency(): string
    {
        return (string) ($this->vm['currency'] ?? '');
    }

    public function getRestUrl(): string
    {
        return (string) ($this->vm['rest_url'] ?? '');
    }

    public function getRestNonce(): string
    {
        return (string) ($this->vm['rest_nonce'] ?? '');
    }

    /**
     * @return array{display_name: string, email: string}
     */
    public function getViewer(): array
    {
        $v = $this->vm['viewer'] ?? null;
        if (!is_array($v)) {
            return ['display_name' => '', 'email' => ''];
        }

        return [
            'display_name' => (string) ($v['display_name'] ?? ''),
            'email' => (string) ($v['email'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    public function getCheckoutGatewayIds(): array
    {
        $ids = $this->vm['checkout_gateway_ids'] ?? null;
        if (!is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $k = sanitize_key((string) $id);
            if ($k !== '') {
                $out[] = $k;
            }
        }

        return array_values(array_unique($out));
    }
}

