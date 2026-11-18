<?php

namespace App\Filesystems;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToDeleteFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class OneDriveAdapter implements FilesystemAdapter
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $refreshToken;
    protected string $rootFolder;
    protected string $tenant;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        string $rootFolder = '',
        string $tenant = 'common'
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        $this->rootFolder = trim($rootFolder, '/');
        $this->tenant = $tenant ?: 'common';
    }

    protected function getAccessToken(): string
    {
        $cacheKey = 'onedrive_access_token_' . md5($this->clientId . $this->refreshToken);

        return Cache::remember($cacheKey, 3000, function () {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to refresh OneDrive access token: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    protected function getUrl(string $path, bool $content = false): string
    {
        $fullPath = $this->rootFolder ? $this->rootFolder . '/' . ltrim($path, '/') : ltrim($path, '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $fullPath)));
        
        if ($content) {
            return "https://graph.microsoft.com/v1.0/me/drive/root:/{$encodedPath}:/content";
        }
        
        return "https://graph.microsoft.com/v1.0/me/drive/root:/{$encodedPath}";
    }

    public function fileExists(string $path): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get($this->getUrl($path));

            if ($response->status() === 200) {
                $data = $response->json();
                return isset($data['file']);
            }

            return false;
        } catch (\Exception $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get($this->getUrl($path));

            if ($response->status() === 200) {
                $data = $response->json();
                return isset($data['folder']);
            }

            return false;
        } catch (\Exception $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->withBody($contents, 'application/octet-stream')
                ->put($this->getUrl($path, true));

            if (!$response->successful()) {
                throw new \Exception('OneDrive write error: ' . $response->body());
            }
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $raw = stream_get_contents($contents);
        $this->write($path, $raw, $config);
    }

    public function read(string $path): string
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get($this->getUrl($path, true));

            if (!$response->successful()) {
                throw new \Exception('OneDrive read error: ' . $response->body());
            }

            return $response->body();
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->delete($this->getUrl($path));

            if ($response->status() !== 204 && $response->status() !== 404 && !$response->successful()) {
                throw new \Exception('OneDrive delete error: ' . $response->body());
            }
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $this->delete($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $parentPath = dirname($path);
            $dirName = basename($path);

            if ($parentPath === '.') {
                $url = "https://graph.microsoft.com/v1.0/me/drive/root/children";
            } else {
                $encodedParent = implode('/', array_map('rawurlencode', explode('/', $parentPath)));
                $url = "https://graph.microsoft.com/v1.0/me/drive/root:/{$encodedParent}:/children";
            }

            $response = Http::withToken($this->getAccessToken())
                ->post($url, [
                    'name' => $dirName,
                    'folder' => (object)[],
                    '@microsoft.graph.conflictBehavior' => 'replace',
                ]);

            if (!$response->successful() && $response->status() !== 409) {
                throw new \Exception('OneDrive createDirectory error: ' . $response->body());
            }
        } catch (\Exception $e) {
            // Fails silently if directory already exists
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Not implemented/supported
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get($this->getUrl($path));

            if ($response->status() === 200) {
                $data = $response->json();
                return new FileAttributes(
                    $path,
                    null,
                    null,
                    null,
                    $data['file']['mimeType'] ?? null
                );
            }
            throw new \Exception('File not found');
        } catch (\Exception $e) {
            return new FileAttributes($path);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get($this->getUrl($path));

            if ($response->status() === 200) {
                $data = $response->json();
                $timestamp = isset($data['lastModifiedDateTime']) 
                    ? strtotime($data['lastModifiedDateTime']) 
                    : null;
                return new FileAttributes($path, null, null, $timestamp);
            }
            throw new \Exception('File not found');
        } catch (\Exception $e) {
            return new FileAttributes($path);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get($this->getUrl($path));

            if ($response->status() === 200) {
                $data = $response->json();
                return new FileAttributes($path, $data['size'] ?? 0);
            }
            throw new \Exception('File not found');
        } catch (\Exception $e) {
            return new FileAttributes($path, 0);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $fullPath = $this->rootFolder ? $this->rootFolder . '/' . ltrim($path, '/') : ltrim($path, '/');
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $fullPath)));
            
            if ($encodedPath === '') {
                $url = "https://graph.microsoft.com/v1.0/me/drive/root/children";
            } else {
                $url = "https://graph.microsoft.com/v1.0/me/drive/root:/{$encodedPath}:/children";
            }

            $response = Http::withToken($this->getAccessToken())
                ->get($url);

            if (!$response->successful()) {
                return [];
            }

            $items = $response->json('value') ?? [];
            $results = [];

            foreach ($items as $item) {
                $itemPath = $path ? rtrim($path, '/') . '/' . $item['name'] : $item['name'];
                if (isset($item['folder'])) {
                    $results[] = new DirectoryAttributes($itemPath);
                } else {
                    $results[] = new FileAttributes(
                        $itemPath,
                        $item['size'] ?? null,
                        null,
                        isset($item['lastModifiedDateTime']) ? strtotime($item['lastModifiedDateTime']) : null,
                        $item['file']['mimeType'] ?? null
                    );
                }
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $content = $this->read($source);
        $this->write($destination, $content, $config);
    }

    public function getUrl(string $path): string
    {
        return route('documento.download-path', ['path' => $path]);
    }
}
