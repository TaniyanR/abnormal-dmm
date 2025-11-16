/**
 * public/assets/js/admin.js
 * Frontend JavaScript for admin UI functionality
 * Handles manual fetch trigger and displays results
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        const runFetchBtn = document.getElementById('runFetchBtn');
        const manualTokenInput = document.getElementById('manualToken');
        const fetchResultDiv = document.getElementById('fetchResult');
        const adminTokenField = document.getElementById('adminTokenField');

        // Get config from global (set by PHP)
        const config = window.__ADMIN_UI || {
            fetchEndpoint: '/api/admin/fetch',
            defaultToken: ''
        };

        // Handle manual fetch button click
        if (runFetchBtn) {
            runFetchBtn.addEventListener('click', function() {
                const token = manualTokenInput.value.trim() || config.defaultToken;

                if (!token) {
                    fetchResultDiv.textContent = 'Error: Please enter ADMIN_TOKEN';
                    fetchResultDiv.style.background = '#f8d7da';
                    fetchResultDiv.style.color = '#721c24';
                    return;
                }

                // Show loading state
                fetchResultDiv.textContent = 'Fetching from DMM API...';
                fetchResultDiv.style.background = '#fff3cd';
                fetchResultDiv.style.color = '#856404';
                runFetchBtn.disabled = true;

                // Call the admin fetch endpoint
                fetch(config.fetchEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify({
                        hits: 20,
                        offset: 1
                    })
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
                    runFetchBtn.disabled = false;

                    if (result.status === 200 && result.data.success) {
                        fetchResultDiv.textContent = JSON.stringify(result.data, null, 2);
                        fetchResultDiv.style.background = '#d4edda';
                        fetchResultDiv.style.color = '#155724';
                    } else {
                        fetchResultDiv.textContent = 'Error: ' + JSON.stringify(result.data, null, 2);
                        fetchResultDiv.style.background = '#f8d7da';
                        fetchResultDiv.style.color = '#721c24';
                    }
                })
                .catch(function(error) {
                    runFetchBtn.disabled = false;
                    fetchResultDiv.textContent = 'Network error: ' + error.message;
                    fetchResultDiv.style.background = '#f8d7da';
                    fetchResultDiv.style.color = '#721c24';
                });
            });
        }

        // Handle form submission for settings
        const settingsForm = document.getElementById('settingsForm');
        if (settingsForm && adminTokenField) {
            settingsForm.addEventListener('submit', function(e) {
                // Get token for form submission
                const token = manualTokenInput.value.trim() || config.defaultToken;
                
                if (!token) {
                    e.preventDefault();
                    alert('Please enter ADMIN_TOKEN to save settings');
                    return false;
                }
                
                // Set the token in the hidden field
                adminTokenField.value = token;
            });
        }

        // If default token is available, pre-fill the manual token input
        if (config.defaultToken && manualTokenInput) {
            manualTokenInput.placeholder = 'ADMIN_TOKEN (loaded from config)';
        }
    });
})();
