import { useCallback, useMemo, useRef, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { EntityListView, StatusBadge } from '../components/shared/list';
import { RowActionsMenu } from '../components/shared/list/RowActionsMenu';
import { ButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { appViewHref } from '../lib/appUrl';
import { courseMetaString, coursePriceLabel, embeddedAuthorName } from '../lib/courseListMeta';
import { formatDisplaySlug } from '../lib/formatDisplaySlug';
import { embeddedTermNames } from '../lib/wpPostTerms';
import { wpPostStatusRowActions } from '../lib/wpPostStatusRowActions';
import { formatPostDate } from '../lib/formatPostDate';
import type { NavItem, SikshyaReactConfig, WpPost } from '../types';

function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

const COURSE_CAT_TAX = 'sikshya_course_category';

export function CoursesPage(props: { config: SikshyaReactConfig; title: string; restBase: string }) {
  const { config, title, restBase } = props;
  const [createOpen, setCreateOpen] = useState(false);
  const { confirm } = useSikshyaDialog();
  const refreshListRef = useRef<(() => Promise<void>) | null>(null);

  const onListReady = useCallback((api: { refresh: () => Promise<void> }) => {
    refreshListRef.current = api.refresh;
  }, []);

  const columns: Column<WpPost>[] = useMemo(
    () => [
      {
        id: 'title',
        header: 'Course',
        render: (r) => (
          <div className="max-w-md">
            <a
              href={appViewHref(config, 'add-course', { course_id: String(r.id) })}
              className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
            >
              <span dangerouslySetInnerHTML={{ __html: r.title.rendered }} />
            </a>
            {r.slug ? (
              <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                {formatDisplaySlug(r.slug, r.status)}
              </div>
            ) : null}
          </div>
        ),
      },
      {
        id: 'categories',
        header: 'Categories',
        defaultHidden: false,
        cellClassName: 'max-w-[14rem] text-slate-600 dark:text-slate-400',
        render: (r) => {
          const names = embeddedTermNames(r, COURSE_CAT_TAX);
          return names.length ? names.join(', ') : '—';
        },
      },
      {
        id: 'author',
        header: 'Author',
        defaultHidden: true,
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => embeddedAuthorName(r),
      },
      {
        id: 'price',
        header: 'Price',
        cellClassName: 'whitespace-nowrap tabular-nums',
        render: (r) => coursePriceLabel(r),
      },
      {
        id: 'duration',
        header: 'Duration',
        defaultHidden: true,
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => courseMetaString(r, '_sikshya_course_duration', '_sikshya_duration'),
      },
      {
        id: 'level',
        header: 'Level',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => courseMetaString(r, '_sikshya_course_level', '_sikshya_difficulty'),
      },
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
          const label = stripTags(r.title.rendered) || 'Course';
          const refresh = () => refreshListRef.current?.() ?? Promise.resolve();
          const base: Parameters<typeof RowActionsMenu>[0]['items'] = [
            {
              key: 'builder',
              label: 'Edit in builder',
              href: appViewHref(config, 'add-course', { course_id: String(r.id) }),
            },
            ...(r.link && r.link !== '#'
              ? [
                  {
                    key: 'view',
                    label: 'View on site',
                    href: r.link,
                    external: true as const,
                  },
                ]
              : []),
          ];
          const statusItems = wpPostStatusRowActions(restBase, r, refresh, confirm);
          return <RowActionsMenu ariaLabel={`Actions for ${label}`} items={[...base, ...statusItems]} />;
        },
      },
    ],
    [config, restBase, confirm]
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
      subtitle="Live data from your site. Create a draft, then finish details in the builder."
      pageActions={
        <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Add new course</ButtonPrimary>
      }
    >
      <CreateCourseModal config={config} open={createOpen} onClose={() => setCreateOpen(false)} />
      <EntityListView
        restBase={restBase}
        searchPlaceholder="Search courses by title…"
        sortFieldOptions={[
          { value: 'title', label: 'Title' },
          { value: 'date', label: 'Published' },
          { value: 'modified', label: 'Last modified' },
        ]}
        defaultSortField="title"
        columnPickerStorageKey="course"
        collectionQueryExtras={{ embed: '1' }}
        onListReady={onListReady}
        columns={columns}
        emptyMessage="No courses match your filters. Try clearing search or choosing another status."
        emptyStateTitle="No courses found"
        emptyStateDescription="Create a draft course to see it listed here."
        emptyStateAction={
          <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Add new course</ButtonPrimary>
        }
        skeletonHeaders={['Course', 'Categories', 'Price', 'Level', 'Updated', 'Status', '']}
      />
    </AppShell>
  );
}
