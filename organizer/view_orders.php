<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$success_msg  = "";
$error_msg    = "";

// ─── Validate merch_id ──────────────────────────────────────────────────────
if(!isset($_GET['merch_id']) || !is_numeric($_GET['merch_id'])) {
    header("Location: manage_merchandise.php");
    exit();
}
$merch_id = intval($_GET['merch_id']);

// ─── Verify organizer owns this merchandise ─────────────────────────────────
$merch_stmt = mysqli_prepare($conn,
    "SELECT m.*, 
     (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM merchandise m
     WHERE m.id = ? AND m.organizer_id = ?"
);
mysqli_stmt_bind_param($merch_stmt, "ii", $merch_id, $organizer_id);
mysqli_stmt_execute($merch_stmt);
$merch_result = mysqli_stmt_get_result($merch_stmt);
if(mysqli_num_rows($merch_result) == 0) {
    mysqli_stmt_close($merch_stmt);
    header("Location: manage_merchandise.php");
    exit();
}
$merch = mysqli_fetch_assoc($merch_result);
mysqli_stmt_close($merch_stmt);

// ──────────────────────────────────────────────────────────────────────────────
//  HELPER: Send notification email to student
// ──────────────────────────────────────────────────────────────────────────────
function sendOrderEmail($conn, $organizer_id, $student_email, $student_name, $subject, $html_body) {
    try {
        // Using PHPMailer — adjust class paths to match your vendor structure
        require_once '../vendor/phpmailer/phpmailer/src/Exception.php';
        require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($student_email, $student_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->send();

        // Log to email_logs
        $log = mysqli_prepare($conn,
            "INSERT INTO email_logs (user_id, recipient_email, subject, sent_date) VALUES (?, ?, ?, NOW())"
        );
        mysqli_stmt_bind_param($log, "iss", $organizer_id, $student_email, $subject);
        mysqli_stmt_execute($log);
        mysqli_stmt_close($log);
        return true;
    } catch(Exception $e) {
        return false;
    }
}

// ──────────────────────────────────────────────────────────────────────────────
//  HELPER: Build email HTML for order status updates
// ──────────────────────────────────────────────────────────────────────────────
function buildOrderEmail($type, $student_name, $merch, $order, $comment = '') {
    $dist_date  = !empty($merch['distribution_date'])
                    ? date('d M Y', strtotime($merch['distribution_date']))
                    : 'to be announced';
    $dist_time  = !empty($merch['distribution_time'])
                    ? date('h:i A', strtotime($merch['distribution_time']))
                    : '';
    $dist_venue = !empty($merch['distribution_venue'])
                    ? htmlspecialchars($merch['distribution_venue'])
                    : 'to be announced';
    $merch_name = htmlspecialchars($merch['name']);
    $price      = '₹' . number_format($merch['price'], 2);

    if($type === 'approved') {
        $subject = "✅ Payment Verified – Your order for {$merch_name} is confirmed!";
        $color   = '#48bb78';
        $icon    = '✅';
        $heading = 'Payment Verified!';
        $intro   = "Great news, <strong>" . htmlspecialchars($student_name) . "</strong>! Your payment for <strong>{$merch_name}</strong> has been verified.";
        $details = "
            <p style='margin:0 0 10px 0;'>📦 <strong>Product:</strong> {$merch_name}</p>
            <p style='margin:0 0 10px 0;'>💰 <strong>Amount:</strong> {$price}</p>
            <p style='margin:0 0 10px 0;'>👕 <strong>Size:</strong> " . (empty($order['size']) ? 'N/A' : htmlspecialchars($order['size'])) . "</p>
            <p style='margin:0 0 10px 0;'>📅 <strong>Pickup Date:</strong> {$dist_date}" . ($dist_time ? " at {$dist_time}" : '') . "</p>
            <p style='margin:0 0 10px 0;'>📍 <strong>Pickup Venue:</strong> {$dist_venue}</p>
        ";
        $footer_note = empty($comment)
            ? "Please make sure to be available on the distribution date to collect your order."
            : "Organizer note: " . htmlspecialchars($comment);
        $cta_text = "Please come to collect your order on the pickup date. Bring this email as confirmation.";
    } elseif($type === 'rejected') {
        $subject = "❌ Payment Verification Failed – Order for {$merch_name}";
        $color   = '#f56565';
        $icon    = '❌';
        $heading = 'Payment Verification Failed';
        $intro   = "Hello <strong>" . htmlspecialchars($student_name) . "</strong>, unfortunately your payment for <strong>{$merch_name}</strong> could not be verified.";
        $details = "
            <p style='margin:0 0 10px 0;'>📦 <strong>Product:</strong> {$merch_name}</p>
            <p style='margin:0 0 10px 0;'>💰 <strong>Amount:</strong> {$price}</p>
        ";
        $reason = !empty($comment) ? htmlspecialchars($comment) : htmlspecialchars($order['rejection_reason'] ?? 'No specific reason provided.');
        $footer_note = "Reason: " . $reason;
        $cta_text = "Please contact the organizer at <strong>" . htmlspecialchars($merch['contact_info']) . "</strong> for assistance or to re-submit your payment.";
    } else { // reminder
        $subject = "📢 Reminder – Collect your order for {$merch_name}";
        $color   = '#667eea';
        $icon    = '📢';
        $heading = 'Collection Reminder';
        $intro   = "Hello <strong>" . htmlspecialchars($student_name) . "</strong>, this is a reminder to collect your order for <strong>{$merch_name}</strong>.";
        $details = "
            <p style='margin:0 0 10px 0;'>📦 <strong>Product:</strong> {$merch_name}</p>
            <p style='margin:0 0 10px 0;'>👕 <strong>Size:</strong> " . (empty($order['size']) ? 'N/A' : htmlspecialchars($order['size'])) . "</p>
            <p style='margin:0 0 10px 0;'>📅 <strong>Pickup Date:</strong> {$dist_date}" . ($dist_time ? " at {$dist_time}" : '') . "</p>
            <p style='margin:0 0 10px 0;'>📍 <strong>Pickup Venue:</strong> {$dist_venue}</p>
        ";
        $footer_note = empty($comment) ? '' : htmlspecialchars($comment);
        $cta_text = "Please ensure you collect your order on the given date. Contact us at <strong>" . htmlspecialchars($merch['contact_info']) . "</strong> for any queries.";
    }

    return [
        'subject' => $subject,
        'body'    => "
        <!DOCTYPE html><html><body style='font-family:Arial,sans-serif;background:#f7fafc;margin:0;padding:20px;'>
        <div style='max-width:600px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);'>
            <div style='background:linear-gradient(135deg,{$color},{$color}cc);color:white;padding:30px;text-align:center;'>
                <div style='font-size:48px;margin-bottom:10px;'>{$icon}</div>
                <h1 style='margin:0;font-size:24px;'>{$heading}</h1>
            </div>
            <div style='padding:30px;'>
                <p style='font-size:16px;color:#4a5568;margin-bottom:20px;'>{$intro}</p>
                <div style='background:#f7fafc;border-radius:8px;padding:20px;margin-bottom:20px;color:#4a5568;'>
                    {$details}
                </div>
                <p style='font-size:14px;color:#718096;border-top:1px solid #e2e8f0;padding-top:15px;'>{$cta_text}</p>
                " . (!empty($footer_note) ? "<p style='font-size:13px;color:#a0aec0;font-style:italic;'>{$footer_note}</p>" : "") . "
            </div>
            <div style='background:#f7fafc;padding:20px;text-align:center;color:#a0aec0;font-size:12px;border-top:1px solid #e2e8f0;'>
                Campus Event Manager &nbsp;|&nbsp; Do not reply to this email
            </div>
        </div></body></html>"
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
//  HANDLE POST ACTIONS
// ──────────────────────────────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $action   = $_POST['action'];
    $order_id = intval($_POST['order_id']);

    // Fetch order (verify it belongs to this merch)
    $ord_stmt = mysqli_prepare($conn,
        "SELECT mo.*, u.full_name, u.email, u.username
         FROM merchandise_orders mo
         JOIN users u ON mo.student_id = u.id
         WHERE mo.id = ? AND mo.merchandise_id = ?"
    );
    mysqli_stmt_bind_param($ord_stmt, "ii", $order_id, $merch_id);
    mysqli_stmt_execute($ord_stmt);
    $ord = mysqli_fetch_assoc(mysqli_stmt_get_result($ord_stmt));
    mysqli_stmt_close($ord_stmt);

    if(!$ord) {
        $error_msg = "Order not found.";
    } else {
        $comment = trim(strip_tags($_POST['comment'] ?? ''));

        // ── APPROVE ─────────────────────────────────────────────────────────
        if($action === 'approve' && $ord['order_status'] === 'pending') {
            $upd = mysqli_prepare($conn,
                "UPDATE merchandise_orders SET order_status='confirmed', organizer_comment=? WHERE id=?"
            );
            mysqli_stmt_bind_param($upd, "si", $comment, $order_id);
            if(mysqli_stmt_execute($upd)) {
                $email_data = buildOrderEmail('approved', $ord['full_name'], $merch, $ord, $comment);
                $sent = sendOrderEmail($conn, $organizer_id, $ord['email'], $ord['full_name'],
                                       $email_data['subject'], $email_data['body']);
                $success_msg = "Order approved!" . ($sent ? " Notification email sent to student." : " (Email delivery failed — please notify student manually.)");
            } else {
                $error_msg = "Failed to update order status.";
            }
            mysqli_stmt_close($upd);

        // ── REJECT ──────────────────────────────────────────────────────────
        } elseif($action === 'reject' && $ord['order_status'] === 'pending') {
            $rejection_reason = trim(strip_tags($_POST['rejection_reason'] ?? ''));
            if(empty($rejection_reason)) {
                $error_msg = "Please provide a rejection reason.";
            } else {
                // Restore stock
                $restore = mysqli_prepare($conn,
                    "UPDATE merchandise SET
                        quantity_available = quantity_available + ?,
                        status = CASE WHEN quantity_available + ? > 0 AND status = 'out_of_stock' THEN 'available' ELSE status END
                     WHERE id = ? AND organizer_id = ?"
                );
                $qty = $ord['quantity'];
                mysqli_stmt_bind_param($restore, "iiii", $qty, $qty, $merch_id, $organizer_id);
                mysqli_stmt_execute($restore);
                mysqli_stmt_close($restore);

                $upd = mysqli_prepare($conn,
                    "UPDATE merchandise_orders SET order_status='rejected', rejection_reason=?, organizer_comment=? WHERE id=?"
                );
                mysqli_stmt_bind_param($upd, "ssi", $rejection_reason, $comment, $order_id);
                if(mysqli_stmt_execute($upd)) {
                    // Merge rejection reason into comment for email
                    $ord['rejection_reason'] = $rejection_reason;
                    $email_data = buildOrderEmail('rejected', $ord['full_name'], $merch, $ord, $rejection_reason);
                    $sent = sendOrderEmail($conn, $organizer_id, $ord['email'], $ord['full_name'],
                                           $email_data['subject'], $email_data['body']);
                    // Refresh merch (stock may have changed)
                    $refresh = mysqli_prepare($conn, "SELECT * FROM merchandise WHERE id=?");
                    mysqli_stmt_bind_param($refresh, "i", $merch_id);
                    mysqli_stmt_execute($refresh);
                    $merch = mysqli_fetch_assoc(mysqli_stmt_get_result($refresh));
                    mysqli_stmt_close($refresh);
                    $success_msg = "Order rejected and stock restored." . ($sent ? " Notification email sent to student." : " (Email delivery failed — please notify student manually.)");
                } else {
                    $error_msg = "Failed to update order status.";
                }
                mysqli_stmt_close($upd);
            }

        // ── SEND REMINDER ────────────────────────────────────────────────────
        } elseif($action === 'notify' && in_array($ord['order_status'], ['confirmed'])) {
            $email_data = buildOrderEmail('reminder', $ord['full_name'], $merch, $ord, $comment);
            $sent = sendOrderEmail($conn, $organizer_id, $ord['email'], $ord['full_name'],
                                   $email_data['subject'], $email_data['body']);
            // Mark notified_at
            $mark = mysqli_prepare($conn, "UPDATE merchandise_orders SET notified_at=NOW() WHERE id=?");
            mysqli_stmt_bind_param($mark, "i", $order_id);
            mysqli_stmt_execute($mark);
            mysqli_stmt_close($mark);
            $success_msg = $sent ? "Reminder sent to " . htmlspecialchars($ord['full_name']) . "." : "Email delivery failed — please notify student manually.";

        // ── MARK COLLECTED ───────────────────────────────────────────────────
        } elseif($action === 'collected' && $ord['order_status'] === 'confirmed') {
            $upd = mysqli_prepare($conn,
                "UPDATE merchandise_orders SET order_status='collected', organizer_comment=? WHERE id=?"
            );
            mysqli_stmt_bind_param($upd, "si", $comment, $order_id);
            if(mysqli_stmt_execute($upd)) {
                $success_msg = "Order marked as collected!";
            } else {
                $error_msg = "Failed to update order status.";
            }
            mysqli_stmt_close($upd);
        } else {
            $error_msg = "Invalid action or order is not in the correct state for this action.";
        }
    }

    // PRG redirect
    $redirect_params = "merch_id={$merch_id}";
    if($success_msg) $redirect_params .= "&msg=" . urlencode($success_msg);
    if($error_msg)   $redirect_params .= "&err=" . urlencode($error_msg);
    header("Location: view_orders.php?" . $redirect_params);
    exit();
}

// Show flash messages from redirect
if(isset($_GET['msg'])) $success_msg = htmlspecialchars($_GET['msg']);
if(isset($_GET['err'])) $error_msg   = htmlspecialchars($_GET['err']);

// ──────────────────────────────────────────────────────────────────────────────
//  FETCH ORDERS
// ──────────────────────────────────────────────────────────────────────────────
$status_filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$allowed_filters = ['all','pending','confirmed','collected','rejected','cancelled'];
if(!in_array($status_filter, $allowed_filters)) $status_filter = 'all';

$where_status = ($status_filter !== 'all') ? "AND mo.order_status = '$status_filter'" : '';

$orders_stmt = mysqli_prepare($conn,
    "SELECT mo.*, u.full_name, u.email, u.username, u.department, u.phone
     FROM merchandise_orders mo
     JOIN users u ON mo.student_id = u.id
     WHERE mo.merchandise_id = ? $where_status
     ORDER BY FIELD(mo.order_status,'pending','confirmed','rejected','collected','cancelled'),
              mo.ordered_at DESC"
);
mysqli_stmt_bind_param($orders_stmt, "i", $merch_id);
mysqli_stmt_execute($orders_stmt);
$orders_result = mysqli_stmt_get_result($orders_stmt);
mysqli_stmt_close($orders_stmt);

// Stats
$stats_query = mysqli_prepare($conn,
    "SELECT order_status, COUNT(*) as cnt FROM merchandise_orders WHERE merchandise_id=? GROUP BY order_status"
);
mysqli_stmt_bind_param($stats_query, "i", $merch_id);
mysqli_stmt_execute($stats_query);
$stats_result = mysqli_stmt_get_result($stats_query);
mysqli_stmt_close($stats_query);

$stats = ['pending'=>0,'confirmed'=>0,'collected'=>0,'rejected'=>0,'cancelled'=>0,'total'=>0];
while($s = mysqli_fetch_assoc($stats_result)) {
    if(isset($stats[$s['order_status']])) $stats[$s['order_status']] = $s['cnt'];
    $stats['total'] += $s['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders – <?php echo htmlspecialchars($merch['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white;
            border-radius: 10px;
            padding: 18px 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .stat-box:hover, .stat-box.active { border-color: var(--color); transform: translateY(-2px); }
        .stat-box .num   { font-size: 28px; font-weight: 700; color: var(--color); }
        .stat-box .label { font-size: 12px; color: #718096; margin-top: 4px; }
        .order-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            overflow: hidden;
            border-left: 5px solid #e2e8f0;
        }
        .order-card.pending   { border-left-color: #ed8936; }
        .order-card.confirmed { border-left-color: #48bb78; }
        .order-card.rejected  { border-left-color: #f56565; }
        .order-card.collected { border-left-color: #667eea; }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .order-body { padding: 20px; }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-pending   { background:#fef3c7; color:#92400e; }
        .badge-confirmed { background:#d1fae5; color:#065f46; }
        .badge-rejected  { background:#fee2e2; color:#991b1b; }
        .badge-collected { background:#e0e7ff; color:#3730a3; }
        .badge-cancelled { background:#f3f4f6; color:#6b7280; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }
        .info-item label { font-size: 11px; text-transform: uppercase; color: #a0aec0; letter-spacing: 0.5px; display:block; margin-bottom:2px; }
        .info-item span  { font-size: 14px; color: #2d3748; font-weight: 500; }
        .action-panel {
            background: #f7fafc;
            border-radius: 8px;
            padding: 16px;
            margin-top: 15px;
            border: 1px solid #e2e8f0;
        }
        .action-panel h4 { margin:0 0 12px 0; font-size:14px; color:#4a5568; }
        .screenshot-thumb {
            max-width: 120px;
            max-height: 120px;
            border-radius: 6px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            object-fit: cover;
            transition: transform 0.2s;
        }
        .screenshot-thumb:hover { transform: scale(1.05); }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            width: 550px;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 12px; right: 15px;
            background: #f56565; color: white;
            border: none; border-radius: 50%;
            width: 28px; height: 28px;
            cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-content img { max-width: 100%; border-radius: 8px; }
        .comment-toggle { cursor:pointer; color:#667eea; font-size:13px; margin-top:8px; display:inline-block; }
        .comment-form { display:none; margin-top:12px; }
        .comment-form.show { display:block; }
        textarea.comment-input { width:100%; padding:10px; border:2px solid #e2e8f0; border-radius:6px; resize:vertical; font-size:14px; font-family:inherit; }
        textarea.comment-input:focus { border-color:#667eea; outline:none; }
        .filter-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .filter-tab  { padding:7px 14px; border-radius:20px; font-size:13px; text-decoration:none; background:#f7fafc; color:#4a5568; border:2px solid #e2e8f0; transition:all 0.2s; }
        .filter-tab:hover, .filter-tab.active { background:#667eea; color:white; border-color:#667eea; }
        .empty-state { text-align:center; padding:60px 20px; background:white; border-radius:10px; }
        .merch-banner {
            display:flex; align-items:center; gap:20px;
            background:white; padding:20px; border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,0.07); margin-bottom:25px;
        }
        .merch-banner img { width:80px; height:80px; object-fit:cover; border-radius:8px; }
        .merch-banner .no-img { width:80px; height:80px; background:#f7fafc; display:flex; align-items:center; justify-content:center; border-radius:8px; font-size:36px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>📦 Orders</h1>
            <div style="display:flex;gap:10px;">
                <button onclick="exportToCSV()" class="btn btn-success">📥 Export CSV</button>
                <a href="manage_merchandise.php" class="btn btn-secondary">← Back to Merchandise</a>
            </div>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Merchandise Info Banner -->
        <div class="merch-banner">
            <?php if($merch['primary_image']): ?>
                <img src="../uploads/merchandise/<?php echo htmlspecialchars($merch['primary_image']); ?>" alt="Product">
            <?php else: ?>
                <div class="no-img">🛍️</div>
            <?php endif; ?>
            <div style="flex:1;">
                <h2 style="margin:0 0 6px 0;color:#2d3748;"><?php echo htmlspecialchars($merch['name']); ?></h2>
                <div style="display:flex;gap:20px;font-size:14px;color:#718096;flex-wrap:wrap;">
                    <span>💰 <strong style="color:#2d3748;">₹<?php echo number_format($merch['price'], 2); ?></strong></span>
                    <span>📦 Stock: <strong style="color:<?php echo $merch['quantity_available'] > 0 ? '#48bb78' : '#f56565'; ?>">
                        <?php echo $merch['quantity_available']; ?></strong></span>
                    <span>📋 Status: 
                        <strong style="color:<?php echo $merch['status']==='available' ? '#48bb78' : ($merch['status']==='out_of_stock' ? '#f56565' : '#718096'); ?>">
                            <?php echo ucwords(str_replace('_',' ',$merch['status'])); ?>
                        </strong>
                    </span>
                    <?php if(!empty($merch['upi_id'])): ?>
                        <span>💳 UPI: <strong style="color:#2d3748;"><?php echo htmlspecialchars($merch['upi_id']); ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php if($merch['distribution_date']): ?>
                    <div style="margin-top:6px;font-size:13px;color:#718096;">
                        🚚 Distribution: <strong style="color:#2d3748;"><?php echo date('d M Y', strtotime($merch['distribution_date'])); ?></strong>
                        <?php if($merch['distribution_time']): ?>
                            at <?php echo date('h:i A', strtotime($merch['distribution_time'])); ?>
                        <?php endif; ?>
                        <?php if($merch['distribution_venue']): ?>
                            @ <?php echo htmlspecialchars($merch['distribution_venue']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <a href="?merch_id=<?php echo $merch_id; ?>" class="stat-box <?php echo $status_filter==='all'?'active':''; ?>" style="--color:#667eea;">
                <div class="num"><?php echo $stats['total']; ?></div>
                <div class="label">Total Orders</div>
            </a>
            <a href="?merch_id=<?php echo $merch_id; ?>&filter=pending" class="stat-box <?php echo $status_filter==='pending'?'active':''; ?>" style="--color:#ed8936;">
                <div class="num"><?php echo $stats['pending']; ?></div>
                <div class="label">⏳ Pending</div>
            </a>
            <a href="?merch_id=<?php echo $merch_id; ?>&filter=confirmed" class="stat-box <?php echo $status_filter==='confirmed'?'active':''; ?>" style="--color:#48bb78;">
                <div class="num"><?php echo $stats['confirmed']; ?></div>
                <div class="label">✅ Confirmed</div>
            </a>
            <a href="?merch_id=<?php echo $merch_id; ?>&filter=collected" class="stat-box <?php echo $status_filter==='collected'?'active':''; ?>" style="--color:#667eea;">
                <div class="num"><?php echo $stats['collected']; ?></div>
                <div class="label">📬 Collected</div>
            </a>
            <a href="?merch_id=<?php echo $merch_id; ?>&filter=rejected" class="stat-box <?php echo $status_filter==='rejected'?'active':''; ?>" style="--color:#f56565;">
                <div class="num"><?php echo $stats['rejected']; ?></div>
                <div class="label">❌ Rejected</div>
            </a>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <?php foreach(['all'=>'All Orders','pending'=>'⏳ Pending','confirmed'=>'✅ Confirmed','collected'=>'📬 Collected','rejected'=>'❌ Rejected','cancelled'=>'🚫 Cancelled'] as $key=>$label): ?>
                <a href="?merch_id=<?php echo $merch_id; ?>&filter=<?php echo $key; ?>"
                   class="filter-tab <?php echo $status_filter===$key?'active':''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Orders List -->
        <?php if(mysqli_num_rows($orders_result) > 0): ?>
        <div id="ordersTable">
        <?php $counter = 1; while($order = mysqli_fetch_assoc($orders_result)): ?>

            <div class="order-card <?php echo $order['order_status']; ?>">
                <!-- Order Header -->
                <div class="order-header">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-weight:700;color:#2d3748;">#<?php echo $counter++; ?></span>
                        <span class="badge badge-<?php echo $order['order_status']; ?>">
                            <?php echo ucwords(str_replace('_',' ',$order['order_status'])); ?>
                        </span>
                        <span style="font-size:13px;color:#718096;">
                            Order #<?php echo $order['id']; ?> &nbsp;•&nbsp;
                            <?php echo date('d M Y, h:i A', strtotime($order['ordered_at'])); ?>
                        </span>
                    </div>
                    <div style="font-size:13px;color:#a0aec0;">
                        <?php if($order['notified_at']): ?>
                            📧 Notified: <?php echo date('d M, h:i A', strtotime($order['notified_at'])); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Body -->
                <div class="order-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Student Name</label>
                            <span><?php echo htmlspecialchars($order['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <span><?php echo htmlspecialchars($order['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Username</label>
                            <span><?php echo htmlspecialchars($order['username']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Department</label>
                            <span><?php echo htmlspecialchars($order['department'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Size Ordered</label>
                            <span><?php echo $order['size'] ? htmlspecialchars($order['size']) : 'N/A / Free Size'; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Quantity</label>
                            <span><?php echo $order['quantity']; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Amount</label>
                            <span>₹<?php echo number_format($merch['price'] * $order['quantity'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Payment Screenshot -->
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px;text-transform:uppercase;color:#a0aec0;letter-spacing:0.5px;display:block;margin-bottom:8px;">Payment Screenshot</label>
                        <?php if(!empty($order['payment_screenshot'])): ?>
                            <img src="../uploads/payment_screenshots/<?php echo htmlspecialchars($order['payment_screenshot']); ?>"
                                 class="screenshot-thumb"
                                 onclick="openImageModal('../uploads/payment_screenshots/<?php echo htmlspecialchars($order['payment_screenshot']); ?>')"
                                 alt="Payment Screenshot"
                                 title="Click to view full size">
                            <small style="display:block;color:#718096;margin-top:4px;font-size:12px;">Click to view full size</small>
                        <?php else: ?>
                            <span style="font-size:13px;color:#a0aec0;font-style:italic;">No screenshot uploaded by student.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Organizer Notes / Rejection Reason -->
                    <?php if(!empty($order['organizer_comment'])): ?>
                        <div style="background:#f0fff4;border:1px solid #9ae6b4;border-radius:6px;padding:12px;margin-bottom:12px;font-size:13px;">
                            <strong style="color:#276749;">📝 Organizer Note:</strong>
                            <span style="color:#4a5568;margin-left:4px;"><?php echo htmlspecialchars($order['organizer_comment']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($order['rejection_reason'])): ?>
                        <div style="background:#fff5f5;border:1px solid #feb2b2;border-radius:6px;padding:12px;margin-bottom:12px;font-size:13px;">
                            <strong style="color:#c53030;">❌ Rejection Reason:</strong>
                            <span style="color:#4a5568;margin-left:4px;"><?php echo htmlspecialchars($order['rejection_reason']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons (only for pending/confirmed) -->
                    <?php if($order['order_status'] === 'pending'): ?>
                        <div class="action-panel">
                            <h4>🔍 Verify Payment — check the screenshot above before acting</h4>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <button onclick="toggleAction('approve-<?php echo $order['id']; ?>')" class="btn btn-success btn-sm">
                                    ✅ Approve & Notify
                                </button>
                                <button onclick="toggleAction('reject-<?php echo $order['id']; ?>')" class="btn btn-danger btn-sm">
                                    ❌ Reject & Notify
                                </button>
                            </div>

                            <!-- Approve Form -->
                            <div id="approve-<?php echo $order['id']; ?>" class="comment-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <label style="font-size:13px;color:#4a5568;display:block;margin:12px 0 6px;">
                                        Optional note to student (e.g., reminder about pickup details):
                                    </label>
                                    <textarea name="comment" class="comment-input" rows="2"
                                        placeholder="e.g., Please bring your student ID to collect your order."></textarea>
                                    <button type="submit" class="btn btn-success btn-sm" style="margin-top:8px;" 
                                        onclick="return confirm('Approve this order and send notification email?')">
                                        ✅ Confirm Approval & Send Email
                                    </button>
                                </form>
                            </div>

                            <!-- Reject Form -->
                            <div id="reject-<?php echo $order['id']; ?>" class="comment-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <label style="font-size:13px;color:#c53030;display:block;margin:12px 0 6px;">
                                        ⚠️ Rejection Reason * (required — student will see this):
                                    </label>
                                    <textarea name="rejection_reason" class="comment-input" rows="2" required
                                        placeholder="e.g., Payment screenshot does not match UPI transaction. No transaction found for this amount."></textarea>
                                    <label style="font-size:13px;color:#4a5568;display:block;margin:8px 0 4px;">
                                        Internal note (optional, not sent to student):
                                    </label>
                                    <textarea name="comment" class="comment-input" rows="2"
                                        placeholder="e.g., Student re-submitted wrong screenshot."></textarea>
                                    <button type="submit" class="btn btn-danger btn-sm" style="margin-top:8px;"
                                        onclick="return confirm('Reject this order? Stock will be restored and student will be notified.')">
                                        ❌ Confirm Rejection & Send Email
                                    </button>
                                </form>
                            </div>
                        </div>

                    <?php elseif($order['order_status'] === 'confirmed'): ?>
                        <div class="action-panel">
                            <h4>📬 Order Confirmed — Awaiting Collection</h4>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <button onclick="toggleAction('notify-<?php echo $order['id']; ?>')" class="btn btn-sm" style="background:#667eea;color:white;">
                                    📧 Send Collection Reminder
                                </button>
                                <button onclick="toggleAction('collected-<?php echo $order['id']; ?>')" class="btn btn-sm" style="background:#805ad5;color:white;">
                                    📬 Mark as Collected
                                </button>
                            </div>

                            <!-- Remind Form -->
                            <div id="notify-<?php echo $order['id']; ?>" class="comment-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="notify">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <label style="font-size:13px;color:#4a5568;display:block;margin:12px 0 6px;">
                                        Optional extra message in reminder:
                                    </label>
                                    <textarea name="comment" class="comment-input" rows="2"
                                        placeholder="e.g., Distribution starts at 1PM sharp, please be on time!"></textarea>
                                    <button type="submit" class="btn btn-sm" style="background:#667eea;color:white;margin-top:8px;"
                                        onclick="return confirm('Send collection reminder to this student?')">
                                        📧 Send Reminder Email
                                    </button>
                                </form>
                            </div>

                            <!-- Mark Collected Form -->
                            <div id="collected-<?php echo $order['id']; ?>" class="comment-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="collected">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <label style="font-size:13px;color:#4a5568;display:block;margin:12px 0 6px;">
                                        Internal note (optional):
                                    </label>
                                    <textarea name="comment" class="comment-input" rows="2"
                                        placeholder="e.g., Collected by student on 12 Jan 2pm."></textarea>
                                    <button type="submit" class="btn btn-sm" style="background:#805ad5;color:white;margin-top:8px;"
                                        onclick="return confirm('Mark this order as collected?')">
                                        📬 Confirm Collected
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                </div><!-- /order-body -->
            </div><!-- /order-card -->
        <?php endwhile; ?>
        </div>

        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:70px;margin-bottom:15px;">📭</div>
                <h2 style="color:#718096;margin-bottom:8px;">
                    <?php echo $status_filter !== 'all' ? "No {$status_filter} orders" : "No Orders Yet"; ?>
                </h2>
                <p style="color:#a0aec0;">
                    <?php echo $status_filter !== 'all' ? "Try selecting a different filter above." : "Orders placed by students will appear here."; ?>
                </p>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- Image Lightbox Modal -->
<div class="modal-overlay" id="imageModal" onclick="closeImageModal(event)">
    <div class="modal-content" style="width:auto;padding:10px;">
        <button class="modal-close" onclick="closeModal()">×</button>
        <img id="modalImage" src="" alt="Payment Screenshot" style="max-width:90vw;max-height:85vh;border-radius:8px;display:block;">
    </div>
</div>

<script>
function toggleAction(id) {
    const el = document.getElementById(id);
    if(!el) return;
    // Close all other open forms first
    document.querySelectorAll('.comment-form.show').forEach(f => {
        if(f.id !== id) f.classList.remove('show');
    });
    el.classList.toggle('show');
}

function openImageModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').classList.add('show');
}
function closeImageModal(e) {
    if(e.target === document.getElementById('imageModal')) closeModal();
}
function closeModal() {
    document.getElementById('imageModal').classList.remove('show');
}

function exportToCSV() {
    const table = document.getElementById('ordersTable');
    if(!table) { alert('No orders to export.'); return; }
    let csv = ['"#","Student","Email","Department","Size","Qty","Amount","Status","Ordered At"'];
    document.querySelectorAll('.order-card').forEach((card, i) => {
        const cells = card.querySelectorAll('.info-item span');
        const status = card.querySelector('.badge')?.innerText.trim() || '';
        const ordered = card.querySelector('.order-header span:last-child')?.innerText || '';
        const row = [
            i+1,
            cells[0]?.innerText || '',
            cells[1]?.innerText || '',
            cells[3]?.innerText || '',
            cells[4]?.innerText || '',
            cells[5]?.innerText || '',
            cells[6]?.innerText || '',
            status,
            ordered.split('•')[1]?.trim() || ''
        ].map(v => '"' + String(v).replace(/"/g,'""') + '"');
        csv.push(row.join(','));
    });
    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv.join('\n'));
    link.download = 'orders_<?php echo $merch_id; ?>_' + new Date().toLocaleDateString().replace(/\//g,'-') + '.csv';
    link.click();
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>