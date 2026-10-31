# R-30 — Dot.Forms transformation: platform audit, competitive position, prioritised roadmap

Brief: `requirements/dot-forms-transformation.md` (verbatim). Audited 2026-10-31 before any code,
per Part A. Statuses are evidence-based; nothing below is assumed from an interface existing.

## 1. What the platform is today

Laravel 12 + Jetstream (Livewire, Teams) + Tailwind. The whole product surface is **eight Livewire
components (~1,760 lines)**: Forms/Index, Builder, Submissions, PublicView, AiBuilder,
AiFieldSuggestion, AiAnalytics, Dashboard/Analytics. 18 web routes. InfoDot SSO (ecosystem auth
POST + closed registration), security headers + report-only CSP (#58), guardian/scheduler probes,
sqlite-pinned tests (~40 feature files, green in CI).

### Works (verified in code + covered by tests)
- **Tenancy**: `forms.team_id` + team scope + `assertBelongsToTeam`; FormsTeamScope/FormPermission
  tests; per-form collaborator roles (`form_user_roles`: editor / submission viewer / approver).
- **Builder (basic)**: add/remove/move-up/move-down across **9 field types** (text, email, number,
  textarea, select, radio, checkbox, date, file), label/placeholder/options/required/helper text,
  save with validation; versions written on save (`form_versions`, restore in the builder —
  covered by a test); public cache invalidation on field changes.
- **One-rule visibility logic**: per field, `trigger_field_key + operator + value`, evaluated
  server-side on the public form as answers arrive (ConditionalFieldLogicTest).
- **Publishing**: `is_published` + slug; public page cached (PublicFormCacheTest); consent
  checkbox, confirmation message, custom CSS, a `conversational_mode` flag, quiz answer key.
- **Submissions**: list, search, choose visible columns, detail modal, bulk delete, CSV export
  (XLSX only when the zip extension exists — honest fallback), per-submission data export;
  completion seconds recorded.
- **AI (OpenAI, gpt-4.1-mini; Gemini configured)**: generate-a-form page, per-field suggestion
  engine, a small response-summary page. Key-gated; no key = disabled.
- **Integrations**: `form_integrations` = propose-a-webhook-URL + owner review/approve flow
  (proposal/approval tests). On submission, approved URLs are called.

### Partially works / thin
- **Logic**: exactly one rule per field, no AND/OR, no pages to branch between; the
  `conditional_logic` column is an **AI-written sentence**, not executable logic.
- **Analytics**: views_count + submission counts + completion time on one dashboard page; no
  starts, no per-question distributions, no date ranges, no device split.
- **Versions**: schema snapshots + restore exist, but responses are not pinned to the version
  they were submitted against (brief §29 requires that relationship).
- **Design**: three small CSS files (666 lines) over Jetstream defaults, and **hard-coded inline
  `style="…"` attributes throughout the builder and public views** — no tokens, no system, no
  DESIGN.md. This is the "generic AI-generated design" the brief names. No dark mode.
- **Conversational mode**: a flag that changes the public layout; not a designed experience.

### Missing entirely
Templates (none). Theme/branding studio (custom CSS string only). Drag-and-drop, autosave,
undo/redo, keyboard shortcuts, sections/pages, duplicate field. Field library beyond the 9
(no rating, scale, yes/no, phone mask, time, signature, address, matrix, ranking, NPS,
calculated/formula, repeating, hidden, consent-as-field, payment). Response status/assignment/
notes/tags. Approval workflows & workflow builder (integration approval ≠ submission workflow).
QR codes, embeds, expiring/password links, scheduled open/close, submission limits. Form-level
API/webhook signatures/versioned API. PDF export, scheduled reports. Draft recovery for
responders. Spam protection beyond auth (no honeypot/rate-limit on public submit — verify).
Dot ecosystem consumers (Files attachment storage, Mines inspections…) — Dot.HR/Finance/Projects
**do not exist as live platforms** (ecosystem registry: active=false), so §31 rows naming them
are future work by definition.

Corrections from the live round (2026-10-31, local demo): response limit and scheduled
opens-at DO exist in the builder settings; public submit HAS per-IP-per-form rate limiting
(10/min) and a honeypot field. Live loop verified end to end: create → add text/select/date →
save → publish → public submit → row in the submissions desk → CSV export offered. Builder
truths seen live: "Create New Form" drops straight into the builder (no scratch/template/AI
choice), emoji-labelled toolbar (📊👁️✨📈💾🚀), field cards are stacked label/type/placeholder
inputs with no inline preview, move = up/down buttons, options are a comma-separated string, and
the narrow-width dashboard collapses to one word per line. Theme = light/dark/brand select + one
hex + logo upload + custom CSS string.

## 2. Competitive position (qualitative; the named references)

| Capability | Typeform | Tally | Jotform | Google Forms | Dot.Forms today | Gap size |
|---|---|---|---|---|---|---|
| Creation speed / zero-setup | good | **excellent** (doc-like) | fair | excellent | fair (modal + builder) | medium |
| Field library | ~30 types | ~40 | ~60 | ~12 | **9** | large |
| Conversational experience | **defining** | basic | fair | none | flag only | large |
| Logic (branch/calc/score) | good | good | **deep** | basic | 1 rule/field | large |
| Themes/branding | strong | light | strong | weak | custom-CSS string | large |
| Templates | huge | large | **huge** | some | **none** | large |
| Response desk (status/notes) | basic | basic | **tables product** | sheet | list+CSV | medium |
| Analytics | good | basic | good | basic | counts only | medium |
| Workflows/approvals | light | none | **strong** | none | webhook call | large |
| AI create/improve/analyse | yes | yes | yes | light | **yes (real, 3 surfaces)** | small |
| Tenancy/enterprise locality | n/a | n/a | n/a | n/a | **strong (teams, SSO, POPIA-aware ecosystem)** | — advantage |

**Where Dot.Forms can honestly win**: (1) business processes, not marketing surveys — SA
business templates (CIPC-aware supplier onboarding, leave, incident, inspection) wired to real
approvals; (2) ecosystem gravity — attachments in Dot.Files, identity from InfoDot SSO, one
login across platforms; (3) AI that is already real here while competitors paywall it; (4) a
distinctive identity — every competitor looks like a pastel SaaS; the Dot fleet has proven
system-level design (Yard, Achromatic Ledger) that reads as engineered.

## 3. Prioritised roadmap

**P0 — Essential foundation** (nothing above it matters while the product looks and edits like this)
1. Design system + identity: its own committed world (DESIGN.md + tokens), kill every inline
   style, rebuild dashboard/index/builder/submissions/public form inside it.
2. Builder UX floor: drag-and-drop reorder, duplicate field, autosave with a visible saved state,
   real preview (desktop/mobile), sections/pages.
3. Field library to ~22 honest types (each stores + validates + renders + exports): + phone,
   url, time, datetime, yes/no, rating, linear scale, dropdown-vs-select cleanup, multi-select,
   signature (drawn), hidden, consent, country, currency, percentage.
4. Public-form floor: mobile-first rendering, spam protection (honeypot + rate limit),
   draft-safe submission (retry without duplicate), accessible validation.

**P1 — High-impact**
5. Logic v2: multi-rule AND/OR visibility, conditional required, page branching; visual builder.
6. Templates: ~20 genuinely useful SA-business templates + organisation template library.
7. Theme studio: presets + tokens (colour/type/layout/progress), live preview, org themes.
8. Response desk: status, assignee, internal notes, tags, filters, saved views; response pinned
   to form version (schema integrity, §29).
9. Analytics v2: views→starts→submissions funnel with defined tracking rules, per-question
   distributions, date ranges, device split; CSV/XLSX/PDF summary exports.

**P2 — Advanced**
10. Calculations/formulas (safe evaluator, no user code), scoring (quiz exists in embryo).
11. Approval workflows: submission → stages (notify, assign, approve/reject, outcome), execution
    history, idempotent, safe retries. (The existing integration-approval flow generalises.)
12. Publishing controls: expiring links, passwords, submission limits, scheduled open/close,
    single-submission; QR; embeds (inline/popup) with customisation.
13. Repeating sections, matrix, ranking, NPS, address, file-multi.

**P3 — Future differentiators**
14. Dot.Files attachment storage (platform file API client — first real cross-platform flow),
    Dot.Mines inspection pack, Dot.Agents-assisted workflows.
15. Versioned public API + signed webhooks + integration logs.
16. AI response analysis v2 (theme clustering with citations), AI form review gate.
17. Payment fields — **blocked on the Paystack decision** (Billing owns payments; never-live
    constraint stands).

Classification per feature (user value / effort / risk) is recorded in the register as phases
ship. P0 = phases 1–2 of the brief's §37; P1 ≈ phases 3–5; P2 ≈ phases 6–7; P3 beyond.

## 4. Live round — confirmed
Create→build→publish→submit→review all work on the seeded dev copy (details folded into §1).
Spam protection exists (honeypot + 10/min/IP/form limiter). Screenshots captured for the
before/after record. The product is a working skeleton with honest bones (tenancy, versions,
caching, SSO, tests) wearing a generic skin — the transformation is justified and the
foundation is reusable.

## Addendum (2026-10-31): the leftover roadmap items shipped
P0 #4 draft safety (localStorage draft + client-token duplicate-submit guard), P1 #9 funnel
(views → starts → lodged per form on Tallies; starts counted from ship date, stated on the page),
P2 #11 approvals as ONE decision stamp per copy (APPROVED/REJECTED + decided_by/at, liftable,
exported; multi-stage chains not built and not claimed), P2 #12 publishing controls (hashed form
password with server-side gate + rate limit; allow-embed flag serving `frame-ancestors *` on the
public route only; QR and iframe snippet in the press's Share group). Single-submission-per-person
is NOT built: respondents are anonymous, so it cannot be enforced honestly. Per-question
distributions, date ranges and device split remain open. Older forms can show more lodged copies
than starts — the counter did not exist before this ship.
