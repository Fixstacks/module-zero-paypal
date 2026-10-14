<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Paypal\Controller\Standard;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect as ResultRedirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Sends the customer to PayPal after Placeorder: reads the redirect URL that
 * Model\Standard::initialize() stored on the just-placed (pending_payment) order.
 *
 * @var \Magento\Framework\Controller\ResultFactory $resultFactory
 */
class Redirect implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * @return ResultRedirect
     */
    public function execute()
    {
        /** @var ResultRedirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $orderId = $this->checkoutSession->getLastRealOrder()->getEntityId();
        $order = $this->orderRepository->get($orderId);
        $paypalUrl = $order->getPayment()?->getAdditionalInformation('paypal_redirect_url');

        if (!$paypalUrl) {
            $resultRedirect->setPath('zero/checkout/index');

            return $resultRedirect;
        }

        $resultRedirect->setUrl($paypalUrl);

        return $resultRedirect;
    }
}
