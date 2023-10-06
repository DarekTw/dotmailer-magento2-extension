<?php

namespace Dotdigitalgroup\Email\Observer\Html;

/**
 *
 * Adds extra columns to the Manage Coupon Codes table of a sales rule.
 */
class BeforeBlockToHtml implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Dotdigitalgroup\Email\Model\Sales\CouponGridFiltererFactory
     */
    private $couponGridFiltererFactory;

    /**
     * Constructor.
     *
     * @param \Dotdigitalgroup\Email\Model\Sales\CouponGridFiltererFactory $couponGridFiltererFactory
     */
    public function __construct(
        \Dotdigitalgroup\Email\Model\Sales\CouponGridFiltererFactory $couponGridFiltererFactory
    ) {
        $this->couponGridFiltererFactory = $couponGridFiltererFactory;
    }

    /**
     * Execute.
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $grid = $observer->getBlock();

        /**
         * \Magento\SalesRule\Block\Adminhtml\Promo\Quote\Edit\Tab\Coupons\Grid
         */
        if ($grid instanceof \Magento\SalesRule\Block\Adminhtml\Promo\Quote\Edit\Tab\Coupons\Grid) {
            $grid->addColumnAfter(
                'generated_by_dotdigital',
                [
                    'header' => __('Generated by dotdigital'),
                    'index' => 'generated_by_dotmailer',
                    'type' => 'options',
                    'default' => '',
                    'options' => ['null' => 'No', '1' => 'Yes'],
                    'width' => '30',
                    'align' => 'center',
                    'filter_condition_callback' => [
                        $this->couponGridFiltererFactory->create(),
                        'filterByGeneratedByDotdigital'
                    ]
                ],
                'created_at'
            )->addColumnAfter(
                'generated_for_email',
                [
                    'header' => __('Email'),
                    'index' => 'email',
                    'default' => '',
                    'width' => '30',
                    'align' => 'center',
                    'filter_condition_callback' => [
                        $this->couponGridFiltererFactory->create(),
                        'filterGeneratedForEmail'
                    ]
                ],
                'generated_by_dotdigital'
            )->addColumnAfter(
                'expires_at',
                [
                    'header' => __('Expires At'),
                    'index' => 'expires_at',
                    'type' => 'datetime',
                    'default' => '',
                    'width' => 30,
                    'align' => 'center',
                ],
                'generated_for_email'
            );
        }
    }
}
