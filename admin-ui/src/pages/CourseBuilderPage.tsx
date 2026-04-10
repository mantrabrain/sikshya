import { useEffect, useMemo, useRef, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { NavIcon } from '../components/NavIcon';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { WPMediaPickerField } from '../components/shared/WPMediaPickerField';
import { CourseBuilderSkeleton, SkeletonLine } from '../components/shared/Skeleton';
import { renderContentEditor } from './content-editors/editors';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { useAsyncData } from '../hooks/useAsyncData';
import { appViewHref } from '../lib/appUrl';
import type { FieldConfig, NavItem, SikshyaReactConfig, TabFieldsMap } from '../types';

/** Shared field chrome — one place for focus rings and dark mode. */
const FIELD_INPUT =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';
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

/** Types available when adding from a chapter (questions are created inside / for a quiz, not here). */
export type CurriculumAddableType = 'lesson_text' | 'lesson_video' | 'quiz' | 'assignment';

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

function FieldInput(props: {
  id: string;
  cfg: FieldConfig;
  value: unknown;
  onChange: (v: unknown) => void;
  users: UserOpt[];
  /** Site root URL for permalink preview */
  siteUrl?: string;
}) {
  const { id, cfg, value, onChange, users, siteUrl } = props;
  const t = cfg.type || 'text';
  const base = (siteUrl || '').replace(/\/?$/, '/');

  if (t === 'textarea') {
    return (
      <textarea
        id={id}
        rows={5}
        className={`${FIELD_INPUT} min-h-[120px] resize-y`}
        placeholder={cfg.placeholder}
        value={(value as string) ?? ''}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (t === 'select') {
    const opts = cfg.options || {};
    return (
      <select
        id={id}
        className={FIELD_INPUT}
        value={(value as string) ?? ''}
        onChange={(e) => onChange(e.target.value)}
      >
        {Object.entries(opts).map(([k, lab]) => (
          <option key={k} value={k}>
            {lab}
          </option>
        ))}
      </select>
    );
  }

  if (t === 'date') {
    const raw = value === undefined || value === null ? '' : String(value);
    const iso = raw.length >= 10 ? raw.slice(0, 10) : raw;
    return (
      <input
        id={id}
        type="date"
        className={FIELD_INPUT}
        value={iso}
        onChange={(e) => onChange(e.target.value)}
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

  if (t === 'user_select' && cfg.multiple) {
    const ids = Array.isArray(value) ? (value as number[]) : [];
    return (
      <div className="mt-1.5 space-y-2">
        <select
          id={id}
          multiple
          size={Math.min(8, Math.max(4, users.length + 1))}
          className={`${FIELD_INPUT} min-h-[8rem] py-2 dark:[color-scheme:dark]`}
          value={ids.map(String)}
          onChange={(e) => {
            const selected = Array.from(e.target.selectedOptions).map((o) => Number(o.value));
            onChange(selected);
          }}
        >
          {users.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </select>
        <p className="text-xs text-slate-500 dark:text-slate-400">
          Hold <kbd className="rounded border border-slate-300 bg-slate-100 px-1 py-0.5 font-mono text-[10px] dark:border-slate-600 dark:bg-slate-800">⌘</kbd> or{' '}
          <kbd className="rounded border border-slate-300 bg-slate-100 px-1 py-0.5 font-mono text-[10px] dark:border-slate-600 dark:bg-slate-800">Ctrl</kbd> to select multiple instructors.
        </p>
      </div>
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
    const subKeys = Object.keys(cfg.subfields);
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
                return (
                  <label key={sk} className={`block min-w-0 ${st === 'textarea' ? 'sm:col-span-2' : ''}`}>
                    <span className={FIELD_LABEL}>{sub.label || sk}</span>
                    {st === 'textarea' ? (
                      <textarea
                        rows={3}
                        className={`${FIELD_INPUT} min-h-[88px] resize-y`}
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
            subKeys.forEach((k) => {
              empty[k] = '';
            });
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
  fid: string;
  fcfg: FieldConfig;
  values: Record<string, unknown>;
  users: UserOpt[];
  siteUrl: string;
  onFieldChange: (fid: string, v: unknown) => void;
}) {
  const { fid, fcfg, values, users, siteUrl, onFieldChange } = props;
  const isCheckbox = (fcfg.type || 'text') === 'checkbox';

  if (isCheckbox) {
    return (
      <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-3 dark:border-slate-700/80 dark:bg-slate-800/30">
        <FieldInput
          id={`f-${fid}`}
          cfg={fcfg}
          value={values[fid]}
          users={users}
          siteUrl={siteUrl}
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
      {fcfg.description ? <p className={FIELD_HINT}>{fcfg.description}</p> : null}
      <FieldInput
        id={`f-${fid}`}
        cfg={fcfg}
        value={values[fid]}
        users={users}
        siteUrl={siteUrl}
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
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.clearTimeout(t);
      window.removeEventListener('keydown', onKey);
    };
  }, [open, onClose]);

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
          New chapter
        </h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Chapters group your lessons and quizzes. You can refine details in the chapter editor anytime.
        </p>
        <label htmlFor="sikshya-new-chapter-title" className={`${FIELD_LABEL} mt-5`}>
          Chapter title
        </label>
        <input
          ref={inputRef}
          id="sikshya-new-chapter-title"
          type="text"
          className={FIELD_INPUT}
          placeholder="e.g. Introduction"
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

const CURRICULUM_ADD_TYPES: Array<{
  type: CurriculumAddableType;
  label: string;
  icon: 'bookOpen' | 'video' | 'puzzle' | 'clipboard';
}> = [
  { type: 'lesson_text', label: 'Text lesson', icon: 'bookOpen' },
  { type: 'lesson_video', label: 'Video lesson', icon: 'video' },
  { type: 'quiz', label: 'Quiz', icon: 'puzzle' },
  { type: 'assignment', label: 'Assignment', icon: 'clipboard' },
];

/** Outline rows: hide legacy linked questions (LMS model: questions sit under quizzes). */
function curriculumOutlineItems(contents: CurriculumContentItem[]): CurriculumContentItem[] {
  return contents.filter((c) => c.type !== 'question');
}

function curriculumItemIcon(item: CurriculumContentItem): string {
  if (item.type === 'lesson') {
    const lt = String(item.meta?.lesson_type || '').toLowerCase();
    if (lt === 'video') return 'video';
    return 'bookOpen';
  }
  const m: Record<CurriculumContentItem['type'], string> = {
    lesson: 'bookOpen',
    quiz: 'puzzle',
    assignment: 'clipboard',
    question: 'helpCircle',
  };
  return m[item.type] || 'plusDocument';
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
    if (lt === 'text') bits.push('Text');
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

function AddContentModal(props: {
  open: boolean;
  /** Target chapter (set from the outline — no picker in the modal). */
  chapterId: number;
  chapterTitle: string;
  contentType: CurriculumAddableType;
  onContentTypeChange: (t: CurriculumAddableType) => void;
  title: string;
  onTitleChange: (v: string) => void;
  onClose: () => void;
  onSubmit: () => void;
  busy: boolean;
}) {
  const {
    open,
    chapterId,
    chapterTitle,
    contentType,
    onContentTypeChange,
    title,
    onTitleChange,
    onClose,
    onSubmit,
    busy,
  } = props;
  const inputRef = useRef<HTMLInputElement>(null);

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
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.clearTimeout(t);
      window.removeEventListener('keydown', onKey);
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  const defaultTitle = (() => {
    const lab = CURRICULUM_ADD_TYPES.find((x) => x.type === contentType)?.label || 'Content';
    return `New ${lab.toLowerCase()}`;
  })();

  return (
    <div
      className="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-[2px] sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="sikshya-add-content-title"
    >
      <button type="button" className="absolute inset-0 z-0 cursor-default" aria-label="Close" onClick={onClose} />
      <div className="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h2 id="sikshya-add-content-title" className="text-lg font-semibold text-slate-900 dark:text-white">
          Add curriculum content
        </h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Add a lesson, quiz, or assignment to this chapter. Quiz questions are added inside the quiz, not here.
        </p>
        {chapterTitle ? (
          <p className="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-200">
            <span className="font-medium text-slate-500 dark:text-slate-400">Chapter: </span>
            <span className="font-semibold">{chapterTitle}</span>
          </p>
        ) : null}

        <div className={`${FIELD_LABEL} mt-5`}>Content type</div>
        <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-4">
          {CURRICULUM_ADD_TYPES.map((opt) => {
            const active = contentType === opt.type;
            return (
              <button
                key={opt.type}
                type="button"
                onClick={() => onContentTypeChange(opt.type)}
                className={`flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 text-xs font-semibold transition sm:text-sm ${
                  active
                    ? 'border-brand-500 bg-brand-50 text-brand-800 dark:border-brand-400 dark:bg-brand-950/50 dark:text-brand-200'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200'
                }`}
              >
                <NavIcon name={opt.icon} className="h-5 w-5" />
                {opt.label}
              </button>
            );
          })}
        </div>

        <label htmlFor="sikshya-add-content-title-input" className={`${FIELD_LABEL} mt-5`}>
          Title
        </label>
        <input
          ref={inputRef}
          id="sikshya-add-content-title-input"
          type="text"
          className={FIELD_INPUT}
          placeholder={defaultTitle}
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
          <ButtonPrimary
            type="button"
            className="px-4 py-2.5"
            disabled={busy || !title.trim() || !chapterId}
            onClick={onSubmit}
          >
            {busy ? 'Adding…' : 'Add content'}
          </ButtonPrimary>
        </div>
      </div>
    </div>
  );
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
  return Object.entries(section.fields || {}).some(
    ([_, fcfg]) => !(activeTab === 'curriculum' && fcfg.type === 'curriculum_builder')
  );
}

/** Icon keys from `icons.json` for left sidebar section rows. */
function sectionIconName(sectionKey: string): string {
  const m: Record<string, string> = {
    basic_info: 'course',
    media_visuals: 'photoImage',
    learning_outcomes: 'badge',
    instructors_section: 'users',
    marketing: 'plusDocument',
    pricing: 'chart',
    schedule: 'clipboard',
    access_enrollment: 'users',
    prerequisites: 'badge',
    advanced_features: 'cog',
    course_settings: 'cog',
    completion_rules: 'badge',
    interaction_features: 'helpCircle',
    certificate_settings: 'badge',
    seo_settings: 'search',
    seo: 'search',
    advanced_settings: 'cog',
    advanced: 'cog',
    curriculum: 'curriculumOutline',
    curriculum_outline: 'curriculumOutline',
  };
  return m[sectionKey] || 'plusDocument';
}

/** Icons for top horizontal builder tabs. */
function builderTabIcon(tabId: string): string {
  const m: Record<string, string> = {
    course: 'course',
    pricing: 'chart',
    curriculum: 'curriculumOutline',
    settings: 'cog',
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
                className={`flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2.5 text-left text-sm font-medium transition-colors ${
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

export function CourseBuilderPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const q = config.query || {};
  const courseIdFromUrl = Number(q.course_id || q.id || 0) || 0;

  if (!courseIdFromUrl) {
    return <CourseBuilderMissingSelection config={config} title={title} />;
  }

  return <CourseBuilderEditor config={config} title={title} courseId={courseIdFromUrl} />;
}

function CourseBuilderMissingSelection({ config, title }: { config: SikshyaReactConfig; title: string }) {
  const [createOpen, setCreateOpen] = useState(false);
  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Select or create a course to edit"
      pageActions={null}
    >
      <CreateCourseModal config={config} open={createOpen} onClose={() => setCreateOpen(false)} />
      <div className="flex min-h-[min(60vh,520px)] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-16 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-100 dark:bg-brand-950/50">
          <NavIcon name="course" className="h-8 w-8 text-brand-600 dark:text-brand-400" />
        </div>
        <h2 className="mt-6 text-xl font-semibold text-slate-900 dark:text-white">Open a course in the builder</h2>
        <p className="mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
          Start by naming a new draft—we save it and bring you here—or pick a course from the catalog.
        </p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Create course</ButtonPrimary>
          <a
            href={appViewHref(config, 'courses')}
            className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Browse courses
          </a>
        </div>
      </div>
    </AppShell>
  );
}

function CourseBuilderEditor({
  config,
  title,
  courseId: initialCid,
}: {
  config: SikshyaReactConfig;
  title: string;
  courseId: number;
}) {
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
  const [saveSuccess, setSaveSuccess] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<unknown>(null);
  const [saving, setSaving] = useState(false);
  const [openSectionKey, setOpenSectionKey] = useState<string | null>(null);
  const [chapterModalOpen, setChapterModalOpen] = useState(false);
  const [newChapterTitle, setNewChapterTitle] = useState('New chapter');
  const [chapterModalBusy, setChapterModalBusy] = useState(false);
  const bootOnceRef = useRef(false);

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
    setValues({ ...d.values, course_id: d.course_id });
    if (!bootOnceRef.current) {
      const req = String(config.query?.tab || '').trim();
      const nextTab = req && d.tabs.some((t) => t.id === req) ? req : d.tabs[0]?.id || 'course';
      setActiveTab(nextTab);
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
          setCurriculumError(e);
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
  const tabs = bootstrap.data?.tabs || [];
  const users = bootstrap.data?.users || [];

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

  useEffect(() => {
    if (!saveSuccess) {
      return;
    }
    const t = window.setTimeout(() => setSaveSuccess(null), 4500);
    return () => window.clearTimeout(t);
  }, [saveSuccess]);

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
    setSaving(true);
    setSaveSuccess(null);
    setSaveError(null);
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
        const fe = res.field_errors
          ? Object.values(res.field_errors).join(' ')
          : JSON.stringify(res.errors || {});
        throw new Error(res.message || fe || 'Save failed');
      }
      const newId = res.data?.course_id;
      if (newId && newId !== courseId) {
        window.location.href = appViewHref(config, 'add-course', { course_id: String(newId) });
        return;
      }
      setSaveSuccess(res.message || 'Saved.');
      bootstrap.refetch();
    } catch (e) {
      setSaveError(e);
    } finally {
      setSaving(false);
    }
  };

  const subtitle = useMemo(() => {
    if (values.title) {
      return String(values.title);
    }
    return initialCid ? `Course #${initialCid}` : 'New course';
  }, [values.title, initialCid]);

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
    if (!window.confirm('Delete this item permanently? This cannot be undone.')) {
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
      setChapterActionError(e);
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
      const lessonKind = addContentType === 'lesson_video' ? 'video' : addContentType === 'lesson_text' ? 'text' : '';

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
      setAddContentError(e);
    } finally {
      setAddContentBusy(false);
    }
  };

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle={subtitle}
      badge={courseId ? 'Editing' : 'Draft'}
      pageActions={
        <div className="flex w-full flex-wrap items-center justify-between gap-3">
          <a
            href={appViewHref(config, 'courses')}
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            <span aria-hidden>←</span> All courses
          </a>
          {courseId ? (
            <span className="text-xs tabular-nums text-slate-500 dark:text-slate-400">Course ID {courseId}</span>
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
      <AddContentModal
        open={addContentOpen}
        chapterId={addContentChapterId}
        chapterTitle={curriculumTree.find((c) => c.id === addContentChapterId)?.title ?? ''}
        contentType={addContentType}
        onContentTypeChange={(t) => {
          setAddContentType(t);
          const lab = CURRICULUM_ADD_TYPES.find((x) => x.type === t)?.label || 'Content';
          setAddContentTitle(`New ${lab.toLowerCase()}`);
        }}
        title={addContentTitle}
        onTitleChange={setAddContentTitle}
        onClose={() => {
          if (!addContentBusy) {
            setAddContentOpen(false);
          }
        }}
        onSubmit={() => void submitNewContent()}
        busy={addContentBusy}
      />
      {saveSuccess && (
        <div
          className="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100"
          role="status"
        >
          <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-200/80 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
            ✓
          </span>
          <div>
            <p className="font-medium">Saved</p>
            <p className="mt-0.5 text-emerald-800/90 dark:text-emerald-100/90">{saveSuccess}</p>
          </div>
        </div>
      )}
      {saveError && (
        <div className="mb-4">
          <ApiErrorPanel
            error={saveError}
            title="Could not save this course"
            onRetry={() => {
              setSaveError(null);
            }}
          />
        </div>
      )}

      {bootstrap.loading && <CourseBuilderSkeleton />}
      {bootstrap.error && (
        <ApiErrorPanel error={bootstrap.error} onRetry={bootstrap.refetch} title="Could not load course" />
      )}

      {!bootstrap.loading && !bootstrap.error && bootstrap.data && (
        <div className="rounded-xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
          <div className="rounded-t-xl border-b border-slate-100 bg-slate-50/90 dark:border-slate-800 dark:bg-slate-900/90">
            <div className="flex flex-col gap-3 px-2 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-4 sm:py-3">
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
                      className={`relative flex shrink-0 items-center gap-2 border-b-2 px-2.5 py-3 text-sm font-semibold transition sm:px-4 ${
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
            className={`grid min-h-[min(70vh,720px)] grid-cols-1 divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-[minmax(300px,380px)_minmax(0,1fr)] lg:divide-x lg:divide-y-0`}
          >
            {/* Left: curriculum outline OR in-page section nav */}
            <aside className="order-2 flex min-h-0 flex-col bg-slate-50/50 text-slate-900 dark:bg-slate-950/40 dark:text-slate-100 lg:sticky lg:top-4 lg:order-none lg:self-start lg:shrink-0">
              {activeTab === 'curriculum' ? (
                <>
                  <div className="border-b border-slate-200/70 px-4 pb-3 pt-4 dark:border-slate-800">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-[11px] font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">
                        Course outline
                      </div>
                      <div className="shrink-0 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                        {curriculumStats.publishedLessons}/{curriculumStats.totalLessons} lessons published
                      </div>
                    </div>
                  </div>
                  <div className="flex-1 px-4 pb-4">
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
                            <p className="text-sm font-medium text-slate-700 dark:text-slate-200">No chapters yet</p>
                            <p className="mx-auto mt-2 max-w-[14rem] text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                              Add a chapter, expand it, then use “Add content” inside that chapter.
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
              {activeTab === 'curriculum' ? (
                <div className="w-full max-w-none">
                  {!curriculumSelection ? (
                    <div className="rounded-xl border border-dashed border-slate-200/90 bg-slate-50/30 px-8 py-16 text-center dark:border-slate-700 dark:bg-slate-900/30">
                      <NavIcon
                        name="curriculumOutline"
                        className="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600"
                      />
                      <p className="mt-4 text-sm font-medium text-slate-700 dark:text-slate-200">
                        Select a chapter or content item
                      </p>
                      <p className="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Lessons, quizzes, and assignments open here. Quiz questions are managed inside each quiz or from
                        Questions.
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
                              fid={fid}
                              fcfg={fcfg}
                              values={values}
                              users={users}
                              siteUrl={siteUrl}
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
                                fid={fid}
                                fcfg={fcfg}
                                values={values}
                                users={users}
                                siteUrl={siteUrl}
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
    </AppShell>
  );
}
