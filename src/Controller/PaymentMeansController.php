<?php
namespace SubsGuru\Payline\Controller;

use App\Controller\Api\ApiController;
use Cake\ORM\TableRegistry;

class PaymentMeansController extends ApiController
{
    public $components = ['PaymentMeanManagement'];

    public function createFromToken()
    {
        $this->request->allowMethod(['post']);

        if (empty($this->request->data['token'])) {
            return $this->respond("No Payline token given", [], 400);
        }

        $token = $this->request->data['token'];

        $cardIndex = (!empty($this->request->data['card_index']))
            ? $this->request->data['card_index']
            : null;

        try {
            $paymentMean = $this->PaymentMeanManagement->create(
                array_merge($this->request->data, [
                    'type' => 'payline',
                    'params' => [
                        'card_number' => '',
                        'card_cvv' => '',
                        'card_exp' => ''
                    ]
                ]),
                [
                    'wallet_id' => $token,
                    'card_index' => $cardIndex
                ]
            );
            $code = 200;
            $message = "Created successfully";
            $data = ['payment_mean_id' => $paymentMean->id];
        } catch(PaymentMeanException $e) {
            $code = 400;
            $message = $e->getMessage();
            $data = $e->getData();
        } catch(PaymentGatewayException $e) {
            $code = 400;
            $message = "Gateway error : " . $e->getMessage();
            $data = $e->getData();
        } catch(Exception $e) {
            $code = 500;
            $message = "Internal server error : " . $e->getMessage();
            $data = ['type' => get_class($e)];
        }

        return $this->respond($message, $data, $code);
    }
}
