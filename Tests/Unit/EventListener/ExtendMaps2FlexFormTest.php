<?php

declare(strict_types=1);

namespace ElementareTeilchen\Maps2BayernAtlas\Tests\Unit\EventListener;

use ElementareTeilchen\Maps2BayernAtlas\EventListener\ExtendMaps2FlexForm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ExtendMaps2FlexFormTest extends TestCase
{
    #[Test]
    public function rendererFieldIsAppendedWhenMaps2AnchorFieldIsMissing(): void
    {
        $listener = new ExtendMaps2FlexForm();
        $method = new ReflectionMethod($listener, 'addRendererField');

        $result = $method->invoke($listener, [
            'settings.categories' => ['label' => 'Categories'],
        ]);

        self::assertSame(
            ['settings.categories', 'settings.mapRenderer'],
            array_keys($result),
        );
    }
}
