# Resilient Polling Implementation Plan

## Issues to Fix
1. HTTP 0 transport errors change row status to failed
2. pending_import_count becomes negative
3. City hub generation runs multiple times
4. No retry logic for transient failures
5. Results fetched even when complete and nothing pending

## Implementation Steps

### 1. Add Transport Error Detection (Lines 4056-4063)
After `api_get_bulk_job_status()` call, detect transport failure and return cached state.

### 2. Fix pending_import_count Computation (Lines 4071-4079)
Replace subtraction logic with actual row counting.

### 3. Add Smart Results Fetching (Lines 4093-4095)
Skip results fetch when complete and nothing pending.

### 4. Add City Hub Lock (Search for "Service pages complete")
Add transient lock and job flag to prevent repeats.

### 5. Increase HTTP Timeouts
Modify api_get_bulk_job_status() and api_get_bulk_job_results() to use 60s timeout.

## Code Changes

### Change 1: Transport Error Detection
```php
// After line 4056
$status = $this->api_get_bulk_job_status( $api_url, $license_key, $job['api_job_id'] );

// Add transport error detection
if ( is_wp_error( $status ) || empty( $status['ok'] ) || ( isset( $status['code'] ) && 0 === (int) $status['code'] ) ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] transport error on api_get_bulk_job_status, returning cached job state' . PHP_EOL, FILE_APPEND );
    
    // Return current job state without modification
    $response_data = array(
        'status' => isset( $job['status'] ) ? $job['status'] : 'running',
        'rows' => isset( $job['rows'] ) ? $job['rows'] : array(),
        'total_rows' => isset( $job['total_rows'] ) ? (int) $job['total_rows'] : 0,
        'processed' => isset( $job['processed'] ) ? (int) $job['processed'] : 0,
        'success' => isset( $job['success'] ) ? (int) $job['success'] : 0,
        'failed' => isset( $job['failed'] ) ? (int) $job['failed'] : 0,
        'skipped' => isset( $job['skipped'] ) ? (int) $job['skipped'] : 0,
        'warning' => 'Temporary connection issue. Retrying...',
    );
    
    wp_send_json_success( $response_data );
    return;
}
```

### Change 2: Fix pending_import_count
```php
// Replace lines 4071-4079
$pending_import_count = 0;
if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
    foreach ( $job['rows'] as $row ) {
        $row_status = isset( $row['status'] ) ? (string) $row['status'] : '';
        $row_locked = isset( $row['locked'] ) && true === $row['locked'];
        $has_post_id = isset( $row['post_id'] ) && (int) $row['post_id'] > 0;
        
        // Count as pending if: status is pending/queued/processing, OR (no post_id AND not locked AND not success)
        if ( in_array( $row_status, array( 'pending', 'queued', 'processing' ), true ) ) {
            $pending_import_count++;
        } elseif ( ! $has_post_id && ! $row_locked && 'success' !== $row_status ) {
            $pending_import_count++;
        }
    }
}
$pending_import_count = max( 0, $pending_import_count );
file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] pending_import_count=' . $pending_import_count . PHP_EOL, FILE_APPEND );
```

### Change 3: Smart Results Fetching
```php
// Before line 4093, add check
$api_status = isset( $status['data']['status'] ) ? (string) $status['data']['status'] : '';
$cursor = isset( $job['api_cursor'] ) ? (string) $job['api_cursor'] : '';
$results_exhausted = isset( $job['results_exhausted'] ) && true === $job['results_exhausted'];

// Skip results fetch if complete and nothing pending
if ( ( 'complete' === $api_status || 'completed' === $api_status ) && $pending_import_count === 0 && '' === $cursor ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] api_status=complete and nothing pending, skipping results fetch' . PHP_EOL, FILE_APPEND );
    // Return current state
    wp_send_json_success( ... );
    return;
}

if ( $results_exhausted && $pending_import_count === 0 ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] results exhausted and nothing pending, skipping fetch' . PHP_EOL, FILE_APPEND );
    wp_send_json_success( ... );
    return;
}
```

### Change 4: Transport Error on Results
```php
// After line 4094
$results = $this->api_get_bulk_job_results( $api_url, $license_key, $job['api_job_id'], $cursor, $batch_size );

// Add transport error detection for results
if ( is_wp_error( $results ) || empty( $results['ok'] ) || ( isset( $results['code'] ) && 0 === (int) $results['code'] ) ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] transport error on api_get_bulk_job_results, returning cached job state' . PHP_EOL, FILE_APPEND );
    
    // Return current job state without modification
    $response_data = array( ... );
    wp_send_json_success( $response_data );
    return;
}
```

### Change 5: Mark Results Exhausted
```php
// After processing results, check if exhausted
if ( empty( $results['data']['items'] ) && ( ! isset( $results['data']['next_cursor'] ) || null === $results['data']['next_cursor'] ) ) {
    $job['results_exhausted'] = true;
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] results exhausted, marking flag' . PHP_EOL, FILE_APPEND );
} else {
    $job['results_exhausted'] = false;
}
```

### Change 6: City Hub Lock
Search for "Service pages complete, starting city hub content generation" and add:
```php
// Check if city hubs already generated
if ( isset( $job['city_hubs_generated'] ) && true === $job['city_hubs_generated'] ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [CITY HUB PHASE] already completed, skipping' . PHP_EOL, FILE_APPEND );
    // Skip city hub generation
} else {
    $lock_key = 'seogen_cityhub_phase_' . $job_id;
    if ( get_transient( $lock_key ) ) {
        file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [CITY HUB PHASE] locked or in progress, skipping' . PHP_EOL, FILE_APPEND );
        // Skip
    } else {
        set_transient( $lock_key, 1, 300 );
        file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [CITY HUB PHASE] started' . PHP_EOL, FILE_APPEND );
        
        // Run city hub generation
        // ...
        
        // Mark complete
        $job['city_hubs_generated'] = true;
        delete_transient( $lock_key );
        file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [CITY HUB PHASE] completed' . PHP_EOL, FILE_APPEND );
    }
}
```
