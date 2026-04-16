<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id']; // Works for any role — column is named student_id in DB
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$allowed_tabs = ['all','pending','confirmed','collected','rejected','cancelled'];
if(!in_array($active_tab, $allowed_tabs)) $active_tab = 'all';

// ── Fetch all orders for this student ────────────────────────────────────────
$where_status = ($active_tab !== 'all') ? "AND mo.order_status = '" . mysqli_real_escape_string($conn, $active_tab) . "'" : '';

$orders_stmt = mysqli_prepare($conn,
    "SELECT mo.*,
            m.name as merch_name, m.price as merch_price, m.category, m.distribution_date,
            m.distribution_time, m.distribution_venue, m.contact_info, m.upi_id,
            (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image,
            u.full_name as organizer_name
     FROM merchandise_orders mo
     JOIN merchandise m ON mo.merchandise_id = m.id
     JOIN users u ON m.organizer_id = u.id
     WHERE mo.student_id = ? $where_status
     ORDER BY FIELD(mo.order_status,'pending','confirmed','rejected','collected','cancelled'),
              mo.ordered_at DESC"
);
mysqli_stmt_bind_param($orders_stmt, "i", $student_id);
mysqli_stmt_execute($orders_stmt);
$orders_result = mysqli_stmt_get_result($orders_stmt);
mysqli_stmt_close($orders_stmt);

// ── Counts per status ─────────────────────────────────────────────────────────
$count_stmt = mysqli_prepare($conn,
    "SELECT order_status, COUNT(*) as cnt FROM merchandise_orders WHERE student_id = ? GROUP BY order_status"
);
mysqli_stmt_bind_param($count_stmt, "i", $student_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
mysqli_stmt_close($count_stmt);

$counts = ['all'=>0,'pending'=>0,'confirmed'=>0,'collected'=>0,'rejected'=>0,'cancelled'=>0];
while($c = mysqli_fetch_assoc($count_result)) {
    if(isset($counts[$c['order_status']])) $counts[$c['order_status']] = $c['cnt'];
    $counts['all'] += $c['cnt'];
}

// Status display config
$status_config = [
    'pending'   => [
        'icon'  => '⏳',
        'label' => 'Payment Pending Verification',
        'color' => '#92400e',
        'bg'    => '#fffbeb',
        'border'=> '#fcd34d',
        'card_border' => '#f59e0b',
        'message' => 'Your payment screenshot has been submitted and is awaiting review by the organizer. This typically takes 1–2 business days. You will receive an email once your payment is verified or if there is an issue.',
    ],
    'confirmed' => [
        'icon'  => '✅',
        'label' => 'Payment Verified — Confirmed',
        'color' => '#065f46',
        'bg'    => '#f0fff4',
        'border'=> '#9ae6b4',
        'card_border' => '#48bb78',
        'message' => 'Great news! Your payment has been verified. Please collect your order on the distribution date from the venue mentioned below. Bring a copy of this confirmation or your email.',
    ],
    'collected' => [
        'icon'  => '📬',
        'label' => 'Collected',
        'color' => '#3730a3',
        'bg'    => '#eef2ff',
        'border'=> '#a5b4fc',
        'card_border' => '#667eea',
        'message' => 'You have successfully collected this order. Enjoy your merchandise!',
    ],
    'rejected'  => [
        'icon'  => '❌',
        'label' => 'Payment Verification Failed',
        'color' => '#991b1b',
        'bg'    => '#fff5f5',
        'border'=> '#feb2b2',
        'card_border' => '#f56565',
        'message' => 'Your payment could not be verified. Please see the rejection reason below and contact the organizer for assistance.',
    ],
    'cancelled' => [
        'icon'  => '🚫',
        'label' => 'Cancelled',
        'color' => '#6b7280',
        'bg'    => '#f9fafb',
        'border'=> '#d1d5db',
        'card_border' => '#9ca3af',
        'message' => 'This order has been cancelled.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Merch Orders - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 14px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white; border-radius: 10px; padding: 16px 12px;
            text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            text-decoration: none; color: inherit;
            border: 2px solid transparent; transition: all 0.2s; display: block;
        }
        .stat-box:hover, .stat-box.active { border-color: var(--c); transform: translateY(-2px); }
        .stat-box .num   { font-size: 26px; font-weight: 700; color: var(--c); }
        .stat-box .label { font-size: 12px; color: #718096; margin-top: 3px; }

        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; }
        .filter-tab  { padding: 7px 14px; border-radius: 20px; font-size: 13px; text-decoration: none;
                       background: #f7fafc; color: #4a5568; border: 2px solid #e2e8f0; transition: all 0.2s; }
        .filter-tab:hover, .filter-tab.active { background: #667eea; color: white; border-color: #667eea; }

        .order-card {
            background: white; border-radius: 12px; margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden;
            border-left: 5px solid var(--border-color);
        }
        .order-card-header {
            display: flex; align-items: center; gap: 16px;
            padding: 16px 20px; background: #f7fafc; border-bottom: 1px solid #e2e8f0;
        }
        .order-thumb {
            width: 70px; height: 70px; border-radius: 8px; object-fit: cover;
            background: #f7fafc; flex-shrink: 0;
        }
        .order-thumb-empty {
            width: 70px; height: 70px; border-radius: 8px; background: #e2e8f0;
            display: flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0;
        }
        .order-card-body   { padding: 20px; }
        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;
            border: 1px solid var(--sp-border); background: var(--sp-bg); color: var(--sp-color);
        }
        .status-message {
            border-radius: 8px; padding: 14px 16px; font-size: 13px; line-height: 1.6;
            border: 1px solid var(--m-border); background: var(--m-bg); color: var(--m-color);
            margin-bottom: 16px;
        }
        .info-row {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;
            margin-bottom: 15px;
        }
        .info-item label { font-size: 11px; text-transform: uppercase; color: #a0aec0; letter-spacing: 0.5px; display: block; margin-bottom: 2px; }
        .info-item span  { font-size: 14px; color: #2d3748; font-weight: 500; }
        .screenshot-thumb { max-width: 100px; max-height: 100px; border-radius: 6px; border: 2px solid #e2e8f0; cursor: pointer; object-fit: cover; }

        .dist-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 14px; font-size: 13px; color: #92400e; }
        .rejection-box { background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 14px; font-size: 14px; margin-bottom: 14px; }
        .organizer-note { background: #f0fff4; border: 1px solid #9ae6b4; border-radius: 8px; padding: 12px; font-size: 13px; margin-bottom: 14px; }

        .lightbox-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; }
        .lightbox-overlay.show { display: flex; }
        .lightbox-overlay img { max-width: 90vw; max-height: 90vh; border-radius: 10px; }
        .lightbox-close { position: absolute; top: 15px; right: 18px; color: white; font-size: 32px; cursor: pointer; line-height: 1; }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 10px; }
        .empty-state .icon { font-size: 72px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>📦 My Merchandise Orders</h1>
            <a href="browse_merchandise.php" class="btn btn-primary">🛍️ Browse Store</a>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <a href="?tab=all" class="stat-box <?php echo $active_tab==='all'?'active':''; ?>" style="--c:#667eea;">
                <div class="num"><?php echo $counts['all']; ?></div>
                <div class="label">All Orders</div>
            </a>
            <a href="?tab=pending" class="stat-box <?php echo $active_tab==='pending'?'active':''; ?>" style="--c:#f59e0b;">
                <div class="num"><?php echo $counts['pending']; ?></div>
                <div class="label">⏳ Pending</div>
            </a>
            <a href="?tab=confirmed" class="stat-box <?php echo $active_tab==='confirmed'?'active':''; ?>" style="--c:#48bb78;">
                <div class="num"><?php echo $counts['confirmed']; ?></div>
                <div class="label">✅ Confirmed</div>
            </a>
            <a href="?tab=collected" class="stat-box <?php echo $active_tab==='collected'?'active':''; ?>" style="--c:#667eea;">
                <div class="num"><?php echo $counts['collected']; ?></div>
                <div class="label">📬 Collected</div>
            </a>
            <a href="?tab=rejected" class="stat-box <?php echo $active_tab==='rejected'?'active':''; ?>" style="--c:#f56565;">
                <div class="num"><?php echo $counts['rejected']; ?></div>
                <div class="label">❌ Rejected</div>
            </a>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <?php $tabs = ['all'=>'All Orders','pending'=>'⏳ Pending','confirmed'=>'✅ Confirmed','collected'=>'📬 Collected','rejected'=>'❌ Rejected','cancelled'=>'🚫 Cancelled'];
            foreach($tabs as $key => $label): ?>
                <a href="?tab=<?php echo $key; ?>" class="filter-tab <?php echo $active_tab===$key?'active':''; ?>">
                    <?php echo $label; ?>
                    <?php if($counts[$key] > 0): ?><span style="margin-left:4px;background:rgba(255,255,255,0.3);border-radius:10px;padding:0 6px;font-size:11px;"><?php echo $counts[$key]; ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Global status legend (shown only on All tab) -->
        <?php if($active_tab === 'all' && $counts['all'] > 0): ?>
        <div style="background:white;border-radius:10px;padding:16px 20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <h4 style="margin:0 0 12px 0;color:#2d3748;font-size:14px;">ℹ️ What do these statuses mean?</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;font-size:13px;">
                <div style="display:flex;gap:8px;align-items:flex-start;"><span>⏳</span><div><strong style="color:#92400e;">Pending</strong> — Screenshot submitted, awaiting organizer verification.</div></div>
                <div style="display:flex;gap:8px;align-items:flex-start;"><span>✅</span><div><strong style="color:#065f46;">Confirmed</strong> — Payment verified! Ready to collect on distribution date.</div></div>
                <div style="display:flex;gap:8px;align-items:flex-start;"><span>📬</span><div><strong style="color:#3730a3;">Collected</strong> — Order picked up successfully.</div></div>
                <div style="display:flex;gap:8px;align-items:flex-start;"><span>❌</span><div><strong style="color:#991b1b;">Rejected</strong> — Payment couldn't be verified. See rejection reason.</div></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Orders -->
        <?php if(mysqli_num_rows($orders_result) > 0): ?>

            <?php while($order = mysqli_fetch_assoc($orders_result)):
                $sc = $status_config[$order['order_status']] ?? $status_config['cancelled'];
            ?>
            <div class="order-card" style="--border-color:<?php echo $sc['card_border']; ?>;">

                <!-- Card Header -->
                <div class="order-card-header">
                    <?php if($order['primary_image']): ?>
                        <img src="../uploads/merchandise/<?php echo htmlspecialchars($order['primary_image']); ?>"
                             class="order-thumb" alt="Product">
                    <?php else: ?>
                        <div class="order-thumb-empty">🛍️</div>
                    <?php endif; ?>

                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                            <strong style="color:#2d3748;font-size:17px;"><?php echo htmlspecialchars($order['merch_name']); ?></strong>
                            <span class="status-pill"
                                  style="--sp-bg:<?php echo $sc['bg']; ?>;--sp-border:<?php echo $sc['border']; ?>;--sp-color:<?php echo $sc['color']; ?>;">
                                <?php echo $sc['icon']; ?> <?php echo $sc['label']; ?>
                            </span>
                        </div>
                        <div style="font-size:13px;color:#718096;">
                            Order #<?php echo $order['id']; ?> &nbsp;•&nbsp;
                            Placed <?php echo date('d M Y, h:i A', strtotime($order['ordered_at'])); ?>
                            &nbsp;•&nbsp;
                            <a href="view_merchandise.php?id=<?php echo $order['merchandise_id']; ?>" style="color:#667eea;">View Product</a>
                        </div>
                    </div>

                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:22px;font-weight:700;color:#667eea;">₹<?php echo number_format($order['merch_price'], 2); ?></div>
                        <div style="font-size:12px;color:#a0aec0;">by <?php echo htmlspecialchars($order['organizer_name']); ?></div>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="order-card-body">

                    <!-- Status Message / Disclaimer -->
                    <div class="status-message"
                         style="--m-bg:<?php echo $sc['bg']; ?>;--m-border:<?php echo $sc['border']; ?>;--m-color:<?php echo $sc['color']; ?>;">
                        <strong><?php echo $sc['icon']; ?> <?php echo $sc['label']; ?>:</strong>
                        <?php echo $sc['message']; ?>
                    </div>

                    <!-- Order Details -->
                    <div class="info-row">
                        <div class="info-item">
                            <label>Category</label>
                            <span><?php echo ucwords(str_replace('-', ' ', $order['category'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Size</label>
                            <span><?php echo $order['size'] ? htmlspecialchars($order['size']) : 'N/A / Free Size'; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Quantity</label>
                            <span><?php echo $order['quantity']; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Amount Paid</label>
                            <span>₹<?php echo number_format($order['merch_price'] * $order['quantity'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Payment Screenshot -->
                    <?php if(!empty($order['payment_screenshot'])): ?>
                    <div style="margin-bottom:15px;">
                        <p style="font-size:12px;color:#a0aec0;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Your Payment Screenshot</p>
                        <img src="../uploads/payment_screenshots/<?php echo htmlspecialchars($order['payment_screenshot']); ?>"
                             class="screenshot-thumb"
                             onclick="openLightbox(this.src)"
                             title="Click to enlarge"
                             alt="Payment Screenshot">
                        <small style="display:block;color:#718096;font-size:12px;margin-top:3px;">Click to enlarge</small>
                    </div>
                    <?php endif; ?>

                    <!-- Rejection Reason (if rejected) -->
                    <?php if($order['order_status'] === 'rejected' && !empty($order['rejection_reason'])): ?>
                        <div class="rejection-box">
                            <strong style="color:#c53030;">❌ Rejection Reason:</strong>
                            <p style="margin:6px 0 0;color:#4a5568;"><?php echo nl2br(htmlspecialchars($order['rejection_reason'])); ?></p>
                            <p style="margin:8px 0 0;font-size:13px;color:#718096;">
                                Please contact the organizer at <strong><?php echo htmlspecialchars($order['contact_info']); ?></strong> for help or to re-submit payment.
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Organizer Comment (if any) -->
                    <?php if(!empty($order['organizer_comment'])): ?>
                        <div class="organizer-note">
                            <strong style="color:#276749;">📝 Note from Organizer:</strong>
                            <p style="margin:5px 0 0;color:#4a5568;"><?php echo nl2br(htmlspecialchars($order['organizer_comment'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Distribution Info (show for confirmed) -->
                    <?php if($order['order_status'] === 'confirmed' && ($order['distribution_date'] || $order['distribution_venue'])): ?>
                        <div class="dist-box">
                            <strong>🚚 Collection Details</strong>
                            <div style="margin-top:8px;display:flex;gap:20px;flex-wrap:wrap;font-size:13px;">
                                <?php if($order['distribution_date']): ?>
                                    <span>📅 <strong><?php echo date('d M Y', strtotime($order['distribution_date'])); ?></strong>
                                    <?php if($order['distribution_time']): ?> at <?php echo date('h:i A', strtotime($order['distribution_time'])); ?><?php endif; ?></span>
                                <?php endif; ?>
                                <?php if($order['distribution_venue']): ?>
                                    <span>📍 <strong><?php echo htmlspecialchars($order['distribution_venue']); ?></strong></span>
                                <?php endif; ?>
                            </div>
                            <p style="margin:8px 0 0;font-size:12px;color:#92400e;">
                                Please bring a copy of this confirmation or your email when collecting your order.
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Last notification timestamp -->
                    <?php if(!empty($order['notified_at'])): ?>
                        <p style="font-size:12px;color:#a0aec0;margin-top:12px;margin-bottom:0;">
                            📧 Last email from organizer: <?php echo date('d M Y, h:i A', strtotime($order['notified_at'])); ?>
                        </p>
                    <?php endif; ?>

                </div>
            </div>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h3 style="color:#2d3748;margin-bottom:8px;">
                    <?php echo $active_tab !== 'all' ? "No {$active_tab} orders" : "No Orders Yet"; ?>
                </h3>
                <p style="color:#718096;margin-bottom:20px;">
                    <?php echo $active_tab !== 'all'
                        ? "You don't have any orders with this status."
                        : "You haven't placed any merchandise orders yet."; ?>
                </p>
                <a href="browse_merchandise.php" class="btn btn-primary">🛍️ Browse the Store</a>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- Image Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">×</span>
    <img id="lightboxImg" src="" alt="Payment Screenshot">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
    document.body.style.overflow = '';
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>