<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Marcar asistencia</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f5f7;
            color: #17202a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: #f3f5f7;
        }

        .page-header {
            border-bottom: 1px solid #dce1e6;
            background: #ffffff;
        }

        .page-header__inner,
        main {
            width: min(100% - 32px, 960px);
            margin: 0 auto;
        }

        .page-header__inner {
            display: flex;
            align-items: center;
            min-height: 68px;
        }

        h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0;
        }

        main {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(260px, 0.8fr);
            gap: 24px;
            padding: 32px 0;
        }

        .camera-panel {
            min-width: 0;
        }

        .video-frame {
            position: relative;
            overflow: hidden;
            width: 100%;
            aspect-ratio: 4 / 3;
            border: 1px solid #cfd6dc;
            border-radius: 6px;
            background: #15191d;
        }

        video {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }

        .camera-status {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 24px;
            background: #15191d;
            color: #f7f8f9;
            text-align: center;
            line-height: 1.5;
        }

        .camera-status[hidden] {
            display: none;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
        }

        button {
            min-height: 46px;
            padding: 0 20px;
            border: 1px solid #12643a;
            border-radius: 6px;
            background: #147a46;
            color: #ffffff;
            font: inherit;
            font-weight: 700;
            letter-spacing: 0;
            cursor: pointer;
        }

        button:hover:not(:disabled) {
            background: #116b3d;
        }

        button:focus-visible {
            outline: 3px solid #8dc8ff;
            outline-offset: 2px;
        }

        button:disabled {
            border-color: #aab2b9;
            background: #aab2b9;
            cursor: not-allowed;
        }

        .result-panel {
            align-self: start;
            min-height: 180px;
            padding: 24px;
            border: 1px solid #d4dbe1;
            border-left: 4px solid #65717c;
            border-radius: 6px;
            background: #ffffff;
        }

        .result-panel[data-state="success"] {
            border-left-color: #147a46;
            background: #f3fbf6;
        }

        .result-panel[data-state="warning"] {
            border-left-color: #b7791f;
            background: #fffaf0;
        }

        .result-panel[data-state="error"] {
            border-left-color: #c53030;
            background: #fff7f7;
        }

        .result-panel[data-state="loading"] {
            border-left-color: #2563a8;
            background: #f5f9ff;
        }

        .result-label {
            margin: 0 0 8px;
            color: #53606c;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .result-title {
            margin: 0;
            font-size: 1.35rem;
            line-height: 1.3;
            letter-spacing: 0;
        }

        .result-detail {
            margin: 10px 0 0;
            color: #46515b;
            line-height: 1.5;
        }

        canvas {
            display: none;
        }

        @media (max-width: 760px) {
            .page-header__inner,
            main {
                width: min(100% - 24px, 640px);
            }

            main {
                grid-template-columns: 1fr;
                padding: 20px 0;
            }

            .result-panel {
                min-height: 150px;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="page-header__inner">
            <h1>Control de asistencia</h1>
        </div>
    </header>

    <main>
        <section class="camera-panel" aria-labelledby="camera-heading">
            <h2 id="camera-heading" hidden>Cámara</h2>
            <div class="video-frame">
                <video id="camera" autoplay playsinline muted></video>
                <p id="camera-status" class="camera-status">Solicitando acceso a la cámara...</p>
            </div>

            <div class="actions">
                <button id="mark-button" type="button" disabled>Marcar asistencia</button>
            </div>

            <canvas id="capture-canvas" aria-hidden="true"></canvas>
        </section>

        <section id="result" class="result-panel" data-state="idle" aria-live="polite" aria-atomic="true">
            <p class="result-label">Estado</p>
            <h2 id="result-title" class="result-title">Cámara en preparación</h2>
            <p id="result-detail" class="result-detail">El resultado de la marcación aparecerá aquí.</p>
        </section>
    </main>

    <script>
        const video = document.getElementById('camera');
        const canvas = document.getElementById('capture-canvas');
        const button = document.getElementById('mark-button');
        const cameraStatus = document.getElementById('camera-status');
        const result = document.getElementById('result');
        const resultTitle = document.getElementById('result-title');
        const resultDetail = document.getElementById('result-detail');
        let cameraStream = null;

        function showResult(state, title, detail = '') {
            result.dataset.state = state;
            resultTitle.textContent = title;
            resultDetail.textContent = detail;
        }

        function cameraErrorMessage(error) {
            if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
                return 'No se pudo acceder a la cámara porque el permiso fue denegado. Habilítalo en la configuración del navegador.';
            }

            if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                return 'No se encontró ninguna cámara disponible en este equipo.';
            }

            if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
                return 'La cámara está siendo usada por otra aplicación o no puede iniciarse.';
            }

            return 'No fue posible iniciar la cámara. Revisa el dispositivo y vuelve a cargar la página.';
        }

        async function startCamera() {
            if (!navigator.mediaDevices?.getUserMedia) {
                const message = 'Este navegador no permite usar la cámara. Abre la página en un navegador actualizado mediante HTTPS o localhost.';
                cameraStatus.textContent = message;
                showResult('error', 'Cámara no disponible', message);
                return;
            }

            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 960 },
                    },
                    audio: false,
                });

                video.srcObject = cameraStream;
                await video.play();
                cameraStatus.hidden = true;
                button.disabled = false;
                showResult('idle', 'Cámara lista', 'Colócate frente a la cámara para marcar tu asistencia.');
            } catch (error) {
                const message = cameraErrorMessage(error);
                cameraStatus.textContent = message;
                showResult('error', 'No se pudo iniciar la cámara', message);
            }
        }

        function captureFrame() {
            if (!video.videoWidth || !video.videoHeight) {
                return Promise.reject(new Error('La cámara todavía no está lista para capturar.'));
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            return new Promise((resolve, reject) => {
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve(blob);
                        return;
                    }

                    reject(new Error('No se pudo generar la fotografía.'));
                }, 'image/jpeg', 0.9);
            });
        }

        async function markAttendance() {
            button.disabled = true;
            showResult('loading', 'Procesando marcación', 'Espera mientras verificamos tu identidad.');

            try {
                const photo = await captureFrame();
                const formData = new FormData();
                formData.append('foto', photo, 'captura.jpg');

                const response = await fetch('/asistencia/marcar', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: formData,
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok && data.tipo) {
                    const type = data.tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada';
                    showResult('success', type, `${data.nombre_completo} · ${data.hora}`);
                    return;
                }

                if (response.ok && data.reconocido === false) {
                    showResult('warning', 'Rostro no reconocido', data.mensaje);
                    return;
                }

                if (response.status === 409) {
                    showResult('warning', 'Marcación ya completada', data.error);
                    return;
                }

                showResult('error', 'No se pudo marcar la asistencia', data.error || data.message || 'Ocurrió un error inesperado.');
            } catch (error) {
                showResult('error', 'No se pudo marcar la asistencia', error.message || 'Revisa la conexión e inténtalo nuevamente.');
            } finally {
                button.disabled = !cameraStream;
            }
        }

        button.addEventListener('click', markAttendance);
        window.addEventListener('pagehide', () => {
            cameraStream?.getTracks().forEach((track) => track.stop());
        });

        startCamera();
    </script>
</body>
</html>