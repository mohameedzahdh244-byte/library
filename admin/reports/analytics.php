<?php
require_once __DIR__ . '/../../config/init.php';

// صلاحيات الموظف/المدير فقط
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff','admin'])) {
  header('Location: /auth/loginform.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تقارير التحليلات - الزيارات والوقت النشط</title>
  <link href="/assets/css/bootstrap.css" rel="stylesheet">
  <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
  <style>
    body{ background:#f8fafc; }
    .report-card{ background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.05); }
    .metric{ display:flex; align-items:center; gap:.75rem; padding:1rem; border-radius:12px; background:#f9fafb; }
    .metric .icon{ width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; }
    .icon-visits{ background:#1E88E5; }
    .icon-users{ background:#10B981; }
    .icon-mins{ background:#F59E0B; }
    .icon-hours{ background:#8B5CF6; }
    .filters .form-select{ min-width: 160px; }
    .chart-card{ background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:1rem; box-shadow:0 6px 18px rgba(0,0,0,.05); }
    /* Fix chart growth: wrap canvas in a fixed-height container */
    .chart-box{ position: relative; height: 320px; max-width: 100%; }
    @media (min-width: 992px){ .chart-box{ height: 360px; } }
    .chart-card canvas{ width: 100% !important; height: 100% !important; display: block; }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h3 class="mb-1">تقارير التحليلات</h3>
        <div class="text-muted">زيارات الصفحة ومدة النشاط (بالدقائق)</div>
      </div>
      <div class="filters d-flex gap-2">
        <select id="pageSel" class="form-select form-select-sm">
          <option value="about">الصفحة التعريفية</option>
          <option value="member">صفحة المشتركين</option>
        </select>
        <select id="rangeSel" class="form-select form-select-sm">
          <option value="day">اليوم (آخر 24 ساعة)</option>
          <option value="month">الشهر (آخر 30 يوماً)</option>
          <option value="year">السنة (آخر 12 شهراً)</option>
        </select>
        <button id="refreshBtn" class="btn btn-primary btn-sm"><i class="fa-solid fa-rotate"></i> تحديث</button>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-12 col-md-3" id="colVisits">
        <div class="metric">
          <div class="icon icon-visits"><i class="fa-solid fa-eye"></i></div>
          <div>
            <div class="text-muted small">إجمالي الزيارات</div>
            <div id="totalVisits" class="fw-bold fs-5">-</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-3" id="colMemberLogins">
        <div class="metric">
          <div class="icon icon-users"><i class="fa-solid fa-user-group"></i></div>
          <div>
            <div class="text-muted small">تسجيلات دخول المشتركين</div>
            <div id="totalMemberLogins" class="fw-bold fs-5">-</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="metric">
          <div class="icon icon-mins"><i class="fa-solid fa-clock"></i></div>
          <div>
            <div class="text-muted small">إجمالي الدقائق النشطة</div>
            <div id="totalMinutes" class="fw-bold fs-5">-</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="metric">
          <div class="icon icon-hours"><i class="fa-regular fa-hourglass-half"></i></div>
          <div>
            <div class="text-muted small">إجمالي الساعات النشطة (س:د)</div>
            <div id="totalHours" class="fw-bold fs-5">-</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="chart-card">
          <h6 class="mb-3"><i class="fa-solid fa-chart-line me-2 text-primary"></i>الزيارات عبر الزمن</h6>
          <div class="chart-box">
            <canvas id="visitsChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="chart-card">
          <h6 class="mb-3"><i class="fa-solid fa-chart-area me-2 text-warning"></i>الدقائق النشطة عبر الزمن</h6>
          <div class="chart-box">
            <canvas id="minutesChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="/assets/js/chart.umd.min.js"></script>
  <script>
    const pageSel = document.getElementById('pageSel');
    const rangeSel = document.getElementById('rangeSel');
    const totalVisitsEl = document.getElementById('totalVisits');
    const totalMemberLoginsEl = document.getElementById('totalMemberLogins');
    const totalMinutesEl = document.getElementById('totalMinutes');
    const totalHoursEl = document.getElementById('totalHours');

    let visitsChart, minutesChart;

    async function fetchStats(){
      const page = pageSel.value; const range = rangeSel.value;
      const url = `/analytics/stats.php?page=${encodeURIComponent(page)}&range=${encodeURIComponent(range)}`;
      const res = await fetch(url);
      const data = await res.json();
      if (!data || !data.ok) { console.error('Failed to load stats', data); return; }

      // Totals
      const colVisits = document.getElementById('colVisits');
      const colMemberLogins = document.getElementById('colMemberLogins');
      const isMember = (data.page === 'member');
      if (colVisits) colVisits.style.display = isMember ? 'none' : '';
      // اعرض بطاقة تسجيلات الدخول فقط لصفحة المشتركين، واحذف مفهوم الزوار الفريدون نهائياً
      if (colMemberLogins) colMemberLogins.style.display = isMember ? '' : 'none';
      totalVisitsEl.textContent = new Intl.NumberFormat('ar-EG').format(data.totals.visits || 0);
      if (isMember && totalMemberLoginsEl) {
        const loginsVal = (data.totals.member_logins || 0);
        totalMemberLoginsEl.textContent = new Intl.NumberFormat('ar-EG').format(loginsVal);
      }
      const mins = Math.round(data.totals.active_minutes || 0);
      totalMinutesEl.textContent = new Intl.NumberFormat('ar-EG', { maximumFractionDigits: 0 }).format(mins);
      if (totalHoursEl) {
        const hours = Math.floor(mins / 60);
        const remMins = mins % 60;
        const mm = String(remMins).padStart(2, '0');
        totalHoursEl.textContent = `${hours}:${mm}`;
        totalHoursEl.title = `${hours} ساعة و ${remMins} دقيقة`;
      }

      // Series
      const labels = (data.series || []).map(p => p.bucket);
      const visits = (data.series || []).map(p => p.visits || 0);
      const minutes = (data.series || []).map(p => p.active_minutes || 0);

      // Draw charts
      drawVisits(labels, visits, page, range);
      drawMinutes(labels, minutes, page, range);
    }

    function drawVisits(labels, dataArr, page, range){
      const ctx = document.getElementById('visitsChart');
      visitsChart && visitsChart.destroy();
      visitsChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'زيارات',
            data: dataArr,
            borderColor: '#1E88E5',
            backgroundColor: 'rgba(30,136,229,.15)',
            borderWidth: 2,
            pointRadius: 2,
            tension: .25,
            fill: true
          }]
        },
        options: { responsive: true, maintainAspectRatio: false,
          scales: { x: { ticks:{ autoSkip: true, maxTicksLimit: 14 } }, y: { beginAtZero: true } },
          plugins: { legend: { display: false } }
        }
      });
    }

    function drawMinutes(labels, dataArr, page, range){
      const ctx = document.getElementById('minutesChart');
      minutesChart && minutesChart.destroy();
      minutesChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'دقائق نشطة',
            data: dataArr,
            borderColor: '#F59E0B',
            backgroundColor: 'rgba(245,158,11,.15)',
            borderWidth: 2,
            pointRadius: 2,
            tension: .25,
            fill: true
          }]
        },
        options: { responsive: true, maintainAspectRatio: false,
          scales: { x: { ticks:{ autoSkip: true, maxTicksLimit: 14 } }, y: { beginAtZero: true } },
          plugins: { legend: { display: false } }
        }
      });
    }

    (function init(){
      if (pageSel && rangeSel) {
        pageSel.addEventListener('change', fetchStats);
        rangeSel.addEventListener('change', fetchStats);
      }
      const refreshBtn = document.getElementById('refreshBtn');
      if (refreshBtn) refreshBtn.addEventListener('click', function(e){ e.preventDefault(); fetchStats(); });
      fetchStats();
      setTimeout(fetchStats, 2000);
    })();
  </script>
</body>
</html>
