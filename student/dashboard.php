<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// ── Event stats ───────────────────────────────────────────────────────────────
$registered_events = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM registrations WHERE user_id = $student_id AND status='registered'"
))['count'];

$upcoming_registered = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM registrations r
     JOIN events e ON r.event_id = e.id
     WHERE r.user_id = $student_id AND r.status='registered' AND e.event_date > NOW()"
))['count'];

$attended = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM ticket_verifications WHERE user_id = $student_id"
))['count'];

$available_events = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM events WHERE status='upcoming' AND event_date > NOW()"
))['count'];

// ── Merch stats ───────────────────────────────────────────────────────────────
$merch_stats_query = "SELECT order_status, COUNT(*) as cnt FROM merchandise_orders WHERE student_id = $student_id GROUP BY order_status";
$merch_stats_result = mysqli_query($conn, $merch_stats_query);
$merch_counts = ['pending'=>0,'confirmed'=>0,'total'=>0];
while($ms = mysqli_fetch_assoc($merch_stats_result)) {
    if(isset($merch_counts[$ms['order_status']])) $merch_counts[$ms['order_status']] = $ms['cnt'];
    $merch_counts['total'] += $ms['cnt'];
}

// ── My upcoming registered events ─────────────────────────────────────────────
$my_events_result = mysqli_query($conn,
    "SELECT e.*, u.full_name as organizer_name,
     (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
     FROM registrations r
     JOIN events e ON r.event_id = e.id
     JOIN users u ON e.organizer_id = u.id
     WHERE r.user_id = $student_id AND r.status='registered' AND e.event_date > NOW()
     ORDER BY e.event_date ASC LIMIT 5"
);

// ── Recent merch orders ────────────────────────────────────────────────────────
$recent_orders_result = mysqli_query($conn,
    "SELECT mo.order_status, mo.ordered_at, m.name as merch_name, m.price,
     (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM merchandise_orders mo
     JOIN merchandise m ON mo.merchandise_id = m.id
     WHERE mo.student_id = $student_id
     ORDER BY mo.ordered_at DESC LIMIT 3"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .merch-mini-card {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px;
            margin-bottom: 10px; background: #fafafa; transition: all 0.2s;
        }
        .merch-mini-card:hover { border-color: #667eea; background: white; }
        .merch-mini-thumb { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; background: #e2e8f0; flex-shrink: 0; }
        .merch-mini-thumb-empty { width: 48px; height: 48px; border-radius: 6px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
        .dot-pending   { background: #f59e0b; }
        .dot-confirmed { background: #48bb78; }
        .dot-collected { background: #667eea; }
        .dot-rejected  { background: #f56565; }
        .dot-cancelled { background: #9ca3af; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>🎓 Student Dashboard</h1>
        </div>

        <!-- ── Event Statistics ──────────────────────────────────────────── -->
        <h3 style="margin-bottom:14px;color:#4a5568;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">📅 Events</h3>
        <div class="dashboard-grid" style="margin-bottom:25px;">
            <div class="dashboard-card">
                <div class="card-icon blue"><span>🎫</span></div>
                <div class="card-content"><h3><?php echo $registered_events; ?></h3><p>Total Registrations</p></div>
            </div>
            <div class="dashboard-card">
                <div class="card-icon green"><span>🚀</span></div>
                <div class="card-content"><h3><?php echo $upcoming_registered; ?></h3><p>Upcoming Events</p></div>
            </div>
            <div class="dashboard-card">
                <div class="card-icon purple"><span>✅</span></div>
                <div class="card-content"><h3><?php echo $attended; ?></h3><p>Events Attended</p></div>
            </div>
            <div class="dashboard-card">
                <div class="card-icon red"><span>📅</span></div>
                <div class="card-content"><h3><?php echo $available_events; ?></h3><p>Available Events</p></div>
            </div>
        </div>

        <!-- ── Merch Statistics ──────────────────────────────────────────── -->
        <h3 style="margin-bottom:14px;color:#4a5568;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">🛍️ Merchandise</h3>
        <div class="dashboard-grid" style="margin-bottom:30px;">
            <div class="dashboard-card" style="cursor:pointer;" onclick="window.location.href='my_merch.php'">
                <div class="card-icon blue"><span>📦</span></div>
                <div class="card-content"><h3><?php echo $merch_counts['total']; ?></h3><p>Total Orders</p></div>
            </div>
            <div class="dashboard-card" style="cursor:pointer;" onclick="window.location.href='my_merch.php?tab=pending'">
                <div class="card-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><span>⏳</span></div>
                <div class="card-content"><h3><?php echo $merch_counts['pending']; ?></h3><p>Pending Verification</p></div>
            </div>
            <div class="dashboard-card" style="cursor:pointer;" onclick="window.location.href='my_merch.php?tab=confirmed'">
                <div class="card-icon green"><span>✅</span></div>
                <div class="card-content"><h3><?php echo $merch_counts['confirmed']; ?></h3><p>Confirmed Orders</p></div>
            </div>
            <div class="dashboard-card" style="cursor:pointer;" onclick="window.location.href='browse_merchandise.php'">
                <div class="card-icon purple"><span>🛍️</span></div>
                <div class="card-content"><h3>Store</h3><p>Browse Merchandise</p></div>
            </div>
        </div>

        <!-- ── Quick Actions ─────────────────────────────────────────────── -->
        <div style="margin-bottom:30px;">
            <h2 style="margin-bottom:15px;color:#2d3748;">⚡ Quick Actions</h2>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="browse_events.php"        class="btn btn-primary">🔍 Browse Events</a>
                <a href="my_events.php"            class="btn btn-secondary">🎫 My Events</a>
                <a href="my_events.php?tab=attended" class="btn btn-success">✅ My Attendance</a>
                <a href="browse_merchandise.php"   class="btn btn-primary" style="background:#8308ac;">🛍️ Browse Merch</a>
                <a href="my_merch.php"             class="btn btn-secondary">📦 My Orders</a>
            </div>
        </div>

        <!-- ── Two-column layout ─────────────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

            <!-- My Upcoming Events -->
            <div class="table-container">
                <h2 style="padding:18px 20px;border-bottom:1px solid #e2e8f0;color:#2d3748;font-size:17px;">
                    📅 Upcoming Registered Events
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Venue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($my_events_result) > 0): ?>
                            <?php while($event = mysqli_fetch_assoc($my_events_result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                                    <td style="white-space:nowrap;"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align:center;color:#a0aec0;padding:20px;">
                                    No upcoming events. <a href="browse_events.php">Browse now!</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div style="padding:14px 20px;border-top:1px solid #e2e8f0;text-align:right;">
                    <a href="my_events.php" style="font-size:13px;color:#667eea;">View all my events →</a>
                </div>
            </div>

            <!-- Recent Merch Orders -->
            <div style="background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);overflow:hidden;">
                <h2 style="padding:18px 20px;border-bottom:1px solid #e2e8f0;color:#2d3748;font-size:17px;">
                    🛍️ Recent Merch Orders
                </h2>
                <div style="padding:16px 20px;">
                    <?php if(mysqli_num_rows($recent_orders_result) > 0): ?>
                        <?php while($ord = mysqli_fetch_assoc($recent_orders_result)):
                            $dot_class = 'dot-' . $ord['order_status'];
                            $status_label = ucfirst($ord['order_status']);
                        ?>
                            <div class="merch-mini-card">
                                <?php if($ord['primary_image']): ?>
                                    <img src="../uploads/merchandise/<?php echo htmlspecialchars($ord['primary_image']); ?>"
                                         class="merch-mini-thumb" alt="Merch">
                                <?php else: ?>
                                    <div class="merch-mini-thumb-empty">🛍️</div>
                                <?php endif; ?>
                                <div style="flex:1;">
                                    <div style="font-weight:600;color:#2d3748;font-size:14px;"><?php echo htmlspecialchars($ord['merch_name']); ?></div>
                                    <div style="font-size:13px;color:#718096;margin-top:2px;">
                                        ₹<?php echo number_format($ord['price'], 2); ?> &nbsp;•&nbsp;
                                        <span class="status-dot <?php echo $dot_class; ?>"></span>
                                        <?php echo $status_label; ?>
                                    </div>
                                    <div style="font-size:11px;color:#a0aec0;margin-top:2px;">
                                        <?php echo date('d M Y', strtotime($ord['ordered_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:30px 20px;color:#a0aec0;">
                            <div style="font-size:40px;margin-bottom:10px;">🛍️</div>
                            <p style="margin:0 0 12px;">No orders yet.</p>
                            <a href="browse_merchandise.php" class="btn btn-primary btn-sm">Browse Store</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="padding:14px 20px;border-top:1px solid #e2e8f0;text-align:right;">
                    <a href="my_merch.php" style="font-size:13px;color:#667eea;">View all orders →</a>
                </div>
            </div>

        </div><!-- /two-col -->

    </main>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>