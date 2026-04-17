/**
 * Single source of truth for Sikshya REST (`/wp-json/sikshya/v1/...`) paths.
 * Do not hard-code these strings in components — import from here.
 */
export const SIKSHYA_ENDPOINTS = {
  courseBuilder: {
    save: '/course-builder/save',
    bootstrap: (courseId: number) =>
      `/course-builder/bootstrap?course_id=${encodeURIComponent(String(courseId))}`,
  },
  admin: {
    courseChapters: (courseId: number) =>
      `/admin/course-chapters?course_id=${encodeURIComponent(String(courseId))}`,
    courseCurriculumTree: (courseId: number) =>
      `/admin/course-curriculum-tree?course_id=${encodeURIComponent(String(courseId))}`,
    /** Aggregate status totals for list tabs (one request per post type). */
    postStatusCounts: (postType: string) =>
      `/admin/post-status-counts?post_type=${encodeURIComponent(postType)}`,
    /** Single course category (name, slug, parent, featured image id). */
    courseCategory: (termId: number) => `/taxonomies/course-category/${encodeURIComponent(String(termId))}`,
    courseCategorySave: '/taxonomies/course-category',
    /** Maintainer actions (cache, diagnostics, export/import). Requires `manage_options`. */
    tools: '/tools',
    /** Live dashboard payload (stats + recent courses). Course-manager auth. */
    overview: '/admin/overview',
    /** Reports chart + stats refresh. Course-manager auth. */
    reportsSnapshot: '/admin/reports-snapshot',
    /** Paginated quiz attempts (per learner + quiz). Course-manager auth. */
    quizAttempts: '/admin/quiz-attempts',
    /** Paginated enrollments with learner/course labels. Course-manager auth. */
    enrollments: '/admin/enrollments',
    /** Paginated payments. Course-manager auth (same as other admin routes). */
    payments: '/admin/payments',
    /** Checkout orders (normalized ledger). */
    orders: '/admin/orders',
    ordersMarkPaid: (id: number) =>
      `/admin/orders/${encodeURIComponent(String(id))}/mark-paid`,
    /** Coupon codes — GET list, POST create. */
    coupons: '/admin/coupons',
    /** Issued learner certificates. */
    issuedCertificates: '/admin/issued-certificates',
    issuedCertificatesRevoke: '/admin/issued-certificates/revoke',
    /** Feature catalog + Pro gates (same payload as `config.licensing`). */
    licensing: '/admin/licensing',
  },
  /** Registered by `sikshya-pro` on the same namespace; 403 when Pro inactive or feature locked. */
  pro: {
    dripRules: '/pro/drip-rules',
    subscriptions: '/pro/subscriptions',
    gradebook: (courseId?: number) =>
      courseId
        ? `/pro/gradebook?course_id=${encodeURIComponent(String(courseId))}`
        : '/pro/gradebook',
    courseInstructors: (courseId: number) =>
      `/pro/course-instructors?course_id=${encodeURIComponent(String(courseId))}`,
    addCourseInstructor: '/pro/course-instructors',
    advancedCertificates: '/pro/certificates/advanced',
  },
  elite: {
    vendors: '/elite/vendors',
    withdrawals: '/elite/withdrawals',
    commissionsReport: '/elite/reports/commissions',
  },
  curriculum: {
    chapters: '/curriculum/chapters',
    content: '/curriculum/content',
    contentLink: '/curriculum/content/link',
    /** Full chapter order + per-chapter content IDs (drag-and-drop outline). */
    outlineStructure: '/curriculum/outline-structure',
  },
  settings: {
    schema: '/settings/schema',
    values: (tab: string) => `/settings/values?tab=${encodeURIComponent(tab)}`,
    save: '/settings/save',
    reset: '/settings/reset',
  },
} as const;

/**
 * WordPress Core REST (`/wp-json/wp/v2/...`) — path after `/wp/v2`.
 */
export const WP_ENDPOINTS = {
  /** List/edit context collection for a registered post type REST base (e.g. `sik_course`). */
  postTypeCollection: (
    restBase: string,
    params: Record<string, string | number | boolean> = {}
  ) => {
    const q = new URLSearchParams({
      per_page: '20',
      page: '1',
      status: 'any',
      context: 'edit',
      ...Object.fromEntries(
        Object.entries(params).map(([k, v]) => [k, String(v)])
      ),
    });
    return `/${restBase.replace(/^\//, '')}?${q.toString()}`;
  },
} as const;
