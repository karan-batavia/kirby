<?php

namespace Kirby\Cms;

use Kirby\Cms\NewPage as Page;
use Kirby\Content\Content;
use Kirby\Content\MemoryStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use TypeError;

#[CoversClass(Page::class)]
class NewPageContentTest extends NewPageTestCase
{
	public const TMP = KIRBY_TMP_DIR . '/Cms.NewPageContentTest';

	public function testContent()
	{
		$page = new Page(['slug' => 'test']);
		$this->assertInstanceOf(Content::class, $page->content());
		$this->assertSame([], $page->content()->toArray());
	}

	public function testSetContentWithInvalidValue()
	{
		$this->expectException(TypeError::class);

		new Page([
			'slug'    => 'test',
			'content' => 'content'
		]);
	}

	public function testSetContentInSingleLanguageMode(): void
	{
		$page = new Page([
			'slug' => 'test',
			'content' => $content = [
				'title'    => 'Title 1',
				'subtitle' => 'Subtitle 1'
			]
		]);

		$this->assertInstanceOf(MemoryStorage::class, $page->storage());

		$this->assertSame($content, $page->content()->toArray());

		$this->assertFileDoesNotExist(static::TMP . '/content/test/default.txt');
	}

	public function testSetContentInMultiLanguageMode(): void
	{
		$this->setUpMultiLanguage();

		$page = new Page([
			'slug' => 'test',
			'content' => $content = [
				'title'    => 'Title 1',
				'subtitle' => 'Subtitle 1'
			]
		]);

		$this->assertInstanceOf(MemoryStorage::class, $page->storage());

		$this->assertSame($content, $page->content()->toArray());
		$this->assertSame($content, $page->content('en')->toArray());
		$this->assertSame($content, $page->content('de')->toArray());

		$this->assertFileDoesNotExist(static::TMP . '/content/test/default.en.txt');
	}
}
