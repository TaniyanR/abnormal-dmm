/**
 * admin.js
 * JavaScript for admin API settings page manual fetch functionality
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
      return; // Elements not found, not on the right page
    }

    runFetchBtn.addEventListener('click', function() {
      runManualFetch(fetchResult, manualTokenInput);
    });
  }

  function runManualFetch(resultEl, tokenInput) {
    const endpoint = (window.__ADMIN_UI && window.__ADMIN_UI.fetchEndpoint) || '/api/admin/fetch';
    const token = tokenInput.value.trim() || (window.__ADMIN_UI && window.__ADMIN_UI.defaultToken) || '';

    if (!token) {
      resultEl.textContent = 'Error: ADMIN_TOKEN is required. Please enter it in the input field.';
      resultEl.style.color = '#721c24';
      return;
    }

    resultEl.textContent = 'Running fetch request...';
    resultEl.style.color = '#333';

    const btn = document.getElementById('runFetchBtn');
    if (btn) btn.disabled = true;

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
      }
    })
    .then(function(response) {
      return response.json().then(function(data) {
        return { status: response.status, data: data };
      }).catch(function() {
        return response.text().then(function(text) {
          return { status: response.status, data: text };
        });
      });
    })
    .then(function(result) {
      let output = 'Status: ' + result.status + '\n\n';
      if (typeof result.data === 'object') {
        output += JSON.stringify(result.data, null, 2);
      } else {
        output += result.data;
      }
      
      resultEl.textContent = output;
      
      if (result.status >= 200 && result.status < 300) {
        resultEl.style.color = '#155724';
      } else {
        resultEl.style.color = '#721c24';
      }
    })
    .catch(function(error) {
      resultEl.textContent = 'Fetch error: ' + error.message;
      resultEl.style.color = '#721c24';
    })
    .finally(function() {
      if (btn) btn.disabled = false;
    });
  }
})();
