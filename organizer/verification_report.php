<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];

// Get filter parameters
$event_filter = "";
$selected_event_id = null;

if(isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $selected_event_id = intval($_GET['event_id']);
    $event_filter = "WHERE e.id = $selected_event_id AND e.organizer_id = $organizer_id";
}

// Fetch organizer's events
$events_query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as total_registered,
                 (SELECT COUNT(*) FROM ticket_verifications WHERE event_id = e.id) as total_verified
                 FROM events e 
                 WHERE e.organizer_id = $organizer_id AND e.status IN ('upcoming', 'ongoing', 'completed')
                 ORDER BY e.event_date DESC";
$events_result = mysqli_query($conn, $events_query);

// Fetch verification data for selected event or all events
$verification_query = "SELECT tv.*, e.title as event_title, e.event_date, e.venue,
                       u.full_name, u.email, u.department
                       FROM ticket_verifications tv
                       JOIN events e ON tv.event_id = e.id
                       JOIN users u ON tv.user_id = u.id
                       $event_filter
                       ORDER BY tv.verified_at DESC";
$verifications_result = mysqli_query($conn, $verification_query);

// Calculate statistics
$total_verified = mysqli_num_rows($verifications_result);

// Get event-wise statistics if showing all events
if(!$selected_event_id) {
    $stats_query = "SELECT e.id, e.title, 
                    (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered,
                    (SELECT COUNT(*) FROM ticket_verifications WHERE event_id = e.id) as verified
                    FROM events e
                    WHERE e.organizer_id = $organizer_id AND e.status IN ('upcoming', 'ongoing', 'completed')
                    ORDER BY e.event_date DESC";
    $stats_result = mysqli_query($conn, $stats_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Report - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 14px;
        }
        
        .event-summary {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78, #38a169);
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>📊 Ticket Verification Report</h1>
                <div style="display: flex; gap: 10px;">
                    <button onclick="exportToCSV()" class="btn btn-success">📥 Export CSV</button>
                    <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
                    <a href="verify_ticket.php" class="btn btn-primary">🎫 Verify Tickets</a>
                </div>
            </div>
            
            <!-- Event Filter -->
            <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="GET" action="">
                    <div style="display: flex; gap: 15px; align-items: end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Filter by Event</label>
                            <select name="event_id" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php 
                                mysqli_data_seek($events_result, 0);
                                while($evt = mysqli_fetch_assoc($events_result)): 
                                ?>
                                    <option value="<?php echo $evt['id']; ?>" 
                                            <?php echo ($selected_event_id == $evt['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($evt['title']); ?> - 
                                        <?php echo $evt['total_verified']; ?>/<?php echo $evt['total_registered']; ?> verified
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php if($selected_event_id): ?>
                            <a href="verification_report.php" class="btn btn-secondary">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Statistics -->
            <?php if(!$selected_event_id && isset($stats_result)): ?>
                <h2 style="margin-bottom: 20px;">📈 Event-wise Verification Summary</h2>
                <?php while($stat = mysqli_fetch_assoc($stats_result)): 
                    $percentage = $stat['registered'] > 0 ? round(($stat['verified'] / $stat['registered']) * 100) : 0;
                ?>
                    <div class="event-summary">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 10px 0; color: #2d3748;">
                                <?php echo htmlspecialchars($stat['title']); ?>
                            </h3>
                            <div style="display: flex; gap: 20px; font-size: 14px; color: #718096;">
                                <span>✅ Verified: <strong style="color: #48bb78;"><?php echo $stat['verified']; ?></strong></span>
                                <span>👥 Registered: <strong><?php echo $stat['registered']; ?></strong></span>
                                <span>📊 Rate: <strong style="color: #667eea;"><?php echo $percentage; ?>%</strong></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <a href="?event_id=<?php echo $stat['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
            
            <!-- Verification Details -->
            <h2 style="margin: 30px 0 20px 0;">🎫 Verification Details</h2>
            
            <?php if($total_verified > 0): ?>
                <div style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 10px 0;">Total Tickets Verified</h3>
                    <div style="font-size: 36px; font-weight: 700;"><?php echo $total_verified; ?></div>
                </div>
                
                <div class="table-container" id="verificationsTable">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Attendee Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <?php if(!$selected_event_id): ?>
                                    <th>Event</th>
                                <?php endif; ?>
                                <th>Ticket ID</th>
                                <th>Verified By</th>
                                <th>Verified At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            mysqli_data_seek($verifications_result, 0);
                            while($ver = mysqli_fetch_assoc($verifications_result)): 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ver['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ver['email']); ?></td>
                                    <td><?php echo htmlspecialchars($ver['department']); ?></td>
                                    <?php if(!$selected_event_id): ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ver['event_title']); ?></strong>
                                            <br>
                                            <small style="color: #718096;">
                                                <?php echo date('M d, Y', strtotime($ver['event_date'])); ?> @ 
                                                <?php echo htmlspecialchars($ver['venue']); ?>
                                            </small>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <code style="background: #f7fafc; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                            <?php echo htmlspecialchars($ver['ticket_code']); ?>
                                        </code>
                                    </td>
                                    <td><?php echo htmlspecialchars($ver['verified_by_name']); ?></td>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($ver['verified_at'])); ?></strong>
                                        <br>
                                        <small style="color: #718096;"><?php echo date('h:i A', strtotime($ver['verified_at'])); ?></small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 10px;">
                    <div style="font-size: 80px; margin-bottom: 20px;">🎫</div>
                    <h2 style="color: #718096; margin-bottom: 10px;">No Tickets Verified Yet</h2>
                    <p style="color: #a0aec0; margin-bottom: 20px;">
                        Start verifying tickets at your event to see the report here.
                    </p>
                    <a href="verify_ticket.php" class="btn btn-primary">Start Verifying</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function exportToCSV() {
            let csv = [];
            let rows = document.querySelectorAll("#verificationsTable table tr");
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                
                csv.push(row.join(","));
            }
            
            let csv_string = csv.join("\n");
            let filename = 'ticket_verifications_' + new Date().toLocaleDateString() + '.csv';
            
            let link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('target', '_blank');
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv_string));
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
    
    <style>
        @media print {
            .sidebar, .content-header button, .content-header a {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
            }
        }
    </style>
    <script src="../assets/js/script.js"></script>
</body>
</html>