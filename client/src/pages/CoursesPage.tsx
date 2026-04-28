import { useEffect, useMemo, useState } from 'react';
import { CreateCourseModal } from '../components/shared/CreateCourseModal';
import { EntityListView, StatusBadge } from '../components/shared/list';
import { ButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { NavIcon } from '../components/NavIcon';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { appViewHref } from '../lib/appUrl';
import { isFeatureEnabled } from '../lib/licensing';
import { courseMetaString, coursePriceLabel, embeddedAuthorName } from '../lib/courseListMeta';
import { formatDisplaySlug } from '../lib/formatDisplaySlug';
import { embeddedTermNames } from '../lib/wpPostTerms';
import { formatPostDate } from '../lib/formatPostDate';
import { term, termLower } from '../lib/terminology';
import type { NavItem, SikshyaReactConfig, WpPost } from '../types';

function isBundleRow(r: WpPost): boolean {
  const m = r.meta as Record<string, unknown> | undefined;
  const v =
    (m && (m._sikshya_course_type ?? (m as Record<string, unknown>).sikshya_course_type)) ??
    r.sikshya_course_type ??
    // Defensive: some WP setups/plugins flatten meta unexpectedly.
    (r as unknown as { _sikshya_course_type?: unknown })._sikshya_course_type;
  return String(v || '') === 'bundle';
}

function isSubscriptionRow(r: WpPost): boolean {
  const m = r.meta as Record<string, unknown> | undefined;
  const v =
    (m && (m._sikshya_course_type ?? (m as Record<string, unknown>).sikshya_course_type)) ??
    r.sikshya_course_type ??
    (r as unknown as { _sikshya_course_type?: unknown })._sikshya_course_type;
  return String(v || '') === 'subscription';
}

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

export function CoursesPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string; restBase: string }) {
  const { config, title, restBase } = props;
  const [createOpen, setCreateOpen] = useState(false);
  const [typeFilter, setTypeFilter] = useState<'any' | 'regular' | 'subscription' | 'bundle'>('any');
  const showCourseStaff = isFeatureEnabled(config, 'multi_instructor');
  const course = term(config, 'course');
  const courses = term(config, 'courses');
  const courseLower = termLower(config, 'course');
  const coursesLower = termLower(config, 'courses');

  useEffect(() => {
    try {
      const sp = new URLSearchParams(window.location.search);
      if (sp.get('create') === '1' || sp.get('create') === 'true') {
        setCreateOpen(true);
      }
    } catch {
      // no-op (SSR / malformed URL)
    }
  }, []);

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
        header: course,
        sortKey: 'title',
        render: (r) => (
          <div className="max-w-md">
            <div className="flex min-w-0 items-center gap-2">
              <a
                href={appViewHref(config, 'add-course', {
                  course_id: String(r.id),
                  ...(isBundleRow(r) ? { force_bundle_ui: '1' } : null),
                })}
                className="min-w-0 truncate font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
              >
                <span dangerouslySetInnerHTML={{ __html: r.title.rendered }} />
              </a>
              {isBundleRow(r) ? (
                <span className="inline-flex h-5 shrink-0 items-center gap-1 rounded-full border border-violet-300 bg-violet-100 px-2 text-[11px] font-semibold leading-none text-violet-900 dark:border-violet-600/80 dark:bg-violet-950/55 dark:text-violet-100">
                  <NavIcon name="bundleBox" className="h-3.5 w-3.5 text-violet-800 dark:text-violet-100" />
                  Bundle
                </span>
              ) : isSubscriptionRow(r) ? (
                <span className="inline-flex h-5 shrink-0 items-center gap-1 rounded-full border border-sky-300 bg-sky-100 px-2 text-[11px] font-semibold leading-none text-sky-900 dark:border-sky-600/80 dark:bg-sky-950/55 dark:text-sky-100">
                  <NavIcon name="plusCircle" className="h-3.5 w-3.5 text-sky-800 dark:text-sky-100" />
                  Subscription
                </span>
              ) : null}
            </div>
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
        render: (r) => courseMetaString(r, '_sikshya_course_duration', '_sikshya_duration', 'sikshya_course_duration'),
      },
      {
        id: 'level',
        header: 'Level',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => courseMetaString(r, '_sikshya_course_level', '_sikshya_difficulty', 'sikshya_course_level'),
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
    [config, course]
  );

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle="Live data from your site. Create a draft, then finish details in the builder."
      pageActions={
        <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Add new {courseLower}</ButtonPrimary>
      }
    >
      <CreateCourseModal config={config} open={createOpen} onClose={() => setCreateOpen(false)} />
      <EntityListView
        restBase={restBase}
        searchPlaceholder={`Search ${coursesLower} by title…`}
        sortFieldOptions={[
          { value: 'title', label: 'Title' },
          { value: 'date', label: 'Published' },
          { value: 'modified', label: 'Last modified' },
          { value: 'id', label: 'ID' },
          { value: 'author', label: 'Author' },
        ]}
        defaultSortField="id"
        columnPickerStorageKey="course"
        collectionQueryExtras={{
          embed: '1',
          // Ensure bundle type meta is present in the collection response so the badge renders reliably.
          fields:
            'id,title,slug,status,link,meta,sikshya_course_type,sikshya_course_price,sikshya_course_duration,sikshya_course_level,sikshya_preview_link,_embedded,date,modified,excerpt,author',
          ...(typeFilter !== 'any' ? { sikshya_course_type: typeFilter } : null),
        }}
        toolbarTrailing={
          <div className="flex flex-wrap items-center gap-2">
            <label className="sr-only" htmlFor="sikshya-course-type-filter">
              {course} type
            </label>
            <select
              id="sikshya-course-type-filter"
              value={typeFilter}
              onChange={(e) =>
                setTypeFilter(e.target.value as 'any' | 'regular' | 'subscription' | 'bundle')
              }
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
              title={`Filter by ${courseLower} type`}
            >
              <option value="any">All types</option>
              <option value="regular">Regular {coursesLower}</option>
              <option value="subscription">Subscription {coursesLower}</option>
              <option value="bundle">Bundle {coursesLower}</option>
            </select>
          </div>
        }
        postRowActions={{
          buildLeadingItems: (r) => {
            const items = [
              {
                key: 'builder',
                label: isBundleRow(r) ? 'Edit bundle' : `Edit ${courseLower} in builder`,
                href: appViewHref(config, 'add-course', {
                  course_id: String(r.id),
                  ...(isBundleRow(r) ? { force_bundle_ui: '1' } : null),
                }),
              },
            ];
            if (showCourseStaff) {
              items.push({
                key: 'course-team',
                label: 'Manage staff',
                href: appViewHref(config, 'course-team', { course_id: String(r.id) }),
              });
            }
            return items;
          },
        }}
        columns={columns}
        emptyMessage={`No ${coursesLower} match your filters. Try clearing search or choosing another status.`}
        emptyStateTitle={`No ${coursesLower} found`}
        emptyStateDescription={`Create a draft ${courseLower} to see it listed here.`}
        emptyStateAction={
          <ButtonPrimary onClick={() => setCreateOpen(true)}>+ Add new {courseLower}</ButtonPrimary>
        }
        skeletonHeaders={[
          'ID',
          '',
          course,
          'Categories',
          'Price',
          'Level',
          'Published',
          'Updated',
          'Status',
        ]}
      />
    </EmbeddableShell>
  );
}
