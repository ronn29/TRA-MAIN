import pandas as pd

# Load dataset (adjust filename if needed)
df = pd.read_excel("ml/career_recommendation_dataset.xlsx")

# Identify question columns
question_cols = [c for c in df.columns if "_Q" in c]

# Convert Yes/No → 1/0 safely
for col in question_cols:
    df[col] = (
        df[col]
        .replace({"Yes": 1, "No": 0})
        .fillna(0)        # 🔑 FIX: handle missing values
        .astype(int)
    )

# Base weights per group
base_weights = {
    "Apt": 1.5,
    "Int": 1.3,
    "Pers": 1.2,
    "CS": 2.0,
    "IT": 2.0,
    "IS": 2.0,
    "ACT": 2.0
}

# Add weight + weighted columns
for col in question_cols:
    prefix = col.split("_")[0]
    weight = base_weights.get(prefix, 1.0)
    df[f"{col}_weight"] = weight
    df[f"{col}_weighted"] = df[col] * weight

# Save output
df.to_excel("ml/career_recommendation_dataset_weighted.xlsx", index=False)

print("✅ Weighted dataset created successfully!")
