document.addEventListener('DOMContentLoaded', function() {
    // وظيفة إظهار/إخفاء كلمة المرور
    const togglePasswordIcon = document.querySelector('.toggle-password-icon');
    const passwordInput = document.getElementById('password');

    if (togglePasswordIcon && passwordInput) {
        togglePasswordIcon.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    }

    // تحسين تجربة المستخدم - التركيز على الحقل التالي عند الضغط على Enter
    const usernameInput = document.getElementById('username');
    if (usernameInput && passwordInput) {
        usernameInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                passwordInput.focus();
            }
        });
    }

    // تحسين شكل النموذج عند التفاعل - الحقول الحديثة
    const modernInputs = document.querySelectorAll('.modern-form-control');
    modernInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });

    // تأثير تفاعلي على الأيقونات
    const inputIcons = document.querySelectorAll('.input-icon');
    inputIcons.forEach(icon => {
        const parentGroup = icon.parentElement;
        const input = parentGroup.querySelector('.modern-form-control');
        
        if (input) {
            input.addEventListener('focus', function() {
                icon.style.transform = 'translateY(-50%) scale(1.1)';
                icon.style.color = 'var(--primary-color)';
            });
            
            input.addEventListener('blur', function() {
                icon.style.transform = 'translateY(-50%) scale(1)';
                icon.style.color = '#64748b';
            });
        }
    });

    // تأثير لطيف عند الضغط على الزر
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    }

    function createAlert(message, type = 'info', duration = 3000) {
    // دائماً استخدم alert-danger (أحمر)
        type = 'error';
            // إزالة أي alert موجود مسبقاً
        const existingAlert = document.querySelector('.login-alert');
        if (existingAlert) existingAlert.remove();

    // تحديد نوع Bootstrap Alert
        let alertType = 'alert-danger';
        let icon = '<i class="fas fa-exclamation-circle me-2 text-danger"></i>';

    // إنشاء عنصر Alert جديد
        const alert = document.createElement('div');
        alert.className = `login-alert alert ${alertType} d-flex align-items-center fade show`;
        alert.setAttribute('role', 'alert');
        alert.innerHTML = `
            ${icon}
            <div class="flex-fill">${message}</div>
            <button type="button" class="btn-close ms-2" aria-label="إغلاق"></button>
        `;

       

    // إضافة Alert إلى placeholder
        const placeholder = document.getElementById('alert-login-placeholder');
        if (placeholder) {
            placeholder.innerHTML = '';
            placeholder.appendChild(alert);
            alert.scrollIntoView({behavior: 'smooth', block: 'center'});
        } else {
            document.body.appendChild(alert);
            alert.scrollIntoView({behavior: 'smooth', block: 'center'});
        }

        // إخفاء تلقائي بعد المدة المحددة
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 200);
            }
        }, duration);
        return alert;
    }

    // ===== معالجة نموذج تسجيل الدخول بـ AJAX =====
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault(); // منع الإرسال التقليدي
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
           
            // --- إرسال البيانات عبر AJAX ---
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('خطأ في الشبكة');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.redirect) {
                    // نجاح: تحويل فوري بدون رسالة
                    window.location.href = data.redirect;
                    return;
                }
                // في حالة الفشل فقط، عرض رسالة الخطأ
                createAlert(data.message, data.type, 3000);
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            })
            .catch(error => {
                console.error('خطأ:', error);
                createAlert('حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.', 'error');
                
                // إعادة تفعيل الزر
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            });
        });
    }
});





