# Browser test coverage

Generated from a real Playwright JSON report — not inferred. Regenerate after any
suite change so it never drifts:

```sh
npx playwright test --project=admin --project=public --reporter=json > /tmp/pw.json
python3 scripts/generate-coverage-log.py /tmp/pw.json docs/BROWSER-TEST-COVERAGE.md "<label>"
```

- Run: **2026-10-15, Scale licence active**
- Engines: Chromium **241 passed / 3 skipped / 0 flaky**, WebKit **239 / 3 / 2 flaky**,
  Firefox **240 / 3 / 1 flaky**. No hard failures on any engine. The four flaky
  results all passed on retry and were timeouts, not assertion failures — this dev
  server runs two PHP workers, so a spec that signs several users in can exceed a
  two-minute budget. Flaky is reported separately from passed on purpose; a test
  that needed a retry has not demonstrated the same thing as one that did not.
- Tests: **244** — 241 passed, 3 skipped, 0 other
- Spec files: **117**

`skipped` almost always means the licence tier does not include the feature, so the spec
guards itself rather than failing. Activate a Growth/Scale licence and those run.

## Why this file exists

So a later session can tell at a glance what is already proven in a browser and skip
re-testing it. If a feature is listed `covered`, the named specs assert it against a real
browser and a real server — re-verify only if you changed that area.


## Free tier

| Feature | Status | Tests | Specs |
|---|---|---|---|
| `core_course_builder` | covered | 18 | `admin/block-editor-save`, `admin/course-block-editor`, `admin/course-builder-ui`, `admin/course-categories-crud`, `admin/course-create-via-rest`, `admin/course-lesson-quiz-edit`, `admin/courses-list`, `admin/lesson-create-via-rest`, `admin/lesson-meta-roundtrip`, `admin/lessons-list`, `learner/course-archive` |
| `lesson_video_text` | covered | 2 | `admin/lesson-meta-roundtrip`, `learner/lesson-media-and-resources` |
| `lesson_attachments` | covered | 2 | `admin/lesson-attachments-editor`, `learner/lesson-attachments` |
| `course_preview_faq` | covered | 2 | `learner/course-content-extras`, `learner/course-preview-lesson` |
| `announcements` | covered | 1 | `learner/course-content-extras` |
| `student_dashboard` | covered | 8 | `admin/student-create`, `learner/achievements`, `learner/learn-shell-bundle-mode`, `learner/learn-shell-course-mode`, `learner/lesson-mark-complete-ui`, `learner/me-progress`, `learner/my-account`, `learner/resume-card` |
| `quiz_basic` | covered | 10 | `admin/quizzes-list`, `learner/quiz-attempt-ui`, `learner/quiz-page-ui`, `learner/quiz-question-types-ui` |
| `certificates_basic` | covered | 1 | `learner/certificate-lifecycle` |
| `assignments_basic` | covered | 2 | `learner/assignment-file-upload-ui`, `learner/assignment-submit-ui` |
| `checkout_native` | covered | 12 | `admin/coupons-checkout-toggle`, `admin/course-sale-price`, `admin/orders-list-and-mark-paid`, `checkout/cart-add-paid`, `checkout/cart-checkout-pages`, `checkout/coupon-applied-quote`, `checkout/paid-purchase-fulfilment` |
| `manual_enrollment` | covered | 3 | `admin/admin-enroll-bypass`, `checkout/cart-add-paid` |
| `coupons_basic` | covered | 4 | `admin/coupon-crud`, `admin/coupon-update-delete`, `admin/coupons-checkout-toggle`, `checkout/coupon-applied-quote` |
| `free_courses` | covered | 2 | `learner/free-enroll-complete`, `learner/free-enroll-ui` |
| `basic_reports` | covered | 1 | `admin/reports-snapshot` |
| `single_instructor` | covered | 3 | `admin/instructor-applications-flow`, `learner/course-archive-instructor-filter` |
| `email_notifications_basic` | covered | 2 | `admin/email-templates-list`, `learner/email-notifications` |
| `page_builder_widgets_basic` | covered | 7 | `admin/block-editor-save`, `admin/course-block-editor`, `learner/shortcodes` |

## Starter tier

| Feature | Status | Tests | Specs |
|---|---|---|---|
| `content_drip` | covered | 2 | `pro/addon-content-drip-enforcement`, `pro/addon-content-drip-flow` |
| `course_reviews` | covered | 2 | `pro/addon-course-reviews-flow`, `pro/addon-course-reviews-publication` |
| `prerequisites` | covered | 1 | `pro/addon-prerequisites-flow` |
| `instructor_dashboard` | covered | 1 | `pro/addon-instructor-dashboard-flow` |
| `drip_notifications` | no browser spec | — | Sends email from the drip cron (`sikshya_pro_drip_cron`), not from any browser action, so it is not reachable from a browser test. Covered by Pro PHPUnit. |
| `calendar` | covered | 1 | `pro/addon-calendar-flow` |

## Pro tier

| Feature | Status | Tests | Specs |
|---|---|---|---|
| `email_advanced_customization` | no browser spec | — | No dedicated browser spec; covered by Pro PHPUnit. |
| `community_discussions` | no browser spec | — | No dedicated browser spec; covered by Pro PHPUnit. |
| `multi_instructor` | covered | 2 | `pro/addon-multi-instructor-flow`, `pro/addon-multi-instructor-staff` |
| `reports_advanced` | covered | 2 | `pro/addon-reports-advanced-export`, `pro/addon-reports-advanced-flow` |
| `gradebook` | covered | 2 | `pro/addon-gradebook-grading`, `pro/addon-gradebook-grid-flow` |
| `activity_log` | covered | 2 | `pro/addon-activity-log-flow`, `pro/addon-activity-log-recording` |
| `certificates_advanced` | covered | 2 | `pro/addon-certificates-advanced-flow`, `pro/addon-certificates-advanced-public` |
| `subscriptions` | covered | 2 | `pro/addon-subscriptions-access`, `pro/addon-subscriptions-flow` |
| `course_bundles` | covered | 2 | `pro/addon-course-bundles-flow`, `pro/addon-course-bundles-purchase` |
| `coupons_advanced` | covered | 2 | `pro/addon-coupons-advanced-flow`, `pro/addon-coupons-advanced-rules` |
| `dynamic_checkout_fields` | no browser spec | — | No dedicated browser spec; covered by Pro PHPUnit. |
| `assignments_advanced` | no browser spec | — | No dedicated browser spec; covered by Pro PHPUnit. |
| `quiz_advanced` | covered | 2 | `pro/addon-quiz-advanced-flow`, `pro/addon-quiz-advanced-pools` |
| `live_classes` | covered | 2 | `pro/addon-live-classes-flow`, `pro/addon-live-classes-visibility` |
| `social_login` | covered | 1 | `pro/addon-social-login-flow` |
| `scorm_h5p_pro` | no browser spec | — | Browser spec exists only as a REST smoke; covered by Pro PHPUnit. |

## Scale tier

| Feature | Status | Tests | Specs |
|---|---|---|---|
| `marketplace_multivendor` | covered | 2 | `pro/addon-marketplace-multivendor-flow`, `pro/addon-marketplace-vendor-scope` |
| `white_label` | covered | 3 | `pro/addon-white-label-branding`, `pro/addon-white-label-flow` |
| `webhooks` | covered | 2 | `pro/addon-webhooks-delivery`, `pro/addon-webhooks-flow` |
| `zapier` | no browser spec | — | No dedicated browser spec; covered by Pro PHPUnit (shared with webhooks). |
| `email_marketing` | covered | 2 | `pro/addon-email-marketing-pipeline`, `pro/addon-email-marketing-rule-flow` |
| `public_api_keys` | covered | 2 | `pro/addon-public-api-keys-auth`, `pro/addon-public-api-keys-flow` |
| `multisite_scale` | blocked — needs a licence | 1 | `pro/addon-multisite-scale-flow` |
| `enterprise_reports` | covered | 2 | `pro/addon-enterprise-reports-flow`, `pro/addon-enterprise-reports-run` |
| `multilingual_enterprise` | no browser spec | — | No dedicated browser spec; covered by Pro PHPUnit. |
| `groups_seats` | covered | 1 | `pro/addon-groups-seats` |

## Platform coverage (not catalogue features)

Auth, roles and capabilities, the React admin shell and every one of its screens, global
search, settings, setup wizard, tools, REST surface, licence-tier gating, and smoke.

- 131 tests across 26 specs:
  - `admin/addons-docs-url` — 1 passed
  - `admin/auditor-readonly-enforcement` — 1 passed
  - `admin/auditor-role` — 1 passed
  - `admin/auth-register-rate-limit` — 1 passed
  - `admin/auth-rest-validation` — 3 passed
  - `admin/bulk-actions` — 3 passed
  - `admin/global-search` — 2 passed
  - `admin/global-search-palette` — 1 passed
  - `admin/health-endpoint` — 1 passed
  - `admin/react-subroutes` — 39 passed
  - `admin/rest-api` — 2 passed
  - `admin/settings-save` — 1 passed
  - `admin/setup-wizard` — 1 passed
  - `admin/setup-wizard-steps` — 2 passed
  - `admin/sikshya-menu` — 2 passed
  - `admin/tools-list` — 1 passed
  - `learner/registration` — 1 passed
  - `learner/single-course-student-count` — 1 passed
  - `pro/addon-rest-smokes` — 11 passed
  - `pro/addon-tier-gating` — 2 skipped
  - `pro/pro-addon-settings` — 31 passed
  - `pro/pro-detection` — 2 passed
  - `pro/pro-react-subroutes` — 18 passed
  - `smoke/admin-dashboard` — 1 passed
  - `smoke/admin-login` — 1 passed
  - `smoke/frontend-home` — 1 passed

## Not covered by a browser test

- **`assignments_advanced`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`community_discussions`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`drip_notifications`** (starter) — Sends email from the drip cron (`sikshya_pro_drip_cron`), not from any browser action, so it is not reachable from a browser test. Covered by Pro PHPUnit.
- **`dynamic_checkout_fields`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`email_advanced_customization`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`multilingual_enterprise`** (scale) — No dedicated browser spec; covered by Pro PHPUnit.
- **`scorm_h5p_pro`** (pro) — Browser spec exists only as a REST smoke; covered by Pro PHPUnit.
- **`zapier`** (scale) — No dedicated browser spec; covered by Pro PHPUnit (shared with webhooks).
