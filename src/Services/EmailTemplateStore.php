<?php

namespace Sikshya\Services;

use Sikshya\Utils\RichText;

/**
 * Per-site email template overrides and custom templates.
 *
 * @package Sikshya\Services
 */
final class EmailTemplateStore
{
    private const OPTION = '_sikshya_email_template_store';

    /**
     * Raw store: id => [ enabled, subject, body_html, name, description, ...custom fields ]
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getStore(): array
    {
        $raw = get_option(self::OPTION, []);
        if (!is_array($raw)) {
            return [];
        }

        return $raw;
    }

    /**
     * @param array<string, array<string, mixed>> $store
     */
    public static function saveStore(array $store): bool
    {
        return update_option(self::OPTION, $store, false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getRow(string $id): ?array
    {
        $s = self::getStore();

        return isset($s[$id]) && is_array($s[$id]) ? $s[$id] : null;
    }

    /**
     * Merge catalog + store into a list row for REST (single template).
     *
     * @return array<string, mixed>|null
     */
    public static function getMerged(string $id): ?array
    {
        $row = self::getRow($id);
        $def = EmailTemplateCatalog::get($id);

        if ($def !== null) {
            return self::shapeMerged($id, $def, $row ?? []);
        }

        if ($row !== null && isset($row['template_type']) && (string) $row['template_type'] === 'custom') {
            return self::shapeCustomMerged($id, $row);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listMerged(): array
    {
        $out = [];
        foreach (EmailTemplateCatalog::definitions() as $id => $def) {
            $row = self::getRow($id);
            $out[] = self::shapeMerged($id, $def, $row ?? []);
        }

        foreach (self::getStore() as $id => $row) {
            if (!is_string($id) || !is_array($row)) {
                continue;
            }
            if (EmailTemplateCatalog::get($id) !== null) {
                continue;
            }
            if ((string) ($row['template_type'] ?? '') !== 'custom') {
                continue;
            }
            $out[] = self::shapeCustomMerged($id, $row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $def
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function shapeMerged(string $id, array $def, array $row): array
    {
        $enabled = array_key_exists('enabled', $row)
            ? Settings::isTruthy($row['enabled'])
            : true;

        $subject = isset($row['subject']) && (string) $row['subject'] !== ''
            ? (string) $row['subject']
            : (string) $def['default_subject'];

        $body = isset($row['body_html']) && (string) $row['body_html'] !== ''
            ? (string) $row['body_html']
            : (string) $def['default_body_html'];

        $name = isset($row['name']) && (string) $row['name'] !== ''
            ? (string) $row['name']
            : (string) $def['name'];

        $description = isset($row['description']) && (string) $row['description'] !== ''
            ? (string) $row['description']
            : (string) $def['description'];

        $recipient_to = isset($row['recipient_to']) && (string) $row['recipient_to'] !== ''
            ? self::sanitizeRecipientTo((string) $row['recipient_to'])
            : (string) ($def['recipient_to'] ?? '{{student_email}}');

        $gate = EmailTemplateGate::metadataFromCatalogDef($def);

        return array_merge(
            [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'event' => (string) $def['event'],
                'category' => (string) $def['category'],
                'recipient' => (string) $def['recipient'],
                'recipient_to' => $recipient_to,
                'template_type' => 'system',
                'enabled' => $enabled,
                'subject' => $subject,
                'body_html' => $body,
                'body_preview' => self::truncate(strip_tags($body)),
                'merge_tags' => $def['merge_tags'] ?? [],
            ],
            $gate
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function shapeCustomMerged(string $id, array $row): array
    {
        $subject = (string) ($row['subject'] ?? '');
        $body = (string) ($row['body_html'] ?? '');
        $event = (string) ($row['event'] ?? 'custom.manual');
        $recipient_to = (string) ($row['recipient_to'] ?? '');
        if ($recipient_to === '') {
            $legacy = (string) ($row['recipient'] ?? 'learner');
            if ($legacy === 'admin') {
                $recipient_to = '{{admin_email}}';
            } elseif ($legacy === 'instructor') {
                $recipient_to = '{{instructor_email}}';
            } else {
                $recipient_to = '{{student_email}}';
            }
        } else {
            $recipient_to = self::sanitizeRecipientTo($recipient_to);
        }

        $gate = EmailTemplateGate::metadataFromCustomEvent($event);

        return array_merge(
            [
                'id' => $id,
                'name' => (string) ($row['name'] ?? __('Custom email', 'sikshya')),
                'description' => (string) ($row['description'] ?? ''),
                'event' => $event,
                'category' => (string) ($row['category'] ?? 'custom'),
                'recipient' => 'custom',
                'recipient_to' => $recipient_to,
                'template_type' => 'custom',
                'enabled' => Settings::isTruthy($row['enabled'] ?? true),
                'subject' => $subject,
                'body_html' => $body,
                'body_preview' => self::truncate(strip_tags($body)),
                'merge_tags' => self::defaultCustomMergeTags(),
            ],
            $gate
        );
    }

    /**
     * @return list<string>
     */
    public static function defaultCustomMergeTags(): array
    {
        return [
            '{{site_name}}',
            '{{site_url}}',
            '{{student_name}}',
            '{{student_email}}',
            '{{learner_name}}',
            '{{learner_email}}',
            '{{instructor_name}}',
            '{{instructor_email}}',
            '{{admin_email}}',
            '{{course_title}}',
            '{{course_url}}',
            '{{certificate_url}}',
            '{{certificate_number}}',
        ];
    }

    public static function sanitizeRecipientTo(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '{{student_email}}';
        }
        if (function_exists('mb_substr')) {
            $s = mb_substr($s, 0, 500);
        } else {
            $s = substr($s, 0, 500);
        }

        return $s;
    }

    public static function sanitizeEventKey(string $event): string
    {
        $event = strtolower(trim($event));
        $clean = preg_replace('/[^a-z0-9._-]/', '', $event);
        $event = is_string($clean) ? $clean : $event;

        return $event !== '' ? $event : 'custom.manual';
    }

    public static function truncate(string $text, int $max = 140): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $max) {
                return $text;
            }

            return mb_substr($text, 0, $max - 1) . '…';
        }

        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 1) . '…';
    }

    /**
     * @param array<string, mixed> $patch
     * @return true|\WP_Error
     */
    public static function updateSystem(string $id, array $patch)
    {
        $def = EmailTemplateCatalog::get($id);
        if ($def === null) {
            return new \WP_Error('sikshya_unknown_template', __('Unknown template.', 'sikshya'), ['status' => 404]);
        }

        $deny = EmailTemplateGate::assertSystemEditableForId($id);
        if ($deny instanceof \WP_Error) {
            return $deny;
        }

        $store = self::getStore();
        $cur = isset($store[$id]) && is_array($store[$id]) ? $store[$id] : [];

        if (array_key_exists('enabled', $patch)) {
            $cur['enabled'] = (bool) $patch['enabled'];
        }
        if (isset($patch['subject'])) {
            $cur['subject'] = sanitize_text_field((string) $patch['subject']);
        }
        if (isset($patch['body_html'])) {
            $cur['body_html'] = wp_kses_post((string) $patch['body_html']);
        }
        if (isset($patch['name'])) {
            $cur['name'] = sanitize_text_field((string) $patch['name']);
        }
        if (isset($patch['description'])) {
            $cur['description'] = RichText::sanitize((string) $patch['description']);
        }
        if (isset($patch['recipient_to'])) {
            $cur['recipient_to'] = self::sanitizeRecipientTo((string) $patch['recipient_to']);
        }

        $store[$id] = $cur;

        return self::saveStore($store);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: string}|\WP_Error
     */
    public static function createCustom(array $data)
    {
        $id = 'custom_' . wp_generate_password(12, false, false);

        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new \WP_Error('sikshya_invalid_template', __('Name is required.', 'sikshya'), ['status' => 400]);
        }

        $recipient_to = isset($data['recipient_to']) ? self::sanitizeRecipientTo((string) $data['recipient_to']) : '{{student_email}}';

        $store = self::getStore();
        $event_key = self::sanitizeEventKey((string) ($data['event'] ?? 'custom.manual'));
        $deny = EmailTemplateGate::assertCustomEditable($event_key);
        if ($deny instanceof \WP_Error) {
            return $deny;
        }

        $store[$id] = [
            'template_type' => 'custom',
            'enabled' => Settings::isTruthy($data['enabled'] ?? true),
            'name' => $name,
            'description' => RichText::sanitize((string) ($data['description'] ?? '')),
            'event' => $event_key,
            'category' => sanitize_key((string) ($data['category'] ?? 'custom')),
            'recipient_to' => $recipient_to,
            'subject' => sanitize_text_field((string) ($data['subject'] ?? '')),
            'body_html' => wp_kses_post((string) ($data['body_html'] ?? '')),
        ];

        return self::saveStore($store) ? ['id' => $id] : new \WP_Error('sikshya_save_failed', __('Could not save template.', 'sikshya'), ['status' => 500]);
    }

    /**
     * @return true|\WP_Error
     */
    public static function updateCustom(string $id, array $patch)
    {
        $store = self::getStore();
        if (!isset($store[$id]) || !is_array($store[$id])) {
            return new \WP_Error('sikshya_unknown_template', __('Unknown template.', 'sikshya'), ['status' => 404]);
        }
        if ((string) ($store[$id]['template_type'] ?? '') !== 'custom') {
            return new \WP_Error('sikshya_invalid_template', __('Not a custom template.', 'sikshya'), ['status' => 400]);
        }

        $cur = $store[$id];

        $next_event = (string) ($cur['event'] ?? 'custom.manual');
        if (isset($patch['event'])) {
            $next_event = self::sanitizeEventKey((string) $patch['event']);
        }
        $deny = EmailTemplateGate::assertCustomEditable($next_event);
        if ($deny instanceof \WP_Error) {
            return $deny;
        }

        if (array_key_exists('enabled', $patch)) {
            $cur['enabled'] = (bool) $patch['enabled'];
        }
        if (isset($patch['subject'])) {
            $cur['subject'] = sanitize_text_field((string) $patch['subject']);
        }
        if (isset($patch['body_html'])) {
            $cur['body_html'] = wp_kses_post((string) $patch['body_html']);
        }
        if (isset($patch['name'])) {
            $cur['name'] = sanitize_text_field((string) $patch['name']);
        }
        if (isset($patch['description'])) {
            $cur['description'] = RichText::sanitize((string) $patch['description']);
        }
        if (isset($patch['event'])) {
            $cur['event'] = self::sanitizeEventKey((string) $patch['event']);
        }
        if (isset($patch['recipient_to'])) {
            $cur['recipient_to'] = self::sanitizeRecipientTo((string) $patch['recipient_to']);
        }

        $store[$id] = $cur;

        return self::saveStore($store);
    }

    /**
     * @return true|\WP_Error
     */
    public static function deleteCustom(string $id)
    {
        $store = self::getStore();
        if (!isset($store[$id]) || !is_array($store[$id])) {
            return new \WP_Error('sikshya_unknown_template', __('Unknown template.', 'sikshya'), ['status' => 404]);
        }
        if ((string) ($store[$id]['template_type'] ?? '') !== 'custom') {
            return new \WP_Error('sikshya_invalid_template', __('Cannot delete a system template.', 'sikshya'), ['status' => 400]);
        }

        unset($store[$id]);

        return self::saveStore($store);
    }
}
