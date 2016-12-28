<?php
namespace SubsGuru\Payline\Payments\Gateway;

use App\Model\Entity\Payment;
use App\Model\Entity\PaymentMean;
use App\Model\Entity\PaymentMeanConfig;
use App\Payments\AbstractPaymentGateway;
use App\Payments\Exception\PaymentGatewayException;
use App\Payments\Exception\PaymentGatewayWarningException;
use Cake\Utility\Text;
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
    public static function getName()
    {
        return 'payline';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrettyName()
    {
        return __d('SubsGuru/Payline', 'Payline');
    }

    /**
     * {@inheritDoc}
     */
    public function getShortDescriptionText()
    {
        return __d('SubsGuru/Payline', 'Payline');
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigurationFields()
    {
        return [
            'merchant_id' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', 'Merchant ID'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'api_key' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', 'API key'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'contract_number' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', 'Contract number'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'environment' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', 'Environment'),
                    'type' => 'select',
                    'options' => [
                        PaylineSDK::ENV_HOMO => __d('SubsGuru/Payline', 'Homologation (testing)'),
                        PaylineSDK::ENV_PROD => __d('SubsGuru/Payline', 'Production')
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
                    'label' => __d('SubsGuru/Payline', "Wallet ID"),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'card_index' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', "Card index"),
                    'type' => 'text',
                    'required' => false,
                    'default' => '',
                ],
                'help' => __d('SubsGuru/Payline', 'Define the card index to use, default card will be used if blank.')
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
                    'label' => __d('SubsGuru/Payline', "Card number"),
                    'type' => 'text',
                    'placeholder' => 'ex: 4929550861981029',
                    'required' => true
                ]
            ],
            'card_cvv' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', "Verification code"),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'card_exp' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', "Expiration date"),
                    'type' => 'text',
                    'placeholder' => __d('SubsGuru/Payline', 'format: MMYY'),
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
    public function onCreate(PaymentMean $paymentMean, array $form, array $options = [])
    {
        if (!empty($options['wallet_id'])) {
            // Wallet token is provided, we didn't create it.
            $walletID = $options['wallet_id'];
            $cardIndex = (!empty($options['card_index'])) ? $options['card_index'] : '';
        } else {
            $walletID = Text::uuid();
            $cardIndex = '';

            // Create Wallet
            $response = $this->createWallet(
                $this->sdk($paymentMean),
                $paymentMean,
                $walletID,
                $form['card_number'],
                $form['card_cvv'],
                $form['card_exp']
            );

            if ($response['success'] !== true) {
                throw new PaymentGatewayException($response['result']['longMessage'], ['error_message' => $response['result']['longMessage'], 'error_code' => $response['result']['code']]);
            }
        }

        $paymentMean->setParameter('wallet_id', $walletID);
        $paymentMean->setParameter('card_index', $cardIndex);
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
