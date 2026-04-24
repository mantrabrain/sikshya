import { useCallback, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { useAsyncData } from '../hooks/useAsyncData';
import type { SikshyaReactConfig } from '../types';

type CertRow = {
  id: number;
  user_id: number;
  course_id: number;
  certificate_number: string;
  issued_date: string;
  status: string;
  verification_code: string;
  template_post_id: number | null;
  verify_url?: string;
  document_url?: string;
};

type ListResponse = {
  ok?: boolean;
  certificates?: CertRow[];
  page?: number;
  per_page?: number;
};

export function IssuedCertificatesPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const { confirm } = useSikshyaDialog();
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const [page, setPage] = useState(1);

  const loader = useCallback(async () => {
    const q = new URLSearchParams({ page: String(page), per_page: '50' });
    return getSikshyaApi().get<ListResponse>(`${SIKSHYA_ENDPOINTS.admin.issuedCertificates}?${q.toString()}`);
  }, [page]);

  const { loading, data, error, refetch } = useAsyncData(loader, [page]);
  const rows = data?.certificates ?? [];

  const revoke = async (id: number) => {
    const ok = await confirm({
      title: 'Revoke certificate?',
      message: 'This certificate will no longer verify publicly.',
      variant: 'danger',
      confirmLabel: 'Revoke',
    });
    if (!ok) {
      return;
    }
    await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.issuedCertificatesRevoke, { id });
    refetch();
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Learner certificates issued when courses are completed (table-backed records)."
      pageActions={
        <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
          Refresh
        </ButtonPrimary>
      }
    >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Could not load certificates" onRetry={() => refetch()} />
        </div>
      ) : null}

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        ) : rows.length === 0 ? (
          <ListEmptyState
            title="No issued certificates yet"
            description="When learners complete a course and certificates are enabled, records appear here with verification codes."
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
              <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                  <th className="px-5 py-3.5">Issued</th>
                  <th className="px-5 py-3.5">Learner</th>
                  <th className="px-5 py-3.5">Course</th>
                  <th className="px-5 py-3.5">Number</th>
                  <th className="px-5 py-3.5">Verification</th>
                  <th className="px-5 py-3.5">Status</th>
                  <th className="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rows.map((r) => (
                  <tr key={r.id} className="bg-white dark:bg-slate-900">
                    <td className="whitespace-nowrap px-5 py-3.5 text-slate-600 dark:text-slate-400">
                      {formatPostDate(r.issued_date)}
                    </td>
                    <td className="px-5 py-3.5">
                      <a
                        href={`${adminBase}user-edit.php?user_id=${r.user_id}`}
                        className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400"
                      >
                        User #{r.user_id}
                      </a>
                    </td>
                    <td className="px-5 py-3.5">
                      <a
                        href={appViewHref(config, 'add-course', { course_id: String(r.course_id) })}
                        className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400"
                      >
                        {`Course #${r.course_id}`}
                      </a>
                    </td>
                    <td className="px-5 py-3.5 font-mono text-xs text-slate-700 dark:text-slate-300">{r.certificate_number}</td>
                    <td className="max-w-[220px] px-5 py-3.5">
                      {r.verify_url ? (
                        <a
                          href={r.verify_url}
                          target="_blank"
                          rel="noreferrer noopener"
                          className="break-all font-mono text-xs text-brand-600 hover:underline dark:text-brand-400"
                          title="Open public verification URL"
                        >
                          {r.verify_url}
                        </a>
                      ) : r.verification_code ? (
                        <code className="break-all font-mono text-xs text-slate-600 dark:text-slate-400">
                          {r.verification_code}
                        </code>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="px-5 py-3.5 capitalize text-slate-700 dark:text-slate-300">{r.status}</td>
                    <td className="px-5 py-3.5 text-right">
                      {r.status === 'active' ? (
                        <button
                          type="button"
                          onClick={() => revoke(r.id)}
                          className="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                        >
                          Revoke
                        </button>
                      ) : (
                        <span className="text-xs text-slate-400">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ListPanel>
    </EmbeddableShell>
  );
}
