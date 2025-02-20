<?php

namespace Kirby\Cms;

use Kirby\Cms\NewFile as File;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(File::class)]
class NewFileHtmlTest extends NewModelTestCase
{
	public const TMP = KIRBY_TMP_DIR . '/Cms.NewFileHtml';

	public function testHtml(): void
	{
		$file = new File([
			'filename' => 'test.jpg',
			'url'      => 'http://getkirby.com/test.jpg',
			'parent'   => $this->app->site(),
			'content' => [
				'alt' => 'This is the alt text'
			]
		]);

		$this->assertSame(
			'<img alt="This is the alt text" src="http://getkirby.com/test.jpg">',
			$file->html()
		);
	}
}
