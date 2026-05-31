# Sikshya audit campaign — regression test plan

Generated 2026-05-27. Covers the 15 patches shipped across the multi-round audit. Each test has: **(R) repro** that triggers the original defect, **(V) verify** the fix works, **(N) negative** that the legitimate flow still works.

Run order doesn't matter — tests are independent. Where a test needs two browser sessions, two distinct user accounts, or two terminals, that's called out.

---

## A. Course Builder — security

### A1. IDOR — instructor cannot edit another instructor's course
**Files**: `src/Api/AdminRestRoutes.php` (`saveCourseBuilder`)

- Setup: create two instructor users I1, I2. I1 owns course `C1`. I2 owns course `C2`.
- **R**: log in as I2. From DevTools / curl, POST to `/wp-json/sikshya/v1/course-builder/save` with `course_id = C1` and an edited title.
- **V**: response `403 forbidden`, body `{"code":"forbidden", "message":"You are not allowed to edit this course."}`. Verify in DB that `C1`'s title is unchanged.
- **N**: I1 saves their own `C1` → success, title updates.

### A2. IDOR — instructor cannot reset another instructor's quiz attempt
**Files**: `src/Api/AdminRestRoutes.php` (`resetAdminQuizAttemptTimer`)

- Setup: I1 owns course `C1` with quiz `Q1`; learner `L1` started an attempt `A1`. I2 owns separate course `C2`.
- **R**: log in as I2. POST to `/wp-json/sikshya/v1/admin/quiz-attempts/<A1.id>/reset-timer`.
- **V**: response `403 forbidden`, message *"You are not allowed to manage attempts on this course."* DB row `A1` unchanged.
- **N**: I1 resets `A1` → success, attempt back to `in_progress`, `started_at = now`.

---

## B. Enrollment + checkout — security & integrity

### B1. Free-enrol can't grant access to a paid course (TOCTOU)
**Files**: `src/Services/CourseService.php` (`enrollUser`), all 6 caller sites including `src/Api/PublicRestRoutes.php`

- Setup: course `C1` with price `$0.00` (free).
- **R-1**: log in as learner. POST `/wp-json/sikshya/v1/me/enroll` `{ course_id: C1 }` → enrolment succeeds (this is the legit free path).
- **R-2**: admin raises `C1` price to `$10` in a separate terminal/tab. Learner POSTs `/me/enroll` again. Expected: `400 invalid_argument`, *"This course requires payment. Please complete checkout."*
- **V** (admin path): admin manually enrols learner into the paid `C1` via `/admin/orders/...` → succeeds because admin endpoint passes `bypass_price_check`.
- **V** (paid path): paid checkout fulfilment succeeds because `OrderFulfillmentService` passes `bypass_price_check`.

### B2. Guest checkout — two parallel orders for same email don't fail
**Files**: `src/Commerce/OrderFulfillmentService.php` (`ensureGuestStudentUser`)

- Setup: two paid courses `C1`, `C2`. No existing user with email `guest+race@example.com`.
- **R**: simulate two concurrent webhook fulfilments for the same email (use two terminals running `wp eval` or two `wp_remote_post` calls). Pre-patch behaviour: one fulfilment succeeds, second fails with `existing_user_email`.
- **V**: both fulfilments succeed. DB has exactly one user with that email and both orders linked to that `user_id`. Both courses enrolled.
- **N**: single-order guest checkout still works — user gets created on first fulfilment.

### B3. Coupon redemption count is atomic
**Files**: `src/Database/Repositories/CouponRepository.php`, `src/Commerce/CheckoutService.php`

- Setup: coupon `RACE10` with `max_uses = 1`, `used_count = 0`.
- **R**: two parallel `/checkout/session` calls both apply `RACE10`. Pre-patch: both succeed, `used_count = 2` in DB.
- **V**: exactly one of the two checkout sessions succeeds. The other gets `RuntimeException` with message *"This coupon has just hit its usage limit. Please remove it and try again."* DB shows `used_count = 1`.
- **N**: a `max_uses = 100` coupon applied 50 times works as expected (each increment by 1).

### B4. Stripe payment intent idempotency
**Files**: `src/Commerce/CheckoutService.php` (`createOrReuseStripePaymentIntent`)

- Setup: paid order `O1` for `$100 USD`, no existing intent.
- **R**: double-click Pay or send two `/checkout/stripe/intent` requests within milliseconds. Pre-patch: two `pi_xxx` intents created on Stripe dashboard.
- **V**: Stripe dashboard shows ONE intent for the order. The `Idempotency-Key` header equals `sikshya-pi-<O1.id>-<minor_cents>-usd` for both requests, so Stripe returns the same intent the second time.
- **N**: if order total changes (coupon applied → minor cents differ), a NEW intent is created (different idempotency key).

---

## C. Learner experience — security & functional

### C1. Quiz time-limit enforced server-side
**Files**: `src/Api/Learner/QuizRoutes.php` (`quizSubmit`)

- Setup: quiz with `_sikshya_quiz_time_limit = 5` (5 minutes), single multiple-choice question.
- **R**: start an attempt. Wait > 6 minutes (real time, or override `attempt.started_at` in DB to 10 minutes ago). Submit with the correct answer in `answers`.
- **V**: response includes `"time_expired": true`, `"passed": false` regardless of correctness. DB `quiz_attempts.time_taken` = server-computed elapsed (not the client's `time_taken` param). DB `status = 'completed'` (not `passed`).
- **N**: submit within the time limit → `time_expired = false`, `passed` reflects actual score.

### C2. Certificate issuance race
**Files**: `src/Services/CertificateIssuanceService.php` (`issueIfEnabled`)

- Setup: enable certificates + auto-issue. Course with one lesson and one quiz that together cross the completion threshold.
- **R**: learner completes the lesson and submits the passing quiz in the same network burst (both REST calls fire within ~50ms of each other). Pre-patch: DB may have two `sikshya_certificates` rows for the same `(user_id, course_id)`.
- **V**: exactly ONE `sikshya_certificates` row per `(user_id, course_id)`. MySQL `GET_LOCK('sikshya_cert_…', 3)` serialises the issuance.
- **N**: complete the same course a second time (after admin reset) → existing active cert is returned, no duplicate row.

---

## D. JWT / mobile auth — security

### D1. Token for deleted user is rejected
**Files**: `src/Api/JwtAuthService.php` (`validateToken`)

- Setup: issue a JWT to learner L1 via `/auth/login`. Then delete L1 via wp-admin.
- **R**: call any `/me/*` endpoint with L1's `Authorization: Bearer <token>` header.
- **V**: response `401`, body includes `jwt_invalid` + *"Token subject no longer exists."*
- **N**: token for an existing user still authenticates.

### D2. Token from a different site is rejected (`iss` validation)
**Files**: `src/Api/JwtAuthService.php`

- Setup: clone the site to a staging URL with the same wp-options table (same JWT secret). Issue a token on staging.
- **R**: present the staging-issued token to production's REST.
- **V**: `401 jwt_invalid` with *"Token issuer mismatch."*
- **N**: production-issued tokens validate on production.

### D3. New tokens are 24h not 7d
**Files**: `src/Api/JwtAuthService.php` (`issueToken`)

- **R**: `POST /auth/login` with valid credentials. Decode the returned JWT (e.g., `jwt.io`). Check `exp - iat`.
- **V**: `exp - iat == 86400` (24h). Filter `sikshya_jwt_default_ttl_seconds` can override for customers needing longer.

### D4. Username enumeration via login error
**Files**: `src/Api/AuthRestRoutes.php`

- **R**: POST `/auth/login` with `{ username: "nonexistent_user", password: "x" }`. Then with `{ username: "<real_user>", password: "wrong_password" }`.
- **V**: both responses are byte-identical — `401`, `{ code: 'invalid_credentials', message: 'Invalid username or password.' }`. No way to differentiate.
- **N** (debug): with `WP_DEBUG=true`, both cases log the underlying error code (`invalid_username`, `incorrect_password`) to `error_log` for admin diagnostics.

---

## E. Admin settings — security

### E1. Settings sanitize_callback now applied
**Files**: `src/Admin/Settings/SettingsManager.php` (`saveTabSettings`, `importSettings`, new `applyFieldSanitization`)

- Setup: General settings tab loaded.
- **R**: POST `/settings/save` with `{ tab: 'general', values: { site_title: "<script>alert(1)</script>Acme" } }`.
- **V**: DB option `_sikshya_site_title` stores `Acme` (script stripped by `sanitize_text_field`). When the title is later echoed in any template, no script tag is present.
- **N**: legitimate values pass through unchanged. Numeric settings round-trip cleanly via `intval`.

### E2. Email header CRLF strip
**Files**: `src/Services/EmailNotificationService.php` (`send`)

- Setup: admin sets `from_name = "Acme\r\nBcc: attacker@evil.com"` via wp-admin (or directly in DB).
- **R**: any plugin email send (e.g., test welcome email).
- **V**: email is sent with `From: Acme <from@site>` — no Bcc header injected. Inspect raw SMTP / mail log.

---

## F. User-meta endpoint disclosure

### F1. Sikshya manager cannot read administrator session tokens
**Files**: `src/Api/UserService.php` (new `formatSafeMeta`)

- Setup: a user M with `manage_sikshya` capability but NOT `manage_options`. An administrator A who has logged in (so `session_tokens` meta exists). A has some private plugin meta `_wfls_2fa_secret` (Wordfence example).
- **R**: log in as M. GET `/wp-json/sikshya/v1/users/<A.id>`.
- **V**: response `meta` field is filtered. No `session_tokens`, no `_wfls_2fa_secret`, no `wp_capabilities`. Only `sikshya_*` keys and explicit allowlist (`description`, `nickname`) present.
- **N**: legitimate `sikshya_*` meta still returned. Customers can extend via `sikshya_user_meta_safe_prefixes` / `sikshya_user_meta_safe_keys` filters.

---

## G. Email template merge — XSS

### G1. Learner display name with HTML can't break email layout
**Files**: `src/Services/EmailTemplateMerge.php` (new `applyHtml`), `src/Services/EmailNotificationService.php`, `src/Api/AdminEmailTemplateRestRoutes.php`

- Setup: learner sets their display name to `<img src=x onerror=alert(1)>Bob`. Trigger any email that references `{{learner_name}}` (e.g. course enrolment confirmation).
- **R**: inspect the rendered HTML email body.
- **V**: the rendered body shows the literal string `<img src=x onerror=alert(1)>Bob` (HTML-escaped to `&lt;img …&gt;Bob`). No `<img>` element exists in the DOM; no tracking pixel fires; no script attempted.
- **N**: legitimate display names (no markup) render normally. URLs with `&` in query strings (`?a=1&b=2`) work — browsers/email clients accept `&amp;` in `href` attributes.
- **Bonus** (admin preview): `/admin/email-templates/preview` shows the same escaped output, matching production send.

---

## H. Pro plugin — marketplace payouts

### H1. Two-admins-mark-paid race for same vendor
**Files**: `src/Addons/MarketplaceMultivendor/Services/WithdrawalService.php` (`markPaid`)

- Setup: vendor V has $100 in available commissions. Two distinct withdrawal requests `W1` and `W2`, both $100, both `approved`.
- **R**: two admins click "Mark paid" on `W1` and `W2` within milliseconds.
- **V**:
  - One withdrawal becomes `PAID` with all commission rows attached.
  - The other returns `WP_Error('commission_race', ..., 409)` with message *"Some commission rows were just attached to another withdrawal for this vendor. Please refresh the queue and try again."*
  - DB shows the second withdrawal's status unchanged (still `approved`).
  - All commission rows are accounted for exactly once.
- **N**: marking paid a single withdrawal with no race succeeds normally.

---

## I. Pro plugin — Gradebook event hook

### I1. `sikshya_assignment_graded` fires after grading
**Files**: `src/Addons/Gradebook/Services/GradebookAssignmentGradeService.php`

- Setup: write a tiny test plugin that hooks the action:
```php
add_action('sikshya_assignment_graded', function ($sub_id, $assign_id, $course_id, $user_id, $grade, $status, $feedback) {
    file_put_contents(WP_CONTENT_DIR . '/sikshya-test.log', json_encode(func_get_args()) . PHP_EOL, FILE_APPEND);
});
```
- **R**: instructor grades a learner's submission via the gradebook UI.
- **V**: log file appended with `[submission_id, assignment_id, course_id, user_id, grade, "graded", feedback]`. All 7 args correctly populated.
- **N**: action does NOT fire when `gradeSubmission()` fails (e.g., on invalid submission id). Verify by passing a bogus submission_id to the REST endpoint.

---

## J. GDPR personal-data exporter + eraser

### J1. Personal data export covers all 10 groups
**Files**: `src/Privacy/PersonalDataExporter.php`, `src/Core/Plugin.php`

- Setup: a learner `L1` with non-trivial data — at least one enrolment, one paid order + payment + coupon redemption, several lesson-completion progress rows, one quiz attempt with answers, one assignment submission with grade + feedback, one issued certificate, one course review with a star rating + text, plus profile meta (phone, location, billing address).
- **R**: wp-admin → Tools → **Export Personal Data** → enter `L1.email` → confirm via the email link → run the export.
- **V**: download the resulting ZIP and open `personal-data.html`. Confirm all ten groups appear:
  - `Sikshya — Profile & preferences`
  - `Sikshya — Course enrolments`
  - `Sikshya — Orders` (with line items + meta snapshot)
  - `Sikshya — Payments`
  - `Sikshya — Coupon redemptions`
  - `Sikshya — Lesson & quiz progress`
  - `Sikshya — Quiz attempts` (with `Answers (raw)` + per-question items)
  - `Sikshya — Assignment submissions` (content + grade + feedback)
  - `Sikshya — Certificates`
  - `Sikshya — Course reviews`
- **N**: same export against a non-existent email → ZIP contains no Sikshya groups (graceful empty).

### J2. Erasure deletes learner-private data + anonymises financial data
**Files**: `src/Privacy/PersonalDataEraser.php`

- Setup: same `L1` from J1, with known row counts in each table (record them before erasure).
- **R**: wp-admin → Tools → **Erase Personal Data** → enter `L1.email` → confirm via the email link → run the erasure.
- **V** — **deleted entirely** (rows gone from DB):
  - `wp_sikshya_enrollments WHERE user_id = L1.id` → 0 rows
  - `wp_sikshya_progress WHERE user_id = L1.id` → 0 rows
  - `wp_sikshya_quiz_attempts WHERE user_id = L1.id` → 0 rows
  - `wp_sikshya_quiz_attempt_items` joined to those attempts → 0 rows (cascade)
  - `wp_sikshya_assignment_submissions WHERE user_id = L1.id` → 0 rows + uploaded files in `wp-content/uploads/...` deleted via `wp_delete_attachment($id, true)`
  - User meta keys `sikshya_user_*`, `_sikshya_billing_*`, `_sikshya_instructor_*`, `_sikshya_learn_notes`, `_sikshya_completed_lessons`, `_sikshya_enrolled_courses`, `sikshya_avatar_attachment_id` → gone
- **V** — **anonymised + retained** (row stays, identifying fields nulled):
  - `wp_sikshya_orders WHERE user_id = 0` → row count = original orders count. `meta` column is `NULL`. Amount + currency + gateway + dates preserved.
  - `wp_sikshya_payments WHERE user_id = 0` → row count = original. `gateway_response` is `NULL`. Amount + transaction_id preserved.
  - `wp_sikshya_coupon_redemptions WHERE user_id = 0` → row count = original. Coupon usage counts unchanged.
  - `wp_sikshya_certificates WHERE user_id = 0` → row count = original. `certificate_data` is `NULL`, `status = 'revoked'`. `verification_code` preserved (third-party verifiers see "revoked" rather than 404).
  - `wp_sikshya_reviews WHERE user_id = 0` → row count = original count of L1-authored reviews. `review_text` is `NULL`. Star `rating` preserved (still contributes to course average).
  - `wp_sikshya_reviews WHERE reply_user_id = 0` → row count = original count of L1-authored replies. `reply_text` + `reply_created_at` are `NULL`.
- **V** — **WP admin shows correct "items retained" messages**: in the privacy tool UI, expect messages explaining:
  - Orders retained for tax / audit
  - Payments retained for refund reconciliation
  - Coupon redemptions retained for usage-count integrity
  - Certificates retained but marked revoked
  - Reviews retained for rating aggregate
- **N**: same erasure flow against a non-existent email → all groups return `done=true, removed=0, retained=0`, no errors.

### J3. Coupon usage cap remains enforced after erasure
**Files**: cross-validation of J2 + the earlier coupon-race patch (B3)

- Setup: coupon `MAX5` with `max_uses = 5`. Learner `L1` has redeemed it twice; `used_count = 2`.
- **R**: erase L1's personal data per J2.
- **V**: `wp_sikshya_coupons.used_count` for `MAX5` is still `2` (NOT decremented — that would be a counter rewrite, not a privacy concern). Two `redemptions` rows now have `user_id = 0` but the coupon is still capped at 5 total.
- **N**: a new learner `L2` redeems `MAX5` → `used_count` increments to 3 normally.

### J4. Certificate verification still works after erasure
**Files**: `src/Api/CertificatesPublicRoutes.php` (public verify endpoint) + J2 retain logic

- Setup: L1 has a `certificate_number = SK-42-7-20260101` with `verification_code` known.
- **R-1**: hit `/wp-json/sikshya/v1/public/certificates/verify?code=<code>` before erasure → returns active cert info with L1's display name.
- **R-2**: erase L1 per J2. Hit the same verify URL.
- **V**: verify endpoint returns the cert row with `status = 'revoked'` and no learner name (since `certificate_data` is null). Third-party verifier sees the cert exists but is no longer valid — preferable to a 404 that suggests the cert was never issued.

---

## K. Login rate limiting

### K1. `/auth/login` blocks brute force after 5 failed attempts
**Files**: `src/Security/LoginRateLimiter.php`, `src/Api/AuthRestRoutes.php`

- Setup: a valid user `learner1` with a known password. Clear any existing bucket transient first (`wp transient delete sikshya_login_fail_<sha1(ip|learner1)>`) or use a fresh username for the test.
- **R**: POST `/wp-json/sikshya/v1/auth/login` 5 times with `{ "username": "learner1", "password": "WRONG" }` from the same IP. Each of the 5 responses → `401 invalid_credentials`.
- **V**: 6th attempt → `429 rate_limited`, body `{ "code": "rate_limited", "message": "Too many failed attempts. Please try again later." }`.
- **V** even with **correct** password: 7th attempt with right password → still `429`. Lockout persists for the configured window (default 900s).
- **N (cooldown)**: wait the lockout window or manually clear the transient. Next attempt with correct credentials → `200`, JWT returned; bucket cleared on success.

### K2. `/auth/web-login` shares the same protection AND error normalisation
**Files**: same

- **R**: 5 failures against `/auth/web-login`.
- **V**: same `429` on attempt 6.
- **N (enumeration defense)**: `wp_signon` previously surfaced different messages for unknown-user vs wrong-password. Now both → `{ code: "invalid_credentials", message: "Invalid email or password." }`. Diffing the response body bytes for `{username: "nonexistent"}` vs `{username: "real_user", password: "wrong"}` shows them byte-identical.

### K3. Per-(IP, username) bucket isolation
**Files**: same

- Setup: two distinct users `learnerA` and `learnerB`. Attacker drains `learnerA`'s bucket (5 wrong-password POSTs).
- **R**: POST correct credentials as `learnerB` from the *same IP*.
- **V**: `learnerB` logs in normally → `200` + JWT. Different bucket key, so a targeted attack on one account can't lock out the entire site.

### K4. Tunable via filters
**Files**: `src/Security/LoginRateLimiter.php`

```php
add_filter('sikshya_login_max_failed_attempts', fn () => 3);
add_filter('sikshya_login_lockout_seconds', fn () => 60);
```
- **V**: rate limit now triggers on the 4th failure (not the 6th). Bucket TTL is 60 seconds instead of 900.

### K5. Successful login wipes the bucket
**Files**: same

- **R**: 3 wrong-password attempts as `learner1`, then 1 correct-password attempt.
- **V**: correct attempt → `200`. Transient is now gone — confirm via `wp transient get sikshya_login_fail_<sha1(ip|learner1)>` returns nothing. The next 5 wrong attempts can be made cleanly before the limiter triggers again.

### K6. IP source is `REMOTE_ADDR` only, not `X-Forwarded-For`
**Files**: `LoginRateLimiter::clientIp()`

- Setup: test against a direct (non-proxied) host.
- **R**: 5 failed login attempts with header `X-Forwarded-For: 1.2.3.4`. Then a 6th with `X-Forwarded-For: 5.6.7.8`.
- **V**: 6th request → `429`. The limiter ignored the forwarded header (attacker-controllable on a non-proxied host). Sites genuinely behind Cloudflare / nginx etc. should resolve real client IP at the web-server layer so `REMOTE_ADDR` is already correct in PHP.

---

## L. JWT token revocation

### L1. `/auth/logout` invalidates outstanding tokens
**Files**: `src/Api/JwtAuthService.php`, `src/Api/AuthRestRoutes.php`

- Setup: learner `L1` calls `/auth/login` with valid credentials → receives JWT `T1`. Use `T1` on `/wp-json/sikshya/v1/me/progress` → `200`.
- **R**: POST `/wp-json/sikshya/v1/auth/logout` with header `Authorization: Bearer T1` → response `{ "success": true }`.
- **V**: subsequent request to `/me/progress` with the SAME `T1` → `401`, body includes `jwt_invalid` + `"Token has been revoked. Please log in again."` Behind the scenes: `sikshya_jwt_token_version` user-meta bumped from 0 → 1; T1's `tv` claim (0) no longer matches.
- **N**: a fresh `/auth/login` after logout → new JWT `T2` with `tv = 1`, authenticates normally.

### L2. Password reset auto-revokes existing tokens
**Files**: `src/Api/JwtAuthService.php` (`registerRevocationHooks`)

- Setup: L1 issues JWT `T1`, confirms it works on `/me/progress`.
- **R**: trigger a password reset for L1 (admin → users → send password reset, OR run `wp_password_change_notification()`). Complete the reset by submitting the form / using `wp_set_password()`.
- **V**: `T1` is now invalid on every `/me/*` endpoint — `401 jwt_invalid` with revocation message. Even though the attacker who stole `T1` doesn't know the new password, they also no longer have a working session token.
- **N**: profile edits that don't change the password (e.g., updating display name via `wp_update_user`) do NOT bump the version. `T1` still works after a name change.

### L3. `/auth/logout` works for cookie-session users (no JWT)
**Files**: `src/Api/AuthRestRoutes.php` (`logoutPermission`)

- Setup: learner logged in via `/auth/web-login` → cookie session active.
- **R**: POST `/auth/logout` with `X-WP-Nonce: <wp_rest_nonce>` (no Bearer header).
- **V**: response `{ "success": true }`. `wp_logout()` was called server-side; cookie cleared. Cookie-session JWT meta bumped, so any JWT they happen to also have outstanding is also revoked.
- **Negative**: request without nonce AND without Bearer → `401 sikshya_forbidden`.

### L4. Per-user isolation
**Files**: `src/Api/JwtAuthService.php`

- Setup: two learners L1 and L2 both have valid JWTs (T1, T2).
- **R**: L1 logs out via `/auth/logout`.
- **V**: T1 invalid (per L1). T2 still valid — different user, different `tv` meta key. L2's session unaffected.

### L5. Programmatic revocation API
**Files**: `src/Api/JwtAuthService.php`

- Setup: in a mu-plugin or admin tool, call:
  ```php
  \Sikshya\Api\JwtAuthService::revokeAllTokensForUser($user_id);
  ```
- **V**: returns the new token-version integer (e.g., 2 if previous was 1). Every outstanding JWT for that user fails the next `validateToken` call. Customers can call this from an admin "force log out user" UI without needing a REST round-trip.

---

## M. Signed attachment download proxy

### M1. Signed URL replaces raw uploads URL on lesson page
**Files**: `src/Security/AttachmentTokenService.php`, `templates/single-lesson.php`, `templates/single-quiz.php`

- Setup: lesson `L1` in course `C1` has one downloadable resource (`attachment_id = 42`). Learner `learner1` is enrolled in `C1`.
- **R**: log in as `learner1`, visit the lesson page, view-source the resources section.
- **V**: the resource `<a href>` is `https://site.test/wp-json/sikshya/v1/file/<base64url>.<hex_sig>` — NOT the raw `wp-content/uploads/2026/05/file.pdf` URL.
- **N (opt-out)**: register `add_filter('sikshya_protect_attachments', '__return_false');` in a test mu-plugin. Reload the lesson. Now the resource link is the raw uploads URL — the filter respects the customer's choice.

### M2. Signed URL works for the issuing user
**Files**: `src/Api/AttachmentProxyRoutes.php`

- **R**: as `learner1`, click the download link from M1 (or curl it with the learner's session cookie).
- **V**: HTTP `200`. Response headers include `Content-Type` matching the file's actual MIME, `Content-Disposition: attachment; filename="…"`, `X-Content-Type-Options: nosniff`. Body is the file bytes.

### M3. Sharing the URL with another account → 403
**Files**: `src/Api/AttachmentProxyRoutes.php` (identity binding)

- Setup: `learner1` sends `learner2` the proxy URL (e.g., via chat / a shared bookmark).
- **R**: `learner2` opens the URL in their own logged-in session.
- **V**: HTTP `403`, body `"This download link was issued to a different account."` `current_user_id()` is `learner2`, the token's `uid` is `learner1` — mismatch caught.
- **N**: `learner2` requests their own signed URL for the same attachment (assuming they're also enrolled). That URL works for them.

### M4. Unauthenticated request → 401
**Files**: same

- **R**: open the proxy URL in an incognito window (no session cookie, no Bearer).
- **V**: HTTP `401`, body `"Authentication required to download this file."`

### M5. Token expiry
**Files**: `AttachmentTokenService` (1h default TTL)

- Setup: minimum-effort version is to set `add_filter('sikshya_attachment_token_ttl_seconds', fn () => 5);` so the token expires in 5 seconds.
- **R**: load the lesson page (token minted, exp = now+5s). Wait 10 seconds. Click the link.
- **V**: HTTP `410 Gone`, body `"Download link has expired."` Reload the lesson page → new URL with fresh `exp` → works again.

### M6. Tampered token → 403
**Files**: `AttachmentTokenService::verify`

- **R**: take a valid signed URL, change one character in the base64-encoded payload portion (e.g., change `att=42` to `att=43` after re-encoding) WITHOUT regenerating the HMAC, paste into the browser.
- **V**: HTTP `403`, body `"Invalid download token signature."` The HMAC over the modified payload no longer matches the token's signature, and `hash_equals` is constant-time so attackers can't time-bias their way to a valid sig.

### M7. JWT-authenticated mobile client can also download
**Files**: `AttachmentProxyRoutes::stream`

- **R**: mobile client receives the signed URL (it can either fetch the lesson API and parse the link, or use a separate `/me/lessons/<id>/resources` endpoint if one exists). Mobile makes a GET to the URL with `Authorization: Bearer <jwt>` and no cookie.
- **V**: HTTP `200`, file streams. Auth path: `get_current_user_id()` is 0, falls through to `JwtAuthService::bearerFromRequest()` + `validateToken()`, resolves the JWT's `sub` → matches token's `uid` → stream.

### M8. Attachment deleted out from under a live URL
**Files**: same

- Setup: a valid signed URL for attachment `42`. Admin deletes attachment `42` from the media library.
- **R**: click the still-live URL.
- **V**: HTTP `404`, body `"Attachment not found."` `get_attached_file($id)` returns false / nonexistent path; we don't try to stream a deleted file.

---

## N. Refund handlers — Stripe `charge.refunded`

### N1. Full refund unenrols + flips order status
**Files**: `src/Commerce/OrderRefundService.php`, `src/Api/WebhooksRestRoutes.php`, `src/Services/CourseService.php` (`forceUnenrollForRefund`), `src/Database/Repositories/PaymentRepository.php` (`markRefundedByOrder`)

- Setup: paid course `C1` ($50). Learner `L1` buys it via Stripe → fulfilment runs → `wp_sikshya_orders.status = 'paid'`, `wp_sikshya_enrollments` row exists for (L1, C1), `wp_sikshya_payments` row has `status = 'completed'` and `transaction_id = pi_...` (the Stripe PaymentIntent ID).
- **R**: refund the charge in the Stripe Dashboard. Stripe sends a `charge.refunded` webhook to `/wp-json/sikshya/v1/webhooks/stripe`.
- **V**:
  - `wp_sikshya_orders WHERE id = <order_id>` → `status = 'refunded'`.
  - `wp_sikshya_payments WHERE transaction_id = '<pi_id>'` → `status = 'refunded'`.
  - `wp_sikshya_enrollments WHERE user_id = L1.id AND course_id = C1` → row gone.
  - `wp_sikshya_progress WHERE user_id = L1.id AND course_id = C1` → rows gone.
  - Course's enrollment-count meta decremented by 1.
- **V (idempotency)**: re-deliver the same webhook event manually (Stripe Dashboard → resend) → endpoint returns `200`, order still `refunded`, no double-deletion errors. The `findByIdForUpdate` row lock plus the `if ($current_status === 'refunded') return true;` short-circuit handle this.

### N2. Refund-driven unenrolment fires the right action with the right reason
**Files**: `src/Services/CourseService.php::forceUnenrollForRefund`

- Setup: register a test listener:
  ```php
  add_action('sikshya_user_unenrolled', function ($uid, $cid, $reason = '', $oid = 0) {
      file_put_contents(WP_CONTENT_DIR . '/refund-unenrol.log', json_encode(compact('uid', 'cid', 'reason', 'oid')) . PHP_EOL, FILE_APPEND);
  }, 10, 4);
  ```
- **R**: trigger a Stripe refund per N1.
- **V**: log line `{ "uid": <L1.id>, "cid": <C1.id>, "reason": "refund", "oid": <order_id> }`. Listeners can distinguish refund-driven removals from self-service drops (which fire `reason = ''`).

### N3. `sikshya_order_refunded` action exposes everything Pro needs for revenue rollback
**Files**: `src/Commerce/OrderRefundService.php`

- Setup:
  ```php
  add_action('sikshya_order_refunded', function ($order_id, $user_id, $courses, $reason, $amount, $order) {
      file_put_contents(WP_CONTENT_DIR . '/refund-order.log', json_encode(compact('order_id', 'user_id', 'courses', 'reason', 'amount')) . PHP_EOL, FILE_APPEND);
  }, 10, 6);
  ```
- **R**: refund via N1.
- **V**: log line includes `order_id`, `user_id`, `courses: [C1.id]`, `reason: "stripe_charge_refunded"`, `amount: 50.0`. Pro multi-instructor's `OrderRevenueShareService` can hook this to flip per-instructor commission rows from `pending`/`available` → `refunded` and adjust outstanding withdrawal balances.

### N4. Partial refund: order stays paid, listener still gets notified
**Files**: `src/Api/WebhooksRestRoutes.php` (`sikshya_order_partial_refund` action)

- Setup: paid order for $50. Stripe Dashboard issues a $5 partial refund.
- **R**: `charge.refunded` webhook arrives with `amount = 5000, amount_refunded = 500, refunded = false`.
- **V**:
  - `wp_sikshya_orders.status` is **still `paid`** — partial refunds don't unenrol the learner from a $50 course because they got $5 back.
  - `sikshya_order_partial_refund` action fires with `(order_id, 5.0, "stripe_charge_refunded", $charge_obj)` so Pro audit / Slack listeners can record the event.
  - The learner's enrolment is intact.

### N5. Refund of an unknown intent → silent ack
**Files**: same

- Setup: send a synthetic `charge.refunded` webhook with `data.object.payment_intent = "pi_nothing_matches"`.
- **R**: webhook arrives, signature valid.
- **V**: response `200 { "ok": true }`. No DB changes — the order lookup returned null, we logged nothing because the event was authentic but irrelevant (could be a charge from a different system using the same Stripe account).

### N6. Refund of an order that's already `refunded` (idempotent retry)
**Files**: `src/Commerce/OrderRefundService.php` (early-return on status check)

- Setup: per N1, the order is now `refunded`.
- **R**: Stripe resends the same `charge.refunded` webhook (or admin manually triggers from Dashboard).
- **V**: response `200 { "ok": true }`. Status is still `refunded`. Enrolment is still gone. `sikshya_order_refunded` action does NOT fire a second time (only fires when we transition from `paid` → `refunded`, not on idempotent re-entries).

### N7. Refund applies via `payment_intent.canceled` too
**Files**: same

- **R**: trigger a `payment_intent.canceled` event (less common, but Stripe fires this if the merchant cancels an intent that was already captured then refunded in one step).
- **V**: same outcome as N1 (order refunded, enrolments removed), with `sikshya_order_refunded` reason = `"stripe_payment_intent_canceled"`.

---

## O. Refund handlers — PayPal (Advanced + IPN)

### O1. `PAYMENT.CAPTURE.REFUNDED` (PayPal Advanced) — full refund
**Files**: `src/Api/WebhooksRestRoutes.php` (PayPal Advanced branch), `src/Commerce/OrderRefundService.php`

- Setup: paid course `C1` ($50) bought via PayPal Advanced. At checkout, `purchase_units[0].custom_id = <local_order_id>` was set (per `CheckoutService.php:823`). Order is in `paid` status; enrolment exists.
- **R**: refund the full charge in PayPal Dashboard. PayPal sends `PAYMENT.CAPTURE.REFUNDED` to `/wp-json/sikshya/v1/webhooks/paypal`. Signature verifies.
- **V**: same end-state as Stripe full refund (N1):
  - Order status → `refunded`
  - Payment row status → `refunded`
  - Enrolment + progress rows deleted
  - `sikshya_order_refunded` action fires with `reason = "paypal_capture_refunded"`, `amount = 50.0`

### O2. PayPal partial refund (Advanced) → action only, no unenrol
**Files**: same

- Setup: $50 order. Refund $5 via PayPal Dashboard.
- **R**: `PAYMENT.CAPTURE.REFUNDED` arrives with `resource.amount.value = "5.00"`.
- **V**:
  - `sikshya_order_partial_refund` fires with `(order_id, 5.0, "paypal_capture_refunded", $resource)`.
  - Order stays `paid`. Learner keeps access.

### O3. PayPal refund without `custom_id` → silent ack (no false action)
**Files**: same

- Setup: a PayPal capture from BEFORE `custom_id` started being set (legacy data). `resource.custom_id` is empty/missing in the refund payload.
- **R**: webhook arrives.
- **V**: response `200`, no refund processed. We can't safely identify the local order without `custom_id`; failing silently is preferable to nuking a random `paid` order on a fragile match.

### O4. PayPal IPN refund — `payment_status = "Refunded"`
**Files**: `src/Api/WebhooksRestRoutes.php` (PayPal IPN branch)

- Setup: paid course bought via PayPal Standard (IPN mode). IPN `custom` field carries the JSON `{ "order_id": <local_id> }`.
- **R**: merchant refunds via PayPal Dashboard → IPN POST arrives at `/webhooks/paypal-ipn` with `payment_status = "Refunded"`, `mc_gross = -50.00`, `custom = {"order_id":<local_id>}`.
- **V**: IPN round-trip-verify passes → handler reads `order_id` from `custom` → `refund_amount = abs(-50.00) = 50.0` → full refund detected → `refundFullOrder` runs → end state same as O1 with `reason = "paypal_ipn_refunded"`.

### O5. PayPal IPN chargeback / reversal — `payment_status = "Reversed"`
**Files**: same

- Setup: same setup as O4. Buyer files a chargeback; PayPal automatically reverses the charge and sends an IPN with `payment_status = "Reversed"`.
- **R**: IPN arrives.
- **V**: same unenrol + status flip as O4, but `reason = "paypal_ipn_reversed"` so audit logs can distinguish merchant-initiated refunds from buyer disputes.

### O6. PayPal IPN partial refund → action only
**Files**: same

- Setup: $50 order. Merchant refunds $5 via PayPal Dashboard (Simple/IPN mode).
- **R**: IPN arrives with `payment_status = "Refunded"`, `mc_gross = -5.00`.
- **V**: same as O2 — `sikshya_order_partial_refund` fires, order stays `paid`.

### O7. Idempotency across all PayPal paths
**Files**: same

- Setup: a successful PayPal refund per O1 already ran.
- **R**: re-deliver the webhook (PayPal Dashboard → resend) OR force IPN re-fire.
- **V**: response `200`, no double-deletion, `sikshya_order_refunded` action does NOT fire a second time. The `OrderRefundService::refundFullOrder()` early-return on `status === 'refunded'` handles this (see N6).

---

## P. Pro multi-instructor + marketplace revenue rollback on refund

### P1. Multi-instructor revenue shares flip to `refunded` on order refund
**Files**: `sikshya-pro/src/Addons/MultiInstructor/Services/OrderRevenueShareService.php` (`onOrderRefunded`), `sikshya-pro/src/Addons/MultiInstructor/Repositories/RevenueSharesRepository.php` (`markRefundedByOrderItemIds`)

- Setup: course `C1` with two instructors (60/40 revenue split). Learner buys for $100. `OrderRevenueShareService::onOrderFulfilled` creates two `revenue_shares` rows: instructor A `$60 pending`, instructor B `$40 pending`. None are `paid` yet.
- **R**: refund the order via Stripe / PayPal — `sikshya_order_refunded` action fires.
- **V**:
  - Both rows now have `status = 'refunded'`.
  - `sikshya_multi_instructor_revenue_refunded` action fires with `(order_id, [order_item_ids], affected_count = 2)`.
- **N**: instructor A's total outstanding income (read via `RevenueSharesRepository::totalAmountForUser`) correctly excludes the refunded row.

### P2. Already-paid revenue shares are NOT clawed back
**Files**: `RevenueSharesRepository::markRefundedByOrderItemIds`

- Setup: same as P1 but instructor A's share row has `status = 'paid'` (because they already received the payout). Instructor B's row is `pending`.
- **R**: refund the order.
- **V**:
  - Instructor B's row → `refunded`.
  - Instructor A's row → **still `paid`** (we don't fake DB consistency that doesn't match the bank reality).
  - `affected_count` returned from `markRefundedByOrderItemIds` is `1`, not `2`. The action listener can surface this as an admin notice if desired.

### P3. Marketplace commission rows: tiered status transition
**Files**: `sikshya-pro/src/Addons/MarketplaceMultivendor/Services/CommissionAccrualService.php` (`onOrderRefunded`), `sikshya-pro/src/Addons/MarketplaceMultivendor/Repositories/CommissionRepository.php` (`markRefundedByOrderItemIds`)

- Setup: marketplace vendor `V1` sells course `C1`. A learner buys for $100 → `CommissionAccrualService::onOrderFulfilled` creates a commission row in `accrued` status with `vendor_net = $80` (after 20% platform fee).
- **R**: refund the order.
- **V**:
  - Commission row's status → `refunded` (was `accrued` → free to flip).
  - `sikshya_marketplace_commissions_refunded` action fires with `(order_id, [order_item_ids], ['refunded' => 1, 'paid_outstanding' => 0])`.
  - Vendor's available withdrawable balance (`listAvailableForVendor`) excludes this row.

### P4. Marketplace commission already paid out → `refunded_after_payout`
**Files**: same

- Setup: vendor `V1` has a commission row in `paid` status (it was attached to a withdrawal that was completed). The vendor already received the money.
- **R**: refund the original order.
- **V**:
  - The commission row's status → `refunded_after_payout` (NOT `refunded`). The DB now flags that this income is owed back; the actual money recovery (debit from next payout, manual transfer) is an admin-side operation.
  - `sikshya_marketplace_commissions_refunded` action fires with counts `['refunded' => 0, 'paid_outstanding' => 1]`. A dashboard widget / admin notice can list these for reconciliation.
- **Notice value**: this is the correct accounting state. Pretending the row is just "refunded" would understate the platform's exposure.

### P5. Idempotent rollback (refund webhook redelivery)
**Files**: both services' `onOrderRefunded`

- Setup: P1 just ran. Rows are `refunded`.
- **R**: Stripe / PayPal redelivers the same `charge.refunded` event. `OrderRefundService::refundFullOrder` short-circuits (already `refunded`) — but does it fire `sikshya_order_refunded` again? **No** — the action only fires after a `paid → refunded` transition. So the rollback listeners run exactly once per order.
- **V**: no double-update; row statuses unchanged; second `sikshya_multi_instructor_revenue_refunded` / `sikshya_marketplace_commissions_refunded` action does NOT fire.

### P6. Addon disabled → listener is a no-op
**Files**: `Addons::isEnabled()` + `TierCapabilities::feature()` guards inside each listener

- Setup: site has the addons installed but the customer has disabled `multi_instructor` in Pro settings. A refund happens.
- **R**: `sikshya_order_refunded` fires.
- **V**: `OrderRevenueShareService::onOrderRefunded` runs but the addon-enabled guard returns early — no DB writes. Same pattern for Marketplace if its `shouldRun()` returns false. Refund still proceeds in core; just no Pro-side rollback.

---

## Q. Course archive N+1 consolidation

### Q1. Logged-in archive: 2 batched queries instead of 3 × N
**Files**: `src/Frontend/Site/UserCourseStateCache.php`, `includes/template-functions.php` (the three helpers), `templates/archive-sik_course.php` (warm-before-loop)

- Setup: a logged-in learner enrolled in some of the courses on the archive page. Archive shows 24 courses.
- **R**: visit `/courses/` with `SAVEQUERIES` enabled (or `Query Monitor` plugin). Inspect the captured queries.
- **V**: exactly TWO custom-table queries fire for enrolment + certificate batched lookups:
  - `SELECT * FROM wp_sikshya_enrollments WHERE user_id = %d AND course_id IN (...)`
  - `SELECT * FROM wp_sikshya_certificates WHERE user_id = %d AND course_id IN (...) AND status = 'active'`
- **N (before)**: the per-card helpers fired up to 3 × 24 = **72 queries** (one for `is_user_enrolled`, one for `is_user_completed`, one for `cert_download_url` per card; `is_user_enrolled` had no static cache so it hit every time).

### Q2. Anonymous visitor: no extra queries
**Files**: same

- **R**: visit `/courses/` while logged out.
- **V**: the warm-before-loop guard (`if ($current_user_id_for_warm > 0)`) prevents any batched query. Card helpers all return `false`/`''` immediately for `user_id <= 0`. **Net additional queries: 0.**

### Q3. Single-course page / admin tool — unchanged behaviour
**Files**: cache's miss-fallback path

- Setup: a single-course page calls `sikshya_is_user_enrolled_in_course($cid)` directly (not via the archive). No warm-before-loop happened.
- **V**: cache miss → falls back to `EnrollmentRepository::findByUserAndCourse()` exactly like the legacy helper. Same single-row query, same result. The cache now serves the answer for any subsequent call to the *other* two helpers for the same `(user, course)` pair (e.g., `is_completed` after `is_enrolled`).

### Q4. Cache survives within a request, not across
**Files**: `UserCourseStateCache::flush()`

- **R**: hit the archive twice (request 1, then request 2). Confirm both requests independently run the batched queries.
- **V**: cache state is per-request only; lives in static class properties that reset between PHP requests. No risk of stale data leaking across users.
- **For PHPUnit**: `UserCourseStateCache::flush()` is exposed so tests can reset between cases.

### Q5. Paginated archive: only second page's courses re-queried
**Files**: `UserCourseStateCache::warm` (per-ID skip-already-cached logic)

- Setup: 50 courses, 24 per page. Page 1 warms IDs 1–24. Then the user navigates to page 2 (IDs 25–50).
- **V**: page 2's `warm()` call only fires queries for IDs 25–50 — the cache's per-ID skip skips re-fetching IDs 1–24 if they happen to overlap (unusual but possible with filters). On a clean second page (no overlap), one query covers exactly the new 26 IDs.

### Q6. Setting "students can download certificates" = off
**Files**: `UserCourseStateCache::certificateDownloadUrl`

- Setup: admin disables the setting in Sikshya general settings.
- **R**: archive renders.
- **V**: the cache's certificate-URL accessor short-circuits to `''` for every card without touching the DB or even consulting the warmed certificate map. Matches the legacy helper's behaviour.

---

## R. Refund handlers — Mollie

### R1. Webhook URL is registered with Mollie at checkout
**Files**: `src/Commerce/CheckoutService.php` (`createMolliePayment`)

- Setup: configure Mollie API key in Sikshya settings.
- **R**: complete a Mollie checkout flow up to the point of payment creation. Inspect the outbound API request body (use a mitm proxy / log).
- **V**: the `POST https://api.mollie.com/v2/payments` body includes `"webhookUrl": "https://<site>/wp-json/sikshya/v1/webhooks/mollie"` and `"metadata": { "order_id": "<local_id>" }`. Without these two fields, refund notifications would never reach the site.

### R2. Full refund via Mollie Dashboard
**Files**: `src/Api/WebhooksRestRoutes.php::mollie`, `src/Commerce/OrderRefundService.php`

- Setup: paid course $50, learner enrolled. Mollie payment is in `paid` state.
- **R**: refund in full from the Mollie Dashboard. Mollie POSTs `id=tr_xxx` to `/wp-json/sikshya/v1/webhooks/mollie`.
- **V**:
  - The handler reads `id`, fetches the payment via `getMolliePayment(tr_xxx)` (server-to-server with the secret key — TLS-protected).
  - Reads `metadata.order_id` → local order found.
  - Currency check passes; `amountRefunded.value = "50.00"`, equals order total → full refund.
  - `refundFullOrder()` runs → order `refunded`, enrolment removed, payment row flipped, `sikshya_order_refunded` fires with `reason = "mollie_payment_refunded"`.
  - Pro listeners (P-series) reverse multi-instructor + marketplace commission rows.

### R3. Partial refund via Mollie → action-only
**Files**: same

- Setup: $50 order. Mollie partial refund of $5.
- **R**: webhook arrives. Fetched payment has `amountRefunded.value = "5.00"`.
- **V**: `is_full = false`; `sikshya_order_partial_refund` fires with `(order_id, 5.0, "mollie_payment_refunded", $payment_object)`. Order stays `paid`. Learner keeps access.

### R4. Mollie webhook with missing payload → 400 at routing layer
**Files**: `WebhooksRestRoutes::hasMolliePayload`

- **R**: send `POST /webhooks/mollie` with empty body OR with the `id` field absent.
- **V**: response `403` (REST permission callback rejects). The handler itself is never invoked. Cheaper than running through verify-on-receipt for obvious junk.

### R5. Mollie webhook for a payment NOT created by Sikshya → silent ack
**Files**: `WebhooksRestRoutes::mollie`

- Setup: a Mollie account shared between Sikshya and another product. A non-Sikshya payment hits the webhook URL (theoretically — Mollie's webhookUrl is per-payment so this shouldn't happen, but defense-in-depth).
- **R**: webhook arrives, `getMolliePayment` returns a payment with no `metadata.order_id`.
- **V**: handler returns `200 { "ok": true }`. No DB changes. Conservative: if we can't identify the local order, we don't risk acting on the wrong one.

### R6. Currency mismatch → 400 (anti-spoof)
**Files**: same

- Setup: a Sikshya order in USD. Webhook arrives for a Mollie payment claiming to refund the same `order_id` but with currency EUR.
- **R**: webhook arrives.
- **V**: handler returns `400 Currency mismatch`. Identical guard pattern to the existing `/checkout/confirm` Mollie verify path so a forged webhook can't reverse an order by guessing IDs.

### R7. Idempotency
**Files**: `OrderRefundService::refundFullOrder` (status === 'refunded' early-return)

- Setup: R2 just ran. Order is `refunded`.
- **R**: Mollie re-delivers the same webhook (their retry policy is up to 24h on 5xx; we returned 200 first time, but admin may force-resend).
- **V**: response `200`, no double-deletion, `sikshya_order_refunded` does NOT fire a second time.

---

## S. Refund handlers — Paystack + Razorpay

### S1. Paystack signature verification rejects unsigned posts
**Files**: `src/Api/WebhooksRestRoutes.php::paystack`

- Setup: Paystack secret key configured.
- **R-1**: POST `/wp-json/sikshya/v1/webhooks/paystack` with an event body but NO `X-Paystack-Signature` header.
- **V-1**: `403` from the permission callback (`hasPaystackSignature` returns false). Handler never runs.
- **R-2**: POST with the header set but a forged signature (random hex).
- **V-2**: `400 Invalid signature`. The `hash_equals` constant-time comparison against `hash_hmac('sha512', $body, $secret)` rejects.

### S2. Paystack `refund.processed` full refund
**Files**: `WebhooksRestRoutes::paystack`, `Commerce/OrderRefundService.php`

- Setup: paid course $50 via Paystack. Order is `paid`, enrolment exists, `transaction.metadata.order_id` was set at checkout per `CheckoutService.php:1293`.
- **R**: refund full in Paystack Dashboard. Paystack sends `refund.processed` with `data.transaction.metadata.order_id = <local_id>`, `data.amount = 5000` (minor units = $50.00).
- **V**: signature verifies → handler resolves order via `transaction.metadata.order_id` → amount equals expected minor → `refundFullOrder($order_id, 'paystack_refund', 50.0)` runs. End state same as N1 / R2.

### S3. Paystack `charge.dispute.create` → reason marked `paystack_dispute`
**Files**: same

- Setup: buyer files a dispute on Paystack. Paystack sends `charge.dispute.create` with the dispute payload.
- **R**: webhook arrives.
- **V**: refund path runs, but `reason = 'paystack_dispute'` so audit logs can distinguish merchant-issued refunds from buyer-initiated disputes.

### S4. Paystack partial refund → action only
**Files**: same

- Setup: $50 order. Partial $10 refund.
- **R**: `refund.processed` arrives with `data.amount = 1000` (minor = $10).
- **V**: `sikshya_order_partial_refund` fires with `(order_id, 10.0, "paystack_refund", $data)`. Order stays `paid`.

### S5. Razorpay signature verification
**Files**: `WebhooksRestRoutes::razorpay`

- Setup: Razorpay webhook secret configured in settings.
- **R-1**: POST `/wp-json/sikshya/v1/webhooks/razorpay` with no `X-Razorpay-Signature` header. **V-1**: `403`.
- **R-2**: POST with forged signature. **V-2**: `400 Invalid signature`. HMAC-SHA256 constant-time check.

### S6. Razorpay `payment.refunded` full refund
**Files**: same

- Setup: paid course bought via Razorpay payment link. At checkout, `notes.order_id` was set per `CheckoutService.php:1359`. Order in `paid`.
- **R**: refund in Razorpay Dashboard. Razorpay sends `payment.refunded` with:
  ```json
  {
    "event": "payment.refunded",
    "payload": {
      "payment": { "entity": { "notes": { "order_id": "<local_id>" }, "amount_refunded": 5000 } }
    }
  }
  ```
- **V**: handler resolves order via `payment.entity.notes.order_id`, reads `amount_refunded = 5000`, compares to expected minor units → full refund → `refundFullOrder` runs. `reason = 'razorpay_refund'`.

### S7. Razorpay `refund.created` carries amount on refund entity
**Files**: same

- Setup: Razorpay sends the more granular `refund.created` event (sometimes fires before `payment.refunded`).
- **R**: webhook arrives with:
  ```json
  {
    "event": "refund.created",
    "payload": {
      "payment": { "entity": { "notes": { "order_id": "<id>" } } },
      "refund": { "entity": { "amount": 5000 } }
    }
  }
  ```
- **V**: handler reads order_id from `payment.entity.notes` AND amount from `refund.entity.amount`. Full refund detected → `refundFullOrder` runs.

### S8. Order ID resolution fallback to `payment_link.entity.notes`
**Files**: same

- Setup: Razorpay account configuration where the refund webhook only carries `payment_link.entity.notes` (no `payment.entity.notes`).
- **R**: webhook with `payload.payment_link.entity.notes.order_id` set, `payload.payment.entity.notes` empty.
- **V**: handler's fallback path reads from `payment_link.entity.notes.order_id`. Refund proceeds normally.

### S9. Idempotency across Paystack + Razorpay
**Files**: `OrderRefundService::refundFullOrder`

- Setup: per S2 or S6, order is `refunded`.
- **R**: re-deliver the same webhook (both gateways retry up to 24h).
- **V**: second delivery → `200` (signature still verifies), `refundFullOrder` short-circuits on already-refunded, `sikshya_order_refunded` does NOT fire again.

### S10. Missing webhook secret → fail closed
**Files**: both handlers

- Setup: `paystack_webhook_secret` AND `paystack_secret_key` both empty (likewise `razorpay_webhook_secret` for Razorpay).
- **R**: webhook arrives with a valid-looking payload.
- **V**: `400 Paystack secret not configured.` / `400 Razorpay webhook secret not configured.` Fail closed rather than processing unauthenticated. Admin sees the error in logs, configures the secret, re-test.

---

## T. Matching-question display shuffle (quiz integrity)

### T1. Right-hand options render in shuffled order
**Files**: `src/Frontend/Site/QuizTemplateData.php` (`buildQuestionViewRowForId`), `templates/partials/quiz-question-fieldset.php`

- Setup: a matching question authored in natural pair order — `left = [A, B, C]`, `right = [1, 2, 3]`, `map = [0, 1, 2]` (A↔1, B↔2, C↔3).
- **R**: render the quiz as a learner. View-source the `<select class="sikshya-matching__select">` dropdowns.
- **V**: the `<option>` elements appear in a randomised order (e.g. `3, 1, 2`), NOT the stored `1, 2, 3`. Reloading the page reshuffles. The naive "the answer is the option at the same row position" cheat no longer works.

### T2. Each option's value is its CANONICAL index (grade-safe)
**Files**: same

- **R**: inspect the `<option>` tags. For the displayed item with text `"3"` (canonical index 2), the option is `<option value="2">3</option>`.
- **V**: option `value` attributes are canonical indices regardless of display order. So a learner picking "the option whose text is 3" submits `value=2` — the same index the grader expects. **Grading is unchanged.**

### T3. Correct matches still score correctly after shuffle
**Files**: `src/Api/Learner/QuizRoutes.php` (`evaluateAnswer` matching — UNCHANGED)

- Setup: T1's question. Learner correctly matches A→1, B→2, C→3 using the shuffled dropdowns.
- **R**: submit the quiz.
- **V**: full marks. The JS `matchingPayload` collects each select's `value` (canonical index) → `map = [0, 1, 2]` → matches the stored `expected_map = [0, 1, 2]`. The shuffle affected only display order, never the submitted index space.

### T4. Wrong matches still score as wrong
**Files**: same

- **R**: learner mismatches (A→2, B→1, C→3) → submits `map = [1, 0, 2]`.
- **V**: scored incorrect — `[1,0,2] !== [0,1,2]`. No false positives introduced by the shuffle.

### T5. Single-option question not shuffled
**Files**: `QuizTemplateData` (`count($right_options) > 1` guard)

- Setup: a (degenerate) matching question with one right option.
- **V**: `shuffle()` is skipped — nothing to randomise. No PHP notice from shuffling a 1-element array.

### T6. Opt-out filter restores fixed order
**Files**: `apply_filters('sikshya_shuffle_matching_options', true, $qid)`

- Setup: `add_filter('sikshya_shuffle_matching_options', '__return_false');` in a test mu-plugin (e.g. for a pedagogically-ordered list).
- **V**: dropdown options render in canonical stored order. Useful for question types where the order itself is instructional.

### T7. Legacy cached payload fallback
**Files**: `quiz-question-fieldset.php` (fallback when `matching_right_options` absent)

- Setup: simulate an older transient / external caller that produced a question payload with `matching_right` but no `matching_right_options`.
- **V**: the template's fallback builds `{index, text}` pairs from the flat `matching_right` array in canonical order. The question still renders and grades correctly (just without the shuffle until the cache refreshes).

---

## U. View-data `extract()` hygiene

### U1. Local variables in render methods can't be overwritten by `$data`
**Files**: `src/Core/View.php` (line 45), `src/Frontend/Controllers/CourseController.php` (lines 247, 260)

- Setup: directly call one of the render methods with a deliberately hostile `$data` array that includes keys colliding with the method's locals:
  ```php
  $view->render('some-template', [
      'template_path' => '/etc/passwd',     // would-be: overwrite the local
      'this'          => 'attacker_object',  // would-be: overwrite $this
      'enrollments'   => $real_data,
  ]);
  ```
- **V (before)**: pure `extract($data)` would silently clobber the function's locals — `$template_path` becomes `/etc/passwd`, but in this code path the `include` already ran so it's mostly harmless TODAY; the risk is FUTURE refactors where `$template_path` is referenced after `extract`.
- **V (after)**: with `EXTR_SKIP`, the `template_path` and `this` keys are silently ignored (existing locals win), and only the safe key `enrollments` flows into the template's scope. Confirm by dumping `get_defined_vars()` from inside the template — only `enrollments` is present.

### U2. Normal callers unaffected
**Files**: same

- Setup: standard render call with non-colliding data:
  ```php
  $view->render('dashboard/my-courses', ['enrollments' => $rows, 'count' => 5]);
  ```
- **V**: both `$enrollments` and `$count` are present in the template scope. `EXTR_SKIP` only skips keys that collide with EXISTING locals — non-colliding keys flow through normally.

### U3. Sweep confirms no other unguarded `extract()` in either plugin
**Files**: full-plugin grep

- **R**: `grep -rn "extract(\$" sikshya/src sikshya-pro/src --include="*.php" | grep -v "EXTR_"`
- **V**: zero matches. All three former call sites now pass `EXTR_SKIP`; no Pro file ever used the unguarded pattern.

---

## V. Admin list tables wired to real data (Quizzes / Lessons / Students)

The four admin list tables under `src/Admin/ListTable/` were originally
shipped as design prototypes — three of them returned a hardcoded array
of fake quizzes/lessons/students for every render, and the
`AbstractListTable::get_status_counts()` default returned `5/2/1/1`
regardless of site state. Filter dropdowns ("Course", "Instructor") used
hardcoded names (`John Smith`, `Sarah Johnson`) instead of querying real
users. Admins on a freshly installed site saw a screen of fake demo
content rather than their own data.

This section wires the three stub tables to real queries, removes the
inflated `get_total_items()` query from CoursesListTable, and resets the
base class default to zeros.

### V1. Quizzes list shows real quizzes from the database
**Files**: `src/Admin/ListTable/QuizzesListTable.php`

- Setup: in a fresh install, create three quizzes via the Course Builder
  ("Quiz One", "Quiz Two", "Quiz Three"), publishing the first two and
  leaving the third as draft. Assign Quiz One to a course.
- Navigate to: WP admin → Sikshya → Quizzes
- **V (before)**: page shows 8 hardcoded rows ("JavaScript Fundamentals
  Quiz", "CSS Layout Techniques", …) with fake instructors "John Smith"
  / "Sarah Johnson". The user's own quizzes are absent.
- **V (after)**: page shows exactly 3 rows — the quizzes the admin just
  created — with real titles, real instructor names, real status
  badges, and the configured time limit. The course-filter dropdown
  lists real published/draft/private courses; the instructor-filter
  dropdown lists real WP users with administrator or instructor role.
  The status tabs at top show real counts via `wp_count_posts(QUIZ)`.
- Implementation notes:
  - `get_items()` runs a `WP_Query` against `PostTypes::QUIZ` and caches
    `found_posts` in a private field so the paginator's `get_total_items()`
    doesn't re-query.
  - Course resolution uses `LessonCourseLink::resolvedCourseIdForQuiz`
    (canonical helper) — quiz→chapter→course traversal is identical to
    Learn/REST callers.
  - Question count reads `_sikshya_quiz_questions` (array of question IDs);
    duration reads `_sikshya_quiz_time_limit` (minutes). The original
    prototype invented meta keys (`_sikshya_quiz_type`,
    `_sikshya_questions_count`) that the plugin doesn't persist.
  - "Type" column resolves the *first* question's `_sikshya_question_type`
    via the question post (quizzes mix question types, so showing one is a
    reasonable summary; explicit "Quiz" fallback when the quiz is empty).

### V2. Lessons list shows real lessons from the database
**Files**: `src/Admin/ListTable/LessonsListTable.php`

- Setup: create two lessons via the Course Builder — one with kind
  `video` and one with kind `text` — assigning both to the same course.
- Navigate to: WP admin → Sikshya → Lessons
- **V (before)**: page shows 12 hardcoded rows with `sikshya_text` /
  `sikshya_video` / `sikshya_quiz` / `sikshya_assignment` "post types"
  (none of which actually exist in WP). Course filter shows hardcoded
  course names.
- **V (after)**: page shows exactly 2 rows — the lessons the admin just
  created — with the right Type badge (Text Lesson / Video Lesson),
  real course title linking back to the Course Builder, real instructor
  name (from `post_author`), real duration from
  `_sikshya_lesson_duration` (or legacy `_sikshya_duration`), and real
  status badge. Course-filter dropdown lists real courses;
  instructor-filter lists real users.
- Implementation notes:
  - `get_items()` runs `WP_Query` against `PostTypes::LESSON` only; the
    lesson **kind** is meta (`_sikshya_lesson_type`), not a post type, so
    the type filter constructs a `meta_query` clause rather than the
    original code's bogus `post_type IN ('sikshya_text', …)` IN-list.
  - Course filter accepts either canonical (`_sikshya_lesson_course`) or
    legacy (`_sikshya_course_id`) meta keys via `relation: OR` — both are
    written by `LessonCourseLink::persistLessonCourseId`.
  - Course resolution per row delegates to
    `LessonCourseLink::resolvedCourseIdForLesson` (memoised per-request).

### V3. Students list shows real WP users with enrollment aggregates
**Files**: `src/Admin/ListTable/StudentsListTable.php`

- Setup: register three subscriber-role users; enrol User A in two
  courses (one enrolled, one completed), enrol User B in one course
  (status `pending`), leave User C unenroled.
- Navigate to: WP admin → Sikshya → Students
- **V (before)**: page shows 8 hardcoded students ("Alice Johnson",
  "Bob Smith", …) with invented email addresses and progress percentages.
- **V (after)**: page shows real WP users. User A → "Courses: 2", status
  badge "Active", progress 50%. User B → "Courses: 1", status badge
  "Pending", progress 0%. User C → "Courses: No courses", status badge
  "Inactive". Course-filter dropdown narrows the user set to learners of
  the selected course (`SELECT DISTINCT user_id FROM sikshya_enrollments
  WHERE course_id = X`).
- Implementation notes:
  - User set comes from `WP_User_Query` with the course filter applied
    as an `include` restriction (subset of users with enrolments in the
    given course). When no user matches, the table short-circuits to
    zero rows before running the user query at all.
  - Enrollment aggregates are computed in **one** `GROUP BY user_id,
    status` query per page render, cached by user id. This is the
    deliberate alternative to the per-cell N+1 lookups that
    `ProgressRepository::getCourseProgress()` would imply.
  - Progress is `completed / total` enrolments (a coarse signal). The
    reports / gradebook addon is the right surface for lesson-level
    rollups — explicitly **not** invoked from this admin list to keep
    the page render bounded.
  - Status filter maps UI labels to enrolment-table status:
    `active → enrolled`, `pending → pending`, `inactive → no restriction
    (computed in row renderer from aggregate counts)`.

### V4. Status filter tabs no longer lie when subclass forgets to override
**Files**: `src/Admin/ListTable/AbstractListTable.php` (line 489)

- Setup: any custom list-table subclass that doesn't override
  `get_status_counts()`. (Today: `StudentsListTable`, which doesn't have
  post-status semantics at all — students are users, not posts.)
- **V (before)**: the subsubsub tab strip shows
  "All (9) | Published (5) | Draft (2) | Pending (1) | Private (1)" no
  matter what — hardcoded demo numbers.
- **V (after)**: the strip shows "All (0) | Published (0) | Draft (0) |
  Pending (0) | Private (0)". On `CoursesListTable` /
  `LessonsListTable` / `QuizzesListTable` the override stays in effect
  and shows real `wp_count_posts()` numbers.

### V5. CoursesListTable pagination count no longer scans every course
**Files**: `src/Admin/ListTable/CoursesListTable.php` (lines 348–386)

- Setup: site with 10,000 courses.
- **V (before)**: `get_total_items()` ran `WP_Query(['posts_per_page' =>
  -1])` and instantiated 10,000 `WP_Post` objects on every admin page
  view just to derive `$query->found_posts`. Slow + memory-heavy.
- **V (after)**: `get_items()` caches `(int) $query->found_posts` in a
  private field; `get_total_items()` returns the cached value. Fallback
  path uses `posts_per_page: 1, fields: 'ids'` — at most one row
  hydrated, no meta loaded. Page load time on a 10k-course store
  drops to the listing-query latency alone (~ms range).

### V6. Sweep confirms no remaining hardcoded list-table data
**Files**: full-plugin grep

- **R**: `grep -rn "For demo purposes\|TODO: Implement actual\|return \$this->getDummyData()" sikshya/src`
- **V**: one match (`CoursesListTable::getDummyData()`), but it is now
  unreachable dead code (no caller in either plugin). It's left in place
  for any external subclass that might still reflect on the method;
  removing it would be a separate cleanup.

---

## W. Course delete cascade

Permanently deleting a course (via admin "Delete permanently",
`wp_delete_post( $id, true )`, or REST `DELETE /wp/v2/sik_course/<id>`)
used to leave the course's children behind: enrolment rows, progress
rows, quiz attempts, certificates, chapters, lessons, quizzes, and
assignments. The rows then surfaced as "course #123 — (no title)" in
reports, broke `count(enrollments)` aggregates, and inflated
usage/billing metrics on the Pro plugin's marketplace ledger.

A new `Sikshya\Services\CourseDeleteCascade` (initialised from
`Plugin::init()`) hooks `before_delete_post` at priority 5 — before WP's
own row delete fires — and:

1. Removes `sikshya_enrollments` rows for the course.
2. Removes `sikshya_progress` rows for the course.
3. Removes `sikshya_quiz_attempts` rows for the course (table has its
   own `course_id` column, so single-key delete).
4. Removes `sikshya_certificates` rows for the course.
5. Walks child posts (chapters → lessons → quizzes → assignments) and
   calls `wp_delete_post( …, true )` so any addon listening on
   `delete_post` for those CPTs runs.
6. Fires `sikshya_course_deleted( $post_id, $post )` so Pro addons
   (multi-instructor revenue share, marketplace commissions, webhooks,
   activity log) have a single named hook to listen on.

Re-entry is guarded by a per-request static set so calling
`wp_delete_post` for the same course twice in one PHP request is a
no-op on the second pass.

### W1. Deleting a course removes all related enrolment rows
**Files**: `src/Services/CourseDeleteCascade.php`, `src/Core/Plugin.php`

- Setup: create course #99 with three enrolled learners. Confirm via
  `SELECT COUNT(*) FROM wp_sikshya_enrollments WHERE course_id = 99;` →
  3.
- Run: WP admin → Courses → trash #99 → Trash → Delete Permanently. Or
  equivalently `wp_delete_post(99, true);`.
- **V (before)**: enrolment rows persist forever, counted by
  `EnrollmentRepository::countAll()` and surfacing in dashboards as
  "course #99" with a blank title.
- **V (after)**: `SELECT COUNT(*) FROM wp_sikshya_enrollments WHERE
  course_id = 99;` → 0.

### W2. Deleting a course removes progress + quiz attempts + certificates
**Files**: same

- Setup: course #99 has 5 lessons (with progress rows), 1 quiz (with 4
  attempt rows across learners), and 2 issued certificates. Pre-delete:
  `progress` = 25 rows, `quiz_attempts` = 4 rows, `certificates` = 2 rows.
- Run: same delete.
- **V (after)**: all three tables return 0 rows for `course_id = 99`.

### W3. Child posts (chapters, lessons, quizzes, assignments) deleted with course
**Files**: same

- Setup: course #99 has 3 chapters, 8 lessons, 2 quizzes, 1 assignment
  (linked via `_sikshya_*_course` meta or chapter-membership).
- Run: same delete.
- **V (after)**: all child posts are gone (`get_post()` returns `null`
  for each child ID); the child posts' own `delete_post` hooks fired
  during the cascade, so any addon's lesson/quiz teardown ran.

### W4. `sikshya_course_deleted` action fires for addon listeners
**Files**: same

- Setup: register a one-off test listener:
  ```php
  add_action('sikshya_course_deleted', function ($id, $post) {
      update_option('cascade_test_seen', $id);
  }, 10, 2);
  ```
- Run: delete course #99 permanently.
- **V (after)**: `get_option('cascade_test_seen')` returns `99`. The
  action is dispatched *after* enrolment/progress/cert rows are gone
  (so listeners observe the cleaned-up state) but *before* WP's own
  `wp_delete_post` finishes removing the course post itself (so
  `$post` snapshot is still valid).

### W5. Cascade is a no-op for non-course post deletions
**Files**: same

- Setup: any non-course post (regular WP post, lesson, quiz, attachment).
- Run: `wp_delete_post( $id, true )`.
- **V**: the cascade listener short-circuits at the
  `post_type !== COURSE` check; no DB queries run, no actions fire,
  no recursion into the cascade. Verified via xdebug breakpoints or by
  asserting the table-exists check is never reached.

### W6. Cascade is idempotent within one request
**Files**: same

- Setup: deliberately call `wp_delete_post(99, true)` twice in the same
  request (the second call would normally be a no-op since WP's row is
  already gone — but the `before_delete_post` hook still fires).
- **V**: the second call hits the static `$visited` guard and returns
  immediately without re-running deletes against tables that no longer
  reference course #99 anyway.

---

## X. Quiz `randomize_questions` setting honoured at render time

Quizzes have a "Randomize question order" toggle saved on
`_sikshya_quiz_randomize_questions` (legacy alias
`sikshya_quiz_randomize_questions`). The React admin shipped the toggle,
the save path persisted it, and `QuizController::getQuizSettings` echoed
it back — but the render path
(`QuizTemplateData::buildQuestionsForQuiz`) read the canonical
`_sikshya_quiz_questions` array and walked it in storage order on every
render, ignoring the toggle entirely.

End result: customers turned the toggle on, observed identical question
ordering across attempts, and concluded the feature was broken.

The fix shuffles question IDs on render when the toggle is enabled. The
shuffle is *display-only* — each question's canonical ID is unchanged,
so the grader (`QuizRoutes::quizSubmit`) resolves the correct answer by
ID and is unaffected by display order. This is the same grade-safe
property we relied on for the matching-question right-column shuffle
(section T).

### X1. Toggle ON → question order varies between attempts
**Files**: `src/Frontend/Site/QuizTemplateData.php` (lines 504–586)

- Setup: quiz with 6 questions in order [Q1, Q2, Q3, Q4, Q5, Q6]. Set
  `_sikshya_quiz_randomize_questions = 1`.
- Run: load the quiz page three times in three browser sessions.
- **V (before)**: same order [Q1, Q2, Q3, Q4, Q5, Q6] in all three
  loads.
- **V (after)**: at least two of the three loads produce a permutation
  other than [Q1, Q2, Q3, Q4, Q5, Q6]. (Theoretical false positive at
  $\frac{1}{6!^2} \approx \frac{1}{518400}$ — ignore.)

### X2. Toggle OFF → question order matches storage
**Files**: same

- Setup: same quiz, `_sikshya_quiz_randomize_questions = 0` (or unset).
- Run: load the quiz page.
- **V**: order is exactly [Q1, Q2, Q3, Q4, Q5, Q6], same as
  `get_post_meta($quiz_id, '_sikshya_quiz_questions', true)`.

### X3. Grading is unaffected by display shuffle
**Files**: same

- Setup: toggle ON, quiz with two questions where Q1's correct answer is
  "B" (index 1) and Q2's correct answer is "A" (index 0).
- Run: load the quiz (order may shuffle to [Q2, Q1]). Submit "A" for Q2
  and "B" for Q1 — i.e. answer each question correctly regardless of
  position.
- **V**: server scores 2/2 correct. The grader keys answers by question
  ID (not display position), so the shuffle is invisible to scoring.

### X4. Legacy unprefixed meta key still works
**Files**: same

- Setup: legacy sample-data import wrote `sikshya_quiz_randomize_questions =
  1` (no underscore prefix) but not the canonical key.
- Run: load the quiz.
- **V**: shuffle is enabled. The resolver falls back to the unprefixed
  key when the prefixed key is empty/null.

### X5. Shuffle is a no-op when there's < 2 questions
**Files**: same

- Setup: quiz with one question, toggle ON.
- **V**: no exception. The shuffle call is guarded by `count($ids) > 1`.

---

## Y. Dead frontend enqueue calls removed

`Frontend::enqueuePageSpecificAssets` called
`wp_enqueue_script('sikshya-course-viewer')`,
`wp_enqueue_script('sikshya-lesson-viewer')`,
`wp_enqueue_script('sikshya-dashboard')`, and
`wp_enqueue_script('sikshya-course-catalog')` (plus matching style
handles) on the corresponding singular / page templates. None of these
handles is registered anywhere in the plugin, and the underlying
JS/CSS files don't exist on disk. WP responded with
`_doing_it_wrong()` notices on every singular course or lesson view in
production logs.

These are remnants of an earlier per-page-bundle architecture that was
superseded when `sikshya-frontend` (a single Vite-built bundle) took
over server-rendered learner pages. The enqueue calls were never
deleted along with the registration calls; removing them removes the
noise without affecting any working code path.

### Y1. Loading a single course page produces no doing-it-wrong notice
**Files**: `src/Frontend/Frontend.php` (lines 430–465 in previous form)

- Setup: dev install with `WP_DEBUG = true` and `WP_DEBUG_LOG = true`.
- Run: load `/courses/some-course/` (singular course view).
- **V (before)**: `wp-content/debug.log` gains lines like
  `PHP Notice: wp_enqueue_script() was called incorrectly. Scripts and
  styles should not be registered or enqueued until the wp_enqueue_scripts,
  …  The script handle "sikshya-course-viewer" has not been registered.`
- **V (after)**: no such lines after the page render. The
  `sikshya-frontend` bundle still loads (server-rendered templates
  unchanged), `sikshya-course-reviews` still enqueues on single course
  pages (registered, real bundle).

### Y2. Single-lesson page is clean
**Files**: same

- Run: load any single lesson view.
- **V**: no `doing_it_wrong` notice; the page renders identically to
  before (template was server-rendered, no client-side bundle missing).

### Y3. Sweep confirms no leftover enqueues for unregistered handles
**Files**: full plugin

- **R**: `grep -rn "wp_enqueue_(script|style).*sikshya-(course-viewer|lesson-viewer|dashboard|course-catalog)" sikshya/src/`
- **V**: zero matches.

---

## Z. UI/UX polish: brand consistency + WCAG basics

Two parallel UI/UX deep-dives looked at the learner templates, the
global design system, and the admin React SPA. Most reported findings
were design suggestions or false positives once verified against the
actual schema — the admin SPA in particular is already 8/10 mature
(skeleton states, error panels, focus-visible rings, modal escape
handling, optimistic UI on save buttons all present). The real,
concrete defects collected here:

### Z1. `--sikshya-info` design token aligned to brand navy
**Files**: `assets/css/public-design-system.css` (line ~28)

- **Before**: `--sikshya-info: #3b82f6;` — Tailwind blue-500, an
  off-brand colour. Today no CSS consumes the token, but any future
  use of `var(--sikshya-info)` would render the wrong blue.
- **After**: `--sikshya-info: #2c5ba8;` — aliased to the brand primary
  navy. The set {`success`, `warning`, `error`, `info`} stays symmetric
  in the design tokens; future consumers stay on-brand by default.
- **V**: open the file at line ~28 and confirm the new value. Run
  `grep -rn "var(--sikshya-info)" assets/css/` — still zero consumers
  (token is defined but unused), as expected.

### Z2. Course-archive filter controls now labelled for screen readers
**Files**: `templates/courses-grid.php` (lines ~52–95)

- **Before**: the search input and the two filter `<select>`s
  (category + difficulty) had no `<label>` element and no `aria-label`.
  A screen-reader user landing on `/courses/` heard "edit text" / "combo
  box" with no semantic context.
- **After**: each control has a visually-hidden `<label class="sikshya-screen-reader-text" for="…">`
  and an explicit `id`. The two selects additionally carry `aria-label`
  for redundancy. The text input switched from `type="text"` to
  `type="search"` so iOS shows a "search" key on the keyboard.
- **V (with screen reader)**: NVDA on Windows / VoiceOver on macOS
  announces "Search courses, edit", "Filter by category, combo box",
  and "Filter by difficulty, combo box" when each control gains focus.
- **V (visual)**: nothing visible changed; `.sikshya-screen-reader-text`
  uses the standard SR-only clip pattern (preserves layout, hides from
  sighted users).

### Z3. Invoice action buttons no longer abuse `<a href="#">`
**Files**: `templates/order-invoice.php` (line ~252)

- **Before**: Print and Download PDF were rendered as `<a class="btn"
  href="#" onclick="window.print()">…</a>`. Clicking either jumped the
  page to the top (default `#` anchor behaviour), screen readers read
  them as "link" (wrong verb — they're in-page actions, not
  navigations), and keyboard users who used `Enter` got a confusing
  scroll jump before the action fired.
- **After**: both are real `<button type="button">` elements with an
  `id`; the script block (which already existed for the PDF flow)
  picks the print button up by id and binds `window.print()` via
  `addEventListener`. "My account" stays an `<a>` because it is
  genuinely a navigation.
- **V**: click Print → browser print dialog opens, no page-jump.
  Click Download PDF → same flow as before, no regression.
- **V (screen reader)**: NVDA announces "Print, button" / "Download PDF,
  button" (correct verbs for in-page actions).

### Z4. Global reduced-motion carve-out for learner pages
**Files**: `assets/css/public-design-system.css` (appended block at end)

- **Before**: 67 `transition` / `animation` declarations scattered
  across `learn.css`, `frontend.css`, `cart.css`, `checkout.css`,
  `single-course.css`, etc. Only 3 of them had a
  `@media (prefers-reduced-motion: reduce)` exception. A user with the
  OS-level "reduce motion" preference saw the same hover slides,
  smooth scrolls, and CTA bounces as everyone else — a known WCAG 2.1
  Success Criterion 2.3.3 issue for vestibular-disorder users.
- **After**: a single blanket carve-out at the end of
  `public-design-system.css` collapses every animation/transition
  inside `.sikshya-public` to ~0ms when reduced-motion is on:
  ```css
  @media (prefers-reduced-motion: reduce) {
    .sikshya-public,
    .sikshya-public *,
    .sikshya-public *::before,
    .sikshya-public *::after {
      animation-duration: 0.01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.01ms !important;
      scroll-behavior: auto !important;
    }
  }
  ```
  `!important` is required because page-specific files re-declare
  `transition` inside `:hover` states, and those would otherwise
  re-introduce motion at higher specificity.
- **V**: macOS → System Settings → Accessibility → Display → Reduce
  Motion ON. Hover over a course card on `/courses/` — no transition
  animation; the styled state appears instantly. Confirm the focus
  ring still renders (it's a `box-shadow`, not motion).
- **V (Lighthouse)**: a11y audit for "Background and foreground colors
  have a sufficient contrast ratio" and animation-related items pass.

### Z5. Findings explicitly verified as NOT bugs (false positives)

To keep the next maintainer from re-chasing closed leads:

1. **"Hardcoded colors in `InlineNotices.tsx`"** — the hardcoded hexes
   are `#2c5ba8` (brand navy, correct), `#ff9500` (marketing orange),
   and `#2271b1` (WordPress admin blue, deliberate native styling).
   No off-brand value. The styles are intentional, not a token bypass.
2. **"Settings form missing `aria-invalid` + `aria-describedby`"** —
   the settings schema has no per-field error state today (save is
   whole-form, errors surface via toast). Adding ARIA error wiring
   would require server-side per-field validation first; cosmetic
   theatre otherwise.
3. **"Settings form missing required-field indicators"** —
   `SettingsField.required` does not exist in the type
   (`client/src/types/settingsSchema.ts`). Fields are either present
   with defaults or absent. Adding asterisks would imply an enforcement
   that doesn't exist.
4. **"`outline: none` without focus replacement"** — all occurrences
   are paired with a `box-shadow` focus ring on the same selector. The
   browser default outline is suppressed in favour of a custom brand
   ring; focus visibility is preserved.
5. **"Missing nonces on legacy AJAX controllers"** — the dispatcher
   they hang off (`Frontend::handleAjaxRequest`) is never wired to a
   `wp_ajax_*` action. Dead code path; methods unreachable.
6. **"Modal focus restoration on close" in admin SPA** — verified
   `Modal.tsx` already stores the trigger ref and restores focus in
   the `useEffect` cleanup. Auditor missed it.

### Z6. Off-brand Tailwind utility classes replaced with `brand-*` / `accent-*` tokens
**Files**:
- `client/src/components/shared/PrerequisiteLockDetailPopover.tsx` (line 230)
- `client/src/pages/CoursesPage.tsx` (line 112)
- `client/src/pages/AddonSettingsPage.tsx` (lines 142, 289, 325, 332)
- `client/src/pages/EmailMarketingPage.tsx` (lines 350, 504, 558)
- `client/src/pages/ContentDripPage.tsx` (line 872)
- `client/src/pages/PrerequisitesPage.tsx` (line 1002)
- `client/src/pages/DashboardPage.tsx` (line 206 + CTA at 219)
- `client/src/pages/SettingsPage.tsx` (lines 188, 506, 516, 525)
- `client/src/components/EnrollmentSettingsTab.tsx` (lines 50, 59, 69, 78)
- `client/src/pages/settingsRenderField.tsx` (line 109, 112)

The Tailwind config at `tailwind.config.js` exposes a `brand-*` palette
bound to the navy `--sikshya-brand-*-rgb` CSS variables and an
`accent-*` palette bound to the purple `--sikshya-accent-*-rgb`
variables. A code-level comment in that config explicitly forbids
`indigo-*` ("use `bg-accent-600` etc for Pro/upgrade surfaces, never
`bg-indigo-*` (Tailwind default indigo doesn't match the logo)").

Several React components and pages had drifted to `indigo-*` /
`violet-*` utility classes — likely from an older scaffolded
component that pre-dated the brand palette. The fixes:

- **General info callouts** (`bg-indigo-50/60`, `border-indigo-100`,
  `text-indigo-900`) → `bg-brand-50/60`, `border-brand-100`,
  `text-brand-900` (5 panels across ContentDrip, Prerequisites,
  AddonSettings, EmailMarketing).
- **Pro / upgrade surfaces** (`bg-violet-100`, `text-violet-700`,
  Pro-locked indicators, dashboard upgrade banner) → `bg-accent-*`
  variants matching the team's own guidance.
- **Checkbox accents** (`text-indigo-600 focus:ring-indigo-500`) →
  `text-brand-600 focus:ring-brand-500`.

### Z7. Intentional non-brand Tailwind uses (do **not** convert)

To preempt the next sweep flagging these:

- `client/src/components/shared/buttons.tsx:6` — explicit indigo
  fallback ahead of `bg-brand-600` in the class list. Comment in the
  file explains: "some builds/themes may not ship the custom `brand-*`
  Tailwind palette. Provide an indigo fallback so primary CTAs never
  become white text on white background." Cascade order (`bg-indigo-600
  … bg-brand-600 …`) means brand wins when available.
- `client/src/components/shared/MultiCoursePicker.tsx:291` — same
  defensive pattern, same in-code comment.
- `client/src/components/shared/list/StatusBadge.tsx:13` — "private"
  post status uses violet as a *category color*, distinct from
  "publish" (emerald), "draft" (amber), "pending" (orange). Categorical
  differentiation, not a brand choice.
- `client/src/components/dashboard/MetricTile.tsx:8` — explicit "violet"
  named color option in a multi-color metric-tile system (blue, green,
  violet, amber, etc.). API surface, leave alone.
- `client/src/pages/EmailTemplatesListPage.tsx:26, 280, 296` — template
  category tags (cert→violet, complete→emerald, account→amber, etc.).
  Same categorical pattern as StatusBadge.
- `client/src/components/email/EmailTemplateForms.tsx:208, 294` —
  variable-token highlight in the email editor. Categorical (not the
  brand accent — purposely a different shade so variable tokens are
  visually distinct from Pro upgrade affordances).
- `client/src/pages/DiscussionsPage.tsx:239, 280` — "Reply needed"
  attention badge. Categorical (alongside slate "Spam", emerald
  "Resolved", etc.).
- `client/src/pages/DashboardPage.tsx:183` — decorative
  `bg-indigo-400/20 blur-2xl` background blur. Purely visual ambient
  blur, not a UI affordance.
- `client/src/pages/content-editors/editors.tsx:1020, 1665` —
  categorical content-type badges.

### Z8. Sweep confirms no remaining brand-utility violations
**Files**: full plugin

- **R**: `grep -rEn "(bg|text|border|ring)-indigo-(400|500|600|700)|(bg|text|border|ring)-violet-(400|500|600|700|800|900)" sikshya/client/src sikshya-pro/client/src`
- **V (after triage)**: only the entries enumerated in Z7. No
  unaccounted-for indigo/violet usage anywhere in either plugin's
  React source.

---

## AA. Deep functional verification: real cross-plugin gaps closed

Four parallel deep-functional-test agents walked the commerce flow
(cart → coupon → checkout → 6 gateways → fulfillment → refund), the
learner journey (enrol → learn → quiz/assignment → certificate), the
admin lifecycle (CRUD → curriculum → bulk → settings), and Pro-addon
integration (revenue share, marketplace commission, drip, prereqs,
subscriptions, webhooks, gradebook). The majority of "findings" turned
out to be false alarms once verified against the actual code:

| Auditor claim | Verdict |
|---|---|
| Mollie webhook lacks HTTP signature verification | False positive — Mollie's published pattern is verify-on-receipt via API callback (covered in section R). |
| PayPal IPN susceptible to MITM | False positive — IPN verify-with-PayPal is the canonical PayPal Simple flow. |
| Order subtotal mutates between session and confirm | False positive — order row persists `subtotal` and `total` at creation ([`CheckoutService.php:275, 313`](src/Commerce/CheckoutService.php#L275)); the bundle pricing filter only fires at quote/session, not at confirm. |
| Course in `draft` accessible to enrolled users via REST | False positive — `LearnPageService.php:360, 439` already gate on `$course->post_status !== 'publish'`. |
| Certificate token reuse within TTL | False positive — token binds `uid` so a shared URL doesn't leak to other users; same-user re-download by design. |
| Lesson-complete REST endpoint accepts unrelated course_id | False positive — auditor refuted their own claim (line 123 has the enrollment check). |
| Gradebook missing quiz/assignment completion listeners | False positive — `GradebookDataService` computes live from `sikshya_quiz_attempts` + assignment submissions on every admin view; no cache to invalidate. |
| Missing nonces on legacy AJAX controllers | False positive — `Frontend::handleAjaxRequest` dispatcher is never wired to `wp_ajax_*`; the methods are unreachable. |
| Slug duplication silently mangled | False positive — WordPress core auto-numbering (`-2`, `-3`); not a Sikshya bug. |

### Real bugs found and patched in this round:

### AA1. MultiInstructor revenue-share rows now purge on course delete
**Files**: `sikshya-pro/src/Addons/MultiInstructor/Services/OrderRevenueShareService.php`

Section W's `CourseDeleteCascade` fires `sikshya_course_deleted` after
purging enrolments/progress/cert rows. Pro's revenue-share ledger was
not listening; rows in `wp_sikshya_pro_revenue_shares` lingered with
order-item references pointing at deleted courses, surfacing in
earnings reports as `Course #123 — (no title)`.

- **R**: Delete a course that has at least one paid order against it.
- **V (before)**: `SELECT COUNT(*) FROM wp_sikshya_pro_revenue_shares rs
  INNER JOIN wp_sikshya_order_items oi ON oi.id = rs.order_item_id
  WHERE oi.course_id = <deleted>;` → returns rows.
- **V (after)**: same query → 0. The listener does a single
  `DELETE rs … INNER JOIN sikshya_order_items oi ON …` so the lookup
  and the delete run in one statement. Withdrawal history (which keys
  off the withdrawal row, not the revenue-share row) is unaffected.

### AA2. Marketplace commission rows now purge on course delete
**Files**: `sikshya-pro/src/Addons/MarketplaceMultivendor/Services/CommissionAccrualService.php`

Same gap as AA1 but for the marketplace commission ledger. Because the
`sikshya_pro_commissions` table denormalises `course_id` directly
(unlike RevenueShares), the cleanup is a single-key `DELETE` rather
than a JOIN. Vendor withdrawal records (which reference a
`withdrawal_id`, not the per-commission rows that fed them) stay
intact — the orphan is acceptable because the withdrawal is the source
of truth for "money sent to vendor".

- **R**: Delete a course attached to a marketplace vendor.
- **V (before)**: `SELECT COUNT(*) FROM wp_sikshya_pro_commissions
  WHERE course_id = <deleted>;` → returns rows.
- **V (after)**: same query → 0.

### AA3. Webhooks addon now dispatches `order.refunded`
**Files**: `sikshya-pro/src/Addons/Webhooks/Services/Outbound/OutboundEventHooks.php`

`OutboundEventHooks` listens to `sikshya_order_fulfilled` and ten
other learning events, but had **no listener for
`sikshya_order_refunded`**. External integrations (CRM sync,
accounting export, audit log) relying on the Webhooks addon to deliver
order events saw the fulfillment side but never the refund side.

- **R**: Trigger a refund via any gateway (Stripe/PayPal/Mollie/Paystack/Razorpay).
- **V (before)**: outbound webhook queue had `order.fulfilled` from the
  original purchase but no corresponding event for the refund.
- **V (after)**: queue now enqueues `order.refunded` with the same
  shape as fulfillment plus `refunded_courses[]`, `reason`,
  `refunded_amount`, and the gateway/currency from the order snapshot.
  Pre-existing `OrderRevenueShareService::onOrderRefunded` and
  `CommissionAccrualService::onOrderRefunded` continue to handle the
  internal ledger reversal; this listener handles the *external*
  dispatch.

### AA4. `LearnerCurriculumHelper` no longer does N×400 `get_post_type` calls
**Files**: `src/Services/LearnerCurriculumHelper.php`

The three public accessors (`lessonIdsForCourse`, `quizIdsForCourse`,
`assignmentIdsForCourse`) each walked chapters → contents and called
`get_post_type($cid)` inline. For a course with 50 chapters × 8
contents that's 400 individual post-type lookups per call — and the
helper is called multiple times per page render (Learn shell, REST
endpoint, progress recompute).

The refactor:
- Walks chapters→contents **once** to gather all candidate IDs.
- Primes the post cache with one `_prime_post_caches($ids, false, false)`
  call (single SELECT for everything).
- Partitions the IDs by post type using cached lookups (O(1) per ID).
- Memoises the partition per course per request, so a back-to-back
  `lessonIdsForCourse(123)` + `quizIdsForCourse(123)` shares the same
  cache slice.

- **V (before)**: cold cache, course with 400 contents → ~400 SQL
  queries per accessor call.
- **V (after)**: cold cache, same course → 1 SQL query. Warm-cache
  follow-up calls inside the same request → 0 SQL queries.
- **V (correctness)**: the returned ID lists are identical to the pre-
  refactor output for every course (deduplicated, ordered by chapter
  position then content position, no spurious IDs).

### AA5. Assignment uploads now have a per-assignment file-size cap
**Files**: `src/Services/AssignmentService.php`

Assignment submission validated min/max **file count** but not per-file
**size**. Sites relying on PHP's `upload_max_filesize` for protection
had no per-assignment override — an admin couldn't set "essay
submissions max 5MB, video submissions max 200MB" without editing
`php.ini`.

- Adds reading of `_sikshya_assignment_max_file_size` post meta (in
  megabytes; `0` = no per-assignment cap).
- Adds a `sikshya_assignment_max_file_size_bytes` filter so hosts can
  impose a global ceiling regardless of per-assignment config.
- Cap is checked **before** `wp_handle_upload` runs, so oversized files
  never land on disk (defends against fill-the-disk DoS).

- **R**: Set `_sikshya_assignment_max_file_size = 5` on an assignment.
  Attempt to upload a 10MB file.
- **V (before)**: file uploaded and stored if PHP allowed it; only
  rejected after server-side write.
- **V (after)**: server returns
  `{success: false, message: "One of your files exceeds the 5 MB limit
  for this assignment."}` before the upload is written.

### AA6. Sweep confirms cross-plugin actions are now bidirectional
**Files**: cross-plugin grep

- **R**: For every `do_action('sikshya_*')` in the free plugin, verify
  there's at least one corresponding `add_action` in Pro (where Pro
  needs to react) — or, conversely, document why no listener is needed:
  ```
  grep -rn "do_action.*'sikshya_" sikshya/src/
  grep -rn "add_action.*'sikshya_" sikshya-pro/src/
  ```
- **V**: `sikshya_order_fulfilled`, `sikshya_order_refunded`,
  `sikshya_course_deleted`, `sikshya_quiz_completed`,
  `sikshya_assignment_submitted`, `sikshya_lesson_completed`,
  `sikshya_user_enrolled`, `sikshya_user_unenrolled`,
  `sikshya_certificate_row_created` all have at least one Pro listener
  (revenue share, commission, webhooks dispatcher, activity log, etc.).
  `sikshya_course_deleted` previously had zero listeners — fixed in
  AA1/AA2 (Pro purge) and inherent in W4 (free-plugin cascade test).
- **V (negative)**: actions with **no Pro listener by design** —
  `sikshya_before_course_cascade_delete` (pre-cascade hook, Pro
  shouldn't listen here because the rows it cares about disappear
  inside the cascade); `sikshya_init` (boot-time, not event-time).

---

## AB. Deep functional verification (round 2): perf + lifecycle + email + i18n

Four more parallel deep-dive agents covered perf/caching, plugin
lifecycle (activation/deactivation/uninstall/migration), email +
cron delivery, and PHP-8 / i18n / RTL. Verified each finding against
the actual code before deciding what to act on — multiple "P0
critical" auditor claims turned out to be misreads of intentional
patterns. Two genuinely critical bugs and a handful of real-but-
moderate issues fell out of the noise.

### AB1. Six missing email-notification listeners — every learner-facing email was silent
**Files**: `src/Services/EmailNotificationService.php` (lines 816–854 → ~940)

The audit found that `EmailNotificationService::registerHookListeners`
registered exactly **two** listeners (`sikshya_course_qa_question_posted`
+ `sikshya_order_fulfilled`), while the service class defined **eight**
public `send*` methods. The six methods *without* a listener:

- `sendEnrollmentEmail` (learner enrolment confirmation)
- `sendAdminEnrollmentNotice` (admin "someone just enrolled" notice)
- `sendInstructorEnrollmentNotice` (instructor copy of the same)
- `sendCourseCompletedEmail` (learner "you finished the course")
- `sendCertificateIssuedEmail` (learner "your certificate is ready")
- `sendWelcomeEmail` (new account welcome)

Every action that *should* have triggered an email
(`sikshya_user_enrolled`, `sikshya_course_completed`,
`sikshya_certificate_row_created`, `user_register`) fired but nothing
listened. The templates were defined in `EmailTemplateCatalog`, the
context builders existed, the merge tags worked — and zero emails
went out. This was a **P0 ship-blocker** that had been silently
shipping for an unknown number of releases.

The fix wires four new `add_action` blocks (the enrolment one fans
out to all three enrolment templates with a single listener so the
admin / instructor toggles in settings stay independently respected):

- **R**: Enrol a fresh user into a free course. Check the user's inbox
  + the admin's inbox + the instructor's inbox.
- **V (before)**: zero emails. The relevant `sendSystemTemplate` calls
  never ran.
- **V (after)**: three emails (learner confirmation, admin notice,
  instructor notice). Each respects its `email_*` enable-toggle in
  settings — disabling "instructor new enrollment" in admin still lets
  the learner confirmation through, etc.
- **V (course completion)**: complete the last required item in a
  course → learner gets the completion email. Issue a certificate →
  learner gets the certificate-issued email.
- **V (welcome)**: register a new account via `/register` → learner
  gets the welcome email (binds to WP-native `user_register` hook
  so signups from any path are covered).

### AB2. Pro text domain mismatch — 75 strings hardcoded to the wrong domain
**Files**: 75 PHP files under `sikshya-pro/src/Addons/`

The Pro plugin registers its own `sikshya-pro` text domain via
`Plugin::loadTextdomain()`, but 75 translation calls in Pro addons
(`EmailAdvancedCustomization`, `WhiteLabel`, `Subscriptions`,
`PublicApiKeys`, etc.) were hardcoded to the **free** plugin's
domain (`'sikshya'`). Even when a translator shipped a
`sikshya-pro-ar_AR.po` covering these strings, WordPress looked them
up against the free MO bundle and fell back to English.

The fix is mechanical: bulk-replace `, 'sikshya')` → `, 'sikshya-pro')`
across all PHP files under `sikshya-pro/src/`. The pattern is narrow
enough that it doesn't catch hook names, option keys, or any other
literal — only the bare-string second argument of `__()` /
`esc_html__()` / `_e()` / etc.

- **R**: `grep -rn "', 'sikshya')" sikshya-pro/src/`
- **V (before)**: 75 matches across Pro addons.
- **V (after)**: 0 matches. `grep -rn "', 'sikshya-pro')" sikshya-pro/src/`
  count went up by exactly 75.
- **V (runtime)**: with a Pro-only locale `.mo` loaded, the SMTP
  settings page (`EmailAdvancedCustomization`) now renders translated
  field labels.

### AB3. Schema version stamped on activation
**Files**: `src/Core/Installer.php` (around line 60)

`Database::SCHEMA_VERSION` was declared `'1.9.0'` but never persisted
to the `sikshya_db_version` option. `Database::getVersion()` always
returned `'0.0.0'`, so a future migration dispatcher (that section's
already-defined `migrateTo110()` … `migrateTo190PaymentsChargeKind()`
private methods) would either re-run every historical migration on
every site or skip them entirely after a partial run.

The fix stamps the version at the end of `Installer::install()`,
mirroring the Pro plugin's `ProSchema::install()` pattern. Activation
is already idempotent (every `add_role`, `dbDelta`, `setRaw` is safe
to repeat), so this is a one-line addition.

- **R**: fresh activate of the free plugin.
- **V**: `get_option('sikshya_db_version')` returns `'1.9.0'`.

### AB4. Quiz grading loop primes the postmeta cache once
**Files**: `src/Api/Learner/QuizRoutes.php` (line ~177)

The quiz-submit handler iterates `$question_ids` **twice** (once to
compute the score, once to write per-question attempt rows). Each
iteration reads three meta fields per question — without priming, a
10-question quiz issued **60** `SELECT meta_value FROM postmeta`
statements per submit (cold cache); a 100-question exam, **600**.

The fix calls `update_meta_cache('post', $primable_qids)` once at the
top of the function. WP's caches are request-scoped, so a single SQL
populates an in-memory hash table that both loops read from for the
rest of the request.

- **R**: 100-question quiz submission with WP debug query logging.
- **V (before)**: ~600 `postmeta` SELECTs per submit (cold cache).
- **V (after)**: 1 `postmeta` SELECT per submit; subsequent
  `get_post_meta` calls are cache hits.
- **V (correctness)**: scoring output identical to pre-patch
  (deterministic; meta values come from the same source either way).

### AB5. CoursesListTable columns now read real data
**Files**: `src/Admin/ListTable/CoursesListTable.php` (lines 482, 570, 606, 620, 654)

The audit flagged the column renderers as a perf issue (6
`get_post_meta` calls per row × 20 rows = 120 queries per admin page
view). Closer inspection revealed a worse bug: **the meta keys the
columns read are never written anywhere in the plugin** —
`course_enrollments`, `course_price`, `course_original_price`,
`course_rating`, `course_lessons` are invented keys. Every course
admin ever saw rendered as "0 students / FREE / no rating / 0 lessons".

The fix replaces each column's meta read with the canonical source:

- **Student count** → `EnrollmentRepository::countByCourse($id)` with a
  per-request memo so the title column (which also shows "X students"
  inline) and the dedicated enrollments column share one query.
- **Price** → `sikshya_get_course_pricing($id)` (the same helper the
  cart, checkout, and single-course page use).
- **Rating** → `_sikshya_rating` post meta (the Pro CourseReviews
  addon's actual storage key, written by
  `CourseReviewService::recomputeCourseAggregateMeta`).
- **Lessons** → `LearnerCurriculumHelper::lessonIdsForCourse($id)`
  (the section AA4 refactor, which primes the post cache in one SQL).

- **R**: open admin → Courses on a site with enrolled paid courses.
- **V (before)**: every row shows "0 students / FREE / — rating / 0 lessons".
- **V (after)**: real enrolment counts (from the enrolments table),
  real prices (sale/regular formatted with currency symbol), real
  ratings when Pro reviews are enabled, real lesson counts.

### AB6. Findings explicitly verified as NOT bugs (false positives this round)

To keep the next maintainer from re-chasing closed leads:

1. **"License double-schedule"** — `LicenseScheduler::init()` already
   wraps `wp_schedule_event` in `if (!wp_next_scheduled(self::CRON_HOOK))`.
   Auditor misread the code.
2. **"`CoursesListTable` get_post_meta perf"** — pre-existing concern
   that turned out to be the wrong framing (the meta keys don't exist
   at all). `WP_Query` primes the meta cache automatically when
   `update_post_meta_cache` is true (the default), so even when the
   keys ARE real (post `WP_Query`), no per-row priming is needed.
3. **"No plaintext email alternative"** — WordPress's `wp_mail`
   pipeline accepts HTML emails as the canonical body and mail clients
   handle the text-only fallback themselves. Not a bug to fix in PHP.
4. **"Drip cron exceptions crash the pipeline"** — WP cron isolates
   each hook callback via its own try-catch wrapper since WP 4.9.6;
   one hook throwing does NOT abort other scheduled hooks in the same
   tick.
5. **"Pro doesn't auto-deactivate when Free deactivates"** — opinion-
   level design preference, not a bug. WP's plugin admin handles the
   "Required Plugins" header (added in `sikshya-pro.php` already);
   manual deactivation produces the standard WP warning.

### AB7. Sweep confirms no remaining wrong-domain calls in Pro
**Files**: `sikshya-pro/`

- **R**: `grep -rn "', 'sikshya')" sikshya-pro/src/`
- **V**: 0 matches. Every translation call in Pro now uses
  `'sikshya-pro'` as its domain (1456 occurrences) and zero use the
  free plugin's domain.

---

---

## Cross-cutting smoke tests

After running individual tests, exercise the full happy paths to make sure no patch broke legitimate flows:

1. **Free course end-to-end**: create course → publish → learner enrols → completes lessons → quiz → assignment → certificate auto-issues.
2. **Paid course end-to-end**: create paid course → guest checkout via Stripe test → fulfilment → enrolment → consumption.
3. **Bundle course**: create bundle (Pro) → learner buys → enrolled in all contained courses.
4. **Multi-instructor revenue**: course with two instructors at 60/40 split → paid order → revenue share rows created proportionally.
5. **Admin manual enrolment**: admin enrols a learner into a paid course via `/admin/...` → succeeds.
6. **Email template preview**: open admin email-template editor → preview an email with sample merge values → render matches production.

---

## Out-of-scope / flagged for separate engineering

These were uncovered during the audit but require their own scoped PRs:

1. ~~**Paystack / Razorpay refund handlers**~~ ✓ **Done.** All five gateways (Stripe, PayPal Advanced + IPN, Mollie, Paystack, Razorpay) now route refunds through `OrderRefundService`. See sections N, O, R, S.
2. ~~**Pro multi-instructor revenue-share rollback** listener for `sikshya_order_refunded`.~~ ✓ **Done** — `OrderRevenueShareService::onOrderRefunded` + `CommissionAccrualService::onOrderRefunded`. See section P.
3. **Partial refund handling** — currently surfaces via `sikshya_order_partial_refund` action but doesn't act on enrolments. Real partial-refund policy (prorate, refund-but-keep-access, etc.) is a product decision.
4. **Per-session JWT revocation** (jti blocklist) — current implementation revokes all tokens for the user on logout / password reset (intentional "log out everywhere" semantics).
5. ~~**Matching-question display-order obfuscation**~~ ✓ **Done** — grade-safe display shuffle (canonical index preserved as the submit value; no permutation reverse needed). See section T.
6. **`extract($data)` refactor** in `Core/View.php`, `Frontend/Controllers/CourseController.php` (defense-in-depth).
7. ~~**Course archive frontend N+1** consolidation (batched meta + permission queries).~~ ✓ **Done** — `UserCourseStateCache` + warm-before-loop. See section Q.

---

## Files modified across the campaign

28 patches + 6 new modules across 33 files. All five payment gateways' refund flows are now handled (Stripe, PayPal Advanced + IPN, Mollie, Paystack, Razorpay). Cross-reference here so QA / a follow-up engineer can git log -p the relevant ranges:

| Test | File |
|---|---|
| A1 | `src/Api/AdminRestRoutes.php` |
| A2 | `src/Api/AdminRestRoutes.php` |
| B1 | `src/Services/CourseService.php`, `src/Commerce/OrderFulfillmentService.php`, `src/Api/AdminRestRoutes.php`, `src/Frontend/Controllers/EnrollmentController.php`, `sikshya-pro/src/Addons/Webhooks/Services/Inbound/InboundRouterService.php` |
| B2 | `src/Commerce/OrderFulfillmentService.php` |
| B3 | `src/Database/Repositories/CouponRepository.php`, `src/Commerce/CheckoutService.php` |
| B4 | `src/Commerce/CheckoutService.php` |
| C1 | `src/Api/Learner/QuizRoutes.php` |
| C2 | `src/Services/CertificateIssuanceService.php` |
| D1, D2, D3 | `src/Api/JwtAuthService.php` |
| D4 | `src/Api/AuthRestRoutes.php` |
| E1 | `src/Admin/Settings/SettingsManager.php` |
| E2 | `src/Services/EmailNotificationService.php` |
| F1 | `src/Api/UserService.php` |
| G1 | `src/Services/EmailTemplateMerge.php`, `src/Services/EmailNotificationService.php`, `src/Api/AdminEmailTemplateRestRoutes.php` |
| H1 | `sikshya-pro/src/Addons/MarketplaceMultivendor/Services/WithdrawalService.php` |
| I1 | `sikshya-pro/src/Addons/Gradebook/Services/GradebookAssignmentGradeService.php` |
| J1, J2, J3, J4 | `src/Privacy/PersonalDataExporter.php`, `src/Privacy/PersonalDataEraser.php`, `src/Core/Plugin.php` |
| K1, K2, K3, K4, K5, K6 | `src/Security/LoginRateLimiter.php`, `src/Api/AuthRestRoutes.php` |
| L1, L2, L3, L4, L5 | `src/Api/JwtAuthService.php`, `src/Api/AuthRestRoutes.php`, `src/Core/Plugin.php` |
| M1, M2, M3, M4, M5, M6, M7, M8 | `src/Security/AttachmentTokenService.php`, `src/Api/AttachmentProxyRoutes.php`, `src/Api/Api.php`, `templates/single-lesson.php`, `templates/single-quiz.php` |
| N1, N2, N3, N4, N5, N6, N7 | `src/Commerce/OrderRefundService.php`, `src/Api/WebhooksRestRoutes.php`, `src/Services/CourseService.php`, `src/Database/Repositories/PaymentRepository.php` |
| O1, O2, O3, O4, O5, O6, O7 | `src/Api/WebhooksRestRoutes.php` (PayPal Advanced + IPN refund branches) |
| P1, P2, P3, P4, P5, P6 | `sikshya-pro/src/Addons/MultiInstructor/Repositories/RevenueSharesRepository.php`, `sikshya-pro/src/Addons/MultiInstructor/Services/OrderRevenueShareService.php`, `sikshya-pro/src/Addons/MarketplaceMultivendor/Repositories/CommissionRepository.php`, `sikshya-pro/src/Addons/MarketplaceMultivendor/Services/CommissionAccrualService.php` |
| Q1, Q2, Q3, Q4, Q5, Q6 | `src/Frontend/Site/UserCourseStateCache.php`, `includes/template-functions.php`, `templates/archive-sik_course.php` |
| R1, R2, R3, R4, R5, R6, R7 | `src/Commerce/CheckoutService.php` (webhookUrl in createMolliePayment), `src/Api/WebhooksRestRoutes.php` (mollie route + handler) |
| S1-S10 | `src/Api/WebhooksRestRoutes.php` (paystack + razorpay routes, signature checks, handlers) |
| T1-T7 | `src/Frontend/Site/QuizTemplateData.php` (shuffle), `templates/partials/quiz-question-fieldset.php` (render canonical-index options) |
| U1, U2, U3 | `src/Core/View.php`, `src/Frontend/Controllers/CourseController.php` |
| V1, V2, V3, V4, V5 | `src/Admin/ListTable/QuizzesListTable.php`, `src/Admin/ListTable/LessonsListTable.php`, `src/Admin/ListTable/StudentsListTable.php`, `src/Admin/ListTable/AbstractListTable.php`, `src/Admin/ListTable/CoursesListTable.php` |
| W1, W2, W3, W4, W5, W6 | `src/Services/CourseDeleteCascade.php`, `src/Core/Plugin.php` |
| X1, X2, X3, X4, X5 | `src/Frontend/Site/QuizTemplateData.php` |
| Y1, Y2, Y3 | `src/Frontend/Frontend.php` |
| Z1, Z2, Z3, Z4 | `assets/css/public-design-system.css`, `templates/courses-grid.php`, `templates/order-invoice.php` |
| Z6 | `client/src/components/shared/PrerequisiteLockDetailPopover.tsx`, `client/src/components/EnrollmentSettingsTab.tsx`, `client/src/pages/{CoursesPage,AddonSettingsPage,EmailMarketingPage,ContentDripPage,PrerequisitesPage,DashboardPage,SettingsPage,settingsRenderField}.tsx` |
| AA1 | `sikshya-pro/src/Addons/MultiInstructor/Services/OrderRevenueShareService.php` |
| AA2 | `sikshya-pro/src/Addons/MarketplaceMultivendor/Services/CommissionAccrualService.php` |
| AA3 | `sikshya-pro/src/Addons/Webhooks/Services/Outbound/OutboundEventHooks.php` |
| AA4 | `src/Services/LearnerCurriculumHelper.php` |
| AA5 | `src/Services/AssignmentService.php` |
| AB1 | `src/Services/EmailNotificationService.php` |
| AB2 | 75 PHP files under `sikshya-pro/src/Addons/` (text-domain bulk-replace) |
| AB3 | `src/Core/Installer.php` |
| AB4 | `src/Api/Learner/QuizRoutes.php` |
| AB5 | `src/Admin/ListTable/CoursesListTable.php` |
