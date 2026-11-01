# Sampling rate, AQL, and the inspection-report formula

## What we are fixing

The inspection report must take its sample size from the ML-08 C=0 table, using the AQL that belongs to each defect class. The accept/reject decision is “any defective unit fails that class” (accept number is always 0). The percentages on the report are results of that lookup. They are not a way to choose the sample.

The [ChatGPT sampling reply](https://chatgpt.com/s/t_6ae47ded73e4819192897b96452e87f1) and the current Quality module agree on the table. They disagree on how many sample sizes one lot gets.

## What the ChatGPT reply got right

For a lot of 1,000 the ML-08 band is 501–1,200, and the reply’s three cells match `Ml08SamplingPlan`:

| Class | AQL | Sample size | Accept |
| --- | --- | --- | --- |
| Critical | 1.0 | 34 | 0 |
| Major | 2.5 | 19 | 0 |
| Others | 6.5 | 11 | 0 |

Those cells are in `Modules/Quality/app/Support/Ml08SamplingPlan.php` (AQL index 10, 12, and 14 of band `[501, 1200, …]`).

Other points to keep:

- Sample size comes from lot range × AQL. There is no algebraic shortcut such as `n = N × rate` or `n = √N`.
- A star in the sheet means inspect the whole lot.
- Accept number is 0 and reject number is 1 on every band. One defective unit in that class rejects the class.
- If any class rejects, the lot rejects.
- Nonconforming % is display only:

```text
nonconforming % = (defective units / sample size) × 100
```

- Count defective units, not defect occurrences. One piece with two defects in the same class is one defective unit.
- Save the AQL, sample size, and accept number on the report. A later edit to the sampling master must not rewrite an old report.

## What we will not take from that reply

The reply also proposes a new defect master, revision-controlled sampling documents, and a defect-quantity screen. The app already has parts, inspection standards, characteristics, the 240 ML-08 bands, and an incoming inspection sheet. This plan stays inside that sheet.

Do not switch the report to ISO 2859-1 / ANSI Z1.4. Those plans use code letters and accept numbers above zero. The old `SP-II-*` rows were removed for that reason. `sampling_plans.standard_ref` still defaults to `ISO 2859-1` in the original migration; loaded ML-08 rows already store `ML-08` and level `C=0`.

## What the code does today

`SamplingPlan::forQuantity()` defaults the AQL to `1`. Both the report form and `QualityWorkflow::saveInspectionReport()` call it that way:

- `Modules/Quality/app/Models/SamplingPlan.php` — `forQuantity($qty, $aql = 1)`
- `Modules/Quality/resources/views/livewire/admin/quality/incoming/report-form-page.blade.php` — one locked sample size for every characteristic
- `Modules/Quality/app/Services/QualityWorkflow.php` — one `sampling_plan_id` and one `sample_size` stored on the report

A lot of 150 therefore always gets sample 13 (`ML08-1-91-150`). That is the Critical / AQL 1.0 cell. The same lot should also get Major 11 (AQL 2.5) and Others 6 (AQL 6.5). The feature test in `tests/Feature/Quality/IncomingReportSheetTest.php` locks in the single value 13, so it will need a new expectation once classes split.

Characteristics already have a class (`C`, `M`, `Mi`, `O`) in `InspectionCharacteristic::CLASSES`. The class is printed on the report and does not change how many pieces are inspected. `sample_plan` on a characteristic is free text from the part standard. It is not the ML-08 lookup.

`sample_size = 0` on a band means “entire lot”. `sampleSizeFor()` turns that into the lot quantity at read time. A saved report must store the resolved piece count, so a later lot-quantity edit cannot change history.

## Rules to implement

### 1. Class AQL map

Default map, locked on the inspection form:

| Class code | Label | AQL column |
| --- | --- | --- |
| `C` | Critical | 1.0 |
| `M` | Major | 2.5 |
| `Mi` | Minor | 6.5 until confirmed |
| `O` | Others | 6.5 |

ChatGPT only names Critical, Major, and Others. Minor exists in our characteristic list and is not in that reply. Until that is confirmed, Minor uses the Others column so it is not silently given the stricter AQL 1.0 sample.

Keep the three (or four) AQL values as data on the active sampling standard, with these defaults. Inspectors do not type them. A future approved revision can change them. This matches the reply’s recommendation without a new revision-history module in the first pass.

### 2. Sample size is a lookup

For each class on the lot:

1. Lot size `N` = `ceil(lot qty)`. Lots below 2 are inspected in full (the table starts at 2).
2. Find the active ML-08 row where `lot_min <= N <= lot_max` and `aql` is that class’s AQL.
3. If the cell is entire-lot (`sample_size` 0), `n = N`.
4. Otherwise `n = min(table sample, N)`.

Sampling rate, shown next to the sample size, is only:

```text
sampling rate = n / N
```

Example, lot 1,000:

| Class | n | Rate |
| --- | --- | --- |
| Critical | 34 | 3.4% |
| Major | 19 | 1.9% |
| Others | 11 | 1.1% |

Never derive `n` from the rate.

### 3. Accept / reject formula

For each class, after the readings are entered:

1. A sample index is one physical piece (reading column 1 is the same piece on every characteristic of that class).
2. That piece is defective for the class when any characteristic in the class is NG at that index. Empty readings are not defectives. Two NG characteristics on the same index still count as one unit.
3. `nonconforming % = defective units / n × 100`, rounded for display (the reply’s 1/34 example is 2.94%).
4. Class result is Reject when `defective units > accept number`. Accept number stays 0, so any defective unit rejects the class. The percentage does not decide this.
5. Lot result is Reject when any class is Reject. Otherwise Accept.

Supplier and UMAC stay as two observation columns. Run the same formula on each side. The lot fails when either side fails, which is what the workflow already does with conforming / non-conforming.

### 4. Snapshot

When the report is saved, store one row per class that was actually used:

- class code
- AQL
- lot band (min, max)
- resolved sample size `n`
- sampling rate
- accept number 0, reject number 1
- defective-unit count
- nonconforming %
- class result

The printed report and the Excel export read this snapshot. They do not call `SamplingPlan::forQuantity()` again.

The existing single `inspection_reports.sample_size` can remain as the Critical / largest `n`, so older code and the current “SAMPLE SIZE” cell still have a number. The class rows are the source of truth.

## Where the change lands

1. **Lookup service** — new helper next to `Ml08SamplingPlan`, for example `Ml08SampleQuote`. Input: lot quantity and the class→AQL map. Output: one quote per class (`aql`, `n`, `rate`, `accept`, `reject`, band). `SamplingPlan::forQuantity()` stays, and callers pass the class AQL instead of relying on the default of 1.
2. **Report form** — `report-form-page.blade.php`. Replace the single sample-size field with the class quotes. Each characteristic row gets `n` reading boxes for its own class. Critical can have 34 boxes while Others on the same lot has 11.
3. **Save path** — `QualityWorkflow::saveInspectionReport()`. Quote from the lot, pad readings to that class `n`, count defective units, write the snapshot, then set supplier/UMAC/lot result from the class results.
4. **Show, PDF, Excel** — `report-show-page.blade.php`, `documents/inspection-report.blade.php`, `InspectionReportWorkbook.php`. Show AQL, `n`, rate, defectives, nonconforming %, and Accept/Reject per class. Header sample size lists all three, not only AQL 1.
5. **Tests** — extend `IncomingReportSheetTest` (lot 150: Critical 13, Major 11, Others 6) and add the ChatGPT case (lot 1,000: 34 / 19 / 11). One NG on Critical index 0 rejects Critical and the lot even when Major and Others are clean. Two NG characteristics on the same index count as one defective unit. A star band stores `n = lot qty` and rate 100%.

## Order of work

1. Add the quote helper and unit checks for the two lots above, including a star cell and a lot smaller than the sample.
2. Persist the class snapshot and fill it from the quote on save. Do not change the sheet layout yet. Confirm a saved report still shows 34 / 19 / 11 after a sampling-plan row is edited.
3. Split the form’s reading columns by class and stop inspectors editing the sample size.
4. Update the on-screen report, PDF, and Excel to print the class line (AQL, sample, rate, defectives, %, result).
5. Update the existing lot-150 feature test, then run the Quality feature tests.

## Decisions still open

- Minor (`Mi`): keep it on AQL 6.5 with Others, or give it its own column.
- Whether the class AQL defaults (1.0 / 2.5 / 6.5) are fixed in code for this pass, or editable on the sampling-standard screen and then locked per inspection. The reply prefers editable by revision. The first pass can hard-code the defaults and add the screen later.
- The Excel note that “the report will be made for three parts only” is not in the app. Leave it out until someone confirms it is still the shop rule.
