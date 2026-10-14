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

/**
 * PayPal's return URL: only redirects to the success page (as Hostedpro\
 * ReturnAction does). Nothing is confirmed here — the customer may never come
 * back; stock paypal/ipn is the only thing that marks the order paid.
 * CsrfAwareActionInterface because PayPal may return with GET or POST.
 */
class ReturnAction implements HttpGetActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory
    ) {
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('checkout/onepage/success');

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
