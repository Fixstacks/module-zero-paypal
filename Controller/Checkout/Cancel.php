<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Paypal\Controller\Checkout;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Zero\Checkout\Model\RedirectPayment\ReturnResult;
use Zero\Paypal\Model\Checkout\OrderUpdater;
use Zero\Paypal\Model\Checkout\Payment;

/**
 * PayPal Checkout's cancel URL (?order=<increment id>): the buyer cancelled on
 * PayPal. Nothing was taken; the order is cancelled and the cart restored.
 */
class Cancel implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ReturnResult $returnResult,
        private readonly OrderUpdater $orderUpdater
    ) {
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $order = $this->returnResult->lastOrder(Payment::CODE, (string) $this->request->getParam('order'));
        if (!$order) {
            return $this->returnResult->elsewhere();
        }
        $this->orderUpdater->cancel($order);

        return $this->returnResult->redirect(ReturnResult::FAILED, 'PayPal');
    }
}
