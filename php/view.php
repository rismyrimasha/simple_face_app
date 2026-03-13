<?php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$person = null;

if ($id > 0) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        $person = $stmt->fetch();
    } catch (Throwable $e) {
        $person = null;
    }
}

$activePage = '';
include __DIR__ . '/nav.php';
?>

<section class="page-header">
    <div>
        <h1>Person Details</h1>
        <p class="muted">View full details of the selected person.</p>
    </div>
</section>

<?php if (!$person): ?>
    <div class="alert error">
        Person not found.
    </div>
    <div class="form-actions">
        <a href="index.php" class="btn secondary">Back to Dashboard</a>
    </div>
<?php else: ?>
    <section class="two-column">
        <div class="column">
            <div class="detail-photo">
                <?php
                $hasPhoto = !empty($person['photo_path']) && file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . $person['photo_path']);
                $photoUrl = $hasPhoto ? '../' . ltrim($person['photo_path'], '/\\') : null;
                ?>
                <?php if ($hasPhoto && $photoUrl !== null): ?>
                    <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Person photo">
                <?php else: ?>
                    <div class="placeholder-photo large">No Photo</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="column">
            <div class="detail-card">
                <h2><?php echo htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <table class="details-table">
                    <tbody>
                    <tr>
                        <th>Age</th>
                        <td><?php echo htmlspecialchars((string)$person['age'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Gender</th>
                        <td><?php echo htmlspecialchars((string)$person['gender'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo htmlspecialchars((string)$person['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo htmlspecialchars((string)$person['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?php echo nl2br(htmlspecialchars((string)$person['address'], ENT_QUOTES, 'UTF-8')); ?></td>
                    </tr>
                    <tr>
                        <th>Notes</th>
                        <td><?php echo nl2br(htmlspecialchars((string)$person['notes'], ENT_QUOTES, 'UTF-8')); ?></td>
                    </tr>
                    <tr>
                        <th>Registered At</th>
                        <td><?php echo htmlspecialchars((string)$person['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    </tbody>
                </table>

                <div class="form-actions">
                    <a href="index.php" class="btn secondary">Back</a>
                    <a href="delete.php?id=<?php echo (int)$person['id']; ?>"
                       class="btn danger"
                       onclick="return confirm('Are you sure you want to delete this person?');">Delete</a>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

</main>
</body>
</html>

