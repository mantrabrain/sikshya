import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App';
import type { SikshyaReactConfig } from './types';

if (import.meta.env.DEV && !window.sikshyaReact) {
  window.sikshyaReact = {
    page: 'dashboard',
    version: 'dev',
    // Match WordPress behavior for plain permalinks in dev too.
    restUrl: '/?rest_route=/sikshya/v1/',
    wpRestUrl: '/?rest_route=/wp/v2/',
    restNonce: '',
    adminUrl: '/wp-admin/',
    appAdminBase: 'http://localhost/wp-admin/admin.php?page=sikshya',
    setupWizardUrl: '/wp-admin/admin.php?page=sikshya-setup&step=1',
    siteUrl: '/',
    pluginUrl: '',
    user: {
      name: 'Developer',
      email: 'dev@example.com',
      avatarUrl: 'https://www.gravatar.com/avatar/be9d18f611892a738e54f2a3a171e2f9?s=160&d=mp&r=g',
      profileUrl: '/wp-admin/profile.php',
      logoutUrl: '/wp-admin/',
    },
    navigation: [
      {
        id: 'dashboard',
        label: 'Dashboard',
        icon: 'dashboard',
        href: '/wp-admin/admin.php?page=sikshya&view=dashboard',
      },
      {
        id: 'course',
        label: 'Course',
        icon: 'course',
        children: [
          {
            id: 'courses',
            label: 'All courses',
            icon: 'table',
            href: '/wp-admin/admin.php?page=sikshya&view=courses',
          },
          {
            id: 'lessons',
            label: 'Lessons',
            icon: 'bookOpen',
            href: '/wp-admin/admin.php?page=sikshya&view=lessons',
          },
        ],
      },
    ],
    initialData: {
      siteName: 'Local Sikshya',
      stats: {
        publishedCourses: 3,
        draftCourses: 1,
        lessons: 12,
        quizzes: 4,
        students: 28,
        revenue: '$0.00',
        enrollments: 0,
      },
      recentCourses: [
        { id: 1, title: 'Intro to WordPress', status: 'publish', modified: new Date().toISOString() },
        { id: 2, title: 'Design systems 101', status: 'draft', modified: new Date().toISOString() },
      ],
    },
    query: {},
    offlineCheckoutEnabled: true,
    licensing: {
      isProActive: false,
      siteTier: 'free',
      siteTierLabel: 'Free',
      upgradeUrl: 'https://mantrabrain.com/plugins/sikshya/#pricing',
      featureStates: {},
      /** Minimal rows so `isFeatureEnabled` + overlays behave like production in Vite dev */
      catalog: [
        { id: 'core_course_builder', label: 'Course builder', tier: 'free', group: 'core', description: '' },
        { id: 'gradebook', label: 'Gradebook', tier: 'pro', group: 'analytics', description: '' },
        { id: 'content_drip', label: 'Content drip', tier: 'starter', group: 'course', description: '' },
        { id: 'subscriptions', label: 'Subscriptions', tier: 'pro', group: 'commerce', description: '' },
        { id: 'multi_instructor', label: 'Multi-instructor', tier: 'pro', group: 'course', description: '' },
        { id: 'marketplace_multivendor', label: 'Marketplace', tier: 'scale', group: 'commerce', description: '' },
        { id: 'certificates_advanced', label: 'Advanced certificates', tier: 'pro', group: 'certificates', description: '' },
        { id: 'automation_zapier_webhooks', label: 'Webhooks', tier: 'scale', group: 'integrations', description: '' },
        { id: 'public_api_keys', label: 'API keys', tier: 'scale', group: 'platform', description: '' },
      ],
    },
  } satisfies SikshyaReactConfig;
}

const rootEl = document.getElementById('sikshya-admin-root');
// Guard against double-loading the module in wp-admin (e.g. caching/CDN issues or duplicate enqueue).
const w = window as unknown as { __sikshyaAdminMounted?: boolean };
if (rootEl && !w.__sikshyaAdminMounted) {
  w.__sikshyaAdminMounted = true;
  createRoot(rootEl).render(
    <StrictMode>
      <App />
    </StrictMode>
  );
}
