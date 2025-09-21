// Return-only JS
let selectedBooksForReturn = [];
let currentMember = null;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-fill member from URL when embedded
    try {
        const params = new URLSearchParams(window.location.search || '');
        const memNo = params.get('mem_no');
        if (memNo) {
            $.post('search_member.php', { q: memNo }, function(data) {
                try {
                    const $tmp = $('<div>').html(data);
                    let $member = $tmp.find('.choose-member').filter(function(){ return $(this).data('no') == memNo; }).first();
                    if (!$member.length) $member = $tmp.find('.choose-member').first();
                    if ($member.length) {
                        const memberData = {
                            id: $member.data('no'),
                            name: $member.data('name'),
                            phone: $member.data('phone') || '',
                            subscription_end: $member.data('subscription-end') || ''
                        };
                        currentMember = memberData;
                        displayReturnMemberInfo(memberData);
                        loadBorrowedBooks(memberData.id);
                    }
                } catch(e) { /* ignore */ }
            });
        }
    } catch(e) { /* ignore */ }
});

function openReturnModal() {
    const modal = new bootstrap.Modal(document.getElementById('returnModal'));
    modal.show();
    resetReturnForm();
}

function resetReturnForm() {
    const form = document.getElementById('returnForm');
    if (form) form.reset();
    selectedBooksForReturn = [];
    currentMember = null;
    const infoDiv = document.getElementById('returnMemberInfoDiv');
    const borrowedDiv = document.getElementById('borrowedBooksDiv');
    const fineSection = document.getElementById('fineSection');
    if (infoDiv) infoDiv.style.display = 'none';
    if (borrowedDiv) borrowedDiv.style.display = 'none';
    if (fineSection) fineSection.style.display = 'none';
    updateSelectedReturnBooksDisplay();
}

// بحث عضو الإرجاع
const returnSearch = document.getElementById('returnMemberSearch');
if (returnSearch) {
    returnSearch.addEventListener('input', function(e) {
        const searchValue = e.target.value;
        if (searchValue.length >= 2) {
            $.post('search_member.php', {q: searchValue}, function(data) {
                let tempDiv = $('<div>').html(data);
                let firstMember = tempDiv.find('.choose-member').first();
                if (firstMember.length > 0) {
                    let memberData = {
                        id: firstMember.data('no'),
                        name: firstMember.data('name'),
                        phone: firstMember.data('phone') || '',
                        subscription_end: firstMember.data('subscription-end') || ''
                    };
                    currentMember = memberData;
                    displayReturnMemberInfo(memberData);
                    loadBorrowedBooks(memberData.id);
                    showAlert(`تم اختيار المشترك: ${memberData.name} (${memberData.id})`, 'success');
                } else {
                    const infoDiv = document.getElementById('returnMemberInfoDiv'); if (infoDiv) infoDiv.style.display = 'none';
                    const borrowedDiv = document.getElementById('borrowedBooksDiv'); if (borrowedDiv) borrowedDiv.style.display = 'none';
                    currentMember = null;
                }
            });
        } else {
            const infoDiv = document.getElementById('returnMemberInfoDiv'); if (infoDiv) infoDiv.style.display = 'none';
            const borrowedDiv = document.getElementById('borrowedBooksDiv'); if (borrowedDiv) borrowedDiv.style.display = 'none';
            currentMember = null;
        }
    });
}

function displayReturnMemberInfo(member) {
    const el = document.getElementById('returnMemberInfo');
    if (!el) return;
    el.innerHTML = `
        <div><strong>الرقم:</strong> ${member.id}</div>
        <div><strong>الاسم:</strong> ${member.name}</div>
        <div><strong>الهاتف:</strong> ${member.phone}</div>`;
    const div = document.getElementById('returnMemberInfoDiv');
    if (div) div.style.display = 'block';
}

function loadBorrowedBooks(memberId) {
    $.post('get_member_borrows.php', {mem_no: memberId}, function(data) {
        try {
            const response = JSON.parse(data);
            const container = $('#borrowedBooks');
            if (response.success && response.borrows.length > 0) {
                let borrowsHTML = '';
                response.borrows.forEach(borrow => {
                    const isOverdue = new Date(borrow.boro_exp_ret_date) < new Date();
                    const overdueDays = isOverdue ? Math.ceil((new Date() - new Date(borrow.boro_exp_ret_date)) / (1000 * 60 * 60 * 24)) : 0;
                    const statusClass = isOverdue ? 'bg-danger' : 'bg-warning text-dark';
                    const statusText = isOverdue ? `متأخر ${overdueDays} يوم` : 'معار';
                    borrowsHTML += `
                        <div class="border rounded p-2 mb-2 borrowed-book-item" 
                             data-borrow-id="${borrow.borrow_detail_id}"
                             data-serial="${borrow.serialnum_book}"
                             data-title="${borrow.book_title}"
                             data-overdue="${isOverdue}"
                             data-overdue-days="${overdueDays}"
                             style="cursor: pointer;">
                            <strong>${borrow.book_title}</strong><br>
                            <small>الرقم التسلسلي: ${borrow.serialnum_book}</small><br>
                            <small>تاريخ الإعارة: ${borrow.boro_date}</small><br>
                            <small>تاريخ الإرجاع المتوقع: ${borrow.boro_exp_ret_date}</small>
                            <span class="badge ${statusClass} float-end">${statusText}</span>
                        </div>`;
                });
                container.html(borrowsHTML);
                $('#borrowedBooksDiv').show();
                $('.borrowed-book-item').on('click', function() {
                    if ($(this).hasClass('disabled') || $(this).hasClass('selected-for-return')) return;
                    const borrowId = $(this).data('borrow-id');
                    const serial = $(this).data('serial');
                    const title = $(this).data('title');
                    const isOverdue = $(this).data('overdue');
                    const overdueDays = $(this).data('overdue-days');
                    addBookToReturnList(borrowId, serial, title, isOverdue, overdueDays);
                });
            } else {
                container.html('<p class="text-muted text-center">لا توجد كتب معارة لهذا المشترك</p>');
                $('#borrowedBooksDiv').show();
            }
        } catch(e) {
            console.error('Error parsing borrowed books:', e);
            $('#borrowedBooks').html('<p class="text-danger text-center">خطأ في جلب البيانات</p>');
            $('#borrowedBooksDiv').show();
        }
    });
}

function addBookToReturnList(borrowId, serial, title, isOverdue, overdueDays) {
    if (selectedBooksForReturn.find(book => book.borrowId === borrowId)) { showAlert('هذا الكتاب مضاف بالفعل للإرجاع', 'warning'); return; }
    selectedBooksForReturn.push({ borrowId, serial, title, isOverdue, overdueDays });
    updateSelectedReturnBooksDisplay();
    showAlert(`تم إضافة الكتاب "${title}" للإرجاع`, 'success');
    markBorrowedItemSelection(borrowId, true);
}

function addBookToReturn() {
    const query = (document.getElementById('returnBookSearch').value || '').trim();
    if (!query) { showAlert('يرجى إدخال رقم تسلسلي أو عنوان للكتاب', 'warning'); return; }
    let found = false;
    document.querySelectorAll('#borrowedBooks .borrowed-book-item').forEach(item => {
        const serial = item.getAttribute('data-serial');
        const title = item.getAttribute('data-title');
        if (serial === query || (title && title.indexOf(query) !== -1)) {
            const borrowId = item.getAttribute('data-borrow-id');
            const isOverdue = item.getAttribute('data-overdue') === 'true';
            const overdueDays = parseInt(item.getAttribute('data-overdue-days') || '0', 10);
            addBookToReturnList(borrowId, serial, title, isOverdue, overdueDays);
            found = true;
        }
    });
    if (!found) showAlert('الكتاب غير موجود ضمن قائمة الكتب المعارة لهذا المشترك', 'danger');
}

function updateSelectedReturnBooksDisplay() {
    const container = document.getElementById('selectedReturnBooks');
    if (!container) return;
    if (selectedBooksForReturn.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">لم يتم تحديد أي كتب للإرجاع بعد</p>';
        const btn = document.getElementById('executeReturnBtn'); if (btn) btn.disabled = true;
        const fs = document.getElementById('fineSection'); if (fs) fs.style.display = 'none';
        return;
    }
    let html = ''; let hasOverdueBooks = false; let totalOverdueDays = 0;
    selectedBooksForReturn.forEach((book, index) => {
        const statusClass = book.isOverdue ? 'text-danger' : 'text-success';
        const statusText = book.isOverdue ? `متأخر ${book.overdueDays} يوم` : 'في الوقت';
        if (book.isOverdue) { hasOverdueBooks = true; totalOverdueDays += book.overdueDays; }
        html += `
            <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-center">
                <div>
                    <strong>${book.title}</strong><br>
                    <small class="text-muted">الرقم التسلسلي: ${book.serial}</small><br>
                    <small class="${statusClass}">${statusText}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBookFromReturn(${index})"><i class="fas fa-times"></i></button>
            </div>`;
    });
    container.innerHTML = html;
    const btn = document.getElementById('executeReturnBtn'); if (btn) btn.disabled = false;
    if (hasOverdueBooks) {
        const fi = document.getElementById('fineInfo'); if (fi) fi.innerHTML = `<p>يوجد كتب متأخرة بإجمالي ${totalOverdueDays} يوم تأخير.</p><p>يمكنك إضافة غرامة تأخير حسب سياسة المكتبة.</p>`;
        const fs = document.getElementById('fineSection'); if (fs) fs.style.display = 'block';
    } else {
        const fs = document.getElementById('fineSection'); if (fs) fs.style.display = 'none';
    }
}

function removeBookFromReturn(index) {
    const removed = selectedBooksForReturn.splice(index, 1)[0];
    updateSelectedReturnBooksDisplay();
    if (removed && removed.borrowId) markBorrowedItemSelection(removed.borrowId, false);
}

function markBorrowedItemSelection(borrowId, selected) {
    const $item = $(`#borrowedBooks .borrowed-book-item[data-borrow-id="${borrowId}"]`);
    if ($item.length) {
        if (selected) { $item.addClass('selected-for-return disabled').attr('title', 'مضاف إلى قائمة الإرجاع').slideUp(150); }
        else { $item.removeClass('selected-for-return disabled').attr('title', '').slideDown(150); }
    }
}

function executeReturn() {
    if (selectedBooksForReturn.length === 0) { showAlert('يرجى تحديد كتب للإرجاع أولاً', 'warning'); return; }
    if (!currentMember || !currentMember.id) { showAlert('يرجى اختيار المشترك أولاً', 'warning'); return; }
    const fineAmount = parseFloat(document.getElementById('fineAmount').value || '0') || 0;
    const btn = document.getElementById('executeReturnBtn');
    const original = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري التنفيذ...';
    const form = new FormData();
    form.append('mem_no', currentMember.id);
    form.append('return_date', new Date().toISOString().split('T')[0]);
    form.append('fine_amount', fineAmount);
    selectedBooksForReturn.forEach(book => { form.append('return_books[]', book.serial); });
    fetch('process_borrowing.php', { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessModal('تم تنفيذ الإرجاع', data.message);
            if (currentMember && currentMember.id) loadBorrowedBooks(currentMember.id);
            selectedBooksForReturn = []; updateSelectedReturnBooksDisplay();
            const fs = document.getElementById('fineSection'); if (fs) fs.style.display = 'none';
            const fa = document.getElementById('fineAmount'); if (fa) fa.value = '';
        } else {
            const msg = data.errors ? data.errors.join('<br>') : (data.message || 'حدث خطأ غير معروف');
            showAlert(msg, 'danger');
        }
    })
    .catch(err => { console.error(err); showAlert('فشل الاتصال بالخادم', 'danger'); })
    .finally(() => { btn.disabled = false; btn.innerHTML = original; });
}

// اختصارات لوحة المفاتيح لحقل بحث الإرجاع
const returnBookSearch = document.getElementById('returnBookSearch');
if (returnBookSearch) {
    returnBookSearch.addEventListener('keydown', function(e) {
        const getItems = () => $('#borrowedBooks .borrowed-book-item:visible:not(.selected-for-return)');
        if (e.key === 'Enter') {
            e.preventDefault();
            const $active = $('#borrowedBooks .borrowed-book-item.kb-active:visible');
            if ($active.length) { $active.trigger('click'); } else { addBookToReturn(); }
        } else if (e.key === 'ArrowDown' || e.key === 'Down') {
            e.preventDefault(); const $items = getItems(); if (!$items.length) return;
            let idx = $items.index($('#borrowedBooks .borrowed-book-item.kb-active:visible'));
            idx = (idx + 1) % $items.length; $items.removeClass('kb-active').css('outline', '');
            const $next = $items.eq(idx).addClass('kb-active').css('outline', '2px solid #0d6efd'); $next.get(0).scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp' || e.key === 'Up') {
            e.preventDefault(); const $items = getItems(); if (!$items.length) return;
            let idx = $items.index($('#borrowedBooks .borrowed-book-item.kb-active:visible'));
            idx = (idx <= 0 ? $items.length - 1 : idx - 1); $items.removeClass('kb-active').css('outline', '');
            const $prev = $items.eq(idx).addClass('kb-active').css('outline', '2px solid #0d6efd'); $prev.get(0).scrollIntoView({ block: 'nearest' });
        }
    });
}

// Utilities
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `${message}<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>`;
    document.body.appendChild(alertDiv);
    setTimeout(() => { if (alertDiv && alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv); }, 3000);
}
function showSuccessModal(title, message) {
    const label = document.getElementById('successModalLabel');
    const details = document.getElementById('successDetails');
    if (label) label.innerHTML = `<i class=\"fas fa-check-circle me-2\"></i>${title}`;
    if (details) details.innerHTML = message;
    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}
