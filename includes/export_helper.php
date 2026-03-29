<?php
/**
 * Export Helper Functions
 * 
 * Core functions for CSV, Excel, and PDF export generation.
 */

/**
 * Ensure export database tables exist
 */
function ensureExportTables($pdo) {
    try {
        // Export history
        $pdo->exec("CREATE TABLE IF NOT EXISTS export_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            export_type ENUM('csv','excel','pdf') NOT NULL,
            data_type VARCHAR(50) NOT NULL,
            filters JSON,
            file_path VARCHAR(500),
            file_name VARCHAR(255),
            file_size INT DEFAULT 0,
            record_count INT DEFAULT 0,
            status ENUM('pending','completed','failed') DEFAULT 'completed',
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_data_type (data_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Scheduled exports
        $pdo->exec("CREATE TABLE IF NOT EXISTS scheduled_exports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            data_type VARCHAR(50) NOT NULL,
            export_type ENUM('csv','excel','pdf') NOT NULL DEFAULT 'csv',
            filters JSON,
            columns JSON,
            frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
            next_run DATETIME NOT NULL,
            last_run DATETIME DEFAULT NULL,
            email_to VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_next_run (next_run),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return true;
    } catch (Exception $e) {
        error_log('ensureExportTables error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get exportable data types with their labels
 */
function getExportableDataTypes() {
    return [
        'projects'     => ['label' => 'Projects', 'icon' => 'bi-kanban'],
        'tasks'        => ['label' => 'Tasks', 'icon' => 'bi-clipboard-check'],
        'invoices'     => ['label' => 'Invoices', 'icon' => 'bi-receipt'],
        'payments'     => ['label' => 'Payments', 'icon' => 'bi-credit-card'],
        'users'        => ['label' => 'Users', 'icon' => 'bi-people'],
        'time_entries' => ['label' => 'Time Entries', 'icon' => 'bi-clock'],
        'reports'      => ['label' => 'Reports Summary', 'icon' => 'bi-graph-up'],
    ];
}

/**
 * Get available columns for a data type
 */
function getExportColumns($dataType) {
    $columns = [
        'projects' => [
            'id' => 'ID',
            'name' => 'Project Name',
            'description' => 'Description',
            'client_name' => 'Client',
            'developer_name' => 'Developer',
            'status' => 'Status',
            'progress' => 'Progress %',
            'budget' => 'Budget',
            'deadline' => 'Deadline',
            'created_at' => 'Created Date',
        ],
        'tasks' => [
            'id' => 'ID',
            'title' => 'Task Title',
            'description' => 'Description',
            'project_name' => 'Project',
            'assigned_to_name' => 'Assigned To',
            'status' => 'Status',
            'priority' => 'Priority',
            'due_date' => 'Due Date',
            'completed_at' => 'Completed Date',
            'created_at' => 'Created Date',
        ],
        'invoices' => [
            'id' => 'ID',
            'invoice_number' => 'Invoice #',
            'client_name' => 'Client',
            'project_name' => 'Project',
            'subtotal' => 'Subtotal',
            'tax_amount' => 'Tax',
            'discount' => 'Discount',
            'total' => 'Total',
            'currency' => 'Currency',
            'status' => 'Status',
            'issue_date' => 'Issue Date',
            'due_date' => 'Due Date',
            'paid_at' => 'Paid Date',
        ],
        'payments' => [
            'id' => 'ID',
            'invoice_number' => 'Invoice #',
            'client_name' => 'Client',
            'amount' => 'Amount',
            'gateway' => 'Gateway',
            'transaction_id' => 'Transaction ID',
            'status' => 'Status',
            'created_at' => 'Payment Date',
        ],
        'users' => [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'phone' => 'Phone',
            'is_active' => 'Active',
            'created_at' => 'Registered Date',
        ],
        'time_entries' => [
            'id' => 'ID',
            'user_name' => 'User',
            'project_name' => 'Project',
            'task_title' => 'Task',
            'description' => 'Description',
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'duration_minutes' => 'Duration (min)',
            'is_approved' => 'Approved',
        ],
        'reports' => [
            'metric' => 'Metric',
            'value' => 'Value',
            'period' => 'Period',
        ],
    ];
    return $columns[$dataType] ?? [];
}

/**
 * Fetch data for export
 */
function fetchExportData($pdo, $dataType, $filters = [], $columns = [], $userId = null, $userRole = 'admin') {
    $isAdmin = ($userRole === 'admin');
    $data = [];

    try {
        switch ($dataType) {
            case 'projects':
                $sql = "SELECT p.*, 
                        c.name as client_name, 
                        d.name as developer_name
                        FROM projects p
                        LEFT JOIN users c ON c.id = p.client_id
                        LEFT JOIN users d ON d.id = p.developer_id
                        WHERE 1=1";
                $params = [];
                
                if (!$isAdmin && $userRole === 'client') {
                    $sql .= " AND p.client_id = ?";
                    $params[] = $userId;
                } elseif (!$isAdmin && $userRole === 'developer') {
                    $sql .= " AND p.developer_id = ?";
                    $params[] = $userId;
                }
                
                if (!empty($filters['status'])) {
                    $sql .= " AND p.status = ?";
                    $params[] = $filters['status'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND p.created_at >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND p.created_at <= ?";
                    $params[] = $filters['date_to'] . ' 23:59:59';
                }
                
                $sql .= " ORDER BY p.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll();
                break;

            case 'tasks':
                $sql = "SELECT t.*, 
                        p.name as project_name,
                        u.name as assigned_to_name
                        FROM tasks t
                        LEFT JOIN projects p ON p.id = t.project_id
                        LEFT JOIN users u ON u.id = t.assigned_to
                        WHERE 1=1";
                $params = [];
                
                if (!$isAdmin && $userRole === 'developer') {
                    $sql .= " AND t.assigned_to = ?";
                    $params[] = $userId;
                } elseif (!$isAdmin && $userRole === 'client') {
                    $sql .= " AND p.client_id = ?";
                    $params[] = $userId;
                }
                
                if (!empty($filters['status'])) {
                    $sql .= " AND t.status = ?";
                    $params[] = $filters['status'];
                }
                if (!empty($filters['priority'])) {
                    $sql .= " AND t.priority = ?";
                    $params[] = $filters['priority'];
                }
                if (!empty($filters['project_id'])) {
                    $sql .= " AND t.project_id = ?";
                    $params[] = $filters['project_id'];
                }
                
                $sql .= " ORDER BY t.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll();
                break;

            case 'invoices':
                $sql = "SELECT i.*, 
                        u.name as client_name,
                        p.name as project_name
                        FROM invoices i
                        LEFT JOIN users u ON u.id = i.client_id
                        LEFT JOIN projects p ON p.id = i.project_id
                        WHERE 1=1";
                $params = [];
                
                if (!$isAdmin && $userRole === 'client') {
                    $sql .= " AND i.client_id = ?";
                    $params[] = $userId;
                }
                
                if (!empty($filters['status'])) {
                    $sql .= " AND i.status = ?";
                    $params[] = $filters['status'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND i.created_at >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND i.created_at <= ?";
                    $params[] = $filters['date_to'] . ' 23:59:59';
                }
                
                $sql .= " ORDER BY i.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll();
                break;

            case 'payments':
                $sql = "SELECT py.*, 
                        i.invoice_number,
                        u.name as client_name
                        FROM payments py
                        LEFT JOIN invoices i ON i.id = py.invoice_id
                        LEFT JOIN users u ON u.id = py.client_id
                        WHERE 1=1";
                $params = [];
                
                if (!$isAdmin && $userRole === 'client') {
                    $sql .= " AND py.client_id = ?";
                    $params[] = $userId;
                }
                
                if (!empty($filters['gateway'])) {
                    $sql .= " AND py.gateway = ?";
                    $params[] = $filters['gateway'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND py.created_at >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND py.created_at <= ?";
                    $params[] = $filters['date_to'] . ' 23:59:59';
                }
                
                $sql .= " ORDER BY py.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll();
                break;

            case 'users':
                if (!$isAdmin) {
                    return []; // Only admin can export users
                }
                
                $sql = "SELECT id, name, email, role, phone, is_active, created_at FROM users WHERE 1=1";
                $params = [];
                
                if (!empty($filters['role'])) {
                    $sql .= " AND role = ?";
                    $params[] = $filters['role'];
                }
                
                $sql .= " ORDER BY created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll();
                break;

            case 'time_entries':
                $sql = "SELECT te.*, 
                        u.name as user_name,
                        p.name as project_name,
                        t.title as task_title
                        FROM time_entries te
                        LEFT JOIN users u ON u.id = te.user_id
                        LEFT JOIN projects p ON p.id = te.project_id
                        LEFT JOIN tasks t ON t.id = te.task_id
                        WHERE 1=1";
                $params = [];
                
                if (!$isAdmin && $userRole === 'developer') {
                    $sql .= " AND te.user_id = ?";
                    $params[] = $userId;
                } elseif (!$isAdmin && $userRole === 'client') {
                    $sql .= " AND p.client_id = ?";
                    $params[] = $userId;
                }
                
                if (!empty($filters['date_from'])) {
                    $sql .= " AND te.start_time >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND te.start_time <= ?";
                    $params[] = $filters['date_to'] . ' 23:59:59';
                }
                if (!empty($filters['project_id'])) {
                    $sql .= " AND te.project_id = ?";
                    $params[] = $filters['project_id'];
                }
                
                $sql .= " ORDER BY te.start_time DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll();
                break;

            case 'reports':
                // Generate summary report data
                if (!$isAdmin) return [];
                
                $data = [];
                
                // Project stats
                $stmt = $pdo->query("SELECT COUNT(*) as total, status FROM projects GROUP BY status");
                foreach ($stmt->fetchAll() as $row) {
                    $data[] = ['metric' => 'Projects - ' . ucfirst($row['status']), 'value' => $row['total'], 'period' => 'All Time'];
                }
                
                // Revenue
                $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as revenue FROM invoices WHERE status = 'paid'");
                $data[] = ['metric' => 'Total Revenue', 'value' => '$' . number_format($stmt->fetchColumn(), 2), 'period' => 'All Time'];
                
                // User counts
                $stmt = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
                foreach ($stmt->fetchAll() as $row) {
                    $data[] = ['metric' => 'Users - ' . ucfirst($row['role']), 'value' => $row['cnt'], 'period' => 'All Time'];
                }
                break;
        }
    } catch (Exception $e) {
        error_log('fetchExportData error: ' . $e->getMessage());
    }

    return $data;
}

/**
 * Generate CSV content
 */
function generateCsvContent($data, $columns) {
    if (empty($data)) return '';
    
    $output = fopen('php://temp', 'r+');
    
    // UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write headers
    if (!empty($columns)) {
        fputcsv($output, array_values($columns));
    } else {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Write data
    foreach ($data as $row) {
        $rowData = [];
        if (!empty($columns)) {
            foreach (array_keys($columns) as $col) {
                $value = $row[$col] ?? '';
                // Clean up value
                if (is_array($value)) $value = json_encode($value);
                $rowData[] = $value;
            }
        } else {
            $rowData = array_values($row);
        }
        fputcsv($output, $rowData);
    }
    
    rewind($output);
    $content = stream_get_contents($output);
    fclose($output);
    
    return $content;
}

/**
 * Generate Excel (tab-separated) content
 */
function generateExcelContent($data, $columns) {
    if (empty($data)) return '';
    
    $lines = [];
    
    // Headers
    if (!empty($columns)) {
        $lines[] = implode("\t", array_values($columns));
    } else {
        $lines[] = implode("\t", array_keys($data[0]));
    }
    
    // Data rows
    foreach ($data as $row) {
        $rowData = [];
        if (!empty($columns)) {
            foreach (array_keys($columns) as $col) {
                $value = $row[$col] ?? '';
                if (is_array($value)) $value = json_encode($value);
                // Escape tabs and newlines
                $value = str_replace(["\t", "\n", "\r"], [' ', ' ', ''], $value);
                $rowData[] = $value;
            }
        } else {
            foreach ($row as $value) {
                if (is_array($value)) $value = json_encode($value);
                $value = str_replace(["\t", "\n", "\r"], [' ', ' ', ''], $value);
                $rowData[] = $value;
            }
        }
        $lines[] = implode("\t", $rowData);
    }
    
    return implode("\r\n", $lines);
}

/**
 * Generate PDF content (HTML-based)
 */
function generatePdfHtml($data, $columns, $title, $filters = []) {
    if (empty($data)) {
        return '<html><body><h1>' . htmlspecialchars($title) . '</h1><p>No data to display.</p></body></html>';
    }
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        h1 { color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
        .meta { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #0d6efd; color: white; padding: 10px; text-align: left; font-size: 11px; }
        td { border: 1px solid #ddd; padding: 8px; font-size: 11px; }
        tr:nth-child(even) { background: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; color: #999; font-size: 10px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($title) . '</h1>
    <div class="meta">
        <p>Generated: ' . date('F j, Y \a\t g:i A') . '</p>
        <p>Total Records: ' . count($data) . '</p>';
    
    if (!empty($filters)) {
        $filterText = [];
        foreach ($filters as $k => $v) {
            if (!empty($v)) {
                $filterText[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . htmlspecialchars($v);
            }
        }
        if (!empty($filterText)) {
            $html .= '<p>Filters: ' . implode(', ', $filterText) . '</p>';
        }
    }
    
    $html .= '</div>
    <table>
        <thead>
            <tr>';
    
    if (!empty($columns)) {
        foreach ($columns as $label) {
            $html .= '<th>' . htmlspecialchars($label) . '</th>';
        }
    } else {
        foreach (array_keys($data[0]) as $col) {
            $html .= '<th>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $col))) . '</th>';
        }
    }
    
    $html .= '</tr>
        </thead>
        <tbody>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        if (!empty($columns)) {
            foreach (array_keys($columns) as $col) {
                $value = $row[$col] ?? '';
                if (is_array($value)) $value = json_encode($value);
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
        } else {
            foreach ($row as $value) {
                if (is_array($value)) $value = json_encode($value);
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
    </table>
    <div class="footer">
        <p>SoftandPix - Generated Report</p>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Save export file and log history
 */
function saveExportFile($pdo, $userId, $dataType, $exportType, $content, $filters = [], $recordCount = 0) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $exportDir = $basePath . '/uploads/exports';
    
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }
    
    $extensions = ['csv' => 'csv', 'excel' => 'xls', 'pdf' => 'html'];
    $ext = $extensions[$exportType] ?? 'txt';
    $filename = $dataType . '_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $exportDir . '/' . $filename;
    
    if (file_put_contents($filepath, $content) !== false) {
        $fileSize = filesize($filepath);
        $relativePath = 'uploads/exports/' . $filename;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO export_history (user_id, export_type, data_type, filters, file_path, file_name, file_size, record_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $exportType,
                $dataType,
                json_encode($filters),
                $relativePath,
                $filename,
                $fileSize,
                $recordCount
            ]);
            return ['success' => true, 'path' => $relativePath, 'filename' => $filename, 'size' => $fileSize];
        } catch (Exception $e) {
            error_log('saveExportFile log error: ' . $e->getMessage());
        }
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

/**
 * Get export history for a user
 */
function getExportHistory($pdo, $userId, $isAdmin = false, $limit = 50) {
    $sql = "SELECT * FROM export_history";
    $params = [];
    
    if (!$isAdmin) {
        $sql .= " WHERE user_id = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}
