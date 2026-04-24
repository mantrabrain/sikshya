import { useMemo } from 'react';
import { AppShell } from '../components/AppShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { appViewHref } from '../lib/appUrl';
import { useAdminRouting } from '../lib/adminRouting';
import type { NavItem, SikshyaReactConfig } from '../types';
import { renderContentEditor } from './content-editors/editors';

/**
 * Where the editor should send the user when they click "back" / "all". For the
 * five lesson-like CPTs we route through the Content library hub with the
 * matching tab pre-selected; for certificates we use the Certificates hub.
 * `sidebarKey` mirrors the sidebar nav id so the rail stays correctly highlighted.
 */
const POST_TYPE_HOMES: Record<string, { view: string; tab?: string; sidebarKey: string }> = {
  sik_course: { view: 'courses', sidebarKey: 'courses' },
  sik_lesson: { view: 'content-library', tab: 'lessons', sidebarKey: 'content-library' },
  sik_quiz: { view: 'content-library', tab: 'quizzes', sidebarKey: 'content-library' },
  sik_assignment: { view: 'content-library', tab: 'assignments', sidebarKey: 'content-library' },
  sik_question: { view: 'content-library', tab: 'questions', sidebarKey: 'content-library' },
  sik_chapter: { view: 'content-library', tab: 'chapters', sidebarKey: 'content-library' },
  sikshya_certificate: { view: 'certificates-hub', tab: 'templates', sidebarKey: 'certificates-hub' },
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

  const home = POST_TYPE_HOMES[postType] || { view: 'dashboard', sidebarKey: 'dashboard' };
  const listView = home.sidebarKey;
  const entityLabel = TITLE_BY_POST_TYPE[postType] || 'Content';
  const backHref = appViewHref(config, home.view, home.tab ? { tab: home.tab } : {});
  const isCertificateEditor = postType === 'sikshya_certificate';

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

  if (isCertificateEditor) {
    return (
      <div className="min-h-screen bg-slate-100 dark:bg-slate-950">
        {renderContentEditor(postType, {
          config,
          postType,
          postId,
          backHref,
          entityLabel,
          onSavedNewId,
        })}
      </div>
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
