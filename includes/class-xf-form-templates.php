<?php
/**
 * Form template definitions.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return a single template by slug, or null if not found.
 *
 * @param string $slug Template slug.
 * @return array|null Template data or null.
 */
function xtremeforms_get_form_template( string $slug ): ?array {
	$templates = xtremeforms_get_all_templates();
	return $templates[ $slug ] ?? null;
}

/**
 * Return all built-in form templates.
 *
 * Each template provides:
 *   name     — human-readable name (translatable)
 *   fields   — array of field definitions matching the builder's field schema
 *   settings — partial settings array (merged over defaults in the builder)
 *
 * @return array<string, array>
 */
function xtremeforms_get_all_templates(): array {
	$uid = static function ( string $prefix ): string {
		return $prefix . '_' . substr( md5( uniqid( '', true ) ), 0, 8 );
	};

	return array(
		'blank'      => array(
			'name'     => __( 'Blank Form', 'xtreme-forms' ),
			'fields'   => array(),
			'settings' => array(
				'submit_label'     => __( 'Submit', 'xtreme-forms' ),
				'thank_you_message' => __( 'Thank you for your submission!', 'xtreme-forms' ),
			),
		),

		'contact'    => array(
			'name'     => __( 'Simple Contact Form', 'xtreme-forms' ),
			'fields'   => array(
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'text',
					'label'             => __( 'Name', 'xtreme-forms' ),
					'placeholder'       => __( 'Your name', 'xtreme-forms' ),
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'email',
					'label'             => __( 'Email', 'xtreme-forms' ),
					'placeholder'       => __( 'your@email.com', 'xtreme-forms' ),
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'textarea',
					'label'             => __( 'Message', 'xtreme-forms' ),
					'placeholder'       => __( 'How can we help?', 'xtreme-forms' ),
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
			),
			'settings' => array(
				'submit_label'     => __( 'Send Message', 'xtreme-forms' ),
				'thank_you_message' => __( "Thank you! We'll be in touch shortly.", 'xtreme-forms' ),
			),
		),

		'lead'       => array(
			'name'     => __( 'Lead Capture Form', 'xtreme-forms' ),
			'fields'   => array(
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'text',
					'label'             => __( 'Full Name', 'xtreme-forms' ),
					'placeholder'       => 'John Smith',
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'email',
					'label'             => __( 'Email', 'xtreme-forms' ),
					'placeholder'       => 'john@company.com',
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'phone',
					'label'             => __( 'Phone', 'xtreme-forms' ),
					'placeholder'       => '+1 (555) 000-0000',
					'required'          => false,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'text',
					'label'             => __( 'Company', 'xtreme-forms' ),
					'placeholder'       => __( 'Company name', 'xtreme-forms' ),
					'required'          => false,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
			),
			'settings' => array(
				'submit_label'     => __( 'Get Started', 'xtreme-forms' ),
				'thank_you_message' => __( 'Thank you! Our team will be in touch soon.', 'xtreme-forms' ),
			),
		),

		'newsletter' => array(
			'name'     => __( 'Newsletter Signup', 'xtreme-forms' ),
			'fields'   => array(
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'text',
					'label'             => __( 'First Name', 'xtreme-forms' ),
					'placeholder'       => __( 'Your name', 'xtreme-forms' ),
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'email',
					'label'             => __( 'Email Address', 'xtreme-forms' ),
					'placeholder'       => 'your@email.com',
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
			),
			'settings' => array(
				'submit_label'     => __( 'Subscribe', 'xtreme-forms' ),
				'thank_you_message' => __( "You're in! Check your inbox for a confirmation.", 'xtreme-forms' ),
			),
		),

		'quote'      => array(
			'name'     => __( 'Quote Request', 'xtreme-forms' ),
			'fields'   => array(
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'text',
					'label'             => __( 'Name', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'email',
					'label'             => __( 'Email', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'phone',
					'label'             => __( 'Phone', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => false,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'textarea',
					'label'             => __( 'Project Description', 'xtreme-forms' ),
					'placeholder'       => __( 'Tell us about your project…', 'xtreme-forms' ),
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'dropdown',
					'label'             => __( 'Budget Range', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => false,
					'options'           => array( '< $1,000', '$1,000 – $5,000', '$5,000 – $25,000', '$25,000+' ),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
			),
			'settings' => array(
				'submit_label'     => __( 'Request Quote', 'xtreme-forms' ),
				'thank_you_message' => __( "Thank you! We'll send your quote within 24 hours.", 'xtreme-forms' ),
			),
		),

		'event'      => array(
			'name'     => __( 'Event Registration', 'xtreme-forms' ),
			'fields'   => array(
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'text',
					'label'             => __( 'Full Name', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'email',
					'label'             => __( 'Email', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => true,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'phone',
					'label'             => __( 'Phone', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => false,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'text',
					'label'             => __( 'Company / Organization', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => false,
					'options'           => array(),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
				array(
					'id'                => $uid( 'f' ),
					'type'              => 'dropdown',
					'label'             => __( 'Number of Attendees', 'xtreme-forms' ),
					'placeholder'       => '',
					'required'          => true,
					'options'           => array( '1', '2', '3', '4', '5+' ),
					'default_value'     => '',
					'conditional_logic' => array( 'enabled' => false, 'logic' => 'and', 'conditions' => array() ),
				),
			),
			'settings' => array(
				'submit_label'     => __( 'Register Now', 'xtreme-forms' ),
				'thank_you_message' => __( "You're registered! Check your email for details.", 'xtreme-forms' ),
			),
		),
	);
}
