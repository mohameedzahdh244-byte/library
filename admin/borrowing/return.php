<?php
require_once '../../config/init.php';

// السماح فقط للموظفين والمديرين
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    die('صلاحيات غير كافية.');
}

$embed = isset($_GET['embed']) && $_GET['embed'] !== '' && $_GET['embed'] !== '0';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الإرجاع</title>
    <link href="/assets/css/bootstrap.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/borrow.css" rel="stylesheet">
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
</head>
<body class="borrow-page<?php echo $embed ? ' embed' : '';?>">
    <div class="container-fluid">
        <div class="main-container"></div>
    </div>

    <?php if (!$embed): ?>
    <!-- Return Modal (وضع مستقل) -->
    <div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark justify-content-between">
                    <h5 class="modal-title" id="returnModalLabel">
                        <i class="fas fa-undo me-2"></i>إدارة الإرجاع
                    </h5>
                    <div class="flex-grow-1"></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="returnForm" autocomplete="off">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="returnMemberSearch" class="form-label">رقم المشترك أو الاسم</label>
                                    <input type="text" class="form-control" id="returnMemberSearch" placeholder="ابحث برقم المشترك أو الاسم" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                                </div>
                                <div class="mb-3" id="returnMemberInfoDiv" style="display: none;">
                                    <div class="alert alert-info">
                                        <strong>معلومات المشترك:</strong>
                                        <div id="returnMemberInfo"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="returnBookSearch" class="form-label">رقم الكتاب التسلسلي أو العنوان</label>
                                    <input type="text" class="form-control" id="returnBookSearch" placeholder="ابحث برقم الكتاب أو العنوان" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="borrowedBooksDiv" style="display: none;">
                            <h6>الكتب المعارة للمشترك:</h6>
                            <div id="borrowedBooks" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;"></div>
                        </div>

                        <div class="mb-3">
                            <h6>الكتب المحددة للإرجاع:</h6>
                            <div id="selectedReturnBooks" class="border rounded p-3" style="min-height: 100px; background: #f8f9fa;">
                                <p class="text-muted text-center">لم يتم تحديد أي كتب للإرجاع بعد</p>
                            </div>
                        </div>

                        <div class="mb-3" id="fineSection" style="display: none;">
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>تنبيه تأخير</h6>
                                <div id="fineInfo"></div>
                                <div class="mt-2">
                                    <label for="fineAmount" class="form-label">مبلغ الغرامة (اختياري)</label>
                                    <input type="number" class="form-control" id="fineAmount" placeholder="أدخل مبلغ الغرامة" step="0.01" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    <button type="button" class="btn btn-warning" id="addReturnBookBtn" onclick="addBookToReturn()">
                        إضافة كتاب للإرجاع
                        <i class="fas fa-plus me-1"></i>
                    </button>
                    <button type="button" class="btn btn-success" id="executeReturnBtn" onclick="executeReturn()" disabled>
                        تنفيذ الإرجاع
                        <i class="fas fa-check me-1"></i>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- وضع مضمّن داخل لوحة التحكم (مطابق تمامًا لشكل الإعارة) -->
    <div class="p-3">
        <h5 class="fw-bold mb-3 text-end text-secondary">إدارة الإرجاع - إضافة إرجاع</h5>
        <form id="returnForm" autocomplete="off">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="returnMemberSearch" class="form-label">رقم المشترك أو الاسم</label>
                        <input type="text" class="form-control" id="returnMemberSearch" placeholder="ابحث برقم المشترك أو الاسم" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                    </div>
                    <div class="mb-3" id="returnMemberInfoDiv" style="display: none;">
                        <div class="alert alert-info">
                            <strong>معلومات المشترك:</strong>
                            <div id="returnMemberInfo"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="returnBookSearch" class="form-label">رقم الكتاب التسلسلي أو العنوان</label>
                        <input type="text" class="form-control" id="returnBookSearch" placeholder="ابحث برقم الكتاب أو العنوان" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                    </div>
                </div>
            </div>

            <div class="mb-3" id="borrowedBooksDiv" style="display: none;">
                <h6>الكتب المعارة للمشترك:</h6>
                <div id="borrowedBooks" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;"></div>
            </div>

            <div class="mb-3">
                <h6>الكتب المحددة للإرجاع:</h6>
                <div id="selectedReturnBooks" class="border rounded p-3" style="min-height: 100px; background: #f8f9fa;">
                    <p class="text-muted text-center">لم يتم تحديد أي كتب للإرجاع بعد</p>
                </div>
            </div>

            <div class="mb-3" id="fineSection" style="display: none;">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>تنبيه تأخير</h6>
                    <div id="fineInfo"></div>
                    <div class="mt-2">
                        <label for="fineAmount" class="form-label">مبلغ الغرامة (اختياري)</label>
                        <input type="number" class="form-control" id="fineAmount" placeholder="أدخل مبلغ الغرامة" step="0.01" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-warning" id="addReturnBookBtn" onclick="addBookToReturn()">
                    إضافة كتاب للإرجاع
                    <i class="fas fa-plus me-1"></i>
                </button>
                <button type="button" class="btn btn-success" id="executeReturnBtn" onclick="executeReturn()" disabled>
                    تنفيذ الإرجاع
                    <i class="fas fa-check me-1"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary">إغلاق</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>تم بنجاح
                    </h5>
                </div>
                <div class="modal-body">
                    <div id="successMessage" class="text-center">
                        <div class="success-animation">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h5 class="mt-3">تمت العملية بنجاح!</h5>
                        <p id="successDetails"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">موافق</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/jquery-3.7.1.min.js"></script>
    <script src="/assets/js/borrow_return.js"></script>
    <?php if (!$embed): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            try { if (typeof openReturnModal === 'function') openReturnModal(); else new bootstrap.Modal(document.getElementById('returnModal')).show(); } catch(e){}
        });
    </script>
    <?php else: ?>
    <style>body.embed{background:transparent;}</style>
    <?php endif; ?>
</body>
</html>
