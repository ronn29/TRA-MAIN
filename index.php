<!DOCTYPE html>
<html lang="en">
<?php
require './db/dbconn.php';
$feedbackItems = [];

$sql = "SELECT f.content, f.student_id, f.created_at, s.first_name
        FROM feedback_tbl f
        LEFT JOIN student_tbl s ON f.student_id = s.school_id
        WHERE f.status = 'approved'
        ORDER BY f.created_at DESC
        LIMIT 6";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $feedbackItems[] = $row;
    }
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/partials/favicon.php'; ?>
    <title>Tragabay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/free.css">
    <script src="https://kit.fontawesome.com/86d07e616e.js" crossorigin="anonymous" defer></script>


</head>
<body>
    
    <?php include 'header.php'; ?>

    <main>
        <section class="welcome_section" id="welcome_section">
            <div class="welcome_content reveal-on-scroll">
                <div class="welcome_text">
                    <h1>Welcome to<br> 
                    <span class="indent">Tragabay</span></h1>
                    <p>Discover tools and support built to guide students through every step of their academic journey.</p>
                    <div class="welcome_section_buttons">
                        <a href="login.php" class="btn btn-primary">Login</a>
                        <a href="register.php" class="btn btn-secondary">Register</a>
                    </div>
                </div>
                <div class="welcome_image">
                    <div class="wrapper">
                        <picture>
                            <source srcset="assets/img/undraw_online-stats_50mk.webp" type="image/webp">
                            <img src="assets/img/undraw_online-stats_50mk.png" alt="" fetchpriority="high" decoding="async">
                        </picture>
                        <picture>
                            <source srcset="assets/img/undraw_dev-productivity_5wps.webp" type="image/webp">
                            <img src="assets/img/undraw_dev-productivity_5wps.png" alt="" loading="lazy" decoding="async">
                        </picture>
                        <picture>
                            <source srcset="assets/img/undraw_programmer_raqr.webp" type="image/webp">
                            <img src="assets/img/undraw_programmer_raqr.png" alt="" loading="lazy" decoding="async">
                        </picture>
                        <picture>
                            <source srcset="assets/img/undraw_analysis_1k4x.webp" type="image/webp">
                            <img src="assets/img/undraw_analysis_1k4x.png" alt="" loading="lazy" decoding="async">
                        </picture>
                    </div>
                </div>
            </div>
        </section>

        <section class="feature" id="feature">
        <div class="feature-content reveal-on-scroll">
            <div class="card1">
                <h3>Career Assessment</h3>
                    <p>
                        provides assessment test for graduating students that helps to identify their strength and understand their career preferences
                    </p>
            </div>

            <div class="card2">
                <h3>Career Path Exploration </h3>
                    <p>
                        shows potential career paths based on programs
                        shows suitable career paths based on the result of assessment
                    </p>
            </div>

            <div class="card3">
                <h3>Resume Template</h3>
                    <p>
                        helps them to create a resume
                        offers industry-specific resume template 
                    </p>
            </div>
        </div>

        <div class="container reveal-on-scroll">
            <div class="text">
                <h2>
                    Appointment Scheduling
                </h2>
                <small>
                    shows real-time availability of guidance councilor <br> and students for verification
                </small>
            </div>
            <div class="image">
                <picture>
                    <source srcset="assets/img/undraw_schedule_6t8k.webp" type="image/webp">
                    <img src="assets/img/undraw_schedule_6t8k.png" alt="" loading="lazy" decoding="async">
                </picture>
            </div>
        </div>

        <div class="container2 reveal-on-scroll">
            <div class="image">
                <picture>
                    <source srcset="assets/img/undraw_online-articles_g9cg.webp" type="image/webp">
                    <img src="assets/img/undraw_online-articles_g9cg.png" alt="" loading="lazy" decoding="async">
                </picture>
            </div>
            <div class="text">
                <h2>
                    Skill Development Resource
                </h2>
                <small>provides links for online courses that matches <br>the result of assessment</small>
            </div>
        </div>
    </section>

    <section class="about" id="about">
        <div class="about-content reveal-on-scroll">
            <div class="card">
                <h3>What is Tragabay?</h3>
                <p>It helps students identify career paths that focusing with their skills, interests, and academic background. 
                    The system will provide personalized career recommendations based on assessments, making it easier for the 
                    students to explore some job opportunities that suit them. It features an automated resume generator with 
                    customizable templates, allowing users to create professional resumes. </p>
            </div>
    
            <div class="card">
                <h3>What is the Purpose of Tragabay?</h3>
                <p>Most of the students across the world are always in confusion after they complete Tertiary and the stage where 
                    they have to choose an appropriate career path. The students don’t have enough maturity to accurately know 
                    about what an individual has to follow in order to choose a suitable career path. </p>
            </div>
    
            <div class="card">
                <h3>How will Tragabay help?</h3>
                <p>The purpose of this research study is to  help the graduates thus graduating of  higher education students of
                    Institute of Computing Studies to identify the accurate careers that interest them.  This web-based career 
                    guidance would play a important part of their journey after graduate, because some students are confused 
                    of what kind of career  is the best suit for them based on their assessment. </p>
            </div>
        </div>
    </section>

    <section class="feedback" id="feedback">
        <div class="feedback_header reveal-on-scroll">
            <h2>What Students Say</h2>
            <p>Real stories from Tragabay users who found clarity in their career journey.</p>
        </div>
        <div class="feedback_scroller reveal-on-scroll">
            <div class="feedback_track">
                <?php if (!empty($feedbackItems)): ?>
                    <?php foreach ($feedbackItems as $fb): ?>
                        <?php
                            $firstName = trim($fb['first_name'] ?? '');
                            $displayName = $firstName !== '' ? $firstName : $fb['student_id'];
                        ?>
                        <article class="feedback_card">
                            <p>"<?php echo htmlspecialchars($fb['content']); ?>"</p>
                            <span>- <?php echo htmlspecialchars($displayName); ?></span>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No feedback available at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    </main>

    <?php include 'footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>