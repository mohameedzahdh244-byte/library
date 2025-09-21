<?php
// تضمين نظام المسارات
require_once '../config/init.php';

checkMemberPermission();

$mem_no = $_SESSION['user_no'];

// الحصول على معلومات المشترك
$stmt = $conn->prepare("SELECT mem_name FROM customer WHERE mem_no = ?");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اقترح كتاب - <?php echo $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <!-- Member Suggest CSS -->
    <link href="../assets/css/member-suggest.css" rel="stylesheet">
    
    <style>
        /* Enhanced Form Styling */
        .form-floating > .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .form-floating > .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
            transform: translateY(-2px);
        }
        
        .form-floating > label {
            font-weight: 600;
            color: #495057;
            padding-right: 1rem;
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #007bff;
        }
        
        /* Button Enhancements */
        .btn-lg {
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,123,255,0.3);
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            font-weight: 600;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            border-color: #6c757d;
            transform: translateY(-2px);
        }
        
        /* Card hover effects */
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
            transition: all 0.3s ease;
        }
        
        /* Status badge animations */
        .badge {
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .badge:hover {
            transform: scale(1.05);
        }
        
        /* Form validation styling */
        .form-control.is-invalid {
            border-color: #dc3545;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Loading spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .d-flex.gap-3 {
                flex-direction: column;
                gap: 1rem !important;
            }
            
            .btn-lg {
                width: 100%;
            }
            
            .form-floating > .form-control {
                font-size: 1rem;
            }
        }
        
        /* RTL Support */
        [dir="rtl"] .form-floating > label {
            right: 0.75rem;
            left: auto;
        }
        
        [dir="rtl"] .me-2 {
            margin-left: 0.5rem !important;
            margin-right: 0 !important;
        }
    </style>
    
    <!-- Analytics -->
    <script src="../assets/js/analytics.js"></script>
</head>
<body data-analytics-page="member">
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="bg-primary text-white p-4 rounded-3 text-center">
                    <i class="fas fa-lightbulb fs-1 mb-3"></i>
                    <h1 class="h2 mb-3">اقترح كتاباً جديداً</h1>
                    <p class="mb-0">شارك معنا في إثراء مكتبتنا! اقترح الكتب التي تود قراءتها وسنعمل على توفيرها</p>
                </div>
            </div>
        </div>

        <!-- Suggestion Form -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 12px 12px 0 0;">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3">
                                <i class="fas fa-plus-circle text-white"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold">اقتراح كتاب جديد</h5>
                                <small class="text-white-75">املأ النموذج أدناه لإرسال اقتراحك</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="suggestionForm">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg" id="bookTitle" name="title" 
                                               placeholder="أدخل عنوان الكتاب" required maxlength="255">
                                        <label for="bookTitle">
                                            <i class="fas fa-book text-primary me-2"></i>
                                            عنوان الكتاب <span class="text-danger">*</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg" id="bookAuthor" name="author" 
                                               placeholder="أدخل اسم المؤلف" required maxlength="255">
                                        <label for="bookAuthor">
                                            <i class="fas fa-user-edit text-primary me-2"></i>
                                            المؤلف <span class="text-danger">*</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <div class="form-floating">
                                    <textarea class="form-control" id="bookNotes" name="notes" 
                                              placeholder="أي ملاحظات أو تفاصيل إضافية عن الكتاب" 
                                              maxlength="1000" style="height: 120px;"></textarea>
                                    <label for="bookNotes">
                                        <i class="fas fa-sticky-note text-primary me-2"></i>
                                        ملاحظات إضافية (اختياري)
                                    </label>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle text-info me-1"></i>
                                    يمكنك إضافة: سبب الاقتراح، دار النشر، رقم الطبعة، أو أي تفاصيل مفيدة
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-end mt-4 pt-3 border-top">
                                <button type="button" class="btn btn-outline-secondary btn-lg px-4" onclick="clearForm()">
                                    <i class="fas fa-eraser me-2"></i>
                                    مسح النموذج
                                </button>
                                <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    إرسال الاقتراح
                                    <div class="spinner-border spinner-border-sm ms-2 d-none" id="submitSpinner"></div>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Suggestions -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            اقتراحاتي السابقة
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="suggestionsLoading" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                            <p class="mt-2 text-muted">جاري تحميل اقتراحاتك...</p>
                        </div>
                        <div id="suggestionsList" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
        <div id="alertContainer"></div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <!-- Member Suggest JS -->
    <script src="../assets/js/member-suggest.js"></script>
</body>
</html>
