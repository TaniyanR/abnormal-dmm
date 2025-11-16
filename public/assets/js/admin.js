/**
 * admin.js
 * JavaScript for admin UI manual fetch trigger
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
    const fetchResult = document.getElementById('fetchResult');

    if (!runFetchBtn || !fetchResult) {
      console.warn('Admin UI elements not found');
      return;
    }

    runFetchBtn.addEventListener('click', function() {
      runManualFetch(manualTokenInput, fetchResult, runFetchBtn);
    });
  }

  function runManualFetch(tokenInput, resultDiv, button) {
    // Get the admin configuration
    const config = window.__ADMIN_UI || {};
    const endpoint = config.fetchEndpoint || '/api/admin/fetch';
    
    // Get token: use manual input if provided, otherwise use default
    let token = tokenInput ? tokenInput.value.trim() : '';
    if (!token && config.defaultToken) {
      token = config.defaultToken;
    }

    if (!token) {
      resultDiv.textContent = 'Error: No admin token provided. Please enter ADMIN_TOKEN or set it in configuration.';
      resultDiv.style.backgroundColor = '#f8d7da';
      resultDiv.style.color = '#721c24';
      return;
    }

    // Disable button during fetch
    button.disabled = true;
    button.textContent = 'Fetching...';
    resultDiv.textContent = 'Sending request to ' + endpoint + '...';
    resultDiv.style.backgroundColor = '#fff3cd';
    resultDiv.style.color = '#856404';

    // Prepare request body
    const requestBody = {
      hits: 20,
      offset: 1
    };

    // Send fetch request
    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
      },
      body: JSON.stringify(requestBody)
    })
    .then(function(response) {
      return response.json().then(function(data) {
        return {
          status: response.status,
          ok: response.ok,
          data: data
        };
      });
    })
    .then(function(result) {
      // Display result
      if (result.ok && result.data.success) {
        resultDiv.style.backgroundColor = '#d4edda';
        resultDiv.style.color = '#155724';
        resultDiv.textContent = 'Success!\n\n' + JSON.stringify(result.data, null, 2);
      } else {
        resultDiv.style.backgroundColor = '#f8d7da';
        resultDiv.style.color = '#721c24';
        resultDiv.textContent = 'Error (HTTP ' + result.status + '):\n\n' + JSON.stringify(result.data, null, 2);
      }
    })
    .catch(function(error) {
      resultDiv.style.backgroundColor = '#f8d7da';
      resultDiv.style.color = '#721c24';
      resultDiv.textContent = 'Network error:\n\n' + error.message;
    })
    .finally(function() {
      // Re-enable button
      button.disabled = false;
      button.textContent = 'Run manual fetch';
    });
  }
})();
