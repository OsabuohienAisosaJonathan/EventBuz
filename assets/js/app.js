/**
 * EventSnap Cloud - Application Core JS
 * Handles premium UI sidebar switches, toggles, and client caching mechanisms.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initial Theme Sync (Supports dark/light mode but defaults to light layout)
    initTheme();

    // Toggle Password Visibility Utility
    const togglePassBtns = document.querySelectorAll('.toggle-password');
    togglePassBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const inputId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(inputId);
            if (passwordInput) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    passwordInput.type = 'password';
                    this.innerHTML = '<i class="bi bi-eye"></i>';
                }
            }
        });
    });

    // Mobile Sidebar Toggle
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
    const appSidebar = document.getElementById('appSidebar');
    if (toggleSidebarBtn && appSidebar) {
        toggleSidebarBtn.addEventListener('click', () => {
            appSidebar.classList.toggle('d-none');
            appSidebar.classList.toggle('d-flex');
        });
    }
});

/**
 * Initializes light/dark theme preference from LocalStorage (Light Mode default)
 */
function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        updateThemeToggleIcon('dark');
    } else {
        document.body.classList.remove('dark-mode');
        updateThemeToggleIcon('light');
    }
}

/**
 * Toggles theme between Light and Dark mode
 */
function toggleTheme() {
    if (document.body.classList.contains('dark-mode')) {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
        updateThemeToggleIcon('light');
    } else {
        document.body.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
        updateThemeToggleIcon('dark');
    }
}

/**
 * Updates the theme toggle icon visual state
 * @param {string} theme ('light' or 'dark')
 */
function updateThemeToggleIcon(theme) {
    const icon = document.getElementById('theme-toggle-icon');
    if (icon) {
        if (theme === 'light') {
            icon.className = 'bi bi-moon-fill';
        } else {
            icon.className = 'bi bi-sun-fill';
        }
    }
}
