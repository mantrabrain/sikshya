import type { WpPost } from '../types';

/** @deprecated import from `mockWpPosts` — kept for course-specific imports */
export const MOCK_WP_COURSES: WpPost[] = [
  {
    id: 9101,
    title: { rendered: 'WordPress fundamentals' },
    status: 'publish',
    slug: 'wordpress-fundamentals',
    date: '2025-01-10T10:00:00',
    modified: '2025-03-18T14:30:00',
  },
  {
    id: 9102,
    title: { rendered: 'Design systems for product teams' },
    status: 'publish',
    slug: 'design-systems',
    date: '2025-02-01T09:00:00',
    modified: '2025-03-12T11:00:00',
  },
  {
    id: 9103,
    title: { rendered: 'Intro to block themes' },
    status: 'draft',
    slug: 'block-themes-intro',
    date: '2025-03-01T16:00:00',
    modified: '2025-03-20T08:15:00',
  },
  {
    id: 9104,
    title: { rendered: 'LMS monetization playbook' },
    status: 'pending',
    slug: 'lms-monetization',
    date: '2025-03-19T12:00:00',
    modified: '2025-03-19T12:00:00',
  },
  {
    id: 9105,
    title: { rendered: 'Accessibility in online courses' },
    status: 'publish',
    slug: 'a11y-courses',
    date: '2024-11-05T10:00:00',
    modified: '2025-02-28T17:45:00',
  },
];

const MOCK_LESSONS: WpPost[] = [
  {
    id: 9201,
    title: { rendered: 'Welcome & orientation' },
    status: 'publish',
    slug: 'welcome-orientation',
    modified: '2025-03-10T10:00:00',
  },
  {
    id: 9202,
    title: { rendered: 'Installing your stack' },
    status: 'publish',
    slug: 'installing-stack',
    modified: '2025-03-08T14:00:00',
  },
  {
    id: 9203,
    title: { rendered: 'Quiz review lesson' },
    status: 'draft',
    slug: 'quiz-review',
    modified: '2025-03-05T09:00:00',
  },
];

const MOCK_QUIZZES: WpPost[] = [
  {
    id: 9301,
    title: { rendered: 'Module 1 checkpoint' },
    status: 'publish',
    slug: 'm1-checkpoint',
    modified: '2025-03-12T11:00:00',
  },
  {
    id: 9302,
    title: { rendered: 'Final assessment' },
    status: 'draft',
    slug: 'final-assessment',
    modified: '2025-03-01T16:00:00',
  },
];

const MOCK_ASSIGNMENTS: WpPost[] = [
  {
    id: 9401,
    title: { rendered: 'Capstone project brief' },
    status: 'publish',
    slug: 'capstone-brief',
    modified: '2025-02-20T12:00:00',
  },
  {
    id: 9402,
    title: { rendered: 'Peer review worksheet' },
    status: 'publish',
    slug: 'peer-review',
    modified: '2025-02-18T09:30:00',
  },
];

const MOCK_QUESTIONS: WpPost[] = [
  {
    id: 9501,
    title: { rendered: 'What is a learning objective?' },
    status: 'publish',
    slug: 'q-learning-objective',
    modified: '2025-01-15T10:00:00',
  },
  {
    id: 9502,
    title: { rendered: 'Select all valid quiz settings' },
    status: 'draft',
    slug: 'q-quiz-settings',
    modified: '2025-01-14T08:00:00',
  },
];

const MOCK_CHAPTERS: WpPost[] = [
  {
    id: 9601,
    title: { rendered: 'Getting started' },
    status: 'publish',
    slug: 'ch-getting-started',
    modified: '2025-03-01T10:00:00',
  },
  {
    id: 9602,
    title: { rendered: 'Advanced topics' },
    status: 'publish',
    slug: 'ch-advanced',
    modified: '2025-02-28T15:00:00',
  },
];

const MOCK_CERTIFICATES: WpPost[] = [
  {
    id: 9701,
    title: { rendered: 'Course completion certificate' },
    status: 'publish',
    slug: 'tpl-completion',
    modified: '2025-02-10T11:00:00',
    meta: { _sikshya_certificate_orientation: 'landscape' },
    _embedded: {
      'wp:featuredmedia': [{ source_url: 'https://picsum.photos/seed/sikshya-cert1/320/200', alt_text: '' }],
    },
  },
  {
    id: 9702,
    title: { rendered: 'Achievement — perfect score' },
    status: 'draft',
    slug: 'tpl-perfect',
    modified: '2025-02-09T14:00:00',
    meta: { _sikshya_certificate_orientation: 'portrait' },
  },
];

const MOCK_BY_REST: Record<string, WpPost[]> = {
  sik_course: MOCK_WP_COURSES,
  sik_lesson: MOCK_LESSONS,
  sik_quiz: MOCK_QUIZZES,
  sik_assignment: MOCK_ASSIGNMENTS,
  sik_question: MOCK_QUESTIONS,
  sik_chapter: MOCK_CHAPTERS,
  sikshya_certificate: MOCK_CERTIFICATES,
};

export function getMockRowsForRestBase(restBase: string): WpPost[] {
  return MOCK_BY_REST[restBase] ?? [];
}
