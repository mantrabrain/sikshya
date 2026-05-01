# Sikshya LMS — layered architecture (Free + Pro + Addons)

This document is the **contract** for how new code should be added. A full migration of every template and controller to these rules is **incremental**.

### Migration status (at a glance)

- **Model-driven (service → page model → template):** Learn hub, single lesson, single course, single quiz, courses grid, cart, checkout, order, account shell (+ partials).
- **Still on legacy `*TemplateData` / `$vm[...]` in places:** some shared partials (e.g. course card, single-course review blocks) and any screen not listed above. Refactor by introducing `*PageService` + `*PageModel`, then switch the template to getters; keep a `toLegacyViewArray()` (or the filtered array) for existing hooks.
- **Database access:** New work keeps `$wpdb` in `Database\Repositories\` and schema helpers. **Known legacy:** some admin `ListTable` and `Admin\Controllers\DashboardController` may still use `$wpdb` or large queries; prefer moving that SQL into a repository in the same feature PR when touched.
- **Sikshya Pro:** `sikshya-pro/src/Rest/ProAddonRoutes.php` and `ProExtendedFeatureRoutes.php` may still contain **inline SQL** in some handlers; the target state is thin routes that call **services** + **repositories** (same rule as free REST).

## Layering (required)

| Layer | Responsibility | Allowed to call |
|--------|----------------|-----------------|
| **Controller** (web + REST) | Request/response, capability checks, pick service method | Services only |
| **Service** | Business rules, feature flags (Free vs Pro), orchestration, building presentation models | Repositories, WordPress APIs, other services |
| **Repository** | Persistence: posts, meta, custom tables, files | `$wpdb` / `WP_Query` / table APIs (encapsulated here) |
| **Model (Presentation)** | Read-only getters for templates & JSON; optional lazy read via service (no writes) | Service (read-only) if lazy-loading; never Repository |
| **Template / view** | Markup and escaping; **no** `get_option`, `$wpdb`, raw unshaped data, or direct service calls | **Model methods only** (target state) |

Legacy code may still use `*TemplateData` arrays; new work should return **models** from services and update templates in the same change when practical.

## Namespaces (Free)

- `Sikshya\Services\` — application services.
- `Sikshya\Services\Frontend\` — learner-facing page builders (e.g. `LearnPageService`).
- `Sikshya\Presentation\Models\` — page/entity DTOs for views (`LearnPageModel`, `CourseModel`, …).
- `Sikshya\Database\Repositories\` — all database / query access.
- `Sikshya\Frontend\Controllers\` — HTTP entry for theme templates.
- `Sikshya\Models\` — legacy facades only; they must **delegate** to `Database\Repositories` (no `$wpdb` in the model). Prefer `Presentation\Models` for new templates.

## Learn & lesson shells (reference)

1. **Learn** — `LearnPageService` + `LearnPageModel` + `templates/learn.php` (see below).
2. **Single lesson** — `LessonPageService` + `SingleLessonPageModel` + `templates/single-lesson.php`; `LessonTemplateData` remains a thin backward-compatible entry.

## Learn page (reference)

1. `LearnPageService::fromRequest()` loads data via repositories + `PublicCurriculumService`, applies `sikshya_learn_template_data`, then wraps the result in `LearnPageModel`.
2. `templates/learn.php` uses only `$page` (`LearnPageModel`) and value objects such as `LearnPageUrlsModel`, `HubCourseRowModel`, `RecommendedCourseModel`, `CourseModel`.
3. Pro continues to filter `sikshya_learn_template_data` (array). Actions receive the **legacy array first**, then the `LearnPageModel` as the second argument for new integrations.

## Pro & add-ons

- **Pro** may register extra filters/actions; it should not fork core files. `sikshya-pro` mirrors the same ideas: services + repositories + frontend hooks.
- **Add-ons** (Free or Pro) are defined in the free `FeatureRegistry`, may be extended via `sikshya_addons_registry` (Sikshya Pro merges more), and live under the free `Sikshya\Addons\` namespace for the catalog. Pro implementation code lives under `sikshya-pro/src/Addons/<StudlyCase>/` (PSR-4: matches `SikshyaPro\Addons\<StudlyCase>\...`; the catalog **id** stays `snake_case`, e.g. `content_drip` → folder `ContentDrip/`). Use the same subfolders for behavior: `Services/`, `Repositories/`, `Rest/`, etc. Only **enabled** add-ons are booted (`AddonInterface::boot()`).

## Naming

- Use **add-on** / **addon** in product code and docs for pluggable features. Reserve **module** for curriculum structure (course sections, etc.), not for the installable add-on list.

## i18n (POT) — PHP + TypeScript admin UI

- Same pattern as **Sikshya**: **WP-CLI** `i18n make-pot` for PHP (`src`, `includes`, `templates`, `assets`, excluding the React sources in `client/` and the built bundle under `assets/admin/react/`). If a `languages/sikshya-js.pot` fragment exists, a second `make-pot … --merge=…` merges it in, then the fragment is removed.
- **JS/TS strings:** `@wordpress/babel-plugin-makepot` runs over **`client/src`** (`.ts`/`.tsx`), not the Vite production bundle — minified bundles do not preserve extractable `__()` / `_n()` / `_x()` sites. Use `@wordpress/i18n` in admin code when you add translatable UI copy; until then Babel may produce no `-js.pot` file (merge is skipped; that is expected).
- **One `package.json` at the plugin root** (`npm ci`, `npm run build`, `npm run dev`); Vite/Tailwind/PostCSS/TS config live next to it. App sources live in **`client/src/`**; output is `assets/admin/react/`.
- Local: `npm ci` at the plugin root, then `composer run makepot:full` / `npm run makepot`. CI runs `.github/workflows/i18n.yml`.
- Quick PHP-only (skip Vite build): `SKIP_SIKSHYA_ADMIN_BUILD=1 bash scripts/makepot.sh` (Babel on `client/src` still runs if `node_modules` is present).

## JSON / REST API

- Reuse the **same** services the web UI uses. Serialize from models or small DTOs; do not duplicate business rules in route files.
- **No inline `$wpdb` in route classes** — load or join rows through a `*Repository` (Pro: e.g. `CourseReviewRepository`), then call the service for rules/aggregates.

## Migration checklist (for future PRs)

1. Move data assembly from `*TemplateData` statics into a `*Service` class.
2. Introduce a `*PageModel` or entity model with getters.
3. Replace template array access with model getters in one vertical slice.
4. Move any remaining `WP_Query` in services into a repository method.
5. Add tests or smoke-test the affected screens.

## Further reading

- `src/Services/Frontend/LearnPageService.php` — learn hub service.
- `src/Presentation/Models/LearnPageModel.php` — learn page model.
