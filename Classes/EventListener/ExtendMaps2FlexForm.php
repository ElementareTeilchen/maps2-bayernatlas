<?php

declare(strict_types=1);

namespace ElementareTeilchen\Maps2BayernAtlas\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterFlexFormDataStructureParsedEvent;

#[AsEventListener(identifier: 'maps2-bayernatlas/extend-maps2-flexform')]
final class ExtendMaps2FlexForm
{
    private const LANGUAGE_FILE = 'LLL:EXT:maps2_bayernatlas/Resources/Private/Language/locallang_be.xlf:';

    /**
     * TYPO3 13 uses the legacy pointer key. TYPO3 14 uses the content type directly.
     */
    private const MAPS2_DATA_STRUCTURE_KEYS = [
        '*,maps2_maps2',
        'maps2_maps2',
    ];

    public function __invoke(AfterFlexFormDataStructureParsedEvent $event): void
    {
        $identifier = $event->getIdentifier();

        if (
            ($identifier['type'] ?? null) !== 'tca'
            || ($identifier['tableName'] ?? null) !== 'tt_content'
            || ($identifier['fieldName'] ?? null) !== 'pi_flexform'
            || !in_array($identifier['dataStructureKey'] ?? null, self::MAPS2_DATA_STRUCTURE_KEYS, true)
        ) {
            return;
        }

        $dataStructure = $event->getDataStructure();
        $generalElements = $dataStructure['sheets']['sDEF']['ROOT']['el'] ?? null;

        if (!is_array($generalElements)) {
            return;
        }

        $dataStructure['sheets']['sDEF']['ROOT']['el'] = $this->addRendererField($generalElements);
        $dataStructure['sheets']['sBayernAtlas'] = $this->getBayernAtlasSheet();

        $event->setDataStructure($dataStructure);
    }

    /**
     * @param array<string, mixed> $elements
     * @return array<string, mixed>
     */
    private function addRendererField(array $elements): array
    {
        $result = [];
        $rendererField = [
            'label' => self::LANGUAGE_FILE . 'flexform.mapRenderer',
            'description' => self::LANGUAGE_FILE . 'flexform.mapRenderer.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => '',
                'items' => [
                    [
                        'label' => self::LANGUAGE_FILE . 'flexform.mapRenderer.siteDefault',
                        'value' => '',
                    ],
                    [
                        'label' => self::LANGUAGE_FILE . 'flexform.mapRenderer.maps2',
                        'value' => 'maps2',
                    ],
                    [
                        'label' => self::LANGUAGE_FILE . 'flexform.mapRenderer.bayernAtlas',
                        'value' => 'bayernatlas',
                    ],
                ],
            ],
        ];
        $rendererFieldInserted = false;

        foreach ($elements as $fieldName => $configuration) {
            $result[$fieldName] = $configuration;

            if ($fieldName === 'settings.poiCollection') {
                $result['settings.mapRenderer'] = $rendererField;
                $rendererFieldInserted = true;
            }
        }

        if (!$rendererFieldInserted) {
            $result['settings.mapRenderer'] = $rendererField;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getBayernAtlasSheet(): array
    {
        return [
            'ROOT' => [
                'sheetTitle' => self::LANGUAGE_FILE . 'flexform.sheetBayernAtlas',
                'type' => 'array',
                'el' => [
                    'settings.bayernAtlasBaseLayer' => [
                        'label' => self::LANGUAGE_FILE . 'flexform.baseLayer',
                        'description' => self::LANGUAGE_FILE . 'flexform.baseLayer.description',
                        'config' => [
                            'type' => 'input',
                            'default' => '',
                            'placeholder' => 'GEORESOURCE_WEB',
                            'eval' => 'trim',
                        ],
                    ],
                    'settings.bayernAtlasShowLabels' => [
                        'label' => self::LANGUAGE_FILE . 'flexform.showLabels',
                        'config' => [
                            'type' => 'select',
                            'renderType' => 'selectSingle',
                            'default' => '',
                            'items' => [
                                [
                                    'label' => self::LANGUAGE_FILE . 'flexform.siteDefault',
                                    'value' => '',
                                ],
                                [
                                    'label' => self::LANGUAGE_FILE . 'flexform.showLabels.show',
                                    'value' => '1',
                                ],
                                [
                                    'label' => self::LANGUAGE_FILE . 'flexform.showLabels.hide',
                                    'value' => '0',
                                ],
                            ],
                        ],
                    ],
                    'settings.bayernAtlasShowLayerControl' => [
                        'label' => self::LANGUAGE_FILE . 'flexform.showLayerControl',
                        'description' => self::LANGUAGE_FILE . 'flexform.showLayerControl.description',
                        'config' => [
                            'type' => 'select',
                            'renderType' => 'selectSingle',
                            'default' => '',
                            'items' => [
                                [
                                    'label' => self::LANGUAGE_FILE . 'flexform.siteDefault',
                                    'value' => '',
                                ],
                                [
                                    'label' => self::LANGUAGE_FILE . 'flexform.showLabels.show',
                                    'value' => '1',
                                ],
                                [
                                    'label' => self::LANGUAGE_FILE . 'flexform.showLabels.hide',
                                    'value' => '0',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
