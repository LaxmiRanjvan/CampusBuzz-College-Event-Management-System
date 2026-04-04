<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// ─────────────────────────────────────────────
// SERVER-SIDE VALIDATION FUNCTION
// ─────────────────────────────────────────────
function validateProfileFields($data, $role) {
    $errors = [];

    // Full Name: required, only letters and spaces, 2–100 chars
    if(empty($data['full_name'])) {
        $errors[] = "Full name is required.";
    } elseif(!preg_match('/^[a-zA-Z\s]{2,100}$/', $data['full_name'])) {
        $errors[] = "Full name must contain only letters and spaces (2–100 characters).";
    }

    // Email: required, valid format
    if(empty($data['email'])) {
        $errors[] = "Email is required.";
    } elseif(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address (e.g. name@domain.com).";
    }

    // Phone: optional, but if provided must be valid Indian mobile number
    if(!empty($data['phone'])) {
        // Strip spaces/dashes for validation
        $phone_clean = preg_replace('/[\s\-]/', '', $data['phone']);
        if(!preg_match('/^[6-9]\d{9}$/', $phone_clean)) {
            $errors[] = "Phone number must be a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.";
        }
    }

    // Department: required, must be one of the allowed options
    $valid_departments = [
        'Computer Science',
        'Information Technology',
        'Electronics',
        'Mechanical',
        'Civil',
        'Electrical',
        'Business Administration',
        'Other'
    ];
    if(isset($data['department'])) {
        if(empty(trim($data['department']))) {
            $errors[] = "Please select a department.";
        } elseif(!in_array(trim($data['department']), $valid_departments)) {
            $errors[] = "Please select a valid department from the list.";
        }
    }

    // Year: for students, must be a valid option if provided
    if($role === 'student' && !empty($data['year'])) {
        $valid_years = ['First Year', 'Second Year', 'Third Year', 'Fourth Year', 'Graduate'];
        if(!in_array($data['year'], $valid_years)) {
            $errors[] = "Please select a valid year.";
        }
    }

    // Bio: optional, max 500 chars
    if(!empty($data['bio']) && strlen($data['bio']) > 500) {
        $errors[] = "Bio must not exceed 500 characters.";
    }

    return $errors;
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $bio        = trim($_POST['bio'] ?? '');
    $department = isset($_POST['department']) ? trim($_POST['department']) : null;
    $year       = isset($_POST['year'])       ? trim($_POST['year'])       : null;

    $validation_data = [
        'full_name'  => $full_name,
        'email'      => $email,
        'phone'      => $phone,
        'bio'        => $bio,
        'department' => $department,
        'year'       => $year,
    ];

    $validation_errors = validateProfileFields($validation_data, $_SESSION['role']);

    if(!empty($validation_errors)) {
        $error = implode('<br>', $validation_errors);
    } else {
        // Safe to escape now that values are validated
        $full_name  = mysqli_real_escape_string($conn, $full_name);
        $email      = mysqli_real_escape_string($conn, $email);
        $phone      = mysqli_real_escape_string($conn, $phone);
        $bio        = mysqli_real_escape_string($conn, $bio);
        $department = $department !== null ? mysqli_real_escape_string($conn, $department) : null;
        $year       = $year       !== null ? mysqli_real_escape_string($conn, $year)       : null;

        // Check if email is already taken by another user
        $check_email = "SELECT id FROM users WHERE email = '$email' AND id != $user_id";
        if(mysqli_num_rows(mysqli_query($conn, $check_email)) > 0) {
            $error = "This email address is already in use by another account.";
        } else {
            // Build update query based on role (unchanged from original)
            if($_SESSION['role'] == 'admin') {
                $update_query = "UPDATE users SET 
                               full_name = '$full_name',
                               email = '$email',
                               phone = '$phone',
                               bio = '$bio',
                               department = '$department'
                               WHERE id = $user_id";
            } elseif($_SESSION['role'] == 'organizer') {
                $update_query = "UPDATE users SET 
                               full_name = '$full_name',
                               email = '$email',
                               phone = '$phone',
                               bio = '$bio',
                               department = '$department'
                               WHERE id = $user_id";
            } else {
                $update_query = "UPDATE users SET 
                               full_name = '$full_name',
                               email = '$email',
                               phone = '$phone',
                               bio = '$bio',
                               year = '$year',
                               department = '$department'
                               WHERE id = $user_id";
            }

            if(mysqli_query($conn, $update_query)) {
                $success = "Profile updated successfully!";
                $_SESSION['user_name'] = $full_name;
            } else {
                $error = "Error updating profile. Please try again.";
            }
        }
    }
}

// Handle profile image upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_image'])) {
    if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);

        // Validate file size (max 2MB)
        if($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $error = "Image size must not exceed 2MB.";
        } elseif(!in_array(strtolower($filetype), $allowed)) {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF are allowed.";
        } else {
            $newfilename  = 'profile_' . $user_id . '_' . time() . '.' . $filetype;
            $upload_path  = '../uploads/profiles/';

            if(!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            if(move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path . $newfilename)) {
                // Delete old profile image if exists
                $old_image_query  = "SELECT profile_image FROM users WHERE id = $user_id";
                $old_image_result = mysqli_query($conn, $old_image_query);
                $old_image        = mysqli_fetch_assoc($old_image_result)['profile_image'];

                if($old_image && file_exists($upload_path . $old_image)) {
                    unlink($upload_path . $old_image);
                }

                $update_image = "UPDATE users SET profile_image = '$newfilename' WHERE id = $user_id";
                if(mysqli_query($conn, $update_image)) {
                    $success = "Profile image updated successfully!";
                }
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        }
    } else {
        $error = "Please select an image to upload.";
    }
}

// Fetch user data
$user_query  = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user        = mysqli_fetch_assoc($user_result);

// Get user statistics
if($user['role'] == 'student') {
    $registrations_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations WHERE user_id = $user_id"))['count'];
    $saved_count         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM event_saves WHERE user_id = $user_id"))['count'];
    $liked_count         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM event_likes WHERE user_id = $user_id"))['count'];
} elseif($user['role'] == 'organizer') {
    $events_count        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM events WHERE organizer_id = $user_id"))['count'];
    $total_registrations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations r JOIN events e ON r.event_id = e.id WHERE e.organizer_id = $user_id"))['count'];
    $merch_count         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM merchandise WHERE organizer_id = $user_id"))['count'];
} elseif($user['role'] == 'admin') {
    $total_users      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'];
    $total_events     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM events"))['count'];
    $pending_approvals= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM events WHERE status = 'pending'"))['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .profile-sidebar {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            align-self: start;
            position: sticky;
            top: 20px;
        }

        .profile-image-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #e2e8f0;
        }

        .profile-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            font-weight: 700;
            border: 5px solid #e2e8f0;
        }

        .upload-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #667eea;
            color: white;
            border: 3px solid white;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }

        .upload-btn:hover {
            background: #5568d3;
            transform: scale(1.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e2e8f0;
        }

        .stat-box   { text-align: center; }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label { font-size: 12px; color: #718096; }

        .profile-main {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-section:last-child { border-bottom: none; }

        .info-box {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-top: 15px;
        }

        /* Inline validation feedback */
        .field-error {
            color: #e53e3e;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .input-invalid {
            border-color: #e53e3e !important;
            background-color: #fff5f5;
        }

        .input-valid {
            border-color: #48bb78 !important;
            background-color: #f0fff4;
        }

        /* Character counter for bio */
        .char-counter {
            font-size: 12px;
            color: #718096;
            text-align: right;
            margin-top: 4px;
        }

        .char-counter.over-limit { color: #e53e3e; font-weight: 600; }

        @media (max-width: 968px) {
            .profile-container { grid-template-columns: 1fr; }
            .profile-sidebar   { position: relative; top: 0; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include '../includes/header.php'; ?>

        <div class="content-header">
            <h1>👤 My Profile</h1>
            <a href="../common/home.php" class="btn btn-secondary">← Back to Home</a>
        </div>

        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; /* already sanitised via htmlspecialchars in validation */ ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="profile-container">

            <!-- ── Sidebar ── -->
            <div class="profile-sidebar">
                <div class="profile-image-container">
                    <?php if($user['profile_image']): ?>
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>"
                             alt="Profile" class="profile-image">
                    <?php else: ?>
                        <div class="profile-placeholder">
                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="imageUploadForm">
                        <input type="file" name="profile_image" id="profileImageInput" accept="image/*" style="display:none;">
                        <label for="profileImageInput" class="upload-btn">📷</label>
                        <button type="submit" name="upload_image" id="uploadBtn" style="display:none;"></button>
                    </form>
                </div>

                <h2 style="margin-bottom:5px;color:#2d3748;"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <p style="color:#718096;margin-bottom:10px;">@<?php echo htmlspecialchars($user['username']); ?></p>

                <span class="role-badge role-<?php echo $user['role']; ?>" style="font-size:14px;">
                    <?php echo ucfirst($user['role']); ?>
                </span>

                <div style="margin-top:20px;padding:15px;background:#f7fafc;border-radius:8px;text-align:left;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <span>📧</span>
                        <span style="font-size:14px;color:#4a5568;"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <?php if($user['phone']): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <span>📱</span>
                        <span style="font-size:14px;color:#4a5568;"><?php echo htmlspecialchars($user['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span>🏢</span>
                        <span style="font-size:14px;color:#4a5568;"><?php echo htmlspecialchars($user['department']); ?></span>
                    </div>
                </div>

                <!-- Statistics -->
                <?php if($user['role'] == 'student'): ?>
                <div class="stats-grid">
                    <div class="stat-box"><div class="stat-number"><?php echo $registrations_count; ?></div><div class="stat-label">Events</div></div>
                    <div class="stat-box"><div class="stat-number"><?php echo $saved_count; ?></div><div class="stat-label">Saved</div></div>
                    <div class="stat-box"><div class="stat-number"><?php echo $liked_count; ?></div><div class="stat-label">Liked</div></div>
                </div>
                <?php elseif($user['role'] == 'organizer'): ?>
                <div class="stats-grid">
                    <div class="stat-box"><div class="stat-number"><?php echo $events_count; ?></div><div class="stat-label">Events</div></div>
                    <div class="stat-box"><div class="stat-number"><?php echo $total_registrations; ?></div><div class="stat-label">Registrations</div></div>
                    <div class="stat-box"><div class="stat-number"><?php echo $merch_count; ?></div><div class="stat-label">Merchandise</div></div>
                </div>
                <?php elseif($user['role'] == 'admin'): ?>
                <div class="stats-grid">
                    <div class="stat-box"><div class="stat-number"><?php echo $total_users; ?></div><div class="stat-label">Users</div></div>
                    <div class="stat-box"><div class="stat-number"><?php echo $total_events; ?></div><div class="stat-label">Events</div></div>
                    <div class="stat-box"><div class="stat-number"><?php echo $pending_approvals; ?></div><div class="stat-label">Pending</div></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Main form ── -->
            <div class="profile-main">
                <form method="POST" action="" id="profileForm" novalidate>

                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 style="margin-bottom:20px;color:#2d3748;">📝 Personal Information</h3>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                            <!-- Full Name -->
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required maxlength="100"
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                       placeholder="e.g. Rahul Sharma">
                                <div class="field-error" id="full_name_error"></div>
                            </div>

                            <!-- Email -->
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" id="email" name="email" required maxlength="150"
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       placeholder="e.g. rahul@college.edu">
                                <div class="field-error" id="email_error"></div>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                            <!-- Phone -->
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" id="phone" name="phone" maxlength="10"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="10-digit mobile number">
                                <div class="field-error" id="phone_error"></div>
                            </div>

                            <!-- Department – Admin / Organizer -->
                            <?php if($user['role'] == 'admin' || $user['role'] == 'organizer'): ?>
                            <div class="form-group">
                                <label>Department *</label>
                                <select id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <?php
                                    $departments = ['Computer Science','Information Technology','Electronics','Mechanical','Civil','Electrical','Business Administration','Other'];
                                    foreach($departments as $dept): ?>
                                        <option value="<?php echo $dept; ?>" <?php echo $user['department'] == $dept ? 'selected' : ''; ?>>
                                            <?php echo $dept; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-error" id="department_error"></div>
                            </div>
                            <?php endif; ?>

                            <!-- Year – Student only -->
                            <?php if($user['role'] == 'student'): ?>
                            <div class="form-group">
                                <label>Year</label>
                                <select id="year" name="year">
                                    <option value="">Select Year</option>
                                    <option value="First Year"  <?php echo $user['year'] == 'First Year'  ? 'selected' : ''; ?>>First Year</option>
                                    <option value="Second Year" <?php echo $user['year'] == 'Second Year' ? 'selected' : ''; ?>>Second Year</option>
                                    <option value="Third Year"  <?php echo $user['year'] == 'Third Year'  ? 'selected' : ''; ?>>Third Year</option>
                                    <option value="Fourth Year" <?php echo $user['year'] == 'Fourth Year' ? 'selected' : ''; ?>>Fourth Year</option>
                                    <option value="Graduate"    <?php echo $user['year'] == 'Graduate'    ? 'selected' : ''; ?>>Graduate</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Department – Student -->
                        <?php if($user['role'] == 'student'): ?>
                        <div class="form-group">
                            <label>Department *</label>
                            <select id="department" name="department" required>
                                <option value="">Select Department</option>
                                <?php
                                $departments = ['Computer Science','Information Technology','Electronics','Mechanical','Civil','Electrical','Business Administration','Other'];
                                foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" <?php echo $user['department'] == $dept ? 'selected' : ''; ?>>
                                        <?php echo $dept; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="department_error"></div>
                        </div>
                        <?php endif; ?>

                        <!-- Bio -->
                        <div class="form-group">
                            <label>Bio <span style="font-weight:400;color:#718096;">(max 500 characters)</span></label>
                            <textarea id="bio" name="bio" rows="4"
                                      placeholder="Tell us about yourself..."
                                      maxlength="500"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <div class="char-counter" id="bio_counter">
                                <span id="bio_count"><?php echo strlen($user['bio'] ?? ''); ?></span> / 500
                            </div>
                            <div class="field-error" id="bio_error"></div>
                        </div>

                        <div class="info-box">
                            <p style="color:#2d3748;margin:0;font-size:14px;">
                                ℹ️ <strong>Editable Fields:</strong>
                                <?php if($user['role'] == 'admin' || $user['role'] == 'organizer'): ?>
                                    You can edit your name, email, phone, bio, and department.
                                <?php else: ?>
                                    You can edit your name, email, phone, bio, year, and department.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Account Information (Read-only) -->
                    <div class="form-section">
                        <h3 style="margin-bottom:20px;color:#2d3748;">🔐 Account Information</h3>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled>
                            </div>
                        </div>

                        <div style="background:#fffbeb;padding:15px;border-radius:8px;border-left:4px solid #f59e0b;margin-top:15px;">
                            <p style="color:#92400e;margin:0;font-size:14px;">
                                ⚠️ Username and Role cannot be changed. Contact admin if you need to update these fields.
                            </p>
                        </div>
                    </div>

                    <!-- Save -->
                    <div style="display:flex;gap:15px;">
                        <button type="submit" name="update_profile" class="btn btn-primary">✓ Save Changes</button>
                        <a href="../common/home.php" class="btn btn-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>

        <?php include '../includes/footer.php'; ?>
    </main>
</div>

<!-- ──────────────────────────────────────────────────────────────
     CLIENT-SIDE VALIDATION
────────────────────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    // ── helpers ──────────────────────────────────────────────────
    function showError(fieldId, msg) {
        const el  = document.getElementById(fieldId + '_error');
        const inp = document.getElementById(fieldId);
        if (!el || !inp) return;
        el.textContent = msg;
        el.style.display = 'block';
        inp.classList.add('input-invalid');
        inp.classList.remove('input-valid');
    }

    function clearError(fieldId) {
        const el  = document.getElementById(fieldId + '_error');
        const inp = document.getElementById(fieldId);
        if (!el || !inp) return;
        el.style.display = 'none';
        inp.classList.remove('input-invalid');
        inp.classList.add('input-valid');
    }

    function clearAllStates(fieldId) {
        const el  = document.getElementById(fieldId + '_error');
        const inp = document.getElementById(fieldId);
        if (!el || !inp) return;
        el.style.display = 'none';
        inp.classList.remove('input-invalid', 'input-valid');
    }

    // ── individual validators ────────────────────────────────────

    function validateFullName() {
        const val = document.getElementById('full_name').value.trim();
        if (!val) {
            showError('full_name', 'Full name is required.'); return false;
        }
        if (!/^[a-zA-Z\s]{2,100}$/.test(val)) {
            showError('full_name', 'Name must contain only letters and spaces (2–100 characters).'); return false;
        }
        clearError('full_name'); return true;
    }

    function validateEmail() {
        const val = document.getElementById('email').value.trim();
        if (!val) {
            showError('email', 'Email address is required.'); return false;
        }
        // RFC-5322 simplified pattern
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if (!emailRegex.test(val)) {
            showError('email', 'Please enter a valid email address (e.g. name@domain.com).'); return false;
        }
        clearError('email'); return true;
    }

    function validatePhone() {
        const inp = document.getElementById('phone');
        if (!inp) return true; // field may not exist
        const val = inp.value.trim();
        if (!val) { clearAllStates('phone'); return true; } // optional field

        // Allow only digits
        if (!/^\d+$/.test(val)) {
            showError('phone', 'Phone number must contain digits only.'); return false;
        }
        if (val.length !== 10) {
            showError('phone', 'Phone number must be exactly 10 digits.'); return false;
        }
        // Indian mobile: starts with 6, 7, 8, or 9
        if (!/^[6-9]/.test(val)) {
            showError('phone', 'Indian mobile numbers must start with 6, 7, 8, or 9.'); return false;
        }
        clearError('phone'); return true;
    }

    function validateDepartment() {
        const inp = document.getElementById('department');
        if (!inp) return true;
        const val = inp.value;
        if (!val || val === '') {
            showError('department', 'Please select a department.'); return false;
        }
        clearError('department'); return true;
    }

    function validateBio() {
        const inp = document.getElementById('bio');
        if (!inp) return true;
        if (inp.value.length > 500) {
            showError('bio', 'Bio must not exceed 500 characters.'); return false;
        }
        clearAllStates('bio'); return true;
    }

    // ── real-time feedback ───────────────────────────────────────

    const fullNameEl = document.getElementById('full_name');
    if (fullNameEl) {
        fullNameEl.addEventListener('input', function () {
            // Block non-letter, non-space keystrokes
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            if (this.value.trim()) validateFullName();
            else clearAllStates('full_name');
        });
        fullNameEl.addEventListener('blur', validateFullName);
    }

    const emailEl = document.getElementById('email');
    if (emailEl) {
        emailEl.addEventListener('blur', validateEmail);
        emailEl.addEventListener('input', function () {
            if (!this.value.trim()) clearAllStates('email');
        });
    }

    const phoneEl = document.getElementById('phone');
    if (phoneEl) {
        phoneEl.addEventListener('input', function () {
            // Strip non-digits as user types
            this.value = this.value.replace(/\D/g, '');
            // Enforce 10-digit max
            if (this.value.length > 10) this.value = this.value.slice(0, 10);
            if (this.value) validatePhone();
            else clearAllStates('phone');
        });
        phoneEl.addEventListener('blur', validatePhone);
    }

    const deptEl = document.getElementById('department');
    if (deptEl) {
        deptEl.addEventListener('change', function () {
            if (this.value) validateDepartment();
            else clearAllStates('department');
        });
    }

    const bioEl = document.getElementById('bio');
    if (bioEl) {
        bioEl.addEventListener('input', function () {
            const len     = this.value.length;
            const counter = document.getElementById('bio_count');
            const wrapper = document.getElementById('bio_counter');
            if (counter) counter.textContent = len;
            if (wrapper) wrapper.classList.toggle('over-limit', len > 500);
            validateBio();
        });
    }

    // ── form submit guard ────────────────────────────────────────
    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            const ok = [
                validateFullName(),
                validateEmail(),
                validatePhone(),
                validateDepartment(),
                validateBio()
            ].every(Boolean);

            if (!ok) {
                e.preventDefault();
                // Scroll to first visible error
                const firstError = form.querySelector('.input-invalid');
                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    // ── image auto-submit ────────────────────────────────────────
    const imgInput = document.getElementById('profileImageInput');
    if (imgInput) {
        imgInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                // Basic client-side size check (2 MB)
                if (this.files[0].size > 2 * 1024 * 1024) {
                    alert('Image must not exceed 2 MB. Please choose a smaller file.');
                    this.value = '';
                    return;
                }
                document.getElementById('uploadBtn').click();
            }
        });
    }

})();
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>