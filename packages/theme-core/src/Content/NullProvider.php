<?php
/**
 * Provider used when JOOservices Resume is not active.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content;

use JOOservices\ThemeCore\Content\Model\Company;
use JOOservices\ThemeCore\Content\Model\CompanyPage;
use JOOservices\ThemeCore\Content\Model\Profile;

/**
 * Empty answers everywhere, so blocks stay empty instead of failing.
 *
 * @api
 */
final class NullProvider implements ContentProviderInterface {

	/**
	 * No profile.
	 */
	public function profile(): ?Profile {
		return null;
	}

	/**
	 * No pinned company.
	 */
	public function pinnedCompany(): ?Company {
		return null;
	}

	/**
	 * No companies.
	 *
	 * @param int  $page          Page.
	 * @param int  $perPage       Per page.
	 * @param bool $excludePinned Exclude pinned.
	 */
	public function companies( int $page, int $perPage, bool $excludePinned = true ): CompanyPage {
		unset( $page, $perPage, $excludePinned );
		return new CompanyPage( array(), 0, 0 );
	}

	/**
	 * No categories.
	 *
	 * @param bool $hideEmpty Hide empty.
	 * @return list<\JOOservices\ThemeCore\Content\Model\CategoryNode>
	 */
	public function categoryTree( bool $hideEmpty = true ): array {
		unset( $hideEmpty );
		return array();
	}

	/**
	 * No topics.
	 *
	 * @param int    $limit    Limit.
	 * @param string $strategy Strategy.
	 * @return list<\JOOservices\ThemeCore\Content\Model\Topic>
	 */
	public function topics( int $limit, string $strategy ): array {
		unset( $limit, $strategy );
		return array();
	}

	/**
	 * No featured posts.
	 *
	 * @param int $limit Limit.
	 * @return list<\JOOservices\ThemeCore\Content\Model\PostCard>
	 */
	public function featured( int $limit ): array {
		unset( $limit );
		return array();
	}

	/**
	 * No related posts.
	 *
	 * @param int $postId Post id.
	 * @param int $limit  Limit.
	 * @return list<\JOOservices\ThemeCore\Content\Model\PostCard>
	 */
	public function related( int $postId, int $limit ): array {
		unset( $postId, $limit );
		return array();
	}
}
