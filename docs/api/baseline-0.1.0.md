# Public API baseline 0.1.0

Symbols marked `@api` in `packages/theme-core/src`. Everything else is `@internal` and may change in any release. Check changes with `tools/bc-check.sh <last-tag>` before releasing.

| Symbol | Kind | File |
|---|---|---|
| `JOOservices\ThemeCore\Assets\AssetManifest` | class | `src/Assets/AssetManifest.php` |
| `JOOservices\ThemeCore\Assets\AssetRegistry` | class | `src/Assets/AssetRegistry.php` |
| `JOOservices\ThemeCore\Assets\Preloader` | class | `src/Assets/Preloader.php` |
| `JOOservices\ThemeCore\Blocks\BlockRegistrar` | class | `src/Blocks/BlockRegistrar.php` |
| `JOOservices\ThemeCore\Blocks\CodeEnhancer` | class | `src/Blocks/CodeEnhancer.php` |
| `JOOservices\ThemeCore\Blocks\DynamicBlock` | class | `src/Blocks/DynamicBlock.php` |
| `JOOservices\ThemeCore\Blocks\EditorBundle` | class | `src/Blocks/EditorBundle.php` |
| `JOOservices\ThemeCore\Blocks\FragmentBlock` | class | `src/Blocks/FragmentBlock.php` |
| `JOOservices\ThemeCore\Blocks\HeadingAnchors` | class | `src/Blocks/HeadingAnchors.php` |
| `JOOservices\ThemeCore\Blocks\Html` | class | `src/Blocks/Html.php` |
| `JOOservices\ThemeCore\Blocks\PatternLoader` | class | `src/Blocks/PatternLoader.php` |
| `JOOservices\ThemeCore\Blocks\TemplateLocator` | class | `src/Blocks/TemplateLocator.php` |
| `JOOservices\ThemeCore\Blocks\ThemeScheme` | class | `src/Blocks/ThemeScheme.php` |
| `JOOservices\ThemeCore\Compat\Acf` | class | `src/Compat/Acf.php` |
| `JOOservices\ThemeCore\Compat\AdapterInterface` | interface | `src/Compat/AdapterInterface.php` |
| `JOOservices\ThemeCore\Compat\AdapterRegistry` | class | `src/Compat/AdapterRegistry.php` |
| `JOOservices\ThemeCore\Compat\Detector` | class | `src/Compat/Detector.php` |
| `JOOservices\ThemeCore\Compat\Forms` | class | `src/Compat/Forms.php` |
| `JOOservices\ThemeCore\Compat\Multilingual` | interface | `src/Compat/Multilingual.php` |
| `JOOservices\ThemeCore\Compat\Multilingual\Lang` | class | `src/Compat/Multilingual/Lang.php` |
| `JOOservices\ThemeCore\Compat\Multilingual\Resolver` | class | `src/Compat/Multilingual/Resolver.php` |
| `JOOservices\ThemeCore\Compat\PageBuilder` | class | `src/Compat/PageBuilder.php` |
| `JOOservices\ThemeCore\Config\Manifest` | class | `src/Config/Manifest.php` |
| `JOOservices\ThemeCore\Config\Settings` | class | `src/Config/Settings.php` |
| `JOOservices\ThemeCore\Container\Container` | class | `src/Container/Container.php` |
| `JOOservices\ThemeCore\Container\ContainerException` | class | `src/Container/ContainerException.php` |
| `JOOservices\ThemeCore\Container\ContainerInterface` | interface | `src/Container/ContainerInterface.php` |
| `JOOservices\ThemeCore\Container\NotFoundException` | class | `src/Container/NotFoundException.php` |
| `JOOservices\ThemeCore\Content\ContentProviderInterface` | interface | `src/Content/ContentProviderInterface.php` |
| `JOOservices\ThemeCore\Content\Model\CategoryNode` | class | `src/Content/Model/CategoryNode.php` |
| `JOOservices\ThemeCore\Content\Model\Company` | class | `src/Content/Model/Company.php` |
| `JOOservices\ThemeCore\Content\Model\CompanyPage` | class | `src/Content/Model/CompanyPage.php` |
| `JOOservices\ThemeCore\Content\Model\ImageRef` | class | `src/Content/Model/ImageRef.php` |
| `JOOservices\ThemeCore\Content\Model\PostCard` | class | `src/Content/Model/PostCard.php` |
| `JOOservices\ThemeCore\Content\Model\Profile` | class | `src/Content/Model/Profile.php` |
| `JOOservices\ThemeCore\Content\Model\Topic` | class | `src/Content/Model/Topic.php` |
| `JOOservices\ThemeCore\Content\NullProvider` | class | `src/Content/NullProvider.php` |
| `JOOservices\ThemeCore\Content\WpContentProvider` | class | `src/Content/WpContentProvider.php` |
| `JOOservices\ThemeCore\Http\CacheHeaders` | class | `src/Http/CacheHeaders.php` |
| `JOOservices\ThemeCore\I18n\JsStrings` | class | `src/I18n/JsStrings.php` |
| `JOOservices\ThemeCore\I18n\Language` | class | `src/I18n/Language.php` |
| `JOOservices\ThemeCore\I18n\LanguageTagging` | class | `src/I18n/LanguageTagging.php` |
| `JOOservices\ThemeCore\I18n\Languages` | class | `src/I18n/Languages.php` |
| `JOOservices\ThemeCore\I18n\Translations` | class | `src/I18n/Translations.php` |
| `JOOservices\ThemeCore\Kernel` | class | `src/Kernel.php` |
| `JOOservices\ThemeCore\Lazy\Decision` | class | `src/Lazy/Decision.php` |
| `JOOservices\ThemeCore\Lazy\FragmentLazy` | class | `src/Lazy/FragmentLazy.php` |
| `JOOservices\ThemeCore\Lazy\Policy` | class | `src/Lazy/Policy.php` |
| `JOOservices\ThemeCore\Module\AbstractModule` | class | `src/Module/AbstractModule.php` |
| `JOOservices\ThemeCore\Module\ModuleInterface` | interface | `src/Module/ModuleInterface.php` |
| `JOOservices\ThemeCore\Performance\Fonts` | class | `src/Performance/Fonts.php` |
| `JOOservices\ThemeCore\Performance\FragmentCache` | class | `src/Performance/FragmentCache.php` |
| `JOOservices\ThemeCore\Performance\Images` | class | `src/Performance/Images.php` |
| `JOOservices\ThemeCore\Seo\Breadcrumbs\Crumb` | class | `src/Seo/Breadcrumbs/Crumb.php` |
| `JOOservices\ThemeCore\Seo\Breadcrumbs\TrailBuilder` | class | `src/Seo/Breadcrumbs/TrailBuilder.php` |
| `JOOservices\ThemeCore\Seo\Canonical` | class | `src/Seo/Canonical.php` |
| `JOOservices\ThemeCore\Seo\Context` | class | `src/Seo/Context.php` |
| `JOOservices\ThemeCore\Seo\DescriptionBuilder` | class | `src/Seo/DescriptionBuilder.php` |
| `JOOservices\ThemeCore\Seo\Hreflang` | class | `src/Seo/Hreflang.php` |
| `JOOservices\ThemeCore\Seo\RobotsBuilder` | class | `src/Seo/RobotsBuilder.php` |
| `JOOservices\ThemeCore\Seo\Schema\Graph` | class | `src/Seo/Schema/Graph.php` |
| `JOOservices\ThemeCore\Seo\Schema\Piece` | interface | `src/Seo/Schema/Piece.php` |
| `JOOservices\ThemeCore\Seo\SocialTags` | class | `src/Seo/SocialTags.php` |
| `JOOservices\ThemeCore\Seo\TitleBuilder` | class | `src/Seo/TitleBuilder.php` |
| `JOOservices\ThemeCore\Seo\YieldPolicy` | class | `src/Seo/YieldPolicy.php` |
| `JOOservices\ThemeCore\Support\Hooks` | class | `src/Support/Hooks.php` |
| `JOOservices\ThemeCore\Support\ReadingTime` | class | `src/Support/ReadingTime.php` |
| `JOOservices\ThemeCore\Terminal\FsBuilder` | class | `src/Terminal/FsBuilder.php` |

68 symbols.
