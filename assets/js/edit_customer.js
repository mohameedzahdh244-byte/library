'use strict';

function sendToParent(type, payload){
  if(window.parent){
    window.parent.postMessage({ source:'edit_member_modal', type, payload }, '*');
  }
}

// إشعار علوي يمين يختفي تلقائياً بعد 3 ثوانٍ
function showTopRightAlert(type, message){
  var container = document.getElementById('notify-top-right');
  if(!container){
    container = document.createElement('div');
    container.id = 'notify-top-right';
    container.style.position = 'fixed';
    container.style.top = '12px';
    container.style.right = '12px';
    container.style.zIndex = '2000';
    container.style.width = 'min(360px, 90vw)';
    container.style.pointerEvents = 'none';
    document.body.appendChild(container);
  }
  var alert = document.createElement('div');
  alert.className = 'alert alert-' + type + ' alert-dismissible fade show shadow';
  alert.setAttribute('role','alert');
  alert.style.pointerEvents = 'all';
  alert.style.direction = 'rtl';
  alert.innerHTML = '\n        ' + message + '\n        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>\n      ';
  container.appendChild(alert);
  setTimeout(function(){
    try{
      alert.classList.remove('show');
      alert.classList.add('fade');
      setTimeout(function(){ alert.remove(); }, 500);
    }catch(e){}
  }, 3000);
}

// جعل تاريخ نهاية الاشتراك تلقائياً بعد سنة من تاريخ البداية
function plusOneYear(dateStr){
  if(!dateStr) return '';
  var base = new Date(dateStr);
  if(isNaN(base.getTime())) return '';
  var y = base.getFullYear()+1, m = base.getMonth(), d = base.getDate();
  var nd = new Date(y, m, d);
  if (nd.getMonth() !== m) { nd = new Date(y, m+1, 0); }
  var mm = String(nd.getMonth()+1).padStart(2,'0');
  var dd = String(nd.getDate()).padStart(2,'0');
  return nd.getFullYear() + '-' + mm + '-' + dd;
}

// إعادة بناء منطق التحديث بأسلوب addcustomer.js
$(document).ready(function(){
  // منطق تعدد أرقام الهاتف في شاشة التعديل
  function getPhoneValues(){
    return $('.phone-input').map(function(){ return $(this).val().trim(); }).get();
  }
  function syncPrimaryPhoneHidden(){
    var phones = getPhoneValues().filter(function(p){ return p !== ''; });
    $('#mem_phone_hidden').val(phones.length ? phones[0] : '');
  }
  function ensureAtLeastOneRow(){
    if ($('.phone-row').length === 0) {
      var rowHtml = '\n          <div class="phone-row position-relative">\n            <i class="bi bi-telephone-fill field-icon start"></i>\n            <input type="text" class="form-control phone-input" name="phones[]" placeholder="أدخل رقم الجوال" />\n            <button type="button" class="icon-btn add-phone text-success" aria-label="add"><i class="bi bi-plus-lg"></i></button>\n          </div>';
      $('#phonesRepeaterEdit').append(rowHtml);
    }
  }

  // إضافة صف رقم جديد (تفويض على الحاوية)
  $('#phonesRepeaterEdit').on('click', '.add-phone', function(){
    console.debug('edit: add-phone clicked');
    var rowHtml = '\n      <div class="phone-row position-relative">\n        <i class="bi bi-telephone-fill field-icon start"></i>\n        <input type="text" class="form-control phone-input" name="phones[]" placeholder="أدخل رقم الجوال" />\n        <button type="button" class="icon-btn remove-phone text-danger" aria-label="remove"><i class="bi bi-dash-lg"></i></button>\n      </div>';
    $('#phonesRepeaterEdit').append(rowHtml);
    $('#phonesRepeaterEdit .phone-row:last .phone-input').focus();
  });

  // حذف صف رقم (تفويض على الحاوية)
  $('#phonesRepeaterEdit').on('click', '.remove-phone', function(){
    console.debug('edit: remove-phone clicked');
    $(this).closest('.phone-row').remove();
    ensureAtLeastOneRow();
    syncPrimaryPhoneHidden();
  });

  // مزامنة الرقم الأساسي عند الكتابة
  $(document).on('input change', '.phone-input', function(){
    syncPrimaryPhoneHidden();
  });

  // عند تغيير تاريخ البداية، حدّث تاريخ النهاية تلقائياً بعد سنة
  $(document).on('change input', 'input[name="start_date"]', function(){
    var v = this.value;
    var next = plusOneYear(v);
    if(next){ $('input[name="end_date"]').val(next); }
  });

  $('#editForm').on('submit', function(e){
    e.preventDefault();
    var $form = $(this);
    // تأكد من مزامنة أول رقم للهاتف
    syncPrimaryPhoneHidden();
    // تحقّق: على الأقل رقم واحد إن كان هناك واجهة الهواتف
    var hasPhonesUI = $('#phonesRepeaterEdit').length > 0;
    if (hasPhonesUI) {
      var firstPhone = $('#mem_phone_hidden').val().trim();
      if (!firstPhone){
        showTopRightAlert('warning', 'الرجاء إدخال رقم جوال واحد على الأقل');
        return;
      }
    }
    // استخدم FormData لإرسال الملفات + الحقول النصية
    var formEl = $form.get(0);
    var fd = new FormData(formEl);
    // ضمان وجود action=update
    if (!fd.has('action')) { fd.append('action', 'update'); }
    $.ajax({
      url: window.location.href,
      method: 'POST',
      data: fd,
      dataType: 'json',
      processData: false,
      contentType: false,
      success: function(r){
        var ok = r && (r.ok === true || r.success === true);
        var type = ok ? 'success' : 'danger';
        var msg = ok ? 'تم تحديث بيانات المشترك بنجاح' : (r && (r.msg || r.message)) || 'فشل التحديث';
        // عرض إشعار علوي يمين داخل الإطار
        showTopRightAlert(type, msg);
        // إرسال إشعار للنافذة الأم ليظهر نفس توست اختيار المشترك
        try{ window.parent.postMessage({ source:'edit_member_modal', type:'notify', payload:{ level:type, message: msg } }, '*'); }catch(e){}
        if(ok){
          // أرسل للنافذة الأب لإعلامها بالتحديث (نرسل mem_no فقط بشكل آمن)
          try {
            var memNoVal = ($form.find('input[name="mem_no"]').val() || '').trim();
            sendToParent('update_member', { mem_no: memNoVal });
          } catch(_) {}
        }
      },
      error: function(xhr){
        var errorMessage = '❌ فشل الاتصال بالخادم أو خطأ غير متوقع';
        if(xhr.responseJSON && xhr.responseJSON.message){ errorMessage = '❌ '+xhr.responseJSON.message; }
        else if(xhr.responseText){ errorMessage = '❌ '+xhr.responseText; }
        showTopRightAlert('danger', errorMessage);
        try{ window.parent.postMessage({ source:'edit_member_modal', type:'notify', payload:{ level:'danger', message: errorMessage } }, '*'); }catch(e){}
      }
    });
  });
});

// camera_capture.js - محاكاة منطق التقاط الصورة كما في صفحة إضافة المشترك
$(document).ready(function(){
  var captureBtn = document.getElementById('captureBtn');
  var retakeBtn = document.getElementById('retakeBtn');
  var video = document.getElementById('cameraStream');
  var img = document.getElementById('photoPreview');
  var canvas = document.getElementById('captureCanvas');
  var hidden = document.getElementById('personal_photo');
  var cameraSelect = document.getElementById('cameraSelect');
  var cameraOverlay = document.getElementById('cameraOverlay');
  var streamRef = null;

  // معاينة فورية عند رفع الصورة (قبل أي إيقاف مبكر)
  (function(){
    var fileEl = document.getElementById('personal_photo_file');
    if (!fileEl) return;
    fileEl.addEventListener('change', function(){
      var f = this.files && this.files[0];
      if (!f) return;
      var preview = document.getElementById('photoPreview');
      var hiddenBase64 = document.getElementById('personal_photo');
      try { if (hiddenBase64) hiddenBase64.value = ''; } catch(_) {}
      try { if (preview) { preview.src = URL.createObjectURL(f); preview.classList.remove('d-none'); } } catch(_) {}
    });
  })();

  if (!video || !img || !canvas || !hidden) return;

  function stopStream(){ try { if (streamRef) streamRef.getTracks().forEach(function(t){ t.stop(); }); } catch(e) {} streamRef = null; }

  function mapGetUserMediaError(err){
    var base = 'تعذر تشغيل الكاميرا. ';
    if (!err || !err.name) return base + 'تأكد من منح الإذن واستخدام HTTPS/localhost.';
    switch (err.name) {
      case 'NotAllowedError': case 'PermissionDeniedError': return base + 'تم رفض الإذن. الرجاء منح إذن الوصول للكاميرا.';
      case 'NotFoundError': case 'DevicesNotFoundError': return base + 'لم يتم العثور على أي كاميرا متاحة.';
      case 'NotReadableError': case 'TrackStartError': return base + 'الجهاز مشغول بواسطة تطبيق آخر.';
      case 'OverconstrainedError': case 'ConstraintNotSatisfiedError': return base + 'الإعدادات المطلوبة لا تتوافق مع أي كاميرا.';
      case 'SecurityError': return base + 'المتصفح منع الوصول لأسباب أمنية. استخدم HTTPS/localhost.';
      default: return base + 'خطأ غير متوقع: ' + err.name;
    }
  }

  async function requestStream(constraints){
    if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
      return navigator.mediaDevices.getUserMedia(constraints);
    }
    var legacy = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
    if (legacy) {
      return new Promise(function(resolve, reject){ legacy.call(navigator, constraints, resolve, reject); });
    }
    throw new Error('متصفحك لا يدعم getUserMedia.');
  }

  async function populateCameras(preserve){
    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices || !cameraSelect) return;
      var prev = preserve ? cameraSelect.value : '';
      var devices = await navigator.mediaDevices.enumerateDevices();
      var cams = devices.filter(function(d){ return d.kind === 'videoinput'; });
      cameraSelect.innerHTML = '';
      var opt0 = document.createElement('option'); opt0.value=''; opt0.textContent='اختيار الكاميرا...'; cameraSelect.appendChild(opt0);
      cams.forEach(function(cam, idx){ var o=document.createElement('option'); o.value=cam.deviceId||''; o.textContent=(cam.label&&cam.label.trim())?cam.label:('كاميرا ' + (idx+1)); cameraSelect.appendChild(o); });
      if (prev) {
        var found = Array.prototype.some.call(cameraSelect.options, function(o){ return o.value === prev; });
        if (found) cameraSelect.value = prev;
      }
    } catch(e){ console.warn('enumerateDevices failed', e); }
  }

  async function performCapture(){
    try {
      if (captureBtn) { captureBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>جاري الالتقاط...'; captureBtn.disabled = true; }
      var constraints = { video: { facingMode: 'user' }, audio: false };
      if (cameraSelect && cameraSelect.value) { constraints = { video: { deviceId: { exact: cameraSelect.value } }, audio: false }; }
      var stream = await requestStream(constraints);
      streamRef = stream; video.srcObject = stream; if (!video.classList.contains('d-none')) video.classList.add('d-none');
      await new Promise(function(resolve){ if (video.readyState>=2 && video.videoWidth) return resolve(); var onLoaded=function(){ if(video.videoWidth) resolve(); video.removeEventListener('loadedmetadata', onLoaded); video.removeEventListener('canplay', onLoaded); }; video.addEventListener('loadedmetadata', onLoaded); video.addEventListener('canplay', onLoaded); });
      var cssSize = 120, ratio = window.devicePixelRatio || 1; canvas.width = cssSize*ratio; canvas.height = cssSize*ratio; canvas.style.width = cssSize+'px'; canvas.style.height = cssSize+'px'; var ctx = canvas.getContext('2d'); ctx.setTransform(ratio,0,0,ratio,0,0);
      var vw = video.videoWidth||640, vh = video.videoHeight||480; var size = Math.min(vw, vh); var sx=(vw-size)/2, sy=(vh-size)/2; ctx.drawImage(video, sx, sy, size, size, 0, 0, cssSize, cssSize);
      var dataUrl = canvas.toDataURL('image/png'); hidden.value = dataUrl; img.src = dataUrl; img.classList.remove('d-none');
      if (cameraOverlay) { cameraOverlay.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>'; cameraOverlay.title = 'إعادة التقاط'; }
      if (captureBtn) captureBtn.classList.add('d-none'); if (retakeBtn) retakeBtn.classList.remove('d-none');
    } catch(e){ showTopRightAlert('danger', '❌ ' + mapGetUserMediaError(e)); }
    finally { stopStream(); if (captureBtn) { captureBtn.innerHTML = '<i class="bi bi-camera-fill me-1"></i>التقاط'; captureBtn.disabled = false; } }
  }

  if (captureBtn) captureBtn.addEventListener('click', function(){ performCapture(); });
  if (retakeBtn) retakeBtn.addEventListener('click', function(){ hidden.value=''; if (cameraOverlay){ cameraOverlay.innerHTML='<i class="bi bi-camera-fill"></i>'; cameraOverlay.title='التقاط صورة'; } if (captureBtn) captureBtn.classList.remove('d-none'); if (retakeBtn) retakeBtn.classList.add('d-none'); performCapture(); });
  if (cameraOverlay) cameraOverlay.addEventListener('click', function(){ if (cameraOverlay.innerHTML.includes('arrow-counterclockwise')) { if (retakeBtn) retakeBtn.click(); } else { performCapture(); } });

  // تعداد الكاميرات ومتابعة تغييرات الأجهزة
  populateCameras(false);
  if (navigator.mediaDevices) {
    if ('ondevicechange' in navigator.mediaDevices) { navigator.mediaDevices.ondevicechange = function(){ populateCameras(true); }; }
    else if (navigator.mediaDevices.addEventListener) { navigator.mediaDevices.addEventListener('devicechange', function(){ populateCameras(true); }); }
  }
});

// زر إغلاق المودال
(function(){
  var btnClose = document.getElementById('btnClose');
  if(!btnClose) return;
  btnClose.addEventListener('click', function(){
    try{ window.parent.postMessage({ source:'edit_member_modal', type:'close' }, '*'); }catch(e){}
    window.close();
  });
})();

// تبديل إظهار/إخفاء كلمة المرور
(function(){
  var toggle = document.getElementById('toggleIcon');
  var input = document.getElementById('mem_password');
  if(toggle && input){
    toggle.addEventListener('click', function(){
      if(input.type === 'password'){
        input.type = 'text';
        toggle.classList.remove('fa-eye');
        toggle.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        toggle.classList.remove('fa-eye-slash');
        toggle.classList.add('fa-eye');
      }
    });
  }
})();
