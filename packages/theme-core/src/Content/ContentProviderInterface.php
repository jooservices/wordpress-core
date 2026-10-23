<?php
/**
 * Content data contract.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content;

use JOOservices\ThemeCore\Content\Model\CategoryNode;
use JOOservices\ThemeCore\Content\Model\Company;
use JOOservices\ThemeCore\Content\Model\CompanyPage;
use JOOservices\ThemeCore\Content\Model\PostCard;
use JOOservices\ThemeCore\Content\Model\Profile;
use JOOservices\ThemeCore\Content\Model\Topic;

/**
 * Stable data API for blocks, structured data and the terminal. Only published, public content is returned.
 *
 * @internal
 */
interface ContentProviderInterface {

	/**
	 * Owner profile (null when there is no owner).
	 */
	public function profile(): ?Profile;

	/**
	 * The pinned "current" company.
	 */
	public function pinnedCompany(): ?Company;

	/**
	 * Companies, newest first.
	 *
	 * @param int  $page          Page, from 1.
	 * @param int  $perPage       Items per page, 1..20.
	 * @param bool $excludePinned Leave the pinned company out.
	 */
	public function companies( int $page, int $perPage, bool $excludePinned = true ): CompanyPage;

	/**
	 * Category tree with cumulative counts.
	 *
	 * @param bool $hideEmpty Hide categories without published posts.
	 * @return list<CategoryNode>
	 */
	public function categoryTree( bool $hideEmpty = true ): array;

	/**
	 * Topics (tags).
	 *
	 * @param int    $limit    Maximum topics.
	 * @param string $strategy `count` or `manual`.
	 * @return list<Topic>
	 */
	public function topics( int $limit, string $strategy ): array;

	/**
	 * Featured posts in the owner's order.
	 *
	 * @param int $limit Maximum posts (capped at 12).
	 * @return list<PostCard>
	 */
	public function featured( int $limit ): array;

	/**
	 * Related posts of a post.
	 *
	 * @param int $postId Post id.
	 * @param int $limit  Maximum posts (capped at 8).
	 * @return list<PostCard>
	 */
	public function related( int $postId, int $limit ): array;
}
