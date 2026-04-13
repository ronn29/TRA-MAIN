"""
Train a Random Forest model for education programs: BCAEd, BECEd, BEEd.
"""

import json
import os
import sys

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, f1_score, precision_score, recall_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder

ML_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, ML_DIR)

try:
    from career_weights_map_educ import career_weight_map
    USE_CAREER_WEIGHTS = True
    print("[OK] Education career weights map loaded")
except Exception as e:
    print(f"[WARN] career_weights_map_educ.py not found ({e}). Training without career weights.")
    career_weight_map = {}
    USE_CAREER_WEIGHTS = False

try:
    from question_weights_map_educ import question_weight_map
    USE_QUESTION_WEIGHTS = True
    print("[OK] Education question weights map loaded")
except Exception as e:
    print(f"[WARN] question_weights_map_educ.py not found ({e}). Using default weights.")
    question_weight_map = {}
    USE_QUESTION_WEIGHTS = False

# ── Load dataset ──
print("\n[INFO] Loading education dataset...")
dataset_path = os.path.join(ML_DIR, "career_recommendation_dataset_educ.xlsx")
if not os.path.exists(dataset_path):
    raise FileNotFoundError(
        f"Dataset not found at {dataset_path}. "
        "Run generate_dataset_educ.py first."
    )

df = pd.read_excel(dataset_path, engine="openpyxl")
print(f"[OK] Loaded {len(df)} rows, {len(df.columns)} columns")

# ── Column definitions ──
target_col = "Recommended_Career"
program_col = "Program"
apt_cols = [f"Apt_Q{i}" for i in range(1, 11)]
pers_cols = [f"Pers_Q{i}" for i in range(1, 11)]
int_cols = [f"Int_Q{i}" for i in range(1, 11)]
all_question_cols = apt_cols + pers_cols + int_cols

# ── Encode Yes/No → 1/0 ──
yes_no_map = {"Yes": 1, "No": 0, "yes": 1, "no": 0}
for col in all_question_cols:
    if col in df.columns:
        df[col] = df[col].map(yes_no_map).fillna(-1).astype(float)

# ── Standardize program names ──
program_mapping = {
    "BCAEd": ["BCAEd", "BCAED", "BACHELOR OF CULTURE AND ARTS EDUCATION"],
    "BECEd": ["BECEd", "BECED", "BACHELOR OF EARLY CHILDHOOD EDUCATION"],
    "BEEd": ["BEEd", "BEED", "BACHELOR OF ELEMENTARY EDUCATION"],
}
reverse_program_map = {
    v.upper(): k for k, variants in program_mapping.items() for v in variants
}

def standardize_program(value):
    if not isinstance(value, str):
        return "Unknown"
    return reverse_program_map.get(value.upper(), "Unknown")

df["Program_Standard"] = df[program_col].apply(standardize_program)
print(f"[OK] Programs: {df['Program_Standard'].value_counts().to_dict()}")

# ── Encode target labels ──
print("\n[INFO] Encoding target labels...")
le = LabelEncoder()
y = df[target_col]
y_encoded = le.fit_transform(y)
print(f"[OK] Encoded {len(le.classes_)} careers")

# ── Class weights from career weight map ──
if USE_CAREER_WEIGHTS:
    print("[INFO] Building class weights from career_weight_map_educ...")
    class_weights = {}
    for idx, career_name in enumerate(le.classes_):
        weights = career_weight_map.get(career_name, {})
        if weights:
            avg_weight = np.mean(list(weights.values()))
            relevance_multiplier = (avg_weight / 3.0) + 0.5
            class_weights[idx] = relevance_multiplier
        else:
            class_weights[idx] = 1.0
    max_w = max(class_weights.values()) if class_weights else 1.0
    class_weights = {k: v / max_w for k, v in class_weights.items()}
    print(f"[OK] Custom class weights applied ({len(class_weights)} classes)")
else:
    class_weights = "balanced"
    print("[OK] Using balanced class weights")

# ── Encode program ──
print("\n[INFO] Encoding program features...")
program_encoder = LabelEncoder()
program_encoder.fit(df[program_col])

for code in ["BCAEd", "BECEd", "BEEd"]:
    if code not in program_encoder.classes_:
        classes = sorted(set(list(program_encoder.classes_) + [code]))
        program_encoder.classes_ = np.array(classes)

df["Program_Encoded"] = program_encoder.transform(df[program_col])

relevance_codes = ["BCAEd", "BECEd", "BEEd"]
for code in relevance_codes:
    df[f"Relevance_{code}"] = (df["Program_Standard"] == code).astype(float)
print("[OK] Program encoding and relevance features added")

# ── Apply question weights ──
if USE_QUESTION_WEIGHTS:
    print("\n[INFO] Applying question weights...")
    for col in all_question_cols:
        if col not in df.columns or col not in question_weight_map:
            continue
        weight_cfg = question_weight_map[col]
        if isinstance(weight_cfg, dict):
            weight_series = df["Program_Standard"].map(lambda x, w=weight_cfg: w.get(x, 1.0))
            mask = weight_series != 1.0
            if mask.any():
                df.loc[mask, col] = df.loc[mask, col] * weight_series[mask]
        else:
            weight = float(weight_cfg)
            if weight != 1.0:
                df[col] = df[col] * weight
    print("[OK] Question weights applied")

# ── Build feature matrix ──
cols_to_drop = [target_col, program_col, "Program_Standard"]
X = df.drop([c for c in cols_to_drop if c in df.columns], axis=1, errors="ignore")

print(f"\n[INFO] Feature matrix shape: {X.shape}")
print(f"[INFO] Features: {list(X.columns)}")

# ── Train/test split ──
X_train, X_test, y_train, y_test = train_test_split(
    X, y_encoded,
    test_size=0.2,
    random_state=42,
    stratify=y_encoded if len(np.unique(y_encoded)) > 1 else None,
)
print(f"[OK] Train: {len(X_train)} | Test: {len(X_test)}")

# ── Train model ──
print("\n[INFO] Training Education Random Forest model...")
model = RandomForestClassifier(
    n_estimators=500,
    max_depth=25,
    min_samples_split=2,
    min_samples_leaf=1,
    max_features="sqrt",
    class_weight=class_weights,
    random_state=42,
    n_jobs=-1,
)
model.fit(X_train, y_train)
print("[OK] Training complete!")

# ── Evaluation ──
print("\n[INFO] Evaluating...")
y_pred = model.predict(X_test)
metrics = {
    "accuracy": float(accuracy_score(y_test, y_pred)),
    "precision_weighted": float(precision_score(y_test, y_pred, average="weighted", zero_division=0)),
    "recall_weighted": float(recall_score(y_test, y_pred, average="weighted", zero_division=0)),
    "f1_weighted": float(f1_score(y_test, y_pred, average="weighted", zero_division=0)),
}
for k, v in metrics.items():
    print(f"  {k}: {v * 100:.2f}%")

# ── Save artifacts ──
print("\n[INFO] Saving education model artifacts...")
joblib.dump(model, os.path.join(ML_DIR, "career_model_educ.pkl"), compress=3)
joblib.dump(le, os.path.join(ML_DIR, "label_encoder_educ.pkl"), compress=3)
joblib.dump(program_encoder, os.path.join(ML_DIR, "program_encoder_educ.pkl"), compress=3)

if USE_CAREER_WEIGHTS:
    with open(os.path.join(ML_DIR, "career_weights_map_educ.json"), "w", encoding="utf-8") as f:
        json.dump(career_weight_map, f, ensure_ascii=False, indent=2)
    print("[OK] Career weights map saved (json)")

if USE_QUESTION_WEIGHTS:
    joblib.dump(question_weight_map, os.path.join(ML_DIR, "question_weights_map_educ.joblib"), compress=3)
    print("[OK] Question weights map saved")

with open(os.path.join(ML_DIR, "model_metrics_educ.json"), "w", encoding="utf-8") as f:
    json.dump(metrics, f, indent=2)
print("[OK] Metrics saved to ml/model_metrics_educ.json")

print("\n[DONE] Education model training complete.")
