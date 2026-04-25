/**
 * Single source of truth for Sikshya REST (`/wp-json/sikshya/v1/...`) paths.
 * Do not hard-code these strings in components — import from here.
 */
export const SIKSHYA_ENDPOINTS = {
  courseBuilder: {
    save: '/course-builder/save',
    bootstrap: (courseId: number) =>
      `/course-builder/bootstrap?course_id=${encodeURIComponent(String(courseId))}`,
    /** Directly set _sikshya_course_type meta (avoids fragile WP REST meta PATCH). */
    setType: '/course-builder/set-type',
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
    /** Course reviews moderation (list + approve/reject/delete). */
    reviews: (params?: { status?: string; course_id?: number; search?: string; page?: number; per_page?: number }) => {
      const q = new URLSearchParams();
      if (params?.status) q.set('status', params.status);
      if (params?.course_id) q.set('course_id', String(params.course_id));
      if (params?.search) q.set('search', params.search);
      if (params?.page) q.set('page', String(params.page));
      if (params?.per_page) q.set('per_page', String(params.per_page));
      const s = q.toString();
      return s ? `/admin/reviews?${s}` : '/admin/reviews';
    },
    reviewApprove: (id: number) => `/admin/reviews/${encodeURIComponent(String(id))}/approve`,
    reviewReject: (id: number) => `/admin/reviews/${encodeURIComponent(String(id))}/reject`,
    reviewDelete: (id: number) => `/admin/reviews/${encodeURIComponent(String(id))}`,
    /** Editable transactional email templates (system + custom). */
    emailTemplates: '/admin/email-templates',
    emailTemplate: (id: string) => `/admin/email-templates/${encodeURIComponent(id)}`,
    emailTemplatePreview: (id: string) => `/admin/email-templates/${encodeURIComponent(id)}/preview`,
    /** Bulk enable / disable / delete custom templates. */
    emailTemplateBulk: '/admin/email-template-bulk',
  },
  /** Registered by `sikshya-pro`; 403 when plan lacks feature (`sikshya_pro_required`) or addon off (`sikshya_addon_disabled`). */
  pro: {
    dripRules: '/pro/drip-rules',
    dripRule: (id: number) => `/pro/drip-rules/${encodeURIComponent(String(id))}`,
    subscriptions: '/pro/subscriptions',
    subscriptionsCancel: '/pro/subscriptions/cancel',
    plans: '/pro/plans',
    plan: (id: number) => `/pro/plans/${encodeURIComponent(String(id))}`,
    gradebook: (courseId?: number) =>
      courseId
        ? `/pro/gradebook?course_id=${encodeURIComponent(String(courseId))}`
        : '/pro/gradebook',
    gradebookExport: (courseId?: number) =>
      courseId
        ? `/pro/gradebook/export?course_id=${encodeURIComponent(String(courseId))}`
        : '/pro/gradebook/export',
    gradebookLearner: (params: { user_id: number; course_id: number }) =>
      `/pro/gradebook/learner?user_id=${encodeURIComponent(String(params.user_id))}&course_id=${encodeURIComponent(String(params.course_id))}`,
    gradebookOverride: '/pro/gradebook/override',
    gradebookGrid: (courseId: number) => `/pro/gradebook/grid?course_id=${encodeURIComponent(String(courseId))}`,
    gradebookDrilldown: (params: { course_id: number; user_id: number; item_type: 'quiz' | 'assignment'; item_id: number }) =>
      `/pro/gradebook/drilldown?course_id=${encodeURIComponent(String(params.course_id))}&user_id=${encodeURIComponent(String(params.user_id))}&item_type=${encodeURIComponent(params.item_type)}&item_id=${encodeURIComponent(String(params.item_id))}`,
    gradebookAssignmentGrade: '/pro/gradebook/assignment-grade',
    gradeScales: '/pro/grade-scales',
    gradeScale: (id: number) => `/pro/grade-scales/${encodeURIComponent(String(id))}`,
    courseInstructors: (courseId: number) =>
      `/pro/course-instructors?course_id=${encodeURIComponent(String(courseId))}`,
    addCourseInstructor: '/pro/course-instructors',
    earnings: (userId: number) => `/pro/earnings?user_id=${encodeURIComponent(String(userId))}`,
    advancedCertificates: '/pro/certificates/advanced',
    /** Growth+ advanced analytics export (addon + plan gated on server). */
    reportsExport: '/pro/extended/reports-export',
    /** Growth+ learner audit trail (addon + plan gated on server). */
    activityLog: (params?: { per_page?: number; page?: number }) => {
      const q = new URLSearchParams();
      if (params?.per_page) q.set('per_page', String(params.per_page));
      if (params?.page) q.set('page', String(params.page));
      const s = q.toString();
      return s ? `/pro/extended/activity-log?${s}` : '/pro/extended/activity-log';
    },
    /** Pro/Scale prerequisites editor (course-level + lesson-level). */
    coursePrerequisites: (id: number) =>
      `/pro/courses/${encodeURIComponent(String(id))}/prerequisites`,
    lessonPrerequisites: (id: number) =>
      `/pro/lessons/${encodeURIComponent(String(id))}/prerequisites`,
    courseLessons: (id: number) =>
      `/pro/courses/${encodeURIComponent(String(id))}/lessons`,
    /** Per-course list of lessons that have lesson-level prerequisite locks (read-only summary for UI). */
    courseLessonPrerequisiteSummary: (id: number) =>
      `/pro/courses/${encodeURIComponent(String(id))}/lesson-prerequisite-summary`,
    /** Reusable picker for course pickers (Bundles, Coupons, Prereqs). */
    coursesSearch: (params: { search?: string; exclude?: number[]; per_page?: number }) => {
      const q = new URLSearchParams();
      if (params.search) q.set('search', params.search);
      if (params.exclude && params.exclude.length) q.set('exclude', params.exclude.join(','));
      if (params.per_page) q.set('per_page', String(params.per_page));
      const s = q.toString();
      return s ? `/pro/courses/search?${s}` : '/pro/courses/search';
    },
    /** Course bundles (Pro). */
    bundles: '/pro/bundles',
    bundle: (id: number) => `/pro/bundles/${encodeURIComponent(String(id))}`,
    bundleCourses: (id: number) => `/pro/bundles/${encodeURIComponent(String(id))}/courses`,
    bundleCoursesItem: (id: number, courseId: number) =>
      `/pro/bundles/${encodeURIComponent(String(id))}/courses/${encodeURIComponent(String(courseId))}`,
    /** Signed storefront URL that adds all bundle courses to the cart at the bundle price. */
    bundlePurchaseLink: (id: number) =>
      `/pro/bundles/${encodeURIComponent(String(id))}/purchase-link`,
    /** Coupon advanced rules (min subtotal + allowed course ids). */
    couponAdvanced: (id: number) => `/pro/coupons/${encodeURIComponent(String(id))}/advanced`,
    /** Social login provider config (toggles + client ids/secrets). */
    socialLogin: '/pro/social-login',
    /** White-label / branding (logo, colors, hide credit). */
    whiteLabel: '/pro/white-label',
    /** Staff: recent course publish dates (reporting). */
    calendarFeed: '/pro/extended/calendar',
    /** Logged-in learner: enrollments, drip unlocks, assignments, live sessions. */
    learnerCalendar: '/pro/learner/calendar',
    /** Instructor dashboard summary (revenue, learners, courses). */
    instructorSummary: '/pro/extended/instructor-summary',
    /** Enterprise weekly summary report controls. */
    enterpriseReportsStatus: '/pro/enterprise-reports/status',
    enterpriseReportsRun: '/pro/enterprise-reports/run',
    enterpriseReportsSettings: '/pro/enterprise-reports/settings',
    /** Drip notifications opt-in status. */
    dripNotificationsStatus: '/pro/drip-notifications/status',
    prerequisiteCourses: (params?: {
      search?: string;
      page?: number;
      per_page?: number;
      course_id?: number;
      orderby?: string;
      order?: 'asc' | 'desc';
      /** When true, list only courses that still have enrollment and/or lesson locks (row drops off after full delete). */
      only_with_locks?: boolean;
    }) => {
      const q = new URLSearchParams();
      if (params?.search) q.set('search', params.search);
      if (params?.page) q.set('page', String(params.page));
      if (params?.per_page) q.set('per_page', String(params.per_page));
      if (params?.course_id && params.course_id > 0) q.set('course_id', String(params.course_id));
      if (params?.orderby) q.set('orderby', params.orderby);
      if (params?.order) q.set('order', params.order);
      if (params?.only_with_locks) q.set('only_with_locks', '1');
      const s = q.toString();
      return s ? `/pro/prerequisites/courses?${s}` : '/pro/prerequisites/courses';
    },
  },
  /** Scale-tier marketplace + automation (`sikshya-pro`). Same 403 rules as `pro`. */
  scale: {
    vendors: '/scale/vendors',
    withdrawals: '/scale/withdrawals',
    commissionsReport: '/scale/reports/commissions',
    automationWebhooks: '/scale/automation/webhooks',
    automationWebhook: (id: number) => `/scale/automation/webhooks/${encodeURIComponent(String(id))}`,
    publicApiKeys: '/scale/public-api/keys',
    publicApiKey: (id: number) => `/scale/public-api/keys/${encodeURIComponent(String(id))}`,
    publicApiPing: '/scale/public-api/ping',
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
    /** POST — sends test mail through the same path as transactional email (Growth+ + professional email add-on). */
    emailTestDelivery: '/settings/email/test-delivery',
  },
} as const;

/**
 * WordPress Core REST (`wp/v2`) — path after the base.
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
