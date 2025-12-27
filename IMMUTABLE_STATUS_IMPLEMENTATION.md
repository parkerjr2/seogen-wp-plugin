# Immutable Status Implementation - Remaining Tasks

## Completed ✅
1. Helper functions added:
   - `seogen_is_row_locked()` - checks if row is immutable
   - `seogen_lock_row()` - locks row after successful import
   - `seogen_acquire_mutex()` - acquires transient lock for canonical key
   - `seogen_release_mutex()` - releases transient lock

2. Status protection added to all write paths:
   - API item failed status (line ~4070)
   - Invalid result_json (line ~4095)
   - wp_insert_post/wp_update_post errors (line ~4221)
   - STATUS SYNC protection (line ~4315)

## Remaining Tasks 🔧

### 1. Add Mutex Lock to Post Creation (FOREGROUND)
**Location:** `ajax_bulk_job_status()` around line 4202

**Before post creation, add:**
```php
// Acquire mutex lock
if ( ! $this->seogen_acquire_mutex( $canonical_key ) ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] MUTEX: Could not acquire lock for key=' . $canonical_key . ', will retry later' . PHP_EOL, FILE_APPEND );
    if ( isset( $job['rows'][ $idx ] ) && ! $this->seogen_is_row_locked( $job, $idx ) ) {
        $job['rows'][ $idx ]['status'] = 'pending';
        $job['rows'][ $idx ]['message'] = __( 'Waiting for concurrent operation to complete.', 'seogen' );
    }
    continue;
}

try {
    // Existing duplicate check and post creation code
    // ...
} finally {
    $this->seogen_release_mutex( $canonical_key );
}
```

### 2. Lock Row After Successful Import (FOREGROUND)
**Location:** After post meta is saved, around line 4290

**Replace:**
```php
if ( isset( $job['rows'][ $idx ] ) ) {
    $job['rows'][ $idx ]['status'] = 'success';
    $job['rows'][ $idx ]['message'] = __( 'Imported.', 'seogen' );
    $job['rows'][ $idx ]['post_id'] = $post_id;
}
```

**With:**
```php
if ( isset( $job['rows'][ $idx ] ) ) {
    $this->seogen_lock_row( $job, $idx, $post_id );
}
```

### 3. Add Mutex Lock to Background Processing
**Location:** `process_bulk_job_step()` around line 4704

**Same mutex pattern as foreground:**
```php
// Before post creation
if ( ! $this->seogen_acquire_mutex( $key ) ) {
    file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] BACKGROUND MUTEX: Could not acquire lock for key=' . $key . PHP_EOL, FILE_APPEND );
    continue; // Skip this item, will retry later
}

try {
    // Existing duplicate check and post creation
    // ...
} finally {
    $this->seogen_release_mutex( $key );
}
```

### 4. Lock Row After Background Import
**Location:** After background post meta is saved, around line 4733

**Replace:**
```php
$job['rows'][ $i ]['status'] = 'success';
$job['rows'][ $i ]['message'] = __( 'Page created successfully.', 'seogen' );
$job['rows'][ $i ]['post_id'] = $post_id;
```

**With:**
```php
$this->seogen_lock_row( $job, $i, $post_id );
```

### 5. Protect STATUS SYNC from Overwriting Locked Rows
**Location:** STATUS SYNC loop around line 4338

**Add at start of loop:**
```php
foreach ( $job['rows'] as $idx => $row ) {
    $row_status = isset( $row['status'] ) ? (string) $row['status'] : '';
    
    // CRITICAL: Never update locked rows
    if ( $this->seogen_is_row_locked( $job, $idx ) ) {
        continue;
    }
    
    // Rest of STATUS SYNC logic...
}
```

### 6. Client-Side JS Protection
**File:** `wp-plugin/seogen/admin/js/wizard.js` or bulk job JS

**Add locked row tracking:**
```javascript
// Track locked rows (imported pages)
const lockedRows = new Set();

// When rendering row status
function renderRowStatus(row, idx) {
    // Check if row is locked
    if (row.status === 'success' || row.locked === true || row.post_id > 0) {
        lockedRows.add(idx);
    }
    
    // Don't update locked rows from poll data
    if (lockedRows.has(idx)) {
        return; // Keep existing UI
    }
    
    // Update UI for non-locked rows
    // ...
}

// On poll failure
if (pollError) {
    showBanner('Polling temporarily failed. Imported pages remain safe.');
    // Don't set individual rows to failed
}
```

### 7. Enhanced HTTP 0 Diagnostics
**Already added:** Line 2247 logs HTTP 0 errors to seogen-debug.log

**Additional logging needed:**
- Log when admin-ajax poll handler starts/ends
- Log response codes and timing
- Add to `ajax_bulk_job_status()` start and end

## Testing Checklist

- [ ] Start bulk generation, let some pages import
- [ ] Simulate network failure (block outgoing requests)
- [ ] Verify imported pages stay "Imported" in UI and database
- [ ] Open job in two browser tabs simultaneously
- [ ] Verify no duplicate pages created (check for -2 slugs)
- [ ] Check seogen-debug.log for HTTP 0 errors with clear context
- [ ] Verify locked rows have `locked: true` and `completed_at` timestamp
- [ ] Verify `notes` array contains protection events

## Key Principles

1. **Immutability**: Once `status='success'`, it NEVER changes
2. **Mutex Protection**: Transient locks prevent concurrent duplicate creation
3. **Graceful Degradation**: If mutex fails, retry later (don't fail permanently)
4. **Client-Side Respect**: UI never overwrites locked row status
5. **Comprehensive Logging**: Every protection event is logged
