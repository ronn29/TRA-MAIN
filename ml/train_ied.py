import json
import os
from typing import List

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, f1_score, precision_score, recall_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder

"""
Train a dedicated model for the IED department.
- Prefers an IED-specific dataset if available.
- Falls back to the existing dataset so a model can still be generated.
- Uses career_weights_map_ied for relevance weighting.
"""

# Load career weights map for IED
try:
    from career_weights_map_ied import career_weight_map
    print("[OK] IED career weights map loaded")
    USE_WEIGHTS = True
except Exception as e:  # pragma: no cover - defensive
    print(f"[WARN] Could not load career_weights_map_ied.py ({e}). Training without weights.")
    career_weight_map = {}
    USE_WEIGHTS = False

# Load question weights (shared map)
try:
    from question_weights_map import question_weight_map
    print("[OK] Question weights map loaded")
    USE_QUESTION_WEIGHTS = True
except Exception as e:  # pragma: no cover - defensive
    print(f"[WARN] question_weights_map.py not found ({e}). Using default weights.")
    question_weight_map = {}
    USE_QUESTION_WEIGHTS = False

# Locate dataset (IED-specific first, then shared)
print("\n[INFO] Loading dataset for IED model...")
dataset_candidates: List[str] = [
    os.path.join("ml", "career_recommendation_dataset_ied.xlsx"),
    os.path.join("ml", "career_recommendation_dataset.xlsx"),
    os.path.join("d:", "Downloads", "career_dataset_15000.csv"),
]

df = None
for path in dataset_candidates:
    if not os.path.exists(path):
        continue
    try:
        if path.lower().endswith(".xlsx"):
            df = pd.read_excel(path, engine="openpyxl")
        else:
            df = pd.read_csv(path)
        print(f"[OK] Dataset loaded: {len(df)} rows, {len(df.columns)} cols ({path})")
        break
    except Exception as exc:  # pragma: no cover - defensive
        print(f"[WARN] Failed to load {path}: {exc}")

if df is None:
    raise FileNotFoundError("No dataset found for IED model. Please add ml/career_recommendation_dataset_ied.xlsx")

# Target column
target_col = "Recommended_Career" if "Recommended_Career" in df.columns else "Career"
y = df[target_col]

# Program detection
program_col = None
if "Program" in df.columns:
    program_col = "Program"
elif "Current_Program" in df.columns:
    program_col = "Current_Program"

has_program = program_col is not None

# Feature columns
apt_cols = [f"Apt_Q{i}" for i in range(1, 11)]
int_cols = [f"Int_Q{i}" for i in range(1, 11)]
pers_cols = [f"Pers_Q{i}" for i in range(1, 11)]
cs_cols = [f"CS_Q{i}" for i in range(1, 11)]
it_cols = [f"IT_Q{i}" for i in range(1, 11)]
is_cols = [f"IS_Q{i}" for i in range(1, 11)]
act_cols = [f"ACT_Q{i}" for i in range(1, 11)]

general_cols = apt_cols + int_cols + pers_cols
program_specific_cols = cs_cols + it_cols + is_cols + act_cols

# Program standardization (include ICS + IED)
program_mapping = {
    "CS": ["CS", "COMPUTER SCIENCE", "BSCS", "BACHELOR OF SCIENCE IN COMPUTER SCIENCE"],
    "IT": ["IT", "INFORMATION TECHNOLOGY", "BSIT", "BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY"],
    "IS": ["IS", "INFORMATION SYSTEMS", "BSIS", "BACHELOR OF SCIENCE IN INFORMATION SYSTEMS"],
    "ACT": ["ACT", "COMPUTER TECHNOLOGY", "ASSOCIATE IN COMPUTER TECHNOLOGY"],
    "ICS": ["ICS", "INFORMATION AND COMPUTER SCIENCE", "BSICS", "INFORMATION & COMPUTER SCIENCE"],
    "IED": [
        "IED",
        "INDUSTRIAL ENGINEERING DESIGN",
        "BSIED",
        "INDUSTRIAL ENGINEERING",
        "BSIE",
        "BACHELOR OF SCIENCE IN INDUSTRIAL ENGINEERING",
    ],
}

reverse_program_map = {val.upper(): key for key, variants in program_mapping.items() for val in variants}

# Detect Excel yes/no format
needs_encoding = "Apt_Q1" in df.columns
if needs_encoding:
    print("[OK] Detected Excel format (Yes/No). Encoding to 0/1.")
    mapping = {"Yes": 1, "No": 0, "yes": 1, "no": 0}
    for col in general_cols + program_specific_cols:
        if col in df.columns:
            df[col] = df[col].map(mapping).fillna(-1).astype(int)

# Add standardized program column
if has_program:
    def standardize_program(value: str) -> str:
        if not isinstance(value, str):
            return "Unknown"
        val_upper = value.upper()
        return reverse_program_map.get(val_upper, "Unknown")

    df["Program_Standard"] = df[program_col].apply(standardize_program)
    print("[OK] Program standardization complete (ICS/IED aware)")
else:
    print("[WARN] No program column detected; proceeding without program features")

# Encode target labels
print("\n[INFO] Encoding target labels...")
le = LabelEncoder()
y_encoded = le.fit_transform(y)
print(f"[OK] Encoded {len(le.classes_)} careers")

# Class weights from IED map
if USE_WEIGHTS and has_program:
    print("[INFO] Building class weights from career_weight_map_ied...")
    class_weights = {}
    for idx, career_name in enumerate(le.classes_):
        weights = career_weight_map.get(career_name, {})
        if weights:
            avg_weight = np.mean(list(weights.values()))
            relevance_multiplier = (avg_weight / 3.0) + 0.5  # scale 0-3 -> 0.5-1.5
            class_weights[idx] = relevance_multiplier
        else:
            class_weights[idx] = 1.0

    # Normalize
    max_w = max(class_weights.values()) if class_weights else 1.0
    class_weights = {k: v / max_w for k, v in class_weights.items()}
    class_weight_param = class_weights
    print(f"[OK] Custom class weights applied ({len(class_weights)} classes)")
else:
    print("[OK] Using balanced class weights")
    class_weight_param = "balanced"

# Encode program and add relevance features
if has_program:
    print("\n[INFO] Encoding program and relevance features...")
    program_encoder = LabelEncoder()
    program_encoder.fit(df[program_col])

    # Ensure IED is present so encoder can handle it at prediction time
    classes = list(program_encoder.classes_)
    if "IED" not in classes:
        classes.append("IED")
        classes = sorted(set(classes))
        program_encoder.classes_ = np.array(classes)
        print("[OK] Added 'IED' to program encoder classes")

    df["Program_Encoded"] = program_encoder.transform(df[program_col])

    relevance_codes = ["CS", "IT", "IS", "ACT", "ICS", "IED"]
    for code in relevance_codes:
        feature_name = f"Relevance_{code}"
        df[feature_name] = 0.0
        if "Program_Standard" in df.columns:
            mask = df["Program_Standard"] == code
            df.loc[mask, feature_name] = 1.0
    print("[OK] Relevance features added")
else:
    program_encoder = None

# Apply question weights (vectorized)
if USE_QUESTION_WEIGHTS:
    print("\n[INFO] Applying question weights...")
    all_question_cols = apt_cols + int_cols + pers_cols + cs_cols + it_cols + is_cols + act_cols

    for col in all_question_cols:
        if col in df.columns:
            df[col] = df[col].astype(float)

    if has_program and "Program_Standard" in df.columns:
        program_std_series = df["Program_Standard"]
    else:
        program_std_series = None

    for col in all_question_cols:
        if col not in df.columns or col not in question_weight_map:
            continue
        weight_cfg = question_weight_map[col]
        if isinstance(weight_cfg, dict):
            if program_std_series is not None:
                weight_series = program_std_series.map(lambda x: weight_cfg.get(x, 1.0))
                mask = weight_series != 1.0
                if mask.any():
                    df.loc[mask, col] = df.loc[mask, col] * weight_series[mask]
        else:
            weight = float(weight_cfg)
            if weight != 1.0:
                df[col] = df[col] * weight
    print("[OK] Question weights applied")
else:
    print("[OK] Default question weights (1.0) applied")

# Build feature matrix
cols_to_drop = [target_col]
if program_col and program_col in df.columns:
    cols_to_drop.append(program_col)
if "Program_Standard" in df.columns:
    cols_to_drop.append("Program_Standard")

X = df.drop(cols_to_drop, axis=1, errors="ignore")

# Train/test split
print("\n[INFO] Splitting data...")
X_train, X_test, y_train, y_test = train_test_split(
    X,
    y_encoded,
    test_size=0.2,
    random_state=42,
    stratify=y_encoded if len(np.unique(y_encoded)) > 1 else None,
)
print(f"[OK] Train: {len(X_train)} | Test: {len(X_test)}")

# Model training
print("\n[INFO] Training IED Random Forest model...")
model = RandomForestClassifier(
    n_estimators=200,
    max_depth=16,
    min_samples_split=5,
    min_samples_leaf=5,
    max_features="sqrt",
    class_weight=class_weight_param,
    random_state=42,
    n_jobs=-1,
)
model.fit(X_train, y_train)
print("[OK] Training complete!")

# Evaluation
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

# Save artifacts
print("\n[INFO] Saving IED model artifacts...")
ML_DIR = os.path.dirname(os.path.abspath(__file__))

joblib.dump(model, os.path.join(ML_DIR, "career_model_ied.pkl"), compress=3)
joblib.dump(le, os.path.join(ML_DIR, "label_encoder_ied.pkl"))

if program_encoder is not None:
    joblib.dump(program_encoder, os.path.join(ML_DIR, "program_encoder_ied.pkl"))
    print("[OK] Program encoder saved")

if USE_WEIGHTS:
    joblib.dump(career_weight_map, os.path.join(ML_DIR, "career_weights_map_ied.joblib"))
    # also export a JSON copy for PHP loader
    with open(os.path.join(ML_DIR, "career_weights_map_ied.json"), "w", encoding="utf-8") as f:
        json.dump(career_weight_map, f, ensure_ascii=False, indent=2)
    print("[OK] Career weights map saved (joblib + json)")

if USE_QUESTION_WEIGHTS:
    joblib.dump(question_weight_map, os.path.join(ML_DIR, "question_weights_map_ied.joblib"))
    print("[OK] Question weights map saved")

with open(os.path.join(ML_DIR, "model_metrics_ied.json"), "w", encoding="utf-8") as f:
    json.dump(metrics, f, indent=2)
print("[OK] Metrics saved to ml/model_metrics_ied.json")

print("\n[DONE] IED model training complete.")
