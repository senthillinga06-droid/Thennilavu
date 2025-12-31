<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) { die('DB error: ' . $conn->connect_error); }
$user_id = $_SESSION['user_id'];

// Fetch member details
$member = $conn->query("SELECT * FROM members WHERE user_id=$user_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
// Fetch user details
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
// Fetch available packages
$packages = [];
$res = $conn->query("SELECT * FROM packages WHERE status='active'");
while ($row = $res->fetch_assoc()) $packages[] = $row;
// Fetch gallery images
$gallery = [];
$imgres = $conn->query("SELECT * FROM member_gallery WHERE member_id=" . ($member['id'] ?? 0));
while ($img = $imgres->fetch_assoc()) $gallery[] = $img;

// Profile update
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $profession = $_POST['profession'] ?? '';
        $city = $_POST['city'] ?? '';
        $country = $_POST['country'] ?? '';
        $photo = $member['photo'] ?? '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $photo = 'uploads/' . time() . '_' . $_FILES['photo']['name'];
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
        }
        $stmt = $conn->prepare("UPDATE members SET name=?, phone=?, profession=?, city=?, country=?, photo=? WHERE id=?");
        $stmt->bind_param('ssssssi', $name, $phone, $profession, $city, $country, $photo, $member['id']);
        $stmt->execute();
        $msg = 'Profile updated!';
        $member = $conn->query("SELECT * FROM members WHERE user_id=$user_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
    }
    if (isset($_POST['upload_image'])) {
        if (isset($_FILES['gallery_image']) && $_FILES['gallery_image']['error'] === 0) {
            $img = 'uploads/' . time() . '_' . $_FILES['gallery_image']['name'];
            move_uploaded_file($_FILES['gallery_image']['tmp_name'], $img);
            $stmt = $conn->prepare("INSERT INTO member_gallery (member_id, image_path) VALUES (?, ?)");
            $stmt->bind_param('is', $member['id'], $img);
            $stmt->execute();
            $msg = 'Image uploaded!';
            $gallery[] = ['image_path' => $img];
        }
    }
    if (isset($_POST['buy_package'])) {
        $pkg = $_POST['package'] ?? '';
        $stmt = $conn->prepare("UPDATE members SET package=? WHERE id=?");
        $stmt->bind_param('si', $pkg, $member['id']);
        $stmt->execute();
        $msg = 'Package updated!';
        $member = $conn->query("SELECT * FROM members WHERE user_id=$user_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(to Bottom, #ec3107, #fda101);
            color: white;
            border-radius: 10px;
        }
        .sidebar .nav-link {
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 5px 0;
        }
        .sidebar .nav-link.active {
            background-color: #495057;
        }
        .profile-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
        .card {
            margin-bottom: 20px;
        }
        .hidden-section {
            display: none;
        }
        .paid-content {
            display: none;
        }
        .paid-user .paid-content {
            display: block;
        }
        .heart {
  position: absolute;
  color: red;
  font-size: 20px;
  pointer-events: none;
  animation: floatUp 3s ease-out forwards;
  opacity: 1;
  z-index: 9999;
}

@keyframes floatUp {
  0% {
    transform: translate(0, 0) scale(1);
    opacity: 1;
  }
  100% {
    transform: translate(-20px, -200px) scale(1.5);
    opacity: 0;
  }
}
    </style>
</head>
<body>
        <nav class="navbar fixed-top" style="background-color: rgb(127, 8, 8);">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">
      <img src="logo.png" alt="" width="30" height="30" class="d-inline-block align-text-top">
    </a>
    <span style="color: aliceblue;"><?php echo htmlspecialchars($user['email']); ?></span>

   <div class="ms-auto d-flex gap-2">
      <button class="btn bg-primary" style=" align-items: center;"> <a class="nav-link text-white fw-semibold me-3" style="margin-left: 15px; text-decoration: none;"  href="">DashBoard</a></button>
      <button class="btn bg-secondary" style=" text-align: center;"> <a class="nav-link text-white fw-semibold me-3" style="margin-left: 15px; text-decoration: none;" href="logout.php">Log Out</a></button>
      
    </div>
  </div>
</nav>
<br><br><br>

<!-- Optional content spacing for fixed nav -->
<div style="height: 80px;"> 

<!-- Navbar -->
<nav class="navbar navbar-expand-lg shadow-sm " style="background: linear-gradient(to right, #ec3107, #fda101); border-radius: 10px; ">
  <div class="container-fluid">
    <!-- Logo -->
    <a class="navbar-brand text-white fw-bold" href="#">
      <img src="logo.png" alt="" width="30" height="30" class="d-inline-block align-text-top me-2">
      Thennilavu
    </a>

    <!-- Toggler for mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup"
      aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navigation Links -->
    <div class="collapse navbar-collapse justify-content-end" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-link  text-white fw-semibold me-3" href="home.php">Home</a>
        <a class="nav-link  text-white fw-semibold me-3"   href="members.php">Member Ship</a>
        <a class="nav-link  text-white fw-semibold me-3"  href="mem.php">Members</a>
        <a class="nav-link text-white fw-semibold me-3" href="package.php">Packages</a>
        <a class="nav-link text-white fw-semibold me-3" href="contact.php">Contact Us</a>
        <a class="nav-link text-white fw-semibold me-3" href="story.php">Stories</a>
      </div>
    </div>
  </div>
</nav>
</div>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center my-4">
                    <img src="<?php echo htmlspecialchars($member['photo'] ?? 'https://via.placeholder.com/100'); ?>" class="profile-img" alt="User Profile">
                    <h5 class="mt-2">User ID: <?php echo htmlspecialchars($member['id']); ?></h5>
                    <h4>Name: <?php echo htmlspecialchars($member['name']); ?></h4>
                    <p>Age: <?php echo htmlspecialchars($member['age']); ?></p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="#" onclick="showSection('dashboard')">Dashboard</a>
                    <a class="nav-link" href="#" onclick="showSection('gallery')">Gallery</a>
                    <a class="nav-link" href="#" onclick="showSection('purchase-history')">Purchase History</a>
                    <a class="nav-link" href="#" onclick="showSection('profile-history')">Profile History</a>
                </nav>
            </div>
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container py-4">
                    <!-- Dashboard Section -->
                    <div id="dashboard" class="section">
                        <h2>Dashboard</h2>
                        <div class="card">
                            <div class="card-body">
                                <h5>Current Package: <?php echo htmlspecialchars($member['package'] ?? 'No package'); ?></h5>
                                <button class="btn btn-primary mb-3" onclick="upgradePackage()">Upgrade Package</button>
                                <!-- Favorite Members (Visible to Paid Users Only) -->
                                <div class="paid-content">
                                    <h5>Favorite Members</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="card">
                                                <img src="https://via.placeholder.com/150" class="card-img-top" alt="Favorite Person">
                                                <div class="card-body">
                                                    <h6>Name: Jane Smith</h6>
                                                    <p>Age: 28<br>Country: India</p>
                                                    <button class="btn btn-sm btn-info">View More</button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Add more favorite members as needed -->
                                    </div>
                                </div>
                                <!-- Interest Sent (Visible to Paid Users Only) -->
                                <div class="paid-content">
                                    <h5>Interest Sent</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="card">
                                                <img src="https://via.placeholder.com/150" class="card-img-top" alt="Interest Person">
                                                <div class="card-body">
                                                    <h6>Name: Priya Sharma</h6>
                                                    <p>Age: 25<br>Gender: Female<br>Marital Status: Single</p>
                                                    <button class="btn btn-sm btn-info">View More</button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Add more interest sent as needed -->
                                    </div>
                                </div>
                                <!-- Image Upload -->
                                <h5>Upload Image</h5>
                                <input type="file" class="form-control mb-3" accept="image/*" onchange="uploadImage(event)">
                            </div>
                        </div>
                    </div>
                    <!-- Gallery Section -->
                    <div id="gallery" class="section hidden-section">
                        <h2>Gallery</h2>
                        <div class="card">
                            <div class="card-body">
                                <h5>Upload New Image</h5>
                                <input type="file" class="form-control mb-3" accept="image/*" onchange="uploadImage(event)">
                                <h5>Uploaded Images</h5>
                                <div class="row" id="gallery-images">
                                    <div class="col-md-3">
                                        <img src="https://via.placeholder.com/150" class="img-fluid mb-3" alt="Uploaded Image">
                                    </div>
                                    <!-- Add more uploaded images dynamically -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Purchase History Section -->
                    <div id="purchase-history" class="section hidden-section">
                        <h2>Purchase History</h2>
                        <div class="card">
                            <div class="card-body">
                                <h5>Member Package Details</h5>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Member Name</th>
                                            <th>Member ID</th>
                                            <th>Current Package</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['id']); ?></td>
                                            <td><?php echo htmlspecialchars($member['package']); ?></td>
                                        </tr>
                                        <!-- Add more rows dynamically -->
                                    </tbody>
                                </table>
                                <h5>Available Packages</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6>Premium Package</h6>
                                                <p>Details: Unlimited profile views, priority matching, etc.</p>
                                                <button class="btn btn-success">Buy Now</button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Add more packages as needed -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Profile History Section -->
                    <div id="profile-history" class="section hidden-section">
                        <h2>Profile History</h2>
                        <div class="card">
                            <div class="card-body">
                                <ul class="list-group">
                                    <li class="list-group-item">Profile Created: 2025-01-01</li>
                                    <li class="list-group-item">Profile Updated: 2025-06-15</li>
                                    <li class="list-group-item">Last Login: 2025-08-07</li>
                                    <!-- Add more history items dynamically -->
                                </ul>
                            </div>
                        </div>
                        <div>
                        <button class="btn btn-outline-primary">Update</button>
                    </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simulate paid user status (true for paid, false for free)
        let isPaidUser = false;

        // Apply paid user class if applicable
        if (isPaidUser) {
            document.body.classList.add('paid-user');
        }

        // Function to show/hide sections
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.add('hidden-section');
            });
            document.getElementById(sectionId).classList.remove('hidden-section');
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`.sidebar .nav-link[onclick="showSection('${sectionId}')"]`).classList.add('active');
        }

        // Function to handle image upload
        function uploadImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgElement = document.createElement('div');
                    imgElement.className = 'col-md-3';
                    imgElement.innerHTML = `<img src="${e.target.result}" class="img-fluid mb-3" alt="Uploaded Image">`;
                    document.getElementById('gallery-images').appendChild(imgElement);
                };
                reader.readAsDataURL(file);
            }
        }

        // Function to handle package upgrade
        function upgradePackage() {
            alert('Redirecting to package upgrade page...');
            // Implement actual upgrade logic here
        }

        // Initialize dashboard as default view
        showSection('dashboard');






        document.addEventListener("mousemove", function(e) {
  const heart = document.createElement("div");
  heart.className = "heart";
  heart.innerHTML = "❤️";

  // Random left/right drift
  const driftX = Math.random() * 40 - 20; // -20 to +20px
  const scale = 1 + Math.random(); // Scale 1–2
  const duration = 2 + Math.random() * 2; // 2–4s duration

  heart.style.left = e.pageX + "px";
  heart.style.top = e.pageY + "px";
  heart.style.transform = `translate(0, 0) scale(${scale})`;
  heart.style.animationDuration = `${duration}s`;

  document.body.appendChild(heart);

  setTimeout(() => {
    heart.remove();
  }, duration * 1000);
});
    </script>
</body>
</html>