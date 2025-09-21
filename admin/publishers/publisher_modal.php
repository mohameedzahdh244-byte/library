<!-- Modal: Create Publisher -->
<div class="modal fade" id="createPublisherModal" tabindex="-1" aria-hidden="true" dir="rtl">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        <h5 class="modal-title">إضافة ناشر جديد</h5>
      </div>
      <div class="modal-body">
        <form id="createPublisherForm">
          <div class="mb-2">
            <label class="form-label">رقم الناشر</label>
            <input type="number" class="form-control" id="cp_pub_no" name="pub_no" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">اسم الناشر</label>
            <input type="text" class="form-control" id="cp_pub_name" name="pub_name" required>
          </div>
          <div class="mb-2">
            <label class="form-label">العنوان</label>
            <input type="text" class="form-control" id="cp_pub_address" name="pub_address">
          </div>
          <div class="mb-2">
            <label class="form-label">البريد الإلكتروني</label>
            <input type="email" class="form-control" id="cp_pub_email" name="pub_email">
          </div>
          <div class="mb-2">
            <label class="form-label">الهاتف</label>
            <input type="text" class="form-control" id="cp_pub_tel" name="pub_tel">
          </div>
          <div id="cp_msg" class="small mt-2"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" id="savePublisherBtn">حفظ</button>
      </div>
    </div>
  </div>
</div>
