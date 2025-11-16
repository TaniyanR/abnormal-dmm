/**
 * admin.js
 * JavaScript for admin UI functionality
 * - Manual fetch trigger
 */

(function() {
  'use strict';

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    const runFetchBtn = document.getElementById('runFetchBtn');
    const fetchResult = document.getElementById('fetchResult');
    const manualToken = document.getElementById('manualToken');

    if (!runFetchBtn || !fetchResult) {
      return; // Elements not found on this page
    }

    runFetchBtn.addEventListener('click', async function() {
      const config = window.__ADMIN_UI || {};
      const endpoint = config.fetchEndpoint || '/public/api/admin/fetch.php';
      const defaultToken = config.defaultToken || '';
      const token = manualToken.value.trim() || defaultToken;

      if (!token) {
        fetchResult.textContent = 'Error: ADMIN_TOKEN is required. Please enter it in the field or set it in .env';
        fetchResult.style.color = '#721c24';
        fetchResult.style.background = '#f8d7da';
        return;
      }

      // Disable button and show loading
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Running...';
      fetchResult.textContent = 'Fetching data from DMM API...';
      fetchResult.style.color = '';
      fetchResult.style.background = '#f9f9f9';

      try {
        const response = await fetch(endpoint, {
          method: 'GET',
          headers: {
            'Authorization': 'Bearer ' + token,
            'X-Admin-Token': token
          }
        });

        const contentType = response.headers.get('content-type');
        let result;
        
        if (contentType && contentType.includes('application/json')) {
          result = await response.json();
        } else {
          result = await response.text();
        }

        // Display result
        if (response.ok) {
          fetchResult.textContent = JSON.stringify(result, null, 2);
          fetchResult.style.color = '#155724';
          fetchResult.style.background = '#d4edda';
        } else {
          fetchResult.textContent = 'Error (HTTP ' + response.status + '):\n' + JSON.stringify(result, null, 2);
          fetchResult.style.color = '#721c24';
          fetchResult.style.background = '#f8d7da';
        }
      } catch (error) {
        fetchResult.textContent = 'Error: ' + error.message;
        fetchResult.style.color = '#721c24';
        fetchResult.style.background = '#f8d7da';
      } finally {
        // Re-enable button
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    });
  }
})();
