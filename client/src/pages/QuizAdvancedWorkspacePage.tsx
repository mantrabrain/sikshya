import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { getErrorSummary } from '../api/errors';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary, LinkButtonSecondary } from '../components/shared/buttons';
import { DataTable, type Column } from '../components/shared/DataTable';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { TermEntityListView } from '../components/shared/list/TermEntityListView';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { appViewHref } from '../lib/appUrl';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { AddonSettingsPage } from './AddonSettingsPage';
import type { SikshyaReactConfig, WpTerm } from '../types';

type BankTerm = { slug: string; name: string; count: number };

type TermsResp = { ok?: boolean; terms?: BankTerm[] };

type PreviewResp = {
  ok?: boolean;
  tag?: string;
  meta_count?: number;
  taxonomy_count?: number;
  combined_count?: number;
  sample_question_ids?: number[];
  message?: string;
};

const QB_TAXONOMY = 'sikshya_qbank';

function slugifyBankName(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

export function QuizAdvancedWorkspacePage(props: {
  config: SikshyaReactConfig;
  title: string;
  embedded?: boolean;
}) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'quiz_advanced');
  const addon = useAddonEnabled('quiz_advanced');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const termsLoader = useCallback(async () => {
    if (!enabled) return { ok: true, terms: [] as BankTerm[] };
    return getSikshyaApi().get<TermsResp>(SIKSHYA_ENDPOINTS.pro.quizAdvancedBankTerms);
  }, [enabled]);
  const termsState = useAsyncData(termsLoader, [enabled]);
  const terms = termsState.data?.terms ?? [];

  const [tagInput, setTagInput] = useState('');
  const [preview, setPreview] = useState<PreviewResp | null>(null);
  const [previewErr, setPreviewErr] = useState<unknown>(null);
  const [previewBusy, setPreviewBusy] = useState(false);

  const [workspaceTab, setWorkspaceTab] = useState<'overview' | 'terms'>('overview');
  const [termListNonce, setTermListNonce] = useState(0);
  const [newBankName, setNewBankName] = useState('');
  const [newBankSlug, setNewBankSlug] = useState('');
  const [createBusy, setCreateBusy] = useState(false);
  const [createErr, setCreateErr] = useState<string | null>(null);
  const [createOk, setCreateOk] = useState<string | null>(null);

  const questionsReactHref = useMemo(() => appViewHref(config, 'content-library', { tab: 'questions' }), [config]);

  const runPreview = async () => {
    const t = tagInput.trim();
    if (!enabled || !t) return;
    setPreviewBusy(true);
    setPreviewErr(null);
    setPreview(null);
    try {
      const r = await getSikshyaApi().get<PreviewResp>(SIKSHYA_ENDPOINTS.pro.quizAdvancedPoolPreview(t));
      setPreview(r);
    } catch (e) {
      setPreviewErr(e);
    } finally {
      setPreviewBusy(false);
    }
  };

  const columns: Column<BankTerm>[] = useMemo(
    () => [
      { id: 'name', header: 'Bank', render: (r) => r.name },
      {
        id: 'slug',
        header: 'Slug (pool tag)',
        render: (r) => <code className="text-xs">{r.slug}</code>,
      },
      {
        id: 'count',
        header: 'Questions',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (r) => String(r.count),
      },
    ],
    [],
  );

  const termColumns: Column<WpTerm>[] = useMemo(
    () => [
      { id: 'name', header: 'Bank name', render: (r) => r.name },
      {
        id: 'slug',
        header: 'Slug (pool tag)',
        render: (r) => <code className="text-xs">{r.slug}</code>,
      },
      {
        id: 'count',
        header: 'Questions',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (r) => String(r.count ?? 0),
      },
    ],
    []
  );

  const createBankTerm = async () => {
    const name = newBankName.trim();
    if (!name) {
      setCreateErr('Enter a bank name.');
      return;
    }
    setCreateBusy(true);
    setCreateErr(null);
    setCreateOk(null);
    try {
      const slugRaw = newBankSlug.trim();
      const slug = slugRaw || slugifyBankName(name) || 'bank';
      await getWpApi().post<WpTerm>(`/${QB_TAXONOMY}`, { name, slug });
      setNewBankName('');
      setNewBankSlug('');
      setCreateOk('Bank created.');
      setTermListNonce((n) => n + 1);
      termsState.refetch();
    } catch (e) {
      setCreateErr(getErrorSummary(e));
    } finally {
      setCreateBusy(false);
    }
  };

  const inner = (
    <GatedFeatureWorkspace
      mode={mode}
      featureId="quiz_advanced"
      config={config}
      featureTitle="Advanced quiz types"
      featureDescription="Reuse questions via banks and pool tags, shuffle order, paginate one question at a time, and keep grading aligned with what each learner actually saw."
      previewVariant="table"
      addonEnableTitle="Advanced quiz types are not enabled"
      addonEnableDescription="Enable the add-on to edit global settings, inspect taxonomy banks, and preview pool sizes before you wire quizzes."
      canEnable={Boolean(addon.licenseOk)}
      enableBusy={addon.loading}
      onEnable={() => void addon.enable()}
      addonError={addon.error}
    >
      {enabled ? (
        <div className="space-y-6">
          <div className="-mt-1 flex flex-wrap items-center gap-2 overflow-x-auto">
            <HorizontalEditorTabs
              ariaLabel="Question banks workspace"
              tabs={[
                { id: 'overview', label: 'Banks & preview', icon: 'table' },
                { id: 'terms', label: 'Bank terms', icon: 'tag' },
              ]}
              value={workspaceTab}
              onChange={(id) => setWorkspaceTab(id as 'overview' | 'terms')}
            />
          </div>

          {workspaceTab === 'overview' ? (
            <div className="space-y-8">
              <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                <h2 className="text-base font-semibold text-slate-900 dark:text-white">Question banks (taxonomy)</h2>
                <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                  Assign banks in the block editor or under <strong>Content library → Questions</strong>. Use the same
                  slug in a quiz’s <em>Pool tag</em> field (or legacy pool meta on questions) for random draws. Create
                  and edit taxonomy banks in the <strong>Bank terms</strong> tab—no classic WordPress tags screen
                  required.
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                  <LinkButtonSecondary href={questionsReactHref}>Browse all questions</LinkButtonSecondary>
                  <ButtonSecondary type="button" onClick={() => setWorkspaceTab('terms')}>
                    Manage bank terms
                  </ButtonSecondary>
                </div>
                {termsState.loading ? (
                  <p className="mt-4 text-sm text-slate-500">Loading banks…</p>
                ) : termsState.error ? (
                  <div className="mt-4">
                    <ApiErrorPanel error={termsState.error} title="Could not load banks" onRetry={() => termsState.refetch()} />
                  </div>
                ) : terms.length === 0 ? (
                  <p className="mt-4 text-sm text-slate-500">
                    No taxonomy banks yet. Open <strong>Bank terms</strong> to add a bank, then assign it to published
                    questions to see counts here.
                  </p>
                ) : (
                  <div className="mt-4 overflow-x-auto">
                    <DataTable<BankTerm> columns={columns} rows={terms} rowKey={(r) => r.slug} wrapInCard={false} />
                  </div>
                )}
              </section>

              <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                <h2 className="text-base font-semibold text-slate-900 dark:text-white">Pool preview</h2>
                <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                  Enter a pool slug to see how many published questions match via meta tag, via taxonomy, and combined
                  (deduplicated).
                </p>
                <div className="mt-3 flex flex-wrap items-end gap-2">
                  <div>
                    <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400" htmlFor="qa-pool-tag">
                      Pool tag
                    </label>
                    <input
                      id="qa-pool-tag"
                      className="w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                      value={tagInput}
                      onChange={(e) => setTagInput(e.target.value)}
                      placeholder="e.g. unit-1"
                    />
                  </div>
                  <ButtonPrimary type="button" disabled={previewBusy || !tagInput.trim()} onClick={() => void runPreview()}>
                    {previewBusy ? 'Checking…' : 'Preview'}
                  </ButtonPrimary>
                </div>
                {previewErr ? (
                  <div className="mt-3">
                    <ApiErrorPanel error={previewErr} title="Preview failed" />
                  </div>
                ) : null}
                {preview && preview.ok ? (
                  <ul className="mt-4 grid gap-2 text-sm text-slate-700 dark:text-slate-300 sm:grid-cols-3">
                    <li>
                      <span className="font-medium">Meta tag matches:</span> {preview.meta_count ?? 0}
                    </li>
                    <li>
                      <span className="font-medium">Taxonomy matches:</span> {preview.taxonomy_count ?? 0}
                    </li>
                    <li>
                      <span className="font-medium">Combined (unique):</span> {preview.combined_count ?? 0}
                    </li>
                  </ul>
                ) : null}
                {preview && preview.ok && (preview.sample_question_ids?.length ?? 0) > 0 ? (
                  <p className="mt-2 text-xs text-slate-500">
                    Sample IDs: {(preview.sample_question_ids ?? []).join(', ')}
                    {(preview.combined_count ?? 0) > 25 ? ' …' : ''}
                  </p>
                ) : null}
                {preview && !preview.ok ? (
                  <p className="mt-3 text-sm text-amber-800 dark:text-amber-200">{preview.message || 'Invalid request.'}</p>
                ) : null}
              </section>
            </div>
          ) : (
            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
              <h2 className="text-base font-semibold text-slate-900 dark:text-white">Bank terms (REST)</h2>
              <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                These terms are the taxonomy behind question banks. The slug is what you enter as a quiz <em>Pool tag</em>
                . Assign banks to individual questions from the question editor sidebar or the Questions list.
              </p>

              <div className="mt-4 grid gap-4 rounded-lg border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/30 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div>
                  <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400" htmlFor="qb-new-name">
                    New bank name
                  </label>
                  <input
                    id="qb-new-name"
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                    value={newBankName}
                    onChange={(e) => setNewBankName(e.target.value)}
                    placeholder="e.g. Unit 1 review"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400" htmlFor="qb-new-slug">
                    Slug (optional)
                  </label>
                  <input
                    id="qb-new-slug"
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                    value={newBankSlug}
                    onChange={(e) => setNewBankSlug(e.target.value)}
                    placeholder="Defaults from name"
                  />
                </div>
                <ButtonPrimary type="button" disabled={createBusy} onClick={() => void createBankTerm()}>
                  {createBusy ? 'Saving…' : 'Add bank'}
                </ButtonPrimary>
              </div>
              {createErr ? (
                <p className="mt-2 text-sm text-rose-700 dark:text-rose-300">{createErr}</p>
              ) : null}
              {createOk ? (
                <p className="mt-2 text-sm text-emerald-800 dark:text-emerald-200">{createOk}</p>
              ) : null}

              <div className="mt-6">
                <TermEntityListView
                  taxonomyRestBase={QB_TAXONOMY}
                  listRefreshNonce={termListNonce}
                  contextHint="Search and sort banks. Counts reflect published questions tagged with each bank."
                  searchPlaceholder="Search banks…"
                  sortFieldOptions={[
                    { value: 'name', label: 'Name' },
                    { value: 'count', label: 'Question count' },
                  ]}
                  defaultSortField="name"
                  columns={termColumns}
                  emptyMessage="No banks match your search."
                  columnPickerStorageKey="sikshya_qbank_terms"
                  skeletonHeaders={['Bank name', 'Slug (pool tag)', 'Questions']}
                  emptyStateTitle="No question banks yet"
                  emptyStateDescription="Add a bank above, then assign it to questions from Content library → Questions."
                  emptyStateAction={<LinkButtonSecondary href={questionsReactHref}>Open questions</LinkButtonSecondary>}
                />
              </div>
            </section>
          )}

          <AddonSettingsPage
            embedded
            config={config}
            title="Advanced quiz settings"
            addonId="quiz_advanced"
            subtitle="Pool draws and learner-facing notices for randomized banks."
            featureTitle="Advanced quiz settings"
            featureDescription="Tune how many questions can be drawn from a pool and whether learners see a short notice when a quiz uses randomized banks."
            relatedCoreSettingsTab="quizzes"
            relatedCoreSettingsLabel="Quizzes"
            nextSteps={[
              {
                label: 'Edit a quiz',
                href: appViewHref(config, 'content-library', { tab: 'quizzes' }),
                description: 'Set pool tag, draw count, shuffle, and one-per-page on individual quizzes.',
              },
              {
                label: 'Tag questions',
                href: appViewHref(config, 'content-library', { tab: 'questions' }),
                description: 'Use pool meta and/or Question banks taxonomy so pools stay organized.',
              },
            ]}
          />
        </div>
      ) : null}
    </GatedFeatureWorkspace>
  );

  if (embedded) {
    return (
      <EmbeddableShell embedded config={config} title={title}>
        {inner}
      </EmbeddableShell>
    );
  }

  return inner;
}
