<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class LessonShellUrlsModel
{
    private string $courses;

    private string $login;

    private string $course;

    private string $learn;

    private string $account;

    public function __construct(
        string $courses = '',
        string $login = '',
        string $course = '',
        string $learn = '',
        string $account = ''
    ) {
        $this->courses = $courses;
        $this->login = $login;
        $this->course = $course;
        $this->learn = $learn;
        $this->account = $account;
    }

    /**
     * @param array<string, string> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['courses'] ?? ''),
            (string) ($row['login'] ?? ''),
            (string) ($row['course'] ?? ''),
            (string) ($row['learn'] ?? ''),
            (string) ($row['account'] ?? '')
        );
    }

    public function getCoursesArchiveUrl(): string
    {
        return $this->courses;
    }

    public function getLoginUrl(): string
    {
        return $this->login;
    }

    public function getCourseUrl(): string
    {
        return $this->course;
    }

    public function getLearnUrl(): string
    {
        return $this->learn;
    }

    public function getAccountUrl(): string
    {
        return $this->account;
    }
}
