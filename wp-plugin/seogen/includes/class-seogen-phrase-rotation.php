<?php
/**
 * Phrase Rotation Library
 * 
 * Provides deterministic phrase rotation for service_city hero/intro lead sentences
 * to reduce repetitive phrasing across pages while maintaining consistent meaning.
 * 
 * Uses intent_group-specific variants with {city} and {service} placeholders.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Phrase_Rotation {
	
	const TEMPLATE_VERSION = '1.0';
	
	/**
	 * Hero/Intro lead sentence variants by intent_group
	 * {city} and {service} placeholders will be replaced
	 */
	const HERO_LEAD_VARIANTS = array(
		'emergency_response' => array(
			'When you need help fast in {city}, we\'re here to get things under control.',
			'Urgent problems in {city} can\'t wait—this page explains what to expect next.',
			'If something needs immediate attention in {city}, this is the right place to start.',
			'For urgent service in {city}, we focus on quick response and clear next steps.',
			'This page is for time-sensitive situations in {city} where fast action matters.',
		),
		
		'repair_service' => array(
			'If something isn\'t working properly in {city}, we\'ll help you understand the repair options.',
			'Repair visits in {city} usually start with a quick diagnosis and a clear plan.',
			'This page covers what to expect from {service} repairs in {city}.',
			'When you need a repair in {city}, the goal is a practical fix—not guesswork.',
			'Here\'s how {service} repair service typically works for customers in {city}.',
		),
		
		'inspection_diagnostic' => array(
			'Not sure what\'s going on? An inspection in {city} helps you make the next decision.',
			'This page explains what diagnostic visits in {city} usually include and what you\'ll receive.',
			'If you want clarity before committing to work in {city}, start with an inspection.',
			'Inspections in {city} are designed to document conditions and guide next steps.',
			'Here\'s what to expect from a {service} inspection in {city}.',
		),
		
		'replacement_installation' => array(
			'If replacement is on the table in {city}, this page explains the typical steps and timelines.',
			'Installing something new in {city} starts with a clear evaluation and straightforward options.',
			'This page covers what to expect when planning a {service} replacement in {city}.',
			'Replacement projects in {city} involve planning—here\'s how the process usually works.',
			'If you\'re considering an upgrade in {city}, this is a good starting point.',
		),
		
		'preventative_maintenance' => array(
			'Preventative maintenance in {city} helps reduce surprises and keep things running reliably.',
			'This page explains what routine maintenance visits in {city} typically include.',
			'If you\'d rather prevent issues than react to them in {city}, maintenance is the next step.',
			'Maintenance in {city} focuses on checks, adjustments, and early issue detection.',
			'Here\'s what to expect from a preventative maintenance appointment in {city}.',
		),
		
		'compliance_safety' => array(
			'Compliance checks in {city} focus on documentation and identifying potential concerns.',
			'This page explains what a safety/compliance review in {city} typically covers.',
			'If you need verification in {city}, a compliance-focused inspection is the right starting point.',
			'Compliance services in {city} are about clear findings and actionable next steps.',
			'Here\'s what to expect from a compliance check in {city}.',
		),
	);
	
	/**
	 * Optional: Universal "Why" block first-sentence openers
	 * Can be used to rotate just the first sentence of Why block
	 */
	const WHY_BLOCK_OPENERS = array(
		'This page is here to help you understand what typically happens next.',
		'This page focuses on the decision most people are trying to make in this situation.',
		'This page is designed to set clear expectations before you schedule service.',
		'This page explains the purpose of this service and how it\'s usually handled.',
		'This page helps you decide on the next step with a clearer picture of what\'s involved.',
	);
	
	/**
	 * Get hero lead sentence for service_city page
	 * 
	 * @param string $intent_group Intent group identifier
	 * @param string $service_slug Service slug for deterministic selection
	 * @param string $service_name Service name for {service} placeholder
	 * @param string $city_name City name for {city} placeholder
	 * @param string $city_slug City slug for deterministic selection
	 * @return string Lead sentence with placeholders replaced
	 */
	public static function get_hero_lead_sentence( $intent_group, $service_slug, $service_name, $city_name, $city_slug ) {
		// Check if intent_group has variants
		if ( ! isset( self::HERO_LEAD_VARIANTS[ $intent_group ] ) ) {
			return '';
		}
		
		$variants = self::HERO_LEAD_VARIANTS[ $intent_group ];
		
		// Select variant deterministically
		$hash_input = $service_slug . '|' . $city_slug . '|' . $intent_group . '|hero_lead|' . self::TEMPLATE_VERSION;
		$hash = crc32( $hash_input );
		$variant_index = abs( $hash ) % count( $variants );
		
		$template = $variants[ $variant_index ];
		
		// Replace placeholders
		$sentence = str_replace( '{city}', $city_name, $template );
		$sentence = str_replace( '{service}', strtolower( $service_name ), $sentence );
		
		return $sentence;
	}
	
	/**
	 * Get "Why" block first-sentence opener
	 * 
	 * @param string $intent_group Intent group for deterministic selection
	 * @param string $service_slug Service slug for deterministic selection
	 * @param string $city_slug City slug for deterministic selection
	 * @return string Opener sentence
	 */
	public static function get_why_block_opener( $intent_group, $service_slug, $city_slug ) {
		$variants = self::WHY_BLOCK_OPENERS;
		
		// Select variant deterministically
		$hash_input = $service_slug . '|' . $city_slug . '|' . $intent_group . '|why_opener|' . self::TEMPLATE_VERSION;
		$hash = crc32( $hash_input );
		$variant_index = abs( $hash ) % count( $variants );
		
		return $variants[ $variant_index ];
	}
}
