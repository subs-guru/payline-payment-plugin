<?php

use App\Payments\PaymentGatewayRepository;

//
// Registering Payline payment handler
//
PaymentGatewayRepository::addHandler('SubsGuru\\Payline\\Payments\\Gateway\\PaylinePaymentGateway');
