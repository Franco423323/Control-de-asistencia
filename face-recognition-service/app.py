import base64
import binascii

import cv2
import numpy as np
from deepface import DeepFace
from flask import Flask, jsonify, request


app = Flask(__name__)


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


@app.post("/enroll")
def enroll():
    try:
        image = decode_image()
    except ValueError as error:
        return jsonify(error=str(error)), 400

    try:
        faces = DeepFace.represent(
            img_path=image,
            model_name="SFace",
            detector_backend="mtcnn",
            enforce_detection=True,
        )
    except ValueError as error:
        if is_face_not_detected_error(error):
            return jsonify(error="No se detectó ningún rostro en la imagen"), 422

        app.logger.exception("DeepFace no pudo procesar la imagen")
        return jsonify(error="No se pudo procesar la imagen"), 500
    except Exception:
        app.logger.exception("Error inesperado al procesar la imagen")
        return jsonify(error="No se pudo procesar la imagen"), 500

    if not faces:
        return jsonify(error="No se detectó ningún rostro en la imagen"), 422

    if len(faces) > 1:
        return jsonify(
            error=(
                "Se detectó más de un rostro, la foto debe tener una sola persona"
            )
        ), 422

    return jsonify(encoding=faces[0]["embedding"]), 200


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000)