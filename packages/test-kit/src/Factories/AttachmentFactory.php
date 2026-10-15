<?php
/**
 * Creates real image attachments for integration tests.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Factories;

/**
 * Generates a gradient PNG with GD and imports it through the media library (so all sub-sizes exist).
 */
final class AttachmentFactory {

	/**
	 * Creates an image attachment.
	 *
	 * @param int    $width   Width in pixels.
	 * @param int    $height  Height in pixels.
	 * @param string $name    File name without extension.
	 * @param int    $post_id Parent post id.
	 */
	public static function image( int $width = 1600, int $height = 900, string $name = 'joo-test', int $post_id = 0 ): int {
		$file  = sys_get_temp_dir() . '/' . $name . '-' . uniqid() . '.png';
		$image = imagecreatetruecolor( $width, $height );
		for ( $y = 0; $y < $height; $y += 8 ) {
			$color = imagecolorallocate( $image, 20 + (int) ( 200 * $y / $height ), 60, 160 );
			imagefilledrectangle( $image, 0, $y, $width, $y + 8, (int) $color );
		}
		imagepng( $image, $file );
		$factory = new \WP_UnitTest_Factory();
		$id      = $factory->attachment->create_upload_object( $file, $post_id );
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.unlink_unlink
		update_post_meta( $id, '_wp_attachment_image_alt', 'Generated test image' );
		return (int) $id;
	}
}
