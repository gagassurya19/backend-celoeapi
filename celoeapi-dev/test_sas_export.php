<?php
// Test script untuk memverifikasi SAS export endpoint
echo "=== TESTING SAS EXPORT ENDPOINT ===\n";

// Test basic export
echo "\n1. Testing basic export (all tables):\n";
$url = "http://localhost:8081/api/etl_sas/export?limit=10&offset=0";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && isset($data['status']) && $data['status'] === true) {
        echo "✅ SUCCESS: HTTP $httpCode\n";
        echo "Tables exported: " . count($data['data']) . "\n";
        
        foreach ($data['data'] as $tableName => $tableData) {
            echo "  - $tableName: " . $tableData['count'] . " records\n";
        }
        
        echo "Has next: " . ($data['has_next'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ FAILED: Invalid response format\n";
        echo "Response: " . substr($response, 0, 500) . "...\n";
    }
} else {
    echo "❌ FAILED: cURL error\n";
}

// Test specific table export
echo "\n2. Testing specific table export (monev_sas_courses):\n";
$url = "http://localhost:8081/api/etl_sas/export?limit=5&offset=0&table=monev_sas_courses";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && isset($data['status']) && $data['status'] === true) {
        echo "✅ SUCCESS: HTTP $httpCode\n";
        if (isset($data['data']['monev_sas_courses'])) {
            $courses = $data['data']['monev_sas_courses'];
            echo "Courses table: " . $courses['count'] . " records\n";
            
            if ($courses['count'] > 0) {
                echo "Sample course data:\n";
                $sample = $courses['rows'][0];
                foreach ($sample as $key => $value) {
                    echo "  $key: $value\n";
                }
            }
        }
    } else {
        echo "❌ FAILED: Invalid response format\n";
    }
} else {
    echo "❌ FAILED: cURL error\n";
}

// Test with debug mode
echo "\n3. Testing with debug mode:\n";
$url = "http://localhost:8081/api/etl_sas/export?limit=5&offset=0&debug=1";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && isset($data['status']) && $data['status'] === true) {
        echo "✅ SUCCESS: HTTP $httpCode\n";
        echo "Debug mode enabled\n";
        
        foreach ($data['data'] as $tableName => $tableData) {
            if (isset($tableData['debug'])) {
                echo "  - $tableName debug:\n";
                echo "    Total count: " . $tableData['debug']['totalCount'] . "\n";
                if (isset($tableData['debug']['filteredCount'])) {
                    echo "    Filtered count: " . $tableData['debug']['filteredCount'] . "\n";
                }
            }
        }
    } else {
        echo "❌ FAILED: Invalid response format\n";
    }
} else {
    echo "❌ FAILED: cURL error\n";
}

echo "\n=== TEST COMPLETED ===\n";
