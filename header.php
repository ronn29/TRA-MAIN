<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>



<body>
<header class="site-header">
    
    <nav class="primary-nav">
        <div class="nav_header">
            <div class="nav_logo">
                <a href="#" class="logo">TRAGABAY</a>
                <div class="nav_logo_icons">
                    <img src="assets/img/pcb_logo.png" alt="PCB logo">
                    <img src="assets/img/guidance_logo.png" alt="Guidance logo">
                </div>
            </div>

            <?php if ($currentPage === 'index.php'): ?>
                <div class="nav_menu_btn" id="menu_btn">
                    <i class="fa-solid fa-bars"></i>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($currentPage === 'index.php'): ?>
            <ul class="nav_links" id="nav_links">
                <li><a href="#welcome_section" class="active">Home</a></li>
                <li><a href="#feature">Feature</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#footer">Contact</a></li>
            </ul>
        <?php endif; ?>
    </nav>
</header>
