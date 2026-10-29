# PDF lessons

A PDF lesson is a curriculum step whose **body is a document**. The learner
opens the lesson and reads the file in place, ticks it complete, and moves on;
progress, drip, prerequisites and certificates treat it exactly like any other
lesson, because it *is* an ordinary `sik_lesson` with
`_sikshya_lesson_type = pdf`.

Free. Nothing to enable.

## Not the same thing as an attachment

Sikshya already had per-lesson attachments (`docs/` aside, see
`src/Services/LessonAttachments.php`): files a learner can download from the
Downloads tab, beside whatever the lesson already is. A PDF lesson is the
other shape — the document is the teaching, not a supplement — and the
distinction decides how the file is served:

| | Attachment | PDF lesson |
|---|---|---|
| Role | extra material | the lesson body |
| Served as | `Content-Disposition: attachment` | `inline`, so it renders |
| Surface | Downloads list | a viewer in the lesson |

Both go through the same signed proxy. Use attachments for the worksheet that
accompanies a video; use a PDF lesson for the handbook chapter that *is* the
week's reading.

## Authoring

Content library → a lesson → **Lesson type → PDF document**. Then either:

1. **Choose a PDF** from the media library (preferred), or
2. paste a **document URL** hosted elsewhere.

The picker is constrained to `application/pdf`, and so is the server: a stored
attachment id that is not a PDF, is not an attachment, or has lost its file
reference resolves to nothing rather than to a frame the browser cannot draw.
An id always wins over a URL, so the URL field is only offered while no file is
chosen — a form that lets you fill in a field it will then ignore is a form
that lies.

Anything written in the editor body appears **under** the viewer as notes.

**Hide the download link** removes the Download link under the viewer. It does
not prevent saving the file, and the field says so: the browser's own PDF
viewer has a save button and no web page can take it away. It is tidiness, not
protection.

## How the file is delivered

For a media library file the page never receives the `wp-content/uploads/`
path. It receives a URL from `AttachmentTokenService`:

```
/wp-json/sikshya/v1/file/<base64url(payload)>.<hmac-sha256>
```

The payload names the attachment, the **single user id** allowed to use it, an
expiry (one hour by default), and — new for this feature — a `dsp` claim asking
for inline delivery. The proxy refuses the URL if the signature does not
verify, if it has expired, or if the requester's authenticated identity is not
the one in the token.

Two decisions in there are worth stating plainly.

**Inline is a signed claim, not a URL parameter.** Disposition decides whether
the browser *renders* bytes served from our own origin. A `?inline=1` would let
anyone ask the proxy to render an arbitrary upload, and an `.html` or `.svg`
rendered from our origin is stored XSS against every logged-in viewer. So the
claim lives inside the HMAC payload: editing it breaks the signature.

**And the claim alone is not enough.** The proxy also refuses to render
anything outside an allowlist, which currently holds exactly one type:
`application/pdf`. A PDF is parsed by the browser's PDF viewer in its own
sandbox with no access to our DOM or cookies, so it can be rendered without
handing over the origin. Both conditions must hold; either alone has been
enough to turn an upload form into stored XSS elsewhere.

**Byte ranges.** The proxy now answers `Range` requests with `206` and a
`Content-Range`, because browser PDF viewers fetch a linearised document a
range at a time. Answering 200-with-everything makes a large document
re-download on every page jump, and some builds give up and show a blank frame.
One range per request is supported; a multi-range, an unknown unit or anything
unparseable means "send it all", since a 206 carrying the wrong bytes is a
corrupt download while a 200 carrying everything is merely less efficient.
Ranges are streamed in 256 KB chunks, so a 40 MB slice is not a 40 MB
allocation.

A document on **another host** gets none of this. It is framed by its URL and
served under whatever rules that host applies; we do check the scheme, because
the value lands in an iframe `src` and `javascript:` there would execute in the
page.

## The viewer

An iframe, plus a **permanent** link row: "Open the document in a new tab", and
a Download link unless it is hidden.

The links are not a fallback revealed on an error — they are always there.
Embedded PDF support is genuinely uneven (iOS Safari renders one unscrollable
page; several Android browsers render nothing), and an iframe that fails to
show a PDF usually fails *silently*, with no `error` event to listen for. That
is why the video/audio fallback pattern, which keys off a player error, could
not be reused. The frame is also shorter on phones for the same reason: a tall
dead frame is worse than a short one.

## Access

The lesson is gated exactly as any other lesson: course access, enrolment,
drip, prerequisites, free-preview. A guest reading a free-preview lesson is a
real case, and the token service cannot bind a URL to nobody, so for user 0 it
falls back to the public attachment URL rather than returning nothing.

One honest limit, inherited from how signed attachment URLs work: a token is
valid for its hour **regardless of what happens to the enrolment in the
meantime**. Refund somebody and an outstanding URL they already hold keeps
working until it expires. Tightening that would mean checking enrolment on
every byte-range request of every PDF page turn.

## Where `pdf` is registered

A lesson kind has to be declared in more places than is comfortable, and a
missing one is invisible in review:

- `_sikshya_lesson_type` value `pdf`
- meta: `_sikshya_lesson_pdf_id`, `_sikshya_lesson_pdf_url`,
  `_sikshya_lesson_pdf_hide_download` — all registered for REST, which is the
  trap audio lessons fell into (the renderer worked; the meta was unregistered,
  so nothing could author one)
- allowlists: `LessonService::createLesson()`, `CourseCurriculumActions`
- admin: lesson editor picker, Add-content picker (`lesson_pdf`), lessons list
  filter and column, course-builder badge
- glyph: `icons.json` `curriculumLessonPdf`, `learn-icons.php` `pdf`,
  `sikshya_curriculum_outline_row_type_icon_html()` `pdf` — one test asserts
  the last two draw the same paths
- outline label: `CurriculumOutlineMeta`

## Tests

`--group pdf_lessons`, 42 tests in `tests/Integration/PdfLessonIntegrationTest.php`.
Mutation tested: 31 mutants, 29 caught; the two survivors are
behaviour-equivalent (each is subsumed by a neighbouring guard, and the
combined mutation of the pair is caught).

## Not claimed

- **No page count, no bookmarks, no per-page progress.** The lesson is complete
  or it is not; we do not know which page anyone is on. Reading progress would
  mean shipping a JavaScript PDF renderer and owning its security surface.
- **No annotations, highlighting or notes on the document.** The lesson's
  existing Notes panel is beside the viewer, not in it.
- **No copy or print prevention.** It is not possible in a browser, and the UI
  does not pretend otherwise.
- **No conversion.** A .docx or .pptx is not turned into a PDF; upload a PDF.
- **No inline delivery for any other type.** Images and video have their own
  lesson kinds and their own players; the allowlist exists to stay at one entry.
- **Whether the PDF itself paints is not verified, and cannot be.**
  `e2e/tests/learner/lesson-pdf.spec.ts` passes in Chromium, WebKit and
  Firefox, which
  establishes real things — the frame is present with a non-empty box (so the
  `min(78vh, 900px)` height applies in both engines), and the proxy delivers
  `inline` with working byte ranges — but a visible iframe is not a rendered
  document. Headless browsers may have no PDF viewer at all, in which case the
  frame is there and empty. That is unobservable from a test, and it is exactly
  why the link row below the viewer is permanent rather than an error state:
  the one failure mode we cannot detect is the one the links cover.
- **Not checked at phone width.** The 60vh/300px mobile cap is CSS only.
