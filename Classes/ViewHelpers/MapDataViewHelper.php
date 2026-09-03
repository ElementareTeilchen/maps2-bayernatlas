<?php

declare(strict_types=1);

namespace ElementareTeilchen\Maps2BayernAtlas\ViewHelpers;

use ElementareTeilchen\Maps2BayernAtlas\Adapter\MapDataAdapter;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class MapDataViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(private readonly MapDataAdapter $mapDataAdapter) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('poiCollections', 'mixed', 'Maps2 POI collections.', true);
        $this->registerArgument('environment', 'array', 'Maps2 frontend environment.', true);
        $this->registerArgument('configuration', 'array', 'BayernAtlas configuration.', true);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, configuration: array<string, mixed>}
     */
    public function render(): array
    {
        return $this->mapDataAdapter->adapt(
            $this->arguments['poiCollections'],
            $this->arguments['environment'],
            $this->arguments['configuration'],
        );
    }
}
