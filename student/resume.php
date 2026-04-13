<?php 
    session_start();
    require '../db/dbconn.php';

    if (!isset($_SESSION['school_id'])) {
        header('Location: ../login.php');
        exit();
    }

    $school_id = $_SESSION['school_id'];
    
    $query = "SELECT * FROM student_tbl WHERE school_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $school_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);

    $default_fields = [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'email' => '',
        'contact_number' => '',
        'address' => '',
        'linkedin_profile' => '',
        'profile_picture' => '',
        'personal_statement' => '',
        'education' => '',
        'work_experience' => '',
        'skills' => '',
        'extracurricular' => '',
        'awards' => '',
        'ref' => ''
    ];

    if ($student) {
        $student = array_merge($default_fields, $student);
    } else {
        $student = $default_fields;
    }

    $activePage = 'resume';
    $photoVersion = $_SESSION['profile_img_version'] ?? time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Resume Builder</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
   
</head>
<body>
    <input type="checkbox" id="sidebar-toggle">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php include 'header.php'; ?>

        <div class="resume-container">
            <div class="resume-builder-header">
            <h2>
                <span class="las la-file-alt"></span>
                    Resume Builder
            </h2>
                <div class="resume-actions">
                    <a href="generate_pdf.php" class="btn btn-primary" id="downloadPdfBtn">
                        <span class="las la-file-pdf"></span>
                        Download PDF
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <span class="las la-check-circle"></span>
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <span class="las la-exclamation-circle"></span>
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="auto-save-indicator" id="autoSaveIndicator">
                <span class="las la-check"></span> Changes saved
            </div>

            <form method="POST" action="save_resume.php" id="resumeForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php
                    if (empty($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    echo htmlspecialchars($_SESSION['csrf_token']);
                ?>">
                <div class="resume-section" data-section="personal">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">1</span>
                            <span class="las la-user section-icon"></span>
                            Personal Information
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('personal')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-personal"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-personal">
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <div class="profile-picture-upload">
                            <div id="picturePreview">
                                <img src="profile_image.php?v=<?php echo $photoVersion; ?>" 
                                     alt="Profile Picture" class="profile-picture-preview" id="profilePreviewImg">
                            </div>
                            <div class="file-upload-wrapper">
                                <input type="file" id="profile_picture" name="profile_picture" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif" 
                                       class="file-upload-input" onchange="previewProfilePicture(this)">
                                <button type="button" class="file-upload-btn" onclick="document.getElementById('profile_picture').click()">
                                    <span class="las la-camera"></span> Choose Photo
                                </button>
                                <?php if (!empty($student['profile_picture'])): ?>
                                    <button type="button" class="remove-picture-btn" onclick="removeProfilePicture()">
                                        <span class="las la-trash"></span> Remove Photo
                                    </button>
                                <?php endif; ?>
                            </div>
                            <small style="text-align: center; color: #666;">
                                Recommended: Square image, max 2MB. Formats: JPG, PNG, GIF
                            </small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" 
                                   value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>"
                                   placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Phone Number</label>
                            <input type="tel" id="contact_number" name="contact_number" 
                                   value="<?php echo htmlspecialchars($student['contact_number']); ?>"
                                   placeholder="e.g., +63 912 345 6789">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" 
                                  placeholder="Enter your complete address"><?php echo htmlspecialchars($student['address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="linkedin_profile">LinkedIn Profile (if available)</label>
                        <input type="url" id="linkedin_profile" name="linkedin_profile" 
                               value="<?php echo htmlspecialchars($student['linkedin_profile'] ?? ''); ?>"
                               placeholder="https://www.linkedin.com/in/yourprofile">
                        <small>Include your LinkedIn profile URL if you have one.</small>
                    </div>
                    </div>
                </div>

                <div class="resume-section" data-section="statement">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">2</span>
                            <span class="las la-quote-left section-icon"></span>
                            Personal Statement / Objective
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('statement')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-statement"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-statement">
                    <div class="form-group">
                        <label for="personal_statement">A brief summary (2-3 sentences) highlighting your goals, skills, and what you can offer</label>
                        <textarea id="personal_statement" name="personal_statement" 
                                  placeholder="Example: Motivated and dedicated student seeking opportunities to apply my skills and knowledge in a professional environment. Strong background in [your field] with excellent problem-solving abilities and a passion for continuous learning..."
                                  maxlength="500" oninput="updateCharCounter('personal_statement', 500)"><?php echo htmlspecialchars($student['personal_statement']); ?></textarea>
                        <div class="char-counter" id="counter-personal_statement">
                            <span id="count-personal_statement"><?php echo strlen($student['personal_statement']); ?></span>/500 characters
                        </div>
                        <small>Keep it concise (2-3 sentences) and focused on your career objectives, skills, and what you can offer.</small>
                    </div>
                    </div>
                </div>

                <div class="resume-section" data-section="education">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">3</span>
                            <span class="las la-graduation-cap section-icon"></span>
                            Education (Reverse-Chronological Order)
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('education')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-education"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-education">
                    <div class="form-group">
                        <label for="education">List your school, college, or university name. Include your degree/diploma/certificate and expected graduation year. Mention relevant coursework or academic achievements.</label>
                        <textarea id="education" name="education" 
                                  placeholder="Example (most recent first):&#10;Bachelor of Science in Computer Science&#10;University Name, City&#10;Expected Graduation: 2025&#10;Relevant Coursework: Data Structures, Algorithms, Database Systems&#10;Academic Achievement: Dean's List (2023-2024)&#10;&#10;High School Diploma&#10;School Name, City&#10;Graduated: 2020&#10;Academic Achievement: Valedictorian"><?php echo htmlspecialchars($student['education']); ?></textarea>
                        <small><strong>Reverse-Chronological Order:</strong> Start with your most recent education and work backwards. Include institution name, degree/diploma/certificate, expected graduation year, relevant coursework, and academic achievements.</small>
                    </div>
                    </div>
                </div>

                <div class="resume-section" data-section="work">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">4</span>
                            <span class="las la-briefcase section-icon"></span>
                            Work Experience (If Any) (Reverse-Chronological Order)
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('work')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-work"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-work">
                    <div class="form-group">
                        <label for="work_experience">List any part-time jobs, internships, or volunteer work. Include the company name, role, and dates worked. Mention key responsibilities and accomplishments.</label>
                        <textarea id="work_experience" name="work_experience" 
                                  placeholder="Example (most recent first):&#10;Intern - Company Name&#10;City, Country&#10;June 2023 - August 2023&#10;• Assisted with project development and testing&#10;• Participated in team meetings and contributed to design decisions&#10;• Completed assigned tasks on time and received positive feedback&#10;&#10;Part-time Job - Company Name&#10;City, Country&#10;January 2022 - December 2022&#10;• Managed customer inquiries and provided support&#10;• Maintained inventory records and processed orders"><?php echo htmlspecialchars($student['work_experience']); ?></textarea>
                        <small><strong>Reverse-Chronological Order:</strong> Starting with the latest and working through past jobs. Include company name, role, dates worked, and key responsibilities and accomplishments.</small>
                    </div>
                    </div>
                </div>

                <div class="resume-section" data-section="skills">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">5</span>
                            <span class="las la-tools section-icon"></span>
                            Skills
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('skills')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-skills"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-skills">
                    <div class="form-group">
                        <label for="skills">List relevant technical, soft, and language skills</label>
                        <textarea id="skills" name="skills" 
                                  placeholder="Example:&#10;Technical Skills: Python, JavaScript, HTML/CSS, MySQL, React, Node.js&#10;Soft Skills: Communication, Teamwork, Problem-solving, Leadership, Time Management&#10;Languages: English (Fluent), Filipino (Native), Spanish (Basic)"><?php echo htmlspecialchars($student['skills']); ?></textarea>
                        <small>List your relevant technical skills, soft skills, and language skills.</small>
                    </div>
                    </div>
                </div>

                <div class="resume-section" data-section="extracurricular">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">6</span>
                            <span class="las la-star section-icon"></span>
                            Extracurricular Activities & Leadership Roles
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('extracurricular')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-extracurricular"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-extracurricular">
                    <div class="form-group">
                        <label for="extracurricular">Highlight participation in clubs, organizations, or leadership positions</label>
                        <textarea id="extracurricular" name="extracurricular" 
                                  placeholder="Example:&#10;President - Computer Science Club (2023 - Present)&#10;• Organized monthly tech talks and workshops&#10;• Led a team of 10 members in organizing annual hackathon&#10;&#10;Member - Debate Team (2022 - 2023)&#10;• Participated in regional debate competitions&#10;• Won Best Speaker award in 2023&#10;&#10;Volunteer - Community Service Organization&#10;• Organized charity events and fundraisers"><?php echo htmlspecialchars($student['extracurricular']); ?></textarea>
                        <small>Include clubs, organizations, volunteer work, leadership positions, and other activities that showcase your involvement and leadership abilities.</small>
                    </div>
                    </div>
                </div>

                <!-- Awards Section -->
                <div class="resume-section" data-section="awards">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">7</span>
                            <span class="las la-trophy section-icon"></span>
                            Awards & Achievements
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('awards')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-awards"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-awards">
                    <div class="form-group">
                        <label for="awards">Mention any academic, sports, or extracurricular awards</label>
                        <textarea id="awards" name="awards" 
                                  placeholder="Example:&#10;Academic Awards:&#10;• Dean's List - Fall 2023, Spring 2024&#10;• Best Thesis Award - 2024&#10;• Academic Excellence Scholarship (2022-2024)&#10;&#10;Competitions:&#10;• First Place - Programming Competition 2023&#10;• Second Place - Hackathon 2022&#10;&#10;Sports:&#10;• MVP - Basketball Tournament 2023"><?php echo htmlspecialchars($student['awards']); ?></textarea>
                        <small>List any academic honors, sports achievements, extracurricular awards, competitions, or notable accomplishments.</small>
                    </div>
                    </div>
                </div>

                <div class="resume-section" data-section="references">
                    <div class="section-header">
                        <h3>
                            <span class="section-number">8</span>
                            <span class="las la-address-book section-icon"></span>
                            References (Optional)
                        </h3>
                        <button type="button" class="section-toggle" onclick="toggleSection('references')" title="Collapse/Expand">
                            <span class="las la-chevron-down" id="icon-references"></span>
                        </button>
                    </div>
                    <div class="section-content" id="content-references">
                    <div class="form-group">
                        <label for="ref">Include contact details of professors or past employers (if required)</label>
                        <textarea id="ref" name="ref" 
                                  placeholder="Example:&#10;Dr. John Doe&#10;Professor, Computer Science Department&#10;University Name&#10;Email: john.doe@university.edu&#10;Phone: +63 912 345 6789&#10;&#10;Ms. Jane Smith&#10;Manager, Company Name&#10;Email: jane.smith@company.com&#10;Phone: +63 912 345 6789"><?php echo htmlspecialchars($student['ref']); ?></textarea>
                        <small>Optional: Include name, title, organization, email, and phone number for professors or past employers (if required by the position you're applying for).</small>
                    </div>
                    </div>
                </div>
            </form>

            <div class="floating-actions" id="floatingActions" style="display: none;">
                <button type="button" class="btn btn-outline floating-preview" id="floatingPreviewBtn" onclick="previewResume()">
                    <span class="las la-eye"></span>
                    Preview
                </button>
                <button type="submit" form="resumeForm" class="btn btn-success floating-save" id="floatingSaveBtn">
                    <span class="las la-save"></span>
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <div id="resumePreview" class="resume-preview">
        <div class="preview-content">
            <div class="preview-header">
                <h2>Resume Preview</h2>
                <button class="preview-close" onclick="closePreview()">
                    <span class="las la-times"></span> Close
                </button>
            </div>
            <div id="previewContent">
            </div>
        </div>
    </div>
    
    <script>
        function previewResume() {
            const form = document.getElementById('resumeForm');
            const formData = new FormData(form);
            const preview = document.getElementById('resumePreview');
            const previewContent = document.getElementById('previewContent');

            let html = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
            
            const profilePicInput = document.getElementById('profile_picture');
            const existingPic = document.getElementById('profilePreviewImg');
            
            if (profilePicInput && profilePicInput.files && profilePicInput.files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    displayPreviewWithPicture(e.target.result);
                };
                reader.readAsDataURL(profilePicInput.files[0]);
                return; // Exit early, will continue in callback
            } else if (existingPic) {
                displayPreviewWithPicture(existingPic.src);
                return;
            } else {
                displayPreviewWithPicture(null);
            }
            
            function displayPreviewWithPicture(pictureSrc) {
                let previewHtml = '<div style="font-family: Arial, Helvetica, sans-serif; max-width: 210mm; min-height: 297mm; margin: 0 auto; padding: 10mm 12mm; background: white; color: #333; line-height: 1.4; box-sizing: border-box;">';
                
                if (pictureSrc) {
                    previewHtml += `<div style="text-align: center; margin-bottom: 6mm;">
                        <img src="${pictureSrc}" alt="Profile Picture" 
                             style="width: 113px; height: 113px; border-radius: 50%; object-fit: cover; border: 2px solid #3281db; display: inline-block;">
                    </div>`;
                }
                
                const firstName = formData.get('first_name') || '';
                const middleName = formData.get('middle_name') || '';
                const lastName = formData.get('last_name') || '';
                const email = formData.get('email') || '';
                const contact = formData.get('contact_number') || '';
                const address = formData.get('address') || '';
                const linkedin = formData.get('linkedin_profile') || '';

                if (firstName || lastName) {
                    const fullName = [firstName, middleName, lastName].filter(n => n).join(' ');
                    previewHtml += `<div style="text-align: center; margin-bottom: 2mm;">
                        <h1 style="color: #3281db; font-size: 16pt; font-weight: bold; margin: 0; padding: 0;">${fullName}</h1>
                    </div>`;
                }
                
                const contactInfo = [];
                if (email) contactInfo.push(email);
                if (contact) contactInfo.push(contact);
                if (address) contactInfo.push(address);
                if (linkedin) contactInfo.push('LinkedIn: ' + linkedin);
                
                if (contactInfo.length > 0) {
                    previewHtml += `<div style="text-align: center; margin-bottom: 3mm; color: #666; font-size: 8pt;">
                        ${contactInfo.join(' | ')}
                    </div>`;
                }

                function formatWithBullets(text) {
                    if (!text) return '';
                    const lines = text.split('\n');
                    let formatted = '';
                    lines.forEach(line => {
                        line = line.trim();
                        if (line) {
                            // If line doesn't start with bullet or dash, add bullet
                            if (line.indexOf('•') !== 0 && line.indexOf('-') !== 0) {
                                formatted += '• ' + line + '<br>';
                            } else {
                                formatted += line + '<br>';
                            }
                        }
                    });
                    return formatted;
                }

                const personalStatement = formData.get('personal_statement');
                if (personalStatement) {
                    // Limit to 2-3 sentences like PDF
                    let statement = personalStatement;
                    const sentences = statement.split(/(?<=[.!?])\s+/);
                    if (sentences.length > 3) {
                        statement = sentences.slice(0, 3).join(' ');
                    }
                    
                    previewHtml += `<div style="margin-bottom: 4mm;">
                        <h4 style="color: #3281db; font-size: 11pt; font-weight: bold; margin: 0 0 2mm 0; padding: 0;">Personal Statement / Objective</h4>
                        <p style="font-size: 9pt; margin: 0; line-height: 1.5; color: #000;">${statement.replace(/\n/g, '<br>')}</p>
                    </div>`;
                }

                const education = formData.get('education');
                if (education) {
                    previewHtml += `<div style="margin-bottom: 4mm;">
                        <h4 style="color: #3281db; font-size: 11pt; font-weight: bold; margin: 0 0 2mm 0; padding: 0;">Education</h4>
                        <p style="font-size: 9pt; margin: 0; line-height: 1.5; color: #000; white-space: pre-line;">${formatWithBullets(education)}</p>
                    </div>`;
                }

                const workExperience = formData.get('work_experience');
                if (workExperience) {
                    previewHtml += `<div style="margin-bottom: 4mm;">
                        <h4 style="color: #3281db; font-size: 11pt; font-weight: bold; margin: 0 0 2mm 0; padding: 0;">Work Experience</h4>
                        <p style="font-size: 9pt; margin: 0; line-height: 1.5; color: #000; white-space: pre-line;">${formatWithBullets(workExperience)}</p>
                    </div>`;
                }

                const skills = formData.get('skills');
                if (skills) {
                    previewHtml += `<div style="margin-bottom: 4mm;">
                        <h4 style="color: #3281db; font-size: 11pt; font-weight: bold; margin: 0 0 2mm 0; padding: 0;">Skills</h4>
                        <p style="font-size: 9pt; margin: 0; line-height: 1.5; color: #000; white-space: pre-line;">${formatWithBullets(skills)}</p>
                    </div>`;
                }

                const extracurricular = formData.get('extracurricular');
                if (extracurricular) {
                    previewHtml += `<div style="margin-bottom: 4mm;">
                        <h4 style="color: #3281db; font-size: 11pt; font-weight: bold; margin: 0 0 2mm 0; padding: 0;">Extracurricular Activities & Leadership Roles</h4>
                        <p style="font-size: 9pt; margin: 0; line-height: 1.5; color: #000; white-space: pre-line;">${formatWithBullets(extracurricular)}</p>
                    </div>`;
                }

                const awards = formData.get('awards');
                if (awards) {
                    previewHtml += `<div style="margin-bottom: 4mm;">
                        <h4 style="color: #3281db; font-size: 11pt; font-weight: bold; margin: 0 0 2mm 0; padding: 0;">Awards & Achievements</h4>
                        <p style="font-size: 9pt; margin: 0; line-height: 1.5; color: #000; white-space: pre-line;">${formatWithBullets(awards)}</p>
                    </div>`;
                }

                const ref = formData.get('ref');
                if (ref) {
                    previewHtml += `<div style="margin-bottom: 4mm;">
                        <h4 style="color: #3281db; font-size: 11pt; font-weight: bold; margin: 0 0 2mm 0; padding: 0;">References</h4>
                        <p style="font-size: 9pt; margin: 0; line-height: 1.5; color: #000; white-space: pre-line;">${ref.replace(/\n/g, '<br>')}</p>
                    </div>`;
                }

                previewHtml += '</div>';
                previewContent.innerHTML = previewHtml;
                preview.style.display = 'block';
            }
        }

        function closePreview() {
            document.getElementById('resumePreview').style.display = 'none';
        }

        document.getElementById('resumePreview').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });

        function previewProfilePicture(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, or GIF).');
                    input.value = '';
                    return;
                }
                
                if (file.size > 2 * 1024 * 1024) {
                    alert('Image size must be less than 2MB.');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('picturePreview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Profile Picture" class="profile-picture-preview" id="profilePreviewImg">`;
                    
                    const removeBtn = document.querySelector('.remove-picture-btn');
                    if (!removeBtn) {
                        const uploadWrapper = document.querySelector('.file-upload-wrapper');
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'remove-picture-btn';
                        removeBtn.innerHTML = '<span class="las la-trash"></span> Remove Photo';
                        removeBtn.onclick = removeProfilePicture;
                        uploadWrapper.appendChild(removeBtn);
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function removeProfilePicture() {
            const input = document.getElementById('profile_picture');
            const preview = document.getElementById('picturePreview');
            const removeBtn = document.querySelector('.remove-picture-btn');
            
            if (input) {
                input.value = '';
            }
            
            preview.innerHTML = '<div class="profile-picture-placeholder" id="profilePreviewPlaceholder"><span class="las la-user"></span></div>';
            
            if (removeBtn) {
                removeBtn.remove();
            }
            
            const form = document.getElementById('resumeForm');
            let removeInput = document.getElementById('remove_profile_picture');
            if (!removeInput) {
                removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.id = 'remove_profile_picture';
                removeInput.name = 'remove_profile_picture';
                removeInput.value = '1';
                form.appendChild(removeInput);
            }
        }

        function calculateProgress() {
            return;
        }

        function updateCharCounter(fieldId, maxLength) {
            const field = document.getElementById(fieldId);
            const counter = document.getElementById('counter-' + fieldId);
            const countSpan = document.getElementById('count-' + fieldId);
            
            if (field && counter && countSpan) {
                const length = field.value.length;
                countSpan.textContent = length;
                
                counter.className = 'char-counter';
                if (length > maxLength * 0.9) {
                    counter.className += ' warning';
                }
                if (length >= maxLength) {
                    counter.className += ' error';
                }
            }
            calculateProgress();
        }

        function toggleSection(sectionId) {
            const content = document.getElementById('content-' + sectionId);
            const icon = document.getElementById('icon-' + sectionId);
            
            if (content && icon) {
                if (content.classList.contains('collapsed')) {
                    content.classList.remove('collapsed');
                    icon.classList.remove('la-chevron-up');
                    icon.classList.add('la-chevron-down');
                } else {
                    content.classList.add('collapsed');
                    icon.classList.remove('la-chevron-down');
                    icon.classList.add('la-chevron-up');
                }
            }
        }

        let previewButtonShown = false;
        window.addEventListener('scroll', function() {
            const floatingActions = document.getElementById('floatingActions');
            const floatingSaveBtn = document.getElementById('floatingSaveBtn');
            const floatingPreviewBtn = document.getElementById('floatingPreviewBtn');
            
            if (window.scrollY > 300) {
                if (floatingActions) {
                    floatingActions.style.display = 'flex';
                    previewButtonShown = true;
                    if (floatingSaveBtn) {
                        floatingSaveBtn.style.display = 'block';
                    }
                    if (floatingPreviewBtn) {
                        floatingPreviewBtn.style.display = 'block';
                    }
                }
            } else {
                if (floatingActions && previewButtonShown) {
                    floatingActions.style.display = 'flex';
                    if (floatingSaveBtn) {
                        floatingSaveBtn.style.display = 'none';
                    }
                    if (floatingPreviewBtn) {
                        floatingPreviewBtn.style.display = 'block';
                    }
                } else if (floatingActions) {
                    floatingActions.style.display = 'none';
                }
            }
        });

        function showAutoSave() {
            const indicator = document.getElementById('autoSaveIndicator');
            if (indicator) {
                indicator.classList.add('show');
                setTimeout(() => {
                    indicator.classList.remove('show');
                }, 2000);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resumeForm');
            if (form) {
                const inputs = form.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        calculateProgress();
                    });
                    input.addEventListener('change', function() {
                        calculateProgress();
                    });
                });
            }
            
            calculateProgress();
            
            const personalStatement = document.getElementById('personal_statement');
            if (personalStatement) {
                updateCharCounter('personal_statement', 500);
            }
        });

        const form = document.getElementById('resumeForm');
        if (form) {
            form.addEventListener('submit', function() {
                showAutoSave();
            });
        }
    </script>
</body>
</html>
