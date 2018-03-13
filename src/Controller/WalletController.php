<?php
namespace SubsGuru\Payline\Controller;

use Cake\ORM\TableRegistry;
use SubsGuru\Payline\Controller\AppController;

class WalletController extends AppController
{
    public function manage($id)
    {
        $paymentMean = TableRegistry::get('SubsGuru/Core.PaymentMeans')->get($id);
        $response = $this->Wallet->get($paymentMean);

        $this->set('paymentMean', $paymentMean);
        $this->set('response', $response);
    }
}
