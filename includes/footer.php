<?php
/**
 * EventSnap Cloud - Universal Dynamic Footer
 */

$isHostDashboard = false;
if (Auth::isLoggedIn()) {
    $activePage = basename($_SERVER['PHP_SELF']);
    $userRole = $_SESSION['user_role'] ?? '';
    $isHostDashboard = in_array($activePage, [
        'dashboard.php', 'create-event.php', 'edit-event.php', 'event-analytics.php', 'admin.php', 'media-crew.php', 'pricing.php'
    ]) && ($userRole === 'owner' || $userRole === 'admin');
}
?>

<?php if ($isHostDashboard): ?>
            <!-- Close Host Sidebar HTML Wrapper tags -->
            <footer class="py-6 mt-16 text-center text-xs text-on-surface-variant/60 border-t border-outline-variant/25">
                <strong>EventSnap Cloud</strong> &bull; Premium Event Photography Management Platform &copy; <?php echo date('Y'); ?>.
            </footer>
            </main>
<?php else: ?>
    <!-- Close Public Header HTML Wrapper tags -->
    </main>

    <!-- Standard Elegant Brand Footer (Matching Reference Layout) -->
    <footer class="py-5 bg-white border-top border-light mt-auto text-muted">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <a class="navbar-brand text-gradient d-flex align-items-center gap-2 mb-3" href="<?php echo BASE_URL; ?>" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.5rem; color: var(--color-brand);">
                        <span><i class="bi bi-camera-fill"></i></span>
                        <span>EventSnap</span>
                    </a>
                    <p class="small text-muted mb-4">
                        The smarter, faster way to collect and share event memories in high resolution. App-free and beautiful.
                    </p>
                    <div class="d-flex gap-3 fs-5">
                        <a href="#" class="text-muted"><i class="bi bi-globe"></i></a>
                        <a href="#" class="text-muted"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-muted"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 col-6 ms-lg-auto">
                    <h6 class="text-dark fw-bold mb-3">Product</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><a href="<?php echo BASE_URL; ?>" class="text-muted text-decoration-none">Product</a></li>
                        <li class="mb-2"><a href="<?php echo BASE_URL; ?>pricing.php" class="text-muted text-decoration-none">Weddings</a></li>
                        <li class="mb-2"><a href="<?php echo BASE_URL; ?>pricing.php" class="text-muted text-decoration-none">Corporate</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 col-6">
                    <h6 class="text-dark fw-bold mb-3">Resources</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Parties</a></li>
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Get the QR</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <h6 class="text-dark fw-bold mb-3">Subscribe</h6>
                    <p class="small text-muted mb-3">Enter your email below to subscribe to event newsletters.</p>
                    <div class="input-group mb-2">
                        <input type="email" class="form-control form-control-premium text-dark bg-light py-2" placeholder="Enter your email" style="border-radius: 12px 0 0 12px;">
                        <button class="btn btn-brand px-3" type="button" style="border-radius: 0 12px 12px 0;">Notify Me</button>
                    </div>
                    <span class="small text-muted" style="font-size: 0.8rem;">&copy; <?php echo date('Y'); ?> EventSnap Cloud. All rights reserved.</span>
                </div>
            </div>
        </div>
    </footer>
<?php endif; ?>

    <!-- CDNs Javascript dependencies -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/app.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/app.js'); ?>"></script>
    
    <!-- Dynamic custom scripts injection -->
    <?php echo isset($pageScripts) ? $pageScripts : ''; ?>

</body>
</html>
