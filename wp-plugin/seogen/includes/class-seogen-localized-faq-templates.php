<?php
/**
 * Localized FAQ Templates
 * 
 * Provides one localized FAQ item per service+city page to introduce
 * light local nuance and support long-tail queries without FAQ spam.
 * 
 * Each intent_group has 2-3 question variants and 2-3 answer variants
 * for deterministic selection.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Localized_FAQ_Templates {
	
	const TEMPLATE_VERSION = '1.0';
	
	/**
	 * Question templates by intent_group
	 * {city} placeholder will be replaced with actual city name
	 */
	const QUESTIONS = array(
		'emergency_response' => array(
			'How are emergency service calls handled in {city}?',
			'What should I do while waiting for emergency service in {city}?',
			'How quickly can emergency service typically arrive in {city}?',
		),
		
		'repair_service' => array(
			'How soon can repairs usually be scheduled in {city}?',
			'What happens during a repair visit in {city}?',
			'How are repair appointments typically coordinated in {city}?',
		),
		
		'inspection_diagnostic' => array(
			'How are inspections typically scheduled in {city}?',
			'What should I expect from an inspection visit in {city}?',
			'How long do diagnostic inspections usually take in {city}?',
		),
		
		'replacement_installation' => array(
			'What does the replacement process usually look like in {city}?',
			'How long do installation projects typically take in {city}?',
			'How are installation appointments scheduled in {city}?',
		),
		
		'preventative_maintenance' => array(
			'How often is maintenance typically scheduled in {city}?',
			'What\'s included in a maintenance visit in {city}?',
			'How are maintenance appointments coordinated in {city}?',
		),
		
		'compliance_safety' => array(
			'What documentation is usually provided after a compliance check in {city}?',
			'How are compliance inspections handled in {city}?',
			'What should I expect during a safety inspection in {city}?',
		),
	);
	
	/**
	 * Answer templates by intent_group
	 * {city} placeholder will be replaced with actual city name
	 */
	const ANSWERS = array(
		'emergency_response' => array(
			'Emergency service response times in {city} depend on current demand and your location. Most providers aim to respond as quickly as possible, though exact timing varies. While waiting, it\'s often helpful to take any immediate safety precautions and have relevant information ready for the service provider.',
			
			'Emergency calls in {city} are typically prioritized based on severity and safety concerns. Response timing depends on factors like current service load and travel distance. Providers usually contact you to confirm details and provide an estimated arrival window when possible.',
			
			'Emergency service in {city} generally involves an initial assessment call followed by dispatch of available technicians. Actual response time varies based on demand and location. Most providers will communicate expected timing and any immediate steps you can take while waiting.',
		),
		
		'repair_service' => array(
			'Repair scheduling in {city} typically depends on the nature of the issue and current service availability. Many providers can arrange appointments within a few days for non-emergency repairs, though timing varies. The repair visit usually includes assessment, explanation of the work needed, and completion of the repair if parts and time allow.',
			
			'During a repair visit in {city}, the technician typically assesses the issue, explains findings, and performs the necessary work. The process often includes testing to verify the repair was successful. Timing depends on the complexity of the repair and whether any parts need to be ordered.',
			
			'Repair appointments in {city} are usually scheduled based on availability and the urgency of the issue. Most providers offer appointment windows rather than exact times. The visit typically includes diagnosis, repair work, and verification that the issue is resolved.',
		),
		
		'inspection_diagnostic' => array(
			'Inspection scheduling in {city} is typically flexible since these visits are often planned rather than urgent. Most providers can arrange appointments within a week or two depending on their schedule. The inspection process usually involves a thorough examination and a detailed report of findings.',
			
			'An inspection visit in {city} generally includes a systematic examination of the relevant systems or components. The inspector typically documents findings, takes photos if needed, and provides a written report. The duration depends on the scope of the inspection and what\'s being examined.',
			
			'Diagnostic inspections in {city} typically take anywhere from 30 minutes to a few hours depending on what\'s being evaluated. The process usually includes testing, measurements, and documentation. Most providers schedule these during regular business hours when conditions are optimal for assessment.',
		),
		
		'replacement_installation' => array(
			'The replacement process in {city} typically begins with an assessment and estimate, followed by scheduling the installation. The actual work timeline depends on the scope of the project and material availability. Most installations are completed in one to several days, with providers coordinating timing to minimize disruption.',
			
			'Installation project duration in {city} varies based on the complexity of the work and any site-specific factors. Simple installations might be completed in a day, while larger projects could take several days. Providers typically schedule the work in phases if needed and communicate the expected timeline upfront.',
			
			'Installation appointments in {city} are usually scheduled once materials are confirmed available. The process often includes site preparation, removal of old equipment if applicable, and installation of the new system. Providers typically coordinate timing and access requirements in advance.',
		),
		
		'preventative_maintenance' => array(
			'Maintenance frequency in {city} depends on the type of system and manufacturer recommendations. Many services are scheduled seasonally or annually. Regular maintenance visits typically include inspection, cleaning, minor adjustments, and identification of any potential issues before they become problems.',
			
			'A maintenance visit in {city} usually includes inspection of key components, cleaning, lubrication if needed, and testing to ensure proper operation. The technician often provides recommendations for any future service needs. Most visits are completed in an hour or two depending on the system complexity.',
			
			'Maintenance appointments in {city} are typically scheduled in advance during regular business hours. Many providers offer seasonal reminders or service agreements. The visit usually includes a checklist of tasks specific to the system being serviced, with documentation of work completed.',
		),
		
		'compliance_safety' => array(
			'After a compliance check in {city}, you typically receive a written report documenting what was inspected and whether it meets current requirements. This documentation often includes photos, test results, and any recommendations. The format and detail level depend on the specific compliance standards being verified.',
			
			'Compliance inspections in {city} are usually scheduled in advance and follow specific protocols based on local or industry requirements. The inspector examines relevant systems, documents findings, and provides certification or identifies any items needing attention. The process is typically straightforward if systems are properly maintained.',
			
			'During a safety inspection in {city}, the inspector systematically checks components against established safety standards. You can expect questions about system history, visual examination, and possibly testing. The inspector typically explains findings and provides documentation of the inspection results.',
		),
	);
	
	/**
	 * Get localized FAQ item (question + answer)
	 * 
	 * @param string $intent_group Intent group identifier
	 * @param string $service_slug Service slug for deterministic selection
	 * @param string $city_name City name for placeholder replacement
	 * @param string $city_slug City slug for deterministic selection
	 * @return array Array with 'question' and 'answer' keys, or empty array if intent not found
	 */
	public static function get_localized_faq( $intent_group, $service_slug, $city_name, $city_slug ) {
		// Check if intent_group has templates
		if ( ! isset( self::QUESTIONS[ $intent_group ] ) || ! isset( self::ANSWERS[ $intent_group ] ) ) {
			return array();
		}
		
		$questions = self::QUESTIONS[ $intent_group ];
		$answers = self::ANSWERS[ $intent_group ];
		
		// Select question variant deterministically
		$hash_input_q = $service_slug . '|' . $city_slug . '|' . $intent_group . '|question|' . self::TEMPLATE_VERSION;
		$hash_q = crc32( $hash_input_q );
		$question_index = abs( $hash_q ) % count( $questions );
		
		// Select answer variant deterministically
		$hash_input_a = $service_slug . '|' . $city_slug . '|' . $intent_group . '|answer|' . self::TEMPLATE_VERSION;
		$hash_a = crc32( $hash_input_a );
		$answer_index = abs( $hash_a ) % count( $answers );
		
		// Replace {city} placeholder with actual city name
		$question = str_replace( '{city}', $city_name, $questions[ $question_index ] );
		$answer = str_replace( '{city}', $city_name, $answers[ $answer_index ] );
		
		return array(
			'question' => $question,
			'answer' => $answer,
		);
	}
}
