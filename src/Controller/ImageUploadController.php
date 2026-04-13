<?php

namespace MartenaSoft\ImageBundle\Controller;

use MartenaSoft\ImageBundle\Entity\Image;
use MartenaSoft\ImageBundle\Form\UploadImageType;
use MartenaSoft\ImageBundle\Service\ImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ImageUploadController extends AbstractController
{
    #[Route("/image/upload/{uuid}", name: "image_upload", methods: ["POST"])]
    public function upload(
        ImageService $saveImageService,
        Request $request,
        string $uuid
    )
    {
        $activeSite = $request->attributes->get('active_site');
        $image = new Image();
        $form = $this->createForm(UploadImageType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('image')->getData();
            /** @var Image $image */
            $image = $form->getData();
            $result = $saveImageService->save($uploadedFile, $image, $activeSite, 'page', $uuid);

            return $this->json([
                'status' => 'success',
                'result' => [
                    'web_path' => $result['sizes']['small']['web_path'] .$result['sizes']['small']['after_upload']['file_name'],
                    'width' => $result['sizes']['small']['width'],
                    'height' => $result['sizes']['small']['height'],
                    'key' => $saveImageService->getKey(
                        $result['sizes']['small']['after_upload']['file_name'],
                        'page',
                        $uuid
                    ),

                ],
            ]);
        }

        return $this->json(['status' => 'error'], 400);
    }
}