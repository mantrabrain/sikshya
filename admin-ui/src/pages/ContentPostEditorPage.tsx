import { useMemo } from 'react';
import { AppShell } from '../components/AppShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { appViewHref } from '../lib/appUrl';
import { useAdminRouting } from '../lib/adminRouting';
import type { NavItem, SikshyaReactConfig } from '../types';
import { renderContentEditor } from './content-editors/editors';

const LIST_VIEW_BY_POST_TYPE: Record<string, string> = {
  sik_course: 'courses',
  sik_lesson: 'lessons',
  sik_quiz: 'quizzes',
  sik_assignment: 'assignments',
  sik_question: 'questions',
  sik_chapter: 'chapters',
  sikshya_certificate: 'certificates',
};

const TITLE_BY_POST_TYPE: Record<string, string> = {
  sik_course: 'Course',
  sik_lesson: 'Lesson',
  sik_quiz: 'Quiz',
  sik_assignment: 'Assignment',
  sik_question: 'Question',
  sik_chapter: 'Chapter',
  sikshya_certificate: 'Certificate',
};

export function ContentPostEditorPage(props: { config: SikshyaReactConfig; shellTitle: string }) {
  const { config, shellTitle } = props;
  const { navigateHref } = useAdminRouting();
  const q = config.query || {};
  const postType = (q.post_type || '').trim();
  const postId = Number(q.post_id || q.id || 0) || 0;
  const isNew = postId <= 0;

  const listView = LIST_VIEW_BY_POST_TYPE[postType] || 'dashboard';
  const entityLabel = TITLE_BY_POST_TYPE[postType] || 'Content';
  const backHref = appViewHref(config, listView);

  const onSavedNewId = (newId: number) => {
    navigateHref(
      appViewHref(config, 'edit-content', {
        post_type: postType,
        post_id: String(newId),
      })
    );
  };

  const subtitle = useMemo(() => {
    if (!postType) {
      return 'Missing content type';
    }
    return isNew ? `New ${entityLabel}` : `Edit ${entityLabel} #${postId}`;
  }, [postType, entityLabel, isNew, postId]);

  if (!postType) {
    return (
      <AppShell
        page={config.page}
        sidebarActivePage="dashboard"
        version={config.version}
        navigation={config.navigation as NavItem[]}
        adminUrl={config.adminUrl}
        userName={config.user.name}
        userAvatarUrl={config.user.avatarUrl}
        title={shellTitle}
        subtitle="Missing content type"
        pageActions={null}
      >
        <ApiErrorPanel
          error={new Error('No post_type in the URL. Open this screen from a list link.')}
          title="Cannot load editor"
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      page={config.page}
      sidebarActivePage={listView}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={shellTitle}
      subtitle={subtitle}
      pageActions={null}
    >
      <div className="w-full min-w-0">
        {renderContentEditor(postType, {
          config,
          postType,
          postId,
          backHref,
          entityLabel,
          onSavedNewId,
        })}
      </div>
    </AppShell>
  );
}
