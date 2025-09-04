<?php
session_start();
if (!isset($_SESSION['jwt'])) {
    header("Location: index.php");
    exit;
}

$jwt = $_SESSION['jwt'];
$url = "http://student-backend:8000/students";

$options = [
    "http" => [
        "header"  => "Authorization: Bearer $jwt\r\n",
        "method"  => "GET",
    ],
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$students = json_decode($result, true);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Students</title>
</head>
<body>
    <h2>Students</h2>
    <ul>
    <?php foreach ($students as $s): ?>
        <li><?= $s['name'] ?> - <?= $s['email'] ?></li>
    <?php endforeach; ?>
    </ul>
</body>
</html>

