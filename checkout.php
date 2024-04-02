<?php

class SumUpCheckout {
    private $accessToken = '';
    private $merchantCode = '';
    private $redirectURL = '';
    private $checkoutUrl = 'https://api.sumup.com/v0.1/checkouts';
    private $logFilename = 'checkout.log';
    private $logFile;

    public function __construct() {
        $this->GetEnv();
        $this->initLogFile();
    }

    public function __destruct() {
        fclose($this->logFile);
    }
    private function GetEnv() {
        $this->accessToken = getenv('SUMUP_ACCESS_TOKEN');
        if (!$this->accessToken) {
            throw new Exception("Could not find access token!");
        }

        $this->merchantCode = getenv('SUMUP_MERCHANT_CODE');
        if (!$this->merchantCode) {
            throw new Exception("Could not find merchant code!");
        }

        $this->redirectURL = getenv('SUMUP_REDIRECT_URL');
    }

    private function initLogFile() {
        $this->logFile = fopen($this->logFilename, 'a');
        if (!$this->logFile) {
            throw new Exception("Could not open the log file for writing.");
        }
    }

    private function writeToLog($message) {
        fwrite($this->logFile, $message . PHP_EOL);
    }

    public function createCheckout($amount) {
        $checkoutData = [
            'checkout_reference' => uniqid(),
            'amount' => $amount,
            'currency' => 'EUR',
            'merchant_code' => $this->merchantCode
        ];

        if (!empty($this->redirectURL)) {
            $checkoutData['redirect_url'] = $this->redirectURL;
        }
        
        $jsonData = json_encode($checkoutData);
        $this->writeToLog("Command: createcheckout");
        $this->writeToLog("Request body:");
        $this->writeToLog($jsonData);

        $ch = curl_init($this->checkoutUrl);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
            $this->writeToLog("Response header: " . $header);
            return strlen($header);
        });

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->writeToLog("cURL error: " . curl_error($ch));
        } else {
            $this->writeToLog("Response body:");
            $this->writeToLog($response);
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        if (isset($responseData['id'])) {
            return json_encode(['checkoutId' => $responseData['id']]);
        } else {
            return json_encode(['error' => 'Failed to create checkout']);
        }
    }

    public function getCheckoutStatus($checkoutID) {
        $this->writeToLog("Command: getcheckoutstatus");
        $this->writeToLog("Request body:");
        $this->writeToLog($checkoutID);

        $ch = curl_init($this->checkoutUrl . '/' . $checkoutID);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
            $this->writeToLog("Response header: " . $header);
            return strlen($header);
        });

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->writeToLog("cURL error: " . curl_error($ch));
        } else {
            $this->writeToLog("Response body:");
            $this->writeToLog($response);
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        if (isset($responseData['id'])) {
            return $response;
        } else {
            return json_encode(['error' => 'Failed to create checkout']);
        }
    }
}

$inputData = json_decode(file_get_contents('php://input'), true);

$response = json_encode(['error' => 'Invalid command']);

if ($inputData['command']=='createcheckout') {
    $amount = $inputData['amount'];
    try {
        $sumUpCheckout = new SumUpCheckout();
        $response = $sumUpCheckout->createCheckout($amount);
    } catch (Exception $e) {
        $response = json_encode(['error' => $e->getMessage()]);
    }
} elseif ($inputData['command']=='getcheckoutstatus') {
    $checkoutid = $inputData['checkoutid'];
    try {
        $sumUpCheckout = new SumUpCheckout();
        $response = $sumUpCheckout->getCheckoutStatus($checkoutid);
    } catch (Exception $e) {
        $response = json_encode(['error' => $e->getMessage()]);
    }
};

echo $response;
