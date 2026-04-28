<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class OrderPageModel
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

    public function getError(): string
    {
        return (string) ($this->vm['error'] ?? '');
    }

    public function hasError(): bool
    {
        return $this->getError() !== '';
    }

    public function getOrder(): ?object
    {
        $o = $this->vm['order'] ?? null;

        return is_object($o) ? $o : null;
    }

    /**
     * @return array<int, object>
     */
    public function getItems(): array
    {
        $it = $this->vm['items'] ?? null;

        return is_array($it) ? $it : [];
    }

    public function getOfflineInstructionsHtml(): string
    {
        return (string) ($this->vm['offline_instructions_html'] ?? '');
    }

    public function getStatusLabel(): string
    {
        return (string) ($this->vm['status_label'] ?? '');
    }

    public function getGatewayLabel(): string
    {
        return (string) ($this->vm['gateway_label'] ?? '');
    }
}

