import { __ } from './i18n';

/**
 * Sikshya email template trigger keys (WordPress actions / internal hooks).
 * Must match `CustomEmailTemplateHookDispatcher` (PHP) and `EmailTemplateCatalog` / `do_action` usage.
 */
export type EmailTriggerEventOption = {
  /** Stored in template `event` (sanitized server-side). */
  key: string;
  title: string;
  /** Shown in the badge next to the title (often same as key; readable for “no event”). */
  badgeLabel: string;
  description: string;
  /** “No event” row uses envelope styling; others use bolt. */
  kind: 'none' | 'event';
};

export const SIKSHYA_EMAIL_TRIGGER_EVENTS: EmailTriggerEventOption[] = [
  {
    key: 'custom.manual',
    title: __('No event (sequence only)', 'sikshya'),
    badgeLabel: 'custom.manual',
    description: __(
      'Use when this template is only sent from automations or manual sends — not tied to a live hook.',
      'sikshya'
    ),
    kind: 'none',
  },
  {
    key: 'user_register',
    title: __('New account registered', 'sikshya'),
    badgeLabel: 'user_register',
    description: __('WordPress fires this when a new user account is created (welcome-style emails).', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_user_enrolled',
    title: __('Learner enrolled in a course', 'sikshya'),
    badgeLabel: 'sikshya_user_enrolled',
    description: __('Triggered when a learner successfully enrolls in a course.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_user_unenrolled',
    title: __('Learner unenrolled', 'sikshya'),
    badgeLabel: 'sikshya_user_unenrolled',
    description: __('Triggered when a learner is removed or unenrolls from a course.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_course_completed',
    title: __('Course completed', 'sikshya'),
    badgeLabel: 'sikshya_course_completed',
    description: __('Triggered when a learner completes all required content in a course.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_certificate_issued',
    title: __('Certificate issued', 'sikshya'),
    badgeLabel: 'sikshya_certificate_issued',
    description: __('Triggered when a certificate is generated for the learner.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_order_fulfilled',
    title: __('Order fulfilled', 'sikshya'),
    badgeLabel: 'sikshya_order_fulfilled',
    description: __('Triggered when a purchase is fulfilled and course access is granted.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_assignment_submitted',
    title: __('Assignment submitted', 'sikshya'),
    badgeLabel: 'sikshya_assignment_submitted',
    description: __('Triggered when a learner submits an assignment.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya.scheduled_reminder',
    title: __('Scheduled / progress reminder', 'sikshya'),
    badgeLabel: 'sikshya.scheduled_reminder',
    description: __('Generic progress nudges (automation), not the same as Pro drip unlock emails.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_drip_lesson_unlocked',
    title: __('Drip: lesson unlocked', 'sikshya'),
    badgeLabel: 'sikshya_drip_lesson_unlocked',
    description: __(
      'Fires when Sikshya Pro drip cron unlocks a lesson for a learner. Pair with Content drip + Drip notifications.',
      'sikshya'
    ),
    kind: 'event',
  },
  {
    key: 'sikshya_drip_course_unlocked',
    title: __('Drip: course schedule unlocked', 'sikshya'),
    badgeLabel: 'sikshya_drip_course_unlocked',
    description: __('Fires when a course-wide drip rule opens the full curriculum for a learner.', 'sikshya'),
    kind: 'event',
  },
  {
    key: 'sikshya_certificate_row_created',
    title: __('Certificate row created', 'sikshya'),
    badgeLabel: 'sikshya_certificate_row_created',
    description: __('Lower-level hook when a certificate database row is created (advanced / integrations).', 'sikshya'),
    kind: 'event',
  },
];

export function findTriggerEventOption(key: string): EmailTriggerEventOption | undefined {
  const k = key.trim();
  return SIKSHYA_EMAIL_TRIGGER_EVENTS.find((o) => o.key === k);
}

/** If the stored key is not in the catalog, surface it as a legacy row so the user can change it. */
export function resolveTriggerOptionsForValue(value: string): EmailTriggerEventOption[] {
  const v = value.trim();
  const base = [...SIKSHYA_EMAIL_TRIGGER_EVENTS];
  if (!v || findTriggerEventOption(v)) {
    return base;
  }
  return [
    ...base,
    {
      key: v,
      title: __('Custom / legacy event', 'sikshya'),
      badgeLabel: v,
      description: __(
        'This key is not in the standard list. Choose another hook or keep it for custom integrations.',
        'sikshya'
      ),
      kind: 'event' as const,
    },
  ];
}
