# Campus Event Manager - Module Dependencies & Database Schema

## 📊 Module Interaction Matrix

### **Which Modules Talk to Each Other?**

```
                    STUDENT    ORGANIZER    ADMIN
STUDENT               -           ✓          ✓
ORGANIZER             ✓           ✓          ✓
ADMIN                 ✓           ✓          -
```

### **Detailed Dependencies**

#### **STUDENT Module Dependencies**
```
Depends On:
├── config/database.php          (Database connection)
├── includes/header.php          (UI component)
├── includes/sidebar.php         (Navigation)
├── includes/footer.php          (UI component)
├── assets/css/style.css         (Styling)
└── assets/js/script.js          (AJAX for like/unlike)

Reads From Database:
├── users                (Authenticate current user)
├── events               (Browse events)
├── registrations        (Check if registered, view my events)
├── merchandise          (Browse merchandise)
├── event_likes          (Check if liked)
└── event_comments       (View comments)

Permissions Required:
└── READ: events, registrations, merchandise
└── WRITE: registrations, event_likes, event_comments
```

---

#### **ORGANIZER Module Dependencies**
```
Depends On:
├── config/database.php              (Database connection)
├── config/email_config.php          (Email service)
├── config/co_organizer_helper.php   (Co-organizer logic)
├── includes/header.php              (UI component)
├── includes/sidebar.php             (Navigation)
├── includes/footer.php              (UI component)
├── assets/css/style.css             (Styling)
├── assets/js/script.js              (AJAX interactions)
├── vendor/phpmailer/                (Email sending)
└── uploads/                         (Store event/merch images)

Reads From Database:
├── users                    (Current organizer, co-organizer info)
├── events                   (Organizer's events)
├── registrations            (Attendee list)
├── event_organizers         (Co-organizer assignments)
├── merchandise              (Organizer's merchandise)
├── tickets                  (Issued tickets, verification)
├── notifications            (Sent notifications history)
└── event_likes/comments     (Engagement data)

Permissions Required:
├── READ: All relevant data
├── WRITE: events, registrations, merchandise, tickets, notifications
└── UPDATE: events, merchandise, event_organizers
```

---

#### **ADMIN Module Dependencies**
```
Depends On:
├── config/database.php              (Database connection)
├── config/email_config.php          (Email service)
├── includes/header.php              (UI component)
├── includes/sidebar.php             (Navigation)
├── includes/footer.php              (UI component)
├── assets/css/style.css             (Styling)
├── assets/js/script.js              (AJAX interactions)
├── vendor/phpmailer/                (Email sending)
└── uploads/                         (Manage user/event images)

Reads From Database:
└── ALL TABLES (Complete system access)

Permissions Required:
├── READ: All tables
├── WRITE: All tables
├── UPDATE: All tables
└── DELETE: Users, Events, Merchandise (as needed)
```

---

## 🗄️ Database Schema Details

### **Table: users** ⭐ (Core - Used by All Modules)
```sql
CREATE TABLE users (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'organizer', 'student') NOT NULL,
    full_name VARCHAR(100),
    department VARCHAR(100),
    year VARCHAR(20),
    bio TEXT,
    address TEXT,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT(11) FOREIGN KEY → users.id
);

Indexed On: id (PK), username (UNIQUE), email (UNIQUE)
Used By: 
  - STUDENT: View profile, display in pages
  - ORGANIZER: Display current user, co-organizer info
  - ADMIN: Full user management
```

---

### **Table: events** (Inferred Structure)
```sql
CREATE TABLE events (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    organizer_id INT(11) FOREIGN KEY → users.id,
    event_date DATE,
    event_time TIME,
    location VARCHAR(255),
    capacity INT(11),
    image VARCHAR(255),
    status ENUM('draft', 'published', 'ongoing', 'completed'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

Used By:
  - STUDENT: browse_events.php, event_detail.php, register_event.php
  - ORGANIZER: create_event.php, manage_events.php, edit_event.php, view_event.php
  - ADMIN: browse_events.php, event_details.php, reports.php
```

---

### **Table: registrations** (Event Attendance)
```sql
CREATE TABLE registrations (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    event_id INT(11) FOREIGN KEY → events.id,
    student_id INT(11) FOREIGN KEY → users.id,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled'),
    UNIQUE KEY (event_id, student_id)
);

Used By:
  - STUDENT: View my events, check registration status
  - ORGANIZER: View registrations, verify attendance
  - ADMIN: Reports, export registration data
```

---

### **Table: merchandise** (Product Inventory)
```sql
CREATE TABLE merchandise (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    organizer_id INT(11) FOREIGN KEY → users.id,
    price DECIMAL(10, 2),
    quantity INT(11),
    image VARCHAR(255),
    status ENUM('available', 'sold_out', 'discontinued'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

Used By:
  - STUDENT: browse_merchandise.php, view_merchandise.php
  - ORGANIZER: create_merchandise.php, manage_merchandise.php
  - ADMIN: browse_merchandise.php, reports.php
```

---

### **Table: event_organizers** (Co-Organizer Management)
```sql
CREATE TABLE event_organizers (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    event_id INT(11) FOREIGN KEY → events.id,
    organizer_id INT(11) FOREIGN KEY → users.id,
    role VARCHAR(100),
    status ENUM('invited', 'accepted', 'declined'),
    created_at TIMESTAMP,
    UNIQUE KEY (event_id, organizer_id)
);

Used By:
  - ORGANIZER: manage_co_organizers.php, co_organizer_invitations.php
  - ADMIN: Full oversight
```

---

### **Table: tickets** (Ticketing System)
```sql
CREATE TABLE tickets (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    ticket_code VARCHAR(100) UNIQUE,
    event_id INT(11) FOREIGN KEY → events.id,
    student_id INT(11) FOREIGN KEY → users.id,
    status ENUM('issued', 'used', 'cancelled'),
    issued_date TIMESTAMP,
    used_date TIMESTAMP,
    qr_code_data TEXT
);

Used By:
  - ORGANIZER: send_tickets.php, verify_ticket.php, verification_report.php
  - ADMIN: Reports, auditing
```

---

### **Table: notifications** (Event Notifications)
```sql
CREATE TABLE notifications (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    event_id INT(11) FOREIGN KEY → events.id,
    recipient_id INT(11) FOREIGN KEY → users.id,
    message TEXT NOT NULL,
    sent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_status TINYINT(1) DEFAULT 0
);

Used By:
  - ORGANIZER: send_notification.php, send_merch_notification.php
  - STUDENT: Could view notification history
  - ADMIN: Reports, auditing
```

---

### **Table: event_likes** (Student Engagement)
```sql
CREATE TABLE event_likes (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    event_id INT(11) FOREIGN KEY → events.id,
    student_id INT(11) FOREIGN KEY → users.id,
    liked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (event_id, student_id)
);

Used By:
  - STUDENT: ajax_toggle_like.php, browse_events.php
```

---

### **Table: event_comments** (Student Discussion)
```sql
CREATE TABLE event_comments (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    event_id INT(11) FOREIGN KEY → events.id,
    student_id INT(11) FOREIGN KEY → users.id,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

Used By:
  - STUDENT: event_detail.php, common/event_comments.php
```

---

## 🔗 Cross-Module Data Flow Scenarios

### **Scenario 1: Student Registers for Event**
```
Flow:
1. Student browses events (student/browse_events.php)
   → Reads from: events table
   
2. Selects event and clicks "Register"
   → Goes to: student/register_event.php
   
3. Registration form submitted
   → Writes to: registrations table (event_id, student_id)
   
4. Organizer views registrations (organizer/view_registrations.php)
   → Reads from: registrations table JOIN users table
   
5. Admin exports data (admin/download_registrations.php)
   → Reads from: registrations table JOIN users table

Database Tables Involved:
  events ← Read
  registrations ← Write
  users ← Read
```

---

### **Scenario 2: Organizer Issues Tickets**
```
Flow:
1. Organizer selects event (organizer/manage_events.php)
   → Reads from: events table
   
2. Views registrations (organizer/view_registrations.php)
   → Reads from: registrations table
   
3. Issues tickets (organizer/send_tickets.php)
   → Reads from: registrations, users tables
   → Writes to: tickets table
   → PHP-Email: Uses phpmailer to send ticket emails
   
4. Creates notification record (organizer/send_notification.php)
   → Writes to: notifications table

Database Tables Involved:
  events ← Read
  registrations ← Read
  users ← Read
  tickets ← Write
  notifications ← Write
```

---

### **Scenario 3: Admin Creates New Event Organizer**
```
Flow:
1. Admin creates user account (admin/create_user.php)
   → Writes to: users table (role='organizer')
   
2. Organizer logs in → organizer/dashboard.php
   → Reads from: users table (current user)
   
3. Organizer creates event (organizer/create_event.php)
   → Writes to: events table (organizer_id=user_id)
   → File upload → uploads/merchandise/ or event images
   
4. Admin monitors (admin/browse_events.php)
   → Reads from: events table

Database Tables Involved:
  users ← Write (create_user) + Read (session)
  events ← Write (create_event)
  File system ← uploads/ directory
```

---

## ⚙️ Configuration Dependencies

### **config/database.php**
```php
Required in EVERY file that accesses database:
require_once '../config/database.php';

Provides: $conn (MySQL connection object)
       mysqli_connect() initialization
```

### **config/email_config.php**
```php
Required in files that send emails:
require_once '../config/email_config.php';

Provides SMTP configuration:
  - SMTP host
  - SMTP port
  - Sender email
  - Sender name
  - SMTP credentials
  
Used by files:
  - organizer/send_notification.php
  - organizer/send_tickets.php
  - organizer/send_merch_notification.php
  - admin/send_email.php
```

### **config/co_organizer_helper.php**
```php
Helper functions for co-organizer operations:

Functions:
  - invite_co_organizer()
  - accept_invitation()
  - remove_co_organizer()
  - get_co_organizers()
  
Used in:
  - organizer/manage_co_organizers.php
  - organizer/co_organizer_invitations.php
```

---

## 📋 Module Feature Dependencies Table

| Feature | Module | Dependencies | DB Tables |
|---------|--------|-------------|-----------|
| Sign-up/Login | All | config/database.php | users |
| Browse Events | Student | database, includes | events, users |
| Register Event | Student | database, includes | registrations, events |
| Create Event | Organizer | database, config/email | events, users |
| Manage Co-Org | Organizer | database, helper fn | event_organizers, users |
| Issue Tickets | Organizer | database, PHPMailer | tickets, registrations |
| Verify Tickets | Organizer | database, includes | tickets, events |
| Manage Users | Admin | database, includes | users |
| Export Data | Admin | database | All tables |
| Send Email | Admin | database, PHPMailer | notifications |

---

## 🔐 Role-Based Database Access Control

### **Student Permissions**
```
READ:
  ✓ users (own profile, others' public info)
  ✓ events (all published events)
  ✓ registrations (own registrations)
  ✓ merchandise (all available)
  ✓ event_likes (to check if liked)
  ✓ event_comments (to read)

WRITE:
  ✓ registrations (own registrations)
  ✓ event_likes (own likes)
  ✓ event_comments (own comments)
  ✓ users (own profile only)
```

### **Organizer Permissions**
```
READ:
  ✓ users (all)
  ✓ events (own + co-organized)
  ✓ registrations (for own events)
  ✓ merchandise (own + co-managed)
  ✓ event_organizers (for own events)
  ✓ tickets (for own events)
  ✓ notifications (sent by self)

WRITE:
  ✓ events (own)
  ✓ registrations (mark attendance)
  ✓ merchandise (own)
  ✓ event_organizers (manage)
  ✓ tickets (create/update for own events)
  ✓ notifications (send for own events)
```

### **Admin Permissions**
```
READ:   ✓ ALL TABLES
WRITE:  ✓ ALL TABLES
UPDATE: ✓ ALL TABLES
DELETE: ✓ ALL TABLES (with caution)
```

---

## 🚨 Important Security Notes

### **Session Verification**
Every page should check:
```php
<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Optionally check role
if($_SESSION['role'] !== 'expected_role') {
    header("Location: ../login.php");
    exit();
}
?>
```

### **SQL Injection Prevention**
Use `mysqli_real_escape_string()` for all user inputs:
```php
$username = mysqli_real_escape_string($conn, trim($_POST['username']));
```

### **Password Security**
Note: Project currently uses MD5 (insecure). Should upgrade to:
```php
password_hash($password, PASSWORD_BCRYPT)  // Hashing
password_verify($input, $hash)              // Verification
```

---

## 📝 Module Change Checklist

**Before Making Changes to Any Module, Verify:**

- [ ] Which database tables will be affected?
- [ ] Which other modules read/write to these tables?
- [ ] Will the change break existing functionality?
- [ ] Are permissions/roles properly enforced?
- [ ] Have you tested with different user roles?
- [ ] Will file uploads be affected (if applicable)?
- [ ] Do email notifications need updates?
- [ ] Should this be documented in PROJECT_OVERVIEW.md?

---

**Version**: 1.0  
**Last Updated**: April 12, 2026  
**For project overview**: See PROJECT_OVERVIEW.md  
**For folder structure**: See FOLDER_STRUCTURE.md
