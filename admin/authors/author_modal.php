<!-- Modal: Create Author -->
<div class="modal fade" id="createAuthorModal" tabindex="-1" aria-hidden="true" dir="rtl">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        <h5 class="modal-title">إضافة مؤلف جديد</h5>
      </div>
      <div class="modal-body">
        <form id="createAuthorForm">
          <div class="mb-2">
            <label class="form-label">رقم المؤلف</label>
            <input type="number" class="form-control" id="ca_ANO" name="ANO" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">اسم المؤلف</label>
            <input type="text" class="form-control" id="ca_Aname" name="Aname" required>
          </div>
          <div id="ca_msg" class="small mt-2"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" id="saveAuthorBtn">حفظ</button>
      </div>
    </div>
  </div>
</div>
