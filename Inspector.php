<?php

declare(strict_types=1);

namespace Quark\Inspector;

use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class Inspector
{
    public const ROUTE_PREFIX = '/_inspector';

    private const CACHE_DIR = 'cache/inspector/';

    private ContainerInterface $container;

    /** @var iterable<string> */
    private $files;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        if (!is_dir($this->basePath(Inspector::CACHE_DIR))) {
            if (!mkdir($concurrentDirectory = $this->basePath(Inspector::CACHE_DIR), 0755, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
    }

    /**
     * Determine if the content is within the set limits.
     */
    public function contentWithinLimits(string $content): bool
    {
        $limit = $this->options['size_limit'] ?? 64;

        return mb_strlen($content) / 1000 <= $limit;
    }

    public function saveData(Collector $collector): void
    {
//        [$msec, $sec] = explode(' ', microtime(), 2);
//        $this->currentData['timeline'] = $this->getStopwatchEvents();
//
//        $msec = (int) ($msec * 100);

        $ts = microtime(true);
        $sec = (int) $ts;
        $msec = (int) (($ts - $sec) * 10000);

        file_put_contents(
            sprintf('%s%d_%04d', $this->basePath(self::CACHE_DIR), $sec, $msec),
            json_encode($collector->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function getFiles(): Collection
    {
        if ($this->files) {
            // Cached files
            return $this->files;
        }

        $this->files = collect(glob($this->basePath(self::CACHE_DIR) . '*', GLOB_NOSORT));
        return $this->files->sort()->reverse();
    }

    public function getFileNames(): Collection
    {
        return $this->getFiles()->map(function ($f) {
            return basename($f);
        });
    }

    public function readInfo($timestamp)
    {
        $file = $this->basePath(self::CACHE_DIR) . basename($timestamp);
        if (!is_file($file)) {
            throw new FileNotFoundException($file);
        }
        $data = file_get_contents($file);

        return json_decode($data);
    }

    private function basePath($path = ''): string
    {
        if ($this->container->has('path.base')) {
            return $this->container->get('path.base') . '/' . $path;
        }

        if (function_exists('base_path')) {
            return base_path($path);
        }

        return '../' . $path; // From `public/`
    }
}
