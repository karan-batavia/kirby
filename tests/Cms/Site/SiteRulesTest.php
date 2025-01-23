<?php

namespace Kirby\Cms;

use Kirby\Exception\PermissionException;

class SiteRulesTest extends TestCase
{
	public function testChangeTitleWithoutPermissions()
	{
		$permissions = $this->createMock(SitePermissions::class);
		$permissions->method('can')->with('changeTitle')->willReturn(false);

		$site = $this->createMock(Site::class);
		$site->method('permissions')->willReturn($permissions);

		$this->expectException(PermissionException::class);
		$this->expectExceptionMessage('You are not allowed to change the title');

		SiteRules::changeTitle($site, 'test');
	}

	public function testUpdate()
	{
		$app = new App();
		$app->impersonate('kirby');

		$this->expectNotToPerformAssertions();

		$site = new Site([]);
		SiteRules::update($site, ['copyright' => '2018']);
	}

	public function testUpdateWithoutPermissions()
	{
		$permissions = $this->createMock(SitePermissions::class);
		$permissions->method('can')->with('update')->willReturn(false);

		$site = $this->createMock(Site::class);
		$site->method('permissions')->willReturn($permissions);

		$this->expectException(PermissionException::class);
		$this->expectExceptionMessage('You are not allowed to update the site');

		SiteRules::update($site, []);
	}
}
