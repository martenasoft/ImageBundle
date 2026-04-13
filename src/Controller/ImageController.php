<?php

namespace MartenaSoft\ImageBundle\Controller;

use MartenaSoft\CommonLibrary\Dictionary\DictionaryMessage;
use MartenaSoft\CommonLibrary\Dictionary\ImageDictionary;
use MartenaSoft\CommonLibrary\Helper\StringHelper;
use MartenaSoft\CommonLibrary\Traits\AdminControllerTrait;
use MartenaSoft\ImageBundle\Entity\Image;
use MartenaSoft\ImageBundle\Form\ImageType;
use MartenaSoft\ImageBundle\Service\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ImageController extends AbstractController
{
    use AdminControllerTrait;
    public function __construct(
        private ImageService $saveImageService
    ) {

    }
    /**
     * @throws \Throwable
     */
    #[Route("/admin/image/{type}/{parentUuid}/items", name: "admin_index_image", methods: ["GET"])]
    #[IsGranted('ROUTE_ACCESS')]
    public function index(Request $request,  string $type, string $parentUuid): Response
    {
        $activeSite = $request->attributes->get('active_site');
        $this->saveImageService->setActiveSite($activeSite);
        $items = $this->saveImageService->getItems($type, $activeSite, [$parentUuid], 'middle');

        $files = [];

        if (!empty($items[$parentUuid]['main_files'])) {
            $files += $items[$parentUuid]['main_files'];
        }

        if (!empty($items[$parentUuid]['files'])) {
            $files += $items[$parentUuid]['files'];
        }

        return $this->render(sprintf('@Image/%s/index.html.twig', $activeSite->templatePath), [
            'items' => $files?? [],
            'type' => $type,
            'parentUuid' => $parentUuid,
        ]);
    }

    #[Route('/image/{type}/set-as-main/{parentUuid}/{key}/{format}',
        name: 'admin_image_set_as_main',
        defaults: ['format' => 'html'],
        methods: ["GET"])
    ]
    #[Route('/image/{type}/unset-as-main/{parentUuid}/{key}/{format}',
        name: 'admin_image_unset_as_main',
        defaults: ['format' => 'html'],
        methods: ["GET"]
    )]
    public function mainImage(
        Request $request,
        LoggerInterface $logger,
        string $type,
        string $parentUuid,
        string $key,
        string $format
    ): Response {

        try {
            $route = $request->attributes->get('_route');
            $activeSiteDto = $request->attributes->get('active_site');
            if ($route === 'admin_image_set_as_main') {
                $this->saveImageService->setAsMain($key, $type, $activeSiteDto, $parentUuid);
                if ($format !== 'json') {
                    $this->addFlash('success',DictionaryMessage::IMAGE_SET_AS_MAIN);
                }
            } else {
                $this->saveImageService->unSetAsMain($key, $type, $activeSiteDto, $parentUuid);
                if ($format !== 'json') {
                    $this->addFlash('success', DictionaryMessage::IMAGE_UNSET_AS_MAIN);
                }
            }
        } catch (\Throwable $exception) {
            if ($format !== 'json') {
                $this->addFlash('danger', DictionaryMessage::SOMETHING_WRONG);
            } else {
                return $this->json([
                    'status' => 'error',
                    'message' => DictionaryMessage::SOMETHING_WRONG,
                ], 400);
            }
            StringHelper::exceptionLoggerHelper(DictionaryMessage::SOMETHING_WRONG, $exception, $logger);
        }

        if ($format === 'json') {
            return $this->json([
                'status' => 'success',
            ]);
        }

        return $this->redirectToRoute('admin_index_image', [
            'type' => $type,
            'parentUuid' => $parentUuid,
            'key' => $key,
        ]);
    }

    /**
     * @throws \Throwable
     */
    #[Route("/admin/image/{type}/show/{parentUuid}/{key}", name: "admin_show_image", methods: ["GET"])]
    public function show(
        Request $request,
        string $type,
        string $parentUuid,
        string $key
    ): Response
    {
        $activeSiteDto = $request->attributes->get('active_site');
        $result = $this->saveImageService->getOneByKey($key, $type, $activeSiteDto, $parentUuid);
        $files = [];

        if (!empty($result[$key]['main_files']['big'])) {
            $files[] = $result[$key]['main_files']['big'];
        }

        if (!empty($result[$key]['files']['big'])) {
            $files[] = $result[$key]['files']['big'];
        }

        return $this->render(sprintf('@Image/%s/show.html.twig', $activeSiteDto->templatePath), [
            'images' => $files,
            'type' => $type,
            'parentUuid' => $parentUuid,
            'key' => $key,
        ]);
    }

    /**
     * @throws \Throwable
     */
    #[Route(
        "/image/{type}/show/{parentUuid}/{size}/{format}",
        defaults: ["format" => "html"],
        name: "show_image",
        methods: ["GET"]
    )]
    public function getImages(
        Request $request,
        string $type,
        string $parentUuid,
        string $size,
        string $format
    ): Response
    {
        $activeSiteDto = $request->attributes->get('active_site');
        $result = $this->saveImageService->getItems($type, $activeSiteDto, [$parentUuid], $size);
        $files = [];

        if (!empty($result[$parentUuid]['main_files'])) {
            foreach ($result[$parentUuid]['main_files'] as $item) {
                $files[] = $item;
            }
        }

        if (!empty($result[$parentUuid]['files'])) {
            foreach ($result[$parentUuid]['files'] as $item) {
                $files[] = $item;
            }
        }

        if ($format === 'json') {
            return $this->json([
                'status' => 'success',
                'images' => $files,
            ]);
        }

        return $this->render(sprintf('@Image/%s/_carusel.html.twig', $activeSiteDto->templatePath), [
            'images' => $files,
            'type' => $type,
            'parentUuid' => $parentUuid,
        ]);
    }

    /**
     * @throws \Throwable
     */
    #[Route(
        "/image/{type}/delete/{parentUuid}/{key}/{format}",
        name: "admin_delete_image",
        defaults: ["format" => "html"],
        methods: ["GET"]
    )]
    public function delete(
        Request $request,

        LoggerInterface $logger,
        string $type,
        string $parentUuid,
        string $key,
        string $format
    ): Response {

        try {
            $this->saveImageService->delete($key, $type, $request->attributes->get('active_site'), $parentUuid);
            if ($format !== 'json') {
                $this->addFlash('success',DictionaryMessage::IMAGE_DELETED);
            }

        } catch (\Throwable $exception) {

            if ($format === 'json') {
                return $this->json([
                    'status' => 'error',
                    'message' => DictionaryMessage::IMAGE_DELETING_ERROR,
                ], 400);
            }
            $this->addFlash('danger', DictionaryMessage::IMAGE_DELETING_ERROR);
            StringHelper::exceptionLoggerHelper(DictionaryMessage::IMAGE_DELETING_ERROR, $exception, $logger);
        }

        if ($format === 'json') {
            return $this->json([
                'status' => 'success',
            ]);
        }

        return $this->redirectToRoute("admin_index_image", [
            "type" => $type,
            "parentUuid" => $parentUuid,
        ]);
    }

    /**
     * @throws \Throwable
     */
    #[Route("/admin/image/{type}/{parentUuid}/create", name: "admin_create_image", methods: ["GET", "POST"])]
    #[IsGranted('ROUTE_ACCESS')]
    public function create(
        string $type,
        string $parentUuid,
        Request $request,
        LoggerInterface $logger
    ): Response {
        $activeSite = $request->attributes->get('active_site');
        $this->saveImageService->setActiveSite($activeSite);
        $image = new Image();
        $image->setType(ImageDictionary::getTypeIndex($type));
        $form = $this->createForm(ImageType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $uploadedFile = $form->get('image')->getData();
                    /** @var Image $image */
                    $image = $form->getData();
                    $this->saveImageService->save($uploadedFile, $image, $activeSite, $type, $parentUuid);
                    $this->addFlash('success',DictionaryMessage::PAGE_SAVED);
                    return $this->redirectToRoute("admin_index_image", [
                        'type' => $type,
                        'parentUuid' => $parentUuid,
                    ]);
                } catch (\Throwable $exception) {
                    $this->addFlash('danger',DictionaryMessage::PAGE_SAVING_ERROR);
                    StringHelper::exceptionLoggerHelper(DictionaryMessage::PAGE_SAVING_ERROR, $exception, $logger);
                }
            } else {
                $this->addFlash('danger', $form->getErrors()->__toString());
            }
        }


        return $this->render(sprintf('@Image/%s/form.html.twig', $activeSite->templatePath), [
            'form' => $form->createView(),
            'type' => $type,
            'parentUuid' => $parentUuid,
        ]);
    }
}
