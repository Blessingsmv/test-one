<?php
session_start();
require_once 'db_connection.php';
$conn = getDbConnection();

// Initialize variables for form inputs
$username = $password = $role = '';
$related_student_id = $related_lecturer_id = $related_staff_id = null;
$error = '';
$success = '';

// Handle form submission: Add or Edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Plain password
    $role = $_POST['role'];
    $related_student_id = !empty($_POST['related_student_id']) ? $_POST['related_student_id'] : null;
    $related_lecturer_id = !empty($_POST['related_lecturer_id']) ? intval($_POST['related_lecturer_id']) : null;
    $related_staff_id = !empty($_POST['related_staff_id']) ? intval($_POST['related_staff_id']) : null;

    if (empty($username) || ( $user_id === 0 && empty($password)) || empty($role)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check duplicate username (ignore current user in edit)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? " . ($user_id ? "AND user_id != ?" : ""));
        if ($user_id) {
            $stmt->bind_param("si", $username, $user_id);
        } else {
            $stmt->bind_param("s", $username);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Username already exists. Choose another.";
            $stmt->close();
        } else {
            $stmt->close();
            if ($user_id === 0) {
                // Insert new user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, related_student_id, related_lecturer_id, related_staff_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssiii", $username, $password_hash, $role, $related_student_id, $related_lecturer_id, $related_staff_id);
                if ($stmt->execute()) {
                    $success = "User added successfully.";
                    // Reset form fields
                    $username = $password = $role = '';
                    $related_student_id = $related_lecturer_id = $related_staff_id = null;
                } else {
                    $error = "Error adding user: " . $stmt->error;
                }
                $stmt->close();
            } else {
                // Edit existing user
                if (!empty($password)) {
                    // If password provided, update hash
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username=?, password_hash=?, role=?, related_student_id=?, related_lecturer_id=?, related_staff_id=? WHERE user_id=?");
                    $stmt->bind_param("sssiiii", $username, $password_hash, $role, $related_student_id, $related_lecturer_id, $related_staff_id, $user_id);
                } else {
                    // Else don't update password_hash
                    $stmt = $conn->prepare("UPDATE users SET username=?, role=?, related_student_id=?, related_lecturer_id=?, related_staff_id=? WHERE user_id=?");
                    $stmt->bind_param("ssiiii", $username, $role, $related_student_id, $related_lecturer_id, $related_staff_id, $user_id);
                }
                if ($stmt->execute()) {
                    $success = "User updated successfully.";
                } else {
                    $error = "Error updating user: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle Delete user request
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: register_users.php");
    exit;
}

// Handle Edit user request (populate form)
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
    $stmt->close();

    if ($edit_user) {
        $username = $edit_user['username'];
        $role = $edit_user['role'];
        $related_student_id = $edit_user['related_student_id'];
        $related_lecturer_id = $edit_user['related_lecturer_id'];
        $related_staff_id = $edit_user['related_staff_id'];
    }
}

// Fetch lists for dropdowns
function fetchAssocArray($conn, $query) {
    $result = $conn->query($query);
    $arr = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $arr[] = $row;
        }
    }
    return $arr;
}

$students = fetchAssocArray($conn, "SELECT student_id, full_name FROM students ORDER BY full_name");
$lecturers = fetchAssocArray($conn, "SELECT lecturer_id, full_name FROM lecturers ORDER BY full_name");
$staffs = fetchAssocArray($conn, "SELECT staff_id, full_name FROM administrative_staff ORDER BY full_name");

// Fetch users for listing
$users = fetchAssocArray($conn, "SELECT * FROM users ORDER BY user_id DESC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>User Registration</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            padding: 8px 12px;
            border: 1px solid #ccc;
        }
        th {
            background: #eee;
        }
        .form-container {
            width: 600px;
            margin: auto;
            padding: 20px;
            border: 1px solid #aaa;
            border-radius: 5px;
        }
        .form-container label {
            display: block;
            margin-top: 10px;
        }
        .form-container input[type=text],
        .form-container input[type=password],
        .form-container select {
            width: 100%;
            padding: 6px;
            margin-top: 4px;
            box-sizing: border-box;
        }
        .form-container button {
            margin-top: 15px;
            padding: 10px 20px;
        }
        .message {
            width: 600px;
            margin: 10px auto;
            padding: 10px;
            border-radius: 3px;
        }
        .error {
            background-color: #fdd;
            border: 1px solid #f99;
        }
        .success {
            background-color: #dfd;
            border: 1px solid #9f9;
        }
        a.button-link {
            padding: 4px 8px;
            background: #337ab7;
            color: white;
            text-decoration: none;
            border-radius: 3px;
        }
        a.button-link:hover {
            background: #286090;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2><?= $edit_user ? "Edit User" : "Register New User" ?></h2>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="register_users.php">
        <?php if ($edit_user): ?>
            <input type="hidden" name="user_id" value="<?= (int)$edit_user['user_id'] ?>" />
        <?php endif; ?>

        <label for="username">Username (unique)*:</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required />

        <label for="password"><?= $edit_user ? "New Password (leave blank to keep current)" : "Password*" ?>:</label>
        <input type="password" id="password" name="password" <?= $edit_user ? '' : 'required' ?> />

        <label for="role">Role*:</label>
        <select id="role" name="role" required>
            <option value="">-- Select Role --</option>
            <?php
            $roles = ['student', 'lecturer', 'admin', 'staff', 'principle', 'registrar', 'dean', 'accounts'];
            foreach ($roles as $r) {
                $selected = ($role === $r) ? 'selected' : '';
                echo "<option value=\"$r\" $selected>$r</option>";
            }
            ?>
        </select>

        <label for="related_student_id">Related Student (if role=student):</label>
        <select id="related_student_id" name="related_student_id">
            <option value="">-- None --</option>
            <?php
            foreach ($students as $student) {
                $sel = ($related_student_id == $student['student_id']) ? 'selected' : '';
                echo "<option value=\"" . htmlspecialchars($student['student_id']) . "\" $sel>" . htmlspecialchars($student['student_id'] . " - " . $student['full_name']) . "</option>";
            }
            ?>
        </select>

        <label for="related_lecturer_id">Related Lecturer (if role=lecturer):</label>
        <select id="related_lecturer_id" name="related_lecturer_id">
            <option value="">-- None --</option>
            <?php
            foreach ($lecturers as $lecturer) {
                $sel = ($related_lecturer_id == $lecturer['lecturer_id']) ? 'selected' : '';
                echo "<option value=\"" . (int)$lecturer['lecturer_id'] . "\" $sel>" . htmlspecialchars($lecturer['lecturer_id'] . " - " . $lecturer['full_name']) . "</option>";
            }
            ?>
        </select>

        <label for="related_staff_id">Related Staff (if role=staff):</label>
        <select id="related_staff_id" name="related_staff_id">
            <option value="">-- None --</option>
            <?php
            foreach ($staffs as $staff) {
                $sel = ($related_staff_id == $staff['staff_id']) ? 'selected' : '';
                echo "<option value=\"" . (int)$staff['staff_id'] . "\" $sel>" . htmlspecialchars($staff['staff_id'] . " - " . $staff['full_name']) . "</option>";
            }
            ?>
        </select>

        <button type="submit"><?= $edit_user ? "Update User" : "Add User" ?></button>
        <?php if ($edit_user): ?>
            <a href="register_users.php" style="margin-left: 10px;">Cancel Edit</a>
        <?php endif; ?>
    </form>
</div>

<div class="user-list">
    <h2 style="text-align:center;">Registered Users</h2>
    <table>
        <thead>
            <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Related Student ID</th>
                <th>Related Lecturer ID</th>
                <th>Related Staff ID</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="8" style="text-align:center;">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int)$user['user_id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td><?= htmlspecialchars($user['related_student_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['related_lecturer_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['related_staff_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['status']) ?></td>
                        <td>
                            <a class="button-link" href="edit_users.php?edit_id=<?= (int)$user['user_id'] ?>">Edit</a> |
                            <a class="button-link" href="register_users.php?delete_id=<?= (int)$user['user_id'] ?>" onclick="return confirm('Delete this user?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
