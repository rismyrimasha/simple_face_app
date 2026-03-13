<?php
if (!isset($activePage)) {
    $activePage = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simple Face App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top-nav">
    <div class="nav-brand">
        <span class="logo-dot"></span>
        <span>Simple Face App</span>
    </div>
    <nav class="nav-links">
        <a href="index.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="register.php" class="<?php echo $activePage === 'register' ? 'active' : ''; ?>">Register</a>
        <a href="search.php" class="<?php echo $activePage === 'search' ? 'active' : ''; ?>">Search</a>
    </nav>
</header>
<main class="page-container">

