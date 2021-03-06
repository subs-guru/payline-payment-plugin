<?php
    $amount = $payment->amount;
    if (!empty($payment->invoices)):
        $paymentInvoices = new \Cake\Collection\Collection($payment->invoices);
        $invoicesList = $paymentInvoices->extract(function($invoice) {
            return $invoice->full_number;
        })->toArray();
        $typeDocument = in_array($paymentInvoices->first()->type, \App\Model\Entity\Invoice::TYPES_CREDIT_NOTES) ? 'Avoir' : 'Facture';

        if ($typeDocument == 'Avoir') {
            $amount = $payment->amount * -1;
        }

        if (count($invoicesList) > 1) {
            $typeDocument .= 's';
        }

        $balanceDueInvoices = $this->Emails->getBalanceDue($payment);
    endif;
?>

Bonjour,

Nous venons de rencontrer une erreur sur un de vos paiements par carte bancaire pour votre abonnement à <?= \Cake\Core\Configure::read(CONFIG_KEY . '.service.name'); ?>.

Merci de nous contacter pour régulariser le probleme :
- par téléphone au 02 78 08 42 50 auprès de Barbara ou Sandrine
- par email en répondant à ce message (compta@spreadfamily.com)


Vous trouverez ci après le détail de votre paiement.

<?php if (isset($invoicesList)): ?>
    <?php echo $typeDocument; ?> : <?= \Cake\Utility\Text::toList($invoicesList); ?>
<?php endif; ?>

Montant : <?= \App\Util\Money::money($amount, $payment->currency); ?>

Moyen de paiement : <?= $payment->getPaymentGateway()->getBillingName(); ?>

<?php $lastPaymentStatus = end($payment->payment_statuses); ?>
Date : <?= $lastPaymentStatus->created; ?>

Motif : <?= nl2br($lastPaymentStatus->payment_mean_infos); ?>

<?php if (!empty($balanceDueInvoices)): ?>
    <?php foreach ($balanceDueInvoices as $invoiceNum => $balanceDue): ?>
Restant dû <?= $invoiceNum ?> : <?= \App\Util\Money::money($balanceDue, $payment->currency); ?>
    <?php endforeach; ?>
<?php endif; ?>