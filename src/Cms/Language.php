<?php

namespace Kirby\Cms;

use Kirby\Content\VersionIdentifier;
use Kirby\Data\Data;
use Kirby\Exception\Exception;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\PermissionException;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Locale;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * The `$language` object represents
 * a single language in a multi-language
 * Kirby setup. You can, for example,
 * use the methods of this class to get
 * the name or locale of a language,
 * check for the default language,
 * get translation strings and many
 * more things.
 *
 * @package   Kirby Cms
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
class Language
{
	use HasSiblings;

	/**
	 * The parent Kirby instance
	 */
	public static App|null $kirby;

	protected string $code;
	protected bool $default;
	protected string $direction;
	protected array $locale;
	protected string $name;
	protected array $slugs;
	protected array $smartypants;
	protected array $translations;
	protected string|null $url;

	/**
	 * Creates a new language object
	 */
	public function __construct(array $props)
	{
		if (isset($props['code']) === false) {
			throw new InvalidArgumentException('The property "code" is required');
		}

		static::$kirby      = $props['kirby'] ?? null;
		$this->code         = trim($props['code']);
		$this->default      = $props['default'] ?? false;
		$this->direction    = ($props['direction'] ?? null) === 'rtl' ? 'rtl' : 'ltr';
		$this->name         = trim($props['name'] ?? $this->code);
		$this->slugs        = $props['slugs'] ?? [];
		$this->smartypants  = $props['smartypants'] ?? [];
		$this->translations = $props['translations'] ?? [];
		$this->url          = $props['url'] ?? null;

		if ($locale = $props['locale'] ?? null) {
			$this->locale = Locale::normalize($locale);
		} else {
			$this->locale = [LC_ALL => $this->code];
		}
	}

	/**
	 * Improved `var_dump` output
	 */
	public function __debugInfo(): array
	{
		return $this->toArray();
	}

	/**
	 * Returns the language code
	 * when the language is converted to a string
	 */
	public function __toString(): string
	{
		return $this->code();
	}

	/**
	 * Returns the base Url for the language
	 * without the path or other cruft
	 */
	public function baseUrl(): string
	{
		$kirbyUrl    = $this->kirby()->url();
		$languageUrl = $this->url();

		if (empty($this->url)) {
			return $kirbyUrl;
		}

		if (Str::startsWith($languageUrl, $kirbyUrl) === true) {
			return $kirbyUrl;
		}

		return Url::base($languageUrl) ?? $kirbyUrl;
	}

	/**
	 * Creates an instance with the same
	 * initial properties.
	 */
	public function clone(array $props = []): static
	{
		return new static(array_replace_recursive([
			'code'         => $this->code,
			'default'      => $this->default,
			'direction'    => $this->direction,
			'locale'       => $this->locale,
			'name'         => $this->name,
			'slugs'        => $this->slugs,
			'smartypants'  => $this->smartypants,
			'translations' => $this->translations,
			'url'          => $this->url,
		], $props));
	}

	/**
	 * Returns the language code/id.
	 * The language code is used in
	 * text file names as appendix.
	 */
	public function code(): string
	{
		return $this->code;
	}

	/**
	 * Internal converter to create or remove
	 * translation files.
	 */
	protected static function converter(string $from, string $to): bool
	{
		$kirby = App::instance();
		$site  = $kirby->site();

		$from = $site->storage()->language($from, true);
		$to   = $site->storage()->language($to, true);

		// convert site
		foreach ($site->files() as $file) {
			F::move(
				$file->storage()->contentFile(VersionIdentifier::changes(), $from),
				$file->storage()->contentFile(VersionIdentifier::changes(), $to)
			);
			F::move(
				$file->storage()->contentFile(VersionIdentifier::published(), $from),
				$file->storage()->contentFile(VersionIdentifier::published(), $to)
			);
		}

		F::move(
			$site->storage()->contentFile(VersionIdentifier::changes(), $from),
			$site->storage()->contentFile(VersionIdentifier::changes(), $to)
		);
		F::move(
			$site->storage()->contentFile(VersionIdentifier::published(), $from),
			$site->storage()->contentFile(VersionIdentifier::published(), $to)
		);

		// convert all pages
		foreach ($kirby->site()->index(true) as $page) {
			foreach ($page->files() as $file) {
				F::move(
					$file->storage()->contentFile(VersionIdentifier::changes(), $from),
					$file->storage()->contentFile(VersionIdentifier::changes(), $to)
				);
				F::move(
					$file->storage()->contentFile(VersionIdentifier::published(), $from),
					$file->storage()->contentFile(VersionIdentifier::published(), $to)
				);
			}

			F::move(
				$page->storage()->contentFile(VersionIdentifier::changes(), $from),
				$page->storage()->contentFile(VersionIdentifier::changes(), $to)
			);
			if ($page->isDraft() === false) {
				F::move(
					$page->storage()->contentFile(VersionIdentifier::published(), $from),
					$page->storage()->contentFile(VersionIdentifier::published(), $to)
				);
			}
		}

		// convert all users
		foreach ($kirby->users() as $user) {
			foreach ($user->files() as $file) {
				F::move(
					$file->storage()->contentFile(VersionIdentifier::changes(), $from),
					$file->storage()->contentFile(VersionIdentifier::changes(), $to)
				);
				F::move(
					$file->storage()->contentFile(VersionIdentifier::published(), $from),
					$file->storage()->contentFile(VersionIdentifier::published(), $to)
				);
			}

			F::move(
				$user->storage()->contentFile(VersionIdentifier::changes(), $from),
				$user->storage()->contentFile(VersionIdentifier::changes(), $to)
			);
			F::move(
				$user->storage()->contentFile(VersionIdentifier::published(), $from),
				$user->storage()->contentFile(VersionIdentifier::published(), $to)
			);
		}

		return true;
	}

	/**
	 * Creates a new language object
	 *
	 * @internal
	 */
	public static function create(array $props): static
	{
		$props['code'] = Str::slug($props['code'] ?? null);
		$kirby         = App::instance();
		$languages     = $kirby->languages();

		// make the first language the default language
		if ($languages->count() === 0) {
			$props['default'] = true;
		}

		$language = new static($props);

		// trigger before hook
		$kirby->trigger(
			'language.create:before',
			[
				'input'    => $props,
				'language' => $language
			]
		);

		// validate the new language
		LanguageRules::create($language);

		$language->save();

		if ($languages->count() === 0) {
			static::converter('', $language->code());
		}

		// update the main languages collection in the app instance
		$kirby->languages(false)->append($language->code(), $language);

		// trigger after hook
		$kirby->trigger(
			'language.create:after',
			[
				'input'    => $props,
				'language' => $language
			]
		);

		return $language;
	}

	/**
	 * Delete the current language and
	 * all its translation files
	 *
	 * @internal
	 * @throws \Kirby\Exception\Exception
	 */
	public function delete(): bool
	{
		$kirby = App::instance();
		$code  = $this->code();

		if ($this->isDeletable() === false) {
			throw new Exception('The language cannot be deleted');
		}

		// trigger before hook
		$kirby->trigger('language.delete:before', [
			'language' => $this
		]);

		if (F::remove($this->root()) !== true) {
			throw new Exception('The language could not be deleted');
		}

		if ($this->isLast() === true) {
			$this->converter($code, '');
		} else {
			$this->deleteContentFiles($code);
		}

		// get the original language collection and remove the current language
		$kirby->languages(false)->remove($code);

		// trigger after hook
		$kirby->trigger('language.delete:after', [
			'language' => $this
		]);

		return true;
	}

	/**
	 * When the language is deleted, all content files with
	 * the language code must be removed as well.
	 */
	protected function deleteContentFiles(mixed $code): bool
	{
		$kirby = App::instance();
		$site  = $kirby->site();

		$language = $site->storage()->language($code, true);

		$site->storage()->delete(VersionIdentifier::changes(), $language);
		$site->storage()->delete(VersionIdentifier::published(), $language);

		foreach ($kirby->site()->index(true) as $page) {
			foreach ($page->files() as $file) {
				$file->storage()->delete(VersionIdentifier::changes(), $language);
				$file->storage()->delete(VersionIdentifier::published(), $language);
			}

			$page->storage()->delete(VersionIdentifier::changes(), $language);
			if ($page->isDraft() === false) {
				$page->storage()->delete(VersionIdentifier::published(), $language);
			}
		}

		foreach ($kirby->users() as $user) {
			foreach ($user->files() as $file) {
				$file->storage()->delete(VersionIdentifier::changes(), $language);
				$file->storage()->delete(VersionIdentifier::published(), $language);
			}

			$user->storage()->delete(VersionIdentifier::changes(), $language);
			$user->storage()->delete(VersionIdentifier::published(), $language);
		}

		return true;
	}

	/**
	 * Reading direction of this language
	 */
	public function direction(): string
	{
		return $this->direction;
	}

	/**
	 * Check if the language file exists
	 */
	public function exists(): bool
	{
		return file_exists($this->root());
	}

	/**
	 * Checks if this is the default language
	 * for the site.
	 */
	public function isDefault(): bool
	{
		return $this->default;
	}

	/**
	 * Checks if the language can be deleted
	 */
	public function isDeletable(): bool
	{
		// the default language can only be deleted if it's the last
		if ($this->isDefault() === true && $this->isLast() === false) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if this is the last language
	 */
	public function isLast(): bool
	{
		return App::instance()->languages()->count() === 1;
	}

	/**
	 * The id is required for collections
	 * to work properly. The code is used as id
	 */
	public function id(): string
	{
		return $this->code;
	}

	/**
	 * Returns the parent Kirby instance
	 */
	public function kirby(): App
	{
		return static::$kirby ??= App::instance();
	}

	/**
	 * Loads the language rules for provided locale code
	 */
	public static function loadRules(string $code): array
	{
		$kirby = App::instance();
		$code  = Str::contains($code, '.') ? Str::before($code, '.') : $code;
		$file  = $kirby->root('i18n:rules') . '/' . $code . '.json';

		if (F::exists($file) === false) {
			$file = $kirby->root('i18n:rules') . '/' . Str::before($code, '_') . '.json';
		}

		try {
			return Data::read($file);
		} catch (\Exception) {
			return [];
		}
	}

	/**
	 * Returns the PHP locale setting array
	 *
	 * @param int $category If passed, returns the locale for the specified category (e.g. LC_ALL) as string
	 */
	public function locale(int $category = null): array|string|null
	{
		if ($category !== null) {
			return $this->locale[$category] ?? $this->locale[LC_ALL] ?? null;
		}

		return $this->locale;
	}

	/**
	 * Returns the human-readable name
	 * of the language
	 */
	public function name(): string
	{
		return $this->name;
	}

	/**
	 * Returns the URL path for the language
	 */
	public function path(): string
	{
		if ($this->url === null) {
			return $this->code;
		}

		return Url::path($this->url);
	}

	/**
	 * Returns the routing pattern for the language
	 */
	public function pattern(): string
	{
		$path = $this->path();

		if (empty($path) === true) {
			return '(:all)';
		}

		return $path . '/(:all?)';
	}

	/**
	 * Returns the absolute path to the language file
	 */
	public function root(): string
	{
		return App::instance()->root('languages') . '/' . $this->code() . '.php';
	}

	/**
	 * Returns the LanguageRouter instance
	 * which is used to handle language specific
	 * routes.
	 */
	public function router(): LanguageRouter
	{
		return new LanguageRouter($this);
	}

	/**
	 * Get slug rules for language
	 *
	 * @internal
	 */
	public function rules(): array
	{
		$code = $this->locale(LC_CTYPE);
		$data = static::loadRules($code);
		return array_merge($data, $this->slugs());
	}

	/**
	 * Saves the language settings in the languages folder
	 *
	 * @internal
	 * @return $this
	 */
	public function save(): static
	{
		try {
			$existingData = Data::read($this->root());
		} catch (Throwable) {
			$existingData = [];
		}

		$props = [
			'code'         => $this->code(),
			'default'      => $this->isDefault(),
			'direction'    => $this->direction(),
			'locale'       => Locale::export($this->locale()),
			'name'         => $this->name(),
			'translations' => $this->translations(),
			'url'          => $this->url,
		];

		$data = array_merge($existingData, $props);

		ksort($data);

		Data::write($this->root(), $data);

		return $this;
	}

	/**
	 * Private siblings collector
	 */
	protected function siblingsCollection(): Collection
	{
		return App::instance()->languages();
	}

	/**
	 * Returns the custom slug rules for this language
	 */
	public function slugs(): array
	{
		return $this->slugs;
	}

	/**
	 * Returns the custom SmartyPants options for this language
	 */
	public function smartypants(): array
	{
		return $this->smartypants;
	}

	/**
	 * Returns the most important
	 * properties as array
	 */
	public function toArray(): array
	{
		return [
			'code'      => $this->code(),
			'default'   => $this->isDefault(),
			'direction' => $this->direction(),
			'locale'    => $this->locale(),
			'name'      => $this->name(),
			'rules'     => $this->rules(),
			'url'       => $this->url()
		];
	}

	/**
	 * Returns the translation strings for this language
	 */
	public function translations(): array
	{
		return $this->translations;
	}

	/**
	 * Returns the absolute Url for the language
	 */
	public function url(): string
	{
		$url   = $this->url;
		$url ??= '/' . $this->code;
		return Url::makeAbsolute($url, $this->kirby()->url());
	}

	/**
	 * Update language properties and save them
	 *
	 * @internal
	 */
	public function update(array $props = null): static
	{
		// don't change the language code
		unset($props['code']);

		// make sure the slug is nice and clean
		$props['slug'] = Str::slug($props['slug'] ?? null);

		$kirby   = App::instance();
		$updated = $this->clone($props);

		if (isset($props['translations']) === true) {
			$updated->translations = $props['translations'];
		}

		// validate the updated language
		LanguageRules::update($updated);

		// trigger before hook
		$kirby->trigger('language.update:before', [
			'language' => $this,
			'input' => $props
		]);

		// convert the current default to a non-default language
		if ($updated->isDefault() === true) {
			$kirby->defaultLanguage()?->clone(['default' => false])->save();

			$site = $kirby->site();

			if ($site->storage()->exists(VersionIdentifier::changes(), $this) === true) {
				$site->storage()->touch(VersionIdentifier::changes(), $this);
			}
			if ($site->storage()->exists(VersionIdentifier::published(), $this) === true) {
				$site->storage()->touch(VersionIdentifier::published(), $this);
			}

			foreach ($kirby->site()->index(true) as $page) {
				$files = $page->files();

				foreach ($files as $file) {
					if ($file->storage()->exists(VersionIdentifier::changes(), $this) === true) {
						$file->storage()->touch(VersionIdentifier::changes(), $this);
					}
					if ($file->storage()->exists(VersionIdentifier::published(), $this) === true) {
						$file->storage()->touch(VersionIdentifier::published(), $this);
					}
				}

				if ($page->storage()->exists(VersionIdentifier::changes(), $this) === true) {
					$page->storage()->touch(VersionIdentifier::changes(), $this);
				}
				if (
					$page->isDraft() === false &&
					$page->storage()->exists(VersionIdentifier::published(), $this) === true
				) {
					$page->storage()->touch(VersionIdentifier::published(), $this);
				}
			}
		} elseif ($this->isDefault() === true) {
			throw new PermissionException('Please select another language to be the primary language');
		}

		$language = $updated->save();

		// make sure the language is also updated in the Kirby language collection
		App::instance()->languages(false)->set($language->code(), $language);

		// trigger after hook
		$kirby->trigger('language.update:after', [
			'newLanguage' => $language,
			'oldLanguage' => $this,
			'input' => $props
		]);

		return $language;
	}

	/**
	 * Returns a language variable object
	 * for the key in the translations array
	 */
	public function variable(string $key): LanguageVariable
	{
		return new LanguageVariable($this, $key);
	}
}
