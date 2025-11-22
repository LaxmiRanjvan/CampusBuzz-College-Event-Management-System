<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .help-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .help-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 40px;
            border-radius: 16px;
            margin-bottom: 40px;
            text-align: center;
            color: white;
        }
        
        .help-hero h1 {
            font-size: 42px;
            margin-bottom: 15px;
        }
        
        .help-search {
            max-width: 600px;
            margin: 30px auto 0;
            position: relative;
        }
        
        .help-search input {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .help-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .faq-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 20px 0;
        }
        
        .faq-item:last-child {
            border-bottom: none;
        }
        
        .faq-question {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .faq-question:hover {
            background: #f7fafc;
        }
        
        .faq-answer {
            padding: 15px 10px 0;
            color: #4a5568;
            line-height: 1.8;
            display: none;
        }
        
        .faq-answer.active {
            display: block;
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .category-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .category-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .contact-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }
        
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .contact-method {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="help-container">
                <!-- Hero Section -->
                <div class="help-hero">
                    <h1>🆘 How can we help you?</h1>
                    <p>Search our knowledge base or browse categories below</p>
                    
                    <div class="help-search">
                        <input type="text" id="searchInput" placeholder="Search for help articles..." onkeyup="searchFAQ()">
                    </div>
                </div>
                
                <!-- Quick Categories -->
                <div class="category-grid">
                    <div class="category-card" onclick="scrollToSection('getting-started')">
                        <div class="category-icon">🚀</div>
                        <h3>Getting Started</h3>
                        <p style="color: #718096; font-size: 14px;">Learn the basics</p>
                    </div>
                    
                    <div class="category-card" onclick="scrollToSection('events')">
                        <div class="category-icon">📅</div>
                        <h3>Events</h3>
                        <p style="color: #718096; font-size: 14px;">Managing events</p>
                    </div>
                    
                    <?php if($user_role == 'student'): ?>
                    <div class="category-card" onclick="scrollToSection('registration')">
                        <div class="category-icon">🎫</div>
                        <h3>Registration</h3>
                        <p style="color: #718096; font-size: 14px;">Registering for events</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="category-card" onclick="scrollToSection('account')">
                        <div class="category-icon">👤</div>
                        <h3>Account</h3>
                        <p style="color: #718096; font-size: 14px;">Profile & settings</p>
                    </div>
                </div>
                
                <!-- Getting Started -->
                <div class="help-section" id="getting-started">
                    <h2 style="margin-bottom: 20px; color: #2d3748;">🚀 Getting Started</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>What is Campus Event Manager?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Campus Event Manager is a comprehensive platform designed to help students, organizers, and administrators manage campus events efficiently. You can browse events, register for activities, save your favorites, and much more!
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>How do I navigate the platform?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Use the sidebar menu on the left to navigate between different sections. The Home page shows all upcoming events, and your Dashboard provides personalized information based on your role (Student, Organizer, or Admin).
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>How do I update my profile?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Click on "Profile" in the sidebar menu. You can update your personal information, upload a profile picture, and add a bio. Remember to click "Save Changes" when you're done!
                        </div>
                    </div>
                </div>
                
                <!-- Events Section -->
                <div class="help-section" id="events">
                    <h2 style="margin-bottom: 20px; color: #2d3748;">📅 Events</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>How do I find events I'm interested in?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            On the Home page, you can:
                            <ul style="margin-top: 10px;">
                                <li>Use the search bar to find specific events</li>
                                <li>Filter by category (Academic, Sports, Cultural, etc.)</li>
                                <li>Filter by event type (Online, Offline, Hybrid)</li>
                                <li>Browse "Upcoming This Week" section for events happening soon</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>What do the event badges mean?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Event badges provide quick information:
                            <ul style="margin-top: 10px;">
                                <li><strong>FULL:</strong> Event has reached maximum capacity</li>
                                <li><strong>"X seats left":</strong> Limited seats available (less than 10)</li>
                                <li><strong>"This Week":</strong> Event happening in the next 7 days</li>
                                <li><strong>Online/Offline/Hybrid:</strong> Event format</li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if($user_role == 'organizer'): ?>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>How do I create a new event?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Go to "Create Event" in your sidebar menu. Fill in all required information including title, description, date, time, venue, and capacity. You can also upload an event image and add registration links. Click "Create Event" to publish it!
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Registration Section (Students Only) -->
                <?php if($user_role == 'student'): ?>
                <div class="help-section" id="registration">
                    <h2 style="margin-bottom: 20px; color: #2d3748;">🎫 Event Registration</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>How do I register for an event?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Browse events on the Home page and click the "Register" button on any event you'd like to attend. If the event is full, the button will be disabled. You can view all your registered events in "My Events" page.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>Can I cancel my registration?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Yes! Go to "My Events" page and click the "Cancel Registration" button next to the event you want to cancel. This will free up your spot for other students.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>What's the difference between Like and Save?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            <ul style="margin-top: 10px;">
                                <li><strong>❤️ Like:</strong> Show appreciation for an event. Likes are visible to everyone and help popular events get more visibility.</li>
                                <li><strong>🔖 Save:</strong> Bookmark events you're interested in for later. Saved events appear in your "My Events" page for easy access.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Account Section -->
                <div class="help-section" id="account">
                    <h2 style="margin-bottom: 20px; color: #2d3748;">👤 Account Settings</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>How do I change my password?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Currently, password changes must be done through an administrator. Please contact your campus admin or use the "Contact Support" option below to request a password reset.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>Can I change my username?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Usernames cannot be changed for security reasons. If you need to update your username, please contact an administrator.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleAnswer(this)">
                            <span>Is my data secure?</span>
                            <span>▼</span>
                        </div>
                        <div class="faq-answer">
                            Yes! We take data security seriously. Your personal information is encrypted and stored securely. We never share your data with third parties without your consent.
                        </div>
                    </div>
                </div>
                
                <!-- Contact Support -->
                <div class="contact-card">
                    <h2 style="margin-bottom: 15px;">📞 Still need help?</h2>
                    <p>Our support team is here to assist you!</p>
                    
                    <div class="contact-methods">
                        <div class="contact-method">
                            <div style="font-size: 32px; margin-bottom: 10px;">📧</div>
                            <h4>Email Support</h4>
                            <p style="font-size: 14px; opacity: 0.9;">support@campusevents.com</p>
                        </div>
                        
                        <div class="contact-method">
                            <div style="font-size: 32px; margin-bottom: 10px;">💬</div>
                            <h4>Live Chat</h4>
                            <p style="font-size: 14px; opacity: 0.9;">Available Mon-Fri, 9AM-5PM</p>
                        </div>
                        
                        <div class="contact-method">
                            <div style="font-size: 32px; margin-bottom: 10px;">📱</div>
                            <h4>Phone</h4>
                            <p style="font-size: 14px; opacity: 0.9;">+91 1234567890</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function toggleAnswer(element) {
            const answer = element.nextElementSibling;
            const arrow = element.querySelector('span:last-child');
            
            answer.classList.toggle('active');
            arrow.textContent = answer.classList.contains('active') ? '▲' : '▼';
        }
        
        function scrollToSection(sectionId) {
            document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function searchFAQ() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question span:first-child').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if(question.includes(input) || answer.includes(input)) {
                    item.style.display = 'block';
                    if(input) {
                        item.querySelector('.faq-answer').classList.add('active');
                        item.querySelector('.faq-question span:last-child').textContent = '▲';
                    }
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>