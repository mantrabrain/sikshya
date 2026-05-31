<?php

declare(strict_types=1);

namespace Sikshya\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;

/**
 * Source-level regression guard for the bundle gate added to
 * `AdminRestRoutes::setCourseType`.
 *
 * Background: pre-fix, the endpoint enforced a feature+addon gate only for
 * `course_type=subscription` and let `course_type=bundle` through
 * unconditionally. The client-side CreateCourseModal hides bundle on Free,
 * but a direct API call would still flip `_sikshya_course_type=bundle` —
 * a server-side licensing bypass.
 *
 * The fix mirrors the existing subscription gate for bundle, requiring both
 * the `course_bundles` feature (licensing tier) AND the addon being enabled.
 *
 * @see \Sikshya\Api\AdminRestRoutes::setCourseType
 */
final class SetCourseTypeBundleGateTest extends TestCase
{
    private const ROUTES_FILE = __DIR__ . '/../../../src/Api/AdminRestRoutes.php';

    private function loadSetCourseTypeBody(): string
    {
        $src = file_get_contents(self::ROUTES_FILE);
        self::assertIsString($src);

        if (!preg_match('/public function setCourseType\s*\([^)]*\)[^{]*\{/', $src, $m, PREG_OFFSET_CAPTURE)) {
            self::fail('Could not locate setCourseType in AdminRestRoutes. Has it been renamed?');
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $len = strlen($src);
        for ($i = $start; $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $start, $i - $start);
                }
            }
        }
        self::fail('Brace-unbalanced source while extracting setCourseType.');
    }

    public function testGatesBundleBehindBothFeatureAndAddon(): void
    {
        $body = $this->loadSetCourseTypeBody();

        self::assertMatchesRegularExpression(
            "/\\\$type\\s*===\\s*'bundle'/",
            $body,
            'setCourseType must include an explicit `$type === \'bundle\'` branch — without it, the client can flip course_type to bundle on Free tier via direct API call.'
        );

        // The bundle branch must check BOTH the licensing tier capability AND
        // the addon being enabled — same defense-in-depth as the existing
        // subscription gate (which served as the model for this fix).
        self::assertMatchesRegularExpression(
            "/TierCapabilities::feature\\s*\\(\\s*'course_bundles'\\s*\\)/",
            $body,
            'Bundle gate must check `TierCapabilities::feature(\'course_bundles\')`.'
        );
        self::assertMatchesRegularExpression(
            "/Addons::isEnabled\\s*\\(\\s*'course_bundles'\\s*\\)/",
            $body,
            'Bundle gate must also check `Addons::isEnabled(\'course_bundles\')` so the addon being disabled still blocks bundle creation.'
        );
    }

    public function testBundleGateRunsBeforeMetaWrite(): void
    {
        $body = $this->loadSetCourseTypeBody();

        $gatePos = strpos($body, "'course_bundles'");
        $writePos = strpos($body, "update_post_meta");
        self::assertIsInt($gatePos, 'course_bundles gate string not found.');
        self::assertIsInt($writePos, 'update_post_meta call not found.');
        self::assertLessThan(
            $writePos,
            $gatePos,
            'Bundle gate must run BEFORE update_post_meta(_sikshya_course_type) so a denied request never mutates state.'
        );
    }
}
