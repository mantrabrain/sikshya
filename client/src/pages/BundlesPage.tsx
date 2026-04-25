import { useCallback, useEffect, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import type { NavItem, SikshyaReactConfig } from '../types';

type BundleRow = {
  id: number;
  title: string;
  slug: string;
  price: number | string;
  currency: string;
  status: string;
  visible_in_listing: boolean;
  course_count: number;
  edit_url: string;
  single_url: string;
};

type ListResp = { ok?: boolean; bundles?: BundleRow[] };

/** Open the course builder on the Pricing tab for a given course / bundle post. */
function courseBuilderHref(config: SikshyaReactConfig, courseId: number): string {
  return appViewHref(config, 'add-course', { course_id: String(courseId), force_bundle_ui: '1' });
}

export function BundlesPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const featureOk = isFeatureEnabled(config, 'course_bundles');
  const addon = useAddonEnabled('course_bundles');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);
  const [titleInput, setTitleInput] = useState('');
  const [priceInput, setPriceInput] = useState('');

  // Purchase link per bundle (fetched on demand when row is expanded).
  const [purchaseUrls, setPurchaseUrls] = useState<Record<number, string>>({});
  const [loadingLinks, setLoadingLinks] = useState<Record<number, boolean>>({});

  const listLoader = useCallback(async () => {
    if (!enabled) return { ok: true, bundles: [] as BundleRow[] };
    return getSikshyaApi().get<ListResp>(SIKSHYA_ENDPOINTS.pro.bundles);
  }, [enabled]);
  const list = useAsyncData(listLoader, [enabled]);
  const bundleRows = list.data?.bundles ?? [];

  const fetchPurchaseLink = async (id: number) => {
    if (purchaseUrls[id] || loadingLinks[id]) return;
    setLoadingLinks((p) => ({ ...p, [id]: true }));
    try {
      const r = await getSikshyaApi().get<{ ok?: boolean; url?: string }>(
        SIKSHYA_ENDPOINTS.pro.bundlePurchaseLink(id)
      );
      if (r.ok && r.url) {
        setPurchaseUrls((p) => ({ ...p, [id]: r.url as string }));
      }
    } finally {
      setLoadingLinks((p) => ({ ...p, [id]: false }));
    }
  };

  const [expandedId, setExpandedId] = useState<number | null>(null);

  useEffect(() => {
    if (expandedId && !purchaseUrls[expandedId]) {
      void fetchPurchaseLink(expandedId);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expandedId]);

  /** Create a bare bundle post then immediately open the Course Builder on the Pricing tab. */
  const onCreate = async () => {
    const t = titleInput.trim();
    if (!t) { setCreateError('Bundle title is required.'); return; }
    setCreateError(null);
    setCreating(true);
    try {
      const r = await getSikshyaApi().post<{ ok?: boolean; id?: number; edit_url?: string; message?: string }>(
        SIKSHYA_ENDPOINTS.pro.bundles,
        {
          title: t,
          price: parseFloat(priceInput) || 0,
          visible_in_listing: true,
        }
      );
      if (r.ok && r.id) {
        // Go straight to the Course Builder → Pricing tab where the instructor
        // fills in the bundle details (price, included courses, visibility).
        window.location.href = courseBuilderHref(config, r.id);
      } else {
        setCreateError(r.message || 'Could not create bundle.');
        setCreating(false);
      }
    } catch (err) {
      setCreateError(err instanceof Error ? err.message : 'Could not create bundle.');
      setCreating(false);
    }
  };

  const onDelete = async (id: number) => {
    if (!window.confirm('Move this bundle to trash? Existing enrollments are not affected.')) return;
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.bundle(id));
      list.refetch();
      if (expandedId === id) setExpandedId(null);
    } catch (err) {
      window.alert(err instanceof Error ? err.message : 'Delete failed.');
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
      branding={config.branding}
      title={title}
      subtitle="Bundles are courses — create one here and configure it in the Course Builder."
      pageActions={
        enabled ? (
          <ButtonPrimary type="button" disabled={list.loading} onClick={() => list.refetch()}>
            {list.loading ? 'Refreshing…' : 'Refresh'}
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="course_bundles"
        config={config}
        featureTitle="Course bundles"
        featureDescription="Package multiple courses into one purchase. Customers pay once and gain access to every course in the bundle."
        previewVariant="cards"
        addonEnableTitle="Course bundles is not enabled"
        addonEnableDescription="Enable the Course bundles add-on to unlock bundle features."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        {/* ── How bundles work notice ── */}
        <div className="mb-6 rounded-2xl border border-brand-100 bg-brand-50/60 p-5 dark:border-brand-900/40 dark:bg-brand-950/20">
          <div className="flex items-start gap-3">
            <span className="mt-0.5 shrink-0 text-brand-600 dark:text-brand-400" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
              </svg>
            </span>
            <div className="text-sm leading-relaxed text-brand-900 dark:text-brand-100">
              <p className="font-semibold">Bundles live in the Course Builder</p>
              <p className="mt-1 text-brand-700 dark:text-brand-300">
                A bundle is a regular course with <strong>type = Course Bundle</strong>. After creating one below,
                you land directly on the <strong>Pricing &amp; Access</strong> tab where you set the price, pick the
                included courses, and control listing visibility — all in one place.
              </p>
            </div>
          </div>
        </div>

        {/* ── Quick create ── */}
        <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h2 className="text-sm font-semibold text-slate-900 dark:text-white">New bundle</h2>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Give it a title, hit Create — you will be taken to the Course Builder to finish the setup.
          </p>
          <div className="mt-4 flex flex-wrap items-end gap-3">
            <label className="block flex-1 min-w-[200px] text-sm">
              <span className="text-slate-600 dark:text-slate-400">Bundle title</span>
              <input
                required
                value={titleInput}
                onChange={(e) => setTitleInput(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') void onCreate(); }}
                className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                placeholder="e.g. Photography Mastery Bundle"
                disabled={creating}
              />
            </label>
            <label className="block w-36 text-sm">
              <span className="text-slate-600 dark:text-slate-400">Starting price</span>
              <input
                type="number"
                step="0.01"
                min="0"
                value={priceInput}
                onChange={(e) => setPriceInput(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                placeholder="0.00"
                disabled={creating}
              />
            </label>
            <ButtonPrimary type="button" onClick={() => void onCreate()} disabled={creating || !titleInput.trim()}>
              {creating ? 'Creating…' : 'Create & configure →'}
            </ButtonPrimary>
          </div>
          {createError ? (
            <p className="mt-2 text-xs text-rose-600 dark:text-rose-400">{createError}</p>
          ) : null}
        </div>

        {/* ── Bundle list ── */}
        {list.error ? (
          <ApiErrorPanel error={list.error} title="Could not load bundles" onRetry={() => list.refetch()} />
        ) : null}

        <ListPanel>
          <div className="border-b border-slate-100 px-4 py-3 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">
            Existing bundles ({bundleRows.length})
          </div>

          {list.loading ? (
            <div className="p-6 text-sm text-slate-500">Loading…</div>
          ) : bundleRows.length === 0 ? (
            <ListEmptyState
              title="No bundles yet"
              description="Create your first bundle above. It will appear here once saved."
            />
          ) : (
            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
              {bundleRows.map((b) => {
                const isExpanded = expandedId === b.id;
                const purchaseUrl = purchaseUrls[b.id];
                return (
                  <li key={b.id} className="text-sm">
                    {/* ── Row summary ── */}
                    <div className="flex items-center gap-3 px-4 py-3">
                      <button
                        type="button"
                        onClick={() => setExpandedId(isExpanded ? null : b.id)}
                        className="flex-1 text-left"
                        aria-expanded={isExpanded}
                      >
                        <div className="flex items-center gap-2 font-medium text-slate-900 dark:text-white">
                          <span>{b.title}</span>
                          {!b.visible_in_listing ? (
                            <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                              Unlisted
                            </span>
                          ) : null}
                          <span className="rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 dark:border-brand-900/50 dark:bg-brand-950/30 dark:text-brand-300">
                            Bundle
                          </span>
                        </div>
                        <div className="mt-0.5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                          <span className="tabular-nums">{Number(b.price).toFixed(2)} {b.currency}</span>
                          <span>·</span>
                          <span>{b.course_count} {b.course_count === 1 ? 'course' : 'courses'}</span>
                          <span>·</span>
                          <span className="capitalize">{b.status}</span>
                          <span>·</span>
                          <span>ID {b.id}</span>
                        </div>
                      </button>

                      <div className="flex shrink-0 items-center gap-2">
                        <a
                          href={courseBuilderHref(config, b.id)}
                          className="rounded-md border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-900/50 dark:bg-brand-950/40 dark:text-brand-200"
                        >
                          Edit in Course Builder
                        </a>
                        {b.single_url ? (
                          <a
                            href={b.single_url}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            title="View on site"
                          >
                            ↗
                          </a>
                        ) : null}
                        <button
                          type="button"
                          onClick={() => void onDelete(b.id)}
                          className="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200"
                        >
                          Delete
                        </button>
                      </div>
                    </div>

                    {/* ── Expanded: purchase / storefront link ── */}
                    {isExpanded ? (
                      <div className="border-t border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/40">
                        <p className="text-xs font-semibold text-slate-600 dark:text-slate-300">
                          Storefront "add to cart" link
                        </p>
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                          Share this URL on landing pages. It adds all bundle courses to the cart at the bundle price.
                        </p>
                        {loadingLinks[b.id] ? (
                          <p className="mt-2 text-xs text-slate-400">Generating link…</p>
                        ) : purchaseUrl ? (
                          <div className="mt-2 flex flex-wrap items-center gap-2">
                            <input
                              readOnly
                              value={purchaseUrl}
                              className="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 font-mono text-[11px] text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                              onFocus={(e) => e.target.select()}
                            />
                            <button
                              type="button"
                              className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                              onClick={() => void navigator.clipboard.writeText(purchaseUrl)}
                            >
                              Copy
                            </button>
                          </div>
                        ) : (
                          <button
                            type="button"
                            className="mt-2 text-xs text-brand-600 underline dark:text-brand-400"
                            onClick={() => void fetchPurchaseLink(b.id)}
                          >
                            Generate link
                          </button>
                        )}

                        <div className="mt-3 flex items-center gap-2">
                          <a
                            href={courseBuilderHref(config, b.id)}
                            className="rounded-md border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 dark:border-brand-900/50 dark:bg-brand-950/40 dark:text-brand-200"
                          >
                            Open Course Builder → Pricing tab
                          </a>
                          <span className="text-xs text-slate-400">
                            Set price, pick included courses, toggle visibility
                          </span>
                        </div>
                      </div>
                    ) : null}
                  </li>
                );
              })}
            </ul>
          )}
        </ListPanel>
      </GatedFeatureWorkspace>
    </AppShell>
  );
}
