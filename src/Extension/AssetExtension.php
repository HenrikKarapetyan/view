<?php

declare(strict_types=1);

namespace Henrik\View\Extension;

use RuntimeException;

final class AssetExtension implements ExtensionInterface
{
    /**
     * @var string root directory storing the published asset files
     */
    private string $basePath;

    /**
     * @var string base URL through which the published asset files can be accessed
     */
    private string $baseUrl;

    /**
     * @var bool whether to append a timestamp to the URL of every published asset
     */
    private bool $appendTimestamp;

    /**
     * @param string $basePath        root directory storing the published asset files
     * @param string $baseUrl         base URL through which the published asset files can be accessed
     * @param bool   $appendTimestamp whether to append a timestamp to the URL of every published asset
     */
    public function __construct(string $basePath, string $baseUrl = '', bool $appendTimestamp = false)
    {
        $this->basePath        = rtrim($basePath, '\/');
        $this->baseUrl         = rtrim($baseUrl, '/');
        $this->appendTimestamp = $appendTimestamp;
    }

    /**
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param string $basePath
     *
     * @return void
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     *
     * @return void
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return bool
     */
    public function isAppendTimestamp(): bool
    {
        return $this->appendTimestamp;
    }

    /**
     * @param bool $appendTimestamp
     *
     * @return void
     */
    public function setAppendTimestamp(bool $appendTimestamp): void
    {
        $this->appendTimestamp = $appendTimestamp;
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions(): array
    {
        return [
            'asset'     => [$this, 'assetFile'],
            'linkAsset' => [$this, 'linkAsset'],
        ];
    }

    /**
     * Includes the asset file and appends a timestamp with the last modification of that file.
     *
     * @param string $file
     *
     * @return string
     */
    public function assetFile(string $file): string
    {
        $url  = $this->baseUrl . '/' . ltrim($file, '/');
        $path = $this->basePath . '/' . ltrim($file, '/');

        if (!file_exists($path)) {
            throw new RuntimeException(sprintf(
                'Asset file "%s" does not exist.',
                $path
            ));
        }

        if ($this->appendTimestamp) {
            return $url . '?v=' . filemtime($path);
        }

        return $url;
    }

    /**
     * Includes the asset file and appends a timestamp with the last modification of that file.
     *
     * @param string $file
     *
     * @return string
     */
    public function linkAsset(string $file): string
    {
        return $this->baseUrl . ltrim($file, '/');
    }
}