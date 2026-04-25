import { useMemo, useState } from 'react';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { EntityListView, StatusBadge } from '../components/shared/list';
import { ButtonPrimary, LinkButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { appViewHref } from '../lib/appUrl';
import { useAdminRouting } from '../lib/adminRouting';
import { formatDisplaySlug } from '../lib/formatDisplaySlug';
import { formatPostDate } from '../lib/formatPostDate';
import type { SikshyaReactConfig, WpPost } from '../types';
import { getWpApi } from '../api';
import { NavIcon } from '../components/NavIcon';
import {
  AddContentTypePickerModal,
  type ContentPickerType,
  defaultTitleFor,
} from '../components/shared/AddContentTypePickerModal';
import { CreateCertificateModal } from '../components/shared/CreateCertificateModal';

function certificatePreviewSrc(r: WpPost): string | null {
  const url = r._embedded?.['wp:featuredmedia']?.[0]?.source_url;
  return typeof url === 'string' && url.length > 0 ? url : null;
}

/** Map a picker selection to the WordPress post type the new item lives under. */
function postTypeForPickerType(t: ContentPickerType): 'sik_lesson' | 'sik_quiz' | 'sik_assignment' {
  if (t === 'quiz') return 'sik_quiz';
  if (t === 'assignment') return 'sik_assignment';
  return 'sik_lesson';
}

/** Lesson sub-kind written into `_sikshya_lesson_type` meta (empty for non-lessons). */
function lessonSubtypeForPickerType(t: ContentPickerType): '' | 'text' | 'video' | 'live' | 'scorm' | 'h5p' {
  switch (t) {
    case 'lesson_text':
      return 'text';
    case 'lesson_video':
      return 'video';
    case 'lesson_live':
      return 'live';
    case 'lesson_scorm':
      return 'scorm';
    case 'lesson_h5p':
      return 'h5p';
    default:
      return '';
  }
}

export function WpEntityListPage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle: string;
  restBase: string;
  embedded?: boolean;
}) {
  const { config, title, subtitle, restBase, embedded } = props;
  const { navigateHref } = useAdminRouting();

  const newHref = appViewHref(config, 'edit-content', { post_type: restBase });
  const isLessonList = restBase === 'sik_lesson';
  const isCertificateList = restBase === 'sikshya_certificate';
  const isQuizList = restBase === 'sik_quiz';
  const isQuestionList = restBase === 'sik_question';
  const isChapterList = restBase === 'sik_chapter';
  const isAssignmentList = restBase === 'sik_assignment';
  const [addLessonOpen, setAddLessonOpen] = useState(false);
  const [addLessonBusy, setAddLessonBusy] = useState(false);
  const [addLessonError, setAddLessonError] = useState<unknown>(null);
  const [addLessonType, setAddLessonType] = useState<ContentPickerType>('lesson_text');
  const [addLessonTitle, setAddLessonTitle] = useState<string>(defaultTitleFor('lesson_text'));
  const [addCertificateOpen, setAddCertificateOpen] = useState(false);

  const openAddLesson = () => {
    setAddLessonError(null);
    setAddLessonType('lesson_text');
    setAddLessonTitle(defaultTitleFor('lesson_text'));
    setAddLessonOpen(true);
  };

  const submitNewLesson = () => {
    const name = addLessonTitle.trim();
    if (!name) {
      return;
    }
    const targetPostType = postTypeForPickerType(addLessonType);
    const lessonKind = lessonSubtypeForPickerType(addLessonType);
    setAddLessonBusy(true);
    setAddLessonError(null);
    void getWpApi()
      .post<{ id: number }>(`/${targetPostType}`, {
        title: name,
        status: 'draft',
        ...(lessonKind ? { meta: { _sikshya_lesson_type: lessonKind } } : null),
      })
      .then((created) => {
        if (!created?.id) {
          throw new Error('Could not create item.');
        }
        navigateHref(
          appViewHref(config, 'edit-content', {
            post_type: targetPostType,
            post_id: String(created.id),
          })
        );
      })
      .catch((e) => {
        setAddLessonError(e);
      })
      .finally(() => {
        setAddLessonBusy(false);
      });
  };

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
        sortKey: 'title',
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
                <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                  {formatDisplaySlug(r.slug, r.status)}
                </div>
              ) : null}
            </div>
          );
        },
      };

      const courseLink = (cid: number) =>
        cid > 0 ? (
          <a
            href={appViewHref(config, 'add-course', { course_id: String(cid) })}
            className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
          >
            #{cid}
          </a>
        ) : (
          '—'
        );

      const idCol: Column<WpPost> = {
        id: 'id',
        header: 'ID',
        sortKey: 'id',
        alwaysVisible: true,
        cellClassName: 'whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400',
        render: (r) => r.id,
      };

      const dateCol: Column<WpPost> = {
        id: 'date',
        header: 'Published',
        sortKey: 'date',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => formatPostDate(r.date),
      };

      const detailCols: Column<WpPost>[] = [];
      if (isLessonList) {
        detailCols.push(
          {
            id: 'course',
            header: 'Course',
            cellClassName: 'whitespace-nowrap',
            render: (r) => {
              const m = r.meta as Record<string, unknown> | undefined;
              return courseLink(Number(m?._sikshya_lesson_course ?? 0));
            },
          },
          {
            id: 'lesson_type',
            header: 'Type',
            cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
            render: (r) => {
              const t = String((r.meta as Record<string, unknown> | undefined)?._sikshya_lesson_type || '');
              return t || '—';
            },
          },
          {
            id: 'duration',
            header: 'Duration',
            defaultHidden: true,
            cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
            render: (r) => String((r.meta as Record<string, unknown> | undefined)?._sikshya_lesson_duration || '—'),
          },
          {
            id: 'video_url',
            header: 'Video URL',
            defaultHidden: true,
            cellClassName: 'max-w-[14rem]',
            render: (r) => {
              const u = String((r.meta as Record<string, unknown> | undefined)?._sikshya_lesson_video_url || '');
              return u ? (
                <a
                  href={u}
                  className="truncate text-brand-600 hover:underline dark:text-brand-400"
                  target="_blank"
                  rel="noreferrer noopener"
                >
                  {u}
                </a>
              ) : (
                '—'
              );
            },
          }
        );
      } else if (isQuizList) {
        detailCols.push(
          {
            id: 'course',
            header: 'Course',
            cellClassName: 'whitespace-nowrap',
            render: (r) => {
              const m = r.meta as Record<string, unknown> | undefined;
              return courseLink(Number(m?._sikshya_quiz_course ?? 0));
            },
          },
          {
            id: 'passing',
            header: 'Pass %',
            cellClassName: 'whitespace-nowrap tabular-nums',
            render: (r) => {
              const v = (r.meta as Record<string, unknown> | undefined)?._sikshya_quiz_passing_score;
              return v != null && String(v) !== '' ? String(v) : '—';
            },
          },
          {
            id: 'time_limit',
            header: 'Time (min)',
            defaultHidden: true,
            cellClassName: 'whitespace-nowrap tabular-nums',
            render: (r) => {
              const v = (r.meta as Record<string, unknown> | undefined)?._sikshya_quiz_time_limit;
              return v != null && String(v) !== '' && String(v) !== '0' ? String(v) : '—';
            },
          }
        );
      } else if (isQuestionList) {
        detailCols.push(
          {
            id: 'qtype',
            header: 'Question type',
            cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
            render: (r) => String((r.meta as Record<string, unknown> | undefined)?._sikshya_question_type || '—'),
          },
          {
            id: 'points',
            header: 'Points',
            cellClassName: 'whitespace-nowrap tabular-nums',
            render: (r) => {
              const v = (r.meta as Record<string, unknown> | undefined)?._sikshya_question_points;
              return v != null && String(v) !== '' ? String(v) : '—';
            },
          }
        );
      } else if (isChapterList) {
        detailCols.push(
          {
            id: 'course',
            header: 'Course',
            cellClassName: 'whitespace-nowrap',
            render: (r) => {
              const m = r.meta as Record<string, unknown> | undefined;
              return courseLink(Number(m?._sikshya_chapter_course_id ?? 0));
            },
          },
          {
            id: 'order',
            header: 'Order',
            cellClassName: 'whitespace-nowrap tabular-nums',
            render: (r) => {
              const v = (r.meta as Record<string, unknown> | undefined)?._sikshya_chapter_order;
              return v != null && String(v) !== '' ? String(v) : '—';
            },
          }
        );
      } else if (isAssignmentList) {
        detailCols.push(
          {
            id: 'course',
            header: 'Course',
            cellClassName: 'whitespace-nowrap',
            render: (r) => {
              const m = r.meta as Record<string, unknown> | undefined;
              return courseLink(Number(m?._sikshya_assignment_course ?? 0));
            },
          },
          {
            id: 'assign_pts',
            header: 'Points',
            cellClassName: 'whitespace-nowrap tabular-nums',
            render: (r) => {
              const v = (r.meta as Record<string, unknown> | undefined)?._sikshya_assignment_points;
              return v != null && String(v) !== '' ? String(v) : '—';
            },
          },
          {
            id: 'assign_type',
            header: 'Type',
            cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
            render: (r) => String((r.meta as Record<string, unknown> | undefined)?._sikshya_assignment_type || '—'),
          }
        );
      }

      const rest: Column<WpPost>[] = [
        ...detailCols,
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
      ];

      return isCertificateList
        ? [idCol, previewCol, titleCol, dateCol, ...rest]
        : [idCol, titleCol, dateCol, ...rest];
    },
    [config, restBase, isLessonList, isCertificateList, isQuizList, isQuestionList, isChapterList, isAssignmentList]
  );

  const postRowActions = useMemo(
    () => ({
      buildLeadingItems: (r: WpPost) => {
        const items = [
          {
            key: 'edit',
            label: 'Edit',
            href: appViewHref(config, 'edit-content', { post_type: restBase, post_id: String(r.id) }),
          },
        ];

        if (isCertificateList) {
          const href = String(r.sikshya_certificate_preview_url || '').trim();
          if (href) {
            items.push({ key: 'preview', label: 'Preview', href, external: true as const });
          }
        }

        return items;
      },
      // Certificate templates should “View” as a public hash link (preview hash stored in meta).
      buildViewHref: (r: WpPost) => {
        if (!isCertificateList) {
          return r.link;
        }
        const href = String(r.sikshya_certificate_preview_url || '').trim();
        return href || null;
      },
    }),
    [config, restBase, isCertificateList]
  );

  const searchPh = `Search ${title.toLowerCase()}…`;
  const pickerKey = `post_${restBase.replace(/[^a-z0-9_-]/gi, '_')}`;
  const openAddCertificate = () => setAddCertificateOpen(true);

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={subtitle}
      pageActions={
        isLessonList ? (
          <ButtonPrimary onClick={openAddLesson}>+ Add lesson</ButtonPrimary>
        ) : isCertificateList ? (
          <ButtonPrimary onClick={openAddCertificate}>+ Add new certificate</ButtonPrimary>
        ) : (
          <LinkButtonPrimary href={newHref}>+ Add new</LinkButtonPrimary>
        )
      }
    >
      {isCertificateList ? (
        <CreateCertificateModal config={config} open={addCertificateOpen} onClose={() => setAddCertificateOpen(false)} />
      ) : null}
      {isLessonList ? (
        <AddContentTypePickerModal
          open={addLessonOpen}
          heading="Add a lesson, quiz, or assignment"
          description="Pick a type, give it a clear name, then open it to add the actual teaching material. Quiz questions are created inside the quiz editor after you add the quiz here."
          contentType={addLessonType}
          onContentTypeChange={(t) => {
            setAddLessonType(t);
            setAddLessonTitle(defaultTitleFor(t));
          }}
          title={addLessonTitle}
          onTitleChange={setAddLessonTitle}
          onClose={() => {
            if (!addLessonBusy) {
              setAddLessonOpen(false);
            }
          }}
          onSubmit={submitNewLesson}
          busy={addLessonBusy}
          error={addLessonError}
        />
      ) : null}
      <EntityListView
        restBase={restBase}
        searchPlaceholder={searchPh}
        sortFieldOptions={[
          { value: 'title', label: 'Title' },
          { value: 'date', label: 'Published' },
          { value: 'modified', label: 'Modified' },
          { value: 'id', label: 'ID' },
        ]}
        defaultSortField="title"
        columnPickerStorageKey={pickerKey}
        collectionQueryExtras={{ embed: '1' }}
        postRowActions={postRowActions}
        columns={columns}
        emptyMessage="No items match your filters."
        emptyStateTitle="No items found"
        emptyStateDescription="Try another status or search term, or add a new item."
        emptyStateAction={
          isLessonList ? (
            <ButtonPrimary onClick={openAddLesson}>+ Add lesson</ButtonPrimary>
          ) : isCertificateList ? (
            <ButtonPrimary onClick={openAddCertificate}>+ Add new certificate</ButtonPrimary>
          ) : (
            <LinkButtonPrimary href={newHref}>+ Add new</LinkButtonPrimary>
          )
        }
        skeletonHeaders={
          isCertificateList
            ? ['ID', 'Preview', 'Title', 'Published', 'Updated', 'Status']
            : isLessonList
              ? ['ID', 'Title', 'Published', 'Course', 'Type', 'Updated', 'Status']
              : isQuizList
                ? ['ID', 'Title', 'Published', 'Course', 'Pass %', 'Updated', 'Status']
                : ['ID', 'Title', 'Published', 'Updated', 'Status']
        }
      />
    </EmbeddableShell>
  );
}
