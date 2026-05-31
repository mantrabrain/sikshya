import type { APIRequestContext, BrowserContext, Page } from '@playwright/test';
import { expect } from '@playwright/test';

export const STUDENT_ROLE = 'sikshya_student';
export const INSTRUCTOR_ROLE = 'sikshya_instructor';

export interface CourseInput {
  title?: string;
  content?: string;
  price?: number;
  type?: 'free' | 'paid';
  authorId?: number;
}

export interface StudentInput {
  username: string;
  email: string;
  password: string;
  role?: string;
}

export const slug = (prefix: string) =>
  `${prefix}-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e6).toString(36)}`;

/**
 * Pulls wpApiSettings.nonce out of any wp-admin page so we can call admin REST
 * routes that gate on a CookieAuth nonce.
 */
export async function getAdminNonce(page: Page): Promise<string> {
  await page.goto('/wp-admin/index.php', { waitUntil: 'domcontentloaded' });
  const nonce = await page.evaluate(() => {
    const w = window as unknown as {
      wpApiSettings?: { nonce?: string };
      sikshyaAdminConfig?: { nonce?: string; restNonce?: string };
    };
    return (
      w.wpApiSettings?.nonce ||
      w.sikshyaAdminConfig?.nonce ||
      w.sikshyaAdminConfig?.restNonce ||
      ''
    );
  });
  if (!nonce) throw new Error('No wpApiSettings.nonce found on /wp-admin/index.php');
  return nonce;
}

/**
 * Create a course via wp/v2/sik_course REST endpoint using cookie auth + nonce.
 */
export async function createCourseViaRest(
  page: Page,
  request: APIRequestContext,
  input: CourseInput = {},
): Promise<{ id: number; title: string; link: string }> {
  const nonce = await getAdminNonce(page);
  const title = input.title ?? `E2E Course ${slug('c')}`;
  const courseData: Record<string, unknown> = {
    title,
    status: 'publish',
    content: input.content ?? '<p>Auto-generated E2E course.</p>',
  };
  if (input.authorId !== undefined && Number(input.authorId) > 0) {
    courseData.author = Number(input.authorId);
  }
  const res = await request.post('/wp-json/wp/v2/sik_course', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: courseData,
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok()) {
    throw new Error(`createCourseViaRest failed ${res.status()}: ${JSON.stringify(body)}`);
  }
  const id = Number(body.id);
  // Combine type + price into one PATCH so neither overwrites the other; both
  // meta keys are registered with show_in_rest so we can drive them directly.
  // `CourseService::getCoursePrice()` reads from `_sikshya_price` (not
  // `_sikshya_course_price` — the latter is a legacy alias). Write both for safety.
  // `sikshya_get_course_pricing` will set effective=null when type==='free' (line
  // 1174 in includes/template-functions.php) — so we only write the price when
  // type !== 'free'.
  const metaPatch: Record<string, string> = {};
  if (input.type !== undefined) {
    metaPatch._sikshya_course_type = input.type;
  }
  if (input.price !== undefined && input.type !== 'free') {
    metaPatch._sikshya_price = String(input.price);
    metaPatch._sikshya_course_price = String(input.price);
  }
  if (Object.keys(metaPatch).length > 0) {
    await request.post(`/wp-json/wp/v2/sik_course/${id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { meta: metaPatch },
    });
  }
  // Also call the Sikshya admin set-type endpoint for compat — it triggers any
  // type-change side effects (e.g. bundle defaults).
  if (input.type !== undefined) {
    await request.post('/wp-json/sikshya/v1/course-builder/set-type', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { course_id: id, course_type: input.type },
    });
  }
  return { id, title, link: body.link ?? '' };
}

/**
 * Create a lesson via wp/v2/sik_lesson REST endpoint.
 *
 * When `linkToCurriculum` (default true) is set, this also creates a chapter
 * under the course and links the lesson into the curriculum so it shows up in
 * `LearnerCurriculumHelper::lessonIdsForCourse()` — required for
 * `/sikshya/v1/me/lesson-complete` to accept it.
 */
export async function createLessonViaRest(
  page: Page,
  request: APIRequestContext,
  courseId: number,
  title?: string,
  linkToCurriculum: boolean = true,
): Promise<{ id: number; title: string; chapterId?: number }> {
  const nonce = await getAdminNonce(page);
  const lessonTitle = title ?? `E2E Lesson ${slug('l')}`;
  const res = await request.post('/wp-json/wp/v2/sik_lesson', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: {
      title: lessonTitle,
      status: 'publish',
      content: '<p>Auto-generated E2E lesson.</p>',
      meta: { _sikshya_course_id: courseId },
    },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok()) {
    throw new Error(`createLessonViaRest failed ${res.status()}: ${JSON.stringify(body)}`);
  }
  const lessonId = Number(body.id);

  if (!linkToCurriculum) {
    return { id: lessonId, title: lessonTitle, link: (body.link as string) ?? '' };
  }

  // 1) Create a chapter for the course.
  const chapterRes = await request.post('/wp-json/sikshya/v1/curriculum/chapters', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { course_id: courseId, title: `E2E Chapter ${slug('ch')}` },
  });
  const chapterBody = await chapterRes.json().catch(() => ({}));
  if (!chapterRes.ok() || chapterBody?.success === false) {
    throw new Error(`createChapter failed ${chapterRes.status()}: ${JSON.stringify(chapterBody)}`);
  }
  const chapterId = Number(chapterBody?.data?.chapter_id ?? 0);

  // 2) Link the lesson into the chapter (which also pushes the chapter into
  //    the course's _sikshya_chapters meta).
  const linkRes = await request.post('/wp-json/sikshya/v1/curriculum/content/link', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { content_id: lessonId, chapter_id: chapterId },
  });
  const linkBody = await linkRes.json().catch(() => ({}));
  if (!linkRes.ok() || linkBody?.success === false) {
    throw new Error(`linkContent failed ${linkRes.status()}: ${JSON.stringify(linkBody)}`);
  }

  return { id: lessonId, title: lessonTitle, chapterId, link: (body.link as string) ?? '' };
}

/**
 * Create a quiz via wp/v2/sik_quiz and link it into the curriculum.
 * Mirrors createLessonViaRest so the quiz shows up in
 * `LearnerCurriculumHelper::quizIdsForCourse()`.
 */
export async function createQuizViaRest(
  page: Page,
  request: APIRequestContext,
  courseId: number,
  title?: string,
): Promise<{ id: number; title: string; chapterId: number; link: string }> {
  const nonce = await getAdminNonce(page);
  const quizTitle = title ?? `E2E Quiz ${slug('q')}`;
  const res = await request.post('/wp-json/wp/v2/sik_quiz', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: {
      title: quizTitle,
      status: 'publish',
      content: '<p>Auto-generated E2E quiz.</p>',
    },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok()) {
    throw new Error(`createQuizViaRest failed ${res.status()}: ${JSON.stringify(body)}`);
  }
  const quizId = Number(body.id);

  const chapterRes = await request.post('/wp-json/sikshya/v1/curriculum/chapters', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { course_id: courseId, title: `E2E Quiz Chapter ${slug('ch')}` },
  });
  const chapterBody = await chapterRes.json().catch(() => ({}));
  if (!chapterRes.ok() || chapterBody?.success === false) {
    throw new Error(`createChapter (quiz) failed ${chapterRes.status()}: ${JSON.stringify(chapterBody)}`);
  }
  const chapterId = Number(chapterBody?.data?.chapter_id ?? 0);

  const linkRes = await request.post('/wp-json/sikshya/v1/curriculum/content/link', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { content_id: quizId, chapter_id: chapterId },
  });
  const linkBody = await linkRes.json().catch(() => ({}));
  if (!linkRes.ok() || linkBody?.success === false) {
    throw new Error(`linkContent (quiz) failed ${linkRes.status()}: ${JSON.stringify(linkBody)}`);
  }

  return { id: quizId, title: quizTitle, chapterId, link: (body.link as string) ?? '' };
}

export type QuestionType =
  | 'multiple_choice'
  | 'true_false'
  | 'multiple_response'
  | 'short_answer'
  | 'fill_blank'
  | 'essay'
  | 'ordering'
  | 'matching';

export interface QuestionInput {
  title?: string;
  type?: QuestionType;
  /** Options for choice/response. For true_false leave empty or pass ['True','False']. Unused for short_answer/essay. */
  options?: string[];
  /**
   * For multiple_choice / true_false: option index (number).
   * For multiple_response: array of indices.
   * For short_answer / fill_blank: the correct string (or pipe-separated alternatives, e.g. "paris|Paris|PARIS").
   * For essay: ignored (essay always grades as 0; manual review required).
   * For ordering: canonical order of indices (e.g. [0,1,2] = original options order).
   * For matching: pre-encoded JSON string of `{"matching":{"left":[…], "right":[…], "map":[…]}}`.
   */
  correct: number | number[] | string;
  points?: number;
}

/**
 * Create a question with arbitrary type and return its ID. Meta keys are
 * registered with `show_in_rest` in PostTypeManager so we can set them via
 * the `meta` field on wp/v2/sik_question.
 *
 * Correct-answer encoding (mirrors `QuizRoutes::evaluateAnswer`):
 *   - `multiple_choice` / `true_false`: stored as the option index as a string
 *   - `multiple_response`: stored as a JSON-encoded array of indices
 */
export async function createQuestionViaRest(
  page: Page,
  request: APIRequestContext,
  opts: QuestionInput,
): Promise<{ id: number; title: string; type: QuestionType }> {
  const nonce = await getAdminNonce(page);
  const type: QuestionType = opts.type ?? 'multiple_choice';
  const title = opts.title ?? `E2E Question ${slug('Q')}`;
  const options = opts.options ?? (type === 'true_false' ? ['True', 'False'] : ['A', 'B']);

  let correctMeta: string;
  if (type === 'multiple_response' || type === 'ordering') {
    if (!Array.isArray(opts.correct)) {
      throw new Error(`${type} requires correct as number[]`);
    }
    correctMeta = JSON.stringify(opts.correct.map((n) => Number(n)));
  } else if (type === 'short_answer' || type === 'fill_blank' || type === 'matching') {
    // matching expects a pre-encoded JSON string of {matching:{left,right,map}}
    if (typeof opts.correct !== 'string') {
      throw new Error(`${type} requires correct as a string`);
    }
    correctMeta = opts.correct;
  } else if (type === 'essay') {
    correctMeta = ''; // ignored server-side
  } else {
    // multiple_choice / true_false
    if (Array.isArray(opts.correct) || typeof opts.correct === 'string') {
      throw new Error(`${type} requires correct as a single number`);
    }
    correctMeta = String(opts.correct);
  }

  const res = await request.post('/wp-json/wp/v2/sik_question', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: {
      title,
      status: 'publish',
      content: '',
      meta: {
        _sikshya_question_type: type,
        _sikshya_question_options: options,
        _sikshya_question_correct_answer: correctMeta,
        _sikshya_question_points: opts.points ?? 1,
      },
    },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok()) {
    throw new Error(`createQuestionViaRest failed ${res.status()}: ${JSON.stringify(body)}`);
  }
  return { id: Number(body.id), title, type };
}

/**
 * Create an essay-style assignment via wp/v2/sik_assignment and link it into
 * the curriculum. Mirrors createLessonViaRest so the assignment is reachable
 * from the learn-page.
 */
export async function createAssignmentViaRest(
  page: Page,
  request: APIRequestContext,
  courseId: number,
  opts: { title?: string; type?: 'essay' | 'url' | 'file_upload'; points?: number } = {},
): Promise<{ id: number; title: string; chapterId: number; link: string }> {
  const nonce = await getAdminNonce(page);
  const title = opts.title ?? `E2E Assignment ${slug('a')}`;
  const res = await request.post('/wp-json/wp/v2/sik_assignment', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: {
      title,
      status: 'publish',
      content: '<p>Auto-generated E2E assignment.</p>',
      meta: {
        _sikshya_assignment_type: opts.type ?? 'essay',
        _sikshya_assignment_points: opts.points ?? 10,
      },
    },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok()) {
    throw new Error(`createAssignmentViaRest failed ${res.status()}: ${JSON.stringify(body)}`);
  }
  const id = Number(body.id);

  const chapterRes = await request.post('/wp-json/sikshya/v1/curriculum/chapters', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { course_id: courseId, title: `E2E Assignment Chapter ${slug('ch')}` },
  });
  const chapterBody = await chapterRes.json().catch(() => ({}));
  if (!chapterRes.ok() || chapterBody?.success === false) {
    throw new Error(`createChapter (assignment) failed ${chapterRes.status()}: ${JSON.stringify(chapterBody)}`);
  }
  const chapterId = Number(chapterBody?.data?.chapter_id ?? 0);

  const linkRes = await request.post('/wp-json/sikshya/v1/curriculum/content/link', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { content_id: id, chapter_id: chapterId },
  });
  const linkBody = await linkRes.json().catch(() => ({}));
  if (!linkRes.ok() || linkBody?.success === false) {
    throw new Error(`linkContent (assignment) failed ${linkRes.status()}: ${JSON.stringify(linkBody)}`);
  }

  return { id, title, chapterId, link: (body.link as string) ?? '' };
}

/**
 * Attach a list of question IDs to a quiz via the `_sikshya_quiz_questions` meta.
 */
export async function attachQuestionsToQuiz(
  page: Page,
  request: APIRequestContext,
  quizId: number,
  questionIds: number[],
): Promise<void> {
  const nonce = await getAdminNonce(page);
  const res = await request.post(`/wp-json/wp/v2/sik_quiz/${quizId}`, {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { meta: { _sikshya_quiz_questions: questionIds, _sikshya_quiz_passing_score: 50 } },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok()) {
    throw new Error(`attachQuestionsToQuiz failed ${res.status()}: ${JSON.stringify(body)}`);
  }
}

/**
 * Create a WP user via wp/v2/users REST endpoint.
 */
export async function createUserViaRest(
  page: Page,
  request: APIRequestContext,
  input: StudentInput,
): Promise<{ id: number }> {
  const nonce = await getAdminNonce(page);
  const res = await request.post('/wp-json/wp/v2/users', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: {
      username: input.username,
      email: input.email,
      password: input.password,
      roles: [input.role ?? STUDENT_ROLE],
    },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok()) {
    if (res.status() === 400 && /exists/i.test(JSON.stringify(body))) {
      return { id: 0 };
    }
    throw new Error(`createUserViaRest failed ${res.status()}: ${JSON.stringify(body)}`);
  }
  return { id: Number(body.id) };
}

/**
 * Open a fresh browser context and log a non-admin user in via /wp-login.php.
 */
export async function loginAs(
  context: BrowserContext,
  username: string,
  password: string,
): Promise<Page> {
  const page = await context.newPage();
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page
    .waitForURL(/wp-admin|wp-login\.php|\/$/, { timeout: 30_000 })
    .catch(() => undefined);
  return page;
}
