<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class PublicUrlsModel
{
    /**
     * @param array<string, string> $row
     */
    public function __construct(private array $row = [])
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $out = [];
        foreach ($row as $k => $v) {
            $k = (string) $k;
            if ($k === '') {
                continue;
            }
            $out[$k] = (string) $v;
        }

        return new self($out);
    }

    public function getHomeUrl(): string
    {
        return (string) ($this->row['home'] ?? '');
    }

    public function getCartUrl(): string
    {
        return (string) ($this->row['cart'] ?? '');
    }

    public function getCheckoutUrl(): string
    {
        return (string) ($this->row['checkout'] ?? '');
    }

    public function getCoursesUrl(): string
    {
        return (string) ($this->row['courses'] ?? '');
    }

    public function getAccountUrl(): string
    {
        return (string) ($this->row['account'] ?? '');
    }
}

