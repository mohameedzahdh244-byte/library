/**
 * Member Book Suggestions JavaScript
 * Handles form submission, validation, and suggestions list
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize page
    initializePage();
    loadMemberSuggestions();
    
    // Form submission
    document.getElementById('suggestionForm').addEventListener('submit', handleFormSubmit);
    
    // Character counters
    setupCharacterCounters();
});

function initializePage() {
    // Track page view
    if (typeof trackEvent === 'function') {
        trackEvent('page_view', 'member_suggest', {
            page: 'suggest_book'
        });
    }
}

function setupCharacterCounters() {
    const titleInput = document.getElementById('bookTitle');
    const authorInput = document.getElementById('bookAuthor');
    const notesInput = document.getElementById('bookNotes');
    
    // Add character counters
    addCharacterCounter(titleInput, 255);
    addCharacterCounter(authorInput, 255);
    addCharacterCounter(notesInput, 1000);
}

function addCharacterCounter(input, maxLength) {
    const counter = document.createElement('div');
    counter.className = 'char-counter';
    counter.textContent = `0/${maxLength}`;
    input.parentNode.appendChild(counter);
    
    input.addEventListener('input', function() {
        const length = this.value.length;
        counter.textContent = `${length}/${maxLength}`;
        
        if (length > maxLength * 0.9) {
            counter.className = 'char-counter danger';
        } else if (length > maxLength * 0.8) {
            counter.className = 'char-counter warning';
        } else {
            counter.className = 'char-counter';
        }
    });
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = document.getElementById('submitBtn');
    const formData = new FormData(form);
    
    // Validate form
    if (!validateForm(form)) {
        return;
    }
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري الإرسال...';
    
    try {
        // Track form submission attempt
        if (typeof trackEvent === 'function') {
            trackEvent('form_submit', 'suggest_book', {
                title: formData.get('title').substring(0, 50) + '...',
                author: formData.get('author').substring(0, 30) + '...'
            });
        }
        
        const response = await fetch('../api/suggestions/create.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', 'تم إرسال اقتراحك بنجاح! سيتم مراجعته من قبل فريق المكتبة.');
            form.reset();
            updateCharacterCounters();
            loadMemberSuggestions(); // Refresh the list
            
            // Track success
            if (typeof trackEvent === 'function') {
                trackEvent('suggestion_created', 'suggest_book', {
                    suggestion_id: result.id
                });
            }
        } else {
            showAlert('danger', result.message || 'حدث خطأ أثناء إرسال الاقتراح. يرجى المحاولة مرة أخرى.');
            
            // Track error
            if (typeof trackEvent === 'function') {
                trackEvent('suggestion_error', 'suggest_book', {
                    error: result.message || 'unknown_error'
                });
            }
        }
    } catch (error) {
        console.error('Error submitting suggestion:', error);
        showAlert('danger', 'حدث خطأ في الاتصال. يرجى التحقق من الاتصال بالإنترنت والمحاولة مرة أخرى.');
        
        // Track network error
        if (typeof trackEvent === 'function') {
            trackEvent('suggestion_network_error', 'suggest_book', {
                error: error.message
            });
        }
    } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>إرسال الاقتراح';
    }
}

function validateForm(form) {
    let isValid = true;
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(el => {
        el.remove();
    });
    
    // Validate title
    const title = form.querySelector('#bookTitle');
    if (!title.value.trim()) {
        showFieldError(title, 'عنوان الكتاب مطلوب');
        isValid = false;
    } else if (title.value.trim().length < 2) {
        showFieldError(title, 'عنوان الكتاب يجب أن يكون أكثر من حرفين');
        isValid = false;
    }
    
    // Validate author
    const author = form.querySelector('#bookAuthor');
    if (!author.value.trim()) {
        showFieldError(author, 'اسم المؤلف مطلوب');
        isValid = false;
    } else if (author.value.trim().length < 2) {
        showFieldError(author, 'اسم المؤلف يجب أن يكون أكثر من حرفين');
        isValid = false;
    }
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback';
    feedback.textContent = message;
    field.parentNode.appendChild(feedback);
}

async function loadMemberSuggestions() {
    const loadingDiv = document.getElementById('suggestionsLoading');
    const listDiv = document.getElementById('suggestionsList');
    
    try {
        const response = await fetch('../api/suggestions/list_member.php');
        const result = await response.json();
        
        if (result.success) {
            displaySuggestions(result.suggestions);
        } else {
            const msg = result.message || 'حدث خطأ أثناء تحميل اقتراحاتك.';
            listDiv.innerHTML = `<div class="alert alert-warning">${escapeHtml(msg)}</div>`;
        }
    } catch (error) {
        console.error('Error loading suggestions:', error);
        listDiv.innerHTML = '<div class="alert alert-danger">خطأ في الاتصال. يرجى إعادة تحميل الصفحة.</div>';
    } finally {
        loadingDiv.style.display = 'none';
        listDiv.style.display = 'block';
    }
}

function displaySuggestions(suggestions) {
    const listDiv = document.getElementById('suggestionsList');
    
    if (!suggestions || suggestions.length === 0) {
        listDiv.innerHTML = `
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-lightbulb text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                </div>
                <h5 class="text-muted mb-3">لا توجد اقتراحات بعد</h5>
                <p class="text-muted mb-4">ابدأ بإرسال اقتراحك الأول باستخدام النموذج أعلاه</p>
                <div class="d-flex justify-content-center gap-3 text-muted small">
                    <div><i class="fas fa-check-circle text-success me-1"></i>سهل وسريع</div>
                    <div><i class="fas fa-clock text-info me-1"></i>استجابة فورية</div>
                    <div><i class="fas fa-heart text-danger me-1"></i>مجاني تماماً</div>
                </div>
            </div>
        `;
        return;
    }
    
    const suggestionsHtml = suggestions.map(suggestion => {
        const statusText = getStatusText(suggestion.status);
        const statusClass = getStatusBootstrapClass(suggestion.status);
        const statusIcon = getStatusIcon(suggestion.status);
        const createdDate = new Date(suggestion.created_at).toLocaleDateString('ar-EG');
        
        return `
            <div class="card mb-3 shadow-sm border-0" style="transition: all 0.3s ease;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="flex-grow-1">
                            <h6 class="card-title fw-bold text-primary mb-2">
                                <i class="fas fa-book me-2"></i>
                                ${escapeHtml(suggestion.title)}
                            </h6>
                            <p class="card-text text-muted mb-2">
                                <i class="fas fa-user-edit me-1"></i>
                                <strong>المؤلف:</strong> ${escapeHtml(suggestion.author)}
                            </p>
                        </div>
                        <span class="badge ${statusClass} px-3 py-2 rounded-pill">
                            <i class="${statusIcon} me-1"></i>
                            ${statusText}
                        </span>
                    </div>
                    
                    ${suggestion.notes ? `
                        <div class="bg-light p-3 rounded mb-3">
                            <small class="text-muted d-block mb-1">
                                <i class="fas fa-sticky-note me-1"></i>
                                <strong>ملاحظاتك:</strong>
                            </small>
                            <small>${escapeHtml(suggestion.notes)}</small>
                        </div>
                    ` : ''}
                    
                    ${suggestion.staff_notes ? `
                        <div class="alert alert-info mb-3 py-2">
                            <small>
                                <i class="fas fa-user-tie me-1"></i>
                                <strong>رد الموظف:</strong> ${escapeHtml(suggestion.staff_notes)}
                            </small>
                        </div>
                    ` : ''}
                    
                    <div class="d-flex justify-content-between align-items-center text-muted small">
                        <span>
                            <i class="fas fa-calendar me-1"></i>
                            تم الإرسال في ${createdDate}
                        </span>
                        <span class="text-primary">
                            <i class="fas fa-hashtag me-1"></i>
                            #${suggestion.id}
                        </span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Add header with count
    const headerHtml = `
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="text-muted mb-0">
                <i class="fas fa-list-ul me-2"></i>
                إجمالي الاقتراحات: <span class="badge bg-primary">${suggestions.length}</span>
            </h6>
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                يتم مراجعة الاقتراحات خلال 24-48 ساعة
            </small>
        </div>
    `;
    
    listDiv.innerHTML = headerHtml + suggestionsHtml;
}

function getStatusBootstrapClass(status) {
    const statusMap = {
        'new': 'bg-primary',
        'reviewed': 'bg-warning text-dark',
        'purchased': 'bg-success',
        'rejected': 'bg-danger'
    };
    return statusMap[status] || 'bg-secondary';
}

function getStatusIcon(status) {
    const iconMap = {
        'new': 'fas fa-star',
        'reviewed': 'fas fa-eye',
        'purchased': 'fas fa-check-circle',
        'rejected': 'fas fa-times-circle'
    };
    return iconMap[status] || 'fas fa-question-circle';
}

function getStatusText(status) {
    const statusMap = {
        'new': 'جديد',
        'reviewed': 'تمت المراجعة',
        'purchased': 'تم الشراء',
        'rejected': 'مرفوض'
    };
    return statusMap[status] || status;
}

function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

function clearForm() {
    const form = document.getElementById('suggestionForm');
    form.reset();
    
    // Clear validation states
    form.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(el => {
        el.remove();
    });
    
    // Reset character counters
    updateCharacterCounters();
    
    // Track form clear
    if (typeof trackEvent === 'function') {
        trackEvent('form_clear', 'suggest_book');
    }
}

function updateCharacterCounters() {
    document.querySelectorAll('.char-counter').forEach(counter => {
        const input = counter.previousElementSibling;
        if (input && input.tagName === 'INPUT' || input.tagName === 'TEXTAREA') {
            const length = input.value.length;
            const maxLength = input.getAttribute('maxlength') || 255;
            counter.textContent = `${length}/${maxLength}`;
            counter.className = 'char-counter';
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
