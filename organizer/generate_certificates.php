<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error   = "";
$success = "";

// ─── Fetch organizer's events (completed/ongoing) ───────────────────────────
$events_query = "SELECT e.id, e.title, e.event_date, e.venue,
                        (SELECT COUNT(*) FROM ticket_verifications WHERE event_id = e.id) as verified_count,
                        (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'registered') as registered_count
                 FROM events e
                 WHERE e.organizer_id = $organizer_id
                   AND e.status IN ('completed','ongoing')
                 ORDER BY e.event_date DESC";
$events_result = mysqli_query($conn, $events_query);

// ─── Selected event ──────────────────────────────────────────────────────────
$event_id = null;
$event    = null;
$attendees = [];
if (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);

    $ev_q = "SELECT * FROM events WHERE id = $event_id AND organizer_id = $organizer_id LIMIT 1";
    $ev_r = mysqli_query($conn, $ev_q);
    if (mysqli_num_rows($ev_r) > 0) {
        $event = mysqli_fetch_assoc($ev_r);

        // Attendees = ticket-verified students
        $att_q = "SELECT u.id, u.full_name, u.email, u.department, u.year,
                         tv.verified_at, tv.ticket_code
                  FROM ticket_verifications tv
                  JOIN users u ON tv.user_id = u.id
                  WHERE tv.event_id = $event_id
                  ORDER BY u.full_name ASC";
        $att_r = mysqli_query($conn, $att_q);
        while ($row = mysqli_fetch_assoc($att_r)) {
            $attendees[] = $row;
        }
    } else {
        $event_id = null;
    }
}

// ─── Handle organizer signature upload ──────────────────────────────────────
$signature_path = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_signature') {
    if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/png', 'image/jpeg', 'image/jpg'];
        if (in_array($_FILES['signature_image']['type'], $allowed)) {
            $sig_dir  = '../uploads/signatures/';
            if (!is_dir($sig_dir)) mkdir($sig_dir, 0755, true);
            $sig_file = 'sig_' . $organizer_id . '_' . time() . '.' . pathinfo($_FILES['signature_image']['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($_FILES['signature_image']['tmp_name'], $sig_dir . $sig_file)) {
                // Store signature path in session for this session
                $_SESSION['organizer_signature'] = $sig_file;
                $success = "Signature uploaded successfully!";
            } else {
                $error = "Failed to save signature file.";
            }
        } else {
            $error = "Only PNG/JPG images are allowed for signature.";
        }
    } else {
        $error = "Please select a valid image file.";
    }
    // Redirect to preserve GET params
    $redirect = 'generate_certificates.php' . ($event_id ? "?event_id=$event_id" : '');
    header("Location: $redirect" . ($error ? "&err=" . urlencode($error) : ($success ? "&msg=" . urlencode($success) : '')));
    exit();
}

// ─── Handle send certificates via email ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_certificates') {
    $send_event_id   = intval($_POST['event_id']);
    $cert_type_map   = $_POST['cert_types'] ?? [];   // [user_id => cert_type]
    $custom_label_map= $_POST['custom_labels'] ?? []; // [user_id => label]
    $organizer_name  = trim($_POST['organizer_name'] ?? '');
    $org_title       = trim($_POST['organizer_title'] ?? 'Event Organizer');
    $institute_name  = trim($_POST['institute_name'] ?? 'Campus Event Management');
    $sig_file        = $_SESSION['organizer_signature'] ?? '';

    // Validate event ownership
    $chk = mysqli_query($conn, "SELECT title, event_date, venue FROM events WHERE id = $send_event_id AND organizer_id = $organizer_id LIMIT 1");
    if (mysqli_num_rows($chk) === 0) {
        $error = "Event not found!";
    } else {
        $ev_data     = mysqli_fetch_assoc($chk);
        $event_title = $ev_data['title'];
        $event_date  = date('F d, Y', strtotime($ev_data['event_date']));
        $event_venue = $ev_data['venue'];

        $sent_count   = 0;
        $failed_count = 0;

        foreach ($cert_type_map as $user_id => $cert_type) {
            $user_id   = intval($user_id);
            $cert_type = trim($cert_type);
            if ($cert_type === '') continue;

            // Fetch user
            $u_r = mysqli_query($conn, "SELECT full_name, email, department FROM users WHERE id = $user_id LIMIT 1");
            if (!$u_r || mysqli_num_rows($u_r) === 0) continue;
            $user = mysqli_fetch_assoc($u_r);

            $custom_label = isset($custom_label_map[$user_id]) ? trim($custom_label_map[$user_id]) : '';
            $cert_label   = match($cert_type) {
                '1st'   => '1st Place 🥇',
                '2nd'   => '2nd Place 🥈',
                '3rd'   => '3rd Place 🥉',
                'participation' => 'Certificate of Participation',
                'custom'=> $custom_label ?: 'Certificate of Achievement',
                default => 'Certificate of Participation',
            };

            $is_winner   = in_array($cert_type, ['1st','2nd','3rd','custom']) && $cert_type !== 'participation';
            $medal_emoji = match($cert_type) { '1st' => '🥇', '2nd' => '🥈', '3rd' => '🥉', default => '🏆' };
            $bg_gradient = match($cert_type) {
                '1st'   => 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)',
                '2nd'   => 'linear-gradient(135deg, #c0c0c0 0%, #a8a8a8 100%)',
                '3rd'   => 'linear-gradient(135deg, #cd7f32 0%, #b8621a 100%)',
                'participation' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                default => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            };

            // Signature image (inline base64)
            $sig_b64 = '';
            if ($sig_file && file_exists('../uploads/signatures/' . $sig_file)) {
                $sig_mime = mime_content_type('../uploads/signatures/' . $sig_file);
                $sig_b64  = 'data:' . $sig_mime . ';base64,' . base64_encode(file_get_contents('../uploads/signatures/' . $sig_file));
            }

            $cert_id = strtoupper('CERT-' . date('Ymd') . '-' . $user_id . '-' . $send_event_id);

            // Build certificate HTML (self-contained, email-safe)
            $cert_html = buildCertificateHTML(
                $user['full_name'], $cert_label, $event_title, $event_date, $event_venue,
                $organizer_name, $org_title, $institute_name, $sig_b64, $cert_id,
                $bg_gradient, $is_winner, $medal_emoji
            );

            // Send email
            $mail = new PHPMailer(true);
            try {
                setupMailer($mail); // uses your email_config.php values
                $mail->addAddress($user['email'], $user['full_name']);
                $mail->Subject = ($is_winner ? "🏆 " : "🎓 ") . "Your Certificate – $event_title";
                $mail->isHTML(true);
                $mail->Body = $cert_html;
                $mail->AltBody = "Congratulations {$user['full_name']}! Please view this email in an HTML-capable email client to see your certificate.";
                $mail->send();
                $sent_count++;

                // Log certificate issuance
                $cert_type_escaped = mysqli_real_escape_string($conn, $cert_type);
                $label_escaped     = mysqli_real_escape_string($conn, $cert_label);
                $cert_id_escaped   = mysqli_real_escape_string($conn, $cert_id);
                mysqli_query($conn, "INSERT INTO certificates (event_id, user_id, cert_type, cert_label, cert_id, issued_at, emailed)
                                     VALUES ($send_event_id, $user_id, '$cert_type_escaped', '$label_escaped', '$cert_id_escaped', NOW(), 1)
                                     ON DUPLICATE KEY UPDATE cert_type='$cert_type_escaped', cert_label='$label_escaped', issued_at=NOW(), emailed=1");
            } catch (Exception $e) {
                $failed_count++;
            }
        }

        if ($sent_count > 0) {
            $success = "✅ $sent_count certificate(s) sent successfully!" . ($failed_count > 0 ? " ($failed_count failed)" : "");
        } else {
            $error = "❌ Failed to send certificates. Please check your email configuration.";
        }
    }
    $redirect = 'generate_certificates.php?event_id=' . $send_event_id . ($success ? '&msg=' . urlencode($success) : '&err=' . urlencode($error));
    header("Location: $redirect");
    exit();
}

// ─── Flash messages from redirect ───────────────────────────────────────────
if (isset($_GET['msg'])) $success = htmlspecialchars($_GET['msg']);
if (isset($_GET['err'])) $error   = htmlspecialchars($_GET['err']);

$sig_file = $_SESSION['organizer_signature'] ?? '';

// ─── Helper: build certificate HTML ─────────────────────────────────────────
function buildCertificateHTML(
    $recipient_name, $cert_label, $event_title, $event_date, $event_venue,
    $organizer_name, $org_title, $institute_name, $sig_b64, $cert_id,
    $bg_gradient, $is_winner, $medal_emoji
) {
    $winner_bar = $is_winner
        ? "<div style='background:rgba(255,255,255,0.25);padding:10px 30px;border-radius:30px;display:inline-block;margin-bottom:20px;font-size:18px;font-weight:700;letter-spacing:1px;color:#fff;'>$medal_emoji $cert_label</div>"
        : '';

    $sig_html = $sig_b64
        ? "<img src='$sig_b64' style='max-height:60px;max-width:180px;object-fit:contain;margin-bottom:4px;' alt='Signature'>"
        : "<div style='width:180px;border-bottom:2px solid #2d3748;margin-bottom:4px;height:40px;'></div>";

    return "
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Certificate – $event_title</title>
</head>
<body style='margin:0;padding:0;background:#f0f4f8;font-family:Georgia,serif;'>
<div style='max-width:750px;margin:30px auto;padding:20px;'>

  <!-- Outer decorative border -->
  <div style='border:6px solid transparent;border-image:$bg_gradient 1;border-radius:4px;'>
  <div style='background:$bg_gradient;border-radius:4px;padding:4px;'>
  <div style='background:white;border-radius:2px;'>

    <!-- Header band -->
    <div style='background:$bg_gradient;padding:28px 30px;text-align:center;'>
      <div style='font-size:13px;letter-spacing:4px;text-transform:uppercase;color:rgba(255,255,255,0.85);margin-bottom:8px;'>$institute_name</div>
      <div style='font-size:28px;font-weight:900;color:#fff;letter-spacing:2px;text-transform:uppercase;'>
        " . ($is_winner ? "Award Certificate" : "Certificate of Participation") . "
      </div>
      $winner_bar
    </div>

    <!-- Body -->
    <div style='padding:40px 50px;text-align:center;'>
      <p style='color:#718096;font-size:14px;letter-spacing:2px;text-transform:uppercase;margin:0 0 12px 0;'>This certificate is proudly presented to</p>

      <div style='font-size:38px;font-weight:700;color:#2d3748;border-bottom:3px solid;border-image:$bg_gradient 1;display:inline-block;padding-bottom:6px;margin-bottom:20px;font-family:\"Palatino Linotype\",Georgia,serif;'>
        $recipient_name
      </div>

      <p style='color:#4a5568;font-size:16px;line-height:1.7;margin:0 0 10px 0;'>
        " . ($is_winner
            ? "for outstanding performance and achieving <strong>$cert_label</strong>"
            : "for their active participation and contribution") . "
      </p>
      <p style='color:#4a5568;font-size:16px;margin:0;'>at the event</p>

      <div style='margin:20px auto;padding:16px 30px;background:#f7fafc;border-left:4px solid #667eea;border-radius:0 8px 8px 0;text-align:left;max-width:440px;'>
        <div style='font-size:20px;font-weight:700;color:#2d3748;margin-bottom:6px;'>🎓 $event_title</div>
        <div style='font-size:14px;color:#718096;'>📅 $event_date</div>
        " . ($event_venue ? "<div style='font-size:14px;color:#718096;'>📍 $event_venue</div>" : '') . "
      </div>

      <!-- Signatures row -->
      <div style='display:flex;justify-content:space-around;align-items:flex-end;margin-top:40px;padding-top:20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:20px;'>
        <div style='text-align:center;min-width:180px;'>
          $sig_html
          <div style='font-size:14px;font-weight:700;color:#2d3748;'>$organizer_name</div>
          <div style='font-size:12px;color:#718096;'>$org_title</div>
        </div>
        <div style='text-align:center;min-width:180px;'>
          <div style='font-size:11px;color:#a0aec0;margin-bottom:4px;'>Certificate ID</div>
          <div style='font-family:monospace;font-size:12px;color:#667eea;background:#f7fafc;padding:4px 10px;border-radius:4px;'>$cert_id</div>
          <div style='font-size:11px;color:#a0aec0;margin-top:6px;'>Issued: " . date('M d, Y') . "</div>
        </div>
      </div>

    </div><!-- /body -->

    <!-- Footer -->
    <div style='background:#f7fafc;padding:12px 30px;text-align:center;border-top:1px solid #e2e8f0;'>
      <span style='font-size:11px;color:#a0aec0;letter-spacing:1px;'>Campus Event Management System · This certificate is digitally generated</span>
    </div>

  </div><!-- /inner white -->
  </div><!-- /gradient wrapper -->
  </div><!-- /outer border -->

</div>
</body>
</html>";
}

// ─── Helper: setup mailer (mirrors your existing pattern) ───────────────────
function setupMailer($mail) {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
}

// ─── Previously issued certificates for this event ──────────────────────────
$issued_certs = [];
if ($event_id) {
    $ic_q = "SELECT user_id, cert_type, cert_label FROM certificates WHERE event_id = $event_id";
    $ic_r = mysqli_query($conn, $ic_q);
    if ($ic_r) {
        while ($row = mysqli_fetch_assoc($ic_r)) {
            $issued_certs[$row['user_id']] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Certificates – Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Layout helpers ── */
        .cert-section { background: white; padding: 24px; border-radius: 10px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .cert-section h3 { margin: 0 0 18px 0; color: #2d3748; font-size: 16px; }

        /* ── Attendee card ── */
        .attendee-card {
            display: flex; align-items: center; gap: 16px;
            padding: 14px 18px; border: 2px solid #e2e8f0;
            border-radius: 10px; margin-bottom: 12px;
            transition: border-color 0.2s;
            background: #fafafa;
        }
        .attendee-card.selected { border-color: #667eea; background: #ebedff; }
        .attendee-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 18px;
            flex-shrink: 0;
        }
        .attendee-info { flex: 1; min-width: 0; }
        .attendee-info strong { display: block; color: #2d3748; font-size: 15px; }
        .attendee-info small  { color: #718096; font-size: 12px; }
        .cert-type-select {
            padding: 7px 12px; border: 2px solid #e2e8f0;
            border-radius: 6px; font-size: 13px; min-width: 180px;
            background: white; cursor: pointer;
        }
        .cert-type-select:focus { border-color: #667eea; outline: none; }
        .custom-label-input {
            padding: 7px 12px; border: 2px solid #e2e8f0;
            border-radius: 6px; font-size: 13px; width: 200px;
            display: none;
        }

        /* ── Badge pills ── */
        .badge-1st  { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .badge-2nd  { background: #f3f4f6; color: #374151; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .badge-3rd  { background: #fde8d8; color: #7c3d12; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .badge-part { background: #e0e7ff; color: #3730a3; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .badge-cust { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }

        /* ── Certificate preview card ── */
        .preview-card {
            border: 4px solid #667eea; border-radius: 12px;
            overflow: hidden; max-width: 620px;
            box-shadow: 0 4px 20px rgba(102,126,234,0.2);
        }
        .preview-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 20px 24px; text-align: center; color: white;
        }
        .preview-header h4 { margin: 0; font-size: 20px; letter-spacing: 1px; }
        .preview-body { padding: 28px 32px; text-align: center; }
        .preview-name { font-size: 28px; font-weight: 700; color: #2d3748; font-family: Georgia, serif; }
        .preview-detail { color: #718096; font-size: 14px; margin: 10px 0; }
        .preview-sig-area { display: flex; justify-content: space-around; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; }

        /* ── Sig upload area ── */
        .sig-drop { border: 2px dashed #cbd5e0; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: border-color 0.2s; }
        .sig-drop:hover { border-color: #667eea; }
        .sig-preview { max-height: 70px; margin-top: 10px; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }

        /* ── Steps indicator ── */
        .steps { display: flex; gap: 0; margin-bottom: 28px; }
        .step { flex: 1; padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #a0aec0; border-bottom: 3px solid #e2e8f0; }
        .step.active { color: #667eea; border-color: #667eea; }
        .step.done   { color: #48bb78; border-color: #48bb78; }

        /* ── Bulk action bar ── */
        .bulk-bar { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 18px; display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
        .bulk-bar label { font-size: 13px; font-weight: 600; color: #4a5568; }

        @media (max-width: 700px) {
            .attendee-card { flex-wrap: wrap; }
            .cert-type-select, .custom-label-input { width: 100%; min-width: 0; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>🎓 Generate Certificates</h1>
            <?php if ($event): ?>
                <a href="manage_events.php" class="btn btn-secondary">← Back to Events</a>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- ══ STEP INDICATOR ══════════════════════════════════════════════ -->
        <div class="steps">
            <div class="step <?php echo !$event ? 'active' : 'done'; ?>">① Select Event</div>
            <div class="step <?php echo ($event && !$sig_file) ? 'active' : ($sig_file ? 'done' : ''); ?>">② Upload Signature</div>
            <div class="step <?php echo ($event && $sig_file) ? 'active' : ''; ?>">③ Assign Certificates</div>
            <div class="step">④ Send / Download</div>
        </div>

        <!-- ══ STEP 1 – CHOOSE EVENT ════════════════════════════════════════ -->
        <div class="cert-section">
            <h3>📅 Step 1 — Select Event</h3>
            <form method="GET" action="">
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="flex:1;margin-bottom:0;min-width:240px;">
                        <label>Event</label>
                        <select name="event_id" required onchange="this.form.submit()" style="padding:11px;font-size:15px;">
                            <option value="">— Choose an event —</option>
                            <?php
                            mysqli_data_seek($events_result, 0);
                            while ($ev = mysqli_fetch_assoc($events_result)):
                            ?>
                                <option value="<?php echo $ev['id']; ?>"
                                    <?php echo ($event_id == $ev['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ev['title']); ?>
                                    (<?php echo date('M d, Y', strtotime($ev['event_date'])); ?>) —
                                    <?php echo $ev['verified_count']; ?> attended
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </form>

            <?php if (mysqli_num_rows($events_result) === 0): ?>
                <div style="margin-top:16px;color:#718096;font-size:14px;">
                    ⚠️ No completed or ongoing events found. Certificate generation is available for events with status <em>completed</em> or <em>ongoing</em>.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($event): ?>

        <!-- ══ STEP 2 – SIGNATURE ═══════════════════════════════════════════ -->
        <div class="cert-section">
            <h3>✍️ Step 2 — Organizer Signature</h3>
            <div style="display:flex;gap:30px;align-items:flex-start;flex-wrap:wrap;">
                <form method="POST" action="?event_id=<?php echo $event_id; ?>" enctype="multipart/form-data" style="flex:1;min-width:260px;">
                    <input type="hidden" name="action" value="upload_signature">
                    <div class="sig-drop" onclick="document.getElementById('sig_file_input').click();">
                        <div style="font-size:36px;margin-bottom:8px;">🖊️</div>
                        <div style="color:#4a5568;font-weight:600;margin-bottom:4px;">Click to upload signature</div>
                        <div style="color:#a0aec0;font-size:13px;">PNG or JPG, transparent PNG recommended</div>
                        <input type="file" id="sig_file_input" name="signature_image" accept="image/png,image/jpeg,image/jpg"
                               style="display:none;" onchange="previewSig(this)">
                    </div>
                    <?php if ($sig_file && file_exists('../uploads/signatures/' . $sig_file)): ?>
                        <div style="margin-top:10px;">
                            <div style="font-size:12px;color:#48bb78;margin-bottom:6px;">✅ Current signature:</div>
                            <img src="../uploads/signatures/<?php echo htmlspecialchars($sig_file); ?>"
                                 class="sig-preview" id="sig_preview_img" alt="Signature">
                        </div>
                    <?php else: ?>
                        <img id="sig_preview_img" class="sig-preview" style="display:none;" alt="Preview">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary" style="margin-top:14px;">💾 Save Signature</button>
                </form>

                <div style="flex:1;min-width:240px;background:#f7fafc;border-radius:8px;padding:18px;border:1px solid #e2e8f0;">
                    <div style="font-size:13px;font-weight:600;color:#4a5568;margin-bottom:10px;">💡 Signature Tips</div>
                    <ul style="color:#718096;font-size:13px;line-height:1.8;margin:0;padding-left:18px;">
                        <li>Use a <strong>transparent PNG</strong> for a clean look on certificates.</li>
                        <li>Sign on white paper, photograph or scan, then remove background using any free tool.</li>
                        <li>Recommended size: at least 400×150 px.</li>
                        <li>Your signature will appear on <em>every</em> certificate for this session.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ══ CERTIFICATE PREVIEW ═════════════════════════════════════════ -->
        <div class="cert-section">
            <h3>👁️ Certificate Preview</h3>
            <p style="color:#718096;font-size:13px;margin-bottom:20px;">
                This is how certificates will look when sent. The name, label, and colours will change per recipient.
            </p>
            <div class="preview-card">
                <div class="preview-header" id="prev-header">
                    <div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;opacity:.8;margin-bottom:4px;" id="prev-institute">Campus Event Management</div>
                    <h4>Certificate of Participation</h4>
                </div>
                <div class="preview-body">
                    <div style="color:#718096;font-size:12px;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;">This is proudly presented to</div>
                    <div class="preview-name">Student Name</div>
                    <div class="preview-detail" style="margin-top:16px;">for their participation at</div>
                    <div style="font-size:17px;font-weight:700;color:#2d3748;margin-top:6px;">
                        🎓 <?php echo htmlspecialchars($event['title']); ?>
                    </div>
                    <div style="color:#718096;font-size:13px;margin-top:4px;">
                        📅 <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                        <?php if ($event['venue']): ?> · 📍 <?php echo htmlspecialchars($event['venue']); ?><?php endif; ?>
                    </div>
                    <div class="preview-sig-area">
                        <div style="text-align:center;">
                            <?php if ($sig_file && file_exists('../uploads/signatures/' . $sig_file)): ?>
                                <img src="../uploads/signatures/<?php echo htmlspecialchars($sig_file); ?>"
                                     style="max-height:50px;max-width:160px;object-fit:contain;margin-bottom:4px;">
                            <?php else: ?>
                                <div style="width:160px;border-bottom:2px solid #2d3748;margin-bottom:4px;height:36px;"></div>
                            <?php endif; ?>
                            <div style="font-size:13px;font-weight:700;color:#2d3748;" id="prev-orgname">Organizer Name</div>
                            <div style="font-size:11px;color:#718096;" id="prev-orgtitle">Event Organizer</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:10px;color:#a0aec0;margin-bottom:4px;">Certificate ID</div>
                            <div style="font-family:monospace;font-size:11px;color:#667eea;background:#f7fafc;padding:3px 8px;border-radius:4px;">CERT-<?php echo date('Ymd'); ?>-XXX</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ STEP 3 – ASSIGN & SEND ══════════════════════════════════════ -->
        <?php if (count($attendees) > 0): ?>
        <div class="cert-section">
            <h3>🏆 Step 3 — Assign Certificate Types & Send</h3>
            <form method="POST" action="" id="cert-form">
                <input type="hidden" name="action" value="send_certificates">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">

                <!-- Organizer info -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:22px;padding:18px;background:#f7fafc;border-radius:8px;border:1px solid #e2e8f0;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Your Name (appears on certificate) *</label>
                        <input type="text" name="organizer_name" required
                               value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>"
                               placeholder="e.g. Dr. Priya Sharma"
                               oninput="document.getElementById('prev-orgname').textContent=this.value||'Organizer Name'">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Your Title / Role</label>
                        <input type="text" name="organizer_title" placeholder="e.g. Event Coordinator"
                               oninput="document.getElementById('prev-orgtitle').textContent=this.value||'Event Organizer'">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Institute / Organisation Name</label>
                        <input type="text" name="institute_name"
                               value="Campus Event Management" placeholder="e.g. XYZ University"
                               oninput="document.getElementById('prev-institute').textContent=this.value||'Campus Event Management'">
                    </div>
                </div>

                <!-- Bulk actions -->
                <div class="bulk-bar">
                    <label>Bulk assign:</label>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="bulkAssign('participation')">🎓 All as Participation</button>
                    <button type="button" class="btn btn-sm" style="background:#f6ad55;color:#744210;" onclick="bulkAssign('none')">✕ Clear All</button>
                    <span style="color:#a0aec0;font-size:13px;">Or assign individually below ↓</span>
                </div>

                <!-- Attendee list -->
                <?php foreach ($attendees as $att):
                    $uid = $att['id'];
                    $existing_type  = $issued_certs[$uid]['cert_type']  ?? '';
                    $existing_label = $issued_certs[$uid]['cert_label'] ?? '';
                ?>
                <div class="attendee-card" id="card-<?php echo $uid; ?>">
                    <div class="attendee-avatar"><?php echo strtoupper(substr($att['full_name'], 0, 1)); ?></div>

                    <div class="attendee-info">
                        <strong><?php echo htmlspecialchars($att['full_name']); ?></strong>
                        <small>
                            <?php echo htmlspecialchars($att['email']); ?>
                            <?php if ($att['department']): ?> · <?php echo htmlspecialchars($att['department']); ?><?php endif; ?>
                        </small>
                        <?php if ($existing_type): ?>
                            <div style="margin-top:4px;">
                                <span class="badge-<?php echo $existing_type === 'participation' ? 'part' : ($existing_type === 'custom' ? 'cust' : $existing_type); ?>">
                                    Previously issued: <?php echo htmlspecialchars($existing_label); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <select name="cert_types[<?php echo $uid; ?>]"
                                class="cert-type-select"
                                id="type-<?php echo $uid; ?>"
                                onchange="onTypeChange(<?php echo $uid; ?>, this.value)">
                            <option value="">— No certificate —</option>
                            <option value="participation" <?php echo $existing_type==='participation'?'selected':''; ?>>🎓 Participation</option>
                            <option value="1st" <?php echo $existing_type==='1st'?'selected':''; ?>>🥇 1st Place</option>
                            <option value="2nd" <?php echo $existing_type==='2nd'?'selected':''; ?>>🥈 2nd Place</option>
                            <option value="3rd" <?php echo $existing_type==='3rd'?'selected':''; ?>>🥉 3rd Place</option>
                            <option value="custom" <?php echo $existing_type==='custom'?'selected':''; ?>>✏️ Custom Label</option>
                        </select>

                        <input type="text"
                               name="custom_labels[<?php echo $uid; ?>]"
                               id="custom-<?php echo $uid; ?>"
                               class="custom-label-input"
                               placeholder="e.g. Best Innovation"
                               value="<?php echo ($existing_type === 'custom') ? htmlspecialchars($existing_label) : ''; ?>"
                               style="<?php echo ($existing_type === 'custom') ? 'display:inline-block;' : ''; ?>">
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Action buttons -->
                <div style="display:flex;gap:14px;margin-top:28px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="btn btn-primary" style="font-size:15px;padding:13px 26px;"
                            onclick="return confirmSend()">
                        📧 Send Certificates via Email
                    </button>
                    <button type="button" class="btn btn-success" style="font-size:15px;padding:13px 26px;"
                            onclick="downloadAll()">
                        📥 Download All as ZIP
                    </button>
                    <a href="?event_id=<?php echo $event_id; ?>" class="btn btn-secondary">↺ Reset</a>
                </div>

                <div style="margin-top:12px;color:#718096;font-size:12px;">
                    💡 <strong>Download All as ZIP</strong> generates each selected certificate as an HTML file and bundles them — ideal if you want to print them for a physical ceremony before emailing digital copies.
                </div>
            </form>
        </div>

        <?php else: /* no attendees */ ?>
        <div class="cert-section" style="text-align:center;padding:50px 20px;">
            <div style="font-size:70px;margin-bottom:16px;">😕</div>
            <h2 style="color:#718096;">No Verified Attendees</h2>
            <p style="color:#a0aec0;margin-bottom:20px;">
                Certificates can only be issued to students whose tickets have been scanned/verified at the event.
            </p>
            <a href="verify_ticket.php?event_id=<?php echo $event_id; ?>" class="btn btn-primary">🎫 Go to Ticket Verification</a>
        </div>
        <?php endif; ?>

        <?php endif; /* $event */ ?>
    </main>
</div>

<!-- ══ DOWNLOAD ZIP (client-side HTML generation + JSZip) ══════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script>
/* ── Signature preview ── */
function previewSig(input) {
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('sig_preview_img');
        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

/* ── Show/hide custom label input ── */
function onTypeChange(uid, val) {
    const customInput = document.getElementById('custom-' + uid);
    const card        = document.getElementById('card-' + uid);
    customInput.style.display = (val === 'custom') ? 'inline-block' : 'none';
    card.classList.toggle('selected', val !== '');
}

/* ── Bulk assign ── */
function bulkAssign(type) {
    document.querySelectorAll('.cert-type-select').forEach(sel => {
        sel.value = type;
        const uid = sel.name.match(/\[(\d+)\]/)[1];
        onTypeChange(uid, type);
    });
}

/* ── Confirm before sending ── */
function confirmSend() {
    const selected = [...document.querySelectorAll('.cert-type-select')].filter(s => s.value !== '');
    if (selected.length === 0) { alert('Please assign at least one certificate before sending!'); return false; }
    return confirm(`You are about to email ${selected.length} certificate(s). Proceed?`);
}

/* ── Download All as ZIP ── */
async function downloadAll() {
    const orgName    = document.querySelector('[name="organizer_name"]').value  || 'Organizer';
    const orgTitle   = document.querySelector('[name="organizer_title"]').value || 'Event Organizer';
    const institute  = document.querySelector('[name="institute_name"]').value  || 'Campus Event Management';
    const eventTitle = <?php echo json_encode($event['title'] ?? ''); ?>;
    const eventDate  = <?php echo json_encode(date('F d, Y', strtotime($event['event_date'] ?? 'now'))); ?>;
    const eventVenue = <?php echo json_encode($event['venue'] ?? ''); ?>;

    const attendees = <?php echo json_encode(array_map(fn($a) => ['id' => $a['id'], 'full_name' => $a['full_name']], $attendees)); ?>;

    const zip      = new JSZip();
    const certDir  = zip.folder("certificates");
    let count      = 0;

    attendees.forEach(att => {
        const sel   = document.getElementById('type-' + att.id);
        if (!sel || sel.value === '') return;
        const type  = sel.value;
        const customEl = document.getElementById('custom-' + att.id);
        const customLabel = (type === 'custom' && customEl) ? customEl.value : '';

        const labelMap = { '1st':'1st Place 🥇','2nd':'2nd Place 🥈','3rd':'3rd Place 🥉','participation':'Certificate of Participation','custom': customLabel || 'Certificate of Achievement' };
        const label    = labelMap[type] || 'Certificate of Participation';
        const isWinner = ['1st','2nd','3rd','custom'].includes(type) && type !== 'participation';
        const gradMap  = { '1st':'linear-gradient(135deg,#f6d365,#fda085)','2nd':'linear-gradient(135deg,#c0c0c0,#a8a8a8)','3rd':'linear-gradient(135deg,#cd7f32,#b8621a)','participation':'linear-gradient(135deg,#667eea,#764ba2)','custom':'linear-gradient(135deg,#43e97b,#38f9d7)' };
        const grad     = gradMap[type] || gradMap['participation'];
        const medalMap = { '1st':'🥇','2nd':'🥈','3rd':'🥉','custom':'🏆' };
        const medal    = medalMap[type] || '';

        const certId   = 'CERT-<?php echo date("Ymd"); ?>-' + att.id;
        const sigHtml  = <?php echo json_encode($sig_file && file_exists('../uploads/signatures/'.$sig_file) ? '<img src="../uploads/signatures/'.htmlspecialchars($sig_file).'" style="max-height:60px;max-width:180px;object-fit:contain;margin-bottom:4px;" alt="Signature">' : '<div style="width:180px;border-bottom:2px solid #2d3748;margin-bottom:4px;height:40px;"></div>'); ?>;

        const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Certificate – ${att.full_name}</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Georgia,serif;">
<div style="max-width:750px;margin:30px auto;padding:20px;">
<div style="border:6px solid transparent;border-image:${grad} 1;border-radius:4px;">
<div style="background:${grad};border-radius:4px;padding:4px;">
<div style="background:white;border-radius:2px;">
<div style="background:${grad};padding:28px 30px;text-align:center;">
  <div style="font-size:13px;letter-spacing:4px;text-transform:uppercase;color:rgba(255,255,255,0.85);margin-bottom:8px;">${institute}</div>
  <div style="font-size:28px;font-weight:900;color:#fff;letter-spacing:2px;text-transform:uppercase;">${isWinner?'Award Certificate':'Certificate of Participation'}</div>
  ${isWinner?`<div style="background:rgba(255,255,255,0.25);padding:10px 30px;border-radius:30px;display:inline-block;margin-top:12px;font-size:18px;font-weight:700;color:#fff;">${medal} ${label}</div>`:''}
</div>
<div style="padding:40px 50px;text-align:center;">
  <p style="color:#718096;font-size:14px;letter-spacing:2px;text-transform:uppercase;margin:0 0 12px;">This certificate is proudly presented to</p>
  <div style="font-size:38px;font-weight:700;color:#2d3748;font-family:'Palatino Linotype',Georgia,serif;">${att.full_name}</div>
  <p style="color:#4a5568;font-size:16px;line-height:1.7;margin:16px 0 8px;">${isWinner?`for outstanding performance and achieving <strong>${label}</strong>`:'for their active participation and contribution'}</p>
  <p style="color:#4a5568;font-size:16px;margin:0;">at the event</p>
  <div style="margin:20px auto;padding:16px 30px;background:#f7fafc;border-left:4px solid #667eea;border-radius:0 8px 8px 0;text-align:left;max-width:440px;">
    <div style="font-size:20px;font-weight:700;color:#2d3748;margin-bottom:6px;">🎓 ${eventTitle}</div>
    <div style="font-size:14px;color:#718096;">📅 ${eventDate}</div>
    ${eventVenue?`<div style="font-size:14px;color:#718096;">📍 ${eventVenue}</div>`:''}
  </div>
  <div style="display:flex;justify-content:space-around;margin-top:40px;padding-top:20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:20px;">
    <div style="text-align:center;min-width:180px;">${sigHtml}<div style="font-size:14px;font-weight:700;color:#2d3748;">${orgName}</div><div style="font-size:12px;color:#718096;">${orgTitle}</div></div>
    <div style="text-align:center;min-width:180px;"><div style="font-size:11px;color:#a0aec0;margin-bottom:4px;">Certificate ID</div><div style="font-family:monospace;font-size:12px;color:#667eea;background:#f7fafc;padding:4px 10px;border-radius:4px;">${certId}</div></div>
  </div>
</div>
<div style="background:#f7fafc;padding:12px 30px;text-align:center;border-top:1px solid #e2e8f0;"><span style="font-size:11px;color:#a0aec0;letter-spacing:1px;">Campus Event Management System · Digitally Generated</span></div>
</div></div></div></div></body></html>`;

        certDir.file(att.full_name.replace(/[^a-z0-9]/gi,'_') + '_certificate.html', html);
        count++;
    });

    if (count === 0) { alert('Please assign certificate types to at least one attendee!'); return; }

    const blob = await zip.generateAsync({ type: 'blob' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'certificates_<?php echo preg_replace('/[^a-z0-9]/i','_',$event['title']??'event'); ?>.zip';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>