import { useEffect, useState } from 'react';
import { getErrorSummary } from '../../api/errors';
import { appViewHref } from '../../lib/appUrl';
import { useAdminRouting } from '../../lib/adminRouting';
import { createDraftCourse } from '../../lib/createCourse';
import { slugFromTitle } from '../../lib/slugFromTitle';
import type { SikshyaReactConfig } from '../../types';
import { ButtonPrimary } from './buttons';
import { Modal } from './Modal';

type Props = {
  config: SikshyaReactConfig;
  open: boolean;
  onClose: () => void;
};

type CourseKind = 'regular' | 'bundle';

/**
 * Shared “new course” flow: title → draft post via REST → redirect to course builder (edit mode).
 */
export function CreateCourseModal({ config, open, onClose }: Props) {
  const { navigateHref } = useAdminRouting();
  const [kind, setKind] = useState<CourseKind>('regular');
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [slugManual, setSlugManual] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const siteRoot = config.siteUrl.replace(/\/$/, '');
  const courseBase = (config.permalinks && config.permalinks.rewrite_base_course) || 'courses';
  const coursePostType = (config.postTypes && config.postTypes.course) || 'sik_course';
  const permalinkPreview = (() => {
    const s = slug.trim() || '…';
    if (config.plainPermalinks) {
      return `${siteRoot}/?post_type=${encodeURIComponent(coursePostType)}&name=${encodeURIComponent(s)}`;
    }
    return `${siteRoot}/${encodeURIComponent(courseBase)}/${encodeURIComponent(s)}/`;
  })();

  useEffect(() => {
    if (open) {
      setKind('regular');
      setTitle('');
      setSlug('');
      setSlugManual(false);
      setError(null);
      setSubmitting(false);
    }
  }, [open]);

  const reset = () => {
    setKind('regular');
    setTitle('');
    setSlug('');
    setSlugManual(false);
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
      const id = await createDraftCourse(title, { slug: slug.trim() || undefined, kind });
      const extra: Record<string, string> = { course_id: String(id) };
      if (kind === 'bundle') {
        // Show bundle UI immediately even if meta propagation is delayed.
        extra.force_bundle_ui = '1';
      }
      const url = appViewHref(config, 'add-course', extra);
      navigateHref(url);
    } catch (err) {
      setError(getErrorSummary(err));
      setSubmitting(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="Create a new course"
      description={
        kind === 'bundle'
          ? 'Bundles sell several courses for one price. You will set price, included courses, and catalog visibility next.'
          : 'Start with a name and web address. You will add lessons, price, and media in the course builder next.'
      }
      size="md"
      footer={
        <div className="flex flex-wrap items-center justify-end gap-2">
          <button
            type="button"
            onClick={handleClose}
            disabled={submitting}
            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Cancel
          </button>
          <ButtonPrimary type="submit" form="sikshya-create-course-form" disabled={submitting}>
            {submitting ? 'Creating…' : kind === 'bundle' ? 'Create bundle & open builder' : 'Create & open builder'}
          </ButtonPrimary>
        </div>
      }
    >
      <form id="sikshya-create-course-form" onSubmit={onSubmit} className="space-y-4">
        <fieldset className="space-y-2">
          <legend className="text-sm font-medium text-slate-800 dark:text-slate-200">What are you creating?</legend>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Choose now — the builder opens with the right layout (full course vs streamlined bundle).
          </p>
          <div className="mt-2 grid gap-2 sm:grid-cols-2">
            <label
              className={`flex cursor-pointer flex-col rounded-xl border p-3 text-left transition ${
                kind === 'regular'
                  ? 'border-brand-500 bg-brand-50/80 ring-2 ring-brand-500/25 dark:border-brand-500 dark:bg-brand-950/30'
                  : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'
              }`}
            >
              <span className="flex items-center gap-2">
                <input type="radio" name="sik-course-kind" checked={kind === 'regular'} onChange={() => setKind('regular')} disabled={submitting} className="h-4 w-4" />
                <span className="text-sm font-semibold text-slate-900 dark:text-white">Regular course</span>
              </span>
              <span className="mt-1 pl-6 text-xs text-slate-600 dark:text-slate-400">
                Lessons, quizzes, curriculum, drip, and all course options.
              </span>
            </label>
            <label
              className={`flex cursor-pointer flex-col rounded-xl border p-3 text-left transition ${
                kind === 'bundle'
                  ? 'border-brand-500 bg-brand-50/80 ring-2 ring-brand-500/25 dark:border-brand-500 dark:bg-brand-950/30'
                  : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'
              }`}
            >
              <span className="flex items-center gap-2">
                <input type="radio" name="sik-course-kind" checked={kind === 'bundle'} onChange={() => setKind('bundle')} disabled={submitting} className="h-4 w-4" />
                <span className="text-sm font-semibold text-slate-900 dark:text-white">Course bundle</span>
              </span>
              <span className="mt-1 pl-6 text-xs text-slate-600 dark:text-slate-400">
                Package existing courses. Builder shows only bundle page + pricing (no curriculum tab).
              </span>
            </label>
          </div>
        </fieldset>

        <div>
          <label htmlFor="sikshya-new-course-title" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {kind === 'bundle' ? 'Bundle name' : 'Working title'}
          </label>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            You can rename anytime. This becomes the public page title.
          </p>
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
            placeholder={kind === 'bundle' ? 'e.g. Full Stack Web Dev Bundle' : 'e.g. WordPress for Beginners'}
            disabled={submitting}
            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
          />
        </div>
        <div>
          <label htmlFor="sikshya-new-course-slug" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
            URL slug
          </label>
          <p className="mt-1 break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
            {permalinkPreview}
          </p>
          <input
            id="sikshya-new-course-slug"
            type="text"
            name="slug"
            autoComplete="off"
            maxLength={180}
            value={slug}
            onChange={(e) => {
              setSlugManual(true);
              const v = e.target.value
                .toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^a-z0-9-]/g, '');
              setSlug(v);
            }}
            placeholder="url-slug"
            disabled={submitting}
            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
          />
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Fills in from the title automatically — change it if you want a shorter link. If that address is taken, WordPress may add
            -2, -3, etc.
          </p>
        </div>
        {error ? (
          <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200" role="alert">
            {error}
          </div>
        ) : null}
        <p className="text-xs text-slate-500 dark:text-slate-400">
          Your {kind === 'bundle' ? 'bundle' : 'course'} is saved as a <strong className="font-medium text-slate-700 dark:text-slate-300">draft</strong> until you publish
          from the builder.
        </p>
      </form>
    </Modal>
  );
}
