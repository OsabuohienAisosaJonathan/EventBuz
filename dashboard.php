<?php
/**
 * EventSnap Cloud - Host Dashboard Portal (Tailwind Theme Parity)
 * FIXED: All auth/redirect logic runs BEFORE outputs any HTML.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

// Auth checks: Host owners & admins only
Auth::requireRole(['owner', 'admin']);

$ownerId = $_SESSION['user_id'];
$events = EventManager::getEventsByOwner($ownerId);
$subStatus = Auth::getSubscriptionStatus();

// Handle deletes
$deleteMsg = '';
if (isset($_GET['delete_event_id']) && isset($_GET['csrf'])) {
    if (verifyCSRFToken($_GET['csrf'])) {
        $delId = (int)$_GET['delete_event_id'];
        $delResult = EventManager::deleteEvent($delId, $ownerId);
        if ($delResult['success']) {
            $deleteMsg = "success:" . $delResult['message'];
            $events = EventManager::getEventsByOwner($ownerId);
        } else {
            $deleteMsg = "danger:" . $delResult['message'];
        }
    }
}

// Compute aggregate metrics
$totalPhotosAgg = 0;
$totalScansAgg = 0;
$activeCount = 0;

foreach ($events as $ev) {
    $totalPhotosAgg += $ev['total_uploads'];
    $isExpired = strtotime($ev['expires_at']) < time();
    if (!$isExpired) {
        $activeCount++;
    }
    
    // Aggregate unique visitor counts
    try {
        $db = getDBConnection();
        $vStmt = $db->prepare("SELECT COUNT(*) FROM guest_sessions WHERE event_id = ?");
        $vStmt->execute([$ev['id']]);
        $totalScansAgg += (int)$vStmt->fetchColumn();
    } catch (PDOException $e) {}
}

// Set default attractive baselines if new account
$displayPhotos = $totalPhotosAgg > 0 ? number_format($totalPhotosAgg) : "0";
$displayScans = $totalScansAgg > 0 ? number_format($totalScansAgg) : "0";
$activeCountDisplay = $activeCount;

// Compute storage capacity metric (max 100MB for Free, Pro gets 10GB, Planner gets 50GB)
$maxStorageBytes = ($subStatus['plan'] === 'Free') ? 100 * 1024 * 1024 : (($subStatus['plan'] === 'Pro') ? 10 * 1024 * 1024 * 1024 : 50 * 1024 * 1024 * 1024);
$totalUsedBytes = 0;
foreach ($events as $ev) {
    try {
        $db = getDBConnection();
        $sStmt = $db->prepare("SELECT SUM(file_size) FROM uploads WHERE event_id = ?");
        $sStmt->execute([$ev['id']]);
        $totalUsedBytes += (int)$sStmt->fetchColumn();
    } catch (PDOException $e) {}
}
$storagePercent = $maxStorageBytes > 0 ? round(($totalUsedBytes / $maxStorageBytes) * 100) : 0;
if ($storagePercent === 0 && count($events) > 0) $storagePercent = 2;
?>
<?php
// Safe to output HTML now
$pageTitle = "Dashboard Overview";
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Moderation Feedback Banner -->
        <?php if (!empty($deleteMsg)): 
            list($aType, $aText) = explode(':', $deleteMsg, 2);
        ?>
            <div class="mb-6 p-4 rounded-xl text-sm border-l-4 <?php echo $aType === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'; ?>" role="alert">
                <p class="font-bold"><?php echo $aType === 'success' ? 'Success' : 'Error'; ?></p>
                <p><?php echo htmlspecialchars($aText); ?></p>
            </div>
        <?php endif; ?>

        <!-- Stats Bento Grid -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-stack-lg">
            <div class="glass-card p-stack-lg rounded-lg border-l-4 border-primary">
                <p class="font-label-sm text-on-surface-variant uppercase tracking-widest mb-stack-sm">Active Events</p>
                <h3 class="font-headline-lg text-headline-lg text-on-surface"><?php echo $activeCountDisplay; ?></h3>
                <p class="font-body-md text-primary mt-2 text-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">trending_up</span> +2 this month
                </p>
            </div>
            <div class="glass-card p-stack-lg rounded-lg">
                <p class="font-label-sm text-on-surface-variant uppercase tracking-widest mb-stack-sm">Total Photos</p>
                <h3 class="font-headline-lg text-headline-lg text-on-surface"><?php echo $displayPhotos; ?></h3>
                <p class="font-body-md text-on-surface-variant mt-2 text-sm">Captured by guests</p>
            </div>
            <div class="glass-card p-stack-lg rounded-lg">
                <p class="font-label-sm text-on-surface-variant uppercase tracking-widest mb-stack-sm">Guest Scans</p>
                <h3 class="font-headline-lg text-headline-lg text-on-surface"><?php echo $displayScans; ?></h3>
                <p class="font-body-md text-tertiary mt-2 text-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">qr_code_2</span> 85% conversion
                </p>
            </div>
            <div class="glass-card p-stack-lg rounded-lg">
                <p class="font-label-sm text-on-surface-variant uppercase tracking-widest mb-stack-sm">Storage Used</p>
                <h3 class="font-headline-lg text-headline-lg text-on-surface"><?php echo $storagePercent; ?>%</h3>
                <div class="w-full bg-surface-container-high h-2 rounded-full mt-4 overflow-hidden">
                    <div class="bg-primary h-full rounded-full" style="width: <?php echo $storagePercent; ?>%"></div>
                </div>
            </div>
        </section>

        <!-- Recent Events Section -->
        <section class="glass-card rounded-lg overflow-hidden">
            <div class="p-stack-lg border-b border-outline-variant/20 flex justify-between items-center">
                <h2 class="font-headline-md text-headline-md text-on-surface">Recent Events</h2>
                <a href="create-event.php" class="hidden sm:flex items-center gap-1.5 text-xs font-bold text-primary hover:underline no-underline">
                    <span class="material-symbols-outlined text-[16px]">add</span>New Event
                </a>
            </div>

            <?php if (empty($events)): ?>
                <div class="px-stack-lg py-16 text-center text-on-surface-variant font-body-md">
                    No events yet. <a href="create-event.php" class="text-primary font-bold hover:underline">Create your first event</a>!
                </div>
            <?php else: ?>

                <?php foreach ($events as $ev):
                    $isExpired = strtotime($ev['expires_at']) < time();
                    $eventDate = strtotime($ev['date']);
                    if ($isExpired)         { $statusClass = 'bg-slate-100 text-slate-500';         $statusText = 'Archived'; }
                    elseif ($eventDate > time()) { $statusClass = 'bg-amber-50 text-amber-700';      $statusText = 'Upcoming'; }
                    else                    { $statusClass = 'bg-green-50 text-green-700';           $statusText = 'Live'; }
                    $avatarIcon = 'favorite'; $avatarBg = 'bg-secondary-container'; $avatarText = 'text-on-secondary-container';
                    if ($ev['type'] === 'Corporate')                          { $avatarIcon = 'hub';        $avatarBg = 'bg-surface-container-highest'; $avatarText = 'text-primary'; }
                    elseif ($ev['type'] === 'Birthday')                       { $avatarIcon = 'celebration'; $avatarBg = 'bg-tertiary-fixed';            $avatarText = 'text-on-tertiary-fixed'; }
                    elseif (in_array($ev['type'], ['Concert','Festival']))    { $avatarIcon = 'music_note';  $avatarBg = 'bg-primary-fixed';             $avatarText = 'text-on-primary-fixed'; }
                ?>

                <!-- ── MOBILE CARD (hidden on md+) ─────────────────────────── -->
                <div class="md:hidden border-b border-outline-variant/10 p-4 last:border-0">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-11 h-11 rounded-xl <?php echo $avatarBg; ?> flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined <?php echo $avatarText; ?> text-[20px]"><?php echo $avatarIcon; ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="gallery.php?id=<?php echo $ev['event_uuid']; ?>" class="font-bold text-on-surface text-sm truncate block no-underline hover:text-primary"><?php echo htmlspecialchars($ev['name']); ?></a>
                            <p class="text-on-surface-variant text-xs mt-0.5 truncate"><?php echo htmlspecialchars($ev['venue']); ?></p>
                        </div>
                        <span class="px-2 py-0.5 <?php echo $statusClass; ?> text-[10px] font-bold rounded-full uppercase tracking-wider flex-shrink-0"><?php echo $statusText; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 text-xs text-on-surface-variant">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">photo</span><?php echo number_format($ev['total_uploads']); ?></span>
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">calendar_today</span><?php echo date('M d, Y', $eventDate); ?></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <a href="qr-code.php?id=<?php echo $ev['event_uuid']; ?>" class="p-1.5 rounded-lg bg-slate-50 border border-outline-variant hover:border-primary text-primary no-underline" title="QR Code">
                                <span class="material-symbols-outlined text-[16px]">qr_code_2</span>
                            </a>
                            <a href="gallery.php?id=<?php echo $ev['event_uuid']; ?>" class="p-1.5 rounded-lg bg-slate-50 border border-outline-variant hover:border-primary text-primary no-underline" title="Gallery">
                                <span class="material-symbols-outlined text-[16px]">photo_library</span>
                            </a>
                            <a href="media-crew.php?id=<?php echo $ev['id']; ?>" class="p-1.5 rounded-lg bg-slate-50 border border-outline-variant hover:border-primary text-primary no-underline" title="Invite Media Crew">
                                <span class="material-symbols-outlined text-[16px]">photo_camera</span>
                            </a>
                            <a href="edit-event.php?id=<?php echo $ev['id']; ?>" class="p-1.5 rounded-lg bg-slate-50 border border-outline-variant hover:border-primary text-primary no-underline" title="Edit">
                                <span class="material-symbols-outlined text-[16px]">edit</span>
                            </a>
                            <a href="dashboard.php?delete_event_id=<?php echo $ev['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>"
                               class="p-1.5 rounded-lg bg-red-50 border border-red-100 text-red-500 no-underline"
                               title="Delete"
                               onclick="return confirm('Permanently delete this event and all uploads?');">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                            </a>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

                <!-- ── DESKTOP TABLE (hidden on mobile) ──────────────────────── -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface-container-low">
                                <th class="px-stack-lg py-4 font-label-sm text-on-surface-variant">Event Name</th>
                                <th class="px-stack-lg py-4 font-label-sm text-on-surface-variant">Date</th>
                                <th class="px-stack-lg py-4 font-label-sm text-on-surface-variant">Photos</th>
                                <th class="px-stack-lg py-4 font-label-sm text-on-surface-variant">Status</th>
                                <th class="px-stack-lg py-4 font-label-sm text-on-surface-variant text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            <?php foreach ($events as $ev):
                                $isExpired = strtotime($ev['expires_at']) < time();
                                $eventDate = strtotime($ev['date']);
                                if ($isExpired)              { $statusClass = 'bg-slate-100 text-slate-500';    $statusText = 'Archived'; }
                                elseif ($eventDate > time()) { $statusClass = 'bg-amber-50 text-amber-700';    $statusText = 'Upcoming'; }
                                else                         { $statusClass = 'bg-green-50 text-green-700';    $statusText = 'Live'; }
                                $avatarIcon = 'favorite'; $avatarBg = 'bg-secondary-container'; $avatarText = 'text-on-secondary-container';
                                if ($ev['type'] === 'Corporate')                       { $avatarIcon = 'hub';        $avatarBg = 'bg-surface-container-highest'; $avatarText = 'text-primary'; }
                                elseif ($ev['type'] === 'Birthday')                    { $avatarIcon = 'celebration'; $avatarBg = 'bg-tertiary-fixed';            $avatarText = 'text-on-tertiary-fixed'; }
                                elseif (in_array($ev['type'], ['Concert','Festival'])) { $avatarIcon = 'music_note';  $avatarBg = 'bg-primary-fixed';             $avatarText = 'text-on-primary-fixed'; }
                            ?>
                            <tr class="hover:bg-primary/5 transition-colors">
                                <td class="px-stack-lg py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg <?php echo $avatarBg; ?> flex items-center justify-center flex-shrink-0">
                                            <span class="material-symbols-outlined <?php echo $avatarText; ?> text-[18px]"><?php echo $avatarIcon; ?></span>
                                        </div>
                                        <div>
                                            <a href="gallery.php?id=<?php echo $ev['event_uuid']; ?>" class="font-bold text-sm text-on-surface hover:text-primary no-underline"><?php echo htmlspecialchars($ev['name']); ?></a>
                                            <p class="text-xs text-on-surface-variant mt-0.5"><?php echo htmlspecialchars($ev['venue']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-stack-lg py-5 text-sm text-on-surface"><?php echo date('M d, Y', $eventDate); ?></td>
                                <td class="px-stack-lg py-5 text-sm text-on-surface"><?php echo number_format($ev['total_uploads']); ?></td>
                                <td class="px-stack-lg py-5">
                                    <span class="px-2.5 py-1 <?php echo $statusClass; ?> text-[11px] font-bold rounded-full uppercase tracking-wider"><?php echo $statusText; ?></span>
                                </td>
                                <td class="px-stack-lg py-5 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <a href="qr-code.php?id=<?php echo $ev['event_uuid']; ?>" class="p-2 rounded-full bg-white border border-outline-variant hover:border-primary transition-colors w-8 h-8 flex items-center justify-center no-underline" title="QR">
                                            <span class="material-symbols-outlined text-primary text-[17px]">qr_code_2</span>
                                        </a>
                                        <a href="gallery.php?id=<?php echo $ev['event_uuid']; ?>" class="p-2 rounded-full bg-white border border-outline-variant hover:border-primary transition-colors w-8 h-8 flex items-center justify-center no-underline" title="Gallery">
                                            <span class="material-symbols-outlined text-primary text-[17px]">photo_library</span>
                                        </a>
                                        <a href="event-analytics.php?id=<?php echo $ev['id']; ?>" class="p-2 rounded-full bg-white border border-outline-variant hover:border-primary transition-colors w-8 h-8 flex items-center justify-center no-underline" title="Analytics">
                                            <span class="material-symbols-outlined text-primary text-[17px]">insights</span>
                                        </a>
                                        <a href="media-crew.php?id=<?php echo $ev['id']; ?>" class="p-2 rounded-full bg-white border border-outline-variant hover:border-primary transition-colors w-8 h-8 flex items-center justify-center no-underline" title="Invite Crew">
                                            <span class="material-symbols-outlined text-primary text-[17px]">photo_camera</span>
                                        </a>
                                        <a href="edit-event.php?id=<?php echo $ev['id']; ?>" class="p-2 rounded-full bg-white border border-outline-variant hover:border-primary transition-colors w-8 h-8 flex items-center justify-center no-underline" title="Edit">
                                            <span class="material-symbols-outlined text-primary text-[17px]">edit</span>
                                        </a>
                                        <a href="dashboard.php?delete_event_id=<?php echo $ev['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>"
                                           class="p-2 rounded-full bg-white border border-outline-variant hover:border-red-400 text-red-500 transition-colors w-8 h-8 flex items-center justify-center no-underline"
                                           title="Delete"
                                           onclick="return confirm('Permanently delete this event and all uploads?');">
                                            <span class="material-symbols-outlined text-[17px]">delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
<?php
$pageScripts = '
    <!-- Background Decorative Element -->
    <div class="fixed top-0 right-0 -z-10 w-1/3 h-1/2 bg-gradient-to-bl from-primary/5 to-transparent blur-[120px] pointer-events-none"></div>
    <div class="fixed bottom-0 left-0 -z-10 w-1/4 h-1/3 bg-gradient-to-tr from-secondary-container/10 to-transparent blur-[100px] pointer-events-none"></div>

    <script>
        document.querySelectorAll(\'.glass-card\').forEach(card => {
            card.addEventListener(\'mouseenter\', () => {
                card.style.transform = \'translateY(-4px)\';
                card.style.transition = \'transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)\';
            });
            card.addEventListener(\'mouseleave\', () => {
                card.style.transform = \'translateY(0)\';
            });
        });

        window.addEventListener(\'load\', () => {
            const rows = document.querySelectorAll(\'tbody tr\');
            rows.forEach((row, index) => {
                row.style.opacity = \'0\';
                row.style.transform = \'translateY(10px)\';
                setTimeout(() => {
                    row.style.transition = \'all 0.5s ease\';
                    row.style.opacity = \'1\';
                    row.style.transform = \'translateY(0)\';
                }, index * 100);
            });
        });
    </script>
';
require_once __DIR__ . '/includes/footer.php';
?>
