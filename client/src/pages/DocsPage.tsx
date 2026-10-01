import { useMemo, useState } from 'react';
import { __ } from '../lib/i18n';
import { NavIcon } from '../components/NavIcon';
import type { SikshyaReactConfig } from '../types';

/**
 * In-plugin documentation.
 *
 * Everything here describes behaviour that exists in this build. Where a value
 * depends on the site (page slugs, current plan) it is read from the boot
 * config rather than hard-coded, so the docs cannot drift from the install the
 * reader is actually looking at.
 */

type Block =
  | { kind: 'p'; text: string }
  | { kind: 'ul'; items: string[] }
  | { kind: 'steps'; items: string[] }
  | { kind: 'note'; text: string }
  | { kind: 'code'; text: string }
  | { kind: 'table'; head: string[]; rows: string[][] };

type Topic = { id: string; title: string; blocks: Block[] };
type Section = { id: string; title: string; icon: string; topics: Topic[] };

function buildSections(cfg: SikshyaReactConfig): Section[] {
  const lic = (cfg as any)?.licensing ?? {};
  const tierLabel: string = lic?.siteTierLabel || __('Free', 'sikshya');
  const isPro: boolean = Boolean(lic?.isProActive);
  const perma = (cfg as any)?.permalinks ?? {};
  const slug = (key: string, fallback: string) =>
    typeof perma?.[key] === 'string' && perma[key] ? perma[key] : fallback;

  const account = slug('account', 'my-learning');
  const learn = slug('learn', 'learn');
  const cart = slug('cart', 'cart');
  const checkout = slug('checkout', 'checkout');
  const login = slug('login', 'login');
  const courseBase = slug('course', 'courses');

  return [
    {
      id: 'getting-started',
      title: __('Getting started', 'sikshya'),
      icon: 'sparkles',
      topics: [
        {
          id: 'what-it-does',
          title: __('What Sikshya does', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Sikshya turns WordPress into a learning platform. You build courses from chapters of lessons, quizzes and assignments, learners enrol and work through them in a distraction-free player, and you can charge for access using the built-in checkout.',
                'sikshya'
              ),
            },
            {
              kind: 'note',
              text: __(
                'Sikshya does not create any WordPress pages. Cart, checkout, login, the learner dashboard and the course player are served directly by the plugin at their own URLs — there is nothing to create or assign.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'first-run',
          title: __('Your first course', 'sikshya'),
          blocks: [
            {
              kind: 'steps',
              items: [
                __('Open Courses and create a course. Give it a title and save.', 'sikshya'),
                __('In the course builder, add a chapter, then add lessons, quizzes or assignments inside it. Content must live in a chapter to appear in the player.', 'sikshya'),
                __('Set a price under Pricing & access, or leave it free.', 'sikshya'),
                __('Publish the course. It appears in your catalog immediately.', 'sikshya'),
                __('Enrol a test account and walk the course yourself before announcing it.', 'sikshya'),
              ],
            },
            {
              kind: 'note',
              text: __(
                'Lessons, quizzes and assignments have no public URLs of their own by design. They are reachable only inside the course player, so learners cannot jump past your paywall by guessing an address.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'pages',
          title: __('Where your learner-facing pages live', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __('These addresses come from your current permalink settings:', 'sikshya'),
            },
            {
              kind: 'table',
              head: [__('Page', 'sikshya'), __('Address', 'sikshya')],
              rows: [
                [__('Course catalog', 'sikshya'), `/${courseBase}/`],
                [__('Learner dashboard', 'sikshya'), `/${account}/`],
                [__('Course player', 'sikshya'), `/${learn}/`],
                [__('Cart', 'sikshya'), `/${cart}/`],
                [__('Checkout', 'sikshya'), `/${checkout}/`],
                [__('Sign in', 'sikshya'), `/${login}/`],
              ],
            },
            {
              kind: 'note',
              text: __(
                'These require pretty permalinks. On Plain permalinks they fall back to query-string addresses, which work but look broken and cannot be shared. Settings → Permalinks, pick anything other than Plain, and save.',
                'sikshya'
              ),
            },
          ],
        },
      ],
    },
    {
      id: 'free-features',
      title: __('What the free plugin includes', 'sikshya'),
      icon: 'bookOpen',
      topics: [
        {
          id: 'free-core',
          title: __('Included at no cost', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'The free plugin is a complete LMS. There is no cap on courses, lessons, learners or earnings.',
                'sikshya'
              ),
            },
            {
              kind: 'ul',
              items: [
                __('Course builder with chapters and drag-and-drop ordering', 'sikshya'),
                __('Video, text and audio lessons, plus lesson attachments', 'sikshya'),
                __('Quizzes with the core question types', 'sikshya'),
                __('Assignments with essay, URL and file-upload submissions', 'sikshya'),
                __('Course certificates from the bundled templates', 'sikshya'),
                __('Native checkout with offline payment, PayPal and Stripe', 'sikshya'),
                __('Basic coupons', 'sikshya'),
                __('Manual enrolment, and free-course self-enrolment', 'sikshya'),
                __('Learner dashboard with progress and achievements', 'sikshya'),
                __('Announcements, course preview, wishlist and basic reports', 'sikshya'),
                __('Blocks and shortcodes for courses, sign-in and registration', 'sikshya'),
              ],
            },
            {
              kind: 'note',
              text: __(
                'Taking card payments through Stripe is part of the free plugin. You do not need a paid plan to charge for a course.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'shortcodes',
          title: __('Blocks and shortcodes', 'sikshya'),
          blocks: [
            {
              kind: 'ul',
              items: [
                __('Sikshya Courses — a grid or list of published courses', 'sikshya'),
                __('Sikshya Login — a sign-in form that keeps errors on the same page', 'sikshya'),
                __('Sikshya Registration — creates a student account, optionally recording an instructor application', 'sikshya'),
              ],
            },
            {
              kind: 'p',
              text: __('Each block has a matching shortcode with the same options:', 'sikshya'),
            },
            {
              kind: 'code',
              text:
                '[sikshya_courses per_page="12" view="grid" category="web-design"]\n' +
                '[sikshya_login redirect_to="/' + account + '/"]\n' +
                '[sikshya_registration type="student"]',
            },
          ],
        },
      ],
    },
    {
      id: 'pro-features',
      title: __('What a paid plan adds', 'sikshya'),
      icon: 'puzzle',
      topics: [
        {
          id: 'plans',
          title: __('The three plans', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: isPro
                ? __('This site is on the %s plan.', 'sikshya').replace('%s', tierLabel)
                : __('This site is on the free plugin. The plans below are optional.', 'sikshya'),
            },
            {
              kind: 'table',
              head: [__('Plan', 'sikshya'), __('Adds', 'sikshya')],
              rows: [
                [__('Starter', 'sikshya'), __('Content drip, course reviews, prerequisites, learner calendar, instructor dashboard', 'sikshya')],
                [__('Growth', 'sikshya'), __('Subscriptions, multi-instructor, advanced certificates, gradebook, discussions, advanced quiz types, SCORM/H5P, social sign-in', 'sikshya')],
                [__('Scale', 'sikshya'), __('Marketplace and multivendor, webhooks, OAuth API keys, white-label, multilingual, multisite, enterprise reports, email marketing', 'sikshya')],
              ],
            },
            {
              kind: 'note',
              text: __(
                'Every paid capability is enforced on the server. Turning an add-on on without the matching plan will not unlock it — requests are refused with a clear "plan required" response rather than quietly failing.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'addons',
          title: __('Turning add-ons on', 'sikshya'),
          blocks: [
            {
              kind: 'steps',
              items: [
                __('Install and activate the Sikshya Pro plugin alongside the free plugin.', 'sikshya'),
                __('Enter your key under License.', 'sikshya'),
                __('Open Addons and switch on the ones you want. Add-ons above your plan show what they need and link to the upgrade.', 'sikshya'),
                __('Configure each add-on from its own entry in the sidebar, or under Settings.', 'sikshya'),
              ],
            },
          ],
        },
      ],
    },
    {
      id: 'configuration',
      title: __('Configuration', 'sikshya'),
      icon: 'cog',
      topics: [
        {
          id: 'commerce',
          title: __('Taking payment', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Settings → Commerce holds your currency and payment methods. Three are available in the free plugin:',
                'sikshya'
              ),
            },
            {
              kind: 'ul',
              items: [
                __('Offline — bank transfer, cash or invoice. On by default. The order is recorded and you mark it paid yourself.', 'sikshya'),
                __('PayPal — off by default; needs your PayPal credentials.', 'sikshya'),
                __('Stripe — off by default; needs your Stripe secret key.', 'sikshya'),
              ],
            },
            {
              kind: 'note',
              text: __(
                'If a course has a price and every method is switched off, learners reach checkout and cannot pay. Sikshya warns you in the dashboard when that happens.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'permalinks',
          title: __('Changing page addresses', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Settings → Permalinks (inside Sikshya) renames any of the learner-facing addresses — for example changing the dashboard from my-learning to dashboard. Sikshya refreshes the URL rules for you when you save.',
                'sikshya'
              ),
            },
            {
              kind: 'note',
              text: __(
                'Avoid giving a WordPress page the same address as one of these. If a page and a Sikshya address collide, the result is confusing for learners.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'uninstall',
          title: __('What happens if you uninstall', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'By default, deleting the plugin leaves your data alone — courses, learners, enrolments and orders all stay. Nothing is erased unless you explicitly ask for it.',
                'sikshya'
              ),
            },
            {
              kind: 'p',
              text: __(
                'If you switch on "erase data on uninstall" in Settings, deleting the plugin removes course content, Sikshya tables and Sikshya options. That cannot be undone, so take a backup first.',
                'sikshya'
              ),
            },
          ],
        },
      ],
    },
    {
      id: 'how-to',
      title: __('How to…', 'sikshya'),
      icon: 'clipboardList',
      topics: [
        {
          id: 'sell-course',
          title: __('Sell a course', 'sikshya'),
          blocks: [
            {
              kind: 'steps',
              items: [
                __('Settings → Commerce: set your currency and switch on at least one payment method.', 'sikshya'),
                __('Open the course, set a price under Pricing & access, and publish.', 'sikshya'),
                __('Optionally mark one lesson as a free preview so visitors can sample it.', 'sikshya'),
                __('Test the full purchase yourself with a non-admin account before announcing.', 'sikshya'),
              ],
            },
          ],
        },
        {
          id: 'free-course',
          title: __('Give a course away free', 'sikshya'),
          blocks: [
            {
              kind: 'steps',
              items: [
                __('Leave the price empty, or set the course type to free.', 'sikshya'),
                __('Publish. Learners get an "Enrol for free" button and join in one click — no checkout.', 'sikshya'),
              ],
            },
          ],
        },
        {
          id: 'enrol-manually',
          title: __('Enrol someone yourself', 'sikshya'),
          blocks: [
            {
              kind: 'steps',
              items: [
                __('Open Enrollments and choose Add enrolment.', 'sikshya'),
                __('Pick the learner and the course. They get access immediately, with no payment taken.', 'sikshya'),
              ],
            },
            {
              kind: 'p',
              text: __('Use this for offline payments, comped seats and support fixes.', 'sikshya'),
            },
          ],
        },
        {
          id: 'send-instructor',
          title: __('Send instructors to their dashboard after sign-in', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'By default Sikshya returns people to the page they came from. To route by role, add this to your child theme or a snippets plugin:',
                'sikshya'
              ),
            },
            {
              kind: 'code',
              text:
                "add_filter('sikshya_auth_redirect_to', function ($url, $user, $context) {\n" +
                "    if (in_array('sikshya_instructor', (array) $user->roles, true)) {\n" +
                "        return home_url('/" + account + "/instructor/');\n" +
                '    }\n' +
                '    return $url;\n' +
                '}, 10, 3);',
            },
            {
              kind: 'note',
              text: __(
                'This covers the Sikshya sign-in form and registration. With Sikshya Pro it also covers social sign-in.',
                'sikshya'
              ),
            },
          ],
        },
      ],
    },
    {
      id: 'troubleshooting',
      title: __('Troubleshooting', 'sikshya'),
      icon: 'helpCircle',
      topics: [
        {
          id: 'pages-404',
          title: __('Sikshya pages return "Not Found" (404)', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Sikshya pages are URL rules (WordPress calls these rewrite rules) rather than WordPress pages. Another plugin rewriting or flushing those rules can drop Sikshya\'s, and every Sikshya address then returns a 404 Not Found.',
                'sikshya'
              ),
            },
            {
              kind: 'steps',
              items: [
                __('Sikshya shows a dashboard warning with a Refresh URL rules button when it detects this — use it.', 'sikshya'),
                __('Otherwise open Settings → Permalinks and press Save Changes once.', 'sikshya'),
                __('Check you are not on Plain permalinks.', 'sikshya'),
              ],
            },
          ],
        },
        {
          id: 'cannot-enrol',
          title: __('Learners cannot sign up or enrol', 'sikshya'),
          blocks: [
            {
              kind: 'ul',
              items: [
                __('Check Settings → General → Membership has "Anyone can register" ticked, otherwise no account can be created.', 'sikshya'),
                __('Check the course is published. Draft and pending courses refuse enrolment on purpose.', 'sikshya'),
                __('For a paid course, enrolment goes through checkout — a learner cannot self-enrol past the price.', 'sikshya'),
              ],
            },
          ],
        },
        {
          id: 'quiz-blank',
          title: __('A quiz looks empty to learners', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'A quiz with no questions tells the learner it is not ready yet. Add questions to the quiz and it will show the start screen instead.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'addon-locked',
          title: __('An add-on will not switch on', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Add-ons above your plan stay locked and say which plan they need. Check your key is active under License. If you have just upgraded, re-check the licence so the new plan is picked up.',
                'sikshya'
              ),
            },
          ],
        },
      ],
    },
    {
      id: 'faq',
      title: __('FAQ', 'sikshya'),
      icon: 'questionMarkCircle',
      topics: [
        {
          id: 'faq-general',
          title: __('Common questions', 'sikshya'),
          blocks: [
            {
              kind: 'ul',
              items: [
                __('Is the free plugin limited? No. Courses, learners and earnings are uncapped, and card payments are included.', 'sikshya'),
                __('Do I need the Pro plugin to take payment? No. Offline, PayPal and Stripe all work in the free plugin.', 'sikshya'),
                __('Will Sikshya create pages for me? No, and it does not need to. Its pages are served at their own addresses.', 'sikshya'),
                __('Does it work with my theme? Sikshya renders through your theme and ships its own templates, which a theme can override.', 'sikshya'),
                __('What happens to my data if I remove the plugin? Nothing is deleted unless you switch on data erasure first.', 'sikshya'),
                __('Can I move from another LMS? Course content is standard WordPress content, so it can be imported — but enrolment and progress history are specific to each LMS.', 'sikshya'),
              ],
            },
          ],
        },
      ],
    },
    {
      id: 'security-privacy',
      title: __('Security & privacy', 'sikshya'),
      icon: 'lockClosed',
      topics: [
        {
          id: 'data',
          title: __('What Sikshya stores', 'sikshya'),
          blocks: [
            {
              kind: 'ul',
              items: [
                __('Course, lesson, quiz and assignment content, as normal WordPress content.', 'sikshya'),
                __('Enrolments, progress, quiz attempts and assignment submissions, in Sikshya\'s own tables.', 'sikshya'),
                __('Orders and coupon redemptions when you sell courses.', 'sikshya'),
                __('Learner accounts, as standard WordPress users.', 'sikshya'),
              ],
            },
          ],
        },
        {
          id: 'external',
          title: __('What leaves your site', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Only what you switch on. Sikshya contacts a payment provider when you enable one and a learner pays, and the licence server when you activate a paid key. Card details are handled by the provider and never stored on your site.',
                'sikshya'
              ),
            },
            {
              kind: 'note',
              text: __(
                'Usage statistics are opt-in. Nothing is sent unless you turn them on.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'permissions',
          title: __('Who can do what', 'sikshya'),
          blocks: [
            {
              kind: 'table',
              head: [__('Role', 'sikshya'), __('Can', 'sikshya')],
              rows: [
                [__('Administrator', 'sikshya'), __('Everything, including settings and licensing', 'sikshya')],
                [__('Instructor', 'sikshya'), __('Create and manage their own courses and learners', 'sikshya')],
                [__('Assistant', 'sikshya'), __('Edit course content, but not publish or delete', 'sikshya')],
                [__('Auditor', 'sikshya'), __('Read reports only, change nothing', 'sikshya')],
                [__('Student', 'sikshya'), __('Enrol, learn, take quizzes, submit assignments', 'sikshya')],
              ],
            },
          ],
        },
      ],
    },
    {
      id: 'developers',
      title: __('For developers', 'sikshya'),
      icon: 'bolt',
      topics: [
        {
          id: 'hooks',
          title: __('Useful filters', 'sikshya'),
          blocks: [
            {
              kind: 'table',
              head: [__('Filter', 'sikshya'), __('Purpose', 'sikshya')],
              rows: [
                ['sikshya_auth_redirect_to', __('Where a visitor lands after signing in or registering. Runs after authentication, so roles are available.', 'sikshya')],
                ['sikshya_rest_me_enroll_allowed', __('Whether self-enrolment is permitted for a paid course.', 'sikshya')],
                ['sikshya_all_dynamic_css', __('Modify the dynamic CSS Sikshya prints on the front end.', 'sikshya')],
                ['sikshya_get_recommanded_plugins', __('Adjust the recommended plugin list.', 'sikshya')],
              ],
            },
          ],
        },
        {
          id: 'rest',
          title: __('REST API', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Sikshya exposes its functionality under the sikshya/v1 namespace. Every route declares a permission check; learner routes are scoped to the signed-in user, and management routes require the matching capability.',
                'sikshya'
              ),
            },
            {
              kind: 'code',
              text:
                'GET  /wp-json/sikshya/v1/me/progress?course_id=123\n' +
                'POST /wp-json/sikshya/v1/me/enroll      { "course_id": 123 }',
            },
            {
              kind: 'note',
              text: __(
                'Authenticate browser requests with a REST nonce, and server-to-server requests with an application password or, on the Scale plan, an OAuth API key.',
                'sikshya'
              ),
            },
          ],
        },
        {
          id: 'templates',
          title: __('Overriding templates', 'sikshya'),
          blocks: [
            {
              kind: 'p',
              text: __(
                'Copy a template from the plugin\'s templates directory into a sikshya folder in your child theme, keeping the same path. Sikshya loads yours instead.',
                'sikshya'
              ),
            },
          ],
        },
      ],
    },
  ];
}

function BlockView({ block }: { block: Block }) {
  switch (block.kind) {
    case 'p':
      return <p className="text-sm leading-6 text-slate-600 dark:text-slate-300">{block.text}</p>;
    case 'ul':
      return (
        <ul className="list-disc space-y-1.5 pl-5 text-sm leading-6 text-slate-600 dark:text-slate-300">
          {block.items.map((i, n) => (
            <li key={n}>{i}</li>
          ))}
        </ul>
      );
    case 'steps':
      return (
        <ol className="list-decimal space-y-1.5 pl-5 text-sm leading-6 text-slate-600 dark:text-slate-300">
          {block.items.map((i, n) => (
            <li key={n}>{i}</li>
          ))}
        </ol>
      );
    case 'note':
      return (
        <div className="rounded-lg border border-sky-200 bg-sky-50/70 px-3.5 py-2.5 text-sm leading-6 text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100">
          {block.text}
        </div>
      );
    case 'code':
      return (
        <pre className="overflow-x-auto rounded-lg bg-slate-900 px-3.5 py-3 text-xs leading-5 text-slate-100 dark:bg-slate-950">
          <code>{block.text}</code>
        </pre>
      );
    case 'table':
      return (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
              <tr>
                {block.head.map((h, n) => (
                  <th key={n} className="px-3.5 py-2 font-semibold">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
              {block.rows.map((r, n) => (
                <tr key={n}>
                  {r.map((c, m) => (
                    <td
                      key={m}
                      className={
                        m === 0
                          ? 'px-3.5 py-2 font-medium text-slate-800 dark:text-slate-100'
                          : 'px-3.5 py-2 text-slate-600 dark:text-slate-300'
                      }
                    >
                      {c}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      );
    default:
      return null;
  }
}

function topicText(t: Topic): string {
  const parts: string[] = [t.title];
  for (const b of t.blocks) {
    if (b.kind === 'p' || b.kind === 'note' || b.kind === 'code') parts.push(b.text);
    if (b.kind === 'ul' || b.kind === 'steps') parts.push(b.items.join(' '));
    if (b.kind === 'table') parts.push(b.head.join(' '), b.rows.map((r) => r.join(' ')).join(' '));
  }
  return parts.join(' ').toLowerCase();
}

export function DocsPage({ config }: { config: SikshyaReactConfig; embedded?: boolean; title?: string }) {
  const sections = useMemo(() => buildSections(config), [config]);
  const [activeId, setActiveId] = useState(sections[0]?.id ?? '');
  const [query, setQuery] = useState('');

  const q = query.trim().toLowerCase();

  const matches = useMemo(() => {
    if (!q) return null;
    const out: { section: Section; topics: Topic[] }[] = [];
    for (const s of sections) {
      const topics = s.topics.filter((t) => topicText(t).includes(q));
      if (topics.length) out.push({ section: s, topics });
    }
    return out;
  }, [q, sections]);

  const active = sections.find((s) => s.id === activeId) ?? sections[0];

  return (
    <div className="flex flex-col gap-5 lg:flex-row">
      <nav aria-label={__('Documentation sections', 'sikshya')} className="lg:w-64 lg:shrink-0">
        <label className="sr-only" htmlFor="sikshya-docs-search">
          {__('Search documentation', 'sikshya')}
        </label>
        <input
          id="sikshya-docs-search"
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={__('Search documentation…', 'sikshya')}
          className="mb-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:ring-slate-700"
        />
        <ul className="space-y-0.5">
          {sections.map((s) => {
            const on = !q && s.id === active?.id;
            return (
              <li key={s.id}>
                <button
                  type="button"
                  onClick={() => {
                    setQuery('');
                    setActiveId(s.id);
                  }}
                  aria-current={on ? 'true' : undefined}
                  className={
                    'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition-colors ' +
                    (on
                      ? 'bg-slate-900 font-medium text-white dark:bg-slate-100 dark:text-slate-900'
                      : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800')
                  }
                >
                  <NavIcon name={s.icon} className="h-4 w-4 shrink-0" />
                  <span className="min-w-0 flex-1 truncate">{s.title}</span>
                </button>
              </li>
            );
          })}
        </ul>
      </nav>

      <div className="min-w-0 flex-1">
        {matches ? (
          <div className="space-y-6">
            <p className="text-sm text-slate-500 dark:text-slate-400">
              {matches.reduce((n, m) => n + m.topics.length, 0) === 0
                ? __('Nothing matched that search.', 'sikshya')
                : __('Search results', 'sikshya')}
            </p>
            {matches.map(({ section, topics }) => (
              <section key={section.id} className="space-y-4">
                <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  {section.title}
                </h2>
                {topics.map((t) => (
                  <article
                    key={t.id}
                    className="space-y-3 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                  >
                    <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">{t.title}</h3>
                    {t.blocks.map((b, n) => (
                      <BlockView key={n} block={b} />
                    ))}
                  </article>
                ))}
              </section>
            ))}
          </div>
        ) : (
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{active?.title}</h2>
            {active?.topics.map((t) => (
              <article
                key={t.id}
                className="space-y-3 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
              >
                <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">{t.title}</h3>
                {t.blocks.map((b, n) => (
                  <BlockView key={n} block={b} />
                ))}
              </article>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
