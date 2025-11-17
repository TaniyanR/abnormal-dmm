/**
 * public/assets/js/admin.js
 * Admin UI JavaScript for manual fetch trigger
 * - Calls window.__ADMIN_UI.fetchEndpoint (fallback /public/api/admin/fetch.php)
 * - Token priority: manual input -> window.__ADMIN_UI.defaultToken (defaultToken should normally be blank)
 * - Default payload: { total: 100 } (change to hits/offset if backend expects that)
 */

(function() {
  'use strict';

  // DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    const runFetchBtn = document.getElementById('runFetchBtn');
    const fetchResult = document.getElementById('fetchResult');
    const manualTokenInput = document.getElementById('manualToken');

    if (!runFetchBtn || !fetchResult) {
      console.warn('Admin UI elements not found: runFetchBtn or fetchResult missing');
      return;
    }

    runFetchBtn.addEventListener('click', handleManualFetch);

    async function handleManualFetch(e) {
      e && e.preventDefault();

      const cfg = window.__ADMIN_UI || {};
      const endpoint = cfg.fetchEndpoint || '/public/api/admin/fetch.php';
      const defaultToken = cfg.defaultToken || '';
      const manualToken = manualTokenInput ? manualTokenInput.value.trim() : '';
      const token = manualToken || defaultToken;

      if (!token) {
        fetchResult.textContent = 'Error: No admin token provided. Please enter ADMIN_TOKEN or configure defaultToken.';
        fetchResult.style.color = '#c0392b';
        return;
      }

      // UI: disable while running
      runFetchBtn.disabled = true;
      const origLabel = runFetchBtn.textContent;
      runFetchBtn.textContent = 'Running...';
      fetchResult.textContent = 'Sending request to ' + endpoint + ' ...';
      fetchResult.style.color = '#333';

      // Payload: default uses total. Replace with hits/offset if needed.
      const payload = {
        total: 100
        // hits: 20,
        // offset: 1
      };

      try {
        const resp = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
          },
          body: JSON.stringify(payload)
        });

        const text = await resp.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (_) {
          // Not JSON, show raw text
          fetchResult.textContent = 'Response (HTTP ' + resp.status + '):\n\n' + text;
          fetchResult.style.color = resp.ok ? '#27ae60' : '#c0392b';
          return;
        }

        const pretty = JSON.stringify(data, null, 2);
        fetchResult.textContent = 'Response (HTTP ' + resp.status + '):\n\n' + pretty;

        const okFlag = resp.ok || data.success === true || data.status === 'done' || data.status === 'ok';
        fetchResult.style.color = okFlag ? '#27ae60' : '#c0392b';
      } catch (err) {
        console.error('Manual fetch failed', err);
        fetchResult.textContent = 'Request failed: ' + (err && err.message ? err.message : String(err));
        fetchResult.style.color = '#c0392b';
      } finally {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = origLabel || 'Run manual fetch';
      }
    }

    // Populate manual token with defaultToken if provided (only if field empty)
    try {
      const cfg2 = window.__ADMIN_UI || {};
      if (cfg2.defaultToken && manualTokenInput && !manualTokenInput.value) {
        manualTokenInput.value = cfg2.defaultToken;
      }
    } catch (e) {
      // ignore
    }
  }
})();