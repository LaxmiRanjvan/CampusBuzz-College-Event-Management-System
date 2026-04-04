<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$error   = "";
$success = "";

// Validate and fetch user ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}
$user_id = intval($_GET['id']);

// Fetch user (prepared statement — was already safe, kept as-is)
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND role != 'admin'");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($user_result) == 0) {
    mysqli_stmt_close($stmt);
    header("Location: manage_users.php");
    exit();
}
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

// Split stored full_name into first and last name for form pre-fill
$name_parts      = explode(' ', $user['full_name'], 2);
$user_first_name = $name_parts[0] ?? '';
$user_last_name  = $name_parts[1] ?? '';

// ─── Whitelist constants ───────────────────────────────────────────────────────
$allowed_depts = [
    'Computer Science','Information Technology','Electronics',
    'Mechanical','Civil','Electrical','Business Administration','Other'
];
$allowed_years = ['First Year','Second Year','Third Year','Fourth Year','Graduate'];
$weak_passwords = ['password','123456','12345678','admin123','password1',
                   'qwerty123','letmein','welcome1','campus123'];

// ─── Handle update ─────────────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {

    // Sanitize inputs
    $username   = trim(strip_tags($_POST['username']    ?? ''));
    $email      = trim(strip_tags($_POST['email']       ?? ''));
    $first_name = trim(strip_tags($_POST['first_name']  ?? ''));
    $last_name  = trim(strip_tags($_POST['last_name']   ?? ''));
    $full_name  = $first_name . ' ' . $last_name;
    $department = trim(strip_tags($_POST['department']  ?? ''));
    $year       = trim(strip_tags($_POST['year']        ?? ''));
    $phone      = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    $new_password = $_POST['new_password'] ?? '';

    // Required presence check
    if(empty($username) || empty($email) || empty($first_name) || empty($last_name) || empty($department) || empty($phone)) {
        $error = "Please fill all required fields.";
    }
    // First name: letters only, min 2 chars
    elseif(!preg_match('/^[a-zA-Z]{2,}$/', $first_name)) {
        $error = "First name must contain only letters (min 2 characters).";
    }
    // Last name: letters only, min 2 chars
    elseif(!preg_match('/^[a-zA-Z]{2,}$/', $last_name)) {
        $error = "Last name must contain only letters (min 2 characters).";
    }
    // Username: letters, numbers, underscore; min 5 chars
    elseif(!preg_match('/^[a-zA-Z0-9_]{5,}$/', $username)) {
        $error = "Username must be at least 5 characters and contain only letters, numbers, or underscore.";
    }
    // Email
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    // Phone (required — must be exactly 10 digits)
    elseif(!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number is required and must be exactly 10 digits.";
    }
    // Department whitelist
    elseif(!in_array($department, $allowed_depts)) {
        $error = "Please select a valid department.";
    } else {
        // Check duplicate username/email (excluding current user)
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        mysqli_stmt_bind_param($check_stmt, "ssi", $username, $email, $user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if(mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Username or email already exists. Please choose different ones.";
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);

            // Password validation (only if changing)
            $update_password  = false;
            $hashed_password  = "";

            if(!empty($new_password)) {
                if(mb_strlen($new_password) < 8) {
                    $error = "Password must be at least 8 characters.";
                } elseif(!preg_match('/[A-Z]/', $new_password)) {
                    $error = "Password must contain at least one uppercase letter.";
                } elseif(!preg_match('/[a-z]/', $new_password)) {
                    $error = "Password must contain at least one lowercase letter.";
                } elseif(!preg_match('/[0-9]/', $new_password)) {
                    $error = "Password must contain at least one number.";
                } elseif(!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $new_password)) {
                    $error = "Password must contain at least one special character.";
                } elseif(in_array(strtolower($new_password), $weak_passwords)) {
                    $error = "Password is too common. Please choose a stronger one.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_password = true;
                }
            }

            if(empty($error)) {
                $year_val = ($user['role'] === 'student') ? $year : '';

                if($update_password) {
                    $upd = mysqli_prepare($conn,
                        "UPDATE users SET username=?, email=?, full_name=?, department=?, year=?, phone=?, password=? WHERE id=?"
                    );
                    mysqli_stmt_bind_param($upd, "sssssssi",
                        $username, $email, $full_name, $department, $year_val, $phone, $hashed_password, $user_id
                    );
                } else {
                    $upd = mysqli_prepare($conn,
                        "UPDATE users SET username=?, email=?, full_name=?, department=?, year=?, phone=? WHERE id=?"
                    );
                    mysqli_stmt_bind_param($upd, "ssssssi",
                        $username, $email, $full_name, $department, $year_val, $phone, $user_id
                    );
                }

                if(mysqli_stmt_execute($upd)) {
                    $success = "User updated successfully!";
                    mysqli_stmt_close($upd);

                    // Refresh user data
                    $ref = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($ref, "i", $user_id);
                    mysqli_stmt_execute($ref);
                    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($ref));
                    mysqli_stmt_close($ref);
                    // Re-split names for form display
                    $name_parts      = explode(' ', $user['full_name'], 2);
                    $user_first_name = $name_parts[0] ?? '';
                    $user_last_name  = $name_parts[1] ?? '';
                } else {
                    $error = "An error occurred while updating. Please try again.";
                    mysqli_stmt_close($upd);
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
    <title>Edit User - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .field-error   { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid   { border-color:#48bb78 !important; }
        .strength-bar-wrap { height:6px; background:#e2e8f0; border-radius:4px; margin-top:6px; }
        .strength-bar      { height:100%; border-radius:4px; transition:width .3s,background .3s; width:0; }
        .strength-label    { font-size:12px; margin-top:4px; font-weight:600; }
        .btn-primary:disabled { opacity:0.45; cursor:not-allowed; pointer-events:none; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1>✏️ Edit User</h1>
                <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div style="background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <div style="background:#f7fafc;padding:15px;border-radius:8px;margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <strong>Editing:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
                        <span class="role-badge role-<?php echo $user['role']; ?>" style="margin-left:10px;">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    <a href="send_email.php?user_id=<?php echo $user_id; ?>"
                       class="btn btn-primary"
                       style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;box-shadow:0 4px 12px rgba(102,126,234,0.3);">
                        <span>📧</span> Send Credentials
                    </a>
                </div>

                <form method="POST" action="" id="editUserForm" novalidate>

                    <!-- User Type (read-only display) -->
                    <div class="form-group">
                        <label>User Type</label>
                        <div style="padding:10px 14px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:6px;color:#4a5568;font-weight:600;">
                            <?php echo ucfirst($user['role']); ?>
                            <span style="font-size:12px;color:#718096;font-weight:400;margin-left:8px;">(Cannot be changed after creation)</span>
                        </div>
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                    </div>

                    <!-- First Name + Last Name -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" id="firstName" required
                                   placeholder="Enter first name"
                                   value="<?php echo htmlspecialchars($user_first_name); ?>">
                            <span class="field-error" id="err-firstname">Letters only, min 2 characters.</span>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" id="lastName" required
                                   placeholder="Enter last name"
                                   value="<?php echo htmlspecialchars($user_last_name); ?>">
                            <span class="field-error" id="err-lastname">Letters only, min 2 characters.</span>
                        </div>
                    </div>

                    <!-- Phone Number (required, full row) -->
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" id="phone" maxlength="10" required
                               placeholder="9876543210"
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        <span class="field-error" id="err-phone">Phone number is required and must be exactly 10 digits.</span>
                    </div>

                    <!-- Username (full row) -->
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" id="username" required
                               value="<?php echo htmlspecialchars($user['username']); ?>">
                        <span class="field-error" id="err-username">Min 5 chars; letters, numbers, underscore only.</span>
                    </div>

                    <!-- Email (full row) -->
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="email" required
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                        <span class="field-error" id="err-email">Enter a valid email address.</span>
                    </div>

                    <!-- New password (optional) -->
                    <div class="form-group">
                        <label>New Password <small style="color:#718096;">(leave blank to keep current)</small></label>
                        <input type="password" name="new_password" id="newPassword"
                               placeholder="Min 8 chars, uppercase, number, special char"
                               autocomplete="new-password">
                        <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
                        <div class="strength-label" id="strengthLabel"></div>
                        <span class="field-error" id="err-password">Password does not meet requirements.</span>
                        <small style="color:#718096;">
                            Requirements: 8+ chars · 1 uppercase · 1 lowercase · 1 number · 1 special character
                        </small>
                    </div>

                    <!-- Department + Year (conditional) -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Department *</label>
                            <select name="department" id="department" required>
                                <option value="">Select Department</option>
                                <?php
                                $depts = ['Computer Science','Information Technology','Electronics','Mechanical','Civil','Electrical','Business Administration','Other'];
                                foreach($depts as $d):
                                    $sel = ($user['department'] == $d) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $d; ?>" <?php echo $sel; ?>><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-dept">Please select a department.</span>
                        </div>

                        <?php if($user['role'] == 'student'): ?>
                        <div class="form-group">
                            <label>Year * <span style="color:#f56565;">(Required for Students)</span></label>
                            <select name="year" id="year" required>
                                <option value="">Select Year</option>
                                <?php
                                $years = ['First Year','Second Year','Third Year','Fourth Year','Graduate'];
                                foreach($years as $y):
                                    $sel = ($user['year'] == $y) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $y; ?>" <?php echo $sel; ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-year">Please select a year.</span>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="year" value="">
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:15px;margin-top:30px;">
                        <button type="submit" name="update_user" id="updateBtn" class="btn btn-primary" disabled>✓ Update User</button>
                        <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                        <a href="view_user.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">👁️ View Full Profile</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    /* ── Real-time validation — edit_user.php ── */
    const WEAK_PASSWORDS = ['password','123456','12345678','admin123','password1',
                            'qwerty123','letmein','welcome1','campus123'];

    function showErr(field, errId, msg) {
        field.classList.add('input-invalid'); field.classList.remove('input-valid');
        const el = document.getElementById(errId);
        if(el){ el.textContent = msg; el.style.display = 'block'; }
    }
    function clearErr(field, errId) {
        field.classList.remove('input-invalid'); field.classList.add('input-valid');
        const el = document.getElementById(errId);
        if(el) el.style.display = 'none';
    }

    const fFirstName = document.getElementById('firstName');
    const fLastName  = document.getElementById('lastName');
    const fUser      = document.getElementById('username');
    const fEmail     = document.getElementById('email');
    const fPhone     = document.getElementById('phone');
    const fPass      = document.getElementById('newPassword');
    const fDept      = document.getElementById('department');
    const fYear      = document.getElementById('year');
    const updateBtn  = document.getElementById('updateBtn');

    /* ── Snapshot original values on page load ── */
    const orig = {
        firstName:  fFirstName.value,
        lastName:   fLastName.value,
        username:   fUser.value,
        email:      fEmail.value,
        phone:      fPhone.value,
        department: fDept.value,
        year:       fYear ? fYear.value : ''
    };

    /* ── Check if anything has actually changed ── */
    function isDirty() {
        return fFirstName.value !== orig.firstName ||
               fLastName.value  !== orig.lastName  ||
               fUser.value      !== orig.username  ||
               fEmail.value     !== orig.email     ||
               fPhone.value     !== orig.phone     ||
               fDept.value      !== orig.department ||
               (fYear && fYear.value !== orig.year) ||
               fPass.value.length > 0; /* new password = always a real change */
    }
    function syncBtn() { updateBtn.disabled = !isDirty(); }

    function valFirstName() {
        const v = fFirstName.value.trim();
        if(!v)                          { showErr(fFirstName,'err-firstname','First name is required.'); return false; }
        if(!/^[a-zA-Z]{2,}$/.test(v))  { showErr(fFirstName,'err-firstname','Letters only, min 2 characters.'); return false; }
        clearErr(fFirstName,'err-firstname'); return true;
    }
    function valLastName() {
        const v = fLastName.value.trim();
        if(!v)                          { showErr(fLastName,'err-lastname','Last name is required.'); return false; }
        if(!/^[a-zA-Z]{2,}$/.test(v))  { showErr(fLastName,'err-lastname','Letters only, min 2 characters.'); return false; }
        clearErr(fLastName,'err-lastname'); return true;
    }
    function valUsername() {
        const v = fUser.value.trim();
        if(!v)                               { showErr(fUser,'err-username','Username is required.'); return false; }
        if(!/^[a-zA-Z0-9_]{5,}$/.test(v))   { showErr(fUser,'err-username','Min 5 chars; letters, numbers, underscore only.'); return false; }
        clearErr(fUser,'err-username'); return true;
    }
    function valEmail() {
        const v  = fEmail.value.trim();
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if(!v)        { showErr(fEmail,'err-email','Email is required.'); return false; }
        if(!re.test(v)){ showErr(fEmail,'err-email','Enter a valid email address.'); return false; }
        clearErr(fEmail,'err-email'); return true;
    }
    function valPhone() {
        const v = fPhone.value.replace(/\D/g,'');
        if(!v || v.length !== 10){ showErr(fPhone,'err-phone','Phone number is required and must be exactly 10 digits.'); return false; }
        clearErr(fPhone,'err-phone'); return true;
    }

    function updateStrengthBar() {
        const pw = fPass.value;
        const bar = document.getElementById('strengthBar');
        const lbl = document.getElementById('strengthLabel');
        if(!pw){ bar.style.width='0'; lbl.textContent=''; return; }
        let score = 0;
        if(pw.length >= 8)  score++;
        if(pw.length >= 12) score++;
        if(/[A-Z]/.test(pw)) score++;
        if(/[a-z]/.test(pw)) score++;
        if(/[0-9]/.test(pw)) score++;
        if(/[!@#$%^&*()\-_+=\[\]{}|;:,.<>?]/.test(pw)) score++;
        if(WEAK_PASSWORDS.includes(pw.toLowerCase())) score = 0;
        if(score <= 2)      { bar.style.width='33%'; bar.style.background='#f56565'; lbl.style.color='#c53030'; lbl.textContent='Weak'; }
        else if(score <= 4) { bar.style.width='66%'; bar.style.background='#ed8936'; lbl.style.color='#c05621'; lbl.textContent='Medium'; }
        else                { bar.style.width='100%'; bar.style.background='#48bb78'; lbl.style.color='#276749'; lbl.textContent='Strong ✓'; }
    }
    function valPassword() {
        const pw = fPass.value;
        updateStrengthBar();
        if(!pw) { clearErr(fPass,'err-password'); return true; } // optional field
        if(pw.length < 8)     { showErr(fPass,'err-password','Minimum 8 characters required.'); return false; }
        if(!/[A-Z]/.test(pw)) { showErr(fPass,'err-password','Include at least one uppercase letter.'); return false; }
        if(!/[a-z]/.test(pw)) { showErr(fPass,'err-password','Include at least one lowercase letter.'); return false; }
        if(!/[0-9]/.test(pw)) { showErr(fPass,'err-password','Include at least one number.'); return false; }
        if(!/[!@#$%^&*()\-_+=\[\]{}|;:,.<>?]/.test(pw)) { showErr(fPass,'err-password','Include at least one special character.'); return false; }
        if(WEAK_PASSWORDS.includes(pw.toLowerCase())) { showErr(fPass,'err-password','Password is too common. Choose a stronger one.'); return false; }
        clearErr(fPass,'err-password'); return true;
    }

    fFirstName.addEventListener('input', function(){ valFirstName(); syncBtn(); });
    fLastName.addEventListener('input',  function(){ valLastName();  syncBtn(); });
    fUser.addEventListener('input',      function(){ valUsername();  syncBtn(); });
    fEmail.addEventListener('input',     function(){ valEmail();     syncBtn(); });
    fPhone.addEventListener('input',     function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); valPhone(); syncBtn(); });
    fPass.addEventListener('input',      function(){ valPassword();  syncBtn(); });
    fDept.addEventListener('change',     function(){ syncBtn(); });
    if(fYear) fYear.addEventListener('change', function(){ syncBtn(); });

    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        let ok = true;
        if(!valFirstName()) ok = false;
        if(!valLastName())  ok = false;
        if(!valPhone())     ok = false;
        if(!valUsername())  ok = false;
        if(!valEmail())     ok = false;
        if(!valPassword())  ok = false;
        if(!fDept.value){ showErr(fDept,'err-dept','Please select a department.'); ok = false; } else clearErr(fDept,'err-dept');
        <?php if($user['role'] == 'student'): ?>
        if(fYear && !fYear.value){ showErr(fYear,'err-year','Year is required for students.'); ok = false; }
        <?php endif; ?>
        if(!ok) e.preventDefault();
    });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>