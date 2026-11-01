# Surveillance methods

How the public-health screens (`/surveillance`) compute their figures. The code is in `src/Services/SurveillanceService.php`, `src/Services/OutbreakService.php` and `src/Support/Stats.php`. Every figure is recomputed from the database when a page loads.

## What is counted

Each event is one test with a definite result: positive or negative. It comes from one of three sources.

| Source | Included | Excluded |
| --- | --- | --- |
| Testing workflow | Released COVID-19 results | Inconclusive and invalid results, unreleased results |
| Laboratory orders | Verified results of COVID-19 RT-PCR, COVID-19 antigen and dengue NS1 | Inconclusive, invalid and equivocal results; results with no state |
| Imported returns | Committed rows from CSV or JSON files | Previewed, discarded and rolled-back files |

- The event date is the release or verification date for internal results, and the `date` column for imports.
- These are counts of tests and positive results. They are not case counts.
- They depend on who is tested and when. Nothing on these screens estimates the true number of infections.

## Positivity and intervals

- **Positivity** is the number of positive results divided by the number of tests.
- **Intervals** are 95% Wilson score intervals. They behave well for small counts and for rates near 0% or 100%.
- **Minimum tests.** A state or facility with fewer than 10 tests shows no rate.
- **Facility comparison.** A facility is called "above" or "below" only when its whole interval lies on one side of the overall rate. Each comparison is separate: no correction is made for testing many facilities. A difference can reflect who a facility tests, not the quality of its work.

## Trend estimate

The chart and the trend end yesterday. The trend is fitted to the last 21 complete days of positive results. Today is left out because it is still filling.

- **Model.** An ordinary least-squares fit of `ln(count + 0.5) = a + b·t`.
- **Daily change.** `exp(b) − 1`. Its 95% interval comes from the standard error of `b` and Student's t with n − 2 degrees of freedom.
- **Doubling or halving time.** `ln 2 / b`.
- **Projection.** Seven days ahead, with a 95% prediction interval on the log scale, transformed back.
- **When no trend is shown.** The estimate needs at least 10 positive results in the 21 days.

The projection extends the recent trend in positive results. It is not a forecast:

- It ignores changes in testing, reporting delays and interventions.
- It assumes the trend is log-linear over the fitted window.

## Seasonal table

Positive results by calendar month, for up to three years, with positivity for each month.

## Outbreak rules

Rules run for each state over a rolling window of 7, 14 or 28 days that ends yesterday. A rule fires at most once per rule, state and window end.

| Rule | Fires when |
| --- | --- |
| Positive count | Positives in the window are at or above the threshold |
| Positivity | Positivity (%) is at or above the threshold, with at least the minimum number of tests |
| Above baseline | Positives exceed the mean of the previous four windows by more than *k* standard deviations, and by at least one |

- **Baseline rule.** It compares like with like and is similar in spirit to the CDC's EARS C-methods. The standard deviation has a floor of 1, so a single case after a quiet month does not raise an alert. Missing windows count as zero.
- **When the check runs.** `bin/surveillance-check.php` should run daily (for example from cron), or an officer runs it from the alerts page.
- **Who is told.** New alerts notify every public-health officer and administrator.
- **Closing an alert.** It needs a note recording what was done.

## Imports

- **Upload.** Files are parsed and validated row by row. Nothing is written until an officer reviews the preview and commits it.
- **Repeated rows.** Within one file, a row repeating an earlier one (same disease, day, state, facility, sex and age band) is rejected.
- **Corrections.** Across files, a later file replaces the earlier figures for the same rows.
- **Rolling back.** A rollback removes only the rows that import still owns.

## Data quality

The quality screen reports:

- completeness of the fields the analyses depend on;
- time from sample collection to released result (median and 90th centile);
- requests stuck in the laboratory stage for three or more days;
- the share of released results that were inconclusive or invalid;
- the share of imported rows rejected;
- the latest day imported for each disease.
