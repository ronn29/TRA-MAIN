import pandas as pd

# Load dataset
df = pd.read_excel("ml/career_recommendation_dataset_weighted.xlsx")

# Career → weight map
career_weight_map = {
    "Software Engineer": {"CS": 3, "IT": 1, "IS": 1, "ACT": 0},
    "Systems Software Developer": {"CS": 3, "IT": 1, "IS": 0, "ACT": 0},
    "Research and Development Computing Professional": {"CS": 3, "IT": 0, "IS": 0, "ACT": 0},
    "Applications Software Developer": {"CS": 3, "IT": 1, "IS": 1, "ACT": 0},
    "Computer Programmer": {"CS": 2, "IT": 2, "IS": 1, "ACT": 1},
    "Systems Analyst": {"CS": 2, "IT": 2, "IS": 3, "ACT": 1},
    "Data Analyst": {"CS": 2, "IT": 1, "IS": 3, "ACT": 1},
    "Quality Assurance Specialist/Engineer": {"CS": 2, "IT": 2, "IS": 2, "ACT": 1},
    "Software Support Specialist": {"CS": 1, "IT": 3, "IS": 2, "ACT": 3},
    "IT Consultant": {"CS": 2, "IT": 3, "IS": 3, "ACT": 1},
    "IT Researcher": {"CS": 3, "IT": 2, "IS": 2, "ACT": 0},
    "Computer Science Instructor": {"CS": 3, "IT": 1, "IS": 1, "ACT": 0},
    "Database Programmer/Designer": {"CS": 2, "IT": 2, "IS": 3, "ACT": 1},
    "Information Security Engineer": {"CS": 2, "IT": 3, "IS": 1, "ACT": 2},
    "Web and Applications Developer": {"CS": 2, "IT": 3, "IS": 3, "ACT": 1},
    "Junior Database Administrator": {"CS": 1, "IT": 3, "IS": 3, "ACT": 1},
    "Systems Administrator": {"CS": 1, "IT": 3, "IS": 2, "ACT": 3},
    "Network Engineer": {"CS": 1, "IT": 3, "IS": 1, "ACT": 3},
    "Junior Information Security Administrator": {"CS": 1, "IT": 3, "IS": 1, "ACT": 3},
    "Systems Integration Personnel": {"CS": 2, "IT": 3, "IS": 2, "ACT": 2},
    "IT Audit Assistant": {"CS": 1, "IT": 2, "IS": 3, "ACT": 1},
    "Technical Support Specialist": {"CS": 1, "IT": 3, "IS": 2, "ACT": 3},
    "QA Specialist": {"CS": 2, "IT": 2, "IS": 3, "ACT": 2},
    "Organizational Process Analyst": {"CS": 1, "IT": 2, "IS": 3, "ACT": 1},
    "Solution Specialist": {"CS": 1, "IT": 2, "IS": 3, "ACT": 0},
    "IS Project Management Personnel": {"CS": 1, "IT": 2, "IS": 3, "ACT": 1},
    "Applications Developer": {"CS": 2, "IT": 3, "IS": 3, "ACT": 1},
    "End-User Trainer": {"CS": 0, "IT": 2, "IS": 3, "ACT": 1},
    "Documentation Specialist": {"CS": 0, "IT": 1, "IS": 3, "ACT": 1},
    "Business Process Specialist": {"CS": 0, "IT": 1, "IS": 3, "ACT": 0},
    "Data Quality Specialist": {"CS": 0, "IT": 2, "IS": 3, "ACT": 1},
    "Entrepreneur in IT": {"CS": 1, "IT": 3, "IS": 3, "ACT": 1},
    "IS Instructor": {"CS": 1, "IT": 1, "IS": 3, "ACT": 0},
    "Computer / Network System Administrator": {"CS": 0, "IT": 3, "IS": 1, "ACT": 3},
    "Computer / Network Support Technician": {"CS": 0, "IT": 3, "IS": 1, "ACT": 3},
    "Software Tester": {"CS": 2, "IT": 2, "IS": 3, "ACT": 1},
    "Programmer Analyst": {"CS": 2, "IT": 2, "IS": 2, "ACT": 1},
    "Network IT Administrator": {"CS": 0, "IT": 3, "IS": 1, "ACT": 3},
}

# Normalize career names
df["Recommended_Career"] = df["Recommended_Career"].astype(str).str.strip()

# Add weight columns
for w in ["CS", "IT", "IS", "ACT"]:
    df[f"{w}_weight"] = df["Recommended_Career"].apply(
        lambda c: career_weight_map.get(c, {}).get(w, 0)
    )

# Save new dataset
df.to_excel("ml/career_recommendation_dataset_with_career_weights.xlsx", index=False)
