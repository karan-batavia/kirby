<?php

namespace Kirby\Image\Darkroom;

use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\TestCase;

/**
 * @coversDefaultClass \Kirby\Image\Darkroom\Imagick
 */
class ImagickTest extends TestCase
{
	public const FIXTURES = __DIR__ . '/../fixtures/image';
	public const TMP      = KIRBY_TMP_DIR . '/Image.Darkroom.Imagick';

	public function setUp(): void
	{
		Dir::make(static::TMP);
	}

	public function tearDown(): void
	{
		Dir::remove(static::TMP);
	}

	public function testProcess()
	{
		$im = new Imagick();

		copy(static::FIXTURES . '/cat.jpg', $file = static::TMP . '/cat.jpg');

		$this->assertSame([
			'blur' => false,
			'crop' => false,
			'format' => null,
			'grayscale' => false,
			'height' => 500,
			'quality' => 90,
			'scaleHeight' => 1.0,
			'scaleWidth' => 1.0,
			'sharpen' => null,
			'width' => 500,
			'interlace' => false,
			'threads' => 1,
			'sourceWidth' => 500,
			'sourceHeight' => 500
		], $im->process($file));
	}

	/**
	 * @covers ::save
	 */
	public function testSaveWithFormat()
	{
		$im = new Imagick(['format' => 'webp']);

		copy(static::FIXTURES . '/cat.jpg', $file = static::TMP . '/cat.jpg');
		$this->assertFalse(F::exists($webp = static::TMP . '/cat.webp'));
		$im->process($file);
		$this->assertTrue(F::exists($webp));
	}

	/**
	 * @dataProvider keepColorProfileStripMetaProvider
	 */
	public function testKeepColorProfileStripMeta(string $basename, bool $crop)
	{
		$im = new Imagick([
			'crop'  => $crop,
			'width' => 250, // do some arbitrary transformation
		]);

		copy(static::FIXTURES . '/' . $basename, $file = static::TMP . '/' . $basename);

		// test if profile has been kept
		// errors have to be redirected to /dev/null, otherwise they would be printed to stdout by Imagick
		$originalProfile = shell_exec('identify -format "%[profile:icc]" ' . escapeshellarg($file) . ' 2>/dev/null');
		$im->process($file);
		$profile = shell_exec('identify -format "%[profile:icc]" ' . escapeshellarg($file) . ' 2>/dev/null');
		$this->assertSame($originalProfile, $profile);

		// ensure that other metadata has been stripped
		$meta = shell_exec('identify -verbose ' . escapeshellarg($file));
		$this->assertStringNotContainsString('photoshop:CaptionWriter', $meta);
		$this->assertStringNotContainsString('GPS', $meta);
	}

	public static function keepColorProfileStripMetaProvider(): array
	{
		return [
			['cat.jpg', false],
			['cat.jpg', true],
			['onigiri-adobe-rgb-gps.jpg', false],
			['onigiri-adobe-rgb-gps.jpg', true],
			['onigiri-adobe-rgb-gps.webp', false],
			['onigiri-adobe-rgb-gps.webp', true],
			['png-adobe-rgb-gps.png', false],
			['png-adobe-rgb-gps.png', true],
			['png-srgb-gps.png', false],
			['png-srgb-gps.png', true],
		];
	}
}
