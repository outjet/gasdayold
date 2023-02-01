<!DOCTYPE html>
<HTML>
<HEAD>
    <link rel="shortcut icon" href="favicon/favicon.ico">
    <script async src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>

<TITLE>Gas Day temperatures</TITLE>
<STYLE>

 @font-face {
  font-family: "egyptienne";
  src: url("fonts/Egyptienne-Regular-webfont.woff2") format('woff2');
}

 @font-face {
  font-family: "urbano";
  src: url("fonts/Urbano-Regular-webfont.woff2") format('woff2');
}

body {
    font-family: 'urbano';
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border: 1px solid black;
    padding: 8px;
    text-align: left;
}

th {
    background-color: #f2f2f2;
    font-family: egyptienne;
    color: #0072ce;
}

tr:nth-child(even) {
    background-color: #f2f2f2;
}


.loading {
    display: none;
    text-align: center;
    font-size: 80px;
    font-weight: bold;
}

a {
    color: #0072ce;
    text-decoration: none;
}

a:link, a:visited {
    color: #0072ce;
}

a:hover {
    color: #0072ce;
    text-decoration: underline;
}

@media only screen and (max-width: 600px) {
  table {
    width: 100%;
  }
  
  th, td {
    font-size: 14px;
  }
}


</STYLE>
</HEAD>
<BODY>
<div id="loading-indicator" class="loading">Loading...</div>
<?php

function get_temperatures($location) {
    // global variable to catch if we're using fresh data
    global $cache_status;
    global $cache_time;
    global $forecastGenerator;
    global $forecastGenerated;
    global $forecastUpdated;
    // Define the cache file path
    $cache_file = 'cache/' . md5($location) . '.json';
    $cache_dir = dirname($cache_file);
    
    // Check if the cache directory exists
    $create_cache_dir = !file_exists($cache_dir);
    
    // Check if the cache directory needs to be created
    if ($create_cache_dir) {
        mkdir($cache_dir, 0755, true);
    }
    
    // Check if the cache file exists and was modified within the last 10 minutes
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 600)) {
        // Read the data from the cache file
        $cache_time = date("F j, Y, g:i:s a", filemtime($cache_file));
        $cache_status =  "Using cached NWS API data from ";
        $response = file_get_contents($cache_file);
    } else {

        // Initialize the cURL session
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.weather.gov/gridpoints/" . $location . "/forecast/hourly?units=us");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/geo+json","User-Agent: Mozilla/5.0 (Windows NT 10.0;Win64) AppleWebkit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        // Save the data to the cache file
        $cache_time = date("F j, Y, g:i:s a");
        $cache_status =  "Live API query sent to NWS at ";
        if (!file_put_contents($cache_file, $response)) {
            error_log("Error writing to cache file: " . $cache_file);
            error_log("Caching failed. Error details: " . json_encode(error_get_last()), 0);

        }
    }

    // Decode the JSON data into an associative array
    $data = json_decode($response, true);
    $daily_temperatures = array();
    if (is_array($data)) {
    $forecastGenerator = $data['properties']['forecastGenerator'];
    $forecastGenerated = $data['properties']['updated'];
    $forecastUpdated = $data['properties']['updated'];
      foreach($data['properties']['periods'] as $period) {
            // Get the start time of the period
            $start_time = strtotime($period["startTime"]);
            // Get the hour of the period
            $hour = date("H", $start_time);
            // Subtract one day if the hour is less than 10
                $start_time -= 54000;
            // Get the day of the period
            $day = date("Y-m-d", $start_time);
            // Get the temperature of the period
            $temperature = $period["temperature"];
            // Check if we have already processed a period for this day
            if (isset($daily_temperatures[$day])) {
                // Add the temperature to the existing sum
                $daily_temperatures[$day]["temperature_sum"] += $temperature;
                // Add 1 to the count
                $daily_temperatures[$day]["count"]++;
            } else {
                // Initialize a new array for this day
                $daily_temperatures[$day] = array(
                    "temperature_sum" => $temperature,
                    "count" => 1
                );
            }
        }

    } 
    else {
        echo "Error: No data received from the API for " . $location;
    }
    return $daily_temperatures;
}

$daily_temperatures_cle = get_temperatures("CLE/77,59");
$daily_temperatures_akr = get_temperatures("CLE/94,39");

?>


<table>
    <tr>
        <th>Gas Day</th>
        <th>CLE</th>
        <th>CAK</th>
        <th>CLE+</th>
    </tr>
    <?php foreach($daily_temperatures_cle as $day => $cle_temps): ?>
        <?php if ($cle_temps["count"] >= 23): ?>
            <tr>
                <td><?php echo $day; ?></td>
                <td><?php echo round($cle_temps["temperature_sum"] / $cle_temps["count"], 1); ?></td>
                <td><?php echo round($daily_temperatures_akr[$day]["temperature_sum"] / $daily_temperatures_akr[$day]["count"], 1); ?></td>
                <td <?php if(($cle_temps["temperature_sum"] / $cle_temps["count"] + $daily_temperatures_akr[$day]["temperature_sum"] / $daily_temperatures_akr[$day]["count"]) / 2 <= 20.0) echo "style='background-color:red;color:white;'"; ?>><?php echo round(($cle_temps["temperature_sum"] / $cle_temps["count"] + $daily_temperatures_akr[$day]["temperature_sum"] / $daily_temperatures_akr[$day]["count"]) / 2, 1); ?></td>
            </tr>
        <?php endif; ?>
    <?php endforeach; ?>
</table>
<BR><BR>
<A href="detail-cle.php" target="_blank">Detailed Hourly Forecast (CLE)</A><BR>
<A href="detail-cak.php" target="_blank">Detailed Hourly Forecast (CAK)</A>
<BR><BR>
   <span style="font-size: 0.8em;">
<?php
    $dateGenerated = new DateTime($forecastGenerated);
    $dateCached = new DateTime($cache_time);

    $timezone = new DateTimeZone("America/New_York");
    $dates = [$dateGenerated, $dateCached];
    foreach ($dates as $date) {
        $date->setTimezone($timezone);
    }
    
    $offset = timezone_offset_get($timezone, $dateGenerated);
    
    echo $cache_status; 
    echo $dateCached->format("F d, Y, g:i:s a"); 
    echo "<BR>";
    echo "NWS Forecast generated: " . $dateGenerated->format("F d, Y, g:i:s a") . "<br>";
($offset >= 0 ? "+" : "") . gmdate("H", abs($offset)) . ")<br>";
?>
</span>
<?php
use GuzzleHttp\Client;

// Create a Guzzle client
$client = new Client();

// Send the API request using Guzzle
$response = $client->get("https://commodities-api.com/api/timeseries?access_key=75hu3uck0m9w5ukoew8y0y9bdt645wk6h8f2gbsqiydsa6qcf4vemt38xytn&base=USD&symbols=NG&start_date=2023-01-01&end_date=2023-01-30", [
    'headers' => [
        'User-Agent' => 'Mozilla 5.0'
    ],
    'verify' => false
]);

// Callback function to process the API response
function processResponse($response) {
    // Decode the JSON response
    $data = json_decode($response->getBody(), true);

    // Check if the data is valid
    if (!isset($data["data"]["rates"])) {
        echo "Failed to parse data from API";
        exit;
    }

    // Get the NG prices
    $ngPrices = [];
    foreach ($data["data"]["rates"] as $date => $rates) {
        $value = $rates["NG"];
        $price = 1 / $value;
        $ngPrices[$date] = $price;
    }

    // Return the NG prices
    return $ngPrices;
}

// Call the callback function and pass the API response as an argument
$ngPrices = processResponse($response);

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>

<canvas id="ngChart"></canvas>
<script>
    // Create the chart
    var ctx = document.getElementById('ngChart').getContext('2d');
    var ngChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_keys($ngPrices)); ?>,
            datasets: [{
                label: 'Natural Gas Prices',
                    data: <?php echo json_encode(array_values($ngPrices)); ?>,
                    backgroundColor: 'rgba(0, 114, 206, 0.2)',
                    borderColor: 'rgba(0, 114, 206, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value, index, values) {
                        return '$' + value.toFixed(2);
                        }
                    }
                    }]
                }
                }

        });
    </script>



</BODY>
</HTML>