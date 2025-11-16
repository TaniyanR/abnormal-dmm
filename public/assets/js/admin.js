// public/assets/js/admin.js
// Admin UI JavaScript for triggering manual fetch

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
      console.error('Required elements not found');
      return;
    }

    runFetchBtn.addEventListener('click', function() {
      handleManualFetch(manualTokenInput, fetchResultDiv);
    });
  }

  async function handleManualFetch(tokenInput, resultDiv) {
    const config = window.__ADMIN_UI || {};
    const endpoint = config.fetchEndpoint || '/public/api/admin/fetch.php';
    const defaultToken = config.defaultToken || '';
    
    // Use token from input field or fall back to default
    const token = (tokenInput && tokenInput.value.trim()) || defaultToken;

    if (!token) {
      resultDiv.textContent = 'Error: No ADMIN_TOKEN provided. Please enter token in the input field.';
      resultDiv.style.color = '#d32f2f';
      return;
    }

    resultDiv.textContent = 'Fetching... Please wait.';
    resultDiv.style.color = '#666';

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
      });

      const data = await response.json();
      
      if (response.ok) {
        resultDiv.textContent = JSON.stringify(data, null, 2);
        resultDiv.style.color = '#2e7d32';
      } else {
        resultDiv.textContent = `Error (${response.status}): ${JSON.stringify(data, null, 2)}`;
        resultDiv.style.color = '#d32f2f';
      }
    } catch (error) {
      resultDiv.textContent = `Fetch failed: ${error.message}`;
      resultDiv.style.color = '#d32f2f';
    }
  }
})();
