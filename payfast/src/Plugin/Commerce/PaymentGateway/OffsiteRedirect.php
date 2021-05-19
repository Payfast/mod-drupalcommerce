<?php
namespace Drupal\commerce_payfast\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\Controller;
use Drupal\commerce_payment;

/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "payfast",
 *   label = "PayFast",
 *   display_label = "PayFast",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_payfast\PluginForm\OffsiteRedirect\PaymentOffsiteForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class OffsiteRedirect extends OffsitePaymentGatewayBase {

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
        return [
            'redirect_method' => 'post',
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['merchant_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant ID'),
            '#default_value' => $this->configuration['merchant_id'],
            '#required' => TRUE,
        ];
        $form['merchant_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant Key'),
            '#default_value' => $this->configuration['merchant_key'],
            '#required' => TRUE,
        ];
        $form['passphrase'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Passphrase'),
            '#default_value' => $this->configuration['passphrase'],
        ];
      $form['debug'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Debug'),
        '#default_value' => $this->configuration['debug'],
      ];
        $this->entityTypeManager;

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['merchant_id'] = $values['merchant_id'];
            $this->configuration['merchant_key'] = $values['merchant_key'];
            $this->configuration['passphrase'] = $values['passphrase'];
          $this->configuration['debug'] = $values['debug'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request) {
      \Drupal::messenger()->addMessage('Thank you for placing your order');
    }

    /**
     * Get data array from a request content.
     *
     * @param string $request_content
     *   The Request content.
     *
     * @return array
     *   The request data array.
     */
    protected function getRequestDataArray($request_content) {
        parse_str(html_entity_decode($request_content), $itn_data);
        return $itn_data;
    }

    protected function loadPaymentByRemoteId($remote_id) {
        /** @var \Drupal\commerce_payment\PaymentStorage $storage */
        $storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payment_by_remote_id = $storage->loadByProperties(['order_id' => $remote_id]);
        return reset($payment_by_remote_id);
    }

    public function onNotify(Request $request) {
        require_once('payfast_common.inc');

        // Variable Initialization
        $pfError = false;
        $pfErrMsg = '';
        $pfData = array();
        $pfHost = (($this->getConfiguration()['mode'] == 'test') ? 'sandbox' : 'www') . '.payfast.co.za';
        $pfParamString = '';

        pflog('PayFast ITN call received');

//// Notify PayFast that information has been received
        if (!$pfError) {
            header('HTTP/1.0 200 OK');
            flush();
        }

//// Get data sent by PayFast
        if (!$pfError) {
            pflog('Get posted data');

            // Posted variables from ITN
            $pfData = pfGetData();

            pflog('PayFast Data: ' . print_r($pfData, true));

            if ($pfData === false) {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

//// Verify security signature
        if (!$pfError) {
            pflog('Verify security signature');

            if ($this->getConfiguration()['passphrase'] === '') {
                $passphrase = null;
            }
            else {
                $passphrase = $this->getConfiguration()['passphrase'];
            }
            
            // If signature different, log for debugging
            if (!pfValidSignature($pfData, $pfParamString, $passphrase)) {
                $pfError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }

//// Due to the increasing popularity of cloud hosting this Security check has been removed
//// Verify source IP (If not in debug mode)
//        if (!$pfError) {
//            pflog('Verify source IP');
//
//            if (!pfValidIP($_SERVER['REMOTE_ADDR'])) {
//                $pfError = true;
//                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
//            }
//        }

//// Verify data received
        if (!$pfError) {
            pflog('Verify data received');

            $pfValid = pfValidData($pfHost, $pfParamString);

            if (!$pfValid) {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

//// Check status and update order
        if (!$pfError) {

            pflog('Check status and update order');

            if ($pfData['payment_status'] == 'COMPLETE') {

              $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
              $payment = $payment_storage->create([
                'state' => 'completed',
                'amount' => $pfData['amount_gross'],
                'payment_gateway' => $this->parentEntity->id(),
                'order_id' => $pfData['custom_int1'],
                'remote_id' => $pfData['pf_payment_id'],
                'remote_state' =>  $pfData['payment_status'],
              ]);
              $payment->setRefundedAmount(new Price(0.00, 'ZAR'));
              $payment->setAmount(new Price($pfData['amount_gross'], 'ZAR'));
              $payment->save();
            }
        }

        if ($pfError) {
            pflog('Error: ' . $pfErrMsg);
        }
    }
}
