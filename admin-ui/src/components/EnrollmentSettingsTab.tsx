import { useMemo, useState, type ReactNode } from 'react';
import { NavIcon } from './NavIcon';
import type { SettingsField, SettingsSection } from '../types/settingsSchema';

type Props = {
  tabSchema: SettingsSection[];
  renderField: (f: SettingsField) => React.ReactNode;
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
          ? 'border-violet-200 bg-violet-50/50 dark:border-violet-900/50 dark:bg-violet-950/25'
          : 'border-slate-200/80 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/30'
      }`}
    >
      {title ? (
        <div className="mb-5 flex items-start gap-3">
          <span
            className={`mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg ${
              locked
                ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
            }`}
          >
            <NavIcon name={locked ? 'badge' : sectionIconName(icon)} className="h-5 w-5" />
          </span>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{title}</h3>
              {locked ? (
                <span className="inline-flex items-center gap-1 rounded-md bg-violet-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-900/50 dark:text-violet-200">
                  <span aria-hidden>★</span> Pro
                </span>
              ) : null}
            </div>
            {description ? (
              <p className="mt-1 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{description}</p>
            ) : null}
            {locked ? (
              <p className="mt-2 text-xs leading-relaxed text-violet-700 dark:text-violet-200">
                {lockedReason || 'Turn on the matching addon to edit these settings.'}
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
    label: 'Purchase & completion',
    icon: 'chart',
    keys: ['enrollment_checkout', 'enrollment_completion'],
  },
  {
    id: 'access',
    label: 'Access & capacity',
    icon: 'users',
    keys: ['enrollment_access', 'enrollment_limits'],
  },
  {
    id: 'rules',
    label: 'Unenroll & advanced',
    icon: 'layers',
    keys: ['enrollment_unenroll', 'enrollment_prerequisites', 'enrollment_periods'],
  },
];

export function EnrollmentSettingsTab(props: Props) {
  const { tabSchema, renderField } = props;
  const [sub, setSub] = useState<(typeof SUB_TABS)[number]['id']>('purchase');

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
          Everything about joining courses, checkout behavior, completion rules, and capacity lives here — not under Courses.
          Use <span className="font-semibold text-slate-600 dark:text-slate-300">Courses</span> for catalog layout, reviews, and
          search only.
        </p>

        <div className="flex w-full flex-wrap gap-1 border-b border-slate-200/80 dark:border-slate-800">
          {SUB_TABS.map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setSub(t.id)}
              className={`relative flex flex-1 items-center justify-center gap-2 px-3 pb-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 sm:flex-none sm:justify-start sm:px-4 ${
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
