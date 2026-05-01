# Sikshya REST route map

WordPress REST base: **`/wp-json/sikshya/v1/`** (namespace `sikshya/v1`).

- **Auth (admin):** cookie session + `X-WP-Nonce` (e.g. `wp.apiFetch` / React admin).
- **Auth (headless):** `Authorization: Bearer <jwt>` from `POST /sikshya/v1/auth/login` where applicable.

## Gating (commercial add-on + Addons)

Routes registered by **`sikshya-pro`** are **always present** when the commercial add-on is loaded. Access is denied with **HTTP 403** instead of omitting the route (avoids ambiguous **404** for API clients).

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
| `/scale/vendors` | GET, POST | `marketplace_multivendor` |
| `/scale/withdrawals` | POST | `marketplace_multivendor` |
| `/scale/reports/commissions` | GET | `marketplace_multivendor` |
| `/scale/automation/webhooks` | GET, POST | `automation_zapier_webhooks` |
| `/scale/automation/webhooks/(?P<id>\d+)` | DELETE | `automation_zapier_webhooks` |
| `/scale/public-api/keys` | GET, POST | `public_api_keys` |
| `/scale/public-api/keys/(?P<id>\d+)` | DELETE | `public_api_keys` |
| `/scale/public-api/ping` | GET | `public_api_keys` (validated inside callback; unauthenticated ping with Bearer key) |

**Course bundles extension (Pro):** My Account strip on `sikshya_account_dashboard_after` (`CourseBundlesAccountPanel` when **Show bundle packs on My Account** is on); filter `sikshya_course_bundles_account_panel_bundles`. Hooks include `sikshya_bundle_pricing_resolved`, `sikshya_course_bundles_after_create`, `sikshya_course_bundles_allow_trash`.

**Advanced coupons extension (Pro):** Global settings via `/pro/addons/coupons_advanced/settings`; storefront hooks on cart, checkout, single course (`sikshya_pro_single_course_price_after`), learn sidebar, account payments. Checkout filters `sikshya_coupon_blocked_message` (5 args), `sikshya_coupon_discount_amount`, `sikshya_coupons_advanced_blocked_message`, `sikshya_coupons_advanced_normalize_save_meta`.

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
