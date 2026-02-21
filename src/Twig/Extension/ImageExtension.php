<?php

namespace MartenaSoft\ImageBundle\Twig\Extension;

use MartenaSoft\ImageBundle\Twig\Runtime\ImageExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ImageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('image', [ImageExtensionRuntime::class, 'image']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image', [ImageExtensionRuntime::class, 'image']),
        ];
    }
}
