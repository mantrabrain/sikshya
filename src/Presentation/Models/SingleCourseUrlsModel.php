<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class SingleCourseUrlsModel
{
    /**
     * @var array<string, string>
     */
    private array $row;

    /**
     * @param array<string, string> $row
     */
    public function __construct(array $row = [])
    {
        $this->row = $row;
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

    public function getCartUrl(): string
    {
        return (string) ($this->row['cart'] ?? '');
    }

    public function getCheckoutUrl(): string
    {
        return (string) ($this->row['checkout'] ?? '');
    }

    public function getLearnUrl(): string
    {
        return (string) ($this->row['learn'] ?? '');
    }

    public function getLearnFirstUrl(): string
    {
        return (string) ($this->row['learn_first'] ?? '');
    }

    public function getAccountUrl(): string
    {
        return (string) ($this->row['account'] ?? '');
    }

    public function getCoursesArchiveUrl(): string
    {
        return (string) ($this->row['courses_archive'] ?? '');
    }

    public function getLoginUrl(): string
    {
        return (string) ($this->row['login'] ?? '');
    }
}

