# xAPI / Learning Record Store

Sikshya can report learning to any conformant LRS as xAPI 1.0.3 statements:
enrollments, lesson and course completions, quiz results with scores,
assignment submissions and certificates.

This document is what to read before a tender answer or an integration call.
It says exactly what is sent, exactly how, and — in the last two sections —
exactly what is not.

- **Add-on:** `xapi_lrs` (Scale tier). Off until enabled, inert until credentials exist.
- **Protocol:** xAPI 1.0.3. `POST {endpoint}/statements`, Basic auth, `X-Experience-API-Version: 1.0.3`.
- **Direction:** outbound only. Sikshya reports *to* your LRS; it is not an LRS.

## What is sent

One statement per event. Nothing is sampled, aggregated or summarised.

| Sikshya event | Verb | Object | Result |
| --- | --- | --- | --- |
| Enrolled in a course | `adlnet.gov/expapi/verbs/registered` | course | — |
| Un-enrolled | `id.tincanapi.com/verb/unregistered` | course | — |
| Lesson completed | `…/verbs/completed` | lesson | `completion: true` |
| Quiz completed | `…/verbs/passed` or `…/verbs/failed` | assessment | `score.raw`, `score.scaled`, `success`, `completion` |
| Assignment submitted | `activitystrea.ms/schema/1.0/submit` | school-assignment | — |
| Course completed | `…/verbs/completed` | course | `completion: true`, `success: true` |
| Certificate earned | `id.tincanapi.com/verb/earned` | certificate | — |

ADL defines no verb for un-enrolling and none for submitting, so those two
come from the registries the rest of the ecosystem uses for them. Every verb
and activity-type IRI can be swapped for a house profile's through
`sikshya_xapi_verb_iri` and `sikshya_xapi_activity_type_iri` — a mismatched
verb is the difference between a report that groups across systems and one
that does not.

Three details that decide whether the data is usable:

- **Activity ids are derived from your site URL and the post id**
  (`https://yoursite/xapi/lesson/412`), not from permalinks. A renamed course
  or a changed permalink structure must not become a different activity in
  reporting. `sikshya_xapi_activity_iri_base` changes the namespace — only
  before a site has reported anything, because the LRS treats a changed IRI as
  a different activity and the history already stored is orphaned.
- **Lessons and quizzes carry their course in `contextActivities.parent`**, so
  an LRS can answer "what has this learner done in this course" without being
  told the structure separately.
- **`score.scaled` is a 0..1 fraction**, which is the only score an LRS can
  compare across systems; `score.raw` keeps the percentage. A quiz with no
  passing score set reports `success: true` on completion — it has no bar to
  clear, and reporting a failure there would mark every learner on it as
  having failed.

## How learners are identified

An xAPI Agent must be identified by exactly one property. Which one is a
data-protection decision, so it is a setting:

| Mode | What the LRS receives | When to choose it |
| --- | --- | --- |
| **Email (`mbox`)** | `mailto:learner@example.com` | The default, and what most LRSs expect. Recognises the same person across systems. |
| **Hashed email (`mbox_sha1sum`)** | a SHA-1 of the `mailto:` IRI | Keeps that cross-system recognition without the address ever leaving your site. |
| **Account** | a user id against your site URL | The least an LRS can be given and still track a learner. No email at all. |

Hashing is over the `mailto:` IRI, as the specification requires — a hash of
the bare address matches nothing in any other system.

`Include display names and titles` is a separate switch. With it off, a
statement carries ids only, and a transcript in the LRS is unreadable without
joining back to your site. Some reviews ask for exactly that.

A learner with no email address produces **no statement** in either email
mode, rather than silently falling back to an account id: changing how a
person is identified half way through their history splits their transcript in
the LRS.

## Delivery

Statements are queued, never sent inline. A learner pressing "mark complete"
does not wait on somebody else's LRS, and a completion survives that LRS being
down for an afternoon.

A cron pass every five minutes drains the queue under a **count and wall-clock
budget** (25 statements, 20 seconds by default; both filterable). Past either,
the run stops and schedules a follow-up. This is not a micro-optimisation: on
wp-cron every due event fires inside one visitor's page request, so a thousand
queued statements without a budget is a thousand serial HTTP POSTs in one page
load — which on shared hosting is killed part-way, leaving the backlog exactly
where it was.

**Every statement carries its own id from the moment it is queued.** That is
what makes retrying safe, and it is why the response taxonomy looks like this:

| Response | Treated as | Why |
| --- | --- | --- |
| 200, 204 | sent | — |
| **409 Conflict** | **sent** | The LRS already has this statement id. This is what a retry looks like after a timeout the LRS actually survived; retrying to the attempt limit would report a stored statement as lost. |
| 400 | failed, no retry | The LRS rejected the statement itself. Retrying sends the same bytes to the same validator. |
| 401, 403 | failed, no retry | Credentials. Retrying twenty times per statement against a production LRS is how an integration gets blocked. |
| 429, 5xx, timeouts, network errors | retry | Exponential backoff, capped at six hours, up to `max_attempts` (5 by default). |

The LRS's own error text is stored for you to read, with your key and secret
redacted out of it first — several LRSs echo the request back in a 400 body.

**Retention is off by default.** The log grows by one row per statement, and 30
or 90 days is reasonable once you trust the integration, but an update must
never delete history a site did not ask to lose. Queued, retrying and failed
rows are never pruned however old: a statement that did not reach the LRS is
the only record that it did not.

## Setting it up

1. Enable **xAPI / Learning Record Store** under Add-ons (Scale tier).
2. In its settings, paste the endpoint, key and secret your LRS gave you. The
   endpoint is the one ending in `/xAPI/` — with or without a trailing slash,
   and already ending in `/statements` is fine too.
3. Choose how learners are identified (above).
4. Sikshya → Integrations → **xAPI / Learning Record Store** → *Send a test
   statement*. It posts a real statement down the production path with the real
   credentials; a test that took a different path would tell you nothing about
   production. The panel then shows queued / retrying / accepted / failed and
   the last ten statements.

## Hooks

| Hook | Use |
| --- | --- |
| `sikshya_xapi_statement` | Last word on a statement before it is queued. |
| `sikshya_xapi_actor` | Replace the Agent — an employee number, say. |
| `sikshya_xapi_verb_iri`, `sikshya_xapi_activity_type_iri` | Follow a house profile's vocabulary. |
| `sikshya_xapi_activity_iri_base` | Change the activity namespace (before first use only). |
| `sikshya_xapi_statements_per_run`, `sikshya_xapi_time_budget` | Resize a drain run. |
| `sikshya_xapi_retry_backoff` | Change the wait before attempt N+1. |
| `sikshya_cmi5_launch_url` | Last word on a unit's launch URL. |
| `sikshya_cmi5_launch_data` | Change the `LMS.LaunchData` document handed to a unit. |
| `sikshya_cmi5_au_satisfied` | Fires after a unit is satisfied (lesson id, user id, the AU row). |

## cmi5

cmi5 is the other half: not a reporting format but a **launch** specification,
where the content arrives as a package that states its own structure and is
handed an endpoint to report to. Sikshya imports, launches and tracks cmi5
content.

### Importing a package

Sikshya → Integrations → **cmi5 packages**: choose the course, upload the zip.
`cmi5.xml` is read and the package becomes curriculum — **a chapter per block
and a lesson per assignable unit**. This is where cmi5 differs from SCORM in
how it meets an LMS: a SCORM zip is one activity whose internals stay inside
the player, while a cmi5 package states a structure and expects the LMS to
place it.

The importer refuses rather than guesses. An unknown `moveOn`, a
`masteryScore` outside 0–1 (cmi5 states it as a decimal, so `80` is refused
rather than read as a bar nobody can clear), a unit with no launch URL, a
course with no units, a structure nested beyond 20 blocks, or an XML document
with a doctype — each is a refusal with the reason, because a completion rule
Sikshya invented is a transcript that is wrong and looks fine.

Removing an imported package forgets the import record and its files. **The
lessons it created stay**, because a learner may have completed one.

### Launching a unit

A learner presses **Start**; the unit opens in the page or its own window,
depending on the package's `launchMethod`. The launch is deliberately an
action, not a page view: a cmi5 launch is an attempt that the LMS reports to
the LRS, so opening one because somebody scrolled past the lesson would fill
reporting with attempts nobody made.

**The endpoint the unit is handed is Sikshya's, not the LRS's.** Handing content
the LRS credentials is the shortest path to a working launch and the wrong one:
it puts a key with write access to the whole Learning Record Store inside a
browser, readable by any script on the page and valid long after the lesson
ends. Instead the unit talks to Sikshya, which knows which session it belongs
to and forwards its statements with credentials that never leave the server.

That also makes one guarantee possible: **a unit cannot report about anybody
else**. Whatever actor, activity or registration a statement claims, it is
stamped with the session it arrived on before being forwarded.

What the unit gets:

| cmi5 requires | Sikshya serves |
| --- | --- |
| `fetch` — a one-time credential exchange | Yes, single use, 15-minute expiry. A token in a browser history is not replayable. |
| `LMS.LaunchData` via the State API | Yes: `launchMode`, `moveOn`, `masteryScore`, `returnURL` and the context template. Read-only — a unit that could rewrite it could rewrite the rules it is judged by. |
| Somewhere to POST statements | Yes, forwarded to your LRS through the ordinary queue, keeping the unit's own statement ids so its retries still deduplicate. |
| State API for the unit's own documents | Yes, up to 256 KB per document — this is how content bookmarks, and without it a resuming unit restarts or fails. |
| Agent Profile | Answered as "no document". Nothing is stored against a learner's profile, and inventing preferences a learner never chose would be worse than none. |
| Statement *reads* | **No** — answered with 405 and a pointer to your LRS. Sikshya is a launching LMS, not an LRS. |

### When a unit counts as done

The unit reports what happened; the **package** says what counts, in its
`moveOn` rule; Sikshya holds both and decides.

| `moveOn` | Satisfied when |
| --- | --- |
| `Passed` | the unit reports `passed` |
| `Completed` | the unit reports `completed` |
| `CompletedAndPassed` | both, in any order, across any number of requests |
| `CompletedOrPassed` | either (cmi5's default when the package states nothing) |
| `NotApplicable` | immediately — it is how a package marks a reference document |

When a unit is satisfied Sikshya sends its own `satisfied` statement in the
`moveon` category, and **marks the lesson complete on the learner's
curriculum** — a unit the package calls satisfied while Sikshya still shows it
as outstanding is the complaint this path exists to prevent. The ordinary
`lesson.completed` statement is *not* also sent for a cmi5 lesson: the unit has
already reported against the package's own activity id, and a second statement
under Sikshya's lesson IRI would have an LRS counting one completion twice.

A `failed` is recorded but never un-satisfies a unit already satisfied: a
learner who passed, came back and failed has still passed it once.

### cmi5 limits

- **Course-level satisfaction is per unit, not per block.** Each AU marks its
  own lesson complete; Sikshya's own course progress then follows from its
  lessons, rather than from a block tree of cmi5 `satisfied` statements.
- **Blocks are flattened into chapters.** Sikshya's curriculum is two levels
  deep, so a unit nested three blocks down lands in a chapter named by its
  path. Nothing is dropped; the hierarchy is not reproduced exactly.
- **No `abandoned` or `waived`.** A session left open is not later reported as
  abandoned, and there is no way to waive a unit for a learner from the LMS.
- **`launchMode` is always `Normal`.** `Browse` and `Review` are not offered.
- **No entitlement keys, no `returnURL` round-trip handling** beyond handing
  the URL to the unit.
- Not an ADL-certified implementation: there is a cmi5 conformance test suite,
  and Sikshya has not been put through it.

## Limits, stated plainly
- **Still not an LRS.** Sikshya accepts statements from a launched cmi5 unit
  in order to forward them, and serves the State API that cmi5 requires — but
  it does not store a statement history, answer statement queries, or serve an
  Activity Profile. Your LRS is the system of record, and the statement read
  API is answered with a 405 saying so.
- **No attachments, no signed statements, no voiding.** A statement sent in
  error stays in the LRS; void it there.
- **One statement per request to the LRS.** Batching would be faster and is a
  later optimisation; per-statement retry semantics are clearer without it. (A
  cmi5 unit may *send* several at once; they are queued individually.)
- **No per-question statements.** A quiz reports one `passed`/`failed` with a
  score, not an `answered` statement per question. Item-level analytics in the
  LRS would need that, and it multiplies statement volume by the number of
  questions — which an LRS usually charges for.
- **Conformance language.** The statements Sikshya produces are valid xAPI
  1.0.3. There is no *LRS* conformance claim to make here, because Sikshya is
  not an LRS, and the ADL conformance test suite tests LRSs.
