/* Member Dashboard JavaScript - Based on Admin Dashboard */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarClose = document.getElementById('sidebarClose');
    
    // Toggle sidebar
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            toggleSidebar();
        });
    }
    
    // Close sidebar on mobile
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Close sidebar with ESC key on mobile
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 991.98) {
            closeSidebar();
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        handleResponsiveSidebar();
    });
    
    // Initial responsive setup
    handleResponsiveSidebar();
});

// Open panel in offcanvas (like admin dashboard)
window.openPanel = function(url, title, headerClass) {
    const off = document.getElementById('panelOffcanvas');
    const frame = document.getElementById('panelFrame');
    const titleEl = document.getElementById('panelTitle');
    if (!off || !frame) return;

    // إخفاء الشريط الجانبي على الموبايل
    if (window.innerWidth <= 767.98) {
        closeSidebar();
        // إخفاء العناصر الأساسية
        document.body.classList.add('offcanvas-open');
    }

    // تعيين عرض الـ offcanvas
    const vw = window.innerWidth || document.documentElement.clientWidth;
    if (window.innerWidth <= 767.98) {
        // ملء الشاشة على الموبايل
        off.style.setProperty('--bs-offcanvas-width', '100vw');
    } else {
        // تكيف مع الشريط الجانبي على الكمبيوتر
        const sidebarWidth = (document.getElementById('sidebar')?.offsetWidth) || 0;
        const width = Math.max(360, vw - sidebarWidth);
        off.style.setProperty('--bs-offcanvas-width', width + 'px');
    }

    // إضافة معاملات iframe للصفحة
    const separator = url.includes('?') ? '&' : '?';
    frame.src = url ? url + separator + 'iframe=1&ajax=1' : '';
    frame.onload = function () {
        try { window.installIframeCloseHandlers(frame); } catch (e) {}
    };
    if (titleEl) titleEl.textContent = title || 'لوحة';
    const header = off.querySelector('.offcanvas-header');
    if (header && headerClass) {
        header.className = `offcanvas-header ${headerClass}`;
    }

    // إعدادات مختلفة للموبايل والكمبيوتر
    const offcanvasOptions = window.innerWidth <= 767.98 
        ? { backdrop: false, keyboard: true, scroll: false }
        : { backdrop: false, keyboard: false, scroll: true };
    
    const bsOffcanvas = new bootstrap.Offcanvas(off, offcanvasOptions);
    bsOffcanvas.show();
    
    // إضافة مستمع لإغلاق الـ offcanvas
    off.addEventListener('hidden.bs.offcanvas', function() {
        document.body.classList.remove('offcanvas-open');
        frame.src = '';
    }, { once: true });
};

// Install iframe close handlers
window.installIframeCloseHandlers = function(iframe) {
    if (!iframe || !iframe.contentWindow) return;
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (!iframeDoc) return;

        // Add close handlers to buttons and links
        const closeSelectors = [
            'button[data-bs-dismiss="offcanvas"]',
            '.btn-close',
            'a[href="#close"]',
            '.close-panel'
        ];

        closeSelectors.forEach(selector => {
            const elements = iframeDoc.querySelectorAll(selector);
            elements.forEach(el => {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('panelOffcanvas'));
                    if (offcanvas) offcanvas.hide();
                });
            });
        });
    } catch (e) {
        // Cross-origin restrictions - ignore
    }
};

// Sidebar control functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth <= 991.98) {
        // Mobile behavior
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    } else {
        // Desktop behavior
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            sidebar.classList.add('collapsed');
        } else {
            sidebar.classList.remove('collapsed');
            sidebar.classList.add('show');
        }
    }
}

function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.remove('collapsed');
    sidebar.classList.add('show');
    
    if (window.innerWidth <= 991.98) {
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth <= 991.98) {
        sidebar.classList.remove('show');
        sidebar.classList.add('collapsed');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Responsive sidebar for mobile
function handleResponsiveSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth <= 991.98) {
        // Mobile: hide sidebar by default
        sidebar.classList.add('mobile');
        if (!sidebar.classList.contains('show')) {
            sidebar.classList.add('collapsed');
        }
    } else {
        // Desktop: show sidebar by default
        sidebar.classList.remove('mobile');
        sidebar.classList.remove('collapsed');
        sidebar.classList.add('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Auto-close sidebar on mobile when clicking nav links
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 991.98 && e.target.closest('.nav-link')) {
        // Small delay to allow navigation to complete
        setTimeout(closeSidebar, 100);
    }
});
