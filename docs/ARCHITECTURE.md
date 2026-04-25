# Sikshya LMS — layered architecture (Free + Pro + Addons)

This document is the **contract** for how new code should be added. A full migration of every template and controller to these rules is **incremental**.

### Migration status (at a glance)

- **Model-driven (service → page model → template):** Learn hub, single lesson, single course, single quiz, courses grid, cart, checkout, order, account shell (+ partials).
- **Still on legacy `*TemplateData` / `$vm[...]` in places:** some shared partials (e.g. course card, single-course review blocks) and any screen not listed above. Refactor by introducing `*PageService` + `*PageModel`, then switch the template to getters; keep a `toLegacyViewArray()` (or the filtered array) for existing hooks.
- **Database access:** New work keeps `$wpdb` in `Database\Repositories\` and schema helpers. **Known legacy:** some admin `ListTable` and `Admin\Controllers\DashboardController` may still use `$wpdb` or large queries; prefer moving that SQL into a repository in the same feature PR when touched.
- **Sikshya Pro:** `src/Rest/ProModuleRoutes.php` and `ProExtendedFeatureRoutes.php` still contain **inline SQL**; the target state is thin routes that call **services** + **repositories** (same rule as free REST).

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

## Pro & Addons

- **Pro** may register extra filters/actions; it should not fork core files. `sikshya-pro` mirrors the same ideas: services + repositories + frontend hooks.
- **Addons** (Free or Pro) should live under `Addons/AddonName/` with the same subfolders when they own behavior: `Controllers/`, `Services/`, `Repositories/`, `Models/`, `Templates/`, `Api/`, and register via the addon manager.

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
