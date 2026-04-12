<?php
session_start();
require_once '../config/database.php';
require_once '../config/co_organizer_helper.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];

// Get filter parameters
$selected_event_id = null;
$selected_event_title = "All Events";
$event_filter_sql = "";

if(isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $selected_event_id = intval($_GET['event_id']);

    // Verify the organizer owns or co-manages this event
    if(!canViewEvent($conn, $selected_event_id, $organizer_id)) {
        header("Location: attendance.php");
        exit();
    }

    $title_q = "SELECT title FROM events WHERE id = $selected_event_id AND organizer_id = $organizer_id";
    $title_r = mysqli_query($conn, $title_q);
    if(mysqli_num_rows($title_r) > 0) {
        $selected_event_title = mysqli_fetch_assoc($title_r)['title'];
        $event_filter_sql = "AND e.id = $selected_event_id";
    }
}

// Fetch organizer's events for the dropdown
$my_events_query = "SELECT id, title FROM events
                    WHERE organizer_id = $organizer_id AND status IN ('upcoming','ongoing','completed')
                    ORDER BY event_date DESC";
$my_events_result = mysqli_query($conn, $my_events_query);

// Fetch attendance records (only students whose tickets have been verified)
$attendance_query = "SELECT tv.verified_at, tv.ticket_code, tv.verified_by_name,
                     u.full_name, u.email, u.department, u.year,
                     e.title as event_title, e.event_date, e.venue
                     FROM ticket_verifications tv
                     JOIN events e ON tv.event_id = e.id
                     JOIN users u ON tv.user_id = u.id
                     WHERE e.organizer_id = $organizer_id $event_filter_sql
                     ORDER BY tv.verified_at DESC";
$attendance_result = mysqli_query($conn, $attendance_query);
$total_attended = mysqli_num_rows($attendance_result);

// Summary stats per event (for the overview panel when no filter)
if(!$selected_event_id) {
    $summary_query = "SELECT e.id, e.title, e.event_date, e.venue, e.status,
                      (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status IN ('registered','attended')) as total_registered,
                      (SELECT COUNT(*) FROM ticket_verifications WHERE event_id = e.id) as total_attended
                      FROM events e
                      WHERE e.organizer_id = $organizer_id AND e.status IN ('upcoming','ongoing','completed')
                      ORDER BY e.event_date DESC";
    $summary_result = mysqli_query($conn, $summary_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .attendance-summary {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .summary-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }
        .summary-meta {
            font-size: 13px;
            color: #718096;
            margin: 4px 0 10px 0;
        }
        .summary-counts {
            display: flex;
            gap: 20px;
            font-size: 14px;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78, #38a169);
        }
        .status-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-upcoming  { background: #bee3f8; color: #2c5282; }
        .status-ongoing   { background: #c6f6d5; color: #276749; }
        .status-completed { background: #e9d8fd; color: #553c9a; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>✅ Event Attendance</h1>
                <div style="display: flex; gap: 10px;">
                    <button onclick="exportToCSV()" class="btn btn-success">📥 Export CSV</button>
                    <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
                    <a href="verify_ticket.php" class="btn btn-primary">🎫 Verify Tickets</a>
                </div>
            </div>

            <!-- Event Filter -->
            <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="GET" action="">
                    <div style="display: flex; gap: 15px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Filter by Event</label>
                            <select name="event_id" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php 
                                mysqli_data_seek($my_events_result, 0);
                                while($evt = mysqli_fetch_assoc($my_events_result)): ?>
                                    <option value="<?php echo $evt['id']; ?>"
                                            <?php echo ($selected_event_id == $evt['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($evt['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php if($selected_event_id): ?>
                            <a href="attendance.php" class="btn btn-secondary" style="white-space: nowrap;">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Stats banner -->
            <div style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 25px;">
                <h3 style="margin: 0 0 6px 0;">📊 Attendance Overview</h3>
                <p style="font-size: 14px; opacity: 0.9; margin: 0 0 6px 0;">
                    Showing attendance for: <strong><?php echo htmlspecialchars($selected_event_title); ?></strong>
                </p>
                <p style="font-size: 28px; font-weight: 700; margin: 0;">
                    <?php echo $total_attended; ?> Attendee<?php echo $total_attended != 1 ? 's' : ''; ?>
                </p>
            </div>

            <!-- Per-event summary (shown when no event filter) -->
            <?php if(!$selected_event_id && isset($summary_result) && mysqli_num_rows($summary_result) > 0): ?>
                <h2 style="margin-bottom: 15px; color: #2d3748;">📈 Event-wise Summary</h2>
                <?php while($s = mysqli_fetch_assoc($summary_result)):
                    $pct = $s['total_registered'] > 0
                        ? round(($s['total_attended'] / $s['total_registered']) * 100)
                        : 0;
                ?>
                    <div class="attendance-summary">
                        <div class="summary-header">
                            <div style="flex: 1; min-width: 0;">
                                <p class="summary-title">
                                    <?php echo htmlspecialchars($s['title']); ?>
                                    <span class="status-pill status-<?php echo $s['status']; ?>" style="margin-left: 8px;">
                                        <?php echo ucfirst($s['status']); ?>
                                    </span>
                                </p>
                                <p class="summary-meta">
                                    <?php echo date('M d, Y', strtotime($s['event_date'])); ?>
                                    <?php if($s['venue']): ?> · <?php echo htmlspecialchars($s['venue']); ?><?php endif; ?>
                                </p>
                                <div class="summary-counts">
                                    <span>✅ Attended: <strong style="color: #48bb78;"><?php echo $s['total_attended']; ?></strong></span>
                                    <span>👥 Registered: <strong><?php echo $s['total_registered']; ?></strong></span>
                                    <span>📊 Rate: <strong style="color: #667eea;"><?php echo $pct; ?>%</strong></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                            </div>
                            <a href="?event_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary" style="margin-left: 20px; white-space: nowrap;">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;">
            <?php endif; ?>

            <!-- Detailed attendance table -->
            <h2 style="margin-bottom: 15px; color: #2d3748;">🎫 Attendance Details</h2>

            <?php if($total_attended > 0): ?>
                <div class="table-container" id="attendanceTable">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Year</th>
                                <?php if(!$selected_event_id): ?><th>Event</th><?php endif; ?>
                                <th>Ticket Code</th>
                                <th>Verified By</th>
                                <th>Attended At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            mysqli_data_seek($attendance_result, 0);
                            while($att = mysqli_fetch_assoc($attendance_result)): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($att['full_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($att['email']); ?></td>
                                    <td><?php echo htmlspecialchars($att['department'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($att['year'] ?? '—'); ?></td>
                                    <?php if(!$selected_event_id): ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($att['event_title']); ?></strong><br>
                                            <small style="color: #718096;">
                                                <?php echo date('M d, Y', strtotime($att['event_date'])); ?>
                                                <?php if($att['venue']): ?> @ <?php echo htmlspecialchars($att['venue']); ?><?php endif; ?>
                                            </small>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <code style="background: #f7fafc; padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                                            <?php echo htmlspecialchars($att['ticket_code']); ?>
                                        </code>
                                        <?php if(strpos($att['ticket_code'], 'MANUAL-') === 0): ?>
                                            <br><small style="color: #ed8936; font-size: 10px;">Manual entry</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($att['verified_by_name']); ?></td>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($att['verified_at'])); ?></strong><br>
                                        <small style="color: #718096;"><?php echo date('h:i A', strtotime($att['verified_at'])); ?></small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 10px;">
                    <div style="font-size: 70px; margin-bottom: 15px;">🎫</div>
                    <h2 style="color: #718096; margin-bottom: 8px;">No Attendance Records Yet</h2>
                    <p style="color: #a0aec0; margin-bottom: 20px;">
                        <?php if($selected_event_id): ?>
                            No one has been verified for this event yet.
                        <?php else: ?>
                            Start verifying tickets to track attendance.
                        <?php endif; ?>
                    </p>
                    <a href="verify_ticket.php" class="btn btn-primary">🎫 Go to Ticket Verification</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function exportToCSV() {
            const rows = document.querySelectorAll("#attendanceTable table tr");
            if(!rows.length) { alert('No data to export.'); return; }

            const csv = [];
            rows.forEach(row => {
                const cols = row.querySelectorAll("td, th");
                const data = Array.from(cols).map(col => {
                    let text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
                    return '"' + text.replace(/"/g, '""') + '"';
                });
                csv.push(data.join(","));
            });

            const csvString = csv.join("\n");
            const filename  = 'attendance_<?php echo $selected_event_id ?? 'all'; ?>_' + new Date().toLocaleDateString().replace(/\//g, '-') + '.csv';

            const link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

    <style>
        @media print {
            .sidebar, .content-header button, .content-header a { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 20px !important; }
        }
    </style>
    <script src="../assets/js/script.js"></script>
</body>
</html>