# Sikshya LMS — Elite Plan Spec (Agency / Marketplace / Enterprise)

Elite is the top tier: marketplace, agency operations, advanced automation, and enterprise learning requirements.

## Elite goals
- Unlock higher ACV via marketplace + white labeling + automation.
- Make Sikshya “agency-ready”: multi-site workflows, client handoff, diagnostics.
- Support enterprise needs without bloating the Free/Pro experience.

---

## 1) Marketplace (multi-vendor LMS)

### Included (Elite)
- Udemy-style marketplace mode:
  - instructor storefronts
  - instructor application/approval workflow
  - commission split rules (global + per-instructor + per-course)
  - payout requests / withdrawals
  - marketplace reports (GMV, net revenue, instructor earnings)
- Vendor analytics dashboard

---

## 2) White label + agency tooling

### White label (Elite)
- remove Sikshya branding in admin + learner UI
- custom logo, naming, labels
- optional “powered by” toggle

### Agency operations (Elite)
- multi-site license activation
- central license dashboard
- client site handoff checklist
- export/import templates (course blueprints)
- reusable content bank (media, quizzes, certificate templates)
- migration tools from major LMS plugins (guided)

---

## 3) Automation, API, integrations

### Webhooks + API (Elite)
- public API endpoints for:
  - enrollments
  - orders
  - courses
  - certificates
  - progress events
- webhooks:
  - purchase completed
  - enrollment created
  - lesson completed
  - course completed
  - certificate issued

### CRM + automation (Elite)
- FluentCRM
- HubSpot
- Mailchimp / Brevo
- Zapier-style automations (native or via webhook connectors)
- Slack/Discord/WhatsApp notifications (optional)

---

## 4) Enterprise learning

### Multilingual (Elite)
- WPML support
- Weglot support

### SCORM / H5P (Elite)
- SCORM advanced (tracking, completion rules)
- H5P interactive content support

### Enterprise reporting (Elite)
- advanced export formats
- department/group-based reporting
- retention and compliance dashboards

---

## Non-functional requirements (Elite)
- Marketplace and payouts must be:
  - auditable
  - secure (capabilities + nonce + strict validation)
  - resilient (idempotent webhook handling)
- Provide “site health” diagnostics:
  - background job queue state
  - database table health for analytics
  - performance warnings and guided remediation

