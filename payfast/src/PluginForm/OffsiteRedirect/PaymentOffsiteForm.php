<?php
/**Copyright (c) 2008 PayFast (Pty) Ltd
You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 */
namespace Drupal\commerce_payfast\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;


class PaymentOffsiteForm extends BasePaymentOffsiteForm {

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);

        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;

        /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
        $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

        $mode = $payment_gateway_plugin->getConfiguration()['mode'] == 'test' ? 'sandbox' : 'www';
        $url = 'https://' . $mode . '.payfast.co.za/eng/process';

        $protocol = $_SERVER['SERVER_PROTOCOL'];
        $protocolString = strtolower(substr($protocol, 0, strpos($protocol, '/')));
        $notifyUrl = $protocolString . '://' . $_SERVER['SERVER_NAME'] . '/payment/notify/payfast';

        $remote_id = rand(99, 9999999);

        $payment->setRemoteId($remote_id);
        $payment->save();

        $merchant_id = $payment_gateway_plugin->getConfiguration()['mode'] == 'test' ? '10000100' : $payment_gateway_plugin->getConfiguration()['merchant_id'];
        $merchant_key = $payment_gateway_plugin->getConfiguration()['mode'] == 'test' ? '46f0cd694581a' : $payment_gateway_plugin->getConfiguration()['merchant_key'];
        $data = [
            'merchant_id' => $merchant_id,
            'merchant_key' => $merchant_key,
            'return_url' => $form['#return_url'],
            'cancel_url' => $form['#cancel_url'],
            'notify_url' => $notifyUrl,
            'm_payment_id' => $payment->getOrderId(),
            'amount' => number_format( sprintf( "%.2f", $payment->getAmount()->getNumber() ), 2, '.', '' ),
            'item_name' => 'Order ID: ' . $payment->getOrderId(),
            'custom_int1' => $remote_id
        ];

        $pfOutput = '';
        // Create output string
        foreach( $data as $key => $value )
        {
            $pfOutput .= $key . '=' . urlencode( trim( $value ) ) . '&';
        }
        $passPhrase = trim( $payment_gateway_plugin->getConfiguration()['passphrase'] );

        if ( empty( $passPhrase ) || $payment_gateway_plugin->getConfiguration()['mode'] == 'test' )
        {
            $pfOutput = substr( $pfOutput, 0, -1 );
        }
        else
        {
            $pfOutput = $pfOutput."passphrase=".urlencode( $passPhrase );
        }

        $data['signature'] = md5( $pfOutput );
        $data['user_agent'] = 'Drupal Commerce 2';

        return $this->buildRedirectForm($form, $form_state, $url, $data, 'post');
    }

}
