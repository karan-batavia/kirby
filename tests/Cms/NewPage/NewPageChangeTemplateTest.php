<?php

namespace Kirby\Cms;

use Kirby\Cms\NewPage as Page;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Page::class)]
class NewPageChangeTemplateTest extends NewPageTestCase
{
	public const TMP = KIRBY_TMP_DIR . '/Cms.NewPageChangeTemplateTest';

	public function testChangeTemplateInMultiLanguageMode()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'blueprints' => [
				'pages/video' => [
					'title'  => 'Video',
					'options' => [
						'template' => [
							'article'
						]
					],
					'fields' => [
						'caption' => [
							'type' => 'text'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				],
				'pages/article' => [
					'title'  => 'Article',
					'fields' => [
						'caption' => [
							'type' => 'radio'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				]
			],
			'hooks' => [
				'page.changeTemplate:before' => function (Page $page, $template) use ($phpunit, &$calls) {
					$phpunit->assertSame('video', $page->intendedTemplate()->name());
					$phpunit->assertSame('article', $template);
					$calls++;
				},
				'page.changeTemplate:after' => function (Page $newPage, Page $oldPage) use ($phpunit, &$calls) {
					$phpunit->assertSame('article', $newPage->intendedTemplate()->name());
					$phpunit->assertSame('video', $oldPage->intendedTemplate()->name());
					$calls++;
				}
			],
			'languages' => [
				[
					'code' => 'en',
					'name' => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch',
				],
				[
					'code' => 'fr',
					'name' => 'Français',
				]
			],
			'site' => [
				'children' => [
					[
						'slug'     => 'test',
						'template' => 'video',
						'translations' => [
							[
								'code' => 'en',
								'content' => [
									'title'   => 'Test',
									'caption' => 'Caption',
									'text'    => 'Text'
								]
							],
							[
								'code' => 'de',
								'content' => [
									'title'   => 'Prüfen',
									'caption' => 'Untertitel',
									'text'    => 'Täxt'
								]
							],
						]
					]
				]
			],
		]);

		$app->impersonate('kirby');
		$page = $app->page('test');

		$this->assertSame('video', $page->intendedTemplate()->name());
		$this->assertSame('Caption', $page->caption()->value());
		$this->assertSame('Text', $page->text()->value());
		$this->assertSame('Untertitel', $page->content('de')->get('caption')->value());
		$this->assertSame('Täxt', $page->content('de')->get('text')->value());

		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$modified = $page->changeTemplate('article');

		$this->assertSame('article', $modified->intendedTemplate()->name());
		$this->assertSame(2, $calls);

		$this->assertFileExists($modified->version(VersionId::latest())->contentFile('en'));
		$this->assertFileExists($modified->version(VersionId::latest())->contentFile('de'));
		$this->assertFileDoesNotExist($modified->version(VersionId::latest())->contentFile('fr'));
		$this->assertNull($modified->caption()->value());
		$this->assertSame('Text', $modified->text()->value());
		$this->assertNull($modified->content('de')->get('caption')->value());
		$this->assertSame('Täxt', $modified->content('de')->get('text')->value());
	}

	public function testChangeTemplateInSingleLanguageMode()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'blueprints' => [
				'pages/video' => [
					'title'  => 'Video',
					'options' => [
						'template' => [
							'article'
						]
					],
					'fields' => [
						'caption' => [
							'type' => 'text'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				],
				'pages/article' => [
					'title'  => 'Article',
					'fields' => [
						'caption' => [
							'type' => 'info'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				]
			],
			'hooks' => [
				'page.changeTemplate:before' => function (Page $page, $template) use ($phpunit, &$calls) {
					$phpunit->assertSame('video', $page->intendedTemplate()->name());
					$phpunit->assertSame('article', $template);
					$calls++;
				},
				'page.changeTemplate:after' => function (Page $newPage, Page $oldPage) use ($phpunit, &$calls) {
					$phpunit->assertSame('article', $newPage->intendedTemplate()->name());
					$phpunit->assertSame('video', $oldPage->intendedTemplate()->name());
					$calls++;
				}
			]
		]);

		$app->impersonate('kirby');

		$page = $app->site()->createChild([
			'slug'     => 'test',
			'template' => 'video',
			'content'  => [
				'title'   => 'Test',
				'caption' => 'Caption',
				'text'    => 'Text'
			]
		]);

		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$this->assertSame('video', $page->intendedTemplate()->name());
		$this->assertSame('Caption', $page->caption()->value());
		$this->assertSame('Text', $page->text()->value());
		$this->assertFileExists($page->root() . '/video.txt');
		$this->assertFileDoesNotExist($page->root() . '/article.txt');

		$modified = $page->changeTemplate('article');

		$this->assertSame('article', $modified->intendedTemplate()->name());
		$this->assertNull($modified->caption()->value());
		$this->assertSame('Text', $modified->text()->value());
		$this->assertSame(2, $calls);
		$this->assertFileExists($modified->root() . '/article.txt');
		$this->assertFileDoesNotExist($modified->root() . '/video.txt');

		$this->assertSame($modified, $drafts->find('test'));
		$this->assertSame($modified, $childrenAndDrafts->find('test'));
	}
}
