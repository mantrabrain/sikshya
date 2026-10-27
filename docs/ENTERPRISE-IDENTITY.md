# Running Sikshya from your identity provider

**For:** IT administrators and anyone answering a security questionnaire about Sikshya.
**Verified:** 2026-10-27, against Sikshya 1.0.15 / Pro 1.0.11 (pending release).

This describes how to drive Sikshya from Okta, Entra ID, Google Workspace or any
other SAML/SCIM provider, what Sikshya does on its own once that is wired up,
and — just as important — what it does not do.

## What Sikshya does and does not provide

**Sikshya does not implement SAML or SCIM, and deliberately so.** WordPress
already has mature plugins for both (miniOrange is the one we test against:
SAML 2.0 SSO with 50+ IdP guides, and SCIM user provisioning). Writing our own
would duplicate a maintained product and add an attack surface to an LMS.

What those plugins cannot do is translate an identity into LMS meaning — a
person in an HR group becoming a learner with mandatory training and a seat.
That translation is what Sikshya provides, and this page is the recipe.

```
Identity provider ──SAML──▶ WordPress sign-in        (miniOrange SSO)
                  ──SCIM──▶ WordPress user + role    (miniOrange provisioning)
                                      │
                                      ▼
                         Sikshya: role → compliance audience
                                  role → group membership (manual or by rule)
                                  departure → seat released, enrolments closed
                                  every change → audit log row
```

## 1. Sign-in (SAML)

Configure the SAML plugin as you would for any WordPress site. Sikshya needs
nothing: it reads the signed-in user from WordPress, so a learner who arrives
through SSO is a learner like any other, and the learner dashboard, Learn pages
and certificates all work unchanged.

**Attribute mapping that matters to Sikshya:** map your IdP's group or job-code
attribute onto the **WordPress role**. Everything below keys off the role,
because that is the one attribute WordPress itself understands.

Use `sikshya_student` for learners and `sikshya_instructor` for people who
author courses. Both are created by Sikshya on activation.

## 2. Provisioning (SCIM)

With SCIM configured, a new hire appears in WordPress as a user with the role
your mapping gives them. Sikshya then does two things without anyone touching
the admin:

**Mandatory training is assigned.** A compliance requirement whose audience is
a role assigns itself to everyone holding that role on its next daily sync, so a
new hire inherits the training their job requires, with the requirement's own
deadline. *(Tested: `IdentityLifecycleTest::testANewHireInheritsTheirMandatoryTrainingWithNobodyAssigningIt`.)*

**The account change is recorded.** The activity log stores an "Account created"
event with the role, the email, where the request came from, and which account
made it — which is the difference between "she signed herself up" and "HR
provisioned her".

**What is not automatic:** group membership. Seats belong to a group a customer
bought, and nothing in SCIM says which group a person's seat should come from,
so a group leader adds them from the group's own roster screen — or you add them
through the REST API from your own script. If you want membership driven by
role, hook `user_register` and call the groups endpoint; there is no built-in
rule for it.

## 3. Role changes

Changing someone's role in the IdP changes it in WordPress, and Sikshya follows:

- Compliance **audiences are re-evaluated** on the next sync, so training the
  new role requires is assigned. Training already assigned is **not** withdrawn:
  an obligation someone was given is a fact, and an auditor asking "was this
  completed" is entitled to an answer rather than to a gap. The practical
  consequence is that a transferred employee can carry an obligation from their
  old job until somebody waives it — waiving is the intended way to close one
  that no longer applies, and a waiver is recorded as a waiver rather than as
  training that happened.
- The log records a **"Role changed"** event carrying the previous roles.
- Group seats are **untouched**. A role change is not a departure, and whether
  the person still belongs on the roster is the group leader's call.

## 4. Deprovisioning

When your IdP deactivates somebody, the SCIM plugin either **deletes** the
WordPress user or **strips their roles**. Sikshya handles both, differently.

**Deleted.** Sikshya fires one event, `sikshya_user_deprovisioned`, and then:

| What happens | Why |
|---|---|
| Open enrolments are closed (`cancelled`) | They are no longer studying. The row stays; nothing is deleted |
| **Completed** enrolments are left alone | That row is the evidence they did the training before they left |
| Group memberships go inactive, **freeing the seat** | A seat is money. Before this, an organisation lost one with every departure and had to find the row by hand |
| Their compliance obligations stop counting | Someone who left is not an outstanding obligation; counting them would drag the figure down for ever |
| Their assignment rows are kept | A completion is still evidence after the person is gone |
| An **"Account removed"** event is logged | With their login, email, roles, how many enrolments were closed, how many seats were freed, and who removed them — the user record no longer exists, so the row has to carry it |

**Roles stripped instead.** The person stays in WordPress, so Sikshya treats
them as present, and this is the case to understand before you choose it:

- They receive **no new** obligations, because they are no longer in any
  role-based audience.
- Obligations they **already have still count**, and they still exist as a user,
  so the compliance figure keeps including them. Waive the obligation, or delete
  the account, to close it.
- Their **seat stays taken**, because they are still on the group's roster.

If your policy is that deactivation should release the seat and clear the
obligation, configure the IdP to **delete** rather than deactivate — that is the
path the table above describes, and the one the tests cover.

## 5. What an auditor can be shown

From **Sikshya → Activity log**, filtered by action:

- *Account created* — who was given access, when, by whom, with what role.
- *Role changed* — what it was before and what it became.
- *Account removed* — who left, when, by whom, and what it cost them (enrolments
  closed, seats freed).
- *Signed in* — **off by default.** It is the only identity event that grows
  with headcount rather than with changes: five thousand people signing in daily
  is close to two million rows a year, in a table whose default is to keep
  everything. Turn it on in Settings → Activity log when it is asked for, and
  set a retention window at the same time.

Each row keeps the identity it describes, so the report still makes sense after
the person's WordPress account is gone.

Give the auditor the **Auditor (read-only)** role Sikshya installs
(`sikshya_auditor`) rather than an administrator account: they can read the log
and the compliance reports without being able to change anything, which is
usually easier to defend than a shared admin login.

## Limits, stated plainly

- **No SAML or SCIM of our own.** You need an identity plugin; we test against
  miniOrange. Nothing here is specific to it except where this page says so.
- **No SOC 2 or ISO 27001 from us, and there cannot be.** The infrastructure
  being certified is yours. That is the trade a self-hosted LMS makes: learner
  data never leaves your estate, and the certification question lands on your
  own controls. If a questionnaire demands a vendor report, we cannot satisfy it.
- **Sign-in method is not recorded.** A SAML sign-in reaches WordPress through
  the same `wp_login` as a password, so the log says "password" unless your
  identity plugin says otherwise through `sikshya_identity_sign_in_method`.
- **Group membership is not provisioned by role.** See §2.
- **Role-based audiences are the integration surface.** Department, job code and
  other HR attributes have to be mapped to a role to drive anything in Sikshya.

## Hooks this relies on

| Hook | Fired when | Payload |
|---|---|---|
| `sikshya_user_deprovisioned` | A WordPress user is deleted | `$user_id`, details: `user_login`, `user_email`, `display_name`, `roles`, `enrollments_cancelled`, `reassigned_to`, `actor_id` |
| `sikshya_group_seats_released` | Seats freed by a departure | `$user_id`, `$released`, details |
| `sikshya_cancel_enrollments_on_user_delete` (filter) | Before enrolments are closed | Return false to leave them open |
| `sikshya_identity_event_source` (filter) | Labelling an identity event | Return e.g. `scim` so the log names the source exactly |
| `sikshya_identity_sign_in_method` (filter) | A sign-in is recorded | Return e.g. `saml` |
