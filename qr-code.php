<?php
/**
 * EventSnap Cloud - Event QR Table Sign Board
 * FIXED: All redirects happen BEFORE header.php outputs any HTML.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

// Auth + redirect logic BEFORE any HTML output
Auth::requireLogin();

$eventUuid = trim($_GET['id'] ?? '');
if (empty($eventUuid)) {
    header("Location: dashboard.php");
    exit;
}

$event = EventManager::getEventByUuid($eventUuid);
if (!$event) {
    header("Location: dashboard.php");
    exit;
}

$isOwner = ($_SESSION['user_id'] === $event['owner_id']);
$isAdmin = ($_SESSION['user_role'] === 'admin');

if (!$isOwner && !$isAdmin) {
    header("Location: dashboard.php");
    exit;
}

// Safe to output HTML now
$pageTitle = "Print Event QR Signs";
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Printing layouts */
    @media print {
        body {
            background-color: #FFFFFF !important;
            color: #000000 !important;
        }
        nav, footer, .sidebar, header, .no-print {
            display: none !important;
        }
        main {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        .print-card-box {
            border: 2px solid #000000 !important;
            background: #FFFFFF !important;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 60px !important;
            width: 100% !important;
            max-width: 100% !important;
            height: 98vh !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
        }
        .print-qr-frame {
            width: 320px !important;
            height: 320px !important;
        }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print max-w-2xl mx-auto mt-2">
    <a href="dashboard.php" class="px-4 py-2 border border-primary text-primary hover:bg-primary/5 rounded-lg text-xs font-bold transition-all text-decoration-none flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">arrow_back</span> Back to Dashboard</a>
    <button onclick="window.print()" class="px-5 py-2.5 bg-primary text-on-primary hover:bg-primary-container font-bold rounded-xl shadow-lg shadow-primary/20 transition-all text-xs flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">print</span> Print Table Sign</button>
</div>

<div class="max-w-2xl mx-auto">
    <div class="print-card-box bg-white rounded-3xl border border-outline-variant/35 shadow-premium p-8 md:p-12 text-center relative overflow-hidden">
        <div class="absolute top-[-50px] left-[-50px] w-[300px] h-[300px] bg-primary/5 rounded-full filter blur-[100px] pointer-events-none"></div>
        
        <!-- Branding Header -->
        <div class="mb-6 flex flex-col items-center">
            <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-[32px]">photo_camera</span>
            </div>
            <h2 class="text-3xl font-extrabold text-on-surface tracking-tight mb-2">Capture the Memories!</h2>
            <p class="text-on-surface-variant text-sm">Help us gather every beautiful snapshot of our event.</p>
        </div>
        
        <hr class="border-outline-variant/20 my-6">
        
        <!-- Large QR Frame -->
        <div class="my-6 flex justify-center">
            <div class="p-4 bg-white rounded-2xl shadow-md border border-outline-variant/45 inline-block">
                <img src="<?php echo BASE_URL . htmlspecialchars($event['qr_path']); ?>" alt="Scan QR Code" class="print-qr-frame w-[250px] h-[250px]">
            </div>
        </div>
        
        <h3 class="text-2xl font-extrabold text-on-surface mb-1 mt-4"><?php echo htmlspecialchars($event['name']); ?></h3>
        <p class="text-on-surface-variant/80 text-sm flex items-center justify-center gap-1.5"><span class="material-symbols-outlined text-[16px] text-primary">location_on</span> <?php echo htmlspecialchars($event['venue']); ?> &bull; <?php echo date('F d, Y', strtotime($event['date'])); ?></p>
        
        <hr class="border-outline-variant/20 my-6">
        
        <!-- Three Easy Steps -->
        <h5 class="text-on-surface-variant/80 text-xs font-bold uppercase tracking-wider mb-4">Three Easy Steps</h5>
        <div class="grid grid-cols-3 gap-4 text-center text-xs text-on-surface-variant">
            <div class="border-r border-outline-variant/20">
                <span class="block text-primary font-bold text-lg mb-1">01</span>
                <span class="font-semibold">Open Camera</span>
            </div>
            <div class="border-r border-outline-variant/20">
                <span class="block text-primary font-bold text-lg mb-1">02</span>
                <span class="font-semibold">Scan QR Code</span>
            </div>
            <div>
                <span class="block text-primary font-bold text-lg mb-1">03</span>
                <span class="font-semibold">Snap & Share</span>
            </div>
        </div>
        
        <div class="mt-12 text-xs text-on-surface-variant/65">
            <span>Powered by <strong>EventSnap.Cloud</strong></span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
