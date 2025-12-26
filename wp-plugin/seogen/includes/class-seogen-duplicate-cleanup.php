<?php
/**
 * Duplicate Page Cleanup Utility
 * 
 * Finds and removes duplicate service pages based on canonical key.
 * Keeps the most recent version and trashes duplicates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Duplicate_Cleanup {
	
	/**
	 * Find all duplicate service pages
	 * 
	 * @return array Array of duplicates grouped by canonical key
	 */
	public static function find_duplicates() {
		$args = array(
			'post_type'      => 'service_page',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		
		$all_posts = get_posts( $args );
		$key_map = array();
		
		// Group posts by canonical key
		foreach ( $all_posts as $post_id ) {
			$key = get_post_meta( $post_id, '_hyper_local_key', true );
			if ( empty( $key ) ) {
				// Try alternate meta key
				$key = get_post_meta( $post_id, '_seogen_canonical_key', true );
			}
			
			if ( ! empty( $key ) ) {
				if ( ! isset( $key_map[ $key ] ) ) {
					$key_map[ $key ] = array();
				}
				$key_map[ $key ][] = $post_id;
			}
		}
		
		// Filter to only duplicates (more than 1 post per key)
		$duplicates = array();
		foreach ( $key_map as $key => $post_ids ) {
			if ( count( $post_ids ) > 1 ) {
				$duplicates[ $key ] = $post_ids;
			}
		}
		
		return $duplicates;
	}
	
	/**
	 * Clean up duplicates - keep most recent, trash the rest
	 * 
	 * @param bool $dry_run If true, only report what would be deleted
	 * @return array Results with counts and details
	 */
	public static function cleanup_duplicates( $dry_run = false ) {
		$duplicates = self::find_duplicates();
		$results = array(
			'total_keys' => count( $duplicates ),
			'total_duplicates' => 0,
			'trashed' => 0,
			'kept' => 0,
			'details' => array(),
		);
		
		foreach ( $duplicates as $key => $post_ids ) {
			$duplicate_count = count( $post_ids ) - 1; // Minus 1 because we keep one
			$results['total_duplicates'] += $duplicate_count;
			
			// Sort by date - most recent first
			usort( $post_ids, function( $a, $b ) {
				$date_a = get_post_field( 'post_modified', $a );
				$date_b = get_post_field( 'post_modified', $b );
				return strtotime( $date_b ) - strtotime( $date_a );
			});
			
			// Keep the first (most recent), trash the rest
			$keep_id = array_shift( $post_ids );
			$keep_title = get_the_title( $keep_id );
			$results['kept']++;
			
			$trashed_ids = array();
			foreach ( $post_ids as $trash_id ) {
				if ( ! $dry_run ) {
					wp_trash_post( $trash_id );
				}
				$trashed_ids[] = $trash_id;
				$results['trashed']++;
			}
			
			$results['details'][] = array(
				'key' => $key,
				'title' => $keep_title,
				'kept_id' => $keep_id,
				'trashed_ids' => $trashed_ids,
				'duplicate_count' => $duplicate_count,
			);
		}
		
		return $results;
	}
	
	/**
	 * Get summary of duplicates without cleaning up
	 * 
	 * @return array Summary information
	 */
	public static function get_duplicate_summary() {
		$duplicates = self::find_duplicates();
		$total_duplicates = 0;
		
		foreach ( $duplicates as $post_ids ) {
			$total_duplicates += count( $post_ids ) - 1; // Minus 1 because we keep one
		}
		
		return array(
			'duplicate_groups' => count( $duplicates ),
			'total_duplicates' => $total_duplicates,
			'total_pages' => array_sum( array_map( 'count', $duplicates ) ),
		);
	}
}
