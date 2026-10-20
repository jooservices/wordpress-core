# Core API usage review

This inventory records every symbol in the 68-entry `docs/api/baseline-0.1.0.md` at the start of JOOWT-253. Searches covered executable consumer files in `themes/starter`, `themes/jooservices`, and `plugins/jooservices-resume`, plus the four example manifests under `docs/spec/examples`. Test-only references were inspected but do not count as theme or plugin consumption. The baseline is regenerated from the final annotations after this inventory is written.

`AssetRegistry` is used by the `joo/assets/register` callback in the Jooservices theme, which calls its `addManifest()`, `style()`, and `module()` methods. `Container` is used through the concrete object returned by `Kernel::container()` in `BuyerOptions.php`.

| Symbol | Kind | Source | Used by | Final annotation |
|---|---|---|---|---|
| `JOOservices\ThemeCore\Assets\AssetManifest` | class | `packages/theme-core/src/Assets/AssetManifest.php` | `themes/jooservices/functions.php` | `@api` |
| `JOOservices\ThemeCore\Assets\AssetRegistry` | class | `packages/theme-core/src/Assets/AssetRegistry.php` | `themes/jooservices/functions.php` | `@api` |
| `JOOservices\ThemeCore\Assets\Preloader` | class | `packages/theme-core/src/Assets/Preloader.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\BlockRegistrar` | class | `packages/theme-core/src/Blocks/BlockRegistrar.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\CodeEnhancer` | class | `packages/theme-core/src/Blocks/CodeEnhancer.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\DynamicBlock` | class | `packages/theme-core/src/Blocks/DynamicBlock.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\EditorBundle` | class | `packages/theme-core/src/Blocks/EditorBundle.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\FragmentBlock` | class | `packages/theme-core/src/Blocks/FragmentBlock.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\HeadingAnchors` | class | `packages/theme-core/src/Blocks/HeadingAnchors.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\Html` | class | `packages/theme-core/src/Blocks/Html.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\PatternLoader` | class | `packages/theme-core/src/Blocks/PatternLoader.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\TemplateLocator` | class | `packages/theme-core/src/Blocks/TemplateLocator.php` | none | `@internal` |
| `JOOservices\ThemeCore\Blocks\ThemeScheme` | class | `packages/theme-core/src/Blocks/ThemeScheme.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\Acf` | class | `packages/theme-core/src/Compat/Acf.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\AdapterInterface` | interface | `packages/theme-core/src/Compat/AdapterInterface.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\AdapterRegistry` | class | `packages/theme-core/src/Compat/AdapterRegistry.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\Detector` | class | `packages/theme-core/src/Compat/Detector.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\Forms` | class | `packages/theme-core/src/Compat/Forms.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\Multilingual` | interface | `packages/theme-core/src/Compat/Multilingual.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\Multilingual\Lang` | class | `packages/theme-core/src/Compat/Multilingual/Lang.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\Multilingual\Resolver` | class | `packages/theme-core/src/Compat/Multilingual/Resolver.php` | none | `@internal` |
| `JOOservices\ThemeCore\Compat\PageBuilder` | class | `packages/theme-core/src/Compat/PageBuilder.php` | none | `@internal` |
| `JOOservices\ThemeCore\Config\Manifest` | class | `packages/theme-core/src/Config/Manifest.php` | none | `@internal` |
| `JOOservices\ThemeCore\Config\Settings` | class | `packages/theme-core/src/Config/Settings.php` | `themes/jooservices/inc/BuyerOptions.php` | `@api` |
| `JOOservices\ThemeCore\Container\Container` | class | `packages/theme-core/src/Container/Container.php` | `themes/jooservices/inc/BuyerOptions.php` | `@api` |
| `JOOservices\ThemeCore\Container\ContainerException` | class | `packages/theme-core/src/Container/ContainerException.php` | none | `@internal` |
| `JOOservices\ThemeCore\Container\ContainerInterface` | interface | `packages/theme-core/src/Container/ContainerInterface.php` | none | `@internal` |
| `JOOservices\ThemeCore\Container\NotFoundException` | class | `packages/theme-core/src/Container/NotFoundException.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\ContentProviderInterface` | interface | `packages/theme-core/src/Content/ContentProviderInterface.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\Model\CategoryNode` | class | `packages/theme-core/src/Content/Model/CategoryNode.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\Model\Company` | class | `packages/theme-core/src/Content/Model/Company.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\Model\CompanyPage` | class | `packages/theme-core/src/Content/Model/CompanyPage.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\Model\ImageRef` | class | `packages/theme-core/src/Content/Model/ImageRef.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\Model\PostCard` | class | `packages/theme-core/src/Content/Model/PostCard.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\Model\Profile` | class | `packages/theme-core/src/Content/Model/Profile.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\Model\Topic` | class | `packages/theme-core/src/Content/Model/Topic.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\NullProvider` | class | `packages/theme-core/src/Content/NullProvider.php` | none | `@internal` |
| `JOOservices\ThemeCore\Content\WpContentProvider` | class | `packages/theme-core/src/Content/WpContentProvider.php` | none | `@internal` |
| `JOOservices\ThemeCore\Http\CacheHeaders` | class | `packages/theme-core/src/Http/CacheHeaders.php` | none | `@internal` |
| `JOOservices\ThemeCore\I18n\JsStrings` | class | `packages/theme-core/src/I18n/JsStrings.php` | none | `@internal` |
| `JOOservices\ThemeCore\I18n\Language` | class | `packages/theme-core/src/I18n/Language.php` | none | `@internal` |
| `JOOservices\ThemeCore\I18n\LanguageTagging` | class | `packages/theme-core/src/I18n/LanguageTagging.php` | none | `@internal` |
| `JOOservices\ThemeCore\I18n\Languages` | class | `packages/theme-core/src/I18n/Languages.php` | none | `@internal` |
| `JOOservices\ThemeCore\I18n\Translations` | class | `packages/theme-core/src/I18n/Translations.php` | none | `@internal` |
| `JOOservices\ThemeCore\Kernel` | class | `packages/theme-core/src/Kernel.php` | `themes/starter/functions.php`, `themes/jooservices/functions.php`, `themes/jooservices/inc/BuyerOptions.php` | `@api` |
| `JOOservices\ThemeCore\Lazy\Decision` | class | `packages/theme-core/src/Lazy/Decision.php` | none | `@internal` |
| `JOOservices\ThemeCore\Lazy\FragmentLazy` | class | `packages/theme-core/src/Lazy/FragmentLazy.php` | none | `@internal` |
| `JOOservices\ThemeCore\Lazy\Policy` | class | `packages/theme-core/src/Lazy/Policy.php` | none | `@internal` |
| `JOOservices\ThemeCore\Module\AbstractModule` | class | `packages/theme-core/src/Module/AbstractModule.php` | none | `@internal` |
| `JOOservices\ThemeCore\Module\ModuleInterface` | interface | `packages/theme-core/src/Module/ModuleInterface.php` | none | `@internal` |
| `JOOservices\ThemeCore\Performance\Fonts` | class | `packages/theme-core/src/Performance/Fonts.php` | none | `@internal` |
| `JOOservices\ThemeCore\Performance\FragmentCache` | class | `packages/theme-core/src/Performance/FragmentCache.php` | none | `@internal` |
| `JOOservices\ThemeCore\Performance\Images` | class | `packages/theme-core/src/Performance/Images.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\Breadcrumbs\Crumb` | class | `packages/theme-core/src/Seo/Breadcrumbs/Crumb.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\Breadcrumbs\TrailBuilder` | class | `packages/theme-core/src/Seo/Breadcrumbs/TrailBuilder.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\Canonical` | class | `packages/theme-core/src/Seo/Canonical.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\Context` | class | `packages/theme-core/src/Seo/Context.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\DescriptionBuilder` | class | `packages/theme-core/src/Seo/DescriptionBuilder.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\Hreflang` | class | `packages/theme-core/src/Seo/Hreflang.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\RobotsBuilder` | class | `packages/theme-core/src/Seo/RobotsBuilder.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\Schema\Graph` | class | `packages/theme-core/src/Seo/Schema/Graph.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\Schema\Piece` | interface | `packages/theme-core/src/Seo/Schema/Piece.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\SocialTags` | class | `packages/theme-core/src/Seo/SocialTags.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\TitleBuilder` | class | `packages/theme-core/src/Seo/TitleBuilder.php` | none | `@internal` |
| `JOOservices\ThemeCore\Seo\YieldPolicy` | class | `packages/theme-core/src/Seo/YieldPolicy.php` | none | `@internal` |
| `JOOservices\ThemeCore\Support\Hooks` | class | `packages/theme-core/src/Support/Hooks.php` | none | `@internal` |
| `JOOservices\ThemeCore\Support\ReadingTime` | class | `packages/theme-core/src/Support/ReadingTime.php` | none | `@internal` |
| `JOOservices\ThemeCore\Terminal\FsBuilder` | class | `packages/theme-core/src/Terminal/FsBuilder.php` | none | `@internal` |

The five retained symbols have in-scope consumers. Every other reviewed symbol had no production consumer or example-manifest class reference and is now marked `@internal`; no unapproved keep exception was made.
