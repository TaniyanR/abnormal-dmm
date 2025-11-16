/**
 * public/assets/js/admin.js
 * Admin UI JavaScript for manual fetch trigger
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
    const manualTokenInput = document.getElementById('manualToken');

    if (!runFetchBtn || !fetchResult) {
      console.warn('Admin UI elements not found');
      return;
    }

    runFetchBtn.addEventListener('click', handleManualFetch);

    async function handleManualFetch(e) {
      e.preventDefault();

      const config = window.__ADMIN_UI || {};
      const endpoint = config.fetchEndpoint || '/api/admin/fetch';
      let token = manualTokenInput ? manualTokenInput.value.trim() : '';
      
      // Use default token if manual token not provided
      if (!token) {
        token = config.defaultToken || '';
      }

      if (!token) {
        fetchResult.textContent = 'Error: No ADMIN_TOKEN provided. Enter token or configure defaultToken.';
        fetchResult.style.color = 'red';
        return;
      }

      // Disable button during fetch
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Fetching...';
      fetchResult.textContent = 'Sending request to ' + endpoint + '...';
      fetchResult.style.color = 'inherit';

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
          },
          body: JSON.stringify({
            hits: 20,
            offset: 1
          })
        });

        const data = await response.json();
        
        // Format and display response
        const formatted = JSON.stringify(data, null, 2);
        fetchResult.textContent = 'Response (HTTP ' + response.status + '):\n\n' + formatted;
        
        if (response.ok && data.success) {
          fetchResult.style.color = 'green';
        } else {
          fetchResult.style.color = 'red';
        }
      } catch (error) {
        fetchResult.textContent = 'Error: ' + error.message;
        fetchResult.style.color = 'red';
      } finally {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    }
  }
})();
