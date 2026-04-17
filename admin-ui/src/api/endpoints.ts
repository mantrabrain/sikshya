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
    /** Shell alerts + licensing + Pro version flags (refresh after licence changes). */
    shellMeta: (view: string) => `/admin/shell-meta?view=${encodeURIComponent(view)}`,
    /** Sikshya Pro license key (requires Pro plugin + `manage_options`). */
    license: '/admin/license',
    licenseActivate: '/admin/license/activate',
    licenseSave: '/admin/license/save',
    licenseDeactivate: '/admin/license/deactivate',
    licenseCheck: '/admin/license/check',
    /** Addon catalog + enable/disable toggles (module system). */
    addons: '/admin/addons',
    addonsEnable: (id: string) => `/admin/addons/${encodeURIComponent(id)}/enable`,
    addonsDisable: (id: string) => `/admin/addons/${encodeURIComponent(id)}/disable`,
  },
  /** Registered by `sikshya-pro` on the same namespace; 403 when Pro inactive or feature locked. */
  pro: {
    dripRules: '/pro/drip-rules',
    subscriptions: '/pro/subscriptions',
    subscriptionsCancel: '/pro/subscriptions/cancel',
    plans: '/pro/plans',
    gradebook: (courseId?: number) =>
      courseId
        ? `/pro/gradebook?course_id=${encodeURIComponent(String(courseId))}`
        : '/pro/gradebook',
    gradebookExport: (courseId?: number) =>
      courseId
        ? `/pro/gradebook/export?course_id=${encodeURIComponent(String(courseId))}`
        : '/pro/gradebook/export',
    courseInstructors: (courseId: number) =>
      `/pro/course-instructors?course_id=${encodeURIComponent(String(courseId))}`,
    addCourseInstructor: '/pro/course-instructors',
    earnings: (userId: number) => `/pro/earnings?user_id=${encodeURIComponent(String(userId))}`,
    advancedCertificates: '/pro/certificates/advanced',
  },
  elite: {
    vendors: '/elite/vendors',
    withdrawals: '/elite/withdrawals',
    commissionsReport: '/elite/reports/commissions',
    /** Outgoing automation webhooks (Elite). */
    automationWebhooks: '/elite/automation/webhooks',
    automationWebhook: (id: number) => `/elite/automation/webhooks/${encodeURIComponent(String(id))}`,
    /** Headless / partner API keys (Elite). */
    publicApiKeys: '/elite/public-api/keys',
    publicApiKey: (id: number) => `/elite/public-api/keys/${encodeURIComponent(String(id))}`,
    publicApiPing: '/elite/public-api/ping',
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
