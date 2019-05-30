<?php

namespace Dotdigitalgroup\Email\Model\Connector;

/**
 * Transactional data for catalog products to sync.
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Product
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $sku = '';

    /**
     * @var string
     */
    public $status = '';

    /**
     * @var string
     */
    public $visibility = '';

    /**
     * @var float
     */
    public $price = 0;

    /**
     * @var float
     */
    public $specialPrice = 0;

    /**
     * @var array
     */
    public $categories = [];

    /**
     * @var string
     */
    public $url = '';

    /**
     * @var string
     */
    public $imagePath = '';

    /**
     * @var string
     */
    public $shortDescription = '';

    /**
     * @var float
     */
    public $stock = 0;

    /**
     * @var array
     */
    public $websites = [];

    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    public $helper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\StatusFactory
     */
    public $statusFactory;

    /**
     * @var \Magento\Catalog\Model\Product\VisibilityFactory
     */
    public $visibilityFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Media\ConfigFactory
     */
    public $mediaConfigFactory;

    /**
     * @var \Magento\CatalogInventory\Model\Stock\ItemFactory
     */
    public $itemFactory;

    /**
     * @var \Magento\Framework\Stdlib\StringUtils
     */
    private $stringUtils;

    /**
     * @var \Dotdigitalgroup\Email\Model\Catalog\UrlFinder
     */
    private $urlFinder;

    /**
     * @var \Magento\CatalogInventory\Api\StockStateInterface
     */
    private $stockStateInterface;

    /**
     * @var KeyValidator
     */
    private $validator;

    /**
     * Product constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     * @param \Magento\Catalog\Model\Product\Media\ConfigFactory $mediaConfigFactory
     * @param \Magento\Catalog\Model\Product\Attribute\Source\StatusFactory $statusFactory
     * @param \Magento\Catalog\Model\Product\VisibilityFactory $visibilityFactory
     * @param \Magento\Framework\Stdlib\StringUtils $stringUtils
     * @param \Dotdigitalgroup\Email\Model\Catalog\UrlFinder $urlFinder
     * @param \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface
     * @param KeyValidator $validator
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Magento\Catalog\Model\Product\Media\ConfigFactory $mediaConfigFactory,
        \Magento\Catalog\Model\Product\Attribute\Source\StatusFactory $statusFactory,
        \Magento\Catalog\Model\Product\VisibilityFactory $visibilityFactory,
        \Magento\Framework\Stdlib\StringUtils $stringUtils,
        \Dotdigitalgroup\Email\Model\Catalog\UrlFinder $urlFinder,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        KeyValidator $validator
    ) {
        $this->mediaConfigFactory = $mediaConfigFactory;
        $this->visibilityFactory  = $visibilityFactory;
        $this->statusFactory      = $statusFactory;
        $this->helper             = $helper;
        $this->storeManager       = $storeManagerInterface;
        $this->stringUtils        = $stringUtils;
        $this->urlFinder          = $urlFinder;
        $this->stockStateInterface = $stockStateInterface;
        $this->validator = $validator;
    }

    /**
     * Set the product data.
     *
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return $this
     */
    public function setProduct($product)
    {
        $this->id = $product->getId();
        $this->sku = $product->getSku();
        $this->name = $product->getName();

        $this->status = $this->statusFactory->create()
            ->getOptionText($product->getStatus());

        $options = $this->visibilityFactory->create()
            ->getOptionArray();
        $this->visibility = (string)$options[$product->getVisibility()];

        $this->getMinPrices($product);

        $this->url = $this->urlFinder->fetchFor($product);

        $this->imagePath = $this->mediaConfigFactory->create()
            ->getMediaUrl($product->getSmallImage());

        $this->stock = (float)number_format($this->getStockQty($product), 2, '.', '');

        $shortDescription = $product->getShortDescription();
        //limit short description
        if ($this->stringUtils->strlen($shortDescription) > \Dotdigitalgroup\Email\Helper\Data::DM_FIELD_LIMIT) {
            $shortDescription = mb_substr($shortDescription, 0, \Dotdigitalgroup\Email\Helper\Data::DM_FIELD_LIMIT);
        }

        $this->shortDescription = $shortDescription;

        //category data
        $count = 0;
        $categoryCollection = $product->getCategoryCollection()
            ->addNameToResult();
        foreach ($categoryCollection as $cat) {
            $this->categories[$count]['Id'] = $cat->getId();
            $this->categories[$count]['Name'] = $cat->getName();
            ++$count;
        }

        //website data
        $count = 0;
        $websiteIds = $product->getWebsiteIds();
        foreach ($websiteIds as $websiteId) {
            $website = $this->storeManager->getWebsite(
                $websiteId
            );
            $this->websites[$count]['Id'] = $website->getId();
            $this->websites[$count]['Name'] = $website->getName();
            ++$count;
        }

        $this->processProductOptions($product);

        unset(
            $this->itemFactory,
            $this->mediaConfigFactory,
            $this->visibilityFactory,
            $this->statusFactory,
            $this->helper,
            $this->storeManager
        );

        return $this;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * This function calculates the stock Quantity for each Product.
     * @return float
     */
    private function getStockQty($product)
    {
        return $this->stockStateInterface->getStockQty($product->getId(), $product->getStore()->getWebsiteId());
    }

    /**
     * @param mixed $product
     *
     * @return null
     */
    private function processProductOptions($product)
    {
        //bundle product options
        if ($product->getTypeId()
            == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE
        ) {
            $optionCollection = $product->getTypeInstance()
                ->getOptionsCollection($product);
            $selectionCollection = $product->getTypeInstance()
                ->getSelectionsCollection(
                    $product->getTypeInstance()->getOptionsIds($product),
                    $product
                );
            $options = $optionCollection->appendSelections(
                $selectionCollection
            );
            foreach ($options as $option) {
                $trimmedTitle = $this->validator->cleanLabel(
                    $option->getDefaultTitle(),
                    '',
                    $option->getId()
                );
                if (empty($trimmedTitle)) {
                    continue;
                }

                $count = 0;
                $selections = $option->getSelections();
                $sOptions = [];
                foreach ($selections as $selection) {
                    $sOptions[$count]['name'] = $selection->getName();
                    $sOptions[$count]['sku'] = $selection->getSku();
                    $sOptions[$count]['id'] = $selection->getProductId();
                    $sOptions[$count]['price'] = (float)number_format(
                        $selection->getPrice(),
                        2,
                        '.',
                        ''
                    );
                    ++$count;
                }
                $this->$trimmedTitle = $sOptions;
            }
        }

        //configurable product options
        if ($product->getTypeId() == 'configurable') {
            $productAttributeOptions = $product->getTypeInstance()
                ->getConfigurableAttributesAsArray($product);

            foreach ($productAttributeOptions as $productAttribute) {
                $trimmedLabel = $this->validator->cleanLabel(
                    $productAttribute['label'],
                    '',
                    $productAttribute['id']
                );
                if (empty($trimmedLabel)) {
                    continue;
                }

                $count = 0;
                $options = [];
                foreach ($productAttribute['values'] as $attribute) {
                    $options[$count]['option'] = $attribute['default_label'];
                    if (isset($attribute['pricing_value'])) {
                        $options[$count]['price'] = (float)number_format(
                            $attribute['pricing_value'],
                            2,
                            '.',
                            ''
                        );
                    }
                    ++$count;
                }
                $this->$trimmedLabel = $options;
            }
        }
    }

    /**
     * Exposes the class as an array of objects.
     *
     * @return array
     */
    public function expose()
    {
        return array_diff_key(
            get_object_vars($this),
            array_flip([
                'storeManager',
                'helper',
                'itemFactory',
                'mediaConfigFactory',
                'visibilityFactory',
                'statusFactory',
                'storeManager',
                'urlFinder',
                'stringUtils',
                'stockStateInterface',
                'validator'
            ])
        );
    }

    /**
     * Set the Minimum Prices for Configurable and Bundle products.
     *
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return null
     */

    private function getMinPrices($product)
    {
        if ($product->getTypeId() == 'configurable') {
            foreach ($product->getTypeInstance()->getUsedProducts($product) as $childProduct) {
                $childPrices[] = $childProduct->getPrice();
                if ($childProduct->getSpecialPrice() !== null) {
                    $childSpecialPrices[] = $childProduct->getSpecialPrice();
                }
            }
            $this->price = isset($childPrices) ? min($childPrices) : null;
            $this->specialPrice = isset($childSpecialPrices) ? min($childSpecialPrices) : null;
        } elseif ($product->getTypeId() == 'bundle') {
            $this->price = $product->getPriceInfo()->getPrice('regular_price')->getMinimalPrice()->getValue();
            $this->specialPrice = $product->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue();
            //if special price equals to price then its wrong.)
            $this->specialPrice = ($this->specialPrice === $this->price) ? null : $this->specialPrice;
        } elseif ($product->getTypeId() == 'grouped') {
            foreach ($product->getTypeInstance()->getAssociatedProducts($product) as $childProduct) {
                $childPrices[] = $childProduct->getPrice();
                if ($childProduct->getSpecialPrice() !== null) {
                    $childSpecialPrices[] = $childProduct->getSpecialPrice();
                }
            }
            $this->price = isset($childPrices) ? min($childPrices) : null;
            $this->specialPrice = isset($childSpecialPrices) ? min($childSpecialPrices) : null;
        } else {
            $this->price = $product->getPrice();
            $this->specialPrice = $product->getSpecialPrice();
        }
        $this->formatPriceValues();
    }

    /**
     * Formats the price values.
     *
     * @return null
     */

    private function formatPriceValues()
    {
        $this->price = (float)number_format(
            $this->price,
            2,
            '.',
            ''
        );

        $this->specialPrice = (float)number_format(
            $this->specialPrice,
            2,
            '.',
            ''
        );
    }
}
