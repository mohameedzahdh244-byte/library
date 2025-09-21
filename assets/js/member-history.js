// تم إلغاء إعادة التحميل التلقائي نهائياً للحفاظ على تجربة المستخدم

// بحث حي وتحديث جزئي للنتائج حسب عوامل التصفية
(function(){
  const searchInput = document.getElementById('search');
  const form = document.getElementById('filterForm');
  const results = document.getElementById('historyResults');
  let timer; let currentController = null;
  if (!searchInput || !form || !results) return;

  function buildUrl(){
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    const q = (searchInput.value || '').trim();
    const status = document.getElementById('status')?.value || '';
    const year = document.getElementById('year')?.value || '';
    if (q.length) params.set('search', q); else params.delete('search');
    if (status.length) params.set('status', status); else params.delete('status');
    if (year.length) params.set('year', year); else params.delete('year');
    url.search = params.toString();
    return url.toString();
  }

  async function fetchResults(force=false){
    const q = searchInput.value.trim();
    if (!force && !(q.length >= 2 || q.length === 0)) return;
    if (currentController) currentController.abort();
    currentController = new AbortController();
    const signal = currentController.signal;
    const preserveY = window.scrollY;
    try {
      const newUrl = buildUrl();
      const response = await fetch(newUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal });
      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const newResults = doc.getElementById('historyResults');
      if (newResults) {
        results.innerHTML = newResults.innerHTML;
        try { window.history.replaceState(null, '', newUrl); } catch(_) {}
        window.scrollTo({ top: preserveY, behavior: 'instant' in window ? 'instant' : 'auto' });
      }
    } catch (e) {
      if (e.name !== 'AbortError') console.warn('Search update failed', e);
    }
  }

  searchInput.addEventListener('input', function(){
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(false), 300);
  });
  const statusEl = document.getElementById('status');
  const yearEl = document.getElementById('year');
  statusEl && statusEl.addEventListener('change', function(){ fetchResults(true); });
  yearEl && yearEl.addEventListener('change', function(){ fetchResults(true); });
})();
