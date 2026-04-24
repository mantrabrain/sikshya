# Sikshya LMS — Free Plan Spec (Creator Starter)

This document defines the **Free** tier scope for Sikshya LMS (WordPress). It is designed to **beat competitor free plans** on activation (“publish → sell → enroll”) while keeping clear upgrade moments for Pro/Elite.

- **Primary goal**: ship a real course business in Free (not a demo).
- **North star**: first course published + first sale + first completion in < 60 minutes.
- **Target users**: solo creators, coaches, small academies; also a credible on-ramp for corporate training and marketplaces.

## Product principles (Free)
- **Opinionated defaults**: users shouldn’t need to understand LMS theory to launch.
- **Progressive disclosure**: only show advanced controls when needed.
- **No-addon maze**: Free should feel complete; Pro unlocks scaling.
- **Performance-first**: pages and admin screens remain fast on shared hosting.
- **Trust by default**: secure checkout, reliable enrollment, resilient email delivery.

---

## 1) Build (course creation)

### Included (Free)
- **Unlimited** courses, lessons, quizzes, and students (no artificial caps).
- **Drag & drop curriculum** with chapters/sections.
- Lesson types:
  - Text lesson
  - Video lesson (URL-based)
  - Downloadable materials / attachments
- Course landing content:
  - Description, outcomes, FAQs, announcements
  - Course preview (selected lessons)
- Course discovery:
  - Course archive with search and filters (baseline)
  - Categories/tags taxonomy pages aligned to main archive layout

### Limits (Free)
- **Single instructor per course** (upgrade moment: multi-instructor collaboration + revenue split).
- **Basic curriculum drip** (see “Teach → Drip”): sequential-only.

### UX flows (Free)
- **Create & publish**
  - Dashboard → Create Course → Build Curriculum → Pricing → Publish → View course page
- **Preview**
  - Course editor → mark preview lessons → confirm “Preview as visitor” CTA

---

## 2) Teach (learning, assessment, engagement)

### Student experience (Free)
- Student dashboard:
  - My courses
  - Progress overview per course
  - Resume learning
- Learning experience:
  - Lesson navigation (next/prev, curriculum outline)
  - Course completion state
- Wishlist (saved courses)

### Quizzes (Free)
- Quiz builder (basic):
  - Multiple choice
  - True/false
  - Short answer
- Passing marks
- Basic timer (single time limit per quiz)
- Basic review experience (attempt summary)

### Assignments (Free)
- “Basic assignments” scope (Free):
  - Assignment creation & submission
  - File upload submission (bounded by WP limits)
  - Manual grading (pass/fail + note)

### Certificates (Free)
- Certificates “basic”:
  - 1–2 starter templates (theme-safe)
  - Award on course completion
  - Minimal variables (student name, course name, completion date)

### Drip / prerequisites (Free)
- **Sequential progression** (minimum viable drip):
  - Require lesson completion before next unlock
  - Optional “complete chapter to unlock next chapter”

---

## 3) Sell (monetization)

Free is intentionally strong here to outperform typical WordPress LMS free versions and to align with your requirement doc.

### Checkout & payments (Free)
- Native checkout flow:
  - One-time purchase per course
  - Free courses enrollment
  - Manual enrollment (admin)
- Gateways (Free baseline):
  - **Stripe**
  - **PayPal**

### Coupons (Free)
- “Basic coupons”:
  - Percentage OR fixed discount
  - Single-use or limited redemptions
  - Optional date range
- No advanced rules (upgrade moment)

### Order management (Free)
- Order list + order detail
- Refund marking (manual) and notes (no advanced flows)

---

## 4) Manage (admin operations)

### Included (Free)
- Admin React shell (fast navigation, no full reload)
- Basic reports:
  - Enrollment counts per course
  - Completion % per course
  - Revenue totals (high-level)
- Email notifications (basic):
  - Enrollment confirmation
  - Course completion
  - Purchase receipt
- Basic roles/capabilities:
  - Admin manages everything
  - Instructor role (single-instructor model)

### Security (Free)
- Nonce validation and capability checks everywhere
- Safe REST patterns and consistent error responses
- Session/auth UX must not flash “Session expired” on load

---

## 5) Integrate (ecosystem)

### Included (Free)
- Page builder compatibility baseline (Elementor widgets / blocks) for course grids and key CTAs
- Basic SEO compatibility (title/description, schema-friendly markup)

---

## Upgrade triggers (Free → Pro) — “paywall moments”

Show upgrade prompts only at intent-rich moments:
- When enabling **advanced drip** modes (date-based / cohort / x-days)
- When trying to add a **second instructor**
- When enabling **subscriptions / recurring** payments
- When needing **exports**, **gradebook**, or **activity logs**
- When creating **advanced certificates** (builder + verification)
- When creating **bundles** or **advanced coupon rules**
- When enabling **live classes** integrations beyond the Free baseline

---

## Competitive intent (why this Free wins)

Compared to Tutor LMS / Masteriyo Free (2026), Sikshya Free should feel superior by:
- **Better monetization out of the box** (native checkout + gateways that usually require paid tiers elsewhere).
- **Cleaner UX** (fewer knobs, better defaults, faster admin navigation).
- **Modern empty states + guided activation** (first course + first sale path).

