import { lazy, Suspense, useEffect } from 'react';
import { getConfig } from './config/env';
import { AppShell } from './components/AppShell';
import { SikshyaDialogProvider } from './components/shared/SikshyaDialogContext';
import { ShellStateProvider, useShellState } from './context/ShellStateContext';
import { AdminRoutingProvider, parseAdminRoute, useAdminRouting } from './lib/adminRouting';
import { applyAdminBrandThemeToRoot, clearAdminBrandThemeFromRoot } from './lib/adminBrandTokens';
import { term } from './lib/terminology';
import type { NavItem } from './types';

const ActivityLogPage = lazy(() =>
  import('./pages/ActivityLogPage').then((m) => ({ default: m.ActivityLogPage }))
);
const AddonsPage = lazy(() => import('./pages/AddonsPage').then((m) => ({ default: m.AddonsPage })));
const BundlesPage = lazy(() => import('./pages/BundlesPage').then((m) => ({ default: m.BundlesPage })));
const CalendarPage = lazy(() => import('./pages/CalendarPage').then((m) => ({ default: m.CalendarPage })));
const ContentDripPage = lazy(() =>
  import('./pages/ContentDripPage').then((m) => ({ default: m.ContentDripPage }))
);
const ContentPostEditorPage = lazy(() =>
  import('./pages/ContentPostEditorPage').then((m) => ({ default: m.ContentPostEditorPage }))
);
const CouponsPage = lazy(() => import('./pages/CouponsPage').then((m) => ({ default: m.CouponsPage })));
const DiscussionsPage = lazy(() =>
  import('./pages/DiscussionsPage').then((m) => ({ default: m.DiscussionsPage }))
);
const CourseBuilderPage = lazy(() =>
  import('./pages/CourseBuilderPage').then((m) => ({ default: m.CourseBuilderPage }))
);
const CourseCategoriesPage = lazy(() =>
  import('./pages/CourseCategoriesPage').then((m) => ({ default: m.CourseCategoriesPage }))
);
const CourseTeamPage = lazy(() =>
  import('./pages/CourseTeamPage').then((m) => ({ default: m.CourseTeamPage }))
);
const CoursesPage = lazy(() => import('./pages/CoursesPage').then((m) => ({ default: m.CoursesPage })));
const DashboardPage = lazy(() =>
  import('./pages/DashboardPage').then((m) => ({ default: m.DashboardPage }))
);
const EmailMarketingPage = lazy(() =>
  import('./pages/EmailMarketingPage').then((m) => ({ default: m.EmailMarketingPage }))
);
const EmailPage = lazy(() => import('./pages/EmailPage').then((m) => ({ default: m.EmailPage })));
const EmailTemplateEditPage = lazy(() =>
  import('./pages/EmailTemplateEditPage').then((m) => ({ default: m.EmailTemplateEditPage }))
);
const EnrollmentsPage = lazy(() =>
  import('./pages/EnrollmentsPage').then((m) => ({ default: m.EnrollmentsPage }))
);
const GenericPlaceholderPage = lazy(() =>
  import('./pages/GenericPlaceholderPage').then((m) => ({ default: m.GenericPlaceholderPage }))
);
const GradebookPage = lazy(() =>
  import('./pages/GradebookPage').then((m) => ({ default: m.GradebookPage }))
);
const GradingPage = lazy(() => import('./pages/GradingPage').then((m) => ({ default: m.GradingPage })));
const InstructorApplicationsPage = lazy(() =>
  import('./pages/InstructorApplicationsPage').then((m) => ({ default: m.InstructorApplicationsPage }))
);
const IntegrationsPage = lazy(() =>
  import('./pages/IntegrationsPage').then((m) => ({ default: m.IntegrationsPage }))
);
const IssuedCertificatesPage = lazy(() =>
  import('./pages/IssuedCertificatesPage').then((m) => ({ default: m.IssuedCertificatesPage }))
);
const LicensePage = lazy(() => import('./pages/LicensePage').then((m) => ({ default: m.LicensePage })));
const MarketplacePage = lazy(() =>
  import('./pages/MarketplacePage').then((m) => ({ default: m.MarketplacePage }))
);
const OrdersPage = lazy(() => import('./pages/OrdersPage').then((m) => ({ default: m.OrdersPage })));
const OrderDetailsPage = lazy(() =>
  import('./pages/OrderDetailsPage').then((m) => ({ default: m.OrderDetailsPage }))
);
const PaymentsPage = lazy(() => import('./pages/PaymentsPage').then((m) => ({ default: m.PaymentsPage })));
const PaymentDetailsPage = lazy(() =>
  import('./pages/PaymentDetailsPage').then((m) => ({ default: m.PaymentDetailsPage }))
);
const PrerequisitesPage = lazy(() =>
  import('./pages/PrerequisitesPage').then((m) => ({ default: m.PrerequisitesPage }))
);
const ReviewsPage = lazy(() => import('./pages/ReviewsPage').then((m) => ({ default: m.ReviewsPage })));
const SettingsPage = lazy(() => import('./pages/SettingsPage').then((m) => ({ default: m.SettingsPage })));
const SocialLoginPage = lazy(() =>
  import('./pages/SocialLoginPage').then((m) => ({ default: m.SocialLoginPage }))
);
const SubscriptionsProPage = lazy(() =>
  import('./pages/SubscriptionsProPage').then((m) => ({ default: m.SubscriptionsProPage }))
);
const WhiteLabelPage = lazy(() =>
  import('./pages/WhiteLabelPage').then((m) => ({ default: m.WhiteLabelPage }))
);
const WpEntityListPage = lazy(() =>
  import('./pages/WpEntityListPage').then((m) => ({ default: m.WpEntityListPage }))
);
const WpUserListPage = lazy(() =>
  import('./pages/WpUserListPage').then((m) => ({ default: m.WpUserListPage }))
);

const hubPages = () => import('./pages/hubs/HubPages');
const BrandingHubPage = lazy(() => hubPages().then((m) => ({ default: m.BrandingHubPage })));
const CertificatesHubPage = lazy(() => hubPages().then((m) => ({ default: m.CertificatesHubPage })));
const ContentLibraryHubPage = lazy(() => hubPages().then((m) => ({ default: m.ContentLibraryHubPage })));
const EmailHubPage = lazy(() => hubPages().then((m) => ({ default: m.EmailHubPage })));
const IntegrationsHubPage = lazy(() => hubPages().then((m) => ({ default: m.IntegrationsHubPage })));
const LearningRulesHubPage = lazy(() => hubPages().then((m) => ({ default: m.LearningRulesHubPage })));
const PeopleHubPage = lazy(() => hubPages().then((m) => ({ default: m.PeopleHubPage })));
const ReportsHubPage = lazy(() => hubPages().then((m) => ({ default: m.ReportsHubPage })));
const SalesHubPage = lazy(() => hubPages().then((m) => ({ default: m.SalesHubPage })));
const ToolsHubPage = lazy(() => hubPages().then((m) => ({ default: m.ToolsHubPage })));

/** Same splash as PHP `ReactAdminView` boot markup — one loader for pre-React + lazy chunks. */
function AdminRouteFallback() {
  return (
    <div
      className="sikshya-admin-boot-loader sikshya-admin-boot-loader--in-shell"
      role="status"
      aria-busy="true"
      aria-live="polite"
    >
      <span className="sikshya-admin-boot-spinner" aria-hidden />
      <span className="screen-reader-text">Loading Sikshya…</span>
    </div>
  );
}

function prefetchAdminChunks(): void {
  // Reduce sidebar "flicker" by preloading common route chunks shortly after first paint.
  // This avoids Suspense fallback flashes on each view change in slower environments.
  const w = window as unknown as { requestIdleCallback?: (cb: () => void, opts?: { timeout: number }) => void };
  const schedule = (cb: () => void) => {
    if (typeof w.requestIdleCallback === 'function') {
      w.requestIdleCallback(cb, { timeout: 1200 });
    } else {
      setTimeout(cb, 350);
    }
  };

  schedule(() => {
    void import('./pages/DashboardPage');
    void import('./pages/CoursesPage');
    void import('./pages/SettingsPage');
    void import('./pages/OrdersPage');
    void import('./pages/OrderDetailsPage');
    void import('./pages/PaymentsPage');
    void import('./pages/PaymentDetailsPage');
    void import('./pages/EnrollmentsPage');
    void import('./pages/AddonsPage');
    void import('./pages/EmailPage');
  });
}

function RoutedApp() {
  const baseConfig = getConfig();
  const { route } = useAdminRouting();
  const { navigation } = useShellState();
  const pageKey =
    typeof route.page === 'string' && route.page.trim() !== '' ? route.page.trim() : 'dashboard';
  const config = { ...baseConfig, page: pageKey, query: route.query ?? {}, navigation };
  const page = config.page;
  const q = (config.query || {}) as Record<string, unknown>;
  const isCertificateBuilder = page === 'edit-content' && String(q.post_type || '').trim() === 'sikshya_certificate';
  const platformName = config.branding?.pluginName?.trim() || 'Sikshya';

  useEffect(() => {
    const root = document.getElementById('sikshya-admin-root');
    if (!root) {
      return;
    }
    const b = config.branding;
    if (!b?.topbarBg && !b?.sidebarBg) {
      clearAdminBrandThemeFromRoot(root);
      return;
    }
    applyAdminBrandThemeToRoot(root, b);
    return () => {
      clearAdminBrandThemeFromRoot(root);
    };
  }, [config.branding?.topbarBg, config.branding?.sidebarBg]);
  const T = {
    course: term(config, 'course'),
    courses: term(config, 'courses'),
    lesson: term(config, 'lesson'),
    lessons: term(config, 'lessons'),
    quiz: term(config, 'quiz'),
    quizzes: term(config, 'quizzes'),
    assignment: term(config, 'assignment'),
    assignments: term(config, 'assignments'),
    chapter: term(config, 'chapter'),
    chapters: term(config, 'chapters'),
    student: term(config, 'student'),
    students: term(config, 'students'),
    instructor: term(config, 'instructor'),
    instructors: term(config, 'instructors'),
    enrollment: term(config, 'enrollment'),
    enrollments: term(config, 'enrollments'),
  };

  useEffect(() => {
    prefetchAdminChunks();
  }, []);

  const routes = (() => {
  switch (page) {
    case 'dashboard':
      return <DashboardPage embedded config={config} title="Dashboard" />;
    case 'courses':
      return <CoursesPage embedded config={config} title={T.courses} restBase="sik_course" />;
    case 'add-course':
      return <CourseBuilderPage embedded config={config} title={`${T.course} builder`} />;
    case 'bundle-builder':
      return <CourseBuilderPage embedded config={config} title="Bundle builder" />;
    case 'edit-content':
      return <ContentPostEditorPage embedded config={config} shellTitle="Edit content" />;
    case 'lessons':
    case 'add-lesson':
      return (
        <WpEntityListPage
          config={config}
          title={T.lessons}
          subtitle="All lessons"
          restBase="sik_lesson"
        />
      );
    case 'quizzes':
      return (
        <WpEntityListPage
          config={config}
          title={T.quizzes}
          subtitle="All quizzes"
          restBase="sik_quiz"
        />
      );
    case 'assignments':
      return (
        <WpEntityListPage
          config={config}
          title={T.assignments}
          subtitle="All assignments"
          restBase="sik_assignment"
        />
      );
    case 'questions':
      return (
        <WpEntityListPage
          config={config}
          title="Questions"
          subtitle="All questions"
          restBase="sik_question"
        />
      );
    case 'chapters':
      return (
        <WpEntityListPage
          config={config}
          title="Chapters"
          subtitle="All chapters"
          restBase="sik_chapter"
        />
      );
    case 'certificates':
      return (
        <WpEntityListPage
          config={config}
          title="Certificates"
          subtitle="Certificate templates"
          restBase="sikshya_certificate"
        />
      );
    case 'issued-certificates':
      return <IssuedCertificatesPage embedded config={config} title="Issued certificates" />;
    case 'orders':
      return <OrdersPage embedded config={config} title="Orders" />;
    case 'order':
      return <OrderDetailsPage embedded config={config} title="Order" />;
    case 'coupons':
      return <CouponsPage embedded config={config} title="Coupons" />;
    case 'reviews':
      return <ReviewsPage embedded config={config} title="Course reviews" />;
    case 'discussions':
      return <DiscussionsPage embedded config={config} title="Discussions & Q&A" />;
    case 'gradebook':
      return <GradebookPage embedded config={config} title="Gradebook" />;
    case 'grading':
      return <GradingPage embedded config={config} title="Grading" />;
    case 'activity-log':
      return <ActivityLogPage embedded config={config} title="Activity log" />;
    case 'content-drip':
      return <ContentDripPage embedded config={config} title="Scheduled access" />;
    case 'subscriptions':
      return <SubscriptionsProPage embedded config={config} title="Subscriptions" />;
    case 'course-team':
      return <CourseTeamPage embedded config={config} title="Course staff" />;
    case 'marketplace':
      return <MarketplacePage embedded config={config} title="Marketplace" />;
    case 'bundles':
      return <BundlesPage embedded config={config} title="Course bundles" />;
    case 'prerequisites':
      return <PrerequisitesPage embedded config={config} title="Prerequisites" />;
    case 'social-login':
      return <SocialLoginPage embedded config={config} title="Social login" />;
    case 'white-label':
      return <WhiteLabelPage embedded config={config} title="White label" />;
    case 'crm-automation':
      return (
        <GenericPlaceholderPage
          config={config}
          title="CRM & email automation"
          description="This screen is not wired in the React shell yet."
        />
      );
    case 'calendar':
      return <CalendarPage embedded config={config} title="Calendar" />;
    /* ---- Tabbed hubs (new sidebar entries that fan out to existing pages). ---- */
    case 'content-library':
      return <ContentLibraryHubPage embedded config={config} title="Content library" />;
    case 'people':
      return <PeopleHubPage embedded config={config} title="People" />;
    case 'certificates-hub':
      return <CertificatesHubPage embedded config={config} title="Certificates" />;
    case 'sales':
      return <SalesHubPage embedded config={config} title="Sales" />;
    case 'email-hub':
    case 'email-templates':
      return <EmailHubPage embedded config={config} title="Email" />;
    case 'branding':
      return <BrandingHubPage embedded config={config} title="Branding" />;
    case 'integrations-hub':
      return <IntegrationsHubPage embedded config={config} title="Integrations" />;
    case 'learning-rules':
      return <LearningRulesHubPage embedded config={config} title="Learning rules" />;
    case 'course-categories':
      return (
        <CourseCategoriesPage
          embedded
          config={config}
          title="Course categories"
          subtitle="Organize courses with hierarchical categories."
        />
      );
    case 'students':
      return (
        <WpUserListPage
          config={config}
          title={T.students}
          subtitle={`Users with the ${T.student} role.`}
          variant="students"
        />
      );
    case 'instructors':
      return (
        <WpUserListPage
          config={config}
          title={T.instructors}
          subtitle={`Users with the ${T.instructor} role. Pending sign-ups are under People → Applications.`}
          variant="instructors"
        />
      );
    case 'instructor-applications':
      return (
        <InstructorApplicationsPage
          embedded
          config={config}
          title="Instructor applications"
          subtitle="Approve or reject learners who applied to teach."
        />
      );
    case 'enrollments':
      return <EnrollmentsPage embedded config={config} title={T.enrollments} />;
    case 'reports':
      return <ReportsHubPage embedded config={config} title="Reports" />;
    case 'payments':
      return <PaymentsPage embedded config={config} title="Payments" />;
    case 'payment':
      return <PaymentDetailsPage embedded config={config} title="Payment" />;
    case 'settings':
      return <SettingsPage embedded config={config} title="Settings" />;
    case 'email':
      return <EmailPage embedded config={config} title="Email" />;
    case 'email-template-edit':
      return <EmailTemplateEditPage embedded config={config} title="Email template" />;
    case 'tools':
      return <ToolsHubPage embedded config={config} title="Tools" />;
    case 'addons':
      return <AddonsPage embedded config={config} title="Addons" />;
    case 'integrations':
      return <IntegrationsPage embedded config={config} title="Integrations" />;
    case 'email-marketing':
      return <EmailMarketingPage embedded config={config} title="Email marketing" />;
    case 'license':
      return <LicensePage embedded config={config} title="License" />;
    default:
      return (
        <GenericPlaceholderPage
          config={config}
          title={platformName}
          description="This admin screen is powered by the React shell."
        />
      );
  }
  })();

  const navTitle = (() => {
    const items = config.navigation as NavItem[];
    const walk = (rows: NavItem[] | undefined): string | null => {
      if (!Array.isArray(rows)) return null;
      for (const r of rows) {
        if ((r as any)?.id === page && typeof (r as any)?.label === 'string' && (r as any).label.trim() !== '') {
          return String((r as any).label).trim();
        }
        const kids = (r as any)?.children as NavItem[] | undefined;
        const hit = walk(kids);
        if (hit) return hit;
      }
      return null;
    };
    return walk(items);
  })();

  const shellTitle = (() => {
    if (navTitle) return navTitle;
    if (page === 'dashboard') return 'Dashboard';
    if (page === 'settings') return 'Settings';
    if (page === 'courses') return T.courses;
    if (page === 'add-course') return `${T.course} builder`;
    if (page === 'bundle-builder') return 'Bundle builder';
    if (page === 'edit-content') {
      const postType = String(q.post_type || '').trim();
      const id = Number(q.post_id || q.id || 0) || 0;
      const label =
        postType === 'sikshya_certificate'
          ? 'Certificate'
          : postType === 'sik_lesson'
            ? T.lesson
            : postType === 'sik_quiz'
              ? T.quiz
              : postType === 'sik_assignment'
                ? T.assignment
                : postType === 'sik_course'
                  ? T.course
                  : postType
                    ? postType
                    : 'Content';
      return id > 0 ? `Edit ${label}` : `New ${label}`;
    }
    return platformName;
  })();

  // Certificate builder is a full-screen workspace with its own header/actions.
  // Do not wrap it in the admin shell (no sidebar, no top bar).
  // No Suspense fallback: PHP skips the Sikshya boot loader for this route; hosts often show their own loader first.
  if (isCertificateBuilder) {
    return <Suspense fallback={null}>{routes}</Suspense>;
  }

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      pluginUrl={config.pluginUrl}
      user={config.user}
      branding={config.branding}
      title={shellTitle}
      subtitle={page === 'settings' ? 'Site-wide defaults for every course' : undefined}
    >
      <Suspense fallback={<AdminRouteFallback />}>{routes}</Suspense>
    </AppShell>
  );
}

export default function App() {
  const baseConfig = getConfig();
  // Ensure the initial render respects the URL (deep links / reload).
  // This also protects us if PHP-provided config.page lags behind the URL.
  const initial = parseAdminRoute(baseConfig);
  const normalizedBase = { ...baseConfig, page: initial.page, query: initial.query };

  return (
    <AdminRoutingProvider baseConfig={normalizedBase}>
      <ShellStateProvider>
        <SikshyaDialogProvider>
          <RoutedApp />
        </SikshyaDialogProvider>
      </ShellStateProvider>
    </AdminRoutingProvider>
  );
}
