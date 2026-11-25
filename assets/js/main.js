document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const ALLOWED_EXTS = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg', 'wma', 'mp4', 'wmv'];
    const MAX_FILE_BYTES = 250 * 1024 * 1024;

    // Elements - Upload & Preview
    const dropZone = document.getElementById('dropZone');
    const recordingInput = document.getElementById('recordingInput');
    const browseFileBtn = document.getElementById('browseFileBtn');
    const filePreviewCard = document.getElementById('filePreviewCard');
    const previewFileName = document.getElementById('previewFileName');
    const previewFileSize = document.getElementById('previewFileSize');
    const previewFileDuration = document.getElementById('previewFileDuration');
    const previewFileType = document.getElementById('previewFileType');
    const previewFileIcon = document.getElementById('previewFileIcon');
    const mediaPreviewContainer = document.getElementById('mediaPreviewContainer');
    const audioPlayer = document.getElementById('audioPlayer');
    const videoPlayer = document.getElementById('videoPlayer');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const audioLanguageSelect = document.getElementById('audioLanguageSelect');
    const summaryLanguageSelect = document.getElementById('summaryLanguageSelect');
    const processRecordingBtn = document.getElementById('processRecordingBtn');
    const globalAlertArea = document.getElementById('globalAlertArea');

    // Elements - Progress
    const processingPanel = document.getElementById('processingPanel');
    const processingStatusText = document.getElementById('processingStatusText');
    const processingPercentText = document.getElementById('processingPercentText');
    const processingProgressBar = document.getElementById('processingProgressBar');

    // Elements - Results
    const resultSection = document.getElementById('resultSection');
    const resultMetaLine = document.getElementById('resultMetaLine');
    const secOverallSummary = document.getElementById('secOverallSummary');
    const secMainTopics = document.getElementById('secMainTopics');
    const secImportantPoints = document.getElementById('secImportantPoints');
    const secKeyMetrics = document.getElementById('secKeyMetrics');
    const secDecisionsConclusions = document.getElementById('secDecisionsConclusions');
    const secActionItems = document.getElementById('secActionItems');
    const secHighLevelSynthesis = document.getElementById('secHighLevelSynthesis');
    const fullTranscriptText = document.getElementById('fullTranscriptText');
    const copySummaryBtn = document.getElementById('copySummaryBtn');
    const copyTranscriptBtn = document.getElementById('copyTranscriptBtn');
    const downloadPdfBtn = document.getElementById('downloadPdfBtn');
    const newAudioBtn = document.getElementById('newAudioBtn');

    // Elements - Settings
    const settingsCollapseEl = document.getElementById('aiSettingsCollapse');
    const closeSettingsBtn = document.getElementById('closeSettingsBtn');
    const aiSettingsForm = document.getElementById('aiSettingsForm');
    const geminiEndpointInput = document.getElementById('geminiEndpoint');
    const geminiModelSelect = document.getElementById('geminiModelSelect');
    const geminiCustomModel = document.getElementById('geminiCustomModel');
    const geminiApiKeyInput = document.getElementById('geminiApiKey');
    const testGeminiBtn = document.getElementById('testGeminiBtn');
    const geminiTestResult = document.getElementById('geminiTestResult');

    const agnesEndpointInput = document.getElementById('agnesEndpoint');
    const agnesModelSelect = document.getElementById('agnesModelSelect');
    const agnesCustomModel = document.getElementById('agnesCustomModel');
    const agnesApiKeyInput = document.getElementById('agnesApiKey');
    const testAgnesBtn = document.getElementById('testAgnesBtn');
    const agnesTestResult = document.getElementById('agnesTestResult');
    const settingsSaveResult = document.getElementById('settingsSaveResult');

    let selectedFile = null;
    let detectedDuration = 'Unknown';
    let objectUrl = null;
    let latestSummaryData = null;
    let latestMetaData = null;

    // Restore browser localStorage convenience keys if present
    try {
        const savedGeminiKey = localStorage.getItem('ai_voice_gemini_key');
        const savedAgnesKey = localStorage.getItem('ai_voice_agnes_key');
        if (savedGeminiKey && geminiApiKeyInput) {
            geminiApiKeyInput.value = savedGeminiKey;
        }
        if (savedAgnesKey && agnesApiKeyInput) {
            agnesApiKeyInput.value = savedAgnesKey;
        }
    } catch (_) {
        // Ignore storage restrictions
    }

    function showAlert(type, message) {
        globalAlertArea.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid ${type === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-check'} me-2"></i>
                <span>${escapeHtml(message)}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }

    function clearAlert() {
        globalAlertArea.innerHTML = '';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        let val = bytes;
        while (val >= 1024 && i < units.length - 1) {
            val /= 1024;
            i++;
        }
        return `${val.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
    }

    function formatDuration(seconds) {
        if (!Number.isFinite(seconds) || seconds <= 0) return 'Unknown';
        const total = Math.round(seconds);
        const hrs = Math.floor(total / 3600);
        const mins = Math.floor((total % 3600) / 60);
        const secs = total % 60;
        if (hrs > 0) {
            return `${hrs}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    function revokeMediaUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        audioPlayer.pause();
        videoPlayer.pause();
        audioPlayer.removeAttribute('src');
        videoPlayer.removeAttribute('src');
        audioPlayer.classList.add('d-none');
        videoPlayer.classList.add('d-none');
        mediaPreviewContainer.classList.add('d-none');
    }

    function resetFileSelection() {
        selectedFile = null;
        detectedDuration = 'Unknown';
        recordingInput.value = '';
        revokeMediaUrl();
        filePreviewCard.classList.add('d-none');
        processRecordingBtn.disabled = true;
    }

    function handleFileChosen(file) {
        clearAlert();
        if (!file) return;

        const parts = file.name.split('.');
        const ext = parts.length > 1 ? parts.pop().toLowerCase() : '';

        if (!ALLOWED_EXTS.includes(ext)) {
            resetFileSelection();
            showAlert('danger', 'Unsupported file format. Allowed: MP3, WAV, M4A, AAC, FLAC, OGG, WMA, MP4, WMV.');
            return;
        }

        if (file.size <= 0) {
            resetFileSelection();
            showAlert('danger', 'Selected file is empty.');
            return;
        }

        if (file.size > MAX_FILE_BYTES) {
            resetFileSelection();
            showAlert('danger', 'File size exceeds the maximum limit of 250MB.');
            return;
        }

        revokeMediaUrl();
        selectedFile = file;
        detectedDuration = 'Unknown';

        const isVideo = ['mp4', 'wmv'].includes(ext);
        previewFileIcon.className = isVideo ? 'fa-solid fa-file-video' : 'fa-solid fa-file-audio';
        previewFileName.textContent = file.name;
        previewFileSize.textContent = formatBytes(file.size);
        previewFileType.textContent = file.type || ext.toUpperCase();
        previewFileDuration.textContent = 'Duration: Loading...';
        filePreviewCard.classList.remove('d-none');
        processRecordingBtn.disabled = false;

        objectUrl = URL.createObjectURL(file);
        if (ext === 'mp4' || ext === 'mp3' || ext === 'wav' || ext === 'm4a' || ext === 'aac' || ext === 'ogg' || ext === 'flac') {
            mediaPreviewContainer.classList.remove('d-none');
            const mediaEl = isVideo ? videoPlayer : audioPlayer;
            mediaEl.classList.remove('d-none');
            mediaEl.src = objectUrl;
            mediaEl.onloadedmetadata = () => {
                detectedDuration = formatDuration(mediaEl.duration);
                previewFileDuration.textContent = `Duration: ${detectedDuration}`;
            };
            mediaEl.onerror = () => {
                detectedDuration = 'Unknown';
                previewFileDuration.textContent = 'Duration: Unavailable';
            };
        } else {
            detectedDuration = 'Unknown';
            previewFileDuration.textContent = 'Duration: Unavailable';
        }
    }

    // Drag & drop + Browse
    browseFileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        recordingInput.click();
    });

    dropZone.addEventListener('click', () => {
        recordingInput.click();
    });

    dropZone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            recordingInput.click();
        }
    });

    ['dragenter', 'dragover'].forEach((evt) => {
        dropZone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('drag-over');
        });
    });

    ['dragleave', 'drop'].forEach((evt) => {
        dropZone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
        });
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (files && files.length > 0) {
            handleFileChosen(files[0]);
        }
    });

    recordingInput.addEventListener('change', () => {
        if (recordingInput.files && recordingInput.files.length > 0) {
            handleFileChosen(recordingInput.files[0]);
        }
    });

    removeFileBtn.addEventListener('click', () => {
        clearAlert();
        resetFileSelection();
    });

    // Stepper & Progress helpers
    const STEP_ORDER = ['upload', 'prepare', 'analyze', 'summarize', 'report', 'done'];

    function setProgress(percent, statusLabel, activeStepKey) {
        const clamped = Math.max(0, Math.min(100, percent));
        processingProgressBar.style.width = `${clamped}%`;
        processingProgressBar.setAttribute('aria-valuenow', String(clamped));
        processingPercentText.textContent = `${Math.round(clamped)}%`;
        processingStatusText.textContent = statusLabel;

        const activeIndex = STEP_ORDER.indexOf(activeStepKey);
        document.querySelectorAll('#processingPanel .step-item').forEach((item) => {
            const key = item.getAttribute('data-step');
            const idx = STEP_ORDER.indexOf(key);
            item.classList.remove('active', 'completed');
            if (activeStepKey === 'done') {
                item.classList.add('completed');
            } else if (idx < activeIndex) {
                item.classList.add('completed');
            } else if (idx === activeIndex) {
                item.classList.add('active');
            }
        });
    }

    function uploadRecordingWithProgress(file, duration) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload.php', true);

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable && e.total > 0) {
                    const uploadPct = (e.loaded / e.total) * 35;
                    setProgress(uploadPct, 'Uploading recording...', 'upload');
                }
            };

            xhr.onload = () => {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && data.ok) {
                        resolve(data);
                    } else {
                        reject(new Error(data.error || `Upload failed (HTTP ${xhr.status}).`));
                    }
                } catch (_) {
                    reject(new Error('Invalid JSON response from server during upload.'));
                }
            };

            xhr.onerror = () => reject(new Error('Network error while uploading recording.'));

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('recording', file);
            formData.append('duration', duration);
            xhr.send(formData);
        });
    }

    // Process button click
    processRecordingBtn.addEventListener('click', async () => {
        if (!selectedFile) return;

        clearAlert();
        resultSection.classList.add('d-none');
        processingPanel.classList.remove('d-none');
        processRecordingBtn.disabled = true;
        removeFileBtn.disabled = true;

        try {
            setProgress(5, 'Uploading recording...', 'upload');
            const uploadResp = await uploadRecordingWithProgress(selectedFile, detectedDuration);

            setProgress(42, 'Preparing recording...', 'prepare');
            await new Promise((r) => setTimeout(r, 180));

            setProgress(62, 'AI analysis...', 'analyze');
            const processPromise = fetch('process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    temp_file: uploadResp.upload.temp_file,
                    duration: detectedDuration,
                    audio_language: audioLanguageSelect.value,
                    summary_language: summaryLanguageSelect.value,
                }),
            });

            const timerSummarize = setTimeout(() => {
                setProgress(78, 'Summary generation...', 'summarize');
            }, 900);

            const timerReport = setTimeout(() => {
                setProgress(90, 'Report generation...', 'report');
            }, 2000);

            const resp = await processPromise;
            clearTimeout(timerSummarize);
            clearTimeout(timerReport);

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Failed to process recording.');
            }

            setProgress(100, 'Completed processing.', 'done');
            renderResult(data);
            showAlert('success', 'Recording transcribed, summarized, and PDF report generated.');
        } catch (err) {
            processingPanel.classList.add('d-none');
            showAlert('danger', err.message || 'Processing failed.');
            processRecordingBtn.disabled = false;
        } finally {
            removeFileBtn.disabled = false;
        }
    });

    function renderBulletList(containerEl, items) {
        containerEl.innerHTML = '';
        const list = Array.isArray(items) ? items : [];
        if (list.length === 0) {
            const li = document.createElement('li');
            li.className = 'text-muted';
            li.textContent = '—';
            containerEl.appendChild(li);
            return;
        }
        list.forEach((txt) => {
            const li = document.createElement('li');
            li.textContent = txt;
            containerEl.appendChild(li);
        });
    }

    function renderDecisionOrAction(containerEl, items, emptyText) {
        containerEl.innerHTML = '';
        const list = Array.isArray(items) ? items : [];
        if (list.length === 0) {
            const div = document.createElement('div');
            div.className = 'empty-state-note';
            div.textContent = emptyText;
            containerEl.appendChild(div);
            return;
        }
        const ul = document.createElement('ul');
        ul.className = 'result-list mb-0';
        list.forEach((txt) => {
            const li = document.createElement('li');
            li.textContent = txt;
            ul.appendChild(li);
        });
        containerEl.appendChild(ul);
    }

    function renderResult(data) {
        const summary = data.summary || {};
        const meta = data.meta || {};
        latestSummaryData = summary;
        latestMetaData = meta;

        resultMetaLine.textContent = `${meta.filename || ''} • ${meta.duration || ''} • ${meta.file_size || ''} • ${meta.summary_language || ''}`;

        secOverallSummary.textContent = summary.overall_summary || '';
        renderBulletList(secMainTopics, summary.main_topics);
        renderBulletList(secImportantPoints, summary.important_points);
        renderBulletList(secKeyMetrics, summary.key_metrics_references);

        renderDecisionOrAction(
            secDecisionsConclusions,
            summary.decisions_conclusions,
            summary.empty_decisions_text || 'No formal decisions mentioned.'
        );
        renderDecisionOrAction(
            secActionItems,
            summary.action_items,
            summary.empty_actions_text || 'No action items identified.'
        );

        secHighLevelSynthesis.textContent = summary.high_level_synthesis || '';
        fullTranscriptText.textContent = data.transcript || '';

        if (data.pdf_url) {
            downloadPdfBtn.href = data.pdf_url;
        }

        resultSection.classList.remove('d-none');
        resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function buildPlainTextSummary() {
        if (!latestSummaryData) return '';
        const s = latestSummaryData;
        const lines = [];

        if (latestMetaData?.filename) {
            lines.push(`Recording: ${latestMetaData.filename}`);
            lines.push('');
        }

        lines.push('1. Overall Summary');
        lines.push(s.overall_summary || '');
        lines.push('');

        lines.push('2. Main Topics Discussed');
        (s.main_topics || []).forEach((t) => lines.push(`- ${t}`));
        lines.push('');

        lines.push('3. Important Points');
        (s.important_points || []).forEach((p) => lines.push(`- ${p}`));
        lines.push('');

        lines.push('4. Key Metrics, References & Information');
        (s.key_metrics_references || []).forEach((m) => lines.push(`- ${m}`));
        lines.push('');

        lines.push('5. Decisions & Conclusions');
        if ((s.decisions_conclusions || []).length > 0) {
            s.decisions_conclusions.forEach((d) => lines.push(`- ${d}`));
        } else {
            lines.push(s.empty_decisions_text || '');
        }
        lines.push('');

        lines.push('6. Action Items & Next Steps');
        if ((s.action_items || []).length > 0) {
            s.action_items.forEach((a) => lines.push(`- ${a}`));
        } else {
            lines.push(s.empty_actions_text || '');
        }
        lines.push('');

        lines.push('7. High-Level Synthesis');
        lines.push(s.high_level_synthesis || '');

        return lines.join('\n').trim();
    }

    async function copyToClipboard(text, buttonEl, defaultLabel) {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
        } catch (_) {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        const origHtml = buttonEl.innerHTML;
        buttonEl.innerHTML = `<i class="fa-solid fa-check me-1"></i>Copied`;
        setTimeout(() => {
            buttonEl.innerHTML = origHtml || defaultLabel;
        }, 1800);
    }

    copySummaryBtn.addEventListener('click', () => {
        copyToClipboard(buildPlainTextSummary(), copySummaryBtn, 'Copy Summary');
    });

    copyTranscriptBtn.addEventListener('click', () => {
        copyToClipboard(fullTranscriptText.textContent || '', copyTranscriptBtn, 'Copy Transcript');
    });

    newAudioBtn.addEventListener('click', () => {
        clearAlert();
        resetFileSelection();
        processingPanel.classList.add('d-none');
        resultSection.classList.add('d-none');
        latestSummaryData = null;
        latestMetaData = null;
        setProgress(0, 'Uploading recording...', 'upload');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Settings UI interactions
    if (closeSettingsBtn && settingsCollapseEl && window.bootstrap?.Collapse) {
        closeSettingsBtn.addEventListener('click', () => {
            const inst = window.bootstrap.Collapse.getOrCreateInstance(settingsCollapseEl, { toggle: false });
            inst.hide();
        });
    }

    geminiModelSelect.addEventListener('change', () => {
        geminiCustomModel.classList.toggle('d-none', geminiModelSelect.value !== 'custom');
    });

    agnesModelSelect.addEventListener('change', () => {
        agnesCustomModel.classList.toggle('d-none', agnesModelSelect.value !== 'custom');
    });

    function gatherSettingsPayload(action) {
        return {
            action,
            csrf_token: csrfToken,
            gemini_endpoint: geminiEndpointInput.value,
            gemini_model_select: geminiModelSelect.value,
            gemini_custom_model: geminiCustomModel.value,
            gemini_api_key: geminiApiKeyInput.value,
            agnes_endpoint: agnesEndpointInput.value,
            agnes_model_select: agnesModelSelect.value,
            agnes_custom_model: agnesCustomModel.value,
            agnes_api_key: agnesApiKeyInput.value,
        };
    }

    testGeminiBtn.addEventListener('click', async () => {
        testGeminiBtn.disabled = true;
        geminiTestResult.innerHTML = '<span class="text-muted">Testing Gemini connection...</span>';
        try {
            const resp = await fetch('api_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(gatherSettingsPayload('test_gemini')),
            });
            const data = await resp.json();
            if (data.ok) {
                geminiTestResult.innerHTML = `<span class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>${escapeHtml(data.message)}</span>`;
            } else {
                geminiTestResult.innerHTML = `<span class="text-danger fw-semibold"><i class="fa-solid fa-circle-xmark me-1"></i>${escapeHtml(data.error || 'Connection failed.')}</span>`;
            }
        } catch (err) {
            geminiTestResult.innerHTML = `<span class="text-danger">${escapeHtml(err.message)}</span>`;
        } finally {
            testGeminiBtn.disabled = false;
        }
    });

    testAgnesBtn.addEventListener('click', async () => {
        testAgnesBtn.disabled = true;
        agnesTestResult.innerHTML = '<span class="text-muted">Testing Agnes AI connection...</span>';
        try {
            const resp = await fetch('api_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(gatherSettingsPayload('test_agnes')),
            });
            const data = await resp.json();
            if (data.ok) {
                agnesTestResult.innerHTML = `<span class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>${escapeHtml(data.message)}</span>`;
            } else {
                agnesTestResult.innerHTML = `<span class="text-danger fw-semibold"><i class="fa-solid fa-circle-xmark me-1"></i>${escapeHtml(data.error || 'Connection failed.')}</span>`;
            }
        } catch (err) {
            agnesTestResult.innerHTML = `<span class="text-danger">${escapeHtml(err.message)}</span>`;
        } finally {
            testAgnesBtn.disabled = false;
        }
    });

    aiSettingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        settingsSaveResult.innerHTML = '<span class="text-muted">Saving settings to .env...</span>';
        try {
            const resp = await fetch('api_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(gatherSettingsPayload('save_settings')),
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Failed to save settings.');
            }

            try {
                if (geminiApiKeyInput.value.trim() !== '') {
                    localStorage.setItem('ai_voice_gemini_key', geminiApiKeyInput.value.trim());
                }
                if (agnesApiKeyInput.value.trim() !== '') {
                    localStorage.setItem('ai_voice_agnes_key', agnesApiKeyInput.value.trim());
                }
            } catch (_) {
                // Ignore localStorage restrictions
            }

            settingsSaveResult.innerHTML = `<span class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>${escapeHtml(data.message)}</span>`;
        } catch (err) {
            settingsSaveResult.innerHTML = `<span class="text-danger fw-semibold"><i class="fa-solid fa-circle-xmark me-1"></i>${escapeHtml(err.message)}</span>`;
        }
    });
});