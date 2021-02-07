<?php
declare(strict_types=1);

namespace dashboard\Brotkrueml\JobRouterDownloads;

use JobRouter\Api\Dashboard\v1\Widget;

require_once 'RssRepository.php';

final class JobRouterDownloads extends Widget
{
    private const CACHE_KEY_TEMPLATE = 'bkJobRouterDownloads_%s';
    private const CACHE_EXPIRE_SECONDS = 86400;
    private const CACHE_IMAGES_PATH = 'cache';

    /** @var RssRepository */
    private $rssRepository;

    /** @var array */
    private $urls;

    public function __construct()
    {
        $this->rssRepository = new RssRepository();
        $this->urls = include 'urls.php';
    }

    public function getTitle(): string
    {
        return CONST_JOBROUTER_DOWNLOADS;
    }

    public function getDimensions(): array
    {
        return [
            'minHeight' => 3,
            'maxHeight' => 4,
            'minWidth' => 3,
            'maxWidth' => 4,
        ];
    }

    public function getData(): array
    {
        $language = $this->getLanguage();
        $cacheKey = \sprintf(self::CACHE_KEY_TEMPLATE, $language);
        $cache = \Utility::getServiceContainer()->cache->getCache('dashboard');

        $feedItems = $cache->get($cacheKey, function ($item) use ($language) {
            $item->expiresAfter(self::CACHE_EXPIRE_SECONDS);

            $feedItems = $this->rssRepository->find($this->urls[$language]['rss']);
            \array_walk($feedItems, function (array &$feedItem): void {
                if (!$feedItem['image']) {
                    return;
                }

                if ($imageUrl = $this->getCachedImageUrl($feedItem['image']['src'])) {
                    $feedItem['image']['src'] = $imageUrl;
                } else {
                    unset($feedItem['image']);
                }
            });

            return $feedItems;
        });

        $dateFormat = $this->getUserDateFormat();
        \array_walk($feedItems, static function (array &$feedItem) use ($dateFormat): void {
            try {
                $feedItem['pubDate'] = (new \DateTimeImmutable($feedItem['pubDate']))->format($dateFormat);
            } catch (\Exception $e) {
                // do nothing
            }
        });

        return [
            'allDownloadsText' => CONST_ALL_DOWNLOADS,
            'allDownloadsUrl' => $this->urls[$language]['overview'],
            'items' => $feedItems,
            'linkTitle' => CONST_LINK_TITLE,
            'noItems' => CONST_NO_ITEMS_FOUND,
        ];
    }

    private function getLanguage(): string
    {
        $language = $this->getUser()->getLanguage();

        return \in_array($language, ['english', 'german']) ? $language : 'english';
    }

    private function getUserDateFormat(): string
    {
        return \str_replace(
            ['DD', 'MM', 'YYYY'],
            ['d', 'm', 'Y'],
            \explode(' ', $this->getUser()->getDateFormat())[0]
        );
    }

    private function getCachedImageUrl(string $remoteImageUrl): ?string
    {
        $imageParts = \explode('/', $remoteImageUrl);
        $imageName = \end($imageParts);
        $cacheDir = $this->getWidgetFilePath(self::CACHE_IMAGES_PATH);
        $imagePath = $cacheDir . DIRECTORY_SEPARATOR . $imageName;

        if (!\is_file($imagePath)) {
            if (!\is_dir($cacheDir)) {
                \mkdir($cacheDir, 0777, true);
            }

            $image = \file_get_contents($remoteImageUrl);
            if ($image !== false) {
                if (\file_put_contents($imagePath, $image) === false) {
                    $imagePath = null;
                }
            }
        }

        if ($imagePath && $imageUrl = $this->getWidgetFileUrl(self::CACHE_IMAGES_PATH . DIRECTORY_SEPARATOR . $imageName)) {
            return $imageUrl;
        }

        return null;
    }
}