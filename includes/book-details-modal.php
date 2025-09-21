<!-- تضمين CSS المخصص للمودال -->
<link href="/assets/css/book-details-modal.css" rel="stylesheet">

<!-- مودال عرض تفاصيل الكتاب المحسّن -->
<div class="modal fade" id="bookDetailsModal" tabindex="-1" aria-labelledby="bookDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-gradient text-white border-0" style="background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);">
        <div class="d-flex align-items-center">
          
          <h4 class="modal-title mb-0 fw-bold" id="bookDetailsModalLabel">
            <i class="fas fa-info-circle me-2"></i>تفاصيل الكتاب
          </h4>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-4">
          <!-- صورة الغلاف -->
          <div class="col-lg-4">
            <div class="text-center">
              <div class="position-relative d-inline-block cover-holder w-100">
                <img id="bookCoverImage" src="" alt="غلاف الكتاب" class="rounded-3 shadow-lg border" style="height: 100%; width: 100%; transition: transform 0.3s ease; object-fit: cover;">
                <div id="noCoverPlaceholder" class="d-none">
                  <div class="bg-gradient-light rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="height: 100%; width: 100%; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                    <div class="text-center text-muted">
                      <div class="bg-primary bg-opacity-10 rounded-circle p-4 mb-3 d-inline-block">
                        <i class="fas fa-book fa-3x text-primary"></i>
                      </div>
                      <h6 class="text-muted mb-0">لا توجد صورة غلاف</h6>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- تفاصيل الكتاب -->
          <div class="col-lg-8">
            <div class="mb-4">
              <h3 id="bookTitle" class="text-dark mb-2 fw-bold"></h3>
            </div>
            
            <!-- معلومات أساسية -->
            <div class="card border-0 bg-light mb-4">
              <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="text-primary mb-0">
                  <i class="fas fa-info-circle me-2"></i>المعلومات الأساسية
                </h6>
              </div>
              <div class="card-body pt-3">
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                      <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="fas fa-user text-primary"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">المؤلف</small>
                        <span id="bookAuthor" class="fw-medium"></span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                      <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="fas fa-building text-success"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">الناشر</small>
                        <span id="bookPublisher" class="fw-medium"></span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                      <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="fas fa-calendar text-info"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">سنة النشر</small>
                        <span id="bookYear" class="fw-medium"></span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                      <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="fas fa-tag text-warning"></i>
                      </div>
                      <div>
                        <small class="text-muted d-block">رقم التصنيف</small>
                        <span id="bookClassification" class="fw-medium"></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- تفاصيل إضافية -->
            <div class="card border-0 bg-light">
              <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="text-secondary mb-0">
                  <i class="fas fa-list-ul me-2"></i>تفاصيل إضافية
                </h6>
              </div>
              <div class="card-body pt-3">
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="text-center p-3 bg-white rounded-3 shadow-sm">
                      <i class="fas fa-file-alt text-success mb-2 d-block"></i>
                      <small class="text-muted d-block">عدد الصفحات</small>
                      <span id="bookPages" class="fw-medium"></span>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-center p-3 bg-white rounded-3 shadow-sm">
                      <i class="fas fa-globe text-info mb-2 d-block"></i>
                      <small class="text-muted d-block">اللغة</small>
                      <span id="bookLanguage" class="fw-medium"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- ملخص الكتاب -->
        <div id="bookSummarySection" class="mt-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-gradient text-white border-0" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
              <h6 class="mb-0 fw-bold">
                <i class="fas fa-align-left me-2"></i>ملخص / مقدمة
              </h6>
            </div>
            <div class="card-body">
              <div class="d-flex align-items-center mb-2">
                <span id="summaryLabel" class="badge rounded-pill text-bg-primary">
                  <i class="fas fa-align-left me-1"></i>
                  ملخص
                </span>
              </div>
              <p id="bookSummary" class="mb-0 text-muted lh-lg"></p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer bg-light border-0 p-4">
        <!-- أزرار الإجراءات حسب نوع المستخدم -->
        <div id="memberActions" class="d-none">
          <button type="button" class="btn btn-warning btn-lg px-4 shadow-sm" id="reserveBookBtn">
            <i class="fas fa-calendar-plus me-2"></i>حجز الكتاب
          </button>
        </div>
        
        <div id="staffActions" class="d-none">
          <button type="button" class="btn btn-primary btn-lg px-4 me-2 shadow-sm" id="editBookBtn">
            تعديل البيانات
            <i class="fas fa-edit me-2"></i>
          </button>
          <button type="button" class="btn btn-info btn-lg px-4 shadow-sm" id="viewHistoryBtn">
            عرض السجل
            <i class="fas fa-history me-2"></i>
          </button>
        </div>
        
        <button type="button" class="btn btn-danger btn-lg px-4" data-bs-dismiss="modal">
          إغلاق
          <i class="fas fa-times me-2"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// وظائف المودال
function showBookDetails(serialnum) {
    // جلب بيانات الكتاب عبر AJAX
    fetch(`/api/book-details.php?serial=${encodeURIComponent(serialnum)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateBookModal(data.book);
                const modalEl = document.getElementById('bookDetailsModal');
                // حارس يمنع فقاعة الإغلاق إلى أي طبقة خارجية
                installModalCloseGuard(modalEl);
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            } else {
                alert('فشل في جلب بيانات الكتاب: ' + (data.message || 'خطأ غير معروف'));
            }
        })
        .catch(error => {
            console.error('خطأ في جلب بيانات الكتاب:', error);
            alert('حدث خطأ في الاتصال بالخادم');
        });
}

function populateBookModal(book) {
    // تعبئة البيانات الأساسية
    document.getElementById('bookTitle').textContent = book.book_title || 'غير محدد';
    document.getElementById('bookAuthor').textContent = book.authors || 'غير محدد';
    document.getElementById('bookPublisher').textContent = book.publisher || 'غير محدد';
    document.getElementById('bookYear').textContent = book.year || 'غير محدد';
    document.getElementById('bookClassification').textContent = book.classification_num || 'غير محدد';
    document.getElementById('bookPages').textContent = book.num_pages ? book.num_pages + ' صفحة' : 'غير محدد';
    document.getElementById('bookLanguage').textContent = book.book_language || 'غير محدد';
    
    // يمكن استخدام book.book_status داخلياً لاتخاذ قرارات (لا يتم عرضه في الواجهة)
    const s = (book.book_status || '').toString().trim().toLowerCase();
    const isAvailable = ['متاح','متوفر','available'].includes(book.book_status)
                        || ['متاح','متوفر'].includes((book.book_status||'').toString().trim())
                        || s === 'available';
    
    // صورة الغلاف
    const coverImg = document.getElementById('bookCoverImage');
    const placeholder = document.getElementById('noCoverPlaceholder');
    
    if (book.cover_image) {
        coverImg.src = '/' + book.cover_image;
        coverImg.onerror = function () {
            coverImg.classList.add('d-none');
            placeholder.classList.remove('d-none');
        };
        coverImg.classList.remove('d-none');
        placeholder.classList.add('d-none');
    } else {
        coverImg.classList.add('d-none');
        placeholder.classList.remove('d-none');
    }
    
    // ملخص الكتاب
    const summarySection = document.getElementById('bookSummarySection');
    const summaryText = document.getElementById('bookSummary');
    
    if (book.summary && book.summary.trim()) {
        const s = book.summary.trim();
        summaryText.textContent = s;
        // تحديد وسم (ملخص/مقدمة) بشكل تلقائي بسيط حسب طول النص
        const labelEl = document.getElementById('summaryLabel');
        if (labelEl) {
            const isIntro = s.length < 180; // نص قصير يعتبر مقدمة
            labelEl.textContent = (isIntro ? 'مقدمة' : 'ملخص');
            labelEl.insertAdjacentHTML('afterbegin', '<i class="fas fa-align-left me-1"></i>');
        }
        summarySection.classList.remove('d-none');
    } else {
        summarySection.classList.add('d-none');
    }
    
    // إظهار الأزرار المناسبة حسب نوع المستخدم
    showAppropriateActions(book);
}

// يمنع خروج المستخدم من صفحة البحث/اللوحة عند إغلاق مودال التفاصيل
function installModalCloseGuard(modalEl){
    if (!modalEl) return;
    if (modalEl._closeGuardInstalled) return;
    modalEl._closeGuardInstalled = true;
    // التقط النقرات على أزرار الإغلاق داخل المودال ومنع الفقاعات للأعلى
    modalEl.addEventListener('click', function(e){
        const btn = e.target.closest('[data-bs-dismiss="modal"]');
        if (btn) {
            // منع أي مستمعات عليا (داخل iframe أو خارجها) من التقاط حدث الإغلاق
            e.stopPropagation();
        }
    }, true); // capture phase
}

// تم إزالة العرض البصري للحالة من المودال، لكن ما زلنا نستخدم الحالة لاتخاذ إجراءات للمستخدم

function showAppropriateActions(book) {
    const memberActions = document.getElementById('memberActions');
    const staffActions = document.getElementById('staffActions');
    
    // إخفاء جميع الأزرار أولاً
    memberActions.classList.add('d-none');
    staffActions.classList.add('d-none');
    
    // تحديد نوع المستخدم من الصفحة الحالية أو متغير عام
    const userType = window.currentUserType || 'guest';
    
    if (userType === 'member') {
        memberActions.classList.remove('d-none');
        
        // تفعيل/تعطيل أزرار العضو حسب حالة الكتاب
        const borrowBtn = document.getElementById('borrowBookBtn');
        const reserveBtn = document.getElementById('reserveBookBtn');
        
        // تفعيل عند (متاح) أو (متوفر)
        const isAvailable = (book.book_status === 'متاح' || book.book_status === 'متوفر' || (book.book_status||'').toLowerCase() === 'available');
        if (borrowBtn) borrowBtn.disabled = !isAvailable;
        if (reserveBtn) reserveBtn.disabled = !!isAvailable;
        
        // ربط الأحداث
        if (borrowBtn) borrowBtn.onclick = () => borrowBook(book.serialnum_book);
        if (reserveBtn) reserveBtn.onclick = () => reserveBook(book.serialnum_book);
        
    } else if (userType === 'staff' || userType === 'admin') {
        staffActions.classList.remove('d-none');
        
        // ربط أحداث أزرار الموظف
        document.getElementById('editBookBtn').onclick = () => editBook(book.serialnum_book);
        document.getElementById('viewHistoryBtn').onclick = () => viewBookHistory(book.serialnum_book);
    }
}

// وظائف الإجراءات
function borrowBook(serialnum) {
    // تنفيذ عملية الاستعارة
    console.log('استعارة الكتاب:', serialnum);
    // يمكن إضافة AJAX call هنا
}

function reserveBook(serialnum) {
    // تنفيذ عملية الحجز عبر AJAX بنفس آلية صفحة البحث
    try {
        const btn = document.getElementById('reserveBookBtn');
        if (!serialnum || !btn || btn.disabled) return;

        const prevHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جارٍ الحجز...';

        const fd = new FormData();
        fd.set('reserve_book', '1');
        fd.set('book_serial', serialnum);
        fd.set('ajax', '1');

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'تمت العملية', !!data.success);
                } else {
                    alert(data.message || 'تمت العملية');
                }
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bookDetailsModal'));
                    if (modal) modal.hide();
                }
            })
            .catch(() => {
                const msg = 'تعذّر تنفيذ العملية. حاول مرة أخرى.';
                if (typeof showToast === 'function') {
                    showToast(msg, false);
                } else {
                    alert(msg);
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = prevHtml;
            });
    } catch (e) {
        console.error('Reservation error:', e);
    }
}

function editBook(serialnum) {
    // فتح نموذج التعديل
    console.log('تعديل الكتاب:', serialnum);
    // يمكن إضافة رابط لصفحة التعديل
}

function viewBookHistory(serialnum) {
    // عرض سجل الكتاب
    console.log('عرض سجل الكتاب:', serialnum);
    // يمكن إضافة رابط لصفحة السجل
}

// إنشاء/إرجاع مودال استضافة لفتح صفحات داخل iframe (للتعديل)
function ensureEditModalHost() {
    var id = 'editBookHostModal';
    var existing = document.getElementById(id);
    if (existing) return existing;
    var html = `
    <div class="modal fade" id="${id}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="fas fa-pen me-2"></i>تعديل بيانات الكتاب</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0" style="height: 75vh;">
            <iframe id="editBookFrame" src="" style="border:0; width:100%; height:100%"></iframe>
          </div>
        </div>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    return document.getElementById(id);
}

// فتح نموذج تعديل الكتاب داخل مودال iframe
function editBook(serialnum) {
    try {
        var host = ensureEditModalHost();
        var frame = host.querySelector('#editBookFrame');
        frame.src = '/admin/books/edit_modal.php?embed=1&serial=' + encodeURIComponent(serialnum);
        var modal = new bootstrap.Modal(host);
        host.addEventListener('hidden.bs.modal', function onHidden(){
          host.removeEventListener('hidden.bs.modal', onHidden);
          try { frame.src = 'about:blank'; } catch(e) {}
        });
        modal.show();
    } catch (e) {
        console.error('تعذر فتح نموذج التعديل:', e);
        // في حال وجود مشكلة بالـ modal، افتح صفحة التعديل مباشرة
        window.location.href = '/admin/books/edit_modal.php?serial=' + encodeURIComponent(serialnum);
    }
}
</script>
