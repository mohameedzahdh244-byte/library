(function(){
  // Lightweight analytics: track page views and active time via Beacon
  if (!('sendBeacon' in navigator)) {
    // Fallback to fetch if Beacon unavailable
  }
  function $(sel){ return document.querySelector(sel); }

  function start(){
    var body = document.body;
    if (!body) return;
    var pageId = body.getAttribute('data-analytics-page') || '';
    if (!pageId) return; // No analytics configured for this page

    // استخدام المسار المطلق الذي يعمل من أي مجلد
    var basePath = document.querySelector('script[src*="analytics.js"]')?.src.replace(/\/assets\/js\/analytics\.js.*$/, '') || '';
    var endpoint = basePath + '/analytics/track.php';
    var sessionId = (function(){
      try {
        var k = 'lib_analytics_sid';
        var sid = localStorage.getItem(k);
        if (!sid) { sid = Math.random().toString(36).slice(2) + Date.now().toString(36); localStorage.setItem(k, sid); }
        return sid;
      } catch(e){ return 'sid_'+Date.now(); }
    })();

    var lastVisibleTs = document.visibilityState === 'visible' ? Date.now() : 0;
    var activeMs = 0;

    function flush(eventType, extra){
      var payload = {
        page: pageId,
        event: eventType,
        active_ms: Math.max(0, Math.round(activeMs)),
        url: location.pathname + location.search,
        ref: document.referrer || '',
        ts: Date.now(),
        sid: sessionId,
        ua: navigator.userAgent
      };
      if (extra && typeof extra === 'object') { for (var k in extra){ payload[k]=extra[k]; } }

      try {
        var blob = new Blob([JSON.stringify(payload)], {type: 'application/json'});
        if (navigator.sendBeacon) {
          navigator.sendBeacon(endpoint, blob);
        } else {
          fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload), keepalive: true});
        }
      } catch(e){}
    }

    // Initial pageview
    flush('pageview');
    // Early heartbeat after 1s to reflect active time immediately
    setTimeout(function(){
      if (document.visibilityState === 'visible') {
        if (lastVisibleTs) { activeMs += Date.now() - lastVisibleTs; lastVisibleTs = Date.now(); }
      }
      flush('heartbeat');
    }, 1000);

    // Track visibility to calculate active time
    function onVis(){
      if (document.visibilityState === 'visible') {
        lastVisibleTs = Date.now();
      } else {
        if (lastVisibleTs) { activeMs += Date.now() - lastVisibleTs; lastVisibleTs = 0; }
        flush('heartbeat');
      }
    }
    document.addEventListener('visibilitychange', onVis);

    // Heartbeat every 10s while visible
    var hb = setInterval(function(){
      if (document.visibilityState === 'visible') {
        if (lastVisibleTs) { activeMs += Date.now() - lastVisibleTs; lastVisibleTs = Date.now(); }
      }
      flush('heartbeat');
    }, 10000);

    // Before unload finalize
    window.addEventListener('pagehide', function(){
      if (lastVisibleTs) { activeMs += Date.now() - lastVisibleTs; lastVisibleTs = 0; }
      flush('final');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
