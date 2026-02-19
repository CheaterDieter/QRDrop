let selectedFile = null;
let selectedRetentionTime = 10; // default: 10 minutes

const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const uploadButton = document.getElementById('uploadButton');
const error = document.getElementById('error');
const success = document.getElementById('success');
const loading = document.getElementById('loading');
const uploadForm = document.getElementById('uploadForm');
const result = document.getElementById('result');
const downloadLink = document.getElementById('downloadLink');
const copyButton = document.getElementById('copyButton');
const qrCodeDiv = document.getElementById('qrCode');
const retentionRadios = document.querySelectorAll('input[name="retentionTime"]');

uploadArea.addEventListener('click', () => fileInput.click());
uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => { uploadArea.classList.remove('dragover'); });
uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.classList.remove('dragover'); const files = e.dataTransfer.files; if (files.length > 0) handleFileSelect(files[0]); });
fileInput.addEventListener('change', (e) => { if (e.target.files.length > 0) handleFileSelect(e.target.files[0]); });
retentionRadios.forEach(radio => {
    radio.addEventListener('change', (e) => { selectedRetentionTime = parseInt(e.target.value, 10); });
});

function handleFileSelect(file) {
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/zip'];
    let ok = false;
    if (file.type && allowedTypes.includes(file.type)) {
        ok = true;
    } else {
        // fallback: check file extension when MIME type is missing/incorrect
        const n = (file.name || '').toLowerCase();
        if (n.endsWith('.pdf') || n.endsWith('.jpg') || n.endsWith('.jpeg') || n.endsWith('.png') || n.endsWith('.zip')) ok = true;
    }
    if (!ok) { showError('Nur PDF-, Bilder (.jpg, .jpeg, .png) oder ZIP-Dateien sind erlaubt!'); return; }
    if (file.size > MAX_FILE_SIZE) { showError('Datei ist zu groß! Maximum: ' + Math.round(MAX_FILE_SIZE / 1024 / 1024) + 'MB'); return; }
    selectedFile = file;
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    fileInfo.classList.add('show');
    uploadButton.disabled = false; hideError();
}

uploadButton.addEventListener('click', uploadFile);

async function uploadFile() {
    if (!selectedFile) return;
    const formData = new FormData(); formData.append('file', selectedFile); formData.append('retention_time', selectedRetentionTime);
    uploadButton.disabled = true; loading.classList.add('show'); hideError();
    try {
        const response = await fetch('upload.php', { method: 'POST', body: formData });
        const text = await response.text(); let data;
        try { data = JSON.parse(text); } catch (parseError) { showError('Server-Fehler: Ungültige Antwort'); uploadButton.disabled = false; loading.classList.remove('show'); return; }
        if (data.success) { hideError(); displayResult(data); uploadForm.style.display = 'none'; result.classList.add('show'); startExpiresCountdown(); } else { showError(data.error || 'Fehler beim Hochladen'); uploadButton.disabled = false; }
    } catch (err) { showError('Fehler beim Hochladen: ' + err.message); uploadButton.disabled = false; } finally { loading.classList.remove('show'); }
}

function displayResult(data) { qrCodeDiv.innerHTML = ''; new QRCode(qrCodeDiv, { text: data.downloadUrl, width: 300, height: 300, colorDark: '#4d4db0', colorLight: '#ffffff' }); downloadLink.value = data.downloadUrl; }

copyButton.addEventListener('click', () => { downloadLink.select(); document.execCommand('copy'); copyButton.textContent = '✓ Kopiert!'; copyButton.classList.add('copied'); setTimeout(() => { copyButton.textContent = 'Kopieren'; copyButton.classList.remove('copied'); }, 2000); });

function startExpiresCountdown() {
    const expiresTime = document.getElementById('expiresTime');
    let totalSeconds = selectedRetentionTime * 60;
    
    function formatTime(seconds) {
        if (seconds <= 0) return 'abgelaufen';
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes}m ${secs}s`;
    }
    
    expiresTime.textContent = formatTime(totalSeconds);
    
    const countdown = setInterval(() => {
        totalSeconds--;
        expiresTime.textContent = formatTime(totalSeconds);
        if (totalSeconds <= 0) {
            clearInterval(countdown);
        }
    }, 1000);
}

function showError(message) { error.textContent = '❌ ' + message; error.classList.add('show'); }
function hideError() { error.classList.remove('show'); }
function showSuccess(message) { success.textContent = '✓ ' + message; success.classList.add('show'); setTimeout(() => { success.classList.remove('show'); }, 3000); }
function formatFileSize(bytes) { if (bytes === 0) return '0 Bytes'; const k = 1024; const sizes = ['Bytes', 'KB', 'MB']; const i = Math.floor(Math.log(bytes) / Math.log(k)); return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]; }
// Client JavaScript (no debug/log statements)

// Terms modal behavior
const termsLink = document.getElementById('termsLink');
const termsModal = document.getElementById('termsModal');
const termsClose = document.getElementById('termsClose');
const termsOverlay = document.getElementById('termsOverlay');
const termsBody = document.getElementById('termsBody');

function showTerms() {
    if (!termsBody) return;
    fetch('terms.html')
        .then(r => r.text())
        .then(html => {
            termsBody.innerHTML = html;
            if (termsModal) {
                termsModal.style.display = 'block';
                termsModal.setAttribute('aria-hidden', 'false');
            }
        })
        .catch(() => {
            termsBody.textContent = 'Die Nutzungsbedingungen konnten nicht geladen werden.';
            if (termsModal) {
                termsModal.style.display = 'block';
                termsModal.setAttribute('aria-hidden', 'false');
            }
        });
}

function hideTerms() {
    if (termsModal) {
        termsModal.style.display = 'none';
        termsModal.setAttribute('aria-hidden', 'true');
    }
}

if (termsLink) termsLink.addEventListener('click', (e) => { e.preventDefault(); showTerms(); });
if (termsClose) termsClose.addEventListener('click', hideTerms);
if (termsOverlay) termsOverlay.addEventListener('click', hideTerms);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideTerms(); });

// Impressum modal behavior
const impressumLink = document.getElementById('impressumLink');
const impressumModal = document.getElementById('impressumModal');
const impressumClose = document.getElementById('impressumClose');
const impressumOverlay = document.getElementById('impressumOverlay');
const impressumBody = document.getElementById('impressumBody');

function showImpressum() {
    if (!impressumBody) return;
    // Try to load impressum.html first, fallback to impressum-sample.html
    fetch('impressum.html')
        .then(r => {
            if (!r.ok) throw new Error('Not found');
            return r.text();
        })
        .catch(() => fetch('impressum-sample.html').then(r => r.text()))
        .then(html => {
            impressumBody.innerHTML = html;
            if (impressumModal) {
                impressumModal.style.display = 'block';
                impressumModal.setAttribute('aria-hidden', 'false');
            }
        })
        .catch(() => {
            impressumBody.textContent = 'Das Impressum konnte nicht geladen werden.';
            if (impressumModal) {
                impressumModal.style.display = 'block';
                impressumModal.setAttribute('aria-hidden', 'false');
            }
        });
}

function hideImpressum() {
    if (impressumModal) {
        impressumModal.style.display = 'none';
        impressumModal.setAttribute('aria-hidden', 'true');
    }
}

if (impressumLink) impressumLink.addEventListener('click', (e) => { e.preventDefault(); showImpressum(); });
if (impressumClose) impressumClose.addEventListener('click', hideImpressum);
if (impressumOverlay) impressumOverlay.addEventListener('click', hideImpressum);

// About modal behavior
const aboutLink = document.getElementById('aboutLink');
const aboutModal = document.getElementById('aboutModal');
const aboutClose = document.getElementById('aboutClose');
const aboutOverlay = document.getElementById('aboutOverlay');
const aboutBody = document.getElementById('aboutBody');

function showAbout() {
    if (!aboutBody) return;
    fetch('about.html')
        .then(r => {
            if (!r.ok) throw new Error('Not found');
            return r.text();
        })
        .then(html => {
            aboutBody.innerHTML = html;
            if (aboutModal) {
                aboutModal.style.display = 'block';
                aboutModal.setAttribute('aria-hidden', 'false');
            }
        })
        .catch(() => {
            aboutBody.textContent = 'Über QRdrop konnte nicht geladen werden.';
            if (aboutModal) {
                aboutModal.style.display = 'block';
                aboutModal.setAttribute('aria-hidden', 'false');
            }
        });
}

function hideAbout() {
    if (aboutModal) {
        aboutModal.style.display = 'none';
        aboutModal.setAttribute('aria-hidden', 'true');
    }
}

if (aboutLink) aboutLink.addEventListener('click', (e) => { e.preventDefault(); showAbout(); });
if (aboutClose) aboutClose.addEventListener('click', hideAbout);
if (aboutOverlay) aboutOverlay.addEventListener('click', hideAbout);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { if (impressumModal && impressumModal.getAttribute('aria-hidden') === 'false') hideImpressum(); if (aboutModal && aboutModal.getAttribute('aria-hidden') === 'false') hideAbout(); } });
