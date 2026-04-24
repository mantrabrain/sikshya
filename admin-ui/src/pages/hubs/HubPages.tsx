import { TabbedHubPage } from '../../components/shared/TabbedHubPage';
import type { SikshyaReactConfig } from '../../types';
import { WpEntityListPage } from '../WpEntityListPage';
import { WpUserListPage } from '../WpUserListPage';
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
import { ReportsPage } from '../ReportsPage';
import { ToolsPage } from '../ToolsPage';
import { isFeatureEnabled } from '../../lib/licensing';

type Props = { config: SikshyaReactConfig; title: string };

/**
 * Library hub: a single nav entry that fans out to the five "all rows of one
 * post type" lists (Lessons / Quizzes / Assignments / Questions / Chapters).
 * Replaces five separate sidebar entries that each rendered the same component
 * with a different `restBase`.
 */
export function ContentLibraryHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
      config={config}
      title={title}
      subtitle="Browse every lesson, quiz, assignment, question, and chapter on the site."
      sidebarActivePage="content-library"
      tabs={[
        {
          id: 'lessons',
          label: 'Lessons',
          icon: 'bookOpen',
          render: (c) => (
            <WpEntityListPage embedded config={c} title="Lessons" subtitle="All lessons" restBase="sik_lesson" />
          ),
        },
        {
          id: 'quizzes',
          label: 'Quizzes',
          icon: 'puzzle',
          render: (c) => (
            <WpEntityListPage embedded config={c} title="Quizzes" subtitle="All quizzes" restBase="sik_quiz" />
          ),
        },
        {
          id: 'assignments',
          label: 'Assignments',
          icon: 'clipboard',
          render: (c) => (
            <WpEntityListPage
              embedded
              config={c}
              title="Assignments"
              subtitle="All assignments"
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
          label: 'Chapters',
          icon: 'layers',
          render: (c) => (
            <WpEntityListPage embedded config={c} title="Chapters" subtitle="All chapters" restBase="sik_chapter" />
          ),
        },
      ]}
    />
  );
}

export function PeopleHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
      config={config}
      title={title}
      subtitle="Learners and instructors that have a Sikshya role on this site."
      sidebarActivePage="people"
      tabs={[
        {
          id: 'students',
          label: 'Students',
          icon: 'users',
          render: (c) => (
            <WpUserListPage
              embedded
              config={c}
              title="Students"
              subtitle="Users with the Sikshya student role."
              variant="students"
            />
          ),
        },
        {
          id: 'instructors',
          label: 'Instructors',
          icon: 'userCircle',
          render: (c) => (
            <WpUserListPage
              embedded
              config={c}
              title="Instructors"
              subtitle="Users with the Sikshya instructor role."
              variant="instructors"
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
      ]}
    />
  );
}

export function SalesHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
      config={config}
      title={title}
      subtitle="Checkout orders and the payment records they produce."
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
  return (
    <TabbedHubPage
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
      config={config}
      title={title}
      subtitle="How your school looks across login, account, and admin screens."
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
      config={config}
      title={title}
      subtitle="Send Sikshya events to Zapier, Make, custom endpoints, and sync contacts into email marketing tools."
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
      ]}
    />
  );
}

export function LearningRulesHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
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
export function ReportsHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
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
export function ToolsHubPage({ config, title }: Props) {
  return (
    <TabbedHubPage
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
