/**
 * Admin-Assisted Import Heartbeat
 * Phase 3: Universal fallback for auto-import
 * Runs on all SEOgen admin pages to import pages in background
 */

(function($) {
    'use strict';
    
    // Configuration
    const HEARTBEAT_INTERVAL = 15000; // 15 seconds
    let heartbeatTimer = null;
    let isRunning = false;
    
    /**
     * Initialize heartbeat
     */
    function initHeartbeat() {
        // Check if we have active jobs
        if (!window.seogenActiveJobs || window.seogenActiveJobs.length === 0) {
            return;
        }
        
        console.log('[SEOgen Import] Starting heartbeat for ' + window.seogenActiveJobs.length + ' active jobs');
        
        // Start heartbeat
        startHeartbeat();
    }
    
    /**
     * Start heartbeat timer
     */
    function startHeartbeat() {
        if (heartbeatTimer) {
            return; // Already running
        }
        
        // Run immediately
        runImportBatch();
        
        // Then run every 15 seconds
        heartbeatTimer = setInterval(runImportBatch, HEARTBEAT_INTERVAL);
    }
    
    /**
     * Stop heartbeat timer
     */
    function stopHeartbeat() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
            console.log('[SEOgen Import] Heartbeat stopped');
        }
    }
    
    /**
     * Run import batch for all active jobs
     */
    function runImportBatch() {
        if (isRunning) {
            return; // Don't overlap requests
        }
        
        if (!window.seogenActiveJobs || window.seogenActiveJobs.length === 0) {
            stopHeartbeat();
            return;
        }
        
        isRunning = true;
        
        // Process each active job
        const promises = window.seogenActiveJobs.map(function(jobId) {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'seogen_run_import_batch',
                    job_id: jobId
                },
                timeout: 30000
            }).then(function(response) {
                if (response.success && response.data) {
                    handleBatchResult(jobId, response.data);
                }
            }).catch(function(error) {
                console.error('[SEOgen Import] Batch failed for job ' + jobId, error);
            });
        });
        
        // Wait for all batches to complete
        Promise.all(promises).finally(function() {
            isRunning = false;
        });
    }
    
    /**
     * Handle batch result
     */
    function handleBatchResult(jobId, result) {
        console.log('[SEOgen Import] Job ' + jobId + ': imported=' + result.imported + ', failed=' + result.failed + ', remaining=' + result.remaining);
        
        // If no items remaining, remove from active jobs
        if (result.remaining === 0) {
            const index = window.seogenActiveJobs.indexOf(jobId);
            if (index > -1) {
                window.seogenActiveJobs.splice(index, 1);
                console.log('[SEOgen Import] Job ' + jobId + ' complete, removed from active jobs');
            }
            
            // Stop heartbeat if no more active jobs
            if (window.seogenActiveJobs.length === 0) {
                stopHeartbeat();
            }
        }
        
        // Trigger custom event for UI updates
        $(document).trigger('seogen:import:progress', {
            jobId: jobId,
            imported: result.imported,
            failed: result.failed,
            remaining: result.remaining,
            errors: result.errors || []
        });
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initHeartbeat();
    });
    
    // Expose stop function for debugging
    window.seogenStopImportHeartbeat = stopHeartbeat;
    
})(jQuery);
