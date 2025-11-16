/**
 * public/assets/js/admin.js
 * Helper functions for admin UI operations
 */

/**
 * Trigger manual fetch from DMM API
 * Called from admin UI button
 */
async function triggerManualFetch() {
    const btn = document.getElementById('manualFetchBtn');
    const responseDiv = document.getElementById('fetchResponse');
    
    if (!btn || !responseDiv) {
        console.error('Required elements not found');
        return;
    }
    
    // Get admin token from global variable
    const adminToken = window.ADMIN_TOKEN || '';
    const apiEndpoint = window.API_ENDPOINT || '/api/admin/fetch';
    
    if (!adminToken) {
        showResponse({
            success: false,
            error: 'Admin token not available'
        }, 'error');
        return;
    }
    
    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '⏳ Fetching... <span class="loading"></span>';
    responseDiv.style.display = 'none';
    responseDiv.className = '';
    
    try {
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${adminToken}`
            },
            body: JSON.stringify({
                hits: 20,
                offset: 1
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            showResponse(data, 'success');
        } else {
            showResponse(data, 'error');
        }
        
    } catch (error) {
        showResponse({
            success: false,
            error: 'Network error: ' + error.message
        }, 'error');
    } finally {
        // Reset button
        btn.disabled = false;
        btn.innerHTML = '▶️ Run Manual Fetch';
    }
}

/**
 * Display API response in the UI
 * 
 * @param {Object} data - Response data from API
 * @param {string} type - Type of response ('success' or 'error')
 */
function showResponse(data, type) {
    const responseDiv = document.getElementById('fetchResponse');
    
    if (!responseDiv) {
        return;
    }
    
    // Format the response nicely
    let content = '';
    
    if (type === 'success') {
        responseDiv.style.backgroundColor = '#d4edda';
        responseDiv.style.color = '#155724';
        responseDiv.style.borderColor = '#c3e6cb';
        
        content = '✅ Success!\n\n';
        
        if (data.message) {
            content += `Message: ${data.message}\n`;
        }
        
        if (data.data) {
            content += '\nDetails:\n';
            content += `- Items Processed: ${data.data.items_processed || 0}\n`;
            content += `- Total Result Count: ${data.data.total_result_count || 0}\n`;
            content += `- Execution Time: ${data.data.execution_ms || 0}ms\n`;
        }
        
    } else {
        responseDiv.style.backgroundColor = '#f8d7da';
        responseDiv.style.color = '#721c24';
        responseDiv.style.borderColor = '#f5c6cb';
        
        content = '❌ Error\n\n';
        
        if (data.error) {
            content += `Error: ${data.error}\n`;
        }
        
        if (data.errors && Array.isArray(data.errors)) {
            content += '\nErrors:\n';
            data.errors.forEach(err => {
                content += `- ${err}\n`;
            });
        }
    }
    
    // Add full response for debugging
    content += '\n--- Full Response ---\n';
    content += JSON.stringify(data, null, 2);
    
    responseDiv.textContent = content;
    responseDiv.style.display = 'block';
    
    // Scroll to response
    responseDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Auto-save indication for forms (optional enhancement)
 * Can be used to show unsaved changes warning
 */
function trackFormChanges() {
    const form = document.querySelector('form');
    if (!form) return;
    
    let hasChanges = false;
    
    form.addEventListener('input', () => {
        hasChanges = true;
    });
    
    form.addEventListener('submit', () => {
        hasChanges = false;
    });
    
    // Warn before leaving if there are unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}

// Initialize form tracking on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', trackFormChanges);
} else {
    trackFormChanges();
}
