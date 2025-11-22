<?php
// footer.php - Place in includes/ folder
?>
<footer style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 40px 30px; margin-top: 60px;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; margin-bottom: 40px;">
            <!-- About Section -->
            <div>
                <h3 style="margin-bottom: 20px; font-size: 24px; display: flex; align-items: center; gap: 10px;">
                    <span>🎓</span> Campus Events
                </h3>
                <p style="line-height: 1.8; opacity: 0.9; font-size: 14px;">
                    Your one-stop platform for discovering and managing campus events, networking with peers, and staying connected with campus life.
                </p>
                <!-- <div style="display: flex; gap: 15px; margin-top: 20px;"> -->
                    <!-- <a href="#" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s;"> -->
                        <!-- 📘 -->
                    <!-- </a> -->
                    <!-- <a href="#" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s;"> -->
                        <!-- 🐦 -->
                    <!-- </a> -->
                    <!-- <a href="#" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s;"> -->
                        <!-- 📷 -->
                    <!-- </a> -->
                    <!-- <a href="#" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s;"> -->
                        <!-- 💼 -->
                    <!-- </a> -->
                <!-- </div> -->
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 style="margin-bottom: 20px; font-size: 18px;">Quick Links</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 12px;">
                        <a href="../common/home.php" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;">
                            🏠 Home
                        </a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="../<?php echo $_SESSION['role']; ?>/browse_events.php" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;">
                            📅 Browse Events
                        </a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="../<?php echo $_SESSION['role']; ?>/browse_merchandise.php" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;">
                            🛍️ Merchandise
                        </a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="../common/profile.php" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;">
                            👤 My Profile
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Support -->
            <div>
                <h4 style="margin-bottom: 20px; font-size: 18px;">Support</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 12px;">
                        <a href="../common/help.php" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;">
                            ❓ Help Center
                        </a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="../common/help.php" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;">
                            📧 Contact Us
                        </a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="../common/help.php" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;">
                            🔒 Privacy Policy
                        </a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <!-- <a href="#" style="color: white; text-decoration: none; opacity: 0.9; font-size: 14px; transition: all 0.3s;"> -->
                            
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Contact Info -->
            <div>
                <h4 style="margin-bottom: 20px; font-size: 18px;">Get in Touch</h4>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="font-size: 18px;">📍</span>
                        <div style="opacity: 0.9; font-size: 14px; line-height: 1.6;">
                            Campus Address<br>
                            City, State - 123456
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 18px;">📧</span>
                        <span style="opacity: 0.9; font-size: 14px;">events@campus.edu</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 18px;">📞</span>
                        <span style="opacity: 0.9; font-size: 14px;">+91 123-456-7890</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bottom Bar -->
        <div style="padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.2); text-align: center;">
            <p style="opacity: 0.9; font-size: 14px; margin: 0;">
                © <?php echo date('Y'); ?> Campus Event Manager. All rights reserved. | Made with ❤️ for students
            </p>
        </div>
    </div>
</footer>

<style>
    footer a:hover {
        opacity: 1 !important;
        transform: translateX(5px);
    }
    
    footer .social-link:hover {
        background: rgba(255,255,255,0.3) !important;
        transform: translateY(-3px) !important;
    }
</style>