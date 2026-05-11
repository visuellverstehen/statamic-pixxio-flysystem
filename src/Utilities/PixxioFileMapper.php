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
        $this->id = (int) $data['id'];
        $this->relativePath = self::getRelativePath($data);
        $this->absolutePath = $data['originalFileURL'];
        $this->filesize = (int) $data['fileSize'];
        $this->width = (int) $data['width'];
        $this->height = (int) $data['height'];
        $this->alternativeText = self::getAlternativeText($data);
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

    protected function getAlternativeText(array $data): ?string
    {
        if (! $alternativeText = $this->getMetaData($data, 'Alternativetext')) {
            return null;
        }

        return YAML::dump($alternativeText);
    }

    protected function getCopyrightText(array $data): ?string
    {
        if (! $copyright = $this->getMetaData($data, 'CopyrightNotice')) {
            return null;
        }

        if (! $photographer = $this->getMetaData($data, 'Fotograf')) {
            return YAML::dump($copyright);
        }

        return YAML::dump("{$copyright}, {$photographer}");
    }

    protected function getDescription(array $data): ?string
    {
        if (! $description = $data['description'] ?? null) {
            return null;
        }

        return YAML::dump($description);
    }

    protected function getMetaData($data, $name): mixed
    {
        $metaData = ($data['importantMetadata'] ?? $data['metadataFields']) ?? [];

        foreach ($metaData ?? [] as $meta) {
            if (($meta['name'] ?? null) === $name) {
                $value = $meta['value'] ?? null;

                return is_array($value) ? null : $value;
            }
        }

        return null;
    }
}
