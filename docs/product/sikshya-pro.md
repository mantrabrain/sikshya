# Sikshya LMS — Pro Plan Spec (Business / Growth)

This document defines the **Pro** tier: features that improve outcomes, increase revenue, and unlock team workflows, while keeping Elite for marketplace/agency scale.

## Pro goals
- Increase revenue per visitor (bundles, subscriptions, smarter discounts).
- Improve learner completion (advanced drip, prerequisites, reminders).
- Reduce instructor/admin effort (gradebook, bulk operations, exports).
- Make Sikshya viable for academies and corporate training without bloat.

---

## 1) Build (advanced course ops)

### Included (Pro)
- Course cloning / duplication
- Course bundles (sell multiple courses together)
- Advanced curriculum operations:
  - bulk move content between chapters
  - draft/publish workflow improvements

---

## 2) Teach (advanced learning & assessment)

### Content drip (Pro)
Unlock scheduling modes beyond sequential:
- **Date-based drip** (unlock on specific date/time)
- **X-days from enrollment** (relative scheduling)
- **Cohort release** (start date per cohort; optional enrollment window)
- Drip notifications (email + in-app)

### Prerequisites (Pro)
- Prerequisite lessons within a course
- Prerequisite courses across catalog
- Lock messaging that is learner-friendly (“what to do next”)

### Advanced quizzes (Pro)
Beyond basic types:
- additional question types (e.g., image matching, fill blanks) as implemented
- quiz attempt rules: attempts, cooldown, question randomization
- detailed quiz report per student and per quiz

### Assignments (Pro)
- rubrics (simple scoring criteria)
- grading queue + filters
- instructor feedback templates
- re-submissions / revision cycle

### Gradebook (Pro)
- per-course and per-student grade view
- export to CSV
- “needs attention” indicators (late / ungraded / failed)

### Advanced certificates (Pro)
- drag & drop certificate builder
- dynamic variables (course, student, completion, score, instructor, serial)
- **verification**:
  - QR code
  - serial IDs
  - public verification page

---

## 3) Sell (revenue growth)

### Subscriptions & memberships (Pro)
- recurring billing (monthly/yearly plans)
- access expiry and renewal handling
- bundle subscriptions / memberships

### Advanced coupons (Pro)
- rules:
  - minimum cart total
  - course/category restrictions
  - first-time buyer only
  - limited to specific emails/domains (optional)
- stacking controls (allow/deny combining)

### Upsells (Pro)
- order bumps (checkout add-ons)
- post-purchase upsell (one-click add)

---

## 4) Teams (multi-instructor + revenue split)

### Multi-instructor (Pro)
- multiple instructors per course
- instructor permissions (owner/editor/grader)
- instructor dashboard enhancements

### Revenue split (Pro)
- per-course commission rules (percentage/fixed)
- instructor earnings view
- payout reporting (manual payouts in Pro; automated in Elite optional)

---

## 5) Reports & analytics (Pro)

### Included (Pro)
- completion and engagement analytics:
  - lesson drop-off
  - average time to completion (where feasible)
  - cohort comparisons
- revenue analytics:
  - by course
  - by coupon
  - by bundle/subscription
- exports:
  - CSV for enrollments, orders, grades, certificates issued
- student activity log (audit trail)

---

## 6) Live learning (Pro)
- Zoom integration
- Google Meet integration
- attendance logs
- calendar view

---

## 7) Better UX + security (Pro)
- social login
- stronger email automation configuration (without making the UI complex)
- 2FA optional (if implemented; can also be Elite depending on scope)

---

## Non-functional requirements (Pro)
- Reports should be built on **scalable storage** (avoid “slow queries over postmeta” for analytics).
- Export jobs must be **backgrounded** for large datasets.
- Every Pro feature must have:
  - a crisp empty state
  - minimal configuration steps
  - predictable URLs and deep links

---

## Upgrade triggers (Pro → Elite)
- enabling multi-vendor marketplace
- white label / rebranding
- webhooks + public API (if positioned as Elite)
- enterprise learning: SCORM/H5P advanced, multilingual, enterprise reports
- agency tooling: multi-site, license dashboard, client handoff

