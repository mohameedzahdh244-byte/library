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
    <title>إدارة الإعارة</title>
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
    <!-- Borrow Modal (وضع مستقل) -->
    <div class="modal fade" id="borrowModal" tabindex="-1" aria-labelledby="borrowModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white justify-content-between">
                    <h5 class="modal-title" id="borrowModalLabel">
                        <img src="../../public/logo.png" alt="شعار المكتبة" class="me-2" style="height: 18px; width: auto;">إدارة الإعارات الجديدة
                    </h5>
                    <div class="flex-grow-1"></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="borrowForm" autocomplete="off">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="memberSearch" class="form-label">رقم المشترك أو الاسم</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" id="mem_search" placeholder="ابحث برقم المشترك أو الاسم" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                                        <input type="hidden" name="mem_no" id="mem_no">
                                        <div id="mem_result" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                    </div>
                                </div>
                                <div class="mb-3" id="memberInfoDiv" style="display: none;">
                                    <div class="alert alert-info">
                                        <strong>معلومات المشترك:</strong>
                                        <div id="memberInfo"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 book-row">
                                    <label for="bookSearch" class="form-label">رقم الكتاب التسلسلي أو العنوان</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control book-search" id="bookSearch" placeholder="ابحث برقم الكتاب أو العنوان" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                                        <div class="book-result list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                    </div>
                                </div>
                                <div class="mb-3" id="bookInfoDiv" style="display: none;">
                                    <div class="alert alert-success">
                                        <strong>معلومات الكتاب:</strong>
                                        <div id="bookInfo"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="borrowDate" class="form-label">تاريخ الإعارة</label>
                                    <input type="date" class="form-control" id="borrowDate" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="returnDate" class="form-label">تاريخ الإرجاع المتوقع</label>
                                    <input type="date" class="form-control" id="returnDate" autocomplete="off">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6>الكتب المحددة للإعارة:</h6>
                            <div id="selectedBooks" class="border rounded p-3" style="min-height: 100px; background: #f8f9fa;">
                                <p class="text-muted text-center">لم يتم تحديد أي كتب بعد</p>
                            </div>
                            <div id="selectedBooksInputs"></div>
                        </div>

                        <div class="mb-3" id="memberBorrowsDiv" style="display: none;">
                            <h6>الإعارات الحالية للمشترك:</h6>
                            <div id="memberBorrows" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    <button type="button" class="btn btn-success" id="addBookBtn" onclick="addBookToBorrow()">
                        إضافة كتاب
                        <i class="fas fa-plus me-1"></i>
                    </button>
                    <button type="submit" class="btn btn-success" id="executeBorrowBtn" form="borrowForm" disabled>
                        تنفيذ الإعارة
                        <i class="fas fa-save me-1"></i>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- وضع مضمّن داخل لوحة التحكم (مطابق لفكرة إضافة كتاب) -->
    <div class="p-3">
        <h5 class="fw-bold mb-3 text-end text-secondary">
            إدارة الإعارة - إضافة إعارة
        </h5>
        <form id="borrowForm" autocomplete="off">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="memberSearch" class="form-label">رقم المشترك أو الاسم</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="mem_search" placeholder="ابحث برقم المشترك أو الاسم" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                            <input type="hidden" name="mem_no" id="mem_no">
                            <div id="mem_result" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                        </div>
                    </div>
                    <div class="mb-3" id="memberInfoDiv" style="display: none;">
                        <div class="alert alert-info">
                            <strong>معلومات المشترك:</strong>
                            <div id="memberInfo"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3 book-row">
                        <label for="bookSearch" class="form-label">رقم الكتاب التسلسلي أو العنوان</label>
                        <div class="position-relative">
                            <input type="text" class="form-control book-search" id="bookSearch" placeholder="ابحث برقم الكتاب أو العنوان" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                            <div class="book-result list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                        </div>
                    </div>
                    <div class="mb-3" id="bookInfoDiv" style="display: none;">
                        <div class="alert alert-success">
                            <strong>معلومات الكتاب:</strong>
                            <div id="bookInfo"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="borrowDate" class="form-label">تاريخ الإعارة</label>
                        <input type="date" class="form-control" id="borrowDate" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="returnDate" class="form-label">تاريخ الإرجاع المتوقع</label>
                        <input type="date" class="form-control" id="returnDate" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <h6>الكتب المحددة للإعارة:</h6>
                <div id="selectedBooks" class="border rounded p-3" style="min-height: 100px; background: #f8f9fa;">
                    <p class="text-muted text-center">لم يتم تحديد أي كتب بعد</p>
                </div>
                <div id="selectedBooksInputs"></div>
            </div>

            <div class="mb-3" id="memberBorrowsDiv" style="display: none;">
                <h6>الإعارات الحالية للمشترك:</h6>
                <div id="memberBorrows" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;"></div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-success" id="addBookBtn" onclick="addBookToBorrow()">
                    إضافة كتاب
                    <i class="fas fa-plus me-1"></i>
                </button>
                <button type="submit" class="btn btn-success" id="executeBorrowBtn" form="borrowForm" disabled>
                    تنفيذ الإعارة
                    <i class="fas fa-save me-1"></i>
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
    <script src="/assets/js/borrow_borrow.js"></script>
    <?php if (!$embed): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            try { if (typeof openBorrowModal === 'function') openBorrowModal(); else new bootstrap.Modal(document.getElementById('borrowModal')).show(); } catch(e){}
        });
    </script>
    <?php else: ?>
    <style>body.embed{background: transparent;}</style>
    <?php endif; ?>
</body>
</html>
