<?php

require_once(__DIR__.'/../vendor/autoload.php');

// note: vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Shared/File.php line 164 
// patched to remove is_readable check. it fails on mounted network drive when it is in fact readable.
// also line 187, same thing.

use PhpOffice\PhpSpreadsheet\IOFactory;
use InvoiceNinja\Sdk\InvoiceNinja;

$url = 'https://invoiceninja';
$token = '';
$excel_sheet_name = 'Income Calculations';

// start row for each quarter
$start_rows = [
	1 => 3,   // Q1 starts at row 3
	2 => 13,  // Q2 starts at row 13
	3 => 21,  // Q3 starts at row 21
	4 => 31   // Q4 starts at row 31
];

$ninja = new InvoiceNinja($token);
$ninja
	->setUrl($url)
	->addHeader(['X-Ninja-Token' => $token]); // v4 style header. lib uses v5 style.

$tz = new DateTimeZone('America/Toronto');
$dt = new DateTimeImmutable('now', $tz);

$month = (int)$dt->format('n');
$year = (int)$dt->format('Y');

// Determine quarter
$quarter = (int)ceil($month / 3);
$start_row = $start_rows[$quarter];

// Calculate start and end dates for the quarter
$start_month = ($quarter - 1) * 3 + 1;
$end_month = $quarter * 3;

$start_date = $dt->modify("{$year}-{$start_month}-01");
/** @disregard P1006 */
$end_date = $dt
	->modify("{$year}-{$end_month}-01")
	->modify('last day of this month');

/** @var DateTimeImmutable $start_date */
/** @var DateTimeImmutable $end_date */

echo "Q{$quarter} - {$start_date->format('Y-m-d')} to {$end_date->format('Y-m-d')}\n";

$ret = $ninja->invoices->all([
	// filtering by date not supported in v4... booo
	'include' => 'payments,client',
	'per_page' => 50
]);

if (empty($ret['data'])) {
    die("Error: No invoices returned from Invoice Ninja API for Q{$quarter} {$year}\n");
}

// Process the API data
$processed_data = [];
foreach ($ret['data'] as $item) {
	if (!empty($item['is_deleted'])) {
        continue;
    }

    // Skip invoices outside the current quarter's date range
    $invoice_date = new DateTimeImmutable($item['invoice_date'], $tz);
    if ($invoice_date < $start_date || $invoice_date > $end_date) {
        continue;
    }

    // Determine payment method from the first payment's transaction_reference
    $method = '';
    $first_payment = $item['payments'][0] ?? null;
    if ($first_payment) {
        $ref = $first_payment['transaction_reference'] ?? '';
        if (str_starts_with($ref, 'ch_')) {
            $method = 'Stripe';
        }
    }
    $processed_data[] = [
        'client'         => $item['client']['name'],
        'invoice_number' => $item['invoice_number'],
        'date'           => $item['invoice_date'],
        'amount'         => (float)$item['amount'],
        'method'         => $method,
    ];
}

// Sort processed_data by date
usort($processed_data, function($a, $b) {
    return new DateTimeImmutable($a['date']) <=> new DateTimeImmutable($b['date']);
});

echo "got ".count($processed_data)." invoices\n";

$filename = $argv[1] ?? '';
if (!$filename || !is_file($filename)) {
	echo "usage: invoiceninja.php <excelfile to update>\n";
	die;
}
// get full path
$filename = realpath($filename);

// Create backup of Excel file before loading
$backup_path = $filename . '_bak_' . microtime(true) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
copy($filename, $backup_path);

try {
    $spreadsheet = IOFactory::load($filename);
    $worksheet = $spreadsheet->getSheetByName($excel_sheet_name);
    
    if (!$worksheet) {
        die("Error: Sheet '{$excel_sheet_name}' not found\n");
    }
    
    // Insert data starting from the appropriate row
    $current_row = $start_row;
    
    foreach ($processed_data as $row_data) {
        // Insert data into specific columns (A, B, C, D, E)
        $worksheet->setCellValue('A' . $current_row, $row_data['client']);
        $worksheet->setCellValue('B' . $current_row, $row_data['invoice_number']);
        $worksheet->setCellValue('C' . $current_row, $row_data['date']);
        $worksheet->setCellValue('D' . $current_row, $row_data['amount']);
        $worksheet->setCellValue('E' . $current_row, $row_data['method']);
        
        $current_row++;
    }
    
    // Save the Excel file
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($filename);
    
    echo "Successfully processed and updated Excel file for {$quarter}/{$year}\n";
    echo "Inserted " . count($processed_data) . " records starting at row {$start_row}\n";
    
} 
catch (Exception $ex) {
	var_dump($ex);
}