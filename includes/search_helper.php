<?php
/**
 * Search Helper Functions
 * 
 * Functions for global search query building, result formatting, and relevance scoring.
 */

/**
 * Ensure search history table exists
 */
function ensureSearchTables($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS search_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            query VARCHAR(255) NOT NULL,
            results_count INT DEFAULT 0,
            entity_types VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_query (query),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return true;
    } catch (Exception $e) {
        error_log('ensureSearchTables error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log a search query
 */
function logSearchQuery($pdo, $userId, $query, $resultsCount = 0, $entityTypes = []) {
    try {
        $stmt = $pdo->prepare("INSERT INTO search_history (user_id, query, results_count, entity_types) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $query, $resultsCount, implode(',', $entityTypes)]);
    } catch (Exception $e) {
        error_log('logSearchQuery error: ' . $e->getMessage());
    }
}

/**
 * Get recent searches for a user
 */
function getRecentSearches($pdo, $userId, $limit = 5) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT query, MAX(created_at) as last_searched 
            FROM search_history 
            WHERE user_id = ? 
            GROUP BY query 
            ORDER BY last_searched DESC 
            LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get popular searches
 */
function getPopularSearches($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("SELECT query, COUNT(*) as search_count 
            FROM search_history 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY query 
            ORDER BY search_count DESC 
            LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get searchable entity types based on user role
 */
function getSearchableEntities($userRole, $isAdmin = false) {
    $entities = [
        'projects' => ['label' => 'Projects', 'icon' => 'bi-kanban', 'color' => 'primary'],
        'tasks' => ['label' => 'Tasks', 'icon' => 'bi-clipboard-check', 'color' => 'info'],
        'invoices' => ['label' => 'Invoices', 'icon' => 'bi-receipt', 'color' => 'success'],
    ];
    
    if ($isAdmin || $userRole === 'admin') {
        $entities['users'] = ['label' => 'Users', 'icon' => 'bi-people', 'color' => 'secondary'];
        $entities['payments'] = ['label' => 'Payments', 'icon' => 'bi-credit-card', 'color' => 'warning'];
        $entities['esign_documents'] = ['label' => 'E-Sign Documents', 'icon' => 'bi-pen', 'color' => 'dark'];
    }
    
    $entities['messages'] = ['label' => 'Chat Messages', 'icon' => 'bi-chat-dots', 'color' => 'info'];
    $entities['files'] = ['label' => 'Files', 'icon' => 'bi-file-earmark', 'color' => 'secondary'];
    $entities['time_entries'] = ['label' => 'Time Entries', 'icon' => 'bi-clock', 'color' => 'info'];
    
    return $entities;
}

/**
 * Calculate relevance score for a search result
 */
function calculateRelevanceScore($query, $title, $description = '', $extraFields = []) {
    $query = strtolower(trim($query));
    $title = strtolower($title);
    $description = strtolower($description);
    
    $score = 0;
    
    // Exact title match (highest priority)
    if ($title === $query) {
        $score += 100;
    }
    // Title starts with query
    elseif (strpos($title, $query) === 0) {
        $score += 80;
    }
    // Title contains query
    elseif (strpos($title, $query) !== false) {
        $score += 60;
    }
    
    // Description contains query
    if (strpos($description, $query) !== false) {
        $score += 30;
    }
    
    // Check extra fields
    foreach ($extraFields as $field) {
        if (strpos(strtolower($field), $query) !== false) {
            $score += 20;
        }
    }
    
    // Word-based scoring for multi-word queries
    $queryWords = preg_split('/\s+/', $query);
    foreach ($queryWords as $word) {
        if (strlen($word) >= 2) {
            if (strpos($title, $word) !== false) {
                $score += 10;
            }
            if (strpos($description, $word) !== false) {
                $score += 5;
            }
        }
    }
    
    return $score;
}

/**
 * Highlight search terms in text
 */
function highlightSearchTerms($text, $query, $maxLength = 150) {
    $query = trim($query);
    if (empty($query) || empty($text)) {
        return htmlspecialchars(substr($text, 0, $maxLength)) . (strlen($text) > $maxLength ? '...' : '');
    }
    
    // Find position of query in text
    $pos = stripos($text, $query);
    
    // If found, show context around the match
    if ($pos !== false) {
        $start = max(0, $pos - 30);
        $end = min(strlen($text), $pos + strlen($query) + 70);
        $excerpt = substr($text, $start, $end - $start);
        
        if ($start > 0) $excerpt = '...' . $excerpt;
        if ($end < strlen($text)) $excerpt .= '...';
    } else {
        $excerpt = substr($text, 0, $maxLength);
        if (strlen($text) > $maxLength) $excerpt .= '...';
    }
    
    // Escape HTML and highlight matches
    $escaped = htmlspecialchars($excerpt);
    $pattern = '/(' . preg_quote(htmlspecialchars($query), '/') . ')/i';
    $highlighted = preg_replace($pattern, '<mark>$1</mark>', $escaped);
    
    return $highlighted;
}

/**
 * Perform global search across all entities
 */
function performGlobalSearch($pdo, $query, $userId, $userRole, $isAdmin = false, $entityFilter = null, $limit = 20, $offset = 0) {
    $results = [];
    $totalResults = 0;
    $query = trim($query);
    $like = '%' . $query . '%';
    
    if (strlen($query) < 2) {
        return ['results' => [], 'total' => 0];
    }
    
    $entities = getSearchableEntities($userRole, $isAdmin);
    
    // Filter to specific entity type if requested
    if ($entityFilter && isset($entities[$entityFilter])) {
        $entities = [$entityFilter => $entities[$entityFilter]];
    }
    
    try {
        // Search Projects
        if (isset($entities['projects'])) {
            $sql = "SELECT p.id, p.name as title, p.description, p.status, 
                    c.name as client_name, 'project' as entity_type
                    FROM projects p
                    LEFT JOIN users c ON c.id = p.client_id
                    WHERE (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
            $params = [$like, $like, $like];
            
            if (!$isAdmin && $userRole === 'client') {
                $sql .= " AND p.client_id = ?";
                $params[] = $userId;
            } elseif (!$isAdmin && $userRole === 'developer') {
                $sql .= " AND p.developer_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'project',
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'subtitle' => $row['client_name'] ?? '',
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'url' => ($isAdmin ? '/admin/' : '/' . $userRole . '/') . 'projects.php?id=' . $row['id'],
                    'icon' => $entities['projects']['icon'],
                    'color' => $entities['projects']['color'],
                    'score' => calculateRelevanceScore($query, $row['title'], $row['description'] ?? '', [$row['client_name'] ?? '']),
                ];
                $totalResults++;
            }
        }
        
        // Search Tasks
        if (isset($entities['tasks'])) {
            $sql = "SELECT t.id, t.title, t.description, t.status, t.priority,
                    p.name as project_name, u.name as assigned_to, 'task' as entity_type
                    FROM tasks t
                    LEFT JOIN projects p ON p.id = t.project_id
                    LEFT JOIN users u ON u.id = t.assigned_to
                    WHERE (t.title LIKE ? OR t.description LIKE ?)";
            $params = [$like, $like];
            
            if (!$isAdmin && $userRole === 'developer') {
                $sql .= " AND t.assigned_to = ?";
                $params[] = $userId;
            } elseif (!$isAdmin && $userRole === 'client') {
                $sql .= " AND p.client_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'task',
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'subtitle' => $row['project_name'] ?? '',
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'url' => ($isAdmin ? '/admin/' : '/' . $userRole . '/') . 'tasks.php?id=' . $row['id'],
                    'icon' => $entities['tasks']['icon'],
                    'color' => $entities['tasks']['color'],
                    'score' => calculateRelevanceScore($query, $row['title'], $row['description'] ?? ''),
                ];
                $totalResults++;
            }
        }
        
        // Search Invoices
        if (isset($entities['invoices'])) {
            $sql = "SELECT i.id, i.invoice_number, i.total, i.status, i.currency,
                    u.name as client_name, 'invoice' as entity_type
                    FROM invoices i
                    LEFT JOIN users u ON u.id = i.client_id
                    WHERE (i.invoice_number LIKE ? OR u.name LIKE ?)";
            $params = [$like, $like];
            
            if (!$isAdmin && $userRole === 'client') {
                $sql .= " AND i.client_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'invoice',
                    'id' => $row['id'],
                    'title' => '#' . $row['invoice_number'],
                    'subtitle' => $row['client_name'] ?? '',
                    'description' => ($row['currency'] ?? 'USD') . ' ' . number_format($row['total'], 2),
                    'status' => $row['status'],
                    'url' => '/invoice/view.php?id=' . $row['id'],
                    'icon' => $entities['invoices']['icon'],
                    'color' => $entities['invoices']['color'],
                    'score' => calculateRelevanceScore($query, $row['invoice_number'], $row['client_name'] ?? ''),
                ];
                $totalResults++;
            }
        }
        
        // Search Users (admin only)
        if (isset($entities['users']) && $isAdmin) {
            $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 10");
            $stmt->execute([$like, $like]);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'user',
                    'id' => $row['id'],
                    'title' => $row['name'],
                    'subtitle' => $row['email'],
                    'description' => ucfirst($row['role']),
                    'url' => '/admin/users.php?id=' . $row['id'],
                    'icon' => $entities['users']['icon'],
                    'color' => $entities['users']['color'],
                    'score' => calculateRelevanceScore($query, $row['name'], $row['email']),
                ];
                $totalResults++;
            }
        }
        
        // Search E-Sign Documents
        if (isset($entities['esign_documents'])) {
            $sql = "SELECT id, title, description, status FROM esign_documents WHERE title LIKE ? OR description LIKE ?";
            $params = [$like, $like];
            
            if (!$isAdmin) {
                $sql .= " AND created_by = ?";
                $params[] = $userId;
            }
            
            $sql .= " LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'esign_document',
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'subtitle' => 'E-Signature Document',
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'url' => '/esign/index.php?id=' . $row['id'],
                    'icon' => $entities['esign_documents']['icon'],
                    'color' => $entities['esign_documents']['color'],
                    'score' => calculateRelevanceScore($query, $row['title'], $row['description'] ?? ''),
                ];
                $totalResults++;
            }
        }
        
        // Search Chat Messages
        if (isset($entities['messages'])) {
            $sql = "SELECT m.id, m.message, m.conversation_id, m.created_at,
                    u.name as sender_name
                    FROM messages m
                    LEFT JOIN users u ON u.id = m.sender_id
                    JOIN conversation_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
                    WHERE m.message LIKE ? AND m.message_type = 'text'
                    ORDER BY m.created_at DESC
                    LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $like]);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'message',
                    'id' => $row['id'],
                    'title' => $row['sender_name'] ?? 'Message',
                    'subtitle' => date('M j, Y', strtotime($row['created_at'])),
                    'description' => $row['message'],
                    'url' => ($isAdmin ? '/admin/' : '/' . $userRole . '/') . 'chat.php?c=' . $row['conversation_id'],
                    'icon' => $entities['messages']['icon'],
                    'color' => $entities['messages']['color'],
                    'score' => calculateRelevanceScore($query, $row['message'], ''),
                ];
                $totalResults++;
            }
        }
        
        // Search Files
        if (isset($entities['files'])) {
            $sql = "SELECT pf.id, pf.file_name, pf.file_path, pf.created_at,
                    p.name as project_name, p.id as project_id
                    FROM project_files pf
                    LEFT JOIN projects p ON p.id = pf.project_id
                    WHERE pf.file_name LIKE ?";
            $params = [$like];
            
            if (!$isAdmin && $userRole === 'client') {
                $sql .= " AND p.client_id = ?";
                $params[] = $userId;
            } elseif (!$isAdmin && $userRole === 'developer') {
                $sql .= " AND p.developer_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'file',
                    'id' => $row['id'],
                    'title' => $row['file_name'],
                    'subtitle' => $row['project_name'] ?? 'File',
                    'description' => '',
                    'url' => '/' . $row['file_path'],
                    'icon' => $entities['files']['icon'],
                    'color' => $entities['files']['color'],
                    'score' => calculateRelevanceScore($query, $row['file_name'], ''),
                ];
                $totalResults++;
            }
        }
        
        // Search Time Entries
        if (isset($entities['time_entries'])) {
            $sql = "SELECT te.id, te.description, te.start_time,
                    p.name as project_name, t.title as task_title
                    FROM time_entries te
                    LEFT JOIN projects p ON p.id = te.project_id
                    LEFT JOIN tasks t ON t.id = te.task_id
                    WHERE te.description LIKE ?";
            $params = [$like];
            
            if (!$isAdmin && $userRole === 'developer') {
                $sql .= " AND te.user_id = ?";
                $params[] = $userId;
            } elseif (!$isAdmin && $userRole === 'client') {
                $sql .= " AND p.client_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'entity_type' => 'time_entry',
                    'id' => $row['id'],
                    'title' => $row['project_name'] ?? 'Time Entry',
                    'subtitle' => $row['task_title'] ?? date('M j, Y', strtotime($row['start_time'])),
                    'description' => $row['description'],
                    'url' => ($isAdmin ? '/admin/' : '/' . $userRole . '/') . 'time_tracking.php',
                    'icon' => $entities['time_entries']['icon'],
                    'color' => $entities['time_entries']['color'],
                    'score' => calculateRelevanceScore($query, $row['description'] ?? '', $row['project_name'] ?? ''),
                ];
                $totalResults++;
            }
        }
        
        // Sort by relevance score
        usort($results, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Apply pagination
        $results = array_slice($results, $offset, $limit);
        
    } catch (Exception $e) {
        error_log('performGlobalSearch error: ' . $e->getMessage());
    }
    
    return ['results' => $results, 'total' => $totalResults];
}

/**
 * Format search result for API response
 */
function formatSearchResult($result, $query) {
    return [
        'type' => $result['entity_type'],
        'id' => $result['id'],
        'label' => htmlspecialchars($result['title']),
        'meta' => htmlspecialchars($result['subtitle'] ?? ''),
        'description' => highlightSearchTerms($result['description'] ?? '', $query, 100),
        'status' => $result['status'] ?? null,
        'url' => $result['url'],
        'icon' => $result['icon'],
        'color' => $result['color'],
    ];
}

/**
 * Get entity type label
 */
function getEntityTypeLabel($entityType) {
    $labels = [
        'project' => 'Project',
        'task' => 'Task',
        'invoice' => 'Invoice',
        'user' => 'User',
        'payment' => 'Payment',
        'message' => 'Message',
        'file' => 'File',
        'time_entry' => 'Time Entry',
        'esign_document' => 'E-Sign Document',
    ];
    return $labels[$entityType] ?? ucfirst(str_replace('_', ' ', $entityType));
}

/**
 * Group search results by entity type
 */
function groupSearchResults($results) {
    $grouped = [];
    foreach ($results as $result) {
        $type = $result['entity_type'];
        if (!isset($grouped[$type])) {
            $grouped[$type] = [
                'label' => getEntityTypeLabel($type),
                'icon' => $result['icon'],
                'color' => $result['color'],
                'results' => [],
            ];
        }
        $grouped[$type]['results'][] = $result;
    }
    return $grouped;
}
