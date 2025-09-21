// Borrow-only JS
let selectedBooksForBorrow = [];
let currentMember = null;

document.addEventListener('DOMContentLoaded', function() {
    setTodayDate();
    // أعِد تقييم حالة زر التنفيذ عند تغيّر mem_no يدويًا
    try {
        const memInput = document.getElementById('mem_no');
        if (memInput) memInput.addEventListener('input', function(){ try { updateSelectedBooksDisplay(); } catch(e){} });
    } catch(e){}
    // Auto-fill from URL params for embed usage
    try {
        const params = new URLSearchParams(window.location.search || '');
        const memNo = params.get('mem_no');
        const serial = params.get('serial');
        const title = params.get('title');
        if (memNo) {
            // اضبط قيمة رقم المشترك مباشرة ليدعم التمكين المبكر
            try { const memEl = document.getElementById('mem_no'); if (memEl) memEl.value = memNo; } catch(e){}
            // Search and pick the member automatically
            $.post('search_member.php', { q: memNo }, function(data) {
                try {
                    const $tmp = $('<div>').html(data);
                    // Prefer exact mem_no match if present
                    let $member = $tmp.find('.choose-member').filter(function(){ return $(this).data('no') == memNo; }).first();
                    if (!$member.length) $member = $tmp.find('.choose-member').first();
                    if ($member.length) {
                        const memberData = {
                            id: $member.data('no'),
                            name: $member.data('name'),
                            phone: $member.data('phone') || '',
                            subscription_end: $member.data('subscription-end') || ''
                        };
                        $('#mem_no').val(memberData.id);
                        $('#mem_search').val(memberData.name);
                        $('#mem_result').empty().hide();
                        currentMember = memberData;
                        displayMemberInfo(memberData);
                        loadMemberBorrows(memberData.id);
                        // فعّل الزر إن كانت هناك كتب محددة مسبقًا
                        try { updateSelectedBooksDisplay(); } catch(e){}
                    }
                } catch(e) { /* ignore */ }
            });
        }
        if (serial) {
            const book = { serial: serial, title: title || serial, author: '' };
            // Show basic book info and add to selection
            try { displayBookInfo({ serial: book.serial, title: book.title, status: 'available' }); } catch(e){}
            if (!selectedBooksForBorrow.find(b => b.serial === book.serial)) {
                selectedBooksForBorrow.push(book);
                updateSelectedBooksDisplay();
            }
        }
    } catch (e) { /* ignore */ }
});

function setTodayDate() {
    const today = new Date().toISOString().split('T')[0];
    const borrowDate = document.getElementById('borrowDate');
    const returnDate = document.getElementById('returnDate');
    if (borrowDate) borrowDate.value = today;
    const rd = new Date(); rd.setDate(rd.getDate() + 14);
    if (returnDate) returnDate.value = rd.toISOString().split('T')[0];
}

function openBorrowModal() {
    const modal = new bootstrap.Modal(document.getElementById('borrowModal'));
    modal.show();
    // لا تُعد ضبط النموذج إن وُجد mem_no أو serial في الرابط (حجوزات/تضمين)
    try {
        const params = new URLSearchParams(window.location.search || '');
        const hasMem = !!params.get('mem_no');
        const hasSerial = !!params.get('serial');
        if (!hasMem && !hasSerial) {
            resetBorrowForm();
        }
    } catch (e) {
        // إن حدث خطأ، حافظ على السلوك السابق
        resetBorrowForm();
    }
}

function resetBorrowForm() {
    const form = document.getElementById('borrowForm');
    if (form) form.reset();
    selectedBooksForBorrow = [];
    currentMember = null;
    const memberInfoDiv = document.getElementById('memberInfoDiv');
    const bookInfoDiv = document.getElementById('bookInfoDiv');
    const memberBorrowsDiv = document.getElementById('memberBorrowsDiv');
    const memResult = document.getElementById('mem_result');
    if (memberInfoDiv) memberInfoDiv.style.display = 'none';
    if (bookInfoDiv) bookInfoDiv.style.display = 'none';
    if (memberBorrowsDiv) memberBorrowsDiv.style.display = 'none';
    if (memResult) memResult.style.display = 'none';
    const bookResult = document.querySelector('.book-result');
    if (bookResult) bookResult.style.display = 'none';
    updateSelectedBooksDisplay();
    setTodayDate();
}

// بحث المشترك
$('#mem_search').on('input', function() {
    let q = $(this).val();
    if(q.length < 2) { 
        $('#mem_result').empty().hide(); 
        const memberInfoDiv = document.getElementById('memberInfoDiv');
        const memberBorrowsDiv = document.getElementById('memberBorrowsDiv');
        if (memberInfoDiv) memberInfoDiv.style.display = 'none';
        if (memberBorrowsDiv) memberBorrowsDiv.style.display = 'none';
        currentMember = null;
        return; 
    }
    $.post('search_member.php', {q:q}, function(data) {
        $('#mem_result').html(data).show();
    });
});

$(document).on('click', '.choose-member', function() {
    let memberData = {
        id: $(this).data('no'),
        name: $(this).data('name'),
        phone: $(this).data('phone') || '',
        subscription_end: $(this).data('subscription-end') || ''
    };
    $('#mem_no').val(memberData.id);
    $('#mem_search').val(memberData.name);
    $('#mem_result').empty().hide();
    currentMember = memberData;
    displayMemberInfo(memberData);
    loadMemberBorrows(memberData.id);
    if (memberData.subscription_end) {
        const end = new Date(memberData.subscription_end);
        const today = new Date();
        end.setHours(0,0,0,0); today.setHours(0,0,0,0);
        if (end < today) showAlert(`تنبيه: هذا المشترك منتهي الاشتراك (${memberData.subscription_end})`, 'warning');
    }
});

function displayMemberInfo(member) {
    const subscriptionStatus = checkSubscriptionStatus(member.subscription_end);
    const el = document.getElementById('memberInfo');
    if (!el) return;
    el.innerHTML = `
        <div><strong>الرقم:</strong> ${member.id}</div>
        <div><strong>الاسم:</strong> ${member.name}</div>
        <div><strong>الهاتف:</strong> ${member.phone}</div>
        <div><strong>انتهاء الاشتراك:</strong> ${member.subscription_end} ${subscriptionStatus.icon}</div>`;
    const div = document.getElementById('memberInfoDiv');
    if (div) { div.style.display = 'block'; div.className = `alert ${subscriptionStatus.class}`; }
}

function checkSubscriptionStatus(endDate) {
    if (!endDate) return { class: 'alert-success', icon: '✅ (ساري المفعول)' };
    const today = new Date();
    const end = new Date(endDate);
    const diffDays = Math.ceil((end - today) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return { class: 'alert-danger', icon: '❌ (منتهي الصلاحية)' };
    if (diffDays < 60) return { class: 'alert-warning', icon: '⚠️ (ينتهي قريباً)' };
    return { class: 'alert-success', icon: '✅ (ساري المفعول)' };
}

function loadMemberBorrows(memberId) {
    $.post('get_member_borrows.php', {mem_no: memberId}, function(data) {
        try {
            const resp = JSON.parse(data);
            const borrows = (resp && resp.success && Array.isArray(resp.borrows)) ? resp.borrows : [];
            const container = document.getElementById('memberBorrows');
            if (!container) return;
            if (borrows.length > 0) {
                let borrowsHTML = '';
                borrows.forEach(borrow => {
                    const isOverdue = new Date(borrow.boro_exp_ret_date) < new Date();
                    const borrowDateText = `تاريخ الإعارة: ${borrow.boro_date}`;
                    borrowsHTML += `
                        <div class="book-item borrow-row ${isOverdue ? 'overdue' : ''}">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong>${borrow.book_title}</strong> (${borrow.serialnum_book})
                                    <br><small class="borrow-dates" data-borrow-date-text="${borrowDateText}">${borrowDateText} | تاريخ الإرجاع المتوقع: ${borrow.boro_exp_ret_date}</small>
                                    ${isOverdue ? '<span class="badge bg-danger ms-2">متأخر</span>' : '<span class="badge bg-warning text-dark ms-2">معار</span>'}
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="small text-muted mb-0" for="renew_${borrow.borrow_detail_id}">تجديد حتى:</label>
                                    <input type="date" class="form-control form-control-sm" id="renew_${borrow.borrow_detail_id}" name="renew_date" value="${borrow.boro_exp_ret_date}" autocomplete="off">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="renewBorrow(${borrow.borrow_detail_id}, this)"><i class="fa fa-rotate-right me-1"></i>تجديد</button>
                                </div>
                            </div>
                        </div>`;
                });
                container.innerHTML = borrowsHTML;
                const div = document.getElementById('memberBorrowsDiv');
                if (div) div.style.display = 'block';
            } else {
                container.innerHTML = '<p class="text-muted text-center">لا توجد إعارات حالية لهذا المشترك</p>';
                const div = document.getElementById('memberBorrowsDiv');
                if (div) div.style.display = 'block';
            }
        } catch(e) {
            console.error('Error parsing member borrows:', e);
            const container = document.getElementById('memberBorrows');
            if (container) container.innerHTML = '<p class="text-danger text-center">خطأ في جلب البيانات</p>';
            const div = document.getElementById('memberBorrowsDiv');
            if (div) div.style.display = 'block';
        }
    });
}

// بحث الكتاب
$(document).on('input', '.book-search', function() {
    let $row = $(this).closest('.book-row');
    let q = $(this).val();
    if(q.length < 2) { 
        $row.find('.book-result').empty().hide(); 
        const div = document.getElementById('bookInfoDiv');
        if (div) div.style.display = 'none';
        return; 
    }
    $.post('search_book.php', {q:q}, function(data) {
        $row.find('.book-result').html(data).show();
    });
});

$(document).on('click', '.choose-book', function() {
    let bookData = {
        serial: $(this).data('serial'),
        title: $(this).data('title'),
        author: $(this).data('author') || '',
        status: $(this).data('status') || 'available'
    };
    $('#bookSearch').val(bookData.title);
    $('.book-result').empty().hide();
    displayBookInfo(bookData);
});

function displayBookInfo(book) {
    let statusText, statusClass;
    switch(book.status) {
        case 'available': statusText = 'متوفر'; statusClass = 'alert-success'; break;
        case 'borrowed': statusText = `معار - الإرجاع المتوقع: ${book.return_date}`; statusClass = 'alert-warning'; break;
        case 'reserved': statusText = 'محجوز'; statusClass = 'alert-info'; break;
        default: statusText = 'غير متوفر'; statusClass = 'alert-danger';
    }
    const el = document.getElementById('bookInfo');
    if (!el) return;
    el.innerHTML = `
        <div><strong>الرقم التسلسلي:</strong> ${book.serial}</div>
        <div><strong>العنوان:</strong> ${book.title}</div>
        <div><strong>الحالة:</strong> <span class="badge ${statusClass === 'alert-success' ? 'bg-success' : statusClass === 'alert-warning' ? 'bg-warning' : 'bg-danger'}">${statusText}</span></div>`;
    const div = document.getElementById('bookInfoDiv');
    if (div) { div.style.display = 'block'; div.className = `alert ${statusClass}`; }
    const addBtn = document.getElementById('addBookBtn');
    if (addBtn) addBtn.disabled = book.status !== 'available';
}

function addBookToBorrow() {
    const memNoEl = document.getElementById('mem_no');
    const hasMember = !!currentMember || (memNoEl && memNoEl.value);
    if (!hasMember) { showAlert('يرجى تحديد المشترك أولاً', 'warning'); return; }
    const bookTitle = document.getElementById('bookSearch').value;
    if (!bookTitle) { showAlert('يرجى البحث عن كتاب أولاً', 'warning'); return; }
    const bookInfoDiv = document.getElementById('bookInfo');
    if (!bookInfoDiv || !bookInfoDiv.innerHTML) { showAlert('يرجى اختيار كتاب من نتائج البحث', 'warning'); return; }
    const serialMatch = bookInfoDiv.innerHTML.match(/الرقم التسلسلي:<\/strong>\s*([^<]+)/);
    const titleMatch = bookInfoDiv.innerHTML.match(/العنوان:<\/strong>\s*([^<]+)/);
    if (!serialMatch) { showAlert('خطأ في بيانات الكتاب', 'danger'); return; }
    const book = { serial: serialMatch[1].trim(), title: titleMatch ? titleMatch[1].trim() : bookTitle, author: '' };
    if (!selectedBooksForBorrow.find(b => b.serial === book.serial)) {
        selectedBooksForBorrow.push(book);
        updateSelectedBooksDisplay();
        document.getElementById('bookSearch').value = '';
        const div = document.getElementById('bookInfoDiv'); if (div) div.style.display = 'none';
        showAlert('تم إضافة الكتاب بنجاح', 'success');
    } else {
        showAlert('الكتاب مضاف مسبقاً', 'warning');
    }
}

function updateSelectedBooksDisplay() {
    const container = document.getElementById('selectedBooks');
    const inputsContainer = document.getElementById('selectedBooksInputs');
    if (!container || !inputsContainer) return;
    if (selectedBooksForBorrow.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">لم يتم تحديد أي كتب بعد</p>';
        inputsContainer.innerHTML = '';
        const btn = document.getElementById('executeBorrowBtn'); if (btn) btn.disabled = true;
    } else {
        let html = ''; let inputsHtml = '';
        selectedBooksForBorrow.forEach((book, index) => {
            html += `
                <div class="book-item d-flex justify-content-between align-items-center">
                    <div><strong>${book.title}</strong> (${book.serial})</div>
                    <button class="btn btn-danger btn-sm" type="button" onclick="removeBookFromBorrow(${index})"><i class="fas fa-trash"></i></button>
                </div>`;
            inputsHtml += `<input type="hidden" name="serialnum_book[]" value="${book.serial}">`;
        });
        container.innerHTML = html; inputsContainer.innerHTML = inputsHtml;
        const btn = document.getElementById('executeBorrowBtn');
        if (btn) {
            const memNoEl = document.getElementById('mem_no');
            const hasMember = !!currentMember || (memNoEl && memNoEl.value);
            btn.disabled = !hasMember;
            if (hasMember) {
                try {
                    btn.removeAttribute('disabled');
                    btn.removeAttribute('aria-disabled');
                    btn.classList.remove('disabled');
                } catch(e){}
            }
        }
    }
}

function removeBookFromBorrow(index) {
    selectedBooksForBorrow.splice(index, 1);
    updateSelectedBooksDisplay();
}

// Submit borrow
const borrowFormEl = document.getElementById('borrowForm');
if (borrowFormEl) {
    borrowFormEl.addEventListener('submit', function(e) {
        e.preventDefault();
        const memNoEl = document.getElementById('mem_no');
        const hasMember = !!currentMember || (memNoEl && memNoEl.value);
        if (!hasMember || selectedBooksForBorrow.length === 0) { showAlert('يرجى تحديد المشترك والكتب المراد إعارتها', 'warning'); return; }
        if (currentMember.subscription_end) {
            const end = new Date(currentMember.subscription_end), today = new Date();
            end.setHours(0,0,0,0); today.setHours(0,0,0,0);
            if (end < today) { showAlert(`لا يمكن تنفيذ الإعارة: اشتراك المشترك منتهي بتاريخ ${currentMember.subscription_end}`, 'danger'); return; }
        }
        updateSelectedBooksDisplay();
        const formData = new FormData(this);
        const borrowDate = document.getElementById('borrowDate').value;
        const expReturnDate = document.getElementById('returnDate').value;
        if (borrowDate) formData.append('borrow_date', borrowDate);
        // ملاحظة: إذا وُجدت الحقول المتعددة expected_return_date[] في الفورم، سيأخذها الخادم كأولوية
        if (expReturnDate) formData.append('expected_return_date', expReturnDate);
        const submitBtn = document.getElementById('executeBorrowBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري التنفيذ...';
        submitBtn.disabled = true;
        fetch('process_borrowing.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSuccessModal('تم تنفيذ الإعارة بنجاح!', `تم إعارة ${selectedBooksForBorrow.length} كتاب للمشترك: ${currentMember.name}`);
                resetBorrowForm();
                setTimeout(() => { const modal = bootstrap.Modal.getInstance(document.getElementById('borrowModal')); if (modal) modal.hide(); }, 2000);
            } else {
                let errorMessage = 'حدث خطأ أثناء تنفيذ العملية';
                if (data.errors && data.errors.length > 0) errorMessage = data.errors.join('<br>');
                showAlert(errorMessage, 'danger');
            }
        })
        .catch(err => { console.error(err); showAlert('حدث خطأ في الاتصال مع الخادم', 'danger'); })
        .finally(() => { submitBtn.innerHTML = originalText; submitBtn.disabled = false; });
    });
}

// Auto-update return date
const borrowDateInput = document.getElementById('borrowDate');
if (borrowDateInput) {
    borrowDateInput.addEventListener('change', function(){
        const borrowDate = new Date(this.value);
        borrowDate.setDate(borrowDate.getDate() + 14);
        const returnDate = document.getElementById('returnDate');
        if (returnDate) returnDate.value = borrowDate.toISOString().split('T')[0];
    });
}

// تجديد الإعارة لكل كتاب من قائمة إعارات المشترك
function renewBorrow(borrowDetailId, btnEl) {
    const wrapper = btnEl ? btnEl.closest('.borrow-row') : null;
    const dateInput = wrapper ? wrapper.querySelector('input[name="renew_date"]') : null;
    const newDate = dateInput && dateInput.value ? dateInput.value : '';
    if (!newDate) { showAlert('يرجى اختيار تاريخ جديد للتجديد', 'warning'); return; }
    const originalHTML = btnEl.innerHTML;
    btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const formData = new FormData();
    formData.append('borrow_detail_id', borrowDetailId);
    formData.append('new_exp_date', newDate);
    fetch('renew.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert('تم التجديد بنجاح', 'success');
                // حدّث العرض الحالي لتاريخ الإرجاع المتوقع
                if (wrapper) {
                    const info = wrapper.querySelector('.borrow-dates');
                    if (info) info.innerHTML = `${info.getAttribute('data-borrow-date-text')} | تاريخ الإرجاع المتوقع: ${newDate}`;
                }
            } else {
                const msg = data.errors && data.errors.length ? data.errors.join('<br>') : 'تعذّر تنفيذ التجديد';
                showAlert(msg, 'danger');
            }
        })
        .catch(() => showAlert('خطأ في الاتصال بالخادم', 'danger'))
        .finally(() => { btnEl.disabled = false; btnEl.innerHTML = originalHTML; });
}

// Utilities
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alertDiv);
    setTimeout(() => { if (alertDiv && alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv); }, 3000);
}
function showSuccessModal(title, message) {
    const label = document.getElementById('successModalLabel');
    const details = document.getElementById('successDetails');
    if (label) label.innerHTML = `<i class="fas fa-check-circle me-2"></i>${title}`;
    if (details) details.innerHTML = message;
    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}
