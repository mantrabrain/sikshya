# Sikshya Roadmap + Upgrade Funnel (Free → Pro → Elite)

This document turns the tier specs into a buildable roadmap and a conversion system.

---

## 1) Upgrade funnel (product-led growth)

### Free → Pro (high-intent prompts)
Trigger upgrade prompts only when users attempt to:
- **Improve learning outcomes**
  - enable advanced drip (date/x-days/cohort)
  - add prerequisites
  - use gradebook / advanced assignment workflows
- **Scale operations**
  - export reports/grades/certificates
  - view detailed analytics or student activity logs
- **Grow revenue**
  - enable subscriptions/memberships
  - sell bundles
  - use advanced coupons and upsells
- **Collaborate**
  - add a second instructor
  - configure revenue splits

Prompt design rules:
- show “what you get” in 1–2 bullets
- show “why now” (outcome), not feature name
- never block core flows in Free (publishing, checkout, enrollment)

### Pro → Elite (enterprise/agency prompts)
Trigger Elite prompts on:
- marketplace enablement (multi-vendor, payouts)
- white label actions (remove branding)
- webhooks/API access requests (external systems)
- multilingual/SCORM/H5P advanced requirements
- multi-site / agency license management needs

---

## 2) Phased roadmap (what to build first)

### Phase 1 — “Free Excellence” (Activation + First Revenue)
**Goal**: best time-to-first-sale and clean learner UX.
- Course builder UX (fast, guided, no clutter)
- Curriculum drag/drop + lesson preview
- Native checkout UX + Stripe/PayPal + Woo integration
- Student dashboard + progress tracking + resume learning
- Basic quizzes, basic certificates, basic assignments
- Clean empty states across public and admin pages

**Done when**:
- a creator can publish + sell + have a student complete with minimal setup

### Phase 2 — “Pro Growth” (Outcomes + ROI)
**Goal**: make Sikshya the best for academies/cohorts and paid growth.
- Advanced drip: date / x-days / cohort scheduling
- Prerequisites (lesson + course)
- Subscriptions/memberships + access expiry
- Bundles + order bumps + advanced coupons
- Gradebook + activity logs + exports
- Advanced certificates (builder + verification)
- Multi-instructor + revenue splitting
- Live learning integrations (Zoom/Meet) + attendance

**Done when**:
- teams can run cohorts and subscriptions with measurable outcomes

### Phase 3 — “Elite Scale” (Marketplace + Agency + Enterprise)
**Goal**: win deals that Tutor/Masteriyo target with their highest tiers.
- Marketplace mode + payouts + vendor analytics
- White label + agency handoff tooling
- Webhooks + public API + CRM/automation connectors
- Enterprise: multilingual + SCORM/H5P advanced + enterprise reporting
- Diagnostics/migrations as first-class “ops” product

**Done when**:
- agencies can deploy at scale and enterprises can integrate reliably

---

## 3) Moat checklist (how Sikshya stays ahead)

### Moat A: UX consistency
- one design system for all public pages
- one design system for admin React shell
- consistent tables, filters, empty states, buttons

### Moat B: Performance
- minimize queries for listings
- avoid postmeta-heavy analytics
- background jobs for exports and heavy reports

### Moat C: Business outcomes baked in
- drop-off detection (lesson where students quit)
- completion blockers surfaced (missing prereq, failing quiz)
- revenue insights (best coupon, best bundle, churn risk)

