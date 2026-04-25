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
    title: 'No event (sequence only)',
    badgeLabel: 'custom.manual',
    description: 'Use when this template is only sent from automations or manual sends — not tied to a live hook.',
    kind: 'none',
  },
  {
    key: 'user_register',
    title: 'New account registered',
    badgeLabel: 'user_register',
    description: 'WordPress fires this when a new user account is created (welcome-style emails).',
    kind: 'event',
  },
  {
    key: 'sikshya_user_enrolled',
    title: 'Learner enrolled in a course',
    badgeLabel: 'sikshya_user_enrolled',
    description: 'Triggered when a learner successfully enrolls in a course.',
    kind: 'event',
  },
  {
    key: 'sikshya_user_unenrolled',
    title: 'Learner unenrolled',
    badgeLabel: 'sikshya_user_unenrolled',
    description: 'Triggered when a learner is removed or unenrolls from a course.',
    kind: 'event',
  },
  {
    key: 'sikshya_course_completed',
    title: 'Course completed',
    badgeLabel: 'sikshya_course_completed',
    description: 'Triggered when a learner completes all required content in a course.',
    kind: 'event',
  },
  {
    key: 'sikshya_certificate_issued',
    title: 'Certificate issued',
    badgeLabel: 'sikshya_certificate_issued',
    description: 'Triggered when a certificate is generated for the learner.',
    kind: 'event',
  },
  {
    key: 'sikshya_order_fulfilled',
    title: 'Order fulfilled',
    badgeLabel: 'sikshya_order_fulfilled',
    description: 'Triggered when a purchase is fulfilled and course access is granted.',
    kind: 'event',
  },
  {
    key: 'sikshya_assignment_submitted',
    title: 'Assignment submitted',
    badgeLabel: 'sikshya_assignment_submitted',
    description: 'Triggered when a learner submits an assignment.',
    kind: 'event',
  },
  {
    key: 'sikshya.scheduled_reminder',
    title: 'Scheduled / progress reminder',
    badgeLabel: 'sikshya.scheduled_reminder',
    description: 'Generic progress nudges (automation), not the same as Pro drip unlock emails.',
    kind: 'event',
  },
  {
    key: 'sikshya_drip_lesson_unlocked',
    title: 'Drip: lesson unlocked',
    badgeLabel: 'sikshya_drip_lesson_unlocked',
    description:
      'Fires when Sikshya Pro drip cron unlocks a lesson for a learner. Pair with Content drip + Drip notifications.',
    kind: 'event',
  },
  {
    key: 'sikshya_drip_course_unlocked',
    title: 'Drip: course schedule unlocked',
    badgeLabel: 'sikshya_drip_course_unlocked',
    description: 'Fires when a course-wide drip rule opens the full curriculum for a learner.',
    kind: 'event',
  },
  {
    key: 'sikshya_certificate_row_created',
    title: 'Certificate row created',
    badgeLabel: 'sikshya_certificate_row_created',
    description: 'Lower-level hook when a certificate database row is created (advanced / integrations).',
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
      title: 'Custom / legacy event',
      badgeLabel: v,
      description: 'This key is not in the standard list. Choose another hook or keep it for custom integrations.',
      kind: 'event' as const,
    },
  ];
}
