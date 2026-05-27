<?php
/**
 * EventSnap Cloud - Password Recovery Portal
 * FIXED: All DB logic and redirects happen BEFORE any HTML is output.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';

$error         = '';
$success       = '';
$simulatedLink = '';
$tokenValid    = false;
$userId        = 0;
$email         = '';

// ── Handle password reset via token in URL ─────────────────────────────────
$token = trim($_GET['token'] ?? '');

if (!empty($token)) {
    $db   = getDBConnection();
    $stmt = $db->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user && strtotime($user['reset_expires']) > time()) {
        $tokenValid = true;
        $userId     = $user['id'];
    } else {
        $error = "The password recovery token is invalid or has expired.";
    }
}

// ── Handle new-password submission (token flow) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_action'])) {
    $newPass     = $_POST['new_password']    ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $uId         = (int)($_POST['user_id']   ?? 0);
    $csrfToken   = $_POST['csrf_token']      ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh and try again.";
    } elseif (strlen($newPass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($newPass !== $confirmPass) {
        $error = "Passwords do not match.";
    } elseif ($uId <= 0) {
        $error = "Invalid request context.";
    } else {
        $db = getDBConnection();
        try {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt   = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $stmt->execute([$hashed, $uId]);
            $success    = "Your password has been successfully updated! You can now log in.";
            $tokenValid = false;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// ── Handle recovery-link request (email flow) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reset_password_action'])) {
    $email     = trim(strtolower($_POST['email'] ?? ''));
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh and try again.";
    } elseif (empty($email)) {
        $error = "Email address is required.";
    } else {
        $db = getDBConnection();
        try {
            $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $updateStmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $updateStmt->execute([$token, $expires, $user['id']]);

                $resetLink     = BASE_URL . "forgot-password.php?token=" . $token;
                $success       = "Recovery link generated! Since this is running locally, the email was simulated.";
                $simulatedLink = $resetLink;
            } else {
                $success = "If that email matches our records, a recovery link has been dispatched to it.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tokenValid ? 'Reset Password' : 'Forgot Password'; ?> | EventSnap</title>
    <meta name="description" content="Recover access to your EventSnap account by resetting your password.">
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
            position: absolute; top: -80px; right: -80px;
            width: 400px; height: 400px; border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .auth-left::after {
            content: '';
            position: absolute; bottom: -120px; left: -60px;
            width: 500px; height: 500px; border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .blob {
            position: absolute; border-radius: 50%;
            background: rgba(255,255,255,0.07); z-index: 0;
        }

        .brand-logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; z-index: 1;
        }
        .brand-logo .icon {
            width: 44px; height: 44px;
            background: rgba(255,255,255,0.18); border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #fff; backdrop-filter: blur(8px);
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

        /* Security tips */
        .tips { display: flex; flex-direction: column; gap: 14px; }
        .tip {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            backdrop-filter: blur(4px);
        }
        .tip i { font-size: 1rem; color: #C4B5FD; flex-shrink: 0; margin-top: 1px; }
        .tip strong { display: block; color: #fff; font-size: 0.85rem; margin-bottom: 2px; }
        .tip span { color: rgba(255,255,255,0.62); font-size: 0.78rem; line-height: 1.4; }

        .left-footer-note {
            z-index: 1;
            border-top: 1px solid rgba(255,255,255,0.15);
            padding-top: 24px;
        }
        .left-footer-note p { font-size: 0.8rem; color: rgba(255,255,255,0.65); }
        .left-footer-note p strong { color: #fff; }
        .left-footer-note a {
            color: #C4B5FD; text-decoration: none; font-weight: 600;
        }
        .left-footer-note a:hover { text-decoration: underline; }

        /* ── RIGHT PANEL ── */
        .auth-right {
            flex: 1;
            display: flex; align-items: center; justify-content: center;
            padding: 48px 40px;
            background: var(--white);
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
            font-size: 0.85rem; display: flex; align-items: center; gap: 10px;
            margin-bottom: 22px; font-weight: 500;
        }
        .auth-alert.danger  { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .auth-alert.success { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }

        /* Success state card */
        .success-block {
            text-align: center; padding: 8px 0;
        }
        .success-block .icon-circle {
            width: 64px; height: 64px; border-radius: 50%;
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.6rem; color: #fff;
            box-shadow: 0 8px 24px rgba(124,58,237,0.3);
        }
        .success-block h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 1.4rem;
            color: var(--text-main); margin-bottom: 10px;
        }
        .success-block p {
            font-size: 0.88rem; color: var(--text-muted);
            line-height: 1.6; margin-bottom: 24px;
        }
        .simulated-link-box {
            background: #F8F7FF; border: 1px dashed #C4B5FD;
            border-radius: var(--radius); padding: 16px;
            margin-bottom: 20px; text-align: left;
        }
        .simulated-link-box p {
            font-size: 0.78rem; color: var(--text-muted);
            margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
        }
        .simulated-link-box p i { color: var(--brand); }
        .simulated-link-box a {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--brand); color: #fff;
            padding: 9px 16px; border-radius: 10px;
            font-size: 0.84rem; font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }
        .simulated-link-box a:hover { background: var(--brand-hover); }

        /* Form elements */
        .field-group { margin-bottom: 18px; }
        .field-label {
            display: block; font-size: 0.83rem; font-weight: 600;
            color: var(--text-main); margin-bottom: 7px;
        }
        .input-wrap {
            position: relative; display: flex; align-items: center;
        }
        .input-icon {
            position: absolute; left: 14px; color: var(--text-muted);
            font-size: 0.95rem; pointer-events: none;
        }
        .input-wrap input {
            width: 100%; padding: 12px 44px 12px 40px;
            border: 1.5px solid var(--border); border-radius: var(--radius);
            font-size: 0.9rem; font-family: 'Inter', sans-serif;
            color: var(--text-main); background: var(--white);
            outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-wrap input::placeholder { color: #B0BCCC; }
        .input-wrap input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
        }
        .toggle-pw {
            position: absolute; right: 14px;
            background: none; border: none; color: var(--text-muted);
            cursor: pointer; padding: 0; font-size: 0.95rem; line-height: 1;
        }
        .toggle-pw:hover { color: var(--brand); }

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
            margin-bottom: 16px;
        }
        .btn-auth:hover {
            background: var(--brand-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(124,58,237,0.38);
        }
        .btn-auth:active { transform: translateY(0); }
        .btn-auth:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        .btn-ghost {
            width: 100%; padding: 12px;
            background: transparent; color: var(--text-muted);
            border: 1.5px solid var(--border); border-radius: var(--radius);
            font-size: 0.88rem; font-weight: 600;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer; text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-ghost:hover { border-color: var(--brand); color: var(--brand); }

        .form-footer {
            text-align: center; font-size: 0.85rem;
            color: var(--text-muted); margin-top: 20px;
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
            <p class="tagline">Account<br>recovery,<br><em>simplified.</em></p>
            <p class="sub">We take your security seriously. Follow the steps and regain access to your event galleries in moments.</p>

            <div class="tips">
                <div class="tip">
                    <i class="bi bi-envelope-check"></i>
                    <div>
                        <strong>Check your inbox</strong>
                        <span>A recovery link will be sent to your registered email address.</span>
                    </div>
                </div>
                <div class="tip">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <strong>Link expires in 1 hour</strong>
                        <span>For your security, the link is time-limited. Request a new one if needed.</span>
                    </div>
                </div>
                <div class="tip">
                    <i class="bi bi-shield-lock"></i>
                    <div>
                        <strong>Choose a strong password</strong>
                        <span>Mix uppercase letters, numbers, and symbols for the best protection.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="left-footer-note">
            <p>Remembered your password? <a href="login.php">Sign in here →</a></p>
            <p style="margin-top:6px;">Need a new account? <a href="register.php">Register free →</a></p>
        </div>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-right">
        <div class="form-box">

            <?php if ($tokenValid): ?>
                <!-- ── RESET PASSWORD FORM ── -->
                <div class="form-eyebrow"><i class="bi bi-key-fill"></i> Set New Password</div>
                <h1>Reset password 🔐</h1>
                <p class="form-subtitle">Choose a strong new password for your EventSnap account.</p>

                <?php if (!empty($error)): ?>
                    <div class="auth-alert danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="forgot-password.php" method="POST" id="resetForm" novalidate>
                    <input type="hidden" name="csrf_token"           value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id"              value="<?php echo $userId; ?>">
                    <input type="hidden" name="reset_password_action" value="1">

                    <div class="field-group">
                        <label class="field-label" for="new_password">New Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" id="new_password" name="new_password"
                                   required placeholder="Min 6 characters">
                            <button type="button" class="toggle-pw" data-target="new_password" aria-label="Toggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="field-group" style="margin-bottom:24px;">
                        <label class="field-label" for="confirm_password">Confirm New Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill input-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   required placeholder="Retype your password">
                            <button type="button" class="toggle-pw" data-target="confirm_password" aria-label="Toggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth" id="resetBtn">
                        <i class="bi bi-shield-check"></i>
                        Update Password
                    </button>
                </form>

            <?php else: ?>
                <!-- ── REQUEST RECOVERY LINK / SUCCESS STATES ── -->

                <?php if (!empty($success)): ?>
                    <div class="success-block">
                        <div class="icon-circle"><i class="bi bi-envelope-check"></i></div>
                        <h2>Recovery link sent!</h2>
                        <p><?php echo htmlspecialchars($success); ?></p>

                        <?php if (!empty($simulatedLink)): ?>
                            <div class="simulated-link-box">
                                <p><i class="bi bi-terminal-fill"></i> <strong>Local dev only</strong> — simulated dispatch link:</p>
                                <a href="<?php echo htmlspecialchars($simulatedLink); ?>">
                                    <i class="bi bi-link-45deg"></i> Proceed to Reset Password
                                </a>
                            </div>
                        <?php endif; ?>

                        <a href="login.php" class="btn-ghost">
                            <i class="bi bi-box-arrow-in-right"></i> Return to Login
                        </a>
                    </div>

                <?php else: ?>
                    <div class="form-eyebrow"><i class="bi bi-shield-question"></i> Password Recovery</div>
                    <h1>Forgot password? 🔑</h1>
                    <p class="form-subtitle">Enter your registered email address and we'll send you a secure recovery link.</p>

                    <?php if (!empty($error)): ?>
                        <div class="auth-alert danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="forgot-password.php" method="POST" id="recoveryForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="field-group" style="margin-bottom:24px;">
                            <label class="field-label" for="email">Email Address</label>
                            <div class="input-wrap">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" id="email" name="email" required
                                       placeholder="john@example.com"
                                       value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn-auth" id="sendBtn">
                            <i class="bi bi-send"></i>
                            Send Recovery Link
                        </button>
                    </form>

                    <a href="login.php" class="btn-ghost">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>

                    <p class="form-footer" style="margin-top:16px;">
                        No account yet? <a href="register.php">Create one free →</a>
                    </p>
                <?php endif; ?>

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

        // Submit loading states
        const forms = {
            recoveryForm: 'sendBtn',
            resetForm:    'resetBtn',
        };
        Object.entries(forms).forEach(([formId, btnId]) => {
            const form = document.getElementById(formId);
            const btn  = document.getElementById(btnId);
            if (form && btn) {
                form.addEventListener('submit', () => {
                    btn.innerHTML = 'Please wait…';
                    btn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>
