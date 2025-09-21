<?php
// تضمين نظام المسارات

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/DB.php';
require_once __DIR__ . '/../config/session.php';
checkMemberPermission();

$embed = (isset($_GET['embed']) && $_GET['embed'] == '1') || (isset($_GET['iframe']) && $_GET['iframe'] == '1') || (isset($_GET['ajax']) && $_GET['ajax'] == '1');
if (!$embed) {
  $page_title = 'أنشطة المكتبة';
  $body_class = '';
  $analytics_page = 'member-activities';
  include __DIR__ . '/../includes/header.php';
} else {
  echo '<!DOCTYPE html>';
  echo '<html lang="ar" dir="rtl">';
  echo '<head>';
  echo '<meta charset="UTF-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<title>أنشطة المكتبة</title>';
  echo '<link href="../assets/css/bootstrap.css" rel="stylesheet">';
  echo '<link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">';
  echo '<link rel="stylesheet" href="../assets/fonts/cairo/cairo.css">';
  echo '</head>';
  echo '<body data-analytics-page="member">';
}
?>
<div class="container py-4" dir="rtl">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h5 m-0 d-flex align-items-center gap-2">
      <i class="fas fa-people-group text-primary"></i>
      الأنشطة المتاحة
    </h1>
    <div class="d-flex align-items-center gap-2">
      <label for="filterType" class="form-label mb-0 me-2">التصنيف:</label>
      <select id="filterType" class="form-select form-select-sm" style="min-width:220px">
        <option value="">كل التصنيفات</option>
      </select>
    </div>
  </div>

  <div id="listAlert" class="alert d-none"></div>

  <div id="activitiesGrid" class="row g-3"></div>

  <nav class="mt-4 d-none" id="pager" aria-label="ترقيم">
    <ul class="pagination justify-content-center mb-0">
      <li class="page-item"><button class="page-link" id="prevPage">السابق</button></li>
      <li class="page-item disabled"><span class="page-link" id="pageInfo"></span></li>
      <li class="page-item"><button class="page-link" id="nextPage">التالي</button></li>
    </ul>
  </nav>
</div>

<script>
(function(){
  const grid = document.getElementById('activitiesGrid');
  const alertBox = document.getElementById('listAlert');
  const pager = document.getElementById('pager');
  const pageInfo = document.getElementById('pageInfo');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');
  const filterType = document.getElementById('filterType');

  let page = 1, total = 0, perPage = 9;

  function showAlert(type, msg){
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
  }

  async function fetchCategories(){
    try {
      const r = await fetch('../api/activities/categories_list.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      const json = await r.json();
      if (!json.success) return;
      const data = json.data || [];
      filterType.innerHTML = '<option value="">كل التصنيفات</option>' + data.map(c=>`<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    } catch(e) { /* تجاهل */ }
  }

  function renderItems(items){
    grid.innerHTML = '';
    if (!items || !items.length){
      grid.innerHTML = '<div class="col-12"><div class="alert alert-info mb-0">لا توجد أنشطة حالياً.</div></div>';
      return;
    }
    items.forEach(a => {
      const status = a.request_status || '';
      const isPending = status === 'pending';
      const isApproved = status === 'approved';
      const isRejected = status === 'rejected';

      const card = document.createElement('div');
      card.className = 'col-12 col-md-6 col-lg-4';
      card.innerHTML = `
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <div class="small text-primary mb-1">${(a.type_name||'').trim() || 'نشاط'}</div>
            <h3 class="h6 fw-bold">${escapeHtml(a.title||'')}</h3>
            <div class="text-muted small mb-2">
              <div>المكان: ${escapeHtml(a.location||'')}</div>
              <div>من: ${fmt(a.start_datetime)}</div>
              <div>إلى: ${fmt(a.end_datetime)}</div>
            </div>
            <div class="small mb-2">
              ${a.is_paid == 1 ? '<span class="badge bg-warning text-dark">مدفوع</span> <span class="text-muted">رسوم: '+num(a.fee_amount)+'</span>' : '<span class="badge bg-success">مجاني</span>'}
            </div>
            ${a.supervisors ? `<div class="small text-muted mb-2">المشرفون: ${escapeHtml(a.supervisors)}</div>` : ''}
            ${a.description ? `<p class="small mb-3 text-secondary" style="white-space:pre-line">${escapeHtml(a.description).slice(0,160)}${a.description.length>160?'...':''}</p>` : ''}
            <div class="mt-auto">
              <div class="d-flex gap-2 mb-2">
                ${isApproved ? '<span class="badge bg-success"><i class="fas fa-check"></i> مقبول</span>' : isPending ? '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> بإنتظار الموافقة</span>' : isRejected ? '<span class="badge bg-danger"><i class="fas fa-times"></i> مرفوض</span>' : `<button class="btn btn-primary btn-sm requestBtn" data-id="${a.id}"><i class="fas fa-paper-plane"></i> طلب انضمام</button>`}
              </div>
              ${(isApproved || isRejected) && a.admin_note ? `<div class="alert alert-${isApproved ? 'success' : 'danger'} alert-sm p-2 mb-0"><small><strong>ملاحظة الموظف:</strong><br>${escapeHtml(a.admin_note)}</small></div>` : ''}
            </div>
          </div>
        </div>`;
      grid.appendChild(card);
    });

    // bind request buttons
    grid.querySelectorAll('.requestBtn').forEach(btn => {
      btn.addEventListener('click', () => openRequest(btn.getAttribute('data-id')));
    });
  }

  function openRequest(activityId){
    const reason = prompt('اذكر سبب طلب الانضمام (اختياري):');
    const fd = new FormData();
    fd.append('activity_id', activityId);
    fd.append('reason', reason || '');
    fetch('../api/activities/request_join.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r=>r.json()).then(json=>{
      if (json.success){
        showAlert('success', json.message || 'تم الإرسال');
        load();
      } else {
        showAlert('danger', json.message || 'تعذر الإرسال');
      }
    }).catch(()=>showAlert('danger','خطأ في الاتصال'));
  }

  function fmt(dt){
    if (!dt) return '—';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    const p = n => (n<10?'0'+n:n);
    return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
  }
  function num(v){ try { return Number(v).toFixed(2); } catch(e){ return v || '0.00'; } }
  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

  function renderPager(){
    const pages = Math.max(1, Math.ceil(total / perPage));
    pageInfo.textContent = `صفحة ${page} / ${pages}`;
    pager.classList.toggle('d-none', pages <= 1);
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= pages;
  }

  function load(){
    const q = new URLSearchParams();
    q.set('page', page);
    q.set('per_page', perPage);
    const typeVal = filterType.value;
    if (typeVal) q.set('type_id', typeVal);
    fetch('../api/activities/list_member.php?'+q.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
      .then(r=>r.json())
      .then(json=>{
        if (!json.success){ showAlert('danger', json.message||'فشل التحميل'); return; }
        total = json.total||0;
        renderItems(json.data||[]);
        renderPager();
      }).catch(()=>showAlert('danger','خطأ في الاتصال'));
  }

  prevBtn.addEventListener('click', ()=>{ if (page>1){ page--; load(); }});
  nextBtn.addEventListener('click', ()=>{ page++; load(); });
  filterType.addEventListener('change', ()=>{ page = 1; load(); });

  // مبدئياً حمّل فقط
  fetchCategories().then(load);
})();
</script>
<?php if (!$embed) { 
  include __DIR__ . '/../includes/footer.php'; 
} else {
  echo '</body>';
  echo '</html>';
} ?>
