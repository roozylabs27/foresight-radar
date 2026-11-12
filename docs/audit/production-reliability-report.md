# Production Reliability, Recovery & Usability Report — Foresight Radar

Phase 4 of the [production readiness validation](production-readiness-report.md), 2026-11-12, on **https://foresight-radar.id** (production used as staging), signed in by the product owner as Developer (ProNext Hub). Browser: the built-in pane, driven by the agent. Code `main` `9ae54b4`.

## 1. Browser E2E: the nine steps on production

Exercise **"[QA PROD] Transisi Energi Industri 2032"** (`cf0bfccc-a506-46c6-b417-e05e6dc3f86e`). "UI" = clicked and typed in the page; "page request" = the same endpoint the page calls, sent from the signed-in page to save repetition (marked).

| ID | Step | How | Expected | Actual | Status |
|---|---|---|---|---|---|
| E2E-01 | Create exercise | UI; empty form submitted first | required-field messages; one exercise | four clear Indonesian messages; created and opened; status Draf | PASS |
| E2E-02 | Double submit of create | UI button clicked twice in one tick | one exercise | one exercise (the form blocks the second submit) | PASS (PRD-12 limited to direct API) |
| E2E-03 | G1 confirm | UI + confirmation dialog | status active, Step 2 opens | as expected; toast; next action Step 2 | PASS (copy issue PRD-17) |
| E2E-04 | Step 2 forces + G2 | UI library dialog (6 forces), agree dialog | list agreed, Step 3 opens | as expected; category coverage shown | PASS |
| E2E-05 | G3 scores | UI agree dialog | scores agreed | scores copied from the organisation ratings and labelled; classes shown; agreed | PASS |
| E2E-06 | Uncertainties + G4 | UI form; identical poles first | identical poles refused; two approved as A and B | "Kedua kutub satu sumbu harus berbeda."; pair strength 98, independent; G4 ×2 | PASS |
| E2E-07 | Framework + G5 | UI create, approve | four quadrants named by poles; approved | as expected (no confirmation dialog for G5, PRD-18) | PASS |
| E2E-08 | Step 5 conditions | 1 via UI, 7 via page requests | two per quadrant, step done | done; counters "n kondisi · min. 2"; no overlaps | PASS |
| E2E-09 | Step 6 AI narratives without evidence | UI | refused with the reason | "Sumbu A (Biaya Pendanaan) belum punya sinyal ACCEPTED yang tertaut…" | PASS |
| E2E-10 | Step 6 narratives by hand + G6 | 1 via UI edit sheet, 3 via page requests; 1 approved via UI, 3 via page requests | four approved, Step 6 done, G6 ×4 | as expected | PASS |
| E2E-11 | Step 7 implications | 1 via UI (force + condition linked), 7 via page requests | step done, links shown | done; item shows its force and quoted condition | PASS |
| E2E-12 | Step 8 response + G7 | UI form (links to implications and conditions, fit per quadrant), UI approve | robustness and "Menjawab" shown; G7 | "Robust · 3/4"; links listed; approved (no confirmation dialog, PRD-18) | PASS |
| E2E-13 | Step 9 indicator | UI form with numeric threshold "> 9 %" and four directions | saved | saved | PASS |
| E2E-14 | Observation below threshold with client claiming "crossed" | page request | not a crossing | `crossed_threshold` false, no event | PASS |
| E2E-15 | Observation above threshold | UI dialog | preview says it crosses; one event; framework, quadrants and response flagged | preview "Ambang > 9 %: nilai ini melewati ambang…", no checkbox; event `> 9` / 9.5; framework + 4 quadrants + response flagged with reason; relevance HH/HL menguat | PASS (display bug PRD-16) |
| E2E-16 | All steps and gate log | overview | nine steps done; G1–G7 logged once each | `ddddddddd`; G1, G2, G3, G4×2, G5, G6×4, G7; then no next action (PRD-06) | PASS |
| E2E-17 | Printable report | page request | agreed content, not draft | not draft; 6 forces, 2 uncertainties, 4 quadrants, 1 response, 1 indicator, 11 decisions | PASS |

**Answer for the brief:** a real organisation **can** complete Steps 1→9 on production through the browser without developer intervention, provided it writes narratives by hand when no AI is available. With AI it was not possible to verify today (section 2).

## 2. AI provider behaviour (live, Gemini `gemini-3.8-flash`, personal key)

| ID | Path | Wait | Result shown to the user | Status |
|---|---|---|---|---|
| AI-01 | Focal issue review | ~4 min | "Layanan AI tidak menjawab tepat waktu." with the button back | handled; AI output NOT TESTED |
| AI-02 | Settings → Test Koneksi | 22 s | "Uji koneksi gagal: Layanan AI sedang sibuk… (HTTP 503 · gemini: This model is currently experiencing high demand…)" | handled |
| AI-03 | Force candidates | ~10 s | "Layanan AI sedang sibuk. Coba lagi sebentar lagi." | handled |
| AI-04 | Pole critique | ~4 min | "Layanan AI tidak menjawab tepat waktu." (framework could be approved meanwhile) | handled |
| AI-05 | Signal extraction (ingest) | ~4 min | source failed: "…tidak menjawab tepat waktu. Sumber tersimpan tanpa sinyal. Coba lagi dari halaman Sinyal."; failed-extraction banner with "Coba lagi" | handled |
| AI-06 | Retry extraction | ~1 min | failed again ("sedang sibuk"); a second retry while `processing` refused (422 "Hanya ekstraksi yang gagal yang bisa dicoba lagi.") | handled |

The provider was overloaded during the whole session (HTTP 503). Every failure left no partial data and told the user what to do; but a user waits **up to four minutes** before learning that the AI is unavailable (PRD-14). **Live AI output (proposals, AI narratives with citations, extraction) is NOT TESTED on production** and must be re-run when the provider answers.

## 3. Failure and edge cases

| ID | Scenario | Result | Status |
|---|---|---|---|
| REL-01 | Invalid invitation link | 404 with a friendly Indonesian page | PASS |
| REL-02 | Request with an expired / wrong CSRF token | 419; page requests show "419 — Sesi Kedaluwarsa"; JSON requests return the raw English "CSRF token mismatch." | PASS (minor copy) |
| REL-03 | Unsaved form text closed with Escape | dialog closes, text lost, no warning | FAIL → PRD-19 |
| REL-04 | Retry while running | refused 422 | PASS |
| REL-05 | AI unavailable on every assist | clear message, no partial data | PASS (wait time PRD-14) |
| REL-06 | Lost / stuck queued jobs | covered by tests (U2: `StaleJob`, atomic claim); not reproduced on production | NOT TESTED (prod) |
| REL-07 | Network interruption, database failure, queue worker stop | cannot be simulated safely on production | NOT TESTED |
| REL-08 | Concurrent gate approval | local: duplicate decision rows (PRD-11) | FAIL (local) |

## 4. Usability observations

| Ref | Observation | Finding |
|---|---|---|
| U-1 | Confirmation dialogs for G1 and G2 say "Langkah 2–9 terbuka" / "Langkah 3–9 akan terbuka", but only the next step opens (the toast after G2 is correct) | PRD-17 |
| U-2 | G1–G4 ask for confirmation; G5 (framework) and G7 (response) are approved on a single click | PRD-18 |
| U-3 | Signals page mixes English ("Candidate Review Queue", "Ingest Source Document / URL", "Signal Title & Evidence", "Ingest & Extract Signals") into the Indonesian UI | PRD-20 |
| U-4 | Adding forces from the library gives no success toast (other actions do) | PRD-20 |
| U-5 | Stale reasons split on "; " — a reason containing "; " (threshold rule and value) shows as two broken lines | PRD-16 |
| U-6 | After Step 9 there is no next action or closing state | PRD-06 |
| U-7 | Every step shows a clear next action, step locks, counters ("n kondisi · min. 2", "0 dari 4 kuadran lengkap"), and empty states | positive |

## 5. Test data created on production (to clean up after the validation)

- Exercise "[QA PROD] Transisi Energi Industri 2032" (`cf0bfccc-…`) with its forces, scores, two uncertainties, framework, 8 conditions, 4 narratives, 8 implications, 1 response, 1 indicator, 2 observations, 1 threshold event and 11 decisions.
- Source "[QA PROD] Catatan kebijakan pembiayaan energi industri 2026" (extraction failed, no signals).
- Earlier: "[SMOKE TEST]" records from 2026-11-11.
