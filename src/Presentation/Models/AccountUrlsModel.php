<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class AccountUrlsModel
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

    public function getHomeUrl(): string
    {
        return (string) ($this->row['home'] ?? '');
    }

    public function getAccountUrl(): string
    {
        return (string) ($this->row['account'] ?? '');
    }

    public function getDashboardUrl(): string
    {
        return (string) ($this->row['account_dashboard'] ?? '');
    }

    public function getLearningUrl(): string
    {
        return (string) ($this->row['account_learning'] ?? '');
    }

    public function getPaymentsUrl(): string
    {
        return (string) ($this->row['account_payments'] ?? '');
    }

    public function getQuizAttemptsUrl(): string
    {
        return (string) ($this->row['account_quiz_attempts'] ?? '');
    }

    public function getProfileUrl(): string
    {
        return (string) ($this->row['account_profile'] ?? '');
    }

    public function getCertificatesUrl(): string
    {
        return (string) ($this->row['account_certificates'] ?? '');
    }

    public function getLearnHubUrl(): string
    {
        return (string) ($this->row['learn'] ?? '');
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

    public function getAddNewCourseUrl(): string
    {
        return (string) ($this->row['add_new_course'] ?? '');
    }

    public function getEditCoursesUrl(): string
    {
        return (string) ($this->row['edit_courses'] ?? '');
    }
}

