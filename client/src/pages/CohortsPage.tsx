import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPanelSkeleton } from '../components/shared/Skeleton';
import { useAsyncData } from '../hooks/useAsyncData';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type Cohort = {
  id: number;
  course_id: number;
  course_title: string;
  name: string;
  starts_at: string | null;
  ends_at: string | null;
  status: string;
  state: string;
  member_count: number;
};

type Member = { user_id: number; display_name: string; email: string };

const FIELD =
  'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-700 dark:text-slate-300';

const STATE_LABELS: Record<string, string> = {
  upcoming: __('Upcoming', 'sikshya'),
  running: __('Running', 'sikshya'),
  ended: __('Ended', 'sikshya'),
  undated: __('No dates set', 'sikshya'),
};

/**
 * Cohorts — scheduled intakes.
 *
 * The start date is the point of the feature, not decoration: drip schedules
 * count from it, so everyone in one intake reaches week two on the same day
 * however late they joined. The screen therefore leads with the date and the
 * lifecycle state, and warns when an intake has no date — because an undated
 * cohort anchors nothing and ordinary per-learner drip applies instead, which is
 * easy to mistake for the feature not working.
 */
export function CohortsPage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle?: string;
  embedded?: boolean;
}) {
  const { config, title, subtitle, embedded } = props;
  const toast = useTopRightToast(3000);

  const [creating, setCreating] = useState(false);
  const [name, setName] = useState('');
  const [courseId, setCourseId] = useState('');
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [busy, setBusy] = useState(false);

  const [openCohort, setOpenCohort] = useState<number | null>(null);
  const [memberEmail, setMemberEmail] = useState('');

  const loader = useCallback(
    () => getSikshyaApi().get<{ ok?: boolean; cohorts?: Cohort[] }>(SIKSHYA_ENDPOINTS.admin.cohorts()),
    [],
  );
  const { loading, data, error, refetch } = useAsyncData(loader, ['cohorts']);
  const cohorts = data?.cohorts ?? [];

  const membersLoader = useCallback(async () => {
    if (openCohort === null) {
      return { members: [] as Member[] };
    }

    return getSikshyaApi().get<{ members?: Member[] }>(SIKSHYA_ENDPOINTS.admin.cohortMembers(openCohort));
  }, [openCohort]);
  const membersState = useAsyncData(membersLoader, [`cohort-members-${openCohort ?? 'none'}`]);
  const members = membersState.data?.members ?? [];

  const openRow = useMemo(() => cohorts.find((c) => c.id === openCohort) ?? null, [cohorts, openCohort]);

  const create = async () => {
    const course = parseInt(courseId, 10) || 0;
    if (name.trim() === '' || course <= 0) {
      toast.error(__('Missing details', 'sikshya'), __('An intake needs a name and a course ID.', 'sikshya'));
      return;
    }
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.cohorts(), {
        name: name.trim(),
        course_id: course,
        starts_at: startsAt || undefined,
        ends_at: endsAt || undefined,
      });
      toast.success(__('Intake created', 'sikshya'), __('Add learners to start the schedule.', 'sikshya'));
      setName('');
      setCourseId('');
      setStartsAt('');
      setEndsAt('');
      setCreating(false);
      void refetch();
    } catch (e) {
      toast.error(__('Could not create', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  const addMember = async () => {
    if (openCohort === null) {
      return;
    }
    if (memberEmail.trim() === '') {
      toast.error(__('Email required', 'sikshya'), __('Enter the learner’s email address.', 'sikshya'));
      return;
    }
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.cohortMembers(openCohort), {
        email: memberEmail.trim(),
      });
      toast.success(
        __('Learner added', 'sikshya'),
        __('They are enrolled, and their schedule counts from the intake start.', 'sikshya'),
      );
      setMemberEmail('');
      void membersState.refetch();
      void refetch();
    } catch (e) {
      toast.error(__('Could not add', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  const removeMember = async (userId: number) => {
    if (openCohort === null) {
      return;
    }
    setBusy(true);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.cohortMember(openCohort, userId));
      toast.success(__('Removed', 'sikshya'), __('Their enrolment and progress are kept.', 'sikshya'));
      void membersState.refetch();
      void refetch();
    } catch (e) {
      toast.error(__('Could not remove', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={
        subtitle ??
        __(
          'Run a course as a scheduled intake. Drip schedules count from the intake’s start date, so everyone in it reaches week two on the same day however late they joined.',
          'sikshya',
        )
      }
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      <ListPanel className="p-4">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p className="text-xs text-slate-500 dark:text-slate-400">
            {cohorts.length > 0 ? `${cohorts.length} ${__('intakes', 'sikshya')}` : null}
          </p>
          <span data-sikshya-new-cohort>
            <ButtonPrimary onClick={() => setCreating((v) => !v)}>
              {creating ? __('Cancel', 'sikshya') : __('New intake', 'sikshya')}
            </ButtonPrimary>
          </span>
        </div>

        {creating ? (
          <div className="mb-5 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
            <div className="grid gap-3 md:grid-cols-4">
              <label className={LABEL}>
                {__('Intake name', 'sikshya')}
                <input
                  className={FIELD}
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder={__('January intake', 'sikshya')}
                  data-sikshya-cohort-name
                />
              </label>
              <label className={LABEL}>
                {__('Course ID', 'sikshya')}
                <input
                  className={FIELD}
                  type="number"
                  min={1}
                  value={courseId}
                  onChange={(e) => setCourseId(e.target.value)}
                  data-sikshya-cohort-course
                />
              </label>
              <label className={LABEL}>
                {__('Starts', 'sikshya')}
                <input
                  className={FIELD}
                  type="date"
                  value={startsAt}
                  onChange={(e) => setStartsAt(e.target.value)}
                  data-sikshya-cohort-starts
                />
                <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                  {__('Drip schedules count from here.', 'sikshya')}
                </span>
              </label>
              <label className={LABEL}>
                {__('Ends (optional)', 'sikshya')}
                <input
                  className={FIELD}
                  type="date"
                  value={endsAt}
                  onChange={(e) => setEndsAt(e.target.value)}
                  data-sikshya-cohort-ends
                />
              </label>
            </div>
            <div className="mt-3">
              <span data-sikshya-cohort-save>
                <ButtonPrimary onClick={() => void create()} disabled={busy}>
                  {busy ? __('Saving…', 'sikshya') : __('Create intake', 'sikshya')}
                </ButtonPrimary>
              </span>
            </div>
          </div>
        ) : null}

        {error ? (
          <ApiErrorPanel error={error} onRetry={() => void refetch()} />
        ) : loading ? (
          <ListPanelSkeleton />
        ) : cohorts.length === 0 ? (
          <ListEmptyState
            title={__('No intakes yet', 'sikshya')}
            description={__(
              'Create one for a term, a bootcamp or a training round, and give it a start date.',
              'sikshya',
            )}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm" data-sikshya-cohorts-table>
              <thead className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                  <th className="py-2 pr-4">{__('Intake', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Course', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Starts', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('State', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Learners', 'sikshya')}</th>
                  <th className="py-2" />
                </tr>
              </thead>
              <tbody>
                {cohorts.map((c) => (
                  <tr key={c.id} className="border-t border-slate-100 dark:border-slate-800">
                    <td className="py-3 pr-4 font-medium text-slate-800 dark:text-slate-100">{c.name}</td>
                    <td className="py-3 pr-4">{c.course_title || `#${c.course_id}`}</td>
                    <td className="py-3 pr-4">
                      {c.starts_at ? (
                        c.starts_at.slice(0, 10)
                      ) : (
                        <span className="text-xs text-amber-800 dark:text-amber-300">
                          {__('not set — drip falls back to each learner’s own date', 'sikshya')}
                        </span>
                      )}
                    </td>
                    <td className="py-3 pr-4">{STATE_LABELS[c.state] ?? c.state}</td>
                    <td className="py-3 pr-4" data-sikshya-cohort-count>
                      {c.member_count}
                    </td>
                    <td className="py-3 text-right">
                      <ButtonSecondary onClick={() => setOpenCohort(openCohort === c.id ? null : c.id)}>
                        {openCohort === c.id ? __('Close', 'sikshya') : __('Manage learners', 'sikshya')}
                      </ButtonSecondary>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ListPanel>

      {openCohort !== null ? (
        <div data-sikshya-cohort-roster>
          <ListPanel className="mt-4 p-4">
            <h2 className="mb-1 text-base font-semibold text-slate-800 dark:text-slate-100">
              {openRow ? openRow.name : __('Learners', 'sikshya')}
            </h2>
            {openRow ? (
              <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">
                {openRow.starts_at
                  ? `${__('Schedules count from', 'sikshya')} ${openRow.starts_at.slice(0, 10)}`
                  : __(
                      'This intake has no start date, so drip schedules count from each learner’s own enrolment instead.',
                      'sikshya',
                    )}
              </p>
            ) : null}

            <div className="mb-4 flex flex-wrap items-end gap-3">
              <label className={LABEL}>
                {__('Learner email', 'sikshya')}
                <input
                  className={FIELD}
                  value={memberEmail}
                  onChange={(e) => setMemberEmail(e.target.value)}
                  placeholder={__('learner@example.test', 'sikshya')}
                  data-sikshya-cohort-member-email
                />
              </label>
              <span data-sikshya-cohort-member-add>
                <ButtonPrimary onClick={() => void addMember()} disabled={busy}>
                  {__('Add to intake', 'sikshya')}
                </ButtonPrimary>
              </span>
            </div>

            {membersState.error ? (
              <ApiErrorPanel error={membersState.error} onRetry={() => void membersState.refetch()} />
            ) : membersState.loading ? (
              <ListPanelSkeleton />
            ) : members.length === 0 ? (
              <ListEmptyState
                title={__('Nobody in this intake yet', 'sikshya')}
                description={__('Adding a learner enrols them in the course.', 'sikshya')}
              />
            ) : (
              <table className="w-full text-left text-sm" data-sikshya-cohort-members-table>
                <thead className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  <tr>
                    <th className="py-2 pr-4">{__('Learner', 'sikshya')}</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody>
                  {members.map((m) => (
                    <tr key={m.user_id} className="border-t border-slate-100 dark:border-slate-800">
                      <td className="py-3 pr-4">
                        <span className="font-medium text-slate-800 dark:text-slate-100">
                          {m.display_name || `#${m.user_id}`}
                        </span>
                        <span className="ml-2 text-xs text-slate-500">{m.email}</span>
                      </td>
                      <td className="py-3 text-right">
                        <ButtonSecondary onClick={() => void removeMember(m.user_id)} disabled={busy}>
                          {__('Remove', 'sikshya')}
                        </ButtonSecondary>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </ListPanel>
        </div>
      ) : null}
    </EmbeddableShell>
  );
}
