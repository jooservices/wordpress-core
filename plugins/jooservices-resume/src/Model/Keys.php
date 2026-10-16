<?php
/**
 * Meta keys of the extended company data model.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Model;

/**
 * Every key is spelled out once so the naming registry can be checked against the source.
 */
final class Keys {

	public const META = array(
		'role'          => '_joo_company_role',
		'from'          => '_joo_company_from',
		'to'            => '_joo_company_to',
		'url'           => '_joo_company_url',
		'logo_id'       => '_joo_company_logo_id',
		'city'          => '_joo_company_city',
		'country'       => '_joo_company_country',
		'industry'      => '_joo_company_industry',
		'summary'       => '_joo_company_summary',
		'employment'    => '_joo_company_employment',
		'remote'        => '_joo_company_remote',
		'description'   => '_joo_company_description',
		'roles'         => '_joo_company_roles',
		'achievements'  => '_joo_company_achievements',
		'technologies'  => '_joo_company_technologies',
		'custom'        => '_joo_company_custom',
		'compensation'  => '_joo_company_compensation',
		'equity'        => '_joo_company_equity',
		'public_fields' => '_joo_company_public_fields',
	);
}
