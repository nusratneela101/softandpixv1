<?php
/**
 * E-Signature Helper Functions
 * 
 * Functions for generating unique document hashes, embedding signatures in PDFs,
 * verifying signatures, and sending signing request emails.
 */

/**
 * Ensure e-signature database tables exist
 */
function ensureEsignTables($pdo) {
    try {
        // E-signature documents
        $pdo->exec("CREATE TABLE IF NOT EXISTS esign_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            content LONGTEXT,
            file_path VARCHAR(500),
            created_by INT NOT NULL,
            status ENUM('draft','pending','signed','expired','revoked') DEFAULT 'draft',
            unique_hash VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            INDEX idx_status (status),
            INDEX idx_hash (unique_hash),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // E-signature signatures
        $pdo->exec("CREATE TABLE IF NOT EXISTS esign_signatures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            signer_id INT DEFAULT NULL,
            signer_name VARCHAR(255) NOT NULL,
            signer_email VARCHAR(255) NOT NULL,
            signature_data LONGTEXT,
            signature_type ENUM('draw','type','upload') DEFAULT 'draw',
            signature_ip VARCHAR(45),
            signed_at DATETIME DEFAULT NULL,
            status ENUM('pending','signed','declined') DEFAULT 'pending',
            decline_reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_document (document_id),
            INDEX idx_signer (signer_id),
            INDEX idx_status (status),
            FOREIGN KEY (document_id) REFERENCES esign_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // E-signature templates
        $pdo->exec("CREATE TABLE IF NOT EXISTS esign_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            content LONGTEXT NOT NULL,
            category VARCHAR(100) DEFAULT 'general',
            created_by INT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // E-signature audit log
        $pdo->exec("CREATE TABLE IF NOT EXISTS esign_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            actor_id INT DEFAULT NULL,
            actor_name VARCHAR(255),
            actor_ip VARCHAR(45),
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_document (document_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at),
            FOREIGN KEY (document_id) REFERENCES esign_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return true;
    } catch (Exception $e) {
        error_log('ensureEsignTables error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate a unique document hash
 * 
 * @return string 64-character hex hash
 */
function generateDocumentHash() {
    return bin2hex(random_bytes(32));
}

/**
 * Generate a short verification code for easier sharing
 * 
 * @param string $hash The full document hash
 * @return string 8-character code
 */
function getShortVerificationCode($hash) {
    return strtoupper(substr($hash, 0, 8));
}

/**
 * Log an e-signature audit event
 * 
 * @param PDO    $pdo
 * @param int    $documentId
 * @param string $action     Action name (created, viewed, signed, declined, revoked, etc.)
 * @param int|null $actorId  User ID performing the action
 * @param string $details    Additional details
 */
function logEsignAudit($pdo, $documentId, $action, $actorId = null, $details = '') {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        if ($ip && strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        // Get actor name
        $actorName = 'System';
        if ($actorId) {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$actorId]);
            $actorName = $stmt->fetchColumn() ?: 'Unknown User';
        }

        $stmt = $pdo->prepare("INSERT INTO esign_audit_log (document_id, action, actor_id, actor_name, actor_ip, details) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$documentId, $action, $actorId, $actorName, $ip, $details]);
    } catch (Exception $e) {
        error_log('logEsignAudit error: ' . $e->getMessage());
    }
}

/**
 * Send a signing request email
 * 
 * @param PDO    $pdo
 * @param array  $document    Document record
 * @param array  $signature   Signature request record
 * @param string $siteUrl     Base site URL
 * @return bool
 */
function sendSigningRequestEmail($pdo, $document, $signature, $siteUrl) {
    $signerEmail = $signature['signer_email'];
    $signerName = $signature['signer_name'];
    $docTitle = $document['title'];
    $signUrl = rtrim($siteUrl, '/') . '/esign/sign.php?id=' . (int)$signature['id'] . '&token=' . urlencode($document['unique_hash']);
    
    $subject = "Signature Requested: {$docTitle}";
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .btn { display: inline-block; background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📝 Signature Request</h2>
            </div>
            <div class='content'>
                <p>Hello {$signerName},</p>
                <p>You have been requested to sign the following document:</p>
                <p><strong>{$docTitle}</strong></p>
                " . (!empty($document['description']) ? "<p>" . htmlspecialchars($document['description']) . "</p>" : "") . "
                <p>Please click the button below to review and sign the document:</p>
                <p style='text-align: center;'>
                    <a href='{$signUrl}' class='btn'>Review & Sign Document</a>
                </p>
                <p><small>If the button doesn't work, copy and paste this link into your browser:<br>{$signUrl}</small></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from SoftandPix.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return send_email($signerEmail, $signerName, $subject, $body);
}

/**
 * Check if a document is expired
 * 
 * @param array $document Document record
 * @return bool
 */
function isDocumentExpired($document) {
    if (empty($document['expires_at'])) {
        return false;
    }
    return strtotime($document['expires_at']) < time();
}

/**
 * Get document status badge class
 * 
 * @param string $status
 * @return string Bootstrap badge class
 */
function getEsignStatusBadge($status) {
    $badges = [
        'draft'    => 'secondary',
        'pending'  => 'warning',
        'signed'   => 'success',
        'expired'  => 'danger',
        'revoked'  => 'dark',
    ];
    return $badges[$status] ?? 'secondary';
}

/**
 * Get signature status badge class
 * 
 * @param string $status
 * @return string Bootstrap badge class
 */
function getSignatureStatusBadge($status) {
    $badges = [
        'pending'  => 'warning',
        'signed'   => 'success',
        'declined' => 'danger',
    ];
    return $badges[$status] ?? 'secondary';
}

/**
 * Generate HTML for viewing/printing a signed document
 * 
 * @param array  $document   Document record
 * @param array  $signatures Array of signature records
 * @param string $siteUrl    Base site URL
 * @return string HTML content
 */
function generateSignedDocumentHtml($document, $signatures, $siteUrl) {
    $verifyUrl = rtrim($siteUrl, '/') . '/esign/verify.php?hash=' . urlencode($document['unique_hash']);
    $signedSignatures = array_filter($signatures, function($s) { return $s['status'] === 'signed'; });

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($document['title']) . '</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .document-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .document-title { font-size: 24px; font-weight: bold; margin: 0; }
        .document-meta { color: #666; font-size: 14px; margin-top: 10px; }
        .document-content { margin-bottom: 40px; }
        .signatures-section { margin-top: 40px; border-top: 2px solid #333; padding-top: 20px; }
        .signature-block { display: inline-block; margin: 20px 30px 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .signature-image { max-width: 200px; max-height: 80px; }
        .signature-typed { font-family: "Brush Script MT", cursive; font-size: 32px; color: #00008B; }
        .signature-info { font-size: 12px; color: #666; margin-top: 10px; }
        .verification { background: #f0f9ff; border: 1px solid #0d6efd; border-radius: 8px; padding: 15px; margin-top: 30px; }
        .verification-code { font-family: monospace; font-size: 14px; background: #e9ecef; padding: 5px 10px; border-radius: 4px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="document-header">
        <h1 class="document-title">' . htmlspecialchars($document['title']) . '</h1>
        <div class="document-meta">
            <div>Document ID: <strong>' . getShortVerificationCode($document['unique_hash']) . '</strong></div>
            <div>Created: ' . date('F j, Y', strtotime($document['created_at'])) . '</div>
            <div>Status: <strong>' . ucfirst($document['status']) . '</strong></div>
        </div>
    </div>
    
    <div class="document-content">
        ' . $document['content'] . '
    </div>';

    if (!empty($signedSignatures)) {
        $html .= '<div class="signatures-section">
            <h3>Signatures</h3>';
        
        foreach ($signedSignatures as $sig) {
            $html .= '<div class="signature-block">';
            
            if ($sig['signature_type'] === 'type') {
                $html .= '<div class="signature-typed">' . htmlspecialchars($sig['signer_name']) . '</div>';
            } elseif (!empty($sig['signature_data'])) {
                $html .= '<img src="' . $sig['signature_data'] . '" class="signature-image" alt="Signature">';
            }
            
            $html .= '<div class="signature-info">
                    <div><strong>' . htmlspecialchars($sig['signer_name']) . '</strong></div>
                    <div>' . htmlspecialchars($sig['signer_email']) . '</div>
                    <div>Signed: ' . date('F j, Y \a\t g:i A', strtotime($sig['signed_at'])) . '</div>
                    <div>IP: ' . htmlspecialchars($sig['signature_ip'] ?? 'N/A') . '</div>
                </div>
            </div>';
        }
        
        $html .= '</div>';
    }

    $html .= '<div class="verification">
        <strong>🔒 Document Verification</strong>
        <p>This document has been digitally signed and can be verified at:</p>
        <p><a href="' . $verifyUrl . '">' . $verifyUrl . '</a></p>
        <p>Verification Code: <span class="verification-code">' . $document['unique_hash'] . '</span></p>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Get default e-signature templates
 * 
 * @return array
 */
function getDefaultEsignTemplates() {
    return [
        [
            'name' => 'Non-Disclosure Agreement (NDA)',
            'category' => 'legal',
            'description' => 'Standard NDA template for confidential information',
            'content' => '<h2>NON-DISCLOSURE AGREEMENT</h2>
<p>This Non-Disclosure Agreement ("Agreement") is entered into as of <strong>[DATE]</strong> between:</p>
<p><strong>Disclosing Party:</strong> [COMPANY NAME]</p>
<p><strong>Receiving Party:</strong> [RECIPIENT NAME]</p>

<h3>1. Definition of Confidential Information</h3>
<p>For purposes of this Agreement, "Confidential Information" means any data or information that is proprietary to the Disclosing Party and not generally known to the public.</p>

<h3>2. Obligations of Receiving Party</h3>
<p>The Receiving Party agrees to:</p>
<ul>
    <li>Hold and maintain the Confidential Information in strict confidence</li>
    <li>Not use the Confidential Information for any purpose other than the purpose stated herein</li>
    <li>Not disclose any Confidential Information to third parties without prior written consent</li>
</ul>

<h3>3. Term</h3>
<p>This Agreement shall remain in effect for a period of <strong>[DURATION]</strong> years from the date of execution.</p>

<h3>4. Return of Materials</h3>
<p>Upon termination of this Agreement, the Receiving Party shall return all materials containing Confidential Information.</p>

<p>IN WITNESS WHEREOF, the parties have executed this Agreement as of the date first written above.</p>'
        ],
        [
            'name' => 'Service Agreement',
            'category' => 'services',
            'description' => 'General service agreement template',
            'content' => '<h2>SERVICE AGREEMENT</h2>
<p>This Service Agreement ("Agreement") is made effective as of <strong>[DATE]</strong></p>

<h3>BETWEEN:</h3>
<p><strong>Service Provider:</strong> [PROVIDER NAME]</p>
<p><strong>Client:</strong> [CLIENT NAME]</p>

<h3>1. Services</h3>
<p>The Service Provider agrees to provide the following services:</p>
<p>[DESCRIPTION OF SERVICES]</p>

<h3>2. Compensation</h3>
<p>The Client agrees to pay the Service Provider:</p>
<p><strong>Total Amount:</strong> $[AMOUNT]</p>
<p><strong>Payment Terms:</strong> [PAYMENT TERMS]</p>

<h3>3. Term</h3>
<p>This Agreement begins on <strong>[START DATE]</strong> and continues until <strong>[END DATE]</strong>.</p>

<h3>4. Termination</h3>
<p>Either party may terminate this Agreement with [NOTICE PERIOD] written notice.</p>

<h3>5. Independent Contractor</h3>
<p>The Service Provider is an independent contractor and not an employee of the Client.</p>

<p>The parties agree to the terms and conditions set forth above.</p>'
        ],
        [
            'name' => 'Freelancer Contract',
            'category' => 'employment',
            'description' => 'Contract template for freelance work',
            'content' => '<h2>FREELANCER CONTRACT</h2>
<p>Date: <strong>[DATE]</strong></p>

<h3>PARTIES</h3>
<p><strong>Client:</strong> [CLIENT NAME]</p>
<p><strong>Freelancer:</strong> [FREELANCER NAME]</p>

<h3>1. Project Description</h3>
<p>[PROJECT DESCRIPTION]</p>

<h3>2. Deliverables</h3>
<ul>
    <li>[DELIVERABLE 1]</li>
    <li>[DELIVERABLE 2]</li>
    <li>[DELIVERABLE 3]</li>
</ul>

<h3>3. Timeline</h3>
<p><strong>Start Date:</strong> [START DATE]</p>
<p><strong>Deadline:</strong> [DEADLINE]</p>

<h3>4. Compensation</h3>
<p><strong>Project Fee:</strong> $[AMOUNT]</p>
<p><strong>Payment Schedule:</strong> [PAYMENT SCHEDULE]</p>

<h3>5. Intellectual Property</h3>
<p>Upon full payment, all work product and intellectual property rights shall be transferred to the Client.</p>

<h3>6. Confidentiality</h3>
<p>The Freelancer agrees to keep all project information confidential.</p>

<h3>7. Revisions</h3>
<p>This contract includes [NUMBER] rounds of revisions.</p>

<p>Both parties agree to the terms outlined above.</p>'
        ],
        [
            'name' => 'Statement of Work (SOW)',
            'category' => 'project',
            'description' => 'Project scope and deliverables document',
            'content' => '<h2>STATEMENT OF WORK</h2>
<p>Document Date: <strong>[DATE]</strong></p>
<p>Project: <strong>[PROJECT NAME]</strong></p>

<h3>1. Project Overview</h3>
<p>[PROJECT OVERVIEW]</p>

<h3>2. Objectives</h3>
<ul>
    <li>[OBJECTIVE 1]</li>
    <li>[OBJECTIVE 2]</li>
    <li>[OBJECTIVE 3]</li>
</ul>

<h3>3. Scope of Work</h3>
<p>[DETAILED SCOPE]</p>

<h4>In Scope:</h4>
<ul>
    <li>[IN SCOPE ITEM 1]</li>
    <li>[IN SCOPE ITEM 2]</li>
</ul>

<h4>Out of Scope:</h4>
<ul>
    <li>[OUT OF SCOPE ITEM 1]</li>
    <li>[OUT OF SCOPE ITEM 2]</li>
</ul>

<h3>4. Deliverables</h3>
<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">
    <tr>
        <th>Deliverable</th>
        <th>Description</th>
        <th>Due Date</th>
    </tr>
    <tr>
        <td>[DELIVERABLE 1]</td>
        <td>[DESCRIPTION]</td>
        <td>[DATE]</td>
    </tr>
</table>

<h3>5. Timeline</h3>
<p><strong>Project Start:</strong> [START DATE]</p>
<p><strong>Project End:</strong> [END DATE]</p>

<h3>6. Budget</h3>
<p><strong>Total Project Cost:</strong> $[AMOUNT]</p>

<h3>7. Acceptance Criteria</h3>
<p>[ACCEPTANCE CRITERIA]</p>

<p>By signing below, both parties agree to the terms of this Statement of Work.</p>'
        ],
    ];
}

/**
 * Count pending signatures for a document
 * 
 * @param PDO $pdo
 * @param int $documentId
 * @return int
 */
function countPendingSignatures($pdo, $documentId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM esign_signatures WHERE document_id = ? AND status = 'pending'");
    $stmt->execute([$documentId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Check if all signatures are complete for a document
 * 
 * @param PDO $pdo
 * @param int $documentId
 * @return bool
 */
function areAllSignaturesComplete($pdo, $documentId) {
    $pending = countPendingSignatures($pdo, $documentId);
    return $pending === 0;
}

/**
 * Update document status based on signatures
 * 
 * @param PDO $pdo
 * @param int $documentId
 */
function updateDocumentStatusFromSignatures($pdo, $documentId) {
    try {
        // Check if expired
        $stmt = $pdo->prepare("SELECT * FROM esign_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();
        
        if (!$doc) return;
        
        if (isDocumentExpired($doc)) {
            $pdo->prepare("UPDATE esign_documents SET status = 'expired' WHERE id = ?")->execute([$documentId]);
            return;
        }
        
        // Check if all signed
        if (areAllSignaturesComplete($pdo, $documentId)) {
            $pdo->prepare("UPDATE esign_documents SET status = 'signed', updated_at = NOW() WHERE id = ?")->execute([$documentId]);
        }
    } catch (Exception $e) {
        error_log('updateDocumentStatusFromSignatures error: ' . $e->getMessage());
    }
}
