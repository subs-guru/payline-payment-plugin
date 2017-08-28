<?php
namespace SubsGuru\Payline\Payments\Gateway\Traits;

use App\Model\Entity\Payment;
use App\Model\Entity\PaymentMean;
use Commercie\Currency\ResourceRepository as Currency;
use Payline\PaylineSDK;

/**
 * Wallet methods.
 * All Payline/Wallet calls from gateway are done there.
 */
trait WalletTrait
{
    /**
     * Create a new Payline API instance from current payment mean configuration.
     *
     * @param App\Model\Entity\PaymentMean $paymentMean Related payment mean
     * @return Payline\PaylineSDK
     */
    public function sdk(PaymentMean $paymentMean)
    {
        $config = $this->getConfiguration()->getProperties();

        return new PaylineSDK(
            $config['merchant_id'],
            $config['api_key'],
            null,
            null,
            null,
            null,
            $config['environment']
        );
    }

    /**
     * Retrieve a Wallet from Payline.
     *
     * @param  PaylineSDK sdk SDK instance
     * @param  PaymentMean $paymentMean Related payment mean
     * @param  integer $cardIndex Card index if wallet have multiple cards
     * @param  string $version Version
     * @return array Payline response data array
     */
    public function getWallet(PaylineSDK $sdk, PaymentMean $paymentMean, $cardIndex = '', $version = '')
    {
        // Wallet final request
        $request = [
            'contractNumber' => $this->getConfiguration()->getProperty('contract_number'),
            'walletId' => $paymentMean->getParameter('wallet_id'),
            'cardInd' => $cardIndex,
            'version' => $version
        ];

        // Sending request to Payline...
        $response = $sdk->getWallet($request);
        $response['success'] = $response['result']['code'] == "02500";

        return $response;
    }

    /**
     * Create a Wallet into Payline.
     *
     * @param  PaylineSDK $sdk SDK instance
     * @param  PaymentMean $paymentMean Related payment mean
     * @param  string $walletID Wallet ID
     * @param  string $card Wallet card number
     * @param  string $cvv Card verification code
     * @param  string $expirationDate Card expiration date (format DDMM)
     * @return array Payline response data array
     */
    public function createWallet(PaylineSDK $sdk, PaymentMean $paymentMean, $walletID, $card, $cvv, $expirationDate)
    {
        $config = $this->getConfiguration()->getProperties();
        $params = $paymentMean->getParameters();
        $customer = $paymentMean->getCustomer();

        // Customer address
        $address = [
            'name' => $customer->org_business_name,
            'street1' => trim($customer->org_address_1),
            'street2' => trim($customer->org_address_2 . "\n" . $customer->org_address_3),
            'zipCode' => $customer->org_zip_code,
            'cityName' => $customer->org_city,
            'country' => $customer->org_country
            // 'phone' => $customer->contact_phone
        ];

        // Wallet final request
        $request = [
            'version' => "0",
            'contractNumber' => $config['contract_number'],
            'wallet' => [
                'walletId' => $walletID,
                'firstName' => $customer->contact_fname,
                'lastName' => $customer->contact_lname,
                'email' => $customer->contact_email,
                'comment' => "",
                'default' => "",
                'cardBrand' => "0"
            ],
            'address' => $address,
            'billingAddress' => $address,
            'ownerAddress' => $address,
            'card' => [
                'type' => 'CB',
                'number' => $card,
                'cvx' => $cvv,
                'expirationDate' => $expirationDate
            ],
            'buyer' => [
                'customerId' => $customer->id
            ]
        ];

        // Sending request to Payline...
        $response = $sdk->createWallet($request);
        $response['success'] = $response['result']['code'] == "02500";

        return $response;
    }

    /**
     * Create a new payment on a Payline Wallet.
     *
     * @param  PaylineSDK $sdk SDK instance
     * @param  Payment $payment Payment to process on Wallet
     * @param  bool $recurrent `true` if payment is recurring
     * @return array Payline response data array
     */
    public function createWalletPayment(PaylineSDK $sdk, Payment $payment, $recurrent, $cardIndex = '')
    {
        $currencyObj = (new Currency)->loadCurrency($payment->currency);
        $config = $this->getConfiguration()->getProperties();
        $parameters = $payment->payment_mean->getParameters();
        $customer = $payment->payment_mean->getCustomer();
        $amount = $payment->getAmount() * 100;

        // Invoice ID
        $sdk->addPrivateData(['key' => 'id', 'value' => $payment->getInvoiceId()]);

        // Transaction
        $transaction = [
            'version' => '0',
            // Payment
            'payment' => [
                'amount' => $amount,
                'currency' => (string)$currencyObj->getCurrencyNumber(),
                'action' => 101,
                'mode' => ($recurrent === true) ? static::PAYMENT_RECURRENT : static::PAYMENT_ONESHOT,
                'contractNumber' => $config['contract_number'],
                'differedActionDate' => '',
                'softDescriptor' => '',
                'cardBand' => '0'
            ],
            // Order
            'order' => [
                'ref' => $payment->id,
                'origin' => '1',
                'amount' => $amount,
                // 'taxes' => 0,
                'currency' => $currencyObj->getCurrencyNumber(),
                'country' => $customer->org_country,
                'date' => date('d/m/Y H:i') // $invoice->created
            ],
            // Buyer
            'buyer' => [
                'legalStatus' => '2',
                'customerId' => $customer->id,
                'firstName' => $customer->contact_fname,
                'lastName' => $customer->contact_lname,
                'email' => $customer->contact_email,
            ],
            // Wallet ID
            'walletId' => $parameters['wallet_id'],
            'walletCvx' => '',
            // cardInd
            'cardInd' => (!empty($cardIndex)) ? $cardIndex : $parameters['card_index']
        ];

        // Sending transaction to Payline...
        $response = $sdk->doImmediateWalletPayment($transaction);
        $response['success'] = $response['result']['code'] == "00000";

        return $response;
    }
}
