from flask import Flask, request, jsonify
import face_recognition
import numpy as np
from PIL import Image
import io
import json

app = Flask(__name__)


def load_image_from_file_storage(file_storage):
    try:
        # Read file content into memory and load with PIL to be tolerant of formats
        img_bytes = file_storage.read()
        pil_image = Image.open(io.BytesIO(img_bytes)).convert("RGB")
        return np.array(pil_image)
    except Exception:
        return None


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/encode", methods=["POST"])
def encode():
    if "image" not in request.files:
        return jsonify({"error": "Image file is required"}), 400

    file = request.files["image"]
    image = load_image_from_file_storage(file)
    if image is None:
        return jsonify({"error": "Unable to read image"}), 400

    face_locations = face_recognition.face_locations(image)
    if len(face_locations) == 0:
        return jsonify({"error": "No face detected"}), 400
    if len(face_locations) > 1:
        return jsonify({"error": "Multiple faces detected"}), 400

    encodings = face_recognition.face_encodings(image, known_face_locations=face_locations)
    if not encodings:
        return jsonify({"error": "Failed to compute face encoding"}), 500

    encoding = encodings[0]
    return jsonify({"encoding": encoding.tolist()})


@app.route("/search", methods=["POST"])
def search():
    if "image" not in request.files:
        return jsonify({"error": "Image file is required"}), 400
    if "encodings" not in request.form:
        return jsonify({"error": "Encodings payload is required"}), 400

    try:
        encodings_payload = json.loads(request.form["encodings"])
    except Exception:
        return jsonify({"error": "Invalid encodings JSON"}), 400

    if not isinstance(encodings_payload, list) or not encodings_payload:
        return jsonify({"error": "Encodings must be a non-empty list"}), 400

    known_ids = []
    known_encodings = []
    for item in encodings_payload:
        try:
            pid = int(item["id"])
            vec = np.array(item["encoding"], dtype="float64")
            if vec.shape != (128,):
                continue
            known_ids.append(pid)
            known_encodings.append(vec)
        except Exception:
            continue

    if not known_encodings:
        return jsonify({"error": "No valid encodings supplied"}), 400

    file = request.files["image"]
    image = load_image_from_file_storage(file)
    if image is None:
        return jsonify({"error": "Unable to read image"}), 400

    face_locations = face_recognition.face_locations(image)
    if len(face_locations) == 0:
        return jsonify({"error": "No face detected in query image"}), 400
    if len(face_locations) > 1:
        return jsonify({"error": "Multiple faces detected in query image"}), 400

    query_encodings = face_recognition.face_encodings(image, known_face_locations=face_locations)
    if not query_encodings:
        return jsonify({"error": "Failed to compute face encoding for query image"}), 500

    query_encoding = query_encodings[0]

    known_encodings_arr = np.stack(known_encodings, axis=0)
    distances = face_recognition.face_distance(known_encodings_arr, query_encoding)

    best_index = int(np.argmin(distances))
    best_distance = float(distances[best_index])

    threshold = 0.50
    if best_distance <= threshold:
        # Convert distance to a simple confidence metric (0-100) where lower distance = higher confidence.
        # This is heuristic and not a calibrated probability.
        confidence = max(0.0, 100.0 * (1.0 - best_distance / threshold))
        return jsonify(
            {
                "match_id": known_ids[best_index],
                "confidence": round(confidence, 1),
                "distance": best_distance,
            }
        )

    return jsonify({"match_id": None, "message": "No match found"})


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5001, debug=False)

