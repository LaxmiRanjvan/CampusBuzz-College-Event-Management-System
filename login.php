<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: common/home.php");
    exit();
}

$error = "";

// ─── RATE LIMITING: max 5 login attempts per 10 minutes ───────────────────────
if(!isset($_SESSION['login_attempts']))   $_SESSION['login_attempts']   = 0;
if(!isset($_SESSION['login_lockout_until'])) $_SESSION['login_lockout_until'] = 0;

$is_locked_out = (time() < $_SESSION['login_lockout_until']);
$seconds_left  = max(0, $_SESSION['login_lockout_until'] - time());

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {

    // Lockout check
    if($is_locked_out) {
        $error = "Too many failed attempts. Please wait {$seconds_left} seconds before trying again.";
    } else {

        // ── Backend: basic presence validation ─────────────────────────────────
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if(empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {

            // ── SECURITY FIX: Use prepared statement (prevents SQL injection) ──
            $stmt = mysqli_prepare($conn, "SELECT id, username, email, role, full_name, password FROM users WHERE BINARY username = ?");
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if(mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                $password_valid = false;

                // Support both legacy MD5 hashes and modern bcrypt
                if(substr($user['password'], 0, 4) === '$2y$') {
                    $password_valid = password_verify($password, $user['password']);
                } else {
                    // Legacy MD5 — verify and auto-upgrade to bcrypt
                    if($user['password'] === md5($password)) {
                        $password_valid = true;
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                        mysqli_stmt_bind_param($upd, "si", $new_hash, $user['id']);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                    }
                }

                mysqli_stmt_close($stmt);

                if($password_valid) {
                    // Reset attempts on success
                    $_SESSION['login_attempts']    = 0;
                    $_SESSION['login_lockout_until'] = 0;

                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];

                    header("Location: common/home.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                mysqli_stmt_close($stmt);
                $error = "Invalid username or password.";
            }

            // Track failed attempts
            if(!empty($error)) {
                $_SESSION['login_attempts']++;
                if($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_lockout_until'] = time() + 600; // 10-minute lockout
                    $error = "Too many failed attempts. You are locked out for 10 minutes.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – CampusBuzz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Sora:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body.auth-page {
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            font-family: 'DM Sans', sans-serif;
            background: #0f0e17;
            overflow: hidden;
        }

        /* ── Left decorative panel ── */
        .auth-panel-left {
            flex: 0 0 30%;
            background: linear-gradient(145deg, #1a1333 0%, #2d1b69 50%, #3d2080 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
            position: relative;
            overflow: hidden;
        }

        /* Animated orbs */
        .auth-panel-left::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(102,126,234,0.35) 0%, transparent 70%);
            top: -100px; left: -100px;
            border-radius: 50%;
            animation: drift 8s ease-in-out infinite alternate;
        }
        .auth-panel-left::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(118,75,162,0.3) 0%, transparent 70%);
            bottom: -80px; right: -80px;
            border-radius: 50%;
            animation: drift 10s ease-in-out infinite alternate-reverse;
        }
        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, 30px) scale(1.08); }
        }

        /* Grid lines overlay */
        .panel-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(102,126,234,0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(102,126,234,0.07) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        .panel-content { position: relative; z-index: 2; text-align: center; }

        /* ── THE LOGO — untouched per requirement ── */
        .logo-display {
            font-family: 'Sora', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: 1px;
            line-height: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            -webkit-text-stroke: 1px black;
            margin-bottom: 28px;
        }
        .logo-display .campus { color: #ffffff; }
        .logo-display .buzz   { color: #667eea; }

        .panel-tagline {
            color: rgba(255,255,255,0.65);
            font-size: 1rem;
            letter-spacing: 0.3px;
            line-height: 1.7;
            max-width: 300px;
        }

        /* Feature pills */
        .panel-features {
            margin-top: 48px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            width: 100%;
            max-width: 300px;
        }
        .feature-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 12px 18px;
            color: rgba(255,255,255,0.8);
            font-size: 0.88rem;
            backdrop-filter: blur(8px);
        }
        .feature-pill span.icon { font-size: 1.15rem; }

        /* ── Right form panel ── */
        .auth-panel-right {
            flex: 0 0 70%;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            position: relative;
        }

        .auth-form-wrap { width: 100%; max-width: 480px; margin: 0 auto; }

        .form-heading {
            font-family: 'Sora', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1333;
            margin-bottom: 6px;
        }
        .form-subheading {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 36px;
        }

        /* ── Alerts ── */
        .cb-alert {
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.875rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.5;
        }
        .cb-alert-error {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #c53030;
        }
        .cb-alert-success {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #276749;
        }
        .cb-alert-icon { flex-shrink: 0; margin-top: 1px; }

        /* ── Input groups ── */
        .cb-field { margin-bottom: 20px; }
        .cb-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 7px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .cb-input-wrap { position: relative; }
        .cb-input-wrap .input-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 1rem;
            pointer-events: none;
            transition: color 0.2s;
        }
        .cb-input {
            width: 100%;
            padding: 13px 14px 13px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            color: #1a1333;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .cb-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .cb-input:focus + .input-icon,
        .cb-input-wrap:focus-within .input-icon { color: #667eea; }
        .cb-input.input-invalid { border-color: #f56565 !important; box-shadow: 0 0 0 3px rgba(245,101,101,0.12) !important; }
        .cb-input.input-valid   { border-color: #48bb78 !important; }
        .cb-input:disabled      { background: #f7fafc; cursor: not-allowed; opacity: 0.7; }

        /* Password toggle */
        .pw-toggle {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; color: #a0aec0;
            font-size: 1.1rem; padding: 0;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: #667eea; }

        .field-error {
            color: #c53030;
            font-size: 0.78rem;
            margin-top: 5px;
            display: none;
        }

        /* Attempts warning */
        .attempts-warning {
            background: #fffbeb;
            border: 1px solid #f6e05e;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.83rem;
            color: #b7791f;
            margin-bottom: 16px;
        }

        /* ── Submit button ── */
        .cb-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
            margin-top: 4px;
        }
        .cb-btn:hover:not(:disabled) {
            opacity: 0.93;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.5);
        }
        .cb-btn:active:not(:disabled) { transform: translateY(0); }
        .cb-btn:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }

        /* ── Forgot password / contact admin notice ── */
        .admin-notice {
            margin-top: 28px;
            padding: 16px 18px;
            background: linear-gradient(135deg, rgba(102,126,234,0.06) 0%, rgba(118,75,162,0.06) 100%);
            border: 1px solid rgba(102,126,234,0.2);
            border-radius: 12px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .admin-notice-icon {
            font-size: 1.3rem;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .admin-notice-text { font-size: 0.84rem; color: #4a5568; line-height: 1.6; }
        .admin-notice-text strong { color: #1a1333; }

        /* ── Responsive: stack on small screens ── */
        @media (max-width: 820px) {
            .auth-panel-left { display: none; }
            .auth-panel-right { width: 100%; padding: 48px 32px; }
        }
        @media (max-width: 420px) {
            .auth-panel-right { padding: 40px 24px; }
        }
    </style>
</head>
<body class="auth-page">

    <!-- Left branding panel -->
    <div class="auth-panel-left">
        <div class="panel-grid"></div>
        <div class="panel-content">
            <div class="logo-display">
                <span class="campus">CAMPUS</span><span class="buzz">BUZZ</span>
            </div>
            <p class="panel-tagline">Your campus. Your events. All in one place.</p>
            <div class="panel-features">
                <div class="feature-pill"><span class="icon">📅</span> Discover campus events instantly</div>
                <div class="feature-pill"><span class="icon">🎫</span> Register &amp; track in one click</div>
                <div class="feature-pill"><span class="icon">🔔</span> Never miss what matters to you</div>
            </div>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-panel-right">
        <div class="auth-form-wrap">

            <p class="form-heading">Welcome back 👋</p>
            <p class="form-subheading">Sign in to your CampusBuzz account</p>

            <?php if($error): ?>
                <div class="cb-alert cb-alert-error">
                    <span class="cb-alert-icon">⚠️</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['registered'])): ?>
                <div class="cb-alert cb-alert-success">
                    <span class="cb-alert-icon">✅</span>
                    <span>Registration successful! Please sign in below.</span>
                </div>
            <?php endif; ?>

            <?php if($is_locked_out): ?>
                <div class="cb-alert cb-alert-error">
                    <span class="cb-alert-icon">🔒</span>
                    <span>Account temporarily locked. Please wait <strong><?php echo $seconds_left; ?></strong> seconds before trying again.</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>

                <!-- Username -->
                <div class="cb-field">
                    <label class="cb-label" for="loginUsername">Username</label>
                    <div class="cb-input-wrap">
                        <input
                            type="text" name="username" id="loginUsername"
                            class="cb-input"
                            placeholder="Enter your username"
                            required autocomplete="username" autofocus
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                        <span class="input-icon">👤</span>
                    </div>
                    <span class="field-error" id="err-username">Please enter your username.</span>
                </div>

                <!-- Password -->
                <div class="cb-field">
                    <label class="cb-label" for="loginPassword">Password</label>
                    <div class="cb-input-wrap">
                        <input
                            type="password" name="password" id="loginPassword"
                            class="cb-input"
                            placeholder="Enter your password"
                            required autocomplete="current-password"
                            <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                        <span class="input-icon">🔑</span>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">👁️</button>
                    </div>
                    <span class="field-error" id="err-password">Please enter your password.</span>
                </div>

                <?php if($_SESSION['login_attempts'] >= 3 && !$is_locked_out): ?>
                    <div class="attempts-warning">
                        ⚠️ <?php echo 5 - $_SESSION['login_attempts']; ?> attempt(s) remaining before your account is locked for 10 minutes.
                    </div>
                <?php endif; ?>

                <button type="submit" name="login" class="cb-btn"
                        <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                    Sign In
                </button>

            </form>

            <!-- Forgot password notice -->
            <div class="admin-notice">
                <span class="admin-notice-icon">🔒</span>
                <div class="admin-notice-text">
                    <strong>Forgot your password?</strong><br>
                    Accounts are managed by your campus administrator. If you've forgotten your password or are unable to log in, please <strong>contact your administrator</strong> to have it reset. You cannot change your password or reset it yourself.
                </div>
            </div>

        </div>
    </div>

    <script>
    /* ── Frontend validation + password toggle ── */
    (function() {
        const form   = document.getElementById('loginForm');
        const uField = document.getElementById('loginUsername');
        const pField = document.getElementById('loginPassword');
        const toggle = document.getElementById('pwToggle');

        // Password show/hide
        toggle.addEventListener('click', function() {
            const showing = pField.type === 'text';
            pField.type = showing ? 'password' : 'text';
            this.textContent = showing ? '👁️' : '🙈';
        });

        function showError(field, errId, msg) {
            field.classList.add('input-invalid');
            field.classList.remove('input-valid');
            const el = document.getElementById(errId);
            el.textContent = msg;
            el.style.display = 'block';
        }
        function clearError(field, errId) {
            field.classList.remove('input-invalid');
            field.classList.add('input-valid');
            document.getElementById(errId).style.display = 'none';
        }

        uField.addEventListener('input', function() {
            this.value.trim().length > 0
                ? clearError(this, 'err-username')
                : showError(this, 'err-username', 'Please enter your username.');
        });

        pField.addEventListener('input', function() {
            this.value.length > 0
                ? clearError(this, 'err-password')
                : showError(this, 'err-password', 'Please enter your password.');
        });

        form.addEventListener('submit', function(e) {
            let valid = true;
            if(!uField.value.trim()) { showError(uField, 'err-username', 'Please enter your username.'); valid = false; }
            if(!pField.value)        { showError(pField, 'err-password', 'Please enter your password.'); valid = false; }
            if(!valid) e.preventDefault();
        });
    })();
    </script>
</body>
</html>