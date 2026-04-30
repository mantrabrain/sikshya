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
    quizAttemptResetTimer: (id: number) => `/admin/quiz-attempts/${encodeURIComponent(String(id))}/reset-timer`,
    /** Paginated enrollments with learner/course labels. Course-manager auth. */
    enrollments: '/admin/enrollments',
    /** POST: manually enroll a learner into a course. */
    enrollmentsManual: '/admin/enrollments/manual',
    /** Instructor application queue (user meta). Requires manage_sikshya or manage_options. */
    instructorApplications: (params?: { status?: string; search?: string; page?: number; per_page?: number }) => {
      const q = new URLSearchParams();
      if (params?.status) q.set('status', params.status);
      if (params?.search) q.set('search', params.search);
      if (params?.page) q.set('page', String(params.page));
      if (params?.per_page) q.set('per_page', String(params.per_page));
      const s = q.toString();
      return s ? `/admin/instructor-applications?${s}` : '/admin/instructor-applications';
    },
    instructorApplicationApprove: (id: number) =>
      `/admin/instructor-applications/${encodeURIComponent(String(id))}/approve`,
    instructorApplicationReject: (id: number) =>
      `/admin/instructor-applications/${encodeURIComponent(String(id))}/reject`,
    /** Paginated payments. Course-manager auth (same as other admin routes). */
    payments: '/admin/payments',
    payment: (id: number) => `/admin/payments/${encodeURIComponent(String(id))}`,
    paymentUpdate: (id: number) => `/admin/payments/${encodeURIComponent(String(id))}`,
    /** Checkout orders: GET list, POST create manual (admin). */
    orders: '/admin/orders',
    order: (id: number) => `/admin/orders/${encodeURIComponent(String(id))}`,
    orderUpdate: (id: number) => `/admin/orders/${encodeURIComponent(String(id))}`,
    ordersMarkPaid: (id: number) =>
      `/admin/orders/${encodeURIComponent(String(id))}/mark-paid`,
    /** Coupon codes — GET list, POST create. */
    coupons: '/admin/coupons',
    coupon: (id: number) => `/admin/coupons/${encodeURIComponent(String(id))}`,
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
    reviewReply: (id: number) => `/admin/reviews/${encodeURIComponent(String(id))}/reply`,
    /** Community discussions / Q&A admin moderation (Pro `community_discussions` addon). */
    discussions: (params?: {
      course_id?: number;
      content_type?: 'lesson' | 'quiz';
      thread_type?: 'discussion' | 'qa';
      status?: 'pending' | 'approved' | 'spam' | 'trash' | 'all';
      attention?: 'all' | 'moderate' | 'reply' | 'answered' | 'spam';
      search?: string;
      page?: number;
      per_page?: number;
    }) => {
      const q = new URLSearchParams();
      if (params?.course_id && params.course_id > 0) q.set('course_id', String(params.course_id));
      if (params?.content_type) q.set('content_type', params.content_type);
      if (params?.thread_type) q.set('thread_type', params.thread_type);
      if (params?.status) q.set('status', params.status);
      if (params?.attention && params.attention !== 'all') q.set('attention', params.attention);
      if (params?.search) q.set('search', params.search);
      if (params?.page) q.set('page', String(params.page));
      if (params?.per_page) q.set('per_page', String(params.per_page));
      const s = q.toString();
      return s ? `/admin/discussions?${s}` : '/admin/discussions';
    },
    discussionsSummary: '/admin/discussions/summary',
    /** POST JSON `{ action: 'approve'|'spam'|'trash'|'delete', ids: number[] }` (max 100 ids). Pro addon. */
    discussionsBulk: '/admin/discussions/bulk',
    discussion: (id: number) => `/admin/discussions/${encodeURIComponent(String(id))}`,
    discussionReply: (id: number) => `/admin/discussions/${encodeURIComponent(String(id))}/reply`,
    discussionApprove: (id: number) => `/admin/discussions/${encodeURIComponent(String(id))}/approve`,
    /** @deprecated Prefer `discussionMarkSpam`; kept for backward compatibility (maps to spam). */
    discussionReject: (id: number) => `/admin/discussions/${encodeURIComponent(String(id))}/reject`,
    discussionMarkSpam: (id: number) => `/admin/discussions/${encodeURIComponent(String(id))}/spam`,
    discussionTrash: (id: number) => `/admin/discussions/${encodeURIComponent(String(id))}/trash`,
    /** Editable transactional email templates (system + custom). */
    emailTemplates: '/admin/email-templates',
    emailTemplate: (id: string) => `/admin/email-templates/${encodeURIComponent(id)}`,
    emailTemplatePreview: (id: string) => `/admin/email-templates/${encodeURIComponent(id)}/preview`,
    /** Bulk enable / disable / delete custom templates. */
    emailTemplateBulk: '/admin/email-template-bulk',
    /** Manually trigger usage tracking send (admin test button). */
    usageTrackingSendNow: '/admin/usage-tracking/send-now',
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
    multiInstructorCourseStaff: (courseId: number) =>
      `/pro/multi-instructor/course-staff?course_id=${encodeURIComponent(String(courseId))}`,
    multiInstructorCourseStaffAll: (params?: { per_page?: number; page?: number }) => {
      const q = new URLSearchParams();
      if (params?.per_page) q.set('per_page', String(params.per_page));
      if (params?.page) q.set('page', String(params.page));
      const s = q.toString();
      return s ? `/pro/multi-instructor/course-staff-all?${s}` : '/pro/multi-instructor/course-staff-all';
    },
    multiInstructorCourseStaffWrite: '/pro/multi-instructor/course-staff',
    multiInstructorEarnings: (userId: number) =>
      `/pro/multi-instructor/earnings?user_id=${encodeURIComponent(String(userId))}`,
    /** POST `{ id: number, status: 'pending' | 'paid' }` — `manage_options` only. */
    multiInstructorEarningsSetStatus: '/pro/multi-instructor/earnings/set-status',
    advancedCertificates: '/pro/certificates/advanced',
    /**
     * Advanced analytics CSV export (`reports_advanced` addon). Query: `type`, optional filters.
     * @see `SikshyaPro\Addons\ReportsAdvanced\Controllers\ReportsAdvancedRestController`
     */
    reportsAdvancedExport: (params: {
      type?: 'summary' | 'enrollments' | 'quiz_attempts';
      course_id?: number;
      status?: string;
      search?: string;
      date_from?: string;
      date_to?: string;
      user_id?: number;
      quiz_id?: number;
    }) => {
      const q = new URLSearchParams();
      if (params.type) q.set('type', params.type);
      if (params.course_id && params.course_id > 0) q.set('course_id', String(params.course_id));
      if (params.status) q.set('status', params.status);
      if (params.search) q.set('search', params.search);
      if (params.date_from) q.set('date_from', params.date_from);
      if (params.date_to) q.set('date_to', params.date_to);
      if (params.user_id && params.user_id > 0) q.set('user_id', String(params.user_id));
      if (params.quiz_id && params.quiz_id > 0) q.set('quiz_id', String(params.quiz_id));
      const s = q.toString();
      return s ? `/pro/reports-advanced/export?${s}` : '/pro/reports-advanced/export';
    },
    /** Growth+ learner audit trail (addon + plan gated on server). */
    activityLog: (params?: {
      per_page?: number;
      page?: number;
      user_id?: number;
      course_id?: number;
      action?: string;
      search?: string;
      date_from?: string;
      date_to?: string;
    }) => {
      const q = new URLSearchParams();
      if (params?.per_page) q.set('per_page', String(params.per_page));
      if (params?.page) q.set('page', String(params.page));
      if (params?.user_id && params.user_id > 0) q.set('user_id', String(params.user_id));
      if (params?.course_id && params.course_id > 0) q.set('course_id', String(params.course_id));
      if (params?.action && String(params.action).trim()) q.set('action', String(params.action).trim());
      if (params?.search && String(params.search).trim()) q.set('search', String(params.search).trim());
      if (params?.date_from && String(params.date_from).trim()) q.set('date_from', String(params.date_from).trim());
      if (params?.date_to && String(params.date_to).trim()) q.set('date_to', String(params.date_to).trim());
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
    /** Advanced quiz types: taxonomy-backed banks + pool diagnostics. */
    quizAdvancedBankTerms: '/pro/quiz-advanced/bank-terms',
    quizAdvancedPoolPreview: (tag: string) =>
      `/pro/quiz-advanced/pool-preview?tag=${encodeURIComponent(tag)}`,
    /** SCORM/H5P (Pro) — managed package library + reports + H5P picker. */
    scormPackages: (params?: { page?: number; per_page?: number; search?: string; status?: string }) => {
      const q = new URLSearchParams();
      if (params?.page) q.set('page', String(params.page));
      if (params?.per_page) q.set('per_page', String(params.per_page));
      if (params?.search) q.set('search', params.search);
      if (params?.status) q.set('status', params.status);
      const s = q.toString();
      return s ? `/pro/scorm-h5p/packages?${s}` : '/pro/scorm-h5p/packages';
    },
    scormPackage: (id: number) => `/pro/scorm-h5p/packages/${encodeURIComponent(String(id))}`,
    scormPackageAttach: (id: number) =>
      `/pro/scorm-h5p/packages/${encodeURIComponent(String(id))}/attach`,
    scormPackageDetach: (id: number) =>
      `/pro/scorm-h5p/packages/${encodeURIComponent(String(id))}/detach`,
    scormCourseSummary: (courseId: number) =>
      `/pro/scorm-h5p/reports/courses/${encodeURIComponent(String(courseId))}`,
    scormLessonAttempts: (lessonId: number, params?: { per_page?: number; offset?: number }) => {
      const q = new URLSearchParams();
      if (params?.per_page) q.set('per_page', String(params.per_page));
      if (params?.offset) q.set('offset', String(params.offset));
      const s = q.toString();
      return s
        ? `/pro/scorm-h5p/reports/lessons/${encodeURIComponent(String(lessonId))}/attempts?${s}`
        : `/pro/scorm-h5p/reports/lessons/${encodeURIComponent(String(lessonId))}/attempts`;
    },
    scormAttemptEvents: (attemptId: number) =>
      `/pro/scorm-h5p/reports/attempts/${encodeURIComponent(String(attemptId))}/events`,
    scormCourseExport: (courseId: number) =>
      `/pro/scorm-h5p/reports/courses/${encodeURIComponent(String(courseId))}/export`,
    scormAttemptsReset: '/pro/scorm-h5p/attempts/reset',
    h5pContents: (params?: { page?: number; per_page?: number; search?: string }) => {
      const q = new URLSearchParams();
      if (params?.page) q.set('page', String(params.page));
      if (params?.per_page) q.set('per_page', String(params.per_page));
      if (params?.search) q.set('search', params.search);
      const s = q.toString();
      return s ? `/pro/scorm-h5p/h5p/contents?${s}` : '/pro/scorm-h5p/h5p/contents';
    },
    /** Coupon advanced rules (min subtotal + allowed course ids). */
    couponAdvanced: (id: number) => `/pro/coupons/${encodeURIComponent(String(id))}/advanced`,
    /** Social login provider config (toggles + client ids/secrets). */
    socialLogin: '/pro/social-login',
    /** White-label / branding (logo, colors, hide credit). */
    whiteLabel: '/pro/white-label',
    /** Per-course white-label overrides. */
    whiteLabelCourse: (id: number) => `/pro/white-label/courses/${encodeURIComponent(String(id))}`,
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
    /** Enterprise reports v2 workspace. */
    enterpriseReportsDashboardV2: '/pro/enterprise-reports/v2/dashboard',
    enterpriseReportsSchedulesV2: '/pro/enterprise-reports/v2/schedules',
    enterpriseReportsScheduleV2: (id: number) =>
      `/pro/enterprise-reports/v2/schedules/${encodeURIComponent(String(id))}`,
    enterpriseReportsRunV2: '/pro/enterprise-reports/v2/run',
    enterpriseReportsArtifactDownloadV2: (id: number) =>
      `/pro/enterprise-reports/v2/artifacts/${encodeURIComponent(String(id))}/download`,
    /** Drip notifications opt-in status. */
    dripNotificationsStatus: '/pro/drip-notifications/status',
    /** POST body: { mode: 'per_lesson' | 'digest' } — lesson unlock email batching. */
    dripNotificationsSettings: '/pro/drip-notifications/settings',
    /** POST body: `{ to?: string }` — test HTML mail (manage_options + Growth+ + email advanced add-on). */
    emailAdvancedTestDelivery: '/pro/email-advanced/test-delivery',
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
  /** Marketplace multivendor addon — admin + vendor surfaces. */
  marketplace: {
    admin: {
      overview: '/pro/marketplace/admin/overview',
      vendors: (params?: { page?: number; per_page?: number; status?: string; search?: string }) => {
        const q = new URLSearchParams();
        if (params?.page) q.set('page', String(params.page));
        if (params?.per_page) q.set('per_page', String(params.per_page));
        if (params?.status) q.set('status', params.status);
        if (params?.search) q.set('search', params.search);
        const s = q.toString();
        return s ? `/pro/marketplace/admin/vendors?${s}` : '/pro/marketplace/admin/vendors';
      },
      vendor: (id: number) => `/pro/marketplace/admin/vendors/${encodeURIComponent(String(id))}`,
      commissions: (params?: { page?: number; per_page?: number; status?: string; vendor_user_id?: number; date_from?: string; date_to?: string }) => {
        const q = new URLSearchParams();
        if (params?.page) q.set('page', String(params.page));
        if (params?.per_page) q.set('per_page', String(params.per_page));
        if (params?.status) q.set('status', params.status);
        if (params?.vendor_user_id) q.set('vendor_user_id', String(params.vendor_user_id));
        if (params?.date_from) q.set('date_from', params.date_from);
        if (params?.date_to) q.set('date_to', params.date_to);
        const s = q.toString();
        return s ? `/pro/marketplace/admin/commissions?${s}` : '/pro/marketplace/admin/commissions';
      },
      withdrawals: (params?: { page?: number; per_page?: number; status?: string }) => {
        const q = new URLSearchParams();
        if (params?.page) q.set('page', String(params.page));
        if (params?.per_page) q.set('per_page', String(params.per_page));
        if (params?.status) q.set('status', params.status);
        const s = q.toString();
        return s ? `/pro/marketplace/admin/withdrawals?${s}` : '/pro/marketplace/admin/withdrawals';
      },
      withdrawalApprove: (id: number) => `/pro/marketplace/admin/withdrawals/${encodeURIComponent(String(id))}/approve`,
      withdrawalReject: (id: number) => `/pro/marketplace/admin/withdrawals/${encodeURIComponent(String(id))}/reject`,
      withdrawalMarkPaid: (id: number) => `/pro/marketplace/admin/withdrawals/${encodeURIComponent(String(id))}/mark-paid`,
      adjustments: '/pro/marketplace/admin/adjustments',
    },
    vendor: {
      me: '/pro/marketplace/vendor/me',
      profile: '/pro/marketplace/vendor/profile',
      payoutMethod: '/pro/marketplace/vendor/payout-method',
      earnings: (params?: { page?: number; per_page?: number; status?: string }) => {
        const q = new URLSearchParams();
        if (params?.page) q.set('page', String(params.page));
        if (params?.per_page) q.set('per_page', String(params.per_page));
        if (params?.status) q.set('status', params.status);
        const s = q.toString();
        return s ? `/pro/marketplace/vendor/earnings?${s}` : '/pro/marketplace/vendor/earnings';
      },
      withdrawals: (params?: { page?: number; per_page?: number }) => {
        const q = new URLSearchParams();
        if (params?.page) q.set('page', String(params.page));
        if (params?.per_page) q.set('per_page', String(params.per_page));
        const s = q.toString();
        return s ? `/pro/marketplace/vendor/withdrawals?${s}` : '/pro/marketplace/vendor/withdrawals';
      },
      withdrawalCancel: (id: number) => `/pro/marketplace/vendor/withdrawals/${encodeURIComponent(String(id))}/cancel`,
    },
  },
  /** Scale-tier automation (`sikshya-pro`). Same 403 rules as `pro`. */
  scale: {
    automationWebhooks: '/scale/automation/webhooks',
    automationWebhook: (id: number) => `/scale/automation/webhooks/${encodeURIComponent(String(id))}`,
    webhooksV2Endpoints: '/scale/webhooks/v2/endpoints',
    webhooksV2Endpoint: (id: number) => `/scale/webhooks/v2/endpoints/${encodeURIComponent(String(id))}`,
    webhooksV2EndpointRotateSecret: (id: number) => `/scale/webhooks/v2/endpoints/${encodeURIComponent(String(id))}/rotate-secret`,
    webhooksV2EndpointTest: (id: number) => `/scale/webhooks/v2/endpoints/${encodeURIComponent(String(id))}/test`,
    webhooksV2Deliveries: '/scale/webhooks/v2/deliveries',
    publicApiKeys: '/scale/public-api/keys',
    publicApiKey: (id: number) => `/scale/public-api/keys/${encodeURIComponent(String(id))}`,
    publicApiPing: '/scale/public-api/ping',
    publicApiApps: '/scale/public-api/apps',
    publicApiApp: (id: number) => `/scale/public-api/apps/${encodeURIComponent(String(id))}`,
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
