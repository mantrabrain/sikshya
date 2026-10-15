#!/usr/bin/env python3
"""Generate docs/BROWSER-TEST-COVERAGE.md from a Playwright JSON report.

The spec -> feature mapping is explicit and hand-maintained on purpose: deriving
it by keyword matching gave wrong answers in both directions (features with good
coverage reported as zero, gated specs reported as executing).
"""
import json, sys, os, collections

# feature id -> tier, taken from Sikshya\Licensing\FeatureRegistry
TIERS = {
 'free': "core_course_builder lesson_video_text lesson_attachments course_preview_faq announcements"
         " student_dashboard quiz_basic certificates_basic assignments_basic checkout_native"
         " manual_enrollment coupons_basic free_courses basic_reports single_instructor"
         " email_notifications_basic page_builder_widgets_basic".split(),
 'starter': "content_drip course_reviews prerequisites instructor_dashboard drip_notifications calendar".split(),
 'pro': "email_advanced_customization community_discussions multi_instructor reports_advanced gradebook"
        " cohorts"
        " activity_log certificates_advanced subscriptions course_bundles coupons_advanced"
        " dynamic_checkout_fields assignments_advanced quiz_advanced live_classes social_login"
        " scorm_h5p_pro".split(),
 'scale': "marketplace_multivendor white_label webhooks zapier email_marketing public_api_keys"
          " multisite_scale enterprise_reports multilingual_enterprise groups_seats"
          " compliance_training".split(),
}
FEATURE_TIER = {f: t for t, fs in TIERS.items() for f in fs}

# spec file (relative to e2e/tests) -> feature ids it exercises. '_platform' = not a
# catalogue feature (auth, roles, admin shell, search, settings, smoke).
MAP = {
 'admin/addons-docs-url': ['_platform'],
 'admin/admin-enroll-bypass': ['manual_enrollment'],
 'admin/auditor-readonly-enforcement': ['_platform'],
 'admin/auditor-role': ['_platform'],
 'admin/auth-register-rate-limit': ['_platform'],
 'admin/auth-rest-validation': ['_platform'],
 'admin/block-editor-save': ['core_course_builder', 'page_builder_widgets_basic'],
 'admin/bulk-actions': ['_platform'],
 'admin/coupon-crud': ['coupons_basic'],
 'admin/coupon-update-delete': ['coupons_basic'],
 'admin/coupons-checkout-toggle': ['coupons_basic', 'checkout_native'],
 'admin/course-block-editor': ['core_course_builder', 'page_builder_widgets_basic'],
 'admin/course-builder-ui': ['core_course_builder'],
 'admin/course-categories-crud': ['core_course_builder'],
 'admin/course-create-via-rest': ['core_course_builder'],
 'admin/course-lesson-quiz-edit': ['core_course_builder'],
 'admin/course-sale-price': ['checkout_native'],
 'admin/courses-list': ['core_course_builder'],
 'admin/email-templates-list': ['email_notifications_basic'],
 'admin/global-search-palette': ['_platform'],
 'admin/global-search': ['_platform'],
 'admin/health-endpoint': ['_platform'],
 'admin/instructor-applications-flow': ['single_instructor'],
 'admin/lesson-create-via-rest': ['core_course_builder'],
 'admin/lesson-meta-roundtrip': ['core_course_builder', 'lesson_video_text'],
 'admin/lesson-attachments-editor': ['lesson_attachments'],
 'admin/lessons-list': ['core_course_builder'],
 'admin/orders-list-and-mark-paid': ['checkout_native'],
 'admin/quizzes-list': ['quiz_basic'],
 'admin/react-subroutes': ['_platform'],
 'admin/reports-snapshot': ['basic_reports'],
 'admin/rest-api': ['_platform'],
 'admin/screen-health-sweep': ['_platform'],
 'admin/settings-save': ['_platform'],
 'admin/setup-wizard-steps': ['_platform'],
 'admin/setup-wizard': ['_platform'],
 'admin/sikshya-menu': ['_platform'],
 'admin/student-create': ['student_dashboard'],
 'admin/tools-list': ['_platform'],
 'checkout/cart-add-paid': ['manual_enrollment', 'checkout_native'],
 'checkout/tax-applied-quote': ['checkout_native'],
 'checkout/cart-checkout-pages': ['checkout_native'],
 'checkout/coupon-applied-quote': ['coupons_basic', 'checkout_native'],
 'checkout/paid-purchase-fulfilment': ['checkout_native'],
 'learner/achievements': ['student_dashboard'],
 'learner/assignment-file-upload-ui': ['assignments_basic'],
 'learner/assignment-submit-ui': ['assignments_basic'],
 'learner/certificate-lifecycle': ['certificates_basic'],
 'learner/course-archive-instructor-filter': ['single_instructor'],
 'learner/course-archive': ['core_course_builder'],
 'learner/course-content-extras': ['course_preview_faq', 'announcements'],
 'learner/course-preview-lesson': ['course_preview_faq'],
 'learner/email-notifications': ['email_notifications_basic'],
 'learner/free-enroll-complete': ['free_courses'],
 'learner/free-enroll-ui': ['free_courses'],
 'learner/learn-shell-bundle-mode': ['student_dashboard'],
 'learner/learn-shell-course-mode': ['student_dashboard'],
 'learner/lesson-mark-complete-ui': ['student_dashboard'],
 'learner/lesson-attachments': ['lesson_attachments'],
 'learner/lesson-audio': ['lesson_video_text'],
 'learner/lesson-media-and-resources': ['lesson_video_text'],
 'learner/me-progress': ['student_dashboard'],
 'learner/my-account': ['student_dashboard'],
 'learner/quiz-attempt-ui': ['quiz_basic'],
 'learner/quiz-page-ui': ['quiz_basic'],
 'learner/quiz-question-types-ui': ['quiz_basic'],
 'learner/registration': ['_platform'],
 'learner/resume-card': ['student_dashboard'],
 'learner/shortcodes': ['page_builder_widgets_basic'],
 'learner/single-course-student-count': ['_platform'],
 'pro/addon-activity-log-recording': ['activity_log'],
 'pro/addon-activity-log-flow': ['activity_log'],
 'pro/addon-calendar-flow': ['calendar'],
 'pro/addon-certificates-advanced-public': ['certificates_advanced'],
 'pro/addon-certificates-advanced-flow': ['certificates_advanced'],
 'pro/addon-compliance-training': ['compliance_training'],
 'pro/addon-cohorts-drip-anchor': ['cohorts', 'content_drip'],
 'pro/addon-content-drip-enforcement': ['content_drip'],
 'pro/addon-content-drip-flow': ['content_drip'],
 'pro/addon-coupons-advanced-rules': ['coupons_advanced'],
 'pro/addon-coupons-advanced-flow': ['coupons_advanced'],
 'pro/addon-course-bundles-purchase': ['course_bundles'],
 'pro/addon-course-bundles-flow': ['course_bundles'],
 'pro/addon-course-reviews-publication': ['course_reviews'],
 'pro/addon-course-reviews-flow': ['course_reviews'],
 'pro/addon-email-marketing-pipeline': ['email_marketing'],
 'pro/addon-email-marketing-rule-flow': ['email_marketing'],
 'pro/addon-enterprise-reports-flow': ['enterprise_reports'],
 'pro/addon-groups-seats': ['groups_seats'],
 'pro/addon-gradebook-grading': ['gradebook'],
 'pro/addon-gradebook-grid-flow': ['gradebook'],
 'pro/addon-instructor-dashboard-flow': ['instructor_dashboard'],
 'pro/addon-live-classes-visibility': ['live_classes'],
 'pro/addon-live-classes-flow': ['live_classes'],
 'pro/addon-marketplace-vendor-scope': ['marketplace_multivendor'],
 'pro/addon-marketplace-multivendor-flow': ['marketplace_multivendor'],
 'pro/addon-multi-instructor-staff': ['multi_instructor'],
 'pro/addon-multi-instructor-flow': ['multi_instructor'],
 'pro/addon-multisite-scale-flow': ['multisite_scale'],
 'pro/addon-prerequisites-flow': ['prerequisites'],
 'pro/addon-public-api-keys-auth': ['public_api_keys'],
 'pro/addon-public-api-keys-flow': ['public_api_keys'],
 'pro/addon-quiz-advanced-pools': ['quiz_advanced'],
 'pro/addon-quiz-advanced-flow': ['quiz_advanced'],
 'pro/addon-reports-advanced-flow': ['reports_advanced'],
 'pro/addon-rest-smokes': ['_platform'],
 'pro/addon-social-login-flow': ['social_login'],
 'pro/addon-subscriptions-access': ['subscriptions'],
 'pro/addon-subscriptions-flow': ['subscriptions'],
 'pro/addon-enterprise-reports-run': ['enterprise_reports'],
 'pro/addon-reports-advanced-export': ['reports_advanced'],
 'pro/addon-tier-gating': ['_platform'],
 'pro/addon-webhooks-delivery': ['webhooks'],
 'pro/addon-webhooks-flow': ['webhooks'],
 'pro/addon-white-label-branding': ['white_label'],
 'pro/addon-white-label-flow': ['white_label'],
 'pro/pro-addon-settings': ['_platform'],
 'pro/pro-detection': ['_platform'],
 'pro/pro-react-subroutes': ['_platform'],
 'smoke/admin-dashboard': ['_platform'],
 'smoke/admin-login': ['_platform'],
 'smoke/frontend-home': ['_platform'],
}

# Features with no browser spec, and why. Keeps the log honest instead of silent.
NO_SPEC = {
 'drip_notifications': 'Sends email from the drip cron (`sikshya_pro_drip_cron`), not from any '
                       'browser action, so it is not reachable from a browser test. Covered by Pro PHPUnit.',
 'email_advanced_customization': 'No dedicated browser spec; covered by Pro PHPUnit.',
 'community_discussions': 'No dedicated browser spec; covered by Pro PHPUnit.',
 'dynamic_checkout_fields': 'No dedicated browser spec; covered by Pro PHPUnit.',
 'assignments_advanced': 'No dedicated browser spec; covered by Pro PHPUnit.',
 'scorm_h5p_pro': 'Browser spec exists only as a REST smoke; covered by Pro PHPUnit.',
 'zapier': 'No dedicated browser spec; covered by Pro PHPUnit (shared with webhooks).',
 'multilingual_enterprise': 'No dedicated browser spec; covered by Pro PHPUnit.',
}

def load(report_path):
    data = json.load(open(report_path, encoding='utf-8'))
    out = collections.defaultdict(list)   # spec key -> [(title, status)]
    def walk(suite, file_hint=None):
        f = suite.get('file') or file_hint
        for spec in suite.get('specs', []) or []:
            for t in spec.get('tests', []) or []:
                results = t.get('results') or []
                status = (results[-1].get('status') if results else None) or t.get('status') or 'unknown'
                if t.get('status') == 'skipped':
                    status = 'skipped'
                key = (spec.get('file') or f or '').replace('e2e/tests/', '').replace('.spec.ts', '')
                out[key].append((spec.get('title', '?'), status))
        for s in suite.get('suites', []) or []:
            walk(s, f)
    for s in data.get('suites', []) or []:
        walk(s)
    return out

def main(report_path, out_path, run_label):
    results = load(report_path)
    # feature -> list of (spec, title, status)
    feat = collections.defaultdict(list)
    platform = []
    unmapped = []
    for spec, tests in sorted(results.items()):
        ids = MAP.get(spec)
        if ids is None:
            unmapped.append(spec); continue
        for fid in ids:
            for title, status in tests:
                (platform if fid == '_platform' else feat[fid]).append((spec, title, status))

    def verdict(entries):
        st = {s for _, _, s in entries}
        if 'failed' in st or 'timedOut' in st: return 'FAILING'
        if 'passed' in st and 'skipped' in st: return 'partial (some tests need a licence)'
        if st == {'skipped'}: return 'blocked — needs a licence'
        if 'passed' in st: return 'covered'
        return 'unknown'

    L = []
    A = L.append
    A('# Browser test coverage\n')
    A('Generated from a real Playwright JSON report — not inferred. Regenerate after any')
    A('suite change so it never drifts:\n')
    A('```sh')
    A('npx playwright test --project=admin --project=public --reporter=json > /tmp/pw.json')
    A('python3 scripts/generate-coverage-log.py /tmp/pw.json docs/BROWSER-TEST-COVERAGE.md "<label>"')
    A('```\n')
    A(f'- Run: **{run_label}**')
    total = sum(len(v) for v in results.values())
    passed = sum(1 for v in results.values() for _, s in v if s == 'passed')
    skipped = sum(1 for v in results.values() for _, s in v if s == 'skipped')
    failed = total - passed - skipped
    A(f'- Tests: **{total}** — {passed} passed, {skipped} skipped, {failed} other')
    A(f'- Spec files: **{len(results)}**\n')
    A('`skipped` almost always means the licence tier does not include the feature, so the spec')
    A('guards itself rather than failing. Activate a Growth/Scale licence and those run.\n')
    A('## Why this file exists\n')
    A('So a later session can tell at a glance what is already proven in a browser and skip')
    A('re-testing it. If a feature is listed `covered`, the named specs assert it against a real')
    A('browser and a real server — re-verify only if you changed that area.\n')

    for tier in ('free', 'starter', 'pro', 'scale'):
        A(f'\n## {tier.capitalize()} tier\n')
        A('| Feature | Status | Tests | Specs |')
        A('|---|---|---|---|')
        for fid in TIERS[tier]:
            entries = feat.get(fid, [])
            if not entries:
                why = NO_SPEC.get(fid, 'No browser spec.')
                A(f'| `{fid}` | no browser spec | — | {why} |')
                continue
            specs = sorted({s for s, _, _ in entries})
            A(f'| `{fid}` | {verdict(entries)} | {len(entries)} | {", ".join(f"`{s}`" for s in specs)} |')

    A('\n## Platform coverage (not catalogue features)\n')
    A('Auth, roles and capabilities, the React admin shell and every one of its screens, global')
    A('search, settings, setup wizard, tools, REST surface, licence-tier gating, and smoke.\n')
    pspecs = sorted({s for s, _, _ in platform})
    A(f'- {len(platform)} tests across {len(pspecs)} specs:')
    for s in pspecs:
        sts = collections.Counter(st for sp, _, st in platform if sp == s)
        A(f'  - `{s}` — ' + ', '.join(f'{v} {k}' for k, v in sorted(sts.items())))

    if unmapped:
        A('\n## Unmapped specs\n')
        A('Present in the suite but not in the mapping table — add them to')
        A('`scripts/generate-coverage-log.py` so coverage stays honest.\n')
        for s in unmapped:
            A(f'- `{s}`')

    A('\n## Not covered by a browser test\n')
    for fid, why in sorted(NO_SPEC.items()):
        if not feat.get(fid):
            A(f'- **`{fid}`** ({FEATURE_TIER.get(fid,"?")}) — {why}')
    A('')
    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    open(out_path, 'w', encoding='utf-8').write('\n'.join(L))
    print(f'wrote {out_path}: {total} tests, {len(results)} specs, {len(unmapped)} unmapped')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], sys.argv[3] if len(sys.argv) > 3 else 'unlabelled')
