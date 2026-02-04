<?php
session_start();


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}


$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root'; 
$password = ''; 


$user_name = 'User';
$user_reg_id = 'Not Set';
$documents_data = [];
$uploaded_count = 0;
$pending_count = 0;
$rejected_count = 0;
$total_required = 10;


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
   
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.registration_id, 
                                  a.partner1_name, a.partner2_name
                           FROM users u 
                           LEFT JOIN applications a ON u.id = a.user_id 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
   
        if (!empty($user['partner1_name']) && !empty($user['partner2_name'])) {
            $user_name = htmlspecialchars($user['partner1_name'] . ' & ' . $user['partner2_name']);
        } elseif (!empty($user['partner1_name'])) {
            $user_name = htmlspecialchars($user['partner1_name']);
        } else {
            $user_name = htmlspecialchars($user['email']);
        }
        
        $user_reg_id = htmlspecialchars($user['registration_id'] ?? 'Not Set');
        
        
        $documents_stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY upload_date DESC");
        $documents_stmt->execute([$user_id]);
        $documents_data = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        foreach ($documents_data as $document) {
            switch ($document['status']) {
                case 'approved':
                case 'uploaded':
                    $uploaded_count++;
                    break;
                case 'pending':
                    $pending_count++;
                    break;
                case 'rejected':
                    $rejected_count++;
                    break;
            }
        }
        
    } else {
       
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Documents page database error: " . $e->getMessage());
    $documents_data = []; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Center | Family Bridge Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/documents.css">
    <link rel="shortcut icon" href="../favlogo.png" type="logo">
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        
        .system-alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<?php include 'includes/header.php' ?>

<?php include 'includes/sidebar.php' ?>

        
        <main class="main-content">
            
            <div class="page-header">
                <h1>Document Center</h1>
                <p>Upload, manage, and track your adoption documents</p>
            </div>

           
            <div class="document-stats">
                <div class="stat-card">
                    <div class="stat-icon uploaded">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="uploadedCount"><?php echo $uploaded_count; ?></h3>
                        <p>Uploaded</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="pendingCount"><?php echo $pending_count; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="rejectedCount"><?php echo $rejected_count; ?></h3>
                        <p>Rejected</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="totalCount"><?php echo $total_required; ?></h3>
                        <p>Total Required</p>
                    </div>
                </div>
            </div>

 
            <div class="document-categories">
                <h2 style="color: var(--primary); margin-bottom: 20px;">
                    <i class="fas fa-folder"></i> Document Categories
                </h2>
                
                <div class="categories-grid">
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="category-title">
                                <h3>Identity Proof</h3>
                                <p>NIC, Passport, etc.</p>
                            </div>
                        </div>
                        <div class="category-progress">
                            <div class="progress-label">
                                <span>Progress</span>
                                <span>0%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="category-title">
                                <h3>Home Study</h3>
                                <p>Home inspection reports</p>
                            </div>
                        </div>
                        <div class="category-progress">
                            <div class="progress-label">
                                <span>Progress</span>
                                <span>0%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 50%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="category-title">
                                <h3>Medical Reports</h3>
                                <p>Health certificates</p>
                            </div>
                        </div>
                        <div class="category-progress">
                            <div class="progress-label">
                                <span>Progress</span>
                                <span>0%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 75%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <div class="category-title">
                                <h3>Legal Documents</h3>
                                <p>Contracts, agreements</p>
                            </div>
                        </div>
                        <div class="category-progress">
                            <div class="progress-label">
                                <span>Progress</span>
                                <span>0%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 25%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

           
            <div class="documents-section">
                <div class="section-header">
                    <h2><i class="fas fa-cloud-upload-alt"></i> Upload Documents</h2>
                    <button class="btn btn-primary" id="bulkUploadBtn">
                        <i class="fas fa-upload"></i> Bulk Upload
                    </button>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Important:</strong> All documents must be in PDF, JPG, or PNG format. Maximum file size is 10MB per document.
                    </div>
                </div>
                
                <form id="uploadForm" action="upload_document.php" method="POST" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="upload-text">
                            <h3>Drag & Drop Files Here</h3>
                            <p>or click to browse files on your computer</p>
                        </div>
                        <button type="button" class="btn btn-secondary" id="browseFilesBtn">
                            <i class="fas fa-folder-open"></i> Browse Files
                        </button>
                        <input type="file" id="fileInput" class="file-input" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" name="files[]">
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label class="form-label required">Document Category</label>
                        <select class="form-control" id="documentCategory" name="category" required>
                            <option value="">Select Category</option>
                            <option value="identity">Identity Proof</option>
                            <option value="home-study">Home Study</option>
                            <option value="medical">Medical Reports</option>
                            <option value="legal">Legal Documents</option>
                            <option value="financial">Financial Documents</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="documentDescription" name="description" rows="3" placeholder="Add any additional information about this document..."></textarea>
                    </div>
                    
                    <div id="filePreviewContainer" style="display: none;">
                        <h3 style="margin: 20px 0 15px; color: var(--primary);">Selected Files</h3>
                        <div id="filePreviews"></div>
                        <button type="submit" class="btn btn-success btn-block" id="uploadFilesBtn" style="margin-top: 20px;">
                            <i class="fas fa-upload"></i> Upload All Files
                        </button>
                    </div>
                </form>
            </div>

        
            <div class="requirements-list">
                <h2 style="color: var(--primary); margin-bottom: 20px;">
                    <i class="fas fa-clipboard-list"></i> Required Documents Checklist
                </h2>
                
                <div class="requirement-item">
                    <div class="requirement-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="requirement-content">
                        <h4>National Identity Cards (NIC)</h4>
                        <p>Scanned copies of both husband and wife's NIC (front and back)</p>
                    </div>
                    <span class="status-badge status-approved">Verified</span>
                </div>
                
                <div class="requirement-item">
                    <div class="requirement-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="requirement-content">
                        <h4>Marriage Certificate</h4>
                        <p>Official marriage certificate issued by relevant authority</p>
                    </div>
                    <span class="status-badge status-approved">Verified</span>
                </div>
                
                <div class="requirement-item">
                    <div class="requirement-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="requirement-content">
                        <h4>Home Study Report</h4>
                        <p>Complete home study report from approved agency</p>
                    </div>
                    <span class="status-badge status-pending">Pending</span>
                </div>
                
                <div class="requirement-item">
                    <div class="requirement-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <div class="requirement-content">
                        <h4>Medical Fitness Certificates</h4>
                        <p>Health certificates from registered medical practitioners</p>
                    </div>
                    <span class="status-badge status-approved">Verified</span>
                </div>
                
                <div class="requirement-item">
                    <div class="requirement-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="requirement-content">
                        <h4>Financial Statements</h4>
                        <p>Bank statements, salary slips, or income tax returns</p>
                    </div>
                    <span class="status-badge status-pending">Pending</span>
                </div>
            </div>

    
            <div class="documents-section">
                <div class="section-header">
                    <h2><i class="fas fa-folder-open"></i> Uploaded Documents</h2>
                    <div>
                        <button class="btn btn-secondary" id="refreshDocsBtn">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button class="btn btn-primary" id="downloadAllBtn">
                            <i class="fas fa-download"></i> Download All
                        </button>
                    </div>
                </div>
                
                <table class="documents-table">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Category</th>
                            <th>Upload Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="documentsTableBody">
                        <?php if (empty($documents_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px;"></i>
                                    <h3 style="color: var(--primary); margin-bottom: 10px;">No Documents Uploaded</h3>
                                    <p style="color: var(--gray);">Start by uploading your first document</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents_data as $doc): ?>
                            <tr>
                                <td>
                                    <div class="document-info">
                                        <div class="document-icon" style="background-color: <?php echo getDocumentColor($doc['category']); ?>20; color: <?php echo getDocumentColor($doc['category']); ?>">
                                            <i class="fas <?php echo getDocumentIcon($doc['category']); ?>"></i>
                                        </div>
                                        <div class="document-details">
                                            <h4><?php echo htmlspecialchars($doc['original_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($doc['file_name']); ?> • <?php echo formatFileSize($doc['file_size']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars(getCategoryName($doc['category'])); ?></td>
                                
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($doc['status']); ?>">
                                        <?php echo getStatusText($doc['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary view-doc-btn" data-id="<?php echo $doc['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="download_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger delete-doc-btn" data-id="<?php echo $doc['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

     
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Note:</strong> Please ensure all uploaded documents are clear, readable, and up-to-date. 
                    Blurry or expired documents will be rejected and may delay your adoption process.
                </div>
            </div>
        </main>
    </div>

    
    <div class="modal" id="previewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="previewTitle">Document Preview</h3>
                <button class="modal-close" data-modal="previewModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                   
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="previewModal">Close</button>
                <button class="btn btn-primary" id="downloadPreviewBtn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button class="btn btn-danger" id="deletePreviewBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

   
                
                <div>
                    <h3 style="color: white; margin-bottom: 15px;">Quick Links</h3>
                    <ul style="list-style: none; color: rgba(255, 255, 255, 0.7);">
                        <li style="margin-bottom: 8px;"><a href="index.php" style="color: inherit; text-decoration: none;">Dashboard</a></li>
                        <li style="margin-bottom: 8px;"><a href="documents.php" style="color: inherit; text-decoration: none;">Document Center</a></li>
                        <li style="margin-bottom: 8px;"><a href="children.php" style="color: inherit; text-decoration: none;">Available Children</a></li>
                        <li><a href="status.php" style="color: inherit; text-decoration: none;">Status Tracking</a></li>
                    </ul>
                </div>
            </div>
            
            <div style="text-align: center; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.5); font-size: 0.9rem;">
                <p>© <?php echo date('Y'); ?> Family Bridge - Child Adoption System. All rights reserved.</p>
                <p style="margin-top: 10px;">Secure Platform | Government Approved | Data Protected</p>
            </div>
        </div>
    </footer>

    <script>
       
      
    </script>
</body>
</html>

<?php

function getCategoryName($category) {
    $categoryMap = [
        'identity' => 'Identity Proof',
        'home-study' => 'Home Study',
        'medical' => 'Medical Reports',
        'legal' => 'Legal Documents',
        'financial' => 'Financial Documents',
        'other' => 'Other'
    ];
    return $categoryMap[$category] ?? $category;
}

function getDocumentIcon($category) {
    $iconMap = [
        'identity' => 'fa-id-card',
        'home-study' => 'fa-home',
        'medical' => 'fa-file-medical',
        'legal' => 'fa-file-contract',
        'financial' => 'fa-file-invoice-dollar',
        'other' => 'fa-file'
    ];
    return $iconMap[$category] ?? 'fa-file';
}

function getDocumentColor($category) {
    $colorMap = [
        'identity' => '#4CAF50',
        'home-study' => '#FF9800',
        'medical' => '#8B4513',
        'legal' => '#2196F3',
        'financial' => '#5D8AA8',
        'other' => '#A9A9A6'
    ];
    return $colorMap[$category] ?? '#A9A9A6';
}

function getStatusText($status) {
    $statusMap = [
        'approved' => 'Approved',
        'pending' => 'Pending Review',
        'rejected' => 'Rejected',
        'uploaded' => 'Uploaded'
    ];
    return $statusMap[$status] ?? $status;
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>