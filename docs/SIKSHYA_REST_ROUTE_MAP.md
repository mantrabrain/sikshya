# Sikshya REST route map

WordPress REST base: **`/wp-json/sikshya/v1/`** (namespace `sikshya/v1`).

- **Auth (admin):** cookie session + `X-WP-Nonce` (e.g. `wp.apiFetch` / React admin).
- **Auth (headless):** `Authorization: Bearer <jwt>` from `POST /sikshya/v1/auth/login` where applicable.

## Gating (commercial add-on + Addons)

Routes registered by **`sikshya-pro`** fall into two groups, and the difference is visible to API clients:

- **Central routes** — everything `SikshyaPro\Bootstrap::registerRest()` registers (`ProAddonRoutes`, `ProExtendedFeatureRoutes`, `ProAddonSettingsRoutes`, reviews, discussions, webhooks, API keys, public API, OAuth). These are **always present** whenever the commercial add-on is loaded, and refuse with **HTTP 403** rather than being omitted, so a client can tell "not allowed" from "not a route".
- **Per-add-on routes** — a controller registered inside an add-on's own `Addon::boot()` (membership levels, points, content bank, cmi5, xAPI, and most other add-on surfaces). `AddonManager::bootEnabledAddons()` never boots a disabled add-on, so while that module is **off** its routes are **not registered at all** and answer **404 `rest_no_route`**, not 403.

That second behaviour is deliberate — a module that is off genuinely is not there — but it is worth knowing before you write a client: **probe the feature catalog (`GET /admin/addons`), not a 404, to decide whether a module exists.** A 404 from a `/pro/…` path means "either this route never existed or its add-on is switched off", and only the catalog tells you which.

| Error code (`code` in JSON) | When |
|-----------------------------|------|
| `rest_forbidden` | Logged-in user lacks capability (`manage_sikshya`, `edit_sikshya_courses`, `manage_options`, etc.). |
| `sikshya_plan_feature_required` | Plan does not include the catalog feature (`TierCapabilities::feature( $id )` is false). Legacy integrations may still check `sikshya_pro_required`; responses may also include `legacy_error_code` / `data.legacy_error_code`. |
| `sikshya_addon_disabled` | Plan includes the feature, but the module is **off** in **Addons** (`Addons::isEnabled( $id )` is false). |

React admin paths are centralized in `client/src/api/endpoints.ts` (`SIKSHYA_ENDPOINTS`).

---

## Free plugin (`sikshya`) — `src/Api/*`

| Route | Methods | Source | Notes |
|-------|---------|--------|-------|
| `/course-builder/save` | POST | `AdminRestRoutes` | Course builder |
| `/course-builder/bootstrap` | GET | `AdminRestRoutes` | `?course_id=` |
| `/admin/course-chapters` | GET | `AdminRestRoutes` | |
| `/admin/course-curriculum-tree` | GET | `AdminRestRoutes` | |
| `/curriculum/content` | POST | `AdminRestRoutes` | Create content |
| `/curriculum/content/link` | POST | `AdminRestRoutes` | |
| `/curriculum/content-item` | POST | `AdminRestRoutes` | |
| `/curriculum/chapter-order` | POST | `AdminRestRoutes` | |
| `/curriculum/lesson-order` | POST | `AdminRestRoutes` | |
| `/curriculum/outline-structure` | POST | `AdminRestRoutes` | |
| `/curriculum/bulk-delete` | POST | `AdminRestRoutes` | |
| `/curriculum/chapters` | POST | `AdminRestRoutes` | Create |
| `/curriculum/chapters/(?P<id>\d+)` | GET, PUT, DELETE | `AdminRestRoutes` | |
| `/taxonomies/course-category` | GET, POST | `AdminRestRoutes` | |
| `/taxonomies/course-category/(?P<id>\d+)` | GET, PUT, DELETE | `AdminRestRoutes` | |
| `/settings/schema` | GET | `AdminRestRoutes` | |
| `/settings/values` | GET | `AdminRestRoutes` | `?tab=` |
| `/settings/save` | POST | `AdminRestRoutes` | |
| `/settings/reset` | POST | `AdminRestRoutes` | |
| `/tools` | POST | `AdminRestRoutes` | Maintainer tools (`permissionTools`) |
| `/admin/post-status-counts` | GET | `AdminRestRoutes` | |
| `/admin/overview` | GET | `AdminRestRoutes` | Dashboard |
| `/admin/licensing` | GET | `AdminRestRoutes` | Same catalog/gates as `window.sikshyaReact.licensing` |
| `/admin/shell-meta` | GET | `AdminRestRoutes` | `?view=` — shell refresh |
| `/admin/reports-snapshot` | GET | `AdminRestRoutes` | |
| `/admin/enrollments` | GET | `AdminRestRoutes` | |
| `/admin/quiz-attempts` | GET | `AdminRestRoutes` | |
| `/admin/payments` | GET | `AdminRestRoutes` | |
| `/admin/issued-certificates` | GET | `AdminRestRoutes` | |
| `/admin/issued-certificates/revoke` | POST | `AdminRestRoutes` | |
| `/admin/orders` | GET | `AdminRestRoutes` | |
| `/admin/orders/(?P<id>\d+)/mark-paid` | POST | `AdminRestRoutes` | Offline / manual fulfill |
| `/admin/coupons` | GET, POST | `AdminRestRoutes` | List + create |
| `/admin/addons` | GET | `AdminAddonsRestRoutes` | Catalog + `enabled[]` |
| `/admin/addons/(?P<id>…)/enable` | POST | `AdminAddonsRestRoutes` | 403 if plan lacks feature |
| `/admin/addons/(?P<id>…)/disable` | POST | `AdminAddonsRestRoutes` | |
| `/admin/license` | GET | `AdminLicenseRestRoutes` | Pro plugin |
| `/admin/license/activate` | POST | `AdminLicenseRestRoutes` | |
| `/admin/license/save` | POST | `AdminLicenseRestRoutes` | |
| `/admin/license/deactivate` | POST | `AdminLicenseRestRoutes` | |
| `/admin/license/check` | POST | `AdminLicenseRestRoutes` | |
| `/courses`, `/courses/(?P<id>\d+)` | CRUD | `Api.php` | CPT-backed |
| `/lessons`, `/lessons/(?P<id>\d+)` | CRUD | `Api.php` | |
| `/quizzes`, `/quizzes/(?P<id>\d+)` | CRUD | `Api.php` | |
| `/users`, `/users/(?P<id>\d+)` | CRUD | `Api.php` | |
| `/enrollments`, `/enrollments/(?P<id>\d+)` | CRUD | `Api.php` | |
| `/progress` | — | `Api.php` | |
| `/certificates` | — | `Api.php` | |
| `/payments` | — | `Api.php` | |
| `/me/progress` | — | `LearnerRestRoutes` | |
| `/me/lesson-complete` | — | `LearnerRestRoutes` | |
| `/me/quiz-submit` | — | `LearnerRestRoutes` | |
| `/me/unenroll` | — | `LearnerRestRoutes` | |
| `/me/reports-advanced/export` | GET | `LearnerRestRoutes` action `sikshya_register_addon_learner_rest_routes` | **Pro** `reports_advanced` learner CSV (`type=my_enrollments\|my_quiz_attempts`); gated by add-on + plan + `allow_learner_self_export` |
| `/me/assignments` | — | `LearnerRestRoutes` | If `assignments_basic` addon |
| `/me/assignment-submit` | — | `LearnerRestRoutes` | |
| `/me/assignment-feedback` | — | `LearnerRestRoutes` | |
| `/me/enroll` | — | `PublicRestRoutes` | |
| `/checkout/session`, `/checkout/quote`, `/checkout/confirm` | — | `CheckoutRestRoutes` | |
| `/webhooks/stripe`, `/webhooks/paypal` | POST | `WebhooksRestRoutes` | Gateway callbacks (`__return_true`) |
| `/public/certificates/verify` | GET | `CertificatesPublicRoutes` | Public verify |
| `/auth/login` | POST | `AuthRestRoutes` | JWT |
| `/file/(?P<token>[^/]+)` | GET | `AttachmentProxyRoutes` | Streams an attachment's bytes instead of exposing its `uploads/` URL. `permission_callback` is `__return_true` on purpose — authority is the HMAC token, which names the attachment, the **one** user id allowed to use it, an expiry, and optionally `dsp: inline`. Identity is resolved from the REST user, a Bearer JWT, or the logged-in cookie read directly (a clicked link carries cookies but no nonce). Mismatch → 403, expired → 410. Answers `Range` with 206; serves `Content-Disposition: inline` only for a signed inline claim **and** an allowlisted MIME (`application/pdf`), otherwise `attachment`. Emits binary and `exit`s, so it never returns a JSON body. |

---

## Pro plugin (`sikshya-pro`) — `src/Rest/*`

Registered on `rest_api_init` (priority 20) when `Sikshya\Licensing\Pro` exists. Each route checks **plan** (`Pro::feature`) and **addon** (`Addons::isEnabled`) in its `permission_callback` (see **Gating** above).

**Scale**-tier paths use the **`/scale/`** prefix (`ScaleAutomationRoutes`, `ScaleMarketplaceRoutes`).

| Route | Methods | `FeatureRegistry` id (primary) |
|-------|---------|--------------------------------|
| `/pro/drip-rules` | GET, POST | `content_drip` |
| `/pro/subscriptions` | GET, POST | `subscriptions` (`SikshyaPro\Addons\Subscriptions\Controllers\SubscriptionsRestController` on `rest_api_init` priority 10 when the add-on boots) |
| `/pro/subscriptions/cancel` | POST | `subscriptions` (same) |
| `/pro/plans` | GET, POST | `subscriptions` (same) |
| `/pro/plans/(?P<id>\d+)` | PUT/PATCH, DELETE | `subscriptions` (same) |
| `/pro/points/leaderboard` | GET | `gamification` (`SikshyaPro\Addons\Gamification\Rest\GamificationRestController`; `scope` = all/month/week, opted-out learners filtered out, `dormant` says whether anything on the site awards points) |
| `/pro/points/learners/(?P<user_id>\d+)` | GET | `gamification` (one learner's standing and ledger) |
| `/pro/points/adjust` | POST | `gamification` (**writes** a manual ledger line; `manage_options`; capped at 100,000 points) |
| `/pro/points/recalculate` | POST | `gamification` (**writes**: rebuilds totals from the ledger, for one `user_id` or everyone; `manage_options`) |
| `/pro/memberships/levels` | GET, POST | `subscriptions` (`SikshyaPro\Addons\Subscriptions\Memberships\Controllers\MembershipsRestController`; GET returns each level with a resolved `course_count` and a `problems` list, POST creates or replaces one — `manage_options`) |
| `/pro/memberships/levels/(?P<id>[A-Za-z0-9_\-]+)` | DELETE | `subscriptions` (removes the level and the access it granted; subscriptions, orders and enrolments are untouched) |
| `/pro/memberships/targets` | GET | `subscriptions` (courses and course categories for the level editor's pickers) |
| `/pro/gradebook` | GET | `gradebook` (registered in Pro: `GradebookRestController`) |
| `/pro/gradebook/export` | GET | `gradebook` |
| `/pro/gradebook/grid` | GET | `gradebook` |
| `/pro/gradebook/drilldown` | GET | `gradebook` |
| `/pro/gradebook/assignment-grade` | POST | `gradebook` |
| `/pro/gradebook/learner` | GET | `gradebook` |
| `/pro/gradebook/override` | POST | `gradebook` |
| `/pro/grade-scales` | GET, POST | `gradebook` |
| `/pro/grade-scales/(?P<id>\d+)` | GET, PUT/PATCH, DELETE | `gradebook` |
| `/pro/multi-instructor/course-staff` | GET, POST, DELETE | `multi_instructor` |
| `/pro/multi-instructor/earnings` | GET | `multi_instructor` |
| `/pro/multi-instructor/earnings/set-status` | POST | `multi_instructor` + `manage_options` |
| `/pro/extended/activity-log` | GET | `activity_log` (`SikshyaPro\Addons\ActivityLog\Controllers\ActivityLogRestController`; query `page`, `per_page`, `user_id`, `course_id`, `action`, `search`, `date_from`, `date_to`) |
| `/pro/reports-advanced/export` | GET | `reports_advanced` (registered when add-on is enabled + licensed; query `type`, filters) |
| `/pro/certificates/advanced` | GET | `certificates_advanced` (`SikshyaPro\Addons\CertificatesAdvanced\Controllers\CertificatesAdvancedRestController`; returns URL templates, merge fields, settings, hook names) |
| `/pro/bundles` | GET, POST | `course_bundles` (`SikshyaPro\Addons\CourseBundles\Controllers\CourseBundlesRestController` on `rest_api_init` priority 10 when the add-on boots) |
| `/pro/bundles/(?P<id>\d+)/courses` | GET, POST | `course_bundles` (same) |
| `/pro/bundles/(?P<id>\d+)/courses/(?P<course_id>\d+)` | DELETE | `course_bundles` (same) |
| `/pro/bundles/(?P<id>\d+)` | DELETE | `course_bundles` (same) |
| `/pro/bundles/(?P<id>\d+)/purchase-link` | GET | `course_bundles` (same) |
| `/pro/coupons/(?P<id>\d+)/advanced` | GET, POST | `coupons_advanced` (`SikshyaPro\Addons\CouponsAdvanced\Controllers\CouponsAdvancedRestController` on `rest_api_init` priority 10; body `rules` object for POST) |
| `/admin/coupons/(?P<id>\d+)` | PATCH | Core admin (`AdminRestRoutes::patchAdminCoupon`) — coupon basics; requires Sikshya admin app permission |
| `/pro/ai/status` | GET | `ai_authoring` (`SikshyaPro\Addons\AiAuthoring\Rest\AiAuthoringRestController`; reports provider, model, language, question types and monthly usage — never the API key) |
| `/pro/ai/outline` | POST | `ai_authoring` (body `topic`, `audience`, `chapters`, `lessons_per_chapter`, `notes`; returns a draft, writes nothing) |
| `/pro/ai/lesson` | POST | `ai_authoring` (body `lesson_id` or `lesson_title`, `context`, `words`; returns `content_html`, writes nothing) |
| `/pro/ai/quiz` | POST | `ai_authoring` (body `chapter_id` or `source_title`, `source_text`, `question_count`, `types`; returns questions, writes nothing) |
| `/pro/ai/apply/chapter` | POST | `ai_authoring` (**writes**: one chapter plus its lessons, through `CurriculumService`) |
| `/pro/ai/apply/lesson` | POST | `ai_authoring` (**writes**: replaces a lesson body) |
| `/pro/ai/apply/quiz` | POST | `ai_authoring` (**writes**: a quiz plus its questions inside one chapter) |
| `/pro/content-bank/items` | GET, POST | `content_bank` (`SikshyaPro\Addons\ContentBank\Rest\BankRoutes`; GET filters `kind`, `search`, `page`, `per_page` and is scoped to the caller's own plus shared items; POST **writes** bank rows from `post_ids` (max 50 per call) and reports a `skipped` list for anything the caller cannot edit) |
| `/pro/content-bank/items/(?P<id>\d+)` | POST/PATCH/PUT, DELETE | `content_bank` (rename and `visibility`; author or site staff only) |
| `/pro/content-bank/items/(?P<id>\d+)/insert` | POST | `content_bank` (**writes**: a new lesson/quiz/assignment in `chapter_id`, through free's `CurriculumService`; requires edit access to that chapter's course) |
| `/pro/content-bank/targets` | GET | `content_bank` (courses the caller may write into, each with its sections) |
| `/pro/content-bank/course-content` | GET | `content_bank` (query `course_id`; the bankable items already in that course, in outline order) |
| `/scale/vendors` | GET, POST | `marketplace_multivendor` |
| `/scale/withdrawals` | POST | `marketplace_multivendor` |
| `/scale/reports/commissions` | GET | `marketplace_multivendor` |
| `/scale/automation/webhooks` | GET, POST | `automation_zapier_webhooks` |
| `/scale/automation/webhooks/(?P<id>\d+)` | DELETE | `automation_zapier_webhooks` |
| `/scale/public-api/keys` | GET, POST | `public_api_keys` |
| `/scale/public-api/keys/(?P<id>\d+)` | DELETE | `public_api_keys` |
| `/scale/public-api/ping` | GET | `public_api_keys` (validated inside callback; unauthenticated ping with Bearer key) |
| `/scale/xapi/status` | GET | `xapi_lrs` (queue counts and last failure; never returns the LRS key or secret) |
| `/scale/xapi/statements` | GET | `xapi_lrs` (the statement log, without statement bodies) |
| `/scale/xapi/test` | POST | `xapi_lrs` (**sends**: one real statement down the production path) |
| `/scale/xapi/drain` | POST | `xapi_lrs` (**sends**: runs one budgeted drain of the queue) |
| `/scale/cmi5/packages` | GET, POST | `xapi_lrs` (**writes**: a POST imports a package and creates chapters + lessons) |
| `/scale/cmi5/packages/(?P<id>\d+)` | GET, DELETE | `xapi_lrs` (DELETE forgets the import; the lessons it created stay) |
| `/scale/cmi5/launch` | POST | `xapi_lrs` (**learner** route: creates a session and returns the launch URL) |
| `/scale/cmi5/lrs/fetch` | POST | *token-authenticated* — the unit's one-time credential exchange |
| `/scale/cmi5/lrs/statements` | POST, PUT | *session-authenticated* — accepted and forwarded; GET answers 405 |
| `/scale/cmi5/lrs/activities/state` | GET, PUT, POST, DELETE | *session-authenticated* — `LMS.LaunchData` is read-only |
| `/scale/cmi5/lrs/agents/profile` | GET, PUT, POST, DELETE | *session-authenticated* — answers "no document" |
| `/scale/cmi5/asset/(?P<uuid>…)/(?P<path>.+)` | GET | enrolment- or session-gated package files |

**Course bundles extension (Pro):** My Account strip on `sikshya_account_dashboard_after` (`CourseBundlesAccountPanel` when **Show bundle packs on My Account** is on); filter `sikshya_course_bundles_account_panel_bundles`. Hooks include `sikshya_bundle_pricing_resolved`, `sikshya_course_bundles_after_create`, `sikshya_course_bundles_allow_trash`.

**Advanced coupons extension (Pro):** Global settings via `/pro/addons/coupons_advanced/settings`; storefront hooks on cart, checkout, single course (`sikshya_pro_single_course_price_after`), learn sidebar, account payments. Checkout filters `sikshya_coupon_blocked_message` (5 args), `sikshya_coupon_discount_amount`, `sikshya_coupons_advanced_blocked_message`, `sikshya_coupons_advanced_normalize_save_meta`.

**AI authoring extension (Pro):** generation routes are read-only against the database; the three `/pro/ai/apply/*` routes are the only ones that write, and they are applied one chapter or one quiz at a time so a large outline cannot land in a single request. Any `course_id`, `chapter_id`, `lesson_id` or `quiz_id` in the request is checked with `InstructorPermissions::canEditObject()`, and non-administrators are refused entirely unless **Let instructors generate** is on. Filters: `sikshya_ai_authoring_http_timeout`, `sikshya_ai_authoring_hourly_user_cap`, `sikshya_ai_authoring_provider_client`, `sikshya_ai_authoring_anthropic_endpoint`, `sikshya_ai_authoring_openai_endpoint`, `sikshya_ai_authoring_system_prompt`, `sikshya_ai_authoring_outline_prompt`, `sikshya_ai_authoring_lesson_prompt`, `sikshya_ai_authoring_quiz_prompt`.

**xAPI / LRS extension (Pro):** outbound only — Sikshya reports to your LRS and is not one. Statements are queued (`wp_sikshya_xapi_statements`) and drained by `sikshya_xapi_drain_queue` every five minutes under a count and wall-clock budget; `sikshya_xapi_prune_statements` prunes *sent* rows daily, and only when retention is set. Each row carries the statement's own UUID from the moment it is queued, which is why a 409 from the LRS counts as sent rather than as a failure. Filters: `sikshya_xapi_statement`, `sikshya_xapi_actor`, `sikshya_xapi_verb_iri`, `sikshya_xapi_activity_type_iri`, `sikshya_xapi_activity_iri_base`, `sikshya_xapi_statements_per_run`, `sikshya_xapi_time_budget`, `sikshya_xapi_retry_backoff`. See `docs/XAPI.md`.

**WooCommerce (free, no REST surface):** hooks only. Grants on `woocommerce_order_status_completed`, `…_processing` and `woocommerce_payment_complete`; revokes on `…_refunded`, `…_cancelled`, `…_failed` and per-line on `woocommerce_order_refunded`; blocks re-purchase through `woocommerce_add_to_cart_validation`. The product→courses link is the `_sikshya_course_ids` post meta, and every enrolment it creates carries `transaction_id = wc:<order_id>`, which is how a refund revokes exactly what that order granted and nothing else. The course page hands over its buy buttons through `sikshya_course_purchase_handled` + `sikshya_course_purchase_actions`. Own hooks: `sikshya_woocommerce_access_granted`, `sikshya_woocommerce_access_revoked`, `sikshya_woocommerce_create_account_for_guest`, `sikshya_woocommerce_guest_role`. See `docs/WOOCOMMERCE.md`.

**Importing from another LMS (free):** `/admin/import/sources` (GET), `/admin/import/run` (POST), `/admin/import/reset` (POST), all `manage_options`. `run` does **one batch** and returns `finished`, a cursor and running totals; the caller repeats until finished, which is how a site with hundreds of courses migrates without one request that no host will hold open. Every created object is recorded in `wp_sikshya_import_map` against the id it came from, which is what makes a second run an update rather than a second catalogue. Sources read and never write: `Sikshya\Import\ImportSourceInterface` + the `sikshya_import_sources` filter; `sikshya_import_batch_size` and `sikshya_import_time_budget` resize a batch. Also `wp sikshya import`. See `docs/MIGRATING-TO-SIKSHYA.md`.

**cmi5 extension (Pro, part of `xapi_lrs`):** the four `/scale/cmi5/lrs/*` routes are the only ones a *launched unit* talks to, authenticated by its session token rather than a cookie, because content runs in an iframe. Every statement they accept is re-stamped with the session's actor, activity and registration before being forwarded, so a unit cannot report about another learner. `LMS.LaunchData` is read-only. Packages live in `uploads/sikshya-cmi5/<uuid>/` and are served only through `/scale/cmi5/asset/…`, never from the uploads URL. Tables: `wp_sikshya_cmi5_packages`, `_aus`, `_sessions`, `_state`. Filters: `sikshya_cmi5_launch_url`, `sikshya_cmi5_launch_data`; action `sikshya_cmi5_au_satisfied`. See `docs/XAPI.md`.

**Activity log extension (Pro):** filters `sikshya_activity_log_action_label`, `sikshya_activity_log_allow_insert`, `sikshya_activity_log_scope_course_ids`, `sikshya_activity_log_show_learn_sidebar`; hook `sikshya_activity_log_recorded` after a row is stored. Learn UI: `sikshya_learn_sidebar_footer` (see `ActivityLogLearnTouchpoints`).

---

## Cron (Pro)

| Hook | Purpose | When unscheduled / skipped |
|------|---------|----------------------------|
| `sikshya_pro_drip_cron` | Content drip unlock grants | `DripCron::maybeSchedule()` clears the schedule when `content_drip` is not licensed or addon is off. |
| `sikshya_activity_log_retention_cron` | Purges old `sikshya_activity_log` rows when retention is enabled | Unscheduled when `activity_log` is off or `retention_days` is 0 (`ActivityLogRetentionService`). |

---

## Legacy AJAX → REST (historical)

Older admin code referred to AJAX actions; many are mapped below for migration reference.

### Course builder & curriculum (CourseAjax)

| Legacy `action` | Target REST route | Method |
|----------------|-------------------|--------|
| `sikshya_save_course_builder` | `/course-builder/save` | POST |
| `sikshya_save_course` | `/course-builder/save` | POST |
| `sikshya_create_content` | `/curriculum/content` | POST |
| `sikshya_link_content_to_chapter` | `/curriculum/content/link` | POST |
| `sikshya_load_curriculum` | `/admin/curriculum` | GET `?course_id=` |
| `sikshya_save_content_type` | `/curriculum/content-item` | POST |
| `sikshya_save_chapter_order` | `/curriculum/chapter-order` | POST |
| `sikshya_save_lesson_order` | `/curriculum/lesson-order` | POST |
| `sikshya_create_chapter` | `/curriculum/chapters` | POST |
| `sikshya_update_chapter` | `/curriculum/chapters` | PUT |
| `sikshya_load_chapter_data` | `/curriculum/chapters/(?P<id>\d+)` | GET |
| `sikshya_bulk_delete_items` | `/curriculum/bulk-delete` | POST |
| `sikshya_course_list` | `/admin/courses` | GET |
| `sikshya_course_delete` | `/admin/courses/(?P<id>\d+)` | DELETE |
| Template/modal loaders | `/admin/templates/(?P<name>[a-z0-9_-]+)` | GET/POST (future) |

### Categories (CategoriesAjax)

| Legacy | Target |
|--------|--------|
| `sikshya_save_category` | `/taxonomies/course-category` | POST |
| `sikshya_delete_category` | `/taxonomies/course-category/(?P<id>\d+)` | DELETE |

### Settings (SettingsAjax)

| Legacy | Target |
|--------|--------|
| `sikshya_save_settings` | `/settings/save` | POST |
| `sikshya_load_settings_tab` | `/settings/values?tab=` | GET |
| `sikshya_reset_settings` | `/settings/reset` | POST |
| `sikshya_export_settings` | `/settings/export` | GET |
| `sikshya_import_settings` | `/settings/import` | POST |

### Licensing (React admin / refresh)

| Purpose | Target | Method |
|---------|--------|--------|
| Feature catalog + Pro gates | `/admin/licensing` | GET |

### List tables / misc

| Legacy | Target |
|--------|--------|
| `sikshya_delete_course` (list-table.js) | `/admin/courses/{id}` | DELETE |
| `sikshya_tools_action` | `/tools` | POST |
| `sikshya_user_action` | `/admin/users` | POST |
| `sikshya_report_action` | `/admin/reports` | POST |
| `sikshya_admin_action` | `/admin/misc` | POST |
| `sikshya_load_table_data` | `/admin/datatable` | POST |

### Frontend (CourseController / FrontendAjax)

| Legacy | Target |
|--------|--------|
| `sikshya_enroll_course` | `/enrollments` or `/courses/(id)/enroll` | POST |
| `sikshya_search_courses` | `/courses?search=` | GET |
| `sikshya_frontend_action` | `/public/*` | per sub-action |

### Controllers/CourseController (duplicate REST)

Consolidate under `src/Api/Api.php`; remove duplicate `register_rest_route` from `src/Controllers/CourseController.php` when unified.

---

**Maintenance:** Update this file when adding or removing `register_rest_route` registrations, especially under `sikshya-pro`. See `docs/AI_ADDON_PREMIUM_UX_IMPLEMENTATION_BLUEPRINT.md` Part H.
