import { useEffect } from 'react';
import { TabbedHubPage } from '../../components/shared/TabbedHubPage';
import { useAdminRouting } from '../../lib/adminRouting';
import type { SikshyaReactConfig } from '../../types';
import { WpEntityListPage } from '../WpEntityListPage';
import { WpUserListPage } from '../WpUserListPage';
import { InstructorApplicationsPage } from '../InstructorApplicationsPage';
import { IssuedCertificatesPage } from '../IssuedCertificatesPage';
import { OrdersPage } from '../OrdersPage';
import { PaymentsPage } from '../PaymentsPage';
import { EmailPage } from '../EmailPage';
import { EmailTemplatesListPage } from '../EmailTemplatesListPage';
import { WhiteLabelPage } from '../WhiteLabelPage';
import { SocialLoginPage } from '../SocialLoginPage';
import { IntegrationsPage } from '../IntegrationsPage';
import { EmailMarketingPage } from '../EmailMarketingPage';
import { ContentDripPage } from '../ContentDripPage';
import { PrerequisitesPage } from '../PrerequisitesPage';
import { CalendarPage } from '../CalendarPage';
import { ActivityLogPage } from '../ActivityLogPage';
import { AddonSettingsPage } from '../AddonSettingsPage';
import { QuizAdvancedWorkspacePage } from '../QuizAdvancedWorkspacePage';
import { LiveClassesWorkspacePage } from '../LiveClassesWorkspacePage';
import { ScormH5pWorkspacePage } from '../ScormH5pWorkspacePage';
import { ReportsPage } from '../ReportsPage';
import { ToolsPage } from '../ToolsPage';
import { isFeatureEnabled } from '../../lib/licensing';
import { appViewHref } from '../../lib/appUrl';
import { term } from '../../lib/terminology';

type Props = { embedded?: boolean; config: SikshyaReactConfig; title: string };

/**
 * Library hub: a single nav entry that fans out to the five "all rows of one
 * post type" lists (Lessons / Quizzes / Assignments / Questions / Chapters).
 * Replaces five separate sidebar entries that each rendered the same component
 * with a different `restBase`.
 */
export function ContentLibraryHubPage({ config, title }: Props) {
  const T = {
    lessons: term(config, 'lessons'),
    quizzes: term(config, 'quizzes'),
    assignments: term(config, 'assignments'),
    chapters: term(config, 'chapters'),
    lesson: term(config, 'lesson'),
    quiz: term(config, 'quiz'),
    assignment: term(config, 'assignment'),
    chapter: term(config, 'chapter'),
  };
  return (
    <TabbedHubPage
      embedded
      config={config}
      title={title}
      subtitle={`Browse every ${T.lesson.toLowerCase()}, ${T.quiz.toLowerCase()}, ${T.assignment.toLowerCase()}, question, and ${T.chapter.toLowerCase()} on the site.`}
      sidebarActivePage="content-library"
      tabs={[
        {
          id: 'lessons',
          label: T.lessons,
          icon: 'bookOpen',
          render: (c) => (
            <WpEntityListPage embedded config={c} title={T.lessons} subtitle={`All ${T.lesson.toLowerCase()}s`} restBase="sik_lesson" />
          ),
        },
        {
          id: 'quizzes',
          label: T.quizzes,
          icon: 'puzzle',
          render: (c) => (
            <WpEntityListPage embedded config={c} title={T.quizzes} subtitle={`All ${T.quiz.toLowerCase()}s`} restBase="sik_quiz" />
          ),
        },
        {
          id: 'assignments',
          label: T.assignments,
          icon: 'clipboard',
          render: (c) => (
            <WpEntityListPage
              embedded
              config={c}
              title={T.assignments}
              subtitle={`All ${T.assignment.toLowerCase()}s`}
              restBase="sik_assignment"
            />
          ),
        },
        {
          id: 'questions',
          label: 'Questions',
          icon: 'helpCircle',
          render: (c) => (
            <WpEntityListPage
              embedded
              config={c}
              title="Questions"
              subtitle="All questions"
              restBase="sik_question"
            />
          ),
        },
        {
          id: 'chapters',
          label: T.chapters,
          icon: 'layers',
          render: (c) => (
            <WpEntityListPage embedded config={c} title={T.chapters} subtitle={`All ${T.chapter.toLowerCase()}s`} restBase="sik_chapter" />
          ),
        },
        {
          id: 'question-banks',
          label: 'Question banks',
          icon: 'puzzle',
          hidden: !isFeatureEnabled(config, 'quiz_advanced'),
          render: (c) => (
            <QuizAdvancedWorkspacePage embedded config={c} title="Question banks" />
          ),
        },
      ]}
    />
  );
}

export function PeopleHubPage({ config, title }: Props) {
  const students = term(config, 'students');
  const instructors = term(config, 'instructors');
  return (
    <TabbedHubPage
      embedded
      config={config}
      title={title}
      subtitle={`${students} and ${instructors.toLowerCase()} that have a role on this site.`}
      sidebarActivePage="people"
      tabs={[
        {
          id: 'students',
          label: students,
          icon: 'users',
          render: (c) => (
            <WpUserListPage
              embedded
              config={c}
              title={students}
              subtitle={`Users with the ${term(config, 'student')} role.`}
              variant="students"
            />
          ),
        },
        {
          id: 'instructors',
          label: instructors,
          icon: 'userCircle',
          render: (c) => (
            <WpUserListPage
              embedded
              config={c}
              title={instructors}
              subtitle={`Users with the ${term(config, 'instructor')} role. Pending sign-ups are under Applications.`}
              variant="instructors"
            />
          ),
        },
        {
          id: 'instructor-applications',
          label: 'Applications',
          icon: 'clipboard',
          render: (c) => (
            <InstructorApplicationsPage
              embedded
              config={c}
              title="Instructor applications"
              subtitle="Approve or reject learners who applied to teach. Approving assigns the instructor role."
            />
          ),
        },
      ]}
    />
  );
}

export function CertificatesHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
      embedded
      config={config}
      title={title}
      subtitle="Designs that get rendered, and a record of every certificate already awarded."
      sidebarActivePage="certificates-hub"
      tabs={[
        {
          id: 'templates',
          label: 'Templates',
          icon: 'badge',
          render: (c) => (
            <WpEntityListPage
              embedded
              config={c}
              title="Certificates"
              subtitle="Certificate templates"
              restBase="sikshya_certificate"
            />
          ),
        },
        {
          id: 'issued',
          label: 'Issued',
          icon: 'clipboard',
          render: (c) => <IssuedCertificatesPage embedded config={c} title="Issued certificates" />,
        },
        {
          id: 'settings',
          label: 'Add-on defaults',
          icon: 'settings',
          hidden: !isFeatureEnabled(config, 'certificates_advanced'),
          render: (c) => (
            <AddonSettingsPage
              embedded
              config={c}
              title="Advanced certificates"
              addonId="certificates_advanced"
              subtitle="Verification links, QR images, and learner-facing controls."
              featureTitle="Advanced certificates"
              featureDescription="Control QR visibility, learner download/share toolbars, and the learn sidebar shortcut to My account."
              relatedCoreSettingsTab="certificates"
              relatedCoreSettingsLabel="Certificates"
              nextSteps={[
                {
                  label: 'Edit certificate templates',
                  href: appViewHref(c, 'certificates-hub', { tab: 'templates' }),
                  description: 'Design layouts and merge fields used when Sikshya issues a certificate.',
                },
                {
                  label: 'Browse issued rows',
                  href: appViewHref(c, 'certificates-hub', { tab: 'issued' }),
                  description: 'Audit who received a credential and open verification links.',
                },
              ]}
            />
          ),
        },
      ]}
    />
  );
}

export function SalesHubPage({ config, title }: Props) {
  const salesSubtitle =
    config.offlineCheckoutEnabled === false
      ? 'Orders (Stripe, PayPal, offline) and payment records. Use Orders → New manual order or Mark paid on pending offline rows. Enable offline checkout under Settings → Payment if learners should see it on checkout.'
      : 'Orders (Stripe, PayPal, offline) and payment records. Use Orders → New manual order or Mark paid on pending offline rows after you confirm payment.';

  return (
    <TabbedHubPage
      embedded
      config={config}
      title={title}
      subtitle={salesSubtitle}
      sidebarActivePage="sales"
      tabs={[
        {
          id: 'orders',
          label: 'Orders',
          icon: 'table',
          render: (c) => <OrdersPage embedded config={c} title="Orders" />,
        },
        {
          id: 'payments',
          label: 'Payments',
          icon: 'columns',
          render: (c) => <PaymentsPage embedded config={c} title="Payments" />,
        },
      ]}
    />
  );
}

export function EmailHubPage({ config, title }: Props) {
  const { route, navigateView } = useAdminRouting();

  // Legacy `view=email-templates` loads this page too; normalize the URL to the hub
  // so bookmarks, support links, and the server redirect all converge on one shape.
  useEffect(() => {
    if (route.page !== 'email-templates') {
      return;
    }
    const q = route.query;
    const nextTab = (q.tab || '').trim() || 'templates';
    const extra: Record<string, string> = { ...q };
    delete extra.tab;
    navigateView('email-hub', { tab: nextTab, ...extra }, { replace: true });
  }, [route.page, route.query, navigateView]);

  return (
    <TabbedHubPage
      embedded
      config={config}
      title={title}
      subtitle="Sender identity, SMTP, and the transactional email templates Sikshya uses."
      sidebarActivePage="email-hub"
      tabs={[
        {
          id: 'delivery',
          label: 'Delivery',
          icon: 'cog',
          render: (c) => <EmailPage embedded config={c} title="Email" />,
        },
        {
          id: 'templates',
          label: 'Templates',
          icon: 'table',
          render: (c) => <EmailTemplatesListPage embedded config={c} title="Email templates" />,
        },
      ]}
    />
  );
}

export function BrandingHubPage({ config, title }: Props) {
  // Show the social-login tab only when the feature exists in this build, so unlicensed
  // customers don't see an empty rail of options promising features they can't use yet.
  const hasSocial = isFeatureEnabled(config, 'social_login');
  return (
    <TabbedHubPage
      embedded
      config={config}
      title={title}
      subtitle="How your brand looks across login, account, and admin screens."
      sidebarActivePage="branding"
      tabs={[
        {
          id: 'white-label',
          label: 'White label',
          icon: 'badge',
          render: (c) => <WhiteLabelPage embedded config={c} title="White label" />,
        },
        {
          id: 'social-login',
          label: 'Social login',
          icon: 'users',
          hidden: !hasSocial,
          render: (c) => <SocialLoginPage embedded config={c} title="Social login" />,
        },
      ]}
    />
  );
}

export function IntegrationsHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
      embedded
      config={config}
      title={title}
      subtitle="Automation and external tools (webhooks, API keys, live classes, SCORM, email marketing). Site-wide LMS defaults stay under Settings — this hub is for connections and add-on workspaces."
      sidebarActivePage="integrations-hub"
      tabs={[
        {
          id: 'webhooks',
          label: 'Webhooks & API',
          icon: 'columns',
          render: (c) => <IntegrationsPage embedded config={c} title="Webhooks & API" />,
        },
        {
          id: 'email-marketing',
          label: 'Email marketing',
          icon: 'mail',
          render: (c) => <EmailMarketingPage embedded config={c} title="Email marketing" />,
        },
        {
          id: 'live-classes',
          label: 'Live classes',
          icon: 'schedule',
          hidden: !isFeatureEnabled(config, 'live_classes'),
          render: (c) => <LiveClassesWorkspacePage embedded config={c} title="Live classes" />,
        },
        {
          id: 'scorm-h5p',
          label: 'SCORM / H5P',
          icon: 'layers',
          hidden: !isFeatureEnabled(config, 'scorm_h5p_pro'),
          render: (c) => <ScormH5pWorkspacePage embedded config={c} title="SCORM / H5P" />,
        },
        {
          id: 'multilingual',
          label: 'Multilingual',
          icon: 'helpCircle',
          hidden: !isFeatureEnabled(config, 'multilingual_enterprise'),
          render: (c) => (
            <AddonSettingsPage
              embedded
              config={c}
              title="Multilingual"
              addonId="multilingual_enterprise"
              subtitle="WPML / Weglot compatibility, translatable settings strings, and per-course overrides."
              featureTitle="Multilingual"
              featureDescription="Make Sikshya’s course pages, cart/checkout, learn experience, and key settings behave correctly in translated sites."
              nextSteps={[
                {
                  label: 'Enable the add-on',
                  href: appViewHref(c, 'addons'),
                  description: 'Turn on “Multilingual” so Sikshya loads its translation compatibility hooks.',
                },
                {
                  label: 'Translate courses and lessons',
                  description: 'Use your multilingual plugin’s workflow (WPML/Weglot) to translate course, lesson, quiz, and assignment content.',
                },
                {
                  label: 'Per-course overrides',
                  description: 'Open a course in Course Builder → Course options → Multilingual to disable translation for specific courses.',
                },
              ]}
            />
          ),
        },
      ]}
    />
  );
}

export function LearningRulesHubPage({ embedded, config, title }: Props) {
  return (
    <TabbedHubPage
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Cross-course access logic — when does each lesson open, and what must be done first."
      sidebarActivePage="learning-rules"
      tabs={[
        {
          id: 'drip',
          label: 'Scheduled access',
          icon: 'schedule',
          render: (c) => <ContentDripPage embedded config={c} title="Scheduled access" />,
        },
        {
          id: 'prerequisites',
          label: 'Prerequisites',
          icon: 'puzzle',
          render: (c) => <PrerequisitesPage embedded config={c} title="Prerequisites" />,
        },
      ]}
    />
  );
}

/**
 * Reports hub. Calendar lived under the Reports group anyway and is really a
 * dated event feed, so it now sits next to Overview as a peer tab. Gradebook
 * keeps its own sidebar entry because it is a true second job ("grade work")
 * rather than a different view of the same dataset.
 */
export function ReportsHubPage({ embedded, config, title }: Props) {
  return (
    <TabbedHubPage
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Enrollment, completion, and dated events."
      sidebarActivePage="reports"
      tabs={[
        {
          id: 'overview',
          label: 'Overview',
          icon: 'chart',
          render: (c) => <ReportsPage embedded config={c} title="Reports overview" />,
        },
        {
          id: 'calendar',
          label: 'Calendar',
          icon: 'schedule',
          render: (c) => <CalendarPage embedded config={c} title="Calendar" />,
        },
      ]}
    />
  );
}

/**
 * Tools hub. Activity log was previously misfiled under Reports — it is an
 * audit trail used during support / compliance, not a metric. Putting it next
 * to system diagnostics is a better mental model.
 */
export function ToolsHubPage({ embedded, config, title }: Props) {
  return (
    <TabbedHubPage
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Diagnostics, exports, audit log, and maintenance."
      sidebarActivePage="tools"
      tabs={[
        {
          id: 'system',
          label: 'System',
          icon: 'wrench',
          render: (c) => <ToolsPage embedded config={c} title="Tools" />,
        },
        {
          id: 'activity',
          label: 'Activity log',
          icon: 'clipboard',
          render: (c) => <ActivityLogPage embedded config={c} title="Activity log" />,
        },
      ]}
    />
  );
}
