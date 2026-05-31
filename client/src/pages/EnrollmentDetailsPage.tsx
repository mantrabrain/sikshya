import { useCallback, useMemo } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAdminRouting } from '../lib/adminRouting';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { term, termLower } from '../lib/terminology';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type EnrollmentDetail = {
  id: number;
  user_id: number;
  course_id: number;
  status: string;
  enrolled_date: string;
  completed_date: string;
  payment_method: string;
  transaction_id: string;
  amount: number;
  progress: number;
  notes: string;
  learner_name: string;
  learner_email: string;
  learner_login: string;
  course_title: string;
  course_status: string;
};

type DetailsResponse = { success?: boolean; enrollment?: EnrollmentDetail; message?: string };

/**
 * Enrollment Details page. URL: `?page=sikshya&view=enrollment-detail&id=<id>`.
 *
 * Linked from the Enrollments list (student name column). Shows the full
 * row joined with the user + course post, plus the progress % and payment
 * trail in one scannable layout.
 */
export function EnrollmentDetailsPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, embedded, title } = props;
  const { route, navigateView } = useAdminRouting();
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const enrollmentId = useMemo(() => parseInt(route.query?.id || '0', 10) || 0, [route.query]);

  const student = term(config, 'student');
  const studentLower = termLower(config, 'student');
  const course = term(config, 'course');
  const courseLower = termLower(config, 'course');
  const enrollmentLower = termLower(config, 'enrollment');

  const loader = useCallback(async () => {
    if (!enrollmentId) throw new Error(__('Missing enrollment id.', 'sikshya'));
    return getSikshyaApi().get<DetailsResponse>(SIKSHYA_ENDPOINTS.admin.enrollmentDetail(enrollmentId));
  }, [enrollmentId]);

  const { loading, data, error, refetch } = useAsyncData(loader, [enrollmentId]);
  const e = data?.enrollment;

  const progressPct = e ? Math.max(0, Math.min(100, Math.round(e.progress))) : 0;

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={enrollmentId ? `${student} ${enrollmentLower} #${enrollmentId}` : enrollmentLower}
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <ButtonSecondary type="button" onClick={() => navigateView('enrollments')}>
            {__('Back to enrollments', 'sikshya')}
          </ButtonSecondary>
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            {__('Refresh', 'sikshya')}
          </ButtonPrimary>
        </div>
      }
    >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title={__('Could not load enrollment', 'sikshya')} onRetry={() => refetch()} />
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          {__('Loading enrollment…', 'sikshya')}
        </div>
      ) : !e ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          {__('Enrollment not found.', 'sikshya')}
        </div>
      ) : (
        <div className="space-y-6">
          {/* Hero card: status + progress at a glance */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <StatusBadge status={e.status} />
                  {e.completed_date ? (
                    <span className="text-xs text-slate-500 dark:text-slate-400">
                      {__('Completed', 'sikshya')} · {formatPostDate(e.completed_date)}
                    </span>
                  ) : null}
                </div>
                <h2 className="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
                  {e.learner_name || `${student} #${e.user_id}`}
                </h2>
                <p className="text-sm text-slate-600 dark:text-slate-400">{e.learner_email || '—'}</p>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  {__('Enrolled in', 'sikshya')}{' '}
                  {e.course_id > 0 ? (
                    <a
                      href={appViewHref(config, 'add-course', { course_id: String(e.course_id) })}
                      className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                      {e.course_title || `${course} #${e.course_id}`}
                    </a>
                  ) : (
                    '—'
                  )}{' '}
                  · {formatPostDate(e.enrolled_date)}
                </p>
              </div>
              <div className="w-full sm:w-auto sm:min-w-[14rem]">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  {__('Progress', 'sikshya')}
                </p>
                <div className="mt-2 flex items-center gap-3">
                  <div className="h-2 flex-1 rounded-full bg-slate-200 dark:bg-slate-700">
                    <div
                      className="h-2 rounded-full bg-brand-500"
                      style={{ width: `${progressPct}%` }}
                      aria-hidden="true"
                    />
                  </div>
                  <span className="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{progressPct}%</span>
                </div>
              </div>
            </div>
          </div>

          {/* Detail grid */}
          <div className="grid gap-4 lg:grid-cols-2">
            <DetailCard title={__('Learner', 'sikshya')}>
              <DetailRow label={student} value={e.learner_name || '—'} />
              <DetailRow label={__('Email', 'sikshya')} value={e.learner_email || '—'} />
              <DetailRow label={__('Username', 'sikshya')} value={e.learner_login || '—'} />
              <DetailRow
                label={__('User profile', 'sikshya')}
                value={
                  <a
                    href={`${adminBase}user-edit.php?user_id=${e.user_id}`}
                    className="text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                  >
                    {__('Open in WP admin →', 'sikshya')}
                  </a>
                }
              />
            </DetailCard>

            <DetailCard title={course}>
              <DetailRow label={__('Title', 'sikshya')} value={e.course_title || `#${e.course_id}`} />
              <DetailRow label={__('Status', 'sikshya')} value={e.course_status || '—'} />
              <DetailRow
                label={__('Edit', 'sikshya')}
                value={
                  e.course_id > 0 ? (
                    <a
                      href={appViewHref(config, 'add-course', { course_id: String(e.course_id) })}
                      className="text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                      {__('Open in course builder →', 'sikshya')}
                    </a>
                  ) : (
                    '—'
                  )
                }
              />
            </DetailCard>

            <DetailCard title={__('Payment', 'sikshya')}>
              <DetailRow
                label={__('Method', 'sikshya')}
                value={e.payment_method || __('— (free or manual)', 'sikshya')}
              />
              <DetailRow
                label={__('Amount', 'sikshya')}
                value={e.amount > 0 ? e.amount.toFixed(2) : __('Free', 'sikshya')}
              />
              <DetailRow label={__('Transaction', 'sikshya')} value={e.transaction_id || '—'} />
            </DetailCard>

            <DetailCard title={enrollmentLower}>
              <DetailRow label={__('ID', 'sikshya')} value={`#${e.id}`} />
              <DetailRow label={__('Enrolled', 'sikshya')} value={formatPostDate(e.enrolled_date)} />
              <DetailRow
                label={__('Completed', 'sikshya')}
                value={e.completed_date ? formatPostDate(e.completed_date) : '—'}
              />
              <DetailRow label={__('Notes', 'sikshya')} value={e.notes || '—'} />
            </DetailCard>
          </div>
        </div>
      )}
    </EmbeddableShell>
  );
}

function DetailCard(props: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
      <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
        {props.title}
      </h3>
      <dl className="space-y-2 text-sm">{props.children}</dl>
    </div>
  );
}

function DetailRow(props: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-3 border-b border-slate-100 pb-2 last:border-b-0 last:pb-0 dark:border-slate-800">
      <dt className="text-slate-500 dark:text-slate-400">{props.label}</dt>
      <dd className="text-right font-medium text-slate-900 dark:text-white">{props.value}</dd>
    </div>
  );
}
