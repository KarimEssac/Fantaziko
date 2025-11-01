<header class="header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <a href="../main.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span class="back-text">Back to Dashboard</span>
        </a>
        <?php if (isset($league) && !empty($league)): ?>
        <h1 class="header-title"><?php echo htmlspecialchars($league['name']); ?></h1>
        <?php endif; ?>
    </div>
    <div class="header-right">
        <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
            <i class="fas fa-moon"></i>
        </button>

    </div>
</header>

<style>
.header {
    position: fixed;
    top: 0;
    left: 280px;
    right: 0;
    height: 70px;
    background: var(--nav-bg);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    z-index: 1001;
    transition: all 0.3s ease;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    min-width: 0;
}

.menu-toggle {
    display: none;
    background: transparent;
    border: 2px solid var(--text-primary);
    color: var(--text-primary);
    width: 45px;
    height: 45px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.2rem;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.menu-toggle:hover {
    background: var(--text-primary);
    color: var(--bg-primary);
}

.back-btn {
    background: transparent;
    border: 2px solid var(--gradient-end);
    color: var(--gradient-end);
    padding: 0.6rem 1.2rem;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
}

.back-btn:hover {
    background: var(--gradient-end);
    color: white;
    transform: translateY(-2px);
}

.header-title {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}

.theme-toggle {
    background: transparent;
    border: 2px solid var(--text-primary);
    color: var(--text-primary);
    width: 45px;
    height: 45px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.theme-toggle:hover {
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    color: white;
    border-color: transparent;
    transform: rotate(180deg);
}


@media (max-width: 1024px) {
    .header {
        left: 0;
        padding: 0 1rem;
    }

    .menu-toggle {
        display: flex;
    }

    .header-left {
        gap: 0.8rem;
    }
    .back-btn {
        margin-left: 0;
    }
}

@media (max-width: 768px) {
    .header {
        padding: 0 0.8rem;
    }

    .header-left {
        gap: 0.5rem;
    }

    .back-btn {
        padding: 0.5rem 0.8rem;
        font-size: 0.85rem;
    }

    .back-text {
        display: none;
    }

    .header-title {
        font-size: 1.1rem;
    }

    .theme-toggle {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }

    .menu-toggle {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .header-title {
        display: none;
    }
    
    .back-btn i {
        margin: 0;
    }
    
    .back-btn {
        padding: 0.5rem;
        min-width: 40px;
        justify-content: center;
    }
}
</style>

<script>
(function() {
    const savedTheme = localStorage.getItem('theme');
    const body = document.body;
    
    if (savedTheme === 'light') {
        body.classList.remove('dark-mode');
    } else {
        body.classList.add('dark-mode');
    }
})();

const themeToggle = document.getElementById('themeToggle');
const body = document.body;
const themeIcon = themeToggle ? themeToggle.querySelector('i') : null;

if (themeToggle && themeIcon) {
    if (body.classList.contains('dark-mode')) {
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
    } else {
        themeIcon.classList.remove('fa-sun');
        themeIcon.classList.add('fa-moon');
    }
    
    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        
        if (body.classList.contains('dark-mode')) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
            localStorage.setItem('theme', 'light');
        }
    });
}

const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');

if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
}
</script>