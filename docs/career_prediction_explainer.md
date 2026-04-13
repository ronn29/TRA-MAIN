# Career Prediction & Recommendation – System Walkthrough

Use this document as a PDF-ready explainer for stakeholders. You can print/export it to PDF directly from your editor or browser.

---

## Overview
The platform generates a personalized career recommendation only after a student completes **all required assessments (40 answers total)**. The flow is:

1) **Collect** completed assessment answers for the signed-in student.
2) **Validate completeness** (must be exactly 40 answers).
3) **Build features** (one-hot-ish vector of answers + program context).
4) **Call ML model** (Random Forest via Python) to get top career probabilities.
5) **Apply program filters** to keep careers relevant to the student’s program.
6) **Return** most-likely, least-likely, and the full ordered list of careers.
7) **Display** the recommendation with any curated resources per career.

Key PHP entrypoints:
- `student/career_prediction.php`: orchestrates data loading, completion checks, and renders the UI.
- `admin/includes/model_predict.php`: builds features, calls the Python model, filters results, and returns the final recommendation payload.

---

## Data Inputs
- **Assessment answers**: From `assessment_results_tbl` where `assessment_status = 'Completed'` (`getAllStudentAnswers`, `getStudentAssessmentResults`).
- **Program info**: From `student_tbl` joined to `program_tbl` (`getStudentProgramCode`), plus allowed careers from `career_program_tbl` (`getCareersForProgram`).
- **Scores/metadata**: Optional per-assessment score display from `assessment_scores` in `career_prediction.php`.

---

## Preconditions / Completion Rules
- Student must be authenticated (`$_SESSION['role'] == 'student'`).
- Only **visible** assessments count (general + program-specific).
- All visible assessments must be completed. For ML, a **hard check** requires **exactly 40 answers** (see `predictCareerWithRandomForest`).
- If incomplete, the UI instructs the student to finish assessments first.

---

## Feature Construction (40 features)
Implemented in `buildRfFeatureVector`:
- Features named: `Apt_Q1..10`, `Int_Q1..10`, `Pers_Q1..10`, `CS_Q1..10`, `IT_Q1..10`, `IS_Q1..10`, `ACT_Q1..10` (total 40).
- Answers normalized to binary: 1 for “Yes”, 0 otherwise.
- A program code is also captured (`Program_Encoded` placeholder for context in payload).

---

## Model Invocation
- PHP builds a payload `{ current_program, features }`.
- Uses Python from `env/Scripts/python.exe` to run `ml/predict_career.py` with `--input-file <tmp JSON>`.
- Output is expected as JSON with `prediction`, `top_probs`, `confidence`, and metrics in `model_debug` (e.g., `accuracy`).
- Errors handled: missing runtime, invalid output, incomplete data.
- Predictions are logged to `ml/prediction_results.jsonl` (`logPredictionResult`) for traceability.

---

## Post-Processing & Filtering
- `top_probs` careers are **filtered by the student’s allowed careers** (from `career_program_tbl`):
  - Non-matching careers are removed.
  - Missing allowed careers are appended to ensure program relevance.
  - If filtering removes everything, returns `no_allowed_careers_match` so the UI can show a clear message.
- `most_likely` is aligned to the filtered list; `least_likely` is set only when two or more options remain.
- Duplicates are removed while preserving order.

---

## Output to UI
Rendered in `student/career_prediction.php`:
- **Most likely** and **least likely** career titles.
- **Full list** of suggested careers (ordered).
- Optional **resources** (links) per career if provided by the backend payload.
- Shows model accuracy if present in `model_debug.metrics.accuracy`.
- If generation fails, the UI displays a friendly error state (e.g., incomplete data, no allowed careers, runtime issues).

---

## Error & Edge Cases
- **Incomplete assessments**: returns `incomplete_assessment` and the UI blocks prediction.
- **Program has no allowed careers**: falls back to raw model list; if filtered to zero, returns `no_allowed_careers_match`.
- **Python/runtime issues**: returns `python_runtime_missing` or `ml_runtime_failed` with stderr for debugging.
- **Invalid model output**: guarded by `invalid_model_output` check.

---

## How to Regenerate the PDF
1) Open this file (`docs/career_prediction_explainer.md`) in your editor or a markdown viewer.
2) Export/Print to PDF (most browsers/IDE viewers support “Print → Save as PDF”). 
3) Share the generated PDF with stakeholders. 

---

## Quick References (File Paths)
- `student/career_prediction.php`
- `admin/includes/model_predict.php`
- `ml/predict_career.py` (Python Random Forest inference)
- `ml/prediction_results.jsonl` (prediction logs)
- Tables: `assessment_results_tbl`, `assessment_tbl`, `program_tbl`, `career_program_tbl`, `student_tbl`, `assessment_scores`

