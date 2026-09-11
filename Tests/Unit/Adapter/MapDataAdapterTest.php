<?php

declare(strict_types=1);

namespace ElementareTeilchen\Maps2BayernAtlas\Tests\Unit\Adapter;

use ElementareTeilchen\Maps2BayernAtlas\Adapter\MapDataAdapter;
use JWeiland\Maps2\Domain\Model\Category;
use JWeiland\Maps2\Domain\Model\PoiCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MapDataAdapterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, array<int, float>|array<int, array<int, float>>}>
     */
    public static function supportedCollectionTypes(): iterable
    {
        yield 'point' => ['Point', 'point', [11.59452, 48.13617]];
        yield 'route' => ['Route', 'line', [[11.5, 48.1], [11.6, 48.2]]];
        yield 'area' => ['Area', 'polygon', [[11.5, 48.1], [11.6, 48.2]]];
        yield 'radius' => ['Radius', 'circle', [11.59452, 48.13617]];
    }

    #[Test]
    #[DataProvider('supportedCollectionTypes')]
    public function mapsSupportedCollectionTypes(
        string $collectionType,
        string $expectedType,
        array $expectedCoordinates,
    ): void {
        $collection = $this->createCollection($collectionType);

        $result = (new MapDataAdapter())->adapt([$collection], [], []);

        self::assertSame($expectedType, $result['items'][0]['type']);
        self::assertSame($expectedCoordinates, $result['items'][0]['coordinates']);
    }

    #[Test]
    public function ignoresUnknownCollectionTypes(): void
    {
        $collection = $this->createCollection('Unknown');

        $result = (new MapDataAdapter())->adapt([$collection], [], []);

        self::assertSame([], $result['items']);
    }

    #[Test]
    public function mapsCategoriesColorAndRadius(): void
    {
        $collection = $this->createCollection('Radius');
        $collection->setStrokeColor('#123456');
        $collection->setRadius(350);

        $category = new TestCategory();
        $category->setTestUid(9);
        $category->setTitle('Democracy');
        $category->setSorting(20);
        $collection->addCategory($category);

        $result = (new MapDataAdapter())->adapt([$collection], [], []);
        $item = $result['items'][0];

        self::assertSame('#123456', $item['color']);
        self::assertSame(350, $item['radius']);
        self::assertSame([
            ['id' => 9, 'title' => 'Democracy', 'sorting' => 20],
        ], $item['categories']);
    }

    #[Test]
    public function omitsEmptyStrokeColor(): void
    {
        $result = (new MapDataAdapter())->adapt([
            $this->createCollection('Point'),
        ], [], []);

        self::assertArrayNotHasKey('color', $result['items'][0]);
    }

    #[Test]
    public function appliesEnvironmentAndConfigurationFallbacks(): void
    {
        $result = (new MapDataAdapter())->adapt([], [
            'settings' => [
                'mapWidth' => '90%',
                'mapHeight' => '',
                'zoom' => 14,
            ],
            'extConf' => [
                'defaultLongitude' => 10.5,
                'defaultLatitude' => 49.2,
            ],
        ], [
            'height' => '500px',
            'zoom' => 12,
            'showLabels' => '0',
        ]);

        self::assertSame([
            'height' => '500px',
            'zoom' => 14,
            'showLabels' => '0',
            'width' => '90%',
            'center' => [10.5, 49.2],
        ], $result['configuration']);
    }

    #[Test]
    public function inheritsCategoryIconsAndAllowsPoiOverrides(): void
    {
        $coreFile = $this->createStub(\TYPO3\CMS\Core\Resource\FileReference::class);
        // maps2 12 resolves relative URLs against a host, which this unit test does not provide.
        $coreFile->method('getPublicUrl')->willReturn('https://example.test/icons/parking.png');
        $coreFile->method('getProperty')->willReturnMap([['width', 60], ['height', 60]]);
        $reference = $this->createStub(\TYPO3\CMS\Extbase\Domain\Model\FileReference::class);
        $reference->method('getOriginalResource')->willReturn($coreFile);
        $references = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $references->attach($reference);

        $category = new TestCategory();
        $category->setMaps2MarkerIcons($references);
        $category->setMaps2MarkerIconWidth(30);
        $category->setMaps2MarkerIconHeight(30);
        $collection = $this->createCollection('Point');
        $collection->addCategory($category);
        $adapter = new MapDataAdapter();
        $icon = $adapter->adapt([$collection], [], [])['items'][0]['icon'];
        self::assertSame('https://example.test/icons/parking.png', $icon['url']);
        self::assertSame(30, $icon['width']);
        self::assertSame(60, $icon['originalWidth']);

        $collection->setMarkerIcons($references);
        $collection->setMarkerIconWidth(42);
        self::assertSame(42, $adapter->adapt([$collection], [], [])['items'][0]['icon']['width']);
    }

    private function createCollection(string $collectionType): TestPoiCollection
    {
        $collection = new TestPoiCollection();
        $collection->setTestUid(67);
        $collection->setCollectionType($collectionType);
        $collection->setTitle('Test item');
        $collection->setLongitude(11.59452);
        $collection->setLatitude(48.13617);
        $collection->setTestPois([
            ['longitude' => 11.5, 'latitude' => 48.1],
            ['longitude' => 11.6, 'latitude' => 48.2],
        ]);

        return $collection;
    }
}

final class TestPoiCollection extends PoiCollection
{
    /** @var array<int, array{longitude: float, latitude: float}> */
    private array $testPois = [];

    public function setTestUid(int $uid): void
    {
        $this->uid = $uid;
    }

    /**
     * @param array<int, array{longitude: float, latitude: float}> $pois
     */
    public function setTestPois(array $pois): void
    {
        $this->testPois = $pois;
    }

    /**
     * @return array<int, array{longitude: float, latitude: float}>
     */
    public function getPois(): array
    {
        return $this->testPois;
    }
}

final class TestCategory extends Category
{
    public function setTestUid(int $uid): void
    {
        $this->uid = $uid;
    }
}
