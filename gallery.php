<?php
/**
 * EventSnap Cloud - Event Masonry Cloud Gallery (Tailwind Theme Parity)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

$eventUuid = trim($_GET['id'] ?? '');
if (empty($eventUuid)) {
    header("Location: index.php");
    exit;
}

$event = EventManager::getEventByUuid($eventUuid);
if (!$event) {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Auth checks
$isOwner = (Auth::isLoggedIn() && $_SESSION['user_id'] === $event['owner_id']);
$isAdmin = (Auth::isLoggedIn() && $_SESSION['user_role'] === 'admin');
$isCrew  = false;

if (Auth::isLoggedIn() && $_SESSION['user_role'] === 'crew') {
    $mcStmt = $db->prepare("SELECT id FROM media_crew WHERE event_id = ? AND email = ? AND is_accepted = 1");
    $mcStmt->execute([$event['id'], $_SESSION['user_email']]);
    if ($mcStmt->fetch()) {
        $isCrew = true;
    }
}

$hasAccess = $isOwner || $isAdmin || $isCrew || (bool)$event['is_public_gallery'];

if (!$hasAccess) {
    header("HTTP/1.1 403 Forbidden");
    header("Location: index.php");
    exit;
}

// Moderation action (before HTML output)
$moderationMsg = '';
if (isset($_GET['delete_upload_id']) && ($isOwner || $isAdmin)) {
    $uploadId = (int)$_GET['delete_upload_id'];
    $csrf     = $_GET['csrf'] ?? '';

    if (verifyCSRFToken($csrf)) {
        try {
            $upStmt = $db->prepare("SELECT file_path FROM uploads WHERE id = ? AND event_id = ?");
            $upStmt->execute([$uploadId, $event['id']]);
            $upload = $upStmt->fetch();

            if ($upload) {
                $diskPath = __DIR__ . '/' . $upload['file_path'];
                if (file_exists($diskPath)) {
                    @unlink($diskPath);
                }
                $delStmt = $db->prepare("DELETE FROM uploads WHERE id = ?");
                $delStmt->execute([$uploadId]);
                $moderationMsg = "success:Photo successfully moderated and deleted.";
            }
        } catch (PDOException $e) {
            $moderationMsg = "danger:Database error: " . $e->getMessage();
        }
    } else {
        $moderationMsg = "danger:Security validation failed.";
    }
}

// Fetch approved captures
$photoStmt = $db->prepare("
    SELECT * FROM uploads
    WHERE event_id = ? AND is_approved = 1
    ORDER BY created_at DESC
");
$photoStmt->execute([$event['id']]);
$photos = $photoStmt->fetchAll();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>EventSnap - Event Gallery | <?php echo htmlspecialchars($event['name']); ?></title>
    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&amp;family=Inter:wght@400;600&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <!-- Tailwind Configuration -->
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
                    "container-padding-desktop": "64px",
                    "container-padding-mobile": "20px",
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
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.5);
            box-shadow: 0px 10px 30px rgba(107, 56, 212, 0.04);
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .masonry-item:hover .overlay {
            opacity: 1;
        }
        .active-filter {
            background-color: #6b38d4;
            color: #ffffff;
        }
        .lightbox-active {
            display: flex !important;
        }
        /* Custom scrollbar for better aesthetic */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f8f9ff;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbc3d7;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #6b38d4;
        }
    </style>
</head>
<body class="bg-surface font-body-md text-on-surface overflow-x-hidden">
    <!-- TopNavBar -->
    <nav class="fixed top-0 w-full z-50 bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-xl border-b border-outline-variant/20 shadow-sm">
        <div class="flex justify-between items-center px-gutter py-4 max-w-7xl mx-auto w-full">
            <span class="font-display-lg text-display-lg font-extrabold text-primary tracking-tight">EventSnap</span>
            <div class="hidden md:flex gap-8 items-center">
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm text-decoration-none" href="index.php">Product</a>
                <?php if (Auth::isLoggedIn()): ?>
                    <?php
                    $dashUrl = 'dashboard.php';
                    if ($_SESSION['user_role'] === 'admin') $dashUrl = 'admin.php';
                    elseif ($_SESSION['user_role'] === 'crew') $dashUrl = 'media-crew.php';
                    ?>
                    <a class="text-primary font-bold border-b-2 border-primary font-label-sm text-label-sm text-decoration-none" href="<?php echo $dashUrl; ?>">Dashboard</a>
                <?php endif; ?>
            </div>
            <?php if (Auth::isLoggedIn()): ?>
                <a href="logout.php" class="bg-primary text-on-primary px-6 py-2 rounded-full font-label-sm text-label-sm hover:scale-95 transition-transform text-decoration-none text-center">Logout</a>
            <?php else: ?>
                <a href="login.php" class="bg-primary text-on-primary px-6 py-2 rounded-full font-label-sm text-label-sm hover:scale-95 transition-transform text-decoration-none text-center">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="pt-32 pb-20 px-gutter max-w-7xl mx-auto">
        <!-- Moderation Feedback -->
        <?php if (!empty($moderationMsg)): 
            list($mType, $mText) = explode(':', $moderationMsg, 2);
        ?>
            <div class="mb-6 p-4 rounded-xl text-sm border-l-4 <?php echo $mType === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'; ?>" role="alert">
                <p class="font-bold"><?php echo $mType === 'success' ? 'Success' : 'Error'; ?></p>
                <p><?php echo htmlspecialchars($mText); ?></p>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <header class="flex flex-col md:flex-row justify-between items-end md:items-center gap-6 mb-12">
            <div>
                <span class="text-primary font-label-sm text-label-sm uppercase tracking-widest mb-2 block"><?php echo date('F d, Y', strtotime($event['date'])); ?></span>
                <h1 class="font-headline-lg text-headline-lg text-on-surface"><?php echo htmlspecialchars($event['name']); ?></h1>
                <p class="text-on-surface-variant mt-2 font-body-md text-body-md"><?php echo htmlspecialchars($event['description'] ?: 'Capturing the magic and sharing memories.'); ?></p>
            </div>
            <?php if ($isOwner || $isAdmin): ?>
                <a href="download-zip.php?id=<?php echo $event['id']; ?>" class="group flex items-center gap-2 bg-surface-container-low border border-primary/20 text-primary px-6 py-3 rounded-xl hover:bg-primary hover:text-on-primary transition-all duration-300 font-label-sm text-label-sm text-decoration-none">
                    <span class="material-symbols-outlined">download</span>
                    Download All (ZIP)
                </a>
            <?php endif; ?>
        </header>

        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center gap-3 mb-8">
            <button class="active-filter px-6 py-2 rounded-full font-label-sm text-label-sm border border-outline-variant/30 transition-all hover:border-primary" id="filter-all" onclick="filterMedia('all')">All Photos</button>
            <button class="bg-surface-container-lowest text-on-surface-variant px-6 py-2 rounded-full font-label-sm text-label-sm border border-outline-variant/30 transition-all hover:border-primary" id="filter-pro" onclick="filterMedia('pro')">Professional (Media Crew)</button>
            <button class="bg-surface-container-lowest text-on-surface-variant px-6 py-2 rounded-full font-label-sm text-label-sm border border-outline-variant/30 transition-all hover:border-primary" id="filter-guest" onclick="filterMedia('guest')">Guests</button>
        </div>

        <!-- Gallery Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="gallery">
            <?php if (empty($photos)): ?>
                <div class="col-span-full py-16 text-center bg-white border border-outline-variant/30 rounded-xl p-8 shadow-[0px_10px_30px_rgba(0,0,0,0.02)]">
                    <span class="material-symbols-outlined text-[64px] text-on-surface-variant mb-4">image_not_supported</span>
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-2">No photos uploaded yet</h3>
                    <p class="text-on-surface-variant mb-6 font-body-md">Be the first to share a memory from this event!</p>
                    <a href="guest-upload.php?id=<?php echo $event['event_uuid']; ?>" class="inline-flex items-center gap-2 bg-primary text-on-primary px-6 py-3 rounded-xl hover:opacity-90 transition-opacity font-label-sm text-decoration-none">
                        <span class="material-symbols-outlined">photo_camera</span>
                        Upload a Snap
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($photos as $ph): 
                    $roleClass = ($ph['uploader_role'] === 'guest') ? 'guest' : 'pro';
                    $roleLabel = ($ph['uploader_role'] === 'guest') ? 'Guest' : (($ph['uploader_role'] === 'crew') ? 'Media Crew' : 'Host Owner');
                ?>
                    <div class="masonry-item <?php echo $roleClass; ?> group relative glass-card rounded-lg overflow-hidden cursor-pointer aspect-square transition-transform duration-500 hover:-translate-y-2" onclick="openLightbox(this, '<?php echo htmlspecialchars($ph['uploader_name']); ?>', '<?php echo $roleLabel; ?>', '<?php echo date('H:i', strtotime($ph['created_at'])); ?>', '<?php echo htmlspecialchars($ph['caption']); ?>', <?php echo $ph['id']; ?>)">
                        <img alt="Gallery photo" class="w-full h-full object-cover rounded-[1.5rem] p-2" src="<?php echo BASE_URL . htmlspecialchars($ph['file_path']); ?>"/>
                        <div class="overlay absolute inset-0 bg-gradient-to-t from-primary/80 to-transparent opacity-0 transition-opacity duration-300 flex flex-col justify-end p-6">
                            <span class="text-on-primary font-label-sm text-label-sm mb-1"><?php echo htmlspecialchars($ph['uploader_name']); ?></span>
                            <span class="text-on-primary/70 text-xs flex items-center gap-1">
                                <?php if ($roleClass === 'pro'): ?>
                                    <span class="material-symbols-outlined text-[14px]">verified</span> 
                                <?php endif; ?>
                                <?php echo $roleLabel; ?>
                            </span>
                        </div>
                        <?php if ($isOwner || $isAdmin): ?>
                            <a href="gallery.php?id=<?php echo $eventUuid; ?>&delete_upload_id=<?php echo $ph['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>" 
                               class="absolute top-4 right-4 bg-red-600 hover:bg-red-700 text-white p-2 rounded-full shadow-lg hover:scale-110 transition-transform flex items-center justify-center w-8 h-8 z-20"
                               title="Moderate snap"
                               onclick="event.stopPropagation(); return confirm('Permanently delete photo from gallery?');">
                                <span class="material-symbols-outlined !text-sm">delete</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full mt-auto bg-surface-container-lowest border-t border-outline-variant/20">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-stack-lg px-gutter py-stack-lg max-w-7xl mx-auto items-center">
            <div class="font-headline-md text-headline-md font-bold text-primary">EventSnap</div>
            <div class="flex flex-wrap justify-center gap-6">
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm text-decoration-none" href="#">Product</a>
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm text-decoration-none" href="#">Weddings</a>
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm text-decoration-none" href="#">Corporate</a>
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm text-decoration-none" href="qr-code.php?id=<?php echo $event['event_uuid']; ?>">Get the QR</a>
            </div>
            <div class="text-on-surface-variant text-right font-label-sm text-label-sm">
                © <?php echo date('Y'); ?> EventSnap Cloud. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Lightbox Modal -->
    <div class="fixed inset-0 z-[100] hidden bg-on-surface/95 backdrop-blur-2xl flex-col items-center justify-center p-4 md:p-12 animate-in fade-in duration-300" id="lightbox">
        <!-- Prominent close button with backdrop and high contrast - extremely easy to tap on mobile -->
        <button class="absolute top-4 right-4 md:top-8 md:right-8 bg-black/40 hover:bg-black/60 text-white rounded-full p-2.5 flex items-center justify-center transition-all z-[110]" onclick="closeLightbox()" aria-label="Close lightbox">
            <span class="material-symbols-outlined text-[28px] md:text-[36px]">close</span>
        </button>
        <div class="relative max-w-5xl w-full h-full flex flex-col items-center justify-center pointer-events-none">
            <img alt="High resolution preview" class="max-h-[70vh] w-auto object-contain rounded-lg shadow-2xl pointer-events-auto" id="lightbox-img" src=""/>
            <div class="mt-8 w-full flex flex-col sm:flex-row justify-between items-start sm:items-end text-white gap-4 pointer-events-auto">
                <div>
                    <h3 class="font-headline-md text-headline-md" id="lightbox-uploader">Uploader Name</h3>
                    <p class="text-sm mt-1 text-white/80" id="lightbox-caption">Caption</p>
                    <div class="flex gap-4 mt-1 opacity-70 font-label-sm text-label-sm">
                        <span class="flex items-center gap-1" id="lightbox-role">Role</span>
                        <span class="flex items-center gap-1" id="lightbox-time">
                            <span class="material-symbols-outlined text-xs">schedule</span>
                            12:00
                        </span>
                    </div>
                </div>
                <div class="flex gap-4">
                    <a id="lightbox-download-btn" href="#" download class="bg-primary text-on-primary px-8 py-3 rounded-xl font-bold flex items-center gap-2 hover:scale-105 transition-transform text-decoration-none">
                        <span class="material-symbols-outlined">download</span>
                        Original HQ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Micro-interactions Script -->
    <script>
        function filterMedia(category) {
            const items = document.querySelectorAll('.masonry-item');
            const buttons = document.querySelectorAll('[id^="filter-"]');
            
            // Update buttons
            buttons.forEach(btn => {
                btn.classList.remove('active-filter', 'bg-primary', 'text-on-primary');
                btn.classList.add('bg-surface-container-lowest', 'text-on-surface-variant');
            });
            
            const activeBtn = document.getElementById(`filter-${category}`);
            activeBtn.classList.remove('bg-surface-container-lowest', 'text-on-surface-variant');
            activeBtn.classList.add('active-filter');

            // Filter items
            items.forEach(item => {
                item.style.display = 'block';
                item.classList.add('animate-in', 'zoom-in-95', 'duration-300');
                
                if (category !== 'all' && !item.classList.contains(category)) {
                    item.style.display = 'none';
                }
            });
        }

        function openLightbox(element, uploader, role, time, caption, uploadId) {
            const lightbox = document.getElementById('lightbox');
            const lbImg = document.getElementById('lightbox-img');
            const lbUploader = document.getElementById('lightbox-uploader');
            const lbRole = document.getElementById('lightbox-role');
            const lbTime = document.getElementById('lightbox-time');
            const lbCaption = document.getElementById('lightbox-caption');
            const lbDownloadBtn = document.getElementById('lightbox-download-btn');

            // Find the image source from the clicked item - robust cross-browser support
            const sourceImg = element.querySelector('img').src;
            
            lbImg.src = sourceImg;
            lbUploader.innerText = uploader;
            lbRole.innerText = role;
            lbCaption.innerText = caption ? caption : 'No caption entered.';
            lbTime.innerHTML = `<span class="material-symbols-outlined text-xs">schedule</span> ${time}`;
            lbDownloadBtn.href = sourceImg;

            lightbox.classList.add('lightbox-active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.classList.remove('lightbox-active');
            document.body.style.overflow = '';
        }

        // Close lightbox when clicking outside the content area (on the backdrop overlay)
        document.getElementById('lightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });

        // Close lightbox on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeLightbox();
        });

        // Simple parallax effect for grid items on scroll
        window.addEventListener('scroll', () => {
            const items = document.querySelectorAll('.masonry-item');
            const scrolled = window.pageYOffset;
            
            items.forEach((item, index) => {
                const speed = 0.05 + (index % 3) * 0.02;
                const yPos = -(scrolled * speed);
                if (window.innerWidth > 768) {
                    item.style.transform = `translateY(${Math.max(yPos / 10, -20)}px)`;
                }
            });
        });
    </script>
</body>
</html>
