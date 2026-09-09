<?php

declare(strict_types=1);

namespace ElementareTeilchen\Maps2BayernAtlas\Adapter;

use JWeiland\Maps2\Domain\Model\Category;
use JWeiland\Maps2\Domain\Model\PoiCollection;

final readonly class MapDataAdapter
{
    /**
     * @return array{items: array<int, array<string, mixed>>, configuration: array<string, mixed>}
     */
    public function adapt(
        mixed $poiCollections,
        array $environment,
        array $configuration,
    ): array {
        if ($poiCollections instanceof PoiCollection) {
            $poiCollections = [$poiCollections];
        }

        if (!is_iterable($poiCollections)) {
            throw new \InvalidArgumentException('Expected an iterable collection of maps2 records.', 1788296400);
        }

        $items = [];
        foreach ($poiCollections as $poiCollection) {
            if (!$poiCollection instanceof PoiCollection) {
                continue;
            }

            $item = $this->mapPoiCollection($poiCollection);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [
            'items' => $items,
            'configuration' => $this->mapConfiguration($environment, $configuration),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPoiCollection(PoiCollection $poiCollection): ?array
    {
        $type = match ($poiCollection->getCollectionType()) {
            'Point' => 'point',
            'Route' => 'line',
            'Area' => 'polygon',
            'Radius' => 'circle',
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $item = [
            'id' => (int)$poiCollection->getUid(),
            'title' => $poiCollection->getTitle(),
            'type' => $type,
            'coordinates' => $this->mapCoordinates($poiCollection, $type),
            'categories' => $this->mapCategories($poiCollection),
        ];

        if ($poiCollection->getStrokeColor() !== '') {
            $item['color'] = $poiCollection->getStrokeColor();
        }

        if ($type === 'point' && $poiCollection->getMarkerIcon() !== '') {
            $references = $poiCollection->getMarkerIcons();
            if ($references->count() === 0) {
                $references = $poiCollection->getFirstFoundCategoryWithIcon()?->getMaps2MarkerIcons();
            }
            $references?->rewind();
            $file = $references?->current()?->getOriginalResource();

            $item['icon'] = [
                'url' => $poiCollection->getMarkerIcon(),
                'width' => $poiCollection->getMarkerIconWidth(),
                'height' => $poiCollection->getMarkerIconHeight(),
                'anchorX' => $poiCollection->getMarkerIconAnchorPosX(),
                'anchorY' => $poiCollection->getMarkerIconAnchorPosY(),
                'originalWidth' => (int)($file?->getProperty('width') ?? 0),
                'originalHeight' => (int)($file?->getProperty('height') ?? 0),
            ];
        }

        if ($type === 'circle') {
            $item['radius'] = $poiCollection->getRadius();
        }

        return $item;
    }

    /**
     * @return array<int, float>|array<int, array<int, float>>
     */
    private function mapCoordinates(PoiCollection $poiCollection, string $type): array
    {
        if ($type !== 'line' && $type !== 'polygon') {
            return [
                $poiCollection->getLongitude(),
                $poiCollection->getLatitude(),
            ];
        }

        return array_map(
            static fn(array $poi): array => [
                (float)($poi['longitude'] ?? 0.0),
                (float)($poi['latitude'] ?? 0.0),
            ],
            $poiCollection->getPois(),
        );
    }

    /**
     * @return array<int, array{id: int, title: string, sorting: int}>
     */
    private function mapCategories(PoiCollection $poiCollection): array
    {
        $categories = [];

        foreach ($poiCollection->getCategories() as $category) {
            if (!$category instanceof Category) {
                continue;
            }

            $categories[] = [
                'id' => (int)$category->getUid(),
                'title' => $category->getTitle(),
                'sorting' => $category->getSorting(),
            ];
        }

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapConfiguration(array $environment, array $configuration): array
    {
        $settings = is_array($environment['settings'] ?? null)
            ? $environment['settings']
            : [];
        $extensionConfiguration = is_array($environment['extConf'] ?? null)
            ? $environment['extConf']
            : [];

        return array_replace($configuration, [
            'width' => ($settings['mapWidth'] ?? null) ?: ($configuration['width'] ?? '100%'),
            'height' => ($settings['mapHeight'] ?? null) ?: ($configuration['height'] ?? '560px'),
            'zoom' => ($settings['zoom'] ?? null) ?: ($configuration['zoom'] ?? 12),
            'center' => [
                (float)($extensionConfiguration['defaultLongitude'] ?? 11.5),
                (float)($extensionConfiguration['defaultLatitude'] ?? 48.8),
            ],
        ]);
    }
}
