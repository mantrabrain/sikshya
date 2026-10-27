import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';
import type { AxeResults, Result } from 'axe-core';

/**
 * The WCAG levels we hold ourselves to. 2.1 AA is what every public-sector
 * procurement checklist and every VPAT asks about, so that is the gate.
 *
 * `best-practice` is deliberately absent: those rules are advice, not
 * conformance, and failing a release on advice trains people to add
 * exceptions until the gate means nothing.
 */
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

/**
 * A plugin only owns its own markup. The surrounding page belongs to WordPress
 * core (admin chrome, the admin bar, core notices) or to whatever theme the
 * site runs, and a violation there is not ours to fix — nor can we fix it
 * without editing somebody else's code.
 *
 * So instead of naming every container by hand, mark the *topmost* elements
 * that carry one of our class/id prefixes and scan only those. A nested owned
 * element is already covered by its ancestor; marking it too would report the
 * same node twice.
 *
 * Both prefixes matter. The account pages are built entirely out of `sik-acc-`
 * classes, so a detector that knew only `sikshya-` found nothing there but the
 * inline SVG icons — four elements — and reported a clean pass for a screen it
 * had not looked at.
 */
const ROOT_ATTR = 'data-sikshya-a11y-root';

async function markOwnRoots(page: Page): Promise<number> {
  return page.evaluate((attr) => {
    document.querySelectorAll(`[${attr}]`).forEach((el) => el.removeAttribute(attr));

    /*
     * Two things are deliberately not roots.
     *
     * `<link>`, `<style>`, `<script>` and friends carry our ids but no
     * rendered content, so scanning them only adds empty contexts. And
     * `<body>` itself carries `sikshya-lms-admin` on admin screens, which
     * would quietly widen the scan to the whole of WordPress's admin chrome —
     * the opposite of what this function is for.
     */
    const SKIP = new Set(['HTML', 'HEAD', 'BODY', 'LINK', 'STYLE', 'SCRIPT', 'META', 'TITLE', 'TEMPLATE']);

    const PREFIXES = ['sikshya-', 'sik-'];
    const ours = (name: string) => PREFIXES.some((prefix) => name.startsWith(prefix));

    const owned = (el: Element) =>
      (el.id && ours(el.id)) || Array.from(el.classList).some(ours);

    const eligible = (el: Element) => owned(el) && !SKIP.has(el.tagName);

    const candidates = PREFIXES.flatMap((prefix) => [`[id^="${prefix}"]`, `[class*="${prefix}"]`]).join(',');

    const roots = Array.from(document.querySelectorAll<HTMLElement>(candidates))
      .filter(eligible)
      .filter((el) => {
        for (let p = el.parentElement; p; p = p.parentElement) {
          if (eligible(p)) return false;
        }
        return true;
      });

    roots.forEach((el) => el.setAttribute(attr, ''));
    return roots.length;
  }, ROOT_ATTR);
}

export interface A11yScanOptions {
  /**
   * Scan these selectors instead of auto-detecting our own markup. Use when a
   * screen's markup is injected into a core container (the block editor, for
   * instance) and so has no `sikshya-` wrapper of its own.
   */
  include?: string[];
  /** Rules to switch off for this screen, each with a reason in the spec. */
  disableRules?: string[];
}

export async function scanA11y(page: Page, options: A11yScanOptions = {}): Promise<AxeResults> {
  let include = options.include;

  if (!include) {
    const found = await markOwnRoots(page);
    // Nothing of ours on the page is a bug in the spec, not a pass: a scan of
    // an empty selector set reports zero violations and would go green.
    expect(found, 'no Sikshya-owned markup found on this page to scan').toBeGreaterThan(0);
    include = [`[${ROOT_ATTR}]`];
  }

  let builder = new AxeBuilder({ page }).withTags(WCAG_TAGS);
  include.forEach((selector) => {
    builder = builder.include(selector);
  });
  if (options.disableRules?.length) {
    builder = builder.disableRules(options.disableRules);
  }

  return builder.analyze();
}

export function formatViolations(violations: Result[]): string {
  if (violations.length === 0) return 'none';

  return violations
    .map((v) => {
      const nodes = v.nodes
        .slice(0, 5)
        .map((n) => `      - ${n.target.join(' ')}\n        ${n.failureSummary?.replace(/\n/g, '\n        ') ?? ''}`)
        .join('\n');
      const more = v.nodes.length > 5 ? `\n      …and ${v.nodes.length - 5} more node(s)` : '';
      return `  [${v.impact ?? 'n/a'}] ${v.id} — ${v.help}\n    ${v.helpUrl}\n${nodes}${more}`;
    })
    .join('\n');
}

/**
 * The gate. Fails with every violation printed, because an a11y failure that
 * only names a rule id sends the reader back to the browser to find out what
 * it was attached to.
 */
export async function expectNoA11yViolations(
  page: Page,
  label: string,
  options: A11yScanOptions = {}
): Promise<AxeResults> {
  const results = await scanA11y(page, options);

  expect(
    results.violations,
    `WCAG 2.1 AA violations on ${label}:\n${formatViolations(results.violations)}`
  ).toEqual([]);

  return results;
}
