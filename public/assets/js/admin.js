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
      console.warn('Admin UI elements not found');
      return;
    }

    runFetchBtn.addEventListener('click', function() {
      runManualFetch();
    });

    function runManualFetch() {
      const config = window.__ADMIN_UI || {};
      const endpoint = config.fetchEndpoint || '/api/admin/fetch';
      const defaultToken = config.defaultToken || '';
      const token = manualTokenInput ? manualTokenInput.value.trim() : '';
      const authToken = token || defaultToken;

      if (!authToken) {
        fetchResult.textContent = 'Error: No admin token provided. Please set ADMIN_TOKEN or enter it in the field.';
        fetchResult.style.color = '#c0392b';
        return;
      }

      // Disable button during request
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Running...';
      fetchResult.textContent = 'Fetching items from DMM API...';
      fetchResult.style.color = '#333';

      // Prepare request payload
      const payload = {
        hits: 20,
        offset: 1
      };

      // Make fetch request
      fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + authToken,
          'X-Admin-Token': authToken
        },
        body: JSON.stringify(payload)
      })
      .then(function(response) {
        return response.json().then(function(data) {
          return {
            status: response.status,
            ok: response.ok,
            data: data
          };
        });
      })
      .then(function(result) {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';

        if (result.ok && result.data.success) {
          fetchResult.textContent = 'Success!\n\n' + JSON.stringify(result.data, null, 2);
          fetchResult.style.color = '#27ae60';
        } else {
          fetchResult.textContent = 'Error (HTTP ' + result.status + '):\n\n' + JSON.stringify(result.data, null, 2);
          fetchResult.style.color = '#c0392b';
        }
      })
      .catch(function(error) {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
        fetchResult.textContent = 'Request failed:\n\n' + error.message;
        fetchResult.style.color = '#c0392b';
        console.error('Fetch error:', error);
      });
    }
  }
})();
