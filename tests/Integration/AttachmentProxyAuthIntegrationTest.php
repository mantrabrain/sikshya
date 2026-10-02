<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Api\AttachmentProxyRoutes;
use Sikshya\Core\Plugin;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Identity resolution for the signed attachment download endpoint.
 *
 * Signed download URLs are meant to be clicked, and a click is a REST request
 * with cookies but no nonce — for which WordPress deliberately reports no
 * current user. The endpoint therefore reads the logged-in cookie itself.
 *
 * Because that loosens how a requester is identified, these tests pin both
 * directions: a real session resolves, and anything else does not. The token's
 * own identity check is covered end-to-end in
 * `e2e/tests/learner/lesson-attachments.spec.ts`.
 */
final class AttachmentProxyAuthIntegrationTest extends WP_UnitTestCase
{
    /** @var array<string, mixed> */
    private array $cookieBackup = [];

    public function set_up(): void
    {
        parent::set_up();
        $this->cookieBackup = $_COOKIE;
    }

    public function tear_down(): void
    {
        $_COOKIE = $this->cookieBackup;
        wp_set_current_user(0);
        parent::tear_down();
    }

    private function resolve(): int
    {
        $routes = new AttachmentProxyRoutes(Plugin::getInstance());
        $method = new \ReflectionMethod($routes, 'resolveRequesterId');
        $method->setAccessible(true);

        return (int) $method->invoke($routes, new WP_REST_Request('GET', '/sikshya/v1/file/x'));
    }

    public function test_no_session_and_no_cookie_resolves_to_nobody(): void
    {
        wp_set_current_user(0);
        unset($_COOKIE[LOGGED_IN_COOKIE]);

        $this->assertSame(0, $this->resolve());
    }

    public function test_an_already_resolved_rest_user_is_used(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user($uid);

        $this->assertSame($uid, $this->resolve());
    }

    /**
     * The case the fix exists for: a valid session whose REST request carried no
     * nonce, so `get_current_user_id()` is 0.
     */
    public function test_a_valid_logged_in_cookie_resolves_without_a_nonce(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user(0);
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($uid, time() + 3600, 'logged_in');

        $this->assertSame($uid, $this->resolve());
    }

    public function test_a_forged_cookie_resolves_to_nobody(): void
    {
        wp_set_current_user(0);
        $_COOKIE[LOGGED_IN_COOKIE] = 'not-a-real-cookie';

        $this->assertSame(0, $this->resolve());
    }

    /**
     * An expired session must not keep working just because the URL still
     * verifies; the token TTL and the session are separate limits.
     */
    public function test_an_expired_cookie_resolves_to_nobody(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user(0);
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($uid, time() - 10, 'logged_in');

        $this->assertSame(0, $this->resolve());
    }

    /**
     * A cookie minted for a different scheme must not be accepted as a
     * logged-in cookie.
     */
    public function test_a_cookie_for_another_scheme_resolves_to_nobody(): void
    {
        $uid = (int) self::factory()->user->create();
        wp_set_current_user(0);
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($uid, time() + 3600, 'auth');

        $this->assertSame(0, $this->resolve());
    }
}
