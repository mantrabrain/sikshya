import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { getErrorSummary } from '../api/errors';
import { DataTable } from '../components/shared/DataTable';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListSearchToolbar, type SortFieldOption } from '../components/shared/list/ListSearchToolbar';
import { InlineRowActions } from '../components/shared/list/InlineRowActions';
import { DEFAULT_LIST_PER_PAGE, ListPaginationBar } from '../components/shared/list/ListPaginationBar';
import { ButtonPrimary } from '../components/shared/buttons';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import type { Column } from '../components/shared/DataTable';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { useWpTermCollection } from '../hooks/useWpTermCollection';
import type { NavItem, SikshyaReactConfig, WpTerm } from '../types';

const TAXONOMY = 'sikshya_course_category';

const FIELD =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';

type CategoryPayload = {
  id: number;
  name: string;
  slug: string;
  description: string;
  parent: number;
  image_id: number;
};

type WpMediaFrame = {
  open: () => void;
  on: (event: 'select', cb: () => void) => void;
  state: () => { get: (k: 'selection') => { first: () => { toJSON: () => { id?: number; url?: string } } } };
};

export function CourseCategoriesPage(props: { config: SikshyaReactConfig; title: string; subtitle: string }) {
  const { config, title, subtitle } = props;
  const { confirm, alert: alertDialog } = useSikshyaDialog();

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [slug, setSlug] = useState('');
  const [parent, setParent] = useState(0);
  const [imageId, setImageId] = useState(0);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const mediaFrameRef = useRef<WpMediaFrame | null>(null);
  const [loadingCategory, setLoadingCategory] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [saveOk, setSaveOk] = useState<string | null>(null);

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);
  const [orderby, setOrderby] = useState<'name' | 'count'>('name');
  const [order, setOrder] = useState<'asc' | 'desc'>('asc');
  const [listNonce, setListNonce] = useState(0);
  const [page, setPage] = useState(1);

  const bumpList = useCallback(() => setListNonce((n) => n + 1), []);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, orderby, order, listNonce]);

  const listQuery = useWpTermCollection({
    taxonomyRestBase: TAXONOMY,
    search: debouncedSearch,
    orderby,
    order,
    page,
    perPage: DEFAULT_LIST_PER_PAGE,
    refreshNonce: listNonce,
  });

  const rows = Array.isArray(listQuery.data?.data) ? listQuery.data.data : [];

  const startNew = useCallback(() => {
    setSelectedId(null);
    setName('');
    setDescription('');
    setSlug('');
    setParent(0);
    setImageId(0);
    setImagePreview(null);
    setFormError(null);
    setSaveOk(null);
  }, []);

  useEffect(() => {
    if (selectedId === null) {
      return;
    }
    let cancelled = false;
    setLoadingCategory(true);
    setFormError(null);
    void getSikshyaApi()
      .get<{ success: boolean; data?: { category?: CategoryPayload } }>(SIKSHYA_ENDPOINTS.admin.courseCategory(selectedId))
      .then((res) => {
        if (cancelled) {
          return;
        }
        const c = res.success && res.data?.category ? res.data.category : null;
        if (!c) {
          setFormError('Could not load category.');
          return;
        }
        setName(c.name);
        setDescription(c.description || '');
        setSlug(c.slug || '');
        setParent(typeof c.parent === 'number' ? c.parent : 0);
        setImageId(typeof c.image_id === 'number' ? c.image_id : 0);
      })
      .catch((e) => {
        if (!cancelled) {
          setFormError(getErrorSummary(e));
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingCategory(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [selectedId]);

  useEffect(() => {
    if (!imageId || imageId <= 0) {
      setImagePreview(null);
      return;
    }
    let cancelled = false;
    void getWpApi()
      .get<{ source_url?: string }>(`/media/${imageId}`)
      .then((m) => {
        if (!cancelled && m?.source_url) {
          setImagePreview(m.source_url);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setImagePreview(null);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [imageId]);

  const parentOptions = useMemo(() => rows.filter((t) => t.id !== selectedId), [rows, selectedId]);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setFormError(null);
    setSaveOk(null);
    try {
      const body: Record<string, string | number> = {
        name: name.trim(),
        description: description.trim(),
        slug: slug.trim(),
        parent,
        image: imageId > 0 ? imageId : 0,
      };
      if (selectedId !== null) {
        body.term_id = selectedId;
      }
      const res = await getSikshyaApi().post<{ success: boolean; message?: string }>(
        SIKSHYA_ENDPOINTS.admin.courseCategorySave,
        body
      );
      if (!res.success) {
        throw new Error(res.message || 'Save failed');
      }
      setSaveOk(selectedId === null ? 'Category created.' : 'Category updated.');
      bumpList();
      if (selectedId === null) {
        startNew();
      }
    } catch (err) {
      setFormError(getErrorSummary(err));
    } finally {
      setSaving(false);
    }
  };

  const sortFieldOptions: SortFieldOption[] = useMemo(
    () => [
      { value: 'name', label: 'Name' },
      { value: 'count', label: 'Course count' },
    ],
    []
  );

  const columns: Column<WpTerm>[] = useMemo(
    () => [
      {
        id: 'id',
        header: 'ID',
        alwaysVisible: true,
        cellClassName: 'whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400',
        render: (t) => t.id,
      },
      {
        id: 'image',
        header: '',
        columnPickerLabel: 'Image',
        alwaysVisible: true,
        headerClassName: 'w-16',
        cellClassName: 'w-16',
        render: (t) => {
          const src = typeof t.sikshya_category_image_url === 'string' ? t.sikshya_category_image_url : '';
          return (
            <div className="flex h-10 w-12 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
              {src ? (
                <img src={src} alt="" className="h-full w-full object-cover" loading="lazy" />
              ) : (
                <span className="text-[10px] font-medium text-slate-400 dark:text-slate-500">—</span>
              )}
            </div>
          );
        },
      },
      {
        id: 'name',
        header: 'Category',
        sortKey: 'name',
        render: (t) => (
          <div className="max-w-md">
            <button
              type="button"
              onClick={() => {
                setSelectedId(t.id);
                setSaveOk(null);
              }}
              className={`text-left font-semibold ${
                selectedId === t.id
                  ? 'text-brand-700 dark:text-brand-300'
                  : 'text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300'
              }`}
            >
              {t.name}
            </button>
            <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{t.slug}</div>
            <InlineRowActions
              ariaLabel={`Actions for ${t.name}`}
              items={[
                {
                  key: 'edit',
                  label: 'Edit in form',
                  onClick: () => {
                    setSelectedId(t.id);
                    setSaveOk(null);
                  },
                },
                {
                  key: 'delete',
                  label: 'Delete',
                  danger: true,
                  onClick: () =>
                    void (async () => {
                      const ok = await confirm({
                        title: 'Delete category?',
                        message: `Delete category “${t.name}”?`,
                        variant: 'danger',
                        confirmLabel: 'Delete',
                      });
                      if (!ok) {
                        return;
                      }
                      try {
                        await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.courseCategory(t.id));
                        if (selectedId === t.id) {
                          startNew();
                        }
                        bumpList();
                      } catch (e) {
                        await alertDialog({
                          title: 'Could not delete category',
                          message: getErrorSummary(e),
                        });
                      }
                    })(),
                },
              ]}
            />
          </div>
        ),
      },
      {
        id: 'count',
        header: 'Courses',
        sortKey: 'count',
        cellClassName: 'tabular-nums text-slate-600 dark:text-slate-400',
        render: (t) => (typeof t.count === 'number' ? t.count : '—'),
      },
    ],
    [selectedId, bumpList, startNew, confirm, alertDialog]
  );

  const emptyContent = (
    <ListEmptyState
      title="No categories found"
      description="No categories match your search. Adjust filters or add one using the form on the left."
    />
  );

  const onSortOrderToggle = () => setOrder((o) => (o === 'asc' ? 'desc' : 'asc'));

  const onSortColumn = useCallback(
    (key: string) => {
      if (key === orderby) {
        setOrder((o) => (o === 'asc' ? 'desc' : 'asc'));
      } else if (key === 'name' || key === 'count') {
        setOrderby(key);
        setOrder('asc');
      }
    },
    [orderby]
  );

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle={subtitle}
      pageActions={null}
    >
      <div className="grid gap-6 lg:grid-cols-[minmax(300px,380px)_1fr] lg:items-start">
        <aside className="lg:sticky lg:top-6">
          <div className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-4 dark:border-slate-800">
              <div>
                <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                  {selectedId === null ? 'Add category' : 'Edit category'}
                </h2>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Name, slug, parent, and image. Select a row on the right to edit.
                </p>
              </div>
              <button
                type="button"
                onClick={startNew}
                className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                New category
              </button>
            </div>

            {loadingCategory ? (
              <p className="mt-4 text-sm text-slate-500">Loading…</p>
            ) : (
              <form id="sikshya-category-side-form" className="mt-4 space-y-4" onSubmit={(e) => void onSubmit(e)}>
                <div>
                  <label htmlFor="cc-name" className={LABEL}>
                    Name
                  </label>
                  <input
                    id="cc-name"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className={FIELD}
                  />
                </div>
                <div>
                  <label htmlFor="cc-desc" className={LABEL}>
                    Description
                  </label>
                  <textarea
                    id="cc-desc"
                    rows={3}
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    className={`${FIELD} min-h-[72px] resize-y`}
                  />
                </div>
                <div>
                  <label htmlFor="cc-slug" className={LABEL}>
                    Slug
                  </label>
                  <input
                    id="cc-slug"
                    value={slug}
                    onChange={(e) => setSlug(e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                    className={`${FIELD} font-mono text-sm`}
                    placeholder="url-segment"
                  />
                </div>
                <div>
                  <label htmlFor="cc-parent" className={LABEL}>
                    Parent category
                  </label>
                  <select
                    id="cc-parent"
                    value={parent}
                    onChange={(e) => setParent(Number(e.target.value))}
                    className={FIELD}
                  >
                    <option value={0}>None</option>
                    {parentOptions.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className={LABEL}>Featured image</label>
                  <div className="mt-1.5 flex flex-wrap items-center gap-3">
                    <button
                      type="button"
                      onClick={() => {
                        const wpAny = window as unknown as {
                          wp?: {
                            media?: (opts: { title?: string; button?: { text?: string }; multiple?: boolean; library?: { type?: string } }) => WpMediaFrame;
                          };
                        };
                        const mediaFactory = wpAny.wp?.media;
                        if (!mediaFactory) {
                          void alertDialog({
                            title: 'Media picker unavailable',
                            message:
                              'WordPress media picker was not found. Please ensure media scripts are available in the admin page.',
                          });
                          return;
                        }
                        if (!mediaFrameRef.current) {
                          mediaFrameRef.current = mediaFactory({
                            title: 'Select category image',
                            button: { text: 'Use this image' },
                            multiple: false,
                            library: { type: 'image' },
                          });
                          mediaFrameRef.current.on('select', () => {
                            const sel = mediaFrameRef.current?.state().get('selection').first().toJSON();
                            const id = sel?.id ? Number(sel.id) : 0;
                            setImageId(id > 0 ? id : 0);
                            if (sel?.url) {
                              setImagePreview(String(sel.url));
                            }
                          });
                        }
                        mediaFrameRef.current.open();
                      }}
                      className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                      {imageId > 0 ? 'Change image' : 'Select image'}
                    </button>
                    {imageId > 0 ? (
                      <button
                        type="button"
                        onClick={() => {
                          setImageId(0);
                          setImagePreview(null);
                        }}
                        className="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                      >
                        Remove
                      </button>
                    ) : null}
                    {imageId > 0 ? (
                      <span className="text-xs text-slate-500 dark:text-slate-400">Attachment ID: {imageId}</span>
                    ) : (
                      <span className="text-xs text-slate-500 dark:text-slate-400">Optional</span>
                    )}
                  </div>
                  {imagePreview ? (
                    <div className="mt-2 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                      <img src={imagePreview} alt="" className="max-h-36 w-full object-cover" />
                    </div>
                  ) : null}
                </div>

                {formError ? (
                  <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
                    {formError}
                  </div>
                ) : null}
                {saveOk ? (
                  <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {saveOk}
                  </div>
                ) : null}

                <ButtonPrimary type="submit" className="w-full" disabled={saving}>
                  {saving ? 'Saving…' : selectedId === null ? 'Create category' : 'Update category'}
                </ButtonPrimary>
              </form>
            )}
          </div>
        </aside>

        <section className="min-w-0">
          <ListPanel>
            <ListSearchToolbar
              searchValue={search}
              onSearchChange={setSearch}
              searchPlaceholder="Search categories…"
              sortField={orderby}
              sortFieldOptions={sortFieldOptions}
              onSortFieldChange={(v) => setOrderby(v as 'name' | 'count')}
              sortOrder={order}
              onSortOrderToggle={onSortOrderToggle}
            />
            <div className="border-b border-slate-100 px-4 py-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
              {listQuery.data?.total != null ? (
                <span>
                  Showing {rows.length} of {listQuery.data.total}
                </span>
              ) : (
                <span>Course categories for your catalog</span>
              )}
            </div>
            {listQuery.error ? (
              <div className="p-4">
                <ApiErrorPanel error={listQuery.error} onRetry={listQuery.refetch} title="Could not load categories" />
              </div>
            ) : listQuery.loading ? (
              <DataTableSkeleton headers={['ID', 'Category', 'Courses']} rows={8} />
            ) : (
              <>
                <ListPaginationBar
                  placement="top"
                  page={page}
                  total={listQuery.data?.total ?? null}
                  totalPages={listQuery.data?.totalPages ?? null}
                  perPage={DEFAULT_LIST_PER_PAGE}
                  onPageChange={setPage}
                  disabled={listQuery.loading}
                />
                <DataTable
                  columns={columns}
                  rows={rows}
                  rowKey={(r) => r.id}
                  emptyContent={emptyContent}
                  wrapInCard={false}
                  sortState={{ orderby, order }}
                  onSortColumn={onSortColumn}
                />
                <ListPaginationBar
                  placement="bottom"
                  page={page}
                  total={listQuery.data?.total ?? null}
                  totalPages={listQuery.data?.totalPages ?? null}
                  perPage={DEFAULT_LIST_PER_PAGE}
                  onPageChange={setPage}
                  disabled={listQuery.loading}
                />
              </>
            )}
          </ListPanel>
        </section>
      </div>
    </AppShell>
  );
}
