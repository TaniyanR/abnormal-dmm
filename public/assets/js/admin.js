/**
 * admin.js
 * JavaScript for admin UI manual fetch functionality
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
      return; // Elements not present on this page
    }

    runFetchBtn.addEventListener('click', function() {
      runManualFetch();
    });

    function runManualFetch() {
      const config = window.__ADMIN_UI || {};
      const endpoint = config.fetchEndpoint || '/api/admin/fetch';
      let token = manualTokenInput.value.trim();

      // Use default token if manual input is empty
      if (!token && config.defaultToken) {
        token = config.defaultToken;
      }

      if (!token) {
        fetchResult.textContent = 'Error: No ADMIN_TOKEN provided. Enter token in the field above or ensure default token is set.';
        fetchResult.style.color = '#dc3545';
        return;
      }

      // Disable button and show loading
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Fetching...';
      fetchResult.textContent = 'Sending request to ' + endpoint + '...';
      fetchResult.style.color = '#666';

      // Prepare request
      const requestData = {
        hits: 20,
        offset: 1
      };

      fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify(requestData)
      })
      .then(function(response) {
        return response.json().then(function(data) {
          return { status: response.status, data: data };
        });
      })
      .then(function(result) {
        // Display result
        const formatted = JSON.stringify(result.data, null, 2);
        fetchResult.textContent = 'Response (HTTP ' + result.status + '):\n' + formatted;
        
        if (result.status === 200 && result.data.success) {
          fetchResult.style.color = '#28a745';
        } else {
          fetchResult.style.color = '#dc3545';
        }
      })
      .catch(function(error) {
        fetchResult.textContent = 'Error: ' + error.message;
        fetchResult.style.color = '#dc3545';
      })
      .finally(function() {
        // Re-enable button
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      });
    }
  }
})();
