"""
Predict careers for education students (BCAEd, BECEd, BEEd).
Falls back to the base model if education artifacts are missing.
"""

import argparse
import json
import os
from typing import Dict, List, Tuple

import joblib
import numpy as np
import pandas as pd

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

MODEL_FILE = os.path.join(ROOT, "ml", "career_model_educ.pkl")
LABEL_ENCODER_FILE = os.path.join(ROOT, "ml", "label_encoder_educ.pkl")
PROGRAM_ENCODER_FILE = os.path.join(ROOT, "ml", "program_encoder_educ.pkl")
QUESTION_WEIGHTS_FILE = os.path.join(ROOT, "ml", "question_weights_map_educ.joblib")
METRICS_FILE = os.path.join(ROOT, "ml", "model_metrics_educ.json")

RELEVANCE_CODES = ["BCAEd", "BECEd", "BEEd"]

FEATURE_GROUPS = {
    "apt": [f"Apt_Q{i}" for i in range(1, 11)],
    "pers": [f"Pers_Q{i}" for i in range(1, 11)],
    "int": [f"Int_Q{i}" for i in range(1, 11)],
}

FEATURE_COLUMNS: List[str] = (
    ["Program_Encoded"]
    + FEATURE_GROUPS["apt"]
    + FEATURE_GROUPS["pers"]
    + FEATURE_GROUPS["int"]
    + [f"Relevance_{code}" for code in RELEVANCE_CODES]
)


def standardize_program(program_value: str) -> str:
    if not isinstance(program_value, str):
        return "Unknown"
    upper = program_value.upper()
    if "BCAED" in upper or "CULTURE AND ARTS" in upper:
        return "BCAEd"
    if "BECED" in upper or "EARLY CHILDHOOD" in upper:
        return "BECEd"
    if "BEED" in upper or "ELEMENTARY EDUCATION" in upper:
        return "BEEd"
    return "Unknown"


def load_artifacts():
    if not os.path.isfile(MODEL_FILE):
        raise FileNotFoundError(
            f"Education model not found at {MODEL_FILE}. "
            "Run train_educ.py first."
        )
    if not os.path.isfile(LABEL_ENCODER_FILE):
        raise FileNotFoundError(
            f"Label encoder not found at {LABEL_ENCODER_FILE}. "
            "Run train_educ.py first."
        )

    model = joblib.load(MODEL_FILE)
    label_enc = joblib.load(LABEL_ENCODER_FILE)
    program_enc = joblib.load(PROGRAM_ENCODER_FILE) if os.path.isfile(PROGRAM_ENCODER_FILE) else None

    question_weights = {}
    if os.path.isfile(QUESTION_WEIGHTS_FILE):
        try:
            question_weights = joblib.load(QUESTION_WEIGHTS_FILE)
        except Exception:
            question_weights = {}

    metrics = None
    if os.path.isfile(METRICS_FILE):
        try:
            with open(METRICS_FILE, "r", encoding="utf-8") as f:
                metrics = json.load(f)
        except Exception:
            metrics = None

    return model, label_enc, program_enc, question_weights, metrics


def encode_program(program_enc, program_value: str) -> Tuple[int, List[str]]:
    warnings: List[str] = []
    if program_enc is None:
        return 0, ["Program encoder missing; defaulting to 0"]

    classes = list(program_enc.classes_)
    if program_value in classes:
        return int(program_enc.transform([program_value])[0]), warnings

    for cls in classes:
        if cls.lower() == program_value.lower():
            return int(program_enc.transform([cls])[0]), warnings

    warnings.append(f"Program '{program_value}' not seen during training; using '{classes[0]}'")
    return int(program_enc.transform([classes[0]])[0]), warnings


def build_feature_row(
    feature_payload: Dict[str, int],
    program_encoded: int,
    program_value: str,
    model,
    question_weights: dict,
) -> pd.DataFrame:
    expected_features = (
        list(model.feature_names_in_)
        if hasattr(model, "feature_names_in_")
        else FEATURE_COLUMNS
    )
    program_standard = standardize_program(program_value)
    feature_dict: Dict[str, float] = {"Program_Encoded": float(program_encoded)}

    for col in FEATURE_COLUMNS:
        if col == "Program_Encoded" or col.startswith("Relevance_"):
            continue
        value = float(feature_payload.get(col, -1))
        if col in question_weights:
            weight_cfg = question_weights[col]
            if isinstance(weight_cfg, dict):
                value *= float(weight_cfg.get(program_standard, 1.0))
            else:
                value *= float(weight_cfg)
        feature_dict[col] = value

    for code in RELEVANCE_CODES:
        feature_dict[f"Relevance_{code}"] = 1.0 if program_standard == code else 0.0

    values = [feature_dict.get(col, 0.0) for col in expected_features]
    return pd.DataFrame([values], columns=expected_features)


def predict(payload: Dict) -> Dict:
    model, label_enc, program_enc, question_weights, metrics = load_artifacts()

    program_value = payload.get("current_program", "") or ""
    if program_enc:
        program_encoded, warn_prog = encode_program(program_enc, program_value)
    else:
        program_encoded, warn_prog = 0, []

    features = payload.get("features", {}) or {}
    df_row = build_feature_row(features, program_encoded, program_value, model, question_weights)

    probs = model.predict_proba(df_row)[0]
    pred_idx = int(np.argmax(probs))
    pred_label = label_enc.inverse_transform([pred_idx])[0]

    top_indices = np.argsort(probs)[::-1][:5]
    top_probs = [
        {"career": label_enc.inverse_transform([i])[0], "prob": float(probs[i])}
        for i in top_indices
    ]

    return {
        "prediction": pred_label,
        "top_probs": top_probs,
        "program_used": program_value,
        "warnings": warn_prog,
        "metrics": metrics,
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", help="JSON payload string")
    parser.add_argument("--input-file", help="Path to JSON payload file")
    args = parser.parse_args()

    if not args.input_json and not args.input_file:
        print(json.dumps({"error": "No input provided"}))
        return

    try:
        if args.input_file:
            with open(args.input_file, "r", encoding="utf-8") as f:
                payload = json.load(f)
        else:
            payload = json.loads(args.input_json)
    except Exception as exc:
        print(json.dumps({"error": f"Invalid JSON: {exc}"}))
        return

    try:
        result = predict(payload)
        print(json.dumps(result))
    except Exception as exc:
        print(json.dumps({"error": str(exc)}))


if __name__ == "__main__":
    main()
