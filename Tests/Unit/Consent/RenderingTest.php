<?php

declare(strict_types=1);

namespace ElementareTeilchen\Maps2BayernAtlas\Tests\Unit\Consent;

use ElementareTeilchen\Maps2BayernAtlas\Adapter\MapDataAdapter;
use ElementareTeilchen\Maps2BayernAtlas\ViewHelpers\MapDataViewHelper;
use JWeiland\Maps2\Configuration\ExtConf;
use JWeiland\Maps2\Helper\MapHelper;
use JWeiland\Maps2\ViewHelpers\IsRequestToMapProviderAllowedViewHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Render the real templates and maps2 permission ViewHelper. Only the framework
 * edges (translation, asset collector, URL builder and map component) are fakes.
 * The HTTP integration check must additionally exercise middleware and caching.
 */
final class RenderingTest extends TestCase
{
    #[DataProvider('permissionCases')]
    public function testMapPartialHonorsMaps2Permission(bool $required, array $cookies, array $query, bool $allowed): void
    {
        $html = $this->render($required, $cookies, $query);

        self::assertSame($allowed, str_contains($html, 'data-test-map="rendered"'));
        self::assertSame(!$allowed, str_contains($html, 'class="maps2-bayernatlas-consent"'));
        self::assertSame($allowed, isset(AssetViewHelper::$assets['maps2-bayernatlas-adapter']));

        if (!$allowed) {
            self::assertSame(['maps2-bayernatlas-consent'], array_keys(AssetViewHelper::$assets));
            self::assertStringContainsString('height: 300px;', $html);
            self::assertStringContainsString('mapProviderRequestsAllowedForMaps2', $html);
        }
    }

    public static function permissionCases(): iterable
    {
        yield 'switch disabled' => [false, [], [], true];
        yield 'first visit' => [true, [], [], false];
        yield 'activation link' => [true, [], ['tx_maps2_maps2' => ['mapProviderRequestsAllowedForMaps2' => 1]], true];
        yield 'saved shared consent' => [true, ['mapProviderRequestsAllowedForMaps2' => '1'], [], true];
        yield 'unrelated cookie' => [true, ['cookieconsent_status' => 'allow'], [], false];
        yield 'invalid query value' => [true, [], ['tx_maps2_maps2' => ['mapProviderRequestsAllowedForMaps2' => 0]], false];
        yield 'consent removed' => [true, [], [], false];
    }

    public function testOverlayUsesBayernAtlasNotice(): void
    {
        $html = $this->render(true, [], [], 'Overlay', 'bayernatlas');

        self::assertStringContainsString('class="maps2-bayernatlas-consent"', $html);
        self::assertStringContainsString('consent.description', $html);
        self::assertStringNotContainsString('data-test-map', $html);
        self::assertSame(['maps2-bayernatlas-consent'], array_keys(AssetViewHelper::$assets));
    }

    public function testStandardRendererKeepsUpstreamOsmOverlay(): void
    {
        $html = $this->render(true, [], [], 'Overlay', 'maps2');

        self::assertStringContainsString('osm.protectData', $html);
        self::assertStringNotContainsString('maps2-bayernatlas-consent', $html);
        self::assertSame([], AssetViewHelper::$assets);
    }

    public function testSharedOverlayTemplateOverridesBothMaps2Versions(): void
    {
        $extensionPath = dirname(__DIR__, 3);
        $maps2Path = dirname((new \ReflectionClass(MapHelper::class))->getFileName(), 3);
        $paths = new TemplatePaths();
        $paths->setTemplateRootPaths([
            $maps2Path . '/Resources/Private/Templates/',
            $extensionPath . '/Resources/Private/Templates/',
        ]);

        self::assertSame(
            $extensionPath . '/Resources/Private/Templates/PoiCollection/Overlay.html',
            $paths->resolveTemplateFileForControllerAndActionAndFormat('PoiCollection', 'Overlay'),
        );
    }

    public function testMultipleDeniedMapsUseDistinctIdsAndOneLocalAsset(): void
    {
        $html = $this->render(true, [], [], 'Multiple');

        self::assertStringContainsString('id="maps2-123"', $html);
        self::assertStringContainsString('id="maps2-456"', $html);
        self::assertSame(2, substr_count($html, 'class="maps2-bayernatlas-consent"'));
        self::assertSame(['maps2-bayernatlas-consent'], array_keys(AssetViewHelper::$assets));
    }

    private function render(bool $required, array $cookies, array $query, string $template = 'Map', string $renderer = 'bayernatlas'): string
    {
        $oldCookies = $_COOKIE;
        $oldRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $request = (new ServerRequest('https://example.test/map'))->withQueryParams($query);
        $_COOKIE = $cookies;
        $GLOBALS['TYPO3_REQUEST'] = $request;
        AssetViewHelper::$assets = [];

        try {
            $context = new RenderingContext();
            $context->setAttribute(ServerRequestInterface::class, $request);
            $context->setViewHelperResolver(new ConsentViewHelperResolver(
                new MapHelper(new ExtConf(explicitAllowMapProviderRequests: $required)),
            ));

            $extensionPath = dirname(__DIR__, 3);
            $paths = $context->getTemplatePaths();
            $maps2Path = dirname((new \ReflectionClass(MapHelper::class))->getFileName(), 3);
            $paths->setPartialRootPaths([
                $maps2Path . '/Resources/Private/Partials/',
                $extensionPath . '/Resources/Private/Partials/',
            ]);
            $paths->setTemplatePathAndFilename($template === 'Map'
                ? $extensionPath . '/Resources/Private/Partials/BayernAtlas/Map.html'
                : $extensionPath . '/Resources/Private/Templates/PoiCollection/Overlay.html');

            if ($template === 'Multiple') {
                $paths->setTemplateSource(<<<'FLUID'
                    <f:render partial="BayernAtlas/Map" arguments="{contentElementUid: 123, environment: environment, configuration: configuration, poiCollections: poiCollections, infoWindow: infoWindow}"/>
                    <f:render partial="BayernAtlas/Map" arguments="{contentElementUid: 456, environment: environment, configuration: configuration, poiCollections: poiCollections, infoWindow: infoWindow}"/>
                    FLUID);
            }

            $view = new TemplateView($context);
            $view->assignMultiple([
                'contentElementUid' => 123,
                'data' => ['uid' => 123],
                'environment' => ['settings' => ['mapRenderer' => $renderer, 'mapProvider' => 'osm', 'mapHeight' => '300']],
                'configuration' => [],
                'poiCollections' => [],
                'infoWindow' => true,
            ]);

            return $view->render();
        } finally {
            $_COOKIE = $oldCookies;
            if ($oldRequest === null) {
                unset($GLOBALS['TYPO3_REQUEST']);
            } else {
                $GLOBALS['TYPO3_REQUEST'] = $oldRequest;
            }
        }
    }
}

final class ConsentViewHelperResolver extends ViewHelperResolver
{
    public function __construct(private readonly MapHelper $mapHelper) {}

    public function resolveViewHelperClassName(string $namespaceIdentifier, string $methodIdentifier): string
    {
        return match ($namespaceIdentifier . ':' . $methodIdentifier) {
            'baf:map' => MapViewHelper::class,
            'f:translate' => TranslationViewHelper::class,
            'f:flashMessages' => EmptyViewHelper::class,
            'f:asset.css', 'f:asset.script' => AssetViewHelper::class,
            'maps2:requestUriForOverlay', 'm:requestUriForOverlay' => ActivationUriViewHelper::class,
            default => parent::resolveViewHelperClassName($namespaceIdentifier, $methodIdentifier),
        };
    }

    public function createViewHelperInstanceFromClassName(string $viewHelperClassName): ViewHelperInterface
    {
        return match ($viewHelperClassName) {
            IsRequestToMapProviderAllowedViewHelper::class => new IsRequestToMapProviderAllowedViewHelper($this->mapHelper),
            MapDataViewHelper::class => new MapDataViewHelper(new MapDataAdapter()),
            default => parent::createViewHelperInstanceFromClassName($viewHelperClassName),
        };
    }
}

final class MapViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('id', 'string', '', true);
        $this->registerArgument('items', 'array', '', true);
        $this->registerArgument('configuration', 'array', '', true);
    }

    public function render(): string
    {
        return '<div data-test-map="rendered"></div>';
    }
}

final class AssetViewHelper extends AbstractViewHelper
{
    public static array $assets = [];

    public function initializeArguments(): void
    {
        foreach (['identifier', 'href', 'src', 'type', 'inline', 'useNonce', 'csp'] as $name) {
            $this->registerArgument($name, 'mixed', '');
        }
    }

    public function render(): string
    {
        self::$assets[$this->arguments['identifier']] = $this->arguments;

        return '';
    }
}

final class TranslationViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', '', true);
    }

    public function render(): string
    {
        return $this->arguments['key'];
    }
}

final class ActivationUriViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('ttContentUid', 'int', '');
    }

    public function render(): string
    {
        return '/map?tx_maps2_maps2[mapProviderRequestsAllowedForMaps2]=1';
    }
}

final class EmptyViewHelper extends AbstractViewHelper
{
    public function render(): string
    {
        return '';
    }
}
