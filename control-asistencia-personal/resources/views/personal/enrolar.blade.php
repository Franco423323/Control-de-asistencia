<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $personal ? 'Enrolar personal' : 'Crear personal' }}</title>
    <style>
        :root {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #17202a;
            background: #f3f5f7;
        }

        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #f3f5f7; }
        header { border-bottom: 1px solid #dce1e6; background: #fff; }
        header div, main { width: min(100% - 32px, 1040px); margin: 0 auto; }
        header div { display: flex; align-items: center; min-height: 68px; }
        h1 { margin: 0; font-size: 1.25rem; }
        main { padding: 28px 0 40px; }
        h2 { margin: 0 0 18px; font-size: 1.2rem; }
        .card { margin-bottom: 24px; padding: 24px; border: 1px solid #d4dbe1; border-radius: 7px; background: #fff; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: grid; gap: 7px; color: #34404b; font-size: .9rem; font-weight: 700; }
        input, select { width: 100%; min-height: 44px; padding: 8px 11px; border: 1px solid #b9c2ca; border-radius: 5px; background: #fff; color: #17202a; font: inherit; }
        input:focus, select:focus { border-color: #236aa5; outline: 3px solid #d8ebfb; }
        .full { grid-column: 1 / -1; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        button, .button-link { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border: 1px solid #12643a; border-radius: 5px; background: #147a46; color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        button:hover:not(:disabled), .button-link:hover { background: #116b3d; }
        button:disabled { border-color: #aab2b9; background: #aab2b9; cursor: not-allowed; }
        .button-secondary { border-color: #6a747d; background: #fff; color: #34404b; }
        .button-secondary:hover:not(:disabled), .button-secondary:hover { background: #edf1f4; }
        .errors, .notice { margin: 0 0 20px; padding: 14px 16px; border-radius: 5px; line-height: 1.5; }
        .errors { border: 1px solid #efb4b4; background: #fff5f5; color: #8c2525; }
        .errors ul { margin: 0; padding-left: 20px; }
        .notice { border: 1px solid #a8d6ba; background: #f1fbf5; color: #195f37; }
        .selected-person { display: flex; justify-content: space-between; gap: 16px; align-items: center; }
        .selected-person p { margin: 5px 0 0; color: #53606c; }
        .enrollment-layout { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(280px, .8fr); gap: 24px; }
        .instruction { margin: 0 0 14px; padding: 13px 15px; border-left: 4px solid #2563a8; background: #f3f8fd; font-weight: 700; line-height: 1.45; }
        .video-frame { position: relative; overflow: hidden; width: 100%; aspect-ratio: 4 / 3; border: 1px solid #cfd6dc; border-radius: 6px; background: #15191d; }
        video { display: block; width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        .camera-status { position: absolute; inset: 0; display: grid; place-items: center; margin: 0; padding: 24px; background: #15191d; color: #fff; text-align: center; line-height: 1.5; }
        .camera-status[hidden] { display: none; }
        canvas { display: none; }
        .thumbnails { display: grid; gap: 12px; }
        .thumbnail { padding: 12px; border: 1px solid #d4dbe1; border-radius: 6px; background: #fafbfc; }
        .thumbnail[data-status="success"] { border-color: #78bc92; background: #f2fbf5; }
        .thumbnail[data-status="error"] { border-color: #e29b9b; background: #fff5f5; }
        .thumbnail-head { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 8px; font-size: .85rem; font-weight: 800; }
        .thumbnail img { display: block; width: 100%; aspect-ratio: 4 / 3; border-radius: 4px; background: #dce1e6; object-fit: cover; transform: scaleX(-1); }
        .thumbnail-placeholder { display: grid; place-items: center; width: 100%; aspect-ratio: 4 / 3; border-radius: 4px; background: #e8ecef; color: #697580; font-size: .82rem; text-align: center; }
        .repeat-button { width: 100%; min-height: 36px; margin-top: 8px; padding: 0 10px; border-color: #697580; background: #fff; color: #34404b; font-size: .82rem; }
        .repeat-button:hover:not(:disabled) { background: #edf1f4; }
        .photo-error { margin: 8px 0 0; color: #a52b2b; font-size: .82rem; line-height: 1.4; }
        .result { margin-top: 18px; padding: 14px 16px; border-left: 4px solid #65717c; border-radius: 5px; background: #f6f8f9; line-height: 1.5; }
        .result[data-state="success"] { border-color: #147a46; background: #f1fbf5; }
        .result[data-state="error"] { border-color: #c53030; background: #fff5f5; }
        .result[data-state="loading"] { border-color: #2563a8; background: #f3f8fd; }
        @media (max-width: 780px) { .form-grid, .enrollment-layout { grid-template-columns: 1fr; } .selected-person { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <header><div><h1>Administración de personal</h1></div></header>
    <main>
        @if ($errors->any())
            <div class="errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if (session('mensaje'))
            <p class="notice">{{ session('mensaje') }}</p>
        @endif

        <section class="card" aria-labelledby="person-heading">
            <h2 id="person-heading">{{ $personal ? 'Personal seleccionado' : 'Crear o seleccionar personal' }}</h2>

            @if ($personal)
                <div class="selected-person">
                    <div>
                        <strong>{{ $personal->nombres }} {{ $personal->apellidos }}</strong>
                        <p>DNI {{ $personal->dni }} · {{ $personal->cargo }} · {{ $personal->rostrosEncodings()->count() }} rostro(s) registrado(s)</p>
                    </div>
                    <a class="button-link button-secondary" href="{{ route('personal.crear') }}">Cambiar persona</a>
                </div>
            @else
                <form method="POST" action="{{ route('personal.guardar') }}">
                    @csrf
                    <div class="form-grid">
                        <label>Nombres<input name="nombres" value="{{ old('nombres') }}" required maxlength="255" autocomplete="given-name"></label>
                        <label>Apellidos<input name="apellidos" value="{{ old('apellidos') }}" required maxlength="255" autocomplete="family-name"></label>
                        <label>DNI<input name="dni" value="{{ old('dni') }}" required inputmode="numeric" pattern="[0-9]{8}" maxlength="8"></label>
                        <label>Cargo<input name="cargo" value="{{ old('cargo', 'Practicante') }}" required maxlength="255"></label>
                    </div>
                    <div class="actions"><button type="submit">Crear y continuar</button></div>
                </form>

                @if ($personas->isNotEmpty())
                    <form id="select-person-form" class="form-grid" style="margin-top: 28px; padding-top: 22px; border-top: 1px solid #e0e5e9;">
                        <label class="full">Seleccionar registro existente
                            <select id="person-select" required>
                                <option value="">Selecciona una persona</option>
                                @foreach ($personas as $persona)
                                    <option value="{{ route('personal.enrolar', $persona) }}">{{ $persona->apellidos }}, {{ $persona->nombres }} — DNI {{ $persona->dni }} ({{ $persona->rostrosEncodings()->count() }} fotos)</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="actions full"><button type="submit" class="button-secondary">Continuar con seleccionado</button></div>
                    </form>
                @endif
            @endif
        </section>

        @if ($personal)
            <section class="card" aria-labelledby="enrollment-heading">
                <h2 id="enrollment-heading">Capturar 3 fotos</h2>
                <div class="enrollment-layout">
                    <div>
                        <p id="instruction" class="instruction">Foto 1 de 3: mira de frente a la cámara</p>
                        <div class="video-frame">
                            <video id="camera" autoplay playsinline muted></video>
                            <p id="camera-status" class="camera-status">Solicitando acceso a la cámara...</p>
                        </div>
                        <canvas id="capture-canvas" aria-hidden="true"></canvas>
                        <div class="actions">
                            <button id="capture-button" type="button" disabled>Capturar foto 1</button>
                            <button id="enroll-button" type="button" disabled>Enrolar</button>
                        </div>
                        <div id="result" class="result" data-state="idle" aria-live="polite">Captura las tres fotografías antes de enrolar.</div>
                    </div>

                    <div id="thumbnails" class="thumbnails" aria-label="Fotografías capturadas"></div>
                </div>
            </section>
        @endif
    </main>

    <script>
        const selectForm = document.getElementById('select-person-form');
        selectForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            const url = document.getElementById('person-select').value;
            if (url) window.location.assign(url);
        });
    </script>

    @if ($personal)
        <script>
            const instructions = [
                'Foto 1 de 3: mira de frente a la cámara',
                'Foto 2 de 3: gira levemente la cabeza',
                'Foto 3 de 3: cambia tu expresión (sonríe o gesto distinto)',
            ];
            const video = document.getElementById('camera');
            const canvas = document.getElementById('capture-canvas');
            const cameraStatus = document.getElementById('camera-status');
            const instruction = document.getElementById('instruction');
            const captureButton = document.getElementById('capture-button');
            const enrollButton = document.getElementById('enroll-button');
            const thumbnails = document.getElementById('thumbnails');
            const result = document.getElementById('result');
            const photos = [null, null, null];
            const enrollmentStatus = ['pending', 'pending', 'pending'];
            const enrollmentErrors = ['', '', ''];
            const initialEncodingCount = {{ $personal->rostrosEncodings()->count() }};
            let currentPhoto = 0;
            let cameraStream = null;
            let busy = false;

            function cameraErrorMessage(error) {
                if (error.name === 'NotAllowedError' || error.name === 'SecurityError') return 'Permiso de cámara denegado. Habilítalo en la configuración del navegador.';
                if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') return 'No se encontró ninguna cámara disponible.';
                if (error.name === 'NotReadableError' || error.name === 'TrackStartError') return 'La cámara está ocupada por otra aplicación o no puede iniciarse.';
                return 'No fue posible iniciar la cámara. Revisa el dispositivo y vuelve a cargar la página.';
            }

            function setResult(state, message) {
                result.dataset.state = state;
                result.textContent = message;
            }

            function previewUrl(blob) {
                return blob ? URL.createObjectURL(blob) : null;
            }

            function renderThumbnails() {
                thumbnails.replaceChildren();
                photos.forEach((photo, index) => {
                    const item = document.createElement('article');
                    item.className = 'thumbnail';
                    item.dataset.status = enrollmentStatus[index];

                    const head = document.createElement('div');
                    head.className = 'thumbnail-head';
                    const label = document.createElement('span');
                    label.textContent = `Foto ${index + 1}`;
                    const status = document.createElement('span');
                    status.textContent = enrollmentStatus[index] === 'success' ? 'Enrolada' : enrollmentStatus[index] === 'error' ? 'Error' : photo ? 'Lista' : 'Pendiente';
                    head.append(label, status);
                    item.append(head);

                    if (photo) {
                        const image = document.createElement('img');
                        image.src = previewUrl(photo);
                        image.alt = `Vista previa de la foto ${index + 1}`;
                        item.append(image);

                        const repeat = document.createElement('button');
                        repeat.type = 'button';
                        repeat.className = 'repeat-button';
                        repeat.textContent = 'Repetir esta foto';
                        repeat.disabled = busy || enrollmentStatus[index] === 'success';
                        repeat.addEventListener('click', () => repeatPhoto(index));
                        item.append(repeat);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'thumbnail-placeholder';
                        placeholder.textContent = instructions[index];
                        item.append(placeholder);
                    }

                    if (enrollmentErrors[index]) {
                        const error = document.createElement('p');
                        error.className = 'photo-error';
                        error.textContent = enrollmentErrors[index];
                        item.append(error);
                    }
                    thumbnails.append(item);
                });
            }

            function updateControls() {
                const pendingIndex = photos.findIndex((photo, index) => !photo && enrollmentStatus[index] !== 'success');
                if (pendingIndex !== -1 && !photos[currentPhoto]) currentPhoto = pendingIndex;
                instruction.textContent = instructions[currentPhoto];
                captureButton.textContent = photos[currentPhoto] ? `Repetir foto ${currentPhoto + 1}` : `Capturar foto ${currentPhoto + 1}`;
                captureButton.disabled = busy || !cameraStream || enrollmentStatus[currentPhoto] === 'success';
                enrollButton.disabled = busy || photos.some((photo, index) => !photo && enrollmentStatus[index] !== 'success') || enrollmentStatus.every((status) => status === 'success');
                renderThumbnails();
            }

            function repeatPhoto(index) {
                if (enrollmentStatus[index] === 'success') return;
                photos[index] = null;
                enrollmentStatus[index] = 'pending';
                enrollmentErrors[index] = '';
                currentPhoto = index;
                setResult('idle', `Repite la foto ${index + 1}. Las fotos ya enroladas se conservarán.`);
                updateControls();
            }

            async function startCamera() {
                if (!navigator.mediaDevices?.getUserMedia) {
                    const message = 'Este navegador no permite usar la cámara. Usa HTTPS o localhost en un navegador actualizado.';
                    cameraStatus.textContent = message;
                    setResult('error', message);
                    return;
                }
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                    video.srcObject = cameraStream;
                    await video.play();
                    cameraStatus.hidden = true;
                    setResult('idle', 'Cámara lista. Captura las tres fotografías.');
                    updateControls();
                } catch (error) {
                    const message = cameraErrorMessage(error);
                    cameraStatus.textContent = message;
                    setResult('error', message);
                }
            }

            function captureBlob() {
                if (!video.videoWidth || !video.videoHeight) return Promise.reject(new Error('La cámara todavía no está lista.'));
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                return new Promise((resolve, reject) => canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('No se pudo generar la fotografía.')), 'image/jpeg', .9));
            }

            captureButton.addEventListener('click', async () => {
                try {
                    photos[currentPhoto] = await captureBlob();
                    enrollmentStatus[currentPhoto] = 'pending';
                    enrollmentErrors[currentPhoto] = '';
                    const next = photos.findIndex((photo, index) => index > currentPhoto && !photo && enrollmentStatus[index] !== 'success');
                    setResult('idle', `Foto ${currentPhoto + 1} capturada. Puedes repetirla o continuar.`);
                    if (next !== -1) currentPhoto = next;
                    updateControls();
                } catch (error) {
                    setResult('error', error.message);
                }
            });

            async function enrollPhoto(index) {
                const formData = new FormData();
                formData.append('personal_id', '{{ $personal->id }}');
                formData.append('foto', photos[index], `foto-${index + 1}.jpg`);
                const response = await fetch('{{ url("/personal/{$personal->id}/enrolar") }}', {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: formData,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.error || data.message || `No se pudo enrolar la foto ${index + 1}.`);
                return data;
            }

            enrollButton.addEventListener('click', async () => {
                busy = true;
                updateControls();
                let completed = enrollmentStatus.filter((status) => status === 'success').length;
                try {
                    for (let index = 0; index < photos.length; index++) {
                        if (enrollmentStatus[index] === 'success') continue;
                        setResult('loading', `Enrolando foto ${index + 1} de 3...`);
                        try {
                            await enrollPhoto(index);
                            enrollmentStatus[index] = 'success';
                            enrollmentErrors[index] = '';
                            completed++;
                            renderThumbnails();
                        } catch (error) {
                            enrollmentStatus[index] = 'error';
                            enrollmentErrors[index] = error.message;
                            currentPhoto = index;
                            throw new Error(`La foto ${index + 1} falló: ${error.message} Repítela y vuelve a pulsar Enrolar; las ${completed} ya exitosas no se perderán.`);
                        }
                    }
                    setResult('success', `Enrolamiento completo: quedaron ${initialEncodingCount + completed} rostros registrados para {{ $personal->nombres }} {{ $personal->apellidos }} (${completed} nuevos en esta sesión).`);
                } catch (error) {
                    setResult('error', error.message);
                } finally {
                    busy = false;
                    updateControls();
                }
            });

            renderThumbnails();
            startCamera();
            window.addEventListener('pagehide', () => cameraStream?.getTracks().forEach((track) => track.stop()));
        </script>
    @endif
</body>
</html>