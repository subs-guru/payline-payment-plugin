<?php
namespace SubsGuru\Payline\Controller;

use SubsGuru\Core\Controller\SubsGuruCoreController;

class AppController extends SubsGuruCoreController
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
