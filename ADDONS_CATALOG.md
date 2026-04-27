# Sikshya add-ons catalog (Title + Description)

Source of truth: `src/Licensing/FeatureRegistry.php` (non-free tiers: Starter, Growth/Pro, Scale).

## Starter

1- **content_drip**: Content drip & scheduled unlock  
  Releases lessons over time (days after enrollment or fixed dates) instead of showing the full course immediately. ( DONE ) 

2- **course_reviews**: Course reviews & ratings  
  Lets enrolled students leave star ratings and written reviews on course pages (optionally moderated). ( DONE ) 

3- **prerequisites**: Prerequisites (lessons & courses)  
  Locks lessons/courses until required lessons/courses are completed. ( COMPLETED )

4- **instructor_dashboard**: Instructor dashboard  
  A limited teacher-facing snapshot (enrollments on courses they authored) without full admin access. ( DONE )

5- **drip_notifications**: Drip & automation emails  
  Enables drip unlock notification emails via Email templates when Content drip unlocks items. ( DONE) 

6- **calendar**: Calendar  
  Learner schedule (enrollments, drip unlocks, due dates) shown on account dashboard + available via REST. ( DONE )

## Growth / Pro

7- **email_advanced_customization**: Professional email delivery & branded templates  
  SMTP / provider-style delivery, optional HTML header & footer on transactional mail (addon), test send from **Email → Delivery** when licensed and enabled; template copy remains under **Email → Templates** for all tiers. ( DONE )

8- **multi_instructor**: Multi-instructor & co-authors  
  Course staff table, builder sync, normalized revenue splits on paid orders, per-course payout toggle, global visibility settings, REST + Course staff admin UI. ( DONE )

9- **reports_advanced**: Advanced analytics & exports  
  Admin CSV exports (summary, enrollments, quiz attempts) via `/pro/reports-advanced/export`, instructor scope + per-course opt-out + addon settings. Learner self-export via `/me/reports-advanced/export` and UI on **My learning**, **Quiz attempts**, and the **learn** sidebar (toggle in addon settings).

10- **gradebook**: Gradebook  
  Course/learner gradebook view across quizzes and assignments, with export support, course grid, overrides, grade scales, global settings, and per-course “hide from learner gradebook”. ( DONE ) 

11- **activity_log**: Student activity log  
  Timeline of learner events (enroll, unenroll, lessons, quizzes, assignments, course completion, certificates, orders), admin REST with filters + scoped access, per-course opt-out, retention cron, global settings, My account strip, learn sidebar strip (course mode), extensibility filters. ( DONE )

12- **certificates_advanced**: Advanced certificates (builder, QR, verification)  
  Visual templates + merge tokens, public verify/doc URLs, optional QR, inactive/revoked HTML (410 + filter), learner toolbar + learn-sidebar account link, cart/checkout completion hint, Certificates hub Settings tab, issuance hooks, admin info REST, course builder meta. ( DONE )

13- **subscriptions**: Subscriptions & memberships  
  Subscription-style access (recurring membership logic) instead of only one-time purchases. Add-on REST (`SubscriptionsRestController`), repositories under `Addons/Subscriptions/Repositories`, course builder bridge (hide subscription pricing when add-on off), global settings (manual grants, period-end enforcement, membership panel), hooks `sikshya_subscriptions_allow_manual_create`, `sikshya_subscriptions_allow_cancel`, `sikshya_subscriptions_after_manual_create`, `sikshya_subscriptions_after_cancel`, `sikshya_subscriptions_after_checkout_fulfillment`, filters `sikshya_course_builder_pricing_tab_fields`, `sikshya_course_builder_pricing_tab_validate_errors`. ( DONE )

14- **course_bundles**: Course bundles  
  Sell several courses together for one price. Meta-based bundles (`CourseBundlesRepository`, `BundleCatalogService`), REST (`CourseBundlesRestController`), account panel (`CourseBundlesAccountPanel`), global settings (signed links, redirect, badges, default bundle match, max courses, account strip), course builder bridge, legacy SQL tables dropped via `ProSchema`, hooks `sikshya_course_bundles_after_create`, `sikshya_course_bundles_rest_list_rows`, `sikshya_course_bundles_allow_trash`, `sikshya_bundle_pricing_resolved`, `sikshya_course_bundles_account_panel_bundles`. ( DONE )

15- **coupons_advanced**: Advanced coupons & upsells  
  Min/max cart subtotal, allow & exclude course lists, max discount cap, per-learner redemption limit, first-paid-order-only, optional schedule window, per-course “exclude from coupons” (builder), global storefront hints + optional cart promo copy, REST (`CouponsAdvancedRestController`), admin coupon PATCH, hooks `sikshya_coupon_blocked_message` (5 args), `sikshya_coupon_discount_amount`, `sikshya_coupons_advanced_blocked_message`, `sikshya_coupons_advanced_normalize_save_meta`. ( DONE )

16- **assignments_advanced**: Advanced assignments  
  Rubric/checklist meta, upload MIME allow-lists, course builder fields, classic assignment meta box, learn-shell UX hooks, global addon settings. ( DONE )

17- **quiz_advanced**: Advanced quiz types  
  Question banks taxonomy + pool tags, random draw with global cap, shuffle / one-per-page, course builder opt-out, learner pool notice, REST pool preview + bank terms, classic meta boxes under the addon, gated Content Library hub tab, block editor fields aligned to settings cap. ( DONE )

18- **live_classes**: Live classes (Zoom / Meet / Classroom)  
  Lesson meta (URL, provider, schedule, optional title/pass hint/recording), per-course promo opt-out, global settings (defaults, join target, schedule limits, cart/account hints), SQL-backed session queries, calendar bridge, classic + React editors, Integrations hub workspace, themed learner UI (lesson, course, learn, catalog, cart/checkout, account). ( DONE )

19- **social_login**: Social login  
  Allows sign-in with Google/social providers instead of only email/password. ( PROMPT ADDED )

20- **scorm_h5p_pro**: SCORM / H5P (Pro tier)  
  Attach SCORM/H5P interactive packages inside Sikshya lessons. ( PROMPT ADded )

## Scale

21- **marketplace_multivendor**: Multi-vendor marketplace  
  `MarketplaceCommissionService` + storefront template hooks for vendor-scoped commissions and ownership. ( DONE )

22- **white_label**: White label & branding  
  `WhiteLabelHooks` — admin-facing label/branding adjustments when licensed and enabled. ( DONE )

23- **webhooks**: Webhooks  
  `OutgoingWebhookDispatcher` (shared automation layer): signed outbound POSTs on enroll, orders, lessons, quizzes, assignments, completions, certificates, drip, reviews, etc., when `webhooks` (or legacy `automation_zapier_webhooks`) is on. ( CONTINUE )

24- **zapier**: Zapier  
  Same dispatcher as Webhooks; Scale-tier “Zapier catch hook” style integration without separate transport code. ( DONE )

25- **email_marketing**: Email marketing (Mailchimp / MailerLite)  
  `EmailMarketingDispatcher` — list sync hooks driven by enrollment/completion-style events when add-on is enabled. ( DONE )

26- **public_api_keys**: Public API & API keys  
  REST CRUD for learner/API keys registered in Pro bootstrap (`ScaleApiKeyRoutes`); add-on gate in licensing. ( DONE )

27- **multisite_scale**: Multisite & network license tools  
  `MultisiteLicenseUi` — network admin guidance for license use on multisite. ( DONE )

28- **enterprise_reports**: Enterprise reporting  
  `EnterpriseReportingCron` — scheduled enterprise summary reporting pipeline. ( DONE )

29- **multilingual_enterprise**: Multilingual (WPML / Weglot)  
  `MultilingualHooks` — compatibility hooks for translated content/plugins. ( DONE )

