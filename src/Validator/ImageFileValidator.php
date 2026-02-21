<?php

namespace MartenaSoft\ImageBundle\Validator;

use MartenaSoft\CommonLibrary\Dictionary\ImageDictionary;
use MartenaSoft\SdkBundle\Service\ImageConfigServiceSdk;
use MartenaSoft\SiteBundle\Service\ActiveSiteService;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\FileValidator;


#[AsTaggedItem('validator.constraint_validator')]
final class ImageFileValidator extends FileValidator
{
    public function __construct(
        private readonly ActiveSiteService $activeSiteService,
        private readonly ImageConfigServiceSdk $imageConfigService
    ) {

    }

    /**
     * @throws \Throwable
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ImageFile) {
            return;
        }

        $data = $this->imageConfigService->get($this->activeSiteService->get());
        $type = ImageDictionary::TYPES[$value->getType()] ?? null;

        if (!isset($data[$type])) {
            return;
        }
        $fileConstraint = new File(
            maxSize: $data[$type]['max_size'],
            mimeTypes: $data[$type]['mime_types']
        );

        parent::validate($value->getImage(), $fileConstraint);
    }
}
