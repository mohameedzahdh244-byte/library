'use strict';

// إشعار علوي يمين يختفي تلقائياً بعد 3 ثوانٍ (نفس فكرة إشعار اختيار المشترك)
function showTopRightAlert(type, message){
  var container = document.getElementById('notify-top-right');
  if(!container){
    container = document.createElement('div');
    container.id = 'notify-top-right';
    container.style.position = 'fixed';
    container.style.top = '12px';
    container.style.right = '12px';
    container.style.zIndex = '3000';
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

let hasSearched = false;
function emptyMessage(){
  return `<div class="text-center text-muted py-4">لا يوجد تطابق</div>`;
}
function renderItems(items){
  if(!items || !items.length){
    $('#results').html(hasSearched ? emptyMessage() : '');
    $('#resultsHeader').hide();
    return;
  }
  const html = items.map((m, idx) => {
    const status = m.mem_status || 'غير معروف';
    const isExpired = /منتهي/.test(status);
    const statusClass = isExpired ? 'status-expired' : 'status-active';
    const renewBtn = isExpired ? `<button class="btn btn-sm btn-warning" data-action="renew" data-mem="${m.mem_no}">تجديد الاشتراك <i class="fa fa-rotate"></i></button>` : '';
    return `
      <div class="col-12 col-md-6 col-lg-4 mb-3">
        <div class="result-item slide-up" style="animation-delay:${idx*0.05}s">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div class="flex-grow-1">
              <div class="small text-muted"><i class="fa fa-id-card me-2 text-primary" title="رقم المشترك"></i>${m.mem_no || ''}</div>
              <div class="fw-bold mt-1"><i class="fa fa-user me-1 text-primary"></i>${m.mem_name || ''}</div>
              <div class="small text-muted"><i class="fa fa-phone me-2 text-success" title="الهاتف"></i>${m.mem_phone || ''}</div>
            </div>
            <span class="badge badge-soft ${statusClass}">${status}</span>
          </div>
          <div class="mt-2 actions d-flex gap-2">
            <button class="btn btn-sm btn-primary" data-action="edit" data-mem="${m.mem_no}">تعديل البيانات <i class="fa fa-pen me-1"></i></button>
            ${renewBtn}
          </div>
        </div>
      </div>`;
  }).join('');
  $('#results').html(`<div class="row g-3">${html}</div>`);
  $('#resultsHeader').show();
  $('#resultsCount').text(`${items.length} نتيجة`);
}

function doSearch(){
  const q = $('#q').val().trim();
  hasSearched = true;
  $('#loading').show();
  $.ajax({
    url: 'search_api_simple.php',
    method: 'GET',
    dataType: 'json',
    data: { q },
    success: function(res){
      if(res && res.success){
        renderItems(res.members || []);
      } else {
        renderItems([]);
      }
      $('#loading').hide();
    },
    error: function(){ renderItems([]); $('#loading').hide(); }
  });
}

$(document).ready(function(){
  let t; 
  $('#q').on('input', function(){
    const val = this.value;
    $('#clearSearch').toggle(!!val);
    clearTimeout(t);
    t = setTimeout(doSearch, 300);
  });
  $('#q').on('keypress', function(e){ if(e.key === 'Enter'){ e.preventDefault(); doSearch(); }});
  // Clear button + Esc support
  $('#clearSearch').on('click', function(){
    $('#q').val('').focus();
    if(hasSearched){ renderItems([]); }
    $('#clearSearch').hide();
  });
  $(document).on('keydown', function(e){ if(e.key === 'Escape'){ $('#clearSearch').trigger('click'); }});

  // handle actions (edit / renew) -> open inline modal with our edit iframe
  $('#results').on('click', '[data-action]', function(){
    const action = $(this).data('action');
    const mem_no = $(this).data('mem');
    if(!mem_no) return;
    // Find item data from DOM (closest card) by reading texts we rendered
    const card = $(this).closest('.result-item');
    const mem_name = card.find('.fw-bold').text().trim().replace(/^\s*\S+\s*/, '');
    const phoneEl = card.find('.fa-phone').parent();
    const mem_phone = phoneEl.length ? phoneEl.text().trim().replace(/^\s*\S+\s*/, '') : '';
    const status = card.find('.badge-soft').text().trim();
    const params = new URLSearchParams({ mem_no, mem_name, mem_phone, mem_status: status, action: action === 'renew' ? 'renew' : '' });
    const src = `/admin/customer/edit_modal.php?${params.toString()}`;
    $('#editFrame').attr('src', src);
    $('#editModal').css('display','flex');
  });

  // close modal controls
  $('#closeEditModal').on('click', function(){ $('#editModal').hide(); $('#editFrame').attr('src','about:blank'); });
  $('#editModal').on('click', function(e){ if(e.target === this){ $('#closeEditModal').trigger('click'); } });
  $(document).on('keydown', function(e){ if(e.key === 'Escape'){ $('#closeEditModal').trigger('click'); }});

  // receive messages from iframe (future: perform AJAX to backend if needed)
  window.addEventListener('message', function(ev){
    if(!ev.data || ev.data.source !== 'edit_member_modal') return;
    const { type, payload } = ev.data;
    if(type === 'update_member' || type === 'renew_member'){
      // close modal and refresh search results
      $('#closeEditModal').trigger('click');
      if($('#q').val()){ doSearch(); }
    } else if (type === 'close') {
      $('#closeEditModal').trigger('click');
    } else if (type === 'notify') {
      var lvl = (payload && payload.level) ? payload.level : 'info';
      var msg = (payload && payload.message) ? payload.message : '';
      if(msg){ showTopRightAlert(lvl, msg); }
    }
  });

  // initial empty state (no search yet)
  renderItems([]);
  $('#q').trigger('focus');
});
