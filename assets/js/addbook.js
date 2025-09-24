$(document).ready(function () {
    $('#addBookForm').on('submit', function (e) {
        e.preventDefault();

        // التحقق من صحة حقل عدد الصفحات
        const numPagesInput = $('#num_pages');
        const numPagesValue = numPagesInput.val().trim();
        const numPagesError = $('#num_pages_error');
        
        // إزالة أي رسائل خطأ سابقة
        numPagesInput.removeClass('is-invalid');
        numPagesError.text('');
        
        // التحقق: إذا كان الحقل غير فارغ، يجب أن يكون رقماً فقط (يُسمح بتركه فارغاً)
        if (numPagesValue !== '' && !$.isNumeric(numPagesValue)) {
            numPagesInput.addClass('is-invalid');
            numPagesError.text('عدد الصفحات يجب أن يكون رقماً فقط');
            return false;
        }

        const actionType = $(document.activeElement).val();

        var formEl = this;
        var fd = new FormData(formEl);
        $.ajax({
            url: '../../../admin/books/addbook_process.php',
            method: 'POST',
            data: fd,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (response) {
                const alertBox = `
                    <div class="alert alert-${response.type} alert-dismissible fade show" role="alert">
                        ${response.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                `;

                $('#form-message').html(alertBox);

                // إخفاء التنبيه بعد 3 ثواني
                setTimeout(() => {
                    const alert = document.querySelector('#form-message .alert');
                    if (alert) {
                        alert.classList.remove("show");
                        alert.classList.add("fade");
                        setTimeout(() => {
                            alert.remove();
                        }, 500);
                    }
                }, 3000);

                if (response.type === 'success') {
                    if (actionType === 'save') {
                        formEl.reset();
                    } else if (actionType === 'save_close') {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('globalModal'));
                        if (modal) modal.hide();
                    }
                }
            },
            error: function (xhr) {
                let errorMessage = "❌ فشل الاتصال بالخادم أو خطأ غير متوقع";
                // إذا كان هناك رسالة خطأ من الخادم
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = `❌ ${xhr.responseJSON.message}`;
                } else if (xhr.responseText) {
                    errorMessage = `❌ ${xhr.responseText}`;
                }

                $('#form-message').html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ${errorMessage}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                `);
                
            }
        });
    });
});

// تهيئة سلوك المودال (عرض/إخفاء) لحالتي التضمين وعدم التضمين
document.addEventListener('DOMContentLoaded', function(){
  // حالة عدم التضمين: إظهار مودال إضافة كتاب وإدارة الإغلاق للعودة
  var modalEl = document.getElementById('addBookModal');
  if (modalEl && window.bootstrap) {
    var modal = window.bootstrap.Modal.getInstance(modalEl);
    if (!modal) modal = new window.bootstrap.Modal(modalEl, { backdrop: false, keyboard: false });
    modal.show();
    modalEl.addEventListener('hidden.bs.modal', function(){
      if (document.referrer) { history.back(); }
      else { window.close(); }
    });
  }

  // حالة التضمين داخل مودال أب: زر إغلاق يُخفي مودال الكتب في النافذة الأم
  var btn = document.getElementById('closeEmbedAddBook');
  if (btn) {
    btn.addEventListener('click', function(){
      try {
        var parentDoc = window.parent && window.parent.document;
        if (!parentDoc) return;
        var parentModalEl = parentDoc.getElementById('booksModal');
        if (parentModalEl && window.parent.bootstrap) {
          var pmodal = window.parent.bootstrap.Modal.getInstance(parentModalEl);
          if (!pmodal) pmodal = new window.parent.bootstrap.Modal(parentModalEl);
          pmodal.hide();
        }
      } catch (e) {
        history.back();
      }
    });
  }
});

// منطق كاميرا صورة الغلاف في صفحة الإضافة
(function(){
  const btnOpen = document.getElementById('openCoverCamera');
  const btnCapture = document.getElementById('captureCoverPhoto');
  const btnRetake = document.getElementById('retakeCoverPhoto');
  const video = document.getElementById('coverCamera');
  const canvas = document.getElementById('coverCanvas');
  const preview = document.getElementById('coverPreview');
  const hiddenInput = document.getElementById('cover_image_data');
  const fileInput = document.getElementById('cover_image');
  let stream = null;

  function stopStream(){
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  }

  if (fileInput && preview) {
    fileInput.addEventListener('change', function(){
      const f = this.files && this.files[0];
      if (f) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; if (hiddenInput) hiddenInput.value=''; };
        reader.readAsDataURL(f);
      }
    });
  }

  if (!btnOpen || !video || !canvas || !preview || !hiddenInput) return;

  btnOpen.addEventListener('click', async function(){
    try {
      stopStream();
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      video.srcObject = stream;
      video.classList.remove('d-none');
      preview.classList.add('d-none');
      btnCapture.classList.remove('d-none');
      btnRetake.classList.add('d-none');
      this.classList.add('d-none');
      // إظهار مؤشر الحالة
      const status = document.getElementById('cameraStatus');
      if (status) status.classList.remove('d-none');
    } catch (e) {
      alert('لا يمكن فتح الكاميرا: قد تكون الصلاحيات مرفوضة.');
    }
  });

  btnCapture.addEventListener('click', function(){
    const vw = video.videoWidth || 480;
    const vh = video.videoHeight || 640;
    canvas.width = 600;
    canvas.height = Math.round(canvas.width * (vh / vw));
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    preview.src = dataUrl;
    if (hiddenInput) hiddenInput.value = dataUrl;
    if (fileInput) fileInput.value = '';
    // أوقف الكاميرا وأظهر المعاينة
    stopStream();
    video.classList.add('d-none');
    preview.classList.remove('d-none');
    btnCapture.classList.add('d-none');
    btnRetake.classList.remove('d-none');
    btnOpen.classList.add('d-none');
    // إخفاء مؤشر الحالة
    const status = document.getElementById('cameraStatus');
    if (status) status.classList.add('d-none');
  });

  btnRetake.addEventListener('click', function(){
    // إعادة الالتقاط: أفرغ الحقل المخفي وأعد فتح الكاميرا
    if (hiddenInput) hiddenInput.value = '';
    btnOpen.classList.remove('d-none');
    btnCapture.classList.add('d-none');
    btnRetake.classList.add('d-none');
    video.classList.add('d-none');
  });

  window.addEventListener('beforeunload', stopStream);
})();