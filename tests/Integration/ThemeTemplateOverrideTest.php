<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Frontend\Theme\TemplateLoader;
use Sikshya\Frontend\Theme\ThemeCompat;
use WP_UnitTestCase;

/**
 * A theme must be able to replace any Sikshya template, and must not be able
 * to reach outside the folder it was given.
 *
 * Every front-end template and partial now resolves through
 * {@see TemplateLoader}. Two things have to hold at once: an override in
 * `yourtheme/sikshya/` wins, and a theme with no override gets byte-for-byte
 * the file that used to be hard-coded — otherwise this is a breaking change
 * for every site already running.
 *
 * @group theme
 */
final class ThemeTemplateOverrideTest extends WP_UnitTestCase
{
    private string $childDir = '';

    private string $parentDir = '';

    public function set_up(): void
    {
        parent::set_up();

        $base = get_temp_dir() . 'sikshya-theme-test-' . wp_generate_password(8, false);
        $this->childDir = $base . '/child';
        $this->parentDir = $base . '/parent';

        wp_mkdir_p($this->childDir . '/sikshya/partials');
        wp_mkdir_p($this->parentDir . '/sikshya/partials');

        add_filter('stylesheet_directory', [$this, 'filterChildDir']);
        add_filter('template_directory', [$this, 'filterParentDir']);

        TemplateLoader::flushCache();
    }

    public function tear_down(): void
    {
        remove_filter('stylesheet_directory', [$this, 'filterChildDir']);
        remove_filter('template_directory', [$this, 'filterParentDir']);

        foreach ([$this->childDir, $this->parentDir] as $dir) {
            if ($dir !== '' && is_dir($dir)) {
                $this->deleteTree($dir);
            }
        }

        TemplateLoader::flushCache();

        parent::tear_down();
    }

    public function filterChildDir(): string
    {
        return $this->childDir;
    }

    public function filterParentDir(): string
    {
        return $this->parentDir;
    }

    public function test_plugin_copy_is_used_when_the_theme_has_no_override(): void
    {
        $path = TemplateLoader::locate('single-course.php');

        $this->assertStringEndsWith('/templates/single-course.php', $path);
        $this->assertFileExists($path);
        $this->assertStringNotContainsString($this->childDir, $path);
    }

    public function test_child_theme_override_wins(): void
    {
        $override = $this->childDir . '/sikshya/single-course.php';
        file_put_contents($override, '<?php // child');

        TemplateLoader::flushCache();

        $this->assertSame($override, TemplateLoader::locate('single-course.php'));
    }

    public function test_parent_theme_override_is_used_when_the_child_has_none(): void
    {
        $override = $this->parentDir . '/sikshya/archive-sik_course.php';
        file_put_contents($override, '<?php // parent');

        TemplateLoader::flushCache();

        $this->assertSame($override, TemplateLoader::locate('archive-sik_course.php'));
    }

    public function test_child_beats_parent_for_the_same_file(): void
    {
        file_put_contents($this->parentDir . '/sikshya/account.php', '<?php // parent');
        file_put_contents($this->childDir . '/sikshya/account.php', '<?php // child');

        TemplateLoader::flushCache();

        $this->assertSame(
            $this->childDir . '/sikshya/account.php',
            TemplateLoader::locate('account.php')
        );
    }

    public function test_partials_are_overridable(): void
    {
        $override = $this->childDir . '/sikshya/partials/course-card.php';
        file_put_contents($override, '<?php // card');

        TemplateLoader::flushCache();

        $this->assertSame($override, TemplateLoader::locate('partials/course-card.php'));
    }

    /**
     * Templates ask for 'partials/x.php'; older PHP callers ask for
     * 'templates/partials/x.php'. A theme author must only have to learn one.
     */
    public function test_both_spellings_resolve_to_the_same_file(): void
    {
        $override = $this->childDir . '/sikshya/partials/course-card.php';
        file_put_contents($override, '<?php // card');

        TemplateLoader::flushCache();

        $this->assertSame(
            TemplateLoader::locate('partials/course-card.php'),
            TemplateLoader::locate('templates/partials/course-card.php')
        );
        $this->assertSame($override, TemplateLoader::locate('templates/partials/course-card.php'));
    }

    public function test_sikshya_locate_template_helper_matches_the_loader(): void
    {
        $this->assertSame(
            TemplateLoader::locate('single-course.php'),
            sikshya_locate_template('single-course.php')
        );
    }

    /**
     * @dataProvider provideRejectedNames
     */
    public function test_paths_that_escape_the_template_folder_are_rejected(string $relative): void
    {
        $this->assertSame('', TemplateLoader::locate($relative), $relative . ' must not resolve');
    }

    /**
     * @return array<string, string[]>
     */
    public static function provideRejectedNames(): array
    {
        return [
            'parent traversal' => ['../../wp-config.php'],
            'nested traversal' => ['partials/../../../wp-config.php'],
            'not a template' => ['partials/course-card.php.jpg'],
            'html file' => ['archive.html'],
            'empty' => [''],
            'null byte' => ["single-course.php\0.jpg"],
        ];
    }

    public function test_override_folder_name_is_filterable(): void
    {
        wp_mkdir_p($this->childDir . '/lms-templates');
        $override = $this->childDir . '/lms-templates/single-course.php';
        file_put_contents($override, '<?php // moved');

        $filter = static function (): string {
            return 'lms-templates';
        };
        add_filter('sikshya_theme_template_dir', $filter);
        TemplateLoader::flushCache();

        $resolved = TemplateLoader::locate('single-course.php');

        remove_filter('sikshya_theme_template_dir', $filter);
        TemplateLoader::flushCache();

        $this->assertSame($override, $resolved);
    }

    /**
     * An empty folder name would make the theme root the override directory,
     * so an unrelated `single-course.php` in a theme would hijack the course
     * template. The filter must not be able to do that.
     */
    public function test_an_empty_override_folder_name_falls_back_to_the_default(): void
    {
        $filter = static function (): string {
            return '';
        };
        add_filter('sikshya_theme_template_dir', $filter);

        $name = TemplateLoader::themeDirName();

        remove_filter('sikshya_theme_template_dir', $filter);

        $this->assertSame(TemplateLoader::DEFAULT_DIR, $name);
    }

    public function test_render_passes_variables_and_isolates_scope(): void
    {
        $override = $this->childDir . '/sikshya/partials/probe.php';
        file_put_contents(
            $override,
            '<?php echo isset($given) ? $given : "missing"; echo isset($path) ? "LEAK" : "";'
        );

        TemplateLoader::flushCache();

        $out = TemplateLoader::render('partials/probe.php', ['given' => 'ok']);

        // 'ok' proves the variable arrived; no 'LEAK' proves the loader's own
        // locals ($path among them) are not visible to the part.
        $this->assertSame('ok', $out);
    }

    public function test_missing_partial_renders_nothing_rather_than_failing(): void
    {
        $this->assertSame('', TemplateLoader::render('partials/does-not-exist.php'));
    }

    /**
     * Layout widths are published whether or not the theme opts in: matching
     * the theme's content width is never a regression.
     */
    public function test_layout_widths_are_read_from_the_theme(): void
    {
        $GLOBALS['content_width'] = 1180;

        $widths = ThemeCompat::layoutWidths();

        $this->assertNotSame('', $widths['content']);
    }

    public function test_palette_is_not_inherited_unless_the_theme_asks(): void
    {
        remove_theme_support('sikshya');

        $support = ThemeCompat::themeSupport();

        $this->assertFalse($support['supported']);
        $this->assertFalse($support['palette'], 'A theme that never opted in must keep Sikshya brand colours.');
    }

    public function test_theme_support_flags_are_read(): void
    {
        add_theme_support('sikshya', ['palette' => true, 'typography' => true]);

        $support = ThemeCompat::themeSupport();

        remove_theme_support('sikshya');

        $this->assertTrue($support['supported']);
        $this->assertTrue($support['palette']);
        $this->assertTrue($support['typography']);
    }

    /**
     * A palette entry holding `var(--x)` or a gradient string would be pasted
     * straight into a `:root{}` declaration, so only literal colours pass.
     */
    /**
     * A palette entry holding `var(--x)` or a `}`-bearing string would be
     * pasted straight into a `:root{}` declaration, so only literal colours
     * pass. Driven through the real theme.json filter rather than a seam.
     */
    public function test_unsafe_palette_values_are_not_emitted(): void
    {
        if (!class_exists('WP_Theme_JSON_Data')) {
            $this->markTestSkipped('theme.json data filter is not available on this WordPress.');
        }

        add_theme_support('sikshya', ['palette' => true]);

        $filter = static function ($theme_json) {
            return $theme_json->update_with([
                'version' => 2,
                'settings' => [
                    'color' => [
                        'palette' => [
                            ['slug' => 'primary', 'color' => 'var(--evil); } body { display:none', 'name' => 'Primary'],
                            ['slug' => 'secondary', 'color' => '#336699', 'name' => 'Secondary'],
                        ],
                    ],
                ],
            ]);
        };

        add_filter('wp_theme_json_data_theme', $filter);
        // The resolver memoises per request; drop it so the filter is seen.
        wp_clean_theme_json_cache();

        $css = ThemeCompat::variablesCss();

        remove_filter('wp_theme_json_data_theme', $filter);
        wp_clean_theme_json_cache();
        remove_theme_support('sikshya');

        $this->assertStringNotContainsString('display:none', $css, 'An unsafe colour escaped into CSS.');
        $this->assertStringNotContainsString('var(--evil', $css);
        // The safe entry in the same palette still comes through.
        $this->assertStringContainsString('#336699', $css);
    }

    private function deleteTree(string $dir): void
    {
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
