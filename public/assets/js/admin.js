/**
 * public/assets/js/admin.js
 * Handles manual fetch trigger from admin UI
 */

(function() {
  'use strict';

  // Wait for DOM to be ready
  document.addEventListener('DOMContentLoaded', function() {
    const runFetchBtn = document.getElementById('runFetchBtn');
    const manualTokenInput = document.getElementById('manualToken');
    const fetchResultDiv = document.getElementById('fetchResult');

    if (!runFetchBtn || !fetchResultDiv) {
      console.warn('Admin UI elements not found');
      return;
    }

    // Get configuration from window.__ADMIN_UI (set by PHP)
    const config = window.__ADMIN_UI || {};
    const fetchEndpoint = config.fetchEndpoint || '/api/admin/fetch';
    const defaultToken = config.defaultToken || '';

    runFetchBtn.addEventListener('click', async function() {
      // Disable button during fetch
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Fetching...';
      fetchResultDiv.textContent = 'Sending request to ' + fetchEndpoint + '...';

      // Get token (use manual input if provided, otherwise use default)
      const token = manualTokenInput.value.trim() || defaultToken;

      if (!token) {
        fetchResultDiv.textContent = 'Error: No ADMIN_TOKEN provided. Please enter one or set default in .env';
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
        return;
      }

      try {
        const response = await fetch(fetchEndpoint, {
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
        
        // Format and display the result
        fetchResultDiv.textContent = JSON.stringify(data, null, 2);
        
        // Add status indicator
        if (response.ok && data.success) {
          fetchResultDiv.style.borderColor = '#28a745';
          fetchResultDiv.style.backgroundColor = '#d4edda';
        } else {
          fetchResultDiv.style.borderColor = '#dc3545';
          fetchResultDiv.style.backgroundColor = '#f8d7da';
        }

      } catch (error) {
        fetchResultDiv.textContent = 'Error: ' + error.message + '\n\nMake sure the fetch endpoint is accessible and the token is valid.';
        fetchResultDiv.style.borderColor = '#dc3545';
        fetchResultDiv.style.backgroundColor = '#f8d7da';
      } finally {
        // Re-enable button
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    });
  });

})();
