<?php
namespace SubsGuru\Payline\Mailer;

use App\Mailer\PaymentsNotificationsMailer;
use App\Model\Entity\Payment;
use App\Model\Entity\PaymentMean;
use App\Util\Localize;
use Cake\Core\Configure;

class PaylineNotificationsMailer extends PaymentsNotificationsMailer
{

    /**
     * Message sent when a payment hits the "error" status
     *
     * @param \App\Model\Entity\PaymentMean $paymentMean Instance of the payment mean the payment was made with.
     * @param \App\Model\Entity\Payment $payment Instance of the payment we are notifying about.
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