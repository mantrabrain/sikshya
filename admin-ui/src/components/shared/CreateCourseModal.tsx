import { useEffect, useState } from 'react';
import { getErrorSummary } from '../../api/errors';
import { appViewHref } from '../../lib/appUrl';
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

/**
 * Shared “new course” flow: title → draft post via REST → redirect to course builder (edit mode).
 */
export function CreateCourseModal({ config, open, onClose }: Props) {
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
      setTitle('');
      setSlug('');
      setSlugManual(false);
      setError(null);
      setSubmitting(false);
    }
  }, [open]);

  const reset = () => {
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
      const id = await createDraftCourse(title, { slug: slug.trim() || undefined });
      const url = appViewHref(config, 'add-course', { course_id: String(id) });
      window.location.href = url;
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
      description="Give your course a working title. You can change everything later in the builder."
      size="md"
      footer={
        <div className="flex flex-wrap items-center justify-end gap-2">
          <button
            type="button"
            onClick={handleClose}
            disabled={submitting}
            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Cancel
          </button>
          <ButtonPrimary type="submit" form="sikshya-create-course-form" disabled={submitting}>
            {submitting ? 'Creating…' : 'Create & open builder'}
          </ButtonPrimary>
        </div>
      }
    >
      <form id="sikshya-create-course-form" onSubmit={onSubmit} className="space-y-4">
        <div>
          <label htmlFor="sikshya-new-course-title" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
            Course title
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
            placeholder="e.g. WordPress for Beginners"
            disabled={submitting}
            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
          />
        </div>
        <div>
          <label htmlFor="sikshya-new-course-slug" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
            Permalink
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
            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
          />
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Auto-filled from the title; edit to customize. WordPress may append a number if the slug is already in use.
          </p>
        </div>
        {error ? (
          <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200" role="alert">
            {error}
          </div>
        ) : null}
        <p className="text-xs text-slate-500 dark:text-slate-400">
          Your course is saved as a <strong className="font-medium text-slate-700 dark:text-slate-300">draft</strong> until you publish
          from the builder.
        </p>
      </form>
    </Modal>
  );
}
