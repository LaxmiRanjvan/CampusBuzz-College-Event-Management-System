<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if(!$event_id) {
    header("Location: browse_events.php");
    exit();
}

$error = "";
$success = "";

// Fetch event details
$event_sql = "SELECT e.*, u.full_name as organizer_name,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
               FROM events e 
               JOIN users u ON e.organizer_id = u.id 
               WHERE e.id = $event_id";
$event_result = mysqli_query($conn, $event_sql);

if(mysqli_num_rows($event_result) == 0) {
    header("Location: browse_events.php");
    exit();
}

$event = mysqli_fetch_assoc($event_result);
$seats_left = $event['max_participants'] - $event['registered_count'];
$is_full = $seats_left <= 0;

// Check if already registered
$check_reg = "SELECT * FROM registrations WHERE event_id=$event_id AND user_id=$user_id";
$already_registered = mysqli_num_rows(mysqli_query($conn, $check_reg)) > 0;

if($already_registered) {
    header("Location: my_events.php?msg=already_registered");
    exit();
}

// Handle registration
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $department = mysqli_real_escape_string($conn, trim($_POST['department']));
    $year = mysqli_real_escape_string($conn, trim($_POST['year']));
    $notes = mysqli_real_escape_string($conn, trim($_POST['notes']));
    
    // Validation
    if(empty($name) || empty($email) || empty($phone)) {
        $error = "Please fill in all required fields!";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif($is_full) {
        $error = "Sorry, this event is full!";
    } else {
        // Check seats again (double-check)
        $check_seats_query = "SELECT 
                             e.max_participants,
                             COUNT(r.id) as current_registrations,
                             (e.max_participants - COUNT(r.id)) as available 
                             FROM events e 
                             LEFT JOIN registrations r ON e.id = r.event_id AND r.status = 'registered'
                             WHERE e.id = $event_id 
                             GROUP BY e.id, e.max_participants";
        $seats_result = mysqli_query($conn, $check_seats_query);
        
        if(!$seats_result) {
            $error = "Database error: " . mysqli_error($conn);
        } else {
            $seats_data = mysqli_fetch_assoc($seats_result);
            
            if(!$seats_data || $seats_data['available'] <= 0) {
                $error = "Sorry, this event just filled up!";
            } else {
                // Insert registration
                $insert_sql = "INSERT INTO registrations 
                              (event_id, user_id, name, email, phone, department, year, notes, status, registration_date) 
                              VALUES 
                              ($event_id, $user_id, '$name', '$email', '$phone', '$department', '$year', '$notes', 'registered', NOW())";
                
                if(mysqli_query($conn, $insert_sql)) {
                    $success = "Registration successful! Redirecting to your events...";
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'my_events.php?msg=registered';
                        }, 2000);
                    </script>";
                } else {
                    $error = "Registration failed: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Pre-fill user data
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user_data = mysqli_fetch_assoc($user_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for <?php echo htmlspecialchars($event['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .registration-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px 16px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            background: #f7fafc;
        }
        
        .registration-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .registration-form {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .form-header {
            margin-bottom: 30px;
        }
        
        .form-header h1 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #718096;
            font-size: 16px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-grid-full {
            grid-column: 1 / -1;
        }
        
        .event-summary {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .summary-header {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .summary-item {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .summary-value {
            color: #2d3748;
            font-size: 16px;
            font-weight: 500;
        }
        
        .seats-alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }
        
        .seats-alert.limited {
            background: #feebc8;
            color: #7c2d12;
        }
        
        .seats-alert.good {
            background: #c6f6d5;
            color: #276749;
        }
        
        .required-note {
            color: #f56565;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 968px) {
            .registration-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .event-summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="registration-container">
                <a href="view_event.php?id=<?php echo $event_id; ?>" class="back-button">
                    ← Back to Event Details
                </a>
                
                <?php if($is_full): ?>
                    <div style="background: white; padding: 60px; text-align: center; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);">
                        <div style="font-size: 80px; margin-bottom: 20px;">😔</div>
                        <h2 style="color: #2d3748; margin-bottom: 10px;">Event Full</h2>
                        <p style="color: #718096; margin-bottom: 20px;">
                            Sorry, this event has reached its maximum capacity.
                        </p>
                        <a href="browse_events.php" class="btn btn-primary">Browse Other Events</a>
                    </div>
                <?php else: ?>
                    <div class="registration-grid">
                        <div class="registration-form">
                            <div class="form-header">
                                <h1>🎫 Event Registration</h1>
                                <p>Fill in your details to register for this event</p>
                            </div>
                            
                            <?php if($error): ?>
                                <div class="alert alert-error" style="background: #fed7d7; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                    ❌ <?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($success): ?>
                                <div class="alert alert-success" style="background: #c6f6d5; color: #276749; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                    ✓ <?php echo $success; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($seats_left <= 10 && !$success): ?>
                                <div class="seats-alert limited">
                                    ⚠️ Only <?php echo $seats_left; ?> seats remaining! Register now.
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!$success): ?>
                            <p class="required-note">* Required fields</p>
                            
                            <form method="POST" action="">
                                <div class="form-grid">
                                    <div class="form-group form-grid-full">
                                        <label>Full Name *</label>
                                        <input type="text" name="name" required 
                                               value="<?php echo htmlspecialchars($user_data['full_name']); ?>"
                                               placeholder="Enter your full name">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Email Address *</label>
                                        <input type="email" name="email" required 
                                               value="<?php echo htmlspecialchars($user_data['email']); ?>"
                                               placeholder="your.email@example.com">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Phone Number *</label>
                                        <input type="tel" name="phone" required 
                                               value="<?php echo isset($user_data['phone']) ? htmlspecialchars($user_data['phone']) : ''; ?>"
                                               placeholder="+91 1234567890">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Department</label>
                                        <input type="text" name="department" 
                                               value="<?php echo isset($user_data['department']) ? htmlspecialchars($user_data['department']) : ''; ?>"
                                               placeholder="e.g., Computer Science">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Year/Semester</label>
                                        <select name="year">
                                            <option value="">Select Year</option>
                                            <option value="1st Year" <?php echo (isset($user_data['year']) && $user_data['year'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                            <option value="2nd Year" <?php echo (isset($user_data['year']) && $user_data['year'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                            <option value="3rd Year" <?php echo (isset($user_data['year']) && $user_data['year'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                            <option value="4th Year" <?php echo (isset($user_data['year']) && $user_data['year'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                            <option value="Graduate" <?php echo (isset($user_data['year']) && $user_data['year'] == 'Graduate') ? 'selected' : ''; ?>>Graduate</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group form-grid-full">
                                        <label>Additional Notes (Optional)</label>
                                        <textarea name="notes" rows="4" 
                                                  placeholder="Any special requirements or questions..."></textarea>
                                    </div>
                                </div>
                                
                                <button type="submit" name="register" class="btn btn-primary" 
                                        style="width: 100%; margin-top: 20px;">
                                    Complete Registration
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-summary">
                            <h3 class="summary-header">📋 Event Summary</h3>
                            
                            <div class="summary-item">
                                <div class="summary-label">Event Name</div>
                                <div class="summary-value"><?php echo htmlspecialchars($event['title']); ?></div>
                            </div>
                            
                            <div class="summary-item">
                                <div class="summary-label">Date & Time</div>
                                <div class="summary-value">
                                    <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?><br>
                                    <?php echo date('h:i A', strtotime($event['event_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="summary-item">
                                <div class="summary-label">Venue</div>
                                <div class="summary-value"><?php echo htmlspecialchars($event['venue']); ?></div>
                            </div>
                            
                            <?php if($event['category']): ?>
                            <div class="summary-item">
                                <div class="summary-label">Category</div>
                                <div class="summary-value"><?php echo htmlspecialchars($event['category']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="summary-item">
                                <div class="summary-label">Organizer</div>
                                <div class="summary-value"><?php echo htmlspecialchars($event['organizer_name']); ?></div>
                            </div>
                            
                            <div class="summary-item">
                                <div class="summary-label">Available Seats</div>
                                <div class="summary-value" style="color: <?php echo $seats_left <= 10 ? '#ed8936' : '#48bb78'; ?>;">
                                    <?php echo $seats_left; ?> / <?php echo $event['max_participants']; ?>
                                </div>
                            </div>
                            
                            <?php if($seats_left > 10): ?>
                                <div class="seats-alert good" style="margin-top: 20px;">
                                    ✓ Good availability
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>