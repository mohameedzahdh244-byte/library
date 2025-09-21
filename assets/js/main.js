// مكتبة بلدية الخليل - JavaScript الرئيسي

// تهيئة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    initTooltips();
    initAnimations();
    initSearchFunctionality();
});

// إدارة الوضع الليلي
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    // تطبيق الثيم المحفوظ
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon(currentTheme);
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            
            // تأثير انتقالي
            document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        });
    }
}

function updateThemeIcon(theme) {
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// رسائل التنبيه
function showAlert(message, type = 'info', duration = 5000) {
    const alertContainer = getOrCreateAlertContainer();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <strong>${getAlertTitle(type)}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // إزالة تلقائية
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, duration);
}

function getAlertTitle(type) {
    const titles = {
        'success': 'نجح!',
        'danger': 'خطأ!',
        'warning': 'تحذير!',
        'info': 'معلومة:'
    };
    return titles[type] || 'إشعار:';
}

function getOrCreateAlertContainer() {
    let container = document.getElementById('alertContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'alertContainer';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    return container;
}

// تهيئة التلميحات
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// الرسوم المتحركة
function initAnimations() {
    // تحريك العناصر عند الظهور
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    });
    
    // مراقبة العناصر القابلة للتحريك
    document.querySelectorAll('.card, .stats-card, .dashboard-card').forEach(el => {
        observer.observe(el);
    });
}

// وظائف البحث
function initSearchFunctionality() {
    const searchInputs = document.querySelectorAll('.search-input');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetTable = this.getAttribute('data-target');
            
            if (targetTable) {
                filterTable(targetTable, searchTerm);
            }
        });
    });
}

function filterTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// تأكيد الحذف
function confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
    return confirm(message);
}

// تحميل البيانات بـ AJAX
function loadData(url, containerId, showLoading = true) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    if (showLoading) {
        container.innerHTML = '<div class="text-center p-4"><div class="loading"></div></div>';
    }
    
    fetch(url)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
            initTooltips(); // إعادة تهيئة التلميحات للمحتوى الجديد
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-danger">خطأ في تحميل البيانات</div>';
            console.error('Error:', error);
        });
}

// إرسال النماذج بـ AJAX
function submitForm(formId, successCallback = null) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // تعطيل الزر وإظهار التحميل
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="loading"></span> جاري الحفظ...';
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            if (successCallback) successCallback(data);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('حدث خطأ غير متوقع', 'danger');
        console.error('Error:', error);
    })
    .finally(() => {
        // إستعادة الزر
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
}

// تحديث الإحصائيات
function updateStats() {
    fetch('admin/ajax/get_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatCard('totalBooks', data.stats.total_books);
                updateStatCard('totalMembers', data.stats.total_members);
                updateStatCard('activeBorrows', data.stats.active_borrows);
                updateStatCard('overdueBorrows', data.stats.overdue_borrows);
            }
        })
        .catch(error => console.error('Error updating stats:', error));
}

function updateStatCard(cardId, value) {
    const element = document.getElementById(cardId);
    if (element) {
        element.textContent = value;
        element.classList.add('fade-in');
    }
}

// التحقق من صحة النماذج
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// تنسيق التواريخ
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// تنسيق الأرقام
function formatNumber(number) {
    return number.toLocaleString('ar-EG');
}

// حفظ البيانات في localStorage
function saveToLocalStorage(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify(data));
    } catch (error) {
        console.error('Error saving to localStorage:', error);
    }
}

function getFromLocalStorage(key) {
    try {
        const data = localStorage.getItem(key);
        return data ? JSON.parse(data) : null;
    } catch (error) {
        console.error('Error reading from localStorage:', error);
        return null;
    }
}

// طباعة الصفحة
function printPage() {
    window.print();
}

// تصدير البيانات
function exportData(url, filename = 'data') {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// مساعد للتحقق من نوع الجهاز
function isMobile() {
    return window.innerWidth <= 768;
}

// تحديث الوقت الحالي
function updateCurrentTime() {
    const timeElements = document.querySelectorAll('.current-time');
    const now = new Date();
    const timeString = now.toLocaleTimeString('ar-EG');
    
    timeElements.forEach(element => {
        element.textContent = timeString;
    });
}

// تحديث الوقت كل ثانية
setInterval(updateCurrentTime, 1000);

// معالجة الأخطاء العامة
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
});

// دوال مساعدة للتاريخ والوقت
const DateHelper = {
    format: function(date, format = 'YYYY-MM-DD') {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        
        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day);
    },
    
    isOverdue: function(dueDate) {
        return new Date(dueDate) < new Date();
    },
    
    daysBetween: function(date1, date2) {
        const oneDay = 24 * 60 * 60 * 1000;
        return Math.round(Math.abs((new Date(date1) - new Date(date2)) / oneDay));
    }
};

// تصدير الدوال للاستخدام العام
window.LibraryApp = {
    showAlert,
    confirmDelete,
    loadData,
    submitForm,
    updateStats,
    validateForm,
    formatDate,
    formatNumber,
    printPage,
    exportData,
    DateHelper
};