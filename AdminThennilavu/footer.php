<?php
// footer.php
?>
<script>
  // Common JavaScript functions can go here
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
  }
  
  function checkAuth() {
    const isLoggedIn = <?= isset($_SESSION['staff_id']) ? 'true' : 'false' ?>;
    if (!isLoggedIn) {
      window.location.href = 'login.php';
    }
  }
</script>
</body>
</html>