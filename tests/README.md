# Sikshya — tests

This directory holds the PHPUnit test suite. The test infrastructure was bootstrapped during the
2026-05 hardening sprint; expect coverage to grow over time.

## Running

```bash
composer install              # ensure dev deps are installed
composer test                 # all suites
vendor/bin/phpunit --testsuite unit         # unit only
vendor/bin/phpunit --testsuite integration  # integration only (needs WP test scaffold)
```

## Layout

- `tests/bootstrap.php` — minimal autoload bootstrap. Unit tests should not require WordPress;
  use Brain Monkey or hand-rolled stubs for any WP function calls.
- `tests/Unit/` — pure-PHP unit tests. Run fast, no DB, no WP runtime.
- `tests/Integration/` — exercises against a real WordPress install (TBD; needs WP test scaffold).

## Adding tests

Prefer **integration tests** for anything that touches enrollment, order fulfillment, certificates,
payments, or licensing — these flows have the highest blast radius if they break, and unit-mocking
WP at scale becomes its own maintenance burden. Pure helpers and value objects are good unit-test
targets.

The first integration tests to add (in order of priority):

1. `OrderFulfillmentService::fulfillPaidOrder` — concurrent-call idempotency (the row-lock fix).
2. Webhook signature verification — happy path + tampered signature + missing secret.
3. License tier read — fresh activation, tampered option, lazy-seed of pre-signature install.
4. OAuth authorize → token exchange round-trip.

## Build artifacts

`tests/`, `phpunit.xml.dist`, and `.phpunit.result.cache` are excluded from the customer-facing
zip by `build.sh`. Add coverage HTML output to `.gitignore` if you turn on coverage locally.

## REST controller split pattern

The historical god-class `Sikshya\Api\LearnerRestRoutes` (and `AdminRestRoutes`) is being broken
up one domain at a time. The pattern, settled on 2026-05-14:

- **Base controller**: `Sikshya\Api\Learner\AbstractLearnerRestController` owns the shared
  scaffolding — `requireLoginOrJwt()` permission callback, `error()` envelope, and
  `getCourseService()`. Every concrete learner controller extends it.
- **Concrete controller per domain**: e.g. `Sikshya\Api\Learner\ContentNoteRoutes` owns its
  own routes, callbacks, constants, and private helpers. Each has its own `register()` that
  calls `register_rest_route` for its domain's paths only.
- **Composition in `LearnerRestRoutes::register()`**: the parent file instantiates each
  extracted controller and calls its `register()`. Routes that haven't yet been extracted stay
  in `LearnerRestRoutes` until they get their own subclass.

Why this pattern over traits or injected services: matches existing OOP patterns in the
codebase, lowest churn at call sites (the `[$this, 'callback']` callable form just works),
easiest to revert if a future need pushes us elsewhere. See the
`project-rest-split-decision` memory entry for the full rationale.

When extracting another domain (assignments, quiz, certificates, etc.), follow the
`ContentNoteRoutes` template: copy callbacks + helpers + constants into the new subclass,
delete from `LearnerRestRoutes`, add one `new XxxRoutes($this->plugin)->register()` call
inside `LearnerRestRoutes::register()`. Cross-check by grepping the rest of the codebase for
any external references before deleting — the old methods were private but constants might
be referenced.

### LearnerRestRoutes split — complete

All five learner domains have been extracted (2026-05-14):

| Controller | Routes | LOC |
|---|---|---|
| `AssignmentRoutes` | `/me/assignments`, `/me/assignment-submit`, `/me/assignment-feedback` | 148 |
| `ContentNoteRoutes` | `/me/content-note` (GET/POST/PUT/DELETE) | 517 |
| `EnrollmentRoutes` | `/me/unenroll` | 71 |
| `ProgressRoutes` | `/me/progress`, `/me/lesson-complete` | 166 |
| `QuizRoutes` | `/me/quiz-attempt` (GET/POST), `/me/quiz-submit` | 596 |
| `AbstractLearnerRestController` (base) | (shared infra — auth, error envelope, sync) | 144 |
| `LearnerRestRoutes` (coordinator) | (instantiates subclasses + fires addon hook) | 54 |

The original `LearnerRestRoutes` was **1,370 LOC**; the coordinator is now **54 LOC** (-96%).

### AdminRestRoutes split — in progress

The same pattern has been applied to `AdminRestRoutes` (originally 3,481 LOC). Now at **2,850 LOC**
(-18%) after extracting four domains:

| Controller | Routes |
|---|---|
| `Sikshya\Api\Admin\CouponRoutes` | `/admin/coupons` (GET/POST) + `/admin/coupons/(?P<id>\d+)` (PATCH) |
| `Sikshya\Api\Admin\InstructorApplicationRoutes` | `/admin/instructor-applications` (GET) + `/approve` + `/reject` |
| `Sikshya\Api\Admin\SetupWizardRoutes` | `/admin/setup-wizard/step` + `/sample-import` |
| `Sikshya\Api\Admin\TaxonomyRoutes` | `/taxonomies/course-category` (POST) + per-id (GET/DELETE) |

`Sikshya\Api\Admin\AbstractAdminRestController` is the base. Owns the six shared admin
permission callbacks (`permissionReactApp`, `permissionManageOptions`, `permissionSalesCommerce`,
`permissionAdmin`, `permissionAdminOrCanEditCertificate`, `permissionTools`), the JWT validation
flow they share, and `jsonBody()` (the request body parser used by every mutating route).

When picking the next domain to extract, prefer well-bounded ones first: a domain whose
callbacks don't share private helpers with other route groups is a faster, lower-risk extraction.
Orders is a notable exception — its `relatedOrderIdFromPaymentGatewayResponse` static is also
called by a payments-domain callback, so plan to extract Orders + Payments together (or factor
the helper into a shared `Admin\OrderHelpers`).
