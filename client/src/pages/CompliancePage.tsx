import { useCallback, useState } from 'react';
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

type Requirement = {
  id: number;
  course_id: number;
  course_title: string;
  title: string;
  audience_type: string;
  audience_id: number;
  audience_key: string;
  due_days: number | null;
  due_date: string | null;
  recurs_every_days: number | null;
  status: string;
};

type ReportRow = {
  requirement_id: number;
  title?: string;
  course_title?: string;
  audience_type?: string;
  total: number;
  compliant: number;
  overdue: number;
  due_soon: number;
  waived: number;
  completed_late: number;
  compliance_percent: number;
};

type Overall = {
  total?: number;
  compliant?: number;
  overdue?: number;
  due_soon?: number;
  waived?: number;
  completed_late?: number;
  compliance_percent?: number;
};

const FIELD =
  'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-700 dark:text-slate-300';

const AUDIENCE_LABELS: Record<string, string> = {
  all: __('Everyone', 'sikshya'),
  role: __('A role', 'sikshya'),
  group: __('A group', 'sikshya'),
  cohort: __('A cohort', 'sikshya'),
};

/**
 * Compliance training — mandatory courses with deadlines and renewals.
 *
 * The numbers are the product here, so the report leads and the definitions
 * follow. Two counts are deliberately shown apart from the headline: `overdue`,
 * because that is the list someone has to act on today, and `completed_late`,
 * because an auditor asking "was this done on time" is not answered by a
 * compliance percentage that counts a late completion as compliant.
 */
export function CompliancePage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle?: string;
  embedded?: boolean;
}) {
  const { config, title, subtitle, embedded } = props;
  const toast = useTopRightToast(3000);

  const [creating, setCreating] = useState(false);
  const [reqTitle, setReqTitle] = useState('');
  const [courseId, setCourseId] = useState('');
  const [audienceType, setAudienceType] = useState('all');
  const [audienceKey, setAudienceKey] = useState('');
  const [audienceId, setAudienceId] = useState('');
  const [dueDays, setDueDays] = useState('30');
  const [recursDays, setRecursDays] = useState('');
  const [busy, setBusy] = useState(false);

  const reqLoader = useCallback(
    () =>
      getSikshyaApi().get<{ requirements?: Requirement[] }>(SIKSHYA_ENDPOINTS.admin.complianceRequirements),
    [],
  );
  const reqState = useAsyncData(reqLoader, ['compliance-requirements']);
  const requirements = reqState.data?.requirements ?? [];

  const reportLoader = useCallback(
    () =>
      getSikshyaApi().get<{ requirements?: ReportRow[]; overall?: Overall }>(
        SIKSHYA_ENDPOINTS.admin.complianceReport,
      ),
    [],
  );
  const reportState = useAsyncData(reportLoader, ['compliance-report']);
  const report = reportState.data?.requirements ?? [];
  const overall = reportState.data?.overall ?? {};

  const refreshBoth = () => {
    void reqState.refetch();
    void reportState.refetch();
  };

  const create = async () => {
    const course = parseInt(courseId, 10) || 0;
    if (course <= 0) {
      toast.error(__('Course required', 'sikshya'), __('Enter the ID of the course to mandate.', 'sikshya'));
      return;
    }
    if (audienceType === 'role' && audienceKey.trim() === '') {
      toast.error(__('Audience required', 'sikshya'), __('Name the role this applies to.', 'sikshya'));
      return;
    }
    if ((audienceType === 'group' || audienceType === 'cohort') && (parseInt(audienceId, 10) || 0) <= 0) {
      toast.error(__('Audience required', 'sikshya'), __('Name the group or cohort this applies to.', 'sikshya'));
      return;
    }

    setBusy(true);
    try {
      const res = await getSikshyaApi().post<{ assigned?: number }>(
        SIKSHYA_ENDPOINTS.admin.complianceRequirements,
        {
          course_id: course,
          title: reqTitle.trim() || undefined,
          audience_type: audienceType,
          audience_key: audienceType === 'role' ? audienceKey.trim() : undefined,
          audience_id:
            audienceType === 'group' || audienceType === 'cohort' ? parseInt(audienceId, 10) || 0 : undefined,
          due_days: parseInt(dueDays, 10) || 0,
          recurs_every_days: parseInt(recursDays, 10) || 0,
        },
      );
      toast.success(
        __('Requirement created', 'sikshya'),
        `${__('Assigned to', 'sikshya')} ${res?.assigned ?? 0} ${__('learners', 'sikshya')}`,
      );
      setReqTitle('');
      setCourseId('');
      setAudienceKey('');
      setAudienceId('');
      setRecursDays('');
      setCreating(false);
      refreshBoth();
    } catch (e) {
      toast.error(__('Could not create', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  const sync = async (id: number) => {
    setBusy(true);
    try {
      const res = await getSikshyaApi().post<{ assigned?: number }>(
        SIKSHYA_ENDPOINTS.admin.complianceRequirementSync(id),
        {},
      );
      toast.success(
        __('Audience re-checked', 'sikshya'),
        `${res?.assigned ?? 0} ${__('newly assigned', 'sikshya')}`,
      );
      refreshBoth();
    } catch (e) {
      toast.error(__('Could not sync', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  const remove = async (id: number) => {
    setBusy(true);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.complianceRequirement(id));
      toast.success(__('Requirement removed', 'sikshya'), __('Learner records are kept.', 'sikshya'));
      refreshBoth();
    } catch (e) {
      toast.error(__('Could not remove', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  const stat = (label: string, value: string | number, hook: string, tone?: string) => (
    <div
      className="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"
      {...{ [hook]: true }}
    >
      <p className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{label}</p>
      <p className={`mt-1 text-xl font-semibold ${tone ?? 'text-slate-800 dark:text-slate-100'}`}>{value}</p>
    </div>
  );

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={
        subtitle ??
        __(
          'Make a course mandatory for an audience, with a deadline and an optional renewal period. Completing it late still counts as completed, but is reported separately — an auditor asks whether it was done on time.',
          'sikshya',
        )
      }
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      {reportState.error ? (
        <ApiErrorPanel error={reportState.error} onRetry={() => void reportState.refetch()} />
      ) : reportState.loading ? (
        <ListPanelSkeleton />
      ) : (
        <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-sikshya-compliance-overall>
          {stat(__('Compliant', 'sikshya'), `${overall.compliance_percent ?? 0}%`, 'data-sikshya-stat-percent')}
          {stat(__('Obligations', 'sikshya'), overall.total ?? 0, 'data-sikshya-stat-total')}
          {stat(
            __('Overdue', 'sikshya'),
            overall.overdue ?? 0,
            'data-sikshya-stat-overdue',
            (overall.overdue ?? 0) > 0 ? 'text-rose-600' : undefined,
          )}
          {stat(__('Due soon', 'sikshya'), overall.due_soon ?? 0, 'data-sikshya-stat-due-soon')}
          {stat(__('Completed late', 'sikshya'), overall.completed_late ?? 0, 'data-sikshya-stat-late')}
        </div>
      )}

      <ListPanel className="p-4">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-base font-semibold text-slate-800 dark:text-slate-100">
            {__('Requirements', 'sikshya')}
          </h2>
          <span data-sikshya-new-requirement>
            <ButtonPrimary onClick={() => setCreating((v) => !v)}>
              {creating ? __('Cancel', 'sikshya') : __('New requirement', 'sikshya')}
            </ButtonPrimary>
          </span>
        </div>

        {creating ? (
          <div className="mb-5 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
            <div className="grid gap-3 md:grid-cols-3">
              <label className={LABEL}>
                {__('Course ID', 'sikshya')}
                <input
                  className={FIELD}
                  type="number"
                  min={1}
                  value={courseId}
                  onChange={(e) => setCourseId(e.target.value)}
                  data-sikshya-requirement-course
                />
              </label>
              <label className={LABEL}>
                {__('Label (optional)', 'sikshya')}
                <input
                  className={FIELD}
                  value={reqTitle}
                  onChange={(e) => setReqTitle(e.target.value)}
                  placeholder={__('Annual safety training', 'sikshya')}
                  data-sikshya-requirement-title
                />
              </label>
              <label className={LABEL}>
                {__('Applies to', 'sikshya')}
                <select
                  className={FIELD}
                  value={audienceType}
                  onChange={(e) => setAudienceType(e.target.value)}
                  data-sikshya-requirement-audience
                >
                  <option value="all">{AUDIENCE_LABELS.all}</option>
                  <option value="role">{AUDIENCE_LABELS.role}</option>
                  <option value="group">{AUDIENCE_LABELS.group}</option>
                  <option value="cohort">{AUDIENCE_LABELS.cohort}</option>
                </select>
              </label>

              {audienceType === 'role' ? (
                <label className={LABEL}>
                  {__('Role slug', 'sikshya')}
                  <input
                    className={FIELD}
                    value={audienceKey}
                    onChange={(e) => setAudienceKey(e.target.value)}
                    placeholder="subscriber"
                    data-sikshya-requirement-audience-key
                  />
                </label>
              ) : null}
              {audienceType === 'group' || audienceType === 'cohort' ? (
                <label className={LABEL}>
                  {audienceType === 'group' ? __('Group ID', 'sikshya') : __('Cohort ID', 'sikshya')}
                  <input
                    className={FIELD}
                    type="number"
                    min={1}
                    value={audienceId}
                    onChange={(e) => setAudienceId(e.target.value)}
                    data-sikshya-requirement-audience-id
                  />
                </label>
              ) : null}

              <label className={LABEL}>
                {__('Days to complete', 'sikshya')}
                <input
                  className={FIELD}
                  type="number"
                  min={0}
                  value={dueDays}
                  onChange={(e) => setDueDays(e.target.value)}
                  data-sikshya-requirement-due-days
                />
                <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                  {__('Counted from when the learner is assigned.', 'sikshya')}
                </span>
              </label>
              <label className={LABEL}>
                {__('Renew every (days)', 'sikshya')}
                <input
                  className={FIELD}
                  type="number"
                  min={0}
                  value={recursDays}
                  onChange={(e) => setRecursDays(e.target.value)}
                  placeholder={__('blank = one-off', 'sikshya')}
                  data-sikshya-requirement-recurs
                />
              </label>
            </div>
            <div className="mt-3">
              <span data-sikshya-requirement-save>
                <ButtonPrimary onClick={() => void create()} disabled={busy}>
                  {busy ? __('Saving…', 'sikshya') : __('Create requirement', 'sikshya')}
                </ButtonPrimary>
              </span>
            </div>
          </div>
        ) : null}

        {reqState.error ? (
          <ApiErrorPanel error={reqState.error} onRetry={() => void reqState.refetch()} />
        ) : reqState.loading ? (
          <ListPanelSkeleton />
        ) : requirements.length === 0 ? (
          <ListEmptyState
            title={__('No requirements yet', 'sikshya')}
            description={__(
              'Mandate a course for everyone, a role, a group or a cohort, and learners are assigned straight away.',
              'sikshya',
            )}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm" data-sikshya-requirements-table>
              <thead className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                  <th className="py-2 pr-4">{__('Requirement', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Course', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Applies to', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Deadline', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Renews', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Compliant', 'sikshya')}</th>
                  <th className="py-2" />
                </tr>
              </thead>
              <tbody>
                {requirements.map((r) => {
                  const row = report.find((x) => x.requirement_id === r.id);
                  const audience =
                    r.audience_type === 'role'
                      ? `${AUDIENCE_LABELS.role}: ${r.audience_key}`
                      : r.audience_type === 'group' || r.audience_type === 'cohort'
                        ? `${AUDIENCE_LABELS[r.audience_type]} #${r.audience_id}`
                        : AUDIENCE_LABELS.all;

                  return (
                    <tr key={r.id} className="border-t border-slate-100 dark:border-slate-800">
                      <td className="py-3 pr-4 font-medium text-slate-800 dark:text-slate-100">{r.title}</td>
                      <td className="py-3 pr-4">{r.course_title || `#${r.course_id}`}</td>
                      <td className="py-3 pr-4">{audience}</td>
                      <td className="py-3 pr-4">
                        {r.due_date
                          ? r.due_date.slice(0, 10)
                          : r.due_days
                            ? `${r.due_days} ${__('days', 'sikshya')}`
                            : __('none', 'sikshya')}
                      </td>
                      <td className="py-3 pr-4">
                        {r.recurs_every_days
                          ? `${__('every', 'sikshya')} ${r.recurs_every_days} ${__('days', 'sikshya')}`
                          : __('one-off', 'sikshya')}
                      </td>
                      <td className="py-3 pr-4">
                        {row ? (
                          <span>
                            <span className="font-semibold">{row.compliance_percent}%</span>
                            <span className="ml-2 text-xs text-slate-500">
                              {row.compliant}/{row.total}
                            </span>
                            {row.overdue > 0 ? (
                              <span className="ml-2 text-xs font-medium text-rose-600">
                                {row.overdue} {__('overdue', 'sikshya')}
                              </span>
                            ) : null}
                          </span>
                        ) : (
                          <span className="text-xs text-slate-500">{__('—', 'sikshya')}</span>
                        )}
                      </td>
                      <td className="py-3 text-right whitespace-nowrap">
                        <span data-sikshya-requirement-sync>
                          <ButtonSecondary onClick={() => void sync(r.id)} disabled={busy}>
                            {__('Re-check audience', 'sikshya')}
                          </ButtonSecondary>
                        </span>
                        <span className="ml-2">
                          <ButtonSecondary onClick={() => void remove(r.id)} disabled={busy}>
                            {__('Remove', 'sikshya')}
                          </ButtonSecondary>
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </ListPanel>
    </EmbeddableShell>
  );
}
