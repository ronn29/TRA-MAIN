<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require '../db/dbconn.php';

$user_id = $_SESSION['user_id'];
$messages = [];
$profile = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'password' => ''
];

function fetchAdminProfile($conn, $user_id)
{
    $info_sql = "SELECT a.first_name, a.last_name, u.email, u.password 
                 FROM admin_tbl a 
                 LEFT JOIN user_tbl u ON a.user_id = u.user_id 
                 WHERE a.user_id = ?";
    $stmt = mysqli_prepare($conn, $info_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    return $data ?: [];
}

$profile = array_merge($profile, fetchAdminProfile($conn, $user_id));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!empty($_FILES['profile_photo']['name'])) {
        $allowed_mime = ['image/jpeg','image/jpg','image/png'];
        $tmp = $_FILES['profile_photo']['tmp_name'];
        $size = $_FILES['profile_photo']['size'];
        $mime = mime_content_type($tmp);

        if (!in_array($mime, $allowed_mime)) {
            $messages[] = ['type' => 'error', 'text' => 'Only JPG and PNG files are allowed.'];
        } elseif ($size > 2 * 1024 * 1024) {
            $messages[] = ['type' => 'error', 'text' => 'Image must be under 2MB.'];
        } else {
            $data = file_get_contents($tmp);
            $stmt = mysqli_prepare($conn, "UPDATE admin_tbl SET profile_picture_blob = ?, profile_picture_mime = ?, date_updated = NOW() WHERE user_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "bsi", $data, $mime, $user_id);
                mysqli_stmt_send_long_data($stmt, 0, $data);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['profile_img'] = "profile_image.php?v=" . time();
                    $_SESSION['admin_profile_img_version'] = time();
                    $messages[] = ['type' => 'success', 'text' => 'Profile photo updated.'];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Upload failed. Please try again.'];
                }
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Upload failed. Please try again.'];
            }
        }
    } else {
        $messages[] = ['type' => 'error', 'text' => 'Please choose a file to upload.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '' || $confirm === '' || $current === '') {
        $messages[] = ['type' => 'error', 'text' => 'Please fill in all password fields.'];
    } elseif ($new !== $confirm) {
        $messages[] = ['type' => 'error', 'text' => 'New password and confirmation do not match.'];
    } else {
        $stored = $profile['password'] ?? '';
        $currentValid = false;
        if (strpos($stored, '$2y$') === 0) {
            $currentValid = password_verify($current, $stored);
        } else {
            $currentValid = hash_equals($stored, $current);
        }

        if (!$currentValid) {
            $messages[] = ['type' => 'error', 'text' => 'Current password is incorrect.'];
        } else {
            $newToStore = (strpos($stored, '$2y$') === 0) ? password_hash($new, PASSWORD_BCRYPT) : $new;
            $upd = mysqli_prepare($conn, "UPDATE user_tbl SET password = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, "si", $newToStore, $user_id);
            if (mysqli_stmt_execute($upd)) {
                $messages[] = ['type' => 'success', 'text' => 'Password updated successfully.'];
                $profile['password'] = $newToStore;
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Failed to update password.'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($first === '' || $last === '' || $email === '') {
        $messages[] = ['type' => 'error', 'text' => 'First name, last name, and email are required.'];
    } else {
        $updAdmin = mysqli_prepare($conn, "UPDATE admin_tbl SET first_name = ?, last_name = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updAdmin, "ssi", $first, $last, $user_id);
        $ok1 = mysqli_stmt_execute($updAdmin);
        mysqli_stmt_close($updAdmin);

        $updUser = mysqli_prepare($conn, "UPDATE user_tbl SET email = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updUser, "si", $email, $user_id);
        $ok2 = mysqli_stmt_execute($updUser);
        mysqli_stmt_close($updUser);

        if ($ok1 && $ok2) {
            $messages[] = ['type' => 'success', 'text' => 'Profile updated successfully.'];
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to update profile.'];
        }
    }
}

$profile = array_merge($profile, fetchAdminProfile($conn, $user_id));

$activePage = 'settings';
$profileVersion = $_SESSION['admin_profile_img_version'] ?? time();
$profileImg = "profile_image.php?v=" . $profileVersion;
$fullName = htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: 'Administrator');
$roleLabel = 'Administrator';
$emailDisplay = htmlspecialchars($profile['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
</head>
<body>
<input type="checkbox" id="sidebar-toggle">

<div class="main-content">
    <?php include 'header.php'; ?>

    <div class="container settings-page">
        <h2>
            <span class="las la-cog"></span>
            Settings
        </h2>

        <div class="settings-hero">
            <div class="settings-hero-avatar">
                <img src="<?php echo htmlspecialchars($profileImg); ?>" alt="Profile photo">
            </div>
            <div class="settings-hero-details">
                <h3><?php echo $fullName; ?></h3>
                <p class="settings-hero-role"><?php echo $roleLabel; ?></p>
                <p class="settings-hero-email"><?php echo $emailDisplay; ?></p>
            </div>
        </div>

        <?php if (!empty($messages)): ?>
            <div class="settings-messages">
                <?php foreach ($messages as $msg): ?>
                    <div class="settings-alert settings-<?php echo $msg['type']; ?>">
                        <?php echo htmlspecialchars($msg['text']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Profile</h3>
                </div>
                <form class="settings-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_photo">
                    <label class="settings-label">Upload new photo (JPG/PNG)</label>
                    <input type="file" name="profile_photo" accept="image/png,image/jpeg" required>
                    <button type="submit" class="btn-primary">Upload</button>
                </form>
            </div>

            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Profile Information</h3>
                </div>
                <form class="settings-form" method="post">
                    <input type="hidden" name="action" value="update_profile">
                    <label class="settings-label">First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>

                    <label class="settings-label">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>

                    <label class="settings-label">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>

                    <button type="submit" class="btn-primary">Update Profile</button>
                </form>
            </div>

            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Security</h3>
                    <p class="settings-card-sub">Update your password regularly to keep your account secure.</p>
                </div>
                <form class="settings-form" method="post">
                    <input type="hidden" name="action" value="change_password">
                    <label class="settings-label">Current Password</label>
                    <input type="password" name="current_password" required>

                    <label class="settings-label">New Password</label>
                    <input type="password" name="new_password" required>

                    <label class="settings-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" required>

                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'sidebar.php'; ?>

</body>
</html>

