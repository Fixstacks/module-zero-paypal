<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Paypal\Test\Unit\Model;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Paypal\Model\Standard;

/**
 * initialize() parks the order in pending_payment and builds the PayPal
 * redirect (_xclick) with the order's base total and the IPN/return URLs.
 */
#[AllowMockObjectsWithoutExpectations]
class StandardTest extends TestCase
{
    private ?string $redirectUrl = null;

    public function testBuildsSandboxRedirectAndParksOrder(): void
    {
        $method = $this->method('seller@example.com', true);
        $state = new DataObject();

        $method->initialize('authorize', $state);

        $this->assertSame(Order::STATE_PENDING_PAYMENT, $state->getState());
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $state->getStatus());
        $this->assertStringStartsWith('https://www.sandbox.paypal.com/cgi-bin/webscr?', $this->redirectUrl);

        parse_str((string) parse_url($this->redirectUrl, PHP_URL_QUERY), $params);
        $this->assertSame('_xclick', $params['cmd']);
        $this->assertSame('seller@example.com', $params['business']);
        $this->assertSame('42.50', $params['amount']);
        $this->assertSame('USD', $params['currency_code']);
        $this->assertSame('000000123', $params['invoice']);
        $this->assertSame('https://shop.test/paypal/ipn', $params['notify_url']);
        $this->assertSame('https://shop.test/zero_paypal/standard/cancel', $params['cancel_return']);
        $this->assertSame('buyer@example.com', $params['email']);
    }

    public function testLiveModeUsesLivePaypalHost(): void
    {
        $this->method('seller@example.com', false)->initialize('authorize', new DataObject());

        $this->assertStringStartsWith('https://www.paypal.com/cgi-bin/webscr?', $this->redirectUrl);
    }

    public function testRequiresBusinessAccount(): void
    {
        $this->expectExceptionMessage('business account email is required');

        $this->method('', true)->initialize('authorize', new DataObject());
    }

    private function method(string $businessAccount, bool $sandbox): Standard
    {
        (new ObjectManager($this))->prepareObjectManager([
            [DirectoryHelper::class, $this->createStub(DirectoryHelper::class)],
        ]);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['paypal/general/business_account', ScopeInterface::SCOPE_STORE, 1, $businessAccount],
            ['paypal/wpp/sandbox_flag', ScopeInterface::SCOPE_STORE, 1, $sandbox ? '1' : '0'],
        ]);
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(static fn (string $route) => 'https://shop.test/' . $route);

        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('000000123');
        $order->method('getBaseGrandTotal')->willReturn(42.5);
        $order->method('getBaseCurrencyCode')->willReturn('USD');
        $order->method('getCustomerEmail')->willReturn('buyer@example.com');

        $payment = $this->createMock(Payment::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('setAdditionalInformation')->willReturnCallback(function (string $key, $value) use ($payment) {
            if ($key === 'paypal_redirect_url') {
                $this->redirectUrl = $value;
            }
            return $payment;
        });

        /** @var Standard $method */
        $method = (new ObjectManager($this))->getObject(Standard::class, [
            'scopeConfig' => $scopeConfig,
            'urlBuilder' => $urlBuilder,
        ]);
        $method->setInfoInstance($payment);

        return $method;
    }
}
