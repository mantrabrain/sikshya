# Sikshya Legacy Migration Plan

This document is the human-readable audit + transform plan that the
`Sikshya\Migration\LegacyMigrator` executes when a site is upgraded from the
legacy `sikshya-old` plugin (version `0.0.x`) to the rewritten `sikshya`
plugin (version `1.0.0`+).

The whole `src/Migration/` directory is **safe to delete** in a future minor
release once the entire installed base has been migrated. The new plugin
checks `class_exists(\Sikshya\Migration\LegacyMigrator::class)` before
booting the migrator, so removing the directory turns the system into a
no-op.

---

## 1. Detection fingerprint

The migrator runs only when at least one of the following is observed:

- `wp_options` contains `sikshya_version`, `sikshya_permalinks`,
  `sikshya_account_page`, `sikshya_currency`, or `sikshya_payment_gateways`.
- `wp_posts` contains a row with `post_type` in
  (`sik_courses`, `sik_lessons`, `sik_sections`, `sik_quizzes`,
  `sik_questions`, `sik_orders`).
- A `wp_term_taxonomy` row exists with `taxonomy` in
  (`sik_course_category`, `sik_course_tag`).
- A custom table exists with name in
  (`{prefix}sikshya_user_items`, `{prefix}sikshya_order_items`,
  `{prefix}sikshya_logs`).

If none of these are present the migrator records a no-op success run and
exits.

---

## 2. Custom post type rename map

Legacy slugs were plural; the rewrite uses singular. Slugs are renamed in
place (`UPDATE wp_posts SET post_type = '...'`) so all post IDs are
preserved. A `_sikshya_migrated_from_legacy` post-meta marker captures the
original legacy type for traceability.

| Legacy CPT       | Rewrite CPT    | Notes                                                         |
|------------------|----------------|---------------------------------------------------------------|
| `sik_courses`    | `sik_course`   | Direct rename.                                                |
| `sik_lessons`    | `sik_lesson`   | Direct rename.                                                |
| `sik_quizzes`    | `sik_quiz`     | Direct rename.                                                |
| `sik_questions`  | `sik_question` | Direct rename.                                                |
| `sik_sections`   | `sik_chapter`  | Sections become chapters; `post_parent` set to the course ID. |
| `sik_orders`     | -              | Posts kept as `sik_orders` for archival reads. Orders are also projected into the new `sikshya_orders` / `sikshya_order_items` tables (see §6). |
| `sikshya-payment` | -             | Legacy stub posts left untouched (no CPT registration in either plugin); preserved as historical record. |

After the rename the migrator drops the rewrite-rule cache via
`flush_rewrite_rules()` so the new (singular) slug rewrites are picked up.

---

## 3. Taxonomy rename map

Slugs change from `sik_*` to `sikshya_*`. We rename rows in
`wp_term_taxonomy.taxonomy` so term IDs and term-relationship rows remain
valid.

| Legacy taxonomy        | Rewrite taxonomy            |
|------------------------|-----------------------------|
| `sik_course_category`  | `sikshya_course_category`   |
| `sik_course_tag`       | `sikshya_course_tag`        |

Taxonomies introduced only by the rewrite (`sikshya_difficulty`,
`sikshya_lesson_type`, `sikshya_question_type`) have no legacy counterpart
and are skipped.

---

## 4. Post-meta rename map

The rewrite uses `_sikshya_*` prefixes consistently; legacy used a mix of
`sikshya_*` (course) and bare keys (section, lesson, question). Renames are
done with `UPDATE IGNORE wp_postmeta SET meta_key = '...'` so duplicates
created by partial migrations are absorbed cleanly.

### Course (`sik_course`)

| Legacy key                              | Rewrite key                |
|-----------------------------------------|----------------------------|
| `sikshya_course_regular_price`          | `_sikshya_price`           |
| `sikshya_course_discounted_price`       | `_sikshya_sale_price`      |
| `sikshya_course_duration`               | `_sikshya_duration`        |
| `sikshya_course_duration_time`          | `_sikshya_duration_unit`   |
| `sikshya_course_level`                  | `_sikshya_difficulty`      |
| `sikshya_course_maximum_students`       | `_sikshya_max_students`    |
| `sikshya_course_video_source`           | `_sikshya_video_source`    |
| `sikshya_course_youtube_video_url`      | `_sikshya_video_url`       |
| `sikshya_course_outcomes`               | `_sikshya_learning_outcomes` |
| `sikshya_course_requirements`           | `_sikshya_target_audience` |
| `sikshya_instructor`                    | `_sikshya_instructor`      |

### Lesson (`sik_lesson`)

| Legacy key                              | Rewrite key                |
|-----------------------------------------|----------------------------|
| `sikshya_lesson_duration`               | `_sikshya_lesson_duration` |
| `sikshya_lesson_duration_time`          | `_sikshya_lesson_duration_unit` |
| `sikshya_is_preview_lesson`             | `_sikshya_is_free`         |
| `sikshya_lesson_video_source`           | `_sikshya_lesson_video_source` |
| `sikshya_lesson_youtube_video_url`      | `_sikshya_lesson_video_url` |
| `sikshya_order_number`                  | `_sikshya_order`           |

### Quiz (`sik_quiz`)

| Legacy key                              | Rewrite key                |
|-----------------------------------------|----------------------------|
| `sikshya_order_number`                  | `_sikshya_order`           |

### Question (`sik_question`)

| Legacy key                              | Rewrite key                                      |
|-----------------------------------------|--------------------------------------------------|
| `type`                                  | `_sikshya_question_type`                         |
| `answers`                               | `_sikshya_question_options`                      |
| `correct_answers`                       | `_sikshya_question_correct_answer`               |
| `quiz_id`                               | `_sikshya_quiz_id`                               |
| `course_id`                             | `_sikshya_course_id`                             |
| `lesson_id`                             | `_sikshya_lesson_id`                             |

### Chapter (`sik_chapter` — formerly `sik_sections`)

| Legacy key       | Rewrite key                    |
|------------------|--------------------------------|
| `course_id`      | `_sikshya_chapter_course_id`   |
| `section_order`  | `_sikshya_chapter_order`       |

After the chapter rename a courses-to-chapters rebuild walks every
`sik_course` and writes `_sikshya_chapters` (the array of chapter IDs in
saved order).

---

## 5. Enrollment + progress migration

Legacy storage: `{prefix}sikshya_user_items` rows with `item_type` matching
the legacy CPT slug and `status` in (`enrolled`, `started`, `completed`).
The rewrite uses two distinct tables:

- `{prefix}sikshya_enrollments` — one row per `(user_id, course_id)` pair
  (UNIQUE constraint).
- `{prefix}sikshya_progress` — one row per
  `(user_id, course_id, lesson_id|NULL, quiz_id|NULL)`.

Transform:

| Legacy row (item_type / status)        | New row                                                    |
|----------------------------------------|------------------------------------------------------------|
| `sik_courses` / `enrolled`             | `enrollments` row, status `enrolled`, enrolled_date copied from `start_time`. |
| `sik_courses` / `completed`            | `enrollments` row, status `completed`, completed_date set. |
| `sik_lessons` / `completed`            | `progress` row with lesson_id, status `completed`.         |
| `sik_lessons` / `started`              | `progress` row with lesson_id, status `in_progress`.       |
| `sik_quizzes` / any                    | `progress` row with quiz_id, status mapped.                |

Inserts use `INSERT IGNORE` to honor the UNIQUE constraints and stay
idempotent. The legacy table is **not dropped** — it remains for forensic
reference; the uninstall path on the new plugin can clean it up.

---

## 6. Order migration

Legacy orders are `sik_orders` posts with a serialized
`sikshya_order_meta` post-meta blob (cart, currency, totals, gateway).
Order line rows live in `{prefix}sikshya_order_items`/`order_itemmeta`.

Transform: for each `sik_orders` post, project the relevant fields into a
new `{prefix}sikshya_orders` row whose `meta` JSON column captures the
legacy `post_id`, `gateway`, and free-form fields. Line items are projected
into `{prefix}sikshya_order_items`. The legacy posts and tables are
preserved.

`sik_orders` posts retain their original status (`sikshya-pending`,
`sikshya-processing`, etc.). The new plugin doesn't register these
statuses, but admin reads still work since post-status constants are not
removed by this migration.

---

## 7. Settings + permalinks

### Direct option-key rename (legacy → rewrite)

| Legacy option                                     | Rewrite option (`_sikshya_…`)         |
|---------------------------------------------------|---------------------------------------|
| `sikshya_currency`                                | `_sikshya_currency`                   |
| `sikshya_currency_position`                       | `_sikshya_currency_position`          |
| `sikshya_currency_symbol_type`                    | `_sikshya_currency_symbol_type`       |
| `sikshya_thousand_separator`                      | `_sikshya_thousand_separator`         |
| `sikshya_decimal_separator`                       | `_sikshya_decimal_separator`          |
| `sikshya_price_number_decimals`                   | `_sikshya_currency_decimal_places`    |
| `sikshya_payment_gateway_test_mode`               | `_sikshya_payment_test_mode`          |
| `sikshya_payment_gateway_enable_logging`          | `_sikshya_payment_enable_logging`     |
| `sikshya_payment_gateway_paypal_email`            | `_sikshya_paypal_email`               |
| `sikshya_payment_gateway_paypal_description`      | `_sikshya_paypal_description`         |

The migrator reads each legacy option and writes the rewrite key only when
the rewrite key is currently empty/unset (so values explicitly set by an
admin in the new plugin are not overwritten).

### Permalinks

`sikshya_permalinks` (legacy serialized array) is unpacked into the
rewrite's individual `_sikshya_rewrite_*` / `_sikshya_permalink_*` options:

| Legacy sub-key                 | Rewrite option                          |
|--------------------------------|-----------------------------------------|
| `sikshya_course_base`          | `_sikshya_rewrite_base_course`          |
| `sikshya_lesson_base`          | `_sikshya_rewrite_base_lesson`          |
| `sikshya_quiz_base`            | `_sikshya_rewrite_base_quiz`            |
| `sikshya_course_category_base` | `_sikshya_rewrite_tax_course_category`  |

Page IDs (`sikshya_account_page`, `sikshya_login_page`, etc.) describe
WordPress page posts that the legacy plugin emitted shortcodes into. The
rewrite uses virtual routes (slug-based), so we copy these page IDs into
new options `_sikshya_legacy_page_<slug>` for theme/template compatibility,
and ignore them otherwise.

---

## 8. User meta

Direct rename of billing fields so they pick up the rewrite's
`_sikshya_billing_*` storage:

| Legacy user-meta key       | Rewrite user-meta key            |
|----------------------------|----------------------------------|
| `billing_first_name`       | `_sikshya_billing_first_name`    |
| `billing_last_name`        | `_sikshya_billing_last_name`     |
| `billing_country`          | `_sikshya_billing_country`       |
| `billing_street_address_1` | `_sikshya_billing_address_1`     |
| `billing_street_address_2` | `_sikshya_billing_address_2`     |
| `billing_postcode`         | `_sikshya_billing_postcode`      |
| `billing_city`             | `_sikshya_billing_city`          |
| `billing_state`            | `_sikshya_billing_state`         |
| `billing_phone`            | `_sikshya_billing_phone`         |
| `billing_email`            | `_sikshya_billing_email`         |

`sikshya_avatar_attachment_id`, `sikshya_instructor_*`, and
`sikshya_student_*` keys are **kept as-is** because the rewrite re-uses the
same names.

---

## 9. Roles + capabilities

The rewrite already calls `add_role` for `sikshya_student`,
`sikshya_instructor`, and `sikshya_assistant` on activation, with broader
capability sets than the legacy plugin. Existing role assignments on users
are untouched (WordPress stores role assignments as user-meta, which is
preserved). The migrator runs `Installer::installRoles()` defensively so
sites that activated the new plugin via dropin-style upgrade still receive
the cap grants.

No legacy capabilities are removed — `manage_sikshya` etc. that existed in
the legacy plugin's dead-code paths (`Sikshya_Install::get_core_capabilities`)
were never assigned, so there's nothing to migrate.

---

## 10. No-op / skipped entities

- **Legacy `wp_sikshya_logs` table** — not migrated; informational only.
  The rewrite has its own `sikshya_logs` table (different schema).
- **Setup wizard state** (`sikshya_setup_wizard_ran`) — not migrated; the
  rewrite ships its own setup wizard.
- **Transients** (`sikshya_installing`, `_sikshya_activation_redirect`,
  `sikshya_test_remote_*`) — purely runtime; not migrated.

---

## 11. Execution flow

1. `LegacyMigrator::scheduleIfPending()` (called from `register_activation_hook`)
   checks the detector and, when legacy data is present, sets
   `sikshya_legacy_migration_pending = 'yes'` plus a fresh state row.
2. On `plugins_loaded` (priority 20) `LegacyMigrator::maybeRun()` reads the
   pending flag and runs a single batched chunk of work, then schedules a
   follow-up `wp_schedule_single_event('sikshya_run_legacy_migration_batch')`
   if there is more to do. Single-request budget is ~10s with batches of
   50 rows per step.
3. Each step records a per-step cursor and counts. Re-runs continue from
   where they left off; calling `executeBatch()` after `isComplete()`
   returns true is a no-op.
4. When all steps complete, the migrator writes `finished_at`, fires
   `do_action('sikshya_legacy_migration_completed', $state)` and emits an
   admin notice with the summary counts.

---

## 12. Observability

- Each run appends to `wp-content/uploads/sikshya-logs/legacy-migration-<timestamp>.log`.
- An admin notice on every Sikshya page links to a Tools page at
  `tools.php?page=sikshya-legacy-migration` exposing status, dry-run,
  retry, and reset actions.
- WP-CLI: `wp sikshya migrate-legacy [--dry-run] [--reset] [--status]`
  exposes the same engine.

---

## 13. Removal hatch

When the installed base is fully migrated, the entire `src/Migration/`
directory can be deleted. The new plugin gates the migrator behind
`class_exists(\Sikshya\Migration\LegacyMigrator::class)`, so deleting the
folder and regenerating the autoload classmap (`composer dump-autoload -o`)
is the only step required.
