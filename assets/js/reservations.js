// تم إلغاء إعادة التحميل التلقائي للحفاظ على تجربة المستخدم

// Auto-show Bootstrap toasts if present
window.addEventListener('DOMContentLoaded', function () {
  var toastEls = document.querySelectorAll('#toastContainer .toast');
  toastEls.forEach(function (el) {
    try {
      var t = new bootstrap.Toast(el);
      t.show();
    } catch (e) {}
  });
});

// Confirm cancel helper (not wired by default; inline confirm exists on button)
function confirmCancel(reservationId) {
  if (confirm('هل أنت متأكد من إلغاء هذا الحجز؟')) {
    var form = document.getElementById('cancel-form-' + reservationId);
    if (form) form.submit();
  }
}
