const menu_btn = document.getElementById('menu_btn');
const nav_links = document.getElementById('nav_links');

if (menu_btn && nav_links) {
    const menu_btn_icon = menu_btn.querySelector('i');

    menu_btn.addEventListener('click', () => {
        nav_links.classList.toggle('open');
    });

    // Close menu when clicking a nav link
    const navLinkItems = nav_links.querySelectorAll('a');
    navLinkItems.forEach(link => {
        link.addEventListener('click', () => {
            nav_links.classList.remove('open');
        });
    });
}

// Simple scroll reveal
const revealEls = document.querySelectorAll('.reveal-on-scroll');
if (revealEls.length > 0) {
    const observer = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15 }
    );

    revealEls.forEach(el => observer.observe(el));
}
  // Password visibility toggles
        document.querySelectorAll('.toggle-password').forEach(btn => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            btn.addEventListener('click', () => {
                const isPassword = target.type === 'password';
                target.type = isPassword ? 'text' : 'password';
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
        });

