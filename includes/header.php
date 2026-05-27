<?php
/**
 * EventSnap Cloud - Universal Dynamic Responsive Header
 * Adapts between Sidebar SaaS Workspace and Elegant Marketing Top Navbar
 * RESPONSIVE: Mobile hamburger sidebar + overlay for dashboard pages
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

$isLoggedIn = Auth::isLoggedIn();
$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

// Determine active view mode (Sidebar SaaS Dashboard vs Elegant Public Landing)
$activePage = basename($_SERVER['PHP_SELF']);
$isHostDashboard = in_array($activePage, [
    'dashboard.php', 'create-event.php', 'edit-event.php', 'event-analytics.php', 'admin.php', 'media-crew.php', 'pricing.php'
]) && $isLoggedIn && ($userRole === 'owner' || $userRole === 'admin');

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . " | EventSnap" : "EventSnap - Capture Every Moment, Instantly."; ?></title>
    
    <!-- SEO Metadata -->
    <meta name="description" content="Sleek, app-free wedding and event photo capturing platform. Simply scan QR codes, take snaps, and compile custom guest galleries.">
    <meta name="keywords" content="EventSnap, photo capture, wedding QR code, guest photos, event gallery sharing, app-free photo uploads">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/default-banner.jpg?v=<?php echo @filemtime(__DIR__ . '/../assets/default-banner.jpg'); ?>">
    
    <!-- Google Fonts & Material Icons -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    
    <!-- Centralized Tailwind configuration -->
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "on-secondary": "#ffffff",
                    "on-surface": "#0b1c30",
                    "surface-container-high": "#dce9ff",
                    "primary-fixed": "#e9ddff",
                    "on-tertiary": "#ffffff",
                    "on-surface-variant": "#494454",
                    "surface-container-highest": "#d3e4fe",
                    "surface-tint": "#6d3bd7",
                    "on-background": "#0b1c30",
                    "on-primary-container": "#fffbff",
                    "on-primary": "#ffffff",
                    "on-secondary-fixed-variant": "#5a4139",
                    "on-primary-fixed": "#23005c",
                    "primary": "#6b38d4",
                    "secondary-fixed-dim": "#e3bfb4",
                    "on-tertiary-container": "#fffbff",
                    "secondary-container": "#fdd8cc",
                    "on-tertiary-fixed-variant": "#673d00",
                    "surface": "#ffffff",
                    "surface-container": "#ffffff",
                    "secondary": "#745850",
                    "on-secondary-fixed": "#2a1710",
                    "surface-bright": "#ffffff",
                    "inverse-primary": "#d0bcff",
                    "tertiary": "#855000",
                    "surface-variant": "#d3e4fe",
                    "primary-container": "#8455ef",
                    "tertiary-fixed-dim": "#ffb869",
                    "secondary-fixed": "#ffdbd0",
                    "on-error-container": "#93000a",
                    "on-secondary-container": "#785c54",
                    "surface-dim": "#ffffff",
                    "error-container": "#ffdad6",
                    "background": "#ffffff",
                    "surface-container-low": "#ffffff",
                    "inverse-surface": "#213145",
                    "outline": "#7b7486",
                    "error": "#ba1a1a",
                    "tertiary-container": "#a76500",
                    "on-error": "#ffffff",
                    "on-primary-fixed-variant": "#5516be",
                    "tertiary-fixed": "#ffdcbb",
                    "on-tertiary-fixed": "#2c1700",
                    "surface-container-lowest": "#ffffff",
                    "inverse-on-surface": "#eaf1ff",
                    "outline-variant": "#cbc3d7",
                    "primary-fixed-dim": "#d0bcff"
            },
            "borderRadius": {
                    "DEFAULT": "1rem",
                    "lg": "2rem",
                    "xl": "3rem",
                    "full": "9999px"
            },
            "spacing": {
                    "gutter": "24px",
                    "stack-md": "16px",
                    "stack-sm": "8px",
                    "container-padding-desktop": "40px",
                    "container-padding-mobile": "16px",
                    "base": "8px",
                    "stack-lg": "32px"
            },
            "fontFamily": {
                    "headline-lg": ["Plus Jakarta Sans"],
                    "label-sm": ["Inter"],
                    "display-lg": ["Plus Jakarta Sans"],
                    "headline-md": ["Plus Jakarta Sans"],
                    "body-lg": ["Inter"],
                    "body-md": ["Inter"]
            },
            "fontSize": {
                    "headline-lg": ["32px", {"lineHeight": "1.2", "letterSpacing": "-0.01em", "fontWeight": "700"}],
                    "label-sm": ["14px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "600"}],
                    "display-lg": ["48px", {"lineHeight": "1.1", "letterSpacing": "-0.02em", "fontWeight": "800"}],
                    "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                    "body-lg": ["18px", {"lineHeight": "1.6", "fontWeight": "400"}],
                    "body-md": ["16px", {"lineHeight": "1.5", "fontWeight": "400"}]
            }
          },
        },
      }
    </script>
    
    <!-- Dropzone.js CSS -->
    <link href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" rel="stylesheet" type="text/css" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbc3d7; border-radius: 10px; }

        /* Sidebar slide transition */
        #app-sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #sidebar-overlay {
            transition: opacity 0.3s ease;
        }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md min-h-screen">

<?php if ($isHostDashboard): ?>
    <!-- ═══════════════════════════════════════════════════════════
         SIDEBAR SAAS DASHBOARD LAYOUT (Responsive)
    ════════════════════════════════════════════════════════════════ -->

    <!-- MOBILE: Dark overlay that appears behind open sidebar -->
    <div id="sidebar-overlay"
         class="fixed inset-0 z-30 bg-black/50 opacity-0 pointer-events-none lg:hidden"
         onclick="closeSidebar()">
    </div>

    <!-- SIDEBAR: Hidden off-screen on mobile, always visible on lg+ -->
    <aside id="app-sidebar"
           class="h-screen w-72 fixed left-0 top-0 z-40
                  -translate-x-full lg:translate-x-0
                  bg-white border-r border-outline-variant/30
                  flex flex-col py-8 pr-4 shadow-xl lg:shadow-none">

        <!-- Brand + Close button row -->
        <div class="px-gutter mb-stack-lg flex items-center justify-between">
            <span class="font-display-lg text-headline-md font-bold text-primary tracking-tight">EventSnap</span>
            <!-- Close button (mobile only) -->
            <button onclick="closeSidebar()"
                    class="lg:hidden p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant transition-colors"
                    aria-label="Close menu">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- User identity card -->
        <div class="px-gutter mb-stack-lg flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-primary/20 text-primary font-bold flex items-center justify-center border-2 border-primary/20 text-sm flex-shrink-0">
                <?php echo strtoupper(substr($userName, 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <p class="font-headline-md text-label-sm text-on-surface truncate"><?php echo htmlspecialchars($userName); ?></p>
                <p class="font-body-md text-[12px] text-on-surface-variant"><?php echo htmlspecialchars(ucfirst($userRole)); ?> Workspace</p>
            </div>
        </div>
        
        <!-- Nav links -->
        <nav class="flex-grow flex flex-col gap-1">
            <a class="<?php echo $activePage === 'dashboard.php' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?> rounded-r-full flex items-center gap-3 p-4 transition-all active:scale-95 no-underline" href="dashboard.php" onclick="closeSidebar()">
                <span class="material-symbols-outlined">calendar_today</span>
                <span class="font-label-sm">Events</span>
            </a>
            <a class="<?php echo $activePage === 'media-crew.php' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?> rounded-r-full flex items-center gap-3 p-4 transition-all active:scale-95 no-underline" href="media-crew.php" onclick="closeSidebar()">
                <span class="material-symbols-outlined">photo_library</span>
                <span class="font-label-sm">Media Crew</span>
            </a>
            <?php if ($userRole === 'admin'): ?>
                <a class="<?php echo $activePage === 'admin.php' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?> rounded-r-full flex items-center gap-3 p-4 transition-all active:scale-95 no-underline" href="admin.php" onclick="closeSidebar()">
                    <span class="material-symbols-outlined">security</span>
                    <span class="font-label-sm">Admin Panel</span>
                </a>
            <?php endif; ?>
            <a class="<?php echo $activePage === 'pricing.php' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?> rounded-r-full flex items-center gap-3 p-4 transition-all active:scale-95 no-underline" href="pricing.php" onclick="closeSidebar()">
                <span class="material-symbols-outlined">diamond</span>
                <span class="font-label-sm">Upgrade Plan</span>
            </a>
        </nav>
        
        <!-- New Event CTA -->
        <div class="px-gutter mt-auto">
            <a href="create-event.php"
               class="w-full text-center py-3.5 px-6 bg-primary text-white font-bold text-sm rounded-xl shadow-lg shadow-primary/20 hover:-translate-y-0.5 transition-transform active:scale-95 flex items-center justify-center gap-2 no-underline">
                <span class="material-symbols-outlined text-[18px]">add</span>
                New Event
            </a>
        </div>
    </aside>

    <!-- MOBILE TOP BAR (visible only below lg breakpoint) -->
    <header class="lg:hidden fixed top-0 left-0 right-0 z-20 h-16
                   bg-white border-b border-outline-variant/30
                   flex items-center justify-between px-4 shadow-sm">
        <!-- Hamburger -->
        <button onclick="openSidebar()"
                class="p-2 rounded-lg hover:bg-surface-container-high text-on-surface-variant transition-colors"
                aria-label="Open menu">
            <span class="material-symbols-outlined">menu</span>
        </button>

        <!-- Brand -->
        <span class="font-display-lg text-[1.1rem] font-bold text-primary tracking-tight">EventSnap</span>

        <!-- Logout shortcut -->
        <a href="logout.php"
           class="p-2 rounded-full hover:bg-primary/5 text-primary transition-colors flex items-center justify-center no-underline"
           title="Logout">
            <span class="material-symbols-outlined text-[20px]">logout</span>
        </a>
    </header>

    <!-- MAIN CONTENT AREA -->
    <!-- pt-16 on mobile to clear the fixed top bar; lg:pt-0 removes it on desktop -->
    <main class="lg:ml-72 pt-16 lg:pt-0 px-4 py-5 lg:px-container-padding-desktop lg:py-container-padding-desktop min-h-screen">
        <!-- Desktop page header -->
        <header class="hidden lg:flex justify-between items-end mb-stack-lg">
            <div>
                <p class="font-body-md text-on-surface-variant mb-1">Welcome back, <?php echo htmlspecialchars($userName); ?></p>
                <h1 class="font-headline-lg text-headline-lg text-on-surface"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard Overview'; ?></h1>
            </div>
            <div class="flex items-center gap-stack-md">
                <a href="logout.php" class="p-3 rounded-full hover:bg-primary/5 transition-colors text-primary flex items-center justify-center w-10 h-10 no-underline" title="Logout">
                    <span class="material-symbols-outlined">logout</span>
                </a>
            </div>
        </header>

        <!-- Mobile page title (below top bar) -->
        <div class="lg:hidden mb-5">
            <h1 class="text-xl font-bold text-on-surface"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></h1>
        </div>

<?php else: ?>
    <!-- ═══════════════════════════════════════════════════════════
         ELEGANT PUBLIC MARKETING NAV
    ════════════════════════════════════════════════════════════════ -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom border-light py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.6rem; color: var(--color-brand); text-decoration: none;">
                <span><i class="bi bi-camera-fill"></i></span>
                <span>EventSnap</span>
            </a>
            
            <div class="d-flex align-items-center gap-3 ms-auto">
                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-brand-outline py-2 px-3">Go to Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-box-arrow-right"></i></a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-sm btn-outline-glass border-0 fw-bold text-muted px-3 text-decoration-none">Login</a>
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-brand text-decoration-none">Create Your Event</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main class="flex-grow-1">
<?php endif; ?>

<?php if ($isHostDashboard): ?>
<!-- Sidebar toggle JavaScript -->
<script>
    function openSidebar() {
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100');
        document.body.style.overflow = '';
    }

    // Auto-close sidebar on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            closeSidebar();
        }
    });
</script>
<?php endif; ?>
