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
    private string $url;

    private string $nonce;

    public function __construct(string $url, string $nonce)
    {
        $this->url = $url;
        $this->nonce = $nonce;
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
