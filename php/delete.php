<?php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        $pdo = db();

        $stmt = $pdo->prepare('SELECT photo_path FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            $photoPath = $row['photo_path'];
            if (!empty($photoPath)) {
                $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $photoPath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $deleteStmt = $pdo->prepare('DELETE FROM persons WHERE id = ?');
            $deleteStmt->execute([$id]);
        }
    } catch (Throwable $e) {
        // Swallow errors for now; redirect regardless.
    }
}

header('Location: index.php');
exit;

