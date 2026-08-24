<?php
function sendSMSNotification($mobileNumber, $userName, $bunkName, $bookingTime) {
    $apiKey = "AZJBQPuDaczdxCL0O4RtY9hSw8NvF6W5K7fky1nbGi2HjqoElMLbc5ZoPJlhpiOEejwr9n46I3BV8vRW";

    // Clean phone number: keep only digits and strip leading 91 if someone entered 12 digits (e.g. 919876543210 -> 9876543210)
    $mobileNumber = preg_replace('/[^0-9]/', '', $mobileNumber);
    if (strlen($mobileNumber) > 10 && substr($mobileNumber, 0, 2) === '91') {
        $mobileNumber = substr($mobileNumber, -10);
    }

    if (empty($mobileNumber) || strlen($mobileNumber) < 10) {
        return false;
    }

    $message = "Hello $userName, your EV slot at $bunkName for $bookingTime is CONFIRMED. Thank you!";

    // Fast2SMS v3 payload fields
    $data = array(
        "route" => "q", // Quick SMS route (or use "v3" if using DLT approved templates)
        "message" => $message,
        "language" => "english",
        "flash" => 0,
        "numbers" => $mobileNumber
    );

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            "authorization: " . $apiKey,
            "accept: */*",
            "cache-control: no-cache",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("SMS cURL Error: " . $err);
        return false;
    } else {
        // Decode response to verify if Fast2SMS accepted it successfully
        $res_arr = json_decode($response, true);
        if (isset($res_arr['return']) && $res_arr['return'] == true) {
            return true;
        } else {
            // Log the API error message if Fast2SMS rejects it (e.g. invalid key, low wallet balance)
            error_log("Fast2SMS API Error Response: " . $response);
            return false;
        }
    }
}
?>