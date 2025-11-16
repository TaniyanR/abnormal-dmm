/**
 * admin.js
 * Client-side JavaScript for admin API settings page.
 * Handles manual fetch trigger and displays results.
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
      console.error('Required DOM elements not found');
      return;
    }

    runFetchBtn.addEventListener('click', handleManualFetch);

    async function handleManualFetch() {
      if (!window.__ADMIN_UI) {
        fetchResultDiv.textContent = 'Error: __ADMIN_UI configuration not found';
        fetchResultDiv.style.color = 'red';
        return;
      }

      const endpoint = window.__ADMIN_UI.fetchEndpoint;
      const defaultToken = window.__ADMIN_UI.defaultToken || '';
      const token = manualTokenInput.value.trim() || defaultToken;

      if (!token) {
        fetchResultDiv.textContent = 'Error: ADMIN_TOKEN required. Please provide token in the input field.';
        fetchResultDiv.style.color = 'red';
        return;
      }

      // Disable button and show loading state
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Running...';
      fetchResultDiv.textContent = 'Fetching data from DMM API...';
      fetchResultDiv.style.color = 'inherit';

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          }
        });

        const data = await response.json();
        
        // Format the response nicely
        fetchResultDiv.textContent = JSON.stringify(data, null, 2);
        
        if (response.ok && data.success) {
          fetchResultDiv.style.color = 'green';
        } else {
          fetchResultDiv.style.color = 'red';
        }
      } catch (error) {
        fetchResultDiv.textContent = `Error: ${error.message}`;
        fetchResultDiv.style.color = 'red';
      } finally {
        // Re-enable button
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    }
  }
})();
