<?php

namespace MartenaSoft\ImageBundle\Service;


use Imagine\Exception\NotFoundException;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use MartenaSoft\CommonLibrary\Helper\StringHelper;
use MartenaSoft\ImageBundle\Entity\Image;
use MartenaSoft\ImageBundle\Repository\ImageRepository;
use MartenaSoft\SdkBundle\Service\ImageConfigServiceSdk;
use MartenaSoft\SiteBundle\Dto\ActiveSiteDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageService
{
    private const string SALT = '33faasd22356WW-5235^523112-45wer2-1';
    private const string LOG_PREFIX = 'image-service';

    private ActiveSiteDto $activeSiteDto;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
        private readonly ImageConfigServiceSdk $imageConfigService,
    )
    {

    }

    /**
     * @throws \Throwable
     */
    public function getActiveSite(): ActiveSiteDto
    {
        return $this->activeSiteDto;
    }

    public function setActiveSite(ActiveSiteDto $activeSiteDto): self
    {
        $this->activeSiteDto = $activeSiteDto;
        return $this;
    }

    /**
     * @throws \Throwable
     */
    public function delete(string $key, string $type, ActiveSiteDto $activeSiteDto, string $uuid): void
    {
        try {
            $items = $this->getOneByKey($key, $type, $activeSiteDto, $uuid);

            foreach ($items[$key] as $key => $items) {
                foreach ($items as $size => $item) {
                    if (empty($item['full_path'])) {
                        continue;
                    }
                    try {
                        unlink($item['full_path']);
                        $this->logger->info(self::LOG_PREFIX . ' File deleted successful!!', [
                            'key' => $key,
                            'size' => $size,
                            'uuid' => $uuid,
                            'full_path' => $item['full_path'],
                            'active_site' => $activeSiteDto->id,
                        ]);
                    } catch(\Throwable $throwable)  {
                        $this->logger->info(self::LOG_PREFIX . ' Error deleting file!!', [
                            'key' => $key,
                            'size' => $size,
                            'uuid' => $uuid,
                            'full_path' => $item['full_path'],
                            'active_site' => $activeSiteDto->id,
                        ]);
                        throw $throwable;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . ' Error removing from db! {_file}', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws \Throwable
     */
    public function getItems(string $type, ActiveSiteDto $activeSiteDto, array $uuid, string $size): array
    {
        $result = [];
        $configs = $this->imageConfigService->get($activeSiteDto, $type);
        if (empty($configs['sizes'])) {
            return [];
        }

        foreach ($uuid as $uuidItem) {
            $path = DIRECTORY_SEPARATOR . $uuidItem . DIRECTORY_SEPARATOR;
            $configs['sizes'][$size]['path'] .= $path;
            $configs['sizes'][$size]['web_path'] .= $path;
            $pathMain = StringHelper::pathCleaner($configs['sizes'][$size]['path'] . DIRECTORY_SEPARATOR . 'main');

            $configs['sizes'][$size]['path'] = StringHelper::pathCleaner($configs['sizes'][$size]['path']);
            $configs['sizes'][$size]['web_path'] = StringHelper::pathCleaner($configs['sizes'][$size]['web_path']);

            $configs['sizes'][$size]['main_files'] = $this->getFiles(
                $pathMain,
                $configs['sizes'][$size]['web_path'] . DIRECTORY_SEPARATOR . 'main' . DIRECTORY_SEPARATOR,
                $configs['sizes'][$size]['path'] . DIRECTORY_SEPARATOR . 'main' . DIRECTORY_SEPARATOR,
                $type,
                $uuidItem,
                ['isMain' => true]
            );

            $configs['sizes'][$size]['files'] = $this->getFiles(
                $configs['sizes'][$size]['path'],
                $configs['sizes'][$size]['web_path'],
                $configs['sizes'][$size]['path'],
                $type,
                $uuidItem,
                ['isMain' => false]
            );

            $result[$uuidItem] = $configs['sizes'][$size];
        }

        return $result;
    }

    public function setAsMain(string $key, string $type, ActiveSiteDto $activeSiteDto, string $uuid): void
    {
        $this->moveFile($key, $type, $activeSiteDto, $uuid, 'files', function (array $items, string $newPath) use ($uuid) {
            $mainDir = StringHelper::pathCleaner(
                $newPath .
                DIRECTORY_SEPARATOR .
                $uuid .
                DIRECTORY_SEPARATOR .
                'main'
            );

            if (!file_exists($mainDir)) {
                mkdir($mainDir, 0777, true);
            }

            $newPath = StringHelper::pathCleaner(
                $mainDir .
                DIRECTORY_SEPARATOR .
                $items['name']
            );
            rename($items['full_path'], $newPath);
        });
    }

    public function unSetAsMain(string $key, string $type, ActiveSiteDto $activeSiteDto, string $uuid): void
    {
        $this->moveFile($key, $type, $activeSiteDto, $uuid, 'main_files', function (array $items, string $newPath) use ($uuid) {
            $newPath = StringHelper::pathCleaner($newPath . DIRECTORY_SEPARATOR . $uuid . DIRECTORY_SEPARATOR . $items['name']);
            rename($items['full_path'], $newPath);
        });
    }

    private function moveFile(string $key, string $type, ActiveSiteDto $activeSiteDto, string $uuid, string $index, callable $move): void
    {
        $items = $this->getOneByKey($key, $type, $activeSiteDto, $uuid)[$key][$index] ?? [];
        $configs = $this->imageConfigService->get($activeSiteDto, $type)['sizes'] ?? [];

        foreach ($items as $key => $item) {
            $newPath = $configs[$key]['path'] ?? null;

            if (!$newPath || empty($item['full_path'])) {
                continue;
            }

            $move($item, $newPath);
        }
    }
    public function getOneByKey(string $key, string $type, ActiveSiteDto $activeSiteDto, string $uuid): array
    {
        $sizes = array_keys(($this->imageConfigService->get($activeSiteDto, $type)['sizes'] ?? []));
        $result = [];
        foreach ($sizes as $size) {
            $config = $this->getItems($type, $activeSiteDto, [$uuid], $size)[$uuid] ?? [];

            if (!empty($config['main_files'])) {
                foreach ($config['main_files'] as $fileName => $fileItems) {
                    if (isset($fileItems['key']) && $fileItems['key'] === $key) {
                        $result[$key]['main_files'][$size] = $fileItems;
                    }
                }
            }

            if (!empty($config['files'])) {
                foreach ($config['files'] as $fileName => $fileItems) {
                    if (isset($fileItems['key']) && $fileItems['key'] === $key) {
                        $result[$key]['files'][$size] = $fileItems;
                    }
                }
            }

        }

        if (empty($result[$key])) {
            $message = sprintf(
                'Item not found key: %s type: %s siteId: %s uuid: %s',
                $key,
                $type,
                $activeSiteDto->id,
                $uuid
            );

            $this->logger->error($message);
            throw new NotFoundException($message);
        }

        return $result;
    }

    public function getKey(string $name, string $type, string $uuid)
    {
        return md5(self::SALT . $name . '-' . $type. '-' . $uuid);
    }

    private function getFiles(
        string $path,
        string $webPath,
        string $fullPath,
        string $type,
        string $uuid,
        array $map = []
    ): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $fileItems = scandir($path) ?? [];
        $files = array_filter($fileItems, fn ($file) => $file !== '.' && $file !== '..' && $file !== 'main');
        $result = [];

        foreach ($files as $name) {
            $result[$name] = array_merge([
                'web_path' => StringHelper::pathCleaner($webPath . $name),
                'full_path' => StringHelper::pathCleaner($fullPath . $name),
                'key' => $this->getKey($name, $type, $uuid),
                'name' => $name,
            ], $map);
        }
        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function save(Form $form, ActiveSiteDto $activeSiteDto, string $type, string $parentUuid): void
    {
        try {
            $uploadedFile = $form->get('image')->getData();
            /** @var Image $image */
            $image = $form->getData();

            if (!$uploadedFile || !$image) {
                $this->logger->error(self::LOG_PREFIX, [
                    'uploadedFile is wrong' => (!$uploadedFile? 'yes' : 'no'),
                    'image is wrong' => (!$image? 'yes' : 'no'),
                ]);
                return;
            }

            $originalFilename = uniqid() . '-' . pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $originalFilename = StringHelper::slug($originalFilename);
            $newFilename = $originalFilename . '.' . $uploadedFile->guessExtension();
            $uniqTmp = uniqid() . '__' . $newFilename;
            $configs = $this->imageConfigService->get($activeSiteDto, $type);

            foreach ($configs['sizes'] as &$config) {
                $path = DIRECTORY_SEPARATOR . $parentUuid . DIRECTORY_SEPARATOR . ($image->isMain()? 'main' . DIRECTORY_SEPARATOR : '');
                $config['path'] .= $path;
                $config['web_path'] .= $path;
                $this->upload($uploadedFile, $image, $config, $newFilename, $uniqTmp, $parentUuid);
                $config['file'] = $uniqTmp;
            }

        } catch (\Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . ' Error saving in db!', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws \Throwable
     */
    public function upload(
        UploadedFile $uploadedFile,
        Image $image,
        array $config,
        string $newFilename,
        string $uniqTmp,
        string $parentUuid
    ): void
    {
        $resizedPath = StringHelper::pathCleaner($config['path'] . DIRECTORY_SEPARATOR);
        $this->removeFile($image, $config);

        if (!file_exists($resizedPath)) {
            //Creating image directory if not exists
            try {
                $this->logger->notice(self::LOG_PREFIX . ' try to create dir {dir} ', ['dir' => $resizedPath]);
                mkdir($resizedPath, 0777, recursive: true);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . ' Error creating dir {dir}!', [
                    'dir' => $resizedPath,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ]);
                throw $e;
            }
        }

        $realPath = $uploadedFile->getRealPath();
        if ($config['width'] > 0 && $config['height'] > 0) {
            //Copy temporary file for resizing
            try {
                $this->logger->notice(self::LOG_PREFIX . ' try to copy for resize {_file} ', ['file' => $uniqTmp]);
                copy($realPath, $uniqTmp);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . ' Error creating dir {_file}!', [
                    '_file' => $uniqTmp,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ]);
                throw $e;
            }

            //Resize temporary file for resizing
            try {
                $this->logger->notice(self::LOG_PREFIX . ' try to copy resized file {_file} ', ['_file' => $uniqTmp]);
                $imageOptimizer = new ImageOptimizer($config['width'], $config['height']);
                $imageOptimizer->resize($uniqTmp);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . ' Error creating file {_dir}!', [
                    '_file' => $uniqTmp,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ]);
                throw $e;
            }

            //Copy resized temporary file to path form config
            try {
                $this->logger->notice(self::LOG_PREFIX . ' try to copy resized file {_file} to new path {path} ', [
                    '_file' => $uniqTmp,
                    'path' => $resizedPath . $newFilename,
                ]);
                copy($uniqTmp, $resizedPath . $newFilename);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . ' Error copy resized file {_file} to new path {path}!', [
                    '_file' => $uniqTmp,
                    'path' => $resizedPath . $newFilename,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ]);
                throw $e;
            }

            //Remove resized temporary file
            try {
                $this->logger->notice(self::LOG_PREFIX . ' try to remove temporary file {_file}', [
                    '_file' => $uniqTmp,
                ]);
                unlink($uniqTmp);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . ' Error removing temporary file {_file}!', [
                    '_file' => $resizedPath . $image->getImage(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ]);
                throw $e;
            }
        } else {
            //Copy real file in path from config
            try {
                $this->logger->notice(self::LOG_PREFIX . ' try to copy original file {_file} to path {path} ', [
                    '_file' => $realPath,
                    'path' => $resizedPath . $image->getImage(),
                ]);
                copy($realPath, $resizedPath . $newFilename);
            } catch (\Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . ' Error creating dir {_file}!', [
                    '_file' => $realPath,
                    'path' => $resizedPath . $image->getImage(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ]);
                throw $e;
            }
        }
    }

    public function getFile(Image $image, array $config): string
    {
        if (empty($image->getImage())) {
            throw new NotFoundException('file name is empty');
        }

        return StringHelper::pathCleaner($config['path'] . DIRECTORY_SEPARATOR . $image->getImage());
    }

    public function removeFile(Image $image, array $config): void
    {
        $fileName = 'not found';
        try {
            $fileName = $this->getFile($image, $config);

            if (!file_exists($fileName)) {
                $this->logger->error(self::LOG_PREFIX . ' try to remove {_file} File not found', [
                    '_file' => $fileName,
                ]);
                return;
            }

            $this->logger->notice(self::LOG_PREFIX . ' try to remove {_file} ', [
                '_file' => $fileName,
            ]);
            unlink($fileName);

        } catch (\Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . ' Error removing {_file}!', [
                '_file' => $fileName,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ]);
            throw $e;
        }
    }
}
