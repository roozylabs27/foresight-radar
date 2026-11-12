# Origin and Review Convention (Scenario Planning)

Roadmap R0.6. Every scenario planning artefact answers two questions:

1. **Where did it come from?** (`origin`)
2. **Has a person accepted it?** (its review state)

AI output is never a decision until a person accepts it, and the UI must show the difference. The values live in one place on each side:

- Backend: `App\Support\Provenance` (origins).
- Frontend: `resources/js/domain/provenance.js` (origins and review states) and the shared `Components/ProvenanceBadge.jsx`.

## Origins

| Value | Meaning | Badge |
|---|---|---|
| `human` | Entered or decided by a person | none (the normal case) |
| `ai` | Written by an AI call; links an `ai_generation_runs` row via `generation_run_id` (R1.12) | provider name, primary colour |
| `template` | Skeleton written by code without AI (narrative template, keyword rules) | "Kerangka tanpa AI" |
| `library` | Taken from the shared library (exercise forces, their first ratings) | "Dari pustaka" |
| `migrated` | Converted from older free text (narrative implications, indicator strings) | "Dari narasi" |

## Review states

`proposed`, `draft`, `reviewed`, `accepted`, `approved`, `rejected`, `retired`. A table with its own decision column uses that column as its review state, instead of adding a second `review_status`.

## Tables

| Table | Origin column | Review state column |
|---|---|---|
| `signals` | `is_ai_generated` (+ `extraction_provider`, `generation_run_id`) | `review_status` (PENDING / ACCEPTED / REJECTED) |
| `project_driving_forces` | `origin` (library) | `inclusion_status` (proposed / included / excluded) |
| `force_assessments` | `origin` (library / human) | gate G3 on the exercise (`scores_confirmed_at`) |
| `critical_uncertainties` | `origin` | `status` (proposed / approved / retired) |
| `scenarios` (framework) | `provider`, `generation_run_id` | `framework_status` (draft / approved) |
| `scenario_quadrants` | `origin` (human / ai / template) | `review_status` (draft / reviewed / approved) |
| `scenario_conditions` | `origin` | `review_status` (proposed / accepted) |
| `scenario_implications` | `origin` | `review_status` (proposed / accepted) |
| `strategic_responses` | `origin` | `status` (proposed / approved / rejected) |
| `early_warning_indicators` | `origin` | `review_status` (proposed / accepted) |

## Rules for new artefacts

1. Add `origin` (string, default `human`) and either `review_status` or a decision column that the table above maps.
2. AI output starts as a proposal (`proposed` / `draft`), links its run through `generation_run_id`, and counts towards a step only after a person accepts it.
3. Show both with `ProvenanceBadge`; pass `originLabel` / `reviewLabel` only to make the text more specific (for example the provider name).
