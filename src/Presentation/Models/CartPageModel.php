<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class CartPageModel
{
    /**
     * @param array<string, mixed> $vm
     */
    private function __construct(private array $vm)
    {
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

    public function isEmpty(): bool
    {
        return $this->getLines() === [];
    }

    public function getSubtotalHint(): float
    {
        return (float) ($this->vm['subtotal_hint'] ?? 0.0);
    }

    public function getCurrency(): string
    {
        return (string) ($this->vm['currency'] ?? '');
    }

    public function getBundleId(): int
    {
        return (int) ($this->vm['bundle_id'] ?? 0);
    }

    public function getBundleTitle(): string
    {
        return (string) ($this->vm['bundle_title'] ?? '');
    }
}

