# Competitive Analysis (2026) — Sikshya vs Tutor LMS vs Masteriyo

This document maps competitor feature splits and defines how Sikshya should win.

## Sources used (official pages)
- **Tutor LMS Free vs Pro**: `https://tutorlms.com/free-vs-pro/`
- **Tutor LMS pricing/features**: `https://tutorlms.com/pricing/`
- **Masteriyo Free vs Pro**: `https://masteriyo.com/free-vs-pro/`
- **Masteriyo pricing/features**: `https://masteriyo.com/pricing`

---

## 1) Competitor tier patterns (what they paywall)

### Tutor LMS (pattern)
Tutor’s public materials emphasize that **Pro unlocks** (high-level):
- Content drip, prerequisites, bundles
- Live classes (Zoom/Google Meet)
- Certificate builder (drag & drop)
- Reporting/analytics
- Membership/subscriptions (native eCommerce + optimized checkout)
- Many gateways and integrations
- Marketplace (multi-instructor ecosystem)

**Notable competitive strength**: breadth of integrations + “all add-ons included” packaging.

### Masteriyo (pattern)
Masteriyo’s Free vs Premium pages show **Premium unlocks** (high-level):
- Advanced drip + prerequisites
- Assignments + advanced quiz types
- Gradebook + advanced analytics + activity logs
- Multiple instructors
- Advanced certificates/templates
- Smart coupons + more gateways
- Marketing tools + webhooks/API access
- White label (Elite)

**Notable competitive strength**: clear add-on feature table, strong “easy UI” positioning, and modern checkout messaging.

---

## 2) Capability map: Parity / Better / Unique (targets for Sikshya)

### A) Build (course creation)
- **Parity**: drag-drop curriculum, lessons, attachments, FAQs, preview.
- **Better** (Sikshya target):
  - faster builder (React shell SPA) + less WordPress list-table friction
  - stronger “activation rails” (guided steps + completion indicators)
- **Unique** (Sikshya moat):
  - course quality assistant (non-AI baseline): “missing pieces” checks before publish

### B) Teach (learning, assessment, outcomes)
- **Parity**: quizzes, certificates, assignments (Pro), gradebook (Pro), drip (Pro).
- **Better**:
  - student UX polish: resume, progress clarity, fewer clicks, better mobile
  - instructor workflow: grading queue, bulk actions, consistent empty states
- **Unique**:
  - outcome-oriented reporting: “drop-off lessons” + “completion blockers” surfaced automatically

### C) Sell (monetization)
- **Tutor strength**: native eCommerce + many gateways, subscriptions/memberships.
- **Masteriyo strength**: gateway breadth + coupons + integrations.
- **Sikshya must win**:
  - best checkout UX in WP LMS (fast, clean, high conversion)
  - best pricing display + bundles + upsell system (Pro)
  - “first sale” guidance in Free

### D) Scale (teams, marketplace, enterprise)
- **Parity**: multi-instructor (Pro), marketplace (Elite), white label (Elite), API/webhooks (Elite).
- **Better**:
  - roles/capabilities that feel like a product, not a WordPress permission puzzle
  - multi-site and agency tooling that is simple and reliable
- **Unique**:
  - “operational excellence”: migration tools + diagnostics + guided fixes

---

## 3) What Sikshya should deliberately NOT copy
- **Do not** ship dozens of loosely connected add-ons with inconsistent UX.
- **Do not** overload Free with advanced settings that reduce activation.
- **Do not** rely on WooCommerce for everything: keep Sikshya checkout first-class.

---

## 4) Must-win differentiators (competition-proof)

### Differentiator 1: Time-to-first-sale
Sikshya Free should enable a creator to:
Create course → set price → connect payment → publish → student checkout → enrollment → start lesson
with minimal configuration.

### Differentiator 2: Clarity and guided operations
Every major admin screen should have:
- clear empty states
- next best actions
- bulk operations
- consistent typography, spacing, and button language

### Differentiator 3: Performance and reliability
- fast admin SPA navigation
- minimal render-blocking assets
- scalable reporting model (avoid “slow WP_Query for analytics” traps)

### Commerce stance
- Sikshya prioritizes a **first-class native checkout** experience and **does not require WooCommerce** to sell courses.

