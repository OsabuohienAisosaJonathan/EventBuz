<?php
/**
 * EventSnap Cloud - Venue Presentation Live Slideshow
 * Full-screen projector monitor showing compiled guest photos with animations and polling feeds.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

$eventUuid = $_GET['id'] ?? '';
if (empty($eventUuid)) {
    header("Location: index.php");
    exit;
}

$event = EventManager::getEventByUuid($eventUuid);
if (!$event) {
    echo "<h1 style='color:red; text-align:center; margin-top:50px;'>404 Event Not Found</h1>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Slideshow | <?php echo htmlspecialchars($event['name']); ?></title>
    <meta name="description" content="Live guest photo slideshow for <?php echo htmlspecialchars($event['name']); ?>.">

    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #030305;
            font-family: 'Inter', sans-serif;
        }

        /* ── Container ────────────────────────────────── */
        #slideshowContainer {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Slide images ─────────────────────────────── */
        .slide-image {
            position: absolute;
            max-width: 95%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 14px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.85), 0 0 60px rgba(139,92,246,0.12);
            opacity: 0;
            transform: scale(0.96);
            transition: opacity 1.2s ease-in-out, transform 1.2s cubic-bezier(0.4,0,0.2,1);
            z-index: 1;
        }
        .slide-image.active {
            opacity: 1;
            transform: scale(1);
            z-index: 2;
        }

        /* ── Caption bar ──────────────────────────────── */
        #captionBar {
            position: absolute;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            background: rgba(10,10,15,0.82);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 18px;
            padding: 14px 28px;
            text-align: center;
            max-width: 80%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.55);
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
            pointer-events: none;
        }
        #captionBar.visible { opacity: 1; }

        #captionUploader {
            display: block;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }
        #captionText {
            color: rgba(255,255,255,0.55);
            font-size: 0.8rem;
        }

        /* ── Floating controls ────────────────────────── */
        #slideshowControls {
            position: absolute;
            top: 18px;
            right: 18px;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.12;
            transition: opacity 0.3s ease;
        }
        #slideshowControls:hover { opacity: 1; }

        .ctrl-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(20,20,30,0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        .ctrl-btn:hover { background: rgba(139,92,246,0.35); }

        .ctrl-select {
            height: 40px;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(20,20,30,0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #fff;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            outline: none;
        }
        .ctrl-select option { background: #1a1a2e; }

        /* ── Initial loader ───────────────────────────── */
        #initialSlideshowPlaceholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        .loader-ring {
            width: 52px;
            height: 52px;
            border: 4px solid rgba(255,255,255,0.1);
            border-top-color: #8b5cf6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        #initialSlideshowPlaceholder p {
            color: rgba(255,255,255,0.45);
            font-size: 0.85rem;
        }
        #initialSlideshowPlaceholder h4 {
            color: rgba(255,255,255,0.85);
            font-size: 1.05rem;
            font-weight: 600;
        }

        /* ── Event name badge ─────────────────────────── */
        #eventBadge {
            position: absolute;
            top: 18px;
            left: 18px;
            z-index: 20;
            background: rgba(10,10,15,0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 55vw;
        }
        #eventBadge .material-symbols-outlined {
            color: #8b5cf6;
            font-size: 18px;
        }
        #eventBadge span.name {
            color: rgba(255,255,255,0.85);
            font-weight: 700;
            font-size: 0.82rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>

    <!-- Event name badge (top-left) -->
    <div id="eventBadge">
        <span class="material-symbols-outlined">photo_camera</span>
        <span class="name"><?php echo htmlspecialchars($event['name']); ?></span>
    </div>

    <!-- Top floating controls (top-right) -->
    <div id="slideshowControls">
        <select class="ctrl-select" id="intervalSelector" title="Transition speed">
            <option value="4000">4 s</option>
            <option value="6000" selected>6 s</option>
            <option value="8000">8 s</option>
            <option value="10000">10 s</option>
        </select>
        <button class="ctrl-btn" id="toggleFullscreenBtn" title="Toggle Fullscreen">
            <span class="material-symbols-outlined" style="font-size:18px;">fullscreen</span>
        </button>
        <a href="gallery.php?id=<?php echo $eventUuid; ?>" class="ctrl-btn" title="Exit to Gallery">
            <span class="material-symbols-outlined" style="font-size:18px;">close</span>
        </a>
    </div>

    <!-- Slideshow container -->
    <div id="slideshowContainer">
        <div id="initialSlideshowPlaceholder">
            <div class="loader-ring"></div>
            <h4>Setting up live slideshow…</h4>
            <p>Aggregating guest photos in real time.</p>
        </div>
    </div>

    <!-- Caption bar -->
    <div id="captionBar">
        <span id="captionUploader">Contributor</span>
        <span id="captionText">Memory details</span>
    </div>

    <script>
        const eventUuid = '<?php echo $eventUuid; ?>';
        const baseUrl   = '<?php echo BASE_URL; ?>';

        // ── Fullscreen toggle ───────────────────────────
        document.getElementById('toggleFullscreenBtn').addEventListener('click', () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.warn('Fullscreen error:', err.message);
                });
            } else {
                document.exitFullscreen();
            }
        });

        // Update fullscreen icon
        document.addEventListener('fullscreenchange', () => {
            const icon = document.querySelector('#toggleFullscreenBtn .material-symbols-outlined');
            icon.textContent = document.fullscreenElement ? 'fullscreen_exit' : 'fullscreen';
        });

        // ── Interval selector ───────────────────────────
        document.getElementById('intervalSelector').addEventListener('change', function () {
            setSlideshowInterval(parseInt(this.value));
        });

        // ── Boot slideshow ──────────────────────────────
        initSlideshow(eventUuid, baseUrl);
    </script>

    <!-- Slideshow engine (vanilla JS, no jQuery) -->
    <script src="<?php echo BASE_URL; ?>assets/js/slideshow.js"></script>
</body>
</html>
