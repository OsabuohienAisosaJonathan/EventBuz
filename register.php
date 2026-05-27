<?php
/**
 * EventSnap Cloud - Registration Portal
 * FIXED: All redirects happen BEFORE any HTML is output.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';

// Redirect already-logged-in users
if (Auth::isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'owner';
    if ($role === 'admin') {
        header("Location: admin.php");
    } elseif ($role === 'crew') {
        header("Location: media-crew.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error   = '';
$success = '';
$name    = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = $_POST['name']       ?? '';
    $email     = $_POST['email']      ?? '';
    $password  = $_POST['password']   ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        $result = Auth::register($name, $email, $password, 'owner');
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | EventSnap – Capture Every Moment</title>
    <meta name="description" content="Create your free EventSnap host account and start collecting guest memories at your next event.">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/default-banner.jpg">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --brand:        #7C3AED;
            --brand-hover:  #6D28D9;
            --brand-light:  #F5F3FF;
            --brand-mid:    #EDE9FE;
            --text-main:    #0F172A;
            --text-muted:   #64748B;
            --border:       #E2E8F0;
            --white:        #FFFFFF;
            --radius:       14px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--white);
            min-height: 100vh;
            display: flex;
        }

        /* ── LEFT PANEL ── */
        .auth-left {
            flex: 0 0 46%;
            background: linear-gradient(145deg, #4C1D95 0%, #6D28D9 40%, #7C3AED 70%, #8B5CF6 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 52px;
            position: relative;
            overflow: hidden;
        }
        .auth-left::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .auth-left::after {
            content: '';
            position: absolute;
            bottom: -120px; left: -60px;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .blob {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            z-index: 0;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            z-index: 1;
        }
        .brand-logo .icon {
            width: 44px; height: 44px;
            background: rgba(255,255,255,0.18);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #fff;
            backdrop-filter: blur(8px);
        }
        .brand-logo span {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 1.5rem; color: #fff;
        }

        .left-hero { z-index: 1; }
        .left-hero .tagline {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 2.4rem;
            line-height: 1.18; color: #fff; margin-bottom: 16px;
        }
        .left-hero .tagline em { font-style: normal; color: #C4B5FD; }
        .left-hero .sub {
            font-size: 0.95rem; color: rgba(255,255,255,0.72);
            line-height: 1.6; max-width: 320px; margin-bottom: 36px;
        }

        /* Steps */
        .steps { display: flex; flex-direction: column; gap: 18px; }
        .step {
            display: flex; align-items: flex-start; gap: 14px;
        }
        .step-num {
            width: 34px; height: 34px; flex-shrink: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 0.85rem; color: #fff;
        }
        .step-text strong {
            display: block; color: #fff;
            font-size: 0.88rem; font-weight: 700; margin-bottom: 2px;
        }
        .step-text span {
            color: rgba(255,255,255,0.62);
            font-size: 0.8rem; line-height: 1.4;
        }

        .left-footer-note {
            z-index: 1;
            border-top: 1px solid rgba(255,255,255,0.15);
            padding-top: 24px;
        }
        .left-footer-note p {
            font-size: 0.8rem; color: rgba(255,255,255,0.65);
        }
        .left-footer-note p strong { color: #fff; }
        .left-footer-note .link {
            color: #C4B5FD; text-decoration: none; font-weight: 600;
        }
        .left-footer-note .link:hover { text-decoration: underline; }

        /* ── RIGHT PANEL ── */
        .auth-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
            background: var(--white);
            overflow-y: auto;
        }

        .form-box { width: 100%; max-width: 420px; }

        .form-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--brand-light); color: var(--brand);
            border-radius: 50px; padding: 5px 14px;
            font-size: 0.78rem; font-weight: 600;
            margin-bottom: 20px; border: 1px solid var(--brand-mid);
        }

        .form-box h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 1.9rem;
            color: var(--text-main); margin-bottom: 6px; line-height: 1.2;
        }
        .form-box .form-subtitle {
            color: var(--text-muted); font-size: 0.9rem;
            margin-bottom: 28px; line-height: 1.55;
        }

        .auth-alert {
            border-radius: var(--radius); padding: 12px 16px;
            font-size: 0.85rem; display: flex;
            align-items: center; gap: 10px;
            margin-bottom: 22px; font-weight: 500;
        }
        .auth-alert.danger  { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .auth-alert.success { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }

        .success-card {
            background: var(--brand-light);
            border: 1px solid var(--brand-mid);
            border-radius: var(--radius);
            padding: 28px 24px;
            text-align: center;
            margin-bottom: 20px;
        }
        .success-card .icon-circle {
            width: 56px; height: 56px;
            background: var(--brand); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.4rem; color: #fff;
        }
        .success-card h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 1.15rem;
            color: var(--text-main); margin-bottom: 8px;
        }
        .success-card p {
            font-size: 0.88rem; color: var(--text-muted); line-height: 1.5;
        }

        .field-group { margin-bottom: 18px; }
        .field-label {
            display: block;
            font-size: 0.83rem; font-weight: 600;
            color: var(--text-main); margin-bottom: 7px;
        }

        .input-wrap {
            position: relative; display: flex; align-items: center;
        }
        .input-icon {
            position: absolute; left: 14px;
            color: var(--text-muted); font-size: 0.95rem;
            pointer-events: none;
        }
        .input-wrap input {
            width: 100%;
            padding: 12px 44px 12px 40px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9rem; font-family: 'Inter', sans-serif;
            color: var(--text-main); background: var(--white);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-wrap input::placeholder { color: #B0BCCC; }
        .input-wrap input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
        }
        .toggle-pw {
            position: absolute; right: 14px;
            background: none; border: none;
            color: var(--text-muted); cursor: pointer;
            padding: 0; font-size: 0.95rem; line-height: 1;
        }
        .toggle-pw:hover { color: var(--brand); }

        /* Password strength */
        .pw-strength {
            display: flex; gap: 4px; margin-top: 8px;
        }
        .pw-strength .bar {
            flex: 1; height: 3px; border-radius: 99px;
            background: var(--border);
            transition: background 0.3s;
        }
        .pw-strength.weak   .bar:nth-child(-n+1) { background: #EF4444; }
        .pw-strength.fair   .bar:nth-child(-n+2) { background: #F59E0B; }
        .pw-strength.good   .bar:nth-child(-n+3) { background: #10B981; }
        .pw-strength.strong .bar:nth-child(-n+4) { background: #7C3AED; }
        .pw-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 5px; }

        /* Terms checkbox */
        .terms-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 20px; margin-top: 4px;
        }
        .terms-row input[type="checkbox"] {
            width: 16px; height: 16px; margin-top: 2px;
            accent-color: var(--brand); flex-shrink: 0; cursor: pointer;
        }
        .terms-row label {
            font-size: 0.82rem; color: var(--text-muted); cursor: pointer; line-height: 1.5;
        }
        .terms-row label a {
            color: var(--brand); font-weight: 600; text-decoration: none;
        }

        .btn-auth {
            width: 100%; padding: 13px;
            background: var(--brand); color: #fff;
            border: none; border-radius: var(--radius);
            font-size: 0.95rem; font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(124,58,237,0.3);
        }
        .btn-auth:hover {
            background: var(--brand-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(124,58,237,0.38);
        }
        .btn-auth:active { transform: translateY(0); }
        .btn-auth:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        .btn-auth-outline {
            width: 100%; padding: 13px;
            background: transparent; color: var(--brand);
            border: 1.5px solid var(--brand); border-radius: var(--radius);
            font-size: 0.95rem; font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-auth-outline:hover {
            background: var(--brand-light);
            transform: translateY(-1px);
        }

        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 22px 0;
            font-size: 0.78rem; color: var(--text-muted);
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }

        .form-footer {
            text-align: center; font-size: 0.85rem; color: var(--text-muted);
        }
        .form-footer a {
            color: var(--brand); font-weight: 700; text-decoration: none;
        }
        .form-footer a:hover { text-decoration: underline; }

        @media (max-width: 900px) {
            .auth-left { display: none; }
            .auth-right { padding: 32px 24px; }
        }
    </style>
</head>
<body>

    <!-- LEFT BRANDING PANEL -->
    <div class="auth-left">
        <div class="blob" style="width:260px;height:260px;top:10%;right:-60px;"></div>
        <div class="blob" style="width:180px;height:180px;bottom:20%;right:10%;"></div>

        <a href="<?php echo BASE_URL; ?>" class="brand-logo">
            <div class="icon"><i class="bi bi-camera-fill"></i></div>
            <span>EventSnap</span>
        </a>

        <div class="left-hero">
            <p class="tagline">Get started<br>in <em>under</em><br>3 minutes.</p>
            <p class="sub">Create your free host account and have your first event gallery ready for guests before you even send the invitations.</p>

            <div class="steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-text">
                        <strong>Create your account</strong>
                        <span>Fill in your name, email and a secure password.</span>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-text">
                        <strong>Create your event</strong>
                        <span>Set up a gallery, add a banner and generate a QR code.</span>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        <strong>Share &amp; collect</strong>
                        <span>Guests scan the QR — photos arrive in real time.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="left-footer-note">
            <p>Already hosting events? <a href="login.php" class="link">Sign in here →</a></p>
            <p style="margin-top:6px;">No credit card required. Free forever on the starter plan.</p>
        </div>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-right">
        <div class="form-box">

            <?php if (!empty($success)): ?>
                <div class="success-card">
                    <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                    <h3>Account Created! 🎉</h3>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
                <a href="login.php" class="btn-auth-outline">
                    <i class="bi bi-box-arrow-in-right"></i> Proceed to Login
                </a>
            <?php else: ?>

                <div class="form-eyebrow"><i class="bi bi-person-plus-fill"></i> Free Account</div>
                <h1>Start snapping free ✨</h1>
                <p class="form-subtitle">Join thousands of event hosts — set up your gallery in minutes.</p>

                <?php if (!empty($error)): ?>
                    <div class="auth-alert danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" id="registerForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="field-group">
                        <label class="field-label" for="name">Full Name</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" id="name" name="name" required
                                   placeholder="e.g. Jane Okafor"
                                   value="<?php echo htmlspecialchars($name); ?>">
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="email">Email Address</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" id="email" name="email" required
                                   placeholder="jane@example.com"
                                   value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="password">Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" id="password" name="password" required
                                   placeholder="Min 6 characters" oninput="checkStrength(this.value)">
                            <button type="button" class="toggle-pw" data-target="password" aria-label="Toggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="pw-strength" id="pwStrength">
                            <div class="bar"></div><div class="bar"></div>
                            <div class="bar"></div><div class="bar"></div>
                        </div>
                        <p class="pw-hint" id="pwHint">Use at least 6 characters</p>
                    </div>

                    <div class="terms-row">
                        <input type="checkbox" id="terms" required checked>
                        <label for="terms">I agree to the <a href="#">Terms of Service</a> &amp; <a href="#">Privacy Policy</a></label>
                    </div>

                    <button type="submit" class="btn-auth" id="registerBtn">
                        <i class="bi bi-person-check-fill"></i>
                        Create Free Account
                    </button>
                </form>

                <div class="divider">already have an account?</div>
                <p class="form-footer"><a href="login.php">← Sign in instead</a></p>

            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-pw').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                const icon  = btn.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'bi bi-eye-slash';
                } else {
                    input.type = 'password';
                    icon.className = 'bi bi-eye';
                }
            });
        });

        // Password strength
        function checkStrength(val) {
            const el   = document.getElementById('pwStrength');
            const hint = document.getElementById('pwHint');
            el.className = 'pw-strength';
            if (!val) { hint.textContent = 'Use at least 6 characters'; return; }
            let score = 0;
            if (val.length >= 6)  score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const labels = ['', 'weak', 'fair', 'good', 'strong'];
            const hints  = ['', 'Too short — keep going', 'Getting there…', 'Good password!', 'Strong password 💪'];
            el.classList.add(labels[score] || 'weak');
            hint.textContent = hints[score] || hints[1];
        }

        // Submit state
        document.getElementById('registerForm').addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            btn.innerHTML = 'Creating Account…';
            btn.disabled = true;
        });
    </script>
</body>
</html>
