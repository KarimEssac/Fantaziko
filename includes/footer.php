<style>
    .footer {
        background: linear-gradient(135deg, #1D60AC 0%, #0A92D7 100%);
        color: #FFFFFF;
        padding: 30px 0;
        margin-top: 50px;
        margin-left: 280px;
        transition: margin-left 0.3s ease;
    }
    
    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 30px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
    }
    
    .footer-section {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .footer-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 5px;
        color: #FFFFFF;
    }
    
    .footer-text {
        font-size: 14px;
        line-height: 1.6;
        color: rgba(255,255,255,0.9);
    }
    
    .footer-links {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .footer-link {
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease, padding-left 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .footer-link:hover {
        color: #FFFFFF;
        padding-left: 5px;
    }
    
    .footer-link::before {
        content: '→';
        font-size: 12px;
    }
    
    .footer-social {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }
    
    .social-icon {
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #FFFFFF;
        text-decoration: none;
        font-size: 18px;
        transition: all 0.3s ease;
    }
    
    .social-icon:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-3px);
    }
    
    .footer-bottom {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.2);
        text-align: center;
    }
    
    .footer-bottom-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .copyright {
        font-size: 14px;
        color: rgba(255,255,255,0.9);
    }
    
    .footer-bottom-links {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .footer-bottom-link {
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease;
    }
    
    .footer-bottom-link:hover {
        color: #FFFFFF;
    }
    
    @media (max-width: 1024px) {
        .footer {
            margin-left: 0;
        }
    }
    
    @media (max-width: 768px) {
        .footer {
            padding: 20px 0;
            margin-top: 30px;
        }
        
        .footer-content {
            padding: 0 15px;
            gap: 20px;
        }
        
        .footer-section {
            gap: 10px;
        }
        
        .footer-bottom-content {
            flex-direction: column;
            text-align: center;
            padding: 0 15px;
        }
        
        .footer-bottom-links {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .footer-title {
            font-size: 16px;
        }
        
        .footer-text,
        .footer-link,
        .copyright,
        .footer-bottom-link {
            font-size: 13px;
        }
        
        .social-icon {
            width: 35px;
            height: 35px;
            font-size: 16px;
        }
    }
</style>

<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3 class="footer-title">Fantaziko</h3>
            <p class="footer-text">
                Your ultimate fantasy sports management platform. Create leagues, manage teams, and track performance with ease.
            </p>
            <div class="footer-social">
                <a href="#" class="social-icon" title="Facebook">📘</a>
                <a href="#" class="social-icon" title="Twitter">🐦</a>
                <a href="#" class="social-icon" title="Instagram">📷</a>
                <a href="#" class="social-icon" title="LinkedIn">💼</a>
            </div>
        </div>
        
        <div class="footer-section">
            <h3 class="footer-title">Quick Links</h3>
            <div class="footer-links">
                <a href="index.php" class="footer-link">Dashboard</a>
                <a href="accounts.php" class="footer-link">Accounts</a>
                <a href="leagues.php" class="footer-link">Leagues</a>
                <a href="teams.php" class="footer-link">Teams</a>
                <a href="players.php" class="footer-link">Players</a>
            </div>
        </div>
        
        <div class="footer-section">
            <h3 class="footer-title">Management</h3>
            <div class="footer-links">
                <a href="matches.php" class="footer-link">Matches</a>
                <a href="match-points.php" class="footer-link">Match Points</a>
                <a href="reports.php" class="footer-link">Reports</a>
                <a href="analytics.php" class="footer-link">Analytics</a>
                <a href="leaderboards.php" class="footer-link">Leaderboards</a>
            </div>
        </div>
        
        <div class="footer-section">
            <h3 class="footer-title">Support</h3>
            <div class="footer-links">
                <a href="settings.php" class="footer-link">Settings</a>
                <a href="logs.php" class="footer-link">System Logs</a>
                <a href="#" class="footer-link">Help Center</a>
                <a href="#" class="footer-link">Documentation</a>
                <a href="#" class="footer-link">Contact Us</a>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Fantaziko. All rights reserved.
            </div>
            <div class="footer-bottom-links">
                <a href="#" class="footer-bottom-link">Privacy Policy</a>
                <a href="#" class="footer-bottom-link">Terms of Service</a>
                <a href="#" class="footer-bottom-link">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>