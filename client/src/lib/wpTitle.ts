/**
 * WordPress `title.rendered` decoder.
 *
 * SECURITY: WP REST returns `title.rendered` as HTML — it's the output
 * of the `the_title` filter, which decodes some entities but does NOT
 * sanitise tags. Feeding that value into `dangerouslySetInnerHTML` opens
 * a stored-XSS path: any post-title that survives `wp_filter_post_kses`
 * (`<a>`, `<img>`, `<span>`, etc.) executes when an admin opens the
 * list page. On Sikshya's course/lesson/quiz/etc. CPTs, `capability_type`
 * is `post`, so any Author-level user (instructor) can craft such a
 * title — an admin viewing Courses then runs
 * `fetch('/wp-json/wp/v2/users/me?_fields=meta')` from the attacker's
 * inline handler under the admin's cookie.
 *
 * `decodeWpTitle` parses via `DOMParser` (no script execution) and
 * returns only `textContent`, so any HTML in the title is stripped down
 * to its visible text without ever entering the DOM as live markup.
 * Callers should render the returned string as text: `<span>{title}</span>`,
 * never as `dangerouslySetInnerHTML`.
 */
export function decodeWpTitle(rendered: unknown): string {
  if (typeof rendered !== 'string') {
    return '';
  }
  if (rendered === '') {
    return '';
  }
  // Guard against SSR / non-DOM environments (defensive; the admin app
  // always runs client-side, but tests may import this file in Node).
  if (typeof DOMParser === 'undefined') {
    return rendered.replace(/<[^>]*>/g, '');
  }
  try {
    const doc = new DOMParser().parseFromString(rendered, 'text/html');
    return doc.body?.textContent ?? '';
  } catch {
    return rendered.replace(/<[^>]*>/g, '');
  }
}
