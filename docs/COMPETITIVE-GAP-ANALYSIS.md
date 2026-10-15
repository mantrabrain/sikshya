# Sikshya vs the market — verified gap analysis

Goal: Sikshya Free + Pro + addons at enterprise grade.

## How this was produced

- **Our inventory** is the 48 ids in `Sikshya\Licensing\FeatureRegistry`, not marketing copy.
- **Every "we don't have this" line was grepped against `sikshya/src` and `sikshya-pro/src`** before being
  written down. Where a grep hit turned out to be an incidental substring match it is called out.
- **Competitor inventories** come from vendor pages (LearnDash on liquidweb.com, the LifterLMS store,
  Themeum's Tutor LMS addon docs), not from comparison blogs. Treat competitor *pricing* and tier
  placement as unverified — it moved between sources.
- **Enterprise requirements** are the recurring items across buyer checklists and RFP guides. No
  authoritative standard defines "enterprise LMS", so these are market expectations, not a spec.

Corrections found while verifying, worth keeping:

- **Weighted grading is already implemented** (`GradebookCalculationService::weightedQuizPercent()` and
  friends). LearnDash markets it as a Pro differentiator; we have it.
- **Learner notes already exist** in the learn shell. LifterLMS sells this as an addon.
- `cohort`, `ceu` and `group_` all appear in our code only as substring matches or unrelated comments.
  They are genuine gaps despite the grep hits.

## Tier 1 — the gaps that actually block enterprise deals

These are the ones that lose a procurement conversation outright.

| Gap | Who has it | Our status | Why it blocks |
|---|---|---|---|
| **Groups / B2B seat sales** | LearnDash Elite (Groups Plus, "sell seats"), LifterLMS (Groups + group leader) | **None.** No group entity, no seat pool, no group leader role | A company cannot buy 50 seats and self-administer them. This is the single largest enterprise gap and a direct revenue lever |
| **Cohorts** | LifterLMS (Course Cohorts, cohort reporting) | **None** | Scheduled intakes progressing together — the norm for corporate and academic programmes |
| **SAML 2.0 SSO** | Enterprise baseline; neither WP competitor does it well | **None.** `social_login` is OAuth only | No SAML means no Okta/Entra/Ping. Usually a hard requirement |
| **SCIM 2.0 provisioning** | Enterprise baseline | **None** | Without it, deprovisioning a leaver is manual — an audit finding |
| **Compliance / mandatory training** | Enterprise baseline; absent from WP competitors | **None.** No assignment-by-role, due dates, recertification, or audit-ready reporting | Regulated industries buy an LMS *for* this. Biggest differentiation opportunity |
| **xAPI / LRS** | LearnDash (via addons) | **None.** We have SCORM + H5P only | Named in nearly every checklist alongside SCORM |
| **WCAG 2.1 AA + VPAT** | Enterprise baseline | A11y polish shipped; **no audit, no VPAT** | Public sector and universities cannot buy without a VPAT/OpenACR |

## Tier 2 — ecosystem and adoption levers

| Gap | Who has it | Our status |
|---|---|---|
| **WooCommerce integration** | LearnDash, LifterLMS, Tutor | **None.** Our checkout is native-only |
| **Migration importers** (LearnDash / LifterLMS / Tutor) | LearnDash markets "Migrations" | **None** — nothing references a competitor schema |
| **AI course outline + quiz authoring** | LearnDash Pro | **None** |
| **Gamification points + leaderboards** | LearnDash (badges *and* points) | Badges/achievements only; **no points, no leaderboard** |
| **Membership plugin bridges** (MemberPress, PMPro, Restrict Content Pro) | LearnDash | **None** |
| **Form plugin bridges** (Gravity, WPForms, Ninja, Formidable) | LifterLMS (4 addons) | **None** |
| **Community bridges** (BuddyPress / bbPress) | LearnDash, Tutor | **None.** We have our own discussions |
| **SMS / Twilio notifications** | LifterLMS (incl. enrol-by-text) | **None** |
| **Quiz export / import** | Tutor | **None** |
| **Shared course steps** (reuse a lesson across courses) | LearnDash | **None** |

## Tier 3 — commercial polish

| Gap | Who has it | Our status |
|---|---|---|
| Gifting a course | LifterLMS | None |
| Name-your-price / donations | LifterLMS | None |
| Cart abandonment recovery | LifterLMS | None |
| Private Site (whole site behind login) | LifterLMS | None — cheap win for internal training |
| Private 1:1 areas | LifterLMS | None |
| Order / grade PDFs | LifterLMS (PDFs addon) | Certificate PDFs only |
| Continuing education units (CEU/credits/hours) | LifterLMS | None |
| Video progression (force full watch) | LearnDash | None |
| Plagiarism detection | LearnDash | None |
| Content integrity (copy/spam protection) | LearnDash | None |
| Custom report cards | LearnDash | None |
| Authorize.Net | LifterLMS | Offline, PayPal, Stripe, Paystack |

## Where we are already ahead

Worth defending, and worth saying out loud in marketing:

- **Marketplace / multivendor** with vendor scoping — Tutor's strength, absent from LearnDash and LifterLMS.
- **White label** with per-course overrides.
- **Webhooks + Zapier + public API keys** — a real integration surface; the WP competitors have none.
- **Multisite network licensing**, enterprise reports, multilingual enterprise.
- **Per-learner signed attachment URLs.** Competitors serve course files from unprotected uploads.
- **Activity log** — maps directly to the audit-log requirement, which most WP LMS plugins cannot answer.

## Recommended sequencing

Rationale: do the things that *unblock a deal* before the things that *win a comparison table*.

1. **Groups, seats and cohorts.** Unlocks B2B. Everything else in Tier 1 is easier once a group entity exists.
2. **Compliance pack** — assignment by role, due dates, recertification, audit-ready reports, CEU. Highest
   differentiation per unit of effort; nothing in the WP market competes.
3. **Enterprise identity** — SAML 2.0, then SCIM 2.0.
4. **Accessibility programme** — WCAG 2.1 AA audit, fixes, published VPAT. Long lead time, so start early.
5. **WooCommerce bridge + migration importers.** Adoption, not capability.
6. **xAPI/LRS**, then Tier 2 bridges, then Tier 3 polish.

## What "enterprise-level testing" has to mean

Current state: Free 165 PHPUnit, Pro 615 PHPUnit (PHP 7.4 + 8.4), 243 browser tests on Chromium +
WebKit + Firefox, all green. That is a solid functional gate and **not yet** an enterprise gate. Missing:

- **Accessibility**: automated axe-core pass per template, plus a manual keyboard and screen-reader pass.
  Required before a VPAT can honestly be published.
- **Load and scale**: the 35s archive regression was found by accident on a 3,900-course dataset. Needs a
  seeded large-dataset fixture (50k learners / 5k courses) with asserted query budgets and page-time
  ceilings, run as a gate. A correctness suite cannot catch a performance regression.
- **Security**: authenticated role-matrix fuzzing across every REST route, not the hand-written probes we
  have now; dependency and CVE scanning.
- **Upgrade**: migration from each supported prior version on real data, plus the interrupted-migration case.
- **Multi-tenancy**: network-install suite (currently 4 multisite tests).
- **i18n**: pseudolocale rendering pass to catch hardcoded strings and layout breakage at 1.4x text length.
- **Data protection**: GDPR export and erase correctness per learner.

## Caveats

Competitor feature lists are from vendor marketing pages and may overstate depth or be out of date;
tier placement and pricing were inconsistent across sources and are not relied on here. Our own column
is grepped from source and is the trustworthy half of this document.
