<?php
/**
 * EventSnap Cloud - Event Analytics Cockpit
 * Overhauled to match the premium light-theme design system.
 * FIXED: All auth/redirect logic runs BEFORE header.php outputs any HTML.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

// Auth checks: Host owner only
Auth::requireRole('owner');

$db = getDBConnection();
$ownerId = $_SESSION['user_id'];
$eventId = (int)($_GET['id'] ?? 0);

// Load Event
$stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND owner_id = ?");
$stmt->execute([$eventId, $ownerId]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: dashboard.php");
    exit;
}

// Load statistics
$metrics = EventManager::getEventAnalytics($eventId);

// Load chronological uploads log
$logStmt = $db->prepare("
    SELECT * FROM uploads 
    WHERE event_id = ? 
    ORDER BY created_at DESC
");
$logStmt->execute([$eventId]);
$uploadsLog = $logStmt->fetchAll();

// Expire countdown
$expiresTimestamp = strtotime($event['expires_at']);
$daysRemaining = ceil(($expiresTimestamp - time()) / (60 * 60 * 24));

// Safe to output HTML now
$pageTitle = "Event Media Analytics";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
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
                    <span class="ml-1 text-on-surface-variant/70 font-semibold md:ml-2">Event Analytics</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="flex justify-between items-center mb-8 flex-wrap gap-4">
        <div>
            <h2 class="text-headline-lg font-bold text-on-surface">Analytics: <?php echo htmlspecialchars($event['name']); ?></h2>
            <p class="text-on-surface-variant text-sm mt-1 flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px] text-primary">location_on</span> <?php echo htmlspecialchars($event['venue']); ?> &bull; Scheduled: <?php echo date('M d, Y', strtotime($event['date'])); ?></p>
        </div>
        
        <!-- Countdown Indicator -->
        <div class="bg-white rounded-xl border border-outline-variant/30 p-4 flex items-center gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">hourglass_empty</span>
            </div>
            <div>
                <span class="text-on-surface-variant/80 text-[11px] font-bold block uppercase tracking-wider">QR Countdown</span>
                <span class="font-bold text-on-surface text-sm">
                    <?php echo $daysRemaining > 0 ? $daysRemaining . " Days Active" : "Expired"; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- 1. Total Photos -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm hover:shadow-premium hover:border-primary/45 transition-all">
            <span class="text-on-surface-variant/85 text-[11px] font-bold block uppercase tracking-wider mb-2">Total Photos</span>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-on-surface"><?php echo number_format($metrics['total_uploads']); ?></span>
            </div>
            <span class="text-on-surface-variant/65 text-xs block mt-2">Captured by guests</span>
        </div>
        
        <!-- 2. Unique Guests -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm hover:shadow-premium hover:border-primary/45 transition-all">
            <span class="text-on-surface-variant/85 text-[11px] font-bold block uppercase tracking-wider mb-2">Unique Guests</span>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-on-surface"><?php echo number_format($metrics['total_visitors']); ?></span>
            </div>
            <span class="text-green-600 text-xs font-semibold block mt-2 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">qr_code_scanner</span> Scanned QR</span>
        </div>
        
        <!-- 3. DSLR Media Crew -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm hover:shadow-premium hover:border-primary/45 transition-all">
            <span class="text-on-surface-variant/85 text-[11px] font-bold block uppercase tracking-wider mb-2">Media Crew DSLR</span>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-on-surface"><?php echo number_format($metrics['crew_uploads']); ?></span>
            </div>
            <span class="text-on-surface-variant/65 text-xs block mt-2">High-Res Uploads</span>
        </div>
        
        <!-- 4. Storage Space -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm hover:shadow-premium hover:border-primary/45 transition-all">
            <span class="text-on-surface-variant/85 text-[11px] font-bold block uppercase tracking-wider mb-2">Storage Used</span>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-on-surface"><?php echo number_format($metrics['storage_bytes'] / (1024 * 1024), 2); ?> MB</span>
            </div>
            <span class="text-on-surface-variant/65 text-xs block mt-2">Server Space</span>
        </div>
    </div>

    <!-- Share Proportions & Features -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Card 1: Media Contribution Share -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm">
            <h3 class="text-base font-bold text-on-surface mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-primary">pie_chart</span> Media Contribution Share</h3>
            <p class="text-on-surface-variant/80 text-xs mb-6">Sharing percentage distribution between Guest Smartphone cameras and Professional DSLR photographer teams.</p>
            
            <?php 
            $guestShare = $metrics['total_uploads'] > 0 ? ($metrics['guest_uploads'] / $metrics['total_uploads']) * 100 : 0;
            $crewShare = $metrics['total_uploads'] > 0 ? ($metrics['crew_uploads'] / $metrics['total_uploads']) * 100 : 0;
            ?>
            
            <div class="mb-4">
                <div class="flex justify-between mb-2 text-xs font-semibold">
                    <span class="text-on-surface flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-primary inline-block"></span> Guest Captures</span>
                    <span class="text-on-surface-variant"><?php echo number_format($guestShare, 1); ?>% (<?php echo $metrics['guest_uploads']; ?> files)</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div class="bg-primary h-full rounded-full" style="width: <?php echo $guestShare; ?>%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex justify-between mb-2 text-xs font-semibold">
                    <span class="text-on-surface flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span> Media Crew DSLR</span>
                    <span class="text-on-surface-variant"><?php echo number_format($crewShare, 1); ?>% (<?php echo $metrics['crew_uploads']; ?> files)</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div class="bg-green-500 h-full rounded-full" style="width: <?php echo $crewShare; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Card 2: Feature Check -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm">
            <h3 class="text-base font-bold text-on-surface mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-primary">toggle_on</span> Feature Check</h3>
            <p class="text-on-surface-variant/80 text-xs mb-4">Current active feature configurations for this digital QR code target.</p>
            
            <ul class="divide-y divide-slate-100 text-xs">
                <li class="py-3 flex justify-between items-center">
                    <span class="font-semibold text-on-surface">Translucent Watermarking</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $event['watermark_enabled'] ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-slate-50 text-on-surface-variant/70 border border-slate-200'; ?>">
                        <?php echo $event['watermark_enabled'] ? 'Active (' . htmlspecialchars($event['watermark_text']) . ')' : 'Disabled'; ?>
                    </span>
                </li>
                <li class="py-3 flex justify-between items-center">
                    <span class="font-semibold text-on-surface">Public Guest Gallery</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $event['is_public_gallery'] ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-slate-50 text-on-surface-variant/70 border border-slate-200'; ?>">
                        <?php echo $event['is_public_gallery'] ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </li>
                <li class="py-3 flex justify-between items-center">
                    <span class="font-semibold text-on-surface">Printable QR Table Card</span>
                    <a href="qr-code.php?id=<?php echo $event['event_uuid']; ?>" class="px-3 py-1.5 border border-primary text-primary hover:bg-primary/5 rounded-lg font-bold transition-all text-decoration-none text-[11px]">Print Sign Board</a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Moderation Audit Table -->
    <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm">
        <div class="flex justify-between items-center mb-6 flex-wrap gap-2">
            <h3 class="text-base font-bold text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-primary">security</span> Media Moderation & History Log</h3>
            <span class="px-2.5 py-1 rounded-full bg-slate-100 text-on-surface text-xs font-bold">Total: <?php echo count($uploadsLog); ?> captures</span>
        </div>

        <?php if (empty($uploadsLog)): ?>
            <div class="text-center py-12">
                <span class="material-symbols-outlined text-[48px] text-on-surface-variant/30">image_not_supported</span>
                <h5 class="text-on-surface font-bold mt-3 text-sm">No captures uploaded yet</h5>
                <p class="text-on-surface-variant/80 text-xs mt-1">Guest snapshots will be logged here for moderation review.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-on-surface-variant font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-3.5 px-4">Thumbnail</th>
                            <th class="py-3.5 px-4">Contributor</th>
                            <th class="py-3.5 px-4">Role</th>
                            <th class="py-3.5 px-4">Caption</th>
                            <th class="py-3.5 px-4">Size</th>
                            <th class="py-3.5 px-4">Date</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 font-medium">
                        <?php foreach ($uploadsLog as $up): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3 px-4">
                                    <a href="<?php echo BASE_URL . htmlspecialchars($up['file_path']); ?>" target="_blank" class="block w-12 h-12 rounded overflow-hidden border border-slate-200 shadow-sm">
                                        <img src="<?php echo BASE_URL . htmlspecialchars($up['file_path']); ?>" alt="Thumb" class="w-full h-full object-cover">
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-on-surface font-bold"><?php echo htmlspecialchars($up['uploader_name']); ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo $up['uploader_role'] === 'guest' ? 'bg-primary/10 text-primary' : 'bg-green-100 text-green-700'; ?>">
                                        <?php echo htmlspecialchars($up['uploader_role']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-on-surface-variant max-w-[200px] truncate" title="<?php echo htmlspecialchars($up['caption']); ?>">
                                    <?php echo htmlspecialchars($up['caption']) ?: '<em class="opacity-50 text-[11px]">No caption</em>'; ?>
                                </td>
                                <td class="py-3 px-4 text-on-surface"><?php echo number_format($up['file_size'] / 1024, 1); ?> KB</td>
                                <td class="py-3 px-4 text-on-surface-variant/80"><?php echo date('M d, g:i A', strtotime($up['created_at'])); ?></td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?php echo BASE_URL . htmlspecialchars($up['file_path']); ?>" download class="p-1.5 rounded-full hover:bg-slate-100 border border-slate-200 text-on-surface-variant transition-colors flex items-center justify-center text-decoration-none" title="Save file">
                                            <span class="material-symbols-outlined text-[16px]">download</span>
                                        </a>
                                        <a href="gallery.php?id=<?php echo $event['event_uuid']; ?>&delete_upload_id=<?php echo $up['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>" 
                                           class="p-1.5 rounded-full hover:bg-red-50 border border-slate-200 hover:border-red-200 text-red-500 transition-colors flex items-center justify-center text-decoration-none" 
                                           title="Purge" 
                                           onclick="return confirm('WARNING: Permanent file purge. Continue?');">
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
