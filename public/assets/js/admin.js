// public/assets/js/admin.js
// Admin UI JavaScript for manual fetch trigger

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

    // Get configuration from global
    const config = window.__ADMIN_UI || {};
    const fetchEndpoint = config.fetchEndpoint || '/public/api/admin/fetch.php';
    const defaultToken = config.defaultToken || '';

    runFetchBtn.addEventListener('click', async function() {
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Running...';
      fetchResult.textContent = 'Fetching...';
      fetchResult.style.background = '#fff3cd';
      fetchResult.style.color = '#856404';

      try {
        // Use token from input or default
        const token = manualTokenInput.value.trim() || defaultToken;
        
        if (!token) {
          throw new Error('No admin token provided');
        }

        const headers = {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        };

        const response = await fetch(fetchEndpoint, {
          method: 'POST',
          headers: headers,
          body: JSON.stringify({})
        });

        const data = await response.json();

        // Display result
        if (response.ok) {
          fetchResult.style.background = '#d4edda';
          fetchResult.style.color = '#155724';
          fetchResult.textContent = 'Success!\n' + JSON.stringify(data, null, 2);
        } else {
          fetchResult.style.background = '#f8d7da';
          fetchResult.style.color = '#721c24';
          fetchResult.textContent = 'Error: ' + (data.error || data.message || 'Unknown error') + '\n' + JSON.stringify(data, null, 2);
        }
      } catch (error) {
        fetchResult.style.background = '#f8d7da';
        fetchResult.style.color = '#721c24';
        fetchResult.textContent = 'Error: ' + error.message;
      } finally {
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    });

    // Set default token if available
    if (defaultToken && manualTokenInput) {
      manualTokenInput.value = defaultToken;
    }
  });
})();
