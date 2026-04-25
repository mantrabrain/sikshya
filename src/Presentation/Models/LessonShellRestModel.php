<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class LessonShellRestModel
{
    public function __construct(
        private string $url,
        private string $nonce
    ) {
    }

    /**
     * @param array<string, string> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['url'] ?? ''),
            (string) ($row['nonce'] ?? '')
        );
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }
}
