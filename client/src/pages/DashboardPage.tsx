import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { MetricTile, QuickActionCard } from '../components/dashboard';
import { NavIcon } from '../components/NavIcon';
import { ButtonPrimary } from '../components/shared/buttons';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPanel } from '../components/shared/list/ListPanel';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { getLicensing } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

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
    return 'Good morning';
  }
  if (h < 18) {
    return 'Good afternoon';
  }
  return 'Good evening';
}

type OverviewPayload = {
  siteName?: string;
  stats?: Partial<DashboardStats>;
  recentCourses?: RecentCourse[];
  dashboardLinks?: { enrollments?: boolean; payments?: boolean };
};

export function DashboardPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const [createOpen, setCreateOpen] = useState(false);
  const [refreshBusy, setRefreshBusy] = useState(false);

  const boot = useMemo(() => resolveDashboardData(config), [config.initialData]);
  const [live, setLive] = useState<typeof boot | null>(null);

  const { siteName, stats, recentCourses, dashboardLinks } = live ?? boot;

  const refreshOverview = useCallback(async () => {
    setRefreshBusy(true);
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
    } finally {
      setRefreshBusy(false);
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
      ? `${stats.draftCourses} draft course${stats.draftCourses === 1 ? '' : 's'} not yet published`
      : 'All courses are either live or archived';

  const enrollmentHint = stats.hasEnrollmentTable
    ? `${stats.completedEnrollments ?? 0} marked completed`
    : 'Enrollments table not found — activate the plugin or run DB updates';

  const licensing = getLicensing(config);

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      branding={config.branding}
      title={title}
      subtitle="Course health, learners, and shortcuts in one place"
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            disabled={refreshBusy}
            onClick={() => void refreshOverview()}
            className="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          >
            {refreshBusy ? 'Refreshing…' : 'Refresh data'}
          </button>
          <ButtonPrimary onClick={() => setCreateOpen(true)}>+ New course</ButtonPrimary>
        </div>
      }
    >
      <CreateCourseModal config={config} open={createOpen} onClose={() => setCreateOpen(false)} />
      <div className="w-full min-w-0 space-y-8">
        <section className="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-brand-600 via-brand-600 to-indigo-700 px-6 py-8 text-white shadow-lg dark:border-slate-800 dark:from-brand-700 dark:via-brand-800 dark:to-slate-900">
          <div
            className="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl"
            aria-hidden
          />
          <div
            className="pointer-events-none absolute -bottom-20 left-1/3 h-48 w-48 rounded-full bg-indigo-400/20 blur-2xl"
            aria-hidden
          />
          <div className="relative">
            <p className="text-sm font-medium text-brand-100/90">{dateLine}</p>
            <h1 className="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
              {greetingLabel()}, {config.user.name}
            </h1>
            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-brand-100/95">
              {siteName ? (
                <>
                  You are managing <span className="font-semibold text-white">{siteName}</span>. Track catalog
                  growth, learner sign-ups, and revenue from this overview.
                </>
              ) : (
                <>Track catalog growth, learner sign-ups, and revenue from this overview.</>
              )}
            </p>
          </div>
        </section>

        {licensing && !licensing.isProActive ? (
          <section
            className="rounded-2xl border border-indigo-200/90 bg-indigo-50/90 px-4 py-3 text-sm text-indigo-950 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/50 dark:text-indigo-100"
            aria-label="Advanced LMS capabilities"
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="min-w-0 leading-relaxed">
                <span className="font-semibold">Scale your school</span>
                {' — '}
                Scheduled lesson access, subscriptions, gradebook, shared course staff, richer certificates, and more.
                Your admin layout stays familiar; upgraded plans unlock the behaviour behind each screen.
              </p>
              <a
                href={licensing.upgradeUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="shrink-0 rounded-xl bg-indigo-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-800 dark:bg-indigo-600 dark:hover:bg-indigo-500"
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
              label="Published courses"
              value={stats.publishedCourses}
              hint="Live in your catalog"
              icon={<NavIcon name="course" className="h-5 w-5" />}
            />
            <MetricTile
              accent="emerald"
              label="Students"
              value={stats.students}
              hint="Users with the Sikshya student role"
              icon={<NavIcon name="users" className="h-5 w-5" />}
            />
            <MetricTile
              accent="violet"
              label="Total revenue"
              value={stats.revenue}
              hint={
                stats.hasPaymentsTable
                  ? 'Sum of completed payments in Sikshya'
                  : 'Payments table not found — revenue may stay at zero'
              }
              icon={<NavIcon name="chart" className="h-5 w-5" />}
            />
            <MetricTile
              accent="amber"
              label="Active enrollments"
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
              label="Draft courses"
              value={stats.draftCourses ?? 0}
              hint={draftHint}
              icon={<NavIcon name="table" className="h-5 w-5" />}
            />
            <MetricTile
              accent="sky"
              label="Lessons"
              value={stats.lessons ?? 0}
              hint="Published lesson posts"
              icon={<NavIcon name="bookOpen" className="h-5 w-5" />}
            />
            <MetricTile
              accent="sky"
              label="Quizzes"
              value={stats.quizzes ?? 0}
              hint="Published quiz posts"
              icon={<NavIcon name="puzzle" className="h-5 w-5" />}
            />
            <MetricTile
              accent="sky"
              label="Assignments"
              value={stats.assignments ?? 0}
              hint="Published assignment posts"
              icon={<NavIcon name="clipboard" className="h-5 w-5" />}
            />
          </div>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <MetricTile
              accent="emerald"
              label="Instructors"
              value={stats.instructors ?? 0}
              hint="Users with the instructor role"
              icon={<NavIcon name="userCircle" className="h-5 w-5" />}
            />
            <MetricTile
              accent="slate"
              label="Certificate templates"
              value={stats.certificateTemplates ?? 0}
              hint="Published certificate designs"
              icon={<NavIcon name="badge" className="h-5 w-5" />}
            />
            <MetricTile
              accent="slate"
              label="Questions"
              value={stats.questions ?? 0}
              hint="Published question bank items"
              icon={<NavIcon name="helpCircle" className="h-5 w-5" />}
            />
            <MetricTile
              accent="slate"
              label="Chapters"
              value={stats.chapters ?? 0}
              hint="Published chapter posts"
              icon={<NavIcon name="layers" className="h-5 w-5" />}
            />
          </div>
        </section>

        <div className="grid gap-6 xl:grid-cols-5">
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
                  title="No courses yet"
                  description="Create your first course to see recent activity and status here."
                  action={<ButtonPrimary onClick={() => setCreateOpen(true)}>Create a course</ButtonPrimary>}
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
                          <span className="sr-only">Open</span>
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
                              title="Open in course builder"
                              aria-label="Open in course builder"
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
                Jump to the screens you use most when running your LMS.
              </p>
              <div className="mt-4 space-y-3">
                <QuickActionCard
                  onClick={() => setCreateOpen(true)}
                  icon="plusCircle"
                  title="New course"
                  description="Name your course, we save a draft and open the builder."
                />
                <QuickActionCard
                  href={appViewHref(config, 'courses')}
                  icon="table"
                  title="Browse courses"
                  description="Search, filter, and bulk-manage your catalog."
                />
                <QuickActionCard
                  href={appViewHref(config, 'content-library', { tab: 'lessons' })}
                  icon="bookOpen"
                  title="Lessons"
                  description="Review and edit lesson content across your site."
                />
                <QuickActionCard
                  href={appViewHref(config, 'content-library', { tab: 'quizzes' })}
                  icon="puzzle"
                  title="Quizzes"
                  description="Manage assessments tied to your courses."
                />
                {dashboardLinks.enrollments ? (
                  <QuickActionCard
                    href={appViewHref(config, 'enrollments')}
                    icon="clipboard"
                    title="Enrollments"
                    description="See who is enrolled and in which courses."
                  />
                ) : null}
                {dashboardLinks.payments ? (
                  <QuickActionCard
                    href={appViewHref(config, 'sales', { tab: 'payments' })}
                    icon="columns"
                    title="Payments"
                    description="Review transactions and reconciliation."
                  />
                ) : null}
              </div>
            </div>

            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-5 dark:border-slate-700 dark:bg-slate-900/40">
              <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Grow your school</h3>
              <ul className="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                <li className="flex gap-2">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" aria-hidden />
                  Invite instructors and assign content ownership from Users.
                </li>
                <li className="flex gap-2">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" aria-hidden />
                  Use Reports to spot completion trends once learners are active.
                </li>
                <li className="flex gap-2">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" aria-hidden />
                  Tune certificates, emails, and branding under Settings.
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
    </AppShell>
  );
}
