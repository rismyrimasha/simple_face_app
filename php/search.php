<?php
require_once __DIR__ . '/config.php';

$errors = [];
$result = null;
$matchPerson = null;
$confidence = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Query photo is required.';
    } else {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $originalName = $_FILES['photo']['name'];
        $tmpPath = $_FILES['photo']['tmp_name'];

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Invalid file type. Allowed: JPG, JPEG, PNG, WEBP.';
        } else {
            try {
                $pdo = db();
                $stmt = $pdo->query('SELECT id, face_encoding FROM persons WHERE face_encoding IS NOT NULL');
                $rows = $stmt->fetchAll();
            } catch (Throwable $e) {
                $rows = [];
                $errors[] = 'Database error: ' . $e->getMessage();
            }

            if (empty($rows)) {
                $errors[] = 'No registered persons with face encodings found.';
            } else {
                $encodings = [];
                foreach ($rows as $row) {
                    $encodingArray = json_decode($row['face_encoding'], true);
                    if (is_array($encodingArray)) {
                        $encodings[] = [
                            'id' => (int)$row['id'],
                            'encoding' => $encodingArray,
                        ];
                    }
                }

                if (empty($encodings)) {
                    $errors[] = 'No valid encodings available.';
                } else {
                    $curlFile = new CURLFile($tmpPath, mime_content_type($tmpPath), $originalName);
                    $postFields = [
                        'encodings' => json_encode($encodings),
                    ];

                    [$data, $error] = call_python_api('search', $postFields, ['image' => $curlFile]);

                    if ($error !== null) {
                        $errors[] = $error;
                    } elseif (isset($data['error'])) {
                        $errors[] = (string)$data['error'];
                    } else {
                        $result = $data;
                        if (isset($data['match_id']) && $data['match_id'] !== null) {
                            try {
                                $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = ?');
                                $stmt->execute([(int)$data['match_id']]);
                                $matchPerson = $stmt->fetch();
                                $confidence = isset($data['confidence']) ? (float)$data['confidence'] : null;
                            } catch (Throwable $e) {
                                $errors[] = 'Database error while loading match details: ' . $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }
}

$activePage = 'search';
include __DIR__ . '/nav.php';
?>

<section class="page-header">
    <div>
        <h1>Search by Face</h1>
        <p class="muted">Upload a photo to find a matching registered person.</p>
    </div>
</section>

<?php if (!empty($errors)): ?>
    <div class="alert error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="two-column">
    <div class="column">
        <div class="upload-box" id="searchPreviewBox">
            <div id="searchPreviewPlaceholder">
                <p>Query Photo Preview</p>
                <p class="muted small">Select or capture an image.</p>
            </div>
            <img id="searchPreviewImage" src="" alt="Search preview" style="display:none;">
            <video id="searchWebcamVideo" autoplay playsinline style="display:none; width:100%; border-radius:0.7rem;"></video>
        </div>
        <form id="searchForm" method="post" enctype="multipart/form-data" class="form-card">
            <div class="form-group">
                <label for="photo">Query Photo</label>
                <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn primary">Search</button>
                <button type="button" class="btn secondary" id="searchStartWebcamBtn">Use Webcam</button>
                <button type="button" class="btn primary" id="searchCaptureBtn" style="display:none;">Capture &amp; Search</button>
            </div>
        </form>
    </div>

    <div class="column">
        <div class="result-card">
            <h2>Search Result</h2>
            <?php if ($result === null && empty($errors)): ?>
                <p class="muted">Results will appear here after you run a search.</p>
            <?php elseif ($result !== null && isset($result['match_id']) && $result['match_id'] !== null && $matchPerson): ?>
                <div class="match-header">
                    <div class="match-status">
                        <span class="status-dot pulse"></span>
                        <span>Match Found</span>
                    </div>
                    <?php if ($confidence !== null): ?>
                        <div class="confidence">
                            <span><?php echo number_format($confidence, 1); ?>%</span> confidence
                        </div>
                    <?php endif; ?>
                </div>
                <div class="match-body">
                    <div class="match-photo">
                        <?php
                        $hasMatchPhoto = !empty($matchPerson['photo_path']) && file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . $matchPerson['photo_path']);
                        $matchPhotoUrl = $hasMatchPhoto ? '../' . ltrim($matchPerson['photo_path'], '/\\') : null;
                        ?>
                        <?php if ($hasMatchPhoto && $matchPhotoUrl !== null): ?>
                            <img src="<?php echo htmlspecialchars($matchPhotoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Matched person photo">
                        <?php else: ?>
                            <div class="placeholder-photo">No Photo</div>
                        <?php endif; ?>
                    </div>
                    <div class="match-details">
                        <h3><?php echo htmlspecialchars($matchPerson['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <table class="details-table">
                            <tbody>
                            <tr>
                                <th>Age</th>
                                <td><?php echo htmlspecialchars((string)$matchPerson['age'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>Gender</th>
                                <td><?php echo htmlspecialchars((string)$matchPerson['gender'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?php echo htmlspecialchars((string)$matchPerson['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars((string)$matchPerson['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                <td><?php echo nl2br(htmlspecialchars((string)$matchPerson['address'], ENT_QUOTES, 'UTF-8')); ?></td>
                            </tr>
                            <tr>
                                <th>Notes</th>
                                <td><?php echo nl2br(htmlspecialchars((string)$matchPerson['notes'], ENT_QUOTES, 'UTF-8')); ?></td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="form-actions">
                            <a href="view.php?id=<?php echo (int)$matchPerson['id']; ?>" class="btn secondary small">View Details</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-match">
                    <p class="muted"><strong>No matching person found.</strong></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
    const searchInput = document.getElementById('photo');
    const searchPreviewImage = document.getElementById('searchPreviewImage');
    const searchPreviewPlaceholder = document.getElementById('searchPreviewPlaceholder');

    const searchWebcamVideo = document.getElementById('searchWebcamVideo');
    const searchStartWebcamBtn = document.getElementById('searchStartWebcamBtn');
    const searchCaptureBtn = document.getElementById('searchCaptureBtn');
    const searchForm = document.getElementById('searchForm');

    // File-input preview
    if (searchInput) {
        searchInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                searchPreviewImage.style.display = 'none';
                searchPreviewPlaceholder.style.display = 'flex';
                searchPreviewImage.src = '';
                if (searchWebcamVideo) searchWebcamVideo.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                searchPreviewImage.src = e.target.result;
                searchPreviewImage.style.display = 'block';
                searchPreviewPlaceholder.style.display = 'none';
                if (searchWebcamVideo) searchWebcamVideo.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    }

    let searchStream = null;

    async function startSearchWebcam() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Webcam is not supported in this browser.');
            return;
        }
        try {
            searchStream = await navigator.mediaDevices.getUserMedia({ video: true });
            searchWebcamVideo.srcObject = searchStream;
            searchWebcamVideo.style.display = 'block';
            searchPreviewImage.style.display = 'none';
            searchPreviewPlaceholder.style.display = 'none';
            searchCaptureBtn.style.display = 'inline-flex';
        } catch (err) {
            alert('Could not access webcam: ' + err.message);
        }
    }

    function stopSearchWebcam() {
        if (searchStream) {
            searchStream.getTracks().forEach(function (t) { t.stop(); });
            searchStream = null;
        }
        if (searchWebcamVideo) {
            searchWebcamVideo.style.display = 'none';
            searchWebcamVideo.srcObject = null;
        }
        if (searchCaptureBtn) {
            searchCaptureBtn.style.display = 'none';
        }
    }

    function captureAndSearch() {
        if (!searchWebcamVideo || !searchStream) {
            alert('Webcam is not active.');
            return;
        }

        var canvas = document.createElement('canvas');
        canvas.width = searchWebcamVideo.videoWidth || 640;
        canvas.height = searchWebcamVideo.videoHeight || 480;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(searchWebcamVideo, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {
            if (!blob) {
                alert('Failed to capture image.');
                return;
            }

            var url = URL.createObjectURL(blob);
            searchPreviewImage.src = url;
            searchPreviewImage.style.display = 'block';
            searchPreviewPlaceholder.style.display = 'none';

            var formData = new FormData(searchForm);
            formData.delete('photo');
            formData.append('photo', blob, 'webcam_query.jpg');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(function (response) {
                return response.text();
            }).then(function (html) {
                document.open();
                document.write(html);
                document.close();
            }).catch(function (err) {
                alert('Failed to submit search: ' + err.message);
            }).finally(function () {
                stopSearchWebcam();
            });
        }, 'image/jpeg', 0.9);
    }

    if (searchStartWebcamBtn) {
        searchStartWebcamBtn.addEventListener('click', startSearchWebcam);
    }
    if (searchCaptureBtn) {
        searchCaptureBtn.addEventListener('click', captureAndSearch);
    }
</script>

</main>
</body>
</html>

