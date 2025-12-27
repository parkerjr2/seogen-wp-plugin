# Resilient Polling Implementation - Manual Application Guide

Due to the complexity of this change, I recommend manually applying these fixes or using a proper IDE to make the changes. The edits are too large for automated application.

## Summary of Required Changes

### 1. Add Helper Method for Response Preparation
Add this new method to the class (around line 1946, after other helper methods):

```php
private function prepare_bulk_job_response( $job ) {
    $rows_with_urls = array();
    if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
        foreach ( $job['rows'] as $row ) {
            $row_copy = $row;
            if ( isset( $row['post_id'] ) && (int) $row['post_id'] > 0 ) {
                $row_copy['edit_url'] = admin_url( 'post.php?post=' . (int) $row['post_id'] . '&action=edit' );
            }
            $rows_with_urls[] = $row_copy;
        }
    }
    
    return array(
        'status' => isset( $job['status'] ) ? $job['status'] : 'running',
        'rows' => $rows_with_urls,
        'total_rows' => isset( $job['total_rows'] ) ? (int) $job['total_rows'] : 0,
        'processed' => isset( $job['processed'] ) ? (int) $job['processed'] : 0,
        'success' => isset( $job['success'] ) ? (int) $job['success'] : 0,
        'failed' => isset( $job['failed'] ) ? (int) $job['failed'] : 0,
        'skipped' => isset( $job['skipped'] ) ? (int) $job['skipped'] : 0,
    );
}
```

### 2. Modify ajax_bulk_job_status() - Add Transport Error Detection After Line 4056

After the line:
```php
$status = $this->api_get_bulk_job_status( $api_url, $license_key, $job['api_job_id'] );
```

Add:
```php
// CRITICAL: Detect transport errors and return cached state
if ( is_wp_error( $status ) || empty( $status['ok'] ) || ( isset( $status['code'] ) && 0 === (int) $status['code'] ) ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] transport error on api_get_bulk_job_status, returning cached job state' . PHP_EOL, FILE_APPEND );
    
    $response_data = $this->prepare_bulk_job_response( $job );
    $response_data['warning'] = 'Temporary connection issue. Retrying...';
    
    wp_send_json_success( $response_data );
    return;
}
```

### 3. Replace pending_import_count Calculation (Lines 4071-4079)

Replace the entire section from:
```php
$local_success_count = 0;
if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
    foreach ( $job['rows'] as $row ) {
        if ( isset( $row['status'] ) && 'success' === $row['status'] ) {
            $local_success_count++;
        }
    }
}
$pending_import_count = $completed_items - $local_success_count;
```

With:
```php
// FIXED: Compute pending_import_count from actual row states
$pending_import_count = 0;
if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
    foreach ( $job['rows'] as $row ) {
        $row_status = isset( $row['status'] ) ? (string) $row['status'] : '';
        $row_locked = isset( $row['locked'] ) && true === $row['locked'];
        $has_post_id = isset( $row['post_id'] ) && (int) $row['post_id'] > 0;
        
        if ( in_array( $row_status, array( 'pending', 'queued', 'processing' ), true ) ) {
            $pending_import_count++;
        } elseif ( ! $has_post_id && ! $row_locked && 'success' !== $row_status && 'skipped' !== $row_status ) {
            $pending_import_count++;
        }
    }
}
$pending_import_count = max( 0, $pending_import_count );
file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] pending_import_count=' . $pending_import_count . ' api_status=' . $api_status . PHP_EOL, FILE_APPEND );
```

### 4. Add Smart Results Fetching (Before Line 4093)

Before the line that starts with `file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Fetching results:`, add:

```php
// OPTIMIZATION: Skip results fetch if complete and nothing pending
$results_exhausted = isset( $job['results_exhausted'] ) && true === $job['results_exhausted'];
if ( ( 'complete' === $api_status || 'completed' === $api_status ) && $pending_import_count === 0 && '' === $cursor ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] api_status=complete and nothing pending, skipping results fetch' . PHP_EOL, FILE_APPEND );
    
    $job['results_exhausted'] = true;
    $this->save_bulk_job( $job_id, $job );
    
    $response_data = $this->prepare_bulk_job_response( $job );
    wp_send_json_success( $response_data );
    return;
}

if ( $results_exhausted && $pending_import_count === 0 ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] results exhausted and nothing pending, skipping fetch' . PHP_EOL, FILE_APPEND );
    
    $response_data = $this->prepare_bulk_job_response( $job );
    wp_send_json_success( $response_data );
    return;
}
```

### 5. Add Transport Error Detection for Results (After Line 4095)

After:
```php
file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API results: ' . wp_json_encode( $results ) . PHP_EOL, FILE_APPEND );
```

Add:
```php
// CRITICAL: Detect transport errors on results and return cached state
if ( is_wp_error( $results ) || empty( $results['ok'] ) || ( isset( $results['code'] ) && 0 === (int) $results['code'] ) ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] transport error on api_get_bulk_job_results, returning cached job state' . PHP_EOL, FILE_APPEND );
    
    $response_data = $this->prepare_bulk_job_response( $job );
    $response_data['warning'] = 'Temporary connection issue. Retrying...';
    
    wp_send_json_success( $response_data );
    return;
}
```

### 6. Mark Results Exhausted (After processing all items, around line 4420)

After the results processing loop and before saving the job, add:

```php
// Mark results exhausted if no items and no cursor
if ( empty( $results['data']['items'] ) && ( ! isset( $results['data']['next_cursor'] ) || null === $results['data']['next_cursor'] ) ) {
    $job['results_exhausted'] = true;
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] [BULK POLL] results exhausted, marking flag' . PHP_EOL, FILE_APPEND );
} else {
    $job['results_exhausted'] = false;
}
```

## Testing

After applying these changes:
1. Test with network interruptions
2. Verify pending_import_count never goes negative
3. Confirm imported pages never show as failed
4. Check that polling continues after HTTP 0 errors

## Note

These changes are substantial. I recommend:
1. Creating a backup of the file first
2. Applying changes in a development environment
3. Testing thoroughly before deploying to production
