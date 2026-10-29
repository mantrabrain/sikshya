# Accessibility

Sikshya holds itself to **WCAG 2.1 Level AA**, enforced by gates that run with
the rest of the test suite rather than by a one-off audit.

This document is for two readers: whoever has to answer an accessibility
question from a buyer, and whoever is about to change the admin UI or a
learner template.

## What a plugin can and cannot be held to

A plugin owns its own markup. The page around it belongs to WordPress core —
the admin chrome, the admin bar, core notices — or to whatever theme the site
runs, and a violation there is neither ours to fix nor ours to be blamed for.

So the browser gate scans **only Sikshya's own markup**: it marks the topmost
elements carrying one of our prefixes (`sikshya-`, `sik-`) and scans those.
That scoping is also the gate's main failure mode — a scan of nothing reports
zero violations and goes green — so `e2e/utils/a11y.ts` fails the test when it
finds no owned markup, and admin screens name `#sikshya-admin-root` explicitly
instead of relying on detection. Both guards exist because both mistakes
happened: an expired admin session once let 43 screens "pass" while the only
thing scanned was the login page's social-login box, and the account pages
reported clean while the detector was finding nothing but their inline SVG
icons.

## The three gates

| Gate | What it catches | Run it |
| --- | --- | --- |
| `e2e/tests/a11y/admin/` | WCAG 2.1 AA violations on all 43 admin screens | `npx playwright test --project=admin e2e/tests/a11y/admin` |
| `e2e/tests/a11y/public/` | the same, on 13 learner-facing pages | `npx playwright test --project=public e2e/tests/a11y/public` |
| `npm run a11y:labels` | `<label>`s that name no control, anywhere in the admin client | `npm run a11y:labels` |

The third runs in CI (`.github/workflows/ci.yml`), because it needs nothing but
Node. The two browser gates run with the rest of the Playwright suite.

The third exists because the first two only ever see a screen's landing state:
a field behind a tab, an accordion or a modal is never scanned. Reading the
source instead found **36** unassociated labels where the browser gate had
found two.

`e2e/tests/a11y/admin/keyboard-and-focus.spec.ts` covers what no static scan
can: that Tab stays inside an open dialog, and that closing one returns focus
to the control that opened it.

### Rules, and why there is no baseline

The gates run axe-core with `wcag2a`, `wcag2aa`, `wcag21a` and `wcag21aa`.
`best-practice` is deliberately excluded: those rules are advice, not
conformance, and failing a release on advice trains people to add exceptions
until the gate means nothing.

There is **no file of accepted violations**. A violation is either fixed or the
rule is switched off for one screen with the reason written at the call site.
A baseline file is where accessibility work goes to die.

## What this pass fixed

Each of these was found by the gates above, not by reading the code.

| Defect | What it meant for a person |
| --- | --- |
| The account menu button had no accessible name on all six account pages | A screen-reader user heard "button" and had no way to know it opened their account menu. Its only text was the name and email, which the narrow layouts hide. |
| `role="progressbar"` with no name, on the resume card and five course cards | "60%" with no indication of what is 60% done. |
| `role="tablist"` containing a plain `<button>` (Pro's Discussion tab) | The whole tab strip stopped being announced as tabs, and that tab was not announced as a tab at all. |
| Five form fields and two checkbox groups unlabelled; 36 more found statically | "Edit, blank". A form you cannot fill in without seeing it. |
| The global search button was named "Open global search" while showing "Search" | Voice control: saying "click Search" did nothing. |
| Course pickers announced their purpose instead of their value | The field said "Select a course" while a course was already chosen, so the current selection was unannounced. |
| Untitled drafts rendered an empty link in list tables | Nothing to read and nothing to click; identical rows. |
| Dialogs did not trap Tab | Focus walked out of the dialog onto the page behind the backdrop — invisible to the user, and already declared non-existent to their screen reader by `aria-modal`. |
| Dialogs did not restore focus on close | Focus dropped to the top of the document; a keyboard user tabbed all the way back to the button they had just pressed, every time. |
| A horizontally scrollable report table could not take focus | Unreachable without a trackpad. |
| Status colours failed contrast as text *and* as badge fills (3.76:1, 3.57:1, 2.4:1, 2.15:1) | Hard-to-read "Remove" links, and near-unreadable white text on sale and featured badges. |
| `--sik-acc-muted` / `--sikshya-learn-muted` (slate-500) measured 4.11–4.46:1 | All secondary copy on the account pages and in the learn shell sat just under the threshold. |
| A `<dl>` holding a form control and two paragraphs | A description list announced, then a control belonging to no term. |
| Quill's toolbar: eight unnamed icon buttons and an unnamed heading picker | The rich-text editor could not be operated without sight. |

Where colour was the problem the fix moved a token rather than editing call
sites: `--sik-acc-muted` to slate-600 and `--sik-acc-success` to emerald-700,
which are **role** tokens, so the palette scales above them keep their honest
names (`--sik-acc-slate-500` is still `#64748b`); and the public status trio
`--sikshya-error/warning/success` to `#dc2626 / #b45309 / #047857`, which are
the palette itself — they are used both as text and as badge fills, so each has
to clear 4.5:1 against white in both directions.

## Not yet evaluated

Stated plainly, because an accessibility claim is worth exactly as much as its
caveats:

- **No manual screen-reader pass.** Nothing here has been driven with NVDA,
  JAWS or VoiceOver. Automated rules catch missing names and bad structure;
  they cannot tell you whether a screen makes sense when read aloud.
- **No keyboard pass beyond dialogs.** The focus-trap spec covers two dialogs.
  Tab order, focus visibility and keyboard operation of the course builder's
  drag-and-drop curriculum are unverified — and a drag-and-drop list is the
  likeliest place to find a keyboard dead end.
- **Zoom and reflow (1.4.10), 400% zoom, and Windows High Contrast** are
  untested.
- **Sale and featured badge contrast** is fixed by colour arithmetic, not by a
  scan: reaching those badges needs a sale-priced course, which the gate has no
  fixture for.
- **Pro's own screens** are scanned only where they appear inside the free
  plugin's admin shell and learner templates.

## If you are adding UI

1. Every `<label>` gets `htmlFor`, or wraps its control. `npm run a11y:labels`
   will tell you; it is cheaper than the browser gate.
2. A set of checkboxes is a group (`role="group"` + `aria-labelledby`), not a
   `<label>`.
3. An accessible name must **contain the visible text** (WCAG 2.5.3). If a
   control shows a value, name it with purpose *and* value, the way a native
   `<select>` is announced.
4. Anything with `role="progressbar"`, `role="region"` or `role="dialog"` needs
   a name on the element that carries the role — not on its wrapper.
5. Icon-only controls need `aria-label`; decorative glyphs need `aria-hidden`.
   A glyph that is on screen must not be hidden *and* excluded from the name,
   or the name stops matching what is visible.
6. New dialogs should use `components/shared/Modal.tsx`, which handles Escape,
   the focus trap and focus restoration. Hand-rolled dialogs need
   `lib/focusTrap.ts`.
7. If a field lets an author add an image that **carries meaning**, the save is
   refused without a description — not warned about. Question media works this
   way (`docs/QUESTION-MEDIA.md`): the image is the question, so an undescribed
   one is a question some learners cannot attempt at all. Where enforcement
   cannot reach — a REST write, an importer, a row older than the check — the
   renderer marks the image decorative rather than reading out its filename.
