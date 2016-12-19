<?php
namespace SubsGuru\Payline\Payments\Gateway;

use App\Model\Entity\Payment;
use App\Model\Entity\PaymentMean;
use App\Model\Entity\PaymentMeanConfig;
use App\Payments\AbstractPaymentGateway;
use App\Payments\Exception\PaymentGatewayException;
use App\Payments\Exception\PaymentGatewayWarningException;
use Payline\PaylineSDK;

/**
 * Payline payment handler.
 *
 * @author Julien Cambien
 */
class PaylinePaymentGateway extends AbstractPaymentGateway
{
    use Traits\WalletTrait;

    /**
     * Payment type - Recurrent
     */
    const PAYMENT_RECURRENT = 'REC';

    /**
     * Payment type - One shot (once)
     */
    const PAYMENT_ONESHOT = 'CPT';

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'payline';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrettyName()
    {
        return __d('payment-payline', 'Payline');
    }

    /**
     * {@inheritDoc}
     */
    public function getShortDescriptionText()
    {
        return __d('payment-payline', 'Payline');
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigurationFields()
    {
        return [
            'merchant_id' => [
                'field' => [
                    'label' => __d('payment-payline', 'Merchant ID'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'api_key' => [
                'field' => [
                    'label' => __d('payment-payline', 'API key'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'contract_number' => [
                'field' => [
                    'label' => __d('payment-payline', 'Contract number'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'environment' => [
                'field' => [
                    'label' => __d('payment-payline', 'Environment'),
                    'type' => 'select',
                    'options' => [
                        PaylineSDK::ENV_HOMO => __d('payment-payline', 'Homologation (testing)'),
                        PaylineSDK::ENV_PROD => __d('payment-payline', 'Production')
                    ],
                    'required' => true
                ]
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getParametersFields()
    {
        return [
            'wallet_id' => [
                'field' => [
                    'label' => __d('payment-payline', "Wallet ID"),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'card_index' => [
                'field' => [
                    'label' => __d('payment-payline', "Card index"),
                    'type' => 'text',
                    'required' => false,
                    'default' => '',
                ],
                'help' => __d('payment-payline', 'Define the card index to use, default card will be used if blank.')
            ]
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Note :
     * We rewrite this form which by default take all customer parameters and ask for them.
     * For Payline case, we input credit card informations from user but we don't store them.
     * Instead, a Wallet token will be created and stored into parameters for future payments.
     */
    public function getFormFields()
    {
        return [
            'card_number' => [
                'field' => [
                    'label' => __d('payment-payline', "Card number"),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'card_cvv' => [
                'field' => [
                    'label' => __d('payment-payline', "Verification code"),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'card_exp' => [
                'field' => [
                    'label' => __d('payment-payline', "Expiration date"),
                    'type' => 'text',
                    'required' => true
                ]
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function validateParameters(array $parameters)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(array $config)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getIntermediateStatuses()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getPossibleActions()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function isManualProcessing()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function doPayment(Payment $payment, array $config, array $parameters, $amount, $currency, $recurrent = false)
    {
        $sdk = $this->sdk($payment->payment_mean);
        $response = $this->createWalletPayment($sdk, $payment, $recurrent);

        $status = ($response['success'] === true)
            ? $this->getSuccessStatus()
            : $this->getErrorStatus();

        $payment->updateStatus($status, $response['result']['longMessage'], $response);
    }

    /**
     * {@inheritDoc}
     */
    public function onCreate(PaymentMean $paymentMean, array $form)
    {
        $walletID = $paymentMean->getCustomer()->id;
        $sdk = $this->sdk($paymentMean);

        // Create Wallet
        $response = $this->createWallet(
            $sdk,
            $paymentMean,
            $walletID,
            $form['card_number'],
            $form['card_cvv'],
            $form['card_exp']
        );

        if ($response['success'] !== true) {
            throw new PaymentGatewayException($response['result']['longMessage'], ['error_message' => $response['result']['longMessage'], 'error_code' => $response['result']['code']]);
        }

        $paymentMean->setParameter('wallet_id', $walletID);
        $paymentMean->setParameter('card_index', '');
    }

    /**
     * {@inheritDoc}
     */
    public function onConfigure(PaymentMeanConfig $config)
    {
        //@TODO Should we check the given Payline account and throw an `PaymentGatewayWarningException` if token is wrong ?
    }

    /**
     * {@inheritDoc}
     */
    public function onParameterize(PaymentMean $paymentMean)
    {
        //@TODO Should we check the given token to Payline and throw an `PaymentGatewayWarningException` if token is wrong ?
    }
}
