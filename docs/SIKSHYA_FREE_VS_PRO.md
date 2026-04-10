# Sikshya LMS — Free vs Pro (product source of truth)

This document defines what ships in **Sikshya** (WordPress.org / free) versus **Sikshya Pro** (commercial add-on). Implementation should gate Pro features via `Sikshya\Licensing\Pro` (see `src/Licensing/Pro.php`) and the companion plugin **Sikshya Pro**.

## Technical model

- **Sikshya (free):** main plugin; full free feature set; hooks and services always load.
- **Sikshya Pro:** separate plugin (`sikshya-pro`) defines `SIKSHYA_PRO_VERSION` and sets `sikshya_pro_is_active`. Free code uses `Sikshya\Licensing\Pro::isActive()` / `Pro::feature( string $slug )` and `Pro::getClientPayload()` for the React admin.

## Sikshya Free (target scope)

### Platform
- Core LMS activation, roles (student, instructor, admin caps), settings, i18n (`sikshya`).
- Repository-backed domain services for enrollments, progress, quizzes, certificates (basic), payments (manual/free path).

### Curriculum
- Unlimited courses, lessons, quizzes (CPT + meta).
- Admin course builder; public catalog and single course.
- Sequential lesson navigation; lesson completion and basic course progress.

### Quizzes (basic)
- Multiple choice, true/false, short text; passing score; attempts; basic review.

### Enrollment & access
- Manual enrollment; self-enroll on free courses; paid flow via **one** primary method (e.g. manual / “mark paid” or simple gateway stub) — expand in Pro.

### Learner UX
- Student dashboard, profile updates, basic certificates (default template).

### Communication (light)
- Course comments via WordPress comments API (discussion service).

### Admin
- Real list data (no placeholders); simple dashboard metrics.

### Developer
- Actions/filters for enroll, complete lesson, quiz pass; read-only or limited REST as documented.

## Sikshya Pro (add-on)

Everything in Free, plus (non-exhaustive; implement incrementally):

- **Automation:** content drip, prerequisites, learning paths, course clone, bundles.
- **Commerce:** subscriptions, coupons, multiple gateways, invoices, deeper Woo/EDD/PMPro integration.
- **Assessment:** assignments, question banks, timers, advanced question types, detailed attempt analytics.
- **Live:** Zoom / Meet / calendar integrations.
- **Multi-instructor / marketplace:** revenue share, payouts, vendor workflows.
- **Analytics:** gradebook, exports, activity log, scheduled reports.
- **Certificates:** builder, multiple templates, branding, verification.
- **Engagement:** announcements, automation emails, richer templates.
- **Integrations:** Elementor/Divi widgets, ESP/CRM, Zapier/Make via webhooks.
- **Security & ops:** 2FA (LMS flows), session tools, white label, full REST + API keys, webhooks.
- **Enterprise:** SCORM/xAPI (module), SSO (future phase).

## Implementation waves (engineering)

1. **Foundation:** repositories, service container, no fatals on frontend flows.
2. **Free loop:** catalog → enroll → lesson → quiz → progress → dashboard.
3. **Pro shell:** `sikshya-pro` plugin + `Pro::feature()` gates for first revenue features (drip, coupons, extra gateways).
4. **Pro depth:** assignments, reporting, integrations per roadmap.

## Competitor reference (why this split)

Aligned with common freemium LMS patterns (e.g. Masteriyo, Tutor LMS): strong free core; Pro sells automation, advanced commerce, analytics, and platform features. LearnDash/Lifter/Sensei Pro inform the depth of paid capabilities.

_Last updated: product + engineering alignment for Sikshya 1.x._
