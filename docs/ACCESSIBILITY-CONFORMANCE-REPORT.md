# Accessibility Conformance Report — Sikshya LMS

**Status: draft, automated and partial-manual evidence only.** This is not yet
a signed VPAT®. It reports what has been tested and what has not, so that a
buyer can see the difference.

- **Product:** Sikshya LMS (free plugin) with Sikshya Pro add-ons
- **Version evaluated:** 1.0.15 / Pro 1.0.11 (unreleased at the time of writing)
- **Standard:** WCAG 2.1 Level AA
- **Date:** 2026-10-27
- **Evaluation method:** axe-core 4.14 driven through Playwright against a live
  WordPress site, over 43 admin screens and 13 learner-facing pages; a static
  source check for unassociated form labels; and scripted keyboard tests for
  dialog focus behaviour. Scanning is scoped to Sikshya's own markup.
- **Not part of the evaluation:** any assistive-technology session. No screen
  reader has been used. See *Scope and limits* below.

## Scope

**In scope.** The React admin (all 43 screens), the course archive, a single
course page, cart, checkout, registration, the six account pages, the learn
shell and a lesson inside it. Pro add-on UI is covered where it renders inside
those surfaces — the Discussion tab in the learner chrome, for instance.

**Out of scope.** The surrounding WordPress admin chrome and the site's theme,
which the plugin does not control; Pro admin screens that do not appear in the
free plugin's shell; email templates and PDF certificates.

## Conformance by success criterion

Only criteria with evidence are listed as supported. Everything an automated
rule set cannot decide is listed as **Not evaluated** rather than assumed.

| Criterion | Level | Conformance | Evidence / remarks |
| --- | --- | --- | --- |
| 1.1.1 Non-text Content | A | Supports | `image-alt`, `button-name`, `link-name`, `aria-command-name`, `input-image-alt` pass on all 56 scanned pages. Decorative glyphs carry `aria-hidden`. |
| 1.3.1 Info and Relationships | A | Supports | `label`, `select-name`, `definition-list`, `list`, `listitem`, `aria-required-children`, `aria-required-parent`, `th-has-data-cells` pass. Backed by `npm run a11y:labels`, which also covers fields the scan cannot reach. |
| 1.3.2 Meaningful Sequence | A | Not evaluated | Needs a screen-reader read-through. |
| 1.3.4 Orientation | AA | Not evaluated | |
| 1.3.5 Identify Input Purpose | AA | Partially supports | Checkout sets `autocomplete` per field (`email`, `tel`, `country-name`, `address-line1/2`, `address-level2`). Not audited elsewhere. |
| 1.4.1 Use of Colour | A | Not evaluated | Status is conveyed with colour *and* text in the surfaces reviewed, but this has not been checked systematically. |
| 1.4.3 Contrast (Minimum) | AA | Supports | `color-contrast` passes on all scanned pages. Four token-level failures were fixed (3.76:1, 3.57:1, 2.4:1, 2.15:1). **Exception:** sale and featured badges are fixed by colour arithmetic but not covered by a scan fixture. |
| 1.4.4 Resize Text | AA | Not evaluated | |
| 1.4.10 Reflow | AA | Not evaluated | No 400%-zoom or 320px-width pass. |
| 1.4.11 Non-text Contrast | AA | Partially supports | Component and focus-indicator contrast is not covered by the scanned rule set. |
| 1.4.12 Text Spacing | AA | Supports | `avoid-inline-spacing` passes. |
| 1.4.13 Content on Hover or Focus | AA | Not evaluated | |
| 2.1.1 Keyboard | A | Partially supports | Dialog operation is verified by script, including a scrollable region made focusable. The course builder's drag-and-drop curriculum is **unverified** and is the likeliest keyboard dead end. |
| 2.1.2 No Keyboard Trap | A | Supports | Both dialogs trap Tab *and* release it on Escape, with focus returned to the opener. Covered by `keyboard-and-focus.spec.ts`. |
| 2.1.4 Character Key Shortcuts | A | Supports | The only shortcut (⌘/Ctrl+K) requires a modifier and is declared with `aria-keyshortcuts`. |
| 2.4.1 Bypass Blocks | A | Supports | The learner shell and account pages render a skip link. |
| 2.4.2 Page Titled | A | Not evaluated | A scan scoped to our own markup never runs page-level rules, so this is not evidenced here. |
| 2.4.3 Focus Order | AA | Partially supports | Dialogs verified. Page-level tab order not audited. |
| 2.4.4 Link Purpose (In Context) | A | Supports | `link-name` passes; untitled records now render "(no title)" instead of an empty link. |
| 2.4.6 Headings and Labels | AA | Partially supports | Every control has a label; heading *order* is a `best-practice` rule and is not gated. |
| 2.4.7 Focus Visible | AA | Not evaluated | Focus styles exist throughout but have not been verified per component. |
| 2.5.3 Label in Name | A | Supports | `label-content-name-mismatch` passes. Four violations were fixed, including the global search button and the course pickers. |
| 3.1.1 Language of Page | A | Not evaluated | WordPress sets `lang` on `<html>`, but the scoped scan does not test the document element, so it is not claimed from this evidence. |
| 3.2.1 On Focus / 3.2.2 On Input | A | Not evaluated | |
| 3.3.1 Error Identification | A | Partially supports | Errors are shown in text; their programmatic association is not audited. |
| 3.3.2 Labels or Instructions | A | Supports | See 1.3.1. |
| 4.1.2 Name, Role, Value | A | Supports | The full `aria-*` rule set passes, including `aria-progressbar-name`, `aria-valid-attr-value`, `aria-allowed-attr` and `aria-hidden-focus`. |
| 4.1.3 Status Messages | AA | Partially supports | Toasts and status strips use `role="status"`/`aria-live`; not audited exhaustively. |

## Scope and limits

1. **No assistive technology was used.** Automated rules find missing names and
   broken structure. They cannot tell you whether a screen is *usable* when
   read aloud. Until a screen-reader pass happens, no criterion that depends on
   comprehension is claimed.
2. **A scan sees a screen's landing state.** Content behind a tab, an accordion
   or an unopened modal is not scanned. The static label check exists to cover
   part of that blind spot; it does not cover all of it.
3. **The theme is not ours.** On the front end, a site's own theme supplies
   headings, landmarks and often colours. A Sikshya page can conform while the
   theme around it does not.
4. **This report moves with the code.** The gates run in the test suite, so a
   regression fails a build rather than quietly ageing this document — but the
   *Not evaluated* rows will stay that way until someone does the manual work.

## Next steps to a signed VPAT

In the order that removes the most uncertainty per hour: a manual keyboard pass
over the course builder; a screen-reader pass (NVDA + VoiceOver) over one full
authoring flow and one full learning flow; 400% zoom and 320px reflow; then
focus-visible and non-text contrast per component.
