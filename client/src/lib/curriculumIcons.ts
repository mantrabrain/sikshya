/**
 * NavIcon keys aligned with PHP `sikshya_curriculum_outline_row_type_icon_html()` (`includes/template-functions.php`)
 * so Course Builder, lists, pickers, and Learn / single-course curricula share one visual language.
 */

export type CurriculumContentKind = 'lesson' | 'quiz' | 'assignment' | string;

/** Returns a `NavIcon` `name` for builder rows / pickers / lists. */
export function navIconForCurriculumRow(
  type: CurriculumContentKind,
  lessonTypeMeta?: string | null,
): string {
  const t = String(type || '').toLowerCase();
  if (t === 'lesson') {
    const lt = String(lessonTypeMeta || '').toLowerCase();
    if (lt === 'video') {
      return 'curriculumLessonVideo';
    }
    if (lt === 'audio') {
      return 'curriculumLessonAudio';
    }
    if (lt === 'live') {
      return 'curriculumLessonLive';
    }
    if (lt === 'scorm') {
      return 'curriculumLessonScorm';
    }
    if (lt === 'h5p') {
      return 'curriculumLessonH5p';
    }
    return 'plusDocument';
  }
  if (t === 'quiz') {
    return 'curriculumQuiz';
  }
  if (t === 'assignment') {
    return 'curriculumAssignment';
  }
  if (t === 'question') {
    return 'helpCircle';
  }
  return 'plusDocument';
}
