/**
 * public/assets/js/admin.js
 * Admin UI JavaScript for manual fetch trigger
 */

(function() {
  'use strict';

  // Wait for DOM ready
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
      // Elements not found on page — nothing to do
      console.warn('Admin UI elements not found: runFetchBtn or fetchResult missing');
      return;
    }

    // Attach handler
    runFetchBtn.addEventListener('click', handleManualFetch);

    async function handleManualFetch(e) {
      e && e.preventDefault();

      const config = window.__ADMIN_UI || {};
      // Default endpoint matches admin UI: /public/api/admin/fetch.php
      const endpoint = config.fetchEndpoint || '/public/api/admin/fetch.php';
      const defaultToken = config.defaultToken || '';
      const manualToken = manualTokenInput ? manualTokenInput.value.trim() : '';
      const token = manualToken || defaultToken;

      if (!token) {
        fetchResult.textContent = 'Error: No admin token provided. Please set ADMIN_TOKEN in config or enter it in the field.';
        fetchResult.style.color = '#c0392b';
        return;
      }

      // Disable UI while running
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Running...';
      fetchResult.textContent = 'Sending request to ' + endpoint + ' ...';
      fetchResult.style.color = '#333';

      // Prepare payload: server accepts { total: N } by default.
      // If your endpoint expects hits/offset instead, replace or add them.
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
        let data = null;
        try {
          data = JSON.parse(text);
        } catch (err) {
          // Not JSON — show raw text
          fetchResult.textContent = 'Response (HTTP ' + resp.status + '):\n\n' + text;
          fetchResult.style.color = resp.ok ? '#27ae60' : '#c0392b';
          return;
        }

        // Show formatted JSON
        const pretty = JSON.stringify(data, null, 2);
        fetchResult.textContent = 'Response (HTTP ' + resp.status + '):\n\n' + pretty;
        // Determine success heuristically: prefer HTTP OK, otherwise look for status/success keys
        const okFlag = resp.ok || data.status === 'done' || data.success === true || data.status === 'ok';
        fetchResult.style.color = okFlag ? '#27ae60' : '#c0392b';
      } catch (error) {
        console.error('Manual fetch failed', error);
        fetchResult.textContent = 'Request failed: ' + (error && error.message ? error.message : String(error));
        fetchResult.style.color = '#c0392b';
      } finally {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    }
  }
})();