<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 08.04.15
 * Time: 19:54
 */

namespace Cundd\PersistentObjectStore\Asset;

use function Couchbase\defaultDecoder;
use Cundd\PersistentObjectStore\Asset\Exception\InvalidUriException;
use Cundd\PersistentObjectStore\Configuration\ConfigurationManager;
use Cundd\PersistentObjectStore\Memory\Manager;
use Cundd\PersistentObjectStore\Utility\GeneralUtility;

/**
 * Asset Provider implementation
 *
 * @package Cundd\PersistentObjectStore\Asset
 */
class AssetProvider implements AssetProviderInterface
{
    /**
     * Prefix for stored keys
     */
    const MEMORY_MANAGER_KEY_PREFIX = 'asset_';

    /**
     * Tag for session objects
     */
    const MEMORY_MANAGER_TAG = 'asset';

    /**
     * Returns if an Asset for the given URI exists
     *
     * @param string $uri
     * @return bool
     */
    public function hasAssetForUri($uri)
    {
        $this->assertUri($uri);

        return $this->getAssetForUri($uri) !== null;
    }

    /**
     * Returns the Asset for the given URI
     *
     * @param string $uri
     * @param bool   $noCache
     * @return AssetInterface|null
     */
    public function getAssetForUri($uri, $noCache = false)
    {
        $this->assertUri($uri);
        $memoryManagerKey = self::MEMORY_MANAGER_KEY_PREFIX . $uri;
        if (!$noCache && Manager::hasObject($memoryManagerKey)) {
            $assetEntry = Manager::getObject($memoryManagerKey);

            return $assetEntry->value;
        }

        $asset = $this->loadAssetForUri($uri);
        $assetEntry = (object)['value' => $asset];
        Manager::registerObject($assetEntry, self::MEMORY_MANAGER_KEY_PREFIX . $uri, [self::MEMORY_MANAGER_TAG]);

        return $asset;
    }

    /**
     * Returns the Asset for the given URI
     *
     * @param string $uri
     * @return AssetInterface|null
     */
    public function loadAssetForUri($uri)
    {
        $basePath = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('publicResources');
        $uri = str_replace('..', '', $uri);
        $fullPath = $basePath . $uri;
        if (file_exists($fullPath)) {
            return new Asset($uri, file_get_contents($fullPath), $this->getAssetContentType($fullPath));
        }

        return null;
    }

    /**
     * @param $fullPath
     * @return string
     */
    private function getAssetContentType($fullPath)
    {
        $fileInfo = new \finfo(FILEINFO_MIME);

        $mimeType = $fileInfo->file($fullPath);
        if ('text/plain' !== substr($mimeType, 0, 10)) {
            return $mimeType;
        }

        // Try to get a better result
        switch (strrchr($fullPath, '.')) {
            case '.css':
                return 'text/css';

            case '.js':
                return 'application/javascript';

            default:
                return $mimeType;
        }
    }

    /**
     * Assert that the given URI is valid
     *
     * @param string $uri
     */
    protected function assertUri($uri)
    {
        if (!is_string($uri)) {
            throw new InvalidUriException(
                sprintf('URI must be of type string %s given', GeneralUtility::getType($uri)),
                1428518315
            );
        }
        if (!$uri) {
            throw new InvalidUriException('No URI given', 1428518305);
        }
        if (strpos($uri, '..') !== false) {
            throw new InvalidUriException('URI contains illegal characters', 1428518310);
        }
    }
}