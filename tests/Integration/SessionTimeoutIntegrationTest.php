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

    /**
     * Put a real session token in place and point the cookie at it.
     *
     * @return array{0: int, 1: string} user id and session token
     */
    private function seedRealSession(int $expires_in): array
    {
        $user_id = (int) self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $manager = \WP_Session_Tokens::get_instance($user_id);
        $token = $manager->create(time() + $expires_in);

        // Identifying data WordPress records at login, so a session that is
        // replaced rather than updated is detectable.
        $manager->update($token, array_merge((array) $manager->get($token), [
            'ip' => '203.0.113.7',
            'ua' => 'SeededAgent/1.0',
            'login' => time() - HOUR_IN_SECONDS,
        ]));

        $user = get_userdata($user_id);
        $_COOKIE[LOGGED_IN_COOKIE] = implode('|', [
            $user->user_login,
            (string) (time() + $expires_in),
            $token,
            'hmac',
        ]);

        return [$user_id, $token];
    }

    public function testExtendingKeepsTheSameSessionToken(): void
    {
        Settings::set('session_timeout', 60);

        // `send_auth_cookies` off is how core's own tests exercise this: the
        // session manager still runs, but no header is emitted.
        add_filter('send_auth_cookies', '__return_false');

        [$user_id, $token] = $this->seedRealSession(5 * MINUTE_IN_SECONDS);

        GlobalSettingsBootstrap::extendSession();

        $manager = \WP_Session_Tokens::get_instance($user_id);

        /*
         * A new token would invalidate every nonce already rendered into the
         * page, because a REST nonce is derived from the token. The user would
         * press "Mark as complete" and get "Cookie check failed" at the exact
         * moment their session was extended. This is the regression the full
         * browser suite caught — 21 specs failing on rest_cookie_invalid_nonce
         * — so it is pinned here rather than left to be rediscovered.
         */
        self::assertNotNull(
            $manager->get($token),
            'Extending must keep the same session token, or every nonce on the page stops working.'
        );

        /*
         * The count is what actually distinguishes the two behaviours. Minting
         * a new token does not remove the old one — WordPress keeps both until
         * they expire — so asserting the old token is still there passes either
         * way. The browser suite caught the regression and this assertion did
         * not, until it counted.
         *
         * In a browser the new cookie replaces the old one, so every nonce
         * already on the page is verified against a token the request no longer
         * uses: 21 specs failed on rest_cookie_invalid_nonce. A test cannot see
         * that directly, because `wp_set_auth_cookie()` does not write back to
         * `$_COOKIE`, so the session count is the honest proxy.
         */
        self::assertCount(
            1,
            $manager->get_all(),
            'Extending must update the existing session, not open a second one — a new token invalidates every nonce the browser already has.'
        );
    }

    public function testExtendingKeepsAnAlreadyIssuedNonceValid(): void
    {
        Settings::set('session_timeout', 60);
        add_filter('send_auth_cookies', '__return_false');

        $this->seedRealSession(5 * MINUTE_IN_SECONDS);

        // Minted as the page would mint it, before the extension happens.
        $nonce = wp_create_nonce('wp_rest');

        GlobalSettingsBootstrap::extendSession();

        self::assertNotFalse(
            wp_verify_nonce($nonce, 'wp_rest'),
            'A nonce rendered before the extension must still verify after it — otherwise the next click fails.'
        );
    }

    public function testExtendingPushesTheSessionExpiryOut(): void
    {
        Settings::set('session_timeout', 60);
        add_filter('send_auth_cookies', '__return_false');

        [$user_id, $token] = $this->seedRealSession(5 * MINUTE_IN_SECONDS);

        $before = (int) (\WP_Session_Tokens::get_instance($user_id)->get($token)['expiration'] ?? 0);

        GlobalSettingsBootstrap::extendSession();

        $after = (int) (\WP_Session_Tokens::get_instance($user_id)->get($token)['expiration'] ?? 0);

        // Otherwise "extending" does nothing and the user is still logged out
        // on schedule while busy.
        self::assertGreaterThan(
            $before,
            $after,
            'The session must actually last longer afterwards.'
        );

        $session = (array) \WP_Session_Tokens::get_instance($user_id)->get($token);

        // WordPress shows these on the user's "log out everywhere else" screen
        // and plugins read them for audit trails; replacing the session instead
        // of updating it throws them away.
        self::assertSame(
            '203.0.113.7',
            (string) ($session['ip'] ?? ''),
            'The recorded IP must survive an extension.'
        );
        self::assertSame(
            'SeededAgent/1.0',
            (string) ($session['ua'] ?? ''),
            'The recorded user agent must survive an extension.'
        );
    }

    public function testExtendingDoesNothingForAVisitor(): void
    {
        add_filter('send_auth_cookies', '__return_false');
        wp_set_current_user(0);

        GlobalSettingsBootstrap::extendSession();

        self::assertSame(0, get_current_user_id(), 'A visitor must not be given a session.');
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
