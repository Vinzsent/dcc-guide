# DCC Care — Design System "The Discipline Ledger"

## Brand understanding

DCC Care is the discipline office system for a school ("DCC"): staff log students,
record offenses, and manage RFID student IDs at the gate/registrar desk. The
existing UI already carries a strong, credible identity — deep evergreen and
brass gold — that reads as institutional without being corporate. This system
keeps that DNA and elevates it: warmer paper surfaces, a display serif for
gravitas, monospace for the record-keeping artifacts (case numbers, student
numbers, RFID hex), and a ledger metaphor for tables (the discipline office's
official record book).

Sources consulted:

- `resources/views/auth/login.blade.php` — brand colors (#14442C evergreen, #FDFDFC paper), Instrument Sans
- `resources/views/dashboard.blade.php` — gold accent (#D4A017), sidebar pattern
- `resources/views/students/index.blade.php` — table conventions, RFID column
- `routes/web.php`, `app/Http/Controllers/*` — domain model: Students, OffenseTypes, OffenseActions, Users, RFID lookup
- `database/migrations/*` — field inventory (student_number, rfid_card_id, education_level, enrollment_status…)

## Index

- `tokens/colors_and_type.css` — canonical tokens (raw + semantic)
- `ui-kit.css` — shared component stylesheet used by every mockup
- `brand/voice-and-tone.md` — copy rules
- Mockups live in `opendesign/mockups/` and link this system relatively

## Voice & tone (summary)

- Sentence case everywhere; Title Case only for proper nouns and page titles.
- Plain, humane, direct. "File an offense", not "Initiate disciplinary action record".
- Numbers and IDs are never decorative: student numbers and RFID hex are always monospace.
- No emoji in product UI.

## Open questions for the client

1. Should "Register as a student" remain linked from the login page (current behavior), or should student self-registration be removed in favor of admin-only enrollment?
2. Confirm severity tiers for offenses — mockups assume Minor / Major / Serious / Grave.
3. Confirm whether user accounts are admin-only (mockups assume admin creates staff accounts; no self-signup).
