<?php

namespace Kirby\Panel\Ui\FilePreviews;

use Kirby\Cms\File;
use Kirby\Toolkit\I18n;

/**
 * @package   Kirby Panel
 * @author    Nico Hoffmann <nico@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 * @since     5.0.0
 * @internal
 */
class FileImagePreview extends FileDefaultPreview
{
	public string $component = 'k-file-image-preview';

	public static function accepts(File $file): bool
	{
		return $file->type() === 'image';
	}

	public function details(): array
	{
		return [
			...parent::details(),
			[
				'title' => I18n::translate('dimensions'),
				'text'  => $this->file->dimensions() . ' ' . I18n::translate('pixel')
			],
			[
				'title' => I18n::translate('orientation'),
				'text'  => I18n::translate('orientation.' . $this->file->dimensions()->orientation())
			]
		];
	}

	public function props(): array
	{
		return [
			...parent::props(),
			'focusable' => $this->file->panel()->isFocusable()
		];
	}
}
