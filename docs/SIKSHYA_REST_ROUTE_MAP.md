# Sikshya: Legacy AJAX → REST route map

WordPress REST base: `/wp-json/sikshya/v1/`

Admin routes use cookie session + `X-WP-Nonce: wp_rest` (via `wp.apiFetch`).  
Mobile uses `Authorization: Bearer <jwt>` from `POST /sikshya/v1/auth/login`.

## Course builder & curriculum (CourseAjax)

| Legacy `action` | Target REST route | Method |
|----------------|-------------------|--------|
| `sikshya_save_course_builder` | `/admin/course-builder/save` | POST |
| `sikshya_save_course` | `/admin/course-builder/save` | POST |
| `sikshya_create_content` | `/admin/curriculum/content` | POST |
| `sikshya_link_content_to_chapter` | `/admin/curriculum/content/link` | POST |
| `sikshya_load_curriculum` | `/admin/curriculum` | GET `?course_id=` |
| `sikshya_save_content_type` | `/admin/curriculum/content-item` | POST |
| `sikshya_save_chapter_order` | `/admin/curriculum/chapter-order` | POST |
| `sikshya_save_lesson_order` | `/admin/curriculum/lesson-order` | POST |
| `sikshya_create_chapter` | `/admin/curriculum/chapters` | POST |
| `sikshya_update_chapter` | `/admin/curriculum/chapters` | PUT |
| `sikshya_load_chapter_data` | `/admin/curriculum/chapters/(?P<id>\\d+)` | GET |
| `sikshya_bulk_delete_items` | `/admin/curriculum/bulk-delete` | POST |
| `sikshya_course_list` | `/admin/courses` | GET |
| `sikshya_course_delete` | `/admin/courses/(?P<id>\\d+)` | DELETE |
| Template/modal loaders | `/admin/templates/(?P<name>[a-z0-9_-]+)` | GET/POST (future) |

## Categories (CategoriesAjax)

| Legacy | Target |
|--------|--------|
| `sikshya_save_category` | `/admin/taxonomies/course-category` | POST |
| `sikshya_delete_category` | `/admin/taxonomies/course-category/(?P<id>\\d+)` | DELETE |

## Settings (SettingsAjax)

| Legacy | Target |
|--------|--------|
| `sikshya_save_settings` | `/admin/settings` | POST |
| `sikshya_load_settings_tab` | `/admin/settings/tab/(?P<tab>[a-z0-9_-]+)` | GET |
| `sikshya_reset_settings` | `/admin/settings/reset` | POST |
| `sikshya_export_settings` | `/admin/settings/export` | GET |
| `sikshya_import_settings` | `/admin/settings/import` | POST |

## Lessons admin (LessonController / LessonAjax)

| Legacy | Target |
|--------|--------|
| `sikshya_lesson_*` | `/admin/lessons` CRUD | |
| `sikshya_save_lesson` etc. | `/admin/lessons` | POST/PUT/DELETE |

## Licensing (React admin / refresh)

| Purpose | Target | Method |
|--------|--------|--------|
| Feature catalog + Pro gates (same as `window.sikshyaReact.licensing`) | `/admin/licensing` | GET |

## List tables / misc

| Legacy | Target |
|--------|--------|
| `sikshya_delete_course` (list-table.js) | `/admin/courses/{id}` | DELETE |
| `sikshya_tools_action` | `/admin/tools` | POST |
| `sikshya_user_action` | `/admin/users` | POST |
| `sikshya_report_action` | `/admin/reports` | POST |
| `sikshya_admin_action` | `/admin/misc` | POST |
| `sikshya_load_table_data` | `/admin/datatable` | POST |

## Frontend (CourseController / FrontendAjax)

| Legacy | Target |
|--------|--------|
| `sikshya_enroll_course` | `/enrollments` or `/courses/(id)/enroll` | POST |
| `sikshya_search_courses` | `/courses?search=` | GET |
| `sikshya_frontend_action` | `/public/*` | per sub-action |

## Controllers/CourseController (duplicate REST)

Consolidate under `src/Api/Api.php`; remove duplicate `register_rest_route` from `src/Controllers/CourseController.php` when unified.
