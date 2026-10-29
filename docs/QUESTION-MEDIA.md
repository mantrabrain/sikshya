# Question media

One **image or audio clip** on a quiz question's stem, shown between the
question and its options.

Free. Nothing to enable.

It exists because a whole class of question cannot be asked in text — "listen
to this and tell us what you hear", "what is wrong with this diagram", "which
of these three waveforms is clipping" — and Sikshya had no way to ask it. The
question title is stored and rendered as **plain text** on purpose
(`QuizTemplateData::buildQuestionViewRowForId()` runs it through
`wp_strip_all_tags()`), so an author could not smuggle an `<img>` into the stem
even deliberately.

## Authoring

Content library → a question, or Add question inside a course — both surfaces
have the field, because a feature only the less-used authoring path can reach
is not shipped.

**Image or audio (optional)** → choose a file from the media library, or paste
a URL. Images and audio appear in **one** media library list: the author is
picking "the thing this question is about" and does not experience that as two
decisions.

The **kind is derived, never asked**: from the attachment's own media type, or
from the URL's extension. The one exception is a URL whose path carries no
usable extension — a CDN serving `/asset/8812` — where there is nothing to
derive from, so the field appears and asks. An unrecognised kind resolves to
**nothing** rather than defaulting, because rendering an audio file inside an
`<img>` replaces the question with a broken-image icon.

When both an attachment and a URL are stored the attachment wins, so the URL
field is only offered while no file is chosen.

## Alt text is not optional for an image

**The editor refuses to save an image without a description.** This is not
pedantry about a checkbox: the image *is* the question, so a learner using a
screen reader has nothing whatsoever to answer from. A question that cannot be
attempted by some learners is a broken question, not an imperfect one.

The description resolves in this order:

1. the question's own `_sikshya_question_media_alt`
2. the attachment's `_wp_attachment_image_alt` — where a careful site already
   keeps it; the picker also copies it into the field, because asking an author
   to retype it per question is how it ends up empty

**Audio is exempt.** The player is named "Audio for question 3" when no
description is given, which is enough to find and operate it — and a transcript
of a clip the learner is being asked to identify would hand over the answer.

**The honest gap:** enforcement is in the authoring UI, not in the server. A
REST client, an importer or a row that predates this feature can store an image
with no description. The renderer then marks it decorative (`alt=""`) rather
than inventing a description or falling back to the filename — a screen reader
announcing "diagram-final-v2.png" is worse than silence, and only an author can
actually fix it. Blocking it server-side would reject writes that work today,
which the compatibility rules do not allow.

## Delivery

A media library file is **not** served from its `wp-content/uploads/` path. It
goes through the same signed proxy as lesson attachments and PDF lessons: a URL
bound to one user id, expiring within the hour.

It is an ordinary download token, with **no inline claim**. `<img>` and
`<audio>` load a subresource, and `Content-Disposition` is not consulted for
subresource loads, so the bytes render from that URL exactly as they would from
the uploads path. Nothing about this feature needs the proxy's inline allowlist
widened past `application/pdf` — and keeping that list at one entry is the whole
reason it is worth having, because it decides what the browser will parse *as a
document* from our own origin. There is a test asserting question media never
mints an inline token.

Audio seeking works because the proxy answers byte ranges, which it learned to
do for PDF lessons (`docs/PDF-LESSONS.md`).

A file on **another host** gets none of this. The scheme is still checked,
because the value lands in a `src` attribute where `javascript:` would execute.

## Accepted types

| | Accepted |
|---|---|
| Image | jpeg, png, gif, webp, avif |
| Audio | mpeg/mp3, mp4/m4a, aac, ogg, wav, webm, flac |

**SVG is deliberately absent.** A script inside an SVG does not run when the
file is loaded through `<img>` — but it does the moment anyone links it or opens
it in a tab, and "safe only while nobody clicks it" is not a property to build
on for a file an author uploaded.

## Where it appears

`templates/partials/quiz-question-fieldset.php`, between the legend and the
options — where a learner reads it *before* choosing rather than after.

The image is capped by **height** (340px, 240px on a phone) rather than width:
a diagram wide enough to read is useless if answering it means scrolling past
the options, and an author uploading a 4000px screenshot is the normal case.

Grading is untouched. The answer a learner submits is the same index it always
was, which is the main reason media is on the stem and not on the options.

## Data

| Meta | |
|---|---|
| `_sikshya_question_media_id` | media library id; wins over the URL |
| `_sikshya_question_media_url` | external URL, used only when no id is set |
| `_sikshya_question_media_kind` | `image` or `audio`; consulted **only** for a URL with no usable extension |
| `_sikshya_question_media_alt` | description |

All four are registered for REST, which is the trap audio lessons fell into:
the renderer worked, the meta was unregistered, so nothing could author one.

A banked question carries all four (`BankItemKinds::questionMetaKeys()` in Pro)
— a media library reference is site-wide, so the copy keeps the picture the
question is about.

## Tests

`--group question_media`, 33 tests in
`tests/Integration/QuestionMediaIntegrationTest.php`. Mutation tested: 24
mutants, 23 caught; the survivor is behaviour-equivalent (removing the
attachment-type check is subsumed by the file-reference check, and the combined
mutation of the pair is caught).

## Not claimed

- **No media on the options.** "Which of these four pictures is a cumulus
  cloud" is not supported. It would change what a learner submits — the answer
  index stops being readable — and it is the half of the feature a grading bug
  would be worst in.
- **One file per question.** Not a gallery, not an image *and* a clip.
- **No video.** A video question wants a player, a poster, captions and a
  "watched it" signal; that is a feature, not a MIME type added to a list.
- **No media in the review screen.** After grading, the breakdown shows the
  question text and the explanation; it does not re-show the image or replay
  the clip.
- **No transcript or caption track** for an audio clip, deliberately — see
  above.
- **Imports do not bring question images across.** The Tutor and LearnDash
  importers (`docs/MIGRATING-TO-SIKSHYA.md`) still import questions without
  media.
- **No cropping, resizing or annotation.** Upload the image you want shown.
- **No Playwright spec.** The viewer, the height cap and audio playback are
  unverified in a real browser.
