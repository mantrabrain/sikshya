# Moving a course library into Sikshya

Sikshya can read another LMS's courses off the same WordPress site and recreate
them as its own. Today that means **Tutor LMS**; the machinery is built so a
second source is a reader class, not a second importer.

It is a **free** feature. Charging somebody to bring their own course library
to us would be a strange toll to put on the way in.

- **Admin:** Sikshya → Tools → **Move from another LMS**
- **CLI:** `wp sikshya import --list`, then `wp sikshya import --source=tutor --dry-run`

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

| Tutor | Sikshya |
| --- | --- |
| `true_false` | `true_false` |
| `single_choice` | `multiple_choice` |
| `multiple_choice` | `multiple_response` |
| `short_answer` | `short_answer` |
| `fill_in_the_blank` | `fill_blank` |
| `open_ended` | `essay` |
| `ordering` | `ordering` |
| `matching` | `matching` |

Anything else — `image_matching`, `image_answering`, `h5p` — is **skipped and
named in the report**, not converted into the nearest thing. A matching
question flattened into a multiple choice is a question that marks learners
wrong for being right, and a quiz that quietly lost its hardest question is
worse than one that tells you which question it lost.

### One mapping worth knowing about

A Tutor enrolment is a post whose status is `completed`, and "completed" there
means *the enrolment went through* — not that the learner finished the course.
Reading it as course completion would mark every student of every course as
having finished it. Course completion comes from Tutor's own completion
records, and only those learners arrive complete.

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

- **Tutor LMS only, for now.** LearnDash is next. The interface to add a source
  is `Sikshya\Import\ImportSourceInterface` plus the `sikshya_import_sources`
  filter; a source reads and never writes.
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
