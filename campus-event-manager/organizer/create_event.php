<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$error = "";
$success = "";

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])) {
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
    $venue = mysqli_real_escape_string($conn, trim($_POST['venue']));
    $event_type = mysqli_real_escape_string($conn, $_POST['event_type']); // NEW
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $max_participants = intval($_POST['max_participants']);
    $registration_deadline = mysqli_real_escape_string($conn, $_POST['registration_deadline']);
    $registration_link = mysqli_real_escape_string($conn, trim($_POST['registration_link'])); // NEW
    $organizer_id = $_SESSION['user_id'];
    
    // Validation
    if(empty($title) || empty($description) || empty($event_date) || empty($venue) || empty($event_type)) {
        $error = "Please fill all required fields!";
    } elseif($max_participants < 1) {
        $error = "Maximum participants must be at least 1!";
    } else {
        // Handle image upload
        $image_name = "";
        if(isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['event_image']['type'];
            $file_size = $_FILES['event_image']['size'];
            
            if(!in_array($file_type, $allowed_types)) {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed!";
            } elseif($file_size > 5242880) {
                $error = "File size must be less than 5MB!";
            } else {
                $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                $image_name = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = '../uploads/' . $image_name;
                
                if(!move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload image!";
                    $image_name = "";
                }
            }
        }
        
        // Insert event if no errors
        if(empty($error)) {
            $sql = "INSERT INTO events (title, description, organizer_id, event_date, venue, event_type, category, 
                    max_participants, registration_deadline, registration_link, image, status) 
                    VALUES ('$title', '$description', $organizer_id, '$event_date', '$venue', '$event_type', '$category', 
                    $max_participants, ".($registration_deadline ? "'$registration_deadline'" : "NULL").", 
                    ".($registration_link ? "'$registration_link'" : "NULL").", '$image_name', 'upcoming')";
            
            if(mysqli_query($conn, $sql)) {
                $success = "Event created successfully! Redirecting...";
                header("refresh:2;url=manage_events.php");
            } else {
                $error = "Error: " . mysqli_error($conn);
                if(!empty($image_name) && file_exists($upload_path)) {
                    unlink($upload_path);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>➕ Create New Event</h1>
                <a href="manage_events.php" class="btn btn-secondary">← Back to Events</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Event Title *</label>
                        <input type="text" name="title" placeholder="e.g., Tech Fest 2025" required 
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" rows="5" placeholder="Describe your event..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Event Date & Time *</label>
                            <input type="datetime-local" name="event_date" required 
                                   value="<?php echo isset($_POST['event_date']) ? $_POST['event_date'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Registration Deadline</label>
                            <input type="datetime-local" name="registration_deadline" 
                                   value="<?php echo isset($_POST['registration_deadline']) ? $_POST['registration_deadline'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Venue *</label>
                            <input type="text" name="venue" placeholder="e.g., Main Auditorium" required 
                                   value="<?php echo isset($_POST['venue']) ? htmlspecialchars($_POST['venue']) : ''; ?>">
                        </div>
                        
                        <!-- NEW: Event Type -->
                        <div class="form-group">
                            <label>Event Type *</label>
                            <select name="event_type" required>
                                <option value="">Select Type</option>
                                <option value="offline" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'offline') ? 'selected' : ''; ?>>Offline (In-Person)</option>
                                <option value="online" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'online') ? 'selected' : ''; ?>>Online (Virtual)</option>
                                <option value="hybrid" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'hybrid') ? 'selected' : ''; ?>>Hybrid (Both)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="">Select Category</option>
                                <option value="Technical" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Technical') ? 'selected' : ''; ?>>Technical</option>
                                <option value="Cultural" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Cultural') ? 'selected' : ''; ?>>Cultural</option>
                                <option value="Sports" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Sports') ? 'selected' : ''; ?>>Sports</option>
                                <option value="Workshop" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
                                <option value="Seminar" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Seminar') ? 'selected' : ''; ?>>Seminar</option>
                                <option value="Competition" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Competition') ? 'selected' : ''; ?>>Competition</option>
                                <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Maximum Participants *</label>
                            <input type="number" name="max_participants" min="1" value="100" required 
                                   value="<?php echo isset($_POST['max_participants']) ? $_POST['max_participants'] : '100'; ?>">
                            <small style="color: #718096; font-size: 13px;">Set the maximum number of students who can register</small>
                        </div>
                    </div>
                    
                    <!-- NEW: External Registration Link -->
                    <div class="form-group">
                        <label>External Registration Link (Optional)</label>
                        <input type="url" name="registration_link" placeholder="https://forms.google.com/..." 
                               value="<?php echo isset($_POST['registration_link']) ? htmlspecialchars($_POST['registration_link']) : ''; ?>">
                        <small style="color: #718096; font-size: 13px;">
                            If you want to use Google Forms or external registration instead of internal system
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Event Image (Optional)</label>
                        <input type="file" name="event_image" accept="image/*" id="eventImage" onchange="previewImage(event)">
                        <small style="color: #718096; font-size: 13px;">
                            Supported formats: JPG, PNG, GIF (Max 5MB)
                        </small>
                        
                        <div id="imagePreview" style="margin-top: 15px; display: none;">
                            <p style="font-weight: 500; margin-bottom: 10px;">Image Preview:</p>
                            <img id="preview" style="max-width: 300px; border-radius: 8px; border: 2px solid #e2e8f0;">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="create_event" class="btn btn-primary">
                            ✓ Create Event
                        </button>
                        <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>