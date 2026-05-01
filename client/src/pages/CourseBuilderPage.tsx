import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AddonEnablePanel } from '../components/AddonEnablePanel';
import { NavIcon } from '../components/NavIcon';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import {
  getApiErrorToastTitle,
  getErrorSummary,
  getToastMessageForApiFailure,
  preferToastForApiError,
} from '../api/errors';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { WPMediaPickerField } from '../components/shared/WPMediaPickerField';
import { CourseBuilderSkeleton, SkeletonLine } from '../components/shared/Skeleton';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import { renderContentEditor } from './content-editors/editors';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import {
  AddContentTypePickerModal,
  defaultTitleFor,
  type ContentPickerType,
} from '../components/shared/AddContentTypePickerModal';
import { useAsyncData } from '../hooks/useAsyncData';
import { appViewHref } from '../lib/appUrl';
import { getCatalogEntry, getLicensing, isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { useAddonEnabled } from '../hooks/useAddons';
import { useAdminRouting } from '../lib/adminRouting';
import type { FieldConfig, NavItem, SikshyaReactConfig, TabFieldsMap } from '../types';
import { DateTimePickerField } from '../components/shared/DateTimePickerField';
import { MultiCoursePicker } from '../components/shared/MultiCoursePicker';
import { QuillField } from '../components/shared/QuillField';
import { term, termLower } from '../lib/terminology';
import { navIconForCurriculumRow } from '../lib/curriculumIcons';

/** Shared field chrome — one place for focus rings and dark mode. */
const FIELD_INPUT =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';
const FIELD_LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const FIELD_HINT = 'mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400';

/** Same active styling as `Sidebar` top-level links (bluish brand tint + inset ring). */
const BUILDER_NAV_ACTIVE =
  'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-100 dark:bg-brand-950/40 dark:text-brand-300 dark:ring-brand-900/40';
/** Same as `Sidebar` `ChildLink` active (nested items). */
const BUILDER_NAV_ACTIVE_NESTED =
  'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-100/80 dark:bg-brand-950/40 dark:text-brand-300 dark:ring-brand-900/50';
const BUILDER_NAV_INACTIVE =
  'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/70';

type TabSummary = { id: string; title: string; description: string };
type UserOpt = { id: number; name: string };

type BootstrapData = {
  course_id: number;
  tabs: TabSummary[];
  tabFields: TabFieldsMap;
  values: Record<string, unknown>;
  users: UserOpt[];
  preview_url?: string;
  /** Server: course post is a bundle (`_sikshya_course_type` = bundle). Builder hides non-essential tabs. */
  is_bundle?: boolean;
};

type BuilderHeaderMeta = {
  title: string;
  subtitleBits: string[];
  status?: string;
};

export type CurriculumContentItem = {
  id: number;
  title: string;
  /** `question` may still appear from legacy chapter links; outline hides these (questions belong under quizzes). */
  type: 'lesson' | 'quiz' | 'assignment' | 'question';
  status?: string;
  meta?: {
    lesson_type?: string;
    duration?: string;
    time_limit?: number;
    points?: number;
  };
};

/**
 * Types available when adding from a chapter (questions are created inside /
 * for a quiz, not here). Aliased to the shared `ContentPickerType` so the
 * curriculum picker and the standalone Lessons-list picker share one vocabulary.
 */
export type CurriculumAddableType = ContentPickerType;

export type CurriculumChapterTree = {
  id: number;
  title: string;
  contents: CurriculumContentItem[];
};

type CurriculumSelection =
  | null
  | { kind: 'chapter'; chapterId: number }
  | { kind: 'content'; chapterId: number; item: CurriculumContentItem };

const CURRICULUM_DRAG_MIME = 'application/x-sikshya-curriculum-v1';

/** Persisted UI: curriculum outline fills page vs. fixed panel with its own scrollbar. */
const CURRICULUM_OUTLINE_FULL_HEIGHT_KEY = 'sikshya_course_builder_curriculum_outline_full_height';

function readCurriculumOutlineFullHeightPref(): boolean {
  if (typeof window === 'undefined') {
    return false;
  }
  try {
    return window.localStorage.getItem(CURRICULUM_OUTLINE_FULL_HEIGHT_KEY) === '1';
  } catch {
    return false;
  }
}

type CurriculumDragPayload =
  | { t: 'chapter'; chapterId: number }
  | { t: 'content'; contentId: number; fromChapterId: number };

function parseCurriculumDrag(dt: DataTransfer): CurriculumDragPayload | null {
  try {
    const raw = dt.getData(CURRICULUM_DRAG_MIME);
    if (!raw) {
      return null;
    }
    const o = JSON.parse(raw) as CurriculumDragPayload;
    if (o.t === 'chapter' && typeof o.chapterId === 'number') {
      return o;
    }
    if (o.t === 'content' && typeof o.contentId === 'number' && typeof o.fromChapterId === 'number') {
      return o;
    }
    return null;
  } catch {
    return null;
  }
}

function cloneCurriculumTree(tree: CurriculumChapterTree[]): CurriculumChapterTree[] {
  return tree.map((c) => ({
    ...c,
    contents: c.contents.map((x) => ({ ...x })),
  }));
}

function MultiUserPickerField(props: {
  id: string;
  users: UserOpt[];
  value: number[];
  onChange: (next: number[]) => void;
  placeholder?: string;
  hint?: string;
}) {
  const { id, users, value, onChange, placeholder, hint } = props;
  const [q, setQ] = useState('');

  const selectedIds = useMemo(() => Array.from(new Set(value.filter((n) => Number.isFinite(n) && n > 0))), [value]);
  const selectedSet = useMemo(() => new Set(selectedIds), [selectedIds]);
  const selectedUsers = useMemo(
    () => selectedIds.map((sid) => users.find((u) => u.id === sid)).filter(Boolean) as UserOpt[],
    [selectedIds, users]
  );

  const filtered = useMemo(() => {
    const query = q.trim().toLowerCase();
    const base = users.filter((u) => !selectedSet.has(u.id));
    if (!query) return base.slice(0, 25);
    return base
      .filter((u) => u.name.toLowerCase().includes(query) || String(u.id).includes(query))
      .slice(0, 25);
  }, [q, users, selectedSet]);

  return (
    <div className="mt-1.5 space-y-2">
      <div className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
        {selectedUsers.length ? (
          <div className="mb-2 flex flex-wrap gap-2">
            {selectedUsers.map((u) => (
              <span
                key={u.id}
                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
              >
                <span className="max-w-[16rem] truncate">{u.name}</span>
                <button
                  type="button"
                  aria-label={`Remove ${u.name}`}
                  className="rounded-full px-1 text-slate-500 hover:bg-red-50 hover:text-red-700 dark:text-slate-300 dark:hover:bg-red-950/30 dark:hover:text-red-200"
                  onClick={() => onChange(selectedIds.filter((x) => x !== u.id))}
                >
                  ×
                </button>
              </span>
            ))}
          </div>
        ) : (
          <div className="mb-2 text-sm text-slate-500 dark:text-slate-400">No instructors selected.</div>
        )}

        <input
          id={id}
          className={`${FIELD_INPUT} mt-0`}
          placeholder={placeholder ?? 'Search users…'}
          value={q}
          onChange={(e) => setQ(e.target.value)}
          autoComplete="off"
          spellCheck={false}
        />

        <div className="mt-2 max-h-56 overflow-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-950/40">
          {filtered.length ? (
            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
              {filtered.map((u) => (
                <li key={u.id}>
                  <button
                    type="button"
                    className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm text-slate-800 hover:bg-slate-50 dark:text-slate-100 dark:hover:bg-slate-800/50"
                    onClick={() => {
                      onChange([...selectedIds, u.id]);
                      setQ('');
                    }}
                  >
                    <span className="min-w-0 truncate">{u.name}</span>
                    <span className="shrink-0 rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
                      #{u.id}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <div className="px-3 py-3 text-sm text-slate-500 dark:text-slate-400">
              {users.length === selectedIds.length ? 'All users selected.' : 'No matches.'}
            </div>
          )}
        </div>
      </div>
      {hint ? <p className="text-xs text-slate-500 dark:text-slate-400">{hint}</p> : null}
    </div>
  );
}

/** Reorder top-level chapters by drag-and-drop. */
function reorderChaptersAtIndex(
  tree: CurriculumChapterTree[],
  fromIndex: number,
  toIndex: number
): CurriculumChapterTree[] {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= tree.length || toIndex >= tree.length) {
    return tree;
  }
  const next = cloneCurriculumTree(tree);
  const [ch] = next.splice(fromIndex, 1);
  let insertAt = toIndex;
  if (fromIndex < toIndex) {
    insertAt = toIndex - 1;
  }
  next.splice(insertAt, 0, ch);
  return next;
}

/**
 * Move a curriculum item before `beforeItemId`, or append when `beforeItemId` is null.
 * `beforeItemId` is resolved after the item is removed from its source chapter.
 */
function moveContentBeforeItem(
  tree: CurriculumChapterTree[],
  contentId: number,
  fromChapterId: number,
  toChapterId: number,
  beforeItemId: number | null
): CurriculumChapterTree[] {
  const next = cloneCurriculumTree(tree);
  const fromCh = next.find((c) => c.id === fromChapterId);
  const toCh = next.find((c) => c.id === toChapterId);
  if (!fromCh || !toCh) {
    return tree;
  }
  const fromIdx = fromCh.contents.findIndex((x) => x.id === contentId);
  if (fromIdx < 0) {
    return tree;
  }
  if (beforeItemId === contentId) {
    return tree;
  }
  const [item] = fromCh.contents.splice(fromIdx, 1);

  let insertAt = toCh.contents.length;
  if (beforeItemId != null) {
    let i = toCh.contents.findIndex((x) => x.id === beforeItemId);
    if (i >= 0) {
      if (fromChapterId === toChapterId && fromIdx < i) {
        i -= 1;
      }
      insertAt = i;
    }
  }
  insertAt = Math.max(0, Math.min(insertAt, toCh.contents.length));
  toCh.contents.splice(insertAt, 0, item);
  return next;
}

function removeContentFromChapter(
  tree: CurriculumChapterTree[],
  chapterId: number,
  contentId: number
): CurriculumChapterTree[] {
  const next = cloneCurriculumTree(tree);
  const ch = next.find((c) => c.id === chapterId);
  if (!ch) {
    return tree;
  }
  const i = ch.contents.findIndex((x) => x.id === contentId);
  if (i < 0) {
    return tree;
  }
  ch.contents.splice(i, 1);
  return next;
}

function slugify(s: string): string {
  return s
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function fieldIsVisible(cfg: FieldConfig, values: Record<string, unknown>): boolean {
  const rules = cfg.depends_all;
  if (Array.isArray(rules) && rules.length > 0) {
    for (const rule of rules) {
      const cur = values[rule.on];
      if (rule.value !== undefined) {
        if (String(cur ?? '') !== String(rule.value)) {
          return false;
        }
      } else if (!cur || cur === '0' || cur === false) {
        return false;
      }
    }
    return true;
  }
  if (cfg.depends_on) {
    const p = values[cfg.depends_on];
    if (Array.isArray(cfg.depends_in) && cfg.depends_in.length > 0) {
      const cur = String(p ?? '');
      return cfg.depends_in.some((v) => String(v) === cur);
    }
    if (cfg.depends_value !== undefined) {
      return String(p ?? '') === String(cfg.depends_value);
    }
    return Boolean(p);
  }
  return true;
}

type LayoutRow =
  | { kind: 'full'; fields: [string, FieldConfig][] }
  | { kind: 'grid'; cols: 2 | 3; fields: [string, FieldConfig][] };

function buildFieldLayoutRows(entries: [string, FieldConfig][]): LayoutRow[] {
  const rows: LayoutRow[] = [];
  let i = 0;
  while (i < entries.length) {
    const [id, cfg] = entries[i];
    const layout = cfg.layout || '';
    if (layout === 'two_column') {
      const chunk: [string, FieldConfig][] = [[id, cfg]];
      i++;
      while (chunk.length < 2 && i < entries.length) {
        const next = entries[i];
        if ((next[1].layout || '') === 'two_column') {
          chunk.push(next);
          i++;
        } else {
          break;
        }
      }
      rows.push({ kind: 'grid', cols: 2, fields: chunk });
      continue;
    }
    if (layout === 'three_column') {
      const chunk: [string, FieldConfig][] = [[id, cfg]];
      i++;
      while (chunk.length < 3 && i < entries.length) {
        const next = entries[i];
        if ((next[1].layout || '') === 'three_column') {
          chunk.push(next);
          i++;
        } else {
          break;
        }
      }
      rows.push({ kind: 'grid', cols: 3, fields: chunk });
      continue;
    }
    rows.push({ kind: 'full', fields: [[id, cfg]] });
    i++;
  }
  return rows;
}

/** Pricing-tab surface for Content drip (Sikshya Pro); rules live under Learning rules → Scheduled access. */
function ContentDripCourseBuilderGateInput(props: { config: SikshyaReactConfig; courseId: number }) {
  const { config, courseId } = props;
  const featureOk = isFeatureEnabled(config, 'content_drip');
  const addon = useAddonEnabled('content_drip');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const lic = getLicensing(config);
  const upgradeUrl =
    lic?.upgradeUrl || config.brandLinks?.upgradeUrl || 'https://mantrabrain.com/plugins/sikshya/#pricing';
  const catalog = getCatalogEntry(config, 'content_drip');
  const featureTitle = catalog?.label || 'Content drip & scheduled unlock';
  const [enableBusy, setEnableBusy] = useState(false);

  const rulesHref =
    courseId > 0
      ? appViewHref(config, 'learning-rules', { tab: 'drip', course_id: String(courseId) })
      : appViewHref(config, 'learning-rules', { tab: 'drip' });

  if (mode === 'pending-addon') {
    return (
      <div className="rounded-xl border border-amber-200/80 bg-amber-50/60 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-100">
        Checking add-on status…
      </div>
    );
  }

  if (mode === 'locked-plan') {
    return (
      <div className="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/40">
        <p className="text-sm font-medium text-slate-900 dark:text-white">{featureTitle}</p>
        <p className="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
          Your plan does not include this module yet. Upgrade to unlock scheduled lesson releases and the Learning rules
          workspace.
        </p>
        <a
          href={upgradeUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-3 inline-flex items-center rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700"
        >
          View plans
        </a>
      </div>
    );
  }

  if (mode === 'addon-off') {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
        <AddonEnablePanel
          title="Content drip is not enabled"
          description="Turn on the Content Drip add-on to load unlock schedules. Edit rules under Learning rules → Scheduled access."
          canEnable={Boolean(addon.licenseOk)}
          enableBusy={enableBusy}
          onEnable={async () => {
            setEnableBusy(true);
            try {
              await addon.enable();
            } finally {
              setEnableBusy(false);
            }
          }}
          upgradeUrl={upgradeUrl}
          error={addon.error}
        />
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-emerald-200/70 bg-emerald-50/40 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/25">
      <p className="text-sm font-medium text-slate-900 dark:text-white">Content drip is ready</p>
      <p className="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
        Configure per-lesson delays and fixed unlock dates in Learning rules. Use the button below to jump straight to
        schedules for this course.
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        <a
          href={rulesHref}
          className="inline-flex items-center rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700"
        >
          Open scheduled access
        </a>
        <a
          href={appViewHref(config, 'addons')}
          className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
        >
          Add-ons
        </a>
      </div>
      {courseId <= 0 ? (
        <p className="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Save the course first to pre-select it on the drip screen.</p>
      ) : null}
    </div>
  );
}

function FieldInput(props: {
  id: string;
  fieldKey?: string;
  cfg: FieldConfig;
  value: unknown;
  onChange: (v: unknown) => void;
  users: UserOpt[];
  config?: SikshyaReactConfig;
  /** Site root URL for permalink preview */
  siteUrl?: string;
  /** Update another builder field (e.g. attachment ID when picking featured image). */
  onSiblingFieldChange?: (key: string, v: unknown) => void;
}) {
  const { id, fieldKey, cfg, value, onChange, users, config, siteUrl, onSiblingFieldChange } = props;
  const t = cfg.type || 'text';
  const base = (siteUrl || '').replace(/\/?$/, '/');
  const scalePickerEnabled = cfg.widget === 'grade_scale_picker';

  const gradeScales = useAsyncData(async () => {
    if (!scalePickerEnabled) {
      return { ok: true, scales: [] as Array<{ id: number; name: string }> };
    }
    try {
      const r = await getSikshyaApi().get<{ ok?: boolean; scales?: Array<{ id: number; name: string }> }>(
        SIKSHYA_ENDPOINTS.pro.gradeScales
      );
      return { ok: true, scales: Array.isArray(r.scales) ? r.scales : [] };
    } catch {
      return { ok: true, scales: [] as Array<{ id: number; name: string }> };
    }
  }, [scalePickerEnabled]);

  const subscriptionPlanPicker = cfg.widget === 'subscription_plan_picker';
  const subscriptionPlans = useAsyncData(async () => {
    if (!subscriptionPlanPicker) {
      return { ok: true, plans: [] as Array<{ id: number; name?: string; amount?: number; currency?: string; interval_unit?: string }> };
    }
    try {
      const r = await getSikshyaApi().get<{
        ok?: boolean;
        plans?: Array<{ id: number; name?: string; amount?: number; currency?: string; interval_unit?: string }>;
      }>(SIKSHYA_ENDPOINTS.pro.plans);
      return { ok: true, plans: Array.isArray(r.plans) ? r.plans : [] };
    } catch {
      return { ok: true, plans: [] as Array<{ id: number; name?: string; amount?: number; currency?: string; interval_unit?: string }> };
    }
  }, [subscriptionPlanPicker]);

  if (t === 'textarea') {
    // Meta description should remain plain-text-ish (SEO snippet), not rich text.
    if (fieldKey === 'meta_description') {
      return (
        <textarea
          id={id}
          rows={4}
          className={`${FIELD_INPUT} min-h-[110px] resize-y`}
          placeholder={cfg.placeholder}
          value={(value as string) ?? ''}
          onChange={(e) => onChange(e.target.value)}
          disabled={Boolean(cfg.disabled)}
        />
      );
    }
    return (
      <QuillField
        label={cfg.label || fieldKey || 'Text'}
        value={(value as string) ?? ''}
        onChange={(html) => onChange(html)}
        placeholder={cfg.placeholder}
        disabled={Boolean(cfg.disabled)}
        minHeightPx={260}
      />
    );
  }

  if (t === 'select') {
    const opts = cfg.options || {};
    const optKeys = Object.keys(opts).map((x) => String(x));
    const ph = cfg.select_placeholder;
    const raw = value === undefined || value === null ? '' : String(value);
    const selectValue = ph && (raw === '' || !optKeys.includes(raw)) ? '' : raw;
    const defVal = cfg.default;
    return (
      <select
        id={id}
        className={FIELD_INPUT}
        value={selectValue}
        onChange={(e) => {
          const v = e.target.value;
          if (ph && v === '') {
            if (defVal !== undefined && defVal !== null) {
              onChange(typeof defVal === 'number' ? defVal : String(defVal));
            } else {
              onChange('');
            }
            return;
          }
          onChange(v);
        }}
      >
        {ph ? <option value="">{ph}</option> : null}
        {Object.entries(opts).map(([k, lab]) => (
          <option key={k} value={k}>
            {lab}
          </option>
        ))}
      </select>
    );
  }

  if (scalePickerEnabled) {
    const raw = Number(value || 0) || 0;
    const scales = gradeScales.data?.scales ?? [];
    return (
      <div className="mt-1.5">
        <select
          id={id}
          className={FIELD_INPUT}
          value={raw > 0 ? String(raw) : ''}
          onChange={(e) => {
            const next = e.target.value ? Number(e.target.value) : 0;
            onChange(next);
          }}
        >
          <option value="">{cfg.select_placeholder || 'Default scale'}</option>
          {scales.map((s) => (
            <option key={s.id} value={String(s.id)}>
              {s.name || `Scale #${s.id}`}
            </option>
          ))}
        </select>
        {gradeScales.loading ? (
          <p className={FIELD_HINT}>Loading grade scales…</p>
        ) : scales.length === 0 ? (
          <p className={FIELD_HINT}>No grade scales yet. Create one from the Grading page.</p>
        ) : null}
      </div>
    );
  }

  if (subscriptionPlanPicker && t === 'number') {
    const raw = Number(value || 0) || 0;
    const plans = subscriptionPlans.data?.plans ?? [];
    const fmt = (n: number, cur?: string) => {
      const c = (cur || 'USD').toUpperCase();
      return `${c} ${n.toFixed(2)}`;
    };
    if (subscriptionPlans.loading) {
      return <p className={FIELD_HINT}>Loading subscription plans…</p>;
    }
    if (plans.length === 0) {
      return (
        <div className="mt-1.5 space-y-1">
          <input
            id={id}
            type="number"
            min={cfg.min}
            max={cfg.max}
            step={cfg.step}
            className={FIELD_INPUT}
            placeholder={cfg.placeholder ?? 'Plan ID'}
            title={cfg.description}
            value={value === undefined || value === null ? '' : String(value)}
            onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}
          />
          <p className={FIELD_HINT}>
            Could not load plans (enable Sikshya Pro, Subscriptions add-on, and licensing) or none exist yet. Enter the
            numeric plan ID from Sikshya Pro → Subscriptions.
          </p>
        </div>
      );
    }
    return (
      <div className="mt-1.5">
        <select
          id={id}
          className={FIELD_INPUT}
          value={raw > 0 ? String(raw) : ''}
          onChange={(e) => {
            const next = e.target.value ? Number(e.target.value) : 0;
            onChange(next);
          }}
        >
          <option value="">{cfg.select_placeholder || 'Select a plan…'}</option>
          {plans.map((p) => (
            <option key={p.id} value={String(p.id)}>
              {(p.name || `Plan #${p.id}`) +
                (p.amount != null && p.interval_unit
                  ? ` — ${fmt(Number(p.amount), p.currency)} / ${p.interval_unit}`
                  : '')}
            </option>
          ))}
        </select>
      </div>
    );
  }

  // Bundle course multi-picker: cfg.widget === 'multi_course_picker', field type 'array'.
  if (cfg.widget === 'multi_course_picker') {
    // value is stored as int[] (PHP serialised → JSON decoded by WP REST → number[]).
    // From the course builder REST endpoint it arrives as a JSON array string or a real array.
    let currentIds: number[] = [];
    if (Array.isArray(value)) {
      currentIds = (value as unknown[]).map(Number).filter(Boolean);
    } else if (typeof value === 'string' && value.trim().startsWith('[')) {
      try { currentIds = (JSON.parse(value) as unknown[]).map(Number).filter(Boolean); } catch { /* noop */ }
    }

    return (
      <div className="mt-2 space-y-2">
        <MultiCoursePicker
          value={currentIds}
          onChange={(ids) => onChange(ids)}
          title={`Select ${config ? termLower(config, 'courses') : 'courses'} for this bundle`}
          placeholder={`Click to add ${config ? termLower(config, 'courses') : 'courses'} to this bundle…`}
          hint={
            currentIds.length === 0
              ? `No ${config ? termLower(config, 'courses') : 'courses'} selected yet.`
              : `${currentIds.length} ${config ? termLower(config, 'course') : 'course'}${currentIds.length === 1 ? '' : 's'} selected.`
          }
        />
        {currentIds.length > 0 ? (
          <p className={FIELD_HINT}>
            {`${currentIds.length} ${config ? termLower(config, 'course') : 'course'}${currentIds.length === 1 ? '' : 's'} included. Buyers get access to all of them with one purchase.`}
          </p>
        ) : null}
      </div>
    );
  }

  if (t === 'date') {
    const raw = value === undefined || value === null ? '' : String(value);
    const iso = raw.length >= 10 ? raw.slice(0, 10) : raw;
    return (
      <DateTimePickerField
        kind="date"
        value={iso}
        onChange={(v) => onChange(v)}
      />
    );
  }

  if (t === 'permalink') {
    return (
      <div className="mt-1.5 flex min-w-0 flex-col gap-2 sm:flex-row sm:items-stretch">
        <span
          className="inline-flex max-h-[42px] shrink-0 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-400 sm:max-w-[45%] sm:truncate"
          title={base ? `${base}…` : undefined}
        >
          {base ? `${base}…/` : '/…/'}
        </span>
        <input
          id={id}
          type="text"
          className={`${FIELD_INPUT} sm:mt-0`}
          placeholder={cfg.placeholder || 'course-url-slug'}
          value={(value as string) ?? ''}
          onChange={(e) => onChange(e.target.value)}
          autoComplete="off"
          spellCheck={false}
        />
      </div>
    );
  }

  if (t === 'media_upload') {
    if (cfg.media_type === 'video') {
      const hint = 'Paste a video URL (YouTube, Vimeo, or direct file).';
      return (
        <div className="mt-1.5 space-y-2">
          <input
            id={id}
            type="url"
            className={FIELD_INPUT}
            placeholder={cfg.placeholder || 'https://'}
            value={(value as string) ?? ''}
            onChange={(e) => onChange(e.target.value)}
          />
          <p className="text-xs text-slate-500 dark:text-slate-400">{hint}</p>
        </div>
      );
    }
    return (
      <WPMediaPickerField
        id={id}
        value={(value as string) ?? ''}
        onChange={(url) => onChange(url)}
        onAttachmentIdChange={
          fieldKey === 'featured_image' && onSiblingFieldChange
            ? (aid) => onSiblingFieldChange('featured_image_id', aid)
            : undefined
        }
        className={FIELD_INPUT}
        placeholder={cfg.placeholder || cfg.description}
        imageOnly
      />
    );
  }

  if (t === 'number') {
    return (
      <input
        id={id}
        type="number"
        min={cfg.min}
        max={cfg.max}
        step={cfg.step}
        className={FIELD_INPUT}
        placeholder={cfg.placeholder ?? '0'}
        title={cfg.description}
        value={value === undefined || value === null ? '' : String(value)}
        onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}
      />
    );
  }

  if (t === 'checkbox') {
    const lab = cfg.label || '';
    const desc = cfg.description;
    return (
      <label htmlFor={id} className="mt-2 flex cursor-pointer items-start gap-3 rounded-lg border border-transparent p-1 -m-1 hover:border-slate-100 hover:bg-slate-50/80 dark:hover:border-slate-800 dark:hover:bg-slate-800/40">
        <input
          id={id}
          type="checkbox"
          className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-500 dark:bg-slate-800"
          checked={Boolean(value)}
          onChange={(e) => onChange(e.target.checked)}
        />
        <span className="min-w-0 flex-1">
          {lab ? <span className="block text-sm font-medium text-slate-800 dark:text-slate-100">{lab}</span> : null}
          {desc ? (
            <span className="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{desc}</span>
          ) : null}
        </span>
      </label>
    );
  }

  if (t === 'user_select') {
    const isMulti = Boolean(cfg.multiple);
    const ids = isMulti
      ? (Array.isArray(value) ? (value as number[]) : [])
      : (value === undefined || value === null || value === '' ? [] : [Number(value) || 0]).filter((n) => n > 0);
    return (
      <MultiUserPickerField
        id={id}
        users={users}
        value={ids}
        onChange={(next) => onChange(isMulti ? next : (next[0] ?? ''))}
        placeholder="Search instructors…"
        hint={isMulti ? 'Type to search, click to add, and use × to remove.' : 'Type to search and click to select.'}
      />
    );
  }

  if (t === 'repeater') {
    const lines = Array.isArray(value) ? (value as string[]) : value ? [String(value)] : [''];
    return (
      <div className="mt-1.5 space-y-2">
        {lines.map((line, idx) => (
          <div key={idx} className="flex gap-2">
            <input
              type="text"
              className={`${FIELD_INPUT} flex-1`}
              placeholder={cfg.placeholder}
              value={line}
              onChange={(e) => {
                const next = [...lines];
                next[idx] = e.target.value;
                onChange(next);
              }}
            />
            <button
              type="button"
              aria-label={`Remove row ${idx + 1}`}
              className="shrink-0 rounded-xl border border-slate-200 px-3 text-sm text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-600 dark:hover:border-red-900/50 dark:hover:bg-red-950/30 dark:hover:text-red-300"
              onClick={() => {
                const next = lines.filter((_, i) => i !== idx);
                onChange(next.length ? next : ['']);
              }}
            >
              ×
            </button>
          </div>
        ))}
        <button
          type="button"
          className="inline-flex items-center gap-1.5 rounded-lg text-sm font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-200"
          onClick={() => onChange([...lines, ''])}
        >
          <span className="text-lg leading-none">+</span> {cfg.add_button_text || 'Add row'}
        </button>
      </div>
    );
  }

  if (t === 'repeater_group' && cfg.subfields) {
    const rows = Array.isArray(value) ? (value as Record<string, string>[]) : [];
    const subKeysBase = Object.keys(cfg.subfields);
    const subKeys =
      fieldKey === 'course_resources'
        ? subKeysBase.filter((k) => k !== 'attachment_id')
        : subKeysBase;
    return (
      <div className="mt-1.5 space-y-4">
        {(rows.length ? rows : [{}]).map((row, idx) => (
          <div
            key={idx}
            className="rounded-xl border border-slate-200/90 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/40"
          >
            <div className="mb-3 flex items-center justify-between gap-2">
              <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Item {idx + 1}
              </span>
              <button
                type="button"
                className="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                onClick={() => {
                  const base = rows.length ? rows : [{}];
                  const next = base.filter((_, i) => i !== idx);
                  onChange(next);
                }}
              >
                Remove
              </button>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              {subKeys.map((sk) => {
                const sub = cfg.subfields![sk];
                const st = sub.type || 'text';
                const sv = row[sk] ?? '';
                const attachmentIdRaw = fieldKey === 'course_resources' ? (row.attachment_id ?? '0') : '0';
                const attachmentId = fieldKey === 'course_resources' ? Number(attachmentIdRaw) || 0 : 0;
                return (
                  <label key={sk} className={`block min-w-0 ${st === 'textarea' ? 'sm:col-span-2' : ''}`}>
                    <span className={FIELD_LABEL}>{sub.label || sk}</span>
                    {fieldKey === 'course_resources' && sk === 'url' ? (
                      <div className="mt-1.5 space-y-2">
                        <div className="rounded-xl border border-slate-200/90 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/40">
                          <div className="text-xs font-semibold text-slate-700 dark:text-slate-200">Choose from Media</div>
                          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Pick a file from the WordPress media library (recommended), or paste a URL below.
                          </p>
                          <div className="mt-2">
                            <WPMediaPickerField
                              id={`${id}-${idx}-resource-media`}
                              value={typeof sv === 'string' ? sv : ''}
                              onChange={(url) => {
                                const next = [...(rows.length ? rows : [{}])];
                                next[idx] = { ...next[idx], url, attachment_id: String(next[idx]?.attachment_id || attachmentId || 0) };
                                onChange(next);
                              }}
                              onAttachmentIdChange={(aid) => {
                                const next = [...(rows.length ? rows : [{}])];
                                next[idx] = { ...next[idx], attachment_id: String(aid || 0) };
                                onChange(next);
                              }}
                              imageOnly={false}
                              placeholder="Upload or choose a file (PDF, ZIP, DOCX, etc.)"
                              className={FIELD_INPUT}
                            />
                          </div>
                        </div>

                        <div className="rounded-xl border border-slate-200/90 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/40">
                          <div className="text-xs font-semibold text-slate-700 dark:text-slate-200">Or paste a URL</div>
                          <input
                            type="url"
                            className={`${FIELD_INPUT} mt-2`}
                            placeholder={sub.placeholder || 'https://'}
                            title={sub.description}
                            value={typeof sv === 'string' ? sv : ''}
                            onChange={(e) => {
                              const url = e.target.value;
                              const next = [...(rows.length ? rows : [{}])];
                              // If user pastes a URL manually, treat it as URL-mode (clear attachment id).
                              next[idx] = { ...next[idx], url, attachment_id: '0' };
                              onChange(next);
                            }}
                          />
                        </div>
                      </div>
                    ) : st === 'textarea' ? (
                      <textarea
                        rows={3}
                        className={`${FIELD_INPUT} min-h-[88px] resize-y`}
                        placeholder={sub.placeholder}
                        value={sv}
                        onChange={(e) => {
                          const next = [...(rows.length ? rows : [{}])];
                          next[idx] = { ...next[idx], [sk]: e.target.value };
                          onChange(next);
                        }}
                      />
                    ) : (
                      <input
                        type={st === 'url' ? 'url' : 'text'}
                        className={FIELD_INPUT}
                        placeholder={sub.placeholder}
                        title={sub.description}
                        value={sv}
                        onChange={(e) => {
                          const next = [...(rows.length ? rows : [{}])];
                          next[idx] = { ...next[idx], [sk]: e.target.value };
                          onChange(next);
                        }}
                      />
                    )}
                  </label>
                );
              })}
            </div>
          </div>
        ))}
        <button
          type="button"
          className="inline-flex items-center gap-1.5 rounded-lg text-sm font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400"
          onClick={() => {
            const empty: Record<string, string> = {};
            subKeysBase.forEach((k) => {
              empty[k] = '';
            });
            if (fieldKey === 'course_resources') {
              empty.attachment_id = '0';
            }
            onChange([...(rows.length ? rows : []), empty]);
          }}
        >
          <span className="text-lg leading-none">+</span> {cfg.add_button_text || 'Add row'}
        </button>
      </div>
    );
  }

  if (t === 'curriculum_builder') {
    return (
      <p className={`${FIELD_HINT} mt-0`}>
        Chapter order and titles are managed from the Curriculum tab using the outline and Sikshya editors.
      </p>
    );
  }

  return (
    <input
      id={id}
      type={t === 'password' ? 'password' : t === 'url' ? 'url' : 'text'}
      className={FIELD_INPUT}
      placeholder={cfg.placeholder}
      value={(value as string) ?? ''}
      onChange={(e) => {
        let v: string | number | boolean = e.target.value;
        if (t === 'number') {
          v = e.target.value === '' ? '' : Number(e.target.value);
        }
        onChange(v);
      }}
    />
  );
}

function BuilderFieldBlock(props: {
  config: SikshyaReactConfig;
  fid: string;
  fcfg: FieldConfig;
  values: Record<string, unknown>;
  users: UserOpt[];
  siteUrl: string;
  courseId: number;
  onFieldChange: (fid: string, v: unknown) => void;
}) {
  const { config, fid, fcfg, values, users, siteUrl, courseId, onFieldChange } = props;
  const isCheckbox = (fcfg.type || 'text') === 'checkbox';

  if (fcfg.widget === 'content_drip_course_builder_gate') {
    return (
      <div className="min-w-0">
        <ContentDripCourseBuilderGateInput config={config} courseId={courseId} />
      </div>
    );
  }

  if (isCheckbox) {
    return (
      <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-3 dark:border-slate-700/80 dark:bg-slate-800/30">
        <FieldInput
          id={`f-${fid}`}
          fieldKey={fid}
          cfg={fcfg}
          value={values[fid]}
          users={users}
          config={config}
          siteUrl={siteUrl}
          onSiblingFieldChange={onFieldChange}
          onChange={(v) => onFieldChange(fid, v)}
        />
      </div>
    );
  }

  return (
    <div className="min-w-0">
      <label htmlFor={`f-${fid}`} className={FIELD_LABEL}>
        {fcfg.label || fid}
        {fcfg.required ? <span className="text-red-500"> *</span> : null}
      </label>
      <div className="min-h-[2.75rem]">
        {fcfg.description ? (
          <p className={FIELD_HINT}>{fcfg.description}</p>
        ) : (
          <p className={`${FIELD_HINT} invisible`} aria-hidden="true">
            placeholder
          </p>
        )}
      </div>
      <FieldInput
        id={`f-${fid}`}
        fieldKey={fid}
        cfg={fcfg}
        value={values[fid]}
        users={users}
        config={config}
        siteUrl={siteUrl}
        onSiblingFieldChange={onFieldChange}
        onChange={(v) => onFieldChange(fid, v)}
      />
    </div>
  );
}

function AddChapterModal(props: {
  open: boolean;
  title: string;
  onTitleChange: (v: string) => void;
  onClose: () => void;
  onSubmit: () => void;
  busy: boolean;
}) {
  const { open, title, onTitleChange, onClose, onSubmit, busy } = props;
  const inputRef = useRef<HTMLInputElement>(null);
  const onCloseRef = useRef(onClose);

  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (!open) {
      return;
    }
    const t = window.setTimeout(() => {
      inputRef.current?.focus();
      inputRef.current?.select();
    }, 50);
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onCloseRef.current();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.clearTimeout(t);
      window.removeEventListener('keydown', onKey);
    };
  }, [open]);

  if (!open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-[2px] sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="sikshya-add-chapter-title"
    >
      <button
        type="button"
        className="absolute inset-0 z-0 cursor-default"
        aria-label="Close"
        onClick={onClose}
      />
      <div className="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h2 id="sikshya-add-chapter-title" className="text-lg font-semibold text-slate-900 dark:text-white">
          Add a chapter
        </h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          A chapter is a section of your course (for example “Getting started” or “Week 2”). Put lessons and quizzes inside it from
          the outline.
        </p>
        <label htmlFor="sikshya-new-chapter-title" className={`${FIELD_LABEL} mt-5`}>
          Chapter name
        </label>
        <input
          ref={inputRef}
          id="sikshya-new-chapter-title"
          type="text"
          className={FIELD_INPUT}
          placeholder="e.g. Introduction or Module 1"
          value={title}
          onChange={(e) => onTitleChange(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              onSubmit();
            }
          }}
        />
        <div className="mt-6 flex flex-wrap justify-end gap-2">
          <button
            type="button"
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            onClick={onClose}
            disabled={busy}
          >
            Cancel
          </button>
          <ButtonPrimary type="button" className="px-4 py-2.5" disabled={busy || !title.trim()} onClick={onSubmit}>
            {busy ? 'Adding…' : 'Add chapter'}
          </ButtonPrimary>
        </div>
      </div>
    </div>
  );
}

/** Outline rows: hide legacy linked questions (LMS model: questions sit under quizzes). */
function curriculumOutlineItems(contents: CurriculumContentItem[]): CurriculumContentItem[] {
  return contents.filter((c) => c.type !== 'question');
}

function curriculumItemIcon(item: CurriculumContentItem): string {
  return navIconForCurriculumRow(item.type, item.meta?.lesson_type ?? '');
}

function contentTypeToPostType(t: CurriculumContentItem['type']): string {
  const m: Record<CurriculumContentItem['type'], string> = {
    lesson: 'sik_lesson',
    quiz: 'sik_quiz',
    assignment: 'sik_assignment',
    question: 'sik_question',
  };
  return m[t];
}

function entityLabelForContent(t: CurriculumContentItem['type']): string {
  const m: Record<CurriculumContentItem['type'], string> = {
    lesson: 'Lesson',
    quiz: 'Quiz',
    assignment: 'Assignment',
    question: 'Question',
  };
  return m[t];
}

function curriculumItemRightMeta(item: CurriculumContentItem): string {
  if (item.type === 'lesson') {
    const bits: string[] = [];
    const lt = (item.meta?.lesson_type || '').toLowerCase();
    const dur = (item.meta?.duration || '').trim();
    if (dur) bits.push(dur);
    if (lt === 'video') bits.push('Video');
    else if (lt === 'live') bits.push('Live');
    else if (lt === 'scorm') bits.push('SCORM');
    else if (lt === 'h5p') bits.push('H5P');
    else if (lt === 'text') bits.push('Text');
    return bits.join(' • ');
  }
  if (item.type === 'quiz') {
    const tl = Number(item.meta?.time_limit || 0) || 0;
    return tl > 0 ? `${tl} min` : '';
  }
  if (item.type === 'assignment') {
    const pts = Number(item.meta?.points || 0) || 0;
    return pts > 0 ? `${pts} pts` : '';
  }
  return '';
}

/** Short labels for the horizontal tab strip (full title stays in `title` tooltip). */
function builderTabShortLabel(tabId: string, fallback: string): string {
  const map: Record<string, string> = {
    course: 'Course',
    pricing: 'Pricing',
    curriculum: 'Curriculum',
    settings: 'Settings',
  };
  return map[tabId] ?? fallback;
}

type SectionEntry = [string, { section?: { title?: string; description?: string }; fields?: Record<string, FieldConfig> }];

function sectionHasVisibleFields(activeTab: string, section: SectionEntry[1]): boolean {
  // Curriculum uses a custom builder surface (outline + editor) and should remain
  // a visible tab even though its schema field is not rendered as a normal form field.
  if (activeTab === 'curriculum') {
    return true;
  }
  return Object.entries(section.fields || {}).some(([, fcfg]) => fcfg.type !== 'curriculum_builder');
}

/** Icon keys from `icons.json` for left sidebar section rows. */
function sectionIconName(sectionKey: string): string {
  const m: Record<string, string> = {
    // Course details (tab: course)
    basic_info: 'course',
    media_visuals: 'photoImage',
    learning_outcomes: 'badge',
    instructors_section: 'users',
    marketing: 'plusDocument',
    seo_settings: 'search',
    seo: 'search',
    // Pricing & access (tab: pricing)
    pricing: 'chart',
    access_enrollment: 'schedule',
    schedule: 'schedule',
    prerequisites: 'helpCircle',
    marketplace_section: 'tag',
    content_drip: 'clipboard',
    // Course options (tab: settings)
    course_settings: 'cog',
    completion_rules: 'badge',
    certificate_settings: 'star',
    interaction_features: 'helpCircle',
    integrations_overrides: 'puzzle',
    pro_scorm_h5p_course: 'layers',
    analytics_visibility: 'chart',
    // Grading (tab: grading)
    grading_scale: 'badge',
    grading_weights: 'badge',
    gradebook_visibility: 'wrench',
    // Curriculum (custom outline surface)
    curriculum: 'curriculumOutline',
    curriculum_outline: 'curriculumOutline',
    // Legacy keys kept so old saved bookmarks don't break.
    advanced_features: 'cog',
    advanced_settings: 'cog',
    advanced: 'cog',
  };
  return m[sectionKey] || 'plusDocument';
}

/** Icons for top horizontal builder tabs. */
function builderTabIcon(tabId: string): string {
  const m: Record<string, string> = {
    course: 'course',
    pricing: 'creditCard',
    curriculum: 'curriculumOutline',
    settings: 'cog',
    grading: 'pencil',
  };
  return m[tabId] || 'course';
}

function BuilderSectionMenu({
  sections,
  activeTab,
  openSectionKey,
  onSelectSection,
}: {
  sections: Record<string, unknown>;
  activeTab: string;
  openSectionKey: string | null;
  onSelectSection: (key: string) => void;
}) {
  const entries = (Object.entries(sections) as SectionEntry[]).filter(([, section]) =>
    sectionHasVisibleFields(activeTab, section)
  );
  if (activeTab === 'curriculum') {
    return null;
  }
  if (entries.length === 0) {
    return null;
  }

  const total = entries.length;
  const activeIndex = entries.findIndex(([k]) => k === openSectionKey);

  return (
    <nav className="flex flex-col p-2 lg:sticky lg:top-4 lg:max-h-[min(80vh,calc(100vh-6rem))] lg:overflow-y-auto lg:p-3" aria-label="Form sections">
      <div className="mb-3 flex items-center justify-between gap-2 px-2">
        <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          Sections
        </div>
        {total > 0 ? (
          <span className="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-medium tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            {activeIndex >= 0 ? activeIndex + 1 : '–'} / {total}
          </span>
        ) : null}
      </div>
      <ul className="space-y-0.5">
        {entries.map(([key, section]) => {
          const label = section.section?.title || key;
          const active = openSectionKey === key;
          const icon = sectionIconName(key);
          return (
            <li key={key}>
              <button
                type="button"
                onClick={() => onSelectSection(key)}
                className={`flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2.5 text-left text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 ${
                  active ? BUILDER_NAV_ACTIVE : BUILDER_NAV_INACTIVE
                }`}
              >
                <span
                  className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${
                    active
                      ? 'bg-brand-100 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300'
                      : 'bg-slate-200/70 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                  }`}
                >
                  <NavIcon name={icon} className="h-4 w-4" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block leading-snug">{label}</span>
                  {section.section?.description ? (
                    <span className="mt-0.5 line-clamp-2 text-[11px] font-normal text-slate-500 dark:text-slate-500">
                      {section.section.description}
                    </span>
                  ) : null}
                </span>
                <NavIcon
                  name="chevronRight"
                  className={`h-4 w-4 shrink-0 opacity-40 ${active ? 'text-brand-600 dark:text-brand-400' : ''}`}
                />
              </button>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

export function CourseBuilderPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();
  const q = config.query || {};
  const courseIdFromUrl = Number(q.course_id || q.id || 0) || 0;

  if (!courseIdFromUrl) {
    return <CourseBuilderMissingSelection embedded={props.embedded} config={config} title={title} />;
  }

  return <CourseBuilderEditor embedded={props.embedded} config={config} title={title} courseId={courseIdFromUrl} />;
}

function CourseBuilderMissingSelection({
  embedded,
  config,
  title,
}: {
  embedded?: boolean;
  config: SikshyaReactConfig;
  title: string;
}) {
  const [createOpen, setCreateOpen] = useState(false);
  const courseLower = termLower(config, 'course');
  const coursesLower = termLower(config, 'courses');
  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={`Select or create a ${courseLower} to edit`}
    >
      <CreateCourseModal config={config} open={createOpen} onClose={() => setCreateOpen(false)} />
      <div className="flex min-h-[min(60vh,520px)] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-16 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-100 dark:bg-brand-950/50">
          <NavIcon name="course" className="h-8 w-8 text-brand-600 dark:text-brand-400" />
        </div>
        <h2 className="mt-6 text-xl font-semibold text-slate-900 dark:text-white">Open a {courseLower} in the builder</h2>
        <p className="mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
          Start by naming a new draft—we save it and bring you here—or pick a {courseLower} from the catalog.
        </p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Create {courseLower}</ButtonPrimary>
          <a
            href={appViewHref(config, 'courses')}
            className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Browse {coursesLower}
          </a>
        </div>
      </div>
    </EmbeddableShell>
  );
}

function CourseBuilderEditor({
  embedded,
  config,
  title,
  courseId: initialCid,
}: {
  embedded?: boolean;
  config: SikshyaReactConfig;
  title: string;
  courseId: number;
}) {
  const { navigateHref } = useAdminRouting();
  const [activeTab, setActiveTab] = useState('course');
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [curriculumTree, setCurriculumTree] = useState<CurriculumChapterTree[]>([]);
  const [curriculumLoading, setCurriculumLoading] = useState(false);
  const [curriculumError, setCurriculumError] = useState<unknown>(null);
  const [curriculumOutlineSaving, setCurriculumOutlineSaving] = useState(false);
  const [curriculumOutlineError, setCurriculumOutlineError] = useState<unknown>(null);
  const [openChapters, setOpenChapters] = useState<Record<number, boolean>>({});
  const [curriculumSelection, setCurriculumSelection] = useState<CurriculumSelection>(null);
  const [editorHeaderMeta, setEditorHeaderMeta] = useState<BuilderHeaderMeta | null>(null);
  const [chapterActionError, setChapterActionError] = useState<unknown>(null);
  const [addContentOpen, setAddContentOpen] = useState(false);
  const [addContentChapterId, setAddContentChapterId] = useState(0);
  const [addContentType, setAddContentType] = useState<CurriculumAddableType>('lesson_text');
  const [addContentTitle, setAddContentTitle] = useState('');
  const [addContentBusy, setAddContentBusy] = useState(false);
  const [addContentError, setAddContentError] = useState<unknown>(null);
  /** When true, outline uses page scroll; when false, outline scrolls inside the curriculum column. */
  const [curriculumOutlineFullHeight, setCurriculumOutlineFullHeight] = useState(readCurriculumOutlineFullHeightPref);
  const toast = useTopRightToast(5200);
  const [saving, setSaving] = useState(false);
  const activeEmbeddedSaveRef = useRef<null | (() => Promise<boolean>)>(null);
  const registerEmbeddedSave = useCallback((fn: (() => Promise<boolean>) | null) => {
    activeEmbeddedSaveRef.current = fn;
  }, []);
  const [openSectionKey, setOpenSectionKey] = useState<string | null>(null);
  const [chapterModalOpen, setChapterModalOpen] = useState(false);
  const [newChapterTitle, setNewChapterTitle] = useState('New chapter');
  const [chapterModalBusy, setChapterModalBusy] = useState(false);
  const bootOnceRef = useRef(false);

  const setCurriculumOutlineFullHeightPersisted = useCallback((next: boolean) => {
    setCurriculumOutlineFullHeight(next);
    try {
      window.localStorage.setItem(CURRICULUM_OUTLINE_FULL_HEIGHT_KEY, next ? '1' : '0');
    } catch {
      /* ignore quota / private mode */
    }
  }, []);

  useEffect(() => {
    bootOnceRef.current = false;
  }, [initialCid]);

  const siteUrl = config.siteUrl || '';

  const bootstrap = useAsyncData(async () => {
    const res = await getSikshyaApi().get<{
      success: boolean;
      data?: BootstrapData;
      message?: string;
    }>(SIKSHYA_ENDPOINTS.courseBuilder.bootstrap(initialCid));
    if (!res.success || !res.data) {
      throw new Error(res.message || 'Could not load the course builder.');
    }
    return res.data;
  }, [initialCid]);

  useEffect(() => {
    const d = bootstrap.data;
    if (!d) {
      return;
    }
    // Ensure course_type is always 'bundle' when the server confirms it,
    // OR when force_bundle_ui is set in the URL (newly-created bundle, meta just saved).
    const forcedBundle =
      d.is_bundle ||
      String(config.query?.force_bundle_ui ?? '') === '1' ||
      config.page === 'bundle-builder';
    setValues({
      ...d.values,
      course_id: d.course_id,
      ...(forcedBundle ? { course_type: 'bundle' } : null),
    });
    if (!bootOnceRef.current) {
      const req = String(config.query?.tab || '').trim();
      const serverTabs = Array.isArray(d.tabs) ? d.tabs : [];
      const hasReq = req !== '' && serverTabs.some((t) => t.id === req);
      const preferredDefault = serverTabs.some((t) => t.id === 'discovery')
        ? 'discovery'
        : serverTabs[0]?.id || 'course';
      setActiveTab(hasReq ? req : preferredDefault);
      bootOnceRef.current = true;
    }
  }, [bootstrap.data, config.query?.tab]);

  const courseId = Number(values.course_id) || bootstrap.data?.course_id || 0;

  useEffect(() => {
    if (activeTab !== 'curriculum' || !courseId) {
      setCurriculumTree([]);
      setCurriculumError(null);
      setCurriculumLoading(false);
      return;
    }
    let cancelled = false;
    setCurriculumLoading(true);
    setCurriculumError(null);
    void getSikshyaApi()
      .get<{ success: boolean; data?: { chapters: CurriculumChapterTree[] } }>(
        SIKSHYA_ENDPOINTS.admin.courseCurriculumTree(courseId)
      )
      .then((res) => {
        if (!cancelled) {
          const rows = res.success && res.data?.chapters ? res.data.chapters : [];
          setCurriculumTree(rows);
        }
      })
      .catch((e) => {
        if (!cancelled) {
          if (preferToastForApiError(e)) {
            setCurriculumError(null);
            toast.error(getApiErrorToastTitle(e), getErrorSummary(e));
          } else {
            setCurriculumError(e);
          }
          setCurriculumTree([]);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setCurriculumLoading(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [activeTab, courseId]);

  useEffect(() => {
    if (!curriculumTree.length) {
      return;
    }
    setOpenChapters((prev) => {
      const next = { ...prev };
      for (const c of curriculumTree) {
        if (next[c.id] === undefined) {
          next[c.id] = true;
        }
      }
      return next;
    });
  }, [curriculumTree]);

  useEffect(() => {
    if (activeTab !== 'curriculum' || !courseId) {
      return;
    }
    if (curriculumSelection) {
      return;
    }
    const first = curriculumTree[0];
    if (!first?.id) {
      return;
    }
    setCurriculumSelection({ kind: 'chapter', chapterId: first.id });
  }, [activeTab, courseId, curriculumSelection, curriculumTree]);

  const tabFields = bootstrap.data?.tabFields || {};
  const rawTabs = bootstrap.data?.tabs || [];
  const users = bootstrap.data?.users || [];

  const tabs = useMemo(() => {
    if (!rawTabs.length) {
      return [];
    }
    const hasVisible = (tabId: string): boolean => {
      const raw = tabFields[tabId] || {};
      const entries = Object.entries(raw) as SectionEntry[];
      return entries.some(([, sec]) => sectionHasVisibleFields(tabId, sec));
    };
    const filtered = rawTabs.filter((t) => hasVisible(t.id));
    return filtered.length ? filtered : rawTabs;
  }, [rawTabs, tabFields]);

  useEffect(() => {
    if (tabs.length === 0) {
      return;
    }
    const ids = tabs.map((t) => t.id);
    if (!ids.includes(activeTab)) {
      setActiveTab(ids.includes('discovery') ? 'discovery' : ids[0] || 'course');
    }
  }, [tabs, activeTab]);

  const sections = tabFields[activeTab] || {};

  useEffect(() => {
    if (activeTab === 'curriculum') {
      setOpenSectionKey(null);
      return;
    }
    const raw = tabFields[activeTab] || {};
    const keys = (Object.entries(raw) as SectionEntry[])
      .filter(([, sec]) => sectionHasVisibleFields(activeTab, sec))
      .map(([k]) => k);
    setOpenSectionKey((prev) => (prev && keys.includes(prev) ? prev : keys[0] ?? null));
  }, [activeTab, tabFields, bootstrap.data]);

  const handleFieldChange = (fid: string, v: unknown) => {
    setValues((prev) => {
      const next: Record<string, unknown> = { ...prev, [fid]: v };
      if (fid === 'title') {
        const s = prev.slug;
        if (s === undefined || s === null || String(s).trim() === '') {
          next.slug = slugify(String(v));
        }
      }
      return next;
    });
  };

  const onSave = async (status: string) => {
    if (activeEmbeddedSaveRef.current) {
      const ok = await activeEmbeddedSaveRef.current();
      if (!ok) {
        toast.error(
          'Could not save',
          'Save or close the embedded lesson, quiz, assignment, or chapter editor first — fix any errors there, save the item, then try again.'
        );
        return;
      }
    }
    setSaving(true);
    try {
      const payload: Record<string, unknown> = { ...values, course_status: status };
      if (!payload.slug && payload.title) {
        payload.slug = slugify(String(payload.title));
      }
      const res = await getSikshyaApi().post<{
        success: boolean;
        message?: string;
        data?: { course_id?: number };
        errors?: unknown;
        field_errors?: Record<string, string>;
      }>(SIKSHYA_ENDPOINTS.courseBuilder.save, payload);
      if (!res.success) {
        const fe = res.field_errors ? Object.values(res.field_errors).filter(Boolean).join('\n') : '';
        toast.error(res.message || 'Could not save', fe || undefined);
        return;
      }
      const newId = res.data?.course_id;
      if (newId && newId !== courseId) {
        const view = isBundleUi ? 'bundle-builder' : 'add-course';
        navigateHref(appViewHref(config, view, { course_id: String(newId) }));
        return;
      }
      const msg = res.message || 'Saved.';
      if (status === 'publish') {
        toast.success('Published', msg);
      } else {
        toast.success('Saved', msg);
      }
      bootstrap.refetch();
    } catch (e) {
      toast.error(getApiErrorToastTitle(e), getToastMessageForApiFailure(e));
    } finally {
      setSaving(false);
    }
  };

  // Three independent signals — any one is enough to engage bundle UI:
  // 1. Server bootstrap confirmed the post has _sikshya_course_type = bundle.
  // 2. The saved values already carry course_type = bundle (returned by bootstrap).
  // 3. URL carries force_bundle_ui=1 (set by the create-modal immediately after creation,
  //    persisted in the PHP query whitelist so it survives a hard reload).
  const isBundleUi =
    Boolean(bootstrap.data?.is_bundle) ||
    String(values.course_type ?? '') === 'bundle' ||
    String(config.query?.force_bundle_ui ?? '') === '1' ||
    config.page === 'bundle-builder';

  const subtitle = useMemo(() => {
    const base = values.title ? String(values.title) : initialCid ? `Course #${initialCid}` : 'New course';
    if (isBundleUi) {
      return values.title ? `${base} · bundle` : `Bundle #${initialCid}`;
    }
    return base;
  }, [values.title, values.course_type, initialCid, isBundleUi]);

  const activeTabMeta = tabs.find((t) => t.id === activeTab);

  const builderBackHref = useMemo(
    () => appViewHref(config, 'add-course', { course_id: String(courseId) }),
    [config, courseId]
  );
  const previewUrl = bootstrap.data?.preview_url || '';

  const curriculumStats = useMemo(() => {
    const all = curriculumTree.flatMap((c) => curriculumOutlineItems(c.contents));
    const lessons = all.filter((i) => i.type === 'lesson');
    const totalLessons = lessons.length;
    const publishedLessons = lessons.filter((i) => (i.status || '').toLowerCase() === 'publish').length;
    return { totalLessons, publishedLessons };
  }, [curriculumTree]);

  useEffect(() => {
    // Keep builder tab shareable / refresh-safe: `?tab=course|pricing|curriculum|settings`.
    if (!activeTab) {
      return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('tab', activeTab);
    window.history.replaceState({}, '', url.toString());
  }, [activeTab]);

  const curriculumTreeRef = useRef(curriculumTree);
  curriculumTreeRef.current = curriculumTree;

  const persistCurriculumOutline = async (nextTree: CurriculumChapterTree[]) => {
    if (!courseId || curriculumOutlineSaving) {
      return;
    }
    setCurriculumOutlineSaving(true);
    setCurriculumOutlineError(null);
    const snapshot = cloneCurriculumTree(curriculumTreeRef.current);
    setCurriculumTree(nextTree);
    try {
      const res = await getSikshyaApi().post<{ success: boolean; message?: string }>(
        SIKSHYA_ENDPOINTS.curriculum.outlineStructure,
        {
          course_id: courseId,
          chapter_order: nextTree.map((c) => c.id),
          chapters: nextTree.map((c) => ({
            chapter_id: c.id,
            content_ids: c.contents.map((x) => x.id),
          })),
        }
      );
      if (!res.success) {
        throw new Error(res.message || 'Could not save outline order.');
      }
    } catch (e) {
      setCurriculumTree(snapshot);
      setCurriculumOutlineError(e);
    } finally {
      setCurriculumOutlineSaving(false);
    }
  };

  const refreshCurriculumTree = async () => {
    if (!courseId) {
      return;
    }
    const treeRes = await getSikshyaApi().get<{
      success: boolean;
      data?: { chapters: CurriculumChapterTree[] };
    }>(SIKSHYA_ENDPOINTS.admin.courseCurriculumTree(courseId));
    setCurriculumTree(treeRes.success && treeRes.data?.chapters ? treeRes.data.chapters : []);
  };

  const permanentlyDeleteCurriculumPost = async (postType: string, postId: number) => {
    if (!postId || postId <= 0) {
      return;
    }
    const ok = await confirm({
      title: 'Delete permanently?',
      message: 'This removes the item from the site. This cannot be undone.',
      variant: 'danger',
      confirmLabel: 'Delete permanently',
    });
    if (!ok) {
      return;
    }
    setCurriculumOutlineError(null);
    setCurriculumOutlineSaving(true);
    try {
      const restBase = postType.replace(/^\//, '');
      // WordPress CPT delete (force=true = permanent).
      await getWpApi().delete(`/${restBase}/${postId}?force=true`);
      await refreshCurriculumTree();
      setCurriculumSelection(null);
    } catch (e) {
      setCurriculumOutlineError(e);
    } finally {
      setCurriculumOutlineSaving(false);
    }
  };

  useEffect(() => {
    if (activeTab !== 'curriculum' || !courseId || !curriculumSelection) {
      setEditorHeaderMeta(null);
      return;
    }
    let cancelled = false;

    const run = async () => {
      try {
        if (curriculumSelection.kind === 'chapter') {
          const ch = curriculumTree.find((c) => c.id === curriculumSelection.chapterId);
          setEditorHeaderMeta({
            title: ch?.title || 'Chapter',
            subtitleBits: ['Chapter'],
          });
          return;
        }

        const item = curriculumSelection.item;
        const restBase = contentTypeToPostType(item.type);
        const p = await getWpApi().get<{
          id: number;
          status?: string;
          meta?: Record<string, unknown>;
        }>(`/${restBase}/${item.id}?context=edit`);
        if (cancelled) return;
        const m = p.meta || {};
        const bits: string[] = [entityLabelForContent(item.type)];
        if (item.type === 'lesson') {
          const lt = String(m['_sikshya_lesson_type'] ?? m['sikshya_lesson_type'] ?? '') || '';
          const dur = String(m['_sikshya_lesson_duration'] ?? m['sikshya_lesson_duration'] ?? '') || '';
          if (dur) bits.push(dur);
          if (lt === 'video') bits.push('Video');
          if (lt === 'text') bits.push('Text');
        }
        if (item.type === 'quiz') {
          const tl = Number(m['_sikshya_quiz_time_limit'] ?? m['sikshya_quiz_time_limit'] ?? 0) || 0;
          if (tl > 0) bits.push(`${tl} min`);
        }
        if (item.type === 'assignment') {
          const pts = Number(m['_sikshya_assignment_points'] ?? m['sikshya_assignment_points'] ?? 0) || 0;
          if (pts > 0) bits.push(`${pts} pts`);
        }

        setEditorHeaderMeta({
          title: item.title || `${entityLabelForContent(item.type)} #${item.id}`,
          subtitleBits: bits,
          status: p.status || '',
        });
      } catch {
        // Non-fatal: header can still render without extra meta.
        setEditorHeaderMeta({
          title:
            curriculumSelection.kind === 'content'
              ? curriculumSelection.item.title
              : 'Chapter',
          subtitleBits: [curriculumSelection.kind === 'content' ? entityLabelForContent(curriculumSelection.item.type) : 'Chapter'],
        });
      }
    };

    void run();
    return () => {
      cancelled = true;
    };
  }, [activeTab, courseId, curriculumSelection, curriculumTree]);

  const submitNewChapter = async () => {
    const name = newChapterTitle.trim();
    if (!courseId || !name) {
      return;
    }
    setChapterModalBusy(true);
    setChapterActionError(null);
    try {
      const created = await getSikshyaApi().post<{
        success: boolean;
        data?: { chapter_id?: number };
        message?: string;
      }>(SIKSHYA_ENDPOINTS.curriculum.chapters, {
        course_id: courseId,
        title: name,
      });
      if (!created.success) {
        throw new Error(created.message || 'Could not create chapter.');
      }
      const treeRes = await getSikshyaApi().get<{
        success: boolean;
        data?: { chapters: CurriculumChapterTree[] };
      }>(SIKSHYA_ENDPOINTS.admin.courseCurriculumTree(courseId));
      setCurriculumTree(treeRes.success && treeRes.data?.chapters ? treeRes.data.chapters : []);
      const newChapterId = created.data?.chapter_id;
      if (newChapterId && newChapterId > 0) {
        setCurriculumSelection({ kind: 'chapter', chapterId: newChapterId });
      }
      setChapterModalOpen(false);
      setNewChapterTitle('New chapter');
    } catch (e) {
      if (preferToastForApiError(e)) {
        setChapterActionError(null);
        toast.error(getApiErrorToastTitle(e), getErrorSummary(e));
      } else {
        setChapterActionError(e);
      }
    } finally {
      setChapterModalBusy(false);
    }
  };

  const submitNewContent = async () => {
    const name = addContentTitle.trim();
    if (!courseId || !name || !addContentChapterId) {
      return;
    }
    setAddContentBusy(true);
    setAddContentError(null);
    try {
      const resolvedType: 'lesson' | 'quiz' | 'assignment' =
        addContentType === 'quiz' ? 'quiz' : addContentType === 'assignment' ? 'assignment' : 'lesson';
      const lessonKind =
        addContentType === 'lesson_video'
          ? 'video'
          : addContentType === 'lesson_live'
          ? 'live'
          : addContentType === 'lesson_scorm'
          ? 'scorm'
          : addContentType === 'lesson_h5p'
          ? 'h5p'
          : addContentType === 'lesson_text'
          ? 'text'
          : '';

      const created = await getSikshyaApi().post<{
        success: boolean;
        data?: { content_id?: number };
        message?: string;
      }>(SIKSHYA_ENDPOINTS.curriculum.content, {
        title: name,
        type: resolvedType,
        description: '',
        ...(resolvedType === 'lesson' ? { lesson_type: lessonKind } : null),
      });
      if (!created.success || !created.data?.content_id) {
        throw new Error(created.message || 'Could not create content.');
      }
      const cid = created.data.content_id;
      const linked = await getSikshyaApi().post<{ success: boolean; message?: string }>(
        SIKSHYA_ENDPOINTS.curriculum.contentLink,
        {
          content_id: cid,
          chapter_id: addContentChapterId,
        }
      );
      if (!linked.success) {
        throw new Error(linked.message || 'Could not link content to chapter.');
      }
      const treeRes = await getSikshyaApi().get<{
        success: boolean;
        data?: { chapters: CurriculumChapterTree[] };
      }>(SIKSHYA_ENDPOINTS.admin.courseCurriculumTree(courseId));
      setCurriculumTree(treeRes.success && treeRes.data?.chapters ? treeRes.data.chapters : []);
      setCurriculumSelection({
        kind: 'content',
        chapterId: addContentChapterId,
        item: { id: cid, title: name, type: resolvedType },
      });
      setAddContentOpen(false);
      setAddContentTitle('');
    } catch (e) {
      if (preferToastForApiError(e)) {
        setAddContentError(null);
        toast.error(getApiErrorToastTitle(e), getErrorSummary(e));
      } else {
        setAddContentError(e);
      }
    } finally {
      setAddContentBusy(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={subtitle}
      badge={isBundleUi ? 'Bundle' : courseId ? 'Editing' : 'Draft'}
      pageActions={
        <div className="flex w-full flex-wrap items-center justify-between gap-3">
          <a
            href={appViewHref(config, 'courses')}
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            <span aria-hidden>←</span> All {termLower(config, 'courses')}
          </a>
          {courseId ? (
            <span className="text-xs tabular-nums text-slate-500 dark:text-slate-400">
              {term(config, 'course')} ID {courseId}
            </span>
          ) : null}
        </div>
      }
    >
      <AddChapterModal
        open={chapterModalOpen}
        title={newChapterTitle}
        onTitleChange={setNewChapterTitle}
        onClose={() => {
          if (!chapterModalBusy) {
            setChapterModalOpen(false);
          }
        }}
        onSubmit={() => void submitNewChapter()}
        busy={chapterModalBusy}
      />
      <AddContentTypePickerModal
        open={addContentOpen}
        addonsHref={appViewHref(config, 'addons')}
        contextLabel="Chapter"
        contextValue={curriculumTree.find((c) => c.id === addContentChapterId)?.title ?? ''}
        contentType={addContentType}
        onContentTypeChange={(t) => {
          setAddContentType(t);
          setAddContentTitle(defaultTitleFor(t));
        }}
        title={addContentTitle}
        onTitleChange={setAddContentTitle}
        onClose={() => {
          if (!addContentBusy) {
            setAddContentOpen(false);
          }
        }}
        onSubmit={() => {
          if (addContentChapterId <= 0) {
            return;
          }
          void submitNewContent();
        }}
        busy={addContentBusy}
      />
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      {bootstrap.loading && <CourseBuilderSkeleton />}
      {bootstrap.error && (
        <ApiErrorPanel error={bootstrap.error} onRetry={bootstrap.refetch} title="Could not load course" />
      )}
      {!bootstrap.loading && !bootstrap.error && bootstrap.data && (
        <div className="rounded-xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
          <div className="rounded-t-xl border-b border-slate-100 bg-slate-50/90 dark:border-slate-800 dark:bg-slate-900/90">
            <div className="flex flex-col gap-3 px-2 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-4 sm:py-3">
              {tabs.length > 1 ? (
                <nav
                  className="flex min-w-0 flex-1 gap-0 overflow-x-auto pb-px [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                  aria-label="Course builder steps"
                >
                  {tabs.map((tab) => {
                    const active = activeTab === tab.id;
                    const short = builderTabShortLabel(tab.id, tab.title);
                    const ic = builderTabIcon(tab.id);
                    return (
                      <button
                        key={tab.id}
                        type="button"
                        title={tab.description ? `${tab.title} — ${tab.description}` : tab.title}
                        onClick={() => setActiveTab(tab.id)}
                        className={`relative flex shrink-0 items-center gap-2 border-b-2 px-2.5 py-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 sm:px-4 ${
                          active
                            ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300'
                            : 'border-transparent text-slate-500 hover:border-slate-200 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200'
                        }`}
                      >
                        <NavIcon
                          name={ic}
                          className={`h-4 w-4 shrink-0 sm:h-[18px] sm:w-[18px] ${active ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'}`}
                        />
                        <span className="sm:hidden">{short}</span>
                        <span className="hidden sm:inline">{tab.title}</span>
                      </button>
                    );
                  })}
                </nav>
              ) : (
                <div className="min-w-0 flex-1" />
              )}
              <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-slate-100/80 pt-2 sm:border-t-0 sm:pt-0">
                {previewUrl ? (
                  <a
                    href={previewUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 rounded-lg border border-slate-200/90 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    <NavIcon name="iconPreview" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                    Preview
                  </a>
                ) : null}
                <button
                  type="button"
                  disabled={saving || bootstrap.loading || !bootstrap.data}
                  onClick={() => onSave('draft')}
                  className="inline-flex items-center gap-2 rounded-lg border border-slate-200/90 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  <NavIcon name="iconSaveDraft" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                  {saving ? 'Saving…' : 'Save draft'}
                </button>
                <ButtonPrimary
                  type="button"
                  disabled={saving || bootstrap.loading || !bootstrap.data}
                  onClick={() => onSave('publish')}
                  className="inline-flex items-center gap-2 rounded-lg px-4 py-2.5"
                >
                  <NavIcon name="iconPublish" className="h-4 w-4 text-white/90" />
                  {saving ? 'Publishing…' : 'Publish'}
                </ButtonPrimary>
              </div>
            </div>
          </div>

          <div
            className={`grid ${
              activeTab === 'curriculum' ? 'min-h-[min(70vh,720px)]' : 'min-h-0'
            } grid-cols-1 divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-[minmax(300px,380px)_minmax(0,1fr)] lg:divide-x lg:divide-y-0`}
          >
            {/* Left: curriculum outline OR in-page section nav */}
            <aside
              className={`order-2 flex min-h-0 flex-col bg-slate-50/50 text-slate-900 dark:bg-slate-950/40 dark:text-slate-100 lg:order-none lg:shrink-0 ${
                activeTab === 'curriculum'
                  ? curriculumOutlineFullHeight
                    ? 'lg:relative lg:self-start'
                    : 'lg:sticky lg:top-4 lg:max-h-[calc(100vh-4.5rem)] lg:self-start'
                  : 'lg:sticky lg:top-4 lg:self-start'
              }`}
            >
              {activeTab === 'curriculum' ? (
                <>
                  <div className="border-b border-slate-200/70 px-4 pb-3 pt-4 dark:border-slate-800">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-[11px] font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">
                        {term(config, 'course')} outline
                      </div>
                      <div className="shrink-0 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                        {curriculumStats.publishedLessons}/{curriculumStats.totalLessons} {termLower(config, 'lessons')}{' '}
                        published
                      </div>
                    </div>
                    <div className="mt-3 rounded-xl border border-slate-200/80 bg-white/70 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900/55">
                      <label
                        htmlFor="sikshya-cb-curriculum-outline-full-height"
                        className="flex cursor-pointer items-center gap-2.5"
                      >
                        <input
                          id="sikshya-cb-curriculum-outline-full-height"
                          type="checkbox"
                          checked={curriculumOutlineFullHeight}
                          onChange={(e) => setCurriculumOutlineFullHeightPersisted(e.target.checked)}
                          className="h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-800 dark:focus:ring-brand-400/25"
                        />
                        <span className="min-w-0 text-xs font-semibold leading-snug text-slate-800 dark:text-slate-100">
                          {curriculumOutlineFullHeight
                            ? 'Outline: page scroll'
                            : 'Outline: panel scroll'}
                        </span>
                      </label>
                    </div>
                  </div>
                  <div
                    className={
                      curriculumOutlineFullHeight ? 'flex-1 px-4 pb-4' : 'flex min-h-0 flex-1 flex-col px-4 pb-4'
                    }
                  >
                    <div
                      className={
                        curriculumOutlineFullHeight
                          ? ''
                          : 'min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-y-contain pr-0.5 [-webkit-overflow-scrolling:touch] [scrollbar-width:thin]'
                      }
                      role={curriculumOutlineFullHeight ? undefined : 'region'}
                      aria-label={curriculumOutlineFullHeight ? undefined : 'Scrollable course outline'}
                    >
                    {curriculumError && (
                      <div className="mb-3">
                        <ApiErrorPanel
                          error={curriculumError}
                          title="Could not load curriculum"
                          onRetry={() => {
                            setCurriculumError(null);
                            if (courseId) {
                              setCurriculumLoading(true);
                              void getSikshyaApi()
                                .get<{ success: boolean; data?: { chapters: CurriculumChapterTree[] } }>(
                                  SIKSHYA_ENDPOINTS.admin.courseCurriculumTree(courseId)
                                )
                                .then((res) => {
                                  setCurriculumTree(res.success && res.data?.chapters ? res.data.chapters : []);
                                  setCurriculumError(null);
                                })
                                .catch((e) => setCurriculumError(e))
                                .finally(() => setCurriculumLoading(false));
                            }
                          }}
                        />
                      </div>
                    )}
                    {chapterActionError && (
                      <div className="mb-3">
                        <ApiErrorPanel
                          error={chapterActionError}
                          title="Could not create chapter"
                          onRetry={() => setChapterActionError(null)}
                        />
                      </div>
                    )}
                    {addContentError && (
                      <div className="mb-3">
                        <ApiErrorPanel
                          error={addContentError}
                          title="Could not add content"
                          onRetry={() => setAddContentError(null)}
                        />
                      </div>
                    )}
                    {curriculumOutlineError && (
                      <div className="mb-3">
                        <ApiErrorPanel
                          error={curriculumOutlineError}
                          title="Could not save outline order"
                          onRetry={() => setCurriculumOutlineError(null)}
                        />
                      </div>
                    )}
                    {curriculumOutlineSaving ? (
                      <p className="mb-2 text-center text-[11px] font-medium text-slate-500 dark:text-slate-400">
                        Saving outline…
                      </p>
                    ) : null}
                    {curriculumLoading ? (
                      <ul className="space-y-3" aria-busy="true" aria-label="Loading curriculum">
                        {Array.from({ length: 4 }).map((_, i) => (
                          <li key={i}>
                            <SkeletonLine className="h-4 w-full max-w-[12rem]" />
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <ol className="mt-3 space-y-3">
                        {curriculumTree.map((ch, idx) => {
                          const expanded = openChapters[ch.id] !== false;
                          const outlineItems = curriculumOutlineItems(ch.contents);
                          const chapterPublished = outlineItems.filter((i) => (i.status || '').toLowerCase() === 'publish')
                            .length;
                          const chapterTotal = outlineItems.length;
                          const chapterSelected =
                            curriculumSelection?.kind === 'chapter' && curriculumSelection.chapterId === ch.id;
                          const chapterRowDraggable =
                            Boolean(courseId) && !curriculumOutlineSaving && !curriculumLoading;
                          const chapterActions: RowActionItem[] = [
                            {
                              key: 'delete',
                              label: 'Delete chapter',
                              danger: true,
                              onClick: () => void permanentlyDeleteCurriculumPost('sik_chapter', ch.id),
                            },
                          ];
                          return (
                            <li
                              key={ch.id}
                              className="group rounded-xl bg-white/80 shadow-sm ring-1 ring-slate-200/70 backdrop-blur-sm dark:bg-slate-900/70 dark:ring-slate-800"
                              onDragOver={(e) => {
                                if (curriculumOutlineSaving || !courseId) {
                                  return;
                                }
                                if (![...e.dataTransfer.types].includes(CURRICULUM_DRAG_MIME)) {
                                  return;
                                }
                                e.preventDefault();
                                e.dataTransfer.dropEffect = 'move';
                              }}
                              onDrop={(e) => {
                                e.preventDefault();
                                if (curriculumOutlineSaving || !courseId) {
                                  return;
                                }
                                const payload = parseCurriculumDrag(e.dataTransfer);
                                if (!payload) {
                                  return;
                                }
                                if (payload.t === 'chapter') {
                                  const fromIdx = curriculumTree.findIndex((c) => c.id === payload.chapterId);
                                  if (fromIdx < 0 || fromIdx === idx) {
                                    return;
                                  }
                                  const next = reorderChaptersAtIndex(curriculumTree, fromIdx, idx);
                                  if (next === curriculumTree) {
                                    return;
                                  }
                                  void persistCurriculumOutline(next);
                                  return;
                                }
                                if (payload.t === 'content') {
                                  const next = moveContentBeforeItem(
                                    curriculumTree,
                                    payload.contentId,
                                    payload.fromChapterId,
                                    ch.id,
                                    null
                                  );
                                  if (next === curriculumTree) {
                                    return;
                                  }
                                  void persistCurriculumOutline(next);
                                }
                              }}
                            >
                              <div
                                className={`grid grid-cols-[1.25rem_1.75rem_minmax(0,1fr)] items-center rounded-lg px-3 transition-colors ${
                                  chapterSelected ? BUILDER_NAV_ACTIVE : BUILDER_NAV_INACTIVE
                                }`}
                                draggable={chapterRowDraggable}
                                onClick={() => setCurriculumSelection({ kind: 'chapter', chapterId: ch.id })}
                                onDragStart={(e) => {
                                  if (!chapterRowDraggable) {
                                    return;
                                  }
                                  // Critical: prevent nested content rows from triggering chapter drag.
                                  e.stopPropagation();
                                  e.dataTransfer.setData(
                                    CURRICULUM_DRAG_MIME,
                                    JSON.stringify({ t: 'chapter', chapterId: ch.id })
                                  );
                                  e.dataTransfer.effectAllowed = 'move';
                                  try {
                                    e.dataTransfer.setDragImage(e.currentTarget, 24, 18);
                                  } catch {
                                    /* ignore */
                                  }
                                }}
                              >
                                <span
                                  className="flex h-10 w-5 shrink-0 select-none items-center justify-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-200"
                                  aria-hidden
                                >
                                  <NavIcon name="dragHandle" className="h-4 w-4" />
                                </span>
                                <button
                                  type="button"
                                  className={`flex h-10 w-7 shrink-0 items-center justify-center ${
                                    chapterSelected
                                      ? 'text-brand-600 dark:text-brand-400'
                                      : 'text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                  }`}
                                  aria-expanded={expanded}
                                  aria-label={expanded ? 'Collapse chapter' : 'Expand chapter'}
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    setOpenChapters((p) => ({
                                      ...p,
                                      [ch.id]: !expanded,
                                    }));
                                  }}
                                >
                                  <NavIcon
                                    name={expanded ? 'chevronDown' : 'chevronRight'}
                                    className="h-4 w-4"
                                  />
                                </button>
                                <div className="flex min-w-0 items-center justify-between gap-2 py-2 text-left text-sm">
                                  <div className="flex min-w-0 flex-1 items-center gap-2 text-left">
                                    <span className="text-[11px] font-semibold tabular-nums text-brand-600 dark:text-brand-300">
                                      {String(idx + 1).padStart(2, '0')}
                                    </span>
                                    <span className="min-w-0 flex-1 truncate font-medium leading-snug">{ch.title}</span>
                                  </div>
                                  <span className="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {chapterPublished}/{chapterTotal}
                                  </span>
                                  <div
                                    className="shrink-0"
                                    onClick={(e) => {
                                      // Keep clicking the action menu from selecting the chapter.
                                      e.stopPropagation();
                                    }}
                                  >
                                    <RowActionsMenu items={chapterActions} ariaLabel="Chapter actions" />
                                  </div>
                                </div>
                              </div>
                              {expanded ? (
                                <div className="border-t border-slate-100 dark:border-slate-800">
                                  <div className="px-3 pb-2 pt-1.5">
                                    <div className="min-w-0 space-y-0.5">
                                      {outlineItems.length > 0 ? (
                                        <ul className="space-y-0.5">
                                          {outlineItems.map((item) => {
                                            const sel =
                                              curriculumSelection?.kind === 'content' &&
                                              curriculumSelection.item.id === item.id;
                                            const contentRowDraggable =
                                              Boolean(courseId) && !curriculumOutlineSaving && !curriculumLoading;
                                            const pt = contentTypeToPostType(item.type);
                                            const contentActions: RowActionItem[] = [
                                              {
                                                key: 'remove',
                                                label: 'Remove from chapter',
                                                onClick: () => {
                                                  const next = removeContentFromChapter(curriculumTree, ch.id, item.id);
                                                  if (next !== curriculumTree) {
                                                    void persistCurriculumOutline(next);
                                                  }
                                                },
                                              },
                                              {
                                                key: 'delete',
                                                label: `Delete ${entityLabelForContent(item.type).toLowerCase()}`,
                                                danger: true,
                                                onClick: () => void permanentlyDeleteCurriculumPost(pt, item.id),
                                              },
                                            ];
                                            return (
                                              <li
                                                key={item.id}
                                                className="rounded-lg"
                                                draggable={contentRowDraggable}
                                                onDragStart={(e) => {
                                                  if (!contentRowDraggable) {
                                                    return;
                                                  }
                                                  // Prevent parent draggable regions from receiving this dragstart.
                                                  e.stopPropagation();
                                                  e.dataTransfer.setData(
                                                    CURRICULUM_DRAG_MIME,
                                                    JSON.stringify({
                                                      t: 'content',
                                                      contentId: item.id,
                                                      fromChapterId: ch.id,
                                                    })
                                                  );
                                                  e.dataTransfer.effectAllowed = 'move';
                                                  try {
                                                    e.dataTransfer.setDragImage(e.currentTarget, 24, 16);
                                                  } catch {
                                                    /* ignore */
                                                  }
                                                }}
                                                onDragOver={(e) => {
                                                  if (curriculumOutlineSaving || !courseId) {
                                                    return;
                                                  }
                                                  if (![...e.dataTransfer.types].includes(CURRICULUM_DRAG_MIME)) {
                                                    return;
                                                  }
                                                  e.preventDefault();
                                                  e.stopPropagation();
                                                  e.dataTransfer.dropEffect = 'move';
                                                }}
                                                onDrop={(e) => {
                                                  e.preventDefault();
                                                  e.stopPropagation();
                                                  if (curriculumOutlineSaving || !courseId) {
                                                    return;
                                                  }
                                                  const payload = parseCurriculumDrag(e.dataTransfer);
                                                  if (!payload || payload.t !== 'content') {
                                                    return;
                                                  }
                                                  const next = moveContentBeforeItem(
                                                    curriculumTree,
                                                    payload.contentId,
                                                    payload.fromChapterId,
                                                    ch.id,
                                                    item.id
                                                  );
                                                  if (next === curriculumTree) {
                                                    return;
                                                  }
                                                  void persistCurriculumOutline(next);
                                                }}
                                              >
                                                <div className="grid grid-cols-[1.25rem_minmax(0,1fr)] items-center gap-2 py-1">
                                                  <span
                                                    className="flex h-8 w-5 shrink-0 select-none items-center justify-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-200"
                                                    aria-hidden
                                                  >
                                                    <NavIcon name="dragHandle" className="h-4 w-4" />
                                                  </span>
                                                  <div
                                                    className={`flex min-w-0 items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm leading-snug transition-colors ${
                                                      sel
                                                        ? `${BUILDER_NAV_ACTIVE_NESTED} font-medium`
                                                        : BUILDER_NAV_INACTIVE
                                                    }`}
                                                  >
                                                    <button
                                                      type="button"
                                                      draggable={false}
                                                      onClick={() =>
                                                        setCurriculumSelection({
                                                          kind: 'content',
                                                          chapterId: ch.id,
                                                          item,
                                                        })
                                                      }
                                                      className="flex min-w-0 flex-1 items-center gap-2 text-left"
                                                    >
                                                      <span
                                                        className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-md ${
                                                          sel
                                                            ? 'bg-brand-100 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300'
                                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200'
                                                        }`}
                                                      >
                                                        <NavIcon
                                                          name={curriculumItemIcon(item)}
                                                          className="h-4 w-4"
                                                        />
                                                      </span>
                                                      <span className="min-w-0 flex-1 truncate">{item.title}</span>
                                                    </button>
                                                    {curriculumItemRightMeta(item) ? (
                                                      <span className="hidden shrink-0 text-xs font-medium tabular-nums text-slate-500 dark:text-slate-400 sm:inline">
                                                        {curriculumItemRightMeta(item)}
                                                      </span>
                                                    ) : null}
                                                    <div className="shrink-0">
                                                      <RowActionsMenu items={contentActions} ariaLabel="Content actions" />
                                                    </div>
                                                  </div>
                                                </div>
                                              </li>
                                            );
                                          })}
                                        </ul>
                                      ) : (
                                        <div className="rounded-lg border border-dashed border-slate-200 bg-white/60 px-3 py-6 text-center dark:border-slate-700 dark:bg-slate-900/40">
                                          <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                            <NavIcon name="plusDocument" className="h-5 w-5" />
                                          </div>
                                          <p className="mt-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                            No content in this chapter yet
                                          </p>
                                          <p className="mx-auto mt-1 max-w-[14rem] text-xs leading-snug text-slate-500 dark:text-slate-400">
                                            Add a lesson, quiz, or assignment to get started.
                                          </p>
                                        </div>
                                      )}
                                      <div
                                        className="mt-2"
                                        onDragOver={(e) => {
                                          if (curriculumOutlineSaving || !courseId) {
                                            return;
                                          }
                                          if (![...e.dataTransfer.types].includes(CURRICULUM_DRAG_MIME)) {
                                            return;
                                          }
                                          e.preventDefault();
                                          e.stopPropagation();
                                          e.dataTransfer.dropEffect = 'move';
                                        }}
                                        onDrop={(e) => {
                                          e.preventDefault();
                                          e.stopPropagation();
                                          if (curriculumOutlineSaving || !courseId) {
                                            return;
                                          }
                                          const payload = parseCurriculumDrag(e.dataTransfer);
                                          if (!payload || payload.t !== 'content') {
                                            return;
                                          }
                                          const next = moveContentBeforeItem(
                                            curriculumTree,
                                            payload.contentId,
                                            payload.fromChapterId,
                                            ch.id,
                                            null
                                          );
                                          if (next === curriculumTree) {
                                            return;
                                          }
                                          void persistCurriculumOutline(next);
                                        }}
                                      >
                                        <button
                                          type="button"
                                          disabled={
                                            !courseId || saving || curriculumLoading || curriculumOutlineSaving
                                          }
                                          className="w-full rounded-md border border-dashed border-slate-200 py-2 text-center text-xs font-semibold text-brand-700 transition hover:border-brand-300 hover:bg-brand-50 disabled:opacity-50 dark:border-slate-600 dark:text-brand-400 dark:hover:border-brand-800 dark:hover:bg-brand-950/30"
                                          onClick={() => {
                                            setAddContentChapterId(ch.id);
                                            setAddContentType('lesson_text');
                                            setAddContentTitle('New text lesson');
                                            setAddContentError(null);
                                            setAddContentOpen(true);
                                          }}
                                        >
                                          + Add content
                                        </button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              ) : null}
                            </li>
                          );
                        })}
                        {!curriculumTree.length && !curriculumError && (
                          <li className="rounded-xl border border-dashed border-slate-200 bg-white/50 px-4 py-10 text-center dark:border-slate-700 dark:bg-slate-900/40">
                            <p className="text-sm font-medium text-slate-700 dark:text-slate-200">Start with a chapter</p>
                            <p className="mx-auto mt-2 max-w-[16rem] text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                              Chapters are sections (like “Week 1”). Use “Add chapter” below, open the chapter, then “Add content”
                              for lessons and quizzes.
                            </p>
                          </li>
                        )}
                      </ol>
                    )}
                    <div className="mt-4">
                      <ButtonPrimary
                        type="button"
                        disabled={!courseId || saving || curriculumLoading}
                        className="w-full rounded-lg bg-slate-900 py-3 text-sm font-semibold hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                        onClick={() => {
                          if (!courseId) {
                            return;
                          }
                          setNewChapterTitle('New chapter');
                          setChapterModalOpen(true);
                        }}
                      >
                        + Add chapter
                      </ButtonPrimary>
                    </div>
                    </div>
                  </div>
                </>
              ) : (
                <BuilderSectionMenu
                  sections={sections}
                  activeTab={activeTab}
                  openSectionKey={openSectionKey}
                  onSelectSection={setOpenSectionKey}
                />
              )}
            </aside>

            {/* Center: main editor */}
            <main className="order-1 min-w-0 bg-white px-3 py-5 sm:px-5 lg:order-none lg:px-6 lg:py-6 xl:px-8 dark:bg-slate-900">
              {activeTab !== 'curriculum' && activeTabMeta?.description ? (
                <div className="mb-6 rounded-xl border border-slate-100 bg-slate-50/90 px-4 py-3 text-sm leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-800/45 dark:text-slate-300">
                  {activeTabMeta.description}
                </div>
              ) : null}
              {activeTab === 'curriculum' ? (
                <div className="w-full max-w-none">
                  {!curriculumSelection ? (
                    <div className="rounded-xl border border-dashed border-slate-200/90 bg-slate-50/30 px-8 py-16 text-center dark:border-slate-700 dark:bg-slate-900/30">
                      <NavIcon
                        name="curriculumOutline"
                        className="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600"
                      />
                      <p className="mt-4 text-sm font-medium text-slate-700 dark:text-slate-200">
                        Choose something from the outline
                      </p>
                      <p className="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Click a chapter to edit its title, or click a lesson, quiz, or assignment to edit its content here. Add
                        quiz questions inside each quiz — not from this empty state.
                      </p>
                    </div>
                  ) : curriculumSelection.kind === 'chapter' ? (
                    <div key={`cb-ch-${curriculumSelection.chapterId}`} className="min-h-[min(60vh,560px)]">
                      {renderContentEditor('sik_chapter', {
                        config,
                        postType: 'sik_chapter',
                        postId: curriculumSelection.chapterId,
                        backHref: builderBackHref,
                        entityLabel: 'Chapter',
                        embedded: true,
                        forcedCourseId: courseId,
                        exposeSave: registerEmbeddedSave,
                      })}
                    </div>
                  ) : (
                    <div key={`cb-co-${curriculumSelection.item.id}`} className="min-h-[min(60vh,560px)]">
                      {renderContentEditor(contentTypeToPostType(curriculumSelection.item.type), {
                        config,
                        postType: contentTypeToPostType(curriculumSelection.item.type),
                        postId: curriculumSelection.item.id,
                        backHref: builderBackHref,
                        entityLabel: entityLabelForContent(curriculumSelection.item.type),
                        embedded: true,
                        exposeSave: registerEmbeddedSave,
                      })}
                    </div>
                  )}
                </div>
              ) : null}

              {Object.entries(sections).map(([sectionKey, section]) => {
                if (sectionKey !== openSectionKey) {
                  return null;
                }
                const fieldEntries = Object.entries(section.fields || {}).filter(
                  ([_, fcfg]) =>
                    !(activeTab === 'curriculum' && fcfg.type === 'curriculum_builder') && fieldIsVisible(fcfg, values)
                );
                if (fieldEntries.length === 0) {
                  return null;
                }
                const layoutRows = buildFieldLayoutRows(fieldEntries);
                return (
                  <div
                    key={sectionKey}
                    id={`cb-section-${sectionKey}`}
                    className="rounded-xl border border-slate-200/75 bg-white dark:border-slate-800 dark:bg-slate-900/80"
                  >
                    <div className="border-b border-slate-100 px-6 py-5 dark:border-slate-800">
                      {activeTabMeta ? (
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">
                          {activeTabMeta.title}
                        </p>
                      ) : null}
                      {section.section?.title ? (
                        <h3 className="mt-1 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                          {section.section.title}
                        </h3>
                      ) : null}
                      {section.section?.description ? (
                        <p className="mt-2 max-w-3xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                          {section.section.description}
                        </p>
                      ) : null}
                    </div>
                    <div className="space-y-8 px-6 py-7">
                      {layoutRows.map((row, rowIdx) => {
                        if (row.kind === 'full') {
                          return row.fields.map(([fid, fcfg]) => (
                            <BuilderFieldBlock
                              key={`${rowIdx}-${fid}`}
                              config={config}
                              fid={fid}
                              fcfg={fcfg}
                              values={values}
                              users={users}
                              siteUrl={siteUrl}
                              courseId={courseId}
                              onFieldChange={handleFieldChange}
                            />
                          ));
                        }
                        const gridCols = row.cols === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3';
                        return (
                          <div key={`row-${rowIdx}`} className={`grid gap-6 ${gridCols}`}>
                            {row.fields.map(([fid, fcfg]) => (
                              <BuilderFieldBlock
                                key={fid}
                                config={config}
                                fid={fid}
                                fcfg={fcfg}
                                values={values}
                                users={users}
                                siteUrl={siteUrl}
                                courseId={courseId}
                                onFieldChange={handleFieldChange}
                              />
                            ))}
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })}
            </main>
          </div>
        </div>
      )}
    </EmbeddableShell>
  );
}
