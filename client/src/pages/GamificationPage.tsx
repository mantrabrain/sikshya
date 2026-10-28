import { useCallback, useState } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { useAddonEnabled } from '../hooks/useAddons';
import { useAsyncData } from '../hooks/useAsyncData';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { SkeletonCard } from '../components/shared/Skeleton';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { AddonSettingsPage } from './AddonSettingsPage';
import type { SikshyaReactConfig, WpRestUser } from '../types';
import { __ } from '../lib/i18n';

type BoardRow = { rank: number; user_id: number; name: string; points: number };

type BoardResponse = {
  ok?: boolean;
  scope?: string;
  rows?: BoardRow[];
  dormant?: boolean;
  visibility?: string;
  learners_with_points?: number;
};

const INPUT_CLASS =
  'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';

const LABEL_CLASS = 'block text-xs font-medium text-slate-600 dark:text-slate-300';

const SCOPES: Array<{ id: string; label: string }> = [
  { id: 'all', label: __('All time', 'sikshya') },
  { id: 'month', label: __('Last 30 days', 'sikshya') },
  { id: 'week', label: __('Last 7 days', 'sikshya') },
];

/**
 * Points and the leaderboard.
 *
 * The board shown here is the same board learners see, filtered the same way —
 * opted-out learners are missing from it, with the gaps in the numbering left
 * visible, because a staff view that quietly re-ranks around hidden people
 * would disagree with the public page about who came second.
 */
export function GamificationPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const featureOk = isFeatureEnabled(config, 'gamification');
  const addon = useAddonEnabled('gamification');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const toast = useTopRightToast(2600);
  const [scope, setScope] = useState('all');
  const [tab, setTab] = useState<'board' | 'settings'>('board');
  const [busy, setBusy] = useState(false);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { rows: [] as BoardRow[], dormant: true, learners: 0 };
    }
    const resp = await getSikshyaApi().get<BoardResponse>(SIKSHYA_ENDPOINTS.pro.pointsLeaderboard({ scope, limit: 50 }));
    return {
      rows: Array.isArray(resp.rows) ? resp.rows : [],
      dormant: Boolean(resp.dormant),
      learners: Number(resp.learners_with_points || 0),
    };
  }, [enabled, scope]);

  const { data, loading, error, refetch } = useAsyncData(loader, [enabled, scope]);
  const rows = data?.rows ?? [];

  const recalculate = async () => {
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.pointsRecalculate, {});
      toast.success(__('Done', 'sikshya'), __('Totals rebuilt from the ledger.', 'sikshya'));
      refetch();
    } catch (e) {
      toast.error(__('Error', 'sikshya'), e instanceof Error ? e.message : __('Could not rebuild totals.', 'sikshya'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle={__('What learners have earned, and where they stand.', 'sikshya')}
      pageActions={
        enabled ? (
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            {__('Refresh', 'sikshya')}
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="gamification"
        config={config}
        featureTitle={__('Points & leaderboard', 'sikshya')}
        featureDescription={__('Award points for lessons, quizzes and completions, and rank learners on a leaderboard you control the visibility of.', 'sikshya')}
        previewVariant="cards"
        addonEnableTitle={__('Points & leaderboard is not enabled', 'sikshya')}
        addonEnableDescription={__('Enable the add-on to start recording points. Every award amount starts at zero, so nothing changes until you set them.', 'sikshya')}
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        <div className="-mt-2 mb-5 flex flex-wrap items-center gap-2 overflow-x-auto">
          <HorizontalEditorTabs
            ariaLabel={__('Points sections', 'sikshya')}
            tabs={[
              { id: 'board', label: __('Leaderboard', 'sikshya'), icon: 'star' },
              { id: 'settings', label: __('What things are worth', 'sikshya'), icon: 'cog' },
            ]}
            value={tab}
            onChange={(id) => setTab(id === 'settings' ? 'settings' : 'board')}
          />
        </div>

        {tab === 'settings' ? (
          <AddonSettingsPage
            embedded
            config={config}
            title={__('Points settings', 'sikshya')}
            addonId="gamification"
            subtitle={__('What each achievement is worth, who may see the leaderboard, and how names appear on it.', 'sikshya')}
            featureTitle={__('Points settings', 'sikshya')}
            featureDescription={__('Every amount starts at zero. Nothing on the site awards points until you decide what they are worth.', 'sikshya')}
          />
        ) : (
          <>
        {data?.dormant ? (
          <p className="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
            {__(
              'Nothing on this site awards points yet — every amount is still zero. Set what a lesson, a passed quiz or a finished course is worth on the next tab.',
              'sikshya'
            )}
          </p>
        ) : null}

        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-points-scope">
              {__('Period', 'sikshya')}
            </label>
            <select
              id="sikshya-points-scope"
              className={`mt-1 w-48 ${INPUT_CLASS}`}
              value={scope}
              onChange={(e) => setScope(e.target.value)}
            >
              {SCOPES.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.label}
                </option>
              ))}
            </select>
          </div>
          <ButtonSecondary type="button" disabled={busy} onClick={() => void recalculate()}>
            {__('Rebuild totals from the ledger', 'sikshya')}
          </ButtonSecondary>
        </div>

        {error ? (
          <ApiErrorPanel error={error} title={__('Could not load the leaderboard', 'sikshya')} onRetry={() => refetch()} />
        ) : loading ? (
          <SkeletonCard rows={4} />
        ) : rows.length === 0 ? (
          <p className="text-sm text-slate-500">{__('Nobody has earned points in this period yet.', 'sikshya')}</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs uppercase text-slate-500">
                <th scope="col" className="py-2">{__('Place', 'sikshya')}</th>
                <th scope="col" className="py-2">{__('Learner', 'sikshya')}</th>
                <th scope="col" className="py-2 text-right">{__('Points', 'sikshya')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.user_id} className="border-t border-slate-100 dark:border-slate-800">
                  <td className="py-2">{row.rank}</td>
                  <td className="py-2">{row.name}</td>
                  <td className="py-2 text-right">{row.points}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        <AdjustPanel
          onDone={() => {
            toast.success(__('Saved', 'sikshya'), __('Points adjusted.', 'sikshya'));
            refetch();
          }}
          onError={(m) => toast.error(__('Error', 'sikshya'), m)}
        />

        <p className="mt-6 text-xs text-slate-500">
          {__('Put the public board on any page with the [sikshya_leaderboard] shortcode. It shows nothing to anyone the visibility setting does not cover.', 'sikshya')}
        </p>
          </>
        )}
      </GatedFeatureWorkspace>
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
    </EmbeddableShell>
  );
}

function AdjustPanel(props: { onDone: () => void; onError: (message: string) => void }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<WpRestUser[]>([]);
  const [userId, setUserId] = useState(0);
  const [points, setPoints] = useState('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  const search = async () => {
    const q = query.trim();
    if (q === '') {
      setResults([]);
      return;
    }
    try {
      const users = await getWpApi().get<WpRestUser[]>(
        `/users?context=edit&per_page=20&search=${encodeURIComponent(q)}`
      );
      setResults(Array.isArray(users) ? users : []);
    } catch {
      setResults([]);
    }
  };

  const submit = async () => {
    const amount = Number(points);
    if (!userId || !Number.isFinite(amount) || amount === 0) {
      props.onError(__('Choose a learner and a non-zero number of points.', 'sikshya'));
      return;
    }
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.pointsAdjust, { user_id: userId, points: amount, note });
      setPoints('');
      setNote('');
      props.onDone();
    } catch (e) {
      props.onError(e instanceof Error ? e.message : __('Could not adjust points.', 'sikshya'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="mt-8 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
      <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{__('Adjust a learner by hand', 'sikshya')}</h3>
      <p className="mt-1 text-xs text-slate-500">
        {__(
          'Use a negative number to take points away. Each adjustment is a separate line in the ledger, so the same correction can be applied twice on purpose.',
          'sikshya'
        )}
      </p>

      <div className="mt-3 grid gap-3 sm:grid-cols-4">
        <div className="sm:col-span-2">
          <label className={LABEL_CLASS} htmlFor="sikshya-points-user-search">
            {__('Find a learner', 'sikshya')}
          </label>
          <div className="mt-1 flex gap-2">
            <input
              id="sikshya-points-user-search"
              type="search"
              className={`w-full ${INPUT_CLASS}`}
              placeholder={__('Search by name or email…', 'sikshya')}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
            <ButtonSecondary type="button" onClick={() => void search()}>
              {__('Search', 'sikshya')}
            </ButtonSecondary>
          </div>
          {results.length > 0 ? (
            <div className="mt-2">
              <label className={LABEL_CLASS} htmlFor="sikshya-points-user">
                {__('Learner', 'sikshya')}
              </label>
              <select
                id="sikshya-points-user"
                className={`mt-1 w-full ${INPUT_CLASS}`}
                value={userId}
                onChange={(e) => setUserId(Number(e.target.value) || 0)}
              >
                <option value={0}>{__('— choose —', 'sikshya')}</option>
                {results.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name}
                  </option>
                ))}
              </select>
            </div>
          ) : null}
        </div>

        <div>
          <label className={LABEL_CLASS} htmlFor="sikshya-points-amount">
            {__('Points', 'sikshya')}
          </label>
          <input
            id="sikshya-points-amount"
            type="number"
            className={`mt-1 w-full ${INPUT_CLASS}`}
            value={points}
            onChange={(e) => setPoints(e.target.value)}
          />
        </div>

        <div>
          <label className={LABEL_CLASS} htmlFor="sikshya-points-note">
            {__('Note', 'sikshya')}
          </label>
          <input
            id="sikshya-points-note"
            type="text"
            className={`mt-1 w-full ${INPUT_CLASS}`}
            value={note}
            onChange={(e) => setNote(e.target.value)}
          />
        </div>
      </div>

      <div className="mt-3 flex justify-end">
        <ButtonPrimary type="button" disabled={busy} onClick={() => void submit()}>
          {busy ? __('Saving…', 'sikshya') : __('Apply adjustment', 'sikshya')}
        </ButtonPrimary>
      </div>
    </section>
  );
}
