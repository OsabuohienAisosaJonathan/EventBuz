<?php
/**
 * EventSnap Cloud - Platform Administrator Cockpit Panel
 * Allows complete global management of database assets, storage diagnostics, users, and events.
 * FIXED: All auth/redirect logic runs BEFORE header.php outputs any HTML.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

// Route check: Requires Admin role
Auth::requireRole('admin');

$db = getDBConnection();
$error = '';
$success = '';

// Handle Admin Delete User requests
if (isset($_GET['delete_user_id']) && isset($_GET['csrf'])) {
    if (verifyCSRFToken($_GET['csrf'])) {
        $delUserId = (int)$_GET['delete_user_id'];
        
        // Prevent deleting oneself
        if ($delUserId === (int)$_SESSION['user_id']) {
            $error = "Self-inflicted deletion is blocked.";
        } else {
            try {
                $db->beginTransaction();
                
                // 1. Fetch and purge all events owned by user physically
                $evStmt = $db->prepare("SELECT id FROM events WHERE owner_id = ?");
                $evStmt->execute([$delUserId]);
                $userEvents = $evStmt->fetchAll();
                
                foreach ($userEvents as $uev) {
                    EventManager::deleteEvent((int)$uev['id'], $delUserId);
                }
                
                // 2. Delete user from database
                $usrStmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $usrStmt->execute([$delUserId]);
                
                $db->commit();
                $success = "User account and all associated events/media purged successfully.";
            } catch (PDOException $e) {
                $db->rollBack();
                $error = "Purge operations encountered errors: " . $e->getMessage();
            }
        }
    }
}

// Handle Admin Delete Event requests (Global moderate)
if (isset($_GET['delete_event_id']) && isset($_GET['csrf'])) {
    if (verifyCSRFToken($_GET['csrf'])) {
        $delEvId = (int)$_GET['delete_event_id'];
        try {
            // Find owner id to satisfy EventManager parameters
            $evOwnerStmt = $db->prepare("SELECT owner_id FROM events WHERE id = ?");
            $evOwnerStmt->execute([$delEvId]);
            $ownerId = $evOwnerStmt->fetchColumn();
            
            if ($ownerId) {
                $res = EventManager::deleteEvent($delEvId, (int)$ownerId);
                if ($res['success']) {
                    $success = $res['message'];
                } else {
                    $error = $res['message'];
                }
            } else {
                $error = "Target event context not found.";
            }
        } catch (PDOException $e) {
            $error = "Purge failed: " . $e->getMessage();
        }
    }
}

// Query platform diagnostics telemetry
$telemetry = [
    'users' => 0,
    'events' => 0,
    'uploads' => 0,
    'storage' => 0
];

try {
    $telemetry['users']   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $telemetry['events']  = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $telemetry['uploads'] = $db->query("SELECT COUNT(*) FROM uploads")->fetchColumn();
    $telemetry['storage'] = $db->query("SELECT SUM(file_size) FROM uploads")->fetchColumn() ?: 0;
} catch (PDOException $e) {}

// Query User list
$usersList = [];
try {
    $usersList = $db->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM events WHERE owner_id = u.id) as events_created 
        FROM users u 
        ORDER BY u.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// Query Event list
$eventsList = [];
try {
    $eventsList = $db->query("
        SELECT e.*, u.name as owner_name, 
               (SELECT COUNT(*) FROM uploads WHERE event_id = e.id) as total_uploads 
        FROM events e 
        JOIN users u ON u.id = e.owner_id
        ORDER BY e.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// Safe to output HTML now
$pageTitle = "Platform Admin Cockpit";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    
    <!-- Title Area -->
    <div class="flex items-center mb-8 mt-3">
        <div>
            <h2 class="text-headline-lg font-bold text-on-surface">Platform Admin Cockpit</h2>
            <p class="text-on-surface-variant text-sm mt-1 flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px] text-primary">security</span> Global dashboard moderation, system telemetry, and diagnostics.</p>
        </div>
    </div>

    <!-- Telemetry Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- 1. Total Registered Planners -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">group</span>
            </div>
            <div>
                <h3 class="text-2xl font-bold text-on-surface"><?php echo number_format($telemetry['users']); ?></h3>
                <p class="text-on-surface-variant text-xs mt-0.5">Registered Users</p>
            </div>
        </div>
        
        <!-- 2. Total Configured Events -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-green-100 text-green-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">calendar_today</span>
            </div>
            <div>
                <h3 class="text-2xl font-bold text-on-surface"><?php echo number_format($telemetry['events']); ?></h3>
                <p class="text-on-surface-variant text-xs mt-0.5">Total Events</p>
            </div>
        </div>
        
        <!-- 3. Total Photo Uploads -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">image</span>
            </div>
            <div>
                <h3 class="text-2xl font-bold text-on-surface"><?php echo number_format($telemetry['uploads']); ?></h3>
                <p class="text-on-surface-variant text-xs mt-0.5">Total Snapped Photos</p>
            </div>
        </div>
        
        <!-- 4. Global Storage Utilization -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">database</span>
            </div>
            <div>
                <h3 class="text-2xl font-bold text-on-surface">
                    <?php 
                    $gb = $telemetry['storage'] / (1024 * 1024 * 1024);
                    if ($gb >= 1.0) {
                        echo number_format($gb, 2) . " GB";
                    } else {
                        echo number_format($telemetry['storage'] / (1024 * 1024), 2) . " MB";
                    }
                    ?>
                </h3>
                <p class="text-on-surface-variant text-xs mt-0.5">Server Space Utilized</p>
            </div>
        </div>
    </div>

    <!-- Feedback notifications banners -->
    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm border-l-4 bg-red-50 border-red-500 text-red-700 flex items-center gap-2" role="alert">
            <span class="material-symbols-outlined text-[20px]">warning</span>
            <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm border-l-4 bg-green-50 border-green-500 text-green-700 flex items-center gap-2" role="alert">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            <p class="font-medium"><?php echo htmlspecialchars($success); ?></p>
        </div>
    <?php endif; ?>

    <!-- Nav Tabs for User and Event lists -->
    <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-6 md:p-8">
        <div class="flex border-b border-slate-100 mb-6">
            <button class="px-6 py-3 border-b-2 border-primary font-bold text-primary text-sm flex items-center gap-2 tab-btn active" id="users-tab" data-target="users-pane">
                <span class="material-symbols-outlined text-[20px]">people</span>
                Manage Planners (<?php echo count($usersList); ?>)
            </button>
            <button class="px-6 py-3 border-b-2 border-transparent hover:border-slate-200 font-semibold text-on-surface-variant/80 hover:text-on-surface text-sm flex items-center gap-2 tab-btn" id="events-tab" data-target="events-pane">
                <span class="material-symbols-outlined text-[20px]">calendar_today</span>
                Manage Events (<?php echo count($eventsList); ?>)
            </button>
        </div>
        
        <div class="tab-content">
            <!-- PANE 1: USER DIRECTORY -->
            <div class="tab-pane" id="users-pane">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-on-surface-variant font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3.5 px-4">Name</th>
                                <th class="py-3.5 px-4">Email</th>
                                <th class="py-3.5 px-4">Access Role</th>
                                <th class="py-3.5 px-4">Events Owned</th>
                                <th class="py-3.5 px-4">Joined Date</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 font-medium">
                            <?php foreach ($usersList as $usr): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3.5 px-4 text-on-surface font-bold"><?php echo htmlspecialchars($usr['name']); ?></td>
                                    <td class="py-3.5 px-4 text-on-surface-variant/80"><?php echo htmlspecialchars($usr['email']); ?></td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo $usr['role'] == 'admin' ? 'bg-red-50 text-red-700 border border-red-200' : ($usr['role'] == 'crew' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-primary/10 text-primary'); ?>">
                                            <?php echo htmlspecialchars($usr['role']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-on-surface font-semibold"><?php echo $usr['events_created']; ?></td>
                                    <td class="py-3.5 px-4 text-on-surface-variant/80"><?php echo date('M d, Y', strtotime($usr['created_at'])); ?></td>
                                    <td class="py-3.5 px-4 text-right">
                                        <?php if ($usr['role'] !== 'admin'): ?>
                                            <a href="admin.php?delete_user_id=<?php echo $usr['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="p-1.5 rounded-full hover:bg-red-50 border border-slate-200 hover:border-red-200 text-red-500 transition-colors flex items-center justify-center text-decoration-none inline-flex"
                                               title="Purge User Account"
                                               onclick="return confirm('WARNING: Are you absolutely sure you want to permanently purge this host, all configured events, and delete all guest photo uploads? This cannot be undone.');">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-on-surface-variant/50 text-xs flex items-center justify-end gap-1"><span class="material-symbols-outlined text-[14px]">lock</span> Locked</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- PANE 2: EVENT DIRECTORY -->
            <div class="tab-pane hidden" id="events-pane">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-on-surface-variant font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3.5 px-4">Event Name</th>
                                <th class="py-3.5 px-4">Registered Owner</th>
                                <th class="py-3.5 px-4">Venue</th>
                                <th class="py-3.5 px-4">Category</th>
                                <th class="py-3.5 px-4">Total Captures</th>
                                <th class="py-3.5 px-4">Date Scheduled</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 font-medium">
                            <?php foreach ($eventsList as $ev): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <a href="gallery.php?id=<?php echo $ev['event_uuid']; ?>" class="text-on-surface text-decoration-none font-bold hover:text-primary transition-colors">
                                            <?php echo htmlspecialchars($ev['name']); ?>
                                        </a>
                                        <span class="block text-on-surface-variant/60 text-[10px] mt-0.5">ID: <code><?php echo $ev['event_uuid']; ?></code></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-on-surface-variant/80"><?php echo htmlspecialchars($ev['owner_name']); ?></td>
                                    <td class="py-3.5 px-4 text-on-surface-variant/80"><?php echo htmlspecialchars($ev['venue']); ?></td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded border border-slate-200 bg-slate-50 text-[10px] text-on-surface-variant"><?php echo htmlspecialchars($ev['type']); ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-primary font-bold"><?php echo $ev['total_uploads']; ?></td>
                                    <td class="py-3.5 px-4 text-on-surface-variant/80"><?php echo date('M d, Y', strtotime($ev['date'])); ?></td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="gallery.php?id=<?php echo $ev['event_uuid']; ?>" class="p-1.5 rounded-full hover:bg-slate-100 border border-slate-200 text-on-surface-variant transition-colors flex items-center justify-center text-decoration-none" title="View Gallery"><span class="material-symbols-outlined text-[16px]">visibility</span></a>
                                            <a href="admin.php?delete_event_id=<?php echo $ev['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="p-1.5 rounded-full hover:bg-red-50 border border-slate-200 hover:border-red-200 text-red-500 transition-colors flex items-center justify-center text-decoration-none"
                                               title="Purge Event Assembly"
                                               onclick="return confirm('WARNING: Are you absolutely sure you want to permanently delete this event and purge all associated photos?');">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = "
<script>
$(document).on('click', '.tab-btn', function() {
    $('.tab-btn').removeClass('active border-primary text-primary').addClass('border-transparent text-on-surface-variant/80 hover:text-on-surface');
    $(this).addClass('active border-primary text-primary').removeClass('border-transparent text-on-surface-variant/80 hover:text-on-surface');
    
    const target = $(this).data('target');
    $('.tab-pane').addClass('hidden');
    $('#' + target).removeClass('hidden');
});
</script>
";
require_once __DIR__ . '/includes/footer.php';
?>
