<?php
require_once __DIR__ . '/config.php';

$pdo = db();

$stmt = $pdo->query('SELECT COUNT(*) AS total FROM persons');
$totalRow = $stmt->fetch();
$total = $totalRow ? (int)$totalRow['total'] : 0;

$personsStmt = $pdo->query('SELECT id, name, age, gender, photo_path, created_at FROM persons ORDER BY created_at DESC');
$persons = $personsStmt->fetchAll();

$activePage = 'dashboard';
include __DIR__ . '/nav.php';
?>

<section class="page-header">
    <div>
        <h1>Registered Persons</h1>
        <p class="muted">Total registered: <strong><?php echo (int)$total; ?></strong></p>
    </div>
    <div class="header-actions">
        <a href="register.php" class="btn primary">Register Person</a>
        <a href="search.php" class="btn secondary">Search by Face</a>
    </div>
</section>

<?php if ($total === 0): ?>
    <section class="empty-state">
        <div class="empty-card">
            <h2>No persons registered yet</h2>
            <p>Start by registering a person with their photo and details.</p>
            <a href="register.php" class="btn primary">Register First Person</a>
        </div>
    </section>
<?php else: ?>
    <section class="grid">
        <?php foreach ($persons as $person): ?>
            <article class="person-card">
                <div class="person-photo">
                    <?php
                    $hasPhoto = !empty($person['photo_path']) && file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . $person['photo_path']);
                    $photoUrl = $hasPhoto ? '../' . ltrim($person['photo_path'], '/\\') : null;
                    ?>
                    <?php if ($hasPhoto && $photoUrl !== null): ?>
                        <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Photo of <?php echo htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                        <div class="placeholder-photo">No Photo</div>
                    <?php endif; ?>
                </div>
                <div class="person-info">
                    <h3><?php echo htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="muted">
                        <?php if (!empty($person['age'])): ?>
                            Age <?php echo (int)$person['age']; ?> •
                        <?php endif; ?>
                        <?php echo htmlspecialchars($person['gender'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p class="muted small">
                        Registered on <?php echo htmlspecialchars($person['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
                <div class="person-actions">
                    <a href="view.php?id=<?php echo (int)$person['id']; ?>" class="btn small">View</a>
                    <a href="delete.php?id=<?php echo (int)$person['id']; ?>"
                       class="btn small danger"
                       onclick="return confirm('Are you sure you want to delete this person?');">Delete</a>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

</main>
</body>
</html>

