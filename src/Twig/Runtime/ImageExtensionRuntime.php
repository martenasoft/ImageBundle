<?php

namespace MartenaSoft\ImageBundle\Twig\Runtime;


use MartenaSoft\CommonLibrary\Dictionary\ImageDictionary;
use MartenaSoft\CommonLibrary\Helper\StringHelper;
use MartenaSoft\ImageBundle\Entity\Image;
use MartenaSoft\ImageBundle\Service\ImageService;
use MartenaSoft\SdkBundle\Service\ImageConfigServiceSdk;
use MartenaSoft\SiteBundle\Dto\ActiveSiteDto;
use Psr\Log\LoggerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class ImageExtensionRuntime implements RuntimeExtensionInterface
{
    private const string LOG_PREFIX = 'image';

    public function __construct(
        private readonly ImageService $imageService,
        private readonly LoggerInterface $logger,
        private readonly ImageConfigServiceSdk $imageConfigService,
    )
    {
    }

    public function image(Image|null $image, ActiveSiteDto $activeSiteDto, string $size, ?int $type = null): string|bool
    {
        $config = $this->imageConfigService->get($activeSiteDto, ImageDictionary::TYPES[($image?->getType() ?? $type)])['sizes'] ?? [];

        if (!$config) {
            $this->logger->error(self::LOG_PREFIX . ' configuration not found', [
                'size' => $size,
                'config' => $config,
            ]);
            return false;
        }

        $notFound = StringHelper::pathCleaner($config[$size]['not_found_web_path']);
        if ($image === null) {
            return $notFound;
        }
        $path = StringHelper::pathCleaner($config[$size]['path'] . DIRECTORY_SEPARATOR . $image->getImage());

        if (!file_exists($path)) {
            $this->logger->error(self::LOG_PREFIX . ' image not found in directory', [
                'path' => $path,
            ]);
            return $notFound;
        }

        return StringHelper::pathCleaner($config[$size]['web_path'] . DIRECTORY_SEPARATOR . $image->getImage());
    }
}
