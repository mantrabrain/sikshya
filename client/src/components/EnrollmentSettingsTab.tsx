import { useMemo, useState, type ReactNode } from 'react';
import { NavIcon } from './NavIcon';
import type { SettingsField, SettingsSection } from '../types/settingsSchema';
import { isTruthyCheckboxValue } from '../pages/settingsRenderField';
import { __ } from '../lib/i18n';

type Props = {
  tabSchema: SettingsSection[];
  renderField: (f: SettingsField) => React.ReactNode;
  /** Current form values (for cross-field hints). */
  draft?: Record<string, unknown>;
};

function sectionIconName(raw?: string): string {
  const s = (raw || '').trim();
  const fa = s.replace(/^fas\s+fa-/, '').replace(/^fa-/, '');
  switch (fa) {
    case 'shopping-cart':
      return 'chart';
    case 'check-circle':
      return 'badge';
    case 'user-plus':
      return 'users';
    case 'users':
      return 'users';
    case 'sign-out-alt':
      return 'layers';
    case 'lock':
      return 'cog';
    case 'calendar-alt':
      return 'tag';
    default:
      return fa || 'layers';
  }
}

function SectionShell(props: {
  title?: string;
  description?: string;
  icon?: string;
  locked?: boolean;
  lockedReason?: string;
  children: ReactNode;
}) {
  const { title, description, icon, locked, lockedReason, children } = props;
  return (
    <section
      className={`rounded-2xl border p-6 shadow-sm ${
        locked
          ? 'border-accent-200 bg-accent-50/50 dark:border-accent-900/50 dark:bg-accent-950/25'
          : 'border-slate-200/80 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/30'
      }`}
    >
      {title ? (
        <div className="mb-5 flex items-start gap-3">
          <span
            className={`mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg ${
              locked
                ? 'bg-accent-100 text-accent-700 dark:bg-accent-900/40 dark:text-accent-200'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
            }`}
          >
            <NavIcon name={locked ? 'badge' : sectionIconName(icon)} className="h-5 w-5" />
          </span>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{title}</h3>
              {locked ? (
                <span className="inline-flex items-center gap-1 rounded-md bg-accent-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-accent-700 dark:bg-accent-900/50 dark:text-accent-200">
                  <span aria-hidden>★</span> {__('Pro', 'sikshya')}
                </span>
              ) : null}
            </div>
            {description ? (
              <p className="mt-1 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{description}</p>
            ) : null}
            {locked ? (
              <p className="mt-2 text-xs leading-relaxed text-accent-700 dark:text-accent-200">
                {lockedReason || __('Turn on the matching addon to edit these settings.', 'sikshya')}
              </p>
            ) : null}
          </div>
        </div>
      ) : null}
      {children}
    </section>
  );
}

/** Sub-tabs group all enrollment-related options in one sidebar destination (no duplicate “Enrollment” under Courses). */
const SUB_TABS: Array<{ id: 'purchase' | 'access' | 'rules'; label: string; icon: string; keys: string[] }> = [
  {
    id: 'purchase',
    label: __('Purchase & completion', 'sikshya'),
    icon: 'chart',
    keys: ['enrollment_checkout', 'enrollment_dynamic_checkout_fields', 'enrollment_completion'],
  },
  {
    id: 'access',
    label: __('Access & capacity', 'sikshya'),
    icon: 'users',
    keys: ['enrollment_access', 'enrollment_limits'],
  },
  {
    id: 'rules',
    label: __('Unenroll & advanced', 'sikshya'),
    icon: 'layers',
    keys: ['enrollment_unenroll', 'enrollment_prerequisites', 'enrollment_periods'],
  },
];

export function EnrollmentSettingsTab(props: Props) {
  const { tabSchema, renderField, draft } = props;
  const [sub, setSub] = useState<(typeof SUB_TABS)[number]['id']>('purchase');

  const guestLoginConflict =
    draft &&
    isTruthyCheckboxValue(draft.allow_guest_enrollment) &&
    isTruthyCheckboxValue(draft.require_login);

  const byKey = useMemo(() => {
    const map = new Map<string, SettingsSection>();
    for (const sec of tabSchema) {
      const k = sec.section_key;
      if (k) {
        map.set(k, sec);
      }
    }
    return map;
  }, [tabSchema]);

  const fieldsOf = (key: string) => byKey.get(key)?.fields ?? [];

  const activeKeys = SUB_TABS.find((t) => t.id === sub)?.keys ?? [];

  return (
    <div className="w-full space-y-0">
      <div className="space-y-4 px-6">
        <p className="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          {__(
            'Everything about joining courses, checkout behavior, completion rules, and capacity lives here — not under Courses. Use Courses for catalog layout, reviews, and search only.',
            'sikshya'
          )}
        </p>

        {guestLoginConflict ? (
          <div
            className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100"
            role="status"
          >
            <p className="font-semibold text-amber-950 dark:text-amber-50">
              {__('These two options usually conflict', 'sikshya')}
            </p>
            <p className="mt-1 text-xs leading-relaxed text-amber-900/90 dark:text-amber-100/90">
              {__(
                'Guest enrollment lets people join without a WordPress account. “Require login for course access” expects a logged-in user to open lessons. Together, guests may enroll but then cannot open content. Turn off guest enrollment if everyone must log in, or turn off the login requirement if you truly need guest access (subject to how your theme handles identity).',
                'sikshya'
              )}
            </p>
          </div>
        ) : null}

        <div className="flex w-full flex-wrap gap-1 border-b border-slate-200/80 dark:border-slate-800">
          {SUB_TABS.map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setSub(t.id)}
              className={`relative flex flex-1 items-center justify-center gap-2 px-3 pb-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 sm:flex-none sm:justify-start sm:px-4 ${
                sub === t.id
                  ? 'text-brand-600 dark:text-brand-400'
                  : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
              }`}
            >
              <NavIcon name={t.icon} className="h-4 w-4 shrink-0" />
              {t.label}
              {sub === t.id ? (
                <span className="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-brand-500 dark:bg-brand-400" />
              ) : null}
            </button>
          ))}
        </div>
      </div>

      <div className="mt-6 w-full space-y-6 px-6">
        {activeKeys.map((sectionKey) => {
          const sec = byKey.get(sectionKey);
          const fields = fieldsOf(sectionKey);
          if (!fields.length) {
            return null;
          }
          return (
            <SectionShell
              key={sectionKey}
              title={sec?.title}
              description={sec?.description}
              icon={sec?.icon}
              locked={!!sec?.locked}
              lockedReason={sec?.locked_reason}
            >
              <div className="grid gap-6 lg:grid-cols-2">{fields.map(renderField)}</div>
            </SectionShell>
          );
        })}
      </div>
    </div>
  );
}
