<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Paypal\Test\Unit\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Paypal\Model\Config;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Paypal\Plugin\PaypalConfigRecognizeZeroStandard;

/**
 * Stock IPN only accepts methods PayPal's Config knows; the plugin answers for
 * zero_paypal_standard from its own `active` flag in the config's store.
 */
#[AllowMockObjectsWithoutExpectations]
class PaypalConfigRecognizeZeroStandardTest extends TestCase
{
    public function testZeroStandardActiveFollowsItsOwnFlagInConfigStore(): void
    {
        $plugin = $this->plugin([5 => true]);
        $subject = $this->subject(5);

        $this->assertTrue($plugin->afterIsMethodActive($subject, false, 'zero_paypal_standard'));
        $this->assertTrue($plugin->afterIsMethodAvailable($subject, false, 'zero_paypal_standard'));
    }

    public function testZeroStandardInactiveInAnotherStore(): void
    {
        $plugin = $this->plugin([5 => true]);
        $subject = $this->subject(6);

        $this->assertFalse($plugin->afterIsMethodActive($subject, true, 'zero_paypal_standard'));
    }

    public function testAvailabilityFallsBackToConfigMethodCode(): void
    {
        $plugin = $this->plugin([5 => true]);
        $subject = $this->subject(5, 'zero_paypal_standard');

        $this->assertTrue($plugin->afterIsMethodAvailable($subject, false));
    }

    public function testOtherMethodsKeepStockResult(): void
    {
        $plugin = $this->plugin([5 => true]);
        $subject = $this->subject(5, 'paypal_express');

        $this->assertFalse($plugin->afterIsMethodActive($subject, false, 'paypal_express'));
        $this->assertTrue($plugin->afterIsMethodAvailable($subject, true));
    }

    /**
     * @param array<int, bool> $activeByStore
     */
    private function plugin(array $activeByStore): PaypalConfigRecognizeZeroStandard
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path, string $scope, $storeId): bool =>
                $path === 'payment/zero_paypal_standard/active'
                && $scope === ScopeInterface::SCOPE_STORE
                && ($activeByStore[(int) $storeId] ?? false)
        );

        return new PaypalConfigRecognizeZeroStandard($scopeConfig);
    }

    private function subject(int $storeId, ?string $methodCode = null): Config
    {
        $subject = $this->createPartialMock(Config::class, ['getMethodCode']);
        $subject->method('getMethodCode')->willReturn($methodCode);
        $subject->setStoreId($storeId);

        return $subject;
    }
}
