// admin.js
// JavaScript for admin UI (admin/api_settings.php)
// Handles manual fetch button click

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    const runFetchBtn = document.getElementById('runFetchBtn');
    const manualTokenInput = document.getElementById('manualToken');
    const fetchResultDiv = document.getElementById('fetchResult');

    if (!runFetchBtn) return;

    runFetchBtn.addEventListener('click', async function() {
      // Get token from input or default
      let token = manualTokenInput ? manualTokenInput.value.trim() : '';
      if (!token && window.__ADMIN_UI && window.__ADMIN_UI.defaultToken) {
        token = window.__ADMIN_UI.defaultToken;
      }

      if (!token) {
        fetchResultDiv.textContent = 'Error: No ADMIN_TOKEN provided';
        fetchResultDiv.style.color = '#721c24';
        return;
      }

      const endpoint = (window.__ADMIN_UI && window.__ADMIN_UI.fetchEndpoint) || '/api/admin/fetch';

      // Show loading state
      fetchResultDiv.textContent = 'Running fetch... Please wait.';
      fetchResultDiv.style.color = '#000';
      runFetchBtn.disabled = true;

      try {
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
        
        // Display the result
        fetchResultDiv.textContent = JSON.stringify(data, null, 2);
        
        if (response.ok && data.success) {
          fetchResultDiv.style.color = '#155724';
        } else {
          fetchResultDiv.style.color = '#721c24';
        }
      } catch (error) {
        fetchResultDiv.textContent = `Error: ${error.message}`;
        fetchResultDiv.style.color = '#721c24';
      } finally {
        runFetchBtn.disabled = false;
      }
    });
  });
})();
