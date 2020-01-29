<?php

/* Risport <SelectCmDevice> sample script to retrieve real-time info
on all devices via the "*" wildcard.  Uses the PHP SoapClient library, and DOMDocument

Copyright (c) 2018 Cisco and/or its affiliates.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

// Enable error reporting for troubleshooting
ini_set("display_errors", 1); 
ini_set("display_startup_errors", 1); 
error_reporting(E_ALL);

// Load the vlucas/phpdotenv library and
// load variables from .env
require "vendor/autoload.php";
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv -> load();
$dotenv -> required("CUCM_HOSTNAME") -> notEmpty();
$dotenv -> required("USERNAME") -> notEmpty();
$dotenv -> required("PASSWORD") -> notEmpty();
$dotenv -> required("WSDL") -> notEmpty();


// Define a context stream - allows us to control
// CA cert handling - here we disable checking (insecure)
$context = stream_context_create(
    array("ssl" => array(
        "verify_peer_name" => false,
        "allow_self_signed" => true
    ))
);

// To enable secure connection (production) use the below form
// including location of your CUCM chain .pem file

// $context = stream_context_create(
//     array("ssl" => array(
//         "verify_peer_name" => true,
//         "allow_self_signed" => true,
//         "cafile" => "cucm-chain.pem"
//     ))
// );

// Create the SoapClient object, populated from the 
// configured WSDL file in the schema/ folder
$client = new SoapClient(
    getenv("WSDL"),
        array("trace" => true,
        "exceptions" => true,
        "location" => "https://".getenv("CUCM_HOSTNAME").":8443/realtimeservice2/services/RISService70",
        "login" => getenv("USERNAME"),
        "password" => getenv("PASSWORD"),
        "stream_context" => $context
    )
);

// Execute the SelectCmDevice request
// Note, multiple records per device can be returned
// e.g. if a device registered to more than one CUCM node
$resp = $client -> SelectCmDevice(
    array("StateInfo" => "",
        "CmSelectionCriteria" => array(
            "MaxReturnedDevices" => 1000,
            "Status" => "Any",
            "SelectBy" => "Name",
            "SelectItems" => array(
                "item" => array(
                    "Item" => "*"
                )
            )
        )
    )
);

// Use DOMDocument to pretty print XML request/response
$dom = new DOMDocument();
$dom -> preserveWhiteSpace = false;
$dom -> formatOutput = true;

$dom -> loadXML($client -> __getLastRequest());
$prettyRequest = $dom->saveXML();
$dom -> loadXML($client -> __getLastResponse());
$prettyResponse = $dom->saveXML();
?>
<!DOCTYPE html>
<html>
<h2>REQUEST</h2>
<h3>Headers:</h3>
<pre><?php echo $client -> __getLastRequestHeaders() ?></pre>
<h3>Body:</h3>
<xmp><?php print_r($prettyRequest) ?></xmp>
<br>
<h2>RESPONSE</h2>
<h3>Headers:</h3>
<pre><?php echo $client -> __getLastResponseHeaders() ?></pre>
<h3>Body:</h3>
<xmp><?php print_r($prettyResponse) ?></xmp>
<br>
<h2>OUTPUT</h2>
<?php
if ($resp -> selectCmDeviceReturn -> SelectCmDeviceResult -> TotalDevicesFound >= 1000) {
    echo '<b>Warning:</b> Max devices of 1000 returned, some devices may not be listed<br>';
}
?>
<style>
table, th, td {
  border: 1px solid black;
  border-collapse: collapse;
}
</style>
<table>
<?php 
echo "<tr><th>Node</th><th>Device Name</th><th>IP Address</th></tr>\n";

foreach ($resp -> selectCmDeviceReturn -> SelectCmDeviceResult -> CmNodes -> item as $node) {
    echo "<tr><td>".$node -> Name."</td><td></td><td></td></tr>\n";

    $list = $node -> CmDevices;

    if (!empty($list)) {

        foreach ($list -> item as $device){

            echo "<tr><td></td>";
            echo "<td>".$device -> Name."</td>";

            if (get_object_vars($device -> IPAddress) ) {

                if (is_array($device -> IPAddress -> item)) {
                    echo "</tr>\n";

                    foreach ($device -> IPAddress -> item as $address) {
                        echo "<tr><td/><td/>";
                        echo "<td>".$address -> IP."</td></tr>\n";
                    }
                }
                else {
                    echo "<td>".$device -> IPAddress -> item -> IP."</td></tr>\n";
                }
            }
            else {
                echo "<td>{Unknown}</td></tr>\n";
            }
        }
    }
}
?>
</table>