<?php
// process_booking.php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $action = $_POST['action'];

    $status = '';
    if ($action == 'approve') $status = 'Disahkan';
    if ($action == 'reject') $status = 'Ditolak';
    if ($action == 'returned') $status = 'Dikembalikan';

    $sql = "UPDATE bookings SET status='$status' WHERE id='$id'";
    if ($conn->query($sql) === TRUE) {
        header("Location: admin_booking.php?success=1");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
