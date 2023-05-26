<?php

namespace VV\PixxioFlysystem\Assets;

use Statamic\Assets\Asset as StatamicAsset;

class Asset extends StatamicAsset
{
    /**
     * Get the asset's URL, catching a special case if it's coming from Pixxio.
     *
     * @return string
     */
    public function url()
    {
        $adapter = $this->container()->disk()->filesystem()->getAdapter();

        if (is_a($adapter, 'VV\PixxioFlysystem\PixxioAdapter')) {
            return $adapter->getUrl($this->path);
        }

        return parent::url();
    }
}
