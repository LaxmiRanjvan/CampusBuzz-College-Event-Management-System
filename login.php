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
            $stmt = mysqli_prepare($conn, "SELECT id, username, email, role, full_name, password FROM users WHERE username = ?");
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
    <title>Login - Campus Event Manager</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Inline validation styles (non-layout) ── */
        .field-error { color: #c53030; font-size: 13px; margin-top: 4px; display: none; }
        input.input-invalid { border-color: #f56565 !important; }
        input.input-valid   { border-color: #48bb78 !important; }
        .attempts-warning   { color: #c05621; font-size: 13px; margin-top: 6px; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h2 style="text-align: center; font-size: 1.6rem; font-weight: 800; letter-spacing: 0.5px; margin: 0; line-height: 1;">
                <br><span style="color: #ffffff;">CAMPUS</span><span style="color: #667eea ;">BUZZ</span><br>
            </h2>
            <h2>Welcome Back!</h2>

            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if(isset($_GET['registered'])): ?>
                <div class="alert alert-success">Registration successful! Please login.</div>
            <?php endif; ?>

            <?php if($is_locked_out): ?>
                <div class="alert alert-error">
                    Account temporarily locked. Please wait <strong><?php echo $seconds_left; ?></strong> seconds.
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="loginUsername"
                           placeholder="Enter your username" required autocomplete="username" autofocus
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                    <span class="field-error" id="err-username">Please enter your username.</span>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="loginPassword"
                           placeholder="Enter your password" required autocomplete="current-password"
                           <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                    <span class="field-error" id="err-password">Please enter your password.</span>
                </div>

                <?php if($_SESSION['login_attempts'] >= 3 && !$is_locked_out): ?>
                    <p class="attempts-warning">
                        ⚠️ <?php echo 5 - $_SESSION['login_attempts']; ?> attempt(s) remaining before lockout.
                    </p>
                <?php endif; ?>

                <button type="submit" name="login" class="btn btn-primary"
                        <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                    Login
                </button>
            </form>
        </div>
    </div>

    <script>
    /* ── Frontend validation for login form ── */
    (function() {
        const form     = document.getElementById('loginForm');
        const uField   = document.getElementById('loginUsername');
        const pField   = document.getElementById('loginPassword');

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

        // Real-time validation
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

        // Pre-submit check
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