<?php
namespace SubsGuru\Payline\Controller;

use App\Controller\AppController as BaseController;

class AppController extends BaseController
{
    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('SubsGuru/Payline.Wallet');
    }
}
