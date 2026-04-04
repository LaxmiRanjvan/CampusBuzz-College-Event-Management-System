<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$error   = "";
$success = "";
$show_email_form = false;
$new_user_data   = null;

// ─── Allowed values for server-side whitelist checks ──────────────────────────
$allowed_roles = ['student', 'organizer'];
$allowed_depts = [
    'Computer Science','Information Technology','Electronics',
    'Mechanical','Civil','Electrical','Business Administration','Other'
];
$allowed_years = ['First Year','Second Year','Third Year','Fourth Year','Graduate'];

// ─── Common weak passwords block-list ─────────────────────────────────────────
$weak_passwords = ['password','123456','12345678','admin123','password1',
                   'qwerty123','letmein','welcome1','campus123'];

// ─── Reusable backend validation helper ───────────────────────────────────────
function validateUserFields($username, $email, $password, $first_name, $last_name, $role,
                            $department, $year, $phone,
                            $allowed_roles, $allowed_depts, $allowed_years, $weak_passwords) {
    // First name: letters only, min 2 chars
    if(!preg_match('/^[a-zA-Z]{2,}$/', $first_name)) {
        return "First name must contain only letters (min 2 characters, no spaces).";
    }
    // Last name: letters only, min 2 chars
    if(!preg_match('/^[a-zA-Z]{2,}$/', $last_name)) {
        return "Last name must contain only letters (min 2 characters, no spaces).";
    }
    // Username: letters, numbers, underscore; min 5 chars; no spaces
    if(!preg_match('/^[a-zA-Z0-9_]{5,}$/', $username)) {
        return "Username must be at least 5 characters and contain only letters, numbers, or underscore.";
    }
    // Email: RFC-style validation
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address (e.g. name@domain.com).";
    }
    // Phone: exactly 10 digits (required)
    if(!preg_match('/^[0-9]{10}$/', $phone)) {
        return "Phone number is required and must be exactly 10 digits.";
    }
    // Password strength
    if(mb_strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if(!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    if(!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    if(!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    if(!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $password)) {
        return "Password must contain at least one special character (!@#\$%^&* etc.).";
    }
    if(in_array(strtolower($password), $weak_passwords)) {
        return "That password is too common. Please choose a stronger one.";
    }
    // Role whitelist
    if(!in_array($role, $allowed_roles)) {
        return "Invalid role selected.";
    }
    // Department whitelist
    if(!in_array($department, $allowed_depts)) {
        return "Please select a valid department.";
    }
    // Year required for students
    if($role === 'student' && !in_array($year, $allowed_years)) {
        return "Please select a valid year for students.";
    }
    return null; // no error
}

// ─── Create User ──────────────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {

    // Sanitize all text inputs (strip tags + trim)
    $username   = trim(strip_tags($_POST['username']    ?? ''));
    $email      = trim(strip_tags($_POST['email']       ?? ''));
    $password   = $_POST['password'] ?? '';        // plain text kept for strength check
    $role       = trim(strip_tags($_POST['role']        ?? ''));
    $first_name = trim(strip_tags($_POST['first_name']  ?? ''));
    $last_name  = trim(strip_tags($_POST['last_name']   ?? ''));
    $full_name  = $first_name . ' ' . $last_name;  // combined for DB storage
    $department = trim(strip_tags($_POST['department']  ?? ''));
    $year       = trim(strip_tags($_POST['year']        ?? ''));
    $phone      = preg_replace('/\D/', '', trim($_POST['phone'] ?? '')); // digits only

    // Required field presence check
    if(empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($role) || empty($department) || empty($phone)) {
        $error = "Please fill all required fields.";
    } else {
        // Run field-level validation
        $val_error = validateUserFields($username, $email, $password, $first_name, $last_name, $role,
                                        $department, $year, $phone,
                                        $allowed_roles, $allowed_depts, $allowed_years, $weak_passwords);
        if($val_error) {
            $error = $val_error;
        } else {
            // ── Check for duplicate username/email (prepared statement) ────────
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
            mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if(mysqli_stmt_num_rows($check_stmt) > 0) {
                $error = "Username or email already exists. Please choose different ones.";
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                // Hash password with bcrypt
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $created_by      = $_SESSION['user_id'];
                $year_val        = ($role === 'student') ? $year : '';

                // ── INSERT with prepared statement (prevents SQL injection) ────
                $ins = mysqli_prepare($conn,
                    "INSERT INTO users (username, email, password, role, full_name, department, year, phone, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($ins, "ssssssssi",
                    $username, $email, $hashed_password, $role,
                    $full_name, $department, $year_val, $phone, $created_by
                );

                if(mysqli_stmt_execute($ins)) {
                    $new_user_id = mysqli_insert_id($conn);
                    $success     = "✅ User created successfully!";
                    $new_user_data = [
                        'id'        => $new_user_id,
                        'username'  => $username,
                        'password'  => $password,   // plain text for email only
                        'email'     => $email,
                        'role'      => $role,
                        'full_name' => $full_name,
                    ];
                    $show_email_form = true;
                } else {
                    $error = "An error occurred while creating the user. Please try again.";
                }
                mysqli_stmt_close($ins);
            }
        }
    }
}

// ─── Send credentials email (unchanged logic, only safe values used) ──────────
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_credentials_email'])) {
    $recipient_id   = intval($_POST['user_id'] ?? 0);
    $plain_password = htmlspecialchars(strip_tags($_POST['plain_password'] ?? ''));
    $custom_message = trim(strip_tags($_POST['custom_message'] ?? ''));

    $user_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($user_stmt, "i", $recipient_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);

    if(mysqli_num_rows($user_result) > 0) {
        $recipient = mysqli_fetch_assoc($user_result);
        $to        = $recipient['email'];
        $to_name   = $recipient['full_name'];
        $subject   = "Your Campus Event Manager Account Credentials";

        $credentials_html = "
        <div class='info-box'>
            <div class='info-item'><div class='info-label'>Username</div><div class='info-value'>" . htmlspecialchars($recipient['username']) . "</div></div>
            <div class='info-item'><div class='info-label'>Email</div><div class='info-value'>" . htmlspecialchars($recipient['email']) . "</div></div>
            <div class='info-item'><div class='info-label'>Account Type</div><div class='info-value'>" . ucfirst($recipient['role']) . "</div></div>
            <div class='info-item' style='margin-top:20px;padding-top:20px;border-top:2px solid #e2e8f0;'>
                <div class='info-label'>Password</div>
                <div class='info-value' style='background:#fff3cd;padding:12px;border-radius:6px;color:#856404;font-family:monospace;font-size:18px;letter-spacing:1px;'>" . htmlspecialchars($plain_password) . "</div>
                <p style='color:#c53030;font-size:14px;margin-top:10px;'><strong>⚠️ Important:</strong> Please change this password immediately after logging in!</p>
            </div>
        </div>";

        $custom_msg_html = "";
        if(!empty($custom_message)) {
            $custom_msg_html = "
            <div style='background:#f7fafc;border-left:4px solid #667eea;padding:20px;margin:25px 0;border-radius:6px;'>
                <strong style='color:#2d3748;font-size:15px;'>📝 Message from Admin:</strong>
                <p style='color:#4a5568;margin:10px 0 0 0;line-height:1.7;'>" . nl2br(htmlspecialchars($custom_message)) . "</p>
            </div>";
        }

        $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
                     '://' . $_SERVER['HTTP_HOST'] .
                     str_replace('/admin/create_user.php', '/login.php', $_SERVER['PHP_SELF']);

        $content = "
        <p style='font-size:16px;color:#2d3748;margin-bottom:20px;'>Hello <strong>" . htmlspecialchars($recipient['full_name']) . "</strong>,</p>
        <p style='color:#4a5568;line-height:1.8;'>Your account has been successfully created on the Campus Event Manager platform. Below are your login credentials:</p>
        {$credentials_html}{$custom_msg_html}
        <div style='text-align:center;margin:30px 0;'><a href='{$login_url}' class='button'>🔐 Login to Your Account</a></div>
        <div style='background:#e6f7ff;border-left:4px solid #1890ff;padding:15px;border-radius:6px;margin-top:25px;'>
            <p style='margin:0;color:#0050b3;font-size:14px;'><strong>💡 Getting Started:</strong><br>
            " . ($recipient['role'] == 'student' ?
                "Browse upcoming events, register for activities, and save your favorites!" :
                "Create and manage events, track registrations, and engage with students!") . "
            </p>
        </div>";

        $html_body = getEmailTemplate("Welcome to Campus Event Manager!", $content);

        if(sendEmail($to, $to_name, $subject, $html_body)) {
            $success = "✅ User created and credentials sent successfully to " . htmlspecialchars($recipient['email']) . "!";
            logEmail($_SESSION['user_id'], $to, $subject);
            $show_email_form = false;
        } else {
            $error = "❌ User created but failed to send email. You can resend from the user management page.";
        }
    }
    mysqli_stmt_close($user_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Validation feedback styles (non-layout, appended) ── */
        .field-error   { color: #c53030; font-size: 13px; margin-top: 4px; display: none; }
        input.input-invalid, select.input-invalid { border-color: #f56565 !important; }
        input.input-valid,   select.input-valid   { border-color: #48bb78 !important; }

        /* Password strength bar */
        .strength-bar-wrap { height: 6px; background: #e2e8f0; border-radius: 4px; margin-top: 6px; }
        .strength-bar      { height: 100%; border-radius: 4px; transition: width .3s, background .3s; width: 0; }
        .strength-label    { font-size: 12px; margin-top: 4px; font-weight: 600; }

        /* Email section (unchanged from original) */
        .email-section { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:30px; border-radius:10px; color:white; margin-top:25px; animation:slideIn .3s ease-out; }
        @keyframes slideIn { from{opacity:0;transform:translateY(-20px)} to{opacity:1;transform:translateY(0)} }
        .credential-preview { background:white; color:#2d3748; padding:20px; border-radius:8px; margin:20px 0; }
        .credential-item    { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e2e8f0; }
        .credential-item:last-child { border-bottom:none; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1>➕ Create New User</h1>
                <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if($success && !$show_email_form): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <div style="margin-top:15px;display:flex;gap:10px;">
                        <a href="create_user.php" class="btn btn-primary btn-sm">➕ Create Another User</a>
                        <a href="manage_users.php" class="btn btn-secondary btn-sm">👥 View All Users</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(!$show_email_form): ?>
            <!-- ── User Creation Form ── -->
            <div style="background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="" id="createUserForm" novalidate>

                    <!-- Role -->
                    <div class="form-group">
                        <label>User Type *</label>
                        <select name="role" id="roleSelect" required onchange="toggleYearField()">
                            <option value="">Select Role</option>
                            <option value="student"   <?php echo (isset($_POST['role']) && $_POST['role']=='student')   ? 'selected' : ''; ?>>Student</option>
                            <option value="organizer" <?php echo (isset($_POST['role']) && $_POST['role']=='organizer') ? 'selected' : ''; ?>>Organizer</option>
                        </select>
                        <span class="field-error" id="err-role">Please select a role.</span>
                    </div>

                    <!-- First Name + Last Name -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" id="firstName" placeholder="Enter first name" required
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            <span class="field-error" id="err-firstname">Letters only, min 2 characters.</span>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" id="lastName" placeholder="Enter last name" required
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            <span class="field-error" id="err-lastname">Letters only, min 2 characters.</span>
                        </div>
                    </div>

                    <!-- Phone Number (required, full row) -->
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" id="phone" placeholder="9876543210" maxlength="10" required
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        <span class="field-error" id="err-phone">Phone number is required and must be exactly 10 digits.</span>
                    </div>

                    <!-- Username (full row) -->
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" id="username" placeholder="Choose username" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <span class="field-error" id="err-username">Min 5 chars; letters, numbers, underscore only.</span>
                    </div>

                    <!-- Email (full row) -->
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="email" placeholder="email@example.com" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <span class="field-error" id="err-email">Enter a valid email (e.g. name@domain.com).</span>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" id="password"
                               placeholder="Min 8 chars, uppercase, lowercase, number, special char" required
                               autocomplete="new-password">
                        <!-- Strength indicator -->
                        <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
                        <div class="strength-label" id="strengthLabel"></div>
                        <span class="field-error" id="err-password">Password does not meet requirements.</span>
                        <small style="color:#718096;">
                            Must have: 8+ characters · 1 uppercase · 1 lowercase · 1 number · 1 special character
                        </small>
                    </div>

                    <!-- Department + Year -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Department *</label>
                            <select name="department" id="department" required>
                                <option value="">Select Department</option>
                                <?php
                                $depts = ['Computer Science','Information Technology','Electronics','Mechanical','Civil','Electrical','Business Administration','Other'];
                                foreach($depts as $d):
                                    $sel = (isset($_POST['department']) && $_POST['department']==$d) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $d; ?>" <?php echo $sel; ?>><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-dept">Please select a department.</span>
                        </div>

                        <div class="form-group" id="yearField" style="display:none;">
                            <label>Year * <span style="color:#f56565;">(Required for Students)</span></label>
                            <select name="year" id="year">
                                <option value="">Select Year</option>
                                <?php
                                $years = ['First Year','Second Year','Third Year','Fourth Year','Graduate'];
                                foreach($years as $y):
                                    $sel = (isset($_POST['year']) && $_POST['year']==$y) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $y; ?>" <?php echo $sel; ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-year">Please select a year.</span>
                        </div>
                    </div>

                    <div style="display:flex;gap:15px;margin-top:30px;">
                        <button type="submit" name="create_user" class="btn btn-primary">✓ Create User</button>
                        <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if($show_email_form && $new_user_data): ?>
            <!-- Email Sending Form (design unchanged) -->
            <div class="email-section">
                <h2 style="margin-top:0;display:flex;align-items:center;gap:10px;"><span>📧</span> Send Login Credentials</h2>
                <p style="opacity:.95;margin-bottom:20px;">User account created successfully! Would you like to send the login credentials via email?</p>

                <div class="credential-preview">
                    <h3 style="margin-top:0;color:#2d3748;">📋 Credentials to be sent:</h3>
                    <div class="credential-item"><strong>Full Name:</strong><span><?php echo htmlspecialchars($new_user_data['full_name']); ?></span></div>
                    <div class="credential-item"><strong>Email:</strong><span><?php echo htmlspecialchars($new_user_data['email']); ?></span></div>
                    <div class="credential-item"><strong>Username:</strong><span><?php echo htmlspecialchars($new_user_data['username']); ?></span></div>
                    <div class="credential-item">
                        <strong>Password:</strong>
                        <span style="font-family:monospace;background:#fff3cd;padding:4px 8px;border-radius:4px;">
                            <?php echo htmlspecialchars($new_user_data['password']); ?>
                        </span>
                    </div>
                    <div class="credential-item">
                        <strong>Role:</strong>
                        <span class="role-badge role-<?php echo $new_user_data['role']; ?>"><?php echo ucfirst($new_user_data['role']); ?></span>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="user_id"        value="<?php echo $new_user_data['id']; ?>">
                    <input type="hidden" name="plain_password" value="<?php echo htmlspecialchars($new_user_data['password']); ?>">
                    <div class="form-group">
                        <label style="color:white;font-weight:600;">Custom Welcome Message (Optional)</label>
                        <textarea name="custom_message" rows="4" placeholder="Add a personalized welcome message..."
                                  style="width:100%;padding:12px;border:none;border-radius:8px;font-family:inherit;font-size:15px;"></textarea>
                    </div>
                    <div style="display:flex;gap:15px;margin-top:20px;">
                        <button type="submit" name="send_credentials_email" class="btn" style="background:white;color:#667eea;font-weight:600;padding:12px 24px;">📧 Send Credentials Now</button>
                        <a href="manage_users.php" class="btn" style="background:rgba(255,255,255,.2);color:white;padding:12px 24px;">Skip for Now</a>
                        <a href="create_user.php" class="btn" style="background:rgba(255,255,255,.2);color:white;padding:12px 24px;">➕ Create Another User</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    /* ══════════════════════════════════════════════════════════
       Real-time validation — create_user.php
    ══════════════════════════════════════════════════════════ */

    // ── Common weak passwords (client-side mirror) ────────────────────────────
    const WEAK_PASSWORDS = ['password','123456','12345678','admin123','password1',
                            'qwerty123','letmein','welcome1','campus123'];

    // ── Helper: show / clear error ────────────────────────────────────────────
    function showErr(field, errId, msg) {
        field.classList.add('input-invalid');
        field.classList.remove('input-valid');
        const el = document.getElementById(errId);
        if(el) { el.textContent = msg; el.style.display = 'block'; }
    }
    function clearErr(field, errId) {
        field.classList.remove('input-invalid');
        field.classList.add('input-valid');
        const el = document.getElementById(errId);
        if(el) el.style.display = 'none';
    }

    // ── Field references ──────────────────────────────────────────────────────
    const fFirstName  = document.getElementById('firstName');
    const fLastName   = document.getElementById('lastName');
    const fUsername   = document.getElementById('username');
    const fEmail      = document.getElementById('email');
    const fPhone      = document.getElementById('phone');
    const fPassword   = document.getElementById('password');
    const fRole       = document.getElementById('roleSelect');
    const fDept       = document.getElementById('department');
    const fYear       = document.getElementById('year');

    // ── Validation functions ──────────────────────────────────────────────────
    function validateFirstName() {
        const v = fFirstName.value.trim();
        if(!v) { showErr(fFirstName,'err-firstname','First name is required.'); return false; }
        if(!/^[a-zA-Z]{2,}$/.test(v)) { showErr(fFirstName,'err-firstname','Letters only, min 2 characters.'); return false; }
        clearErr(fFirstName,'err-firstname'); return true;
    }
    function validateLastName() {
        const v = fLastName.value.trim();
        if(!v) { showErr(fLastName,'err-lastname','Last name is required.'); return false; }
        if(!/^[a-zA-Z]{2,}$/.test(v)) { showErr(fLastName,'err-lastname','Letters only, min 2 characters.'); return false; }
        clearErr(fLastName,'err-lastname'); return true;
    }
    function validateUsername() {
        const v = fUsername.value.trim();
        if(!v) { showErr(fUsername,'err-username','Username is required.'); return false; }
        if(!/^[a-zA-Z0-9_]{5,}$/.test(v)) { showErr(fUsername,'err-username','Min 5 chars; letters, numbers, underscore only — no spaces.'); return false; }
        clearErr(fUsername,'err-username'); return true;
    }
    function validateEmail() {
        const v = fEmail.value.trim();
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if(!v) { showErr(fEmail,'err-email','Email is required.'); return false; }
        if(!re.test(v)) { showErr(fEmail,'err-email','Enter a valid email address (e.g. name@domain.com).'); return false; }
        clearErr(fEmail,'err-email'); return true;
    }
    function validatePhone() {
        const v = fPhone.value.replace(/\D/g,'');
        if(!v || v.length !== 10) { showErr(fPhone,'err-phone','Phone number is required and must be exactly 10 digits.'); return false; }
        clearErr(fPhone,'err-phone'); return true;
    }
    function getPasswordStrength(pw) {
        let score = 0;
        if(pw.length >= 8)  score++;
        if(pw.length >= 12) score++;
        if(/[A-Z]/.test(pw)) score++;
        if(/[a-z]/.test(pw)) score++;
        if(/[0-9]/.test(pw)) score++;
        if(/[!@#$%^&*()\-_+=\[\]{}|;:,.<>?]/.test(pw)) score++;
        if(WEAK_PASSWORDS.includes(pw.toLowerCase())) score = 0;
        return score; // 0-6
    }
    function updateStrengthBar() {
        const pw    = fPassword.value;
        const score = getPasswordStrength(pw);
        const bar   = document.getElementById('strengthBar');
        const lbl   = document.getElementById('strengthLabel');
        if(!pw) { bar.style.width='0'; lbl.textContent=''; return; }
        if(score <= 2)      { bar.style.width='33%'; bar.style.background='#f56565'; lbl.style.color='#c53030'; lbl.textContent='Weak'; }
        else if(score <= 4) { bar.style.width='66%'; bar.style.background='#ed8936'; lbl.style.color='#c05621'; lbl.textContent='Medium'; }
        else                { bar.style.width='100%'; bar.style.background='#48bb78'; lbl.style.color='#276749'; lbl.textContent='Strong ✓'; }
    }
    function validatePassword() {
        const pw = fPassword.value;
        updateStrengthBar();
        if(!pw)                { showErr(fPassword,'err-password','Password is required.'); return false; }
        if(pw.length < 8)      { showErr(fPassword,'err-password','Password must be at least 8 characters.'); return false; }
        if(!/[A-Z]/.test(pw))  { showErr(fPassword,'err-password','Include at least one uppercase letter.'); return false; }
        if(!/[a-z]/.test(pw))  { showErr(fPassword,'err-password','Include at least one lowercase letter.'); return false; }
        if(!/[0-9]/.test(pw))  { showErr(fPassword,'err-password','Include at least one number.'); return false; }
        if(!/[!@#$%^&*()\-_+=\[\]{}|;:,.<>?]/.test(pw)) { showErr(fPassword,'err-password','Include at least one special character (!@#$%^&* etc.).'); return false; }
        if(WEAK_PASSWORDS.includes(pw.toLowerCase())) { showErr(fPassword,'err-password','Password is too common. Choose a stronger one.'); return false; }
        clearErr(fPassword,'err-password'); return true;
    }

    // ── Attach real-time listeners ────────────────────────────────────────────
    fFirstName.addEventListener('input', validateFirstName);
    fLastName.addEventListener('input', validateLastName);
    fUsername.addEventListener('input', validateUsername);
    fEmail   .addEventListener('input', validateEmail);
    fPhone   .addEventListener('input', function() {
        // Strip non-digits while typing
        this.value = this.value.replace(/\D/g,'').slice(0,10);
        validatePhone();
    });
    fPassword.addEventListener('input', validatePassword);

    // ── Year field toggle ─────────────────────────────────────────────────────
    function toggleYearField() {
        const role      = fRole.value;
        const yearField = document.getElementById('yearField');
        if(role === 'student') {
            yearField.style.display = 'block';
            fYear.required = true;
        } else {
            yearField.style.display = 'none';
            fYear.required = false;
            fYear.value = '';
        }
    }
    toggleYearField();

    // ── Pre-submit full validation ────────────────────────────────────────────
    document.getElementById('createUserForm').addEventListener('submit', function(e) {
        let valid = true;

        if(!fRole.value)  { showErr(fRole,'err-role','Please select a role.'); valid = false; }
        else clearErr(fRole,'err-role');

        if(!validateFirstName()) valid = false;
        if(!validateLastName())  valid = false;
        if(!validatePhone())     valid = false;
        if(!validateUsername())  valid = false;
        if(!validateEmail())     valid = false;
        if(!validatePassword())  valid = false;

        if(!fDept.value) { showErr(fDept,'err-dept','Please select a department.'); valid = false; }
        else clearErr(fDept,'err-dept');

        if(fRole.value === 'student' && !fYear.value) {
            showErr(fYear,'err-year','Year is required for students.'); valid = false;
        } else {
            clearErr(fYear,'err-year');
        }

        if(!valid) e.preventDefault();
    });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>