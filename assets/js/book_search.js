
$(document).ready(function () {
  // إظهار/إخفاء زر المسح بناءً على وجود نص
  const searchInput = $('#searchInput');
  if (searchInput.length) {
    searchInput.on('input', function () {
      const v = $(this).val().trim();
      $('#clearSearch').toggleClass('show', v.length > 0);
    });
  }

  // زر مسح البحث
  $('#clearSearch').on('click', function () {
    if (searchInput.length) {
      searchInput.val('').trigger('input').focus();
    }
    clearResults();
  });

  // البحث يتم فقط عند الضغط على زر "بحث" (submit)
  $('#staffSearchForm').on('submit', function (e) {
    e.preventDefault();
    const query = searchInput.length ? searchInput.val().trim() : '';
    if (query.length >= 2) {
      performSearch(query);
    } else {
      clearResults();
    }
  });
});

function performSearch(query) {
  showLoading();

  $.ajax({
    url: 'search_api_simple.php',
    method: 'POST',
    data: { query: query },
    dataType: 'json',
    success: function (response) {
      hideLoading();
      if (response.success && response.books.length > 0) {
        displayResults(response.books);
      } else {
        showNoResults();
      }
    },
    error: function () {
      hideLoading();
      showError('حدث خطأ أثناء البحث');
    }
  });
}

function displayResults(books) {
  let html = '';
  books.forEach((book, index) => {
    html += createBookItem(book, index);
  });
  // Inject as a responsive grid
  $('#resultsContainer').html(html);
  $('#resultsCount').text(`${books.length} نتيجة`);
  $('#resultsHeader').show().addClass('fade-in');
  $('#noResults').hide();

  // ربط أزرار التعديل لفتح نموذج تعديل الكتاب داخل مودال
  bindEditButtons();
  // ربط النقر على البطاقة لفتح التفاصيل
  bindCardClicks();
}

function createBookItem(book, index) {
  const status = (book.availability_status || 'متوفر');
  let badgeClass = 'status-available';
  if (status === 'محجوز') badgeClass = 'status-reserved';
  else if (status === 'معار') badgeClass = 'status-borrowed';
  else if (status === 'متأخر') badgeClass = 'status-overdue';
  const expected = book.boro_exp_ret_date ? `<span class="text-muted ms-2"><i class="fas fa-clock me-2" title="تاريخ متوقع للإرجاع"></i>${book.boro_exp_ret_date.substring(0, 10)}</span>` : '';
  return `
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card h-100 shadow-sm border-0 slide-up book-item" data-serial="${book.serialnum_book}" style="animation-delay: ${index * 0.05}s">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-start justify-content-between mb-2">
            <div class="flex-grow-1">
              <div class="book-title"><i class="fas fa-book-open title-icon me-1"></i>${book.book_title}</div>
              <div class="text-muted small"><i class="fas fa-barcode me-2 text-primary" title="الرقم التسلسلي"></i>${book.serialnum_book} ${expected}</div>
            </div>
            <span class="status-badge ${badgeClass}">${status}</span>
          </div>
          <div class="text-muted small d-flex flex-wrap gap-3 mt-1">
            <span><i class="fas fa-user me-2 text-primary" title="المؤلف"></i>${book.author || 'غير محدد'}</span>
            <span><i class="fas fa-bookmark me-2 text-success" title="التصنيف"></i>${book.classification_num || 'غير محدد'}</span>
          </div>
          <div class="book-footer d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-sm btn-primary btn-edit edit-book-btn" data-serial="${book.serialnum_book}">
              تعديل
              <i class="fas fa-pen"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
}


function showLoading() {
  $('#loadingContainer').show();
  $('#resultsHeader').hide();
  $('#noResults').hide();
}

function hideLoading() {
  $('#loadingContainer').hide();
}

function showNoResults() {
  $('#noResults').show();
  $('#resultsHeader').hide();
}

function clearResults() {
  $('#resultsContainer').empty();
  $('#resultsHeader').hide();
  $('#noResults').hide();
}

function showError(message) {
  alert(message);
}

// يضمن وجود مودال الاستضافة لتعديل الكتاب، ويعيد كائن المودال
function ensureEditModalHost() {
  var id = 'editBookHostModal';
  var $existing = $('#' + id);
  if ($existing.length) {
    return $existing[0];
  }
  var modalHtml = `
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
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  return document.getElementById(id);
}

// ربط نقر بطاقات الكتب لفتح مودال تفاصيل الكتاب
function bindCardClicks() {
  // منع تكرار الربط
  if (bindCardClicks._bound) return;
  bindCardClicks._bound = true;

  // تفويض الحدث على مستوى الوثيقة ليدعم العناصر المُضافة ديناميكياً
  document.addEventListener('click', function (e) {
    const card = e.target.closest('.book-item');
    if (!card) return;
    // تجاهل النقرات على العناصر التفاعلية داخل البطاقة
    if (e.target.closest('form, button, a, input, select, textarea, label')) return;
    const serial = card.getAttribute('data-serial');
    if (!serial) return;
    if (typeof showBookDetails === 'function') {
      e.preventDefault();
      // تحديد نوع المستخدم كموظف لهذه الصفحة (احتياطي في حال لم يضبط من الصفحة)
      if (!window.currentUserType) window.currentUserType = 'staff';
      showBookDetails(serial);
    }
  });
}

function bindEditButtons() {
  $(document).off('click', '.edit-book-btn').on('click', '.edit-book-btn', function(){
    var serial = $(this).data('serial');
    if (!serial) return;
    var host = ensureEditModalHost();
    var frame = host.querySelector('#editBookFrame');
    frame.src = '/admin/books/edit_modal.php?embed=1&serial=' + encodeURIComponent(serial);
    var modal = new bootstrap.Modal(host);
    host.addEventListener('hidden.bs.modal', function onHidden(){
      host.removeEventListener('hidden.bs.modal', onHidden);
      try { frame.src = 'about:blank'; } catch(e) {}
    });
    modal.show();
  });
}
