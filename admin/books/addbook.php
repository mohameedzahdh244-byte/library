<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اضافة كتاب</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.css">
    <link rel="stylesheet" href="/assets/css/addbook.css">
    <link rel="stylesheet" href="/assets/css/publisher.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
 
</head>
<body>
<?php $isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1'; ?>
<?php if (!$isEmbed): ?>
  <div class="modal fade" id="addBookModal" data-bs-backdrop="false" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content rounded-4 shadow-lg">
        <div class="modal-header bg-primary text-white">
        </div>
        <div class="modal-body p-4">
<?php else: ?>
  <div class="p-3">
  
<?php endif; ?>
          <form id="addBookForm" method="POST" enctype="multipart/form-data" autocomplete="off">
            <h5 class="fw-bold mb-3 text-end text-secondary">
              <i class="bi bi-book-half"></i> بيانات الكتاب
            </h5>
            <?php
            // جلب أعلى رقم كتاب حالي من قاعدة البيانات
            include_once __DIR__ . '/../../config/DB.php';
            $serialnum_book_auto = 1;
            $result = $conn->query("SELECT MAX(serialnum_book) AS max_no FROM book");
            if ($result && $row = $result->fetch_assoc()) {
                $serialnum_book_auto = intval($row['max_no']) + 1;
            }
            ?>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="serialnum_book" class="form-label">رقم المتسلسل</label>
                <input id="serialnum_book" name="serialnum_book" type="number" class="form-control" step="any" placeholder="أدخل رقم المتسلسل" value="<?php echo $serialnum_book_auto; ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="book_title" class="form-label">عنوان الكتاب</label>
                <input id="book_title" name="book_title" type="text" class="form-control" placeholder="أدخل عنوان الكتاب"  />
            </div>
            <div class="col-md-6 mb-3">
                <label for="classification_num" class="form-label">رمز التصنيف</label>
                <input id="classification_num" name="classification_num" type="text" class="form-control" placeholder="أدخل رمز التصنيف"  />
            </div>
          
           
            <div class="col-md-6 mb-3">
                <label class="form-label d-flex justify-content-between align-items-center">
                  <span>اسم الناشر</span>
                  <button type="button" class="btn btn-sm btn-outline-primary openCreatePublisher">إضافة ناشر</button>
                </label>
                <div class="position-relative">
                  <input type="text" class="form-control" id="publisher_search" placeholder="ابحث باسم الناشر">
                  <input type="hidden" name="pub_no" id="pub_no">
                  <div id="publisher_result" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>
                  <div id="publisher_message" class="mt-1" style="display:none; max-width: fit-content;" role="status" aria-live="polite"></div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label d-flex justify-content-between align-items-center">
                  <span>اسم المؤلف</span>
                  <button type="button" class="btn btn-sm btn-outline-primary openCreateAuthor">إضافة مؤلف</button>
                </label>
                <div class="position-relative">
                  <div id="author_tags" class="d-flex flex-wrap gap-2 mb-2"></div>
                  <input type="text" class="form-control" id="author_search" placeholder="ابحث باسم المؤلف">
                  <input type="hidden" name="ANO" id="ANO">
                  <div id="author_result" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>
                  <div id="author_message" class="mt-1" style="display:none; max-width: fit-content;" role="status" aria-live="polite"></div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label d-flex justify-content-between align-items-center">
                  <span>اسم المورد</span>
                  <button type="button" class="btn btn-sm btn-outline-primary openCreateSupplier">إضافة مورد</button>
                </label>
                <div class="position-relative">
                  <input type="text" class="form-control" id="supplier_search" placeholder="ابحث باسم المورد">
                  <input type="hidden" name="sup_no" id="sup_no">
                  <div id="supplier_result" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="book_language" class="form-label"> لغة الكتاب</label>
                <select id="book_language" name="book_language" class="form-select" >
                    <option selected disabled>اختر اللغة</option>
                    <option>عربي</option>
                    <option>انجليزي</option>
                    <option>عربي وانجليزي</option>
                    <option>فرنسي</option>
                    <option>الماني</option>
                    <option>عبري</option>
                    <option>تركي</option>
                    <option>اسباني</option>
                    <option>ايطالي</option>
                    <option>روسي</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="year" class="form-label">السنة</label>
                <input id="year" name="year" type="text" class="form-control" placeholder="أدخل سنة الإصدار" />
            </div>
            <div class="col-md-6 mb-3">
                <label for="edition" class="form-label">الطبعة</label>
                <input id="edition" name="edition" type="text" class="form-control" placeholder="أدخل رقم الطبعة" />
            </div>
            <div class="col-md-6 mb-3">
                <label for="dimension" class="form-label">الأبعاد</label>
                <input id="dimension" name="dimension" type="text" class="form-control" placeholder="مثلاً 20/30 سم" />
            </div>
            <div class="col-md-6 mb-3">
                <label for="notes" class="form-label">ملاحظات</label>
                <input id="notes" name="notes" type="text" class="form-control" placeholder="أدخل ملاحظات إضافية" />
            </div>
            <div class="col-md-6 mb-3">
                <label for="ISBN" class="form-label">ISBN</label>
                <input id="ISBN" name="ISBN" type="text" class="form-control" placeholder="أدخل رقم ISBN" />
            </div>
            <div class="col-md-6 mb-3">
                <label for="deposit_num" class="form-label">رقم الإيداع</label>
                <input id="deposit_num" name="deposit_num" type="text" class="form-control" placeholder="أدخل رقم الإيداع" />
            </div>
            <div class="col-md-6 mb-3">
                <label for="num_pages" class="form-label">عدد الصفحات</label>
                <input id="num_pages" name="num_pages" type="number" class="form-control" placeholder="أدخل عدد الصفحات" min="1" />
                <div class="invalid-feedback" id="num_pages_error"></div>
            </div>
            <!-- واجهة تصوير الغلاف الاحترافية -->
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-header bg-transparent border-0 text-center py-3">
                        <h6 class="text-white mb-0 fw-bold">
                            صورة غلاف الكتاب
                            <i class="bi bi-camera-fill me-2"></i>
                        </h6>
                    </div>
                    <div class="card-body text-center p-4">
                        <!-- منطقة المعاينة الرئيسية -->
                        <div class="position-relative d-inline-block mb-3">
                            <div class="cover-preview-container" style="width: 200px; height: 280px; margin: 0 auto;">
                                <img id="coverPreview" src="/public/placeholder.svg" alt="معاينة الغلاف" 
                                     class="img-fluid rounded-3 shadow-lg border border-white border-3" 
                                     style="width: 100%; height: 100%; object-fit: cover; background: #f8f9fa;">
                                <video id="coverCamera" autoplay playsinline class="d-none rounded-3 shadow-lg border border-white border-3" 
                                       style="width: 100%; height: 100%; object-fit: cover;"></video>
                                <canvas id="coverCanvas" width="400" height="560" class="d-none"></canvas>
                            </div>
                            <!-- مؤشر الحالة -->
                            <div class="position-absolute top-0 start-0 m-2">
                                <span id="cameraStatus" class="badge bg-success bg-opacity-75 d-none">
                                    <i class="bi bi-camera-video-fill me-1"></i>جاهز للتصوير
                                </span>
                            </div>
                        </div>
                        
                        <!-- أزرار التحكم -->
                        <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                            <button type="button" id="openCoverCamera" class="btn btn-light btn-lg px-4 py-2 rounded-pill shadow-sm">
                                <span class="fw-semibold">فتح الكاميرا</span>
                                <i class="bi bi-camera-video text-primary me-2"></i>
                            </button>
                            <button type="button" id="captureCoverPhoto" class="btn btn-success btn-lg px-4 py-2 rounded-pill shadow-sm d-none">
                                <span class="fw-semibold">التقاط الصورة</span>
                                <i class="bi bi-camera-fill me-2"></i>
                            </button>
                            <button type="button" id="retakeCoverPhoto" class="btn btn-outline-light btn-lg px-4 py-2 rounded-pill shadow-sm d-none">
                                <span class="fw-semibold">إعادة التقاط</span>
                                <i class="bi bi-arrow-counterclockwise me-2"></i>
                            </button>
                        </div>
                        
                        <!-- خيار رفع الملف -->
                        <div class="border-top border-white border-opacity-25 pt-3">
                            <label for="cover_image" class="btn btn-outline-light btn-lg px-4 py-2 rounded-pill shadow-sm mb-2 d-inline-block">
                                <span class="fw-semibold">رفع صورة من الجهاز</span>
                                <i class="bi bi-upload me-2"></i>
                            </label>
                            <input id="cover_image" name="cover_image" type="file" class="d-none" accept="image/*" capture="environment" />
                            <div class="text-white-50 small mt-2">
                                يمكنك رفع صورة أو التقاط صورة جديدة • JPG, PNG, GIF • حد أقصى 5MB
                            </div>
                            <div class="invalid-feedback" id="cover_image_error"></div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="cover_image_data" name="cover_image_data" value="" />
            </div>
            <div class="col-12 mb-3">
                <label for="summary" class="form-label">ملخص/مقدمة الكتاب</label>
                <textarea id="summary" name="summary" class="form-control" rows="4" placeholder="أدخل ملخصاً أو مقدمة عن الكتاب..."></textarea>
                <div class="invalid-feedback" id="summary_error"></div>
            </div>
        </div>

        <h5 class="fw-bold mb-3 mt-4 text-end text-secondary">
          <i class="bi bi-archive"></i> بيانات الفهرسة
        </h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="stage" class="form-label">مرحلة المعالجة</label>
                <select id="stage" name="stage" class="form-select" >
                    <option value="">اختر المرحلة</option>
                    <option value="بصدد إعداد الطلب">بصدد إعداد الطلب</option>
                    <option value="طُبع أمر الطلب">طُبع أمر الطلب</option>
                    <option value="أرسل الطلب">أرسل الطلب</option>
                    <option value="أرسل التذكير">أرسل التذكير</option>
                    <option value="أُلغي الطلب">أُلغي الطلب</option>
                    <option value="تم تسلّم الكتاب">تم تسلّم الكتاب</option>
                    <option value="تمت فهرست الكتاب">تمت فهرست الكتاب</option>
                    <option value="تم تكشيف الكتاب">تم تكشيف الكتاب</option>
                    <option value="تم ترفيف الكتاب">تم ترفيف الكتاب</option>
                    <option value="حرر الكتاب للإستخدام">حرر الكتاب للإستخدام</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="department" class="form-label">القسم</label>
                <select id="department" name="department" class="form-select" >
                    <option value="">اختر القسم</option>
                    <option value="المكتبة العامة">المكتبة العامة</option>
                    <option value="مكتبة الطفل">مكتبة الطفل</option>
                    <option value="مكتبة العلوم الاسلامية">مكتبة العلوم الاسلامية</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="book_type" class="form-label">نوع الكتاب</label>
                <select id="book_type" name="book_type" class="form-select" >
                    <option value="">اختر نوع الكتاب</option>
                    <option value="عادي">عادي</option>
                    <option value="مرجع">مرجع</option>
                    <option value="مجموعة رسائل">مجموعة رسائل</option>
                    <option value="رسالة جامعية">رسالة جامعية</option>
                    <option value="مجموعة">مجموعة</option>
                    <option value="مرجع اطفال">مرجع اطفال</option>
                    <option value="مجلة علمية محكمة">مجلة علمية محكمة</option>
                    <option value="مجلة">مجلة</option>
                 </select>
             </div>
            <div class="col-md-6 mb-3">
                <label for="book_status" class="form-label">حالة الكتاب</label>
                <select id="book_status" name="book_status" class="form-select" >
                    <option value="">اختر حالة الكتاب</option>
                    <option value="على الرف">على الرف</option>
                    <option value="في التجليد">في التجليد</option>
                    <option value="مفقود">مفقود</option>
                    <option value="متلف">متلف</option>
                    <option value="جديد">جديد</option>
                    <option value="في المخزن">في المخزن</option>
                </select>
            </div>
        </div>
    
    
    <div id="form-message" class="mt-3 text-center"></div>
    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="submit" class="btn btn-success px-4 fw-bold">
         أضافة كتاب
         <i class="bi bi-save2"></i>
      </button>
   
      <button type="button" id="closeEmbedAddBook" class="btn btn-outline-secondary px-4 fw-bold" aria-label="إغلاق">
        إغلاق
        <i class="bi bi-x-circle"></i>
      </button>
    </div>
    </form>
<?php if (!$isEmbed): ?>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  </div>
<?php endif; ?>


  <script src="/assets/js/jquery-3.7.1.min.js"></script>
  <script src="/assets/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/publisher.js"></script>
  <script src="/assets/js/author.js"></script>
  <script src="/assets/js/supplier.js"></script>
  <script src="/assets/js/addbook.js"></script>
  <script>
    // منطق إغلاق مضمّن يعمل دائماً حتى إن تعطّل السكربتات الخارجية
    (function(){
      var btn = document.getElementById('closeEmbedAddBook');
      if (!btn) return;
      btn.addEventListener('click', function(){
        try {
          var pwin = window.parent || null;
          var pdoc = pwin && pwin.document;
          if (!pdoc) { if (document.referrer) { history.back(); } else { window.close(); } return; }
          var closed = false;
          var ids = ['booksModal','customersModal','borrowModal'];
          for (var i=0;i<ids.length;i++){
            var el = pdoc.getElementById(ids[i]);
            if (!el) continue;
            try {
              if (pwin.bootstrap){
                var inst = pwin.bootstrap.Modal.getInstance(el) || new pwin.bootstrap.Modal(el);
                inst.hide();
              } else {
                el.classList.remove('show');
                el.setAttribute('aria-hidden','true');
                el.setAttribute('inert','');
              }
              closed = true;
              break;
            } catch(e) { /* تجاهل */ }
          }
          if (!closed){ if (document.referrer) { history.back(); } else { window.close(); } }
        } catch (e) { if (document.referrer) { history.back(); } else { window.close(); } }
      });
    })();
  </script>

<?php include_once __DIR__ . '/../publishers/publisher_modal.php'; ?>
<?php include_once __DIR__ . '/../authors/author_modal.php'; ?>
<?php include_once __DIR__ . '/../suppliers/supplier_modal.php'; ?>

</body>
</html>