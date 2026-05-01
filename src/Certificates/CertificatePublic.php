<?php

namespace Sikshya\Certificates;

use Sikshya\Database\Repositories\CertificateRepository;
use Sikshya\Services\PermalinkService;

/**
 * Public hash-based certificate page for the free plugin.
 *
 * Pretty: /{certificate_base}/{hash}
 * Plain:  /{certificate_base}/?hash={hash}
 *
 * When Pro is active, its advanced handler can take over; this keeps the free plugin functional.
 */
final class CertificatePublic
{
    private const QUERY_VAR = 'sikshya_cert_hash';

    public static function boot(): void
    {
        add_filter('query_vars', [self::class, 'queryVars']);
        add_action('init', [self::class, 'registerRewriteRules'], 21);
        add_action('template_redirect', [self::class, 'templateRedirect'], 0);
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public static function queryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function registerRewriteRules(): void
    {
        if (PermalinkService::isPlainPermalinks()) {
            return;
        }
        $p = PermalinkService::get();
        $base = isset($p['rewrite_base_certificate']) ? PermalinkService::sanitizeSlug((string) $p['rewrite_base_certificate']) : 'certificates';
        add_rewrite_rule('^' . preg_quote($base, '/') . '/([a-f0-9]{64})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    public static function templateRedirect(): void
    {
        /**
         * Commercial add-on may register a truthy filter to take over certificate URLs.
         *
         * @param bool $skip Skip core certificate rendering.
         */
        if (apply_filters('sikshya_certificate_public_skip_core_handler', false)) {
            return;
        }

        $hash = '';
        $rv = get_query_var(self::QUERY_VAR);
        if (is_string($rv) && $rv !== '') {
            $hash = $rv;
        } elseif (!empty($_GET['hash'])) {
            // Plain permalink: only handle when request path matches the certificate base.
            $p = PermalinkService::get();
            $base = isset($p['rewrite_base_certificate']) ? PermalinkService::sanitizeSlug((string) $p['rewrite_base_certificate']) : 'certificates';
            $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
            $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
            $want = '/' . trim($base, '/') . '/';
            if ($path === $want || rtrim($path, '/') . '/' === $want) {
                $hash = (string) $_GET['hash'];
            }
        }

        if ($hash === '') {
            return;
        }

        self::serveByHash($hash);
    }

    private static function serveByHash(string $hash): void
    {
        $clean = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $hash) ?? '');
        if (strlen($clean) !== 64) {
            self::render404();
        }

        $share_url = CertificateRenderer::publicUrlForHash($clean);

        // 1) Issued certificate (per-user hash) — unique for each course completion.
        $repo = new CertificateRepository();
        $row = $repo->findByVerificationCode($clean);
        if ($row && (string) $row->status !== 'active') {
            status_header(410);
            nocache_headers();
            header('Content-Type: text/html; charset=UTF-8');
            $status = sanitize_key((string) ($row->status ?? ''));
            $msg = $status === 'revoked'
                ? __('This certificate has been revoked and is no longer valid.', 'sikshya')
                : __('This certificate is not active and cannot be displayed.', 'sikshya');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>' . esc_html__('Certificate unavailable', 'sikshya') . '</title></head><body style="font-family:system-ui;padding:2rem;max-width:40rem;">';
            echo '<h1 style="margin-top:0;">' . esc_html__('Certificate unavailable', 'sikshya') . '</h1>';
            echo '<p>' . esc_html($msg) . '</p></body></html>';
            exit;
        }
        if ($row && (string) $row->status === 'active') {
            $user = get_userdata((int) $row->user_id);
            $learner = $user ? $user->display_name : '';
            $course_title = get_the_title((int) $row->course_id) ?: '';
            $issued = (string) $row->issued_date;
            $serial = (string) $row->certificate_number;
            $template_id = isset($row->template_post_id) ? (int) $row->template_post_id : 0;

            $body = '';
            if ($template_id > 0) {
                $post = get_post($template_id);
                if ($post && $post->post_status === 'publish') {
                    $body = (string) $post->post_content;
                }
            }
            if ($body === '') {
                $body = self::defaultTemplate();
            }

            $qr = CertificateRenderer::qrImgTag($share_url);

            $repl = [
                // Legacy tokens
                '{{learner_name}}' => esc_html($learner),
                '{{course_title}}' => esc_html($course_title),
                '{{issued_date}}' => esc_html($issued),

                // Builder-ish tokens (so templates can be shared between free and Pro)
                '{{student_name}}' => esc_html($learner),
                '{{course_name}}' => esc_html($course_title),
                '{{completion_date}}' => esc_html($issued),

                // Shared tokens
                '{{certificate_number}}' => esc_html($serial),
                '{{verification_code}}' => esc_html($clean),
                '{{verification_url}}' => esc_url($share_url),
                '{{document_url}}' => esc_url($share_url),
                '{{site_name}}' => esc_html(get_bloginfo('name')),
                '{{qr_image}}' => $qr,
            ];

            $body = strtr($body, $repl);
            $can_controls = current_user_can('edit_posts');
            $uid = get_current_user_id();
            if (!$can_controls) {
                $can_controls = $uid > 0 && $uid === (int) $row->user_id;
            }
            nocache_headers();
            header('Content-Type: text/html; charset=UTF-8');
            $issuer = (string) get_bloginfo('name');
            $meta_line = sprintf(
                esc_html__('Credential ID %1$s · Issued by %2$s · Issued date %3$s', 'sikshya'),
                $clean,
                $issuer !== '' ? $issuer : '—',
                $issued !== '' ? $issued : '—'
            );
            echo CertificateRenderer::wrap($body, $course_title, $template_id, $share_url, $clean, $can_controls, $meta_line); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        // 2) Template preview (stable per-template hash) — used for template “Preview” in admin UI.
        $q = new \WP_Query([
            'post_type' => 'sikshya_certificate',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_sikshya_certificate_preview_hash',
                    'value' => $clean,
                    'compare' => '=',
                ],
            ],
        ]);
        $template_id = isset($q->posts[0]) ? (int) $q->posts[0] : 0;
        if ($template_id <= 0) {
            self::render404();
        }
        $post = get_post($template_id);
        $body = ($post && $post->post_type === 'sikshya_certificate') ? (string) $post->post_content : '';
        if ($body === '') {
            self::render404();
        }

        $qr = CertificateRenderer::qrImgTag($share_url);
        $repl = [
            '{{student_name}}' => esc_html__('Student name', 'sikshya'),
            '{{course_name}}' => esc_html__('Course name', 'sikshya'),
            '{{completion_date}}' => esc_html(date_i18n(get_option('date_format'))),
            '{{certificate_number}}' => esc_html__('CERT-0001', 'sikshya'),
            '{{verification_code}}' => esc_html($clean),
            '{{verification_url}}' => esc_url($share_url),
            '{{document_url}}' => esc_url($share_url),
            '{{site_name}}' => esc_html(get_bloginfo('name')),
            '{{qr_image}}' => $qr,
            // legacy tokens
            '{{learner_name}}' => esc_html__('Student name', 'sikshya'),
            '{{course_title}}' => esc_html__('Course name', 'sikshya'),
            '{{issued_date}}' => esc_html(date_i18n(get_option('date_format'))),
        ];

        $body = strtr($body, $repl);
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        $issuer = (string) get_bloginfo('name');
        $meta_line = sprintf(
            esc_html__('Credential ID %1$s · Issued by %2$s · Issued date %3$s', 'sikshya'),
            $clean,
            $issuer !== '' ? $issuer : '—',
            esc_html(date_i18n(get_option('date_format')))
        );
        echo CertificateRenderer::wrap($body, wp_strip_all_tags(get_the_title($template_id)), $template_id, $share_url, $clean, current_user_can('edit_posts'), $meta_line); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function defaultTemplate(): string
    {
        return '<div class="sikshya-cert-default">'
            . '<h1>{{course_title}}</h1>'
            . '<p>' . esc_html__('Awarded to', 'sikshya') . ' <strong>{{learner_name}}</strong></p>'
            . '<p>' . esc_html__('Certificate no.', 'sikshya') . ' <strong>{{certificate_number}}</strong></p>'
            . '<p>' . esc_html__('Issued', 'sikshya') . ' <strong>{{issued_date}}</strong></p>'
            . '<div class="qr">{{qr_image}}</div>'
            . '<p class="small"><a href="{{verification_url}}">' . esc_html__('Verify online', 'sikshya') . '</a></p>'
            . '</div>';
    }

    private static function render404(): void
    {
        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
        include get_404_template();
        exit;
    }
}

