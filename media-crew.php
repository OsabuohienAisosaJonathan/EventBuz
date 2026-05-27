<?php
/**
 * EventSnap Cloud - Media Crew Portal Dashboard
 * Overhauled to match the premium light-theme design system.
 * FIXED: All auth/redirect logic runs BEFORE header.php outputs any HTML.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';

$db = getDBConnection();
$error = '';
$success = '';
$simulatedLink = '';

// Check if accessing via Invitation Token
$inviteToken = trim($_GET['invite'] ?? '');
$isInviteFlow = !empty($inviteToken);
$inviteValid = false;
$inviteEvent = null;
$inviteEmail = '';

if ($isInviteFlow) {
    try {
        $stmt = $db->prepare("SELECT * FROM media_crew WHERE invite_token = ? AND is_accepted = 0 LIMIT 1");
        $stmt->execute([$inviteToken]);
        $mc = $stmt->fetch();
        
        if ($mc) {
            $inviteValid = true;
            $inviteEmail = $mc['email'];
            
            // Get event details
            $evStmt = $db->prepare("SELECT * FROM events WHERE id = ?");
            $evStmt->execute([$mc['event_id']]);
            $inviteEvent = $evStmt->fetch();
        } else {
            $error = "This invitation token is invalid or has already been accepted.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle Acceptance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_invite_action'])) {
    $token = $_POST['invite_token'] ?? '';
    $name = trim($_POST['crew_name'] ?? '');
    $password = $_POST['crew_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please try again.";
    } elseif (empty($name) || empty($password)) {
        $error = "Name and password credentials are required.";
    } else {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT * FROM media_crew WHERE invite_token = ? AND is_accepted = 0 LIMIT 1");
            $stmt->execute([$token]);
            $mc = $stmt->fetch();
            
            if ($mc) {
                // 1. Create User account with role 'crew'
                $hashedPass = password_hash($password, PASSWORD_BCRYPT);
                $uStmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'crew') ON DUPLICATE KEY UPDATE role='crew'");
                $uStmt->execute([$name, $mc['email'], $hashedPass]);
                
                // 2. Mark invite as accepted
                $accStmt = $db->prepare("UPDATE media_crew SET is_accepted = 1 WHERE id = ?");
                $accStmt->execute([$mc['id']]);
                
                $db->commit();
                $success = "Invitation successfully accepted! You can now log in to access the Media Crew uploader dashboard.";
                $isInviteFlow = false;
            } else {
                $db->rollBack();
                $error = "Invalid invitation token context.";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Database transaction failed: " . $e->getMessage();
        }
    }
}

// Handle Host sending invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invite_action'])) {
    Auth::requireRole('owner');
    
    $eventId = (int)($_POST['event_id'] ?? 0);
    $crewEmail = trim(strtolower($_POST['crew_email'] ?? ''));
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please try again.";
    } elseif (empty($crewEmail) || !filter_var($crewEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "A valid email address is required.";
    } else {
        try {
            $evStmt = $db->prepare("SELECT id, name FROM events WHERE id = ? AND owner_id = ?");
            $evStmt->execute([$eventId, $_SESSION['user_id']]);
            $event = $evStmt->fetch();
            
            if (!$event) {
                $error = "Event not found or permission denied.";
            } else {
                $token = bin2hex(random_bytes(32));
                $insStmt = $db->prepare("INSERT INTO media_crew (event_id, email, invite_token) VALUES (?, ?, ?)");
                $insStmt->execute([$eventId, $crewEmail, $token]);
                
                $inviteUrl = BASE_URL . "media-crew.php?invite=" . $token;
                $success = "Media Crew invitation generated! Simulated email dispatch completed.";
                $simulatedLink = $inviteUrl;
            }
        } catch (PDOException $e) {
            $error = "Invitation dispatch failed: " . $e->getMessage();
        }
    }
}

// Normal logged-in Media Crew dashboard view
$crewEvents = [];
if (Auth::isLoggedIn() && $_SESSION['user_role'] === 'crew') {
    try {
        $stmt = $db->prepare("
            SELECT e.* 
            FROM media_crew mc
            JOIN events e ON e.id = mc.event_id
            WHERE mc.email = ? AND mc.is_accepted = 1
            ORDER BY e.date DESC
        ");
        $stmt->execute([$_SESSION['user_email']]);
        $crewEvents = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Host list of invites
$hostInvites     = [];
$activeHostEvent = null;
$hostEventId     = 0;
$ownerEvents     = [];   // populated when no ?id= so owner can pick

if (Auth::isLoggedIn() && $_SESSION['user_role'] === 'owner') {
    $hostEventId = (int)($_GET['id'] ?? 0);
    try {
        if ($hostEventId > 0) {
            // Load the specific event (owner-verified)
            $evStmt = $db->prepare("SELECT id, name FROM events WHERE id = ? AND owner_id = ?");
            $evStmt->execute([$hostEventId, $_SESSION['user_id']]);
            $activeHostEvent = $evStmt->fetch();

            if ($activeHostEvent) {
                $stmt = $db->prepare("SELECT * FROM media_crew WHERE event_id = ?");
                $stmt->execute([$hostEventId]);
                $hostInvites = $stmt->fetchAll();
            } else {
                // Not their event — fall through to event picker
                $hostEventId = 0;
            }
        }

        if ($hostEventId === 0) {
            // Show the owner a list of their own events to pick from
            $ownerEvStmt = $db->prepare("SELECT id, name FROM events WHERE owner_id = ? ORDER BY date DESC");
            $ownerEvStmt->execute([$_SESSION['user_id']]);
            $ownerEvents = $ownerEvStmt->fetchAll();
        }
    } catch (PDOException $e) {}
}

// Safe to output HTML now
$pageTitle = "Media Crew Operations";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. INVITATION REGISTRATION FLOW -->
<?php if ($isInviteFlow && $inviteValid): ?>
    <div class="max-w-md mx-auto my-12">
        <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-6 md:p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-[32px]">photo_camera_back</span>
                </div>
                <h2 class="text-headline-md font-bold text-on-surface mb-1">Accept Crew Invitation</h2>
                <p class="text-on-surface-variant/80 text-sm">You have been invited to join the media crew for <br><strong class="text-on-surface"><?php echo htmlspecialchars($inviteEvent['name']); ?></strong>.</p>
            </div>
            
            <form action="media-crew.php?invite=<?php echo htmlspecialchars($inviteToken); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="invite_token" value="<?php echo htmlspecialchars($inviteToken); ?>">
                <input type="hidden" name="accept_invite_action" value="1">
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-on-surface-variant mb-2">Your Email (Assigned)</label>
                    <input type="email" class="w-full rounded-lg border border-outline-variant/60 bg-slate-50 px-4 py-3 text-on-surface-variant/70 cursor-not-allowed text-sm" value="<?php echo htmlspecialchars($inviteEmail); ?>" readonly>
                </div>
                
                <div class="mb-4">
                    <label for="crew_name" class="block text-sm font-semibold text-on-surface-variant mb-2">Full Name / Studio Name</label>
                    <input type="text" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all text-sm" id="crew_name" name="crew_name" required placeholder="Studio Max Photography">
                </div>
                
                <div class="mb-6">
                    <label for="crew_password" class="block text-sm font-semibold text-on-surface-variant mb-2">Create Password</label>
                    <div class="relative rounded-lg overflow-hidden border border-outline-variant/60 focus-within:border-primary focus-within:ring focus-within:ring-primary/20 transition-all bg-background flex items-center">
                        <span class="px-3 text-on-surface-variant/50"><span class="material-symbols-outlined text-[20px]">lock</span></span>
                        <input type="password" class="w-full border-0 focus:ring-0 bg-transparent px-1 py-3 text-on-surface text-sm" id="crew_password" name="crew_password" required placeholder="••••••••">
                        <button class="px-3 text-on-surface-variant/60 hover:text-primary transition-colors toggle-password" type="button" data-target="crew_password"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                    </div>
                </div>
                
                <button type="submit" class="w-full py-3 bg-primary text-on-primary hover:bg-primary-container font-bold rounded-xl shadow-lg shadow-primary/20 transition-all text-sm">Accept Invitation & Join</button>
            </form>
        </div>
    </div>

<!-- 2. HOST INVITE SENDING WORKSPACE -->
<?php elseif (Auth::isLoggedIn() && $_SESSION['user_role'] === 'owner'): ?>

    <div class="max-w-4xl mx-auto">
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
                        <span class="ml-1 text-on-surface-variant/70 font-semibold md:ml-2">Invite Media Crew</span>
                    </div>
                </li>
            </ol>
        </nav>

        <?php if ($hostEventId === 0): ?>
            <!-- ── No event selected: show event picker ─────────── -->
            <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-6 md:p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[24px]">group_add</span>
                    </div>
                    <div>
                        <h2 class="text-headline-md font-bold text-on-surface">Invite Media Crew</h2>
                        <p class="text-on-surface-variant text-sm mt-0.5">Select which event you want to assign photographers to.</p>
                    </div>
                </div>

                <?php if (empty($ownerEvents)): ?>
                    <div class="text-center py-10">
                        <span class="material-symbols-outlined text-[48px] text-on-surface-variant/30">event_busy</span>
                        <h5 class="text-on-surface font-bold mt-3 text-sm">No events found</h5>
                        <p class="text-on-surface-variant/80 text-xs mt-1 mb-4">Create an event first, then invite your media crew.</p>
                        <a href="create-event.php" class="px-6 py-2.5 bg-primary text-on-primary font-bold rounded-xl text-sm no-underline inline-flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">add</span> Create Event
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($ownerEvents as $oe): ?>
                            <a href="media-crew.php?id=<?php echo $oe['id']; ?>" class="flex items-center gap-4 p-4 rounded-xl border border-outline-variant/40 hover:border-primary hover:bg-primary/5 transition-all no-underline group">
                                <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">photo_camera</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-on-surface text-sm truncate group-hover:text-primary transition-colors"><?php echo htmlspecialchars($oe['name']); ?></p>
                                    <p class="text-on-surface-variant/70 text-xs mt-0.5">Manage crew invitations</p>
                                </div>
                                <span class="material-symbols-outlined text-on-surface-variant/40 group-hover:text-primary transition-colors">chevron_right</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- ── Event selected: invite form + roster ──────────── -->
            <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-6 md:p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[24px]">group_add</span>
                    </div>
                    <div>
                        <h2 class="text-headline-md font-bold text-on-surface">Invite Professional Crew</h2>
                        <p class="text-on-surface-variant text-sm mt-0.5">Assign DSLR photographers to <strong><?php echo htmlspecialchars($activeHostEvent['name']); ?></strong>.</p>
                    </div>
                </div>

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
                    <?php if (!empty($simulatedLink)): ?>
                        <div class="border border-sky-100 bg-sky-50/50 rounded-xl p-4 mb-6 text-center">
                            <p class="text-xs text-sky-800 font-semibold flex items-center justify-center gap-1 mb-2">
                                <span class="material-symbols-outlined text-[16px]">terminal</span>
                                SIMULATED INVITE LINK (Local Dev Only):
                            </p>
                            <a href="<?php echo htmlspecialchars($simulatedLink); ?>" class="px-4 py-2 border border-primary text-primary hover:bg-primary/5 rounded-lg text-xs font-bold transition-all text-decoration-none inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">link</span> Accept Crew Invite
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form action="media-crew.php?id=<?php echo $hostEventId; ?>" method="POST" class="mb-8">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="event_id" value="<?php echo $hostEventId; ?>">
                    <input type="hidden" name="send_invite_action" value="1">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-grow">
                            <input type="email" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-4 py-3 text-on-surface transition-all text-sm" name="crew_email" required placeholder="Photographer email (e.g. photographer@studio.com)">
                        </div>
                        <div>
                            <button type="submit" class="w-full md:w-auto px-6 py-3 bg-primary text-on-primary hover:bg-primary-container font-bold rounded-xl shadow-lg shadow-primary/20 transition-all text-sm">Send Invite</button>
                        </div>
                    </div>
                </form>

                <h4 class="text-sm font-bold text-on-surface uppercase tracking-wider mb-4 border-t border-outline-variant/20 pt-6">Crew Invites & Status</h4>
                <?php if (empty($hostInvites)): ?>
                    <p class="text-on-surface-variant/80 text-xs">No crew invited yet. Send an invitation above to enable DSLR bulk photo uploads.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-on-surface-variant font-bold uppercase tracking-wider text-[10px]">
                                    <th class="py-3 px-4">Invited Email</th>
                                    <th class="py-3 px-4">Sent Date</th>
                                    <th class="py-3 px-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 font-medium">
                                <?php foreach ($hostInvites as $hi): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="py-3.5 px-4 text-on-surface font-bold"><?php echo htmlspecialchars($hi['email']); ?></td>
                                        <td class="py-3.5 px-4 text-on-surface-variant/80"><?php echo date('M d, Y g:i A', strtotime($hi['created_at'])); ?></td>
                                        <td class="py-3.5 px-4 text-right">
                                            <?php if ($hi['is_accepted']): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-green-50 text-green-700 border border-green-200">Accepted</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

<!-- 3. MEDIA CREW PHOTOGRAPHER PORTAL UPLOADER -->
<?php elseif (Auth::isLoggedIn() && $_SESSION['user_role'] === 'crew'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto">
        <div class="col-span-1">
            <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-6">
                <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-[24px]">photo_camera</span>
                </div>
                <h3 class="text-headline-md text-base font-bold text-on-surface mb-1">Photographer Cockpit</h3>
                <p class="text-on-surface-variant text-xs">Bulk upload professional high-resolution JPEG, PNG, or WEBP photos compiled on your professional cameras.</p>
                
                <hr class="border-outline-variant/20 my-6">
                
                <div class="mb-4">
                    <label for="uploaderCredit" class="block text-xs font-semibold text-on-surface-variant mb-2">Watermark Credit Label</label>
                    <input type="text" class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-3 py-2 text-on-surface transition-all text-xs" id="uploaderCredit" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" placeholder="e.g. Studio Max">
                </div>
                
                <div class="mb-2">
                    <label for="crewEventSelector" class="block text-xs font-semibold text-on-surface-variant mb-2">Target Event</label>
                    <select class="w-full rounded-lg border border-outline-variant/60 focus:border-primary focus:ring focus:ring-primary/20 bg-background px-3 py-2 text-on-surface transition-all text-xs" id="crewEventSelector">
                        <?php if (empty($crewEvents)): ?>
                            <option value="">No Active Event Assigned</option>
                        <?php else: ?>
                            <?php foreach ($crewEvents as $ce): ?>
                                <option value="<?php echo htmlspecialchars($ce['event_uuid']); ?>"><?php echo htmlspecialchars($ce['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="col-span-1 lg:col-span-2">
            <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-6 md:p-8 h-full">
                <h4 class="text-base font-bold text-on-surface mb-2 flex items-center gap-2"><span class="material-symbols-outlined text-primary">cloud_upload</span> DSLR Professional Bulk Upload</h4>
                <p class="text-on-surface-variant text-xs">Drag your professional images from your explorer directly into the dropzone. Photos will be saved with uploader credentials.</p>
                
                <div id="crewDropzone" class="border-2 border-dashed border-outline-variant/60 hover:border-primary hover:bg-primary/5 rounded-xl cursor-pointer p-8 text-center mt-6 transition-all">
                    <div class="dz-message py-4">
                        <span class="material-symbols-outlined text-[48px] text-primary mb-3">cloud_upload</span>
                        <span class="block text-on-surface font-bold text-sm">Drag & Drop Professional Files Here</span>
                        <span class="text-on-surface-variant/80 text-xs mt-1 block">Supports multiple files simultaneous uploads (Max size: 15MB)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Fallback -->
    <div class="max-w-md mx-auto my-12 text-center">
        <div class="bg-white rounded-xl border border-outline-variant/30 shadow-premium p-8">
            <span class="material-symbols-outlined text-[48px] text-on-surface-variant/40">lock</span>
            <h3 class="text-on-surface font-bold mt-4 text-base">Authentication Required</h3>
            <p class="text-on-surface-variant text-xs mt-1 mb-6">Please log in to your Event owner or Media Crew dashboard to access this cockpit.</p>
            <a href="login.php" class="px-6 py-3 bg-primary text-on-primary hover:bg-primary-container font-bold rounded-xl shadow-lg shadow-primary/20 transition-all text-sm text-decoration-none inline-block">Log In Portal</a>
        </div>
    </div>
<?php endif; ?>

<?php
// Disable Dropzone auto-discover BEFORE the library loads
$dzDisable = '<script>window.Dropzone && (Dropzone.autoDiscover = false);</script>';

$pageScripts = $dzDisable;

if (Auth::isLoggedIn() && $_SESSION['user_role'] === 'crew') {
    $safeUserName = htmlspecialchars($_SESSION['user_name'] ?? 'Photographer', ENT_QUOTES);
    $pageScripts .= "
    <script>
        // Disable before Dropzone DOMContentLoaded (belt-and-braces)
        if (window.Dropzone) Dropzone.autoDiscover = false;

        document.addEventListener('DOMContentLoaded', function () {
            var crewDropzone = new Dropzone('#crewDropzone', {
                url: '" . BASE_URL . "ajax-handler.php',
                paramName: 'file',
                maxFilesize: 15,
                acceptedFiles: 'image/*',
                autoProcessQueue: true,
                parallelUploads: 3,
                sending: function(file, xhr, formData) {
                    var selector = document.getElementById('crewEventSelector');
                    var credit   = document.getElementById('uploaderCredit');
                    formData.append('event_uuid',    selector ? selector.value : '');
                    formData.append('uploader_name', credit && credit.value.trim() ? credit.value.trim() : '$safeUserName');
                    formData.append('uploader_role', 'crew');
                    formData.append('action', 'upload');
                },
                success: function(file, response) {
                    if (response.success) {
                        setTimeout(function() { crewDropzone.removeFile(file); }, 1500);
                    } else {
                        alert(response.message || 'Upload error.');
                        crewDropzone.removeFile(file);
                    }
                },
                error: function(file, message) {
                    alert('Upload failed: ' + (typeof message === 'string' ? message : 'server error'));
                    crewDropzone.removeFile(file);
                }
            });
        });

        // Vanilla password toggle
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.toggle-password');
            if (!btn) return;
            var input = document.getElementById(btn.dataset.target);
            var icon  = btn.querySelector('.material-symbols-outlined');
            if (!input) return;
            if (input.type === 'password') { input.type = 'text';     if (icon) icon.textContent = 'visibility_off'; }
            else                           { input.type = 'password'; if (icon) icon.textContent = 'visibility'; }
        });
    </script>
    ";
} else {
    $pageScripts .= "
    <script>
        // Vanilla password toggle
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.toggle-password');
            if (!btn) return;
            var input = document.getElementById(btn.dataset.target);
            var icon  = btn.querySelector('.material-symbols-outlined');
            if (!input) return;
            if (input.type === 'password') { input.type = 'text';     if (icon) icon.textContent = 'visibility_off'; }
            else                           { input.type = 'password'; if (icon) icon.textContent = 'visibility'; }
        });
    </script>
    ";
}
require_once __DIR__ . '/includes/footer.php';
?>
