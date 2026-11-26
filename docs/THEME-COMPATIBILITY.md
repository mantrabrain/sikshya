# Theme compatibility

Written for theme authors, and for developers making Sikshya fit a theme they
did not write.

Sikshya renders its own front end: the course archive, single course, lesson and
quiz shells, cart, checkout, account. This document is the contract for changing
any of that from a theme, without forking the plugin and without a child plugin.

Three mechanisms, in order of how much you should reach for them:

1. **[Catalog blocks](#1-catalog-blocks)** — build the pages yourself out of
   blocks that already follow your theme.
2. **[Template overrides](#2-template-overrides)** — replace one of Sikshya's
   templates or partials with your own file.
3. **[Theme support](#3-theme-support)** — tell Sikshya to inherit your palette
   and type instead of using its own brand colours.

---

## 1. Catalog blocks

Since 1.0.21 Sikshya ships three blocks that render course listings and inherit
the active theme rather than imposing the plugin's design:

| Block | Name | What it is |
| --- | --- | --- |
| Course Grid | `sikshya/course-grid` | Grid or list of course cards |
| Course Categories | `sikshya/course-categories` | Tiles, pills or a list of category links |
| Course Search | `sikshya/course-search` | Search field pointed at the course archive |

They are the recommended way to build a catalog page, a "featured courses"
section on the home page, or a "continue learning" strip for signed-in users.

### Why they look like your theme

`sikshya/course-grid` declares colour, spacing, typography and border supports
and emits `get_block_wrapper_attributes()`, so everything the user picks in the
block sidebar reaches the DOM. Beyond that:

- Colours resolve `--wp--preset--color--*` first and fall back to Sikshya's
  tokens, so a block theme's palette wins without any theme CSS.
- Radii read `--wp--custom--border-radius`, gaps read `--wp--style--block-gap`.
- Headings and body text set `font-family: inherit` and `font-size: inherit` —
  the card title takes your heading scale.
- The call-to-action carries `wp-element-button`, the class block themes style,
  so it matches every other button on the site. On a classic theme the plugin
  supplies a modest fallback button; on a block theme those fallbacks are
  `revert`ed so they cannot fight yours.

The older `sikshya/courses` block still exists and still renders the archive's
markup. It is kept for sites already using it; prefer `sikshya/course-grid` for
anything new.

### Styling the grid from a theme

Set the custom properties on the block's own class — no `!important` needed,
because the plugin's rules are single-class selectors:

```css
.wp-block-sikshya-course-grid {
  --sik-cg-surface: #fff;
  --sik-cg-border: #dcdcdc;
  --sik-cg-accent: var(--wp--preset--color--brand);
  --sik-cg-radius: 4px;
}
```

Column counts are properties too, so a theme can override the block's own
choice at a breakpoint it cares about:

```css
@media (min-width: 1400px) {
  .wp-block-sikshya-course-grid { --sikshya-grid-columns: 4; }
}
```

### Reshaping the query

```php
add_filter( 'sikshya_course_grid_query_args', function ( $args, $attributes, $page ) {
	// Hide courses tagged "internal" from every grid.
	$args['tax_query'][] = [
		'taxonomy' => 'sikshya_course_tag',
		'field'    => 'slug',
		'terms'    => [ 'internal' ],
		'operator' => 'NOT IN',
	];

	return $args;
}, 10, 3 );
```

### Reshaping one card's data

```php
add_filter( 'sikshya_course_grid_card_data', function ( $data, $course_id, $settings ) {
	$data['excerpt'] = my_theme_course_tagline( $course_id ) ?: $data['excerpt'];

	return $data;
}, 10, 3 );
```

---

## 2. Template overrides

Every template and partial Sikshya ships resolves through a loader that checks
your theme first. Copy the file you want to change out of the plugin's
`templates/` directory into a `sikshya/` folder in your theme, keeping the same
relative path:

```
wp-content/plugins/sikshya/templates/single-course.php
→ wp-content/themes/yourtheme/sikshya/single-course.php

wp-content/plugins/sikshya/templates/partials/course-card.php
→ wp-content/themes/yourtheme/sikshya/partials/course-card.php
```

A child theme's copy beats the parent's, and the parent's beats the plugin's. A
theme with no override gets the plugin's file, unchanged.

### What you can override

| File | Page |
| --- | --- |
| `archive-sik_course.php` | Course archive / catalog |
| `single-course.php` | Single course |
| `single-lesson.php` | Lesson shell |
| `single-quiz.php` | Quiz shell |
| `taxonomy-course-category.php` | A course category |
| `taxonomy-course-category-root.php` | The category index |
| `taxonomy-course-tag.php` | A course tag |
| `author.php` | Instructor profile |
| `courses-grid.php` | `[sikshya_courses]` / catalog page |
| `account.php` | Learner account shell |
| `learn.php` | Learn (course player) shell |
| `cart.php`, `checkout.php`, `order.php`, `login.php` | Commerce and auth |
| `partials/course-card.php` | Card used by the archive and shortcode |
| `partials/block-course-card.php` | Card used by `sikshya/course-grid` |
| `partials/course-archive-sidebar.php` | Archive filters sidebar |
| `partials/course-archive-toolbar.php` | Archive sort / view toolbar |
| `partials/course-discovery-breadcrumb.php` | Breadcrumb |
| `partials/single-course-reviews.php` | Reviews on a single course |
| `partials/learn-curriculum-outline.php` | Curriculum outline in the player |
| `partials/learn-assignment-panel.php` | Assignment panel |
| `partials/quiz-question-fieldset.php` | One quiz question |
| `partials/account-view-*.php` | Each account screen |

### Rules of the road

- **Partials are markup only.** The data they render is prepared before they
  load (`\Sikshya\Blocks\CourseGrid\CourseCardData` for the block card,
  `\Sikshya\Frontend\Site\*TemplateData` for the pages). Your override inherits
  every lookup and cache the plugin already did — do not re-query in the
  partial, and do not add a query per card.
- **Only `.php` files resolve.** A traversal or a non-PHP extension is refused,
  so nothing in the override folder can be turned into an include target.
- **Keep `sikshya_get_header()` / `sikshya_get_footer()`** if you override a
  full page template. They open the block-theme wrapper (`wp-site-blocks`,
  header and footer template parts) on a block theme and call `get_header()` /
  `get_footer()` on a classic one. Calling `get_header()` directly breaks block
  themes.
- **Check the plugin's copy on upgrade.** Sikshya does not version its
  templates, and an override pins the markup as of the day you copied it.

### Moving the override folder

If `sikshya/` collides with something your theme already has:

```php
add_filter( 'sikshya_theme_template_dir', function () {
	return 'lms';   // yourtheme/lms/single-course.php
} );
```

### Serving a template from somewhere else

```php
add_filter( 'sikshya_locate_template', function ( $path, $relative, $candidates ) {
	if ( $relative === 'single-course.php' && my_plugin_is_premium_course() ) {
		return MY_PLUGIN_DIR . 'templates/premium-course.php';
	}

	return $path;
}, 10, 3 );
```

### From PHP

```php
// Absolute path, theme override preferred.
$path = sikshya_locate_template( 'partials/course-card.php' );

// Load a part in an isolated scope (nothing from the caller leaks in).
sikshya_get_template_part( 'partials/course-card.php', [ 'course' => $post ] );
```

---

## 3. Theme support

By default Sikshya's own pages use Sikshya's brand colours. A theme can ask to
be followed instead:

```php
add_action( 'after_setup_theme', function () {
	add_theme_support( 'sikshya', [
		'palette'    => true,   // bind Sikshya's brand tokens to your palette
		'typography' => true,   // inherit your fonts
	] );
} );
```

With `palette` on, Sikshya's `--sikshya-primary`, `--sikshya-secondary` and
`--sikshya-accent` are bound to your `primary`, `secondary` and `accent` palette
slots — from `theme.json` on a block theme, or `editor-color-palette` on a
classic one. Slots you do not define are left alone, so a partial palette is
fine. Only literal colour values are accepted; a palette entry holding a
`var()` or an expression is ignored rather than pasted into a stylesheet.

### Content width

This one needs no opt-in, because matching your container is never a
regression. Sikshya reads `layout.contentSize` and `layout.wideSize` from
`theme.json`, or `$content_width` on a classic theme, and publishes them as:

```css
--sikshya-theme-content-size
--sikshya-theme-wide-size
```

Both have the plugin's own figures as fallbacks, so a theme that declares
neither is unaffected.

### Adding your own properties

```php
add_filter( 'sikshya_theme_compat_declarations', function ( $declarations, $support ) {
	$declarations[] = '--sikshya-radius:0';

	return $declarations;
}, 10, 2 );
```

---

## Block themes (full site editing)

Sikshya's pages are PHP templates served through `template_include`, which takes
precedence over the block template canvas. They work on a block theme today:
`sikshya_get_header()` / `sikshya_get_footer()` emit the `wp-site-blocks`
wrapper and your header and footer template parts, so the page sits inside your
site frame.

What this means in practice:

- Your header, footer, colours, fonts and widths apply.
- The course archive is **not** editable in the Site Editor. To build an archive
  you can edit there, make a page out of `sikshya/course-search`,
  `sikshya/course-categories` and `sikshya/course-grid` and set it as your
  catalog page.
- `block_template_part()` is used for the header and footer areas, so a theme
  that names those parts anything other than `header` / `footer` should provide
  the standard names as well.

## Reference

| Filter / hook | Purpose |
| --- | --- |
| `sikshya_locate_template` | Final say on which file is loaded |
| `sikshya_theme_template_dir` | Name of the theme's override folder |
| `sikshya_theme_compat_declarations` | Custom properties Sikshya emits |
| `sikshya_course_grid_query_args` | `WP_Query` args for a course grid |
| `sikshya_course_grid_card_data` | One card's view model |
| `sikshya_course_grid_max_enrolled_ids` | Cap on enrolled-course ids per grid |
| `sikshya_course_categories_block_args` | `get_terms()` args for the categories block |
| `sikshya_course_card_rating_vm` | Rating shown on a card (archive and grid) |

| Theme support | Purpose |
| --- | --- |
| `add_theme_support( 'sikshya', [ 'palette' => true ] )` | Inherit the theme palette |
| `add_theme_support( 'sikshya', [ 'typography' => true ] )` | Inherit the theme fonts |
| `add_theme_support( 'sikshya', [ 'layout' => false ] )` | Stop reading theme widths |
