<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminConfig;
use Sikshya\Admin\ReactAdminView;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\ReportsRepository;

/**
 * Reports data for the React admin (snapshot API). Legacy PHP report views and admin-ajax removed.
 *
 * @package Sikshya\Admin\Controllers
 */
class ReportController
{
    /**
     * @var array|null
     */
    private static $reports_snapshot_cache = null;

    protected Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @return array{stats: array<string, mixed>, chart: array{labels: string[], counts: int[]}}
     */
    public static function getReportsPageSnapshot(): array
    {
        if (null !== self::$reports_snapshot_cache) {
            return self::$reports_snapshot_cache;
        }

        $repo = new ReportsRepository();
        self::$reports_snapshot_cache = [
            'stats' => $repo->getOverviewStats(),
            'chart' => $repo->getEnrollmentChartLast12Months(),
        ];

        return self::$reports_snapshot_cache;
    }

    public static function clearReportsSnapshotCache(): void
    {
        self::$reports_snapshot_cache = null;
    }

    public function renderReportsPage(): void
    {
        ReactAdminView::render('reports', ReactAdminConfig::reportsInitialData());
    }
}
