<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if(!$event_id) {
    header("Location: browse_events.php");
    exit();
}

$error   = "";
$success = "";

// ── Fetch event details ────────────────────────────────────────────────────────
$ev_stmt = mysqli_prepare($conn,
    "SELECT e.*, u.full_name as organizer_name,
     (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
     FROM events e JOIN users u ON e.organizer_id = u.id WHERE e.id = ?"
);
mysqli_stmt_bind_param($ev_stmt, "i", $event_id);
mysqli_stmt_execute($ev_stmt);
$event_result = mysqli_stmt_get_result($ev_stmt);

if(mysqli_num_rows($event_result) == 0) {
    mysqli_stmt_close($ev_stmt);
    header("Location: browse_events.php");
    exit();
}
$event = mysqli_fetch_assoc($event_result);
mysqli_stmt_close($ev_stmt);

$seats_left = $event['max_participants'] - $event['registered_count'];
$is_full    = $seats_left <= 0;

// ── Check if already registered ───────────────────────────────────────────────
$check_reg = mysqli_prepare($conn, "SELECT id FROM registrations WHERE event_id=? AND user_id=? AND status='registered'");
mysqli_stmt_bind_param($check_reg, "ii", $event_id, $user_id);
mysqli_stmt_execute($check_reg);
mysqli_stmt_store_result($check_reg);
$already_registered = mysqli_stmt_num_rows($check_reg) > 0;
mysqli_stmt_close($check_reg);

if($already_registered) {
    header("Location: my_events.php?msg=already_registered");
    exit();
}

// ── Pre-fill user data ────────────────────────────────────────────────────────
$usr_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($usr_stmt, "i", $user_id);
mysqli_stmt_execute($usr_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($usr_stmt));
mysqli_stmt_close($usr_stmt);

// ── Handle registration form submission ───────────────────────────────────────
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {

    // All fields are editable, pre-filled from user profile
    $name       = trim(strip_tags($_POST['name']       ?? ''));
    $phone      = preg_replace('/\D/', '', trim($_POST['phone']      ?? ''));
    $year       = trim(strip_tags($_POST['year']       ?? ''));
    $notes      = trim(strip_tags($_POST['notes']      ?? ''));
    $email      = trim(strip_tags($_POST['email']      ?? ''));
    $department = trim(strip_tags($_POST['department'] ?? ''));

    // ── Validation ────────────────────────────────────────────────────────────
    if(empty($name) || empty($phone) || empty($email)) {
        $error = "Please fill in all required fields.";
    } elseif(!preg_match('/^[a-zA-Z]+(?: [a-zA-Z]+)*$/', $name) || mb_strlen($name) < 3) {
        $error = "Full name must contain only letters and spaces (min 3 characters).";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif(!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    } elseif($is_full) {
        $error = "Sorry, this event is full!";
    } else {
        // Double-check seats
        $seats_stmt = mysqli_prepare($conn,
            "SELECT e.max_participants, COUNT(r.id) as current_reg,
             (e.max_participants - COUNT(r.id)) as available
             FROM events e LEFT JOIN registrations r ON e.id = r.event_id AND r.status='registered'
             WHERE e.id = ? GROUP BY e.id, e.max_participants"
        );
        mysqli_stmt_bind_param($seats_stmt, "i", $event_id);
        mysqli_stmt_execute($seats_stmt);
        $seats_data = mysqli_fetch_assoc(mysqli_stmt_get_result($seats_stmt));
        mysqli_stmt_close($seats_stmt);

        if(!$seats_data || $seats_data['available'] <= 0) {
            $error = "Sorry, this event just filled up!";
        } else {
            // Check if a cancelled row already exists for this user+event
            $chk_cancel = mysqli_prepare($conn,
                "SELECT id FROM registrations WHERE event_id=? AND user_id=? AND status='cancelled'"
            );
            mysqli_stmt_bind_param($chk_cancel, "ii", $event_id, $user_id);
            mysqli_stmt_execute($chk_cancel);
            mysqli_stmt_store_result($chk_cancel);
            $has_cancelled_row = mysqli_stmt_num_rows($chk_cancel) > 0;
            mysqli_stmt_close($chk_cancel);

            if($has_cancelled_row) {
                // UPDATE the existing cancelled row back to registered
                $upd = mysqli_prepare($conn,
                    "UPDATE registrations
                     SET name=?, email=?, phone=?, department=?, year=?, notes=?,
                         status='registered', registration_date=NOW()
                     WHERE event_id=? AND user_id=? AND status='cancelled'"
                );
                mysqli_stmt_bind_param($upd, "ssssssii",
                    $name, $email, $phone, $department, $year, $notes, $event_id, $user_id
                );
                $exec_ok = mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            } else {
                // No previous row — fresh INSERT
                $ins = mysqli_prepare($conn,
                    "INSERT INTO registrations
                     (event_id, user_id, name, email, phone, department, year, notes, status, registration_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'registered', NOW())"
                );
                mysqli_stmt_bind_param($ins, "iissssss",
                    $event_id, $user_id, $name, $email, $phone, $department, $year, $notes
                );
                $exec_ok = mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }

            if($exec_ok) {
                $success = "Registration successful! Redirecting to your events...";
                echo "<script>setTimeout(function(){ window.location.href='my_events.php?msg=registered'; }, 2000);</script>";
            } else {
                $error = "Registration failed. Please try again.";
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
    <title>Register for <?php echo htmlspecialchars($event['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .registration-container { max-width:900px; margin:0 auto; }
        .back-button { display:inline-flex; align-items:center; gap:8px; color:#667eea; text-decoration:none; font-weight:600; margin-bottom:20px; padding:10px 16px; border-radius:6px; transition:all .3s; }
        .back-button:hover { background:#f7fafc; }
        .registration-grid { display:grid; grid-template-columns:2fr 1fr; gap:30px; }
        .registration-form { background:white; padding:40px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.1); }
        .form-header { margin-bottom:30px; }
        .form-header h1 { color:#2d3748; font-size:28px; margin-bottom:10px; }
        .form-header p  { color:#718096; font-size:16px; }
        .form-grid      { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .form-grid-full { grid-column:1/-1; }
        .event-summary  { background:white; padding:30px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.1); height:fit-content; position:sticky; top:20px; }
        .summary-header { font-size:18px; color:#2d3748; margin-bottom:20px; font-weight:600; }
        .summary-item   { padding:15px 0; border-bottom:1px solid #e2e8f0; }
        .summary-item:last-child { border-bottom:none; }
        .summary-label  { font-size:12px; color:#718096; text-transform:uppercase; font-weight:600; margin-bottom:5px; }
        .summary-value  { color:#2d3748; font-size:16px; font-weight:500; }
        .seats-alert    { padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600; text-align:center; }
        .seats-alert.limited { background:#feebc8; color:#7c2d12; }
        .seats-alert.good    { background:#c6f6d5; color:#276749; }
        .required-note  { color:#f56565; font-size:14px; margin-bottom:20px; }
        /* Validation styles */
        .field-error    { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid   { border-color:#48bb78 !important; }
        @media (max-width:968px){ .registration-grid{grid-template-columns:1fr;} .form-grid{grid-template-columns:1fr;} .event-summary{position:static;} }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="registration-container">
                <a href="view_event.php?id=<?php echo $event_id; ?>" class="back-button">← Back to Event Details</a>

                <?php if($is_full): ?>
                <div style="background:white;padding:60px;text-align:center;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);">
                    <div style="font-size:80px;margin-bottom:20px;">😔</div>
                    <h2 style="color:#2d3748;margin-bottom:10px;">Event Full</h2>
                    <p style="color:#718096;margin-bottom:20px;">Sorry, this event has reached its maximum capacity.</p>
                    <a href="browse_events.php" class="btn btn-primary">Browse Other Events</a>
                </div>
                <?php else: ?>
                <div class="registration-grid">
                    <div class="registration-form">
                        <div class="form-header">
                            <h1>🎫 Event Registration</h1>
                            <p>Fill in your details to register for this event</p>
                        </div>

                        <?php if($error): ?>
                            <div class="alert alert-error" style="background:#fed7d7;color:#c53030;padding:15px;border-radius:8px;margin-bottom:20px;">❌ <?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success" style="background:#c6f6d5;color:#276749;padding:15px;border-radius:8px;margin-bottom:20px;">✓ <?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <?php if($seats_left <= 10 && !$success): ?>
                            <div class="seats-alert limited">⚠️ Only <?php echo $seats_left; ?> seats remaining! Register now.</div>
                        <?php endif; ?>

                        <?php if(!$success): ?>
                        <p class="required-note">* Required fields</p>

                        <form method="POST" action="" id="regForm" novalidate>
                            <div class="form-grid">

                                <!-- Full Name — editable -->
                                <div class="form-group form-grid-full">
                                    <label>Full Name *</label>
                                    <input type="text" name="name" id="regName" required
                                           value="<?php echo htmlspecialchars($user_data['full_name']); ?>"
                                           placeholder="Enter your full name">
                                    <span class="field-error" id="err-name">Only letters and spaces allowed (min 3 characters).</span>
                                </div>

                                <!-- Email — editable, pre-filled from user record -->
                                <div class="form-group">
                                    <label>Email Address *</label>
                                    <input type="email" name="email" id="regEmail" required
                                           value="<?php echo htmlspecialchars($user_data['email']); ?>"
                                           placeholder="your.email@example.com">
                                    <span class="field-error" id="err-email">Enter a valid email address.</span>
                                </div>

                                <!-- Phone — editable -->
                                <div class="form-group">
                                    <label>Phone Number *</label>
                                    <input type="tel" name="phone" id="regPhone" required maxlength="10"
                                           value="<?php echo isset($user_data['phone']) ? htmlspecialchars(preg_replace('/\D/','',$user_data['phone'])) : ''; ?>"
                                           placeholder="9876543210">
                                    <span class="field-error" id="err-phone">Phone must be exactly 10 digits.</span>
                                </div>

                                <!-- Department — editable, pre-filled from user record -->
                                <div class="form-group">
                                    <label>Department</label>
                                    <input type="text" name="department"
                                           value="<?php echo isset($user_data['department']) ? htmlspecialchars($user_data['department']) : ''; ?>"
                                           placeholder="e.g., Computer Science">
                                </div>

                                <!-- Year — dropdown, pre-selected from user record -->
                                <div class="form-group">
                                    <label>Year/Semester</label>
                                    <select name="year">
                                        <option value="">Select Year</option>
                                        <?php
                                        // Must match DB values set in create_user: 'First Year','Second Year', etc.
                                        $yrs = ['First Year','Second Year','Third Year','Fourth Year','Graduate'];
                                        foreach($yrs as $yr):
                                            $sel = (isset($user_data['year']) && $user_data['year'] === $yr) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $yr; ?>" <?php echo $sel; ?>><?php echo $yr; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Notes — optional -->
                                <div class="form-group form-grid-full">
                                    <label>Additional Notes <small style="color:#718096;">(Optional)</small></label>
                                    <textarea name="notes" rows="4" placeholder="Any special requirements or questions..."></textarea>
                                </div>
                            </div>

                            <button type="submit" name="register" class="btn btn-primary" style="width:100%;margin-top:20px;">
                                Complete Registration
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <!-- Event Summary sidebar -->
                    <div class="event-summary">
                        <h3 class="summary-header">📋 Event Summary</h3>
                        <div class="summary-item">
                            <div class="summary-label">Event Name</div>
                            <div class="summary-value"><?php echo htmlspecialchars($event['title']); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Date &amp; Time</div>
                            <div class="summary-value">
                                <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?><br>
                                <?php echo date('h:i A', strtotime($event['event_date'])); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Venue</div>
                            <div class="summary-value"><?php echo htmlspecialchars($event['venue']); ?></div>
                        </div>
                        <?php if($event['category']): ?>
                        <div class="summary-item">
                            <div class="summary-label">Category</div>
                            <div class="summary-value"><?php echo htmlspecialchars($event['category']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="summary-item">
                            <div class="summary-label">Organizer</div>
                            <div class="summary-value"><?php echo htmlspecialchars($event['organizer_name']); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Available Seats</div>
                            <div class="summary-value" style="color:<?php echo $seats_left<=10?'#ed8936':'#48bb78'; ?>;">
                                <?php echo $seats_left; ?> / <?php echo $event['max_participants']; ?>
                            </div>
                        </div>
                        <?php if($seats_left > 10): ?>
                            <div class="seats-alert good" style="margin-top:20px;">✓ Good availability</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    /* ── Real-time validation — register_event.php ── */
    function showErr(f,id,msg){ f.classList.add('input-invalid');f.classList.remove('input-valid');const el=document.getElementById(id);if(el){el.textContent=msg;el.style.display='block';} }
    function clearErr(f,id){ f.classList.remove('input-invalid');f.classList.add('input-valid');const el=document.getElementById(id);if(el)el.style.display='none'; }

    const fName  = document.getElementById('regName');
    const fEmail = document.getElementById('regEmail');
    const fPhone = document.getElementById('regPhone');

    if(fName) {
        fName.addEventListener('input', function(){
            const v = this.value.trim();
            if(!v)                                     { showErr(this,'err-name','Full name is required.'); return; }
            if(!/^[a-zA-Z]+(?: [a-zA-Z]+)*$/.test(v)) { showErr(this,'err-name','Only letters and single spaces allowed.'); return; }
            if(v.length < 3)                           { showErr(this,'err-name','Minimum 3 characters required.'); return; }
            clearErr(this,'err-name');
        });
    }
    if(fEmail) {
        fEmail.addEventListener('input', function(){
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
            re.test(this.value.trim()) ? clearErr(this,'err-email') : showErr(this,'err-email','Enter a valid email address.');
        });
    }
    if(fPhone) {
        fPhone.addEventListener('input', function(){
            this.value = this.value.replace(/\D/g,'').slice(0,10);
            this.value.length === 10 ? clearErr(this,'err-phone') : showErr(this,'err-phone','Phone must be exactly 10 digits.');
        });
    }

    const form = document.getElementById('regForm');
    if(form) {
        form.addEventListener('submit', function(e){
            let ok = true;
            const n = fName.value.trim();
            if(!n || !/^[a-zA-Z]+(?: [a-zA-Z]+)*$/.test(n) || n.length < 3) {
                showErr(fName,'err-name','Full name must contain only letters and spaces (min 3 chars).'); ok=false;
            }
            if(!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(fEmail.value.trim())) {
                showErr(fEmail,'err-email','Enter a valid email address.'); ok=false;
            }
            if(fPhone.value.replace(/\D/g,'').length !== 10) {
                showErr(fPhone,'err-phone','Phone must be exactly 10 digits.'); ok=false;
            }
            if(!ok) e.preventDefault();
        });
    }
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>