<?php

use App\Payments\PaymentGatewayRepository;

//
// Registering Payline payment handler
//
PaymentGatewayRepository::add('SubsGuru\\Payline\\Payments\\Gateway\\PaylinePaymentGateway');
