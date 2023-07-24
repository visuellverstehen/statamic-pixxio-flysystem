<?php

namespace VV\PixxioFlysystem\Utilities;

use GuzzleHttp\Psr7\MimeType;
use Statamic\Facades\YAML;
use VV\PixxioFlysystem\Traits\PixxioFileHelper;

class PixxioFileMapper
{
    use PixxioFileHelper;

    protected int $id;
    protected string $relativePath;
    protected string $absolutePath;
    protected int $filesize;
    protected int $width;
    protected int $height;
    protected ?string $alternativeText;
    protected ?string $copyright;
    protected ?string $description;
    protected ?string $lastModified;

    public function __construct(array $data)
    {
        $this->id = (int)$data['id'];
        $this->relativePath = self::getRelativePath($data);
        $this->absolutePath = $data['imagePath'];
        $this->filesize = (int)$data['fileSize'];
        $this->width = (int)$data['imageWidth'];
        $this->height = (int)$data['imageHeight'];
        $this->alternativeText = YAML::dump($data['dynamicMetadata']['Alternativetext']) ?? null;
        $this->copyright = self::getCopyrightText($data);
        $this->description = self::getDescription($data);
        $this->lastModified = $data['uploadDate'] ?? null;
    }

    public function toArray()
    {
        return [
            'pixxio_id' => $this->id,
            'relative_path' => $this->relativePath,
            'absolute_path' => $this->absolutePath,
            'filesize' => $this->filesize,
            'width' => $this->width,
            'height' => $this->height,
            'mimetype' => MimeType::fromFilename($this->relativePath),
            'last_modified' => $this->lastModified,
            'alternative_text' => $this->alternativeText,
            'copyright' => $this->copyright,
            'description' => $this->description,
            'updated_at' => now(),
        ];
    }

    protected function getCopyrightText(array $data): ?string
    {
        if (!$copyright = $data['dynamicMetadata']['CopyrightNotice'] ?? null) {
            return null;
        }

        if (!$photographer = $data['dynamicMetadata']['Fotograf'] ?? null) {
            return  YAML::dump($copyright);
        }

        return YAML::dump("{$copyright}, {$photographer}");
    }

    protected function getDescription(array $data): ?string
    {
        if (!$description = $data['description'] ?? null) {
            return null;
        }

        return YAML::dump($description);
    }
}
