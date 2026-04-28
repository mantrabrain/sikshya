=== Sikshya LMS — WordPress LMS for Courses, Quizzes & eLearning ===
Contributors: mantrabrain
Donate link: https://mantrabrain.com/
Tags: lms, elearning, education, courses, learning management system
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build, sell, and deliver online courses on WordPress: modern course builder, lessons, quizzes, certificates, checkout, and learner dashboards—without a maze of add-ons.

== Description ==

**Sikshya LMS** is a WordPress learning management system built for **fast activation**: publish a curriculum, enroll learners, collect payment where needed, and prove outcomes with quizzes and completion certificates. The free core is intentionally strong so you can run a real training business before you ever upgrade.

Use Sikshya for coaching programs, professional training, internal enablement, or the first phase of a course marketplace—while keeping content, users, and revenue on infrastructure you control.

### Get started

👉 [Sikshya LMS — product & pricing](https://mantrabrain.com/plugins/sikshya/?utm_source=wporg&utm_medium=readme&utm_campaign=home)

👉 [Sikshya Pro](https://mantrabrain.com/plugins/sikshya/?utm_source=wporg&utm_medium=readme&utm_campaign=pro)

👉 [Documentation](https://mantrabrain.com/plugins/sikshya/)

👉 [Contact support](https://mantrabrain.com/contact/)

👉 [Sikshya LMS Facebook Community](https://www.facebook.com/groups/sikshyalms/)

Join the community for release notes, setup tips, and peer discussion with other course creators on WordPress.

### Why choose Sikshya?

* **Creator-first workflow** — A fast admin experience (React-powered shell) so you spend time teaching, not hunting through scattered WordPress screens.
* **Commerce that belongs in the free core** — Paid courses, coupons, orders, and mainstream gateways are part of the baseline story—not an afterthought locked behind “contact sales.”
* **Sensible defaults** — Fewer knobs on day one; advanced automation, marketplace, and reporting unlock with **Sikshya Pro** when you are ready to scale.
* **WordPress-native** — Courses, lessons, quizzes, and questions follow familiar CPT + capabilities patterns, with REST-oriented services where documented—so agencies and developers can extend predictably.

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

Use these shortcodes to embed Sikshya LMS components on any page/post.

* **Courses list**: `[sikshya_courses]`
  * **What it does**: Lists published courses using the same card layout as the course catalog.
  * **Attributes**:
    * `per_page` (default `9`, max `50`)
    * `columns` (optional; `3` uses a fixed 3-column layout, other values use an auto grid)
    * `view` (`grid` or `list`, default `grid`)
    * `category` (course category slug)
    * `tag` (course tag slug)
    * `search` (search term)
    * `orderby` (`date`, `title`, or `price`, default `date`)
    * `order` (`asc` or `desc`, default `desc`)
    * `pagination` (`1` or `0`, default `1`)
  * **Example**: `[sikshya_courses per_page="12" view="grid" orderby="date" order="desc" pagination="1"]`

* **Instructor application / registration form**: `[sikshya_instructor_registration]`
  * **What it does**: Shows a secure “apply to become an instructor” form for logged-in users. Guests will see a login prompt.

### Upgrade to Sikshya Pro

Unlock advanced drip and prerequisites, multi-instructor collaboration, subscriptions, deeper analytics and gradebook workflows, bundles, white-label options, and broader integrations. **Free stays generous; Pro unlocks scale.**

👉 [Explore Sikshya Pro](https://mantrabrain.com/plugins/sikshya/?utm_source=wporg&utm_medium=readme&utm_campaign=pro_detail)

### Use of third-party services

Features you enable may connect to services **you** configure. Examples:

* **Stripe** — [Terms of service](https://stripe.com/legal/ssa) · [Privacy](https://stripe.com/privacy)
* **PayPal** — [User agreement](https://www.paypal.com/us/legalhub/paypal/useragreement-full) · [Privacy](https://www.paypal.com/us/legalhub/paypal/privacy-full)
* **Embedded or linked video** — YouTube, Vimeo, or other hosts may apply their own embed terms, cookies, or analytics; see each provider’s policies.

If optional diagnostic or telemetry features are introduced in a future release, they will be disclosed in the changelog, documented on the product site, and gated behind explicit consent where required.

== Installation ==

1. Install **Sikshya LMS** from **Plugins → Add New** (search “Sikshya”) or upload the `sikshya` folder to `wp-content/plugins/`.
2. Activate the plugin.
3. Open **Sikshya** in the admin menu and complete setup: required pages, permalinks, branding basics, and email sender settings.
4. Configure **Payments** (Stripe/PayPal) using test credentials first; confirm a test order before switching live.
5. Create a course, add curriculum, publish, and visit the public course URL to verify your theme layout.
6. Optional: join the [Sikshya LMS Facebook Community](https://www.facebook.com/groups/sikshyalms/) for tips from other site owners.

== Frequently Asked Questions ==

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

Please follow the responsible disclosure process published on the vendor site (do not post exploit details in public reviews or forums).

= How does Sikshya relate to Sikshya Pro? =

**Sikshya** (this plugin) is the free foundation. **Sikshya Pro** is a separate commercial add-on that unlocks advanced features and service levels.

== Screenshots ==

1. Sikshya admin shell — fast navigation between courses, learners, and commerce tools.
2. Course builder — curriculum outline, lessons, and structured metadata.
3. Course catalog — discovery, filters, and archive layout aligned to your theme.
4. Single course page — outcomes, curriculum preview, and enrollment call-to-action.
5. Lesson experience — navigation, completion, and media-friendly layouts.
6. Quiz taking — attempts, scoring, and learner feedback aligned to quiz settings.
7. Student dashboard — enrollments, progress, and resume learning.
8. Checkout & orders — purchase path, coupons, and operational order detail.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Core course, lesson, quiz, and question model with builder-oriented admin UI.
* Student and instructor roles with capability-safe management surfaces.
* Baseline commerce: Stripe/PayPal-oriented checkout, coupons, orders.
* Learner templates, account views, and progress-oriented flows.
* REST-aligned services where documented for integrations.

== Upgrade Notice ==

= 1.0.0 =
First stable release. Back up your site before production rollout; confirm permalinks, required pages, and payment keys after activation.
