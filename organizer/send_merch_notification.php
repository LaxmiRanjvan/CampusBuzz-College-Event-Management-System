<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error = "";
$success = "";
$merch_id = null;

// Get merchandise ID from URL or POST
if(isset($_GET['merch_id']) && is_numeric($_GET['merch_id'])) {
    $merch_id = intval($_GET['merch_id']);
} elseif(isset($_POST['merch_id']) && is_numeric($_POST['merch_id'])) {
    $merch_id = intval($_POST['merch_id']);
}

// Fetch merchandise details if merch_id is provided
$merch = null;
if($merch_id) {
    $merch_stmt = mysqli_prepare($conn, "SELECT m.*, 
                                   (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image
                                   FROM merchandise m 
                                   WHERE m.id = ? AND m.organizer_id = ?");
    mysqli_stmt_bind_param($merch_stmt, "ii", $merch_id, $organizer_id);
    mysqli_stmt_execute($merch_stmt);
    $merch_result = mysqli_stmt_get_result($merch_stmt);
    
    if(mysqli_num_rows($merch_result) > 0) {
        $merch = mysqli_fetch_assoc($merch_result);
    }
    mysqli_stmt_close($merch_stmt);
}

// Handle notification sending
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $selected_merch_id = intval($_POST['merch_id']);
    $notification_type = $_POST['notification_type'];
    $custom_subject = trim($_POST['custom_subject']);
    $custom_message = trim($_POST['custom_message']);
    $recipient_filter = $_POST['recipient_filter'];
    $department_filter = isset($_POST['department']) ? mysqli_real_escape_string($conn, $_POST['department']) : '';
    
    // Fetch merchandise
    $merch_stmt = mysqli_prepare($conn, "SELECT m.*, 
                                  (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image
                                  FROM merchandise m 
                                  WHERE m.id = ? AND m.organizer_id = ?");
    mysqli_stmt_bind_param($merch_stmt, "ii", $selected_merch_id, $organizer_id);
    mysqli_stmt_execute($merch_stmt);
    $merch_result = mysqli_stmt_get_result($merch_stmt);
    
    if(mysqli_num_rows($merch_result) == 0) {
        $error = "Merchandise not found or you don't have permission!";
    } else {
        $merch_data = mysqli_fetch_assoc($merch_result);
        
        // Build recipient query based on filter
        $recipients_query = "SELECT * FROM users WHERE role = 'student'";
        
        if($recipient_filter == 'department' && !empty($department_filter)) {
            $recipients_query .= " AND department = '$department_filter'";
        }
        
        $recipients_result = mysqli_query($conn, $recipients_query);
        
        if(mysqli_num_rows($recipients_result) == 0) {
            $error = "No recipients found matching your filter criteria!";
        } else {
            $sent_count = 0;
            $failed_count = 0;
            
            while($recipient = mysqli_fetch_assoc($recipients_result)) {
                // Prepare subject and content based on type
                switch($notification_type) {
                    case 'low_stock':
                        $subject = !empty($custom_subject) ? $custom_subject : "⚠️ Limited Stock: " . $merch_data['name'];
                        $emoji = "⏰";
                        $title = "Limited Stock Alert";
                        $heading = "Hurry! Only " . $merch_data['quantity_available'] . " items left!";
                        $badge_color = "#ed8936";
                        break;
                        
                    case 'new_arrival':
                        $subject = !empty($custom_subject) ? $custom_subject : "🎉 New Arrival: " . $merch_data['name'];
                        $emoji = "✨";
                        $title = "New Product Available";
                        $heading = "Check out our latest merchandise!";
                        $badge_color = "#48bb78";
                        break;
                        
                    case 'sale':
                        $subject = !empty($custom_subject) ? $custom_subject : "🔥 Special Offer: " . $merch_data['name'];
                        $emoji = "💰";
                        $title = "Special Sale";
                        $heading = "Don't miss this special offer!";
                        $badge_color = "#f56565";
                        break;
                        
                    case 'restock':
                        $subject = !empty($custom_subject) ? $custom_subject : "🔄 Back in Stock: " . $merch_data['name'];
                        $emoji = "📦";
                        $title = "Product Restocked";
                        $heading = "Your favorite item is back in stock!";
                        $badge_color = "#667eea";
                        break;
                        
                    default: // custom
                        $subject = $custom_subject;
                        $emoji = "📢";
                        $title = "Merchandise Update";
                        $heading = "Important announcement about our merchandise";
                        $badge_color = "#667eea";
                }
                
                // Build product card
                $product_image = "";
                if($merch_data['primary_image']) {
                    $image_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                                '://' . $_SERVER['HTTP_HOST'] . 
                                str_replace('/organizer/send_merch_notification.php', '/uploads/merchandise/' . $merch_data['primary_image'], $_SERVER['PHP_SELF']);
                    $product_image = "<img src='" . $image_url . "' alt='Product' style='width: 100%; height: 250px; object-fit: cover; border-radius: 10px; margin-bottom: 15px;'>";
                }
                
                $product_card = "
                <div style='background: white; padding: 25px; border-radius: 12px; margin: 25px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    " . $product_image . "
                    
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <span style='display: inline-block; padding: 6px 14px; background: #bee3f8; color: #2c5282; border-radius: 15px; font-size: 12px; font-weight: 600; margin-bottom: 12px;'>
                            " . ucwords(str_replace('-', ' ', $merch_data['category'])) . "
                        </span>
                        <h2 style='margin: 10px 0; color: #2d3748; font-size: 26px;'>" . htmlspecialchars($merch_data['name']) . "</h2>
                        <div style='font-size: 32px; font-weight: 700; color: #667eea; margin: 15px 0;'>
                            ₹" . number_format($merch_data['price'], 2) . "
                        </div>
                    </div>
                    
                    <div style='background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px;'>
                        <p style='color: #4a5568; line-height: 1.6; margin: 0;'>
                            " . nl2br(htmlspecialchars($merch_data['description'])) . "
                        </p>
                    </div>
                    
                    <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 15px 0;'>
                        <div style='background: " . ($merch_data['quantity_available'] > 0 ? '#c6f6d5' : '#fed7d7') . "; padding: 12px; border-radius: 8px; text-align: center;'>
                            <div style='font-size: 11px; color: " . ($merch_data['quantity_available'] > 0 ? '#276749' : '#c53030') . "; margin-bottom: 4px;'>STOCK STATUS</div>
                            <div style='font-weight: 700; color: " . ($merch_data['quantity_available'] > 0 ? '#276749' : '#c53030') . "; font-size: 16px;'>
                                " . ($merch_data['quantity_available'] > 0 ? $merch_data['quantity_available'] . " Available" : "Out of Stock") . "
                            </div>
                        </div>";
                
                if(!empty($merch_data['sizes_available'])) {
                    $product_card .= "
                        <div style='background: #e6e9fc; padding: 12px; border-radius: 8px; text-align: center;'>
                            <div style='font-size: 11px; color: #5a67d8; margin-bottom: 4px;'>SIZES</div>
                            <div style='font-weight: 600; color: #5a67d8; font-size: 14px;'>
                                " . htmlspecialchars($merch_data['sizes_available']) . "
                            </div>
                        </div>";
                }
                
                $product_card .= "
                    </div>";
                
                // Distribution info
                if($merch_data['distribution_date'] || $merch_data['distribution_venue']) {
                    $product_card .= "
                    <div style='background: #fff3cd; padding: 12px; border-radius: 8px; margin: 15px 0;'>
                        <div style='font-size: 12px; font-weight: 600; color: #856404; margin-bottom: 8px;'>🚚 DISTRIBUTION</div>";
                    
                    if($merch_data['distribution_date']) {
                        $product_card .= "
                        <div style='color: #856404; font-size: 13px; margin-bottom: 4px;'>
                            📅 " . date('M d, Y', strtotime($merch_data['distribution_date']));
                        if($merch_data['distribution_time']) {
                            $product_card .= " @ " . date('h:i A', strtotime($merch_data['distribution_time']));
                        }
                        $product_card .= "</div>";
                    }
                    
                    if($merch_data['distribution_venue']) {
                        $product_card .= "
                        <div style='color: #856404; font-size: 13px;'>
                            📍 " . htmlspecialchars($merch_data['distribution_venue']) . "
                        </div>";
                    }
                    
                    $product_card .= "</div>";
                }
                
                $product_card .= "</div>";
                
                // Custom message section
                $message_html = "";
                if(!empty($custom_message)) {
                    $message_html = "
                    <div style='background: #f7fafc; padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #667eea;'>
                        <h4 style='margin-top: 0; color: #2d3748;'>📝 Special Message:</h4>
                        <p style='color: #4a5568; line-height: 1.8; margin: 10px 0 0 0;'>" . nl2br(htmlspecialchars($custom_message)) . "</p>
                    </div>";
                }
                
                // Order button
                $merch_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                            '://' . $_SERVER['HTTP_HOST'] . 
                            str_replace('/organizer/send_merch_notification.php', '/student/view_merchandise.php?id=' . $merch_data['id'], $_SERVER['PHP_SELF']);
                
                $order_button = "";
                if($merch_data['quantity_available'] > 0) {
                    $order_button = "
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $merch_url . "' class='button' style='display: inline-block; padding: 15px 40px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                            🛒 View Product & Order Now
                        </a>
                    </div>";
                }
                
                $content = "
                <p style='font-size: 16px; color: #2d3748; margin-bottom: 20px;'>
                    Hello <strong>" . htmlspecialchars($recipient['full_name']) . "</strong>,
                </p>
                <p style='color: #4a5568; line-height: 1.8;'>
                    " . htmlspecialchars($heading) . "
                </p>
                
                " . $product_card . "
                " . $message_html . "
                " . $order_button;
                
                // Add urgency note for low stock
                if($notification_type == 'low_stock') {
                    $content .= "
                    <div style='background: #fed7d7; border-left: 4px solid #f56565; padding: 20px; border-radius: 8px; margin-top: 20px;'>
                        <p style='margin: 0; color: #c53030; font-weight: 600;'>
                            ⚠️ Limited quantity available! Order now before it's gone.
                        </p>
                    </div>";
                }
                
                // Add contact info
                $content .= "
                <div style='background: #f7fafc; padding: 15px; border-radius: 8px; margin-top: 20px;'>
                    <p style='margin: 0; color: #4a5568; font-size: 14px;'>
                        <strong>📞 Contact:</strong> " . htmlspecialchars($merch_data['contact_info']) . "
                    </p>
                </div>";
                
                $html_body = getEmailTemplate($title, $content, "This is a promotional email about campus merchandise.");
                
                // Send email
                if(sendEmail($recipient['email'], $recipient['full_name'], $subject, $html_body)) {
                    $sent_count++;
                    logEmail($organizer_id, $recipient['email'], $subject);
                } else {
                    $failed_count++;
                }
            }
            
            if($sent_count > 0) {
                $success = "✅ Successfully sent notification to $sent_count student(s)!";
                if($failed_count > 0) {
                    $success .= " ($failed_count failed)";
                }
            } else {
                $error = "❌ Failed to send notifications. Please check your email configuration.";
            }
        }
    }
    mysqli_stmt_close($merch_stmt);
}

// Fetch all merchandise by this organizer
$merch_list_query = "SELECT m.id, m.name, m.quantity_available, m.price,
                     (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image
                     FROM merchandise m 
                     WHERE m.organizer_id = ? 
                     ORDER BY m.created_at DESC";
$merch_stmt = mysqli_prepare($conn, $merch_list_query);
mysqli_stmt_bind_param($merch_stmt, "i", $organizer_id);
mysqli_stmt_execute($merch_stmt);
$merch_list_result = mysqli_stmt_get_result($merch_stmt);

// Fetch departments for filter
$dept_query = "SELECT DISTINCT department FROM users WHERE role = 'student' AND department IS NOT NULL AND department != '' ORDER BY department";
$dept_result = mysqli_query($conn, $dept_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Merchandise Notification - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>📢 Send Merchandise Notification</h1>
                <div style="display: flex; gap: 10px;">
                    <?php if($merch_id): ?>
                        <a href="view_merchandise.php?id=<?php echo $merch_id; ?>" class="btn btn-secondary">← Back to Product</a>
                    <?php endif; ?>
                    <a href="manage_merchandise.php" class="btn btn-secondary">My Products</a>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Info Banner -->
            <div style="background: #e6fffa; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #38b2ac;">
                <h3 style="margin-top: 0; color: #234e52;">ℹ️ About Merchandise Notifications</h3>
                <p style="color: #234e52; margin: 10px 0 0 0; line-height: 1.6;">
                    Send promotional emails to students about your merchandise. You can:
                </p>
                <ul style="color: #234e52; margin: 10px 0 0 20px;">
                    <li>Announce low stock or limited availability</li>
                    <li>Promote new arrivals and special sales</li>
                    <li>Notify about restocked items</li>
                    <li>Target all students or specific departments</li>
                </ul>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="">
                    
                    <!-- Merchandise Selection -->
                    <?php if($merch): ?>
                        <input type="hidden" name="merch_id" value="<?php echo $merch['id']; ?>">
                        
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; margin-bottom: 25px; color: white;">
                            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                                <span>🛍️</span> Promoting:
                            </h3>
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <?php if($merch['primary_image']): ?>
                                    <img src="../uploads/merchandise/<?php echo htmlspecialchars($merch['primary_image']); ?>" 
                                         style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 3px solid white;">
                                <?php endif; ?>
                                <div style="flex: 1;">
                                    <div style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">
                                        <?php echo htmlspecialchars($merch['name']); ?>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; opacity: 0.95;">
                                        <div>
                                            <div style="font-size: 12px; opacity: 0.8;">Price</div>
                                            <div style="font-weight: 600;">₹<?php echo number_format($merch['price'], 2); ?></div>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; opacity: 0.8;">Stock</div>
                                            <div style="font-weight: 600;"><?php echo $merch['quantity_available']; ?> available</div>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; opacity: 0.8;">Category</div>
                                            <div style="font-weight: 600;"><?php echo ucwords(str_replace('-', ' ', $merch['category'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Select Product *</label>
                            <select name="merch_id" required onchange="window.location.href='send_merch_notification.php?merch_id=' + this.value" style="padding: 12px; font-size: 15px;">
                                <option value="">-- Choose a product --</option>
                                <?php while($m = mysqli_fetch_assoc($merch_list_result)): ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo htmlspecialchars($m['name']); ?> 
                                        (₹<?php echo number_format($m['price'], 2); ?>) - 
                                        <?php echo $m['quantity_available']; ?> in stock
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($merch): ?>
                    
                    <!-- Notification Type -->
                    <div class="form-group">
                        <label>Notification Type *</label>
                        <select name="notification_type" required onchange="updateTemplate(this.value)" style="padding: 12px; font-size: 15px;">
                            <option value="low_stock">⏰ Low Stock Alert (Only <?php echo $merch['quantity_available']; ?> left!)</option>
                            <option value="new_arrival">✨ New Arrival Announcement</option>
                            <option value="sale">🔥 Special Sale/Offer</option>
                            <option value="restock">📦 Back in Stock</option>
                            <option value="custom">📧 Custom Message</option>
                        </select>
                    </div>
                    
                    <!-- Email Subject -->
                    <div class="form-group">
                        <label>Email Subject *</label>
                        <input type="text" 
                               name="custom_subject" 
                               id="emailSubject" 
                               required 
                               placeholder="Subject line for the email"
                               style="padding: 12px; font-size: 15px;">
                    </div>
                    
                    <!-- Custom Message -->
                    <div class="form-group">
                        <label>Your Message *</label>
                        <textarea name="custom_message" 
                                  rows="6" 
                                  required
                                  placeholder="Add special details, promotional text, or instructions..."
                                  style="width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 15px; line-height: 1.6;"></textarea>
                        <small style="color: #718096;">This message will be prominently displayed with the product details.</small>
                    </div>
                    
                    <!-- Recipient Filter -->
                    <div style="margin-bottom: 25px;">
                        <label style="font-weight: 600; color: #2d3748; display: block; margin-bottom: 12px;">
                            Target Audience *
                        </label>
                        
                        <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border: 2px solid #e2e8f0;">
                            <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: white; border-radius: 6px; cursor: pointer; margin-bottom: 10px;">
                                <input type="radio" name="recipient_filter" value="all" checked onchange="toggleDepartmentFilter()">
                                <div>
                                    <strong style="color: #2d3748;">All Students</strong>
                                    <div style="font-size: 13px; color: #718096;">Send to all registered students</div>
                                </div>
                            </label>
                            
                            <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: white; border-radius: 6px; cursor: pointer;">
                                <input type="radio" name="recipient_filter" value="department" onchange="toggleDepartmentFilter()">
                                <div>
                                    <strong style="color: #2d3748;">Specific Department</strong>
                                    <div style="font-size: 13px; color: #718096;">Target students from a specific department</div>
                                </div>
                            </label>
                            
                            <div id="departmentSelect" style="margin-top: 15px; display: none;">
                                <select name="department" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;">
                                    <option value="">-- Select Department --</option>
                                    <?php 
                                    mysqli_data_seek($dept_result, 0);
                                    while($dept = mysqli_fetch_assoc($dept_result)): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                            <?php echo htmlspecialchars($dept['department']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="send_notification" class="btn btn-primary" style="font-size: 16px; padding: 14px 28px;">
                            📧 Send Notification
                        </button>
                        <a href="manage_merchandise.php" class="btn btn-secondary">Cancel</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        function updateTemplate(type) {
            const subjectField = document.getElementById('emailSubject');
            const merchName = "<?php echo isset($merch['name']) ? addslashes($merch['name']) : 'Product'; ?>";
            const stockQty = "<?php echo isset($merch['quantity_available']) ? $merch['quantity_available'] : '0'; ?>";
            
            switch(type) {
                case 'low_stock':
                    subjectField.value = "⚠️ Limited Stock: " + merchName + " - Only " + stockQty + " Left!";
                    break;
                case 'new_arrival':
                    subjectField.value = "🎉 New Arrival: " + merchName + " - Order Now!";
                    break;
                case 'sale':
                    subjectField.value = "🔥 Special Offer: " + merchName + " - Don't Miss Out!";
                    break;
                case 'restock':
                    subjectField.value = "🔄 Back in Stock: " + merchName;
                    break;
                case 'custom':
                    subjectField.value = "";
                    break;
            }
        }
        
        function toggleDepartmentFilter() {
            const departmentRadio = document.querySelector('input[name="recipient_filter"][value="department"]');
            const departmentSelect = document.getElementById('departmentSelect');
            
            if(departmentRadio.checked) {
                departmentSelect.style.display = 'block';
            } else {
                departmentSelect.style.display = 'none';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($merch): ?>
            updateTemplate('low_stock');
            <?php endif; ?>
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>