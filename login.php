<?php
/**
 * EventSnap Cloud - User Authentication Login Portal
 * CRITICAL FIX: All redirects MUST happen before any HTML is output.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';

// Redirect already-logged-in users before ANY HTML is sent
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
$email   = '';

// Process POST submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = $_POST['email']      ?? '';
    $password   = $_POST['password']   ?? '';
    $csrfToken  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        $result = Auth::login($email, $password);
        if ($result['success']) {
            $role = $_SESSION['user_role'] ?? 'owner';
            if ($role === 'admin') {
                header("Location: admin.php");
            } elseif ($role === 'crew') {
                header("Location: media-crew.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
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
    <title>Login | EventSnap – Capture Every Moment</title>
    <meta name="description" content="Log in to your EventSnap account to manage events, view guest galleries, and more.">
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
            font-size: 1.3rem;
            color: #fff;
            backdrop-filter: blur(8px);
        }
        .brand-logo span {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
            color: #fff;
        }

        .left-hero {
            z-index: 1;
        }
        .left-hero .tagline {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            line-height: 1.18;
            color: #fff;
            margin-bottom: 16px;
        }
        .left-hero .tagline em {
            font-style: normal;
            color: #C4B5FD;
        }
        .left-hero .sub {
            font-size: 0.95rem;
            color: rgba(255,255,255,0.72);
            line-height: 1.6;
            max-width: 320px;
            margin-bottom: 36px;
        }

        .feature-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 50px;
            padding: 8px 16px;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 500;
            margin-bottom: 10px;
            backdrop-filter: blur(6px);
        }
        .feature-pill i { font-size: 0.85rem; color: #C4B5FD; }
        .features-list { display: flex; flex-direction: column; gap: 6px; }

        .left-footer-note {
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            border-top: 1px solid rgba(255,255,255,0.15);
            padding-top: 24px;
        }
        .avatar-stack { display: flex; }
        .avatar-stack .av {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C4B5FD, #7C3AED);
            border: 2px solid #6D28D9;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 700; color: #fff;
            margin-right: -8px;
        }
        .left-footer-note .note {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.72);
            margin-left: 16px;
        }
        .left-footer-note .note strong { color: #fff; }

        /* Floating decorative blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            z-index: 0;
        }

        /* ── RIGHT PANEL ── */
        .auth-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
            background: var(--white);
        }

        .form-box {
            width: 100%;
            max-width: 420px;
        }

        .form-box .form-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--brand-light);
            color: var(--brand);
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid var(--brand-mid);
        }

        .form-box h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            color: var(--text-main);
            margin-bottom: 6px;
            line-height: 1.2;
        }
        .form-box .form-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 32px;
            line-height: 1.55;
        }

        /* Alert */
        .auth-alert {
            border-radius: var(--radius);
            padding: 12px 16px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            font-weight: 500;
        }
        .auth-alert.danger  { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .auth-alert.success { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }

        /* Form fields */
        .field-group { margin-bottom: 20px; }
        .field-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 7px;
        }
        .field-label label {
            font-size: 0.83rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .field-label a {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--brand);
            text-decoration: none;
        }
        .field-label a:hover { text-decoration: underline; }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 0.95rem;
            pointer-events: none;
        }
        .input-wrap input {
            width: 100%;
            padding: 12px 44px 12px 40px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            background: var(--white);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-wrap input::placeholder { color: #B0BCCC; }
        .input-wrap input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
        }
        .toggle-pw {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0;
            font-size: 0.95rem;
            line-height: 1;
        }
        .toggle-pw:hover { color: var(--brand); }

        /* Submit button */
        .btn-auth {
            width: 100%;
            padding: 13px;
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(124,58,237,0.3);
            margin-top: 8px;
        }
        .btn-auth:hover {
            background: var(--brand-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(124,58,237,0.38);
        }
        .btn-auth:active { transform: translateY(0); }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 26px 0;
            color: var(--border);
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .form-footer {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .form-footer a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
        }
        .form-footer a:hover { text-decoration: underline; }

        /* Mobile */
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
            <p class="tagline">Every moment,<br><em>captured &amp; shared</em><br>instantly.</p>
            <p class="sub">The all-in-one platform where guests upload memories and hosts curate breathtaking event galleries — no app download needed.</p>

            <div class="features-list">
                <div class="feature-pill"><i class="bi bi-qr-code-scan"></i> Instant QR guest upload</div>
                <div class="feature-pill"><i class="bi bi-images"></i> Auto-organised gallery</div>
                <div class="feature-pill"><i class="bi bi-shield-check"></i> Privacy-first, secure hosting</div>
            </div>
        </div>

        <div class="left-footer-note">
            <div class="avatar-stack">
                <div class="av">A</div>
                <div class="av">B</div>
                <div class="av">C</div>
                <div class="av">D</div>
            </div>
            <p class="note"><strong>2,400+ event hosts</strong> trust EventSnap to preserve their best days.</p>
        </div>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-right">
        <div class="form-box">

            <div class="form-eyebrow"><i class="bi bi-shield-lock-fill"></i> Secure Login</div>
            <h1>Welcome back 👋</h1>
            <p class="form-subtitle">Enter your credentials below to access your EventSnap cockpit.</p>

            <?php if (!empty($error)): ?>
                <div class="auth-alert danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="field-group">
                    <div class="field-label">
                        <label for="email">Email Address</label>
                    </div>
                    <div class="input-wrap">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" id="email" name="email" required
                               placeholder="john@example.com"
                               value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">
                        <label for="password">Password</label>
                        <a href="forgot-password.php">Forgot password?</a>
                    </div>
                    <div class="input-wrap">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" id="password" name="password" required placeholder="••••••••">
                        <button type="button" class="toggle-pw" data-target="password" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-auth" id="loginBtn">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Sign In to EventSnap
                </button>
            </form>

            <div class="divider">or</div>

            <p class="form-footer">
                Don't have an account? <a href="register.php">Create one free →</a>
            </p>

        </div>
    </div>

    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-pw').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.dataset.target;
                const input = document.getElementById(targetId);
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

        // Submit button loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<span class="spinner"></span> Signing In…';
            btn.disabled = true;
        });
    </script>
</body>
</html>
