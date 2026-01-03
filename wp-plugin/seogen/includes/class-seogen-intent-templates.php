<?php
/**
 * Intent-based content templates for doorway-page risk mitigation
 * 
 * This file contains deterministic template variants for:
 * - "Why This Page Exists" blocks
 * - Intent-locked CTA text
 * - Intent-specific Process section structures
 * 
 * Template version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Intent_Templates {
	
	const TEMPLATE_VERSION = '1.0';
	
	const INTENT_GROUPS = array(
		'emergency_response',
		'repair_service',
		'inspection_diagnostic',
		'replacement_installation',
		'preventative_maintenance',
		'compliance_safety',
	);
	
	/**
	 * Get "Why This Page Exists" template variants
	 * 5 variants per intent group, 80-160 words each
	 */
	public static function get_why_variants() {
		return array(
			'emergency_response' => array(
				'When an urgent issue comes up in {city}, waiting too long can make a bad situation worse. Emergency service pages like this are meant for situations where something needs immediate attention—whether it\'s active damage, a safety concern, or a system that stopped working without warning.

This page focuses on what to expect when fast response matters most in {city}, including how emergency service differs from standard repairs and what steps typically come first to stabilize the situation before long-term solutions are discussed.',

				'Emergencies rarely happen at a convenient time, and in {city}, service calls often spike during periods of heavy use or extreme conditions. This page exists to help you understand what qualifies as an emergency and how emergency response typically works when time is a critical factor.

Instead of general service information, this page is focused on immediate response, safety checks, and next steps once the urgent issue is under control.',

				'Emergency service is different from routine work. In {city}, urgent issues often require quick decisions before the full scope of repairs is known. This page is designed to explain that process so you know what happens first, what can usually be addressed right away, and what may need follow-up service later.

If you\'re dealing with a situation that can\'t wait, this page helps set expectations before emergency work begins.',

				'When something suddenly stops working or creates a safety concern in {city}, emergency service focuses on fast action and damage control. This page exists specifically for those moments, not for long-term planning or upgrades.

Here, you\'ll find information tailored to urgent service calls, including how emergency visits are handled and what typically comes next after the immediate issue is addressed.',

				'Emergency service decisions are usually made under pressure. This page exists to help customers in {city} understand what emergency response looks like, what problems are typically handled right away, and how emergency service differs from scheduled work.

The goal is to provide clarity during stressful situations so you know what to expect from an emergency visit.',
			),
			
			'repair_service' => array(
				'Repair services in {city} often start with determining whether a problem can be fixed directly or if larger work may be needed. This page exists to help you understand that decision-making process before scheduling a repair.

It focuses on common repair scenarios, how issues are typically diagnosed, and what factors influence repair options so you can make an informed choice.',

				'When something isn\'t working as it should in {city}, repair service is usually the next step. This page explains how repair visits typically work, what\'s involved in diagnosing the issue, and how repair recommendations are made.

Instead of broad service descriptions, this page is designed to help you understand the repair process from start to finish.',

				'Many repair decisions come down to understanding the cause of a problem and the most practical way to fix it. In {city}, repair work often begins with a focused evaluation before any work is performed.

This page exists to explain what that evaluation looks like and how repair options are typically presented.',

				'Repair services are meant for issues that can be corrected without full replacement. This page focuses on how repair work is approached in {city}, including what\'s typically inspected, repaired, and tested during a service visit.

It\'s designed to help you know what to expect before scheduling repair work.',

				'If you\'re considering a repair in {city}, this page exists to clarify how repair services are handled and how decisions are made once the issue is identified.

Understanding the repair process ahead of time helps avoid surprises and makes it easier to plan next steps.',
			),
			
			'inspection_diagnostic' => array(
				'Inspection and diagnostic services are designed to answer questions before work begins. In {city}, inspections are often used to identify issues, document conditions, or determine whether repairs are needed at all.

This page focuses on what an inspection typically includes, what kind of information you receive afterward, and how inspection results are used to guide next steps.',

				'Not every problem is obvious right away. This page exists to explain how diagnostic and inspection services work in {city}, especially when the goal is understanding the condition of a system rather than fixing it immediately.

It\'s intended for customers who want clarity before deciding on repairs or replacement.',

				'Inspections are often the first step in making informed service decisions. In {city}, diagnostic visits are commonly used to evaluate performance, identify concerns, and provide documented findings.

This page explains what to expect from an inspection and how the results are typically presented.',

				'This page exists for situations where understanding the condition of something matters more than immediate action. Inspection and diagnostic services in {city} are often used for planning, documentation, or verification purposes.

Here, you\'ll find information focused specifically on inspection scope and outcomes.',

				'Diagnostic services help determine what\'s happening before work begins. In {city}, inspections are commonly scheduled when there\'s uncertainty about the cause or severity of an issue.

This page explains how inspections are handled and how findings are used to recommend next steps.',
			),
			
			'replacement_installation' => array(
				'Replacement and installation decisions usually involve comparing options, timelines, and long-term considerations. This page exists to help customers in {city} understand what the replacement process typically looks like before committing to a new installation.

It focuses on evaluation, planning, and what happens from estimate through completion.',

				'When repair is no longer the best option, replacement or installation may be the next step. This page explains how replacement projects are typically handled in {city}, including assessment, planning, and installation stages.

It\'s designed to support informed decision-making, not rushed choices.',

				'Replacement services often involve more planning than standard repairs. In {city}, installation projects usually begin with an evaluation to determine the best solution.

This page outlines that process and what customers should expect along the way.',

				'Installing or replacing equipment is a long-term decision. This page exists to explain how replacement projects are approached in {city}, including evaluation, option selection, and installation timelines.

Understanding the process helps ensure expectations are aligned before work begins.',

				'Replacement and installation services are different from routine service calls. This page focuses on how those projects are handled in {city}, from initial assessment through final installation.

It\'s intended for customers considering a larger upgrade or replacement.',
			),
			
			'preventative_maintenance' => array(
				'Preventative maintenance is designed to reduce unexpected issues over time. In {city}, maintenance services are often scheduled to help keep systems running reliably and catch small issues early.

This page explains how maintenance visits typically work and what they\'re meant to accomplish.',

				'Regular maintenance can help extend the life of equipment and reduce service disruptions. This page exists to explain how preventative maintenance is handled in {city} and what\'s usually included during a visit.

It\'s focused on ongoing care rather than one-time fixes.',

				'Maintenance services are proactive by design. In {city}, maintenance visits are commonly used to check performance, make adjustments, and address wear before it becomes a larger problem.

This page outlines what to expect from a maintenance appointment.',

				'This page exists for customers in {city} who want consistent upkeep instead of reactive service calls. Preventative maintenance focuses on routine checks and performance verification.

Understanding what maintenance includes helps set clear expectations.',

				'Preventative maintenance is about planning ahead. This page explains how maintenance services are typically structured in {city} and how they support long-term system reliability.

It\'s intended for customers looking to reduce unexpected breakdowns.',
			),
			
			'compliance_safety' => array(
				'Compliance and safety services focus on meeting required standards and identifying potential risks. In {city}, these services are often used to verify conditions and document findings.

This page explains what compliance-focused inspections typically include and how results are used.',

				'Safety and compliance checks are often scheduled to confirm systems meet current requirements. This page exists to explain how compliance services are handled in {city} and what documentation is typically provided.

It\'s focused on verification rather than repairs.',

				'Compliance services are different from standard inspections. In {city}, they\'re commonly used to review conditions, identify concerns, and provide written findings.

This page outlines that process and what to expect.',

				'This page exists for customers who need confirmation that systems meet applicable safety or operational standards in {city}.

It focuses on inspection scope, documentation, and follow-up recommendations.',

				'Compliance and safety reviews help identify risks and document current conditions. This page explains how those services are typically performed in {city} and how findings are shared.

It\'s intended for planning and verification purposes.',
			),
		);
	}
	
	/**
	 * Get CTA text variants (intent-locked)
	 * 3-4 variants per intent group
	 */
	public static function get_cta_variants() {
		return array(
			'emergency_response' => array(
				'Get Help Now',
				'Call for Emergency Service',
				'Request Immediate Assistance',
			),
			'repair_service' => array(
				'Request Repair Service',
				'Book a Repair Visit',
				'Schedule Repair Service',
			),
			'inspection_diagnostic' => array(
				'Schedule an Inspection',
				'Book a Diagnostic Visit',
				'Request an Inspection',
			),
			'replacement_installation' => array(
				'Request an Estimate',
				'Get a Replacement Quote',
				'Schedule a Replacement Evaluation',
			),
			'preventative_maintenance' => array(
				'Schedule Maintenance',
				'Set Up Maintenance Service',
				'Book Preventative Maintenance',
			),
			'compliance_safety' => array(
				'Request a Compliance Check',
				'Schedule a Safety Inspection',
				'Book a Compliance Review',
			),
		);
	}
	
	/**
	 * Get Process section structure (intent-specific headings)
	 */
	public static function get_process_structures() {
		return array(
			'emergency_response' => array(
				'headings' => array(
					'Immediate Response',
					'Safety & Damage Control',
					'Stabilization',
					'Follow-Up Planning',
				),
				'focus' => 'dispatch timing, immediate mitigation, safety checks',
			),
			'repair_service' => array(
				'headings' => array(
					'Diagnose the Issue',
					'Review Repair Options',
					'Complete the Repair',
					'Verify Performance',
				),
				'focus' => 'diagnosis, repair options, repair execution, verification',
			),
			'inspection_diagnostic' => array(
				'headings' => array(
					'What We Inspect',
					'What You Receive',
					'Understanding the Results',
					'Recommended Next Steps',
				),
				'focus' => 'inspection scope, deliverables (report/findings), next-step recommendations',
			),
			'replacement_installation' => array(
				'headings' => array(
					'Evaluate Needs',
					'Review Options',
					'Provide Estimate',
					'Installation Timeline',
				),
				'focus' => 'assessment, options, estimate, install timeline',
			),
			'preventative_maintenance' => array(
				'headings' => array(
					'Routine Checks',
					'Adjustments & Tune-Ups',
					'Performance Validation',
					'Ongoing Scheduling',
				),
				'focus' => 'checklist, tune-up, performance validation, seasonal scheduling',
			),
			'compliance_safety' => array(
				'headings' => array(
					'Compliance Review',
					'Safety Findings',
					'Documentation',
					'Recommended Actions',
				),
				'focus' => 'code/safety focus, documentation, remediation recommendations',
			),
		);
	}
	
	/**
	 * Get deterministic variant index based on stable hash
	 * 
	 * @param string $service_slug
	 * @param string $city_slug
	 * @param string $intent_group
	 * @param string $variant_type 'why'|'cta'
	 * @return int Variant index
	 */
	public static function get_variant_index( $service_slug, $city_slug, $intent_group, $variant_type ) {
		// Create stable hash input
		$hash_input = $service_slug . '|' . $city_slug . '|' . $intent_group . '|' . $variant_type . '|' . self::TEMPLATE_VERSION;
		
		// Generate stable hash
		$hash = crc32( $hash_input );
		if ( $hash < 0 ) {
			$hash = abs( $hash );
		}
		
		// Get variant count
		if ( 'why' === $variant_type ) {
			$variants = self::get_why_variants();
			$count = isset( $variants[ $intent_group ] ) ? count( $variants[ $intent_group ] ) : 5;
		} elseif ( 'cta' === $variant_type ) {
			$variants = self::get_cta_variants();
			$count = isset( $variants[ $intent_group ] ) ? count( $variants[ $intent_group ] ) : 3;
		} else {
			$count = 1;
		}
		
		return $hash % $count;
	}
	
	/**
	 * Get "Why This Page Exists" content for a specific page
	 * 
	 * @param string $service_slug
	 * @param string $city_name
	 * @param string $city_slug
	 * @param string $intent_group
	 * @return string Formatted content with city name inserted
	 */
	public static function get_why_content( $service_slug, $city_name, $city_slug, $intent_group ) {
		$variants = self::get_why_variants();
		
		// Default to repair_service if intent not found
		if ( ! isset( $variants[ $intent_group ] ) ) {
			$intent_group = 'repair_service';
		}
		
		$variant_index = self::get_variant_index( $service_slug, $city_slug, $intent_group, 'why' );
		$template = $variants[ $intent_group ][ $variant_index ];
		
		// Replace {city} placeholder
		return str_replace( '{city}', $city_name, $template );
	}
	
	/**
	 * Get CTA text for a specific page
	 * 
	 * @param string $service_slug
	 * @param string $city_slug
	 * @param string $intent_group
	 * @return string CTA text
	 */
	public static function get_cta_text( $service_slug, $city_slug, $intent_group ) {
		$variants = self::get_cta_variants();
		
		// Default to repair_service if intent not found
		if ( ! isset( $variants[ $intent_group ] ) ) {
			$intent_group = 'repair_service';
		}
		
		$variant_index = self::get_variant_index( $service_slug, $city_slug, $intent_group, 'cta' );
		return $variants[ $intent_group ][ $variant_index ];
	}
	
	/**
	 * Get Process section structure for a specific intent
	 * 
	 * @param string $intent_group
	 * @return array Process structure with headings and focus
	 */
	public static function get_process_structure( $intent_group ) {
		$structures = self::get_process_structures();
		
		// Default to repair_service if intent not found
		if ( ! isset( $structures[ $intent_group ] ) ) {
			$intent_group = 'repair_service';
		}
		
		return $structures[ $intent_group ];
	}
	
	/**
	 * Validate intent group value
	 * 
	 * @param string $intent_group
	 * @return bool
	 */
	public static function is_valid_intent( $intent_group ) {
		return in_array( $intent_group, self::INTENT_GROUPS, true );
	}
	
	/**
	 * Get default intent group
	 * 
	 * @return string
	 */
	public static function get_default_intent() {
		return 'repair_service';
	}
}
