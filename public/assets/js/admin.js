/**
 * public/assets/js/admin.js
 * Client-side JavaScript for admin UI.
 * Handles manual fetch button click and displays results.
 */

(function() {
  'use strict';

  // Wait for DOM to be ready
  document.addEventListener('DOMContentLoaded', function() {
    const runFetchBtn = document.getElementById('runFetchBtn');
    const fetchResult = document.getElementById('fetchResult');
    const manualTokenInput = document.getElementById('manualToken');

    if (!runFetchBtn || !fetchResult) {
      console.warn('Admin UI elements not found');
      return;
    }

    runFetchBtn.addEventListener('click', async function() {
      const token = manualTokenInput && manualTokenInput.value 
        ? manualTokenInput.value 
        : (window.__ADMIN_UI && window.__ADMIN_UI.defaultToken) || '';

      if (!token) {
        fetchResult.textContent = 'Error: No ADMIN_TOKEN provided';
        fetchResult.style.color = '#721c24';
        return;
      }

      const endpoint = (window.__ADMIN_UI && window.__ADMIN_UI.fetchEndpoint) || '/public/api/admin/fetch.php';

      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Running...';
      fetchResult.textContent = 'Fetching data...';
      fetchResult.style.color = '#000';

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        });

        let data;
        try {
          data = await response.json();
        } catch (jsonError) {
          throw new Error(`Invalid JSON response: ${jsonError.message}`);
        }
        
        // Format JSON for display
        const formatted = JSON.stringify(data, null, 2);
        fetchResult.textContent = formatted;

        if (response.ok && data.success) {
          fetchResult.style.color = '#155724';
        } else {
          fetchResult.style.color = '#721c24';
        }
      } catch (error) {
        fetchResult.textContent = `Error: ${error.message}`;
        fetchResult.style.color = '#721c24';
      } finally {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    });
  });
})();
