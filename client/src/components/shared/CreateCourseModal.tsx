import { useEffect, useMemo, useRef, useState, type ReactNode, type RefObject } from 'react';
import { getErrorSummary } from '../../api/errors';
import { appViewHref } from '../../lib/appUrl';
import { useAdminRouting } from '../../lib/adminRouting';
import { createDraftCourse } from '../../lib/createCourse';
import { slugFromTitle } from '../../lib/slugFromTitle';
import { sikshyaPricingUrl } from '../../lib/upgradeUrl';
import type { SikshyaReactConfig } from '../../types';
import { isFeatureEnabled } from '../../lib/licensing';
import { useAddonEnabled } from '../../hooks/useAddons';
import { ButtonPrimary } from './buttons';
import { Modal } from './Modal';
import { term, termLower } from '../../lib/terminology';

type Props = {
  config: SikshyaReactConfig;
  open: boolean;
  onClose: () => void;
};

type CourseKind = 'regular' | 'bundle';

const FIELD_LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const FIELD_HINT = 'mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400';
const FIELD_INPUT =
  'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';

export function CreateCourseModal({ config, open, onClose }: Props) {
  const { navigateHref } = useAdminRouting();
  const [kind, setKind] = useState<CourseKind>('regular');
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [slugManual, setSlugManual] = useState(false);
  const [slugEditing, setSlugEditing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const slugInputRef = useRef<HTMLInputElement>(null);
  const bundlesFeatureOk = isFeatureEnabled(config, 'course_bundles');
  const bundlesAddon = useAddonEnabled('course_bundles');
  const courseLower = termLower(config, 'course');
  const courseTitle = term(config, 'course');
  const coursesLower = termLower(config, 'courses');
  const brandName = config.branding?.pluginName?.trim() || 'Sikshya';

  const canUseBundles = useMemo(() => {
    if (!bundlesFeatureOk) return false;
    if (bundlesAddon.loading) return false;
    return Boolean(bundlesAddon.enabled);
  }, [bundlesAddon.enabled, bundlesAddon.loading, bundlesFeatureOk]);

  const siteRoot = config.siteUrl.replace(/\/$/, '');
  const courseBase = (config.permalinks && config.permalinks.rewrite_base_course) || 'courses';
  const coursePostType = (config.postTypes && config.postTypes.course) || 'sik_course';
  const effectiveSlug = slug.trim() || 'your-course';
  const permalinkPreview = (() => {
    if (config.plainPermalinks) {
      return `${siteRoot}/?post_type=${coursePostType}&name=${effectiveSlug}`;
    }
    return `${siteRoot}/${courseBase}/${effectiveSlug}/`;
  })();

  useEffect(() => {
    if (open) {
      setKind('regular');
      setTitle('');
      setSlug('');
      setSlugManual(false);
      setSlugEditing(false);
      setError(null);
      setSubmitting(false);
    }
  }, [open]);

  useEffect(() => {
    if (!slugEditing) {
      return;
    }
    const t = window.setTimeout(() => {
      slugInputRef.current?.focus();
      slugInputRef.current?.select();
    }, 0);
    return () => window.clearTimeout(t);
  }, [slugEditing]);

  const reset = () => {
    setKind('regular');
    setTitle('');
    setSlug('');
    setSlugManual(false);
    setSlugEditing(false);
    setError(null);
    setSubmitting(false);
  };

  const handleClose = () => {
    if (!submitting) {
      reset();
      onClose();
    }
  };

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const safeKind: CourseKind = kind === 'bundle' && !canUseBundles ? 'regular' : kind;
      const id = await createDraftCourse(title, { slug: slug.trim() || undefined, kind: safeKind });
      const extra: Record<string, string> = { course_id: String(id) };
      if (safeKind === 'bundle') {
        extra.force_bundle_ui = '1';
      }
      const url = appViewHref(config, 'add-course', extra);
      navigateHref(url);
    } catch (err) {
      setError(getErrorSummary(err));
      setSubmitting(false);
    }
  };

  const upgradeHref = sikshyaPricingUrl('addon-enable-upgrade', 'course_bundles');

  const finishSlugEdit = () => {
    setSlugEditing(false);
    if (!slug.trim() && title.trim()) {
      setSlug(slugFromTitle(title));
      setSlugManual(false);
    }
  };

  const regularCourseLabel = `Regular ${courseTitle}`;
  const courseBundleLabel = `${courseTitle} bundle`;
  const titlePlaceholder =
    kind === 'bundle' && canUseBundles ? 'e.g. Full Stack Bootcamp' : 'e.g. WordPress for Beginners';
  const nameFieldLabel =
    kind === 'bundle' && canUseBundles ? `${courseBundleLabel} name` : `${courseTitle} name`;

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title={`Create your ${courseLower}`}
      description="Pick a format, add a name, and you're ready to build content."
      size="comfortable"
      footer={
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <DraftIcon />
            Starts as a draft — nothing is public until you publish.
          </p>
          <div className="flex shrink-0 items-center justify-end gap-2">
            <button
              type="button"
              onClick={handleClose}
              disabled={submitting}
              className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
              Cancel
            </button>
            <ButtonPrimary
              type="submit"
              form="sikshya-create-course-form"
              disabled={submitting || !title.trim()}
              className="px-5 py-2.5"
            >
              {submitting ? 'Creating…' : 'Create & continue'}
            </ButtonPrimary>
          </div>
        </div>
      }
    >
      <form id="sikshya-create-course-form" onSubmit={onSubmit} className="space-y-5">
        <section>
          <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Choose a format</h3>
          <p className={`${FIELD_HINT} mt-0.5`}>
            {canUseBundles
              ? `Click a card to select — ${regularCourseLabel} or ${courseBundleLabel}.`
              : `Click a card to select. With ${brandName} Pro, you can choose ${courseBundleLabel} too.`}
          </p>
          <div className="mt-2.5 grid grid-cols-2 gap-2.5" role="radiogroup" aria-label={`${courseTitle} format`}>
            <FormatChoiceCard
              selected={kind === 'regular'}
              onSelect={() => setKind('regular')}
              disabled={submitting}
              icon={<RegularIcon />}
              title={regularCourseLabel}
              description={`One ${courseLower} with lessons, videos, and quizzes.`}
            />
            {canUseBundles ? (
              <FormatChoiceCard
                selected={kind === 'bundle'}
                onSelect={() => setKind('bundle')}
                disabled={submitting}
                icon={<BundleIcon />}
                title={courseBundleLabel}
                description={`Package existing ${coursesLower} and sell them with one checkout.`}
              />
            ) : (
              <BundleFormatCardLocked
                bundleLabel={courseBundleLabel}
                brandName={brandName}
                coursesLower={coursesLower}
                upgradeHref={upgradeHref}
                loading={bundlesAddon.loading}
              />
            )}
          </div>
        </section>

        <section className="border-t border-slate-100 pt-5 dark:border-slate-800">
          <div>
            <label htmlFor="sikshya-new-course-title" className={FIELD_LABEL}>
              {nameFieldLabel}
            </label>
            <input
              id="sikshya-new-course-title"
              type="text"
              name="title"
              required
              autoComplete="off"
              maxLength={200}
              value={title}
              onChange={(e) => {
                const v = e.target.value;
                setTitle(v);
                if (!slugManual) {
                  setSlug(v.trim() ? slugFromTitle(v) : '');
                }
              }}
              placeholder={titlePlaceholder}
              disabled={submitting}
              autoFocus
              className={`${FIELD_INPUT} mt-1.5`}
            />
          </div>

          <div className="mt-4">
            <span className={FIELD_LABEL}>Permalink</span>
            <p className={`${FIELD_HINT} mt-0.5`}>The web address where learners open this {courseLower}.</p>
            <PermalinkField
              editing={slugEditing}
              url={permalinkPreview}
              slug={slug}
              disabled={submitting}
              inputRef={slugInputRef}
              onStartEdit={() => setSlugEditing(true)}
              onFinishEdit={finishSlugEdit}
              onSlugChange={(v) => {
                setSlugManual(true);
                setSlug(v);
              }}
            />
          </div>
        </section>

        {error ? (
          <div
            className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200"
            role="alert"
          >
            {error}
          </div>
        ) : null}
      </form>
    </Modal>
  );
}

function PermalinkField({
  editing,
  url,
  slug,
  disabled,
  inputRef,
  onStartEdit,
  onFinishEdit,
  onSlugChange,
}: {
  editing: boolean;
  url: string;
  slug: string;
  disabled?: boolean;
  inputRef: RefObject<HTMLInputElement | null>;
  onStartEdit: () => void;
  onFinishEdit: () => void;
  onSlugChange: (slug: string) => void;
}) {
  if (editing) {
    return (
      <div className="mt-2">
        <div className="flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:focus-within:border-brand-500">
          <input
            ref={inputRef}
            id="sikshya-new-course-slug"
            type="text"
            name="slug"
            autoComplete="off"
            maxLength={180}
            value={slug}
            disabled={disabled}
            aria-label="Customize permalink"
            onChange={(e) => {
              const v = e.target.value
                .toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^a-z0-9-]/g, '');
              onSlugChange(v);
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                onFinishEdit();
              }
              if (e.key === 'Escape') {
                e.preventDefault();
                onFinishEdit();
              }
            }}
            className="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 font-mono text-xs text-slate-900 outline-none focus:ring-0 dark:text-white"
          />
          <button
            type="button"
            onClick={onFinishEdit}
            disabled={disabled}
            className="flex shrink-0 items-center self-stretch border-l border-slate-200 bg-slate-50 px-4 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500/40 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800/80 dark:text-brand-300 dark:hover:bg-brand-950/40"
          >
            Done
          </button>
        </div>
        <p className="mt-1.5 truncate font-mono text-[11px] text-slate-400 dark:text-slate-500" title={url}>
          Preview: {url}
        </p>
      </div>
    );
  }

  return (
    <div className="mt-2 flex items-center gap-2" title={url}>
      <GlobeIcon />
      <span className="min-w-0 flex-1 truncate font-mono text-xs text-slate-600 dark:text-slate-400">{url}</span>
      <button
        type="button"
        onClick={onStartEdit}
        disabled={disabled}
        className="shrink-0 text-xs font-semibold text-brand-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:opacity-50 dark:text-brand-300"
      >
        Edit link
      </button>
    </div>
  );
}

function FormatChoiceCard({
  selected,
  onSelect,
  disabled,
  icon,
  title,
  description,
}: {
  selected: boolean;
  onSelect: () => void;
  disabled?: boolean;
  icon: ReactNode;
  title: string;
  description: string;
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      disabled={disabled}
      aria-pressed={selected}
      aria-label={`${title}. ${description}${selected ? ' Selected.' : ''}`}
      className={`relative flex w-full cursor-pointer items-start gap-2.5 rounded-lg border px-2.5 py-2.5 text-left transition disabled:cursor-not-allowed disabled:opacity-60 ${
        selected
          ? 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-500/25 dark:border-brand-500 dark:bg-brand-950/25'
          : 'border-slate-200 bg-white hover:border-brand-300 hover:bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'
      }`}
    >
      <SelectionCheck selected={selected} />
      <span
        className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md ${
          selected
            ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-300'
            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
        }`}
        aria-hidden
      >
        {icon}
      </span>
      <span className="min-w-0 flex-1 pr-5">
        <span className="block text-[13px] font-semibold leading-tight text-slate-900 dark:text-white">{title}</span>
        <span className="mt-1 block text-[11px] leading-snug text-slate-500 dark:text-slate-400">{description}</span>
      </span>
    </button>
  );
}

function BundleFormatCardLocked({
  bundleLabel,
  brandName,
  coursesLower,
  upgradeHref,
  loading,
}: {
  bundleLabel: string;
  brandName: string;
  coursesLower: string;
  upgradeHref: string;
  loading: boolean;
}) {
  return (
    <div
      className="flex w-full items-start gap-2.5 rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-2.5 py-2.5 dark:border-slate-700 dark:bg-slate-800/25"
      role="group"
      aria-label={`${bundleLabel} — ${brandName} Pro`}
    >
      <span
        className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-200/70 text-slate-400 dark:bg-slate-700"
        aria-hidden
      >
        <BundleIcon className="h-4 w-4" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="flex flex-wrap items-center gap-1">
          <span className="text-[13px] font-semibold text-slate-600 dark:text-slate-300">{bundleLabel}</span>
          <span className="rounded bg-accent-100 px-1 py-px text-[9px] font-bold uppercase text-accent-800 dark:bg-accent-950/50 dark:text-accent-300">
            Pro
          </span>
        </span>
        <p className="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
          Package existing {coursesLower} —{' '}
          {loading ? (
            <span>checking plan…</span>
          ) : (
            <a
              href={upgradeHref}
              target="_blank"
              rel="noopener noreferrer"
              className="font-semibold text-accent-700 hover:underline dark:text-accent-300"
            >
              Upgrade to Pro to select
            </a>
          )}
        </p>
      </span>
    </div>
  );
}

function SelectionCheck({ selected }: { selected: boolean }) {
  return (
    <span
      className={`absolute right-2 top-2.5 flex h-4 w-4 items-center justify-center rounded-full border ${
        selected
          ? 'border-brand-600 bg-brand-600 text-white'
          : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-800'
      }`}
      aria-hidden
    >
      {selected ? (
        <svg viewBox="0 0 20 20" fill="currentColor" className="h-2.5 w-2.5">
          <path
            fillRule="evenodd"
            d="M16.7 5.3a1 1 0 010 1.4l-7.4 7.4a1 1 0 01-1.4 0L3.3 9.5a1 1 0 011.4-1.4L8.6 12l6.7-6.7a1 1 0 011.4 0z"
            clipRule="evenodd"
          />
        </svg>
      ) : null}
    </span>
  );
}

function RegularIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-4 w-4" aria-hidden>
      <path d="M4 19.5A2.5 2.5 0 016.5 17H20" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M9 7h7M9 11h5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function BundleIcon({ className = 'h-4 w-4' }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className={className} aria-hidden>
      <path
        d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path d="M3.27 6.96L12 12.01l8.73-5.05" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M12 22.08V12" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function GlobeIcon() {
  return (
    <svg
      className="h-4 w-4 shrink-0 text-slate-400"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      strokeWidth={2}
      aria-hidden
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3c2.2 2.5 3.4 5.6 3.4 9s-1.2 6.5-3.4 9M12 3c-2.2 2.5-3.4 5.6-3.4 9s1.2 6.5 3.4 9"
      />
    </svg>
  );
}

function DraftIcon() {
  return (
    <svg className="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
  );
}
