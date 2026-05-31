# Sikshya REST conventions

This is the **forward-going contract** for any REST route added to the Sikshya Free or Pro plugin (and any addon that registers under `sikshya/v1`). It does **not** retroactively change the shape of any existing endpoint — client integrations depend on those.

Read this alongside [`SIKSHYA_REST_ROUTE_MAP.md`](./SIKSHYA_REST_ROUTE_MAP.md), which is the catalogue of what exists today.

## TL;DR

For **new** routes:

```php
register_rest_route('sikshya/v1', '/foo', [
    'methods'             => 'POST',
    'callback'            => [$this, 'createFoo'],
    'permission_callback' => [\Sikshya\Api\RestPermissions::class, 'forStaff'],
]);

public function createFoo(\WP_REST_Request $request): \WP_REST_Response
{
    $body = $request->get_json_params();
    if (!is_array($body)) {
        return \Sikshya\Api\RestResponse::badRequest(__('Invalid JSON body.', 'sikshya'));
    }
    // …business logic…
    return \Sikshya\Api\RestResponse::ok(['foo_id' => $id], 201);
}
```

## 1. Namespace + versioning

- Always under `sikshya/v1`. Do not invent a new namespace per addon.
- Path: `/<domain>/<resource>[/<id>][/<sub-action>]`. Examples: `/admin/coupons`, `/pro/subscriptions/(?P<id>\d+)/cancel`.
- We do not currently ship `/v2`. Breaking changes go behind a feature flag or a new resource path.

## 2. Permission callbacks — use `RestPermissions`

Use [`Sikshya\Api\RestPermissions`](../src/Api/RestPermissions.php) instead of inlining the cookie-or-JWT dance:

| Call | When to use |
|---|---|
| `RestPermissions::forStaff` | LMS staff backend — React admin shell, content REST. Cookie + nonce **or** JWT. |
| `RestPermissions::forManageOptions` | Commerce, settings, sensitive admin (`manage_options`). Cookie + nonce **or** JWT. |
| `RestPermissions::forTools` | Maintainer tools (export/import, cache, diagnostics). `manage_options` only, no JWT path. |
| `RestPermissions::forLearner` | The `/me/*` family. Logged-in **or** valid JWT. |
| `RestPermissions::forPublic` | Anyone can call. Prefer this over a literal `__return_true` so the surface is greppable. |

Pro-addon-gated routes still wrap one of the above with the Pro check, e.g.:

```php
'permission_callback' => static function (\WP_REST_Request $r) {
    $base = \Sikshya\Api\RestPermissions::forManageOptions($r);
    if (is_wp_error($base)) return $base;
    return \SikshyaPro\Permissions\ProRestAddonPermissions::forRest('subscriptions');
},
```

Existing controllers that use instance-method callbacks (`[$this, 'permissionAdmin']` on `AbstractAdminRestController`) continue to work — those methods now delegate to `RestPermissions` internally as part of the Phase 1 refactor. There is no breaking change.

## 3. Response envelope — use `RestResponse`

Use [`Sikshya\Api\RestResponse`](../src/Api/RestResponse.php). The canonical shape is:

```jsonc
// success
{ "ok": true,  "data": { /* … */ } }

// error
{ "ok": false, "code": "invalid_params", "message": "…", "data": { /* optional */ } }
```

| Helper | Status | When |
|---|---|---|
| `RestResponse::ok($data, $status = 200, $meta = [])` | 200 / 201 | Success. `$meta` is merged at the top level for pagination cursors, request ids, etc. |
| `RestResponse::error($code, $message, $status, $data = null)` | any 4xx/5xx | Generic error. |
| `RestResponse::badRequest($message, $code = 'invalid_params', $data = null)` | 400 | Validation failures. |
| `RestResponse::unauthorized($message)` | 401 | No credentials. |
| `RestResponse::forbidden($message)` | 403 | Authenticated, insufficient caps. |
| `RestResponse::notFound($message)` | 404 | Resource missing or deleted. |
| `RestResponse::conflict($message)` | 409 | State collision (duplicate enrollment, email already exists, etc.). |
| `RestResponse::serviceUnavailable($message)` | 503 | Dependency unavailable (custom table not installed, third-party down). |

### What about the legacy shapes?

Three response shapes coexist in the historical codebase:

- `{ success, message?, data? }` — legacy learner + auth routes.
- `{ ok, data?, message? }` — newer admin + Pro addon routes.
- bare arrays — early courses/lessons endpoints.

**Do not change** an existing route's shape. Doing so will break the React admin shell, the mobile app's stored payload schemas, and any third-party Zapier-style integration. Standardisation is opt-in for new code only.

## 4. Pagination

When a route returns a list, support these query params and surface their result in `meta`:

```jsonc
GET /sikshya/v1/admin/courses?page=2&per_page=20

{
  "ok": true,
  "data": [ /* … */ ],
  "page": 2,
  "per_page": 20,
  "total": 137,
  "total_pages": 7
}
```

- Default `per_page` is **20**.
- Max `per_page` is **100**. (Bulk endpoints already cap at 100; some older routes still allow 500 — those are grandfathered. New routes use 100.)
- Always validate `page >= 1` and `1 <= per_page <= 100`.

## 5. Errors — be greppable

- The `code` field is a **stable, machine-readable slug** in `snake_case` (e.g. `invalid_params`, `course_locked`, `quiz_unavailable`).
- The `message` is **localised**, human-readable, terminating in a period.
- Field-level validation errors go under `data.errors` as a `{ field: message }` map:

```jsonc
{
  "ok": false,
  "code": "validation_failed",
  "message": "Some fields are invalid.",
  "data": {
    "errors": {
      "email": "Enter a valid email.",
      "discount_value": "Must be between 0 and 100."
    }
  }
}
```

## 6. Method semantics

- `GET` — safe + idempotent. No side effects.
- `POST` — non-idempotent. Creates or runs an action. Always returns `201` for resource creation, `200` for actions (with the action's result in `data`).
- `PUT` — replace the addressed resource. Idempotent.
- `PATCH` — partial update. Idempotent in practice (same body, same outcome).
- `DELETE` — remove the addressed resource. Idempotent. Returns `200` with `data: { deleted: true }` or `204 No Content`.

## 7. Idempotency keys

For order-creation and similar routes where retries are likely, accept a client-supplied `idempotency_key` header. Store the first response under that key for at least 24 h and return it verbatim on retry. New routes only — do not retrofit silently.

## 8. Filenames + class layout

- One controller per file under `src/Api/<Group>/<Domain>Routes.php` (e.g. `src/Api/Admin/CouponRoutes.php`).
- Controllers extend `AbstractAdminRestController` (admin) or `AbstractLearnerRestController` (learner) so the shared scaffolding (body parsing, JWT validation cache, error helpers) stays in one place.
- Pro addon REST lives under `sikshya-pro/src/Addons/<StudlyCase>/Controllers/<Name>RestController.php`.

## 9. Versioning a contract change

If you must change a response shape:

1. **Add a new resource path** alongside the old one — e.g. `/admin/courses` keeps the legacy shape; `/admin/courses-v2` ships the new shape.
2. Document the difference at the top of `SIKSHYA_REST_ROUTE_MAP.md`.
3. Deprecate the old path with an admin-facing notice + a `Deprecation` header on the response. Plan a removal window of at least one major release.

## 10. Tests are the safety net

Every new route should land with at least:

- One PHPUnit integration test under `tests/Integration/` exercising the happy path + one validation failure.
- One Playwright e2e test under `e2e/tests/<group>/` if the route is reachable from the React admin or a customer flow.

For Pro addon REST, the parameterised pattern at `e2e/tests/pro/addon-rest-smokes.spec.ts` is the cheapest place to add a one-liner check.

---

**Owners:** if you find an existing route that violates these conventions, **do not break it**. File a note in the project memory or this doc instead, and use the conventions only for new code or for routes you're already extracting/splitting.
