import { useMemo, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { EntityListView, StatusBadge } from '../components/shared/list';
import { RowActionsMenu } from '../components/shared/list/RowActionsMenu';
import { ButtonPrimary, LinkButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { useEntityListMockEnabled } from '../lib/entityListMock';
import { getMockRowsForRestBase } from '../lib/mockWpPosts';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import type { NavItem, SikshyaReactConfig, WpPost } from '../types';
import { getWpApi } from '../api';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { NavIcon } from '../components/NavIcon';

function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

function certificatePreviewSrc(r: WpPost): string | null {
  const url = r._embedded?.['wp:featuredmedia']?.[0]?.source_url;
  return typeof url === 'string' && url.length > 0 ? url : null;
}

function AddLessonTypeModal(props: {
  open: boolean;
  busy: boolean;
  error: unknown;
  onClose: () => void;
  onCreate: (lessonType: 'text' | 'video') => void;
}) {
  const { open, busy, error, onClose, onCreate } = props;
  if (!open) {
    return null;
  }
  return (
    <div
      className="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-[2px] sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="sikshya-add-lesson-type-title"
    >
      <button type="button" className="absolute inset-0 z-0 cursor-default" aria-label="Close" onClick={onClose} />
      <div className="relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h2 id="sikshya-add-lesson-type-title" className="text-lg font-semibold text-slate-900 dark:text-white">
          Add lesson
        </h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the lesson type to start with.</p>
        {error ? (
          <div className="mt-4">
            <ApiErrorPanel error={error} title="Could not create lesson" onRetry={() => void 0} />
          </div>
        ) : null}
        <div className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
          <button
            type="button"
            disabled={busy}
            onClick={() => onCreate('text')}
            className="rounded-xl border border-slate-200 bg-white px-4 py-4 text-left text-sm font-semibold text-slate-800 transition hover:border-slate-300 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          >
            <div className="flex items-center gap-2">
              <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-100">
                <NavIcon name="bookOpen" className="h-4 w-4" />
              </span>
              <span>Text lesson</span>
            </div>
            <div className="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
              Written content, notes, and resources.
            </div>
          </button>
          <button
            type="button"
            disabled={busy}
            onClick={() => onCreate('video')}
            className="rounded-xl border border-slate-200 bg-white px-4 py-4 text-left text-sm font-semibold text-slate-800 transition hover:border-slate-300 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          >
            <div className="flex items-center gap-2">
              <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-100">
                <NavIcon name="video" className="h-4 w-4" />
              </span>
              <span>Video lesson</span>
            </div>
            <div className="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
              Video URL plus transcript/notes.
            </div>
          </button>
        </div>
        <div className="mt-6 flex justify-end">
          <button
            type="button"
            disabled={busy}
            onClick={onClose}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
}

export function WpEntityListPage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle: string;
  restBase: string;
}) {
  const { config, title, subtitle, restBase } = props;

  const newHref = appViewHref(config, 'edit-content', { post_type: restBase });
  const isLessonList = restBase === 'sik_lesson';
  const isCertificateList = restBase === 'sikshya_certificate';
  const [addLessonOpen, setAddLessonOpen] = useState(false);
  const [addLessonBusy, setAddLessonBusy] = useState(false);
  const [addLessonError, setAddLessonError] = useState<unknown>(null);

  const columns: Column<WpPost>[] = useMemo(
    () => {
      const editHref = (id: number) =>
        appViewHref(config, 'edit-content', { post_type: restBase, post_id: String(id) });

      const previewCol: Column<WpPost> = {
        id: 'preview',
        header: 'Preview',
        defaultHidden: false,
        cellClassName: 'w-20',
        render: (r) => {
          const src = certificatePreviewSrc(r);
          return (
            <div className="flex h-12 w-16 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800">
              {src ? (
                <img src={src} alt="" className="h-full w-full object-cover" loading="lazy" />
              ) : (
                <NavIcon name="badge" className="h-5 w-5 text-slate-300 dark:text-slate-600" />
              )}
            </div>
          );
        },
      };

      const titleCol: Column<WpPost> = {
        id: 'title',
        header: 'Title',
        render: (r) => {
          const meta = r.meta as Record<string, unknown> | undefined;
          const orient = meta
            ? String(meta._sikshya_certificate_orientation || meta.sikshya_certificate_orientation || '')
            : '';
          return (
            <div className="max-w-md">
              <a
                href={editHref(r.id)}
                className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
              >
                <span className="inline-flex items-center gap-2">
                  {isLessonList ? (
                    <span className="flex h-7 w-7 items-center justify-center rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                      <NavIcon
                        name={
                          String(meta?._sikshya_lesson_type || '').toLowerCase() === 'video' ? 'video' : 'bookOpen'
                        }
                        className="h-4 w-4"
                      />
                    </span>
                  ) : null}
                  <span dangerouslySetInnerHTML={{ __html: r.title.rendered }} />
                </span>
              </a>
              {isCertificateList && orient ? (
                <div className="mt-0.5 text-xs capitalize text-slate-500 dark:text-slate-400">{orient}</div>
              ) : null}
              {r.slug ? (
                <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{r.slug}</div>
              ) : null}
            </div>
          );
        },
      };

      const rest: Column<WpPost>[] = [
        {
          id: 'modified',
          header: 'Updated',
          cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
          render: (r) => formatPostDate(r.modified || r.date),
        },
        {
          id: 'status',
          header: 'Status',
          render: (r) => <StatusBadge status={r.status} />,
        },
        {
          id: 'actions',
          header: '',
          alwaysVisible: true,
          headerClassName: 'w-14 text-right',
          cellClassName: 'text-right',
          render: (r) => {
            const label = stripTags(r.title.rendered) || 'Item';
            const items = [
              { key: 'edit', label: 'Edit', href: editHref(r.id) },
              ...(r.link && r.link !== '#'
                ? [{ key: 'view', label: 'View', href: r.link, external: true as const }]
                : []),
            ];
            return <RowActionsMenu ariaLabel={`Actions for ${label}`} items={items} />;
          },
        },
      ];

      return isCertificateList ? [previewCol, titleCol, ...rest] : [titleCol, ...rest];
    },
    [config, restBase, isLessonList, isCertificateList]
  );

  const searchPh = `Search ${title.toLowerCase()}…`;
  const pickerKey = `post_${restBase.replace(/[^a-z0-9_-]/gi, '_')}`;
  const useMock = useEntityListMockEnabled(config);
  const mockRows = getMockRowsForRestBase(restBase);

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
      pageActions={
        isLessonList ? (
          <ButtonPrimary
            onClick={() => {
              setAddLessonError(null);
              setAddLessonOpen(true);
            }}
          >
            + Add lesson
          </ButtonPrimary>
        ) : (
          <LinkButtonPrimary href={newHref}>+ Add new</LinkButtonPrimary>
        )
      }
    >
      <AddLessonTypeModal
        open={addLessonOpen}
        busy={addLessonBusy}
        error={addLessonError}
        onClose={() => {
          if (!addLessonBusy) {
            setAddLessonOpen(false);
          }
        }}
        onCreate={(lessonType) => {
          setAddLessonBusy(true);
          setAddLessonError(null);
          void getWpApi()
            .post<{ id: number }>(`/${restBase}`, {
              title: lessonType === 'video' ? 'New video lesson' : 'New text lesson',
              status: 'draft',
              meta: {
                _sikshya_lesson_type: lessonType,
              },
            })
            .then((created) => {
              if (!created?.id) {
                throw new Error('Could not create lesson.');
              }
              window.location.href = appViewHref(config, 'edit-content', {
                post_type: restBase,
                post_id: String(created.id),
              });
            })
            .catch((e) => {
              setAddLessonError(e);
            })
            .finally(() => {
              setAddLessonBusy(false);
            });
        }}
      />
      <EntityListView
        restBase={restBase}
        searchPlaceholder={searchPh}
        sortFieldOptions={[
          { value: 'title', label: 'Title' },
          { value: 'date', label: 'Date' },
          { value: 'modified', label: 'Modified' },
        ]}
        defaultSortField="title"
        columnPickerStorageKey={pickerKey}
        collectionQueryExtras={isCertificateList ? { embed: '1' } : undefined}
        columns={columns}
        useMockPlaceholder={useMock}
        mockPlaceholderRows={mockRows}
        emptyMessage="No items match your filters."
        emptyStateTitle="No items found"
        emptyStateDescription="Try another status or search term, or add a new item."
        emptyStateAction={
          isLessonList ? (
            <ButtonPrimary
              onClick={() => {
                setAddLessonError(null);
                setAddLessonOpen(true);
              }}
            >
              + Add lesson
            </ButtonPrimary>
          ) : (
            <LinkButtonPrimary href={newHref}>+ Add new</LinkButtonPrimary>
          )
        }
        skeletonHeaders={
          isCertificateList ? ['Preview', 'Title', 'Updated', 'Status', ''] : ['Title', 'Updated', 'Status', '']
        }
      />
    </AppShell>
  );
}
