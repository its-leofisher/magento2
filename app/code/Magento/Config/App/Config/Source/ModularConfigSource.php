<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Config\App\Config\Source;

use Magento\Config\Model\Config\Structure\Reader as ConfigStructureReader;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ConfigSourceInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\App\Config\Initial\Reader as InitialConfigReader;

/**
 * Class for retrieving initial configuration from modules
 *
 * @api
 * @since 100.1.2
 */
class ModularConfigSource implements ConfigSourceInterface
{
    /**
     * @var InitialConfigReader
     */
    private $initialConfigReader;

    /**
     * @var ConfigStructureReader
     */
    private $configStructureReader;

    /**
     * @param InitialConfigReader $initialConfigReader
     * @param ConfigStructureReader|null $configStructureReader
     */
    public function __construct(
        InitialConfigReader $initialConfigReader,
        ?ConfigStructureReader $configStructureReader = null
    ) {
        $this->initialConfigReader = $initialConfigReader;
        $this->configStructureReader = $configStructureReader
            ?? ObjectManager::getInstance()->get(ConfigStructureReader::class);
    }

    /**
     * Get initial data
     *
     * @param string $path Format is scope type and scope code separated by slash: e.g. "type/code"
     * @return array
     * @since 100.1.2
     */
    public function get($path = '')
    {
        $initialConfig = $this->initialConfigReader->read();
        $configStructure = $this->configStructureReader->read(Area::AREA_ADMINHTML);
        $sections = $configStructure['config']['system']['sections'] ?? [];
        $defaultConfig = $initialConfig['data']['default'] ?? [];
        $initialConfig['data']['default'] = $this->merge($defaultConfig, $sections);

        $data = new DataObject($initialConfig);
        if ($path !== '') {
            $path = '/' . $path;
        }
        return $data->getData('data' . $path) ?: [];
    }

    /**
     * Merge initial config with config structure
     *
     * @param array $config
     * @param array $sections
     * @return array
     */
    private function merge(array $config, array $sections): array
    {
        foreach ($sections as $section) {
            if (isset($section['children'])) {
                $config[$section['id']] = $this->merge(
                    $config[$section['id']] ?? [],
                    $section['children']
                );
            } elseif ($section['_elementType'] === 'field') {
                $config += [$section['id'] => null];
            }
        }

        return $config;
    }
}
