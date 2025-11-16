/**
 * public/assets/js/admin.js
 * JavaScript for admin UI - handles manual fetch trigger
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
    const fetchResultDiv = document.getElementById('fetchResult');

    if (!runFetchBtn) {
      console.warn('Run fetch button not found');
      return;
    }

    runFetchBtn.addEventListener('click', async function() {
      const token = manualTokenInput?.value?.trim() || window.__ADMIN_UI?.defaultToken || '';
      const endpoint = window.__ADMIN_UI?.fetchEndpoint || '/public/api/admin/fetch.php';

      if (!token) {
        alert('Please provide ADMIN_TOKEN');
        return;
      }

      // Disable button during fetch
      runFetchBtn.disabled = true;
      runFetchBtn.textContent = 'Fetching...';
      
      if (fetchResultDiv) {
        fetchResultDiv.textContent = 'Sending request...';
        fetchResultDiv.style.background = '#fff3cd';
      }

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-Admin-Token': token
          },
          body: JSON.stringify({
            action: 'fetch'
          })
        });

        const contentType = response.headers.get('content-type');
        let result;
        
        if (contentType && contentType.includes('application/json')) {
          result = await response.json();
        } else {
          const text = await response.text();
          result = { raw_response: text, status: response.status };
        }

        // Display result
        if (fetchResultDiv) {
          fetchResultDiv.textContent = JSON.stringify(result, null, 2);
          
          if (response.ok) {
            fetchResultDiv.style.background = '#d4edda';
          } else {
            fetchResultDiv.style.background = '#f8d7da';
          }
        }

        if (!response.ok) {
          console.error('Fetch failed:', result);
        }

      } catch (error) {
        console.error('Error during manual fetch:', error);
        
        if (fetchResultDiv) {
          fetchResultDiv.textContent = `Error: ${error.message}\n\nThe endpoint ${endpoint} may not exist yet or is not accessible.`;
          fetchResultDiv.style.background = '#f8d7da';
        }
      } finally {
        // Re-enable button
        runFetchBtn.disabled = false;
        runFetchBtn.textContent = 'Run manual fetch';
      }
    });
  }
})();
