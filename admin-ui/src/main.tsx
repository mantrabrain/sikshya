import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App';
import type { SikshyaReactConfig } from './types';

if (import.meta.env.DEV && !window.sikshyaReact) {
  window.sikshyaReact = {
    page: 'dashboard',
    version: 'dev',
    restUrl: '/wp-json/sikshya/v1/',
    restNonce: '',
    adminUrl: '/wp-admin/',
    appAdminBase: 'http://localhost/wp-admin/admin.php?page=sikshya',
    siteUrl: '/',
    pluginUrl: '',
    user: { name: 'Developer', avatarUrl: '' },
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
    licensing: {
      isProActive: false,
      siteTier: 'free',
      siteTierLabel: 'Free',
      upgradeUrl: 'https://store.mantrabrain.com/downloads/sikshya-pro/',
      featureStates: {},
      catalog: [],
    },
  } satisfies SikshyaReactConfig;
}

const rootEl = document.getElementById('sikshya-admin-root');
if (rootEl) {
  createRoot(rootEl).render(
    <StrictMode>
      <App />
    </StrictMode>
  );
}
