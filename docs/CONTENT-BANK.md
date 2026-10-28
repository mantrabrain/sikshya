# Content bank

A library of lessons, quizzes and assignments that can be dropped into any
course, so shared material is built once. Pro add-on `content_bank`, Starter
tier and above, **off by default** like every paid add-on.

Admin → Content library → **Content bank**.

- **Save from a course** — pick a course, tick what is worth keeping.
- **Library** — search, filter by type, rename, share, insert, remove.

## It copies, and that is the design

LearnDash shares a step: one lesson post listed in several courses. Sikshya
cannot do that, and the reason is not a missing feature.

Progress, lesson completions and quiz attempts are all stored against a
*lesson id*. A lesson that belonged to two courses would share those rows, so
a learner who finished it in course A would show as having finished it in
course B — a course they may never have been enrolled in. Certificates,
gradebooks and completion-triggered emails all read those rows. There is no
version of shared steps that does not change what every existing row means,
and this product is already in use.

So inserting a bank item **creates a new lesson, quiz or assignment** in the
target section. Each course keeps its own records.

The cost of that is real and worth saying plainly: **editing a banked item
does not reach courses already built from it.** Fix a typo in the bank and the
twelve courses that already took a copy still have the typo. Where an author
wants one canonical copy, a bank is the wrong tool — put the material on a
page, or in a single course the others link to.

## What travels

The item's title and body, plus an **allowlist** of meta per type — lesson
kind and media URLs, transcripts, attachments, durations; quiz time limit,
passing score, attempt limits, shuffling; assignment points and submission
rules. A quiz also carries its questions: title, body, type, points, options,
explanation, and the answer key **exactly as stored**, because the grader
reads a different shape per question type and a second encoder would be a
second chance to mark learners wrong for being right.

Add-ons can extend the list with `sikshya_content_bank_meta_keys`
(`$keys, $kind`). Keys outside Sikshya's own prefix are dropped, and so is
anything that binds a post to one course — the course-link keys, ordering,
chapter contents, quiz question ids, the cmi5 unit id. That filtering is
applied on the way in *and* on the way out, so a hand-edited or
future-written payload cannot re-link a copy either.

What does **not** travel: the course the item came from, its position in a
chapter, learner data of any kind, and content-drip timing (drip dates belong
to a course's schedule, not to the material).

SCORM and H5P lessons travel by **reference**: the copy points at the same
package, which is how attaching a package to several lessons already works,
and attempts stay keyed to each lesson. **cmi5 lessons are refused** with a
message rather than half-copied — a cmi5 lesson is one row in the assignable
unit table and its satisfaction state is keyed to that row, so a copy would
either share the row or arrive with no unit at all, which looks like a working
lesson until a learner presses Start. Re-import the package into the other
course instead.

## Who can do what

Three separate questions, deliberately not collapsed into one:

| Action | Requires |
|---|---|
| Open the bank | Site staff (`manage_sikshya` / `manage_options`) or an instructor, with the add-on enabled on a licensed site |
| Save an item | Edit access to the content being saved |
| See an item | Your own items, items shared by their author, or anything at all for site staff |
| Rename / share / remove an item | Being its author (or site staff) |
| Insert into a course | Edit access to **that course** |

Edit access is resolved through `InstructorPermissions`, which inherits
ownership of a lesson or quiz from its course, rather than through
`edit_post` on the post itself.

A **shared** item is readable by every author on the site and editable by
none of them — otherwise one author's tidy-up silently rewrites a library ten
courses were built from. Sharing can be turned back off; items already
inserted are unaffected, because they are ordinary course content.

## Questions are not in the bank

`quiz_advanced` already groups questions into reusable **question banks** as a
taxonomy, which is what a quiz draws a random pool from. A second, parallel
question library would leave an author guessing which one a quiz uses. The
content bank captures a quiz *with* its questions instead.

## Turning it off, and uninstalling

Disabling the add-on hides the library. Nothing is deleted, and every lesson
or quiz already inserted keeps working — it never needed the add-on.

Pro's uninstaller removes bank rows only when the site has explicitly opted
into data deletion (`SIKSHYA_PRO_DELETE_DATA_ON_UNINSTALL`). Content inserted
into courses is never touched: it belongs to the course.

## Not claimed

- No folders or tags inside the bank. Search and type filter only.
- No "insert from bank" button inside the course builder yet; insertion is
  driven from the bank workspace, which picks the course and section.
- No bulk insert into several courses at once.
- No sync, and none planned under this design — see the copy argument above.
- Chapters (sections) themselves cannot be banked, only their contents.
- No browser test: the workspace is covered by the REST and service suites
  (`--group content_bank`), not by Playwright.
