(function(){
  $(function(){
    var $input = $('#supplier_search');
    var $hidden = $('#sup_no');
    var $res = $('#supplier_result');
    var $msg = $('#supplier_message');

    function showMsg(type, text) {
      $msg.removeClass().addClass('alert alert-' + type).text(text).show();
      setTimeout(function(){ $msg.fadeOut(); }, 3000);
    }

    // Search suppliers
    $input.on('input', function(){
      var q = $.trim($(this).val());
      if (q.length < 2) { $res.hide().empty(); return; }
      $.get('/admin/suppliers/search_supplier_ajax.php', { q: q }, function(data){
        if (!data || !data.results) { $res.hide().empty(); return; }
        if (data.results.length === 0) {
          var emptyHtml = '<div class="list-group-item">\
             <div class="d-flex justify-content-between align-items-center">\
               <span class="text-muted">لا توجد نتائج</span>\
               <button type="button" class="btn btn-sm btn-outline-primary openCreateSupplier">إضافة مورد</button>\
             </div>\
           </div>';
          $res.html(emptyHtml).show();
          return;
        }
        var html = '';
        $.each(data.results, function(_, it){
          html += '<a href="#" class="list-group-item list-group-item-action supplier-item" data-sup-no="'+ it.sup_no +'" data-sup-name="'+ $('<div>').text(it.sup_name).html() +'">' +
                  '<div class="d-flex justify-content-between align-items-center">' +
                  '<span>' + $('<div>').text(it.sup_name).html() + '</span>' +
                  '<span class="badge bg-secondary">'+ it.sup_no +'</span>' +
                  '</div>' +
                  '</a>';
        });
        html += '<div class="list-group-item">\
                   <button type="button" class="btn btn-outline-primary w-100 openCreateSupplier">إضافة مورد</button>\
                 </div>';
        $res.html(html).show();
      }, 'json').fail(function(xhr, textStatus, errorThrown){
        if (textStatus === 'parsererror' && xhr && xhr.responseText) {
          console.error('Supplier search parsererror. Raw response:', xhr.responseText);
        } else {
          console.error('Supplier search failed:', {textStatus: textStatus, error: errorThrown, status: xhr && xhr.status, xhr: xhr});
        }
        $res.html(
          '<div class="list-group-item">\
             <div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">\
               <i class="bi bi-x-octagon"></i>\
               <div>تعذر الاتصال بالخادم</div>\
             </div>\
           </div>'
        ).show();
      });
    });

    // pick supplier
    $(document).on('click', '.supplier-item', function(e){
      e.preventDefault();
      var no = $(this).data('sup-no');
      var name = $(this).data('sup-name');
      $hidden.val(no);
      $input.val(name);
      $res.hide().empty();
      showMsg('success', 'تم اختيار المورد');
    });

    // click outside closes results
    $(document).on('click', function(e){
      if (!$(e.target).closest('#supplier_result, #supplier_search').length){
        $res.hide();
      }
    });

    // open create supplier modal
    $(document).on('click', '.openCreateSupplier', function(){
      $('#cs_msg').removeClass().empty();
      $('#createSupplierForm')[0].reset();
      // fetch next number
      $.get('/admin/suppliers/create_supplier_ajax.php', { action: 'next_no' }, function(resp){
        if (resp && resp.success) {
          $('#cs_sup_no').val(resp.next_no);
        }
        var modal = new bootstrap.Modal(document.getElementById('createSupplierModal'));
        modal.show();
      }, 'json').fail(function(){
        var modal = new bootstrap.Modal(document.getElementById('createSupplierModal'));
        modal.show();
      });
    });

    // save supplier
    $(document).on('click', '#saveSupplierBtn', function(){
      var $btn = $(this).prop('disabled', true);
      var noVal = $('#cs_sup_no').val();
      var nameVal = $('#cs_sup_name').val();
      if (!noVal || !nameVal) {
        $('#cs_msg').removeClass().addClass('alert alert-warning').text('الرجاء تعبئة رقم واسم المورد');
        $btn.prop('disabled', false);
        return;
      }
      var formData = $('#createSupplierForm').serialize();
      $.post('/admin/suppliers/create_supplier_ajax.php', formData, function(resp){
        if (resp && resp.success) {
          $('#supplier_search').val(resp.supplier.sup_name);
          $('#sup_no').val(resp.supplier.sup_no);
          $('#createSupplierModal').modal('hide');
          showMsg('success', 'تم حفظ المورد بنجاح');
        } else {
          $('#cs_msg').removeClass().addClass('alert alert-danger').text(resp && resp.message ? resp.message : 'تعذر حفظ المورد');
        }
      }, 'json').fail(function(xhr){
        var t = (xhr && xhr.responseText) ? xhr.responseText : '';
        console.error('create supplier failed', t);
        $('#cs_msg').removeClass().addClass('alert alert-danger').text('تعذر حفظ المورد');
      }).always(function(){
        $btn.prop('disabled', false);
      });
    });
  });
})();
