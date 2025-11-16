// public/assets/js/admin.js
// Handle manual fetch button click for admin UI

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    var runFetchBtn = document.getElementById('runFetchBtn');
    var fetchResult = document.getElementById('fetchResult');
    var manualTokenInput = document.getElementById('manualToken');

    if (!runFetchBtn || !fetchResult) {
      return;
    }

    runFetchBtn.addEventListener('click', function() {
      // Get token from input or use default from window.__ADMIN_UI
      var token = manualTokenInput ? manualTokenInput.value.trim() : '';
      if (!token && window.__ADMIN_UI && window.__ADMIN_UI.defaultToken) {
        token = window.__ADMIN_UI.defaultToken;
      }

      if (!token) {
        fetchResult.textContent = 'Error: No ADMIN_TOKEN provided.';
        fetchResult.style.background = '#f8d7da';
        fetchResult.style.color = '#721c24';
        return;
      }

      var endpoint = (window.__ADMIN_UI && window.__ADMIN_UI.fetchEndpoint) 
        ? window.__ADMIN_UI.fetchEndpoint 
        : '/public/api/admin/fetch.php';

      // Disable button and show loading
      runFetchBtn.disabled = true;
      fetchResult.textContent = 'Fetching... please wait.';
      fetchResult.style.background = '#fff3cd';
      fetchResult.style.color = '#856404';

      // Call the fetch endpoint
      fetch(endpoint, {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + token,
          'Content-Type': 'application/json'
        }
      })
      .then(function(response) {
        return response.json().then(function(data) {
          return {
            status: response.status,
            data: data
          };
        });
      })
      .then(function(result) {
        // Display the result
        fetchResult.textContent = JSON.stringify(result.data, null, 2);
        
        if (result.status >= 200 && result.status < 300) {
          fetchResult.style.background = '#d4edda';
          fetchResult.style.color = '#155724';
        } else {
          fetchResult.style.background = '#f8d7da';
          fetchResult.style.color = '#721c24';
        }
      })
      .catch(function(error) {
        fetchResult.textContent = 'Error: ' + error.message;
        fetchResult.style.background = '#f8d7da';
        fetchResult.style.color = '#721c24';
      })
      .finally(function() {
        runFetchBtn.disabled = false;
      });
    });
  });
})();
