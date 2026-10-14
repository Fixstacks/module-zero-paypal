<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Paypal\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Paypal\Model\Config;
use Magento\Store\Model\ScopeInterface;

/**
 * Lets stock PayPal IPN accept zero_paypal_standard orders: for unknown codes
 * isMethodActive() needs a hardcoded country list, so payments stayed
 * pending. Only those two answers change; IPN's checks (postback, receiver
 * email, amount) stay stock.
 */
class PaypalConfigRecognizeZeroStandard
{
    private const METHOD_CODE = 'zero_paypal_standard';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @param Config $subject
     * @param bool $result
     * @param string $method
     * @return bool
     */
    public function afterIsMethodActive(Config $subject, $result, $method)
    {
        return $method === self::METHOD_CODE ? $this->isActive($subject) : $result;
    }

    /**
     * @param Config $subject
     * @param bool $result
     * @param string|null $methodCode
     * @return bool
     */
    public function afterIsMethodAvailable(Config $subject, $result, $methodCode = null)
    {
        $code = $methodCode ?: $subject->getMethodCode();

        return $code === self::METHOD_CODE ? $this->isActive($subject) : $result;
    }

    private function isActive(Config $subject): bool
    {
        // Read straight from config in the instance's OWN store scope —
        // Ipn::_getConfig() builds it with the order's store id. Two things
        // that looked simpler don't work, confirmed locally: `$subject->
        // getValue('active')` maps `active` through stock's own per-method
        // path table and returns null for this code, and Config has no public
        // store-id getter, so its protected `_storeId` is read via a bound
        // closure (read-only, no behavior change).
        $storeId = \Closure::bind(fn () => $this->_storeId, $subject, $subject)();

        return $this->scopeConfig->isSetFlag(
            'payment/' . self::METHOD_CODE . '/active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
