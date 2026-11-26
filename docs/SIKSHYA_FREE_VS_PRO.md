# Sikshya LMS — Free vs Pro (product source of truth)

This document is the product-level narrative of what ships in **Sikshya** (WordPress.org / free) versus **Sikshya Pro** (commercial add-on). Implementation should gate Pro features via `Sikshya\Licensing\Pro` (see `src/Licensing/Pro.php`) and the companion plugin **Sikshya Pro**.

> **The machine-readable source of truth is `src/Licensing/FeatureRegistry.php`**, which carries the tier for every catalogued feature and is what the Addons screen renders. As of 1.0.21 that is 55 features: 17 free, 8 Starter, 18 Growth, 12 Scale. Where this document and the registry disagree, the registry is right — and this document is wrong and should be fixed. Several of the lines below were stale for exactly that reason until 1.0.21.

## Technical model

- **Sikshya (free):** main plugin; full free feature set; hooks and services always load.
- **Sikshya Pro:** separate plugin (`sikshya-pro`) defines `SIKSHYA_PRO_VERSION` and sets `sikshya_pro_is_active`. Free code uses `Sikshya\Licensing\Pro::isActive()` / `Pro::feature( string $slug )` and `Pro::getClientPayload()` for the React admin.

## Sikshya Free (target scope)

### Platform
- Core LMS activation, roles (student, instructor, admin caps), settings, i18n (`sikshya`).
- Repository-backed domain services for enrollments, progress, quizzes, certificates (basic), payments (manual/free path).

### Curriculum
- Unlimited courses, lessons, quizzes (CPT + meta).
- 8 question types, any of which can carry one image or audio clip on its stem — see
  [QUESTION-MEDIA.md](QUESTION-MEDIA.md).
- Admin course builder; public catalog and single course.
- Sequential lesson navigation; lesson completion and basic course progress.
- Lesson kinds: text, video, audio, **PDF document** (served through the signed, per-user,
  expiring attachment proxy — see [PDF-LESSONS.md](PDF-LESSONS.md)). Live classes, SCORM, H5P and
  cmi5 are Pro.

### Quizzes (basic)
- Multiple choice, true/false, short text; passing score; attempts; basic review.

### Enrollment & access
- Manual enrollment; self-enroll on free courses.
- **Selling is free.** Native checkout with **Stripe and PayPal** (`checkout_native`), basic **coupons** (`coupons_basic`), and orders/invoicing for those payments. This was once planned as a Pro feature and this document said so for several releases; it has shipped in free since 1.0.x. Pro adds subscriptions, membership levels, advanced coupon rules, bundles and dynamic checkout fields — not the ability to take money.
- **Selling through WooCommerce is also free** (see [WOOCOMMERCE.md](WOOCOMMERCE.md)).

### Assignments (basic)
- Create assignments, collect learner submissions, grade them by hand (`assignments_basic`). Pro adds advanced assignment workflows.

### Learner UX
- Student dashboard, profile updates, basic certificates (default template).

### Reporting (basic)
- On-screen enrolment, progress, completion and revenue figures (`basic_reports`). Pro adds exports, the gradebook, the activity log and scheduled reports.

### Announcements
- Course announcements (`announcements`) — **free**, despite having been listed under Pro in this document until 1.0.21.

### Blocks and theme integration
- Catalog blocks: **Course Grid**, **Course Categories** and **Course Search**, which take their colours, fonts and buttons from the active theme.
- Any Sikshya template or partial can be overridden from a `sikshya/` folder in the theme; the theme's content width is followed automatically. See [THEME-COMPATIBILITY.md](THEME-COMPATIBILITY.md).

### Migrating in
- Importing a course library from **Tutor LMS** or **LearnDash** is free (see [MIGRATING-TO-SIKSHYA.md](MIGRATING-TO-SIKSHYA.md)).

### Communication (light)
- Course comments via WordPress comments API (discussion service).

### Admin
- Real list data (no placeholders); simple dashboard metrics.

### Developer
- Actions/filters for enroll, complete lesson, quiz pass; read-only or limited REST as documented.

## Sikshya Pro (add-on)

Everything in Free, plus (non-exhaustive; implement incrementally):

- **Authoring:** AI drafting of outlines, lesson bodies and quiz questions (`ai_authoring`). The
  generation and the provider key live in Pro; the AI studio screen lives in Free, like every
  other add-on's admin UI.
- **Automation:** content drip, prerequisites, learning paths, course clone, bundles.
- **Authoring:** a **content bank** of reusable lessons, quizzes and assignments (`content_bank`, Starter — see [CONTENT-BANK.md](CONTENT-BANK.md)). It inserts copies rather than sharing one post, because progress and quiz attempts are keyed to a single lesson.
- **Commerce:** subscriptions and membership levels, **advanced** coupon rules (`coupons_advanced`), course bundles, dynamic checkout fields, deeper Woo/EDD/PMPro integration. Basic checkout and basic coupons are free — see above.
- **Assessment:** question banks, timers, advanced question types, detailed attempt analytics, **advanced** assignment workflows (`assignments_advanced`). Basic assignments are free.
- **Live:** Zoom / Meet / calendar integrations.
- **Multi-instructor / marketplace:** revenue share, payouts, vendor workflows.
- **Analytics:** gradebook, exports, activity log, scheduled reports (`reports_advanced`). The on-screen basic reports are free.
- **Certificates:** builder, multiple templates, branding, verification (`certificates_advanced`). A default-template certificate is free.
- **Engagement:** automation emails, richer templates, and **points with a leaderboard** (`gamification`, Growth — see [GAMIFICATION.md](GAMIFICATION.md)). Every award amount starts at zero and the board is staff-only until you widen it.
- **Integrations:** Elementor/Divi widgets, ESP/CRM, Zapier/Make via webhooks. **Selling through WooCommerce is free** (see [WOOCOMMERCE.md](WOOCOMMERCE.md)), as is importing a course library from Tutor LMS or LearnDash (see [MIGRATING-TO-SIKSHYA.md](MIGRATING-TO-SIKSHYA.md)).
- **Security & ops:** 2FA (LMS flows), session tools, white label, full REST + API keys, webhooks.
- **Enterprise:** SCORM and H5P content; **xAPI reporting to your own LRS** (`xapi_lrs`, Scale — see [XAPI.md](XAPI.md)); **cmi5** course import, launch and satisfaction reporting, shipped in 1.0.15 (this document previously said cmi5 was not included); identity lifecycle for SAML/SCIM deployments (see [ENTERPRISE-IDENTITY.md](ENTERPRISE-IDENTITY.md)).

## Implementation waves (engineering)

1. **Foundation:** repositories, service container, no fatals on frontend flows.
2. **Free loop:** catalog → enroll → lesson → quiz → progress → dashboard.
3. **Pro shell:** `sikshya-pro` plugin + `Pro::feature()` gates for first revenue features (drip, coupons, extra gateways).
4. **Pro depth:** assignments, reporting, integrations per roadmap.

## Competitor reference (why this split)

Aligned with common freemium LMS patterns (e.g. Masteriyo, Tutor LMS): strong free core; Pro sells automation, advanced commerce, analytics, and platform features. LearnDash/Lifter/Sensei Pro inform the depth of paid capabilities.

_Last updated: 2026-11-26, for Sikshya 1.0.21 — reconciled line by line against `src/Licensing/FeatureRegistry.php`._
