<?php
class Revolut extends NonmerchantGateway {
    private $meta;
    public function __construct() {
        $this->loadConfig(dirname(__FILE__) . DS . "config.json");
        Loader::loadComponents($this, ["Input"]);
        Loader::loadHelpers($this, ["Html"]);
        Language::loadLang("revolut", null, dirname(__FILE__) . DS . "language" . DS);
    }

    public function setMeta(array $meta = null) {
        $this->meta = $meta;
    }

    public function getName() {
        return "Revolut Pay";
    }

    public function getDescription() {
        return "Revolut Pay is an online payment processing service that helps you accept credit cards.";
    }

    public function getVersion() {
        return "0.1";
    }

    public function getAuthors() {
        return array(array("name" => "WhiteHat", "url" => "https://whitehat.ro"));
    }

    public function getLogo() {
        return null;
    }

    public function getSettings(array $meta = null) {
        // Load the view into this object, so helpers can be automatically add to the view
        $this->view = new View("settings", "default");
        $this->view->setDefaultView("components" . DS . "gateways" . DS . "nonmerchant" . DS . "revolut" . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ["Form", "Html"]);

        $this->view->set("meta", $meta);

        return $this->view->fetch();
    }

    public function editSettings(array $meta) {
        $rules = [
            "merchant_key" => [
                "empty" => [
                    "rule" => "isEmpty",
                    "negate" => true,
                    "message" => "Code empty"
                ]
            ]
        ];
        $this->Input->setRules($rules);
        $this->Input->validates($meta);
        return $meta;
    }

    public function encryptableFields() {
        return ["merchant_key"];
    }

    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null) {
        $amount = round($amount, 2);
        Loader::loadModels($this, ["Contacts"]);

        $params = [
            "amount" => $amount."00",
            "currency" => $this->currency,
            "description" => $options["description"] ?? "Payment",
            "redirect_url" => Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . "/revolut",
            "merchant_order_data" => [
                "reference" => (uniqid() ?? null)
            ],
        ];

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://merchant.revolut.com/api/orders",
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_CUSTOMREQUEST=> "POST",
                CURLOPT_POSTFIELDS => json_encode($params),
                CURLOPT_HTTPHEADER => array("Content-Type: application/json",
                                            "Accept: application/json",
                                            "Authorization: Bearer ". $this->meta["merchant_key"],
                                            "Revolut-Api-Version: 2023-09-01"),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $invoice = json_decode($response);
        } catch (Exception $e) {
            $this->Input->setErrors([$e->getMessage()]);
        }

        $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
        $this->view->set("invoice_url", $invoice->checkout_url ?? null);

        return $this->view->fetch();
    }

    public function success(array $get, array $post) {
        return null;
    }

    public function validate(array $get, array $post) {
        return null;
    }

    public function refund($reference_id, $transaction_id, $amount, $notes = null) {
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    public function void($reference_id, $transaction_id, $notes = null) {
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }
}
