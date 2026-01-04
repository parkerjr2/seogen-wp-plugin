<?php
/**
 * Hub Connective Explainer Templates
 * 
 * Provides explanatory content blocks for service hub and city hub pages
 * to clarify topical hierarchy and reduce artificial fragmentation signals.
 * 
 * These templates explain WHY services are organized into separate pages
 * and HOW they differ by situation or user need.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Hub_Explainer_Templates {
	
	const TEMPLATE_VERSION = '1.0';
	
	/**
	 * Service Hub explainer variants
	 * Explain why services are grouped by type of situation or scope
	 */
	const SERVICE_HUB_VARIANTS = array(
		// Variant 1: Situation-focused
		"Each service listed below addresses a different situation or project scope. We've organized them into dedicated pages so you can quickly find information relevant to your specific needs, whether you're dealing with an urgent issue, planning a major project, or scheduling routine care. This structure helps you compare options and understand what each service involves before making a decision.",
		
		// Variant 2: Decision-focused
		"The services below are separated into individual pages because each one involves different considerations, timelines, and decision factors. This organization allows you to explore the specific details, process, and requirements for the type of work you need without sorting through unrelated information. Whether you need immediate assistance or are planning ahead, you'll find focused guidance for your situation.",
	);
	
	/**
	 * City Hub explainer variants
	 * Explain how local services differ by urgency, scope, or work type
	 */
	const CITY_HUB_VARIANTS = array(
		// Variant 1: Local need-focused
		"Local service needs vary widely depending on urgency, project scope, and the type of work required. Each service page below focuses on a specific situation you might face, from emergency responses to planned installations. This organization helps you find providers who specialize in your particular need rather than sorting through general information that may not apply to your circumstances.",
		
		// Variant 2: User decision-focused
		"We've organized local services into separate pages because different situations require different information and decision-making approaches. Whether you're dealing with an emergency, planning a replacement, or scheduling preventative care, each page provides details specific to that type of service. This structure ensures you can quickly access the guidance most relevant to your current needs without navigating through unrelated content.",
	);
	
	/**
	 * Get service hub explainer content
	 * 
	 * @param string $hub_key Hub identifier for deterministic selection
	 * @return string Explainer paragraph
	 */
	public static function get_service_hub_explainer( $hub_key ) {
		$variants = self::SERVICE_HUB_VARIANTS;
		$variant_index = self::select_variant( $hub_key, 'service_hub', count( $variants ) );
		return $variants[ $variant_index ];
	}
	
	/**
	 * Get city hub explainer content
	 * 
	 * @param string $hub_key Hub identifier for deterministic selection
	 * @param string $city_slug City slug for additional entropy
	 * @return string Explainer paragraph
	 */
	public static function get_city_hub_explainer( $hub_key, $city_slug = '' ) {
		$variants = self::CITY_HUB_VARIANTS;
		$hash_input = $hub_key . '|' . $city_slug;
		$variant_index = self::select_variant( $hash_input, 'city_hub', count( $variants ) );
		return $variants[ $variant_index ];
	}
	
	/**
	 * Deterministic variant selection using hash
	 * 
	 * @param string $identifier Unique identifier (hub_key or hub_key|city_slug)
	 * @param string $context Context type (service_hub or city_hub)
	 * @param int $variant_count Number of available variants
	 * @return int Variant index (0-based)
	 */
	private static function select_variant( $identifier, $context, $variant_count ) {
		$hash_input = $identifier . '|' . $context . '|' . self::TEMPLATE_VERSION;
		$hash = crc32( $hash_input );
		return abs( $hash ) % $variant_count;
	}
}
