<?php
// league_owner/footer.php
?>

<style>
    .footer {
        margin-left: 280px;
        background: var(--nav-bg);
        backdrop-filter: blur(10px);
        border-top: 1px solid var(--border-color);
        padding: 2rem;
        margin-top: 3rem;
        transition: all 0.3s ease;
    }
    
    body.dark-mode .footer {
        background: rgba(10, 10, 10, 0.95);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .footer-content {
        max-width: 1400px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
    }
    
    .footer-section {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .footer-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .footer-title i {
        color: var(--gradient-end);
        font-size: 1rem;
    }
    
    .footer-links {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }
    
    .footer-link {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .footer-link:hover {
        color: var(--gradient-end);
        transform: translateX(5px);
    }
    
    .footer-link i {
        font-size: 0.85rem;
        width: 20px;
    }
    
    .footer-info {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }
    
    .footer-info-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        color: var(--text-secondary);
        font-size: 0.95rem;
    }
    
    .footer-info-icon {
        width: 35px;
        height: 35px;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.9rem;
    }
    
    .footer-bottom {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .footer-copyright {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .footer-social {
        display: flex;
        gap: 1rem;
    }
    
    .social-link {
        width: 40px;
        height: 40px;
        background: rgba(29, 96, 172, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gradient-end);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    
    body.dark-mode .social-link {
        background: rgba(10, 146, 215, 0.15);
    }
    
    .social-link:hover {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(10, 146, 215, 0.3);
    }
    
    .footer-brand {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }
    
    .footer-brand img {
        height: 35px;
        width: auto;
    }
    
    body.dark-mode .footer-brand img {
        content: url('../assets/images/logo white outline.png');
    }
    
    body:not(.dark-mode) .footer-brand img {
        content: url('../assets/images/logo.png');
    }
    
    .footer-brand-text {
        font-size: 1.2rem;
        font-weight: 900;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    body.dark-mode .footer-brand-text {
        background: none;
        -webkit-text-fill-color: white;
        color: white;
    }
    
    @media (max-width: 1024px) {
        .footer {
            margin-left: 0;
        }
    }
    
    @media (max-width: 768px) {
        .footer {
            padding: 1.5rem 1rem;
        }
        
        .footer-content {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3 class="footer-title">
                <i class="fas fa-trophy"></i>
                About League
            </h3>
            <div class="footer-info">
                <?php if (isset($league)): ?>
                <div class="footer-info-item">
                    <div class="footer-info-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">League Name</div>
                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($league['name']); ?></div>
                    </div>
                </div>
                <div class="footer-info-item">
                    <div class="footer-info-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">League Token</div>
                        <div style="font-weight: 600; color: var(--text-primary); font-family: monospace;"><?php echo htmlspecialchars($league['token']); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-section">
            <h3 class="footer-title">
                <i class="fas fa-cogs"></i>
                Quick Links
            </h3>
            <div class="footer-links">
                <a href="league_settings.php?token=<?php echo $league_token; ?>" class="footer-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="manage_teams.php?token=<?php echo $league_token; ?>" class="footer-link">
                    <i class="fas fa-shield-alt"></i>
                    Manage Teams
                </a>
                <a href="manage_players.php?token=<?php echo $league_token; ?>" class="footer-link">
                    <i class="fas fa-users"></i>
                    Manage Players
                </a>
                <a href="leaderboard.php?token=<?php echo $league_token; ?>" class="footer-link">
                    <i class="fas fa-trophy"></i>
                    Leaderboard
                </a>
            </div>
        </div>
        
        <div class="footer-section">
            <h3 class="footer-title">
                <i class="fas fa-question-circle"></i>
                Support
            </h3>
            <div class="footer-links">
                <a href="#" class="footer-link">
                    <i class="fas fa-book"></i>
                    Documentation
                </a>
                <a href="#" class="footer-link">
                    <i class="fas fa-life-ring"></i>
                    Help Center
                </a>
                <a href="#" class="footer-link">
                    <i class="fas fa-envelope"></i>
                    Contact Us
                </a>
                <a href="../main.php" class="footer-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Main Dashboard
                </a>
            </div>
        </div>
        
        <div class="footer-section">
            <h3 class="footer-title">
                <i class="fas fa-chart-line"></i>
                League Stats
            </h3>
            <div class="footer-info">
                <?php if (isset($league)): ?>
                <div class="footer-info-item">
                    <div class="footer-info-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">Current Round</div>
                        <div style="font-weight: 600; color: var(--text-primary);">Round <?php echo $league['round']; ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="footer-brand">
            <img src="../assets/images/logo white outline.png" alt="Fantazina">
            <span class="footer-brand-text">FANTAZINA</span>
        </div>
        
        <div class="footer-copyright">
            © <?php echo date('Y'); ?> Fantazina. All rights reserved.
        </div>
        
        <div class="footer-social">
            <a href="#" class="social-link" title="Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="social-link" title="Twitter">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="#" class="social-link" title="Instagram">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="#" class="social-link" title="Discord">
                <i class="fab fa-whatsapp"></i>
            </a>
        </div>
    </div>
</footer>