<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Model\Category\Plugin;

use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Model\StorageInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\CatalogUrlRewrite\Model\ResourceModel\Category\Product;

class Storage
{
    /** @var UrlFinderInterface */
    private $urlFinder;

    /** @var Product */
    private $productResource;

    /**
     * @param UrlFinderInterface $urlFinder
     * @param Product $productResource
     */
    public function __construct(
        UrlFinderInterface $urlFinder,
        Product $productResource
    ) {
        $this->urlFinder = $urlFinder;
        $this->productResource = $productResource;
    }

    /**
     * @param \Magento\UrlRewrite\Model\StorageInterface $object
     * @param null $result
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $urls
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterReplace(StorageInterface $object, $result, array $urls)
    {
        $toSave = [];
        foreach ($this->filterUrls($urls) as $record) {
            $metadata = $record->getMetadata();
            $toSave[] = [
                'url_rewrite_id' => $record->getUrlRewriteId(),
                'category_id' => $metadata['category_id'],
                'product_id' => $record->getEntityId(),
            ];
        }
        if ($toSave) {
            $this->productResource->saveMultiple($toSave);
        }
    }

    /**
     * @param \Magento\UrlRewrite\Model\StorageInterface $object
     * @param array $data
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDeleteByData(StorageInterface $object, array $data)
    {
        $this->productResource->removeMultipleByFilter($data);
    }

    /**
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $urls
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function filterUrls(array $urls)
    {
        $filteredUrls = [];
        /** @var UrlRewrite $url */
        foreach ($urls as $url) {
            if ($this->isCorrectUrl($url)) {
                $filteredUrls[] = $url;
            }
        }
        $data = [];
        foreach ($filteredUrls as $url) {
            foreach ([UrlRewrite::REQUEST_PATH, UrlRewrite::STORE_ID] as $key) {
                $fieldValue = $url->getByKey($key);
                if (!isset($data[$key]) || !in_array($fieldValue, $data[$key])) {
                    $data[$key][] = $fieldValue;
                }
            }
        }
        return $data ? $this->urlFinder->findAllByData($data) : [];
    }

    /**
     * @param UrlRewrite $url
     * @return bool
     */
    protected function isCorrectUrl(UrlRewrite $url)
    {
        $metadata = $url->getMetadata();
        return $url->getEntityType() == ProductUrlRewriteGenerator::ENTITY_TYPE
        && !empty($metadata['category_id'])
        && $url->getIsAutogenerated();
    }
}
