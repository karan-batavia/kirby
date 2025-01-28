<?php

use Kirby\Cms\App;
use Kirby\Panel\Ui\Buttons\ViewButtons;
use Kirby\Toolkit\I18n;

return [
	'system' => [
		'pattern' => 'system',
		'action'  => function () {
			$kirby        = App::instance();
			$system       = $kirby->system();
			$updateStatus = $system->updateStatus();
			$license      = $system->license();

			$environment = [
				[
					'label'  => $license->status()->label(),
					'value'  => $license->label(),
					'theme'  => $license->status()->theme(),
					'icon'   => $license->status()->icon(),
					'dialog' => $license->status()->dialog()
				],
				[
					'label' => $updateStatus?->label() ?? I18n::translate('version'),
					'value' => $kirby->version(),
					'link'  => $updateStatus?->url() ??
						'https://github.com/getkirby/kirby/releases/tag/' . $kirby->version(),
					'theme' => $updateStatus?->theme(),
					'icon'  => $updateStatus?->icon() ?? 'info'
				],
				[
					'label' => 'PHP',
					'value' => phpversion(),
					'icon'  => 'code'
				],
				[
					'label' => I18n::translate('server'),
					'value' => $system->serverSoftwareShort() ?? '?',
					'icon'  => 'server'
				]
			];

			$exceptions = $updateStatus?->exceptionMessages() ?? [];

			$plugins = $system->plugins()->values(function ($plugin) use (&$exceptions) {
				$authors      = $plugin->authorsNames();
				$updateStatus = $plugin->updateStatus();
				$version      = $updateStatus?->toArray();
				$version    ??= $plugin->version() ?? '–';

				if ($updateStatus !== null) {
					$exceptions = [
						...$exceptions,
						...$updateStatus->exceptionMessages()
					];
				}

				return [
					'author'  => empty($authors) ? '–' : $authors,
					'license' => $plugin->license()->toArray(),
					'name'    => [
						'text' => $plugin->name() ?? '–',
						'href' => $plugin->link(),
					],
					'status'  => $plugin->license()->status()->toArray(),
					'version' => $version,
				];
			});

			$security = $updateStatus?->messages() ?? [];

			if ($kirby->option('debug', false) === true) {
				$security[] = [
					'id'   => 'debug',
					'text' => I18n::translate('system.issues.debug'),
					'link' => 'https://getkirby.com/security/debug'
				];
			}

			if ($kirby->environment()->https() !== true) {
				$security[] = [
					'id'   => 'https',
					'text' => I18n::translate('system.issues.https'),
					'link' => 'https://getkirby.com/security/https'
				];
			}

			if ($kirby->option('panel.vue.compiler', true) === true) {
				$security[] = [
					'id'   => 'compiler',
					'text' => 'The Vue compiler is enabled',
					'link' => 'https://getkirby.com/security/vue-compiler'
				];
			}

			return [
				'component' => 'k-system-view',
				'props'     => [
					'buttons'     => fn () =>
						ViewButtons::view('system')->render(),
					'environment' => $environment,
					'exceptions'  => $kirby->option('debug') === true ? $exceptions : [],
					'info'        => $system->info(),
					'plugins'     => $plugins,
					'security'    => $security,
					'urls'        => [
						'content' => $system->exposedFileUrl('content'),
						'git'     => $system->exposedFileUrl('git'),
						'kirby'   => $system->exposedFileUrl('kirby'),
						'site'    => $system->exposedFileUrl('site')
					]
				]
			];
		}
	],
];
