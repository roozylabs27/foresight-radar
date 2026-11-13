# Scenario relevance (Step 9)

**What it answers:** given the early warning indicators of one exercise, which of the four scenarios does the recent evidence point to, and why?

**What it is not:** a probability. It is a transparent count of indicator readings against each scenario's expectations. Two quadrants with the same count are equally supported; a count of 3 is not "more likely" in any statistical sense. The page says so ("Hitungan, bukan probabilitas").

Implemented in `App\Services\EarlyWarningIndicatorService::relevance()` (audit 2026-11-11, U5 / AUD-06). Tests: `tests/Feature/ScenarioPlanning/EarlyWarningIndicatorTest.php` (relevance tests).

## Inputs

- **Indicators:** only active, accepted ones (`status = active`, `review_status = accepted`) that tell the futures apart. An indicator counts only when at least one pair of quadrants that differ on a single axis (HH/LH or HL/LL for axis A, HH/HL or LH/LL for axis B) expects different directions (PI-09, 2026-11-13). Retired indicators, proposals and indicators expecting the same direction everywhere do not count: they would raise every reading at once.
- **Step 9 done:** accepted, active indicators with an owner and a direction in every quadrant that **together tell both axes apart**. One discriminating indicator for one axis is not enough.
- **Observations:** only accepted ones (`review_status = accepted`; proposals from World Bank or AI matching count once a person confirms them) that have a direction (`rising`, `falling`, `stable`).
- **Expectations:** each indicator's expected direction per quadrant. Qualitative or unset expectations do not count for that quadrant.

## Rule

1. **One vote per indicator.** Take the indicator's latest directional observation (by observed date, then entry order). Earlier observations do not add weight, so one indicator observed often cannot dominate.
2. **Recency window.** The latest observation must be inside the last 12 months (`RELEVANCE_WINDOW_MONTHS`). An indicator whose latest directional observation is older is reported as *outdated* and not counted.
3. **Per quadrant, compare the latest direction with the quadrant's expectation:**

   | Expected \ observed | rising | falling | stable |
   |---|---|---|---|
   | rising | supporting | contradicting | unclear |
   | falling | contradicting | supporting | unclear |
   | stable | unclear | unclear | supporting |

4. **Reading per quadrant:** *menguat* (strengthening) when supporting > contradicting, *melemah* (weakening) when supporting < contradicting, otherwise *belum jelas* (unclear). Unclear readings never tip the balance.
5. **Leading quadrant:** the quadrant with the highest net score (supporting − contradicting), only when that score is positive and no other quadrant has it.

## Explanation shown

For each quadrant the page lists every counted indicator: its name, its latest direction and date, the quadrant's expected direction, the verdict, and a mark when that observation crossed the indicator's threshold (U4). The window start date and the number of outdated indicators are stated above the quadrants.

## Limits

- Directions are recorded by people (or derived from consecutive World Bank values); the count is only as good as those readings.
- Indicators have equal weight. Weighting by importance or reliability would need an explicit, documented decision before it is added.
- Threshold crossings are shown but do not change the count; they flag the framework and responses for review instead (see U4).
