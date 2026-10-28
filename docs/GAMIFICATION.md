# Points and the leaderboard

The `gamification` add-on (Growth tier and above) gives learners points for
things they finish, keeps a ledger of every point awarded, and can publish a
leaderboard.

**Every award amount starts at zero.** Enabling the add-on on a live site
changes nothing at all until somebody decides what a lesson is worth. That is
deliberate: the alternative is a site waking up with a scoreboard it never
designed, and learners comparing numbers nobody chose.

## What pays, and what it is keyed to

| Setting | Fires on | Paid once per |
|---|---|---|
| Points for completing a lesson | `sikshya_lesson_completed` | lesson |
| Points for passing a quiz | `sikshya_quiz_completed`, when the score reaches the quiz's passing mark | quiz |
| Points for completing an assignment | `sikshya_assignment_completed` | assignment |
| Points for completing a course | `sikshya_course_completed` | course |
| Points for enrolling in a course | `sikshya_user_enrolled` | course |
| Points for earning a certificate | `sikshya_certificate_issued` | course |

**"Once per thing" is the whole design.** Those hooks fire more than once in
normal use: a learner can un-tick and re-tick a lesson, and a quiz can be
retaken as often as its settings allow. An economy keyed to *events* is one a
learner can farm by clicking a checkbox twice. So each award carries an
idempotency key derived from the thing being rewarded — `lesson:412`, not "the
412th completion event" — and that key is `UNIQUE` in the database. The second
award is refused by the index, not by a check that two simultaneous requests
could both pass.

A quiz pays for **passing**, not for attempting: paying per attempt pays most
to the learner who understood it least.

Nothing is ever taken back. Un-completing a lesson does not remove the points
it paid, because the idempotency key already means re-completing it cannot pay
again — and a total that goes down when a learner tidies up their progress
reads as a punishment for housekeeping.

## Two tables

- `wp_sikshya_points_ledger` — append-only, one row per award: who, how many,
  what for, and the unique key.
- `wp_sikshya_points_totals` — one row per learner holding the running total.

The second is derived from the first, and kept anyway. A leaderboard is
`ORDER BY points DESC LIMIT 20`; doing that as a `SUM() GROUP BY` over the
ledger is a filesort over every point ever awarded, on a page a site may well
put in its sidebar.

The total moves only after the ledger row lands, and moves with
`points = points + N` rather than a read-then-write, so concurrent awards add
up instead of overwriting each other. Anything that drifts anyway — a row
deleted by hand, an import — is repaired by **Rebuild totals from the ledger**
on the admin screen, which is also available per learner over REST.

## Manual adjustments

Site staff can add or subtract points by hand, with a note. These are the one
kind of award with no natural idempotency key: applying the same correction
twice may be exactly what an administrator means to do, so each gets a unique
key of its own and each one lands. Adjustments are capped at 100,000 points at
a time, which is a typo guard rather than a policy.

## Privacy

A leaderboard publishes the names of real people next to a number that says
how little they have done. So:

- **It is staff-only until you widen it.** `Site staff only` is the default;
  `Signed-in learners` and `Anyone, including visitors` are deliberate choices.
- **Learners can take themselves off it.** They keep earning points and keep
  seeing their own total and place — hiding somebody from the board is not the
  same as hiding their score from them. Turning the site-wide setting off
  re-exposes them but does **not** delete their preference, so turning it back
  on restores their choice rather than losing it.
- **Names are abbreviated by default** — "Priya S." A learner with no first and
  last name on file has their display name abbreviated the same way, because a
  board that prints some people in full is not abbreviated.
- **A deleted person leaves no trace.** `sikshya_user_deprovisioned` removes
  both their totals and their ledger rows, and that listener is registered even
  when the add-on is switched off — otherwise a site that turned it off last
  month would put a departed employee back on the board the day it turned it
  on. The board query also joins to `wp_users`, so a row that somehow survives
  is still never shown.

Hidden learners leave **gaps in the numbering**: if the top learner is hidden,
the board starts at place 2. Renumbering around them would misstate everyone
else's standing.

## Where it shows up

- **Sikshya → People → Points** — the board, the manual adjustment form, the
  rebuild button, and the award amounts.
- **`[sikshya_leaderboard]`** on any page. Attributes: `scope` (`all`, `month`,
  `week`) and `limit`. It renders nothing at all to a viewer the visibility
  setting does not cover — not an error, not an empty table.
- **Account page** — the learner's own total, their place, their last five
  awards, and the switch that hides them. The panel does not appear on a site
  where nothing awards points: "0 points" there describes the configuration,
  not the learner.

## For integrators

`do_action('sikshya_points_awarded', $user_id, $points, $reason, $object_id)`
fires **only** when points were actually written — never on the repeat the
idempotency key refused.

## Not claimed

- **No badges beyond the four the free plugin already awards.** First
  enrolment, first lesson, first course and five courses live in
  `LearnerAchievementStub` and are not configurable; this add-on is points, not
  a badge designer.
- **No streaks, daily logins or login bonuses.** Those reward turning up rather
  than learning, and they need a different kind of ledger — one keyed to days
  rather than to things.
- **No spending.** Points are a score, not a currency: there is nothing to buy
  with them and no integration with coupons or discounts.
- **No per-course or per-group leaderboards.** One board for the site.
- **No level or tier names** ("Bronze", "Gold") on top of the totals.
- **No browser test.** The rules are covered by 49 PHP integration tests
  (`--group gamification`), including the rendered public HTML.
