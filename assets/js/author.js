$(function(){
  var $input = $('#author_search');
  var $res = $('#author_result');
  var $hidden = $('#ANO');
  var $msg = $('#author_message');
  var $tags = $('#author_tags'); // وجوده يعني دعم متعدد
  var timer = null;

  function hasMulti(){ return $tags && $tags.length > 0; }

  function currentAuthorIds(){
    if (!hasMulti()) return $hidden.val() ? [$hidden.val()] : [];
    var ids = [];
    $tags.find('input[name="authors[]"]').each(function(){ ids.push($(this).val()); });
    return ids;
  }

  function updateHiddenANO(){
    if (!hasMulti()) return; // لا تغير سلوك الحالة القديمة
    var ids = currentAuthorIds();
    $hidden.val(ids.length ? ids[0] : '');
  }

  function addAuthorTag(id, text){
    if (!hasMulti()){
      // وضعية مفردة قديمة
      $hidden.val(id);
      $input.val(text);
      $res.hide().empty();
      return;
    }
    // لا تكرار
    var exists = $tags.find('input[name="authors[]"][value="'+id+'"]').length > 0;
    if (exists) {
      // عرض رسالة بسيطة بدلاً من الإخفاء لمنع الإرباك
      try {
        $msg.html('<div class="alert alert-warning d-flex align-items-center gap-2 mb-2 py-2">\
          <i class="bi bi-exclamation-triangle"></i>\
          <div>هذا المؤلف مُضاف بالفعل</div>\
        </div>').show();
        setTimeout(function(){ $msg.fadeOut(200, function(){ $(this).empty().show(); }); }, 2000);
      } catch(e) {}
      $input.val('');
      return;
    }
    var $chip = $('<span class="badge bg-secondary d-inline-flex align-items-center p-2">\
        <span class="ms-1">'+$('<div>').text(text).html()+'</span>\
        <button type="button" class="btn btn-sm btn-light ms-2 remove-author" aria-label="إزالة" data-id="'+id+'">&times;</button>\
        <input type="hidden" name="authors[]" value="'+id+'">\
      </span>');
    $tags.append($chip);
    updateHiddenANO();
    // أغلق قائمة النتائج بعد الاختيار حتى في وضع تعدد المؤلفين
    $input.val('');
    try { $res.hide().empty(); } catch(e){}
    try { $input.blur(); } catch(e){}
  }

  $(document).on('click', '#author_tags .remove-author', function(){
    var $chip = $(this).closest('span.badge');
    $chip.remove();
    updateHiddenANO();
  });

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
             <div>اكتب <strong>حرفين على الأقل</strong> للبحث عن مؤلف</div>\
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
      $.get('/admin/authors/search_author_ajax.php', { q: q }, function(data){
        var items = (data && data.results) ? data.results : [];
        if (!items.length) {
          $res.html(
            '<div class="list-group-item">\
               <div class="alert alert-warning d-flex align-items-center gap-2 mb-2 py-2">\
                 <i class="bi bi-exclamation-triangle"></i>\
                 <div>لا توجد نتائج مطابقة</div>\
               </div>\
               <button type="button" class="btn btn-outline-primary w-100 openCreateAuthor">\
                 <i class="bi bi-plus-circle"></i> إضافة مؤلف جديد\
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
      }, 'json').fail(function(xhr, textStatus, errorThrown){
        if (textStatus === 'parsererror' && xhr && xhr.responseText) {
          console.error('Author search parsererror. Raw response:', xhr.responseText);
        } else {
          console.error('Author search failed:', {textStatus: textStatus, error: errorThrown, status: xhr && xhr.status, xhr: xhr});
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
    }, 250);
  });

  // Pick item
  $(document).on('click', '#author_result .item', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    var text = $(this).data('text');
    addAuthorTag(id, text);
  });

  // Hide on outside click
  $(document).on('click', function(e){
    if (!$(e.target).closest('#author_result, #author_search').length){
      $res.hide();
    }
  });

  // Open modal (always available)
  $(document).on('click', '.openCreateAuthor', function(){
    $('#ca_msg').empty();
    var form = document.getElementById('createAuthorForm');
    if (form) form.reset();
    $.get('/admin/authors/create_author_ajax.php', { action: 'next_no' }, function(resp){
      if (resp && resp.success) { $('#ca_ANO').val(resp.next_no); } else { $('#ca_ANO').val(''); }
      var modal = new bootstrap.Modal(document.getElementById('createAuthorModal'));
      modal.show();
      setTimeout(function(){ $('#ca_Aname').trigger('focus'); }, 200);
    }, 'json').fail(function(){
      $('#ca_ANO').val('');
      var modal = new bootstrap.Modal(document.getElementById('createAuthorModal'));
      modal.show();
      $('#ca_msg').html('\
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2">\
          <i class="bi bi-exclamation-circle"></i>\
          <div>تعذر جلب الرقم التالي، يمكنك إدخاله يدويًا</div>\
        </div>'
      );
    });
  });

  // Save new author
  $(document).on('click', '#saveAuthorBtn', function(){
    var data = {
      ANO: $('#ca_ANO').val(),
      Aname: $('#ca_Aname').val()
    };
    if (!data.Aname || !data.ANO) {
      $('#ca_msg')
        .html(
          '<div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2">\
             <i class="bi bi-exclamation-triangle"></i>\
             <div>يرجى إدخال <strong>رقم</strong> و<strong>اسم</strong> المؤلف</div>\
           </div>'
        )
        .show();
      setTimeout(function(){
        $('#ca_msg').fadeOut(200, function(){ $(this).empty().show(); });
      }, 3000);
      return;
    }
    $('#saveAuthorBtn').prop('disabled', true).text('جارٍ الحفظ...');
    $.post('/admin/authors/create_author_ajax.php', data, function(resp){
      if (resp && resp.success && resp.author) {
        addAuthorTag(resp.author.ANO, resp.author.Aname);
        $('#author_result').hide().empty();
        $('#author_message')
          .html('\
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-0 small" role="status" aria-live="polite">\
              <i class="bi bi-check-circle-fill"></i>\
              <div><strong>تم الحفظ:</strong> تمت إضافة المؤلف بنجاح</div>\
            </div>'
          )
          .show();
        setTimeout(function(){ $('#author_message').fadeOut(200); }, 3000);
        var modalEl = document.getElementById('createAuthorModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      } else {
        $('#ca_msg').html(
          '<div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">\
             <i class="bi bi-x-octagon"></i>\
             <div>تعذر الحفظ</div>\
           </div>'
        );
      }
    }, 'json').fail(function(xhr){
      var msg = 'تعذر الاتصال بالخادم';
      if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
      $('#ca_msg').html(
        '<div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">\
           <i class="bi bi-x-octagon"></i>\
           <div>'+msg+'</div>\
         </div>'
      );
    }).always(function(){
      $('#saveAuthorBtn').prop('disabled', false).text('حفظ');
    });
  });
});
