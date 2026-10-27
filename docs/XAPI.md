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

## Limits, stated plainly

- **This is not cmi5.** cmi5 is a *launch* specification on top of xAPI:
  packaged content, a course structure XML, launch URLs, AU sessions and the
  State API. Sending xAPI statements is a prerequisite for it, not a subset of
  it. Sikshya does not package, launch or track cmi5 content, and saying "xAPI"
  in a tender does not answer a cmi5 question.
- **Outbound only.** Sikshya has no LRS of its own: it does not accept, store
  or query statements, and there is no Statement, State, Activity Profile or
  Agent Profile API to read from. Your LRS is the system of record.
- **No attachments, no signed statements, no voiding.** A statement sent in
  error stays in the LRS; void it there.
- **One statement per request.** Batching several into one POST would be
  faster and is a later optimisation; per-statement retry semantics are
  clearer without it.
- **No per-question statements.** A quiz reports one `passed`/`failed` with a
  score, not an `answered` statement per question. Item-level analytics in the
  LRS would need that, and it multiplies statement volume by the number of
  questions — which an LRS usually charges for.
- **Conformance language.** The statements Sikshya produces are valid xAPI
  1.0.3. There is no *LRS* conformance claim to make here, because Sikshya is
  not an LRS, and the ADL conformance test suite tests LRSs.
