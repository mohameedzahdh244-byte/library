    
    <!-- Footer الخرافي -->
    <footer class="footer-luxury position-relative overflow-hidden" 
            style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1565c0 100%); margin-top: 4rem;">
        
        <!-- خلفية متحركة رائعة -->
        <div class="footer-bg-animation position-absolute top-0 start-0 w-100 h-100">
            <!-- موجات متحركة -->
            <div class="wave-animation position-absolute bottom-0 start-0 w-100" style="height: 100px; opacity: 0.1;">
                <svg viewBox="0 0 1200 120" xmlns="http://www.w3.org/2000/svg">
                    <path d="M0,60 C300,120 900,0 1200,60 L1200,120 L0,120 Z" fill="rgba(255,255,255,0.1)" class="wave-path"/>
                </svg>
            </div>
            
            <!-- نجوم متلألئة -->
            <div class="stars-container position-absolute top-0 start-0 w-100 h-100">
                <div class="star star-1"></div>
                <div class="star star-2"></div>
                <div class="star star-3"></div>
                <div class="star star-4"></div>
                <div class="star star-5"></div>
                <div class="star star-6"></div>
            </div>
            
            <!-- خلفية متطورة -->
            <div class="geometric-bg position-absolute top-0 start-0 w-100 h-100" style="opacity: 0.02;">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="luxuryPattern" x="0" y="0" width="120" height="120" patternUnits="userSpaceOnUse">
                            <circle cx="60" cy="60" r="2" fill="white" opacity="0.4"/>
                            <circle cx="20" cy="20" r="1" fill="white" opacity="0.3"/>
                            <circle cx="100" cy="100" r="1.5" fill="white" opacity="0.3"/>
                            <polygon points="60,30 70,50 50,50" fill="white" opacity="0.2"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#luxuryPattern)"/>
                </svg>
            </div>
        </div>
        
        <div class="container position-relative">
            <!-- القسم الرئيسي -->
            <div class="row g-5 py-5">
                <!-- معلومات المكتبة -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand mb-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="footer-logo-luxury me-4 position-relative">
                                <div class="logo-glow-ring position-absolute top-50 start-50 translate-middle"></div>
                                <div class="footer-logo-wrapper d-flex align-items-center justify-content-center rounded-4 shadow-lg"
                                     style="width: 65px; height: 65px; background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05)); 
                                            backdrop-filter: blur(15px); border: 2px solid rgba(255,255,255,0.2);">
                                    <img src="./../public/logo.png" alt="شعار المكتبة" 
                                         style="width: 38px; height: 38px; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));">
                                </div>
                            </div>
                            <div class="brand-text">
                                <h3 class="text-white fw-bold mb-2 brand-title" style="font-size: 1.4rem; letter-spacing: 1px;">
                                    مكتبة بلدية الخليل
                                </h3>
                                <div class="brand-subtitle d-flex align-items-center">
                                    <div class="subtitle-line me-2"></div>
                                    <span class="text-white-75 fw-semibold" style="font-size: 0.9rem;">الرقمية المتطورة</span>
                                    <div class="subtitle-line ms-2"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="footer-divider mb-3"></div>
                        
                        <p class="text-white-75 lh-lg mb-4" style="font-size: 0.9rem;">
                            منارة العلم والمعرفة في قلب مدينة الخليل، نوفر خدمات مكتبية متطورة لدعم التعليم والبحث العلمي والثقافة.
                        </p>
                        
                    </div>
                </div>
                
                <!-- روابط سريعة -->
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h5 class="text-white fw-bold mb-3 d-flex align-items-center">
                            <i class="fas fa-link me-2 text-white-50"></i>
                            روابط سريعة
                        </h5>
                        <div class="footer-divider mb-3"></div>
                        
                        <div class="footer-links-container">
                            <div class="footer-link-item mb-3">
                                <a href="./../index.php" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-home me-3 text-white-50" style="width: 18px;"></i>
                                    <span>الرئيسية</span>
                                </a>
                            </div>
                            <div class="footer-link-item mb-3">
                                <a href="search.php" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-search me-3 text-white-50" style="width: 18px;"></i>
                                    <span>البحث عن الكتب</span>
                                </a>
                            </div>
                            <div class="footer-link-item mb-3">
                                <a href="./../index.php#about" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-info-circle me-3 text-white-50" style="width: 18px;"></i>
                                    <span>حول المكتبة</span>
                                </a>
                            </div>
                            <div class="footer-link-item mb-3">
                                <a href="./../auth/loginform.php" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-sign-in-alt me-3 text-white-50" style="width: 18px;"></i>
                                    <span>تسجيل الدخول</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- خدماتنا -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h5 class="text-white fw-bold mb-3 d-flex align-items-center">
                            <i class="fas fa-cogs me-2 text-white-50"></i>
                            خدماتنا
                        </h5>
                        <div class="footer-divider mb-3"></div>
                        
                        <div class="services-container">
                            <div class="service-item mb-3">
                                <a href="#" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-book me-3 text-white-50" style="width: 18px;"></i>
                                    <span>استعارة الكتب والمراجع</span>
                                </a>
                            </div>
                            <div class="service-item mb-3">
                                <a href="activities_public.php" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-calendar-alt me-3 text-white-50" style="width: 18px;"></i>
                                    <span>الأنشطة الثقافية والتعليمية</span>
                                </a>
                            </div>
                            <div class="service-item mb-3">
                                <a href="#" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-laptop me-3 text-white-50" style="width: 18px;"></i>
                                    <span>المكتبة الرقمية المتطورة</span>
                                </a>
                            </div>
                            <div class="service-item mb-3">
                                <a href="#" class="footer-link d-flex align-items-center py-2">
                                    <i class="fas fa-users me-3 text-white-50" style="width: 18px;"></i>
                                    <span>قاعات الدراسة والمطالعة</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات التواصل -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h5 class="text-white fw-bold mb-3 d-flex align-items-center">
                            <i class="fas fa-address-book me-2 text-white-50"></i>
                            تواصل معنا
                        </h5>
                        <div class="footer-divider mb-3"></div>
                        
                        <div class="footer-contact mb-4">
                            <div class="contact-item mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div>
                                        <span class="text-white-75 fw-medium">الخليل، فلسطين</span><br>
                                        <small class="text-white-50">بئر الحمص</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="contact-item mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <span class="text-white-75 fw-medium">السبت - الخميس</span><br>
                                        <small class="text-white-50">9:00 ص - 3:00 م</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- روابط التواصل الاجتماعي -->
                        <div class="social-section">
                            <h6 class="text-white-75 mb-3 fw-semibold">تابعنا على:</h6>
                            <div class="d-flex gap-3">
                                <a href="https://facebook.com/Hebron.Municipality.public.Library" 
                                   target="_blank" rel="noopener noreferrer"
                                   class="social-btn facebook" title="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="http://www.hebron-city.ps/" 
                                   target="_blank" rel="noopener noreferrer"
                                   class="social-btn website" title="الموقع الرسمي">
                                    <i class="fas fa-globe"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- خط فاصل -->
            <div class="footer-separator"></div>
            
            <!-- حقوق الطبع -->
            <div class="footer-bottom py-4">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start">
                        <p class="text-white-50 mb-0 fw-medium">
                            &copy; 2025 <span class="text-white">محمد زاهده</span>. جميع الحقوق محفوظة.
                        </p>
                    </div>
                    <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                        <div class="d-flex justify-content-center justify-content-md-end align-items-center gap-2">
                            <small class="text-white-50">صُنع بـ</small>
                            <i class="fas fa-heart text-danger pulse-animation"></i>
                            <small class="text-white-50">في فلسطين</small>
                            <img src="https://flagcdn.com/16x12/ps.png" alt="فلسطين" class="ms-1">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Footer CSS -->
    <link rel="stylesheet" href="./../assets/css/footer.css">

    <!-- Footer JavaScript الخرافي -->
    <script>
        // تحريك العدادات
        function animateCounters() {
            const counters = document.querySelectorAll('.counter-number');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const counter = entry.target;
                        const target = parseInt(counter.getAttribute('data-target'));
                        const duration = 2000; // 2 ثانية
                        const step = target / (duration / 16); // 60fps
                        let current = 0;
                        
                        const timer = setInterval(() => {
                            current += step;
                            if (current >= target) {
                                counter.textContent = target.toLocaleString() + '+';
                                clearInterval(timer);
                            } else {
                                counter.textContent = Math.floor(current).toLocaleString();
                            }
                        }, 16);
                        
                        observer.unobserve(counter);
                    }
                });
            }, { threshold: 0.5 });
            
            counters.forEach(counter => observer.observe(counter));
        }
        
        // تأثيرات التمرير المتقدمة
        function initScrollEffects() {
            const footer = document.querySelector('.footer-luxury');
            const stars = document.querySelectorAll('.star');
            
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const rate = scrolled * -0.5;
                
                // تحريك النجوم بسرعات مختلفة
                stars.forEach((star, index) => {
                    const speed = (index + 1) * 0.1;
                    star.style.transform = `translateY(${rate * speed}px)`;
                });
            });
        }
        
        // تأثيرات الماوس المتقدمة
        function initMouseEffects() {
            const luxuryCards = document.querySelectorAll('.luxury-stat-card');
            
            luxuryCards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    const rotateX = (y - centerY) / 10;
                    const rotateY = (centerX - x) / 10;
                    
                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.05)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = '';
                });
            });
        }
        
        // تأثير الكتابة المتدرجة
        function typeWriterEffect() {
            const brandTitle = document.querySelector('.brand-title');
            if (brandTitle) {
                const text = brandTitle.textContent;
                brandTitle.textContent = '';
                brandTitle.style.opacity = '1';
                
                let i = 0;
                const timer = setInterval(() => {
                    brandTitle.textContent += text.charAt(i);
                    i++;
                    if (i >= text.length) {
                        clearInterval(timer);
                    }
                }, 100);
            }
        }
        
        // تفعيل جميع التأثيرات عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            animateCounters();
            initScrollEffects();
            initMouseEffects();
            
            // تأخير تأثير الكتابة
            setTimeout(typeWriterEffect, 500);
            
            // تأثير ظهور تدريجي للعناصر
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const fadeObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // تطبيق التأثير على العناصر
            document.querySelectorAll('.footer-section, .luxury-stat-card, .social-btn').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                fadeObserver.observe(el);
            });
        });
        
        // تأثير الجسيمات المتحركة
        function createFloatingParticles() {
            const footer = document.querySelector('.footer-luxury');
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'floating-particle';
                particle.style.cssText = `
                    position: absolute;
                    width: 2px;
                    height: 2px;
                    background: rgba(255,255,255,0.3);
                    border-radius: 50%;
                    pointer-events: none;
                    animation: float ${5 + Math.random() * 10}s linear infinite;
                    left: ${Math.random() * 100}%;
                    animation-delay: ${Math.random() * 5}s;
                `;
                footer.appendChild(particle);
            }
        }
        
        // إضافة CSS للجسيمات المتحركة
        const particleStyle = document.createElement('style');
        particleStyle.textContent = `
            @keyframes float {
                0% { 
                    transform: translateY(100vh) rotate(0deg);
                    opacity: 0;
                }
                10% { opacity: 1; }
                90% { opacity: 1; }
                100% { 
                    transform: translateY(-100px) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(particleStyle);
        
        // تفعيل الجسيمات
        setTimeout(createFloatingParticles, 1000);
    </script>

    <!-- Bootstrap JavaScript -->
    <script src="./../assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="./../assets/js/main.js"></script>
    
    <!-- رسائل التنبيه -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <script>
            showAlert('<?php echo $_SESSION['success_message']; ?>', 'success');
        </script>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <script>
            showAlert('<?php echo $_SESSION['error_message']; ?>', 'error');
        </script>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
</body>
</html>







