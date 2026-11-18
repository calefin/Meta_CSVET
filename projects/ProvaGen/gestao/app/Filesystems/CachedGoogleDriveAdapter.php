<?php

namespace App\Filesystems;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;

class CachedGoogleDriveAdapter implements FilesystemAdapter
{
    protected FilesystemAdapter $adapter;
    protected int $ttl = 600; // 10 minutos em segundos

    public function __construct(FilesystemAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function getAdapter(): FilesystemAdapter
    {
        return $this->adapter;
    }

    public function fileExists(string $path): bool
    {
        return Cache::remember("gdrive:file_exists:" . md5($path), $this->ttl, function () use ($path) {
            return $this->adapter->fileExists($path);
        });
    }

    public function directoryExists(string $path): bool
    {
        return Cache::remember("gdrive:dir_exists:" . md5($path), $this->ttl, function () use ($path) {
            return $this->adapter->directoryExists($path);
        });
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->clearCacheForPath($path);
        $this->adapter->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->clearCacheForPath($path);
        $this->adapter->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->clearCacheForPath($path);
        $this->adapter->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->clearCacheForPath($path);
        $this->adapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->clearCacheForPath($path);
        $this->adapter->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->clearCacheForPath($path);
        $this->adapter->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->adapter->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return Cache::remember("gdrive:mime_type:" . md5($path), $this->ttl, function () use ($path) {
            return $this->adapter->mimeType($path);
        });
    }

    public function lastModified(string $path): FileAttributes
    {
        return Cache::remember("gdrive:last_modified:" . md5($path), $this->ttl, function () use ($path) {
            return $this->adapter->lastModified($path);
        });
    }

    public function fileSize(string $path): FileAttributes
    {
        return Cache::remember("gdrive:file_size:" . md5($path), $this->ttl, function () use ($path) {
            return $this->adapter->fileSize($path);
        });
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $cacheKey = "gdrive:list_contents:" . md5($path) . ":" . ($deep ? '1' : '0');

        return Cache::remember($cacheKey, $this->ttl, function () use ($path, $deep) {
            $contents = $this->adapter->listContents($path, $deep);
            return is_array($contents) ? $contents : iterator_to_array($contents);
        });
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->clearCacheForPath($source);
        $this->clearCacheForPath($destination);
        $this->adapter->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->clearCacheForPath($source);
        $this->clearCacheForPath($destination);
        $this->adapter->copy($source, $destination, $config);
    }

    public function clearCacheForPath(string $path): void
    {
        Cache::forget("gdrive:file_exists:" . md5($path));
        Cache::forget("gdrive:dir_exists:" . md5($path));
        Cache::forget("gdrive:mime_type:" . md5($path));
        Cache::forget("gdrive:last_modified:" . md5($path));
        Cache::forget("gdrive:file_size:" . md5($path));

        $parent = dirname($path);
        Cache::forget("gdrive:list_contents:" . md5($parent) . ":0");
        Cache::forget("gdrive:list_contents:" . md5($parent) . ":1");
        Cache::forget("gdrive:list_contents:" . md5($path) . ":0");
        Cache::forget("gdrive:list_contents:" . md5($path) . ":1");
    }

    public static function clearAllGoogleDriveCache(): void
    {
        Cache::forget("gdrive:list_contents:" . md5('submissions') . ":0");
        Cache::forget("gdrive:list_contents:" . md5('submissions') . ":1");
        Cache::forget("gdrive:list_contents:" . md5('returns') . ":0");
        Cache::forget("gdrive:list_contents:" . md5('returns') . ":1");
        Cache::forget("gdrive:list_contents:" . md5('receipts') . ":0");
        Cache::forget("gdrive:list_contents:" . md5('receipts') . ":1");
        Cache::forget("gdrive:list_contents:" . md5('/') . ":0");
        Cache::forget("gdrive:list_contents:" . md5('/') . ":1");
    }

    public function getUrl(string $path): string
    {
        return route('documento.download-path', ['path' => $path]);
    }
}
