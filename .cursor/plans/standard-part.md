# Incoming part standard

One incoming part has one inspection standard. The standard is not a separate list. It lives on its own page, opened from the parts table.

Source form: `INCOMING INSPECTION STANDARD.xlsx` (sheet `ACLP (2)`). Yellow cells are the printed form. White cells are the inspection lines.

## Parts list

File: `Modules/Quality/resources/views/livewire/admin/quality/incoming/parts-page.blade.php`

- Remove the **Standards** count column (`standards_count`).
- In that place, show one icon per row:
  - **Plus** when the part has no standard. Opens the create page for that part.
  - **Document** when the part already has a standard. Opens that standard.
- The part row menu (edit / delete) stays as it is.

## Where the standard lives

- Route: `admin/quality/incoming/parts/{part}/standard`
- Name: `admin::quality.incoming.parts.standard`
- Page title: the part code and description, then “Incoming standard”.
- Breadcrumb: Incoming Quality parts → that part.
- Creating and editing use the same page. The first save creates the standard. Later visits update it.
- A part cannot have a second standard. Enforce with a unique `part_id` on `inspection_standards`.
- Remove **Incoming Quality Standard** from the Incoming sidebar (`Navigation.php`). Drop the list route `admin::quality.incoming.standards` and the old create route. Keep reports pointing at the standard that belongs to the part.
- If a part already has more than one standard, keep the active one with the latest `effective_from`, then the highest id, and leave the others untouched until they are cleaned up by hand. New saves must not create another row.

## Page layout

The page is the yellow form, not the current code / title / characteristics editor.

### Fixed (yellow). Not typed. Same on every standard and on the later PDF

Header

- Title: **Inspection Standard - Parts**
- Form mark: **UMAC-IIS**
- Labels only: Supplier's Name, Supplier's Code, Doc.No., Page, Rev.No., Rev.Date, Model, Part Name, Part No., Drawing No., Drawing Rev.No.
- Column headings: S.No., Inspection Item, Criteria, Inspection Method, Inspection Tool, Sample Plan, Inspection Stage, Remarks

Footer

- **General Tolerance** grid, values fixed:

  | Above | 0.5 | 3 | 6 | 30 | 120 | 315 | 1000 |
  | Up to and including | 3 | 6 | 30 | 120 | 315 | 1000 | 2000 |
  | Tolerance | ± 0.1 | ± 0.1 | ± 0.2 | ± 0.3 | ± 0.5 | ± 0.8 | ± 1.2 |

- Labels: Approved By, Checked By, Prepared By
- Revision-history headings: Rev. No, Rev.Date, Description of Changes
- Instrument line: V.C = Vernier Caliper, MIC Met = Micro Meter, TPG = Thread Plug Gauge, V.H.G = Vernier Height Gauge, TRG = Thread Ring Gauge, H.T = Hardness Tester, D.T.G = Dial Thickness Gauge
- Category line: C = Critical, M = Major, S = Significant, Mi = Minor

### Filled from the part (shown, not retyped)

- Part Name ← part description (`name`)
- Part No. ← part code (`part_number`)
- Supplier's Name and Supplier's Code start from the part vendor (`name`, `code`). The user can change them on the standard; the part vendor is not overwritten.

### Typed on the standard (header)

| Field | Sample from the sheet |
| --- | --- |
| Standard No. | WR-01 |
| Doc. No. | F/QA/02 |
| Page | 1 of 1 |
| Rev. No. | 1 |
| Rev. Date | 12.11.2024 |
| Model | ACLP |
| Drawing No. | 1258-01-002 |
| Drawing Rev. No. | 0 |
| Supplier's Name | AAR AAR TECHNOPLAST |
| Supplier's Code | (blank on the sample) |

### Typed inspection lines

Add and remove rows. S.No. is the row order.

| Column | Sample |
| --- | --- |
| Inspection Item | Dimension, Diameter, MATERIAL, COLOUR, Fitment with assy, Appearance |
| Criteria | 144±0.2, POLYCARBONATE, BLACK, No impact on fitment |
| Inspection Method | D.V.C., VISUAL |
| Inspection Tool | D.V.C., VISUAL |
| Sample Plan | Doc.No. ML-08 |
| Inspection Stage | IQC |
| Remarks | optional |

At least one line is required to save. Criteria stays free text (the sheet uses `144±0.2`, `ø2.4±0.1(2PLS)`, and sentences). Do not split criteria into min/max on this page.

### Typed footer

- Approved By, Checked By, Prepared By (names)
- Revision history rows: Rev. No, Rev. Date, Description of Changes. The sample has one row: `1`, `12.11.2024`, `Tolerance revised as per Tol Chart`.

## Document upload

- One file on the standard. Uploading again replaces it.
- Allow `.xlsx`, `.xls`, and `.pdf` only.
- Show the file name with download and remove.
- The upload is the source sheet the user already has. It does not replace the form fields. PDF generation uses the saved fields, not the uploaded file.

## PDF (later)

Do not generate a file in the first build. Store the fields so a PDF can be produced from them.

When that step is added:

- Button on the standard page: **Generate PDF**
- Output matches the yellow sheet: fixed header and footer, typed values in the same places, inspection lines in order, tolerance grid and legends unchanged.
- Follow the purchase-order document view (`quality::documents.purchase-order`) until a PDF library is chosen.

## Data

Keep `inspection_standards` and `inspection_characteristics`. Add the sheet fields. Map old columns so existing report links keep working.

`inspection_standards`

- `part_id` unique
- `code` ← Standard No. (keep the column; it is already unique)
- `revision` ← Rev. No.
- `effective_from` ← Rev. Date
- `title` ← Model (keep required; default from the part description when Model is empty)
- `supplier_name`, `supplier_code`
- `doc_no`, `page_label`
- `drawing_no`, `drawing_rev`
- `approved_by`, `checked_by`, `prepared_by`
- `document_path`, `document_name` (nullable; one file)
- `sampling_plan_id` stays nullable and unused on this page
- `status` stays, default `active`

`inspection_characteristics` (one row per inspection line)

- `sequence` ← S.No.
- `name` ← Inspection Item
- `specification` ← Criteria
- `method` ← Inspection Method
- `instrument` ← Inspection Tool
- `sample_plan` (new)
- `inspection_stage` (new)
- `remarks` (new)
- Leave `tolerance_min`, `tolerance_max`, `unit`, `is_critical` in the table so old rows and report judging still load. This page does not edit them.

New table `inspection_standard_revisions`

- `inspection_standard_id`
- `rev_no`, `rev_date`, `description`
- `sequence`

`IncomingPart`: `standard()` hasOne `InspectionStandard`. `currentStandard()` returns that row.

## Save rules

- Standard No. required, unique.
- Rev. No. and Rev. Date required.
- Supplier name and code optional.
- Doc. No., Page, Model, Drawing No., Drawing Rev. No. optional.
- One or more inspection lines. Item and Criteria required on each line.
- Revision-history rows optional. A row with any value needs Rev. No. and Rev. Date.
- Document optional. Reject anything that is not xls, xlsx, or pdf. Max size 10 MB.

## Out of scope

- Editing the yellow labels, tolerance numbers, or legends.
- A standards index page.
- More than one standard, or more than one uploaded file, per part.
- PDF output in the first build.
- Changing inspection reports beyond reading the single standard for the part.
