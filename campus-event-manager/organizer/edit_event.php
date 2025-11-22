<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get event ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_events.php");
    exit();
}

$event_id = intval($_GET['id']);

// Fetch event details
$event_query = "SELECT * FROM events WHERE id = $event_id AND organizer_id = $organizer_id";
$event_result = mysqli_query($conn, $event_query);

if(mysqli_num_rows($event_result) == 0) {
    header("Location: manage_events.php");
    exit();
}

$event = mysqli_fetch_assoc($event_result);

// Handle update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_event'])) {
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
    $venue = mysqli_real_escape_string($conn, trim($_POST['venue']));
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $max_participants = intval($_POST['max_participants']);
    $registration_deadline = mysqli_real_escape_string($conn, $_POST['registration_deadline']);
    
    // Validation
    if(empty($title) || empty($description) || empty($event_date) || empty($venue)) {
        $error = "Please fill all required fields!";
    } elseif($max_participants < 1) {
        $error = "Maximum participants must be at least 1!";
    } else {
        $image_name = $event['image']; // Keep old image by default
        
        // Handle new image upload
        if(isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['event_image']['type'];
            $file_size = $_FILES['event_image']['size'];
            
            if(!in_array($file_type, $allowed_types)) {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed!";
            } elseif($file_size > 5242880) {
                $error = "File size must be less than 5MB!";
            } else {
                // Delete old image
                if(!empty($event['image']) && file_exists('../uploads/' . $event['image'])) {
                    unlink('../uploads/' . $event['image']);
                }
                
                // Upload new image
                $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                $image_name = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = '../uploads/' . $image_name;
                
                if(!move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload image!";
                    $image_name = $event['image']; // Revert to old image
                }
            }
        }
        
        // Handle image removal
        if(isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if(!empty($event['image']) && file_exists('../uploads/' . $event['image'])) {
                unlink('../uploads/' . $event['image']);
            }
            $image_name = "";
        }
        
        // Update event if no errors
        if(empty($error)) {
            $sql = "UPDATE events SET 
                    title = '$title',
                    description = '$description',
                    event_date = '$event_date',
                    venue = '$venue',
                    category = '$category',
                    max_participants = $max_participants,
                    registration_deadline = '$registration_deadline',
                    image = '$image_name'
                    WHERE id = $event_id AND organizer_id = $organizer_id";
            
            if(mysqli_query($conn, $sql)) {
                $success = "Event updated successfully! Redirecting...";
                header("refresh:2;url=manage_events.php");
            } else {
                $error = "Error: " . mysqli_error($conn);
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
    <title>Edit Event - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>✏️ Edit Event</h1>
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
                        <input type="text" name="title" required value="<?php echo htmlspecialchars($event['title']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" rows="5" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Event Date & Time *</label>
                            <input type="datetime-local" name="event_date" required 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Registration Deadline</label>
                            <input type="datetime-local" name="registration_deadline" 
                                   value="<?php echo $event['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($event['registration_deadline'])) : ''; ?>">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Venue *</label>
                            <input type="text" name="venue" required value="<?php echo htmlspecialchars($event['venue']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="">Select Category</option>
                                <option value="Technical" <?php echo $event['category'] == 'Technical' ? 'selected' : ''; ?>>Technical</option>
                                <option value="Cultural" <?php echo $event['category'] == 'Cultural' ? 'selected' : ''; ?>>Cultural</option>
                                <option value="Sports" <?php echo $event['category'] == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                                <option value="Workshop" <?php echo $event['category'] == 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="Seminar" <?php echo $event['category'] == 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                                <option value="Competition" <?php echo $event['category'] == 'Competition' ? 'selected' : ''; ?>>Competition</option>
                                <option value="Other" <?php echo $event['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Maximum Participants *</label>
                        <input type="number" name="max_participants" min="1" required 
                               value="<?php echo $event['max_participants']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Event Image</label>
                        
                        <?php if($event['image']): ?>
                            <div style="margin-bottom: 15px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                                <p style="font-weight: 500; margin-bottom: 10px;">Current Image:</p>
                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" 
                                     style="max-width: 300px; border-radius: 8px; border: 2px solid #e2e8f0;">
                                <br><br>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="remove_image" value="1" id="removeImage">
                                    <span style="color: #f56565; font-weight: 500;">Remove this image</span>
                                </label>
                            </div>
                        <?php endif; ?>
                        
                        <input type="file" name="event_image" accept="image/*" id="eventImage" onchange="previewImage(event)">
                        <small style="color: #718096; font-size: 13px;">
                            Upload new image (JPG, PNG, GIF - Max 5MB)
                        </small>
                        
                        <!-- Image Preview -->
                        <div id="imagePreview" style="margin-top: 15px; display: none;">
                            <p style="font-weight: 500; margin-bottom: 10px;">New Image Preview:</p>
                            <img id="preview" style="max-width: 300px; border-radius: 8px; border: 2px solid #e2e8f0;">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="update_event" class="btn btn-primary">
                            ✓ Update Event
                        </button>
                        <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // Image preview function
        function previewImage(event) {
            const file = event.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
                
                // Uncheck remove image if new image is selected
                const removeCheckbox = document.getElementById('removeImage');
                if(removeCheckbox) {
                    removeCheckbox.checked = false;
                }
            }
        }
        
        // Hide new image preview if remove image is checked
        const removeImageCheckbox = document.getElementById('removeImage');
        if(removeImageCheckbox) {
            removeImageCheckbox.addEventListener('change', function() {
                if(this.checked) {
                    document.getElementById('imagePreview').style.display = 'none';
                    document.getElementById('eventImage').value = '';
                }
            });
        }
    </script>
</body>
</html>