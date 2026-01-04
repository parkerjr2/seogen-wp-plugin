<?php
/**
 * City Hub Audience Framing Templates
 * 
 * Provides audience-specific framing paragraphs for city hub pages
 * to clarify who the hub is for and how needs differ across audiences.
 * 
 * Improves Helpful Content + EEAT signals and reduces hub clustering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_City_Hub_Audience_Templates {
	
	const TEMPLATE_VERSION = '1.0';
	
	/**
	 * Homeowner/Residential audience templates
	 * For hubs targeting homeowners, families, residential properties
	 */
	const HOMEOWNER_VARIANTS = array(
		// Variant 1: Planning and disruption focus
		"This hub is for homeowners in {city} who need help with service decisions for their property. Whether you're dealing with an urgent issue, planning a project, or scheduling routine care, the type of work you need determines which service page is most relevant. Each linked page focuses on a specific situation to help you understand the process, timeline, and considerations for your home.",
		
		// Variant 2: Occupied space focus
		"Homeowners in {city} face different service needs depending on the situation—from emergency repairs that can't wait to planned upgrades that require careful scheduling. This hub organizes our local services by the type of work involved, so you can find information specific to your circumstances. Each service page addresses a distinct scenario, helping you make informed decisions for your occupied living space.",
	);
	
	/**
	 * Business/Commercial/Property Manager audience templates
	 * For hubs targeting businesses, commercial properties, property managers
	 */
	const BUSINESS_VARIANTS = array(
		// Variant 1: Documentation and downtime focus
		"This hub is for {city} businesses and property managers who need to coordinate service work for commercial or rental properties. Different situations require different approaches—emergency response, scheduled maintenance, or compliance work each involve distinct planning and documentation needs. The service pages below are organized by work type to help you find the specific information relevant to your facility or tenant requirements.",
		
		// Variant 2: Operational continuity focus
		"Businesses and property managers in {city} must balance service needs with operational continuity and tenant obligations. This hub organizes our local services by the nature of the work—urgent repairs, preventative maintenance, or planned installations each require different scheduling and coordination. Each linked page focuses on a specific service scenario to help you plan effectively and minimize disruption to your operations or tenants.",
	);
	
	/**
	 * General/Neutral audience templates
	 * For hubs where audience is unclear or mixed
	 */
	const GENERAL_VARIANTS = array(
		// Variant 1: Situation-based organization
		"This hub organizes our local services in {city} by the type of situation you're facing. Service needs vary widely—from urgent issues requiring immediate response to planned projects that allow for scheduling flexibility. Each service page below focuses on a specific type of work, helping you find information relevant to your particular circumstances and decision-making timeline.",
		
		// Variant 2: Need differentiation focus
		"Local service needs in {city} differ based on urgency, scope, and the nature of the work required. This hub groups our services by situation type rather than listing everything together. Whether you need emergency assistance, routine maintenance, or a planned upgrade, each linked page addresses a distinct scenario with its own considerations and process details.",
	);
	
	/**
	 * Detect audience type from hub label and key
	 * 
	 * @param string $hub_label Hub label (e.g., "Residential Roofing")
	 * @param string $hub_key Hub key (e.g., "residential-roofing")
	 * @return string Audience type: homeowner, business, or general
	 */
	public static function detect_audience( $hub_label, $hub_key ) {
		$combined = strtolower( $hub_label . ' ' . $hub_key );
		
		// Check for homeowner/residential indicators
		$homeowner_keywords = array( 'residential', 'home', 'homeowner', 'house', 'family' );
		foreach ( $homeowner_keywords as $keyword ) {
			if ( false !== strpos( $combined, $keyword ) ) {
				return 'homeowner';
			}
		}
		
		// Check for business/commercial indicators
		$business_keywords = array( 'commercial', 'business', 'facility', 'property', 'tenant', 'retail', 'industrial' );
		foreach ( $business_keywords as $keyword ) {
			if ( false !== strpos( $combined, $keyword ) ) {
				return 'business';
			}
		}
		
		// Default to general
		return 'general';
	}
	
	/**
	 * Get audience framing paragraph for city hub
	 * 
	 * @param string $hub_label Hub label
	 * @param string $hub_key Hub key
	 * @param string $city_name City name (e.g., "Tulsa")
	 * @param string $city_slug City slug for deterministic selection
	 * @return string Framing paragraph with city name inserted
	 */
	public static function get_audience_framing( $hub_label, $hub_key, $city_name, $city_slug ) {
		// Detect audience type
		$audience = self::detect_audience( $hub_label, $hub_key );
		
		// Get appropriate template variants
		$variants = array();
		switch ( $audience ) {
			case 'homeowner':
				$variants = self::HOMEOWNER_VARIANTS;
				break;
			case 'business':
				$variants = self::BUSINESS_VARIANTS;
				break;
			default:
				$variants = self::GENERAL_VARIANTS;
				break;
		}
		
		// Select variant deterministically
		$hash_input = $city_slug . '|' . $hub_key . '|' . self::TEMPLATE_VERSION;
		$hash = crc32( $hash_input );
		$variant_index = abs( $hash ) % count( $variants );
		
		$template = $variants[ $variant_index ];
		
		// Replace {city} placeholder with actual city name
		$framing = str_replace( '{city}', $city_name, $template );
		
		return $framing;
	}
}
