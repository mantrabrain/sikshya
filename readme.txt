=== Sikshya LMS — Build & Sell Online Courses ===
Contributors: mantrabrain
Donate link: https://mantrabrain.com/
Tags: lms, online courses, elearning, education, quizzes
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build and sell online courses on WordPress with one plugin: a visual course builder, lessons, quizzes, certificates, checkout, and learner dashboards—without stacking five separate tools.

== Description ==

**Sikshya LMS** is a **WordPress LMS** (learning management system) for creators who want students to enroll, learn, and pay—without leaving your site. The free version is built so you can launch a real course catalog, take payments when you are ready, and grow into **Sikshya Pro** only when you need automation and advanced add-ons.

**In plain English:** you create courses in the WordPress admin; learners open your course pages on the front of your site, track progress in their account, and complete quizzes or assignments you publish.

Use Sikshya for coaching, professional training, customer education, internal onboarding, or the start of a course marketplace—with full control of content, branding, and revenue.

### Get started

👉 [Sikshya LMS — product & pricing](https://mantrabrain.com/plugins/sikshya/?utm_source=wporg&utm_medium=readme&utm_campaign=home)

👉 [Sikshya Pro](https://mantrabrain.com/plugins/sikshya/?utm_source=wporg&utm_medium=readme&utm_campaign=pro)

👉 [Documentation](https://mantrabrain.com/plugins/sikshya/)

👉 [Contact support](https://mantrabrain.com/contact/)

👉 [Sikshya LMS Facebook Community](https://www.facebook.com/groups/sikshyalms/)

Join the community for release notes, setup tips, and peer discussion with other course creators on WordPress.

### New to WordPress LMS plugins?

* **No code required** to publish lessons and quizzes—you work inside Sikshya’s admin screens like other WordPress plugins.
* **Your theme** controls fonts and many layout basics; Sikshya adds course templates and learner views so selling and learning stay consistent.
* **Start small:** create one course, one short lesson, and one quiz; invite a test student account before you invite paying customers.
* **Payments are optional:** offer free courses first, then connect Stripe or PayPal when you sell.

### Why choose Sikshya?

* **Creator-first workflow** — A fast admin experience (React-powered shell) so you spend time teaching, not hunting through scattered WordPress screens.
* **Commerce that belongs in the free core** — Paid courses, coupons, orders, and mainstream gateways are part of the baseline story—not an afterthought locked behind “contact sales.”
* **Sensible defaults** — Fewer knobs on day one; advanced automation, marketplace, and reporting unlock with **Sikshya Pro** when you are ready to scale.
* **WordPress-native** — Courses, lessons, quizzes, and questions follow familiar custom post types and capabilities, with REST-oriented services where documented—so agencies and developers can extend predictably.

### Who is Sikshya for?

* **Coaches, consultants, and creators** shipping paid programs without hiring a platform team.
* **Training companies & academies** replacing spreadsheets with enrollments, progress, and assessments.
* **Teams doing internal training** who need completion tracking and light certification.
* **Agencies** standardizing one dependable LMS layer across client sites (including multisite when configured carefully).

### Extended use cases

* **Blended learning** — Self-paced lessons plus scheduled touchpoints (extended live tooling ships in Pro where applicable).
* **Customer education** — Product training and onboarding academies tied to your brand site.
* **Community & cohort programs** — Clear curriculum and progress signals; pair with your favorite community plugins as needed.

### Top features (free core)

**Courses & curriculum**

* Unlimited courses, lessons, and quizzes (within your hosting limits).
* Structured curriculum with sections/chapters and drag-and-drop style ordering.
* Lesson types: text, video via URL/embed-style delivery, downloadable materials.
* Course landing content: descriptions, FAQs, announcements, preview lessons.
* Course archive with search and filters aligned to your theme.

**Quizzes & assignments**

* Quiz builder: multiple choice, true/false, short answer.
* Passing marks, attempts, and timer-oriented assessment controls.
* Sequential progression and chapter-style gating where configured.
* Assignments with submission and manual grading for real-world evaluation.

**Learners**

* Student dashboard: enrollments, progress, resume learning.
* Wishlist for saved courses.
* Role-aware flows for administrators and instructors.

**Checkout & monetization (free baseline)**

* Free courses, paid courses, and manual enrollment by staff.
* Stripe and PayPal as first-class payment paths in settings.
* Coupons: percentage or fixed discounts, redemption limits, optional date windows.
* Order management: visibility, notes, and administrative refund-style workflows as implemented per release.

**Reliability & operations**

* Capability checks, nonces, and disciplined REST patterns aligned with WordPress security expectations.
* Transactional email hooks for enrollment, purchase, and completion journeys (templates evolve by release).
* Translation-ready (`sikshya` text domain); RTL-friendly layouts are a continuous improvement target—report theme-specific gaps via support.

### Native commerce & checkout

Sell access without duct-taping five plugins together for a basic launch: configure gateways, test in sandbox or test mode when available, publish your course page, and route buyers through a checkout experience designed for digital education—not generic cart prose bolted onto an LMS.

### Platform notes

* **Themes** — Built to cooperate with well-coded WordPress themes; use a default theme briefly if you need to isolate CSS conflicts.
* **Multisite** — Network-enabled; validate roles, capabilities, and data boundaries per site before production.
* **Developers** — Hooks and filters around enrollments, lesson completion, and quiz outcomes; REST coverage is documented alongside the product page.

### Shortcodes

Sikshya registers the shortcodes below. Paste them into any page, post, or widget that runs WordPress shortcodes (Shortcode block, Classic editor, or a theme template that calls `do_shortcode`). Attribute names are lowercase unless noted.

**Quick reference**

* `[sikshya_courses]` — Grid or list of published courses (same card UI as the catalog).
* `[sikshya_login]` — Sign-in form (Sikshya auth handler; errors stay on the same page).
* `[sikshya_registration]` — Create a Sikshya student account; optional instructor intent submits a pending teaching application.

**`[sikshya_courses]`**

**What it does:** Queries published courses and renders them with the same course card partial used on archives and the catalog.

**Attributes** (all optional except where a default is listed):

* `per_page` — Number of courses per page. Default `9`. Minimum `1`, maximum `50`.
* `columns` — Layout hint. `3` forces a three-column grid; other positive values (up to `6`) adjust the auto grid; `0` or omitted uses the default auto layout.
* `view` — `grid` or `list`. Default `grid`.
* `category` — Filter by **course category** taxonomy slug (not the numeric ID).
* `tag` — Filter by **course tag** taxonomy slug.
* `search` — Free-text search string (same idea as the catalog search).
* `orderby` — `date`, `title`, or `price`. Default `date`.
* `order` — `asc` or `desc`. Default `desc`.
* `pagination` — `1` (show paging) or `0` (single page). Default `1`. When enabled, page links use the query argument **`sikshya_courses_page`** so paging does not clash with the main query.

**Examples**

`[sikshya_courses]`

`[sikshya_courses per_page="12" view="grid" category="web-design" orderby="price" order="asc" pagination="1"]`

`[sikshya_courses view="list" search="wordpress" pagination="0"]`

**`[sikshya_login]`**

**What it does:** Renders an email-or-username + password form that authenticates through Sikshya’s `admin-post` handler (`wp_signon`). Failed logins show a notice on the **same URL** (no redirect to `wp-login.php`). Used on the virtual login page and inside checkout.

**Attributes:**

* `redirect_to` — Absolute or relative URL after **successful** login. Validated with `wp_validate_redirect`. If empty, the handler falls back to the HTTP referer, then the site home URL.

**Examples**

`[sikshya_login]`

`[sikshya_login redirect_to="/my-account/"]`

`[sikshya_login redirect_to="https://example.com/checkout/"]`

**`[sikshya_registration]`**

**What it does:** Renders a registration form (display name optional, email, password). Creates a WordPress user with the **Sikshya student** role, then triggers the same **new-user email notifications WordPress sends after core registration** (`wp_send_new_user_notifications`, admin + user). Intended for checkout (“Create account”) and custom landing pages.

**Attributes:**

* `type` — `student` or `instructor`. Default `student`. **`instructor` does not assign the instructor role:** the account is a student and a **pending instructor application** is recorded (same meta as the account “Apply to teach” flow). An administrator approves applications in the dashboard; only then is the `sikshya_instructor` role added.
* `redirect_to` — Same behavior as `[sikshya_login]` after successful registration.

**Developers:** Filter `sikshya_send_new_user_notifications` (bool, user ID) to disable core emails if you replace them with your own.

**Examples**

`[sikshya_registration]`

`[sikshya_registration type="student"]`

`[sikshya_registration type="instructor" redirect_to="/courses/"]`

### Upgrade to Sikshya Pro

Unlock advanced drip and prerequisites, multi-instructor collaboration, subscriptions, deeper analytics and gradebook workflows, bundles, white-label options, and broader integrations. **Free stays generous; Pro unlocks scale.**

👉 [Explore Sikshya Pro](https://mantrabrain.com/plugins/sikshya/?utm_source=wporg&utm_medium=readme&utm_campaign=pro_detail)

👉 [Sikshya pricing & plans](https://mantrabrain.com/plugins/sikshya#pricing)

### Sikshya Pro add-on catalog

Below is the full commercial add-on line-up from the Sikshya feature registry. **Each title links to pricing** so you can compare plans. Availability varies by plan tier (Starter, Growth / Pro band, Scale); see the pricing page for the current matrix.

#### Starter-band add-ons

* **[Content drip & scheduled unlock](https://mantrabrain.com/plugins/sikshya#pricing)** — Release lessons over time (“day 3 after signup”, dates, cohort pace) instead of opening the full catalog on day one. Best for paced programs and term-style delivery; disable for purely self‑paced libraries.

* **[Course reviews & ratings](https://mantrabrain.com/plugins/sikshya#pricing)** — Collect star ratings and written reviews on course pages with moderation before they go live. Builds social proof in the catalog; turn off when public reviews don’t fit your model.

* **[Prerequisites (lessons & courses)](https://mantrabrain.com/plugins/sikshya#pricing)** — Require completion of chosen lessons or whole courses before the next step unlocks—ideal for sequencing, compliance, or leveled paths. Leave off when every course stands alone.

* **[Instructor dashboard](https://mantrabrain.com/plugins/sikshya#pricing)** — Gives each teacher a concise snapshot (e.g. enrollments on their courses) without sharing the whole admin site. Useful when instructors should see **their** numbers only.

* **[Drip & automation emails](https://mantrabrain.com/plugins/sikshya#pricing)** — Optional transactional emails when drip rules unlock lessons or schedules (templates in Email templates). Pair with Content drip when you want “lesson unlocked” style notices.

* **[Calendar](https://mantrabrain.com/plugins/sikshya#pricing)** — Shows learners a dated schedule—enrollments, upcoming drip unlocks, assignment due dates—on My account plus REST data for custom UIs. Handy when deadlines and releases should appear in one place.

#### Growth / Pro-band add-ons

* **[Professional email delivery & branded templates](https://mantrabrain.com/plugins/sikshya#pricing)** — Route Sikshya emails through a proper ESP (SendGrid-style setup) and wrap messages with your branding. Improve deliverability versus generic PHP mail.

* **[Course discussions & Q&A](https://mantrabrain.com/plugins/sikshya#pricing)** — In-course discussions and Q&A with instructor moderation for cohort-led learning. Skip when comments are handled entirely outside Sikshya.

* **[Multi-instructor & co-authors](https://mantrabrain.com/plugins/sikshya#pricing)** — Assign multiple instructors per course with optional revenue splits for shared authoring and payouts. Keeps ledger-style splits disciplined at checkout.

* **[Advanced analytics & exports](https://mantrabrain.com/plugins/sikshya#pricing)** — Download enrollment-style and progress-ready data for Excel/Sheets and offline planning. Bridges dashboard charts and spreadsheets when stakeholders need files.

* **[Gradebook](https://mantrabrain.com/plugins/sikshya#pricing)** — Consolidates quizzes and graded assignments into a per‑learner, per‑course scores view plus export workflows. Targets real grading—not “completion only.”

* **[Student activity log](https://mantrabrain.com/plugins/sikshya#pricing)** — Timeline of milestones (enrollment, completions, quizzes, submissions, checkout) when you must answer **what happened, when**. Helpful support and dispute trail.

* **[Advanced certificates (builder, QR, verification)](https://mantrabrain.com/plugins/sikshya#pricing)** — Verification links/pages, richer layouts, and optional QR tying to proofs beyond the basic PDF. Use when authenticity checks matter externally.

* **[Subscriptions & memberships](https://mantrabrain.com/plugins/sikshya#pricing)** — Sell ongoing access via recurring billing models instead of strictly one-shot course sales. Fits memberships and renewals layered on gateways you configure.

* **[Course bundles](https://mantrabrain.com/plugins/sikshya#pricing)** — Sell several courses together for one bundled price—“bootcamp packs” or value SKUs—with enrollment logic tied to the pack.

* **[Advanced coupons & upsells](https://mantrabrain.com/plugins/sikshya#pricing)** — Coupon rules beyond a flat discount—minimum order, applicability to chosen courses—and checkout guardrails accordingly.

* **[Dynamic checkout fields](https://mantrabrain.com/plugins/sikshya#pricing)** — Add configurable checkout questions (text, select, checkbox) with simple visibility rules. Store answers on orders or profiles when you need VAT, referrals, consent, etc.

* **[Advanced assignments](https://mantrabrain.com/plugins/sikshya#pricing)** — Rubric-style grading guidance and uploads restricted by file types for stricter coursework hand-ins.

* **[Advanced quiz types](https://mantrabrain.com/plugins/sikshya#pricing)** — Groups / pools of reusable questions when you assemble many quizzes without duplicating stems—think organized question banking.

* **[Live classes (Zoom / Meet / Classroom)](https://mantrabrain.com/plugins/sikshya#pricing)** — Persist meeting links and platform labels directly on lessons so learners always hit the correct live URL from the syllabus.

* **[Social login](https://mantrabrain.com/plugins/sikshya#pricing)** — Let learners sign in with Google-style providers when policy allows fewer passwords-only accounts.

* **[SCORM / H5P (Pro tier)](https://mantrabrain.com/plugins/sikshya#pricing)** — Embed packaged SCORM or H5P experiences inside Sikshya lessons—bridge vendor-built interactives inside your Sikshya path.

#### Scale-band add-ons

* **[Multi-vendor marketplace](https://mantrabrain.com/plugins/sikshya#pricing)** — Track vendor ownership per course plus platform-vs-seller splits for many independent sellers sharing one storefront.

* **[White label & branding](https://mantrabrain.com/plugins/sikshya#pricing)** — Tune Sikshya-facing labels and learner/admin chrome toward your agency or customer brand—including login accents where supported.

* **[Webhooks](https://mantrabrain.com/plugins/sikshya#pricing)** — Deliver signed JSON to your HTTPS endpoints whenever major LMS lifecycle events occur for custom automation backends.

* **[Zapier](https://mantrabrain.com/plugins/sikshya#pricing)** — First-class Zapier workflow entry points so Sikshya events can fan into thousands of Zap actions without bespoke code projects.

* **[Email marketing (Mailchimp / MailerLite)](https://mantrabrain.com/plugins/sikshya#pricing)** — Keep marketing lists synced from enrollments/completions so campaigns react to Sikshya learning milestones.

* **[Public API & API keys](https://mantrabrain.com/plugins/sikshya#pricing)** — Issue revocable secrets for bespoke apps/partners integrating over REST without sharing WordPress passwords.

* **[Multisite & network license tools](https://mantrabrain.com/plugins/sikshya#pricing)** — Guidance surfaces for multisite admins mapping licenses across subsites on true WordPress networks.

* **[Enterprise reporting](https://mantrabrain.com/plugins/sikshya#pricing)** — Automated weekly KPI-style email rollups aimed at inbox-friendly executive snapshots—pair with analytics exports when you need detail too.

* **[Multilingual (WPML / Weglot)](https://mantrabrain.com/plugins/sikshya#pricing)** — Bridges Sikshya’s front-end/interface strings into popular translation stacks so multi‑language sites localize consistently beside your theme/content.

### Use of third-party services

Features you enable may connect to services **you** configure. Examples:

* **Stripe** — [Terms of service](https://stripe.com/legal/ssa) · [Privacy](https://stripe.com/privacy)
* **PayPal** — [User agreement](https://www.paypal.com/us/legalhub/paypal/useragreement-full) · [Privacy](https://www.paypal.com/us/legalhub/paypal/privacy-full)
* **Embedded or linked video** — YouTube, Vimeo, or other hosts may apply their own embed terms, cookies, or analytics; see each provider’s policies.

If optional diagnostic or telemetry features are introduced in a future release, they will be disclosed in the changelog, documented on the product site, and gated behind explicit consent where required.

== Installation ==

1. Install **Sikshya LMS** from **Plugins → Add New** (search “Sikshya”) or upload the `sikshya` folder to `wp-content/plugins/`.
2. Activate the plugin.
3. Open **Sikshya** in the WordPress admin menu and walk through setup: required pages, permalink structure (pretty URLs recommended), basic branding, and email sender settings.
4. Under **Payments**, add **Stripe** and/or **PayPal** using **test** keys first; run a small test purchase before switching to live keys.
5. Create your first course, add at least one lesson and (optionally) a quiz, publish, then open the public course URL in a private browser window to see what learners see.
6. Optional: join the [Sikshya LMS Facebook Community](https://www.facebook.com/groups/sikshyalms/) for tips from other site owners.

**Tip:** If anything looks wrong on the front of your site, temporarily switch to a default WordPress theme (Twenty Twenty-Five, etc.) to tell Sikshya styling apart from theme conflicts.

== Frequently Asked Questions ==

= What is an LMS (in one sentence)? =

A **learning management system** is software that hosts your lessons, tracks who finished what, and often handles enrollment or payment—so you are not emailing PDFs and spreadsheets by hand.

= Is Sikshya LMS free? =

Yes. This plugin ships a full free core for building courses, enrolling learners, running quizzes, and selling with baseline checkout features. Advanced modules and priority support are available with **Sikshya Pro**.

= Do I need coding skills? =

No for day-to-day course building. Developers can still extend Sikshya using WordPress hooks, filters, and documented REST endpoints.

= Does Sikshya work with any WordPress theme? =

It is designed for broad theme compatibility. If layouts clash, test with a default WordPress theme to separate theme CSS from LMS templates.

= How do I sell courses? =

Create a paid course, set the price, connect **Stripe** and/or **PayPal** under Sikshya payment settings, and run a test transaction before accepting live payments.

= Can I use WooCommerce or another cart instead? =

The free core emphasizes native-style course checkout for speed and clarity. Deeper cart and membership integrations may appear in Pro or via future integrations—check the product page for the current roadmap.

= Does Sikshya support subscriptions or memberships? =

Recurring subscriptions and advanced membership rules are part of the **Sikshya Pro** positioning. The free tier focuses on strong one-time (and free-course) selling.

= Is Sikshya multisite compatible? =

Yes, the plugin is flagged for network use. Always verify instructor/student capabilities and data isolation per subsite in staging.

= Can I translate Sikshya? =

Yes. Strings use the `sikshya` text domain and are compatible with Loco Translate, WPML, TranslatePress, and similar workflows.

= Where can I get help or talk to other users? =

Use [Contact support](https://mantrabrain.com/contact/) for account or technical issues, and join the [Sikshya LMS Facebook Community](https://www.facebook.com/groups/sikshyalms/) for peer discussion and best practices.

= How do I report a security vulnerability? =

Email the WordPress Plugins Team at plugins@wordpress.org with details (do not post exploit steps in public reviews). You may also use the vendor contact page on the product site for coordinated disclosure.

= How does Sikshya relate to Sikshya Pro? =

**Sikshya** (this plugin) is the free foundation. **Sikshya Pro** is a separate commercial add-on that unlocks advanced features and service levels.

= Will this hurt my site’s SEO? =

Sikshya outputs normal WordPress pages and URLs. Use clear course titles, excerpts, and internal links from your homepage or blog—same good habits as any WordPress site. Pair with your preferred SEO plugin for meta titles and sitemaps.

== Screenshots ==

1. Sikshya admin dashboard — quick access to courses, learners, and commerce from one React-powered shell.
2. Course list in the admin — search, filter, and manage all courses from a single screen.
3. Course Builder — edit curriculum, settings, and content in one structured workspace.
4. Global Settings — payments, emails, labels, and LMS-wide behavior in one place.
5. Public course catalog — how courses appear to visitors when using your theme and Sikshya templates.
6. Learn experience — lesson view with curriculum sidebar and progress-friendly layout for enrolled students.

== Changelog ==

= 1.0.0 - 2026-04-30 =
* Initial public release.
* Core course, lesson, quiz, and question model with builder-oriented admin UI.
* Student and instructor roles with capability-safe management surfaces.
* Baseline commerce: Stripe/PayPal-oriented checkout, coupons, orders.
* Learner templates, account views, and progress-oriented flows.
* REST-aligned services where documented for integrations.
* Default certificate presets (Regalia & Vertex) ship without QR blocks; QR-style verification remains documented as a Pro-oriented enhancement.
* Requires PHP 7.4+ and WordPress 6.0+; tested on current stable WordPress releases.
* Checkout (with Sikshya Pro Dynamic Checkout Fields): optional server-rendered dynamic field markup—JavaScript attaches listeners and visibility without rebuilding the form in the browser; includes `CheckoutDynamicFieldsView` and refactored checkout helpers (`dfBindDynamicFields`, `dfSyncValuesFromHost`).

== Upgrade Notice ==

= 1.0.0 - 2026-04-30 =
First stable release. Back up your site before production rollout; confirm permalinks, required pages, and payment keys after activation. Clear page caches if checkout forms are cached at the edge.
