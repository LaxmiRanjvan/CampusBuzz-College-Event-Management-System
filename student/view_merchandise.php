<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: browse_merchandise.php");
    exit();
}

$merch_id = intval($_GET['id']);

// ── Fetch merchandise ────────────────────────────────────────────────────────
$merch_stmt = mysqli_prepare($conn,
    "SELECT m.*, u.full_name as organizer_name, u.department as organizer_dept
     FROM merchandise m
     JOIN users u ON m.organizer_id = u.id
     WHERE m.id = ?"
);
mysqli_stmt_bind_param($merch_stmt, "i", $merch_id);
mysqli_stmt_execute($merch_stmt);
$merch_result = mysqli_stmt_get_result($merch_stmt);
if(mysqli_num_rows($merch_result) == 0) {
    mysqli_stmt_close($merch_stmt);
    header("Location: browse_merchandise.php");
    exit();
}
$merch = mysqli_fetch_assoc($merch_result);
mysqli_stmt_close($merch_stmt);

// ── Check if student already has an active order ─────────────────────────────
$active_order_stmt = mysqli_prepare($conn,
    "SELECT id, order_status FROM merchandise_orders
     WHERE merchandise_id = ? AND student_id = ?
     AND order_status IN ('pending','confirmed')
     LIMIT 1"
);
mysqli_stmt_bind_param($active_order_stmt, "ii", $merch_id, $student_id);
mysqli_stmt_execute($active_order_stmt);
$active_order_result = mysqli_stmt_get_result($active_order_stmt);
$existing_order = mysqli_num_rows($active_order_result) > 0 ? mysqli_fetch_assoc($active_order_result) : null;
mysqli_stmt_close($active_order_stmt);

// ── Fetch images ─────────────────────────────────────────────────────────────
$images_stmt = mysqli_prepare($conn, "SELECT * FROM merchandise_images WHERE merchandise_id = ? ORDER BY is_primary DESC");
mysqli_stmt_bind_param($images_stmt, "i", $merch_id);
mysqli_stmt_execute($images_stmt);
$images_result = mysqli_stmt_get_result($images_stmt);
$images_array = [];
while($img = mysqli_fetch_assoc($images_result)) {
    $images_array[] = $img;
}
mysqli_stmt_close($images_stmt);

$sizes = !empty($merch['sizes_available']) ? array_map('trim', explode(',', $merch['sizes_available'])) : [];

// ── Handle order submission ───────────────────────────────────────────────────
$order_success = false;
$order_error   = "";

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $size_selected = trim(strip_tags($_POST['size_selected'] ?? ''));
    $quantity      = 1; // Fixed at 1 per order for simplicity

    // Validate size if sizes are defined
    if(!empty($sizes) && (empty($size_selected) || !in_array($size_selected, $sizes))) {
        $order_error = "Please select a valid size before placing your order.";
    } elseif($merch['quantity_available'] < 1) {
        $order_error = "Sorry, this item is now out of stock.";
    } elseif($existing_order) {
        $order_error = "You already have an active order for this item. Check My Merch for its status.";
    } elseif(!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        $order_error = "Please upload your payment screenshot before submitting.";
    } else {
        // Validate screenshot file
        $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
        $file_mime    = mime_content_type($_FILES['payment_screenshot']['tmp_name']);

        if(!in_array($file_mime, $allowed_mime)) {
            $order_error = "Payment screenshot must be a JPG, PNG, or GIF image.";
        } elseif($_FILES['payment_screenshot']['size'] > 5 * 1024 * 1024) {
            $order_error = "Payment screenshot must be under 5MB.";
        } else {
            // Upload screenshot
            $upload_dir = '../uploads/payment_screenshots/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext      = pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION);
            $filename = 'pay_' . $student_id . '_' . $merch_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);

            if(!move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $upload_dir . $filename)) {
                $order_error = "Failed to upload screenshot. Please try again.";
            } else {
                // Insert order
                $ins = mysqli_prepare($conn,
                    "INSERT INTO merchandise_orders (merchandise_id, student_id, quantity, size, payment_screenshot, order_status, ordered_at)
                     VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
                );
                $size_val = !empty($size_selected) ? $size_selected : null;
                mysqli_stmt_bind_param($ins, "iiiss", $merch_id, $student_id, $quantity, $size_val, $filename);

                if(mysqli_stmt_execute($ins)) {
                    // Decrement stock
                    $decr = mysqli_prepare($conn,
                        "UPDATE merchandise SET
                            quantity_available = GREATEST(0, quantity_available - ?),
                            status = CASE
                                WHEN quantity_available - ? <= 0 THEN 'out_of_stock'
                                ELSE status
                            END
                         WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($decr, "iii", $quantity, $quantity, $merch_id);
                    mysqli_stmt_execute($decr);
                    mysqli_stmt_close($decr);

                    // Refresh merch data
                    $rf = mysqli_prepare($conn, "SELECT m.*, u.full_name as organizer_name, u.department as organizer_dept FROM merchandise m JOIN users u ON m.organizer_id = u.id WHERE m.id = ?");
                    mysqli_stmt_bind_param($rf, "i", $merch_id);
                    mysqli_stmt_execute($rf);
                    $merch = mysqli_fetch_assoc(mysqli_stmt_get_result($rf));
                    mysqli_stmt_close($rf);

                    $existing_order = ['order_status' => 'pending'];
                    $order_success  = true;
                    mysqli_stmt_close($ins);
                } else {
                    // Remove uploaded file on DB failure
                    @unlink($upload_dir . $filename);
                    $order_error = "Failed to place order. Please try again.";
                    mysqli_stmt_close($ins);
                }
            }
        }
    }
}

$can_order = $merch['quantity_available'] > 0
          && !empty($merch['upi_id'])
          && !$existing_order
          && !$order_success;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($merch['name']); ?> - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .product-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .image-gallery  { background: white; padding: 20px; border-radius: 10px; }
        .main-image     { width: 100%; height: 400px; object-fit: cover; border-radius: 10px; margin-bottom: 15px; }
        .thumbnail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; }
        .thumbnail      { width: 100%; height: 80px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid transparent; transition: all 0.3s; }
        .thumbnail:hover, .thumbnail.active { border-color: #667eea; }
        .product-info   { background: white; padding: 30px; border-radius: 10px; }
        .size-option    { display: inline-block; padding: 10px 20px; border: 2px solid #e2e8f0; border-radius: 6px; margin: 5px; cursor: pointer; transition: all 0.3s; user-select: none; }
        .size-option:hover, .size-option.selected { border-color: #667eea; background: #667eea; color: white; }
        @media (max-width: 768px) { .product-container { grid-template-columns: 1fr; } }

        /* ── UPI Payment Modal ─────────────────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 9999;
            align-items: center; justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: white;
            border-radius: 16px;
            padding: 0;
            max-width: 520px; width: 95%;
            max-height: 92vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp { from { transform: translateY(40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header {
            padding: 20px 25px 16px;
            border-bottom: 2px solid #e2e8f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header h3 { margin: 0; color: #2d3748; font-size: 18px; }
        .modal-close-btn {
            background: #f56565; color: white; border: none; border-radius: 50%;
            width: 30px; height: 30px; cursor: pointer; font-size: 17px;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-body  { padding: 22px 25px; }
        .modal-footer { padding: 16px 25px 22px; border-top: 1px solid #e2e8f0; }
        .upi-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border-radius: 12px; padding: 20px; text-align: center;
            margin-bottom: 18px;
        }
        .upi-box .label  { font-size: 12px; opacity: 0.85; margin-bottom: 6px; }
        .upi-box .upi-id { font-size: 22px; font-weight: 700; letter-spacing: 0.5px; word-break: break-all; }
        .upi-copy-btn {
            margin-top: 10px; padding: 6px 16px; background: rgba(255,255,255,0.25);
            color: white; border: 1px solid rgba(255,255,255,0.5);
            border-radius: 20px; cursor: pointer; font-size: 13px;
        }
        .upi-copy-btn:hover { background: rgba(255,255,255,0.4); }
        .step-badge {
            display: inline-block; width: 22px; height: 22px; background: #667eea;
            color: white; border-radius: 50%; text-align: center; line-height: 22px;
            font-size: 12px; font-weight: 700; margin-right: 6px; flex-shrink: 0;
        }
        .step-row { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 12px; font-size: 14px; color: #4a5568; }
        .upload-zone {
            border: 2px dashed #cbd5e0; border-radius: 10px; padding: 20px;
            text-align: center; cursor: pointer; transition: all 0.2s; position: relative;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: #667eea; background: #f0f0ff; }
        .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-preview { display: none; margin-top: 12px; }
        .upload-preview img { max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid #e2e8f0; }
        .disclaimer-box {
            background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px;
            padding: 12px 14px; font-size: 13px; color: #92400e; margin-bottom: 16px;
        }
        .qr-image { max-width: 180px; border-radius: 10px; border: 2px solid #e2e8f0; margin: 10px auto; display: block; }
        .selected-size-display { font-weight: 600; color: #667eea; }

        /* ── Status Banner ─────────────────────────────────────────────────── */
        .status-banner {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 18px 20px; border-radius: 10px; margin-bottom: 20px;
        }
        .status-banner.pending   { background: #fffbeb; border: 1px solid #fcd34d; }
        .status-banner.confirmed { background: #f0fff4; border: 1px solid #9ae6b4; }
        .status-banner.rejected  { background: #fff5f5; border: 1px solid #feb2b2; }
        .status-banner .icon   { font-size: 30px; }
        .status-banner .title  { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
        .status-banner .detail { font-size: 13px; line-height: 1.6; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>🛍️ Product Details</h1>
            <div style="display:flex;gap:10px;">
                <a href="my_merch.php" class="btn btn-secondary">📦 My Orders</a>
                <a href="browse_merchandise.php" class="btn btn-secondary">← Back to Store</a>
            </div>
        </div>

        <?php if($order_success): ?>
            <div class="alert alert-success">
                ✅ Order placed successfully! Your payment is under review. Check <a href="my_merch.php">My Merch</a> for updates.
            </div>
        <?php endif; ?>
        <?php if($order_error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($order_error); ?></div>
        <?php endif; ?>

        <div class="product-container">
            <!-- ── Image Gallery ─────────────────────────────────────────── -->
            <div class="image-gallery">
                <?php if(count($images_array) > 0): ?>
                    <img id="mainImage"
                         src="../uploads/merchandise/<?php echo htmlspecialchars($images_array[0]['image_path']); ?>"
                         class="main-image" alt="Product">
                    <?php if(count($images_array) > 1): ?>
                        <div class="thumbnail-grid">
                            <?php foreach($images_array as $i => $img): ?>
                                <img src="../uploads/merchandise/<?php echo htmlspecialchars($img['image_path']); ?>"
                                     class="thumbnail <?php echo $i == 0 ? 'active' : ''; ?>"
                                     onclick="changeImage('<?php echo htmlspecialchars($img['image_path']); ?>', this)"
                                     alt="Thumbnail">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="height:400px;display:flex;align-items:center;justify-content:center;background:#f7fafc;border-radius:10px;">
                        <div style="font-size:100px;">🛍️</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Product Information ───────────────────────────────────── -->
            <div class="product-info">
                <span style="display:inline-block;padding:5px 12px;background:#bee3f8;color:#2c5282;border-radius:12px;font-size:13px;margin-bottom:15px;">
                    <?php echo ucwords(str_replace('-', ' ', $merch['category'])); ?>
                </span>

                <h2 style="margin-bottom:15px;color:#2d3748;font-size:28px;">
                    <?php echo htmlspecialchars($merch['name']); ?>
                </h2>

                <div style="font-size:32px;font-weight:700;color:#667eea;margin-bottom:20px;">
                    ₹<?php echo number_format($merch['price'], 2); ?>
                </div>

                <!-- Active Order Status Banner -->
                <?php if($existing_order && !$order_success): ?>
                    <?php
                    $status = $existing_order['order_status'];
                    $banner_map = [
                        'pending'   => ['icon'=>'⏳','title'=>'Order Placed — Pending Verification','class'=>'pending',
                                        'detail'=>'Your order has been placed and your payment screenshot is under review by the organizer. This usually takes 1–2 days. You will receive an email notification once verified.'],
                        'confirmed' => ['icon'=>'✅','title'=>'Payment Verified — Confirmed!','class'=>'confirmed',
                                        'detail'=>'Your payment has been verified. Please be available on the distribution date to collect your order. Check your email for pickup details.'],
                        'rejected'  => ['icon'=>'❌','title'=>'Order Rejected','class'=>'rejected',
                                        'detail'=>'Your order was not confirmed. Please check My Merch for the rejection reason and contact the organizer if needed.'],
                    ];
                    $b = $banner_map[$status] ?? null;
                    if($b): ?>
                        <div class="status-banner <?php echo $b['class']; ?>">
                            <div class="icon"><?php echo $b['icon']; ?></div>
                            <div>
                                <div class="title"><?php echo $b['title']; ?></div>
                                <div class="detail"><?php echo $b['detail']; ?></div>
                                <a href="my_merch.php" style="font-size:13px;color:#667eea;font-weight:600;display:inline-block;margin-top:6px;">View in My Merch →</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Stock Status -->
                <div style="padding:15px;background:<?php echo $merch['quantity_available'] > 0 ? '#c6f6d5' : '#fed7d7'; ?>;border-radius:8px;margin-bottom:20px;">
                    <?php if($merch['quantity_available'] > 0): ?>
                        <strong style="color:#276749;">✓ In Stock</strong>
                        <p style="margin:5px 0 0 0;color:#276749;font-size:14px;"><?php echo $merch['quantity_available']; ?> units available</p>
                    <?php else: ?>
                        <strong style="color:#c53030;">✗ Out of Stock</strong>
                        <p style="margin:5px 0 0 0;color:#c53030;font-size:14px;">This item is currently unavailable</p>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #e2e8f0;">
                    <h3 style="margin-bottom:10px;color:#2d3748;">Description</h3>
                    <p style="line-height:1.8;color:#4a5568;"><?php echo nl2br(htmlspecialchars($merch['description'])); ?></p>
                </div>

                <!-- Sizes -->
                <?php if(!empty($sizes)): ?>
                    <div style="margin-bottom:20px;">
                        <h3 style="margin-bottom:10px;color:#2d3748;">Available Sizes <small style="color:#718096;font-weight:400;">(select before ordering)</small></h3>
                        <div id="sizeOptions">
                            <?php foreach($sizes as $size): ?>
                                <span class="size-option" data-size="<?php echo htmlspecialchars($size); ?>"
                                      onclick="selectSize('<?php echo htmlspecialchars($size); ?>', this)">
                                    <?php echo htmlspecialchars($size); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if(!empty($merch['size_guide'])): ?>
                            <details style="margin-top:12px;padding:12px;background:#f7fafc;border-radius:8px;">
                                <summary style="cursor:pointer;font-weight:600;color:#2d3748;">📏 Size Guide</summary>
                                <p style="margin-top:8px;line-height:1.6;color:#4a5568;font-size:14px;">
                                    <?php echo nl2br(htmlspecialchars($merch['size_guide'])); ?>
                                </p>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Distribution Details -->
                <?php if($merch['distribution_date'] || $merch['distribution_venue']): ?>
                    <div style="margin-bottom:20px;padding:15px;background:#fff3cd;border-radius:8px;">
                        <h4 style="margin-bottom:10px;color:#856404;">🚚 Distribution Details</h4>
                        <?php if($merch['distribution_date']): ?>
                            <p style="margin:5px 0;color:#856404;">
                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($merch['distribution_date'])); ?>
                                <?php if($merch['distribution_time']): ?> at <?php echo date('h:i A', strtotime($merch['distribution_time'])); ?><?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if($merch['distribution_venue']): ?>
                            <p style="margin:5px 0;color:#856404;"><strong>Venue:</strong> <?php echo htmlspecialchars($merch['distribution_venue']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Return Policy -->
                <?php if(!empty($merch['return_policy'])): ?>
                    <details style="margin-bottom:20px;padding:15px;background:#f7fafc;border-radius:8px;">
                        <summary style="cursor:pointer;font-weight:600;color:#2d3748;">🔄 Return Policy</summary>
                        <p style="margin-top:8px;line-height:1.6;color:#4a5568;font-size:14px;">
                            <?php echo nl2br(htmlspecialchars($merch['return_policy'])); ?>
                        </p>
                    </details>
                <?php endif; ?>

                <!-- Contact -->
                <div style="padding:15px;background:#f7fafc;border-radius:8px;margin-bottom:20px;">
                    <h4 style="margin-bottom:8px;color:#2d3748;">📞 Contact Information</h4>
                    <p style="color:#4a5568;margin:0;"><?php echo htmlspecialchars($merch['contact_info']); ?></p>
                </div>

                <!-- Order Button / Status -->
                <?php if($can_order): ?>
                    <button id="placeOrderBtn"
                            onclick="openPaymentModal()"
                            class="btn btn-primary"
                            style="width:100%;font-size:18px;padding:15px;">
                        🛒 Place Order
                    </button>
                    <?php if(!empty($sizes)): ?>
                        <p style="margin-top:8px;font-size:13px;color:#718096;text-align:center;">
                            Please select a size above before placing your order.
                        </p>
                    <?php endif; ?>

                <?php elseif($merch['quantity_available'] <= 0): ?>
                    <button class="btn btn-secondary" style="width:100%;font-size:18px;padding:15px;" disabled>
                        ✗ Out of Stock
                    </button>

                <?php elseif(empty($merch['upi_id'])): ?>
                    <button class="btn btn-secondary" style="width:100%;font-size:18px;padding:15px;" disabled>
                        Orders Not Available Yet
                    </button>

                <?php elseif($existing_order): ?>
                    <a href="my_merch.php" class="btn btn-success" style="width:100%;font-size:16px;padding:14px;text-align:center;display:block;">
                        📦 Track Your Order →
                    </a>
                <?php endif; ?>

                <!-- Organizer -->
                <div style="margin-top:20px;padding-top:20px;border-top:1px solid #e2e8f0;">
                    <p style="font-size:13px;color:#718096;margin:0;">
                        Sold by: <strong><?php echo htmlspecialchars($merch['organizer_name']); ?></strong>
                        <?php if($merch['organizer_dept']): ?>(<?php echo htmlspecialchars($merch['organizer_dept']); ?>)<?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ── UPI Payment Modal ─────────────────────────────────────────────────── -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>💳 Complete Your Payment</h3>
            <button class="modal-close-btn" onclick="closePaymentModal()">×</button>
        </div>

        <div class="modal-body">
            <!-- Steps -->
            <div class="step-row"><span class="step-badge">1</span> Pay the amount below using any UPI app (GPay, PhonePe, Paytm, etc.).</div>
            <div class="step-row"><span class="step-badge">2</span> Take a screenshot of the successful payment confirmation.</div>
            <div class="step-row"><span class="step-badge">3</span> Upload the screenshot below and submit your order.</div>

            <!-- UPI Box -->
            <div class="upi-box">
                <div class="label">PAY VIA UPI</div>
                <div class="upi-id" id="upiIdDisplay"><?php echo htmlspecialchars($merch['upi_id']); ?></div>
                <div style="font-size:14px;margin-top:8px;opacity:0.9;">
                    Amount: <strong>₹<?php echo number_format($merch['price'], 2); ?></strong>
                </div>
                <button class="upi-copy-btn" onclick="copyUPI()">📋 Copy UPI ID</button>
            </div>

            <!-- QR Code if available -->
            <?php if(!empty($merch['qr_image'])): ?>
                <div style="text-align:center;margin-bottom:18px;">
                    <p style="font-size:13px;color:#718096;margin-bottom:6px;">Or scan the QR code:</p>
                    <img src="../uploads/merchandise/<?php echo htmlspecialchars($merch['qr_image']); ?>"
                         class="qr-image" alt="Payment QR Code">
                </div>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid #e2e8f0;margin:18px 0;">

            <!-- Payment Form -->
            <form method="POST" action="" enctype="multipart/form-data" id="orderForm">
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="size_selected" id="hiddenSize" value="">

                <?php if(!empty($sizes)): ?>
                    <div style="margin-bottom:16px;padding:12px;background:#f0f0ff;border-radius:8px;font-size:14px;">
                        🔹 Selected Size: <span id="modalSizeDisplay" class="selected-size-display">None selected</span>
                    </div>
                <?php endif; ?>

                <div class="disclaimer-box">
                    ⚠️ <strong>Important:</strong> Only upload a screenshot of a <em>successful</em> UPI payment. 
                    Fake or incorrect screenshots will result in order rejection. 
                    The organizer will cross-verify your payment before confirming your order.
                </div>

                <label style="font-weight:600;color:#2d3748;font-size:14px;display:block;margin-bottom:8px;">
                    📸 Upload Payment Screenshot *
                </label>

                <div class="upload-zone" id="uploadZone">
                    <input type="file" name="payment_screenshot" id="screenshotInput"
                           accept="image/*" required onchange="previewScreenshot(event)">
                    <div id="uploadPlaceholder">
                        <div style="font-size:36px;margin-bottom:8px;">📤</div>
                        <p style="color:#718096;margin:0;font-size:14px;">Click or drag & drop your payment screenshot here</p>
                        <small style="color:#a0aec0;">JPG, PNG, GIF — max 5MB</small>
                    </div>
                </div>
                <div class="upload-preview" id="uploadPreview">
                    <img id="previewImg" src="" alt="Payment Screenshot Preview">
                    <p style="margin:8px 0 0;font-size:13px;color:#48bb78;font-weight:600;">✓ Screenshot ready to upload</p>
                    <button type="button" onclick="removeScreenshot()" style="font-size:12px;color:#f56565;background:none;border:none;cursor:pointer;margin-top:4px;">✕ Remove</button>
                </div>
            </form>
        </div>

        <div class="modal-footer">
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closePaymentModal()" class="btn btn-secondary" style="flex:1;">Cancel</button>
                <button type="button" onclick="submitOrder()" class="btn btn-primary" style="flex:2;">
                    ✅ Submit Order & Screenshot
                </button>
            </div>
            <p style="font-size:12px;color:#a0aec0;text-align:center;margin:10px 0 0;">
                Your order status will be updated once the organizer verifies your payment.
            </p>
        </div>
    </div>
</div>

<script>
let selectedSize = '';

function selectSize(size, el) {
    selectedSize = size;
    document.querySelectorAll('.size-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    // Sync modal display
    const disp = document.getElementById('modalSizeDisplay');
    if(disp) disp.textContent = size;
    const hid = document.getElementById('hiddenSize');
    if(hid) hid.value = size;
}

function openPaymentModal() {
    <?php if(!empty($sizes)): ?>
    if(!selectedSize) {
        alert('Please select a size before placing your order.');
        return;
    }
    <?php endif; ?>
    const modal = document.getElementById('paymentModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Close on backdrop click
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if(e.target === this) closePaymentModal();
});

function copyUPI() {
    const upi = document.getElementById('upiIdDisplay').textContent.trim();
    navigator.clipboard.writeText(upi).then(() => {
        const btn = event.target;
        btn.textContent = '✓ Copied!';
        setTimeout(() => btn.textContent = '📋 Copy UPI ID', 2000);
    }).catch(() => {
        prompt('Copy this UPI ID:', upi);
    });
}

function previewScreenshot(event) {
    const file = event.target.files[0];
    if(!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('uploadPlaceholder').style.display = 'none';
        document.getElementById('uploadPreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function removeScreenshot() {
    document.getElementById('screenshotInput').value = '';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('uploadPreview').style.display = 'none';
}

function submitOrder() {
    <?php if(!empty($sizes)): ?>
    if(!selectedSize) {
        alert('Please select a size before submitting.');
        return;
    }
    document.getElementById('hiddenSize').value = selectedSize;
    <?php endif; ?>
    const file = document.getElementById('screenshotInput').files[0];
    if(!file) {
        alert('Please upload your payment screenshot before submitting.');
        return;
    }
    if(!confirm('Confirm order submission? Make sure your payment is complete and the screenshot is clear.')) return;
    document.getElementById('orderForm').submit();
}

function changeImage(imagePath, thumbnail) {
    document.getElementById('mainImage').src = '../uploads/merchandise/' + imagePath;
    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
    thumbnail.classList.add('active');
}

// Drag & drop
const zone = document.getElementById('uploadZone');
if(zone) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('dragover');
        const dt = e.dataTransfer;
        if(dt.files[0]) {
            document.getElementById('screenshotInput').files = dt.files;
            previewScreenshot({ target: { files: dt.files } });
        }
    });
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>