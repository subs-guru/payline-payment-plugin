<?php
namespace SubsGuru\Payline\Controller\Component;

use SubsGuru\Core\Model\Entity\PaymentMean;
use Cake\Controller\Component;
use Payline\PaylineSDK;

class WalletComponent extends Component
{
    public function get(PaymentMean $paymentMean, $cardIndex = 1, $version = '')
    {
        $gateway = $paymentMean->getPaymentGateway();
        $sdk = $gateway->sdk($paymentMean);

        return $gateway->getWallet($sdk, $paymentMean, $cardIndex = 1, $version = '');
    }
}
