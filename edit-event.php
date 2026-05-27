<?php
/**
 * EventSnap Cloud - Modify Existing Event Settings
 * FIXED: All auth/redirect logic runs BEFORE header.php outputs any HTML.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

// Auth checks: Host + Admin - redirect happens BEFORE any HTML
Auth::requireRole(['owner', 'admin']);

$db      = getDBConnection();
$ownerId = $_SESSION['user_id'];
$eventId = (int)($_GET['id'] ?? 0);

// Load event - redirect if not found/unauthorized
$stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND owner_id = ?");
$stmt->execute([$eventId, $ownerId]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: dashboard.php");
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = $_POST['name']             ?? '';
    $type             = $_POST['type']             ?? '';
    $date             = $_POST['date']             ?? '';
    $venue            = $_POST['venue']            ?? '';
    $description      = $_POST['description']      ?? '';
    $isPublicGallery  = isset($_POST['is_public_gallery']) ? 1 : 0;
    $watermarkEnabled = isset($_POST['watermark_enabled']) ? 1 : 0;
    $watermarkText    = $_POST['watermark_text']   ?? 'EventSnap';
    $csrfToken        = $_POST['csrf_token']       ?? '';
    $bannerFile       = $_FILES['banner']          ?? null;

    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please try again.";
    } else {
        $result = EventManager::editEvent(
            $eventId, $ownerId,
            $name, $type, $date, $venue, $description,
            $bannerFile, $isPublicGallery, $watermarkEnabled, $watermarkText
        );

        if ($result['success']) {
            // Safe — no HTML sent yet
            header("Location: dashboard.php");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Safe to output HTML now
$pageTitle = "Modify Event Settings";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Breadcrumbs -->
    <nav class="flex mb-6 text-sm" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="dashboard.php" class="inline-flex items-center text-on-surface-variant hover:text-primary transition-colors text-decoration-none font-medium">
                    <span class="material-symbols-outlined text-[18px] mr-1">dashboard</span>
                    Dashboard
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant/40">chevron_right</span>
                    <span class="ml-1 text-on-surface-variant/70 font-semibold md:ml-2">Modify Event</span>
                </div>
            </li>
        </ol>
    </nav>
    
    <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-6 md:p-8">
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">edit_note</span>
            </div>
            <div>
                <h2 class="text-headline-md font-bold text-on-surface">Modify Event Configuration</h2>
                <p class="text-on-surface-variant text-sm mt-0.5">Update parameters, banners, or watermark toggles.</p>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-xl text-sm border-l-4 bg-red-50 border-red-500 text-red-700 flex items-center gap-2" role="alert">
                <span class="material-symbols-outlined text-[20px]">warning</span>
                <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <form action="edit-event.php?id=<?php echo $eventId; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- 1. Event Name -->
                <div class="col-span-1 md:col-span-2">
                    <label for="name" class="block text-sm font-semibold text-on-surface-variant mb-2">Event Name</label>
                    <input type="text" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all" id="name" name="name" required placeholder="e.g. Sarah & Michael Wedding" value="<?php echo htmlspecialchars($event['name']); ?>">
                </div>
                
                <!-- 2. Event Type -->
                <div class="col-span-1">
                    <label for="type" class="block text-sm font-semibold text-on-surface-variant mb-2">Event Category</label>
                    <select class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all" id="type" name="type" required>
                        <option value="Wedding" <?php echo ($event['type'] == 'Wedding') ? 'selected' : ''; ?>>Wedding</option>
                        <option value="Birthday" <?php echo ($event['type'] == 'Birthday') ? 'selected' : ''; ?>>Birthday Party</option>
                        <option value="Corporate" <?php echo ($event['type'] == 'Corporate') ? 'selected' : ''; ?>>Corporate Event</option>
                        <option value="Concert" <?php echo ($event['type'] == 'Concert') ? 'selected' : ''; ?>>Live Concert</option>
                        <option value="Festival" <?php echo ($event['type'] == 'Festival') ? 'selected' : ''; ?>>Festival</option>
                        <option value="Others" <?php echo ($event['type'] == 'Others') ? 'selected' : ''; ?>>Other Occasion</option>
                    </select>
                </div>
                
                <!-- 3. Event Date -->
                <div class="col-span-1">
                    <label for="date" class="block text-sm font-semibold text-on-surface-variant mb-2">Scheduled Date</label>
                    <input type="date" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all" id="date" name="date" required value="<?php echo htmlspecialchars($event['date']); ?>">
                </div>
                
                <!-- 4. Venue -->
                <div class="col-span-1 md:col-span-2">
                    <label for="venue" class="block text-sm font-semibold text-on-surface-variant mb-2">Venue Location</label>
                    <input type="text" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all" id="venue" name="venue" required placeholder="Grand Hall, NY" value="<?php echo htmlspecialchars($event['venue']); ?>">
                </div>
                
                <!-- 5. Description -->
                <div class="col-span-1 md:col-span-3">
                    <label for="description" class="block text-sm font-semibold text-on-surface-variant mb-2">Event Description (Optional)</label>
                    <textarea class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all" id="description" name="description" rows="3" placeholder="Provide guest details..."><?php echo htmlspecialchars($event['description']); ?></textarea>
                </div>
                
                <!-- Current Cover Cover -->
                <div class="col-span-1 md:col-span-3">
                    <label class="block text-sm font-semibold text-on-surface-variant mb-2">Current Event Banner</label>
                    <img src="<?php echo BASE_URL . htmlspecialchars($event['banner_path']); ?>" alt="Cover Banner" class="rounded-lg border border-outline-variant/30 mb-4 max-h-[180px] w-full object-cover">
                    
                    <label for="banner" class="block text-sm font-semibold text-on-surface-variant mb-2">Upload New Cover (Optional)</label>
                    <input type="file" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-2.5 text-on-surface-variant transition-all file:mr-4 file:py-1.5 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer" id="banner" name="banner" accept="image/*">
                </div>
                
                <hr class="border-outline-variant/20 my-2 col-span-1 md:col-span-3">
                
                <!-- Switches -->
                <h4 class="text-sm font-bold text-on-surface uppercase tracking-wider col-span-1 md:col-span-3">Feature Parameters</h4>
                
                <!-- Public switch -->
                <div class="col-span-1 md:col-span-3 md:col-start-1 p-4 rounded-xl border border-outline-variant/35 bg-surface-container-low/40">
                    <div class="flex items-center justify-between">
                        <div class="pr-4">
                            <label class="text-sm font-bold text-on-surface block" for="is_public_gallery">Enable Public Guest Gallery</label>
                            <span class="text-xs text-on-surface-variant/80 block mt-1">If enabled, guests can scroll and view all uploaded media. If disabled, they can only snap & upload.</span>
                        </div>
                        <div class="relative inline-flex items-center cursor-pointer">
                            <input class="sr-only peer" type="checkbox" id="is_public_gallery" name="is_public_gallery" value="1" <?php echo $event['is_public_gallery'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Watermarking switch -->
                <div class="col-span-1 md:col-span-3 p-4 rounded-xl border border-outline-variant/35 bg-surface-container-low/40">
                    <div class="flex items-center justify-between">
                        <div class="pr-4">
                            <label class="text-sm font-bold text-on-surface block" for="watermark_enabled">Inject Image Watermark</label>
                            <span class="text-xs text-on-surface-variant/80 block mt-1">Embed custom signature watermark text on the bottom-right corner of all photos.</span>
                        </div>
                        <div class="relative inline-flex items-center cursor-pointer">
                            <input class="sr-only peer" type="checkbox" id="watermark_enabled" name="watermark_enabled" value="1" <?php echo $event['watermark_enabled'] ? 'checked' : ''; ?> onchange="toggleWatermarkInput()">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Watermark signature input -->
                <div class="col-span-1 md:col-span-3 hidden" id="watermark_text_container">
                    <label for="watermark_text" class="block text-sm font-semibold text-on-surface-variant mb-2">Custom Watermark Signature</label>
                    <input type="text" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all" id="watermark_text" name="watermark_text" value="<?php echo htmlspecialchars($event['watermark_text'] ?: 'EventSnap'); ?>" placeholder="e.g. Sarah & Michael Wedding">
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row justify-end gap-3 mt-8">
                <a href="dashboard.php" class="w-full sm:w-auto text-center px-6 py-3 border border-outline hover:bg-slate-50 text-on-surface-variant font-bold rounded-xl transition-all no-underline text-sm">Cancel</a>
                <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-primary text-on-primary hover:bg-primary-container font-bold rounded-xl shadow-lg shadow-primary/20 transition-all text-sm">Save Modifications</button>
            </div>
        </form>
    </div>
</div>

<?php 
$pageScripts = "
<script>
function toggleWatermarkInput() {
    const isChecked = $('#watermark_enabled').is(':checked');
    if (isChecked) {
        $('#watermark_text_container').removeClass('hidden');
    } else {
        $('#watermark_text_container').addClass('hidden');
    }
}
toggleWatermarkInput();
</script>
";
require_once __DIR__ . '/includes/footer.php'; 
?>
