<?php
// File: C:\inetpub\wwwroot\portal\create-obr.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'];
$user_department = $_SESSION['department'] ?? '';
$user_position = $_SESSION['position'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create OBR - Romblon Provincial Government</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 for searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --tiffany: #A7E4D5;
            --turq: #30D5C8;
            --navy: #09324A;
            --light-navy: #0A4A6F;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        
        .form-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-title {
            color: var(--navy);
            border-bottom: 2px solid var(--turq);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .line-item-row {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f9f9f9;
            transition: all 0.3s;
        }
        .line-item-row:hover {
            background: #f0f7ff;
            border-color: var(--tiffany);
        }
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
        }
        .file-preview {
            max-width: 100px;
            max-height: 100px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 2px;
        }
        .form-required:after {
            content: " *";
            color: #e74c3c;
        }
        .btn-add-line {
            background: var(--navy);
            color: white;
            border: none;
        }
        .btn-add-line:hover {
            background: var(--light-navy);
        }
        .btn-remove-line {
            background: #e74c3c;
            color: white;
            border: none;
        }
        .btn-remove-line:hover {
            background: #c0392b;
        }
        
        /* Select2 custom styling */
        .select2-container--default .select2-selection--single {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            height: 38px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        
        /* Make sure dropdowns are properly sized */
        .line-item-row .select2-container {
            width: 100% !important;
        }
        
        /* Main content adjustment for sidebar */
        .main-content-wrapper {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 992px) {
            .main-content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'dashboard_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content-wrapper">
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-file-invoice-dollar"></i> Create OBR (Obligation Request)</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.history.back()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <a href="dashboard.php" class="btn btn-sm btn-outline-primary ms-2">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>
        
        <!-- Progress Indicator -->
        <div class="progress mb-4" style="height: 10px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="formProgress"></div>
        </div>
        
        <!-- Success/Error Messages -->
        <div id="messageContainer"></div>
        
        <!-- OBR Creation Form -->
        <form id="obrForm" enctype="multipart/form-data">
            <!-- Section 1: Basic Information -->
            <div class="form-section" id="section1">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h3>
                
                <div class="row">
                    <!-- OBR Number (Manual Entry) -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label form-required">OBR Number</label>
                        <input type="text" class="form-control" id="trackingNumber" name="trackingNumber" 
                               required placeholder="Enter OBR Number (e.g., OBR-2024-001)">
                        <small class="text-muted">Enter your OBR number manually</small>
                    </div>
                    
                    <!-- Date -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label form-required">Date</label>
                        <input type="datetime-local" class="form-control" id="obrDate" name="obrDate" required 
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>
                
                <div class="row">
                    <!-- Department (Auto-filled from session) -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label form-required">Department/Office</label>
                        <input type="text" class="form-control" id="department" name="department" 
                               value="<?php echo htmlspecialchars($user_department); ?>" readonly>
                        <small class="text-muted">Automatically filled from your profile</small>
                    </div>
                    
                    <!-- Payee -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label form-required">Payee</label>
                        <input type="text" class="form-control" id="payee" name="payee" required 
                               placeholder="Enter payee name">
                    </div>
                </div>
                
                <div class="row">
                    <!-- Address (Province/Municipality from PSGC) -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label form-required">Province</label>
                        <select class="form-select" id="province" name="province" required>
                            <option value="">Select Province...</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label form-required">Municipality</label>
                        <select class="form-select" id="municipality" name="municipality" required disabled>
                            <option value="">Select Municipality...</option>
                        </select>
                    </div>
                </div>
                
                <!-- General Remarks -->
                <div class="col-12 mb-3">
                    <label class="form-label">Remarks/Notes</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                              placeholder="Enter any remarks or notes for this OBR..."></textarea>
                </div>
            </div>
            
            <!-- Section 2: Particulars (Line Items) -->
            <div class="form-section" id="section2">
                <h3 class="section-title"><i class="fas fa-list-alt"></i> Particulars</h3>
                
                <!-- Line Items Container -->
                <div id="lineItemsContainer">
                    <!-- Line items will be added here dynamically -->
                </div>
                
                <!-- Add Line Item Button -->
                <div class="text-center mb-4">
                    <button type="button" class="btn btn-add-line" id="addLineItem" disabled>
                        <i class="fas fa-plus"></i> Add Line Item
                    </button>
                </div>
                
                <!-- Total Amount -->
                <div class="row justify-content-end">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-end">
                                <h5 class="card-title">Total Amount</h5>
                                <div class="total-amount" id="totalAmount">₱ 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section 3: Attachments -->
            <div class="form-section" id="section3">
                <h3 class="section-title"><i class="fas fa-paperclip"></i> Supporting Documents</h3>
                
                <div class="mb-3">
                    <label class="form-label">Upload Supporting Documents</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple 
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx">
                    <div class="form-text">You can upload multiple files (PDF, Word, Excel, Images)</div>
                    <small class="text-warning">Maximum total file size: 10MB. For larger files, please compress or split them.</small>
                </div>
                
                <!-- File Preview Area -->
                <div id="filePreview" class="mt-3"></div>
            </div>
            
            <!-- Hidden fields for user info -->
            <input type="hidden" id="createdBy" name="createdBy" value="<?php echo htmlspecialchars($user_id); ?>">
            <input type="hidden" id="createdByName" name="createdByName" value="<?php echo htmlspecialchars($user_name); ?>">
            <input type="hidden" id="createdByPosition" name="createdByPosition" value="<?php echo htmlspecialchars($user_position); ?>">
            
            <!-- Form Actions -->
            <div class="form-section">
                <div class="row">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-secondary" onclick="saveAsDraft()">
                            <i class="fas fa-save"></i> Save as Draft
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-check-circle"></i> Submit OBR
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- JavaScript Libraries - LOAD JQUERY FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>
</html>

<!-- Separate JavaScript to avoid jQuery conflicts -->
<script>
// Wait for jQuery to be fully loaded
if (typeof jQuery === 'undefined') {
    console.error('jQuery not loaded!');
    // Reload jQuery if not loaded
    var script = document.createElement('script');
    script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
    script.onload = function() {
        initializeOBRForm();
    };
    document.head.appendChild(script);
} else {
    // jQuery is loaded, initialize the form
    $(document).ready(function() {
        initializeOBRForm();
    });
}

function initializeOBRForm() {
    // Global variables
    let lineItemCounter = 0;
    let responsibilityCenters = []; // Store loaded responsibility centers
    
    // Date formatting helper function
    function formatDateForSQL(dateTimeString) {
        // Convert "2024-01-28T15:30" to "2024-01-28 15:30:00"
        const date = new Date(dateTimeString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }
    
    // File to base64 conversion
    function fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    }
    
    // Get file icon based on file type
    function getFileIcon(fileType) {
        if (fileType.includes('pdf')) return 'fas fa-file-pdf';
        if (fileType.includes('word') || fileType.includes('document')) return 'fas fa-file-word';
        if (fileType.includes('excel') || fileType.includes('spreadsheet')) return 'fas fa-file-excel';
        if (fileType.includes('image')) return 'fas fa-file-image';
        return 'fas fa-file';
    }
    
    // Show message function
    function showMessage(message, type) {
        const messageDiv = document.getElementById('messageContainer');
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'warning' ? 'alert-warning' : 'alert-danger';
        
        const alertHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        messageDiv.innerHTML = alertHTML;
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.parentElement === messageDiv) {
                    alert.remove();
                }
            });
        }, 5000);
    }
    
    function initializeForm() {
        // Set today's date
        const now = new Date();
        const timezoneOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = new Date(now - timezoneOffset).toISOString().slice(0, 16);
        document.getElementById('obrDate').value = localISOTime;
    }
    
    async function loadDropdowns() {
        try {
            // Load responsibility centers from API
            const response = await fetch('api/responsibility-centers.php');
            const data = await response.json();
            
            if (data.success) {
                // Store responsibility centers for use in line items
                responsibilityCenters = data.data;
                console.log('Loaded responsibility centers:', responsibilityCenters.length);
                
                // Enable add line item button
                document.getElementById('addLineItem').disabled = false;
                
                // Add first line item
                addLineItem();
            } else {
                throw new Error(data.error || 'Failed to load responsibility centers');
            }
            
            // Load provinces from PSGC API
            const provinceResponse = await fetch('api/address.php?action=provinces');
            const provinceData = await provinceResponse.json();
            
            if (provinceData.success) {
                const provinceSelect = document.getElementById('province');
                provinceSelect.innerHTML = '<option value="">Select Province...</option>';
                
                provinceData.data.forEach(province => {
                    const option = document.createElement('option');
                    option.value = province.code;
                    option.textContent = province.name;
                    provinceSelect.appendChild(option);
                });
            }
            
            return data.success; // Return success status
            
        } catch (error) {
            showMessage('Error loading dropdown data: ' + error.message, 'danger');
            console.error('Error loading dropdowns:', error);
            return false;
        }
    }
    
    function setupEventListeners() {
        // Province change event
        $('#province').change(async function() {
            const provinceCode = $(this).val();
            if (provinceCode) {
                await loadMunicipalities(provinceCode);
            } else {
                $('#municipality').prop('disabled', true).empty().append('<option value="">Select Municipality...</option>');
            }
            updateProgress();
        });
        
        // File upload preview and size check
        document.getElementById('attachments').addEventListener('change', function(e) {
            previewFiles(e.target.files);
            updateProgress();
        });
        
        // Form submission
        document.getElementById('obrForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitOBR('Submitted');
        });
        
        // Add line item button
        document.getElementById('addLineItem').addEventListener('click', addLineItem);
    }
    
    async function loadMunicipalities(provinceCode) {
        try {
            const response = await fetch(`api/address.php?action=municipalities&provinceCode=${provinceCode}`);
            const data = await response.json();
            
            if (data.success) {
                const munSelect = $('#municipality');
                munSelect.empty().append('<option value="">Select Municipality...</option>');
                
                data.data.forEach(mun => {
                    munSelect.append(new Option(mun.name, mun.name));
                });
                
                munSelect.prop('disabled', false);
            } else {
                showMessage('Error loading municipalities: ' + data.error, 'warning');
            }
        } catch (error) {
            showMessage('Error loading municipalities: ' + error.message, 'warning');
        }
    }
    
    function addLineItem() {
        lineItemCounter++;
        const lineItemId = `lineItem_${lineItemCounter}`;
        
        const lineItemHTML = `
            <div class="line-item-row" id="${lineItemId}">
                <div class="row">
                    <div class="col-md-1 mb-2">
                        <label class="form-label">#${lineItemCounter}</label>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label form-required">Responsibility Center</label>
                        <select class="form-select line-responsibility-center" name="lineResCenter[]" required style="width: 100%;">
                            <option value="">Select Responsibility Center...</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">FPP</label>
                        <input type="text" class="form-control" name="lineFPP[]" placeholder="Enter FPP">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label form-required">Account Code</label>
                        <select class="form-select line-account-code" name="lineAccCode[]" required style="width: 100%;">
                            <option value="">Search account code...</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label form-required">Amount</label>
                        <input type="number" class="form-control line-amount" name="lineAmount[]" 
                               step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div class="col-md-1 mb-2 d-flex align-items-end">
                        ${lineItemCounter > 1 ? `
                        <button type="button" class="btn btn-remove-line btn-sm" onclick="window.removeLineItem('${lineItemId}')">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <label class="form-label form-required">Particulars</label>
                        <textarea class="form-control" name="lineParticulars[]" rows="2" 
                                  placeholder="Enter description for this line item..." required></textarea>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('lineItemsContainer').insertAdjacentHTML('beforeend', lineItemHTML);
        
        // Populate responsibility centers for this line item
        populateLineResponsibilityCenters(lineItemId);
        
        // Initialize account code search for this line item
        initializeAccountCodeSearch(lineItemId);
        
        // Add event listener for amount change
        $(`#${lineItemId} .line-amount`).on('change keyup', function() {
            calculateTotal();
            updateProgress();
        });
        
        updateProgress();
    }
    
    // Make removeLineItem available globally
    window.removeLineItem = function(lineItemId) {
        // Destroy Select2 before removing to prevent memory leaks
        $(`#${lineItemId} .line-responsibility-center`).select2('destroy');
        $(`#${lineItemId} .line-account-code`).select2('destroy');
        
        document.getElementById(lineItemId).remove();
        renumberLineItems();
        calculateTotal();
        updateProgress();
    };
    
    function populateLineResponsibilityCenters(lineItemId) {
        const resSelect = $(`#${lineItemId} .line-responsibility-center`);
        resSelect.empty().append('<option value="">Select Responsibility Center...</option>');
        
        // Check if responsibility centers are loaded
        if (responsibilityCenters.length === 0) {
            console.error('Responsibility centers not loaded yet');
            // Try to load them now
            loadDropdowns().then(() => {
                if (responsibilityCenters.length > 0) {
                    populateDropdownWithData(resSelect);
                }
            });
        } else {
            populateDropdownWithData(resSelect);
        }
    }
    
    function populateDropdownWithData(selectElement) {
        // Clear existing options (except the first one)
        selectElement.find('option:not(:first)').remove();
        
        // Add responsibility centers
        responsibilityCenters.forEach(center => {
            selectElement.append(new Option(`${center.code} - ${center.name}`, center.code));
        });
        
        // Initialize Select2 AFTER populating the options
        selectElement.select2({
            placeholder: "Select Responsibility Center...",
            allowClear: true,
            width: '100%'
        }).on('change', updateProgress);
    }
    
    function initializeAccountCodeSearch(lineItemId) {
        // Initialize Select2 for account code search
        $(`#${lineItemId} .line-account-code`).select2({
            placeholder: "Search account code...",
            allowClear: true,
            width: '100%',
            ajax: {
                url: 'api/account-codes.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term
                    };
                },
                processResults: function(data) {
                    if (data.success) {
                        return {
                            results: data.data.map(code => ({ id: code, text: code }))
                        };
                    }
                    return { results: [] };
                },
                cache: true
            }
        }).on('change', updateProgress);
    }
    
    function renumberLineItems() {
        lineItemCounter = 0;
        document.querySelectorAll('.line-item-row').forEach((row, index) => {
            lineItemCounter++;
            row.querySelector('label').textContent = `#${lineItemCounter}`;
            
            // Show remove button only if there's more than 1 line item
            const removeBtn = row.querySelector('.btn-remove-line');
            if (lineItemCounter === 1) {
                if (removeBtn) removeBtn.remove();
            } else if (!removeBtn) {
                // Add remove button if it doesn't exist
                const buttonContainer = row.querySelector('.col-md-1.mb-2.d-flex.align-items-end');
                if (buttonContainer) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-remove-line btn-sm';
                    button.innerHTML = '<i class="fas fa-trash"></i>';
                    button.onclick = function() {
                        window.removeLineItem(row.id);
                    };
                    buttonContainer.appendChild(button);
                }
            }
        });
    }
    
    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.line-amount').forEach(input => {
            const amount = parseFloat(input.value) || 0;
            total += amount;
        });
        
        document.getElementById('totalAmount').textContent = '₱ ' + total.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function previewFiles(files) {
        const preview = document.getElementById('filePreview');
        preview.innerHTML = '';
        
        // Check total file size (max 10MB)
        let totalSize = 0;
        for (let i = 0; i < files.length; i++) {
            totalSize += files[i].size;
        }
        
        if (totalSize > 10 * 1024 * 1024) { // 10MB limit
            showMessage('Total file size exceeds 10MB. Please reduce file sizes.', 'warning');
            document.getElementById('attachments').value = '';
            return;
        }
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'file-preview';
                    img.title = `${file.name} (${(file.size/1024).toFixed(1)} KB)`;
                    preview.appendChild(img);
                } else {
                    const icon = getFileIcon(file.type);
                    const div = document.createElement('div');
                    div.className = 'file-preview bg-light d-flex align-items-center justify-content-center';
                    div.title = `${file.name} (${(file.size/1024).toFixed(1)} KB)`;
                    div.innerHTML = `<i class="${icon} fa-2x text-secondary"></i>`;
                    preview.appendChild(div);
                }
            };
            
            reader.readAsDataURL(file);
        }
    }
    
    async function submitOBR(status) {
        // Validate form
        if (!validateForm()) {
            showMessage('Please fill in all required fields.', 'warning');
            return;
        }
        
        // Validate line items
        if (lineItemCounter === 0) {
            showMessage('Please add at least one line item.', 'warning');
            return;
        }
        
        // Check total file size
        const files = document.getElementById('attachments').files;
        let totalSize = 0;
        for (let i = 0; i < files.length; i++) {
            totalSize += files[i].size;
        }
        
        if (totalSize > 10 * 1024 * 1024) { // 10MB limit
            showMessage('Total file size exceeds 10MB. Please reduce file sizes.', 'warning');
            return;
        }
        
        // Prepare line items data
        const lineItems = [];
        let hasErrors = false;
        
        document.querySelectorAll('.line-item-row').forEach(row => {
            const resCenter = row.querySelector('[name="lineResCenter[]"]').value;
            const accCode = row.querySelector('[name="lineAccCode[]"]').value;
            const particulars = row.querySelector('[name="lineParticulars[]"]').value;
            const amount = parseFloat(row.querySelector('[name="lineAmount[]"]').value) || 0;
            
            if (!resCenter || !accCode || !particulars || amount <= 0) {
                hasErrors = true;
                row.classList.add('border-danger');
            } else {
                row.classList.remove('border-danger');
                lineItems.push({
                    ResCenter: resCenter,
                    FPP: row.querySelector('[name="lineFPP[]"]').value,
                    AccCode: accCode,
                    Amount: amount,
                    Particulars: particulars
                });
            }
        });
        
        if (hasErrors) {
            showMessage('Please fill in all required fields in line items.', 'warning');
            return;
        }
        
        // Prepare attachments as base64 (but limit to first 3 files to avoid 413 error)
        const attachments = [];
        const filesToUpload = files;
        
        // Limit to 3 files to avoid 413 error
        const maxFiles = Math.min(filesToUpload.length, 3);
        
        for (let i = 0; i < maxFiles; i++) {
            const file = filesToUpload[i];
            try {
                const base64 = await fileToBase64(file);
                attachments.push({
                    FileName: file.name,
                    FileType: file.type,
                    FileData: base64.split(',')[1], // Remove data URL prefix
                    FileSize: file.size,
                    Description: file.name
                });
            } catch (error) {
                console.error('Error converting file to base64:', error);
            }
        }
        
        // Warn if some files weren't included
        if (filesToUpload.length > 3) {
            showMessage(`Note: Only the first 3 files were uploaded (${filesToUpload.length - 3} files skipped due to size limits).`, 'warning');
        }
        
        // Prepare form data with proper date format
        const datetimeInput = document.getElementById('obrDate').value;
        const formattedDatetime = formatDateForSQL(datetimeInput);
        
        const formData = {
            Payee: document.getElementById('payee').value,
            TrackingNumber: document.getElementById('trackingNumber').value,
            Department: document.getElementById('department').value,
            Address: document.getElementById('municipality').value + ', ' + document.getElementById('province').value,
            Amount: parseFloat(document.getElementById('totalAmount').textContent.replace(/[^0-9.]/g, '')),
            Datetime: formattedDatetime,
            CreatedBy: document.getElementById('createdBy').value,
            CreatedByName: document.getElementById('createdByName').value,
            CreatedByPosition: document.getElementById('createdByPosition').value,
            Remarks: document.getElementById('remarks').value,
            LineItems: lineItems,
            Attachments: attachments,
            Status: status
        };
        
        // Show loading
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        // Submit via AJAX
        try {
            const response = await fetch('api/save-obr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Check server configuration.');
            }
            
            const result = await response.json();
            
            if (result.success) {
                showMessage(`OBR created successfully! OBR Number: ${result.trackingNumber}`, 'success');
                
                // Reset form on successful submission if needed
                if (status === 'Submitted') {
                    setTimeout(() => {
                        window.location.href = 'my-obrs.php';
                    }, 2000);
                }
            } else {
                showMessage('Error creating OBR: ' + result.error, 'danger');
            }
        } catch (error) {
            console.error('Submission error:', error);
            showMessage('Error: ' + error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Submit OBR';
        }
    }
    
    // Make saveAsDraft available globally
    window.saveAsDraft = function() {
        submitOBR('Draft');
    };
    
    // Make resetForm available globally
    window.resetForm = function() {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            // Destroy all Select2 instances
            $('.line-responsibility-center').select2('destroy');
            $('.line-account-code').select2('destroy');
            
            document.getElementById('obrForm').reset();
            document.getElementById('lineItemsContainer').innerHTML = '';
            document.getElementById('filePreview').innerHTML = '';
            
            lineItemCounter = 0;
            
            // Reset provinces dropdown
            document.getElementById('province').value = '';
            const municipalitySelect = document.getElementById('municipality');
            municipalitySelect.disabled = true;
            municipalitySelect.innerHTML = '<option value="">Select Municipality...</option>';
            
            // Add first line item after reset
            addLineItem();
            updateProgress();
            showMessage('Form has been reset.', 'info');
        }
    };
    
    function validateForm() {
        let isValid = true;
        
        // Check required fields
        document.querySelectorAll('#obrForm [required]').forEach(field => {
            if (!field.value) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
    
    function updateProgress() {
        let progress = 0;
        
        // Check each section
        if (document.getElementById('trackingNumber').value) progress += 20;
        if (document.getElementById('payee').value) progress += 10;
        if (document.getElementById('province').value && document.getElementById('municipality').value) progress += 10;
        
        // Check line items
        if (lineItemCounter > 0) {
            let lineItemsValid = true;
            document.querySelectorAll('.line-item-row').forEach(row => {
                if (!row.querySelector('[name="lineResCenter[]"]').value ||
                    !row.querySelector('[name="lineAccCode[]"]').value || 
                    !row.querySelector('[name="lineParticulars[]"]').value ||
                    !row.querySelector('[name="lineAmount[]"]').value) {
                    lineItemsValid = false;
                }
            });
            if (lineItemsValid) progress += 40;
        }
        
        // Check attachments
        if (document.getElementById('attachments').files.length > 0) progress += 10;
        
        document.getElementById('formProgress').style.width = progress + '%';
    }
    
    // Initialize everything
    initializeForm();
    loadDropdowns();
    setupEventListeners();
    updateProgress();
}
</script>