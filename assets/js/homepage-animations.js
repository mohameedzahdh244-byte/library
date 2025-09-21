// تأثيرات الصفحة الرئيسية المحدثة
document.addEventListener('DOMContentLoaded', function() {
    
    // عداد الأرقام المتحرك
    function animateCounters() {
        const counters = document.querySelectorAll('.counter');
        
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            const increment = target / 100;
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current).toLocaleString();
                }
            }, 20);
        });
    }
    
    // تأثير الظهور عند التمرير
    function handleScrollAnimations() {
        const animatedElements = document.querySelectorAll('.info-card, .hero-stat-card');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        animatedElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            observer.observe(el);
        });
    }
    
    // تأثير الخلفية المتحركة
    function createFloatingElements() {
        const heroSection = document.querySelector('.hero-section');
        if (!heroSection) return;
        
        for (let i = 0; i < 5; i++) {
            const floatingElement = document.createElement('div');
            floatingElement.className = 'floating-element';
            floatingElement.style.cssText = `
                position: absolute;
                width: ${Math.random() * 100 + 50}px;
                height: ${Math.random() * 100 + 50}px;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 50%;
                top: ${Math.random() * 100}%;
                left: ${Math.random() * 100}%;
                animation: float ${Math.random() * 10 + 10}s infinite ease-in-out;
                pointer-events: none;
            `;
            heroSection.appendChild(floatingElement);
        }
    }
    
    // تأثير تمرير ناعم للروابط
    function setupSmoothScrolling() {
        const links = document.querySelectorAll('a[href^="#"]');
        
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
    
    // تأثير تغيير الهيدر عند التمرير
    function setupHeaderScrollEffect() {
        const navbar = document.getElementById('mainNavbar');
        if (!navbar) return;
        
        let lastScrollTop = 0;
        
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > 100) {
                navbar.classList.add('scrolled');
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else {
                navbar.classList.remove('scrolled');
                navbar.style.boxShadow = '';
                navbar.style.backdropFilter = '';
            }
            
            lastScrollTop = scrollTop;
        });
    }
    
    // تأثير تحريك الكروت عند التمرير
    function setupParallaxEffect() {
        const cards = document.querySelectorAll('.info-card');
        
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;
            
            cards.forEach((card, index) => {
                const speed = (index + 1) * 0.1;
                card.style.transform = `translateY(${rate * speed}px)`;
            });
        });
    }
    
    // تفعيل جميع التأثيرات
    setTimeout(() => {
        animateCounters();
        handleScrollAnimations();
        createFloatingElements();
        setupSmoothScrolling();
        setupHeaderScrollEffect();
    }, 500);
    
    // إضافة أنماط CSS للعناصر المتحركة
    const style = document.createElement('style');
    style.textContent = `
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(5deg); }
            50% { transform: translateY(-10px) rotate(-5deg); }
            75% { transform: translateY(-15px) rotate(3deg); }
        }
        
        .floating-element {
            animation: float 15s infinite ease-in-out;
        }
        
        .navbar.scrolled {
            background: rgba(13, 110, 253, 0.95) !important;
            transition: all 0.3s ease;
        }
        
        .hero-stat-card:hover .stat-icon i {
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .info-card:hover {
            transform: translateY(-8px) scale(1.02);
        }
        
        .goal-item:hover {
            transform: scale(1.05);
            background: rgba(255,255,255,0.2) !important;
        }
    `;
    document.head.appendChild(style);
});
