// Edit Book - AJAX submit and helpers
(function(){
  const form = document.getElementById('editBookForm');
  const msgBox = document.getElementById('editBookMessage');
  if (!form) return;

  function renderAlert(message, type){
    if (!msgBox) return;
    const html = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
      </div>`;
    msgBox.innerHTML = html;
    // إخفاء تلقائي بعد 3 ثواني مثل addbook.js
    setTimeout(() => {
      const alert = msgBox.querySelector('.alert');
      if (alert) {
        alert.classList.remove('show');
        alert.classList.add('fade');
        setTimeout(() => { alert.remove(); }, 500);
      }
    }, 3000);
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    
    // التحقق من صحة حقل عدد الصفحات
    const numPagesInput = document.querySelector('#num_pages, [name="num_pages"]');
    const numPagesError = document.getElementById('num_pages_error');
    
    if (numPagesInput && numPagesError) {
      const numPagesValue = numPagesInput.value.trim();
      
      // إزالة أي رسائل خطأ سابقة
      numPagesInput.classList.remove('is-invalid');
      numPagesError.textContent = '';
      
      // التحقق: إذا كان الحقل غير فارغ، يجب أن يكون رقماً فقط (يُسمح بتركه فارغاً)
      if (numPagesValue !== '' && isNaN(numPagesValue)) {
        numPagesInput.classList.add('is-invalid');
        numPagesError.textContent = 'عدد الصفحات يجب أن يكون رقماً فقط';
        return false;
      }
    }
    
    const btn = form.querySelector('button[type="submit"]');
    const prev = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري الحفظ...'; }

    const fd = new FormData(form);
    fetch(window.location.href, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data && data.ok) {
          renderAlert('✔️ تم حفظ التغييرات بنجاح.', 'success');
        } else {
          renderAlert((data && data.msg) ? ('❌ ' + data.msg) : '❌ تعذّر حفظ التغييرات', 'danger');
        }
      })
      .catch(() => renderAlert('❌ تعذّر الاتصال بالخادم', 'danger'))
      .finally(() => { if (btn){ btn.disabled = false; btn.innerHTML = prev; } });
  });

  // --- منطق كاميرا الغلاف في التعديل ---
  const btnOpen = document.getElementById('openCoverCameraEdit');
  const btnCapture = document.getElementById('captureCoverPhotoEdit');
  const btnRetake = document.getElementById('retakeCoverPhotoEdit');
  const video = document.getElementById('coverCameraEdit');
  const canvas = document.getElementById('coverCanvasEdit');
  const preview = document.getElementById('coverPreviewEdit');
  const hiddenInput = document.getElementById('cover_image_data');
  const fileInput = document.getElementById('cover_image_edit');
  let stream = null;

  function stopStream(){ if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; } }

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

  if (btnOpen && btnCapture && btnRetake && video && canvas && preview && hiddenInput) {
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
        const status = document.getElementById('cameraStatusEdit');
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
      const status = document.getElementById('cameraStatusEdit');
      if (status) status.classList.add('d-none');
    });

    btnRetake.addEventListener('click', function(){
      if (hiddenInput) hiddenInput.value = '';
      btnOpen.classList.remove('d-none');
      btnCapture.classList.add('d-none');
      btnRetake.classList.add('d-none');
      video.classList.add('d-none');
    });

    window.addEventListener('beforeunload', stopStream);
  }
})();
