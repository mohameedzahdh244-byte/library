// تم إلغاء إعادة التحميل التلقائي نهائياً للحفاظ على تجربة المستخدم

// عرض التوستات تلقائياً عند وجود رسائل
window.addEventListener('DOMContentLoaded', function() {
  var toastEls = document.querySelectorAll('#toastContainer .toast');
  toastEls.forEach(function(el){
    try { new bootstrap.Toast(el).show(); } catch (e) {}
  });
});

// تأكيد إلغاء الحجز
function confirmCancel(reservationId) {
  if (confirm('هل أنت متأكد من إلغاء هذا الحجز؟')) {
    var form = document.getElementById('cancelForm' + reservationId);
    form && form.submit();
  }
}
