/**
 * Merges two 10×10 long-form courses into sample-data/sample-lms.json (in place).
 * Run: node scripts/merge-long-sample-courses.mjs
 *
 * After merge, regenerate unique course/content slugs so Learn URLs stay unambiguous:
 *   node scripts/add-sample-pack-slugs.mjs
 */
import { readFileSync, writeFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const samplePath = join(root, 'sample-data', 'sample-lms.json');

const YT = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';

const UNI_LABELS = [
  'Foundations',
  'Systems',
  'Sequences',
  'Practice lab',
  'Evidence',
  'Stakeholders',
  'Risk',
  'Delivery',
  'Measurement',
  'Integration',
];

const MOD_LABELS = ['Design', 'Build', 'Iterate', 'Assessment', 'Stories', 'Skills', 'Collaboration', 'Launch', 'Operate', 'Growth'];

function longLessonHtml(chapter, slot, label) {
  return (
    `<p><strong>${label}</strong> — Extended sample material for <em>Chapter ${chapter}</em>, segment ${slot}. Use it to test scroll behaviour, reading length, typography on the Learn page, outlines, learner progress, and resume behaviour immediately after sample import.</p>` +
    `<p>Production courses should shorten paragraphs and add headings; this block deliberately runs long so QA can stress bulky lessons without manual authoring hundreds of paragraphs.</p>` +
    `<p>Pattern used here: motivate the idea in plain language, give a constrained example, name a common misconception, link forward to formative checks. Replace with disciplinary content when SMEs sign off.</p>` +
    `<ul>` +
    `<li>Explain the outcome in one sentence without jargon overload.</li>` +
    `<li>Apply Chapter ${chapter} ideas to an easy scenario learners already know.</li>` +
    `<li>Contrast with a neighbouring concept instructors say trips people up.</li>` +
    `<li>Prep for quizzes or assignments that bookend each chapter rhythm.</li>` +
    `</ul>` +
    `<p>You may swap in transcripts, captions, downloadable handouts, diagrams, embeds, or H5P. Validate keyboard focus and colour contrast against your storefront theme—not only the admin SPA.</p>` +
    `<p>Close every reading with measurable micro-outcomes learners can recall before grading; it keeps scaffolding honest when coordinators walk end-to-end before launch.</p>`
  );
}

function longChapterIntro(chapter, strand) {
  return (
    `<p><strong>${strand} · Chapter ${chapter}</strong>: long chapter description for curriculum accordion / builder previews.</p>` +
    `<p>Each imported chapter bundles <strong>ten</strong> curriculum items rotating lessons, demonstration videos (placeholder URLs), auto-gradable quizzes, and written-style assignments.</p>` +
    `<p>Reorder inside Sikshya after import; rename carefully post-launch if learners rely on bookmarks and deep-links.</p>`
  );
}

function longCourseIntro(htmlTitleLead) {
  return (
    `<p>${htmlTitleLead}</p>` +
    `<p>This imported course is deliberately <strong>large</strong>: <strong>10 chapters</strong> each with at least <strong>10 items</strong> (mixed types). Use it to exercise outlines, cache invalidation after import, admin list paging, enrolment dashboards, moderation queues, and bulk operations.</p>` +
    `<p>Operational checklist for reviewers:</p>` +
    `<ul>` +
    `<li>Replace streaming placeholders with compliant media licences.</li>` +
    `<li>Align quizzes and assignments with grading rubrics and academic integrity norms.</li>` +
    `<li>Confirm transactional emails/certificates behave with long completions.</li>` +
    `<li>Profile frontend queries if hosting is constrained—heavy trees spotlight N+1 issues early.</li>` +
    `</ul>` +
    `<p>Delete wholesale once staging reflects your real syllabus; regenerate via this repo script if QA needs fresh volume again.</p>`
  );
}

function miniQuiz(prefix, chapter, slot, passingScore, randomize) {
  return {
    type: 'quiz',
    title: `${prefix} Quiz — Ch.${chapter} · ${slot}`,
    content:
      `<p>Autosampled quiz for Chapter ${chapter}. Customize stems before awarding credit.</p>` +
      `<p>Timed section ${slot}; adjust duration and shuffle per policy.</p>`,
    passing_score: passingScore,
    time_limit: 12 + (slot % 8),
    attempts_allowed: 0,
    randomize,
    questions: [
      {
        title: `Chapter ${chapter}: formative checks improve retention versus passive scrolling alone.`,
        question_type: 'true_false',
        points: 1,
        options: ['True', 'False'],
        correct_answer: '0',
      },
      {
        title: 'Which practice keeps long LMS lessons navigable?',
        question_type: 'multiple_choice',
        points: 1,
        options: [
          'Descriptive headings and one primary idea per section',
          'One endless paragraph hiding every detail',
          'Random heading levels for surprise',
          'Remove objectives from learner view',
        ],
        correct_answer: '0',
      },
      {
        title: `Slot ${slot} spot check (Chapter ${chapter}): curricula should visualize progress.`,
        question_type: 'true_false',
        points: 1,
        options: ['True', 'False'],
        correct_answer: '0',
      },
    ],
  };
}

function miniAssignment(prefix, chapter, slot) {
  const types = ['written', 'written', 'checklist'];
  const t = types[slot % types.length];
  return {
    type: 'assignment',
    title: `${prefix} Task — Ch.${chapter} · ${slot}`,
    content:
      `<p><strong>Brief (${t})</strong></p>` +
      `<p>Draft 200–260 words outlining how Chapter ${chapter} would be pitched to newcomers, cite one formative check you embed mid-lesson, and note a misuse teams make when authoring long LMS modules.</p>` +
      `<p>Rewrite with domain rubrics after instructional design review.</p>`,
    meta: {
      _sikshya_assignment_points: 8 + (slot % 6),
      _sikshya_assignment_type: t,
    },
  };
}

/**
 * Exactly 10 content rows per chapter.
 * @param {object} opts
 */
function chapterContents(opts) {
  const { chapterNum, lessonPrefix, quizPass, firstLessonFree } = opts;
  const out = [];

  out.push({
    type: 'lesson',
    title: `${lessonPrefix} Reading — Ch.${chapterNum} foundation`,
    content: longLessonHtml(chapterNum, 1, 'Foundations reading'),
    lesson_type: 'text',
    duration: 14 + (chapterNum % 5),
    ...(firstLessonFree && chapterNum === 1 ? { meta: { _sikshya_is_free: '1' } } : {}),
  });
  out.push({
    type: 'lesson',
    title: `${lessonPrefix} Instructor video — Ch.${chapterNum}`,
    content:
      `<p>Demonstration placeholder; swap for hosted lecture capture.</p>` + longLessonHtml(chapterNum, 2, 'Video lecture notes'),
    lesson_type: 'video',
    duration: 11 + (chapterNum % 4),
    video_url: YT,
  });
  out.push({
    type: 'lesson',
    title: `${lessonPrefix} Applied reading — Ch.${chapterNum}`,
    content: longLessonHtml(chapterNum, 3, 'Application drill'),
    lesson_type: 'text',
    duration: 15,
  });
  out.push(miniQuiz(lessonPrefix, chapterNum, 4, quizPass, chapterNum % 2 === 0));
  out.push(miniAssignment(lessonPrefix, chapterNum, 5));
  out.push({
    type: 'lesson',
    title: `${lessonPrefix} Case narrative — Ch.${chapterNum}`,
    content: longLessonHtml(chapterNum, 6, 'Scenario walk-through'),
    lesson_type: 'text',
    duration: 17,
  });
  out.push({
    type: 'lesson',
    title: `${lessonPrefix} Spotlight video — Ch.${chapterNum}`,
    content:
      `<p>Second streaming clip keeps dense chapters approachable.</p>` + longLessonHtml(chapterNum, 7, 'Spotlight commentary'),
    lesson_type: 'video',
    duration: 9,
    video_url: YT,
  });
  out.push(miniQuiz(lessonPrefix, chapterNum, 8, quizPass + 6, chapterNum % 2 === 1));
  out.push(miniAssignment(lessonPrefix, chapterNum, 9));
  out.push({
    type: 'lesson',
    title: `${lessonPrefix} Synthesis recap — Ch.${chapterNum}`,
    content: longLessonHtml(chapterNum, 10, 'Wrap-up synthesis'),
    lesson_type: 'text',
    duration: 13,
  });

  if (out.length !== 10) {
    throw new Error(`Chapter ${chapterNum} expected 10 contents, got ${out.length}`);
  }
  return out;
}

function buildChapters(theme, lessonPrefix, quizPass, firstLessonFree) {
  /** @type {object[]} */
  const chapters = [];
  const labels = theme === 'uni' ? UNI_LABELS : MOD_LABELS;
  const subtitle = theme === 'uni' ? 'integrated strand' : 'mixed-modality rehearsal';

  for (let c = 1; c <= 10; c += 1) {
    const label = labels[c - 1] || `Block ${c}`;
    chapters.push({
      title: `${String(c).padStart(2, '0')} — ${label} (${subtitle})`,
      content: longChapterIntro(c, lessonPrefix),
      contents: chapterContents({
        chapterNum: c,
        lessonPrefix,
        quizPass,
        firstLessonFree,
      }),
    });
  }
  return chapters;
}

function main() {
  const raw = readFileSync(samplePath, 'utf8');
  const pack = JSON.parse(raw);
  const courses = pack.courses;
  if (!Array.isArray(courses)) {
    throw new Error('Invalid pack: courses not array');
  }

  let showcaseIdx = -1;
  let mixedIdx = -1;
  courses.forEach((co, i) => {
    if (!co || typeof co !== 'object' || typeof co.title !== 'string') {
      return;
    }
    const t = co.title;
    // Idempotent: matches short or long title variants.
    if (t.includes('Structured learning path') && t.includes('showcase')) {
      showcaseIdx = i;
    }
    if (t.includes('All content types in one place')) {
      mixedIdx = i;
    }
  });

  if (showcaseIdx < 0 || mixedIdx < 0) {
    throw new Error('Could not find showcase / mixed courses to replace (titles changed?)');
  }

  const showcase = { ...courses[showcaseIdx] };
  showcase.title = 'Sample: Structured learning path (showcase — long curriculum)';
  showcase.excerpt =
    '10 chapters × 10 items each: long readings, dual videos per chapter, quizzes, assignments. Paid + sale; first reading is free-preview.';
  showcase.content = longCourseIntro(
    '📚 <strong>Showcase long path</strong> — Same catalogue positioning as the short showcase demo, rebuilt for outline stress tests and substantive course body copy.'
  );
  showcase.chapters = buildChapters('uni', 'Showcase', 62, true);
  showcase.meta = { ...(showcase.meta || {}), _sikshya_duration: '4800' };

  const mixed = { ...courses[mixedIdx] };
  mixed.title = 'Sample: All content types in one place (long curriculum)';
  mixed.excerpt =
    '10 chapters × 10 items repeating every modality together—ideal for validating mixed assessments at scale.';
  mixed.content = longCourseIntro(
    '🧭 <strong>Mixed long path</strong> — Mirrors the miniature “everything in one chapter” motif, amplified across ten modules with deterministic structure.'
  );
  mixed.chapters = buildChapters('mod', 'Mixed', 58, false);
  mixed.meta = { ...(mixed.meta || {}), _sikshya_duration: '5200' };

  courses[showcaseIdx] = showcase;
  courses[mixedIdx] = mixed;

  pack.label = 'Sikshya seven-course feature pack (+ long curricula)';
  pack.description =
    'Sample pack with varied pricing and modality demos. Two flagship rows are deliberately long-form: ten chapters each, ten curriculum items per chapter—mixing lengthy HTML lessons, two placeholder videos per chapter, quizzes, and assignments—ideal for stressing outlines, enrolment dashboards, grading queues, import cache, and list pagination.';
  writeFileSync(samplePath, JSON.stringify(pack, null, 2) + '\n', 'utf8');
  console.log('Updated', samplePath);
}

main();
