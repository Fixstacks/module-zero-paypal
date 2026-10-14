<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Paypal\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;

/**
 * Classic PayPal redirect (zero_paypal_standard), business email only:
 * initialize() leaves the order pending_payment with a redirect to _xclick;
 * stock IPN marks it paid (Plugin\PaypalConfigRecognizeZeroStandard). One
 * aggregate line, as the IPN's mc_gross must equal base_total_due exactly.
 *
 * @var \Magento\Framework\App\Config\ScopeConfigInterface $_scopeConfig inherited from AbstractMethod
 */
class Standard extends AbstractMethod
{
    /**
     * @var string
     */
    protected $_code = 'zero_paypal_standard';

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * PayPal's classic redirect endpoints (unrelated to the IPN postback URL,
     * which stock AbstractIpn handles).
     */
    private const CGI_URL_LIVE = 'https://www.paypal.com/cgi-bin/webscr';
    private const CGI_URL_SANDBOX = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param PaymentData $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param UrlInterface $urlBuilder
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentData $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        private readonly UrlInterface $urlBuilder,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @param string $paymentAction
     * @param object $stateObject
     * @return void
     * @throws LocalizedException
     */
    public function initialize($paymentAction, $stateObject): void
    {
        $payment = $this->getInfoInstance();
        /** @var Order $order */
        $order = $payment->getOrder();

        $businessAccount = (string) $this->_scopeConfig->getValue(
            'paypal/general/business_account',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
        if ($businessAccount === '') {
            throw new LocalizedException(
                __('PayPal is not fully configured — a business account email is required.')
            );
        }

        $order->setCanSendNewEmailFlag(false);
        $payment->setAmountAuthorized($order->getTotalDue());
        $payment->setBaseAmountAuthorized($order->getBaseTotalDue());

        $redirectUrl = $this->buildRedirectUrl($order, $businessAccount);
        $payment->setAdditionalInformation('paypal_redirect_url', $redirectUrl);

        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    /**
     * @param Order $order
     * @param string $businessAccount
     * @return string
     */
    private function buildRedirectUrl(Order $order, string $businessAccount): string
    {
        $sandbox = (bool) $this->_scopeConfig->getValue(
            'paypal/wpp/sandbox_flag',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );

        $params = [
            'cmd' => '_xclick',
            'business' => $businessAccount,
            'item_name' => (string) __('Order #%1', $order->getIncrementId()),
            'amount' => $this->formatAmount((float) $order->getBaseGrandTotal()),
            'currency_code' => (string) $order->getBaseCurrencyCode(),
            'invoice' => (string) $order->getIncrementId(),
            'no_shipping' => '1',
            'no_note' => '1',
            'charset' => 'utf-8',
            'notify_url' => $this->urlBuilder->getUrl('paypal/ipn', ['_secure' => true]),
            'return' => $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true]),
            'cancel_return' => $this->urlBuilder->getUrl('zero_paypal/standard/cancel', ['_secure' => true]),
        ];

        $email = $order->getCustomerEmail();
        if ($email) {
            $params['email'] = $email;
        }

        return ($sandbox ? self::CGI_URL_SANDBOX : self::CGI_URL_LIVE) . '?' . http_build_query($params);
    }

    /**
     * Amount as a plain "1234.56" string.
     *
     * @param float $amount
     * @return string
     */
    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @inheritDoc
     */
    public function validate(): self
    {
        return $this;
    }
}
