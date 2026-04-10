import { useMemo, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { EntityListView, StatusBadge } from '../components/shared/list';
import { ButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { appViewHref } from '../lib/appUrl';
import { courseMetaString, coursePriceLabel, embeddedAuthorName } from '../lib/courseListMeta';
import { formatDisplaySlug } from '../lib/formatDisplaySlug';
import { embeddedTermNames } from '../lib/wpPostTerms';
import { formatPostDate } from '../lib/formatPostDate';
import type { NavItem, SikshyaReactConfig, WpPost } from '../types';

function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

function featuredThumbSrc(r: WpPost): string | null {
  const url = r._embedded?.['wp:featuredmedia']?.[0]?.source_url;
  return typeof url === 'string' && url.length > 0 ? url : null;
}

function excerptPlain(r: WpPost): string {
  const raw = r.excerpt?.rendered;
  if (!raw) {
    return '';
  }
  return stripTags(raw).replace(/\s+/g, ' ').trim();
}

const COURSE_CAT_TAX = 'sikshya_course_category';

export function CoursesPage(props: { config: SikshyaReactConfig; title: string; restBase: string }) {
  const { config, title, restBase } = props;
  const [createOpen, setCreateOpen] = useState(false);

  const columns: Column<WpPost>[] = useMemo(
    () => [
      {
        id: 'id',
        header: 'ID',
        sortKey: 'id',
        alwaysVisible: true,
        cellClassName: 'whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400',
        render: (r) => r.id,
      },
      {
        id: 'thumb',
        header: '',
        columnPickerLabel: 'Image',
        alwaysVisible: true,
        headerClassName: 'w-16',
        cellClassName: 'w-16',
        render: (r) => {
          const src = featuredThumbSrc(r);
          return (
            <div className="flex h-12 w-14 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800">
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
        id: 'title',
        header: 'Course',
        sortKey: 'title',
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
        sortKey: 'author',
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
        id: 'excerpt',
        header: 'Excerpt',
        defaultHidden: true,
        cellClassName: 'max-w-xs text-slate-600 dark:text-slate-400',
        render: (r) => {
          const t = excerptPlain(r);
          return t ? <span className="line-clamp-2">{t}</span> : '—';
        },
      },
      {
        id: 'date',
        header: 'Published',
        sortKey: 'date',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => formatPostDate(r.date),
      },
      {
        id: 'modified',
        header: 'Updated',
        sortKey: 'modified',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => formatPostDate(r.modified || r.date),
      },
      {
        id: 'status',
        header: 'Status',
        render: (r) => <StatusBadge status={r.status} />,
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
          { value: 'id', label: 'ID' },
          { value: 'author', label: 'Author' },
        ]}
        defaultSortField="title"
        columnPickerStorageKey="course"
        collectionQueryExtras={{ embed: '1' }}
        postRowActions={{
          buildLeadingItems: (r) => [
            {
              key: 'builder',
              label: 'Edit in builder',
              href: appViewHref(config, 'add-course', { course_id: String(r.id) }),
            },
          ],
        }}
        columns={columns}
        emptyMessage="No courses match your filters. Try clearing search or choosing another status."
        emptyStateTitle="No courses found"
        emptyStateDescription="Create a draft course to see it listed here."
        emptyStateAction={
          <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Add new course</ButtonPrimary>
        }
        skeletonHeaders={[
          'ID',
          '',
          'Course',
          'Categories',
          'Price',
          'Level',
          'Published',
          'Updated',
          'Status',
        ]}
      />
    </AppShell>
  );
}
