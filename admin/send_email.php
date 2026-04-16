<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$error = "";
$success = "";
$user_id = null;

// Get user ID from URL or POST
if(isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
} elseif(isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
}

// Fetch user details if user_id is provided
$user = null;
if($user_id) {
    $user_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND role != 'admin'");
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    
    if(mysqli_num_rows($user_result) > 0) {
        $user = mysqli_fetch_assoc($user_result);
    }
    mysqli_stmt_close($user_stmt);
}

// Handle email sending
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_email'])) {
    $recipient_id = intval($_POST['user_id']);
    $custom_message = trim($_POST['custom_message']);
    $send_password = isset($_POST['send_password']) ? 1 : 0;
    $temp_password = trim($_POST['temp_password']);
    
    // Validate password if checkbox is checked
    if($send_password && empty($temp_password)) {
        $error = "Please enter a temporary password!";
    } else {
        // Fetch recipient details
        $recipient_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND role != 'admin'");
        mysqli_stmt_bind_param($recipient_stmt, "i", $recipient_id);
        mysqli_stmt_execute($recipient_stmt);
        $recipient_result = mysqli_stmt_get_result($recipient_stmt);
        
        if(mysqli_num_rows($recipient_result) == 0) {
            $error = "User not found!";
        } else {
            $recipient = mysqli_fetch_assoc($recipient_result);
            
            // Update password in database if provided
            if($send_password && !empty($temp_password)) {
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $recipient_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            
            // Prepare email content
            $to = $recipient['email'];
            $to_name = $recipient['full_name'];
            $subject = "Your Campus Event Manager Account Credentials";
            
            // Build credentials content
            $credentials_html = "
            <div class='info-box'>
                <div class='info-item'>
                    <div class='info-label'>Username</div>
                    <div class='info-value'>" . htmlspecialchars($recipient['username']) . "</div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Email</div>
                    <div class='info-value'>" . htmlspecialchars($recipient['email']) . "</div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Account Type</div>
                    <div class='info-value'>" . ucfirst($recipient['role']) . "</div>
                </div>";
            
            if($send_password && !empty($temp_password)) {
                $credentials_html .= "
                <div class='info-item' style='margin-top: 20px; padding-top: 20px; border-top: 2px solid #e2e8f0;'>
                    <div class='info-label'>Password</div>
                    <div class='info-value' style='background: #fff3cd; padding: 12px; border-radius: 6px; color: #856404; font-family: monospace; font-size: 18px; letter-spacing: 1px;'>" . htmlspecialchars($temp_password) . "</div>
                    <p style='color: #c53030; font-size: 14px; margin-top: 10px;'>
                        <strong>⚠️ Important:</strong> Please change this password immediately after logging in!
                    </p>
                </div>";
            }
            
            $credentials_html .= "</div>";
            
            // Custom message section
            $custom_msg_html = "";
            if(!empty($custom_message)) {
                $custom_msg_html = "
                <div style='background: #f7fafc; border-left: 4px solid #667eea; padding: 20px; margin: 25px 0; border-radius: 6px;'>
                    <strong style='color: #2d3748; font-size: 15px;'>📝 Message from Admin:</strong>
                    <p style='color: #4a5568; margin: 10px 0 0 0; line-height: 1.7;'>" . nl2br(htmlspecialchars($custom_message)) . "</p>
                </div>";
            }
            
            // Login button
            $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                         '://' . $_SERVER['HTTP_HOST'] . 
                         str_replace('/admin/send_email.php', '/login.php', $_SERVER['PHP_SELF']);
            
            $content = "
            <p style='font-size: 16px; color: #2d3748; margin-bottom: 20px;'>
                Hello <strong>" . htmlspecialchars($recipient['full_name']) . "</strong>,
            </p>
            <p style='color: #4a5568; line-height: 1.8;'>
                Your account has been successfully created on the Campus Event Manager platform. 
                Below are your login credentials:
            </p>
            
            " . $credentials_html . "
            
            " . $custom_msg_html . "
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . $login_url . "' class='button'>
                    🔐 Login to Your Account
                </a>
            </div>
            
            <div style='background: #e6f7ff; border-left: 4px solid #1890ff; padding: 15px; border-radius: 6px; margin-top: 25px;'>
                <p style='margin: 0; color: #0050b3; font-size: 14px;'>
                    <strong>💡 Getting Started:</strong><br>
                    " . ($recipient['role'] == 'student' ? 
                        "Browse upcoming events, register for activities, and save your favorites!" : 
                        "Create and manage events, track registrations, and engage with students!") . "
                </p>
            </div>";
            
            $html_body = getEmailTemplate("Welcome to Campus Event Manager!", $content);
            
            // Send email using configured method
            if(sendEmail($to, $to_name, $subject, $html_body)) {
                $success = "✅ Email sent successfully to " . htmlspecialchars($recipient['full_name']) . " (" . htmlspecialchars($recipient['email']) . ")";
                
                // Log the email
                logEmail($_SESSION['user_id'], $to, $subject);
            } else {
                $error = "❌ Failed to send email. Please check your email configuration in config/email_config.php";
            }
        }
        mysqli_stmt_close($recipient_stmt);
    }
}

// Fetch all users for dropdown (if no specific user)
$all_users_query = "SELECT id, full_name, email, username, role FROM users WHERE role != 'admin' ORDER BY full_name";
$all_users_result = mysqli_query($conn, $all_users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Credentials Email - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .password-toggle {
            position: relative;
        }
        .password-toggle input {
            padding-right: 45px;
        }
        .password-toggle .toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            background: none;
            border: none;
            color: #667eea;
            font-size: 20px;
        }
        .email-preview {
            background: #f7fafc;
            padding: 25px;
            border-radius: 10px;
            border: 2px dashed #cbd5e0;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>📧 Send Credentials Email</h1>
                <div style="display: flex; gap: 10px;">
                    <?php if($user_id): ?>
                        <a href="view_user.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">← Back to User</a>
                    <?php endif; ?>
                    <a href="manage_users.php" class="btn btn-secondary">View All Users</a>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="">
                    <?php if($user): ?>
                        <!-- Preselected User -->
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        
                        <div style="background: #f7fafc; padding: 20px; border-radius: 10px; border-left: 4px solid #667eea; margin-bottom: 25px;">
                            <h3 style="margin-top: 0; color: #2d3748; display: flex; align-items: center; gap: 10px;">
                                <span>📤</span> Sending credentials to:
                            </h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                                <div>
                                    <div style="color: #718096; font-size: 13px; margin-bottom: 5px;">Full Name</div>
                                    <div style="font-weight: 600; color: #2d3748; font-size: 16px;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                </div>
                                <div>
                                    <div style="color: #718096; font-size: 13px; margin-bottom: 5px;">Email Address</div>
                                    <div style="font-weight: 600; color: #2d3748; font-size: 16px;"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <div>
                                    <div style="color: #718096; font-size: 13px; margin-bottom: 5px;">Username</div>
                                    <div style="font-weight: 600; color: #2d3748; font-size: 16px;"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div>
                                    <div style="color: #718096; font-size: 13px; margin-bottom: 5px;">Account Type</div>
                                    <span class="role-badge role-<?php echo $user['role']; ?>" style="font-size: 14px; padding: 6px 14px;">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- User Selection -->
                        <div class="form-group">
                            <label>Select User to Send Credentials *</label>
                            <select name="user_id" required style="padding: 12px; font-size: 15px;">
                                <option value="">-- Choose a user --</option>
                                <?php while($usr = mysqli_fetch_assoc($all_users_result)): ?>
                                    <option value="<?php echo $usr['id']; ?>">
                                        <?php echo htmlspecialchars($usr['full_name']); ?> 
                                        (<?php echo htmlspecialchars($usr['username']); ?>) - 
                                        <?php echo ucfirst($usr['role']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Password Option -->
                    <div style="background: #fff3cd; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; font-weight: 600; color: #856404;">
                            <input type="checkbox" name="send_password" id="sendPasswordCheck" onchange="togglePasswordField()">
                            <span>🔒 Include password in email (and update user's password)</span>
                        </label>
                        
                        <div id="passwordField" style="display: none; margin-top: 15px;">
                            <label style="color: #856404; font-weight: 600; display: block; margin-bottom: 8px;">
                                New Password *
                            </label>
                            <div class="password-toggle">
                                <input type="password" 
                                       name="temp_password" 
                                       id="tempPassword" 
                                       placeholder="Enter a new password (min 6 characters)"
                                       style="width: 100%; padding: 12px; border: 2px solid #ffc107; border-radius: 8px; font-size: 15px;">
                                <button type="button" class="toggle-btn" onclick="togglePasswordVisibility()">
                                    👁️
                                </button>
                            </div>
                            <small style="color: #856404; display: block; margin-top: 8px;">
                                ⚠️ This will update the user's password in the database and send it via email
                            </small>
                        </div>
                    </div>
                    
                    <!-- Custom Message -->
                    <div class="form-group">
                        <label>Custom Message (Optional)</label>
                        <textarea name="custom_message" 
                                  rows="5" 
                                  placeholder="Add a personalized welcome message or special instructions..."
                                  style="width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 15px; line-height: 1.6;"></textarea>
                        <small style="color: #718096;">This message will appear in the email with the credentials.</small>
                    </div>
                    
                    <!-- Email Preview -->
                    <div class="email-preview">
                        <h4 style="margin-top: 0; color: #2d3748; display: flex; align-items: center; gap: 10px;">
                            <span>📋</span> Email Will Include:
                        </h4>
                        <ul style="color: #4a5568; line-height: 2; margin: 15px 0;">
                            <li>✅ Username and email address</li>
                            <li>✅ Account type (Student/Organizer)</li>
                            <li id="passwordInclude" style="display: none;">✅ Password (will be updated in database)</li>
                            <li>✅ Your custom welcome message</li>
                            <li>✅ Direct login link to the platform</li>
                            <li>✅ Getting started instructions</li>
                        </ul>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="send_email" class="btn btn-primary" style="font-size: 16px; padding: 14px 28px;">
                            📧 Send Email Now
                        </button>
                        <?php if($user_id): ?>
                            <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Email Configuration Notice -->
            <!-- <div style="background: #e6f7ff; border-left: 4px solid #1890ff; padding: 20px; border-radius: 8px; margin-top: 20px;">
                <h4 style="margin-top: 0; color: #0050b3; display: flex; align-items: center; gap: 10px;">
                    <span>ℹ️</span> Email Configuration
                </h4>
                <p style="color: #0050b3; margin: 0; line-height: 1.7;">
                    Currently using: <strong><?php echo strtoupper(EMAIL_METHOD); ?></strong> email method.<br>
                    To change email provider, edit <code>config/email_config.php</code>
                </p>
            </div> -->
        </main>
    </div>
    
    <script>
        function togglePasswordField() {
            const checkbox = document.getElementById('sendPasswordCheck');
            const passwordField = document.getElementById('passwordField');
            const tempPassword = document.getElementById('tempPassword');
            const passwordInclude = document.getElementById('passwordInclude');
            
            if(checkbox.checked) {
                passwordField.style.display = 'block';
                tempPassword.required = true;
                passwordInclude.style.display = 'list-item';
            } else {
                passwordField.style.display = 'none';
                tempPassword.required = false;
                passwordInclude.style.display = 'none';
            }
        }
        
        function togglePasswordVisibility() {
            const input = document.getElementById('tempPassword');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>