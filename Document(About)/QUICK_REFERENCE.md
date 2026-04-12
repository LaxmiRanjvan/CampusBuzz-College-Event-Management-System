# Campus Event Manager - Quick Reference Guide

## 📚 Documentation Overview

This project now has **4 comprehensive documentation files**:

1. **PROJECT_OVERVIEW.md** - Complete project guide (start here!)
2. **FOLDER_STRUCTURE.md** - Directory organization and file purpose
3. **MODULE_DEPENDENCIES.md** - Database schema and module interactions
4. **QUICK_REFERENCE.md** - This file (quick lookups)

---

## 🎯 Quick Answers

### "I'm making changes to the STUDENT module. What should I know?"

**Essential Files**:
- Module directory: `student/`
- Configuration: `config/database.php`
- UI components: `includes/header.php`, `includes/sidebar.php`, `includes/footer.php`
- Styling: `assets/css/style.css`
- JavaScript: `assets/js/script.js`

**Database Tables Used**:
- `users` - User authentication and profile
- `events` - Event information
- `registrations` - Student event registrations
- `merchandise` - Product catalog
- `event_likes` - Like/unlike functionality

**Key Files**:
- `student/dashboard.php` - Student home page
- `student/browse_events.php` - Event discovery
- `student/register_event.php` - Event registration
- `student/my_events.php` - View registered events
- `student/ajax_toggle_like.php` - Like events (AJAX endpoint)

**Important Variables**:
```php
$_SESSION['user_id']   // Current student's ID
$_SESSION['role']      // Should be 'student'
```

**Cross-Module Impact**:
- Student registrations are viewed by Organizers (organizer/view_registrations.php)
- Student likes/comments are seen by all users
- Student registration data is exported by Admin (admin/download_registrations.php)

---

### "I'm making changes to the ORGANIZER module. What should I know?"

**Essential Files**:
- Module directory: `organizer/`
- Config files: `database.php`, `email_config.php`, `co_organizer_helper.php`
- Email library: `vendor/phpmailer/phpmailer/`
- File uploads: `uploads/`

**Database Tables Used**:
- `users` - User authentication
- `events` - Event management
- `registrations` - Attendee tracking
- `merchandise` - Product inventory
- `event_organizers` - Co-organizer assignments
- `tickets` - Ticket issuance and verification
- `notifications` - Notification history

**Major Features** (19 files):
- Event Management: create, edit, manage, view events
- Ticketing: issue tickets, verify tickets, generate reports
- Co-Organizers: invite, manage, assign
- Merchandise: create, edit, manage products
- Communications: send notifications, send emails

**Key Files by Function**:
```
Event Management:
  ├── create_event.php
  ├── manage_events.php
  ├── edit_event.php
  └── view_event.php

Ticketing:
  ├── send_tickets.php
  ├── verify_ticket.php
  └── verification_report.php

Co-Organizers:
  ├── manage_co_organizers.php
  └── co_organizer_invitations.php

Merchandise:
  ├── create_merchandise.php
  ├── manage_merchandise.php
  └── edit_merchandise.php

Communications:
  └── send_notification.php
```

**Cross-Module Impact**:
- Admin can see all organizer-created events
- Students see published events created by organizers
- Organizer notifications go to registered students

---

### "I'm making changes to the ADMIN module. What should I know?"

**Essential Files**:
- Module directory: `admin/`
- Configuration: All config files
- Email library: `vendor/phpmailer/`
- File management: `uploads/`

**Database Tables Used**:
- ALL TABLES (complete system access)

**Major Features** (16 files):
- User Management: create, edit, delete users
- System Overview: view all events, all merchandise
- Reporting: generate comprehensive reports
- Data Export: export users, events, registrations

**Key Files by Function**:
```
User Management:
  ├── manage_users.php
  ├── create_user.php
  ├── edit_user.php
  └── view_user.php

System Browsing:
  ├── browse_events.php
  ├── browse_merchandise.php
  └── reports.php

Data Export:
  ├── download_events.php
  ├── download_users.php
  ├── download_registrations.php
  └── export_report.php
```

**Cross-Module Impact**:
- Can create users of any role (student, organizer, admin)
- Can monitor all system activity
- Can override organizer/student actions if needed

---

## 🗄️ Database Quick Reference

### Critical Tables

| Table | Records | Purpose |
|-------|---------|---------|
| `users` | User accounts | Authentication, profiles, roles |
| `events` | Campus events | Event data, organizer info |
| `registrations` | Attendances | Who registered for which event |
| `merchandise` | Products | Item catalog, pricing |
| `event_organizers` | Co-organizer assignments | Event collaboration |
| `tickets` | Event tickets | Ticketing & verification |
| `notifications` | Messages sent | Communication history |

### Most Queried Tables (by module)
```
STUDENT:        events, registrations, merchandise
ORGANIZER:      events, registrations, event_organizers, tickets
ADMIN:          users, events, registrations, reports view
```

---

## 🔍 Finding What You Need

### "I need to find the code that..."

**...authenticates users?**
- `login.php` - Login form and verification
- `config/database.php` - Database connection
- `includes/header.php` - Session check in UI

**...sends emails?**
- `organizer/send_notification.php` - Event notifications
- `organizer/send_tickets.php` - Ticket emails
- `admin/send_email.php` - System emails
- `config/email_config.php` - Email configuration
- `vendor/phpmailer/phpmailer/` - Email library

**...manages events?**
- `organizer/create_event.php` - Create new event
- `organizer/edit_event.php` - Edit existing event
- `organizer/manage_events.php` - List organizer's events
- `admin/browse_events.php` - View all system events

**...creates users?**
- `admin/create_user.php` - Admin user creation
- `index.php` - Student/Organizer sign-up

**...issues tickets?**
- `organizer/send_tickets.php` - Ticket generation
- `organizer/verify_ticket.php` - Ticket verification

**...handles file uploads?**
- Various files with: `$_FILES['field']` handling
- Uploads stored in: `uploads/profiles/` or `uploads/merchandise/`

**...builds reports?**
- `admin/reports.php` - Report generation
- `organizer/verification_report.php` - Ticket verification reports

**...uses AJAX?**
- `student/ajax_toggle_like.php` - Like/unlike endpoint
- `assets/js/script.js` - AJAX request handling

---

## 🚀 Common Tasks

### **Task: Add a new field to Student profile**

1. Update database: `users` table
2. Check storage: `uploads/profiles/`
3. Modify files: 
   - `admin/edit_user.php` - Edit form
   - `common/profile.php` - Display profile
4. Update styles: `assets/css/style.css`
5. Test with: student role

---

### **Task: Create a new event notification**

1. Create PHP file in: `organizer/`
2. Include database: `config/database.php`
3. Include email: `config/email_config.php`
4. Use library: `vendor/phpmailer/`
5. Write to DB: `notifications` table
6. Follow pattern from: `organizer/send_notification.php`

---

### **Task: Add event filtering/search**

1. Modify: `student/browse_events.php` or `organizer/browse_events.php`
2. Update query: SQL WHERE clauses for filtering
3. Add UI: Search form in HTML
4. Add JavaScript: `assets/js/script.js` for dynamic filtering
5. Test: Verify filtering works by role

---

### **Task: Modify user role permissions**

1. Find role checks in: Any module file (grep for `$_SESSION['role']`)
2. Update logic: Add new conditions if needed
3. Test: Log in as different roles
4. Note: Admin can bypass most restrictions

---

## 📋 File Naming Conventions

| Pattern | Usage | Example |
|---------|-------|---------|
| `browse_*.php` | List/browse pages | `browse_events.php` |
| `create_*.php` | Creation forms | `create_event.php` |
| `edit_*.php` | Edit forms | `edit_event.php` |
| `view_*.php` | Detail/view pages | `view_event.php` |
| `manage_*.php` | Management interfaces | `manage_events.php` |
| `ajax_*.php` | AJAX endpoints | `ajax_toggle_like.php` |
| `send_*.php` | Communication senders | `send_notification.php` |
| `*_report.php` | Report generators | `verification_report.php` |
| `export_*.php` | Data exporters | `export_report.php` |
| `download_*.php` | Download handlers | `download_events.php` |

---

## 🔐 Security Checklist

Before deploying any changes:

- [ ] Session check: `if(!isset($_SESSION['user_id']))` 
- [ ] Role verification: `if($_SESSION['role'] !== 'expected')`
- [ ] SQL injection prevention: Use `mysqli_real_escape_string()`
- [ ] File upload validation: Check file type and size
- [ ] Password hashing: Use proper hashing (not MD5)
- [ ] CSRF protection: Consider token validation
- [ ] Error handling: Don't expose database errors to users
- [ ] Email validation: Verify email format before sending

---

## 🐛 Debugging Tips

### **Check Session**
```php
<?php
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>
```

### **Check Database Connection**
```php
<?php
require_once 'config/database.php';
if($conn) {
    echo "Database connected!";
} else {
    echo "Database error: " . mysqli_connect_error();
}
?>
```

### **Check User Role**
```php
<?php
session_start();
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "User Role: " . $_SESSION['role'] . "<br>";
?>
```

### **Check Query Results**
```php
<?php
$result = mysqli_query($conn, $query);
echo "Rows: " . mysqli_num_rows($result) . "<br>";
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
}
?>
```

---

## 📞 Quick Links

| Need Help With | Go To |
|----------------|-------|
| Project overview | PROJECT_OVERVIEW.md |
| File locations | FOLDER_STRUCTURE.md |
| Database & dependencies | MODULE_DEPENDENCIES.md |
| Quick answers | QUICK_REFERENCE.md (this file) |
| Database setup | users.sql |
| Configuration | config/database.php |
| Email setup | config/email_config.php |

---

## 🔗 Inter-Module Communication Matrix

```
STUDENT → Can Read:     events, merchandise
          Can Write:    registrations, event_likes, comments
          Can See:      own profile, own registrations

ORGANIZER → Can Read:   events (own), registrations (own), users, merchandise (own)
            Can Write:  events, registrations, merchandise, tickets, notifications
            Can See:    organizer dashboard, created events

ADMIN → Can Read:       ALL DATA
        Can Write:      ALL DATA
        Can See:        complete system overview
```

---

## ✅ Pre-Deployment Checklist

Before pushing changes to production:

- [ ] All files follow naming conventions
- [ ] Database changes are documented
- [ ] Cross-module impacts tested
- [ ] Role-based testing completed
- [ ] Security checks passed
- [ ] File upload paths verified
- [ ] Email configuration checked
- [ ] Error handling implemented
- [ ] Documentation updated
- [ ] Code reviewed for bugs

---

**Keep this file as your quick reference!**  
**For detailed information, see the other 3 documentation files.**

**Version**: 1.0  
**Last Updated**: April 12, 2026  
**Project Status**: In Active Development
