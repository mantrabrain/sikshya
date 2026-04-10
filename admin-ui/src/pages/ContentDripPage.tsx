import { useCallback, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { FeatureUpsell } from '../components/FeatureUpsell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { getLicensing, isFeatureEnabled } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

type Rule = { id?: number; course_id?: number; lesson_id?: number; rule_type?: string; rule_value?: string };

type ListResp = { ok?: boolean; rules?: Rule[] };

export function ContentDripPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const lic = getLicensing(config);
  const enabled = isFeatureEnabled(config, 'content_drip');
  const [courseId, setCourseId] = useState('');
  const [lessonId, setLessonId] = useState('');
  const [ruleValue, setRuleValue] = useState('7');
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const listLoader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rules: [] as Rule[] };
    }
    const q = courseId ? `?course_id=${encodeURIComponent(courseId)}` : '';
    return getSikshyaApi().get<ListResp>(`${SIKSHYA_ENDPOINTS.pro.dripRules}${q}`);
  }, [courseId, enabled]);

  const { loading, data, error, refetch } = useAsyncData(listLoader, [courseId, enabled]);
  const rules = data?.rules ?? [];

  const saveRule = async (e: FormEvent) => {
    e.preventDefault();
    setMsg(null);
    const cid = parseInt(courseId, 10);
    if (!Number.isFinite(cid) || cid <= 0) {
      setMsg('Enter a course ID to attach the rule.');
      return;
    }
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.dripRules, {
        course_id: cid,
        lesson_id: lessonId ? parseInt(lessonId, 10) : 0,
        rule_type: 'delay_days',
        rule_value: ruleValue,
      });
      setMsg('Rule saved.');
      refetch();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
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
      subtitle="Control when lessons open—by delay, calendar, or cohort."
    >
      {!enabled ? (
        <FeatureUpsell
          title="Scheduled access"
          description="Release lessons on a schedule, after enrollment, or for cohorts. Learner actions respect these rules when enabled for your site."
          licensing={lic}
        />
      ) : (
        <>
          {error ? <ApiErrorPanel error={error} title="Could not load rules" onRetry={() => refetch()} /> : null}
          <form
            onSubmit={saveRule}
            className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
          >
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Add drip rule</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-3">
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Course ID
                <input
                  required
                  type="number"
                  value={courseId}
                  onChange={(e) => setCourseId(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Lesson ID (optional)
                <input
                  type="number"
                  value={lessonId}
                  onChange={(e) => setLessonId(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Days after enrollment
                <input
                  type="number"
                  min={0}
                  value={ruleValue}
                  onChange={(e) => setRuleValue(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
            </div>
            <div className="mt-4 flex items-center gap-3">
              <ButtonPrimary type="submit" disabled={saving}>
                {saving ? 'Saving…' : 'Save rule'}
              </ButtonPrimary>
              <button
                type="button"
                onClick={() => refetch()}
                className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                Refresh list
              </button>
            </div>
            {msg ? <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">{msg}</p> : null}
          </form>

          <ListPanel>
            {loading ? (
              <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
            ) : rules.length === 0 ? (
              <ListEmptyState title="No drip rules" description="Create a rule to delay access to lessons after enrollment." />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                    <tr>
                      <th className="px-5 py-3.5">ID</th>
                      <th className="px-5 py-3.5">Course</th>
                      <th className="px-5 py-3.5">Lesson</th>
                      <th className="px-5 py-3.5">Type</th>
                      <th className="px-5 py-3.5">Value</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rules.map((r) => (
                      <tr key={String(r.id)} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5">{r.id}</td>
                        <td className="px-5 py-3.5">{r.course_id}</td>
                        <td className="px-5 py-3.5">{r.lesson_id ?? '—'}</td>
                        <td className="px-5 py-3.5">{r.rule_type}</td>
                        <td className="px-5 py-3.5">{r.rule_value}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ListPanel>
        </>
      )}
    </AppShell>
  );
}
