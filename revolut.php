<?php
class Revolut extends NonmerchantGateway {
    private $meta;
    public function __construct() {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
        Loader::loadComponents($this, ['Input']);
        Loader::loadHelpers($this, ['Html']);
    }

    public function setMeta(array $meta = null) {
        $this->meta = $meta;
    }

    public function getName() {
        return "Revolut";
    }

    public function getVersion() {
        return "0.1";
    }

    public function getAuthors() {
        return "WhiteHat";
    }

    public function getSettings(array $meta = null) {
        return [];
    }

    public function editSettings(array $meta) {
        $rules = [
            'merchant_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'message' => 'Code empty'
                ]
            ]
        ];
        $this->Input->setRules($rules);
        $this->Input->validates($meta);
        return $meta;
    }

    public function encryptableFields() {
        return ['merchant_key'];
    }

    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null) {
        $amount = round($amount, 2);
        Loader::loadModels($this, ['Contacts']);

        $params = [
            'amount' => $amount.'00',
            'currency' => $this->currency,
            'description' => $options['description'] ?? 'Payment',
            'redirect_url' => Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/revolut/?client_id='. ($contact_info['client_id'] ?? null) .'&amount='. ($amount ?? null)),
            'merchant_order_data' => [
                'reference' => (uniqid() ?? null)
            ],
        ];

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL=>'https://merchant.revolut.com/api/orders',CURLOPT_RETURNTRANSFER=>1,CURLOPT_HEADER=>0,CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_POSTFIELDS=>json_encode($params),
                CURLOPT_HTTPHEADER=>array('Content-Type: application/json','Accept: application/json','Authorization: Bearer ". $this->meta['merchant_key'] ."','Revolut-Api-Version: 2023-09-01'),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $invoice = json_decode($response);
        } catch (Exception $e) {
            $this->Input->setErrors([$e->getMessage()]);
        }

        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        $this->view->set('invoice_url', $params['redirect_url'] ?? null);

        return $this->view->fetch();
    }

    public function success(array $get, array $post) {
        return null;
    }

    public function validate(array $get, array $post) {
        return null;
    }

    public function refund($reference_id, $transaction_id, $amount, $notes = null) {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    public function void($reference_id, $transaction_id, $notes = null) {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }
}
