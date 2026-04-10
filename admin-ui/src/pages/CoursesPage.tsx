import { useMemo, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { EntityListView, StatusBadge } from '../components/shared/list';
import { RowActionsMenu } from '../components/shared/list/RowActionsMenu';
import { ButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { useEntityListMockEnabled } from '../lib/entityListMock';
import { getMockRowsForRestBase } from '../lib/mockWpPosts';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import type { NavItem, SikshyaReactConfig, WpPost } from '../types';

function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

export function CoursesPage(props: { config: SikshyaReactConfig; title: string; restBase: string }) {
  const { config, title, restBase } = props;
  const [createOpen, setCreateOpen] = useState(false);

  const useMockPlaceholder = useEntityListMockEnabled(config);
  const mockRows = getMockRowsForRestBase('sik_course');

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
              <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{r.slug}</div>
            ) : null}
          </div>
        ),
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
          const items = [
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
          return <RowActionsMenu ariaLabel={`Actions for ${label}`} items={items} />;
        },
      },
    ],
    [config]
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
        useMockPlaceholder={useMockPlaceholder}
        mockPlaceholderRows={mockRows}
        columns={columns}
        emptyMessage="No courses match your filters. Try clearing search or choosing another status."
        emptyStateTitle="No courses found"
        emptyStateDescription="Create a draft course to see it listed here."
        emptyStateAction={
          <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Add new course</ButtonPrimary>
        }
        skeletonHeaders={['Course', 'Updated', 'Status', '']}
      />
    </AppShell>
  );
}
