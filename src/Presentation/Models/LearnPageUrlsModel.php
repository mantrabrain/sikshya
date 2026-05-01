<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class LearnPageUrlsModel
{
    private string $account;

    private string $course;

    private string $learn;

    private string $coursesArchive;

    private string $login;

    public function __construct(
        string $account = '',
        string $course = '',
        string $learn = '',
        string $coursesArchive = '',
        string $login = ''
    ) {
        $this->account = $account;
        $this->course = $course;
        $this->learn = $learn;
        $this->coursesArchive = $coursesArchive;
        $this->login = $login;
    }

    /**
     * @param array<string, string> $row
     */
    public static function fromUrlRow(array $row): self
    {
        return new self(
            (string) ($row['account'] ?? ''),
            (string) ($row['course'] ?? ''),
            (string) ($row['learn'] ?? ''),
            (string) ($row['courses_archive'] ?? ''),
            (string) ($row['login'] ?? '')
        );
    }

    public function getAccountUrl(): string
    {
        return $this->account;
    }

    public function getCourseUrl(): string
    {
        return $this->course;
    }

    public function getLearnUrl(): string
    {
        return $this->learn;
    }

    public function getCoursesArchiveUrl(): string
    {
        return $this->coursesArchive;
    }

    public function getLoginUrl(): string
    {
        return $this->login;
    }
}
