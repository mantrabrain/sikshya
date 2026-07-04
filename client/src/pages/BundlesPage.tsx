import { useCallback, useEffect, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { AddonSettingsPage } from './AddonSettingsPage';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPanelSkeleton } from '../components/shared/Skeleton';
import { ButtonPrimary } from '../components/shared/buttons';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

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

type BundlesTabId = 'bundles' | 'settings';

/** Open the course builder on the Pricing tab for a given course / bundle post. */
function courseBuilderHref(config: SikshyaReactConfig, courseId: number): string {
  return appViewHref(config, 'add-course', { course_id: String(courseId), force_bundle_ui: '1' });
}

export function BundlesPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const dialog = useSikshyaDialog();
  const featureOk = isFeatureEnabled(config, 'course_bundles');
  const addon = useAddonEnabled('course_bundles');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [workspaceTab, setWorkspaceTab] = useState<BundlesTabId>('bundles');

  useEffect(() => {
    if (!enabled) {
      setWorkspaceTab('bundles');
    }
  }, [enabled]);

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
    const ok = await dialog.confirm({
      title: __('Move bundle to trash?', 'sikshya'),
      message: __('Existing enrollments are not affected.', 'sikshya'),
      confirmLabel: __('Move to trash', 'sikshya'),
      variant: 'danger',
    });
    if (!ok) return;
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.bundle(id));
      list.refetch();
      if (expandedId === id) setExpandedId(null);
    } catch (err) {
      void dialog.alert({
        title: __('Delete failed', 'sikshya'),
        message: err instanceof Error ? err.message : 'Delete failed.',
      });
    }
  };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle={__('Bundles are courses — create one here and configure it in the Course Builder.', 'sikshya')}
      pageActions={
        enabled ? (
          <ButtonPrimary type="button" disabled={list.loading} onClick={() => list.refetch()}>
            {list.loading ? __('Refreshing…', 'sikshya') : __('Refresh', 'sikshya')}
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="course_bundles"
        config={config}
        featureTitle={__('Course bundles', 'sikshya')}
        featureDescription={__('Package multiple courses into one purchase. Customers pay once and gain access to every course in the bundle.', 'sikshya')}
        previewVariant="cards"
        addonEnableTitle={__('Course bundles is not enabled', 'sikshya')}
        addonEnableDescription={__('Enable the Course bundles add-on to unlock bundle features.', 'sikshya')}
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        {enabled ? (
          <div className="-mt-2 mb-5 flex flex-wrap items-center gap-2 overflow-x-auto">
            <HorizontalEditorTabs
              ariaLabel={__('Course bundles sections', 'sikshya')}
              tabs={[
                { id: 'bundles', label: 'Bundles', icon: 'table' },
                { id: 'settings', label: 'Add-on defaults', icon: 'cog' },
              ]}
              value={workspaceTab}
              onChange={(id) => setWorkspaceTab(id as BundlesTabId)}
            />
          </div>
        ) : null}

        {enabled && workspaceTab === 'settings' ? (
          <AddonSettingsPage
            embedded
            config={config}
            title={__('Course bundles settings', 'sikshya')}
            addonId="course_bundles"
            subtitle={__('Control storefront links, redirects, marketing badges, and optional default bundle matching.', 'sikshya')}
            featureTitle={__('Course bundles settings', 'sikshya')}
            featureDescription={__('These options apply site-wide. Bundle contents and prices are still edited per bundle in the Course Builder.', 'sikshya')}
            relatedCoreSettingsTab="payment"
            relatedCoreSettingsLabel="Payment"
            nextSteps={[
              {
                label: 'Manage bundles',
                description: 'Create packs and open each one in the Course Builder.',
                href: appViewHref(config, 'bundles'),
              },
              {
                label: 'Open courses',
                description: 'Add regular courses, then attach them on the bundle’s Pricing tab.',
                href: appViewHref(config, 'courses'),
              },
            ]}
          />
        ) : null}

        {workspaceTab === 'settings' ? null : (
          <>
        {/* ── How bundles work notice ── */}
        <div className="mb-6 rounded-2xl border border-brand-100 bg-brand-50/60 p-5 dark:border-brand-900/40 dark:bg-brand-950/20">
          <div className="flex items-start gap-3">
            <span className="mt-0.5 shrink-0 text-brand-600 dark:text-brand-400" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
              </svg>
            </span>
            <div className="text-sm leading-relaxed text-brand-900 dark:text-brand-100">
              <p className="font-semibold">{__('Bundles live in the Course Builder', 'sikshya')}</p>
              <p className="mt-1 text-brand-700 dark:text-brand-300">
                A bundle is a regular course with <strong>{__('type = Course Bundle', 'sikshya')}</strong>. After creating one below,
                you land directly on the <strong>Pricing &amp; Access</strong> tab where you set the price, pick the
                included courses, and control listing visibility — all in one place.
              </p>
            </div>
          </div>
        </div>

        {/* ── Quick create ── */}
        <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{__('New bundle', 'sikshya')}</h2>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Give it a title, hit Create — you will be taken to the Course Builder to finish the setup.
          </p>
          <div className="mt-4 flex flex-wrap items-end gap-3">
            <label className="block flex-1 min-w-[200px] text-sm">
              <span className="text-slate-600 dark:text-slate-400">{__('Bundle title', 'sikshya')}</span>
              <input
                required
                value={titleInput}
                onChange={(e) => setTitleInput(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') void onCreate(); }}
                className="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                placeholder={__('e.g. Photography Mastery Bundle', 'sikshya')}
                disabled={creating}
              />
            </label>
            <label className="block w-36 text-sm">
              <span className="text-slate-600 dark:text-slate-400">{__('Starting price', 'sikshya')}</span>
              <input
                type="number"
                step="0.01"
                min="0"
                value={priceInput}
                onChange={(e) => setPriceInput(e.target.value)}
                className="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                placeholder={__('0.00', 'sikshya')}
                disabled={creating}
              />
            </label>
            <ButtonPrimary type="button" onClick={() => void onCreate()} disabled={creating || !titleInput.trim()}>
              {creating ? __('Creating…', 'sikshya') : __('Create & configure →', 'sikshya')}
            </ButtonPrimary>
          </div>
          {createError ? (
            <p className="mt-2 text-xs text-rose-600 dark:text-rose-400">{createError}</p>
          ) : null}
        </div>

        {/* ── Bundle list ── */}
        {list.error && workspaceTab === 'bundles' ? (
          <ApiErrorPanel error={list.error} title={__('Could not load bundles', 'sikshya')} onRetry={() => list.refetch()} />
        ) : null}

        <ListPanel>
          <div className="border-b border-slate-100 px-4 py-3 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">
            Existing bundles ({bundleRows.length})
          </div>

          {list.loading ? (
            <ListPanelSkeleton columns={3} rows={6} />
          ) : bundleRows.length === 0 ? (
            <ListEmptyState
              title={__('No bundles yet', 'sikshya')}
              description={__('Create your first bundle above. It will appear here once saved.', 'sikshya')}
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
                            <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                              Unlisted
                            </span>
                          ) : null}
                          <span className="rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700 dark:border-brand-900/50 dark:bg-brand-950/30 dark:text-brand-300">
                            Bundle
                          </span>
                        </div>
                        <div className="mt-0.5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                          <span className="tabular-nums">{Number(b.price).toFixed(2)} {b.currency}</span>
                          <span>·</span>
                          <span>{b.course_count} {b.course_count === 1 ? __('course', 'sikshya') : __('courses', 'sikshya')}</span>
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
                            title={__('View on site', 'sikshya')}
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
                          <p className="mt-2 text-xs text-slate-400">{__('Generating link…', 'sikshya')}</p>
                        ) : purchaseUrl ? (
                          <div className="mt-2 flex flex-wrap items-center gap-2">
                            <input
                              readOnly
                              value={purchaseUrl}
                              className="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-2 py-1.5 font-mono text-xs text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
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
          </>
        )}
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
