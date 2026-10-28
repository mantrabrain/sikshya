import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { useAsyncData } from '../hooks/useAsyncData';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { ConfirmDialog } from '../components/shared/ConfirmDialog';
import { Modal } from '../components/shared/Modal';
import { SkeletonCard } from '../components/shared/Skeleton';
import { __ } from '../lib/i18n';

export type MembershipPlan = {
  id: number;
  name: string;
  amount: number;
  currency: string;
  interval_unit: string;
  status: string;
};

type Problem = { code: string; message: string };

export type MembershipLevel = {
  id: string;
  title: string;
  tagline: string;
  description: string;
  bullets: string[];
  plan_id: number;
  join_course_id: number;
  include_all: boolean;
  course_ids: number[];
  category_ids: number[];
  highlight: boolean;
  button_label: string;
  status: 'active' | 'draft' | string;
  position: number;
  course_count?: number;
  problems?: Problem[];
};

type TargetCourse = {
  id: number;
  title: string;
  status: string;
  course_type: string;
  plan_id: number;
};

type TargetCategory = { id: number; name: string; count: number };

const INPUT_CLASS =
  'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';

const LABEL_CLASS = 'block text-xs font-medium text-slate-600 dark:text-slate-300';

function blankLevel(): MembershipLevel {
  return {
    id: '',
    title: '',
    tagline: '',
    description: '',
    bullets: [],
    plan_id: 0,
    join_course_id: 0,
    include_all: false,
    course_ids: [],
    category_ids: [],
    highlight: false,
    button_label: '',
    status: 'active',
    position: 0,
  };
}

function planLabel(plan: MembershipPlan | undefined): string {
  if (!plan) {
    return __('No plan', 'sikshya');
  }
  return `${plan.name} — ${plan.amount.toFixed(2)} ${plan.currency} / ${plan.interval_unit}`;
}

/**
 * Membership levels: the product surface over plans that already exist.
 *
 * A level never holds a price. It points at a plan (which is where price and
 * billing interval live, and what the gateways charge) and lists the courses
 * that plan opens. That keeps one definition of money on the site.
 */
export function MembershipLevelsTab(props: {
  onToast: (t: { kind: 'success' | 'error'; text: string } | null) => void;
}) {
  const { onToast } = props;
  const [editing, setEditing] = useState<MembershipLevel | null>(null);
  const [deleting, setDeleting] = useState<MembershipLevel | null>(null);
  const [busy, setBusy] = useState(false);

  const loader = useCallback(async () => {
    const [levels, targets] = await Promise.all([
      getSikshyaApi().get<{ ok?: boolean; levels?: MembershipLevel[]; plans?: MembershipPlan[] }>(
        SIKSHYA_ENDPOINTS.pro.membershipLevels
      ),
      getSikshyaApi().get<{ ok?: boolean; courses?: TargetCourse[]; categories?: TargetCategory[] }>(
        SIKSHYA_ENDPOINTS.pro.membershipTargets
      ),
    ]);
    return {
      levels: Array.isArray(levels.levels) ? levels.levels : [],
      plans: Array.isArray(levels.plans) ? levels.plans : [],
      courses: Array.isArray(targets.courses) ? targets.courses : [],
      categories: Array.isArray(targets.categories) ? targets.categories : [],
    };
  }, []);

  const { data, loading, error, refetch } = useAsyncData(loader, []);
  const levels = data?.levels ?? [];
  const plans = data?.plans ?? [];
  const courses = data?.courses ?? [];
  const categories = data?.categories ?? [];

  const planById = useMemo(() => {
    const m = new Map<number, MembershipPlan>();
    plans.forEach((p) => m.set(p.id, p));
    return m;
  }, [plans]);

  const courseTitleById = useMemo(() => {
    const m = new Map<number, string>();
    courses.forEach((c) => m.set(c.id, c.title));
    return m;
  }, [courses]);

  const save = async (level: MembershipLevel) => {
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.membershipLevels, level);
      setEditing(null);
      onToast({ kind: 'success', text: __('Membership level saved.', 'sikshya') });
      refetch();
    } catch (e) {
      onToast({ kind: 'error', text: e instanceof Error ? e.message : __('Could not save the level.', 'sikshya') });
    } finally {
      setBusy(false);
    }
  };

  const remove = async (level: MembershipLevel) => {
    setBusy(true);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.membershipLevel(level.id));
      setDeleting(null);
      onToast({ kind: 'success', text: __('Membership level deleted.', 'sikshya') });
      refetch();
    } catch (e) {
      onToast({ kind: 'error', text: e instanceof Error ? e.message : __('Could not delete the level.', 'sikshya') });
    } finally {
      setBusy(false);
    }
  };

  if (loading) {
    return <SkeletonCard rows={4} />;
  }
  if (error) {
    return (
      <ApiErrorPanel error={error} title={__('Could not load membership levels', 'sikshya')} onRetry={() => refetch()} />
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <p className="max-w-3xl text-sm text-slate-600 dark:text-slate-400">
          {__(
            'A level names a plan and the courses that plan opens. Put the public pricing table on any page with the [sikshya_membership_levels] shortcode. Levels only ever add access — a course in no level behaves exactly as it does today.',
            'sikshya'
          )}
        </p>
        <ButtonPrimary type="button" onClick={() => setEditing(blankLevel())} disabled={plans.length === 0}>
          {__('Add level', 'sikshya')}
        </ButtonPrimary>
      </div>

      {plans.length === 0 ? (
        <p className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
          {__('Create a plan first on the Plans tab. A level has no price of its own — it charges through a plan.', 'sikshya')}
        </p>
      ) : null}

      {levels.length === 0 ? (
        <p className="text-sm text-slate-500">{__('No membership levels yet.', 'sikshya')}</p>
      ) : (
        <ul className="space-y-3">
          {levels.map((level) => (
            <li
              key={level.id}
              className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-semibold text-slate-900 dark:text-white">
                    {level.title}
                    {level.status === 'draft' ? (
                      <span className="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-normal text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                        {__('Draft', 'sikshya')}
                      </span>
                    ) : null}
                  </p>
                  <p className="mt-1 text-xs text-slate-500">
                    {planLabel(planById.get(level.plan_id))}
                    {' · '}
                    {level.include_all
                      ? __('every course', 'sikshya')
                      : `${level.course_count ?? 0} ${__('courses', 'sikshya')}`}
                    {level.join_course_id > 0
                      ? ` · ${__('join via', 'sikshya')} ${courseTitleById.get(level.join_course_id) ?? `#${level.join_course_id}`}`
                      : ''}
                  </p>
                </div>
                <div className="flex gap-2">
                  <ButtonSecondary type="button" onClick={() => setEditing(level)}>
                    {__('Edit', 'sikshya')}
                  </ButtonSecondary>
                  <ButtonSecondary type="button" onClick={() => setDeleting(level)}>
                    {__('Delete', 'sikshya')}
                  </ButtonSecondary>
                </div>
              </div>

              {level.problems && level.problems.length > 0 ? (
                <ul className="mt-3 space-y-1">
                  {level.problems.map((p) => (
                    <li key={p.code} className="text-xs text-amber-700 dark:text-amber-300">
                      {p.message}
                    </li>
                  ))}
                </ul>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {editing ? (
        <LevelEditor
          level={editing}
          plans={plans}
          courses={courses}
          categories={categories}
          busy={busy}
          onCancel={() => setEditing(null)}
          onSave={(l) => void save(l)}
        />
      ) : null}

      {deleting ? (
        <ConfirmDialog
          open
          variant="danger"
          busy={busy}
          title={__('Delete this membership level?', 'sikshya')}
          confirmLabel={__('Delete', 'sikshya')}
          onConfirm={() => void remove(deleting)}
          onClose={() => setDeleting(null)}
        >
          {__(
            'Learners keep their subscriptions and keep paying — only this level, and the access it added, goes away. Courses with their own required plan are unaffected.',
            'sikshya'
          )}
        </ConfirmDialog>
      ) : null}
    </div>
  );
}

function LevelEditor(props: {
  level: MembershipLevel;
  plans: MembershipPlan[];
  courses: TargetCourse[];
  categories: TargetCategory[];
  busy: boolean;
  onCancel: () => void;
  onSave: (level: MembershipLevel) => void;
}) {
  const { plans, courses, categories, busy, onCancel, onSave } = props;
  const [draft, setDraft] = useState<MembershipLevel>(props.level);
  const [bulletText, setBulletText] = useState((props.level.bullets || []).join('\n'));
  const [courseSearch, setCourseSearch] = useState('');

  const set = <K extends keyof MembershipLevel>(key: K, value: MembershipLevel[K]) =>
    setDraft((d) => ({ ...d, [key]: value }));

  const toggleId = (key: 'course_ids' | 'category_ids', id: number) =>
    setDraft((d) => {
      const current = d[key];
      return {
        ...d,
        [key]: current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
      };
    });

  const visibleCourses = useMemo(() => {
    const q = courseSearch.trim().toLowerCase();
    if (q === '') {
      return courses.slice(0, 100);
    }
    return courses.filter((c) => c.title.toLowerCase().includes(q)).slice(0, 100);
  }, [courses, courseSearch]);

  const subscriptionCourses = useMemo(
    () => courses.filter((c) => c.course_type === 'subscription'),
    [courses]
  );

  return (
    <Modal
      open
      size="lg"
      title={props.level.id === '' ? __('New membership level', 'sikshya') : __('Edit membership level', 'sikshya')}
      onClose={onCancel}
    >
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-level-title">
              {__('Name', 'sikshya')}
            </label>
            <input
              id="sikshya-level-title"
              type="text"
              className={`mt-1 w-full ${INPUT_CLASS}`}
              value={draft.title}
              onChange={(e) => set('title', e.target.value)}
            />
          </div>
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-level-plan">
              {__('Plan (price and billing cycle)', 'sikshya')}
            </label>
            <select
              id="sikshya-level-plan"
              className={`mt-1 w-full ${INPUT_CLASS}`}
              value={draft.plan_id}
              onChange={(e) => set('plan_id', Number(e.target.value) || 0)}
            >
              <option value={0}>{__('— choose a plan —', 'sikshya')}</option>
              {plans.map((p) => (
                <option key={p.id} value={p.id}>
                  {planLabel(p)}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label className={LABEL_CLASS} htmlFor="sikshya-level-tagline">
            {__('Tagline', 'sikshya')}
          </label>
          <input
            id="sikshya-level-tagline"
            type="text"
            className={`mt-1 w-full ${INPUT_CLASS}`}
            value={draft.tagline}
            onChange={(e) => set('tagline', e.target.value)}
          />
        </div>

        <div>
          <label className={LABEL_CLASS} htmlFor="sikshya-level-bullets">
            {__('Selling points (one per line)', 'sikshya')}
          </label>
          <textarea
            id="sikshya-level-bullets"
            rows={4}
            className={`mt-1 w-full ${INPUT_CLASS}`}
            value={bulletText}
            onChange={(e) => {
              setBulletText(e.target.value);
              set(
                'bullets',
                e.target.value
                  .split('\n')
                  .map((l) => l.trim())
                  .filter((l) => l !== '')
              );
            }}
          />
        </div>

        <fieldset className="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
          <legend className="px-1 text-xs font-medium text-slate-600 dark:text-slate-300">
            {__('What this level opens', 'sikshya')}
          </legend>
          <label className="flex items-center gap-2 text-sm" htmlFor="sikshya-level-all">
            <input
              id="sikshya-level-all"
              type="checkbox"
              checked={draft.include_all}
              onChange={(e) => set('include_all', e.target.checked)}
            />
            {__('Every published course (all-access pass)', 'sikshya')}
          </label>

          {!draft.include_all ? (
            <div className="mt-3 grid gap-4 sm:grid-cols-2">
              <div>
                <p className={LABEL_CLASS}>{__('Categories', 'sikshya')}</p>
                <div className="mt-1 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                  {categories.length === 0 ? (
                    <p className="text-xs text-slate-500">{__('No course categories yet.', 'sikshya')}</p>
                  ) : (
                    categories.map((cat) => (
                      <label
                        key={cat.id}
                        className="flex items-center gap-2 text-sm"
                        htmlFor={`sikshya-level-cat-${cat.id}`}
                      >
                        <input
                          id={`sikshya-level-cat-${cat.id}`}
                          type="checkbox"
                          checked={draft.category_ids.includes(cat.id)}
                          onChange={() => toggleId('category_ids', cat.id)}
                        />
                        {cat.name}
                      </label>
                    ))
                  )}
                </div>
                <p className="mt-1 text-xs text-slate-500">
                  {__('A course added to a chosen category later joins this level automatically.', 'sikshya')}
                </p>
              </div>

              <div>
                <label className={LABEL_CLASS} htmlFor="sikshya-level-course-search">
                  {__('Individual courses', 'sikshya')}
                </label>
                <input
                  id="sikshya-level-course-search"
                  type="search"
                  className={`mt-1 w-full ${INPUT_CLASS}`}
                  placeholder={__('Search courses', 'sikshya')}
                  value={courseSearch}
                  onChange={(e) => setCourseSearch(e.target.value)}
                />
                <div className="mt-1 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                  {visibleCourses.map((course) => (
                    <label
                      key={course.id}
                      className="flex items-center gap-2 text-sm"
                      htmlFor={`sikshya-level-course-${course.id}`}
                    >
                      <input
                        id={`sikshya-level-course-${course.id}`}
                        type="checkbox"
                        checked={draft.course_ids.includes(course.id)}
                        onChange={() => toggleId('course_ids', course.id)}
                      />
                      {course.title}
                      {course.status !== 'publish' ? (
                        <span className="text-xs text-slate-400">({course.status})</span>
                      ) : null}
                    </label>
                  ))}
                </div>
              </div>
            </div>
          ) : null}
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-level-join">
              {__('Join through this course', 'sikshya')}
            </label>
            <select
              id="sikshya-level-join"
              className={`mt-1 w-full ${INPUT_CLASS}`}
              value={draft.join_course_id}
              onChange={(e) => set('join_course_id', Number(e.target.value) || 0)}
            >
              <option value={0}>{__('— none (no Join button) —', 'sikshya')}</option>
              {subscriptionCourses.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.title}
                </option>
              ))}
            </select>
            <p className="mt-1 text-xs text-slate-500">
              {__(
                'Checkout always sells a course, so a level is bought by buying its subscription-priced course. Name that course after the membership and the receipt reads correctly.',
                'sikshya'
              )}
            </p>
          </div>
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-level-button">
              {__('Button label', 'sikshya')}
            </label>
            <input
              id="sikshya-level-button"
              type="text"
              className={`mt-1 w-full ${INPUT_CLASS}`}
              placeholder={__('Join', 'sikshya')}
              value={draft.button_label}
              onChange={(e) => set('button_label', e.target.value)}
            />
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-level-status">
              {__('Status', 'sikshya')}
            </label>
            <select
              id="sikshya-level-status"
              className={`mt-1 w-full ${INPUT_CLASS}`}
              value={draft.status}
              onChange={(e) => set('status', e.target.value)}
            >
              <option value="active">{__('Active', 'sikshya')}</option>
              <option value="draft">{__('Draft (hidden, grants nothing)', 'sikshya')}</option>
            </select>
          </div>
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-level-position">
              {__('Order', 'sikshya')}
            </label>
            <input
              id="sikshya-level-position"
              type="number"
              className={`mt-1 w-full ${INPUT_CLASS}`}
              value={draft.position}
              onChange={(e) => set('position', Number(e.target.value) || 0)}
            />
          </div>
          <div className="flex items-end">
            <label className="flex items-center gap-2 text-sm" htmlFor="sikshya-level-highlight">
              <input
                id="sikshya-level-highlight"
                type="checkbox"
                checked={draft.highlight}
                onChange={(e) => set('highlight', e.target.checked)}
              />
              {__('Highlight this card', 'sikshya')}
            </label>
          </div>
        </div>

        <div className="flex justify-end gap-2">
          <ButtonSecondary type="button" onClick={onCancel} disabled={busy}>
            {__('Cancel', 'sikshya')}
          </ButtonSecondary>
          <ButtonPrimary type="button" onClick={() => onSave(draft)} disabled={busy || draft.title.trim() === ''}>
            {busy ? __('Saving…', 'sikshya') : __('Save level', 'sikshya')}
          </ButtonPrimary>
        </div>
      </div>
    </Modal>
  );
}
