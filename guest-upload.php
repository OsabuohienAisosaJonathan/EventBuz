<?php
/**
 * EventSnap Cloud - Mobile-First App-Free Guest Upload Portal
 * Overhauled to match the exact guest upload design specified by the user.
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

$isExpired = strtotime($event['expires_at']) < time();
$guestNameCached = $_SESSION['guest_name'] ?? '';

// Fetch recently added approved photos to populate the thumbnail history slider
$db = getDBConnection();
$thumbs = [];
try {
    $stmt = $db->prepare("SELECT id, file_path FROM uploads WHERE event_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([$event['id']]);
    $thumbs = $stmt->fetchAll();
    
    // Count remaining uploads beyond the shown thumbnails
    $totalCount = $db->prepare("SELECT COUNT(*) FROM uploads WHERE event_id = ? AND is_approved = 1");
    $totalCount->execute([$event['id']]);
    $remCount = (int)$totalCount->fetchColumn() - 2;
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>EventSnap - Guest Upload</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;family=Plus+Jakarta+Sans:wght@600;700;800&amp;display=swap" rel="stylesheet"/>
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
                    "headline-lg-mobile": ["Plus Jakarta Sans"],
                    "headline-md": ["Plus Jakarta Sans"],
                    "body-lg": ["Inter"],
                    "body-md": ["Inter"]
            },
            "fontSize": {
                    "headline-lg": ["32px", {"lineHeight": "1.2", "letterSpacing": "-0.01em", "fontWeight": "700"}],
                    "label-sm": ["14px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "600"}],
                    "display-lg": ["48px", {"lineHeight": "1.1", "letterSpacing": "-0.02em", "fontWeight": "800"}],
                    "headline-lg-mobile": ["28px", {"lineHeight": "1.2", "fontWeight": "700"}],
                    "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                    "body-lg": ["18px", {"lineHeight": "1.6", "fontWeight": "400"}],
                    "body-md": ["16px", {"lineHeight": "1.5", "fontWeight": "400"}]
            }
          },
        },
      }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .bouncy-progress {
            transition: width 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md min-h-screen flex flex-col items-center">
    <!-- TopNavBar (Shared Component Reference) -->
    <nav class="fixed top-0 w-full z-50 bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-xl border-b border-outline-variant/20 dark:border-outline/10 shadow-sm dark:shadow-none">
        <div class="flex justify-between items-center px-gutter py-4 max-w-7xl mx-auto w-full">
            <span class="font-display-lg text-display-lg font-extrabold text-primary dark:text-primary-fixed tracking-tight">EventSnap</span>
            <div class="flex items-center gap-stack-md">
                <?php if ($event['is_public_gallery']): ?>
                    <a href="gallery.php?id=<?php echo $event['event_uuid']; ?>" class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm">Gallery</a>
                <?php endif; ?>
                <a href="login.php" class="bg-primary text-on-primary px-6 py-2 rounded-full font-label-sm text-label-sm hover:opacity-90 transition-all active:scale-95 text-center">Login</a>
            </div>
        </div>
    </nav>

    <?php if ($isExpired): ?>
        <!-- Welcome Concluded Canvas -->
        <main class="mt-24 w-full max-w-lg px-container-padding-mobile md:px-0 flex flex-col gap-stack-lg pb-stack-lg">
            <header class="text-center space-y-4 py-8 bg-white p-6 rounded-xl border border-outline-variant/30 shadow-[0px_10px_30px_rgba(0,0,0,0.04)]">
                <div class="w-16 h-16 rounded-full bg-error-container/20 flex items-center justify-center text-error mx-auto mb-4">
                    <span class="material-symbols-outlined !text-3xl">event_busy</span>
                </div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Event Concluded</h1>
                <p class="text-on-surface-variant font-body-md">This event has concluded and photo captures are closed. Thank you.</p>
                <?php if ($event['is_public_gallery']): ?>
                    <a href="gallery.php?id=<?php echo $event['event_uuid']; ?>" class="mt-4 inline-flex w-full items-center justify-center bg-primary text-on-primary py-3 rounded-lg font-headline-md hover:opacity-90 transition-all text-center">
                        <span class="material-symbols-outlined mr-2">image</span> View Event Gallery
                    </a>
                <?php endif; ?>
            </header>
        </main>
    <?php else: ?>
        <!-- Main Content Canvas -->
        <main class="mt-24 w-full max-w-lg px-container-padding-mobile md:px-0 flex flex-col gap-stack-lg pb-stack-lg">
            
            <!-- Success Screen (Hidden by default) -->
            <section class="hidden text-center space-y-6 py-12 px-6 bg-white rounded-xl border border-outline-variant/30 shadow-[0px_10px_30px_rgba(0,0,0,0.04)]" id="success-screen">
                <div class="w-20 h-20 rounded-full bg-green-50 text-green-600 flex items-center justify-center mx-auto mb-4 border border-green-150 animate-bounce">
                    <span class="material-symbols-outlined !text-4xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                </div>
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface">Memories Shared!</h2>
                    <p class="text-on-surface-variant font-body-md mt-2">Your photos have been uploaded to the event gallery successfully.</p>
                </div>
                
                <div class="space-y-3 pt-4">
                    <button class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary py-4 px-6 rounded-lg font-headline-md hover:bg-primary-container active:scale-95 transition-all shadow-lg shadow-primary/20" onclick="resetForMoreSnaps()">
                        <span class="material-symbols-outlined">photo_camera</span>
                        Snap More Pictures
                    </button>
                    
                    <?php if ($event['is_public_gallery']): ?>
                        <a href="gallery.php?id=<?php echo $event['event_uuid']; ?>" class="w-full flex items-center justify-center gap-2 bg-secondary-container/30 text-on-secondary-container py-4 px-6 rounded-lg font-headline-md border border-secondary-container/50 hover:bg-secondary-container/50 active:scale-98 transition-all no-underline block">
                            <span class="material-symbols-outlined">image</span>
                            View Event Gallery
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <div id="main-upload-interface" class="flex flex-col gap-stack-lg w-full">
                <!-- Welcome Banner -->
                <header class="text-center space-y-2 py-4">
                    <h1 class="font-headline-lg text-headline-lg text-on-surface">Welcome to <?php echo htmlspecialchars($event['name']); ?></h1>
                    <p class="text-on-surface-variant font-body-md">Capture the magic and share it with everyone!</p>
                </header>

                <!-- Primary Upload Action -->
                <section class="glass-card p-stack-lg rounded-xl shadow-[0px_10px_30px_rgba(0,0,0,0.04)] border border-outline-variant/30 flex flex-col items-center gap-stack-md group cursor-pointer active:scale-95 transition-transform duration-200" onclick="document.getElementById('camera-input').click()">
                    <div class="w-24 h-24 rounded-full bg-primary-container/20 flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined !text-5xl" style="font-variation-settings: 'FILL' 1;">photo_camera</span>
                    </div>
                    <h2 class="font-headline-md text-headline-md text-primary">Tap to Take Photo</h2>
                    <p class="text-on-surface-variant text-center font-body-md">Share a moment instantly from your camera</p>
                    <input accept="image/*" capture="environment" class="hidden" id="camera-input" type="file"/>
                </section>

                <!-- Secondary Action -->
                <button class="w-full flex items-center justify-center gap-stack-sm bg-secondary-container/30 text-on-secondary-container py-4 px-stack-lg rounded-lg border border-secondary-container/50 hover:bg-secondary-container/50 transition-all font-headline-md text-headline-md active:scale-98" onclick="document.getElementById('gallery-input').click()">
                    <span class="material-symbols-outlined">image</span>
                    Upload from Gallery
                    <input accept="image/*" class="hidden" id="gallery-input" multiple="" type="file"/>
                </button>

                <!-- Dynamic Upload Section (Hidden by default, shown via JS) -->
                <section class="hidden space-y-stack-md" id="upload-flow">
                    <!-- Preview & Caption Area -->
                    <div class="glass-card p-stack-md rounded-lg border border-outline-variant/30">
                        <div class="flex gap-stack-md overflow-x-auto pb-stack-sm" id="preview-list">
                            <!-- Image Preview Items injected here -->
                        </div>
                        
                        <!-- Add or Snap More Pictures Inline Utility -->
                        <div class="flex gap-2 mt-3">
                            <button type="button" onclick="document.getElementById('camera-input').click()" class="flex-grow flex items-center justify-center gap-1.5 py-2.5 px-3 bg-secondary-container/20 text-on-secondary-container border border-secondary-container/30 hover:bg-secondary-container/35 rounded-xl text-[12px] font-semibold active:scale-95 transition-all">
                                <span class="material-symbols-outlined text-[16px]">photo_camera</span> Snap More
                            </button>
                            <button type="button" onclick="document.getElementById('gallery-input').click()" class="flex-grow flex items-center justify-center gap-1.5 py-2.5 px-3 bg-secondary-container/20 text-on-secondary-container border border-secondary-container/30 hover:bg-secondary-container/35 rounded-xl text-[12px] font-semibold active:scale-95 transition-all">
                                <span class="material-symbols-outlined text-[16px]">image</span> Add More
                            </button>
                        </div>
                        
                        <div class="mt-stack-md">
                            <label class="block font-label-sm text-label-sm text-on-surface-variant mb-2" for="caption">ADD A CAPTION</label>
                            <textarea class="w-full bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary placeholder:text-outline p-stack-sm text-body-md" id="caption" placeholder="Write something sweet..." rows="2"></textarea>
                        </div>
                    </div>
                    <!-- Progress List -->
                    <div class="space-y-stack-sm hidden" id="upload-progress-container">
                        <div class="flex justify-between items-center px-1">
                            <span class="font-label-sm text-label-sm text-on-surface" id="upload-progress-text">Uploading 1 photo...</span>
                            <span class="font-label-sm text-label-sm text-primary" id="upload-progress-percent">0%</span>
                        </div>
                        <div class="w-full h-3 bg-surface-container-high rounded-full overflow-hidden">
                            <div class="bouncy-progress h-full bg-gradient-to-r from-primary to-primary-container" id="upload-progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                    <button class="w-full bg-primary text-on-primary py-4 rounded-lg font-headline-md text-headline-md shadow-lg shadow-primary/20 hover:translate-y-[-2px] transition-all active:scale-95" id="send-to-gallery-btn">
                        Send to Gallery
                    </button>
                </section>

                <!-- Quick Gallery Preview -->
                <section class="mt-stack-lg">
                    <h3 class="font-label-sm text-label-sm text-on-surface-variant mb-stack-sm tracking-widest uppercase">RECENTLY ADDED</h3>
                    <div class="grid grid-cols-3 gap-2">
                        <?php if (empty($thumbs)): ?>
                            <div class="col-span-3 text-center py-4 text-on-surface-variant font-body-md">
                                No photos added yet. Be the first to share a memory!
                            </div>
                        <?php else: ?>
                            <?php 
                            $idx = 0;
                            foreach ($thumbs as $th): 
                                $idx++;
                            ?>
                                <?php if ($idx === 3 && $remCount > 0): ?>
                                    <div class="aspect-square rounded-lg overflow-hidden bg-surface-container relative">
                                        <img class="w-full h-full object-cover opacity-60" src="<?php echo BASE_URL . htmlspecialchars($th['file_path']); ?>" alt="Recently uploaded photo"/>
                                        <div class="absolute inset-0 flex items-center justify-center font-label-sm text-label-sm text-on-surface font-bold">+<?php echo $remCount; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="aspect-square rounded-lg overflow-hidden bg-surface-container">
                                        <img class="w-full h-full object-cover" src="<?php echo BASE_URL . htmlspecialchars($th['file_path']); ?>" alt="Recently uploaded photo"/>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    <?php endif; ?>

    <!-- Footer (Shared Component Reference) -->
    <footer class="w-full mt-auto bg-surface-container-lowest dark:bg-surface-dim border-t border-outline-variant/20">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-stack-lg px-gutter py-stack-lg max-w-7xl mx-auto items-center">
            <div class="flex flex-col gap-2">
                <span class="font-headline-md text-headline-md font-bold text-primary">EventSnap</span>
                <p class="font-body-md text-body-md text-on-surface-variant">© <?php echo date('Y'); ?> EventSnap Cloud. All rights reserved.</p>
            </div>
            <div class="flex gap-stack-md justify-center">
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm" href="#">Product</a>
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm" href="#">Weddings</a>
                <a class="text-on-surface-variant hover:text-primary transition-colors font-label-sm text-label-sm" href="#">Corporate</a>
            </div>
            <div class="flex justify-center md:justify-end">
                <a href="<?php echo BASE_URL; ?>qr-code.php?id=<?php echo $event['event_uuid']; ?>" class="flex items-center gap-2 text-primary font-semibold underline decoration-2 underline-offset-4 hover:opacity-80 transition-opacity">
                    <span class="material-symbols-outlined">qr_code_2</span>
                    Get the QR
                </a>
            </div>
        </div>
    </footer>

    <!-- JS Logic -->
    <script>
        const cameraInput = document.getElementById('camera-input');
        const galleryInput = document.getElementById('gallery-input');
        const uploadFlow = document.getElementById('upload-flow');
        const previewList = document.getElementById('preview-list');
        const captionTextarea = document.getElementById('caption');
        const sendToGalleryBtn = document.getElementById('send-to-gallery-btn');
        const uploadProgressContainer = document.getElementById('upload-progress-container');
        const uploadProgressText = document.getElementById('upload-progress-text');
        const uploadProgressPercent = document.getElementById('upload-progress-percent');
        const uploadProgressBar = document.getElementById('upload-progress-bar');

        let selectedFiles = [];

        function showUploadFlow() {
            uploadFlow.classList.remove('hidden');
            uploadProgressContainer.classList.add('hidden');
            sendToGalleryBtn.disabled = false;
            sendToGalleryBtn.innerHTML = 'Send to Gallery';
            uploadFlow.scrollIntoView({ behavior: 'smooth' });
            
            uploadFlow.classList.add('animate-pulse');
            setTimeout(() => uploadFlow.classList.remove('animate-pulse'), 1000);
        }

        function updatePreviews() {
            previewList.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const url = URL.createObjectURL(file);
                const item = document.createElement('div');
                item.className = 'relative flex-shrink-0 w-24 h-24 rounded-lg overflow-hidden border border-outline-variant/20';
                item.innerHTML = `
                    <img class="w-full h-full object-cover" src="${url}"/>
                    <button type="button" class="absolute top-1 right-1 bg-on-surface/80 text-surface rounded-full p-0.5 hover:bg-error transition-colors text-white flex items-center justify-center w-5 h-5" title="Remove" onclick="removeFile(${index})">
                        <span class="material-symbols-outlined !text-sm">close</span>
                    </button>
                `;
                previewList.appendChild(item);
            });
        }

        window.removeFile = function(index) {
            selectedFiles.splice(index, 1);
            updatePreviews();
            if (selectedFiles.length === 0) {
                uploadFlow.classList.add('hidden');
            }
        };

        cameraInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                selectedFiles = selectedFiles.concat(files);
                updatePreviews();
                showUploadFlow();
                cameraInput.value = ''; // Reset input value to allow capture/selection loop
            }
        });

        galleryInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                selectedFiles = selectedFiles.concat(files);
                updatePreviews();
                showUploadFlow();
                galleryInput.value = ''; // Reset input value to allow selection loop
            }
        });

        sendToGalleryBtn.addEventListener('click', () => {
            if (selectedFiles.length === 0) return;

            uploadProgressContainer.classList.remove('hidden');
            sendToGalleryBtn.disabled = true;
            sendToGalleryBtn.innerHTML = 'Uploading...';

            let filesUploaded = 0;
            const totalFiles = selectedFiles.length;
            const caption = captionTextarea.value.trim();

            function uploadNext() {
                if (filesUploaded >= totalFiles) {
                    uploadProgressText.innerText = 'Completed!';
                    uploadProgressPercent.innerText = '100%';
                    uploadProgressBar.style.width = '100%';
                    setTimeout(() => {
                        // Hide main upload interface
                        document.getElementById('main-upload-interface').classList.add('hidden');
                        
                        // Show success screen
                        const successScreen = document.getElementById('success-screen');
                        successScreen.classList.remove('hidden');
                        successScreen.scrollIntoView({ behavior: 'smooth' });
                    }, 500);
                    return;
                }

                const file = selectedFiles[filesUploaded];
                const formData = new FormData();
                formData.append('file', file);
                formData.append('event_uuid', '<?php echo $eventUuid; ?>');
                formData.append('uploader_name', 'Guest');
                formData.append('caption', caption);
                formData.append('uploader_role', 'guest');
                formData.append('action', 'upload');

                uploadProgressText.innerText = `Uploading photo ${filesUploaded + 1} of ${totalFiles}...`;

                const xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo BASE_URL; ?>ajax-handler.php', true);

                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        const percentComplete = Math.round((event.loaded / event.total) * 100);
                        const overallPercent = Math.round(((filesUploaded / totalFiles) * 100) + (percentComplete / totalFiles));
                        uploadProgressPercent.innerText = `${overallPercent}%`;
                        uploadProgressBar.style.width = `${overallPercent}%`;
                    }
                });

                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                filesUploaded++;
                                uploadNext();
                            } else {
                                alert(`Upload failed: ${res.message}`);
                                resetUploadBtn();
                            }
                        } catch (err) {
                            alert('Upload response error.');
                            resetUploadBtn();
                        }
                    } else {
                        alert('Upload server connection error.');
                        resetUploadBtn();
                    }
                };

                xhr.onerror = function() {
                    alert('Upload connection failed.');
                    resetUploadBtn();
                };

                xhr.send(formData);
            }

            function resetUploadBtn() {
                sendToGalleryBtn.disabled = false;
                sendToGalleryBtn.innerHTML = 'Send to Gallery';
                uploadProgressContainer.classList.add('hidden');
            }

            uploadNext();
        });

        window.resetForMoreSnaps = function() {
            // Hide success screen
            document.getElementById('success-screen').classList.add('hidden');
            
            // Show main interface
            document.getElementById('main-upload-interface').classList.remove('hidden');
            
            // Reset state
            selectedFiles = [];
            captionTextarea.value = '';
            uploadFlow.classList.add('hidden');
            
            // Proactively open camera capture trigger
            cameraInput.click();
        };
    </script>
</body>
</html>
