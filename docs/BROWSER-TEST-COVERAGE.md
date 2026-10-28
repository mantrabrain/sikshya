# Browser test coverage

Generated from a real Playwright JSON report — not inferred. Regenerate after any
suite change so it never drifts:

```sh
npx playwright test --project=admin --project=public --reporter=json > /tmp/pw.json
python3 scripts/generate-coverage-log.py /tmp/pw.json docs/BROWSER-TEST-COVERAGE.md "<label>"
```

- Run: **segmented: chromium + webkit + firefox, 2026-10-16**
- Tests: **295** — 292 passed, 3 skipped, 0 other
- Spec files: **125**

`skipped` almost always means the licence tier does not include the feature, so the spec
guards itself rather than failing. Activate a Growth/Scale licence and those run.

## How the last run was executed

Six project runs, one process each — `admin`, `public`, `admin-webkit`,
`public-webkit`, `admin-firefox`, `public-firefox` — rather than all six in a
single invocation. On this development machine a single two-hour run saturates
the site (PHP-FPM is configured with two workers) and Local's services have
stopped serving part-way through more than once, which piles timeouts into
whichever project happens to run last and reads as a wall of product failures.
Separate processes keep each segment short and let the machine recover between
them; the driver also refuses to start a segment against a site that is not
answering, so a dead site shows up as a skipped segment rather than invented
failures.

Every segment finished green: 251 + 41 per browser, 876 passed, 0 failed, 0
flaky, 9 skipped (three licence-gated specs across three browsers).
`admin-firefox` took three attempts to get there, and both earlier failures
were diagnosed rather than retried away — one spec signs three learners in
through the real login form and needed `test.slow()`, and one failed on
`NS_ERROR_CONNECTION_REFUSED` when the site went down mid-test, passing 3/3 on
its own afterwards.

The counts above come from one browser's `admin` + `public` pair, which covers
every spec in the suite exactly once.

## Why this file exists

So a later session can tell at a glance what is already proven in a browser and skip
re-testing it. If a feature is listed `covered`, the named specs assert it against a real
browser and a real server — re-verify only if you changed that area.


## Free tier

| Feature | Status | Tests | Specs |
|---|---|---|---|
| `core_course_builder` | covered | 18 | `admin/block-editor-save`, `admin/course-block-editor`, `admin/course-builder-ui`, `admin/course-categories-crud`, `admin/course-create-via-rest`, `admin/course-lesson-quiz-edit`, `admin/courses-list`, `admin/lesson-create-via-rest`, `admin/lesson-meta-roundtrip`, `admin/lessons-list`, `learner/course-archive` |
| `lesson_video_text` | covered | 3 | `admin/lesson-meta-roundtrip`, `learner/lesson-audio`, `learner/lesson-media-and-resources` |
| `lesson_attachments` | covered | 2 | `admin/lesson-attachments-editor`, `learner/lesson-attachments` |
| `course_preview_faq` | covered | 2 | `learner/course-content-extras`, `learner/course-preview-lesson` |
| `announcements` | covered | 1 | `learner/course-content-extras` |
| `student_dashboard` | covered | 8 | `admin/student-create`, `learner/achievements`, `learner/learn-shell-bundle-mode`, `learner/learn-shell-course-mode`, `learner/lesson-mark-complete-ui`, `learner/me-progress`, `learner/my-account`, `learner/resume-card` |
| `quiz_basic` | covered | 10 | `admin/quizzes-list`, `learner/quiz-attempt-ui`, `learner/quiz-page-ui`, `learner/quiz-question-types-ui` |
| `certificates_basic` | covered | 1 | `learner/certificate-lifecycle` |
| `assignments_basic` | covered | 2 | `learner/assignment-file-upload-ui`, `learner/assignment-submit-ui` |
| `checkout_native` | covered | 13 | `admin/coupons-checkout-toggle`, `admin/course-sale-price`, `admin/orders-list-and-mark-paid`, `checkout/cart-add-paid`, `checkout/cart-checkout-pages`, `checkout/coupon-applied-quote`, `checkout/paid-purchase-fulfilment`, `checkout/tax-applied-quote` |
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
| `content_drip` | covered | 3 | `pro/addon-cohorts-drip-anchor`, `pro/addon-content-drip-enforcement`, `pro/addon-content-drip-flow` |
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
| `ai_authoring` | covered | 3 | `admin/ai-studio-admin-ui` — the provider call is stubbed at the browser boundary (a real generation costs money and returns different words each run; the provider contract is pinned by `AiAuthoringGenerationTest`), but the apply calls are real and the result is read back from the curriculum API |
| `cohorts` | covered | 3 | `admin/cohorts-admin-ui`, `pro/addon-cohorts-drip-anchor` |
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
| `groups_seats` | covered | 2 | `admin/groups-admin-ui`, `pro/addon-groups-seats` |
| `compliance_training` | covered | 3 | `admin/compliance-admin-ui`, `pro/addon-compliance-training` |

## Platform coverage (not catalogue features)

Auth, roles and capabilities, the React admin shell and every one of its screens, global
search, settings, setup wizard, tools, REST surface, licence-tier gating, and smoke.

- 237 tests across 32 specs:
  - `admin/addons-docs-url` — 1 passed
  - `admin/auditor-readonly-enforcement` — 1 passed
  - `admin/auditor-role` — 1 passed
  - `admin/auth-register-rate-limit` — 1 passed
  - `admin/auth-rest-validation` — 3 passed
  - `admin/bulk-actions` — 3 passed
  - `admin/global-search` — 2 passed
  - `admin/import-tools-panel` — 1 passed (the migration panel: its two promises on screen, and a preview that creates nothing)
  - `admin/global-search-palette` — 1 passed
  - `admin/health-endpoint` — 1 passed
  - `admin/react-subroutes` — 39 passed
  - `admin/rest-api` — 2 passed
  - `admin/ai-studio-admin-ui` — 3 passed
  - `admin/screen-health-sweep` — 43 passed
  - `a11y/admin/admin-screens-a11y` — 43 passed (WCAG 2.1 AA, every admin screen)
  - `a11y/admin/keyboard-and-focus` — 4 passed (dialog focus trap + focus return)
  - `a11y/public/public-pages-a11y` — 13 passed (WCAG 2.1 AA, learner-facing pages)
  - `admin/settings-save` — 1 passed
  - `admin/setup-wizard` — 1 passed
  - `admin/setup-wizard-steps` — 2 passed
  - `admin/sikshya-menu` — 2 passed
  - `admin/tools-list` — 1 passed
  - `learner/registration` — 1 passed
  - `learner/single-course-student-count` — 1 passed
  - `pro/addon-rest-smokes` — 11 passed
  - `pro/addon-xapi-flow` — 3 passed (status never leaks credentials; queue rows carry their own statement id; the Integrations panel renders)
  - `pro/addon-tier-gating` — 2 skipped
  - `pro/pro-addon-settings` — 31 passed
  - `pro/pro-detection` — 2 passed
  - `pro/pro-react-subroutes` — 18 passed
  - `smoke/admin-dashboard` — 1 passed
  - `smoke/admin-login` — 1 passed
  - `smoke/frontend-home` — 1 passed

## Accessibility

Three gates, described in full in [ACCESSIBILITY.md](ACCESSIBILITY.md):

- the two axe-core specs above, scanning **only** Sikshya's own markup over 43
  admin screens and 13 learner pages, at WCAG 2.1 AA;
- `npm run a11y:labels`, a static check for `<label>`s that name no control —
  it found 36 where the browser gate saw 2, because a scan only ever sees a
  screen's landing state;
- the keyboard spec, which covers what no scan can: that Tab stays inside an
  open dialog and that closing one returns focus to the control that opened it.

Both axe specs were mutation-tested in this repo: reverting one colour fix and
removing one `aria-label` each turned the relevant test red, and removing the
focus trap turned the keyboard spec red.

What is **not** covered is listed under *Not yet evaluated* in
[ACCESSIBILITY.md](ACCESSIBILITY.md) — most importantly, no screen reader has
been used, and the course builder's drag-and-drop curriculum has no keyboard
test.

## Importing from another LMS

The browser spec covers the panel and a preview. Everything that decides
whether a migration is *correct* — the answer-key encoding per question type,
Tutor's enrolment status meaning "the enrolment went through" rather than "the
learner finished", LearnDash's three levels folding into two, reading ProQuiz's
serialised answer objects without instantiating them, re-running without
duplicating, reading terms while the old plugin is deactivated, the batch
cursor — is **34 PHPUnit tests** across `TutorImportIntegrationTest` and
`LearnDashImportIntegrationTest`, because they need the other plugin's own
custom tables, which a browser test cannot seed.

## Selling through WooCommerce

There is no browser spec, and that is a gap worth naming: WooCommerce is not
installed in this environment, so a browser test could only exercise the parts
that do nothing without it. The rules are **23 PHPUnit tests** in
`WooCommerceBridgeIntegrationTest`, written against an `OrderSnapshot` rather
than a `WC_Order` precisely so they can run here — idempotent granting, a
refund that closes only what that order opened, partial refunds, guest account
creation, re-purchase after a refund, and the add-to-cart block. The one class
that touches WooCommerce's API, `WooOrderReader`, is untested and deliberately
dull: it reads an order and builds a snapshot.

## The content bank

No browser spec either, and for a weaker reason than WooCommerce's: the
workspace is reachable here, it just has not been driven from a browser. The
rules are **33 PHPUnit tests** in `ContentBankIntegrationTest`
(`--group content_bank`) covering what a snapshot carries and refuses, what an
insert writes and where, and the three separate permission questions — use the
bank, read an item, write into a course. Mutation run against them: **23 of 24
seeded defects caught**, the survivor behaviour-equivalent (a forbidden meta
key added to the base allowlist is still stripped by the same guard, and
removing both halves fails two tests).

What a browser spec would add that these do not: that the insert dialog's
course and section pickers really narrow to what the signed-in author may edit,
and that the capture list matches the course outline on screen.

## Membership levels

No browser spec. The rules are **52 PHPUnit tests** in
`MembershipLevelsIntegrationTest` (`--group memberships`), covering the
catalogue (all / courses / categories, unioned not intersected, unpublished
excluded), the additive-only access rule at all three gates, the single
refusal the enrolment gate may overturn, every fault the editor reports, and
the rendered public HTML — the pricing table, the "included with membership"
panel and the account line. Mutation run against them: **29 of 30 seeded
defects caught**; the survivor is behaviour-equivalent (removing the
`$userId <= 0` guard changes nothing while `hasActiveSubscription()` keeps its
own, and removing both still fails nothing because no subscription row belongs
to user 0).

What a browser spec would add: that the level editor's course picker and the
tab's problem list behave on screen, and that the pricing table lays out at
phone width.

## Not covered by a browser test

- **`assignments_advanced`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`community_discussions`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`drip_notifications`** (starter) — Sends email from the drip cron (`sikshya_pro_drip_cron`), not from any browser action, so it is not reachable from a browser test. Covered by Pro PHPUnit.
- **`content_bank`** (starter) — No dedicated browser spec; covered by Pro PHPUnit (`--group content_bank`). See above.
- **`subscriptions` → membership levels** (pro) — No dedicated browser spec; covered by Pro PHPUnit (`--group memberships`). See above.
- **`dynamic_checkout_fields`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`email_advanced_customization`** (pro) — No dedicated browser spec; covered by Pro PHPUnit.
- **`multilingual_enterprise`** (scale) — No dedicated browser spec; covered by Pro PHPUnit.
- **`scorm_h5p_pro`** (pro) — Browser spec exists only as a REST smoke; covered by Pro PHPUnit.
- **`zapier`** (scale) — No dedicated browser spec; covered by Pro PHPUnit (shared with webhooks).
- **`xapi_lrs`** (scale) — The browser spec covers the admin surface only. Statement *shape* and delivery semantics — the 409-counts-as-sent rule, the retry taxonomy, actor modes, the drain budget, retention — are 42 Pro PHPUnit tests, because they need a fake LRS answering with specific status codes, which a browser test cannot arrange.
