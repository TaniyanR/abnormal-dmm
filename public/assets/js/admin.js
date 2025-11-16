// public/assets/js/admin.js
// JavaScript for admin UI manual fetch trigger

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

    if (!runFetchBtn) {
      console.warn('runFetchBtn not found');
      return;
    }

    runFetchBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      // Get config from global
      const config = window.__ADMIN_UI || {};
      const endpoint = config.fetchEndpoint || '/public/api/admin/fetch.php';
      const token = manualTokenInput.value.trim() || config.defaultToken || '';

      if (!token) {
        fetchResultDiv.textContent = 'Error: No ADMIN_TOKEN provided. Enter token or set default.';
        return;
      }

      // Disable button during fetch
      runFetchBtn.disabled = true;
      fetchResultDiv.textContent = 'Running fetch...';

      // Call admin fetch endpoint
      fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({})
      })
      .then(function(response) {
        return response.json().then(function(data) {
          return { status: response.status, data: data };
        });
      })
      .then(function(result) {
        fetchResultDiv.textContent = 'Status: ' + result.status + '\n\n' + 
                                       JSON.stringify(result.data, null, 2);
      })
      .catch(function(error) {
        fetchResultDiv.textContent = 'Error: ' + error.message;
      })
      .finally(function() {
        runFetchBtn.disabled = false;
      });
    });
  }
})();
