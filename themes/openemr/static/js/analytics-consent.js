(function () {
  var KEY = 'oemr-analytics-consent';
  var banner = document.getElementById('analytics-consent');
  if (!banner) return;
  var gaId = banner.getAttribute('data-ga-id');

  function getChoice() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }

  function setChoice(value) {
    try { localStorage.setItem(KEY, value); } catch (e) {}
  }

  function loadAnalytics() {
    if (window.gtag) return;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', gaId, {
      allow_google_signals: false,
      allow_ad_personalization_signals: false
    });
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
    document.head.appendChild(s);
  }

  // Cookies may have been set on the host or the registrable parent domain.
  function clearAnalyticsCookies() {
    var host = location.hostname;
    var parent = host.split('.').slice(-2).join('.');
    document.cookie.split(';').forEach(function (c) {
      var name = c.split('=')[0].trim();
      if (name.indexOf('_ga') !== 0) return;
      [host, '.' + host, parent, '.' + parent].forEach(function (d) {
        document.cookie = name + '=; Max-Age=0; path=/; domain=' + d;
      });
      document.cookie = name + '=; Max-Age=0; path=/';
    });
  }

  banner.addEventListener('click', function (e) {
    var choice = e.target.getAttribute('data-consent');
    if (!choice) return;
    var previous = getChoice();
    setChoice(choice);
    banner.hidden = true;
    if (choice === 'granted') {
      loadAnalytics();
    } else if (previous === 'granted') {
      clearAnalyticsCookies();
      location.reload();
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-analytics-settings]')) {
      e.preventDefault();
      banner.hidden = false;
    }
  });

  var choice = getChoice();
  if (choice === 'granted') {
    loadAnalytics();
  } else if (choice !== 'denied') {
    banner.hidden = false;
  }
})();
