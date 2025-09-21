(function(){
  const form = document.getElementById('publicSearchForm');
  if(!form) return;

  const input = document.getElementById('search_query');
  const typeSelect = document.getElementById('search_type');
  const results = document.getElementById('liveResults');
  const MIN_LEN = 2;
  // تم إلغاء البحث اللحظي والاكتفاء بالبحث عند الضغط على زر "بحث"

  function setLoading() {
    if (!results) return;
    results.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
  }

  async function doSearch() {
    const q = (input?.value || '').trim();
    const st = (typeSelect?.value || 'title');

    if (!results) return;

    if (q.length < MIN_LEN) {
      results.innerHTML = '';
      return;
    }

    setLoading();

    try {
      const fd = new FormData();
      fd.append('ajax', '1');
      fd.append('search_query', q);
      fd.append('search_type', st);

      const resp = await fetch('/includes/search.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const html = await resp.text();
      results.innerHTML = html;
    } catch (e) {
      results.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء البحث. حاول مجددًا.</div>';
      console.error('Search error', e);
    }
  }

  // منع إرسال النموذج الافتراضي وإعادة التحميل
  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    doSearch();
  });
})();
