/**
 * admin.js
 * JavaScript for admin API settings UI
 * Handles manual fetch trigger button
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
    const manualTokenInput = document.getElementById('manualToken');
    const fetchResultDiv = document.getElementById('fetchResult');

    if (!runFetchBtn || !fetchResultDiv) {
      return; // Elements not found
    }

    runFetchBtn.addEventListener('click', async function() {
      const token = manualTokenInput ? manualTokenInput.value.trim() : '';
      const defaultToken = window.__ADMIN_UI && window.__ADMIN_UI.defaultToken ? window.__ADMIN_UI.defaultToken : '';
      const endpoint = window.__ADMIN_UI && window.__ADMIN_UI.fetchEndpoint ? window.__ADMIN_UI.fetchEndpoint : '/api/admin/fetch';
      
      const useToken = token || defaultToken;

      if (!useToken) {
        fetchResultDiv.textContent = 'Error: No ADMIN_TOKEN provided';
        fetchResultDiv.style.background = '#f8d7da';
        fetchResultDiv.style.color = '#721c24';
        return;
      }

      // Disable button during fetch
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Fetching...';
      fetchResultDiv.textContent = 'Sending request...';
      fetchResultDiv.style.background = '#f8f9fa';
      fetchResultDiv.style.color = '#000';

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + useToken
          },
          body: JSON.stringify({
            hits: 20,
            offset: 1
          })
        });

        const data = await response.json();
        
        if (response.ok) {
          fetchResultDiv.textContent = 'Success!\n\n' + JSON.stringify(data, null, 2);
          fetchResultDiv.style.background = '#d4edda';
          fetchResultDiv.style.color = '#155724';
        } else {
          fetchResultDiv.textContent = 'Error (HTTP ' + response.status + '):\n\n' + JSON.stringify(data, null, 2);
          fetchResultDiv.style.background = '#f8d7da';
          fetchResultDiv.style.color = '#721c24';
        }
      } catch (error) {
        fetchResultDiv.textContent = 'Fetch error: ' + error.message;
        fetchResultDiv.style.background = '#f8d7da';
        fetchResultDiv.style.color = '#721c24';
      } finally {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    });
  }
})();
