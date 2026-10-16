<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Services\GlobalSettingsBootstrap;
use Sikshya\Services\Settings;
use WP_UnitTestCase;

/**
 * The "log out inactive users after (minutes)" setting.
 *
 * It had two faults, both of which applied to every install because the
 * setting defaults to 120 minutes.
 *
 * It discarded `$remember`, so a "Remember Me" login — an explicit request for
 * a long session, which WordPress answers with fourteen days — expired in two
 * hours instead, with nothing in the UI to explain why the site owner kept
 * being signed out.
 *
 * And it was not an idle timeout at all: it capped the absolute life of the
 * login cookie, so somebody halfway through marking a batch of assignments was
 * signed out mid-task at the two-hour mark whatever they were doing. The thing
 * its own label promises not to do was the only thing it did.
 */
final class SessionTimeoutIntegrationTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        Settings::set('session_timeout', 120);
        unset($_COOKIE[LOGGED_IN_COOKIE]);

        parent::tearDown();
    }

    public function testAnOrdinaryLoginIsCappedToTheConfiguredTimeout(): void
    {
        Settings::set('session_timeout', 30);

        $length = apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, 1, false);

        self::assertSame(
            30 * MINUTE_IN_SECONDS,
            (int) $length,
            'An ordinary login must use the configured timeout — that is what the setting is for.'
        );
    }

    public function testARememberedLoginKeepsTheLengthWordPressPromised(): void
    {
        Settings::set('session_timeout', 30);

        $length = apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, 1, true);

        self::assertSame(
            14 * DAY_IN_SECONDS,
            (int) $length,
            'Ticking “Remember Me” asks for a long session; shortening it to the idle timeout breaks a promise the login form made.'
        );
    }

    public function testTheTimeoutNeverLengthensASessionWordPressWantedShorter(): void
    {
        Settings::set('session_timeout', 1440);

        // The filter caps; it must never raise. A host or another plugin
        // shortening the session has a reason to.
        $length = apply_filters('auth_cookie_expiration', 10 * MINUTE_IN_SECONDS, 1, false);

        self::assertSame(
            10 * MINUTE_IN_SECONDS,
            (int) $length,
            'The setting is a cap, not a floor.'
        );
    }

    public function testTheTimeoutIsClampedToItsAdvertisedBounds(): void
    {
        Settings::set('session_timeout', 1);
        self::assertSame(
            15 * MINUTE_IN_SECONDS,
            GlobalSettingsBootstrap::sessionTimeoutSeconds(),
            'Below the minimum the field advertises, the minimum applies.'
        );

        Settings::set('session_timeout', 999999);
        self::assertSame(
            1440 * MINUTE_IN_SECONDS,
            GlobalSettingsBootstrap::sessionTimeoutSeconds(),
            'Above the maximum the field advertises, the maximum applies.'
        );
    }

    /**
     * Put a readable auth cookie in place for the given user and expiry.
     */
    private function seedCookie(int $user_id, int $expiration): void
    {
        $user = get_userdata($user_id);
        self::assertNotFalse($user);

        $_COOKIE[LOGGED_IN_COOKIE] = implode('|', [
            $user->user_login,
            (string) $expiration,
            'token-' . $user_id,
            'hmac',
        ]);
    }

    public function testAnActiveSessionIsExtendedPastTheHalfwayPoint(): void
    {
        Settings::set('session_timeout', 60);

        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        // 10 minutes left of a 60-minute window: well past halfway.
        $this->seedCookie($user_id, time() + 10 * MINUTE_IN_SECONDS);

        self::assertTrue(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'Somebody still working must be kept signed in — being logged out mid-task is the exact thing this setting says it will not do.'
        );
    }

    public function testAFreshSessionIsLeftAlone(): void
    {
        Settings::set('session_timeout', 60);

        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        // 50 minutes left of 60: nowhere near the halfway point.
        $this->seedCookie($user_id, time() + 50 * MINUTE_IN_SECONDS);

        self::assertFalse(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'Re-issuing a cookie on every request would be pointless work and would churn session tokens.'
        );
    }

    public function testARememberedSessionIsNotShortenedByTheExtension(): void
    {
        Settings::set('session_timeout', 60);

        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        // Fourteen days left: a remembered session. Re-issuing it as an
        // ordinary one would quietly cut it down to the idle timeout — the
        // original bug, reintroduced from the other end.
        $this->seedCookie($user_id, time() + 14 * DAY_IN_SECONDS);

        self::assertFalse(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'A remembered session must be left as it is.'
        );
    }

    public function testAnExpiredSessionIsNotRevived(): void
    {
        Settings::set('session_timeout', 60);

        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $this->seedCookie($user_id, time() - MINUTE_IN_SECONDS);

        // The timeout has to mean something. Extending a session that already
        // ran out would make it unreachable.
        self::assertFalse(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'An expired session must not be extended.'
        );
    }

    public function testARequestWithNoCookieIsLeftAlone(): void
    {
        Settings::set('session_timeout', 60);

        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        unset($_COOKIE[LOGGED_IN_COOKIE]);

        // An application password or bearer token has no cookie to extend, and
        // issuing one would hand that request a browser session it never asked
        // for.
        self::assertFalse(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'A non-cookie request must not be given a cookie.'
        );
    }

    public function testALoggedOutRequestIsLeftAlone(): void
    {
        Settings::set('session_timeout', 60);
        wp_set_current_user(0);
        $_COOKIE[LOGGED_IN_COOKIE] = 'someone|' . (time() + 60) . '|token|hmac';

        self::assertFalse(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'A visitor must never be issued a session.'
        );
    }

    public function testAnUnreadableCookieIsIgnored(): void
    {
        Settings::set('session_timeout', 60);

        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_COOKIE[LOGGED_IN_COOKIE] = 'not-a-wordpress-cookie';

        self::assertFalse(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'A cookie we cannot parse must be left to WordPress to reject.'
        );
    }

    public function testTheExtensionCanBeDeclinedByFilter(): void
    {
        Settings::set('session_timeout', 60);

        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $this->seedCookie($user_id, time() + 5 * MINUTE_IN_SECONDS);

        add_filter('sikshya_extend_active_session', static fn (): bool => false);

        // A site with a hard compliance ceiling on session length needs to be
        // able to turn sliding sessions off.
        self::assertFalse(
            GlobalSettingsBootstrap::shouldExtendSession(),
            'A site must be able to decline the extension.'
        );
    }
}
