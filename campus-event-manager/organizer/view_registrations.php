<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];

// If specific event_id is provided
$event_filter = "";
$selected_event_id = null;
$selected_event_title = "All Events";

if(isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $selected_event_id = intval($_GET['event_id']);
    
    // Verify event belongs to this organizer
    $verify_query = "SELECT title FROM events WHERE id = $selected_event_id AND organizer_id = $organizer_id";
    $verify_result = mysqli_query($conn, $verify_query);
    
    if(mysqli_num_rows($verify_result) > 0) {
        $event_filter = "AND e.id = $selected_event_id";
        $selected_event_title = mysqli_fetch_assoc($verify_result)['title'];
    }
}

// Fetch all events by this organizer for dropdown
$my_events_query = "SELECT id, title FROM events WHERE organizer_id = $organizer_id ORDER BY event_date DESC";
$my_events_result = mysqli_query($conn, $my_events_query);

// Fetch registrations
$registrations_query = "SELECT r.*, e.title as event_title, e.event_date, e.venue, 
                        u.username, u.email, u.full_name
                        FROM registrations r
                        JOIN events e ON r.event_id = e.id
                        JOIN users u ON r.user_id = u.id
                        WHERE e.organizer_id = $organizer_id $event_filter
                        ORDER BY r.registration_date DESC";
$registrations_result = mysqli_query($conn, $registrations_query);

// Get statistics
$total_registrations = mysqli_num_rows($registrations_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Registrations - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>👥 Event Registrations</h1>
                <div style="display: flex; gap: 10px;">
                    <button onclick="exportToCSV()" class="btn btn-success">📥 Export CSV</button>
                    <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
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
                                mysqli_data_seek($my_events_result, 0);
                                while($evt = mysqli_fetch_assoc($my_events_result)): 
                                ?>
                                    <option value="<?php echo $evt['id']; ?>" 
                                            <?php echo ($selected_event_id == $evt['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($evt['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php if($selected_event_id): ?>
                            <a href="view_registrations.php" class="btn btn-secondary" style="white-space: nowrap;">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Statistics -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;">📊 Registration Statistics</h3>
                <p style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">
                    Showing registrations for: <strong><?php echo htmlspecialchars($selected_event_title); ?></strong>
                </p>
                <p style="font-size: 24px; font-weight: bold; margin: 0;">
                    Total Registrations: <?php echo $total_registrations; ?>
                </p>
            </div>
            
            <?php if($total_registrations > 0): ?>
                <div class="table-container" id="registrationsTable">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Event</th>
                                <th>Event Date</th>
                                <th>Venue</th>
                                <th>Status</th>
                                <th>Registered On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            mysqli_data_seek($registrations_result, 0);
                            while($reg = mysqli_fetch_assoc($registrations_result)): 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($reg['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($reg['username']); ?></td>
                                    <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                    <td><?php echo htmlspecialchars($reg['event_title']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($reg['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($reg['venue']); ?></td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'registered' => '#48bb78',
                                            'waitlisted' => '#ed8936',
                                            'cancelled' => '#f56565'
                                        ];
                                        $color = $status_colors[$reg['status']] ?? '#718096';
                                        ?>
                                        <span style="padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            <?php echo ucfirst($reg['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($reg['registration_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 10px;">
                    <h2 style="color: #718096; margin-bottom: 10px;">📭 No Registrations Yet</h2>
                    <p style="color: #a0aec0;">
                        <?php if($selected_event_id): ?>
                            No students have registered for this event yet.
                        <?php else: ?>
                            No registrations found for any of your events.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Export to CSV function
        function exportToCSV() {
            let csv = [];
            let rows = document.querySelectorAll("#registrationsTable table tr");
            
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
            let filename = 'event_registrations_' + new Date().toLocaleDateString() + '.csv';
            
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
</body>
</html>