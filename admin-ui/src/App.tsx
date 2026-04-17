import { getConfig } from './config/env';
import { SikshyaDialogProvider } from './components/shared/SikshyaDialogContext';
import { ContentPostEditorPage } from './pages/ContentPostEditorPage';
import { ContentDripPage } from './pages/ContentDripPage';
import { CourseCategoriesPage } from './pages/CourseCategoriesPage';
import { CourseBuilderPage } from './pages/CourseBuilderPage';
import { CourseTeamPage } from './pages/CourseTeamPage';
import { CoursesPage } from './pages/CoursesPage';
import { CouponsPage } from './pages/CouponsPage';
import { DashboardPage } from './pages/DashboardPage';
import { EnrollmentsPage } from './pages/EnrollmentsPage';
import { GenericPlaceholderPage } from './pages/GenericPlaceholderPage';
import { GradebookPage } from './pages/GradebookPage';
import { IssuedCertificatesPage } from './pages/IssuedCertificatesPage';
import { MarketplacePage } from './pages/MarketplacePage';
import { OrdersPage } from './pages/OrdersPage';
import { PaymentsPage } from './pages/PaymentsPage';
import { ReportsPage } from './pages/ReportsPage';
import { SettingsPage } from './pages/SettingsPage';
import { SubscriptionsProPage } from './pages/SubscriptionsProPage';
import { ToolsPage } from './pages/ToolsPage';
import { WpEntityListPage } from './pages/WpEntityListPage';
import { WpUserListPage } from './pages/WpUserListPage';
import { AdminRoutingProvider, parseAdminRoute, useAdminRouting } from './lib/adminRouting';
import { AddonsPage } from './pages/AddonsPage';

function RoutedApp() {
  const baseConfig = getConfig();
  const { route } = useAdminRouting();
  const config = { ...baseConfig, page: route.page, query: route.query };
  const page = config.page;

  const routes = (() => {
  switch (page) {
    case 'dashboard':
      return <DashboardPage config={config} title="Dashboard" />;
    case 'courses':
      return <CoursesPage config={config} title="Courses" restBase="sik_course" />;
    case 'add-course':
      return <CourseBuilderPage config={config} title="Course builder" />;
    case 'edit-content':
      return <ContentPostEditorPage config={config} shellTitle="Edit content" />;
    case 'lessons':
    case 'add-lesson':
      return (
        <WpEntityListPage
          config={config}
          title="Lessons"
          subtitle="All lessons"
          restBase="sik_lesson"
        />
      );
    case 'quizzes':
      return (
        <WpEntityListPage
          config={config}
          title="Quizzes"
          subtitle="All quizzes"
          restBase="sik_quiz"
        />
      );
    case 'assignments':
      return (
        <WpEntityListPage
          config={config}
          title="Assignments"
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
    case 'gradebook':
      return <GradebookPage config={config} title="Gradebook" />;
    case 'content-drip':
      return <ContentDripPage config={config} title="Scheduled access" />;
    case 'subscriptions':
      return <SubscriptionsProPage config={config} title="Subscriptions" />;
    case 'course-team':
      return <CourseTeamPage config={config} title="Course staff" />;
    case 'marketplace':
      return <MarketplacePage config={config} title="Marketplace" />;
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
          title="Students"
          subtitle="Users with the Sikshya student role."
          variant="students"
        />
      );
    case 'instructors':
      return (
        <WpUserListPage
          config={config}
          title="Instructors"
          subtitle="Users with the Sikshya instructor role."
          variant="instructors"
        />
      );
    case 'enrollments':
      return <EnrollmentsPage config={config} title="Enrollments" />;
    case 'reports':
      return <ReportsPage config={config} title="Reports overview" />;
    case 'payments':
      return <PaymentsPage config={config} title="Payments" />;
    case 'settings':
      return <SettingsPage config={config} title="Settings" />;
    case 'tools':
      return <ToolsPage config={config} title="Tools" />;
    case 'addons':
      return <AddonsPage config={config} title="Addons" />;
    default:
      return (
        <GenericPlaceholderPage
          config={config}
          title="Sikshya"
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
      <RoutedApp />
    </AdminRoutingProvider>
  );
}
