<?php

namespace MartenaSoft\ImageBundle\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;

class ImageOptimizer
{
    private Imagine $imagine;

    private int $width;
    private int $height;

    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->imagine = new Imagine();
    }

    public function resize(string $filename): void
    {
        list($iWidth, $iHeight) = getimagesize($filename);
        $ratio = $iWidth / $iHeight;
        $width = $this->width;
        $height = $this->height;
        if ($this->width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }
        $photo = $this->imagine->open($filename);
        $box = new Box($width, $height);
        $photo->resize($box)->save($filename);
    }
}
