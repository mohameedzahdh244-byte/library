
$(document).ready(function () {
    // توليد كلمة مرور قوية واقتراحها تلقائياً في حقل كلمة المرور
    function generateStrongPassword(len){
        len = (typeof len === 'number' && len >= 8) ? len : 12;
        var lowers = 'abcdefghijkmnopqrstuvwxyz'; // بدون l
        var uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';  // بدون I,O
        var digits = '23456789';                 // بدون 0,1
        var symbols = '!@#$%^&*';
        function pick(str){ return str.charAt(Math.floor(Math.random()*str.length)); }
        var pwd = [ pick(lowers), pick(uppers), pick(digits) ];
        var pool = lowers + uppers + digits + symbols;
        for (var i = pwd.length; i < len; i++) pwd.push(pick(pool));
        // خلط الأحرف
        for (var j = pwd.length - 1; j > 0; j--) {
            var k = Math.floor(Math.random()*(j+1));
            var t = pwd[j]; pwd[j] = pwd[k]; pwd[k] = t;
        }
        return pwd.join('');
    }
    // لا نقوم بتعبئة كلمة المرور تلقائياً عند التحميل بناءً على تفضيلك
    // زر إظهار/إخفاء كلمة المرور (نفس منطق تسجيل الدخول)
    const togglePasswordIcon = document.querySelector('#togglePwdIcon');
    const passwordInput = document.getElementById('mem_password');

    if (togglePasswordIcon && passwordInput) {
        togglePasswordIcon.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    }
    // زر توليد كلمة مرور جديدة
    $('#regenPwdBtn').on('click', function(){
        var $pwd = $('#mem_password');
        if ($pwd.length === 0) return;
        var newPwd = generateStrongPassword(8);
        $pwd.val(newPwd).trigger('input');
        // وميض خفيف للدلالة على التحديث
        try {
            $pwd.addClass('is-valid');
            setTimeout(function(){ $pwd.removeClass('is-valid'); }, 800);
        } catch(e){}
    });
    // أدوات أرقام الهاتف المتعددة
    function getPhoneValues(){
        return $('.phone-input').map(function(){ return $(this).val().trim(); }).get();
    }
    function syncPrimaryPhoneHidden(){
        const phones = getPhoneValues().filter(p=>p!== '');
        $('#mem_phone_hidden').val(phones.length ? phones[0] : '');
    }
    function ensureAtLeastOneRow(){
        if ($('.phone-row').length === 0) {
            const rowHtml = `
              <div class="phone-row position-relative">
                <i class="bi bi-telephone-fill field-icon start"></i>
                <input type="text" class="form-control phone-input" name="phones[]" placeholder="أدخل رقم الجوال" />
                <button type="button" class="icon-btn add-phone text-success" aria-label="add"><i class="bi bi-plus-lg"></i></button>
              </div>`;
            $('#phonesRepeater').append(rowHtml);
        }
    }

    // إضافة رقم جديد (تفويض على الحاوية لتفادي مشاكل الالتقاط)
    $('#phonesRepeater').on('click', '.add-phone', function(){
        console.debug('add-phone clicked');
        const rowHtml = `
          <div class="phone-row position-relative">
            <i class="bi bi-telephone-fill field-icon start"></i>
            <input type="text" class="form-control phone-input" name="phones[]" placeholder="أدخل رقم الجوال" />
            <button type="button" class="icon-btn remove-phone text-danger" aria-label="remove"><i class="bi bi-dash-lg"></i></button>
          </div>`;
        $('#phonesRepeater').append(rowHtml);
        // تركيز على آخر حقل مضاف
        $('#phonesRepeater .phone-row:last .phone-input').focus();
    });

    // حذف رقم (تفويض على الحاوية)
    $('#phonesRepeater').on('click', '.remove-phone', function(){
        console.debug('remove-phone clicked');
        $(this).closest('.phone-row').remove();
        ensureAtLeastOneRow();
        syncPrimaryPhoneHidden();
    });

    // لا نستخدم tooltips لزر الإضافة/الحذف حسب طلبك

    // مزامنة الرقم الأساسي عند الكتابة
    $(document).on('input change', '.phone-input', function(){
        syncPrimaryPhoneHidden();
    });

    // تقديم النموذج
    $('#addCustomerForm').on('submit', function (e) {
        console.log('Form submitted');
        e.preventDefault();

        const actionType = $(document.activeElement).val();

        // تأكد أن الحقل المخفي يحمل أول رقم
        syncPrimaryPhoneHidden();
        // تحقّق بسيط: وجود رقم واحد على الأقل
        const firstPhone = $('#mem_phone_hidden').val().trim();
        if (!firstPhone){
            $('#customerMessageArea').html(`
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    الرجاء إدخال رقم جوال واحد على الأقل.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                </div>
            `);
            return;
        }

        $.ajax({
          url: '../../../admin/customer/addcustomer_process.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                const alertBox = `
                    <div class="alert alert-${response.type} alert-dismissible fade show" role="alert">
                        ${response.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                `;

                $('#customerMessageArea').html(alertBox);

                // إخفاء التنبيه بعد 3 ثواني
                setTimeout(() => {
                    const alert = document.querySelector('#customerMessageArea .alert');
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
                        $('#addCustomerForm')[0].reset();
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

                $('#customerMessageArea').html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ${errorMessage}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                `);

               
            }
        });
    });
    
});

// subscription_status.js
// تحديث حالة الاشتراك تلقائياً بناءً على التواريخ
$(document).ready(function() {
    var statusChangedByUser = false;
    function setStatusColor(val) {
        var color = 'black';
        if (val === 'ساري') color = 'green';
        else if (val === 'منتهي') color = 'red';
        else if (val === 'موقوف') color = 'gray';
        $('#atatus').css({color: color, fontWeight: 'bold'});
    }
    function updateStatusAuto() {
        if (statusChangedByUser) return;
        var statusSelect = $('#atatus');
        var start = $('#start_date').val();
        var end = $('#end_date').val();
        var now = new Date();
        var startDate = start ? new Date(start) : null;
        var endDate = end ? new Date(end) : null;
        if (startDate && endDate) {
            if (now >= startDate && now <= endDate) {
                statusSelect.val('ساري');
            } else {
                statusSelect.val('منتهي');
            }
        }
        setStatusColor(statusSelect.val());
    }
    $('#start_date, #end_date').on('change', function() {
        statusChangedByUser = false;
        updateStatusAuto();
    });
    $('#atatus').on('change', function() {
        setStatusColor($(this).val());
        statusChangedByUser = true;
    });
    
    // عند تحميل الصفحة
    updateStatusAuto();
});

// auto_fee.js
// حساب تلقائي للرسوم حسب العمر مع إمكانية التعديل اليدوي
$(document).ready(function(){
  var $birthEl = $('#mem_birth');
  var $amountEl = $('#amount');
  if ($birthEl.length === 0 || $amountEl.length === 0) return;

  if (!$amountEl.attr('data-autofilled')) {
    $amountEl.attr('data-autofilled','1');
  }

  function calcFeeFromBirth(birthStr){
    if (!birthStr) return null;
    var b = new Date(birthStr);
    if (isNaN(b.getTime())) return null;
    var today = new Date();
    var age = today.getFullYear() - b.getFullYear();
    var m = today.getMonth() - b.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < b.getDate())) age--;
    if (age < 0) return null;
    return age < 12 ? 6 : 12;
  }

  function maybeAutofill(){
    var fee = calcFeeFromBirth($birthEl.val());
    if (fee == null) return;
    var auto = $amountEl.attr('data-autofilled') === '1';
    if (auto || $amountEl.val() === ''){
      $amountEl.val(fee);
      $amountEl.attr('data-autofilled','1');
    }
  }

  $birthEl.on('change input', maybeAutofill);
  $amountEl.on('input', function(){
    if ($amountEl.val() === '') {
      $amountEl.attr('data-autofilled','1');
    } else {
      $amountEl.attr('data-autofilled','0');
    }
  });
});

// close_embed.js
// زر الإغلاق للنموذج المضمّن
$(document).ready(function(){
  var btn = document.getElementById('closeEmbedCustomer');
  if (!btn) return;
  btn.addEventListener('click', function(){
    try {
      var pwin = window.parent || null;
      var pdoc = pwin && pwin.document;
      if (!pdoc) { if (document.referrer) { history.back(); } else { window.close(); } return; }
      var closed = false;
      var ids = ['customersModal','booksModal','borrowModal'];
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
        } catch(e) { /* ignore */ }
      }
      if (!closed){ if (document.referrer) { history.back(); } else { window.close(); } }
    } catch (e) { if (document.referrer) { history.back(); } else { window.close(); } }
  });
});

// camera_capture.js - التقاط صورة بالكاميرا وتعبئتها في personal_photo كـ Base64
$(document).ready(function(){
  var startBtn = document.getElementById('startCameraBtn');
  var captureBtn = document.getElementById('captureBtn');
  var retakeBtn = document.getElementById('retakeBtn');
  var video = document.getElementById('cameraStream');
  var img = document.getElementById('photoPreview');
  var canvas = document.getElementById('captureCanvas');
  var hidden = document.getElementById('personal_photo');
  var cameraSelect = document.getElementById('cameraSelect');
  var cameraOverlay = document.getElementById('cameraOverlay');
  var streamRef = null;

  // نسمح بغياب startBtn لأننا نلتقط الصور مباشرة بدون زر تشغيل
  if (!video || !img || !canvas || !hidden) return;

  function stopStream(){
    try { if (streamRef) streamRef.getTracks().forEach(function(t){ t.stop(); }); } catch(e) {}
    streamRef = null;
  }

  // طلب وسائط الكاميرا بأمان مع دعم المتصفحات القديمة
  async function requestStream(constraints){
    // دعم حديث
    
    if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
      return navigator.mediaDevices.getUserMedia(constraints);
    }
    // سياق غير آمن قد يعطّل mediaDevices بالكامل
    if (location && location.protocol !== 'https:' && 
        location.hostname !== 'localhost' && 
        location.hostname !== '127.0.0.1' && 
        !location.hostname.startsWith('192.168.') &&
        !location.hostname.startsWith('10.') &&
        !location.hostname.endsWith('.local')) {
      throw new Error('يتطلب الوصول للكاميرا اتصالًا آمنًا (HTTPS) أو تشغيلًا على localhost.');
    }
    // Fallback قديم
    var legacy = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
    if (legacy) {
      return new Promise(function(resolve, reject){
        legacy.call(navigator, constraints, resolve, reject);
      });
    }
    throw new Error('متصفحك لا يدعم getUserMedia. حدّث المتصفح أو استخدم آخر.');
  }

  async function populateCameras(preserve){
    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices || !cameraSelect) return;
      var prev = preserve ? cameraSelect.value : '';
      const devices = await navigator.mediaDevices.enumerateDevices();
      const cams = devices.filter(function(d){ return d.kind === 'videoinput'; });
      // إعادة بناء القائمة
      cameraSelect.innerHTML = '';
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'اختيار الكاميرا...';
      cameraSelect.appendChild(placeholder);
      cams.forEach(function(cam, idx){
        var opt = document.createElement('option');
        opt.value = cam.deviceId || '';
        var label = cam.label && cam.label.trim() !== '' ? cam.label : ('كاميرا ' + (idx+1));
        opt.textContent = label;
        cameraSelect.appendChild(opt);
      });
      // استعادة الاختيار السابق إن أمكن
      if (prev) {
        var found = Array.prototype.some.call(cameraSelect.options, function(o){ return o.value === prev; });
        if (found) cameraSelect.value = prev;
      }
    } catch(err){
      // تجاهل الأخطاء الصامتة (قبل منح الإذن قد تكون labels فارغة)
      console.warn('enumerateDevices failed or blocked', err);
    }
  }

  // أداة لعرض رسالة في أعلى النموذج
  function showMessage(type, text) {
    var area = document.getElementById('customerMessageArea');
    if (!area) return alert(text);
    area.innerHTML = (
      '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
      text +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>' +
      '</div>'
    );
    // إخفاء تلقائي بعد 4 ثوانٍ
    setTimeout(function(){
      try {
        var alertEl = area.querySelector('.alert');
        if (alertEl) {
          var bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
          bsAlert.close();
        }
      } catch(e){}
    }, 4000);
  }

  function mapGetUserMediaError(err){
    var base = 'تعذر تشغيل الكاميرا. ';
    if (!err || !err.name) return base + 'تأكد من منح الإذن واستخدام HTTPS/localhost.';
    switch (err.name) {
      case 'NotAllowedError':
      case 'PermissionDeniedError':
        return base + 'تم رفض الإذن. الرجاء منح إذن الوصول للكاميرا.';
      case 'NotFoundError':
      case 'DevicesNotFoundError':
        return base + 'لم يتم العثور على أي كاميرا متاحة.';
      case 'NotReadableError':
      case 'TrackStartError':
        return base + 'الجهاز مشغول بواسطة تطبيق آخر.';
      case 'OverconstrainedError':
      case 'ConstraintNotSatisfiedError':
        return base + 'الإعدادات المطلوبة لا تتوافق مع أي كاميرا.';
      case 'SecurityError':
        return base + 'المتصفح منع الوصول لأسباب أمنية. استخدم HTTPS/localhost.';
      default:
        return base + 'خطأ غير متوقع: ' + err.name;
    }
  }

  if (startBtn) startBtn.addEventListener('click', async function(){
    try {
      // تحديث النص والأيقونة أثناء التحميل
      startBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>جاري التشغيل...';
      startBtn.disabled = true;
      
      var constraints = { video: { facingMode: 'user' }, audio: false };
      if (cameraSelect && cameraSelect.value) {
        constraints = { video: { deviceId: { exact: cameraSelect.value } }, audio: false };
      }
      const stream = await requestStream(constraints);
      streamRef = stream;
      video.srcObject = stream;
      
      // تبديل العرض مع تأثيرات
      img.classList.add('d-none');
      video.classList.remove('d-none');
      if (cameraStatus) cameraStatus.classList.remove('d-none');
      
      // تحديث الأزرار
      startBtn.classList.add('d-none');
      captureBtn.classList.remove('d-none');
      retakeBtn.classList.add('d-none');
      
      // بعد منح الإذن ستظهر تسميات الكاميرات
      populateCameras(true);
    } catch(err){
      console.error(err);
      showMessage('danger', '❌ ' + mapGetUserMediaError(err));
      // إعادة تعيين الزر في حالة الخطأ
      startBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i>تشغيل';
      startBtn.disabled = false;
    }
  });

  // التقاط صورة فقط بدون معاينة فيديو مباشرة
  async function performCapture(){
    try {
      // تعطيل زر الالتقاط أثناء العملية
      if (captureBtn) {
        captureBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>جاري الالتقاط...';
        captureBtn.disabled = true;
      }

      // فتح الكاميرا مؤقتاً
      var constraints = { video: { facingMode: 'user' }, audio: false };
      if (cameraSelect && cameraSelect.value) {
        constraints = { video: { deviceId: { exact: cameraSelect.value } }, audio: false };
      }
      const stream = await requestStream(constraints);
      streamRef = stream;
      video.srcObject = stream;
      // لا نعرض الفيديو للمستخدم
      if (!video.classList.contains('d-none')) video.classList.add('d-none');

      // الانتظار حتى تصبح أبعاد الفيديو متاحة
      await new Promise(function(resolve){
        if (video.readyState >= 2 && video.videoWidth) return resolve();
        var onLoaded = function(){
          if (video.videoWidth) resolve();
          video.removeEventListener('loadedmetadata', onLoaded);
          video.removeEventListener('canplay', onLoaded);
        };
        video.addEventListener('loadedmetadata', onLoaded);
        video.addEventListener('canplay', onLoaded);
      });

      // إعداد Canvas عالي الدقة
      var cssSize = 120;
      var ratio = window.devicePixelRatio || 1;
      canvas.width = cssSize * ratio;
      canvas.height = cssSize * ratio;
      canvas.style.width = cssSize + 'px';
      canvas.style.height = cssSize + 'px';
      var ctx = canvas.getContext('2d');
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      var vw = video.videoWidth || 640;
      var vh = video.videoHeight || 480;
      var size = Math.min(vw, vh);
      var sx = (vw - size) / 2;
      var sy = (vh - size) / 2;
      // لا نعكس الصورة النهائية
      ctx.drawImage(video, sx, sy, size, size, 0, 0, cssSize, cssSize);
      var dataUrl = canvas.toDataURL('image/png');
      hidden.value = dataUrl;
      img.src = dataUrl;
      img.classList.remove('d-none');

      // تحديث الأيقونة والأزرار
      if (cameraOverlay) {
        cameraOverlay.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
        cameraOverlay.title = 'إعادة التقاط';
      }
      if (captureBtn) captureBtn.classList.add('d-none');
      if (retakeBtn) retakeBtn.classList.remove('d-none');
    } catch(e){
      console.error(e);
      showMessage('danger', '❌ ' + mapGetUserMediaError(e));
    } finally {
      // إيقاف الكاميرا فوراً بعد الالتقاط
      stopStream();
      if (captureBtn) {
        captureBtn.innerHTML = '<i class="bi bi-camera-fill me-1"></i>التقاط الصورة';
        captureBtn.disabled = false;
      }
    }
  }

  if (captureBtn) captureBtn.addEventListener('click', function(){
    performCapture();
  });

  if (retakeBtn) retakeBtn.addEventListener('click', function(){
    hidden.value = '';
    if (cameraOverlay) {
      cameraOverlay.innerHTML = '<i class="bi bi-camera-fill"></i>';
      cameraOverlay.title = 'التقاط صورة';
    }
    // إظهار زر الالتقاط مرة أخرى ثم الالتقاط مباشرة أو انتظار المستخدم
    if (captureBtn) captureBtn.classList.remove('d-none');
    if (retakeBtn) retakeBtn.classList.add('d-none');
    // تنفيذ التقاط جديد مباشرة لتقليل الخطوات
    performCapture();
  });

  // دعم النقر على أيقونة الكاميرا
  if (cameraOverlay) {
    cameraOverlay.addEventListener('click', function(){
      if (cameraOverlay.innerHTML.includes('arrow-counterclockwise')) {
        // إعادة التقاط
        if (retakeBtn) retakeBtn.click();
      } else {
        // التقاط صورة بدون تشغيل معاينة
        performCapture();
      }
    });
  }

  // تعداد الكاميرات عند الجاهزية، وعند تغير الأجهزة
  populateCameras(false);
  if (navigator.mediaDevices) {
    if ('ondevicechange' in navigator.mediaDevices) {
      navigator.mediaDevices.ondevicechange = function(){ populateCameras(true); };
    } else if (navigator.mediaDevices.addEventListener) {
      navigator.mediaDevices.addEventListener('devicechange', function(){ populateCameras(true); });
    }
  }
});

// تهيئة Bootstrap Tooltips للعناصر ذات data-bs-toggle="tooltip"
$(document).ready(function(){
  try {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(el){
      bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body' });
    });
  } catch(e) { /* ignore */ }
});