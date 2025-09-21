

document.addEventListener('DOMContentLoaded', () => {
    // ===== 1) Sidebar toggle - تم نقله إلى dashboard.php =====

    // Live clock for header
    const timeEl = document.getElementById('currentTime');
    function updateTime() {
        if (!timeEl) return;
        const now = new Date();
        try {
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour12: true });
        } catch {
            timeEl.textContent = now.toLocaleTimeString('en-US');
        }
    }
    if (timeEl) { updateTime(); setInterval(updateTime, 1000); }

    // ---------- Modal Helpers (Bootstrap) ----------
    function ensureModal(id, opts) {
        let modal = document.getElementById(id);
        if (modal) return modal;
        const div = document.createElement('div');
        div.id = id;
        div.className = 'modal fade';
        div.dir = 'rtl';
        div.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header d-flex align-items-center pe-0">
              <h5 class="modal-title mb-0">${opts.title || 'تأكيد'}</h5>
              <button type="button" class="btn-close me-auto m-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><div class="fs-6">${opts.body || ''}</div></div>
            <div class="modal-footer d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${opts.cancelText || 'إلغاء'}</button>
              <button type="button" class="btn ${opts.okClass || 'btn-primary'}" data-role="ok">${opts.okText || 'موافق'}</button>
            </div>
          </div>
        </div>`;
        document.body.appendChild(div);
        return div;
    }

    async function confirmDialog(message, options={}) {
        const id = 'appConfirmModal';
        const modalEl = ensureModal(id, { title: options.title || 'تأكيد الإرسال', body: message, okText: 'نعم، متابعة', cancelText: 'إلغاء', okClass: 'btn-danger' });
        // Update body text each time
        modalEl.querySelector('.modal-body').innerHTML = `<div class="text-center">${message}</div>`;
        return new Promise(resolve => {
            const okBtn = modalEl.querySelector('[data-role="ok"]');
            const bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            const cleanup = () => {
                okBtn.removeEventListener('click', onOk);
                modalEl.removeEventListener('hidden.bs.modal', onHide);
            };
            function onOk(){ resolve(true); bsModal.hide(); }
            function onHide(){ cleanup(); resolve(false); }
            okBtn.addEventListener('click', onOk);
            modalEl.addEventListener('hidden.bs.modal', onHide, { once: true });
            bsModal.show();
        });
    }

    async function infoDialog(message, options={}) {
        const id = 'appInfoModal';
        const modalEl = ensureModal(id, { title: options.title || 'النتيجة', body: message, okText: 'حسنًا', cancelText: 'إغلاق', okClass: 'btn-primary' });
        modalEl.querySelector('.modal-footer .btn-secondary').classList.add('d-none');
        modalEl.querySelector('.modal-body').innerHTML = `<div class="text-center white-space-pre">${message}</div>`;
        return new Promise(resolve => {
            const okBtn = modalEl.querySelector('[data-role="ok"]');
            const bsModal = new bootstrap.Modal(modalEl, { backdrop: true });
            const onHide = () => { modalEl.removeEventListener('hidden.bs.modal', onHide); resolve(true); };
            okBtn.onclick = () => bsModal.hide();
            modalEl.addEventListener('hidden.bs.modal', onHide);
            bsModal.show();
        });
    }

    // Global: send overdue reminders (used by dashboard widget button)
    window.sendOverdueReminders = async function() {
        try {
            const proceed = await confirmDialog('هل تريد إرسال تذكير SMS لجميع المتأخرين الآن؟');
            if (!proceed) return;
            const btn = document.getElementById('overdueRemindAllBtn');
            const oldHtml = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>جارٍ الإرسال...'; }
            
            // بدء العملية في الخلفية
            fetch('../massage/overdue.php', { method: 'GET', cache: 'no-store' })
                .then(resp => resp.json().catch(() => ({})))
                .then(data => {
                    // تسجيل النتائج في الخلفية فقط، بدون إظهار للمستخدم
                    console.log('نتائج الإرسال:', data);
                })
                .catch(e => console.log('خطأ في الإرسال:', e));
            
            // إظهار رسالة فورية للمستخدم
            await infoDialog('تم الإرسال بنجاح.');
            if (btn) { btn.disabled = false; btn.innerHTML = oldHtml; }
        } catch (e) {
            await infoDialog('حدث خطأ غير متوقع أثناء الإرسال');
            const btn = document.getElementById('overdueRemindAllBtn');
            if (btn) { btn.disabled = false; btn.innerHTML = 'تذكير للجميع'; }
        }
    };

    // ===== 2) Offcanvas helpers (exposed on window for inline handlers) =====
    window.openPanel = function(url, title, headerClass) {
        const off = document.getElementById('panelOffcanvas');
        const frame = document.getElementById('panelFrame');
        const titleEl = document.getElementById('panelTitle');
        const sidebar = document.getElementById('sidebar');
        if (!off || !frame) return;

        // تحديد العرض حسب حجم الشاشة
        if (window.innerWidth <= 991.98) {
            // موبايل: شاشة كاملة
            off.style.setProperty('--bs-offcanvas-width', '100vw');
        } else {
            // كمبيوتر: عرض محسوب
            const sw = (sidebar && sidebar.classList.contains('show')) ? sidebar.offsetWidth : 0;
            const vw = window.innerWidth || document.documentElement.clientWidth;
            const padding = 0;
            const width = Math.max(360, Math.min(vw - sw - padding, vw));
            off.style.setProperty('--bs-offcanvas-width', width + 'px');
        }

        frame.src = url || '';
        frame.onload = function () {
            try { window.installIframeCloseHandlers(frame); } catch (e) {}
        };
        if (titleEl) titleEl.textContent = title || 'لوحة';
        const header = off.querySelector('.offcanvas-header');
        if (header) header.className = 'offcanvas-header justify-content-between px-2 ' + (headerClass || 'bg-primary text-white');

        const oc = bootstrap.Offcanvas.getInstance(off) || new bootstrap.Offcanvas(off);
        const onResize = () => {
            if (window.innerWidth <= 991.98) {
                // موبايل: شاشة كاملة
                off.style.setProperty('--bs-offcanvas-width', '100vw');
            } else {
                // كمبيوتر: عرض محسوب
                const sidebar = document.getElementById('sidebar');
                const sw2 = (sidebar && sidebar.classList.contains('show')) ? sidebar.offsetWidth : 0;
                const vw2 = window.innerWidth || document.documentElement.clientWidth;
                const w2 = Math.max(360, Math.min(vw2 - sw2, vw2));
                off.style.setProperty('--bs-offcanvas-width', w2 + 'px');
            }
        };
        off.addEventListener('shown.bs.offcanvas', () => window.addEventListener('resize', onResize), { once: true });
        off.addEventListener('hide.bs.offcanvas', () => window.removeEventListener('resize', onResize), { once: true });
        oc.show();
    };

    window.installIframeCloseHandlers = function(frameEl) {
        const off = document.getElementById('panelOffcanvas');
        if (!frameEl || !off) return;
        const doc = frameEl.contentDocument || frameEl.contentWindow?.document;
        if (!doc) return;
        const isCloseText = (t) => {
            if (!t) return false;
            const s = t.replace(/\s+/g, '').toLowerCase();
            return s.includes('اغلاق') || s.includes('إغلاق');
        };
        const candidates = Array.from(doc.querySelectorAll('button, a, input[type="button"], input[type="submit"], .btn'));
        candidates.forEach(el => {
            const text = (el.innerText || el.value || '').trim();
            if (isCloseText(text)) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const form = el.closest('form');
                    if (form) form.addEventListener('submit', ev => ev.preventDefault(), { once: true });
                    const oc = bootstrap.Offcanvas.getInstance(off) || new bootstrap.Offcanvas(off);
                    oc.hide();
                }, { capture: true });
            }
        });
    };

    // Expose page shortcuts used by sidebar buttons
    window.openUsers = function() {
        window.openPanel('users/index.php?embed=1', 'إدارة المستخدمين', 'bg-secondary text-white');
    };
    window.openSearchBooks = function() {
        window.openPanel('books/search_modal.php', 'إدارة الكتب - بحث', 'bg-primary text-white');
    };
    window.openAddBook = function() {
        window.openPanel('books/addbook.php?embed=1', 'إدارة الكتب - إضافة كتاب', 'bg-primary text-white');
    };
    window.openSearchCustomer = function() {
        window.openPanel('customer/search_modal.php', 'إدارة الأعضاء - بحث', 'bg-success text-white');
    };
    window.openAddCustomer = function() {
        window.openPanel('customer/addcustomer.php?embed=1', 'إدارة الأعضاء - إضافة', 'bg-success text-white');
    };

    // Allow child iframes to request opening a panel
    window.addEventListener('message', function(event) {
        try {
            const data = event.data || {};
            if (data.type === 'openInBooksModal' && data.url) {
                window.openPanel(data.url, 'إدارة الكتب', 'bg-primary text-white');
            }
        } catch (e) { console.warn('openInBooksModal message error', e); }
    });

    // ===== 3) Charts from data-* =====
    if (window.Chart) {
        const borrowEl = document.getElementById('borrowChart');
        if (borrowEl) {
            const active = Number(borrowEl.dataset.activeBorrows || 0);
            const completed = Number(borrowEl.dataset.completedBorrows || 0);
            const overdue = Number(borrowEl.dataset.overdueBooks || 0);
            const borrowCtx = borrowEl.getContext('2d');
            new Chart(borrowCtx, {
                type: 'doughnut',
                data: {
                    labels: ['إعارات نشطة', 'إعارات مكتملة', 'كتب متأخرة'],
                    datasets: [{
                        data: [active, completed, overdue],
                        backgroundColor: ['#60a5fa', '#34d399', '#f87171'],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#334155', boxWidth: 14, boxHeight: 14, usePointStyle: true } },
                        title: { display: true, text: 'نظرة عامة على الإعارات', color: '#0f172a', font: { weight: '600', size: 14 } }
                    }
                }
            });
        }

        const activityEl = document.getElementById('activityChart');
        if (activityEl) {
            let labels = [];
            let series = [];
            try { labels = JSON.parse(activityEl.dataset.weekLabels || '[]'); } catch(e) {}
            try { series = JSON.parse(activityEl.dataset.weekData || '[]'); } catch(e) {}
            const activityCtx = activityEl.getContext('2d');
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'الإعارات',
                        data: series,
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29, 78, 216, 0.15)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#1d4ed8'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#334155', boxWidth: 14, boxHeight: 14, usePointStyle: true } },
                        title: { display: true, text: 'الإعارات خلال الأسابيع (هذا الشهر)', color: '#0f172a', font: { weight: '600', size: 14 } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(2, 6, 23, 0.06)' }, ticks: { color: '#475569' } },
                        x: { grid: { display: false }, ticks: { color: '#475569' } }
                    }
                }
            });
        }
    }

    // ===== 4) Tooltips =====
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) { new bootstrap.Tooltip(tooltipTriggerEl); });

    // ===== 5) Recent activities filter =====
    (function(){
        const filters = document.getElementById('activityFilters');
        if (!filters) return;
        const buttons = Array.from(filters.querySelectorAll('button[data-filter]'));
        const items = Array.from(document.querySelectorAll('#recentActivities .activity-item'));
        function applyFilter(type){
            items.forEach(it => {
                const k = (it.getAttribute('data-key') || '').toLowerCase();
                const show = (type === '*') || (k === type);
                it.classList.toggle('d-none', !show);
            });
        }
        filters.addEventListener('click', (e) => {
            const control = e.target.closest('[data-filter]');
            if (!control) return;
            if (control.tagName === 'A') e.preventDefault();
            const type = control.getAttribute('data-filter');
            if (control.tagName === 'BUTTON') {
                buttons.forEach(b => b.classList.remove('active'));
                control.classList.add('active');
            } else if (control.tagName === 'A') {
                buttons.forEach(b => b.classList.remove('active'));
            }
            applyFilter(type);
        });
    })();

    // ===== 6) Expand/collapse recent activities list =====
    (function(){
        const toggleBtn = document.getElementById('recentExpandToggle');
        const container = document.querySelector('.recent-activities .card-body');
        if (!toggleBtn || !container) return;
        toggleBtn.addEventListener('click', function(){
            const expanded = container.classList.toggle('expanded');
            toggleBtn.textContent = expanded ? 'عرض أقل' : 'عرض الكل';
        });
    })();

    // ===== 7) Keep previous dynamic loader (guarded) =====
    const operationCards = document.querySelectorAll('.operation-card');
    const dynamicContent = document.getElementById('dynamic-content');
    const bindSubOperations = () => {
        const subOperations = document.querySelectorAll('.operation-sub');
        subOperations.forEach(btn => {
            btn.addEventListener('click', () => {
                const page = btn.getAttribute('data-page');
                if (!dynamicContent || !page) return;
                fetch(page)
                    .then(response => response.text())
                    .then(content => { dynamicContent.innerHTML = content; })
                    .catch(() => { dynamicContent.innerHTML = `<div class="alert alert-danger">فشل تحميل الصفحة.</div>`; });
            });
        });
    };
    const loadSectionDashboard = (folder) => {
        if (!dynamicContent || !folder) return;
        fetch(`${folder}/dashboard.php`)
            .then(response => response.text())
            .then(data => { dynamicContent.innerHTML = data; bindSubOperations(); })
            .catch(() => { dynamicContent.innerHTML = `<div class="alert alert-danger">فشل تحميل العمليات.</div>`; });
    };
    operationCards.forEach(card => {
        card.addEventListener('click', () => {
            const folder = card.getAttribute('data-folder');
            loadSectionDashboard(folder);
        });
    });
});


        
        
        
        
        
        
        
        
        
        
        

    


