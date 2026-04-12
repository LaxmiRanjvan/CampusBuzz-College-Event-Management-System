# Campus Event Manager - Folder Structure & Organization

## 📂 Complete Directory Tree

```
campus-event-manager/
│
├── 🔑 ROOT LEVEL AUTHENTICATION & ENTRY POINTS
├── index.php                          # Landing page / Sign-up page (public)
├── login.php                          # User login interface (public)
├── logout.php                         # Session termination (private)
│
├── 📚 DOCUMENTATION
├── README.md                          # Project introduction
├── PROJECT_OVERVIEW.md                # Comprehensive project guide (THIS FILE)
├── project structure.md               # Project structure notes
├── problams/                          # Issues/Problems tracking folder
│
├── ⚙️ CONFIGURATION & DEPENDENCIES
├── composer.json                      # PHP dependency manifest
│
├── 🗄️ DATABASE
├── users.sql                          # Database schema & initial data
│
│
├── 🔐 CONFIG/ - Configuration & System Setup
│   ├── database.php                   # MySQL connection (DB credentials)
│   ├── email_config.php               # Email service configuration (SMTP)
│   └── co_organizer_helper.php        # Co-organizer utility functions
│
│
├── 🎨 INCLUDES/ - Shared UI Components
│   ├── header.php                     # Page header / top navigation
│   ├── sidebar.php                    # Left sidebar navigation
│   └── footer.php                     # Page footer
│
│
├── 👨‍💼 ADMIN/ - Administrator Module [16 files]
│   │
│   ├── 📊 Dashboard & Main Interface
│   ├── dashboard.php                  # Admin home dashboard
│   │
│   ├── 👥 User Management
│   ├── manage_users.php               # User list & management interface
│   ├── create_user.php                # Create new user form & handler
│   ├── edit_user.php                  # Edit user details form & handler
│   ├── view_user.php                  # User profile detailed view
│   │
│   ├── 📅 Event Oversight
│   ├── browse_events.php              # View all system events
│   ├── event_details.php              # Event detailed information
│   ├── view_event.php                 # Event view page
│   │
│   ├── 🛍️ Merchandise Oversight
│   ├── browse_merchandise.php         # View all system merchandise
│   ├── view_merchandise.php           # Merchandise detailed view
│   │
│   ├── 📑 Reporting & Export
│   ├── reports.php                    # Generate system reports
│   ├── export_report.php              # Export reports to files
│   ├── download_events.php            # Export events data
│   ├── download_users.php             # Export users data
│   ├── download_registrations.php     # Export registration records
│   │
│   └── 📧 System Communication
│       └── send_email.php             # Send system-wide emails
│
│
├── 🎯 ORGANIZER/ - Event Organizer Module [19 files]
│   │
│   ├── 📊 Dashboard
│   ├── dashboard.php                  # Organizer home dashboard
│   │
│   ├── 📅 EVENT MANAGEMENT SUITE
│   ├── create_event.php               # Create new event form & handler
│   ├── manage_events.php              # Organizer's events management page
│   ├── edit_event.php                 # Edit event details form & handler
│   ├── view_event.php                 # View event information
│   ├── browse_events.php              # Browse created events list
│   │
│   ├── 🎟️ TICKET & VERIFICATION SYSTEM
│   ├── send_tickets.php               # Issue tickets to attendees
│   ├── verify_ticket.php              # Verify tickets at event entrance
│   ├── verification_report.php        # Generate ticket verification reports
│   │
│   ├── 📢 EVENT COMMUNICATIONS
│   ├── send_notification.php          # Send notifications to attendees
│   ├── view_registrations.php         # View registered attendees
│   │
│   ├── 🛍️ MERCHANDISE MANAGEMENT
│   ├── create_merchandise.php         # Create merchandise product
│   ├── manage_merchandise.php         # Merchandise management interface
│   ├── edit_merchandise.php           # Edit merchandise details
│   ├── send_merch_notification.php    # Send merchandise notifications
│   ├── browse_merchandise.php         # View created merchandise
│   ├── view_merchandise.php           # Merchandise detailed view
│   │
│   └── 👥 CO-ORGANIZER MANAGEMENT
│       ├── manage_co_organizers.php   # Manage co-organizers for events
│       └── co_organizer_invitations.php # Handle co-organizer invitations
│
│
├── 👨‍🎓 STUDENT/ - Student Module [9 files]
│   │
│   ├── 📊 Dashboard
│   ├── dashboard.php                  # Student home dashboard
│   │
│   ├── 📅 EVENT DISCOVERY & REGISTRATION
│   ├── browse_events.php              # Event discovery & browsing
│   ├── event_detail.php               # Detailed event information page
│   ├── register_event.php             # Event registration form & processor
│   ├── my_events.php                  # Student's registered events
│   ├── view_event.php                 # Event view page
│   │
│   ├── 💬 ENGAGEMENT
│   └── ajax_toggle_like.php           # Like/unlike events (AJAX endpoint)
│
│   ├── 🛍️ MARKETPLACE
│   ├── browse_merchandise.php         # Merchandise discovery & browsing
│   └── view_merchandise.php           # Merchandise detailed view
│
│
├── 🎨 ASSETS/ - Frontend Resources
│   │
│   ├── css/
│   │   └── style.css                  # Global stylesheet for entire application
│   │
│   ├── js/
│   │   └── script.js                  # Client-side JavaScript
│   │                                  # (AJAX calls, form validation, UI interactions)
│   │
│   └── images/                        # UI images, icons, logos
│       └── (static images)
│
│
├── 📤 UPLOADS/ - User-Generated Content Storage
│   │
│   ├── profiles/                      # User profile pictures
│   │   ├── profile_1_1763063749.jpg  # Format: profile_[USER_ID]_[TIMESTAMP].jpg
│   │   ├── profile_2_1769749502.jpg
│   │   └── ...
│   │
│   └── merchandise/                   # Product images
│       ├── product_1_1763063749.jpg  # Format: product_[PRODUCT_ID]_[TIMESTAMP].jpg
│       └── ...
│
│
└── 📦 VENDOR/ - Third-Party Dependencies (Composer)
    │
    ├── autoload.php                   # Composer autoloader
    ├── composer/                      # Composer metadata
    │   ├── autoload_*.php             # Autoloading functions
    │   ├── installed.php              # Installed packages list
    │   ├── installed.json             # Package metadata
    │   └── ...
    │
    └── phpmailer/                     # PHPMailer library (for SMTP email)
        └── phpmailer/
            ├── src/                   # Source code
            ├── language/              # Email templates & language files
            ├── composer.json
            ├── README.md
            ├── VERSION
            └── ...
```

---

## 📊 Module Statistics

| Module | Files | Purpose |
|--------|-------|---------|
| **Admin** | 16 | System oversight, user management, reporting |
| **Organizer** | 19 | Event creation, co-organizer management, ticketing |
| **Student** | 9 | Event discovery, registration, engagement |
| **Core** | 5 | Authentication & entry points (index, login, logout, config, includes) |

**Total Application Files**: ~60 PHP files (excluding vendor)

---

## 🔄 Data Flow Architecture

### **Request Flow by Module**

#### **STUDENT FLOW**
```
index.php → User Sign-Up
    ↓
login.php → Authentication
    ↓
student/dashboard.php → Main Hub
    ├─→ student/browse_events.php → Event Discovery
    │    └─→ student/event_detail.php → Event Info + Register
    │         └─→ student/register_event.php → POST Handler
    │
    ├─→ student/my_events.php → View Registrations
    │
    ├─→ student/ajax_toggle_like.php ← AJAX Like Events
    │
    └─→ student/browse_merchandise.php → Market Discovery
         └─→ student/view_merchandise.php → Product Details
```

#### **ORGANIZER FLOW**
```
login.php → Authentication
    ↓
organizer/dashboard.php → Main Hub
    ├─→ Event Management
    │   ├─→ organizer/create_event.php → New Events
    │   ├─→ organizer/manage_events.php → Event List
    │   ├─→ organizer/edit_event.php → Update Events
    │   └─→ organizer/view_registrations.php → Attendee List
    │
    ├─→ Ticketing System
    │   ├─→ organizer/send_tickets.php → Issue Tickets
    │   ├─→ organizer/verify_ticket.php → Check Tickets
    │   └─→ organizer/verification_report.php → Reports
    │
    ├─→ Communications
    │   └─→ organizer/send_notification.php → Bulk Emails
    │
    ├─→ Co-Organizers
    │   ├─→ organizer/manage_co_organizers.php → Assign
    │   └─→ organizer/co_organizer_invitations.php → Invites
    │
    └─→ Merchandise
        ├─→ organizer/create_merchandise.php → New Products
        ├─→ organizer/manage_merchandise.php → Product List
        └─→ organizer/send_merch_notification.php → Updates
```

#### **ADMIN FLOW**
```
login.php → Authentication
    ↓
admin/dashboard.php → Main Hub
    ├─→ User Management
    │   ├─→ admin/manage_users.php → User List
    │   ├─→ admin/create_user.php → New Users
    │   └─→ admin/edit_user.php → Modify Users
    │
    ├─→ System Overview
    │   ├─→ admin/browse_events.php → All Events
    │   ├─→ admin/browse_merchandise.php → All Merchandise
    │   └─→ admin/reports.php → Analytics
    │
    └─→ Data Export
        ├─→ admin/download_events.php → Events CSV
        ├─→ admin/download_users.php → Users CSV
        └─→ admin/download_registrations.php → Registrations CSV
```

---

## 🗄️ Key Configuration Files Reference

### **database.php** - Database Connection
```
Location: config/database.php
Purpose: MySQL connection initialization
Used By: Every PHP file that accesses data
Critical For: Data persistence across all modules
```

### **email_config.php** - Email Service
```
Location: config/email_config.php
Purpose: SMTP configuration for notifications
Used By: 
  - organizer/send_notification.php
  - organizer/send_tickets.php
  - admin/send_email.php
Dependency: PHPMailer (in vendor/)
```

### **co_organizer_helper.php** - Utility Functions
```
Location: config/co_organizer_helper.php
Purpose: Co-organizer business logic
Used By: organizer/manage_co_organizers.php
Functionality: Invite, assign, and manage co-organizers
```

---

## 🎨 Shared UI Components Reference

### **header.php** - Top Navigation
```
Location: includes/header.php
Includes: Logo, user menu, navigation links
Loaded By: All pages via include/require
Content: User name display, logout button, role indicator
```

### **sidebar.php** - Context-Sensitive Menu
```
Location: includes/sidebar.php
Loaded By: Module-specific pages
Content: Role-based navigation links
Variations: Different sidebars for student/organizer/admin
```

### **footer.php** - Page Footer
```
Location: includes/footer.php
Loaded By: All pages via include/require
Content: Copyright, links, contact info
```

---

## 💾 Upload Directory Structure

### **Profile Images**
```
uploads/profiles/
├── profile_1_1763063749.jpg
├── profile_2_1769749502.jpg
└── profile_[USER_ID]_[UNIX_TIMESTAMP].jpg

Naming Convention: profile_[USER_ID]_[UNIX_TIMESTAMP].jpg
Size Limit: (See upload configurations)
Formats: JPG, PNG
Usage: User profile picture display
```

### **Merchandise Images**
```
uploads/merchandise/
├── product_1_1763063749.jpg
├── product_2_1769749502.jpg
└── product_[MERCH_ID]_[UNIX_TIMESTAMP].jpg

Naming Convention: product_[MERCH_ID]_[UNIX_TIMESTAMP].jpg
Size Limit: (See upload configurations)
Formats: JPG, PNG
Usage: Product display in marketplace
```

---

## 🔐 Session & Authentication Variables

### **Global Session Variables** (Set at login)
```php
$_SESSION['user_id']           // User's numeric ID
$_SESSION['username']          // Login username
$_SESSION['role']              // User role: 'student', 'organizer', 'admin'
```

### **URL Routing Pattern**
```
https://campus-event-manager.local/
├── / or /index.php → Public (sign-up/login)
├── /login.php → Public (authentication)
├── /login.php POST → Session creation
├── /logout.php → Session destruction
├── /student/* → Student module (role-protected)
├── /organizer/* → Organizer module (role-protected)
└── /admin/* → Admin module (role-protected)
```

---

## 📋 File Organization Best Practices

### **When Adding New Features:**

1. **Place in correct module directory**:
   - Student features → `student/`
   - Organizer features → `organizer/`
   - Admin features → `admin/`

2. **Use consistent naming**:
   - List pages: `browse_*.php` or `manage_*.php`
   - Detail pages: `view_*.php` or `*_detail.php`
   - Form pages: `create_*.php` or `edit_*.php`
   - API endpoints: `ajax_*.php`

3. **Include necessary headers**:
   ```php
   <?php
   session_start();
   require_once '../config/database.php';
   
   // Verify user is logged in
   if(!isset($_SESSION['user_id'])) {
       header("Location: ../login.php");
       exit();
   }
   
   // Verify user role (if needed)
   if($_SESSION['role'] !== 'student') {
       header("Location: ../login.php");
       exit();
   }
   ?>
   ```

4. **Use consistent includes for UI**:
   ```php
   <?php require_once '../includes/header.php'; ?>
   <?php require_once '../includes/sidebar.php'; ?>
   
   <!-- Content here -->
   
   <?php require_once '../includes/footer.php'; ?>
   ```

---

## 🔍 Quick Navigation Reference

**Need to modify:**
- **User profiles** → `includes/header.php` or `admin/` module
- **Event creation** → `organizer/create_event.php` or `organizer/edit_event.php`
- **Student registration** → `student/register_event.php`
- **Email notifications** → `config/email_config.php` or `organizer/send_notification.php`
- **Merchandise** → `organizer/` or `student/` merchandise files
- **Reports** → `admin/reports.php` or `admin/export_report.php`
- **Styling** → `assets/css/style.css`
- **JavaScript interactions** → `assets/js/script.js`

---

## 📌 Important Relationships

| Component | Depends On | Related To |
|-----------|-----------|-----------|
| **Student Module** | Database, Config | Events, Registrations, Merchandise |
| **Organizer Module** | Database, Config, Email | Events, Co-Organizers, Tickets, Merchandise |
| **Admin Module** | Database, Config | All Users, All Events, All Data |
| **Includes** | Varies | All modules |
| **Assets** | Varies | All modules |
| **Uploads** | File System | User profiles, Merchandise images |

---

**Version**: 1.0  
**Last Updated**: April 12, 2026  
**For questions about structure**: Refer to PROJECT_OVERVIEW.md
