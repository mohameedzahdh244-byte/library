// تم تعطيل إعادة التحميل التلقائي للحفاظ على النتائج وعدم فقدان الحالة
// ملاحظة: تم إلغاء البحث اللحظي والاكتفاء بالبحث عند الضغط على زر "بحث"

// بحث لحظي فوري: جلب النتائج وتحديث جزء النتائج بدون إعادة تحميل
(function(){
    const form = document.getElementById('searchForm');
    const input = document.getElementById('search_query');
    const typeSel = document.getElementById('search_type');
    const container = document.getElementById('liveResults');
    if (!form || !input || !container) return;

    let controller = null;
    const loader = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">جاري البحث...</div></div>';

    async function doSearch(){
        const q = input.value.trim();
        if (q.length < 2) {
            container.innerHTML = '';
            if (typeof showToast === 'function') showToast('يرجى إدخال كلمة بحث من حرفين على الأقل', false);
            return;
        }
// اجعل الدالة متاحة على النطاق العام صراحة
window.bindBookCardClicks = bindBookCardClicks;

        // ألغِ أي طلب سابق قيد التنفيذ لتجنب التقطيع
        if (controller) controller.abort();
        controller = new AbortController();

        const fd = new FormData(form);
        fd.set('search_query', q);

        container.innerHTML = loader;
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: fd, signal: controller.signal });
            const html = await res.text();
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            const frag = tmp.querySelector('#liveResults');
            if (frag) {
                container.innerHTML = frag.innerHTML;
                // إعادة تهيئة مستمعات الحجز بعد تحديث النتائج
                bindReservationHandlers(container);
                // ربط نقر بطاقات الكتب لفتح المودال
                bindBookCardClicks(container);
            } else {
                container.innerHTML = '';
            }
        } catch (e) {
            if (e.name === 'AbortError') return; // تم إلغاء الطلب لصالح طلب أحدث
            container.innerHTML = '<div class="alert alert-danger">تعذّر جلب النتائج حالياً.</div>';
        }
    }

    // البحث يتم فقط عند الضغط على زر "بحث"
    form.addEventListener('submit', function(e){
        e.preventDefault();
        doSearch();
        return false;
    });
})();

// دالة مساعدة: عرض توست أعلى يمين
function showToast(message, isSuccess) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center ' + (isSuccess ? 'text-bg-success' : 'text-bg-danger') + ' border-0 mb-2';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.setAttribute('data-bs-delay', '3000');
    toastEl.setAttribute('data-bs-autohide', 'true');
    toastEl.innerHTML = '<div class="d-flex">\
        <div class="toast-body">\
            <i class="fas ' + (isSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle') + ' me-2"></i>' + (message || '') + '\
        </div>\
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="إغلاق"></button>\
    </div>';
    container.appendChild(toastEl);
    try {
        const t = new bootstrap.Toast(toastEl);
        toastEl.addEventListener('hidden.bs.toast', function(){ toastEl.remove(); });
        t.show();
    } catch(e) {
        // fallback
        alert(message);
        toastEl.remove();
    }
}

// تفويض نقر لفتح مودال تفاصيل الكتاب عند الضغط على بطاقة الكتاب (نطاق عام)
function bindBookCardClicks(root) {
    // نربط مرة واحدة فقط عبر المستند بالكامل
    if (bindBookCardClicks._bound) return;
    document.addEventListener('click', function(e){
        const card = e.target.closest('.book-card');
        if (!card) return;
        // تجاهل النقرات على عناصر تفاعلية داخل البطاقة (أزرار/روابط/حقول)
        if (e.target.closest('form, button, a, input, select, textarea, label')) return;
        const serial = card.getAttribute('data-book-serial');
        if (serial && typeof showBookDetails === 'function') {
            e.preventDefault();
            showBookDetails(serial);
        }
    });
    bindBookCardClicks._bound = true;
}

// وظيفة لإرفاق مستمعات الحجز بالـ AJAX (يمكن استدعاؤها بعد تحديث النتائج)
function bindReservationHandlers(root) {
    const scope = root || document;
    scope.querySelectorAll('.book-card form').forEach(function(form) {
        if (form.dataset.bound === '1') return; // منع الربط المكرر
        if (!form.querySelector('input[name="reserve_book"][value="1"]')) return;
        form.dataset.bound = '1';
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            if (btn.disabled) return false; // منع المعالجة إذا كان الزر معطلاً
            // ملاحظة: أزلنا التوست المسبق عند "معار ومحجوز" لتجنّب ازدواج الرسائل

            // جهّز البيانات
            const fd = new FormData(form);
            fd.set('ajax', '1');

            // تعطيل الزر أثناء الطلب
            const prevHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جارٍ الحجز...';

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                showToast(data.message || 'تمت العملية', !!data.success);
            } catch (err) {
                showToast('تعذّر تنفيذ العملية. حاول مرة أخرى.', false);
            } finally {
                btn.disabled = false;
                btn.innerHTML = prevHtml;
            }
        });
    });
    // تفعيل التلميحات داخل النطاق الحالي (للنتائج المحمّلة حديثاً)
    scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
        if (!el.dataset.tooltipInit) {
            new bootstrap.Tooltip(el);
            el.dataset.tooltipInit = '1';
        }
    });
}

// ربط أولي عند تحميل الصفحة
bindReservationHandlers(document);
// ربط أولي لنقر بطاقات الكتب عند التحميل الأول (مع حارس أمان)
if (typeof bindBookCardClicks === 'function') {
    bindBookCardClicks(document);
}
// تفعيل أولي للتلميحات عند التحميل
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
    if (!el.dataset.tooltipInit) {
        new bootstrap.Tooltip(el);
        el.dataset.tooltipInit = '1';
    }
});
