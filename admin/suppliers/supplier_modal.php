<!-- Modal: Create Supplier -->
<div class="modal fade" id="createSupplierModal" tabindex="-1" aria-hidden="true" dir="rtl">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        <h5 class="modal-title">إضافة مورد جديد</h5>
      </div>
      <div class="modal-body">
        <form id="createSupplierForm">
          <div class="mb-2">
            <label class="form-label">رقم المورد</label>
            <input type="number" class="form-control" id="cs_sup_no" name="sup_no" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">اسم المورد</label>
            <input type="text" class="form-control" id="cs_sup_name" name="sup_name" required>
          </div>
          <div class="mb-2">
            <label class="form-label">العنوان</label>
            <input type="text" class="form-control" id="cs_sup_address" name="sup_address">
          </div>
          <div class="mb-2">
            <label class="form-label">البريد الإلكتروني</label>
            <input type="email" class="form-control" id="cs_sup_email" name="sup_email">
          </div>
          <div class="mb-2">
            <label class="form-label">الهاتف</label>
            <input type="text" class="form-control" id="cs_sup_tel" name="sup_tel">
          </div>
          <div id="cs_msg" class="small mt-2"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" id="saveSupplierBtn">حفظ</button>
      </div>
    </div>
  </div>
</div>
