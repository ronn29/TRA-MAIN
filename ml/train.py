import pandas as pd
import numpy as np
import os
import joblib
import matplotlib.pyplot as plt

from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    precision_score,
    recall_score,
    f1_score,
    confusion_matrix,
    ConfusionMatrixDisplay
)
from sklearn.preprocessing import LabelEncoder

# ==============================
# LOAD QUESTION WEIGHTS (OPTIONAL)
# ==============================
try:
    from question_weights_map import question_weight_map
    USE_QUESTION_WEIGHTS = True
    print("✓ Question weights map loaded")
except ImportError:
    USE_QUESTION_WEIGHTS = False
    question_weight_map = {}
    print("⚠ question_weights_map not found")

# ==============================
# LOAD DATASET
# ==============================
print("\n📂 Loading dataset...")
file_path = os.path.join("ml", "career_recommendation_dataset_with_career_weights.xlsx")
df = pd.read_excel(file_path, engine="openpyxl")
print(f"✓ Loaded {len(df)} samples")

# ==============================
# TARGET & PROGRAM
# ==============================
target_col = "Recommended_Career" if "Recommended_Career" in df.columns else "Career"
y = df[target_col]

program_col = "Program" if "Program" in df.columns else (
    "Current_Program" if "Current_Program" in df.columns else None
)
has_program = program_col is not None

# ==============================
# VERIFY CAREER WEIGHT COLUMNS
# ==============================
weight_cols = ["CS_weight", "IT_weight", "IS_weight", "ACT_weight"]
missing = [c for c in weight_cols if c not in df.columns]
if missing:
    raise ValueError(f"❌ Missing career weight columns: {missing}")

# ==============================
# QUESTION COLUMNS
# ==============================
apt_cols  = [f"Apt_Q{i}"  for i in range(1, 11)]
int_cols  = [f"Int_Q{i}"  for i in range(1, 11)]
pers_cols = [f"Pers_Q{i}" for i in range(1, 11)]
cs_cols   = [f"CS_Q{i}"   for i in range(1, 11)]
it_cols   = [f"IT_Q{i}"   for i in range(1, 11)]
is_cols   = [f"IS_Q{i}"   for i in range(1, 11)]
act_cols  = [f"ACT_Q{i}"  for i in range(1, 11)]

all_question_cols = (
    apt_cols + int_cols + pers_cols +
    cs_cols + it_cols + is_cols + act_cols
)

# ==============================
# ENCODE YES / NO
# ==============================
mapping = {"Yes": 1, "No": 0, "yes": 1, "no": 0}

for col in all_question_cols:
    if col in df.columns:
        df[col] = df[col].map(mapping).fillna(-1).astype(float)

# ==============================
# PROGRAM STANDARDIZATION
# ==============================
if has_program:
    program_map = {
        "CS":  ["CS", "Computer Science", "BSCS"],
        "IT":  ["IT", "Information Technology", "BSIT"],
        "IS":  ["IS", "Information Systems", "BSIS"],
        "ACT": ["ACT", "Computer Technology"]
    }
    reverse_map = {v: k for k, vals in program_map.items() for v in vals}
    df["Program_Standard"] = df[program_col].map(reverse_map).fillna("Unknown")

# ==============================
# LABEL ENCODING
# ==============================
le = LabelEncoder()
y_encoded = le.fit_transform(y)

# ==============================
# CLASS WEIGHTS (CAREER LEVEL)
# ==============================
class_weights = {}

for idx, career in enumerate(le.classes_):
    rows = df[df[target_col] == career]
    if not rows.empty:
        avg_rel = rows[weight_cols].mean().mean()
        class_weights[idx] = 0.7 + (avg_rel / 3.0) * 0.6
    else:
        class_weights[idx] = 1.0

# normalize
max_w = max(class_weights.values())
class_weights = {k: v / max_w for k, v in class_weights.items()}

print("✓ Class weights created")

# ==============================
# PROGRAM–CAREER RELEVANCE FEATURES
# ==============================
for p in ["CS", "IT", "IS", "ACT"]:
    df[f"Relevance_{p}"] = df[f"{p}_weight"] / 3.0

# ==============================
# QUESTION WEIGHTS (FEATURE LEVEL)
# ==============================
if USE_QUESTION_WEIGHTS:
    for col, weight in question_weight_map.items():
        if col in df.columns:
            if isinstance(weight, dict) and has_program:
                df[col] *= df["Program_Standard"].map(
                    lambda x: weight.get(x, 1.0)
                )
            elif isinstance(weight, (int, float)):
                df[col] *= float(weight)

print("✓ Question weights applied")

# ==============================
# CREATE FEATURES (X)
# ==============================
drop_cols = [target_col, program_col, "Program_Standard"]
X = df.drop(columns=[c for c in drop_cols if c in df.columns], errors="ignore")

# ==============================
# SAMPLE WEIGHTS (ROW LEVEL)
# ==============================
sample_weights = np.ones(len(df), dtype=float)

if has_program:
    prog_to_col = {
        "CS": "CS_weight",
        "IT": "IT_weight",
        "IS": "IS_weight",
        "ACT": "ACT_weight"
    }

    for i in df.index:
        prog = df.loc[i, "Program_Standard"]
        if prog in prog_to_col:
            rel = df.loc[i, prog_to_col[prog]]
            sample_weights[i] = 0.7 + (rel / 3.0) * 0.6

print("✓ Sample weights created")

# ==============================
# TRAIN / TEST SPLIT
# ==============================
X_train, X_test, y_train, y_test, w_train, w_test = train_test_split(
    X, y_encoded, sample_weights,
    test_size=0.20,
    random_state=42,
    stratify=y_encoded
)

# ==============================
# RANDOM FOREST MODEL
# ==============================
model = RandomForestClassifier(
    n_estimators=200,
    max_depth=16,
    min_samples_leaf=5,
    max_features="sqrt",
    class_weight=class_weights,
    random_state=42,
    n_jobs=-1
)

model.fit(X_train, y_train, sample_weight=w_train)

# ==============================
# EVALUATION
# ==============================
y_pred = model.predict(X_test)

print("\n📊 Model Performance")
print(f"Accuracy : {accuracy_score(y_test, y_pred)*100:.2f}%")
print(f"Precision: {precision_score(y_test, y_pred, average='weighted', zero_division=0)*100:.2f}%")
print(f"Recall   : {recall_score(y_test, y_pred, average='weighted', zero_division=0)*100:.2f}%")
print(f"F1 Score : {f1_score(y_test, y_pred, average='weighted', zero_division=0)*100:.2f}%")

# ==============================
# CONFUSION MATRIX
# ==============================
print("\n🧩 Confusion Matrix")

cm = confusion_matrix(y_test, y_pred)
disp = ConfusionMatrixDisplay(confusion_matrix=cm, display_labels=le.classes_)

fig, ax = plt.subplots(figsize=(14, 14))
disp.plot(cmap="Blues", xticks_rotation=90, ax=ax, values_format="d")
plt.title("Confusion Matrix – Career Recommendation Model")
plt.tight_layout()
plt.show()

# ==============================
# NORMALIZED CONFUSION MATRIX
# ==============================
cm_norm = confusion_matrix(y_test, y_pred, normalize="true")
disp_norm = ConfusionMatrixDisplay(confusion_matrix=cm_norm, display_labels=le.classes_)

fig, ax = plt.subplots(figsize=(14, 14))
disp_norm.plot(cmap="Blues", xticks_rotation=90, ax=ax, values_format=".2f")
plt.title("Normalized Confusion Matrix (Recall per Career)")
plt.tight_layout()
plt.show()

# ==============================
# SAVE MODEL FILES
# ==============================
ML_DIR = os.path.dirname(os.path.abspath(__file__))

joblib.dump(model, os.path.join(ML_DIR, "career_model.pkl"), compress=3)
joblib.dump(le, os.path.join(ML_DIR, "label_encoder.pkl"))

if USE_QUESTION_WEIGHTS:
    joblib.dump(question_weight_map, os.path.join(ML_DIR, "question_weights_map.joblib"))

print("\n✅ Training complete. All artifacts saved.")
