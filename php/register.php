<?php
require_once __DIR__ . '/config.php';

$errors = array();
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $age     = trim(isset($_POST['age']) ? $_POST['age'] : '');
    $gender  = trim(isset($_POST['gender']) ? $_POST['gender'] : '');
    $phone   = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
    $email   = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
    $notes   = trim(isset($_POST['notes']) ? $_POST['notes'] : '');

    if ($name === '') {
        $errors[] = 'Full Name is required.';
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Photo is required.';
    }

    $photoPathRelative = null;
    $encodingJson = null;

    if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $originalName = $_FILES['photo']['name'];
        $tmpPath = $_FILES['photo']['tmp_name'];

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Invalid file type. Allowed: JPG, JPEG, PNG, WEBP.';
        } else {
            $uniqueName = 'person_' . uniqid('', true) . '.' . $ext;
            $destPath = UPLOAD_DIR . $uniqueName;

            if (!move_uploaded_file($tmpPath, $destPath)) {
                $errors[] = 'Failed to save uploaded photo.';
            } else {
                $photoPathRelative = 'uploads/' . $uniqueName;

                $curlFile = new CURLFile($destPath, mime_content_type($destPath), $uniqueName);
                [$data, $error] = call_python_api('encode', [], ['image' => $curlFile]);

                if ($error !== null) {
                    $errors[] = $error;
                    @unlink($destPath);
                    $photoPathRelative = null;
                } elseif (isset($data['error'])) {
                    $errors[] = (string)$data['error'];
                    @unlink($destPath);
                    $photoPathRelative = null;
                } elseif (!isset($data['encoding']) || !is_array($data['encoding'])) {
                    $errors[] = 'Invalid encoding data from Python API.';
                    @unlink($destPath);
                    $photoPathRelative = null;
                } else {
                    $encodingJson = json_encode($data['encoding']);
                }
            }
        }
    }

    if (empty($errors) && $photoPathRelative !== null && $encodingJson !== null) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('INSERT INTO persons 
                (name, age, gender, address, phone, email, notes, photo_path, face_encoding) 
                VALUES (:name, :age, :gender, :address, :phone, :email, :notes, :photo_path, :face_encoding)');

            $ageValue = $age !== '' ? (int)$age : null;

            $stmt->execute([
                ':name'          => $name,
                ':age'           => $ageValue,
                ':gender'        => $gender,
                ':address'       => $address,
                ':phone'         => $phone,
                ':email'         => $email,
                ':notes'         => $notes,
                ':photo_path'    => $photoPathRelative,
                ':face_encoding' => $encodingJson,
            ]);

            $successMessage = 'Person registered successfully.';
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$activePage = 'register';
include __DIR__ . '/nav.php';
?>

<section class="page-header">
    <div>
        <h1>Register Person</h1>
        <p class="muted">Upload a photo and enter details to register a new person.</p>
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

<?php if ($successMessage !== null): ?>
    <div class="alert success">
        <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
        <a href="index.php" class="inline-link">Back to Dashboard</a>
    </div>
<?php endif; ?>

<section class="two-column">
    <div class="column">
        <div class="upload-box" id="photoPreviewBox">
            <div id="photoPreviewPlaceholder">
                <p>Photo Preview</p>
                <p class="muted small">Select or capture an image.</p>
            </div>
            <img id="photoPreviewImage" src="" alt="Photo preview" style="display:none;">
            <video id="webcamVideo" autoplay playsinline style="display:none; width:100%; border-radius:0.7rem;"></video>
        </div>
        <div class="form-actions" style="margin-top:0.6rem;">
            <label class="file-input-label">
                <span>Choose Photo</span>
                <input type="file" name="photo" id="photoInput" form="registerForm" accept=".jpg,.jpeg,.png,.webp">
            </label>
            <button type="button" class="btn secondary" id="startWebcamBtn">Use Webcam</button>
            <button type="button" class="btn primary" id="captureBtn" style="display:none;">Capture</button>
        </div>
        <p class="muted small">Supported: file upload or webcam capture.</p>
    </div>

    <div class="column">
        <form id="registerForm" method="post" enctype="multipart/form-data" class="form-card">
            <div class="form-group">
                <label for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" required value="<?php echo isset($name) ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" min="0" value="<?php echo isset($age) ? htmlspecialchars($age, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select</option>
                        <option value="Male" <?php echo (isset($gender) && $gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($gender) && $gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (isset($gender) && $gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"><?php echo isset($address) ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"><?php echo isset($notes) ? htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary">Register</button>
                <a href="index.php" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
    const photoInput = document.getElementById('photoInput');
    const previewImage = document.getElementById('photoPreviewImage');
    const previewPlaceholder = document.getElementById('photoPreviewPlaceholder');

    const webcamVideo = document.getElementById('webcamVideo');
    const startWebcamBtn = document.getElementById('startWebcamBtn');
    const captureBtn = document.getElementById('captureBtn');
    const form = document.getElementById('registerForm');

    // File-input live preview
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                previewImage.style.display = 'none';
                previewPlaceholder.style.display = 'flex';
                previewImage.src = '';
                if (webcamVideo) webcamVideo.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewImage.src = e.target.result;
                previewImage.style.display = 'block';
                previewPlaceholder.style.display = 'none';
                if (webcamVideo) webcamVideo.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    }

    let stream = null;

    async function startWebcam() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Webcam is not supported in this browser.');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            webcamVideo.srcObject = stream;
            webcamVideo.style.display = 'block';
            previewImage.style.display = 'none';
            previewPlaceholder.style.display = 'none';
            captureBtn.style.display = 'inline-flex';
        } catch (err) {
            alert('Could not access webcam: ' + err.message);
        }
    }

    function stopWebcam() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        if (webcamVideo) {
            webcamVideo.style.display = 'none';
            webcamVideo.srcObject = null;
        }
        if (captureBtn) {
            captureBtn.style.display = 'none';
        }
    }

    function captureAndSubmit() {
        if (!webcamVideo || !stream) {
            alert('Webcam is not active.');
            return;
        }

        var canvas = document.createElement('canvas');
        canvas.width = webcamVideo.videoWidth || 640;
        canvas.height = webcamVideo.videoHeight || 480;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(webcamVideo, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {
            if (!blob) {
                alert('Failed to capture image.');
                return;
            }

            var url = URL.createObjectURL(blob);
            previewImage.src = url;
            previewImage.style.display = 'block';
            previewPlaceholder.style.display = 'none';

            var formData = new FormData(form);
            formData.delete('photo');
            formData.append('photo', blob, 'webcam_capture.jpg');

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
                alert('Failed to submit form: ' + err.message);
            }).finally(function () {
                stopWebcam();
            });
        }, 'image/jpeg', 0.9);
    }

    if (startWebcamBtn) {
        startWebcamBtn.addEventListener('click', startWebcam);
    }
    if (captureBtn) {
        captureBtn.addEventListener('click', captureAndSubmit);
    }
</script>

</main>
</body>
</html>

