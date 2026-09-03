<?php

declare(strict_types=1);

namespace ElementareTeilchen\Maps2BayernAtlas\Tests\Unit\ViewHelpers;

use ElementareTeilchen\Maps2BayernAtlas\Adapter\MapDataAdapter;
use ElementareTeilchen\Maps2BayernAtlas\ViewHelpers\MapDataViewHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MapDataViewHelperTest extends TestCase
{
    #[Test]
    public function receivesMapDataAdapterThroughConstructor(): void
    {
        $constructor = (new ReflectionClass(MapDataViewHelper::class))->getConstructor();

        self::assertNotNull($constructor);
        self::assertSame(
            MapDataAdapter::class,
            (string)$constructor->getParameters()[0]->getType(),
        );
    }

    #[Test]
    public function rendersDataThroughInjectedAdapter(): void
    {
        $viewHelper = new MapDataViewHelper(new MapDataAdapter());
        $viewHelper->setArguments([
            'poiCollections' => [],
            'environment' => [],
            'configuration' => ['showLabels' => '0'],
        ]);

        self::assertSame([
            'items' => [],
            'configuration' => [
                'showLabels' => '0',
                'width' => '100%',
                'height' => '560px',
                'zoom' => 12,
                'center' => [11.5, 48.8],
            ],
        ], $viewHelper->render());
    }
}
