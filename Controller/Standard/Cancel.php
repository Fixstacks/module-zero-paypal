<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Paypal\Controller\Standard;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Paypal\Helper\Checkout as PaypalCheckoutHelper;

/**
 * Return URL for a customer who cancels on PayPal: cancels the pending order and
 * reactivates its quote (stock Paypal\Helper\Checkout, as Hostedpro\Cancel does),
 * so the cart is back exactly as it was.
 */
class Cancel implements HttpGetActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly PaypalCheckoutHelper $paypalCheckoutHelper,
        private readonly MessageManager $messageManager
    ) {
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $this->paypalCheckoutHelper->cancelCurrentOrder((string) __('Cancelled by customer at PayPal.'));
        $this->paypalCheckoutHelper->restoreQuote();
        $this->messageManager->addNoticeMessage(
            __('Your PayPal payment was cancelled. Your cart is unchanged.')
        );

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('zero/checkout/index');

        return $resultRedirect;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
