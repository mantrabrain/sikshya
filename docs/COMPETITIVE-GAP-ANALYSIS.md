# Sikshya vs the market — verified gap analysis

Reviewed **2026-10-27**, against Sikshya 1.0.14 / Pro 1.0.10, and amended the same day when AI
authoring shipped (pending 1.0.15 / Pro 1.0.11).

Goal: Sikshya Free + Pro + addons at enterprise grade.

## How this was produced

- **Our column is grepped from `sikshya/src` and `sikshya-pro/src`**, never from marketing copy, and
  every grep hit was opened to check it was not an incidental substring. The inventory baseline is the
  **51 feature ids** in `Sikshya\Licensing\FeatureRegistry` (free 17, starter 6, pro 17, scale 11).
- **Competitor claims come from vendor pricing and feature pages fetched on 2026-10-27**, not from
  comparison blogs: tutorlms.com, liquidweb.com/software/learndash, lifterlms.com,
  stylemixthemes.com/masterstudy. Where a vendor page was truncated or a tier table did not render, that
  is said rather than guessed.
- **"Enterprise requirements" are recurring items across 2026 buyer checklists and RFP templates.** No
  standard defines "enterprise LMS". Most of those checklists are vendor-authored, so they indicate
  market expectation, not a specification.

### Corrections to the previous version of this file

The previous version was written before Groups, Cohorts and Compliance shipped, and several of its
"None" entries were already wrong by the time it was read. Recording them because a stale gap list is
worse than none — it misdirects a roadmap.

| Previous claim | Reality on 2026-10-27 |
|---|---|
| Groups / B2B seat sales: **None** — "the single largest enterprise gap" | **Shipped.** `groups_seats` (scale), with seat caps, group leaders, self-service roster and an admin screen |
| Cohorts: **None** | **Shipped.** `cohorts` (pro), drip anchored on the intake, with an admin screen |
| Compliance / mandatory training: **None** | **Shipped.** `compliance_training` (scale) — audience by role/group/cohort, deadlines, recurrence, waivers, audit reporting, admin screen |
| Payment gateways: "Offline, PayPal, Stripe, Paystack" | **Nine**: offline, bank transfer, PayPal, Stripe, Razorpay, Mollie, Paystack, Square, Authorize.Net. Authorize.Net was listed as a LifterLMS-only gap; we have it, including recurring |
| Memberships: **None**, "subscriptions only" | **Shipped 2026-10-28.** The capability already existed (a plan per course via `_sikshya_required_plan_id`); the product surface now does too — membership levels with a catalogue (all / courses / categories), a `[sikshya_membership_levels]` pricing table, an "included with membership" panel on a course, and the level named on the learner's account. Levels are additive only. Joining still goes through a subscription-priced course, because every order line is a course line |
| Audio lessons: "half-wired" / Tax: "a setting that does nothing" | Both fixed in 1.0.14 |
| AI authoring: "`docs/AI_ADDON_PREMIUM_UX_IMPLEMENTATION_BLUEPRINT.md` is a plan, not code" | **That file is not an AI-authoring plan.** It is a blueprint *for AI coding agents* about addon gating and premium UX — the three-axis model, `GatedFeatureWorkspace`, the doc→feature matrix. There was no AI-authoring design at all. Corrected here because the earlier wording implied a head start that did not exist |

Gaps confirmed as genuine despite a grep hit, after opening each file: **Divi/WPBakery** (the matches
are `individual`, `divider`),
**course collections** (`RestCollectionQueryService` is REST pagination), **direct messages** and
**public profiles** (description text and an OAuth `profile` scope), **OIDC** (the hit is Google
sign-in requesting `openid email profile` — consumer OAuth, not enterprise SSO).

## The strategic question the last version skipped

Every 2026 enterprise checklist asks for **SOC 2 Type II or ISO 27001**, with the report date.
**A self-hosted plugin vendor cannot answer that**, because the infrastructure being certified is the
customer's own. No amount of feature work changes it. The same is true of uptime SLAs and DPAs for a
processor relationship that does not exist.

So "enterprise" has to be read as two different markets:

- **The RFP market** (Docebo, Cornerstone, SAP SuccessFactors, TalentLMS). Entry requires vendor
  certifications we structurally cannot hold. Chasing it with features is a trap: we would build SCIM
  and still lose on the security questionnaire.
- **The self-hosted / data-sovereignty market.** Organisations that already run WordPress, must keep
  learner data on their own infrastructure, have internal IT, and answer their *own* SOC 2 scope.
  Universities, government agencies, regulated SMEs, franchise and association networks. For them our
  architecture is the *reason* to buy, and the certification question lands on their host, not on us.

Everything below is prioritised for the second market. Worth saying in marketing too: "your data never
leaves your infrastructure" is a feature for this buyer, not an apology.

## The identity finding — do not rebuild the protocol

The previous version called SAML 2.0 and SCIM 2.0 Tier 1 blockers to build. Research says otherwise:
**miniOrange already ships generic SAML 2.0 SSO and SCIM provisioning for WordPress**, 50+ IdPs, with
Okta and Entra guides and a LearnDash-specific walkthrough. Any customer can bolt it onto any WP LMS
today. Building our own SAML would duplicate a mature plugin and win nothing.

What those plugins **cannot** do is map identity onto LMS meaning. That is the real gap, and it is
much cheaper than a protocol implementation:

- An IdP group should land a learner in a **Sikshya group** (seats, leader, roster), not just a WP role.
- Deprovisioning should **close enrolments and record the compliance outcome**, not only delete a user.
- The HR attribute an auditor cares about — department, job code, hire date — should drive a
  **compliance audience**, and the assignment should be in the audit trail.

Two thirds of this already works by composition and nobody has written it down: miniOrange SCIM creates
the user with a role, and `compliance_training` assigns by role on its daily sync, so a new hire
inherits their mandatory training with no manual step. **That is a verifiable end-to-end enterprise
story available today**, and it is undocumented and untested. Writing and testing that recipe is worth
more than a quarter of protocol work.

## Tier 1 — what actually blocks a deal now

| Gap | Who has it | Our status | Why it matters |
|---|---|---|---|
| **xAPI / LRS, and cmi5** | Nobody natively. MasterStudy points customers at third-party Grassblade | **xAPI shipped 2026-10-27** — `xapi_lrs` add-on: 7 event types as xAPI 1.0.3 statements to any conformant LRS, queue with budgeted drain, 409-aware retry, three learner-identification modes, `docs/XAPI.md`. **cmi5 shipped 2026-10-28** — package import to curriculum (chapter per block, lesson per AU), launch with a one-time `fetch` token against *our* endpoint rather than the LRS's credentials, `LMS.LaunchData`, State API, moveOn satisfaction that completes the lesson. Not ADL-certified | Named in nearly every checklist alongside SCORM. The only remaining *standards* gap, and the one place we could be the only WP LMS that answers it natively |
| **WCAG 2.1/2.2 AA + VPAT / OpenACR** | Nobody publishes one | **Gated 2026-10-27.** axe-core WCAG 2.1 AA gates over 43 admin screens + 13 learner pages, a static label gate, a dialog focus-trap spec, 14 defect classes fixed, and a **draft** conformance report (`docs/ACCESSIBILITY-CONFORMANCE-REPORT.md`). **Still no screen-reader pass, so no signed VPAT** | Public sector and universities are procedurally barred from buying without it. Long lead time — start before it is asked for |
| **IdP → LMS semantics bridge** | Nobody | **Documented and tested 2026-10-27** — `docs/ENTERPRISE-IDENTITY.md`, with the new-hire-inherits-training path covered end to end. Departure now releases the group seat, closes open enrolments, and stops counting the person against compliance. Still not automatic: group membership by role |
| **Identity audit trail** | Nobody | **Shipped 2026-10-27**: account created / role changed / account removed, each carrying the identity and the actor, plus opt-in sign-ins. Sign-in *method* is still unknowable without the identity plugin declaring it |

## Tier 2 — where we are behind the WordPress market

This is the list that loses a comparison table, and the first row is now urgent.

| Gap | Who has it | Our status |
|---|---|---|
| **AI authoring** | **All three majors.** Tutor "AI Studio" (outline, lesson, quiz, feature image) in every paid tier; LearnDash AI course builder; MasterStudy "AI Lab" builds a course from one prompt | **Shipped** as `ai_authoring` (Starter and above): outline → chapters and lessons, lesson body, quiz questions with answer keys and explanations, bring-your-own key for Anthropic or OpenAI. Still missing against Tutor: AI feature images. The content bank it had nowhere to write to shipped on the 28th |
| **Migration importers** | MasterStudy imports from Tutor, LearnDash, LearnPress, LifterLMS, Masteriyo | **Tutor LMS and LearnDash shipped 2026-10-28**, free: courses, structure, lessons, quizzes with their answer keys, enrolments and completions, previewable and re-runnable. LearnDash's three levels adapt to Sikshya's two, and its ProQuiz answers are read without instantiating the classes they name. **LearnPress, LifterLMS and Masteriyo are not written** | Pure acquisition: it is the difference between "we could move" and "we moved". See `docs/MIGRATING-TO-SIKSHYA.md` |
| **WooCommerce bridge** | LearnDash, LifterLMS, Tutor | **Shipped 2026-10-28**, free: a product sells any number of courses, `completed`/`processing`/`payment_complete` grant idempotently, refunds close only what that order opened, guest checkout creates the account the purchase needs, and the course page hands its buy button to WooCommerce so two checkouts never compete. **Subscriptions renewals are not wired** | Adoption, not capability: a shop that already runs WooCommerce has its gateways, taxes and accounting working, and will not take a second checkout. See `docs/WOOCOMMERCE.md` |
| **Content bank / shared course steps** | Tutor "Content Bank"; LearnDash shared steps | **Shipped 2026-10-28** as `content_bank` (Starter and above): a library of lessons, quizzes and assignments, saved from any course and inserted into any section the author can edit; a quiz travels with its questions, answer keys and settings; items are private until shared with the site's other authors. Deliberately a **copy**, not LearnDash's shared post — progress and attempts are keyed to one lesson, so a shared step would report a learner finished in a course they never took. What that costs is stated: editing a banked item does not reach courses already built from it. cmi5 lessons are refused rather than half-copied. See `docs/CONTENT-BANK.md` |
| **Gamification points + leaderboard** | MasterStudy "Point System", LearnDash | **Shipped 2026-10-28** — the `gamification` add-on: a ledger with per-thing idempotency (so re-completing a lesson cannot farm points), derived totals for a fast board, manual adjustments, and a leaderboard that is staff-only until widened, honours a learner opt-out and forgets a deleted person. See `docs/GAMIFICATION.md` |
| **Membership product surface** | Tutor, MasterStudy, LifterLMS | **Closed 2026-10-28** — membership levels over existing plans; see `docs/MEMBERSHIPS.md` for what is not claimed (no plan-only checkout, no level tiering, no proration) |
| **PDF lessons** | MasterStudy | **Shipped 2026-10-29**, free: a `pdf` lesson kind whose body is a document, read in a viewer in the lesson. A media library file is delivered through the existing signed, per-user, expiring proxy rather than its uploads URL — which meant teaching that proxy inline delivery (a **signed** claim, gated on a one-entry MIME allowlist, because rendering an uploaded `.html` from our own origin is stored XSS) and byte ranges, which PDF viewers need. See `docs/PDF-LESSONS.md` |
| **Question media** (image/audio in a question) | MasterStudy, LearnDash | **Shipped 2026-10-29**, free: one image or audio clip on a question's stem, on all 8 types, served through the signed per-user proxy. The kind is derived from the file rather than asked, and an image cannot be saved without a description — the image *is* the question, so without one it cannot be attempted with a screen reader. Media **on the options** is deliberately not built: it would change what a learner submits. See `docs/QUESTION-MEDIA.md` |
| **Starter site templates** (~20) | MasterStudy | **None.** Adoption lever, not a capability |
| **Divi / WPBakery** | MasterStudy | **None.** Gutenberg and basic widgets |
| **BuddyPress / bbPress, SMS/Twilio, wishlist, trial courses, direct messages, public profiles, CEU/credits** | Various | **None** — each verified at 0 real hits |

## Where we are ahead — and should say so

- **Compliance & mandatory training.** Nothing in the WordPress market competes. Audience by
  role/group/cohort, derived overdue, recurrence dated from actual certification, waivers recorded
  separately, late completion reported apart from compliant.
- **Groups + seats + cohorts as one system**, with self-service rosters. LearnDash sells seats;
  LifterLMS sells groups; neither joins them to compliance.
- **Integration surface:** webhooks, Zapier, public API keys. The WP competitors have none — GetApp
  even lists Tutor LMS as having no API.
- **Marketplace / multivendor** with vendor scoping, **white label** with per-course overrides,
  **multisite network licensing**, **multilingual enterprise**.
- **Per-learner signed attachment URLs.** Competitors serve course files from unprotected uploads.
- **Activity log** — the closest thing in this market to an audit log, and now the only one that
  covers the accounts themselves: who was given access, whose role changed, who was removed, by whom.
- **Nine payment gateways**, including Authorize.Net and Square with recurring support.
- **AI authoring that cannot damage a course.** The three majors generate straight into the
  curriculum; ours returns an editable draft and applies it only on a second, explicit action. And
  because the key is the customer's, the AI feature does not break the "your data never leaves your
  infrastructure" promise the rest of the product makes.
- **Test depth.** 876 browser tests across Chromium, WebKit and Firefox; Free 147 integration + 27 unit
  + 12 uninstall + 21 migration + 4 multisite; Pro 721 + 9 + 5; green on PHP 7.4 and 8.4.

## Recommended sequencing

Reasoning: close the thing that is now *table stakes* before the things that differentiate, because a
missing checkbox everyone else ticks loses the comparison before our strengths are read.

1. ~~**AI authoring.**~~ **Done 2026-10-27**, in the shape the market settled on (outline → lesson →
   quiz, bring your own API key) plus two things the competitors do not do: generation never writes
   to a course until a human applies a draft it can edit, and the key is the customer's own, so no
   course material passes through the vendor. That last point is the data-sovereignty argument
   applied to AI, and it is worth saying in marketing: Tutor, LearnDash and MasterStudy all route
   generation through their own service.
2. ~~**Document and test the IdP → Sikshya recipe.**~~ **Done 2026-10-27.** The recipe is
   `docs/ENTERPRISE-IDENTITY.md`; the audit trail and the departure path are code, not prose. Two
   things found while doing it, both worse than the missing documentation: a deleted employee kept
   their group **seat** for ever, and a departed employee counted against **compliance** for ever.
   What is still manual is group membership by role — SCIM says nothing about which group a seat
   comes from, and inventing a rule would be guessing.
3. ~~**Accessibility programme.** axe-core gate per template, manual keyboard and screen-reader pass,
   remediation, published VPAT. Start now; it cannot be compressed.~~ **Half done, 2026-10-27.** The
   automatable half is in and enforced: gates on 56 pages, a static label check that found 36 fields
   the scan could not reach, dialog focus behaviour under test, and every defect found fixed. The
   half that cannot be automated is still open — no screen reader has been used, the course builder's
   drag-and-drop curriculum has no keyboard test, and reflow/zoom is untested — so the report is a
   draft, not a VPAT. See `docs/ACCESSIBILITY.md`.
4. ~~**xAPI/LRS, then cmi5.** The one standards gap, and a genuine first in this market.~~ **Done, 2026-10-28.** xAPI reporting shipped on the 27th; cmi5 import, launch and satisfaction on the 28th. No WordPress LMS has either natively. What is *not* claimed: ADL certification (there is a conformance suite and we have not been through it), block-level satisfaction, `abandoned`/`waived`, and launch modes other than `Normal` — all listed in `docs/XAPI.md`.
5. ~~**Migration importers**, starting with Tutor and LearnDash by install base. Pure acquisition.~~ **Tutor and LearnDash done, 2026-10-28** — free, previewable, re-runnable, non-destructive, and between them the two largest WordPress LMS install bases. LearnPress / LifterLMS / Masteriyo next *if* the install-base numbers justify them; the framework makes each one a reader class.
6. ~~**WooCommerce bridge.** Adoption, not capability.~~ **Done, 2026-10-28** — free, and deliberately not a second pricing system: WooCommerce charges, Sikshya grants. Subscriptions renewals are the one open piece.
7. ~~**Membership surface, content bank**, points/leaderboard, PDF lessons, question media.~~ **Content bank
   done, 2026-10-28** — snapshots rather than shared posts, because the existing progress and attempt rows
   are keyed to one lesson and a shared step would have made them lie. **Membership surface done,
   2026-10-28** — a level over an existing plan rather than a second access system or a second
   checkout, because every order line in Sikshya is a course line and a plan-only order would have
   put refunds, invoices and receipts at risk for a button. **Points and leaderboard done,
   2026-10-28** — the hard part was not the scoreboard but making an achievement pay exactly once,
   since the LMS fires its completion hooks again whenever a learner re-ticks a lesson. **PDF
   lessons done, 2026-10-29** — the lesson kind was an afternoon; the work was that the signed
   attachment proxy only ever forced a download, so inline delivery had to be added without
   handing anyone a way to render an arbitrary upload from our own origin, and byte ranges had to
   be answered because PDF viewers fetch a document a range at a time. **Question media done,
   2026-10-29** — on the stem, not on the options, because option media changes what a learner
   submits and is the half a grading bug would be worst in; and an image stem is refused without a
   description, since the image is the question. Remaining: starter site templates, then
   Divi/WPBakery.

Deliberately **not** recommended: building our own SAML or SCIM server, and pursuing SOC 2-gated RFPs.

## What "enterprise-level testing" still has to mean

Shipped since the last review: query-budget gates (compliance sync, list endpoints, admin lists),
upgrade verification on real data (1.0.9 → 1.0.14 on a throwaway install, content and settings
preserved), and a 42-screen admin health sweep.

Still missing, in priority order:

- **Accessibility:** automated axe-core per template plus a manual keyboard and screen-reader pass.
  Required before a VPAT can honestly be published. **Not started.**
- **Security:** authenticated role-matrix fuzzing across every REST route, rather than hand-written
  probes; dependency and CVE scanning in CI. **Not started.**
- **i18n:** pseudolocale rendering pass to catch hardcoded strings and layout breakage at 1.4x text
  length. **Not started.**
- **Load and scale:** the budget pattern exists but covers three areas. Extend to the course archive,
  the learner dashboard and the reporting endpoints, on a seeded 50k-learner / 5k-course fixture, as a
  gate. A correctness suite cannot catch a performance regression — the 35-second archive and the
  534-second webhook backlog were both found by accident.
- **Data protection:** GDPR export and erase correctness per learner.
- **Multi-tenancy:** 4 multisite tests is a smoke test, not a suite.

## Caveats

Competitor feature lists come from vendor pages and may overstate depth. LifterLMS's tier comparison
table did not render in the fetched page, so which tier includes Groups is unconfirmed. The LearnPress
pricing page 404'd, so LearnPress is carried over from the previous review and is the weakest column
here. Tier placement and pricing move; our own column is grepped from source and is the trustworthy
half of this document.
