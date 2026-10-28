# Moving a course library into Sikshya

Sikshya can read another LMS's courses off the same WordPress site and recreate
them as its own. Today that means **Tutor LMS** and **LearnDash**; the
machinery is built so a further source is a reader class, not a second
importer.

It is a **free** feature. Charging somebody to bring their own course library
to us would be a strange toll to put on the way in.

- **Admin:** Sikshya → Tools → **Move from another LMS**
- **CLI:** `wp sikshya import --list`, then `wp sikshya import --source=tutor --dry-run`
  (or `--source=learndash`)

## The two things that matter more than any field mapping

**It never writes to the other plugin.** Its courses, lessons, quizzes,
enrolments and completions are read and left exactly as they were. Keep it
installed, compare the two, switch back if the result is not what you wanted —
that possibility is the only reason a nervous administrator will try this at
all.

**It can be run twice.** Every object Sikshya creates is recorded against the
id it came from, so a second run updates what it already brought across instead
of making a second catalogue. That matters because running an importer twice is
not an accident — people stop half way, add a course to the old plugin, or press
the button again because nothing seemed to happen.

Both are covered by tests that fail if either stops being true.

## What comes across

Tutor's model maps onto Sikshya's almost one to one, which is why it was the
first source. LearnDash needs a little more judgement, and has a section of its
own below.

| From Tutor | Becomes | Notes |
| --- | --- | --- |
| Course | Course | Title, body, excerpt, status, author, featured image, categories, tags, difficulty, duration, benefits/requirements/audience |
| Topic | Chapter | In the author's order |
| Lesson | Lesson | Body, attachments, and a video lesson stays a **video** lesson with its URL |
| Quiz | Quiz | Passing grade, and the time limit converted to minutes — Tutor states a limit *with a unit*, so two hours is 120 |
| Quiz question | Question | With the answer key in the form Sikshya's grader reads: an option index, not a label |
| Enrolment | Enrolment | With the date the learner actually joined, not the date of the import |
| Course completion | Enrolment marked complete | — |
| Lesson completion | Lesson marked complete | A learner who had finished a lesson does not have to do it again |

### Question types

Six types can be carried faithfully, because each one can be written in exactly
the encoding Sikshya's grader reads:

| Sikshya type | From Tutor | From LearnDash |
| --- | --- | --- |
| `true_false` | `true_false` | — |
| `multiple_choice` (one answer) | `single_choice` | `single` |
| `multiple_response` (several) | `multiple_choice` | `multiple` |
| `short_answer` | `short_answer` | `free_answer` |
| `fill_blank` | `fill_in_the_blank` | `cloze_answer` |
| `essay` (graded by a person) | `open_ended` | `essay` |

Everything else is **skipped and named in the report**. Two groups, for two
different reasons:

- **No equivalent at all**: Tutor's `image_matching`, `image_answering`, `h5p`;
  LearnDash's `assessment_answer`, which is a survey scale rather than a graded
  question.
- **An equivalent that would give the answer away**: `matching` and `ordering`
  (LearnDash's `matrix_sort_answer` and `sort_answer`). Sikshya supports both,
  and the source stores enough to rebuild them — but it stores the options
  *already in their correct order*, so an imported question would be trivially
  correct unless the player happened to shuffle. A question that cannot be got
  wrong is worse than one the report told you to rebuild.

A short answer with no accepted wordings is skipped too: it can never be marked
right, so importing it would hand every learner a question they must fail.

### One Tutor mapping worth knowing about

A Tutor enrolment is a post whose status is `completed`, and "completed" there
means *the enrolment went through* — not that the learner finished the course.
Reading it as course completion would mark every student of every course as
having finished it. Course completion comes from Tutor's own completion
records, and only those learners arrive complete.

## LearnDash

LearnDash is three levels deep — course, lesson, topic — where Sikshya is two,
so the shape adapts to the course rather than forcing one answer on every site:

- a course that **uses topics** becomes a chapter per lesson. The lesson's own
  page becomes the first lesson inside its chapter, so whatever the author
  wrote there is not lost just because the lesson became a container;
- a **flat** course, with no topics anywhere, keeps its lessons as lessons
  inside a single chapter. Turning twenty lessons into twenty chapters of one
  lesson each is faithful to LearnDash and unreadable in Sikshya.

Quizzes are picked up wherever they hang — off a topic, a lesson, or the course
itself (those land in a "Course quizzes" chapter). A quiz time limit is stated
in *seconds* by LearnDash, so 600 becomes 10 minutes.

**Enrolments come from two places**, because they answer different questions:
the `learndash_user_activity` table has everyone who ever started the course,
and the `course_<id>_access_from` user meta has everyone who was *given* access
— including learners who never opened it, who would otherwise be dropped.
Completions come from the activity table, and only rows marked complete.

**Questions are read out of the ProQuiz tables, where the answers are
serialised PHP objects.** They are decoded with `allowed_classes => false`,
which never instantiates anything, and the resulting incomplete objects are
read property by property. That matters twice: letting stored data name a class
to construct is how deserialisation becomes code execution, and on the usual
migration LearnDash is deactivated, so the class it names does not exist
anyway.

**Not carried from LearnDash:** group-based enrolment (LearnDash groups have no
Sikshya equivalent yet, so a learner enrolled *only* through a group is not
imported — check your groups before switching over), certificates, assignments,
essay submissions, and course prerequisites.

## How to run it

1. **Preview.** The preview counts everything a real run would create and
   creates none of it. A migration nobody can preview is a migration nobody
   runs on a live site.
2. **Import.** The screen works through the library a few courses at a time and
   shows progress; on a large site use the CLI instead, where there is no
   request to time out and no tab to close:

   ```sh
   wp sikshya import --source=tutor            # until finished
   wp sikshya import --source=tutor --batch=1  # one batch, then stop
   ```

3. **Check a course or two** — a quiz especially — before switching the old
   plugin off.

**Start from the beginning** clears the record of how far a run got, so the
next run looks at every course again. It does *not* undo an import: nothing
already brought across is duplicated or removed.

## Limits, stated plainly

- **Tutor LMS and LearnDash.** LearnPress, LifterLMS and Masteriyo are not
  written. The interface to add one is `Sikshya\Import\ImportSourceInterface`
  plus the `sikshya_import_sources` filter: a source reads its own plugin,
  translates its question types into Sikshya's, and never writes.
- **No orders, payments or subscriptions.** Money has to be reconciled by a
  person, and silently recreating historical transactions in a second system is
  the last thing an accountant wants.
- **No certificates, reviews, question banks, or assignment submissions.**
- **Course progress is not copied as a percentage.** Lesson completions are,
  and Sikshya's own progress follows from them — a copied percentage would
  disagree with the lessons underneath it the moment anything changed.
- **Media is not re-uploaded.** Both plugins share one media library, so images
  and attachments are re-pointed rather than duplicated. Files hosted outside
  the library stay where they are.
- **Nothing is deleted from the source, ever** — including when you remove an
  import's record. Taking the old plugin's content away is a decision for the
  site owner, taken deliberately, not a side effect of an import.
