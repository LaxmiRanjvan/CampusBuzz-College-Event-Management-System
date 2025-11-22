<?php
session_start();
require_once '../config/database.php';

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
    
    // Fetch recipient details
    $recipient_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND role != 'admin'");
    mysqli_stmt_bind_param($recipient_stmt, "i", $recipient_id);
    mysqli_stmt_execute($recipient_stmt);
    $recipient_result = mysqli_stmt_get_result($recipient_stmt);
    
    if(mysqli_num_rows($recipient_result) == 0) {
        $error = "User not found!";
    } else {
        $recipient = mysqli_fetch_assoc($recipient_result);
        
        // Prepare email content
        $to = $recipient['email'];
        $subject = "Your Campus Event Manager Account Credentials";
        
        // Create email body
        $message = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f7fafc; padding: 30px; border-radius: 0 0 10px 10px; }
        .credentials-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
        .credential-item { margin: 15px 0; }
        .credential-label { color: #718096; font-size: 0.9rem; margin-bottom: 5px; }
        .credential-value { font-size: 1.1rem; font-weight: 600; color: #2d3748; background: #e2e8f0; padding: 10px; border-radius: 5px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; color: #718096; font-size: 0.9rem; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1 style='margin: 0;'>🎓 Campus Event Manager</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>Your Account Credentials</p>
        </div>
        
        <div class='content'>
            <p>Hello <strong>" . htmlspecialchars($recipient['full_name']) . "</strong>,</p>
            
            <p>Your account has been created on the Campus Event Manager platform. Below are your login credentials:</p>
            
            <div class='credentials-box'>
                <div class='credential-item'>
                    <div class='credential-label'>Username:</div>
                    <div class='credential-value'>" . htmlspecialchars($recipient['username']) . "</div>
                </div>
                
                <div class='credential-item'>
                    <div class='credential-label'>Email:</div>
                    <div class='credential-value'>" . htmlspecialchars($recipient['email']) . "</div>
                </div>
                
                <div class='credential-item'>
                    <div class='credential-label'>Account Type:</div>
                    <div class='credential-value'>" . ucfirst($recipient['role']) . "</div>
                </div>
            </div>
            
            " . (!empty($custom_message) ? "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;'><strong>Note from Admin:</strong><br>" . nl2br(htmlspecialchars($custom_message)) . "</div>" : "") . "
            
            <p><strong>Important:</strong> Please change your password after logging in for the first time.</p>
            
            <div style='text-align: center;'>
                <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/login.php' class='button'>
                    Login to Your Account
                </a>
            </div>
            
            <div class='footer'>
                <p>This is an automated message from Campus Event Manager.<br>
                If you have any questions, please contact the administrator.</p>
                <p style='color: #a0aec0; font-size: 0.85rem;'>Sent on " . date('F d, Y \a\t h:i A') . "</p>
            </div>
        </div>
    </div>
</body>
</html>
        ";
        
        // Email headers for HTML
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Campus Event Manager <noreply@campus-events.com>" . "\r\n";
        
        // Send email
        if(mail($to, $subject, $message, $headers)) {
            $success = "Email sent successfully to " . htmlspecialchars($recipient['full_name']) . " (" . htmlspecialchars($recipient['email']) . ")";
            
            // Log the email send in database (optional - you can create an email_logs table)
            $log_stmt = mysqli_prepare($conn, "INSERT INTO email_logs (user_id, recipient_email, subject, sent_date) VALUES (?, ?, ?, NOW())");
            if($log_stmt) {
                mysqli_stmt_bind_param($log_stmt, "iss", $recipient_id, $to, $subject);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
            }
        } else {
            $error = "Failed to send email. Please check your server's email configuration.";
        }
    }
    mysqli_stmt_close($recipient_stmt);
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
    <title>Send Email - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .user-select-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .selected-user-info {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }
        
        .email-preview {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 2px dashed #cbd5e0;
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
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="">
                    <?php if($user): ?>
                        <!-- If user is preselected from edit page -->
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        
                        <div class="selected-user-info">
                            <h3 style="margin-top: 0; color: #2d3748;">📤 Sending credentials to:</h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                                <div>
                                    <div style="color: #718096; font-size: 0.9rem;">Name:</div>
                                    <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                </div>
                                <div>
                                    <div style="color: #718096; font-size: 0.9rem;">Email:</div>
                                    <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <div>
                                    <div style="color: #718096; font-size: 0.9rem;">Username:</div>
                                    <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div>
                                    <div style="color: #718096; font-size: 0.9rem;">Role:</div>
                                    <div>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- User selection dropdown -->
                        <div class="form-group">
                            <label>Select User to Send Credentials *</label>
                            <select name="user_id" required style="padding: 12px; width: 100%; border: 1px solid #e2e8f0; border-radius: 8px;">
                                <option value="">-- Select a user --</option>
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
                    
                    <!-- Custom Message -->
                    <div class="form-group">
                        <label>Custom Message (Optional)</label>
                        <textarea name="custom_message" rows="4" placeholder="Add a personalized message to include in the email..." style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit;"></textarea>
                        <small style="color: #718096;">This message will be included in the email along with the credentials.</small>
                    </div>
                    
                    <!-- Email Preview Info -->
                    <div class="email-preview">
                        <h4 style="margin-top: 0; color: #2d3748;">📋 Email Will Include:</h4>
                        <ul style="color: #4a5568; line-height: 1.8;">
                            <li>Username and Email address</li>
                            <li>Account type (Student/Organizer)</li>
                            <li>Your custom message (if provided)</li>
                            <li>Login link to the platform</li>
                            <li>Instructions to change password</li>
                        </ul>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="send_email" class="btn btn-primary">
                            📧 Send Email
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
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-top: 20px;">
                <h4 style="margin-top: 0; color: #856404;">⚠️ Email Configuration</h4>
                <p style="color: #856404; margin: 0;">
                    Make sure your server is configured to send emails. For testing purposes, you can use services like:
                    <strong>PHPMailer, SendGrid, Mailgun, or SMTP configuration</strong>.
                </p>
            </div>
        </main>
    </div>
</body>
</html>