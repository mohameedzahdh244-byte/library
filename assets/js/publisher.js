$(function(){
  var $input = $('#publisher_search');
  var $res = $('#publisher_result');
  var $hidden = $('#pub_no');
  var $msg = $('#publisher_message');
  var timer = null;

  function highlight(text, term){
    if (!term) return text;
    try {
      var rx = new RegExp('('+ term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') +')','ig');
      return text.replace(rx, '<mark>$1</mark>');
    } catch(e){ return text; }
  }

  // Search input
  $input.on('input', function(){
    var q = ($input.val() || '').trim();
    $msg.hide().text('');
    clearTimeout(timer);
    $hidden.val('');
    if (q.length < 2) {
      $res.html(
        '<div class="list-group-item bg-light">\
           <div class="alert alert-info d-flex align-items-center gap-2 mb-0 py-2">\
             <i class="bi bi-info-circle"></i>\
             <div>اكتب <strong>حرفين على الأقل</strong> للبحث عن ناشر</div>\
           </div>\
         </div>'
      ).show();
      return;
    }
    $res.html(
      '<div class="list-group-item bg-light">\
         <div class="d-flex justify-content-center align-items-center gap-2 py-2">\
           <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>\
           <span class="text-muted">جاري البحث...</span>\
         </div>\
       </div>'
    ).show();
    timer = setTimeout(function(){
      $.get('/admin/publishers/search_publisher_ajax.php', { q: q }, function(data){
        var items = (data && data.results) ? data.results : [];
        if (!items.length) {
          $res.html(
            '<div class="list-group-item">\
               <div class="alert alert-warning d-flex align-items-center gap-2 mb-2 py-2">\
                 <i class="bi bi-exclamation-triangle"></i>\
                 <div>لا توجد نتائج مطابقة</div>\
               </div>\
               <button type="button" class="btn btn-outline-primary w-100 openCreatePublisher">\
                 <i class="bi bi-plus-circle"></i> إضافة ناشر جديد\
               </button>\
             </div>'
          ).show();
          return;
        }
        var html = '';
        items.forEach(function(it){
          var safeText = $('<div>').text(it.text || '').html();
          var marked = highlight(safeText, q);
          html += '<a href="#" class="list-group-item list-group-item-action item d-flex justify-content-between align-items-center" data-id="'+it.id+'" data-text="'+safeText+'">'+
                  '<span>'+marked+'</span>'+
                  '<i class="bi bi-check2-square text-secondary opacity-50"></i>'+
                  '</a>';
        });
        $res.html(html).show();
      }, 'json').fail(function(){
        $res.html(
          '<div class="list-group-item">\
             <div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">\
               <i class="bi bi-x-octagon"></i>\
               <div>تعذر الاتصال بالخادم</div>\
             </div>\
           </div>'
        ).show();
      });
    }, 250);
  });

  // Pick item
  $(document).on('click', '#publisher_result .item', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    var text = $(this).data('text');
    $hidden.val(id);
    $input.val(text);
    $res.hide().empty();
  });

  // Hide on outside click
  $(document).on('click', function(e){
    if (!$(e.target).closest('#publisher_result, #publisher_search').length){
      $res.hide();
    }
  });

  // Open modal (always available)
  $(document).on('click', '.openCreatePublisher', function(){
    $('#cp_msg').empty();
    var form = document.getElementById('createPublisherForm');
    if (form) form.reset();
    $.get('/admin/publishers/create_publisher_ajax.php', { action: 'next_no' }, function(resp){
      if (resp && resp.success) { $('#cp_pub_no').val(resp.next_no); } else { $('#cp_pub_no').val(''); }
      var modal = new bootstrap.Modal(document.getElementById('createPublisherModal'));
      modal.show();
      setTimeout(function(){ $('#cp_pub_name').trigger('focus'); }, 200);
    }, 'json').fail(function(){
      $('#cp_pub_no').val('');
      var modal = new bootstrap.Modal(document.getElementById('createPublisherModal'));
      modal.show();
      $('#cp_msg').html('\
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2">\
          <i class="bi bi-exclamation-circle"></i>\
          <div>تعذر جلب الرقم التالي، يمكنك إدخاله يدويًا</div>\
        </div>'
      );
    });
  });

  // Save new publisher
  $(document).on('click', '#savePublisherBtn', function(){
    var data = {
      pub_no: $('#cp_pub_no').val(),
      pub_name: $('#cp_pub_name').val(),
      pub_address: $('#cp_pub_address').val(),
      pub_email: $('#cp_pub_email').val(),
      pub_tel: $('#cp_pub_tel').val()
    };
    if (!data.pub_name || !data.pub_no) {
      $('#cp_msg')
        .html(
          '<div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2">\
             <i class="bi bi-exclamation-triangle"></i>\
             <div>يرجى إدخال <strong>رقم</strong> و<strong>اسم</strong> الناشر</div>\
           </div>'
        )
        .show();
      setTimeout(function(){
        $('#cp_msg').fadeOut(200, function(){ $(this).empty().show(); });
      }, 3000);
      return;
    }
    $('#savePublisherBtn').prop('disabled', true).text('جارٍ الحفظ...');
    $.post('/admin/publishers/create_publisher_ajax.php', data, function(resp){
      if (resp && resp.success && resp.publisher) {
        $('#pub_no').val(resp.publisher.pub_no);
        $('#publisher_search').val(resp.publisher.pub_name);
        $('#publisher_result').hide().empty();
        $('#publisher_message')
          .html('\
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-0 small" role="status" aria-live="polite">\
              <i class="bi bi-check-circle-fill"></i>\
              <div><strong>تم الحفظ:</strong> تمت إضافة الناشر بنجاح</div>\
            </div>'
          )
          .show();
        setTimeout(function(){ $('#publisher_message').fadeOut(200); }, 3000);
        var modalEl = document.getElementById('createPublisherModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      } else {
        $('#cp_msg').html(
          '<div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">\
             <i class="bi bi-x-octagon"></i>\
             <div>تعذر الحفظ</div>\
           </div>'
        );
      }
    }, 'json').fail(function(xhr){
      var msg = 'تعذر الاتصال بالخادم';
      if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
      $('#cp_msg').html(
        '<div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">\
           <i class="bi bi-x-octagon"></i>\
           <div>'+msg+'</div>\
         </div>'
      );
    }).always(function(){
      $('#savePublisherBtn').prop('disabled', false).text('حفظ');
    });
  });
});
