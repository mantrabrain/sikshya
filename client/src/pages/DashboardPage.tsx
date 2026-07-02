import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { MetricTile, QuickActionCard } from '../components/dashboard';
import { NavIcon } from '../components/NavIcon';
import { ButtonPrimary } from '../components/shared/buttons';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPanel } from '../components/shared/list/ListPanel';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { getLicensing } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';
import { __, _n, sprintf } from '../lib/i18n';

type DashboardStats = {
  publishedCourses: number;
  draftCourses?: number;
  lessons?: number;
  quizzes?: number;
  assignments?: number;
  questions?: number;
  chapters?: number;
  certificateTemplates?: number;
  students: number;
  instructors?: number;
  revenue: string;
  enrollments: number;
  completedEnrollments?: number;
  distinctLearners?: number;
  hasEnrollmentTable?: boolean;
  hasPaymentsTable?: boolean;
};

type RecentCourse = {
  id: number;
  title: string;
  status: string;
  modified: string;
};

function resolveDashboardData(config: SikshyaReactConfig): {
  siteName: string;
  stats: DashboardStats;
  recentCourses: RecentCourse[];
  dashboardLinks: { enrollments: boolean; payments: boolean };
} {
  const raw = config.initialData as {
    siteName?: string;
    stats?: Partial<DashboardStats>;
    recentCourses?: RecentCourse[];
    dashboardLinks?: { enrollments?: boolean; payments?: boolean };
  };

  const stats: DashboardStats = {
    publishedCourses: raw.stats?.publishedCourses ?? 0,
    draftCourses: raw.stats?.draftCourses ?? 0,
    lessons: raw.stats?.lessons ?? 0,
    quizzes: raw.stats?.quizzes ?? 0,
    assignments: raw.stats?.assignments ?? 0,
    questions: raw.stats?.questions ?? 0,
    chapters: raw.stats?.chapters ?? 0,
    certificateTemplates: raw.stats?.certificateTemplates ?? 0,
    students: raw.stats?.students ?? 0,
    instructors: raw.stats?.instructors ?? 0,
    revenue: raw.stats?.revenue ?? '$0.00',
    enrollments: raw.stats?.enrollments ?? 0,
    completedEnrollments: raw.stats?.completedEnrollments ?? 0,
    distinctLearners: raw.stats?.distinctLearners ?? 0,
    hasEnrollmentTable: raw.stats?.hasEnrollmentTable ?? false,
    hasPaymentsTable: raw.stats?.hasPaymentsTable ?? false,
  };

  return {
    siteName: typeof raw.siteName === 'string' ? raw.siteName : '',
    stats,
    recentCourses: Array.isArray(raw.recentCourses) ? raw.recentCourses : [],
    dashboardLinks: {
      enrollments: Boolean(raw.dashboardLinks?.enrollments),
      payments: Boolean(raw.dashboardLinks?.payments),
    },
  };
}

function greetingLabel(): string {
  const h = new Date().getHours();
  if (h < 12) {
    return __('Good morning', 'sikshya');
  }
  if (h < 18) {
    return __('Good afternoon', 'sikshya');
  }
  return __('Good evening', 'sikshya');
}

type OverviewPayload = {
  siteName?: string;
  stats?: Partial<DashboardStats>;
  recentCourses?: RecentCourse[];
  dashboardLinks?: { enrollments?: boolean; payments?: boolean };
};

export function DashboardPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const [createOpen, setCreateOpen] = useState(false);
  const boot = useMemo(() => resolveDashboardData(config), [config.initialData]);
  const [live, setLive] = useState<typeof boot | null>(null);

  const { siteName, stats, recentCourses, dashboardLinks } = live ?? boot;

  const refreshOverview = useCallback(async () => {
    try {
      const d = await getSikshyaApi().get<OverviewPayload>(SIKSHYA_ENDPOINTS.admin.overview);
      setLive((prev) => {
        const cur = prev ?? boot;
        return {
          siteName: typeof d.siteName === 'string' ? d.siteName : cur.siteName,
          stats: { ...cur.stats, ...(d.stats || {}) },
          recentCourses: Array.isArray(d.recentCourses) ? d.recentCourses : cur.recentCourses,
          dashboardLinks: {
            enrollments: Boolean(d.dashboardLinks?.enrollments ?? cur.dashboardLinks.enrollments),
            payments: Boolean(d.dashboardLinks?.payments ?? cur.dashboardLinks.payments),
          },
        };
      });
    } catch {
      /* keep SSR / boot data */
    }
  }, [boot]);

  useEffect(() => {
    void refreshOverview();
  }, [refreshOverview]);

  const dateLine = useMemo(() => {
    try {
      return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
      }).format(new Date());
    } catch {
      return '';
    }
  }, []);

  const draftHint =
    (stats.draftCourses ?? 0) > 0
      ? sprintf(
          _n(
            '%d draft course not yet published',
            '%d draft courses not yet published',
            stats.draftCourses ?? 0,
            'sikshya'
          ),
          stats.draftCourses ?? 0
        )
      : __('All courses are either live or archived', 'sikshya');

  const enrollmentHint = stats.hasEnrollmentTable
    ? sprintf(__(' %d marked completed', 'sikshya'), stats.completedEnrollments ?? 0).trimStart()
    : __('Enrollments table not found — activate the plugin or run DB updates', 'sikshya');

  const licensing = getLicensing(config);

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle={__('Course health, learners, and shortcuts in one place', 'sikshya')}
    >
      <CreateCourseModal config={config} open={createOpen} onClose={() => setCreateOpen(false)} />
      <div className="w-full min-w-0 space-y-8">
        {/*
         * Dashboard hero — subtle brand tint. We landed here after two rounds:
         * the original brand→accent gradient with decorative blurs was too
         * heavy, plain white read as flat. A faint diagonal brand-50 → white
         * wash carries a hint of the plugin identity without competing with
         * the widgets below. Border also tinted (brand-100) so the edge
         * doesn't feel neutrally-outlined against the coloured fill.
         */}
        <section className="rounded-2xl border border-blue-100 bg-blue-50 px-6 py-8 shadow-sm dark:border-blue-900/40 dark:bg-blue-950/30">
          <p className="text-sm font-medium text-blue-700/80 dark:text-blue-300/80">{dateLine}</p>
          <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
            {greetingLabel()}, {config.user.name}
          </h1>
          <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {siteName ? (
              <>
                You are managing <span className="font-semibold text-slate-900 dark:text-white">{siteName}</span>. Track catalog
                growth, learner sign-ups, and revenue from this overview.
              </>
            ) : (
              <>{__('Track catalog growth, learner sign-ups, and revenue from this overview.', 'sikshya')}</>
            )}
          </p>
        </section>

        {licensing && !licensing.isProActive ? (
          <section
            className="rounded-2xl border border-accent-200/90 bg-accent-50/90 px-4 py-3 text-sm text-accent-950 shadow-sm dark:border-accent-900/50 dark:bg-accent-950/50 dark:text-accent-100"
            aria-label={__('Advanced LMS capabilities', 'sikshya')}
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="min-w-0 leading-relaxed">
                <span className="font-semibold">{__('Scale your course business', 'sikshya')}</span>
                {' — '}
                {__('Scheduled lesson access, subscriptions, gradebook, shared course staff, richer certificates, and more. Your admin layout stays familiar; upgraded plans unlock the behaviour behind each screen.', 'sikshya')}
              </p>
              <a
                href={licensing.upgradeUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="shrink-0 rounded-xl bg-accent-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-accent-800 dark:bg-accent-600 dark:hover:bg-accent-500"
              >
                View plans
              </a>
            </div>
          </section>
        ) : null}

        <section aria-labelledby="dash-primary-kpis">
          <h2 id="dash-primary-kpis" className="sr-only">
            Primary metrics
          </h2>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricTile
              accent="brand"
              label={__('Published courses', 'sikshya')}
              value={stats.publishedCourses}
              hint={__('Live in your catalog', 'sikshya')}
              icon={<NavIcon name="course" className="h-5 w-5" />}
            />
            <MetricTile
              accent="emerald"
              label={__('Students', 'sikshya')}
              value={stats.students}
              hint={__('Users with the Sikshya student role', 'sikshya')}
              icon={<NavIcon name="users" className="h-5 w-5" />}
            />
            <MetricTile
              accent="violet"
              label={__('Total revenue', 'sikshya')}
              value={stats.revenue}
              hint={
                stats.hasPaymentsTable
                  ? __('Sum of completed payments in Sikshya', 'sikshya')
                  : __('Payments table not found — revenue may stay at zero', 'sikshya')
              }
              icon={<NavIcon name="chart" className="h-5 w-5" />}
            />
            <MetricTile
              accent="amber"
              label={__('Active enrollments', 'sikshya')}
              value={stats.enrollments}
              hint={enrollmentHint}
              icon={<NavIcon name="badge" className="h-5 w-5" />}
            />
          </div>
        </section>

        <section aria-labelledby="dash-content-kpis">
          <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
            <h2 id="dash-content-kpis" className="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Content library
            </h2>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <MetricTile
              accent="slate"
              label={__('Draft courses', 'sikshya')}
              value={stats.draftCourses ?? 0}
              hint={draftHint}
              icon={<NavIcon name="table" className="h-5 w-5" />}
            />
            <MetricTile
              accent="sky"
              label={__('Lessons', 'sikshya')}
              value={stats.lessons ?? 0}
              hint={__('Published lesson posts', 'sikshya')}
              icon={<NavIcon name="bookOpen" className="h-5 w-5" />}
            />
            <MetricTile
              accent="sky"
              label={__('Quizzes', 'sikshya')}
              value={stats.quizzes ?? 0}
              hint={__('Published quiz posts', 'sikshya')}
              icon={<NavIcon name="puzzle" className="h-5 w-5" />}
            />
            <MetricTile
              accent="sky"
              label={__('Assignments', 'sikshya')}
              value={stats.assignments ?? 0}
              hint={__('Published assignment posts', 'sikshya')}
              icon={<NavIcon name="clipboard" className="h-5 w-5" />}
            />
          </div>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <MetricTile
              accent="emerald"
              label={__('Instructors', 'sikshya')}
              value={stats.instructors ?? 0}
              hint={__('Users with the instructor role', 'sikshya')}
              icon={<NavIcon name="userCircle" className="h-5 w-5" />}
            />
            <MetricTile
              accent="slate"
              label={__('Certificate templates', 'sikshya')}
              value={stats.certificateTemplates ?? 0}
              hint={__('Published certificate designs', 'sikshya')}
              icon={<NavIcon name="badge" className="h-5 w-5" />}
            />
            <MetricTile
              accent="slate"
              label={__('Questions', 'sikshya')}
              value={stats.questions ?? 0}
              hint={__('Published question bank items', 'sikshya')}
              icon={<NavIcon name="helpCircle" className="h-5 w-5" />}
            />
            <MetricTile
              accent="slate"
              label={__('Chapters', 'sikshya')}
              value={stats.chapters ?? 0}
              hint={__('Published chapter posts', 'sikshya')}
              icon={<NavIcon name="layers" className="h-5 w-5" />}
            />
          </div>
        </section>

        <div className="grid items-start gap-6 xl:grid-cols-5">
          <section className="xl:col-span-3" aria-labelledby="dash-recent-courses">
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 id="dash-recent-courses" className="text-base font-semibold text-slate-900 dark:text-white">
                Recently updated courses
              </h2>
              <a
                href={appViewHref(config, 'courses')}
                className="text-sm font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
              >
                View all
              </a>
            </div>
            <ListPanel>
              {recentCourses.length === 0 ? (
                <ListEmptyState
                  compact
                  title={__('No courses yet', 'sikshya')}
                  description={__('Create your first course to see recent activity and status here.', 'sikshya')}
                  action={<ButtonPrimary onClick={() => setCreateOpen(true)}>{__('Create a course', 'sikshya')}</ButtonPrimary>}
                />
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                      <tr>
                        <th scope="col" className="px-5 py-3.5">
                          Course
                        </th>
                        <th scope="col" className="px-5 py-3.5">
                          Status
                        </th>
                        <th scope="col" className="px-5 py-3.5">
                          Updated
                        </th>
                        <th scope="col" className="w-12 px-5 py-3.5 text-right">
                          <span className="sr-only">{__('Open', 'sikshya')}</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                      {recentCourses.map((row) => (
                        <tr
                          key={row.id}
                          className="bg-white transition-colors hover:bg-slate-50/80 dark:bg-slate-900 dark:hover:bg-slate-800/60"
                        >
                          <td className="px-5 py-3.5">
                            <a
                              href={appViewHref(config, 'add-course', { course_id: String(row.id) })}
                              className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                            >
                              {row.title || '(No title)'}
                            </a>
                          </td>
                          <td className="px-5 py-3.5">
                            <StatusBadge status={row.status} />
                          </td>
                          <td className="whitespace-nowrap px-5 py-3.5 text-slate-600 dark:text-slate-400">
                            {formatPostDate(row.modified)}
                          </td>
                          <td className="px-5 py-3.5 text-right">
                            <a
                              href={appViewHref(config, 'add-course', { course_id: String(row.id) })}
                              className="inline-flex rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                              title={__('Open in course builder', 'sikshya')}
                              aria-label={__('Open in course builder', 'sikshya')}
                            >
                              <NavIcon name="chevronRight" className="h-4 w-4" />
                            </a>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </ListPanel>
          </section>

          <section className="space-y-6 xl:col-span-2" aria-labelledby="dash-shortcuts">
            <div>
              <h2 id="dash-shortcuts" className="text-base font-semibold text-slate-900 dark:text-white">
                Shortcuts
              </h2>
              <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {__('Jump to the screens you use most when running your LMS.', 'sikshya')}
              </p>
              <div className="mt-4 space-y-3">
                <QuickActionCard
                  onClick={() => setCreateOpen(true)}
                  icon="plusCircle"
                  title={__('New course', 'sikshya')}
                  description={__('Name your course, we save a draft and open the builder.', 'sikshya')}
                />
                <QuickActionCard
                  href={appViewHref(config, 'courses')}
                  icon="table"
                  title={__('Browse courses', 'sikshya')}
                  description={__('Search, filter, and bulk-manage your catalog.', 'sikshya')}
                />
                <QuickActionCard
                  href={appViewHref(config, 'content-library', { tab: 'lessons' })}
                  icon="bookOpen"
                  title={__('Lessons', 'sikshya')}
                  description={__('Review and edit lesson content across your site.', 'sikshya')}
                />
                <QuickActionCard
                  href={appViewHref(config, 'content-library', { tab: 'quizzes' })}
                  icon="puzzle"
                  title={__('Quizzes', 'sikshya')}
                  description={__('Manage assessments tied to your courses.', 'sikshya')}
                />
                {dashboardLinks.enrollments ? (
                  <QuickActionCard
                    href={appViewHref(config, 'enrollments')}
                    icon="clipboard"
                    title={__('Enrollments', 'sikshya')}
                    description={__('See who is enrolled and in which courses.', 'sikshya')}
                  />
                ) : null}
                {dashboardLinks.payments ? (
                  <QuickActionCard
                    href={appViewHref(config, 'sales', { tab: 'payments' })}
                    icon="columns"
                    title={__('Payments', 'sikshya')}
                    description={__('Review transactions and reconciliation.', 'sikshya')}
                  />
                ) : null}
              </div>
            </div>

            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-5 dark:border-slate-700 dark:bg-slate-900/40">
              <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{__('Grow your course business', 'sikshya')}</h3>
              <ul className="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                <li className="flex gap-2">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" aria-hidden />
                  {__('Invite instructors and assign content ownership from Users.', 'sikshya')}
                </li>
                <li className="flex gap-2">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" aria-hidden />
                  {__('Use Reports to spot completion trends once learners are active.', 'sikshya')}
                </li>
                <li className="flex gap-2">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" aria-hidden />
                  {__('Tune certificates, emails, and branding under Settings.', 'sikshya')}
                </li>
              </ul>
              <div className="mt-4 flex flex-wrap gap-2">
                <a
                  href={appViewHref(config, 'reports')}
                  className="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  Open reports
                </a>
                {dashboardLinks.enrollments ? (
                  <a
                    href={appViewHref(config, 'enrollments')}
                    className="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    Enrollments
                  </a>
                ) : null}
                {dashboardLinks.payments ? (
                  <a
                    href={appViewHref(config, 'sales', { tab: 'payments' })}
                    className="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    Payments
                  </a>
                ) : null}
                <a
                  href={appViewHref(config, 'settings')}
                  className="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  Settings
                </a>
              </div>
            </div>
          </section>
        </div>
      </div>
    </EmbeddableShell>
  );
}
