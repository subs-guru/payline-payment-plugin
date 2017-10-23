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
                        PaylineSDK::ENV_PROD => __d('SubsGuru/Payline', 'Production'),
                        PaylineSDK::ENV_PROD_CC => __d('SubsGuru/Payline', 'Production (certificat based)')
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
                    'placeholder' => null,
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => ['cc', 'all'],
                        'message' => 'Wrong credit card number'
                    ]
                ]
            ],
            'card_cvv' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', "Verification code"),
                    'type' => 'text',
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateCardCVV'],
                        'message' => __d('SubsGuru/Payline', 'Wrong card CVV format (3 digits)')
                    ]
                ]
            ],
            'card_exp' => [
                'field' => [
                    'label' => __d('SubsGuru/Payline', "Expiration date"),
                    'type' => 'text',
                    'pattern' => '([0-9]{2}\/[0-9]{2}|[0-9]{4})',
                    'placeholder' => __d('SubsGuru/Payline', 'format: MMYY'),
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateCardExpiration'],
                        'message' => __d('SubsGuru/Payline', 'Wrong card expiration format (MMYY)')
                    ]
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

    public function getCurrentServiceInformations(PaymentMean $paymentMean)
    {
        $walletId = $paymentMean->getParameter('wallet_id');

        if (empty($walletId)) {
            return [];
        }

        $sdk = $this->sdk($paymentMean);
        $response = $this->getWallet($sdk, $paymentMean);

        if ($response['success'] !== true) {
            return [];
        }

        // dump($response); die;

        $expirationDate = $response['wallet']['card']['expirationDate'];

        return [
            'card' => [
                'type' => 'string',
                'label' => __d('SubsGuru/Payline', "Card number"),
                'value' => $response['wallet']['card']['number']
            ],
            'expiration' => [
                'type' => 'string',
                'label' => __d('SubsGuru/Payline', "Expiration date"),
                'value' => substr($expirationDate, 0, 2) . '/20' . substr($expirationDate, 2)
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function isManualProcessing()
    {
        return false;
    }

    public function isRecoverable(Payment $payment)
    {
        if ($payment->hasError() !== true) {
            return false;
        }

        $status = $payment->getCurrentStatus();

        $recoverableCodes = [
            'XXXXX', // Unauthorized
            '01116', // Amount limit
            '01121', // Debit limit exceeded
            '01202', // Fraud suspected by bank
            '01907', // Card provider server error
            '01909', // Bank server Internal error
            '01912', // Card provider server unknown or unavailable
        ];

        $recoverableMessages = [
            "could not connect to host" // Problem when connecting to Payline server
        ];

        $response = $status->getExecutionInformations();

        return in_array($response['result']['code'], $recoverableCodes)
            || in_array(strtolower($response['result']['shortMessage']), $recoverableMessages);
    }

    /**
     * {@inheritDoc}
     */
    public function doPayment(Payment $payment, array $config, array $parameters, $amount, $currency, $recurrent = false)
    {
        $timezone = date_default_timezone_get();

        $sdk = $this->sdk($payment->payment_mean);
        $response = $this->createWalletPayment($sdk, $payment, $recurrent);

        $status = ($response['success'] === true)
            ? $this->getSuccessStatus()
            : $this->getErrorStatus();

        date_default_timezone_set($timezone);

        $payment->updateStatus($status, $response['result']['longMessage'], $response);
    }

    /**
     * {@inheritDoc}
     */
    public function doRecover(Payment $payment, array $config, array $params)
    {
        $this->doPayment($payment, $config, $params, $payment->getAmount(), $payment->getCurrency());
    }

    /**
     * {@inheritDoc}
     */
    public function onCreate(PaymentMean $paymentMean, array $form, array $options = [])
    {
        $timezone = date_default_timezone_get();

        if (!empty($options['wallet_id'])) {
            // Wallet token is provided, we don't create it.
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
                $this->parseCardExpirationDate($form['card_exp'])
            );

            if ($response['success'] !== true) {
                throw new PaymentGatewayException($response['result']['longMessage'] . ' (' . $response['result']['code'] . ')', [
                    'error_message' => $response['result']['longMessage'],
                    'error_code' => $response['result']['code']
                ]);
            }
        }

        date_default_timezone_set($timezone);

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

    /**
     * Check credit card CVV.
     * Format: "123"
     *
     * @param string $exp Card CVV value
     *
     * @return bool
     */
    public function validateCardCVV($exp)
    {
        return preg_match('/[0-9]{3}/', $exp) === 1;
    }

    /**
     * Check credit card expiration date.
     * Format : "MMYY" or "MM/YY"
     *
     * @param string $exp Card expiration date value
     *
     * @return bool
     */
    public function validateCardExpiration($exp)
    {
        return preg_match('/^([0-9]{2}\/[0-9]{2}|[0-9]{4})$/', $exp) === 1;
    }

    /**
     * Parse and reformat (if needed) an expiration date for a Payline credit card.
     *
     * @param string $exp Expiration date
     *
     * @return string Formatted expiration date
     */
    private function parseCardExpirationDate($exp)
    {
        // Format "MM/YY"
        if (preg_match('/^(?<MM>[0-9]+)\/(?<YY>[0-9]+)$/', $exp, $captures)) {
            return $captures['MM'] . $captures['YY'];
        }

        // Format "MMYY"
        return $exp;
    }
}
