# Memberships

A **membership level** is a named product over a subscription plan you already have:
it says which plan a learner must hold, and which courses that plan opens.

Before this existed, the same capability was configured one course at a time — set a
course's type to *subscription* and pick its required plan. That still works, and nothing
about it changed. What was missing was the product: somewhere to say "Gold is these forty
courses", a page a visitor can be sent to, and a way for a learner to see what they are
paying for.

Part of the **Subscriptions** add-on (Growth tier and above). There is no separate
add-on to enable: a membership with no way to charge for it would be a price list.

## The model

```
Plan  (wp_sikshya_plans)      price, currency, billing interval  ← the only place money lives
  ↑
Level (option: sikshya_pro_membership_levels)
        title, tagline, bullets, highlight, order
        catalogue:  all courses | course ids | category ids
        join course: the subscription-priced course people buy
```

A level never holds a price. It points at a plan, and the plan is what the recurring
gateways charge and what the subscription row records. One definition of money per site.

Levels live in one option rather than a table. A site has a handful of them, every access
check needs all of them at once, and an option read is one cached query where a table
would be a query per check. The option is not autoloaded.

## Memberships only ever add access

This is the rule the implementation is built around, and the one worth knowing before you
use it on a live site:

> A course that is in no level behaves exactly as it did before levels existed. Deleting
> every level restores the previous behaviour exactly.

Three gates decide access, and a membership can only ever say *yes* at each of them:

| Gate | Filter | What a membership does |
|---|---|---|
| Course access | `sikshya_user_has_course_access` | Grants when the learner holds a level containing the course |
| Enrolment | `sikshya_can_enroll_user_in_course` | Overturns **one** refusal — `sikshya_subscription_required` — and nothing else |
| Lesson / quiz access | `sikshya_access_check` | Same answer as the course gate, so a member is never given the course page and then locked out of every lesson in it |

The enrolment gate is the only place a membership turns a "no" into a "yes", and it is
deliberately narrow. Capacity limits, prerequisites, drip and a closed course all still
refuse a member; the refusal a membership answers is the subscription gate's own "you do
not hold this course's plan", which is precisely the question a membership exists to
answer differently.

A level in **draft** status, or one with no plan chosen, grants nothing at all.

## Buying a membership

A level is bought by buying one course: the **join course** you nominate on the level.
That course must be priced as a subscription and must charge the level's plan.

This is not an oversight. Every order line in Sikshya is a course line —
`wp_sikshya_order_items.course_id` is `NOT NULL` — and refunds, invoices, receipts,
renewal recording and the admin order screens all read that shape. Inventing a second
order shape for plan-only purchases would have put all of them at risk, on a payments
path that is already live on real sites, in exchange for a button.

In practice this reads correctly, because the join course *is* the membership product:
name it "Gold membership", set its type to subscription and its plan to Gold, and the
cart, the receipt and the order all say "Gold membership".

A level with no join course is still saved and still grants access — somebody may be
selling it off-site, or granting it by hand. Its public card simply has no button, and
the admin screen says so.

## Where it shows up

- **Sikshya → Subscriptions → Membership levels** — the editor.
- **`[sikshya_membership_levels]`** — the pricing table, on any page. Attributes:
  `ids` (comma-separated level ids, to show a subset), `show_courses` (`1`/`0`),
  `courses_limit` (how many course titles to list per card, max 50).
- **Single course page** — "Included with membership", naming the levels that contain
  this course and linking to each one's join course. A member sees "you have this".
- **Account page** — the existing membership panel now also names the level and how many
  courses it includes, for an active subscription only.

A visitor who already holds a level sees "You are a member" instead of a Join button.

## Things the admin screen will tell you

A level is saved even when it is half-finished, because half-finished is a normal state
to be in. But none of these faults are visible from the public page — it renders a card
with no button, or no card at all — so each is reported in the editor:

| Reported | Why it matters |
|---|---|
| `no_plan` / `missing_plan` / `inactive_plan` | Nobody can hold the level, so it grants nothing; an inactive plan is refused at checkout |
| `empty_catalogue` / `no_published_courses` | The level opens nothing |
| `no_join_course` / `join_course_unpublished` | No way to buy it |
| `join_course_not_subscription` | Buying it takes the money and starts no subscription |
| `join_course_plan_mismatch` | The learner pays for a different plan from the one the level requires, so paying grants nothing |

## Categories track the catalogue

A level built from course categories picks up a course added to one of those categories
later, with no edit to the level. A level built from explicit course ids does not. Both
are useful; the editor says which you are choosing.

Only **published** courses are counted and listed. A level resolves at most 200 courses
for display purposes; the access check never builds the list at all — it asks the cheap
question (does this one course match the rules?) rather than the expensive one.

## Permissions

The level editor requires `manage_options`, like the plans and subscriptions screens it
sits beside. Nothing about memberships is exposed to instructors: a level changes who can
read other people's courses, which is a site-owner decision.

## Uninstall

Levels are stored in `sikshya_pro_membership_levels` and are removed by Sikshya Pro's
opt-in uninstall purge along with the rest of the `sikshya_pro_*` options. Without the
opt-in, nothing is deleted. Removing a level never touches subscriptions, orders or
enrolments: learners keep their subscriptions and keep being billed — only the extra
access the level granted goes away.

## Not claimed

- **No plan-only checkout.** See above; a level is bought through its join course.
- **No per-level trial, setup fee or coupon.** Those belong to the plan and the existing
  coupon system, and a level does not add a second place to configure them.
- **No automatic enrolment row.** A membership grants access the same way an active
  subscription already does — through `sikshya_user_has_course_access`, which
  `isUserEnrolled()` consults — so progress, quizzes and certificates work. But no
  enrolment row is created for every course in a level, which means a hundred-course
  all-access pass does not put a hundred rows in your enrolment list for every member.
- **No tiering between levels.** Gold does not inherit Silver; list Silver's courses in
  Gold, or use "every published course".
- **No proration or upgrade path between levels.** Switching a learner from one level to
  another is a subscription change, handled where subscriptions are handled.
- **No browser test.** The levels editor and the public table are covered by 52 PHP
  integration tests, including the public HTML, but no Playwright spec drives the screen.
