"""
Predict careers using the saved Random Forest model.

Input (JSON):
{
  "current_program": "BSCS",
  "features": {
    "Program_Encoded": <ignored>,
    "Apt_Q1": 1,
    ...
    "ACT_Q10": 0
  }
}

Output (JSON):
{
  "prediction": "Software Engineer",
  "top_probs": [{"career": "...", "prob": 0.42}, ...],
  "program_used": "BSCS",
  "warnings": []
}
"""

import argparse
import json
import os
from typing import Dict, List

import joblib
import numpy as np
import pandas as pd


ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
# Load career_model.pkl (from train.py with career weights integration)
# Only uses the new model - no fallback to old model files
# Check both root directory and ml subdirectory (train.py saves to root)
MODEL_FILE_ML = os.path.join(ROOT, "ml", "career_model.pkl")
MODEL_FILE_ROOT = os.path.join(ROOT, "career_model.pkl")
CAREER_ENCODER_FILE_ML = os.path.join(ROOT, "ml", "label_encoder.pkl")
CAREER_ENCODER_FILE_ROOT = os.path.join(ROOT, "label_encoder.pkl")
PROGRAM_ENCODER_FILE_ML = os.path.join(ROOT, "ml", "program_encoder.pkl")
PROGRAM_ENCODER_FILE_ROOT = os.path.join(ROOT, "program_encoder.pkl")
QUESTION_WEIGHTS_FILE_ML = os.path.join(ROOT, "ml", "question_weights_map.joblib")
QUESTION_WEIGHTS_FILE_ROOT = os.path.join(ROOT, "question_weights_map.joblib")
METRICS_FILE = os.path.join(ROOT, "ml", "model_metrics.json")


FEATURE_GROUPS = {
    "apt": [f"Apt_Q{i}" for i in range(1, 11)],
    "int": [f"Int_Q{i}" for i in range(1, 11)],
    "pers": [f"Pers_Q{i}" for i in range(1, 11)],
    "cs": [f"CS_Q{i}" for i in range(1, 11)],
    "it": [f"IT_Q{i}" for i in range(1, 11)],
    "is": [f"IS_Q{i}" for i in range(1, 11)],
    "act": [f"ACT_Q{i}" for i in range(1, 11)],
}

FEATURE_COLUMNS = (
    ["Program_Encoded"]
    + FEATURE_GROUPS["apt"]
    + FEATURE_GROUPS["int"]
    + FEATURE_GROUPS["pers"]
    + FEATURE_GROUPS["cs"]
    + FEATURE_GROUPS["it"]
    + FEATURE_GROUPS["is"]
    + FEATURE_GROUPS["act"]
    + ["Relevance_CS", "Relevance_IT", "Relevance_IS", "Relevance_ACT"]  # Added by train.py when USE_WEIGHTS=True
)


def load_artifacts():
    # Load career_model.pkl (from train.py with career weights integration)
    # Check both root and ml directory (train.py saves to root when run from root)
    model_file = MODEL_FILE_ROOT if os.path.isfile(MODEL_FILE_ROOT) else MODEL_FILE_ML
    career_encoder_file = CAREER_ENCODER_FILE_ROOT if os.path.isfile(CAREER_ENCODER_FILE_ROOT) else CAREER_ENCODER_FILE_ML
    program_encoder_file = PROGRAM_ENCODER_FILE_ROOT if os.path.isfile(PROGRAM_ENCODER_FILE_ROOT) else PROGRAM_ENCODER_FILE_ML
    question_weights_file = QUESTION_WEIGHTS_FILE_ROOT if os.path.isfile(QUESTION_WEIGHTS_FILE_ROOT) else QUESTION_WEIGHTS_FILE_ML
    
    if not os.path.isfile(model_file):
        raise FileNotFoundError(f"Model file not found. Checked: '{MODEL_FILE_ROOT}' and '{MODEL_FILE_ML}'. Please train the model first using train.py")
    
    if not os.path.isfile(career_encoder_file):
        raise FileNotFoundError(f"Career encoder file not found. Checked: '{CAREER_ENCODER_FILE_ROOT}' and '{CAREER_ENCODER_FILE_ML}'. Please train the model first using train.py")
    
    model = joblib.load(model_file)
    career_enc = joblib.load(career_encoder_file)
    program_enc = joblib.load(program_encoder_file) if os.path.isfile(program_encoder_file) else None
    
    # Load question weights if available
    question_weights = {}
    if os.path.isfile(question_weights_file):
        try:
            question_weights = joblib.load(question_weights_file)
        except Exception:
            question_weights = {}
    
    return model, career_enc, program_enc, question_weights


def encode_program(program_enc, program_value: str):
    """Encode program; gracefully handle unseen values by best-effort matching."""
    warnings = []
    classes = list(program_enc.classes_)

    if program_value in classes:
        return program_enc.transform([program_value])[0], warnings

    # Try case-insensitive match
    for cls in classes:
        if cls.lower() == program_value.lower():
            return program_enc.transform([cls])[0], warnings

    # Fallback to first class with warning
    warnings.append(
        f"Program '{program_value}' not in encoder; using '{classes[0]}' instead."
    )
    return program_enc.transform([classes[0]])[0], warnings


def build_feature_row(feature_payload: Dict[str, int], program_encoded: int, program_value: str = "", model=None, **kwargs) -> pd.DataFrame:
    """Ensure all expected columns are present; default missing to -1."""
    # Use model's feature names if available to ensure correct order
    if model is not None and hasattr(model, 'feature_names_in_'):
        expected_features = list(model.feature_names_in_)
    else:
        expected_features = FEATURE_COLUMNS
    
    # Build feature dictionary
    feature_dict = {}
    
    # Add Program_Encoded
    feature_dict["Program_Encoded"] = program_encoded
    
    # Calculate program standard first (needed for program-specific weights)
    program_standard = "Unknown"
    if program_value:
        program_upper = program_value.upper()
        if "CS" in program_upper or "COMPUTER SCIENCE" in program_upper:
            program_standard = "CS"
        elif "IT" in program_upper or "INFORMATION TECHNOLOGY" in program_upper:
            program_standard = "IT"
        elif "IS" in program_upper or "INFORMATION SYSTEMS" in program_upper:
            program_standard = "IS"
        elif "ACT" in program_upper or "COMPUTER TECHNOLOGY" in program_upper or "ASSOCIATE" in program_upper:
            program_standard = "ACT"
    
    # Add all question features from payload (apply weights if available)
    question_weights = kwargs.get('question_weights', {})
    
    for col in FEATURE_COLUMNS:
        if col != "Program_Encoded" and not col.startswith("Relevance_"):
            value = float(feature_payload.get(col, -1))
            # Apply question weight if available
            if col in question_weights:
                weight_config = question_weights[col]
                
                # Check if weight is program-specific (dict) or universal (number)
                if isinstance(weight_config, dict):
                    # Get weight for this student's program
                    weight = weight_config.get(program_standard, 1.0)  # Default to 1.0 if program not found
                else:
                    # Universal weight (same for all programs)
                    weight = float(weight_config)
                
                value = value * weight
            feature_dict[col] = value
    
    # Calculate program standard (needed for both relevance features and question weights)
    # This is done here so it can be used for question weights above
    if not program_standard or program_standard == "Unknown":
        program_standard = "Unknown"
        if program_value:
            program_upper = program_value.upper()
            if "CS" in program_upper or "COMPUTER SCIENCE" in program_upper:
                program_standard = "CS"
            elif "IT" in program_upper or "INFORMATION TECHNOLOGY" in program_upper:
                program_standard = "IT"
            elif "IS" in program_upper or "INFORMATION SYSTEMS" in program_upper:
                program_standard = "IS"
            elif "ACT" in program_upper or "COMPUTER TECHNOLOGY" in program_upper or "ASSOCIATE" in program_upper:
                program_standard = "ACT"
    
    # Set relevance features based on student's program
    # During training, these were set based on career-program relevance
    # During prediction, we set them based on the student's program to provide context
    # Set relevance for the student's program to 1.0 (maximum relevance)
    # This tells the model "this student is from program X" which helps it
    # prioritize careers relevant to that program
    feature_dict["Relevance_CS"] = 1.0 if program_standard == "CS" else 0.0
    feature_dict["Relevance_IT"] = 1.0 if program_standard == "IT" else 0.0
    feature_dict["Relevance_IS"] = 1.0 if program_standard == "IS" else 0.0
    feature_dict["Relevance_ACT"] = 1.0 if program_standard == "ACT" else 0.0
    
    # Create DataFrame with values in the exact order expected by the model
    values = [feature_dict.get(col, 0.0) for col in expected_features]
    
    return pd.DataFrame([values], columns=expected_features)


def predict(payload: Dict) -> Dict:
    model, career_enc, program_enc, question_weights = load_artifacts()

    program_value = payload.get("current_program", "") or ""
    
    # Handle case where program_encoder might be None (if model was trained without program)
    if program_enc is not None:
        program_encoded, warn_prog = encode_program(program_enc, program_value)
    else:
        program_encoded = 0  # Default to 0 if no program encoder
        warn_prog = ["Program encoder not available, using default encoding"]

    feature_payload = payload.get("features", {}) or {}
    df_row = build_feature_row(feature_payload, program_encoded, program_value, model, question_weights=question_weights)

    # Predictions
    probs = model.predict_proba(df_row)[0]
    pred_idx = int(np.argmax(probs))
    pred_label = career_enc.inverse_transform([pred_idx])[0]

    top_indices = np.argsort(probs)[::-1][:5]
    top_probs = [
        {"career": career_enc.inverse_transform([i])[0], "prob": float(probs[i])}
        for i in top_indices
    ]

    metrics = None
    if os.path.isfile(METRICS_FILE):
        try:
            with open(METRICS_FILE, "r", encoding="utf-8") as f:
                metrics = json.load(f)
        except Exception:
            metrics = None

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
    except Exception as e:
        print(json.dumps({"error": f"Invalid JSON: {e}"}))
        return

    try:
        result = predict(payload)
        print(json.dumps(result))
    except Exception as e:
        print(json.dumps({"error": str(e)}))


if __name__ == "__main__":
    main()

