import { useEffect } from 'react';
import { getConfig } from './config/env';
import { SikshyaDialogProvider } from './components/shared/SikshyaDialogContext';
import { ContentPostEditorPage } from './pages/ContentPostEditorPage';
import { ContentDripPage } from './pages/ContentDripPage';
import { CourseCategoriesPage } from './pages/CourseCategoriesPage';
import { CourseBuilderPage } from './pages/CourseBuilderPage';
import { CourseTeamPage } from './pages/CourseTeamPage';
import { CoursesPage } from './pages/CoursesPage';
import { CouponsPage } from './pages/CouponsPage';
import { ReviewsPage } from './pages/ReviewsPage';
import { DashboardPage } from './pages/DashboardPage';
import { EnrollmentsPage } from './pages/EnrollmentsPage';
import { GenericPlaceholderPage } from './pages/GenericPlaceholderPage';
import { ActivityLogPage } from './pages/ActivityLogPage';
import { BundlesPage } from './pages/BundlesPage';
import { CalendarPage } from './pages/CalendarPage';
import { GradebookPage } from './pages/GradebookPage';
import { GradingPage } from './pages/GradingPage';
import { IntegrationsPage } from './pages/IntegrationsPage';
import { LicensePage } from './pages/LicensePage';
import { IssuedCertificatesPage } from './pages/IssuedCertificatesPage';
import { MarketplacePage } from './pages/MarketplacePage';
import { PrerequisitesPage } from './pages/PrerequisitesPage';
import { SocialLoginPage } from './pages/SocialLoginPage';
import { WhiteLabelPage } from './pages/WhiteLabelPage';
import { EmailMarketingPage } from './pages/EmailMarketingPage';
import { OrdersPage } from './pages/OrdersPage';
import { PaymentsPage } from './pages/PaymentsPage';
import { ReportsPage } from './pages/ReportsPage';
import { EmailPage } from './pages/EmailPage';
import { EmailTemplateEditPage } from './pages/EmailTemplateEditPage';
import { SettingsPage } from './pages/SettingsPage';
import { SubscriptionsProPage } from './pages/SubscriptionsProPage';
import { ToolsPage } from './pages/ToolsPage';
import { WpEntityListPage } from './pages/WpEntityListPage';
import { WpUserListPage } from './pages/WpUserListPage';
import { InstructorApplicationsPage } from './pages/InstructorApplicationsPage';
import { ShellStateProvider } from './context/ShellStateContext';
import { AdminRoutingProvider, parseAdminRoute, useAdminRouting } from './lib/adminRouting';
import { AddonsPage } from './pages/AddonsPage';
import { applyAdminBrandThemeToRoot, clearAdminBrandThemeFromRoot } from './lib/adminBrandTokens';
import { term } from './lib/terminology';
import {
  BrandingHubPage,
  CertificatesHubPage,
  ContentLibraryHubPage,
  EmailHubPage,
  IntegrationsHubPage,
  LearningRulesHubPage,
  PeopleHubPage,
  ReportsHubPage,
  SalesHubPage,
  ToolsHubPage,
} from './pages/hubs/HubPages';

function RoutedApp() {
  const baseConfig = getConfig();
  const { route } = useAdminRouting();
  const pageKey =
    typeof route.page === 'string' && route.page.trim() !== '' ? route.page.trim() : 'dashboard';
  const config = { ...baseConfig, page: pageKey, query: route.query ?? {} };
  const page = config.page;
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

  const routes = (() => {
  switch (page) {
    case 'dashboard':
      return <DashboardPage config={config} title="Dashboard" />;
    case 'courses':
      return <CoursesPage config={config} title={T.courses} restBase="sik_course" />;
    case 'add-course':
      return <CourseBuilderPage config={config} title={`${T.course} builder`} />;
    case 'bundle-builder':
      return <CourseBuilderPage config={config} title="Bundle builder" />;
    case 'edit-content':
      return <ContentPostEditorPage config={config} shellTitle="Edit content" />;
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
      return <IssuedCertificatesPage config={config} title="Issued certificates" />;
    case 'orders':
      return <OrdersPage config={config} title="Orders" />;
    case 'coupons':
      return <CouponsPage config={config} title="Coupons" />;
    case 'reviews':
      return <ReviewsPage config={config} title="Course reviews" />;
    case 'gradebook':
      return <GradebookPage config={config} title="Gradebook" />;
    case 'grading':
      return <GradingPage config={config} title="Grading" />;
    case 'activity-log':
      return <ActivityLogPage config={config} title="Activity log" />;
    case 'content-drip':
      return <ContentDripPage config={config} title="Scheduled access" />;
    case 'subscriptions':
      return <SubscriptionsProPage config={config} title="Subscriptions" />;
    case 'course-team':
      return <CourseTeamPage config={config} title="Course staff" />;
    case 'marketplace':
      return <MarketplacePage config={config} title="Marketplace" />;
    case 'bundles':
      return <BundlesPage config={config} title="Course bundles" />;
    case 'prerequisites':
      return <PrerequisitesPage config={config} title="Prerequisites" />;
    case 'social-login':
      return <SocialLoginPage config={config} title="Social login" />;
    case 'white-label':
      return <WhiteLabelPage config={config} title="White label" />;
    case 'crm-automation':
      return (
        <GenericPlaceholderPage
          config={config}
          title="CRM & email automation"
          description="This screen is not wired in the React shell yet."
        />
      );
    case 'calendar':
      return <CalendarPage config={config} title="Calendar" />;
    /* ---- Tabbed hubs (new sidebar entries that fan out to existing pages). ---- */
    case 'content-library':
      return <ContentLibraryHubPage config={config} title="Content library" />;
    case 'people':
      return <PeopleHubPage config={config} title="People" />;
    case 'certificates-hub':
      return <CertificatesHubPage config={config} title="Certificates" />;
    case 'sales':
      return <SalesHubPage config={config} title="Sales" />;
    case 'email-hub':
    case 'email-templates':
      return <EmailHubPage config={config} title="Email" />;
    case 'branding':
      return <BrandingHubPage config={config} title="Branding" />;
    case 'integrations-hub':
      return <IntegrationsHubPage config={config} title="Integrations" />;
    case 'learning-rules':
      return <LearningRulesHubPage config={config} title="Learning rules" />;
    case 'course-categories':
      return (
        <CourseCategoriesPage
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
          config={config}
          title="Instructor applications"
          subtitle="Approve or reject learners who applied to teach."
        />
      );
    case 'enrollments':
      return <EnrollmentsPage config={config} title={T.enrollments} />;
    case 'reports':
      return <ReportsHubPage config={config} title="Reports" />;
    case 'payments':
      return <PaymentsPage config={config} title="Payments" />;
    case 'settings':
      return <SettingsPage config={config} title="Settings" />;
    case 'email':
      return <EmailPage config={config} title="Email" />;
    case 'email-template-edit':
      return <EmailTemplateEditPage config={config} title="Email template" />;
    case 'tools':
      return <ToolsHubPage config={config} title="Tools" />;
    case 'addons':
      return <AddonsPage config={config} title="Addons" />;
    case 'integrations':
      return <IntegrationsPage config={config} title="Integrations" />;
    case 'email-marketing':
      return <EmailMarketingPage config={config} title="Email marketing" />;
    case 'license':
      return <LicensePage config={config} title="License" />;
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

  return <SikshyaDialogProvider>{routes}</SikshyaDialogProvider>;
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
        <RoutedApp />
      </ShellStateProvider>
    </AdminRoutingProvider>
  );
}
