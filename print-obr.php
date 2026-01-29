<?php
// print-obr.php
session_start();
require_once 'config.php'; // Your config file

// Get OBR ID from URL
$obrID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($obrID <= 0) {
    die('Invalid OBR ID');
}

// Database connection
$serverName = "10.0.0.4\PGR2022";
$connectionOptions = array(
    "Database" => "PGR-DMTS",
    "Uid" => "PRG_AdminUser",
    "PWD" => "PRG!ESXiAdminProd#2025$",
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => true,
    "Encrypt" => false
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die('Database connection failed');
}

// Fetch OBR and its line items
$sql = "SELECT OBR.*, 
               (SELECT COUNT(*) FROM OBRLineItems WHERE OBRID = OBR.OBRID) as ItemCount,
               (SELECT STRING_AGG(Particulars, CHAR(10)) FROM OBRLineItems WHERE OBRID = OBR.OBRID) as AllParticulars
        FROM OBR 
        WHERE OBRID = ?";
$params = array($obrID);
$stmt = sqlsrv_query($conn, $sql, $params);

if (!$stmt || !sqlsrv_has_rows($stmt)) {
    die('OBR not found');
}

$obr = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

// Fetch line items separately for better formatting
$lineItems = [];
$itemsSql = "SELECT * FROM OBRLineItems WHERE OBRID = ? ORDER BY LineID";
$itemsStmt = sqlsrv_query($conn, $itemsSql, $params);
if ($itemsStmt) {
    while ($row = sqlsrv_fetch_array($itemsStmt, SQLSRV_FETCH_ASSOC)) {
        $lineItems[] = $row;
    }
    sqlsrv_free_stmt($itemsStmt);
}

sqlsrv_close($conn);

// Generate tracking URL (adjust domain as needed)
$trackingURL = 'https://' . $_SERVER['HTTP_HOST'] . '/portal/tracking.php?tracking=' . urlencode($obr['TrackingNumber']);
// Path to barcode/QR code library (you'll need to install one)
$qrCodeLib = 'vendor/phpqrcode/qrlib.php'; // Example: Using phpqrcode
$barcodeLib = 'vendor/picqer/php-barcode-generator/autoload.php'; // Example: Using picqer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBR Print - <?php echo htmlspecialchars($obr['TrackingNumber']); ?></title>
    <!-- CSS for Print -->
    <style>
        @media print {
            body { margin: 0; padding: 0; background: white; font-size: 12pt; }
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
        }
        @page { margin: 0.5in; }
        
        body { 
            font-family: 'Times New Roman', Times, serif; 
            width: 8.5in; /* Letter width */
            margin: 0 auto;
            color: black;
            line-height: 1.2;
        }
        
        .obr-container {
            border: 2px solid black;
            padding: 20px;
            position: relative;
            min-height: 11in; /* Letter height */
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #999;
            padding-bottom: 15px;
        }
        
        .header-logo {
            height: 80px;
            margin-bottom: 5px;
        }
        
        .gov-title {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .obr-title {
            font-size: 16pt;
            font-weight: bold;
            margin: 10px 0;
            text-decoration: underline;
        }
        
        .tracking-section {
            position: absolute;
            top: 20px;
            right: 20px;
            text-align: center;
            width: 180px;
        }
        
        .tracking-qr {
            width: 100px;
            height: 100px;
            margin: 0 auto 5px;
            border: 1px solid #ccc;
        }
        
        .tracking-number {
            font-family: monospace;
            font-size: 10pt;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .info-table td {
            padding: 6px 10px;
            vertical-align: top;
            border: 1px solid black;
        }
        
        .info-label {
            font-weight: bold;
            width: 25%;
            background-color: #f0f0f0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th, .items-table td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        
        .items-table th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #f8f8f8;
        }
        
        .signature-section {
            margin-top: 40px;
            width: 100%;
        }
        
        .signature-box {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            padding: 15px;
            border-top: 1px solid black;
            margin-top: 40px;
        }
        
        .signature-label {
            font-weight: bold;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid black;
            margin: 40px 0 10px;
        }
        
        .signature-name {
            text-align: center;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .signature-position {
            text-align: center;
            font-size: 10pt;
        }
        
        .footer-section {
            margin-top: 50px;
            padding-top: 10px;
            border-top: 1px dashed #999;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
        
        .footer-barcode {
            margin-top: 10px;
            height: 40px;
        }
        
        .print-controls {
            text-align: center;
            padding: 20px;
            background: #f5f5f5;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Print Controls (Hidden when printing) -->
    <div class="print-controls no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Document
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            <i class="fas fa-times"></i> Close
        </button>
        <button onclick="saveAsPDF()" class="btn btn-success">
            <i class="fas fa-file-pdf"></i> Save as PDF
        </button>
    </div>
    
    <!-- OBR Document -->
    <div class="obr-container">
        <!-- Header with Logo and Titles -->
        <div class="header-section">
            <img src="images/logo.png" alt="Provincial Government of Romblon" class="header-logo" onerror="this.style.display='none'">
            <div class="gov-title">Republic of the Philippines</div>
            <div class="gov-title">PROVINCIAL GOVERNMENT OF ROMBLON</div>
            <div>Romblon, Romblon</div>
            <div class="obr-title">OBLIGATION REQUEST</div>
        </div>
        
        <!-- QR Code and Tracking Number (Top Right) -->
        <div class="tracking-section">
            <?php
            // Generate QR Code (requires phpqrcode library)
            if (file_exists($qrCodeLib)) {
                require_once $qrCodeLib;
                $qrTempFile = 'temp/qr_' . $obr['TrackingNumber'] . '.png';
                if (!file_exists('temp')) mkdir('temp', 0777, true);
                QRcode::png($trackingURL, $qrTempFile, QR_ECLEVEL_L, 8);
                echo '<img src="' . $qrTempFile . '" class="tracking-qr" alt="QR Code">';
            } else {
                echo '<div class="tracking-qr" style="background:#eee; display:flex; align-items:center; justify-content:center;">';
                echo '<span style="font-size:8pt;">QR Code<br>Library Missing</span>';
                echo '</div>';
            }
            ?>
            <div class="tracking-number"><?php echo htmlspecialchars($obr['TrackingNumber']); ?></div>
            <div style="font-size:8pt;">Scan to Track</div>
        </div>
        
        <!-- Basic Information Table -->
        <table class="info-table">
            <tr>
                <td class="info-label">Payee</td>
                <td><?php echo htmlspecialchars($obr['Payee']); ?></td>
            </tr>
            <tr>
                <td class="info-label">Office</td>
                <td><?php echo htmlspecialchars($obr['Department']); ?></td>
            </tr>
            <tr>
                <td class="info-label">Address</td>
                <td><?php echo htmlspecialchars($obr['Address']); ?></td>
            </tr>
            <tr>
                <td class="info-label">OBR Number</td>
                <td><strong><?php echo htmlspecialchars($obr['TrackingNumber']); ?></strong></td>
            </tr>
            <tr>
                <td class="info-label">Date</td>
                <td><?php echo $obr['Datetime']->format('F j, Y'); ?></td>
            </tr>
        </table>
        
        <!-- Line Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="20%">Responsibility Center</th>
                    <th width="45%">Particulars</th>
                    <th width="15%">F.P.P</th>
                    <th width="10%">Account Code</th>
                    <th width="10%">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalAmount = 0;
                foreach ($lineItems as $item) {
                    $totalAmount += $item['Amount'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['ResCenter']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($item['Particulars'])); ?></td>
                        <td><?php echo htmlspecialchars($item['FPP']); ?></td>
                        <td><?php echo htmlspecialchars($item['AccCode']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($item['Amount'], 2); ?></td>
                    </tr>
                    <?php
                }
                ?>
                <!-- Empty rows to match OBR.xlsx format (approximately 10 rows total) -->
                <?php for ($i = count($lineItems); $i < 10; $i++): ?>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
                <?php endfor; ?>
                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;"><strong>Total</strong></td>
                    <td style="text-align: right;"><strong>₱ <?php echo number_format($totalAmount, 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Signatures Section (Matching OBR.xlsx) -->
        <div class="signature-section">
            <!-- Certified A (Left Side) -->
            <div class="signature-box" style="text-align: left;">
                <div class="signature-label">Certified</div>
                <div style="margin-bottom: 15px;">
                    <div>Charges to appropriation/allotment necessary, lawful</div>
                    <div>and under my direct supervision</div>
                    <div>Supporting documents valid, proper and legal</div>
                </div>
                <div class="signature-line"></div>
                <div class="signature-name">JOSE R. RIANO</div>
                <div class="signature-position">GOVERNOR</div>
                <div class="signature-position">Head, Requesting Office/Authorized Representative</div>
                <div style="margin-top: 10px;">Date: ____________________</div>
            </div>
            
            <!-- Certified B (Right Side) -->
            <div class="signature-box" style="text-align: left; float: right;">
                <div class="signature-label">Certified</div>
                <div style="margin-bottom: 15px;">
                    <div>Existence of available appropriation</div>
                </div>
                <div class="signature-line"></div>
                <div class="signature-name">AMELIE G. MALLEN</div>
                <div class="signature-position">PGDH - PBO</div>
                <div class="signature-position">Head, Budget Unit/Authorized Representative</div>
                <div style="margin-top: 10px;">Date: ____________________</div>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <!-- Footer with Barcode and System Message -->
        <div class="footer-section">
            <div>---------------------------------------------------------------------------------------------------</div>
            <div><em>This is a computer-generated document from the Romblon Provincial Government Document Management & Tracking System (PGR-DMTS).</em></div>
            <div><em>For verification, scan the barcode below or visit the tracking portal.</em></div>
            
            <?php
            // Generate Barcode (requires picqer/php-barcode-generator)
            if (file_exists($barcodeLib)) {
                require_once $barcodeLib;
                $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
                $barcodeData = $generator->getBarcode($obr['TrackingNumber'], $generator::TYPE_CODE_128);
                echo '<img src="data:image/png;base64,' . base64_encode($barcodeData) . '" class="footer-barcode" alt="Barcode">';
            } else {
                echo '<div class="footer-barcode" style="background:#eee; padding:5px; font-family:monospace;">';
                echo 'BARCODE: ' . htmlspecialchars($obr['TrackingNumber']);
                echo '</div>';
            }
            ?>
            <div style="font-size: 8pt; margin-top: 5px;">Tracking URL: <?php echo htmlspecialchars($trackingURL); ?></div>
        </div>
    </div>
    
    <script>
    function saveAsPDF() {
        alert('PDF generation would require a library like TCPDF or Dompdf. Implement server-side PDF generation.');
        // You could redirect to: generate-pdf.php?id=<?php echo $obrID; ?>
    }
    
    // Auto-print option
    window.onload = function() {
        // Uncomment next line to auto-print when page loads
        // setTimeout(function() { window.print(); }, 1000);
    };
    </script>
</body>
</html>
<?php
// Cleanup temporary QR code file
if (isset($qrTempFile) && file_exists($qrTempFile)) {
    unlink($qrTempFile);
}
?>