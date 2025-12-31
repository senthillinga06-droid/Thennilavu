<?php
// Buffer output so we can discard any HTML (like header.php output) for AJAX JSON responses
ob_start();
require_once 'header.php';

// Handle AJAX requests
if(isset($_POST['action'])){
    $action = $_POST['action'];
    
    if($action === 'add'){
        // Handle file upload for author photo
        $author_photo = '';
        if(isset($_FILES['author_photo']) && $_FILES['author_photo']['error'] == 0) {
            $target_dir = "uploads/";
            if(!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $file_extension = pathinfo($_FILES["author_photo"]["name"], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $filename;
            
            // Check if image file is actual image
            $check = getimagesize($_FILES["author_photo"]["tmp_name"]);
            if($check !== false) {
                if(move_uploaded_file($_FILES["author_photo"]["tmp_name"], $target_file)) {
                    $author_photo = $target_file;
                }
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO blog (title, content, author_id, author_name, author_photo, category, status, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssisssss",
            $_POST['title'],
            $_POST['content'],
            $_POST['author_id'],
            $_POST['author_name'],
            $author_photo,
            $_POST['category'],
            $_POST['status'],
            $_POST['publish_date']
        );
    // Return clean JSON (discard any buffered HTML from header.php)
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo $stmt->execute() ? json_encode(['success'=>true]) : json_encode(['success'=>false,'error'=>$stmt->error]);
        $stmt->close();
        exit;
    }
    elseif($action === 'get'){
        $id = intval($_POST['blog_id']);
        $res = $conn->query("SELECT * FROM blog WHERE blog_id=$id");
        if($res->num_rows>0){
            $blog = $res->fetch_assoc();
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>true,'blog'=>$blog]);
        } else {
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false]);
        }
        exit;
    }
    elseif($action === 'edit'){
        // Handle file upload for author photo if a new one is provided
        $author_photo = $_POST['existing_author_photo'];
        if(isset($_FILES['author_photo']) && $_FILES['author_photo']['error'] == 0) {
            $target_dir = "uploads/";
            if(!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $file_extension = pathinfo($_FILES["author_photo"]["name"], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $filename;
            
            // Check if image file is actual image
            $check = getimagesize($_FILES["author_photo"]["tmp_name"]);
            if($check !== false) {
                if(move_uploaded_file($_FILES["author_photo"]["tmp_name"], $target_file)) {
                    // Delete old photo if it exists
                    if(!empty($author_photo) && file_exists($author_photo)) {
                        unlink($author_photo);
                    }
                    $author_photo = $target_file;
                }
            }
        }
        
        $stmt = $conn->prepare("UPDATE blog SET title=?, content=?, author_name=?, author_photo=?, category=?, status=?, publish_date=? WHERE blog_id=?");
        $stmt->bind_param(
            "sssssssi",
            $_POST['title'],
            $_POST['content'],
            $_POST['author_name'],
            $author_photo,
            $_POST['category'],
            $_POST['status'],
            $_POST['publish_date'],
            $_POST['blog_id']
        );
        
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo $stmt->execute() ? json_encode(['success'=>true]) : json_encode(['success'=>false,'error'=>$stmt->error]);
        $stmt->close();
        exit;
    }
    elseif($action === 'delete'){
        // Get the blog to delete the associated author photo
        $id = intval($_POST['blog_id']);
        $res = $conn->query("SELECT author_photo FROM blog WHERE blog_id=$id");
        if($res->num_rows>0){
            $blog = $res->fetch_assoc();
            // Delete the author photo file if it exists
            if(!empty($blog['author_photo']) && file_exists($blog['author_photo'])) {
                unlink($blog['author_photo']);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM blog WHERE blog_id=?");
        $stmt->bind_param("i", $_POST['blog_id']);
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo $stmt->execute() ? json_encode(['success'=>true]) : json_encode(['success'=>false,'error'=>$stmt->error]);
        $stmt->close();
        exit;
    }
}

// Fetch stats and blogs
$total_blogs = $conn->query("SELECT COUNT(*) as count FROM blog")->fetch_assoc()['count'];
$published_blogs = $conn->query("SELECT COUNT(*) as count FROM blog WHERE status='published'")->fetch_assoc()['count'];
$draft_blogs = $conn->query("SELECT COUNT(*) as count FROM blog WHERE status='draft'")->fetch_assoc()['count'];
$archived_blogs = $conn->query("SELECT COUNT(*) as count FROM blog WHERE status='archived'")->fetch_assoc()['count'];

$blogs_result = $conn->query("SELECT * FROM blog ORDER BY created_at DESC");
?>

<style>
    .main-content {
        background-color: #f1f5f9;
        min-height: calc(100vh - var(--header-height));
        padding: 2rem;
        margin-top: 30px;
        margin-left:130px;
    }

    .content-section {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 2rem;
        margin-bottom: 2rem;
        width: 100%;
    }

    /* Top Actions */
    .top-actions {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
        font-size: 14px;
    }

    .action-btn.primary {
        background: var(--primary);
        color: #fff;
    }

    .action-btn.secondary {
        background: #f1f1f1;
        color: #333;
    }

    .action-btn:hover {
        opacity: .9;
        transform: translateY(-2px);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .stat-card {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid var(--primary);
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 20px;
    }

    .stat-info h3 {
        font-size: 14px;
        color: #777;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-number {
        font-size: 24px;
        font-weight: 700;
        color: #333;
        margin-bottom: 5px;
    }

    /* Table Container */
    .table-container {
        width: 100%;
        overflow-x: auto;
        border-radius: var(--border-radius);
        border: 1px solid var(--gray-light);
        background: white;
        margin-top: 1rem;
    }

    /* Data Table */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    .data-table th, 
    .data-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--gray-light);
    }

    .data-table th {
        background-color: var(--primary);
        color: white;
        font-weight: 600;
        position: sticky;
        top: 0;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    .data-table tr:hover {
        background-color: #f8fafc;
    }

    .data-table tr:last-child td {
        border-bottom: none;
    }

    .blog-info .blog-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .blog-info .blog-excerpt {
        font-size: 13px;
        color: var(--gray);
        max-width: 300px;
        line-height: 1.4;
    }

    .author-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .author-info img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .author-info .author-name {
        font-weight: 600;
        font-size: 14px;
    }

    .author-info .author-role {
        font-size: 12px;
        color: var(--gray);
    }

    .category-badge, .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: capitalize;
        display: inline-block;
    }

    .category-badge.dating-tips { background: #e8f5e9; color: #2e7d32; }
    .category-badge.relationships { background: #e3f2fd; color: #1565c0; }
    .category-badge.wedding { background: #f3e5f5; color: #7b1fa2; }
    .category-badge.communication { background: #fff3e0; color: #ef6c00; }
    .category-badge.safety { background: #ffebee; color: #c62828; }

    .status-badge.success { background: #e8f5e9; color: #2e7d32; }
    .status-badge.pending { background: #fff3e0; color: #ef6c00; }
    .status-badge.failed { background: #ffebee; color: #c62828; }

    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
    }

    .view-btn, .edit-btn, .delete-btn {
        width: 35px;
        height: 35px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        font-size: 14px;
    }

    .view-btn { background: #e3f2fd; color: #1565c0; }
    .view-btn:hover { background: #d1e9fc; transform: scale(1.1); }

    .edit-btn { background: #fff3e0; color: #ef6c00; }
    .edit-btn:hover { background: #ffe8cc; transform: scale(1.1); }

    .delete-btn { background: #ffebee; color: #c62828; }
    .delete-btn:hover { background: #ffdde0; transform: scale(1.1); }

    .text-center { text-align: center; }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal-content {
        background: #fff;
        border-radius: var(--border-radius);
        width: 100%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--gray-light);
    }

    .modal-header h2 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--dark);
        font-size: 1.5rem;
    }

    .modal-body {
        padding: 2rem;
    }

    .close {
        color: var(--gray);
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close:hover {
        color: var(--dark);
        background-color: var(--gray-light);
        border-radius: 50%;
    }

    /* Form Styles */
    .form-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .form-column {
        flex: 1;
        min-width: 200px;
    }

    .form-column.full-width {
        flex: 100%;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--gray-light);
        border-radius: 6px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 120px;
        line-height: 1.5;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .btn-primary, .btn-secondary {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        font-size: 14px;
    }

    .btn-primary {
        background: var(--primary);
        color: #fff;
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: #f1f1f1;
        color: #333;
    }

    .btn-secondary:hover {
        background: #e5e5e5;
        transform: translateY(-2px);
    }

    .author-photo-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        margin-top: 10px;
        display: none;
        border: 3px solid var(--gray-light);
    }

    /* Mobile Responsive Styles */
    @media (max-width: 1200px) {
        .main-content {
            margin-left: 0;
            padding: 1rem;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
            margin-top: 1rem;
        }

        .content-section {
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .top-actions {
            flex-direction: column;
            gap: 0.75rem;
        }

        .action-btn {
            width: 100%;
            justify-content: center;
            padding: 1rem;
        }

        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card {
            padding: 1.25rem;
        }

        .table-container {
            border: none;
            background: transparent;
        }

        .data-table {
            min-width: 100%;
            font-size: 0.9rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }

        .blog-info .blog-excerpt {
            max-width: 200px;
        }

        .action-buttons {
            flex-direction: column;
            gap: 0.5rem;
        }

        .view-btn, .edit-btn, .delete-btn {
            width: 100%;
            height: 35px;
        }

        .modal {
            padding: 0.5rem;
        }

        .modal-content {
            margin: 1rem;
        }

        .modal-header {
            padding: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-row {
            flex-direction: column;
            gap: 0;
        }

        .form-column {
            min-width: 100%;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn-primary, .btn-secondary {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 0.5rem;
        }

        .content-section {
            padding: 1rem;
            border-radius: 8px;
        }

        .data-table {
            font-size: 0.8rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.6rem 0.4rem;
        }

        .author-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }

        .author-info img {
            width: 30px;
            height: 30px;
        }

        .modal-content {
            padding: 0;
        }

        .modal-header {
            padding: 1rem;
        }

        .modal-body {
            padding: 1rem;
        }
    }
</style>

<main class="main-content">
    <div class="top-actions">
        <button id="addBlogBtn" class="action-btn primary">
            <i class="fas fa-plus"></i> Add New Blog
        </button>
    </div>

    <div class="content-section">
        <h2>Blog Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Blogs</h3>
                    <div class="stat-number"><?= $total_blogs ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Published Blogs</h3>
                    <div class="stat-number"><?= $published_blogs ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>Draft Blogs</h3>
                    <div class="stat-number"><?= $draft_blogs ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <div class="stat-info">
                    <h3>Archived Blogs</h3>
                    <div class="stat-number"><?= $archived_blogs ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="content-section">
        <h2>All Blogs</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Blog</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($blogs_result->num_rows > 0): ?>
                        <?php while($blog = $blogs_result->fetch_assoc()): ?>
                            <tr>
                                <td class="blog-info">
                                    <div class="blog-title"><?= htmlspecialchars($blog['title']) ?></div>
                                    <div class="blog-excerpt"><?= substr(htmlspecialchars($blog['content']), 0, 50) . '...' ?></div>
                                </td>
                                <td class="author-info">
                                    <?php if(!empty($blog['author_photo'])): ?>
                                        <img src="<?= htmlspecialchars($blog['author_photo']) ?>" alt="Author">
                                    <?php endif; ?>
                                    <div>
                                        <div class="author-name"><?= htmlspecialchars($blog['author_name']) ?></div>
                                        <div class="author-role">Author</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="category-badge <?= strtolower(str_replace(' ', '-', $blog['category'])) ?>">
                                        <?= htmlspecialchars($blog['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($blog['status'] == 'published'): ?>
                                        <span class="status-badge success">Published</span>
                                    <?php elseif($blog['status'] == 'draft'): ?>
                                        <span class="status-badge pending">Draft</span>
                                    <?php else: ?>
                                        <span class="status-badge failed">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="view-btn" onclick="viewBlog(<?= $blog['blog_id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="edit-btn" onclick="openEditModal(<?= $blog['blog_id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-btn" onclick="deleteBlog(<?= $blog['blog_id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No blogs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal -->
<div id="blogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-blog"></i> <span id="modalTitle">Add Blog</span></h2>
            <button class="close" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="blogForm" enctype="multipart/form-data">
                <input type="hidden" name="blog_id" id="blogId">
                <input type="hidden" name="existing_author_photo" id="existingAuthorPhoto">
                <div class="form-row">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" id="title" required>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Author ID</label>
                            <input type="number" name="author_id" id="author_id" required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Author Name</label>
                            <input type="text" name="author_name" id="author_name" required>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Author Photo</label>
                            <input type="file" name="author_photo" id="author_photo" accept="image/*" onchange="previewImage(this)">
                            <img id="authorPhotoPreview" class="author-photo-preview" src="" alt="Author Photo Preview">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" id="category" required>
                                <option value="Dating Tips">Dating Tips</option>
                                <option value="Relationships">Relationships</option>
                                <option value="Wedding">Wedding</option>
                                <option value="Communication">Communication</option>
                                <option value="Safety">Safety</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="status" required>
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-column full-width">
                        <div class="form-group">
                            <label>Publish Date</label>
                            <input type="date" name="publish_date" id="publish_date" required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-column full-width">
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" id="content" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn-secondary" id="cancelModal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal
const blogModal = document.getElementById('blogModal');
const addBlogBtn = document.getElementById('addBlogBtn');
const closeModal = document.getElementById('closeModal');
const cancelModal = document.getElementById('cancelModal');
const modalTitle = document.getElementById('modalTitle');
const blogForm = document.getElementById('blogForm');

addBlogBtn.onclick = ()=>{
    blogForm.reset();
    document.getElementById('blogId').value = '';
    document.getElementById('existingAuthorPhoto').value = '';
    document.getElementById('authorPhotoPreview').style.display = 'none';
    modalTitle.innerText = 'Add Blog';
    blogModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
};

closeModal.onclick = ()=> {
    blogModal.style.display = 'none';
    document.body.style.overflow = 'auto';
};

cancelModal.onclick = ()=> {
    blogModal.style.display = 'none';
    document.body.style.overflow = 'auto';
};

window.onclick = e=> { 
    if(e.target == blogModal) {
        blogModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
};

// Image preview function
function previewImage(input) {
    const preview = document.getElementById('authorPhotoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
        preview.src = '';
    }
}

// AJAX
blogForm.onsubmit = async e=>{
    e.preventDefault();
    const formData = new FormData(blogForm);
    let action = formData.get('blog_id') ? 'edit' : 'add';
    formData.append('action', action);

    try {
        const res = await fetch('', {method:'POST', body:formData});
        const data = await res.json();
        if(data.success){
            alert('Saved successfully!');
            location.reload();
        } else {
            alert('Error: '+(data.error||'Failed to save'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
};

function openEditModal(id){
    modalTitle.innerText = 'Edit Blog';
    const formData = new FormData();
    formData.append('action','get');
    formData.append('blog_id',id);

    fetch('',{method:'POST',body:formData})
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            const blog = data.blog;
            document.getElementById('blogId').value = blog.blog_id;
            document.getElementById('title').value = blog.title;
            document.getElementById('author_id').value = blog.author_id;
            document.getElementById('author_name').value = blog.author_name;
            document.getElementById('existingAuthorPhoto').value = blog.author_photo;
            document.getElementById('category').value = blog.category;
            document.getElementById('status').value = blog.status;
            document.getElementById('publish_date').value = blog.publish_date;
            document.getElementById('content').value = blog.content;
            
            // Show existing author photo if available
            const preview = document.getElementById('authorPhotoPreview');
            if (blog.author_photo) {
                preview.src = blog.author_photo;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
            
            blogModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } else {
            alert('Blog not found');
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function deleteBlog(id){
    if(confirm('Are you sure you want to delete this blog?')){
        const formData = new FormData();
        formData.append('action','delete');
        formData.append('blog_id',id);
        fetch('',{method:'POST',body:formData})
        .then(r=>r.json())
        .then(data=>{
            if(data.success) {
                location.reload();
            } else {
                alert('Error: '+data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

function viewBlog(id) {
    // You can implement a view functionality here
    alert('View functionality for blog ID: ' + id);
}

// Handle mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
});
</script>

</body>
</html>