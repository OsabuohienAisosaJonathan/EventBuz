/**
 * EventSnap Cloud — Slideshow presentation polling controller
 * Vanilla JS only — no jQuery required.
 */

let slideshowQueue       = [];
let currentSlideIndex    = -1;
let lastLoadedPhotoId    = 0;
let slideshowTimer       = null;
let changeIntervalMs     = 6000;   // default 6 s
let eventUuidContext     = '';
let assetBaseUrl         = '';
let isPollingActive      = false;

/* ─────────────────────────────────────────────────────────
   Public API
───────────────────────────────────────────────────────── */

/**
 * Initialises the slideshow engine.
 * @param {string} uuid    - Event UUID
 * @param {string} baseUrl - Application base URL
 */
function initSlideshow(uuid, baseUrl) {
    eventUuidContext = uuid;
    assetBaseUrl     = baseUrl;

    // First fetch: boot the cycle immediately
    fetchSlideshowPhotos(true);

    // Poll every 10 s for new guest photos
    setInterval(() => {
        if (!isPollingActive) fetchSlideshowPhotos(false);
    }, 10000);
}

/**
 * Adjusts the slide transition interval dynamically.
 * @param {number} ms
 */
function setSlideshowInterval(ms) {
    changeIntervalMs = ms;
    if (slideshowTimer) {
        clearInterval(slideshowTimer);
        startSlideshowCycle();
    }
}

/* ─────────────────────────────────────────────────────────
   Internal helpers
───────────────────────────────────────────────────────── */

/**
 * Fetches new photos from the AJAX feed endpoint.
 * @param {boolean} isFirstLoad
 */
function fetchSlideshowPhotos(isFirstLoad = false) {
    isPollingActive = true;

    const params = new URLSearchParams({
        action:        'get_slideshow_feed',
        event_uuid:    eventUuidContext,
        last_photo_id: lastLoadedPhotoId
    });

    fetch(`${assetBaseUrl}ajax-handler.php?${params}`)
        .then(res => res.json())
        .then(response => {
            isPollingActive = false;

            if (response.success && response.photos.length > 0) {
                response.photos.forEach(photo => {
                    // Pre-load image to avoid blank frames
                    const img = new Image();
                    img.src = assetBaseUrl + photo.file_path;

                    // Add to queue only if not already present
                    if (!slideshowQueue.some(item => item.id === photo.id)) {
                        slideshowQueue.push(photo);
                    }

                    if (photo.id > lastLoadedPhotoId) {
                        lastLoadedPhotoId = photo.id;
                    }
                });

                if (isFirstLoad) {
                    dismissPlaceholder(() => {
                        cycleToNextSlide();
                        startSlideshowCycle();
                    });
                }
            } else if (isFirstLoad) {
                // Empty gallery fallback banner
                slideshowQueue.push({
                    id:            0,
                    file_path:     'assets/default-banner.jpg',
                    uploader_name: 'EventSnap Cloud',
                    caption:       'Welcome! Scan the QR code and snap beautiful photos to share them here live!'
                });

                dismissPlaceholder(() => {
                    cycleToNextSlide();
                    startSlideshowCycle();
                });
            }
        })
        .catch(() => {
            isPollingActive = false;
            if (isFirstLoad) {
                const ph = document.getElementById('initialSlideshowPlaceholder');
                if (ph) {
                    ph.innerHTML = `
                        <div style="color:rgba(255,255,255,.5);text-align:center">
                            <span class="material-symbols-outlined" style="font-size:48px;color:#ef4444">wifi_off</span>
                            <p style="margin-top:12px;font-size:.85rem">Could not connect to EventSnap servers.</p>
                        </div>`;
                }
            }
        });
}

/**
 * Fades out and removes the initial loading placeholder.
 * @param {Function} callback - Runs after placeholder is gone
 */
function dismissPlaceholder(callback) {
    const placeholder = document.getElementById('initialSlideshowPlaceholder');
    if (!placeholder) { callback(); return; }

    placeholder.style.transition = 'opacity 0.5s ease';
    placeholder.style.opacity    = '0';

    setTimeout(() => {
        placeholder.remove();
        callback();
    }, 500);
}

/**
 * Starts the automatic slide-cycling timer.
 */
function startSlideshowCycle() {
    slideshowTimer = setInterval(cycleToNextSlide, changeIntervalMs);
}

/**
 * Advances to the next photo and renders it with a crossfade.
 */
function cycleToNextSlide() {
    if (slideshowQueue.length === 0) return;

    currentSlideIndex++;
    if (currentSlideIndex >= slideshowQueue.length) currentSlideIndex = 0;

    const photo    = slideshowQueue[currentSlideIndex];
    const fullPath = assetBaseUrl + photo.file_path;
    const container = document.getElementById('slideshowContainer');

    // Build new slide element
    const newSlide     = document.createElement('img');
    newSlide.src       = fullPath;
    newSlide.className = 'slide-image';
    newSlide.alt       = `Photo by ${photo.uploader_name}`;
    container.appendChild(newSlide);

    // Trigger CSS transition on next frame
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            // Deactivate previous slides
            container.querySelectorAll('.slide-image').forEach(el => {
                if (el !== newSlide) el.classList.remove('active');
            });
            newSlide.classList.add('active');

            // Update caption
            const captionBar      = document.getElementById('captionBar');
            const captionUploader = document.getElementById('captionUploader');
            const captionText     = document.getElementById('captionText');

            captionUploader.textContent = photo.uploader_name || 'Guest';
            captionText.textContent     = photo.caption        || 'Capturing beautiful snapshots';
            captionBar.classList.add('visible');

            // Remove old slide DOM nodes after fade completes
            setTimeout(() => {
                container.querySelectorAll('.slide-image').forEach(el => {
                    if (el !== newSlide) el.remove();
                });
            }, 1500);
        });
    });
}
