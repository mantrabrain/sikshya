import type { SikshyaReactConfig } from '../types';

type TermKey =
  | 'course'
  | 'courses'
  | 'lesson'
  | 'lessons'
  | 'quiz'
  | 'quizzes'
  | 'assignment'
  | 'assignments'
  | 'chapter'
  | 'chapters'
  | 'student'
  | 'students'
  | 'instructor'
  | 'instructors'
  | 'enrollment'
  | 'enrollments';

const DEFAULTS: Record<TermKey, string> = {
  course: 'Course',
  courses: 'Courses',
  lesson: 'Lesson',
  lessons: 'Lessons',
  quiz: 'Quiz',
  quizzes: 'Quizzes',
  assignment: 'Assignment',
  assignments: 'Assignments',
  chapter: 'Chapter',
  chapters: 'Chapters',
  student: 'Student',
  students: 'Students',
  instructor: 'Instructor',
  instructors: 'Instructors',
  enrollment: 'Enrollment',
  enrollments: 'Enrollments',
};

export function term(config: SikshyaReactConfig, key: TermKey): string {
  const v = config.terminology?.[key];
  if (typeof v === 'string' && v.trim()) return v.trim();
  return DEFAULTS[key];
}

export function termLower(config: SikshyaReactConfig, key: TermKey): string {
  return term(config, key).toLowerCase();
}

