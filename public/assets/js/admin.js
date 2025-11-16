// public/assets/js/admin.js
// Admin UI JavaScript for manual fetch trigger

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    const runFetchBtn = document.getElementById('runFetchBtn');
    const manualTokenInput = document.getElementById('manualToken');
    const fetchResult = document.getElementById('fetchResult');

    if (!runFetchBtn || !fetchResult) {
      return; // Elements not found, not on admin page
    }

    runFetchBtn.addEventListener('click', async function() {
      const token = manualTokenInput.value.trim() || window.__ADMIN_UI.defaultToken;
      
      if (!token) {
        fetchResult.textContent = 'Error: No admin token provided';
        fetchResult.style.color = '#d32f2f';
        return;
      }

      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Fetching...';
      fetchResult.textContent = 'Sending request...';
      fetchResult.style.color = '#333';

      try {
        const endpoint = window.__ADMIN_UI.fetchEndpoint;
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            hits: 20,
            offset: 1
          })
        });

        const data = await response.json();
        
        fetchResult.textContent = JSON.stringify(data, null, 2);
        
        if (response.ok && data.success) {
          fetchResult.style.color = '#2e7d32';
        } else {
          fetchResult.style.color = '#d32f2f';
        }
      } catch (error) {
        fetchResult.textContent = `Error: ${error.message}`;
        fetchResult.style.color = '#d32f2f';
      } finally {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    });
  });
})();
