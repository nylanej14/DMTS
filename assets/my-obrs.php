<?php
// File: C:\inetpub\wwwroot\portal\my-obrs.php
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
$user_role = $_SESSION['user_role'] ?? $_SESSION['UserAccess'] ?? 'User';

// Check if user is admin (admin can see all OBRs)
$isAdmin = ($user_role === 'Admin' || $user_role === 'Administrator');

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

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My OBRs - Document Management & Tracking System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
        
        .content-section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .section-title {
            color: var(--navy);
            border-bottom: 2px solid var(--turq);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-submitted { background: #0d6efd; color: white; }
        .status-pending { background: #ffc107; color: black; }
        .status-approved { background: #198754; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-completed { background: #20c997; color: white; }
        
        .action-btn {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            margin: 2px;
        }
        
        .table th {
            background-color: var(--navy);
            color: white;
            border: none;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin: 10px 0;
        }
        
        .btn-create {
            background: var(--navy);
            color: white;
            border: none;
        }
        
        .btn-create:hover {
            background: var(--light-navy);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
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
            <h1 class="h2">
                <i class="fas fa-file-invoice-dollar"></i> 
                <?php echo $isAdmin ? 'All OBR Documents' : 'My OBR Documents'; ?>
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="create-obr.php" class="btn btn-create btn-sm">
                    <i class="fas fa-plus-circle"></i> Create New OBR
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <div id="messageContainer"></div>
        
        <!-- Filter Section -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="fas fa-filter"></i> Filter OBRs</h5>
            <form id="filterForm" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Draft" <?php echo $statusFilter === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="Submitted" <?php echo $statusFilter === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" id="dateFrom" 
                           value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" id="dateTo" 
                           value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" id="searchQuery" 
                               placeholder="Search OBR..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- OBR List Section -->
        <div class="content-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="section-title mb-0">
                    <i class="fas fa-list"></i> OBR Documents
                </h3>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshTable()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                </div>
            </div>
            
            <!-- OBR Table -->
            <div class="table-responsive">
                <table id="obrTable" class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>OBR Number</th>
                            <th>Payee</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            // Build the query based on user role and filters
                            $sql = "SELECT * FROM OBR WHERE 1=1";
                            $params = array();
                            
                            // If not admin, only show user's OBRs
                            if (!$isAdmin) {
                                $sql .= " AND CreatedBy = ?";
                                $params[] = $user_id;
                            }
                            
                            // Apply status filter
                            if ($statusFilter) {
                                $sql .= " AND Status = ?";
                                $params[] = $statusFilter;
                            }
                            
                            // Apply date range filter
                            if ($dateFrom) {
                                $sql .= " AND CONVERT(DATE, Datetime) >= ?";
                                $params[] = $dateFrom;
                            }
                            if ($dateTo) {
                                $sql .= " AND CONVERT(DATE, Datetime) <= ?";
                                $params[] = $dateTo;
                            }
                            
                            // Apply search filter
                            if ($searchQuery) {
                                $sql .= " AND (TrackingNumber LIKE ? OR Payee LIKE ? OR Department LIKE ?)";
                                $searchTerm = "%{$searchQuery}%";
                                $params[] = $searchTerm;
                                $params[] = $searchTerm;
                                $params[] = $searchTerm;
                            }
                            
                            // Order by date descending
                            $sql .= " ORDER BY Datetime DESC";
                            
                            // Execute query
                            $stmt = sqlsrv_query($conn, $sql, $params);
                            
                            if ($stmt) {
                                $rowCount = 0;
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $rowCount++;
                                    $statusClass = 'status-' . strtolower($row['Status']);
                                    $date = $row['Datetime']->format('M d, Y');
                                    $amount = number_format($row['TotalAmount'], 2);
                                    $obrID = $row['OBRID'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['TrackingNumber']); ?></strong>
                                            <?php if ($row['Status'] === 'Draft'): ?>
                                                <br><small class="text-muted">Draft - Not Submitted</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['Payee']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Department']); ?></td>
                                        <td><?php echo $date; ?></td>
                                        <td>₱ <?php echo $amount; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $row['Status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <!-- View Button -->
                                                <a href="view-obr.php?id=<?php echo $obrID; ?>" 
                                                   class="btn btn-info btn-sm action-btn" 
                                                   title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <!-- Edit Button (only for Draft) -->
                                                <?php if ($row['Status'] === 'Draft'): ?>
                                                <a href="edit-obr.php?id=<?php echo $obrID; ?>" 
                                                   class="btn btn-warning btn-sm action-btn" 
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <!-- Print Button -->
                                                <a href="print-obr.php?id=<?php echo $obrID; ?>" 
                                                   target="_blank"
                                                   class="btn btn-secondary btn-sm action-btn" 
                                                   title="Print">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                
                                                <!-- Track Button -->
                                                <a href="track-obr.php?tracking=<?php echo urlencode($row['TrackingNumber']); ?>" 
                                                   class="btn btn-primary btn-sm action-btn" 
                                                   title="Track">
                                                    <i class="fas fa-search-location"></i>
                                                </a>
                                                
                                                <!-- Delete Button (only for Draft) -->
                                                <?php if ($row['Status'] === 'Draft'): ?>
                                                <button onclick="confirmDelete(<?php echo $obrID; ?>, '<?php echo htmlspecialchars($row['TrackingNumber']); ?>')" 
                                                        class="btn btn-danger btn-sm action-btn" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                
                                // Show empty state if no OBRs found
                                if ($rowCount === 0) {
                                    echo '<tr><td colspan="7" class="text-center py-5">';
                                    echo '<div class="empty-state">';
                                    echo '<i class="fas fa-inbox"></i>';
                                    echo '<h4>No OBR documents found</h4>';
                                    echo '<p class="text-muted">';
                                    if ($searchQuery || $statusFilter || $dateFrom || $dateTo) {
                                        echo 'Try adjusting your filters or ';
                                    }
                                    echo 'Create your first OBR</p>';
                                    echo '<a href="create-obr.php" class="btn btn-create">';
                                    echo '<i class="fas fa-plus-circle"></i> Create New OBR';
                                    echo '</a>';
                                    echo '</div>';
                                    echo '</td></tr>';
                                }
                                
                                sqlsrv_free_stmt($stmt);
                            } else {
                                echo '<tr><td colspan="7" class="text-center text-danger py-3">';
                                echo 'Error loading OBR data. Please try again.';
                                echo '</td></tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" class="text-center text-danger py-3">';
                            echo 'Database connection failed. Please contact administrator.';
                            echo '</td></tr>';
                        }
                        
                        if (isset($conn)) {
                            sqlsrv_close($conn);
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Statistics -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-chart-pie"></i> OBR Statistics
                            </h6>
                            <div class="row text-center">
                                <?php
                                if ($conn = sqlsrv_connect($serverName, $connectionOptions)) {
                                    // Get total count
                                    $totalSql = "SELECT COUNT(*) as total FROM OBR";
                                    $totalParams = array();
                                    if (!$isAdmin) {
                                        $totalSql .= " WHERE CreatedBy = ?";
                                        $totalParams[] = $user_id;
                                    }
                                    
                                    $stmt = sqlsrv_query($conn, $totalSql, $totalParams);
                                    $total = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['total'] : 0;
                                    sqlsrv_free_stmt($stmt);
                                    
                                    // Get status counts
                                    $statuses = ['Draft', 'Submitted', 'Pending', 'Approved', 'Rejected', 'Completed'];
                                    foreach ($statuses as $status) {
                                        $statusSql = "SELECT COUNT(*) as count FROM OBR WHERE Status = ?";
                                        $statusParams = array($status);
                                        if (!$isAdmin) {
                                            $statusSql .= " AND CreatedBy = ?";
                                            $statusParams[] = $user_id;
                                        }
                                        
                                        $stmt = sqlsrv_query($conn, $statusSql, $statusParams);
                                        $count = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['count'] : 0;
                                        $percentage = $total > 0 ? round(($count / $total) * 100) : 0;
                                        
                                        echo '<div class="col-md-2 col-sm-4 col-6 mb-3">';
                                        echo '<div class="p-2">';
                                        echo '<div class="status-badge status-' . strtolower($status) . ' mb-2">' . $status . '</div>';
                                        echo '<h5 class="mb-1">' . $count . '</h5>';
                                        echo '<small class="text-muted">' . $percentage . '% of total</small>';
                                        echo '</div>';
                                        echo '</div>';
                                        
                                        sqlsrv_free_stmt($stmt);
                                    }
                                    
                                    sqlsrv_close($conn);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#obrTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[3, 'desc']], // Sort by date descending
                language: {
                    search: "Search within table:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
            
            // Auto-submit filter form when filters change
            $('#statusFilter, #dateFrom, #dateTo').change(function() {
                $('#filterForm').submit();
            });
            
            // Check for URL message parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('message')) {
                const message = urlParams.get('message');
                const type = urlParams.get('type') || 'success';
                showMessage(message, type);
            }
        });
        
        function showMessage(message, type = 'success') {
            const messageDiv = $('#messageContainer');
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'warning' ? 'alert-warning' : 'alert-danger';
            
            const alertHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            messageDiv.html(alertHTML);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                $('.alert').alert('close');
            }, 5000);
        }
        
        function resetFilters() {
            // Reset all filter inputs
            $('#statusFilter').val('');
            $('#dateFrom').val('');
            $('#dateTo').val('');
            $('#searchQuery').val('');
            
            // Submit the form (will reload with no filters)
            $('#filterForm').submit();
        }
        
        function refreshTable() {
            location.reload();
        }
        
        function confirmDelete(obrID, trackingNumber) {
            if (confirm(`Are you sure you want to delete OBR ${trackingNumber}? This action cannot be undone.`)) {
                deleteOBR(obrID);
            }
        }
        
        function deleteOBR(obrID) {
            // Show loading
            const originalText = $('.btn-danger i').parent().html();
            $('.btn-danger i').parent().html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: 'api/delete-obr.php',
                method: 'POST',
                data: { obrID: obrID },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showMessage('Error: ' + response.error, 'danger');
                        $('.btn-danger i').parent().html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Network error: ' + error, 'danger');
                    $('.btn-danger i').parent().html(originalText);
                }
            });
        }
        
        function exportToExcel() {
            // Get current filters
            const status = $('#statusFilter').val();
            const dateFrom = $('#dateFrom').val();
            const dateTo = $('#dateTo').val();
            const search = $('#searchQuery').val();
            
            // Build export URL
            let exportUrl = 'api/export-obrs.php?export=excel';
            if (status) exportUrl += '&status=' + encodeURIComponent(status);
            if (dateFrom) exportUrl += '&date_from=' + encodeURIComponent(dateFrom);
            if (dateTo) exportUrl += '&date_to=' + encodeURIComponent(dateTo);
            if (search) exportUrl += '&search=' + encodeURIComponent(search);
            
            // Open export URL in new window
            window.open(exportUrl, '_blank');
        }
        
        // Auto-refresh every 60 seconds if on the page
        setInterval(() => {
            if (document.hasFocus()) {
                const dataTable = $('#obrTable').DataTable();
                dataTable.ajax.reload(null, false); // false means don't reset paging
            }
        }, 60000);
    </script>
</body>
</html>