"""
Predict careers for IED students using the dedicated IED model.
Falls back to the base ICS/general model if IED artifacts are missing.
"""

import argparse
import json
import os
from typing import Dict, List, Tuple

import joblib
import numpy as np
import pandas as pd


ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

# IED-first artifact paths
MODEL_FILE_IED = os.path.join(ROOT, "ml", "career_model_ied.pkl")
LABEL_ENCODER_IED = os.path.join(ROOT, "ml", "label_encoder_ied.pkl")
PROGRAM_ENCODER_IED = os.path.join(ROOT, "ml", "program_encoder_ied.pkl")
QUESTION_WEIGHTS_IED = os.path.join(ROOT, "ml", "question_weights_map_ied.joblib")
METRICS_FILE_IED = os.path.join(ROOT, "ml", "model_metrics_ied.json")

# Fallback to base (ICS/general) artifacts when IED is missing
MODEL_FILE_BASE = os.path.join(ROOT, "ml", "career_model.pkl")
LABEL_ENCODER_BASE = os.path.join(ROOT, "ml", "label_encoder.pkl")
PROGRAM_ENCODER_BASE = os.path.join(ROOT, "ml", "program_encoder.pkl")
QUESTION_WEIGHTS_BASE = os.path.join(ROOT, "ml", "question_weights_map.joblib")
METRICS_FILE_BASE = os.path.join(ROOT, "ml", "model_metrics.json")


RELEVANCE_CODES = ["CS", "IT", "IS", "ACT", "ICS", "IED"]
FEATURE_GROUPS = {
    "apt": [f"Apt_Q{i}" for i in range(1, 11)],
    "int": [f"Int_Q{i}" for i in range(1, 11)],
    "pers": [f"Pers_Q{i}" for i in range(1, 11)],
    "cs": [f"CS_Q{i}" for i in range(1, 11)],
    "it": [f"IT_Q{i}" for i in range(1, 11)],
    "is": [f"IS_Q{i}" for i in range(1, 11)],
    "act": [f"ACT_Q{i}" for i in range(1, 11)],
}

FEATURE_COLUMNS: List[str] = (
    ["Program_Encoded"]
    + FEATURE_GROUPS["apt"]
    + FEATURE_GROUPS["int"]
    + FEATURE_GROUPS["pers"]
    + FEATURE_GROUPS["cs"]
    + FEATURE_GROUPS["it"]
    + FEATURE_GROUPS["is"]
    + FEATURE_GROUPS["act"]
    + [f"Relevance_{code}" for code in RELEVANCE_CODES]
)


def standardize_program(program_value: str) -> str:
    if not isinstance(program_value, str):
        return "Unknown"
    upper = program_value.upper()
    if "IED" in upper or "INDUSTRIAL ENGINEERING" in upper:
        return "IED"
    if "ICS" in upper or "INFORMATION AND COMPUTER SCIENCE" in upper:
        return "ICS"
    if "CS" in upper or "COMPUTER SCIENCE" in upper:
        return "CS"
    if "IT" in upper or "INFORMATION TECHNOLOGY" in upper:
        return "IT"
    if "IS" in upper or "INFORMATION SYSTEM" in upper:
        return "IS"
    if "ACT" in upper or "COMPUTER TECHNOLOGY" in upper:
        return "ACT"
    return "Unknown"


def load_first_existing(path_candidates: List[str]):
    for path in path_candidates:
        if os.path.isfile(path):
            return path
    return None


def load_artifacts():
    model_file = load_first_existing([MODEL_FILE_IED, MODEL_FILE_BASE])
    label_file = load_first_existing([LABEL_ENCODER_IED, LABEL_ENCODER_BASE])
    program_file = load_first_existing([PROGRAM_ENCODER_IED, PROGRAM_ENCODER_BASE])
    qweights_file = load_first_existing([QUESTION_WEIGHTS_IED, QUESTION_WEIGHTS_BASE])

    if not model_file or not label_file:
        raise FileNotFoundError("Model or label encoder not found. Train ml/train_ied.py first.")

    model = joblib.load(model_file)
    label_enc = joblib.load(label_file)
    program_enc = joblib.load(program_file) if program_file and os.path.isfile(program_file) else None

    question_weights = {}
    if qweights_file and os.path.isfile(qweights_file):
        try:
            question_weights = joblib.load(qweights_file)
        except Exception:
            question_weights = {}

    metrics_file = METRICS_FILE_IED if os.path.isfile(METRICS_FILE_IED) else METRICS_FILE_BASE
    metrics = None
    if metrics_file and os.path.isfile(metrics_file):
        try:
            with open(metrics_file, "r", encoding="utf-8") as f:
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

    # Case-insensitive match
    for cls in classes:
        if cls.lower() == program_value.lower():
            return int(program_enc.transform([cls])[0]), warnings

    warnings.append(f"Program '{program_value}' not seen during training; using '{classes[0]}'")
    return int(program_enc.transform([classes[0]])[0]), warnings


def build_feature_row(feature_payload: Dict[str, int], program_encoded: int, program_value: str, model, question_weights) -> pd.DataFrame:
    expected_features = list(model.feature_names_in_) if hasattr(model, "feature_names_in_") else FEATURE_COLUMNS

    program_standard = standardize_program(program_value)

    feature_dict: Dict[str, float] = {"Program_Encoded": float(program_encoded)}

    for col in FEATURE_COLUMNS:
        if col == "Program_Encoded" or col.startswith("Relevance_"):
            continue
        value = float(feature_payload.get(col, -1))

        # Apply question weight when available
        if col in question_weights:
            weight_cfg = question_weights[col]
            if isinstance(weight_cfg, dict):
                value *= float(weight_cfg.get(program_standard, 1.0))
            else:
                value *= float(weight_cfg)

        feature_dict[col] = value

    # Relevance flags
    for code in RELEVANCE_CODES:
        feature_dict[f"Relevance_{code}"] = 1.0 if program_standard == code else 0.0

    values = [feature_dict.get(col, 0.0) for col in expected_features]
    return pd.DataFrame([values], columns=expected_features)


def predict(payload: Dict) -> Dict:
    model, label_enc, program_enc, question_weights, metrics = load_artifacts()

    program_value = payload.get("current_program", "") or ""
    program_encoded, warn_prog = encode_program(program_enc, program_value) if program_enc else (0, [])

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
