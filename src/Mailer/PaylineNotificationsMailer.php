<?php
namespace SubsGuru\Payline\Mailer;

use SubsGuru\Core\Mailer\PaymentsNotificationsMailer;
use SubsGuru\Core\Model\Entity\Payment;
use SubsGuru\Core\Model\Entity\PaymentMean;
use SubsGuru\Core\Util\Localize;
use Cake\Core\Configure;

class PaylineNotificationsMailer extends PaymentsNotificationsMailer
{

    /**
     * Message sent when a payment hits the "error" status
     *
     * @param \SubsGuru\Core\Model\Entity\PaymentMean $paymentMean Instance of the payment mean the payment was made with.
     * @param \SubsGuru\Core\Model\Entity\Payment $payment Instance of the payment we are notifying about.
     * @return void
     */
    public function error(PaymentMean $paymentMean, Payment $payment)
    {
        $language = Localize::locale(true);

        $invoices = $payment->invoices;
        $this->attachInvoicesPaymentsDoc($invoices);

        $this ->defineBaseConfig($paymentMean, $payment);

        if ($payment->getAmount() > 0) {
            $this
                ->template('SubsGuru/Payline.' . $language . '/payments/error-payment', 'SubsGuru/Payline.' . $language . '/default')
                ->subject(__d('payments', 'Payment error for your subscription to {0}', Configure::read(CONFIG_KEY . '.service.name')));
        }
    }
}