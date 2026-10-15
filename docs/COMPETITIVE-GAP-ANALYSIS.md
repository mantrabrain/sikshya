# Sikshya vs the market — verified gap analysis

Goal: Sikshya Free + Pro + addons at enterprise grade.

## How this was produced

- **Our inventory** is the 48 ids in `Sikshya\Licensing\FeatureRegistry`, not marketing copy.
- **Every "we don't have this" line was grepped against `sikshya/src` and `sikshya-pro/src`** before being
  written down. Where a grep hit turned out to be an incidental substring match it is called out.
- **Competitor inventories** come from vendor pages (LearnDash on liquidweb.com, the LifterLMS store,
  Themeum's Tutor LMS addon docs, StyleMix's MasterStudy feature pages, the LearnPress add-ons page),
  not from comparison blogs. Treat competitor *pricing* and tier placement as unverified — it moved
  between sources.
- **The first version of this document was incomplete.** It covered LearnDash, LifterLMS and Tutor
  only. MasterStudy and LearnPress were added later and produced a dozen gaps this file had missed,
  listed under "Second pass" below. Breadth of catalogue is MasterStudy's strength and it is where we
  are furthest behind.
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

## Second pass — gaps found only after adding MasterStudy and LearnPress

MasterStudy publishes by far the widest catalogue of the five. Each line below was
grepped against both plugins before being written down.

| Gap | Who has it | Our status |
|---|---|---|
| **Taxes on checkout** | MasterStudy | **A setting that does nothing.** `tax_rate` exists in `SettingsManager` and is read nowhere in `Commerce/` or `Services/` — an administrator can set a rate and no total changes |
| **Audio lessons** | MasterStudy | **Half-wired.** The lesson editor has a branch for `lessonType === 'audio'`, but the type picker offers no such option and the server allowlist is `['text','video','live','scorm','h5p']`, so an audio lesson silently becomes a text one |
| **PDF lessons** | MasterStudy | None |
| **Question media** (image/audio inside a question) | MasterStudy, LearnDash (audio clip) | None |
| **Gamification points + leaderboard** | MasterStudy, LearnDash | None. `points_earned` is quiz scoring, not a points economy |
| **Trial courses** | MasterStudy | None |
| **Memberships** (access by membership level, distinct from subscriptions) | MasterStudy, LifterLMS, LearnPress (PMPro) | Subscriptions only |
| **Course collections** | LearnPress | None (bundles are a purchase unit, not a browse grouping) |
| **Upsell / bonus coupons at checkout** | LearnPress | None |
| **Direct messages between users** | MasterStudy | None. Discussions are per-course, not person-to-person |
| **Public instructor / student profiles** | MasterStudy | None |
| **Divi and WPBakery support** | MasterStudy | Gutenberg and basic widgets only |
| **Ready-made starter site templates** (~20) | MasterStudy | None. A significant adoption lever rather than a capability |
| **Wishlist** | LearnPress ships it **free** | Removed from our catalogue in 1.0.10 because it had no implementation. Correct as an honesty fix, but the competitive answer is to build it |
| **LMS migration importers** | MasterStudy imports from Tutor, LearnDash, LearnPress, LifterLMS and Masteriyo | None |

Our live-class provider coverage is better than the gap list above implies: Zoom, Google Meet, Jitsi
and Webex are all present in `LiveProviderCatalog`.

### Two bugs this research surfaced

Both verified by reading the code, neither fixed yet:

1. ~~**`tax_rate` is a no-op setting.**~~ **Fixed.** Wired through `TaxService` into the quote and the
   order, with a `tax_total` column and a versioned migration. Exclusive and inclusive modes are both
   implemented; a rate of 0 (the shipped default) leaves every existing store untouched.
2. ~~**The `audio` lesson type is inconsistent across layers.**~~ **Fixed.** The meta is now
   REST-registered, the type picker offers it, the server allowlist accepts it, and the renderer that
   already worked is finally reachable.

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

## Measured findings worth acting on

Both found while testing, both recorded rather than hotfixed.

**Sample import is a ~17-second synchronous request.** The setup wizard's sample
pack inserts about 375 posts (7 courses, 131 lessons, 42 quizzes, 126 questions,
42 assignments) and measured 16.8–17.8 seconds server-side. Re-measured while
adding ~1,100 posts to the site and the time stayed flat, so it is the insert
cost itself, not something that scales with existing data — it is not the same
class of bug as the 34-second archive query. It still matters: plenty of shared
hosts cap `max_execution_time` at 30 seconds, so on a slower host this can die
part-way and leave half-imported sample data behind. It should be batched with
progress reporting rather than left as one blocking call.

**The test suite had a systemic identity leak.** `browser.newContext()` inherits
the project's `storageState`, which is the administrator's session. Sixteen call
sites across ten specs created "another user" contexts that silently carried the
admin cookie — and they were disproportionately the permission and isolation
specs (vendor scoping, multi-instructor stranger checks, auditor read-only,
instructor-versus-student). Fixed with two helpers, `freshContext()` and
`adminContext()`, so intent is explicit at the call site. How many assertions
were previously vacuous was not measured and is not claimed.

## Current test baseline

- PHP: Free 128 (27 unit, 101 integration) plus 12 uninstall, 21 migration, 4
  multisite; Pro 625 integration and 9 unit. Green on PHP 7.4 and 8.4.
- Browser: 244 tests per engine. Chromium 241 passed / 3 skipped / 0 flaky;
  WebKit 239 / 3 / 2 flaky; Firefox 240 / 3 / 1 flaky. No hard failures.
- Known flake: `studentSession` can still report no authenticated session under
  load, because a real `wp-login.php` POST against two PHP workers occasionally
  outlasts its wait. It passes on retry. Worth hardening with a bounded re-check
  of `/users/me` before it throws.

Progress against the list under "What enterprise-level testing has to mean":

- **Load and scale with asserted query budgets** — started. The first budget
  gate is `ComplianceQueryBudgetTest`, which asserts the compliance sync's cost
  does not grow per learner. It was written after that sync shipped with an N+1
  measuring 17,481 queries over 6.6 seconds on a real 2,913-learner dataset while
  every behaviour test passed. Reverting the fix makes the budget fail with
  "243 queries against 64 for 10 — cost is growing per learner". The pattern now
  exists and should be extended to the course archive, the learner dashboard and
  the reporting endpoints.
- **Accessibility with a publishable VPAT** — not started.
- **REST role-matrix fuzzing** — not started.
- **Upgrade on real data** — partially. The `tax_total` migration was verified on
  a real MySQL install (1.10.0 with the column absent, upgraded, then re-run for
  idempotency) rather than only under the temporary-table harness.
- **i18n pseudolocale rendering** — not started.

## Caveats

Competitor feature lists are from vendor marketing pages and may overstate depth or be out of date;
tier placement and pricing were inconsistent across sources and are not relied on here. Our own column
is grepped from source and is the trustworthy half of this document.
