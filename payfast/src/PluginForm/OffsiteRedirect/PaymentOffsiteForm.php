<?php

namespace Drupal\commerce_payfast\PluginForm\OffsiteRedirect;

use Drupal;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * PaymentOffsiteForm class
 */
class PaymentOffsiteForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface
{
    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('request_stack')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        /** @var PaymentInterface $payment */
        $payment = $this->entity;

        /** @var OffsitePaymentGatewayInterface $payment_gateway_plugin */
        $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

        $mode = $payment_gateway_plugin->getConfiguration()['mode'] == 'test' ? 'sandbox' : 'www';
        $url  = 'https://' . $mode . '.payfast.co.za/eng/process';

        $orderId = $payment->getOrderId();
        try {
            $payment->save();
        } catch (EntityStorageException $e) {
            Drupal::logger('your_module')->error(
                'Entity storage exception: @message',
                ['@message' => $e->getMessage()]
            );
        }

        $merchant_id  = $payment_gateway_plugin->getConfiguration()['merchant_id'];
        $merchant_key = $payment_gateway_plugin->getConfiguration()['merchant_key'];

        $order = Order::load($orderId);

        $data = [
            'merchant_id'  => $merchant_id,
            'merchant_key' => $merchant_key,
            'return_url'   => $form['#return_url'],
            'cancel_url'   => $form['#cancel_url'],
            'notify_url'   => $this->getSiteUrl() . '/payment/notify/payfast',
            'm_payment_id' => $payment->getOrderId(),
            'amount'       => number_format((float)$payment->getAmount()->getNumber(), 2, '.', ''),
            'item_name'    => 'Order ID: ' . $orderId,
            'custom_int1'  => $orderId,
            'custom_str1'  => 'PF_DRUPAL_9_COMMERCE_CORE_2.3.9',
        ];

        foreach ($order->getItems() as $order_item) {
            $product = $order_item->getPurchasedEntity()->getProduct();

            if (isset($product->field_subscription_type->value) && isset($product->field_recurring_amount->value)
                && isset($product->field_frequency->value) && isset($product->field_cycles->value)) {
                $data['custom_str2']       = gmdate('Y-m-d');
                $data['subscription_type'] = $product->field_subscription_type->value;
                $data['recurring_amount']  = number_format(
                    (float)sprintf('%.2f', $product->field_recurring_amount->value),
                    2,
                    '.',
                    ''
                );
                $data['frequency']         = $product->field_frequency->value;
                $data['cycles']            = $product->field_cycles->value;
            }
        }

        $pfOutput = '';
        // Create output string
        foreach ($data as $key => $value) {
            $pfOutput .= $key . '=' . urlencode(trim($value)) . '&';
        }
        $passPhrase = trim($payment_gateway_plugin->getConfiguration()['passphrase']);

        if (empty($passPhrase)) {
            $pfOutput = substr($pfOutput, 0, -1);
        } else {
            $pfOutput = $pfOutput . 'passphrase=' . urlencode($passPhrase);
        }

        $data['signature']  = md5($pfOutput);
        $data['user_agent'] = 'Drupal Commerce 2';

        try {
            $redirect_form = [];
            $redirect_form = $this->buildRedirectForm($form, $form_state, $url, $data, 'post');
        } catch (NeedsRedirectException $e) {
            Drupal::logger('your_module')->error(
                'Needs redirect exception: @message',
                ['@message' => $e->getMessage()]
            );
        }

        return $redirect_form;
    }

    public function getSiteUrl()
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->getSchemeAndHttpHost();
        }

        return '';
    }
}
