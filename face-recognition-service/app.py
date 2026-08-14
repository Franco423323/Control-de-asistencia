import base64
import binascii
import json

import cv2
import numpy as np
from deepface import DeepFace
from flask import Flask, jsonify, request


app = Flask(__name__)

SFACE_COSINE_THRESHOLD = 0.593


@app.get("/health")
def health():
    return jsonify(status="ok"), 200


def decode_image():
    uploaded_file = request.files.get("imagen")

    if uploaded_file is not None:
        image_bytes = uploaded_file.read()
    else:
        encoded_image = request.form.get("imagen", "").strip()

        if not encoded_image:
            raise ValueError('El campo "imagen" es obligatorio')

        if encoded_image.startswith("data:"):
            try:
                _, encoded_image = encoded_image.split(",", 1)
            except ValueError as error:
                raise ValueError("La imagen en base64 no es válida") from error

        try:
            image_bytes = base64.b64decode(encoded_image, validate=True)
        except (binascii.Error, ValueError) as error:
            raise ValueError("La imagen en base64 no es válida") from error

    if not image_bytes:
        raise ValueError("La imagen está vacía")

    image = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_COLOR)

    if image is None:
        raise ValueError("El contenido enviado no es una imagen válida")

    return image


def is_face_not_detected_error(error):
    message = str(error).lower()
    return "face could not be detected" in message or "face is not detected" in message


def extract_single_face_encoding(image):
    faces = DeepFace.represent(
        img_path=image,
        model_name="SFace",
        detector_backend="mtcnn",
        enforce_detection=True,
    )

    if not faces:
        raise ValueError("No se detectó ningún rostro en la imagen")

    if len(faces) > 1:
        raise ValueError(
            "Se detectó más de un rostro, la foto debe tener una sola persona"
        )

    return faces[0]["embedding"]


def get_face_encoding_response(image):
    try:
        return extract_single_face_encoding(image), None
    except ValueError as error:
        if (
            str(error) == "No se detectó ningún rostro en la imagen"
            or is_face_not_detected_error(error)
        ):
            return None, (
                jsonify(error="No se detectó ningún rostro en la imagen"),
                422,
            )

        if str(error).startswith("Se detectó más de un rostro"):
            return None, (jsonify(error=str(error)), 422)

        app.logger.exception("DeepFace no pudo procesar la imagen")
        return None, (jsonify(error="No se pudo procesar la imagen"), 500)
    except Exception:
        app.logger.exception("Error inesperado al procesar la imagen")
        return None, (jsonify(error="No se pudo procesar la imagen"), 500)


def parse_known_encodings():
    encoded_payload = request.form.get("encodings_conocidos", "").strip()

    if not encoded_payload:
        return []

    try:
        known_encodings = json.loads(encoded_payload)
    except json.JSONDecodeError as error:
        raise ValueError(
            'El campo "encodings_conocidos" no contiene JSON válido'
        ) from error

    if not isinstance(known_encodings, list):
        raise ValueError('El campo "encodings_conocidos" debe ser una lista')

    parsed_encodings = []

    for index, item in enumerate(known_encodings):
        if (
            not isinstance(item, dict)
            or "personal_id" not in item
            or "encoding" not in item
        ):
            raise ValueError(
                f"El encoding conocido en la posición {index} no es válido"
            )

        try:
            encoding = np.asarray(item["encoding"], dtype=np.float64)
        except (TypeError, ValueError) as error:
            raise ValueError(
                f"El encoding conocido en la posición {index} no es válido"
            ) from error

        if (
            encoding.ndim != 1
            or encoding.size == 0
            or not np.all(np.isfinite(encoding))
            or np.linalg.norm(encoding) == 0
        ):
            raise ValueError(
                f"El encoding conocido en la posición {index} no es válido"
            )

        parsed_encodings.append((item["personal_id"], encoding))

    return parsed_encodings


@app.post("/enroll")
def enroll():
    try:
        image = decode_image()
    except ValueError as error:
        return jsonify(error=str(error)), 400

    encoding, error_response = get_face_encoding_response(image)

    if error_response is not None:
        return error_response

    return jsonify(encoding=encoding), 200


@app.post("/recognize")
def recognize():
    try:
        known_encodings = parse_known_encodings()
    except ValueError as error:
        return jsonify(error=str(error)), 400

    if not known_encodings:
        return jsonify(error="No hay personal enrolado para comparar"), 422

    try:
        image = decode_image()
    except ValueError as error:
        return jsonify(error=str(error)), 400

    encoding, error_response = get_face_encoding_response(image)

    if error_response is not None:
        return error_response

    candidate = np.asarray(encoding, dtype=np.float64)

    if any(known.size != candidate.size for _, known in known_encodings):
        return jsonify(error="Los encodings conocidos no son compatibles"), 400

    known_matrix = np.vstack([known for _, known in known_encodings])
    similarities = (known_matrix @ candidate) / (
        np.linalg.norm(known_matrix, axis=1) * np.linalg.norm(candidate)
    )
    distances = 1 - similarities
    closest_index = int(np.argmin(distances))
    closest_distance = float(distances[closest_index])

    if closest_distance < SFACE_COSINE_THRESHOLD:
        return jsonify(
            reconocido=True,
            personal_id=known_encodings[closest_index][0],
            distancia=closest_distance,
        ), 200

    return jsonify(reconocido=False), 200


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000)