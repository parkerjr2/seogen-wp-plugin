<?php
/**
 * Service Scope Boundary Templates
 * 
 * Provides "What This Service Is Not" content blocks for service+city pages
 * to clarify service boundaries and improve decision clarity.
 * 
 * These templates define what each service type does NOT cover,
 * helping users self-qualify and reducing overlap between related services.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Scope_Boundary_Templates {
	
	/**
	 * Get scope boundary bullets for a given intent group
	 * 
	 * @param string $intent_group Intent group identifier
	 * @return array List of boundary statements (3-5 bullets)
	 */
	public static function get_scope_boundaries( $intent_group ) {
		$boundaries = array(
			'emergency_response' => array(
				'Non-urgent or scheduled service appointments',
				'Long-term upgrades or full system replacements',
				'Preventative maintenance visits or routine inspections',
				'Diagnostic-only evaluations without immediate action',
			),
			
			'repair_service' => array(
				'Complete system replacement or new installations',
				'Preventative maintenance programs or service contracts',
				'Diagnostic-only inspections without performing repairs',
				'Emergency response outside of standard service hours',
			),
			
			'inspection_diagnostic' => array(
				'Full repair or replacement work',
				'Emergency response or immediate mitigation services',
				'Long-term maintenance agreements or service plans',
				'Installation of new systems or equipment',
			),
			
			'replacement_installation' => array(
				'Minor repairs or temporary fixes',
				'Diagnostic-only evaluations without installation',
				'Routine maintenance or inspection services',
				'Emergency repair response',
			),
			
			'preventative_maintenance' => array(
				'Emergency repairs or urgent service calls',
				'Major system replacements or new installations',
				'One-time corrective repairs without ongoing service',
				'Diagnostic inspections without maintenance work',
			),
			
			'compliance_safety' => array(
				'Repair or replacement work beyond code requirements',
				'Emergency response services',
				'Ongoing maintenance plans or service contracts',
				'Installation of new systems or equipment',
			),
		);
		
		// Return boundaries for the specified intent, or empty array if not found
		return isset( $boundaries[ $intent_group ] ) ? $boundaries[ $intent_group ] : array();
	}
	
	/**
	 * Get formatted scope boundary block HTML
	 * 
	 * @param string $intent_group Intent group identifier
	 * @return string Gutenberg block markup
	 */
	public static function get_scope_boundary_block( $intent_group ) {
		$bullets = self::get_scope_boundaries( $intent_group );
		
		if ( empty( $bullets ) ) {
			return '';
		}
		
		$output = array();
		
		// Heading
		$output[] = '<!-- wp:heading {"level":2,"className":"seogen-scope-boundary-heading"} -->';
		$output[] = '<h2 class="seogen-scope-boundary-heading">What This Service Is Not</h2>';
		$output[] = '<!-- /wp:heading -->';
		$output[] = '';
		
		// Bullet list
		$output[] = '<!-- wp:list {"className":"seogen-scope-boundary-list"} -->';
		$output[] = '<ul class="seogen-scope-boundary-list">';
		
		foreach ( $bullets as $bullet ) {
			$output[] = '<li>' . esc_html( $bullet ) . '</li>';
		}
		
		$output[] = '</ul>';
		$output[] = '<!-- /wp:list -->';
		
		return implode( "\n", $output );
	}
}
