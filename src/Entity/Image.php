<?php

namespace MartenaSoft\ImageBundle\Entity;


use Doctrine\ORM\Mapping as ORM;

use MartenaSoft\CommonLibrary\Dictionary\ImageDictionary;
use MartenaSoft\CommonLibrary\Entity\Interfaces\AuthorInterface;
use MartenaSoft\CommonLibrary\Entity\Interfaces\ImageInterface;
use MartenaSoft\CommonLibrary\Entity\Traits\AuthorTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\CreatedAtTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\DeletedAtTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\ImageTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\ParentUuidTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\PostgresIdTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\SiteIdTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\UpdatedAtTrait;
use MartenaSoft\CommonLibrary\Entity\Traits\UuidTrait;
use MartenaSoft\ImageBundle\Repository\ImageRepository;
use MartenaSoft\ImageBundle\Validator\ImageFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ImageFile]
class Image
{
    private ?string $image = null;

    private ?bool $isMain = false;

    private ?int $type = null;


    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function isMain(): ?bool
    {
        return $this->isMain;
    }

    public function setIsMain(?bool $isMain): static
    {
        $this->isMain = $isMain;
        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): static
    {
        $this->type = $type;
        return $this;
    }
}
