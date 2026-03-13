Simple Face App
===============

### Overview

**Simple Face App** is a small PHP + Python demo that lets an admin:

- **Register a person** by uploading a face photo and entering personal details. The app sends the photo to a Python Flask API that uses the `face_recognition` library to compute a 128‑dimension face encoding, then stores it in MySQL.
- **Search for a person** by uploading a new photo. The app compares the detected face against all stored encodings and, if a match is found, shows the matched person’s profile and a confidence score.

### Tech Stack

- **Frontend + Backend**: Plain PHP (no frameworks)
- **Database**: MySQL
- **Face Recognition Service**: Python Flask (separate local API)
- **Face Recognition Library**: `face_recognition` (dlib based)
- **Styling**: Plain CSS, dark professional theme

Project Structure
-----------------

```text
faceapp/
├── php/
│   ├── config.php       # DB credentials, Python API URL, PDO helper
│   ├── index.php        # Dashboard showing all registered persons
│   ├── register.php     # Form to register a new person with photo
│   ├── search.php       # Upload a photo to search/identify a person
│   ├── view.php         # View full details of a single person
│   ├── delete.php       # Delete a person and their photo file
│   ├── nav.php          # Shared navigation bar included on all pages
│   └── style.css        # Stylesheet for all pages (dark theme)
├── python/
│   └── app.py           # Flask API with /encode, /search, /health endpoints
├── sql/
│   └── schema.sql       # MySQL database + persons table schema
└── uploads/             # Folder where uploaded photos are stored
```

MySQL Schema
------------

Database name: **`faceapp`**

Main table: **`persons`** with columns:

- **id** `INT AUTO_INCREMENT PRIMARY KEY`
- **name** `VARCHAR(100) NOT NULL`
- **age** `INT`
- **gender** `VARCHAR(20)`
- **address** `TEXT`
- **phone** `VARCHAR(20)`
- **email** `VARCHAR(100)`
- **notes** `TEXT`
- **photo_path** `VARCHAR(255)` – relative path like `uploads/person_abc123.jpg`
- **face_encoding** `JSON` – stores the 128‑float encoding from Python
- **created_at** `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`

See `sql/schema.sql` for the exact `CREATE TABLE` statement.

Python Flask API
----------------

The Flask app (in `python/app.py`) exposes three endpoints and runs on **port 5001**:

- **GET `/health`**  
  Returns `{"status": "ok"}` to verify the service is running.

- **POST `/encode`**
  - Accepts: multipart form with an **`image`** file.
  - Behavior:
    - Loads the image, detects faces, and enforces exactly one face.
    - Computes a 128‑dimensional face encoding.
  - Responses:
    - On success:  
      `{"encoding": [...128 floats...]}`  
    - On error:  
      `{"error": "No face detected"}` (HTTP 400)  
      `{"error": "Multiple faces detected"}` (HTTP 400)

- **POST `/search`**
  - Accepts: multipart form with:
    - `image` file – the query photo
    - `encodings` – JSON string of objects:  
      `[{"id": 1, "encoding": [...128 floats...]}, ...]`
  - Behavior:
    - Encodes the query photo (single face required).
    - Computes distances to all stored encodings using `face_recognition.face_distance`.
    - Finds the closest match and compares it to a configurable threshold (`0.50` in code).
  - Responses:
    - Match found (distance ≤ threshold):  
      `{"match_id": 3, "confidence": 87.4, "distance": 0.23}`
    - No match (distance > threshold):  
      `{"match_id": null, "message": "No match found"}`

PHP Pages – Behavior
--------------------

### `config.php`

- Defines:
  - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
  - `PYTHON_API` = `http://localhost:5001`
  - `UPLOAD_DIR` = absolute path to the `uploads/` folder
- Provides:
  - `db()` – singleton PDO connection with exception error mode.
  - `call_python_api($endpoint, $postFields, $files)` – helper using cURL.

### `register.php`

- Shows a two‑column layout:
  - **Left**: photo upload area with live preview.
  - **Right**: form fields:
    - Full Name (required)
    - Age
    - Gender (Male / Female / Other)
    - Phone
    - Email
    - Address (textarea)
    - Notes (textarea)
    - Photo (required)
- On submit:
  - Validates that **name** and **photo** are present.
  - Validates photo extension: `jpg`, `jpeg`, `png`, `webp`.
  - Saves the image into `uploads/` with a unique filename via `uniqid()`.
  - Sends the saved file to Python **`/encode`** using PHP cURL (`CURLFile`).
  - If Python returns an error:
    - Deletes the uploaded file.
    - Displays the error.
  - On success:
    - Stores all person details + `face_encoding` (JSON string) in MySQL.
    - Shows a success message with a link back to the dashboard.
  - Uses client‑side JavaScript (`FileReader`) to display a live image preview.

### `search.php`

- Two‑column layout:
  - **Left**: query photo upload with live preview + submit button.
  - **Right**: result panel.
- On submit:
  - Fetches all `id, face_encoding` rows from `persons` where `face_encoding IS NOT NULL`.
  - Builds a JSON array of `{id, encoding}` objects.
  - Sends the query photo and this JSON payload to Python **`/search`**.
  - If Python returns `match_id`:
    - Loads full details of that person from MySQL.
    - Displays:
      - Person’s photo
      - Name
      - Confidence percentage with a pulsing green indicator
      - All details in a table.
  - If no match:
    - Shows a clear **“No matching person found”** message in the result panel.

### `index.php`

- Runs:
  - `SELECT COUNT(*)` to show total registered persons.
  - `SELECT id, name, age, gender, photo_path, created_at FROM persons ORDER BY created_at DESC`.
- Displays:
  - Page header with **“Register Person”** and **“Search by Face”** buttons.
  - A responsive grid of **person cards**:
    - Photo thumbnail
    - Name, age, gender
    - Registration date
    - **View** and **Delete** buttons.
  - An empty‑state card if no persons are registered.

### `view.php`

- Accepts `?id=X` in the URL.
- Loads full details for that person from the database.
- Shows:
  - Large photo
  - All fields (name, age, gender, phone, email, address, notes, created_at) in a detail card.
- Includes **Back** and **Delete** buttons.

### `delete.php`

- Accepts `?id=X`.
- Fetches `photo_path` from MySQL for that record.
- Deletes the physical photo file from `uploads/` using `unlink()`, if it exists.
- Deletes the database row.
- Redirects back to `index.php`.

### `nav.php`

- Shared navigation bar rendered at the top of every page:
  - Links: **Dashboard**, **Register**, **Search**.
  - Highlights the active link using an `$activePage` variable.

Security & Validation
---------------------

- All database operations use **PDO prepared statements**.
- File uploads:
  - Validate extension against a whitelist: `jpg`, `jpeg`, `png`, `webp`.
- All user‑supplied values are escaped with `htmlspecialchars()` before rendering.
- The `face_encoding` JSON column is never rendered back to the browser.

UI & Styling
------------

- Dark theme with deep navy / charcoal background (see `php/style.css`).
- Card‑based layout for listings and detail views.
- **Register page**:
  - Two columns: photo upload (with preview) on the left, form on the right.
- **Search page**:
  - Upload form on the left, result panel on the right.
- Responsive:
  - On mobile, the columns collapse into a single column.
- Live image preview on both upload forms via JavaScript `FileReader`.
- Uses Google Font **DM Sans**.
- When a match is found, the result panel shows a **green pulsing dot** next to the confidence percentage.

Setup Instructions
------------------

1. **Import database schema**
   - Open phpMyAdmin (or your preferred MySQL client).
   - Import `sql/schema.sql`.

2. **Configure PHP**
   - Open `php/config.php`.
   - Set:
     - `DB_HOST`
     - `DB_NAME` (default: `faceapp`)
     - `DB_USER`
     - `DB_PASS`
   - Make sure the `uploads/` directory exists and is writable by PHP (the app will attempt to create it on first run).

3. **Place PHP files into your web root**
   - For WAMP/XAMPP, copy the `php/` folder into your web root (e.g. `C:\wamp64\www\faceapp\php` or similar).
   - Access it in the browser as:  
     `http://localhost/faceapp/php/`

4. **Set up Python environment**
   - Create and activate a virtual environment (recommended).
   - Install dependencies:

     ```bash
     pip install flask face_recognition numpy Pillow
     ```

   - **Note**: `face_recognition` depends on `dlib`. Make sure you have a working `dlib` installation for your OS and Python version.

5. **Run the Flask API**

   From the `python/` folder:

   ```bash
   python app.py
   ```

   - The API will run on `http://127.0.0.1:5001`.
   - Test it in a browser or with curl:

     ```bash
     curl http://127.0.0.1:5001/health
     ```

     You should see:

     ```json
     {"status": "ok"}
     ```

6. **Use the web app**

   - Visit: `http://localhost/faceapp/php/`
   - Register a few people with clear, front‑facing photos.
   - Use the **Search by Face** page to upload a new photo and test matching.
