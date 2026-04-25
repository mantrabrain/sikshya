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
    case 'list':
      return 'course';
    case 'star':
      return 'badge';
    case 'tags':
      return 'tag';
    case 'search':
      return 'helpCircle';
    case 'user-plus':
      return 'users';
    case 'cog':
      return 'cog';
    default:
      return fa || 'cog';
  }
}

function SectionShell(props: {
  title?: string;
  description?: string;
  icon?: string;
  children: ReactNode;
}) {
  const { title, description, icon, children } = props;
  return (
    <section className="rounded-2xl border border-slate-200/80 bg-slate-50 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950/30">
      {title ? (
        <div className="mb-5 flex items-start gap-3">
          <span className="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            <NavIcon name={sectionIconName(icon)} className="h-5 w-5" />
          </span>
          <div className="min-w-0">
            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{title}</h3>
            {description ? (
              <p className="mt-1 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{description}</p>
            ) : null}
          </div>
        </div>
      ) : null}
      {children}
    </section>
  );
}

const SUB_TABS: Array<{ id: 'catalog' | 'discovery'; label: string; icon: string; keys: string[] }> = [
  { id: 'catalog', label: 'Catalog & layout', icon: 'course', keys: ['course_display'] },
  {
    id: 'discovery',
    label: 'Discovery',
    icon: 'tag',
    keys: ['course_reviews', 'course_tax', 'course_search'],
  },
];

export function CourseSettingsTab(props: Props) {
  const { tabSchema, renderField } = props;
  const [sub, setSub] = useState<(typeof SUB_TABS)[number]['id']>('catalog');

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
          Layout of the course catalog and single course pages, plus reviews, categories, and search. Enrollment rules, buttons,
          and completion are under <span className="font-semibold text-slate-600 dark:text-slate-300">Enrollment</span> in the
          sidebar.
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
            <SectionShell key={sectionKey} title={sec?.title} description={sec?.description} icon={sec?.icon}>
              <div
                className={`grid gap-6 ${
                  sectionKey === 'course_search' || sectionKey === 'course_reviews' ? 'lg:grid-cols-2' : 'lg:grid-cols-2'
                }`}
              >
                {fields.map(renderField)}
              </div>
            </SectionShell>
          );
        })}
      </div>
    </div>
  );
}
