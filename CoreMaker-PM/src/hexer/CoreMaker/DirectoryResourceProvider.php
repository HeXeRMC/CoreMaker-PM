<?php

namespace hexer\CoreMaker;

use pocketmine\plugin\ResourceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class DirectoryResourceProvider implements ResourceProvider {

    public function __construct(
        private string $path
    ) {}

    public function getResource(string $filename) {
        $file = $this->path . "/resources/" . $filename;
        if (!file_exists($file)) {
            return null;
        }
        return fopen($file, "rb");
    }

    public function getResources(): array {
        $resources = [];

        $resourcePath = $this->path . "/resources";
        if (!is_dir($resourcePath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resourcePath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = substr($file->getPathname(), strlen($resourcePath) + 1);
                $resources[$relative] = new SplFileInfo($file->getPathname());
            }
        }

        return $resources;
    }
}
