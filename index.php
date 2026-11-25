<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';

Security::startSession();
$csrfToken = Security::csrfToken();

$geminiEndpoint = (string)$config['gemini']['endpoint'];
$geminiModel    = (string)$config['gemini']['model'];
$geminiModels   = (array)$config['gemini']['models'];

$agnesEndpoint  = (string)$config['agnes']['endpoint'];
$agnesModel     = (string)$config['agnes']['model'];
$agnesModels    = (array)$config['agnes']['models'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Security::escape($csrfToken) ?>">
    <title><?= Security::escape($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-dark top-navbar sticky-top">
        <div class="container app-container d-flex align-items-center justify-content-between py-2">
            <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="index.php">
                <span class="brand-icon-wrap">
                    <i class="fa-solid fa-wave-square"></i>
                </span>
                <span class="brand-title"><?= Security::escape($config['app_name']) ?></span>
            </a>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="status-pill engine-ready" title="Gemini Multimodal Engine Ready">
                    <span class="status-dot"></span>
                    <span>AI Engine Ready</span>
                </span>
                <span class="status-pill stateless-badge" title="Zero-Database Stateless Architecture">
                    <i class="fa-solid fa-bolt me-1"></i>Stateless Realtime
                </span>
                <button
                    type="button"
                    id="toggleSettingsBtn"
                    class="btn btn-sm btn-outline-light settings-btn"
                    data-bs-toggle="collapse"
                    data-bs-target="#aiSettingsCollapse"
                    aria-expanded="false"
                    aria-controls="aiSettingsCollapse"
                >
                    <i class="fa-solid fa-sliders me-1"></i>Settings
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="container app-container py-4 py-md-5">
        <!-- Collapsible AI Settings Panel -->
        <section class="collapse mb-4" id="aiSettingsCollapse">
            <div class="glass-card settings-card p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-light-subtle">
                    <div>
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-gear me-2 text-primary"></i>AI Provider Configuration</h5>
                        <p class="text-muted small mb-0">Configure Google Gemini (primary processing engine) and Agnes AI (secondary provider configuration).</p>
                    </div>
                    <button type="button" class="btn-close" id="closeSettingsBtn" aria-label="Close"></button>
                </div>

                <form id="aiSettingsForm" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>">

                    <div class="row g-4">
                        <!-- Google Gemini AI -->
                        <div class="col-lg-6">
                            <div class="provider-box p-3 rounded-3 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0 text-primary">
                                        <i class="fa-solid fa-sparkles me-1"></i>Google Gemini AI – Primary Engine
                                    </h6>
                                    <span class="badge bg-primary-subtle text-primary">Primary</span>
                                </div>

                                <div class="mb-3">
                                    <label for="geminiEndpoint" class="form-label small fw-semibold">API Base Endpoint</label>
                                    <input
                                        type="url"
                                        class="form-control form-control-sm font-mono"
                                        id="geminiEndpoint"
                                        name="gemini_endpoint"
                                        value="<?= Security::escape($geminiEndpoint) ?>"
                                        required
                                    >
                                </div>

                                <div class="mb-3">
                                    <label for="geminiModelSelect" class="form-label small fw-semibold">Gemini Model</label>
                                    <select class="form-select form-select-sm font-mono mb-2" id="geminiModelSelect" name="gemini_model_select">
                                        <?php
                                        $isPresetGemini = in_array($geminiModel, $geminiModels, true);
                                        foreach ($geminiModels as $m):
                                            $sel = ($isPresetGemini && $geminiModel === $m) ? 'selected' : '';
                                        ?>
                                            <option value="<?= Security::escape($m) ?>" <?= $sel ?>><?= Security::escape($m) ?></option>
                                        <?php endforeach; ?>
                                        <option value="custom" <?= !$isPresetGemini ? 'selected' : '' ?>>Custom Model Name</option>
                                    </select>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm font-mono <?= $isPresetGemini ? 'd-none' : '' ?>"
                                        id="geminiCustomModel"
                                        name="gemini_custom_model"
                                        placeholder="Enter custom Gemini model name"
                                        value="<?= !$isPresetGemini ? Security::escape($geminiModel) : '' ?>"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label for="geminiApiKey" class="form-label small fw-semibold">Gemini API Key</label>
                                    <input
                                        type="password"
                                        class="form-control form-control-sm font-mono"
                                        id="geminiApiKey"
                                        name="gemini_api_key"
                                        placeholder="Leave blank to keep saved key"
                                        autocomplete="new-password"
                                    >
                                </div>

                                <button type="button" id="testGeminiBtn" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-plug me-1"></i>Test Gemini Connection
                                </button>
                                <div id="geminiTestResult" class="mt-2 small"></div>
                            </div>
                        </div>

                        <!-- Agnes AI -->
                        <div class="col-lg-6">
                            <div class="provider-box p-3 rounded-3 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0 text-indigo">
                                        <i class="fa-solid fa-microchip me-1"></i>Agnes AI – Secondary Provider Configuration
                                    </h6>
                                    <span class="badge bg-secondary-subtle text-secondary">Configurable</span>
                                </div>

                                <div class="mb-3">
                                    <label for="agnesEndpoint" class="form-label small fw-semibold">Agnes API Endpoint</label>
                                    <input
                                        type="url"
                                        class="form-control form-control-sm font-mono"
                                        id="agnesEndpoint"
                                        name="agnes_endpoint"
                                        value="<?= Security::escape($agnesEndpoint) ?>"
                                        required
                                    >
                                </div>

                                <div class="mb-3">
                                    <label for="agnesModelSelect" class="form-label small fw-semibold">Agnes AI Model</label>
                                    <select class="form-select form-select-sm font-mono mb-2" id="agnesModelSelect" name="agnes_model_select">
                                        <?php
                                        $isPresetAgnes = in_array($agnesModel, $agnesModels, true);
                                        foreach ($agnesModels as $am):
                                            $sel = ($isPresetAgnes && $agnesModel === $am) ? 'selected' : '';
                                        ?>
                                            <option value="<?= Security::escape($am) ?>" <?= $sel ?>><?= Security::escape($am) ?></option>
                                        <?php endforeach; ?>
                                        <option value="custom" <?= !$isPresetAgnes ? 'selected' : '' ?>>Custom Model Name</option>
                                    </select>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm font-mono <?= $isPresetAgnes ? 'd-none' : '' ?>"
                                        id="agnesCustomModel"
                                        name="agnes_custom_model"
                                        placeholder="Enter custom Agnes model name"
                                        value="<?= !$isPresetAgnes ? Security::escape($agnesModel) : '' ?>"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label for="agnesApiKey" class="form-label small fw-semibold">Agnes API Key</label>
                                    <input
                                        type="password"
                                        class="form-control form-control-sm font-mono"
                                        id="agnesApiKey"
                                        name="agnes_api_key"
                                        placeholder="Leave blank to keep saved key"
                                        autocomplete="new-password"
                                    >
                                </div>

                                <button type="button" id="testAgnesBtn" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-plug me-1"></i>Test Agnes AI Connection
                                </button>
                                <div id="agnesTestResult" class="mt-2 small"></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4 pt-3 border-top border-light-subtle">
                        <div id="settingsSaveResult" class="small"></div>
                        <button type="submit" id="saveSettingsBtn" class="btn btn-primary fw-semibold">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Settings to Server (.env)
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Main Introduction -->
        <header class="text-center mb-4 mb-md-5 hero-header">
            <h1 class="hero-title fw-extrabold mb-3">Transform Voice &amp; Video into Executive Intelligence</h1>
            <p class="hero-subtitle mx-auto mb-0">
                Transcribe meeting recordings, technical lectures, or bilingual audio with high-precision AI. Generate comprehensive multi-section summaries and PDF reports.
            </p>
        </header>

        <!-- Alert Area -->
        <div id="globalAlertArea" class="mb-3"></div>

        <!-- Upload & Configuration Card -->
        <section class="glass-card p-4 p-md-5 mb-4" id="uploadCard">
            <div id="dropZone" class="drop-zone text-center p-4 p-md-5 rounded-4" tabindex="0" role="button" aria-label="Upload audio or video recording">
                <input
                    type="file"
                    id="recordingInput"
                    name="recording"
                    class="d-none"
                    accept=".mp3,.wav,.m4a,.aac,.flac,.ogg,.wma,.mp4,.wmv,audio/*,video/mp4,video/x-ms-wmv"
                >
                <div class="upload-icon-circle mx-auto mb-3">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <h5 class="fw-bold mb-1">Drag &amp; drop your recording here</h5>
                <p class="text-muted small mb-3">or click below to browse from your device</p>
                <button type="button" id="browseFileBtn" class="btn btn-outline-primary btn-sm px-3 fw-semibold mb-3">
                    <i class="fa-solid fa-folder-open me-1"></i>Browse File
                </button>
                <div class="supported-formats small text-muted">
                    <div><strong>Audio:</strong> MP3, WAV, M4A, AAC, FLAC, OGG, WMA</div>
                    <div><strong>Video:</strong> MP4, WMV &bull; <strong>Max Size:</strong> 250MB</div>
                </div>
            </div>

            <!-- Selected File Preview Card -->
            <div id="filePreviewCard" class="file-preview-card mt-4 p-3 p-md-4 rounded-3 d-none">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div class="d-flex align-items-start gap-3 min-w-0">
                        <div class="file-badge-icon">
                            <i class="fa-solid fa-file-audio" id="previewFileIcon"></i>
                        </div>
                        <div class="min-w-0">
                            <h6 class="fw-bold mb-1 text-truncate" id="previewFileName">—</h6>
                            <div class="d-flex flex-wrap gap-2 small text-muted font-mono">
                                <span id="previewFileSize">—</span>
                                <span>&bull;</span>
                                <span id="previewFileDuration">Duration: Detecting...</span>
                                <span>&bull;</span>
                                <span id="previewFileType">—</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="removeFileBtn" class="btn btn-sm btn-outline-danger flex-shrink-0" title="Remove selected file">
                        <i class="fa-solid fa-xmark me-1"></i>Remove
                    </button>
                </div>

                <div id="mediaPreviewContainer" class="mt-3 d-none">
                    <audio id="audioPlayer" class="w-100 d-none" controls preload="metadata"></audio>
                    <video id="videoPlayer" class="w-100 rounded-2 d-none" style="max-height: 260px;" controls preload="metadata"></video>
                </div>
            </div>

            <!-- Language Configuration -->
            <div class="row g-3 mt-4">
                <div class="col-md-6">
                    <label for="audioLanguageSelect" class="form-label fw-semibold small text-uppercase tracking-wide">
                        <i class="fa-solid fa-language me-1 text-primary"></i>Audio Language
                    </label>
                    <select id="audioLanguageSelect" class="form-select">
                        <option value="Auto Detect" selected>Auto Detect</option>
                        <option value="Chinese">Chinese</option>
                        <option value="English">English</option>
                        <option value="Malay">Malay</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="summaryLanguageSelect" class="form-label fw-semibold small text-uppercase tracking-wide">
                        <i class="fa-solid fa-file-lines me-1 text-primary"></i>Summary Language
                    </label>
                    <select id="summaryLanguageSelect" class="form-select">
                        <option value="Chinese" selected>Chinese</option>
                        <option value="English">English</option>
                        <option value="Malay">Malay</option>
                    </select>
                </div>
            </div>

            <!-- Process CTA -->
            <div class="d-grid mt-4">
                <button type="button" id="processRecordingBtn" class="btn btn-primary btn-lg fw-bold py-3" disabled>
                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Transcribe &amp; Generate Summary
                </button>
            </div>

            <!-- Processing Progress & Stepper -->
            <div id="processingPanel" class="processing-panel mt-4 p-4 rounded-3 d-none">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold small text-primary" id="processingStatusText">Uploading recording...</span>
                    <span class="font-mono small fw-semibold" id="processingPercentText">0%</span>
                </div>
                <div class="progress mb-3" style="height: 8px;">
                    <div
                        id="processingProgressBar"
                        class="progress-bar progress-bar-striped progress-bar-animated"
                        role="progressbar"
                        style="width: 0%"
                        aria-valuenow="0"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>

                <div class="stepper-list row row-cols-2 row-cols-sm-3 row-cols-md-6 g-2 small">
                    <div class="col step-item" data-step="upload">
                        <div class="step-box p-2 rounded-2 text-center">
                            <i class="fa-solid fa-cloud-arrow-up d-block mb-1"></i>
                            <span>Uploading recording</span>
                        </div>
                    </div>
                    <div class="col step-item" data-step="prepare">
                        <div class="step-box p-2 rounded-2 text-center">
                            <i class="fa-solid fa-sliders d-block mb-1"></i>
                            <span>Preparing recording</span>
                        </div>
                    </div>
                    <div class="col step-item" data-step="analyze">
                        <div class="step-box p-2 rounded-2 text-center">
                            <i class="fa-solid fa-brain d-block mb-1"></i>
                            <span>AI analysis</span>
                        </div>
                    </div>
                    <div class="col step-item" data-step="summarize">
                        <div class="step-box p-2 rounded-2 text-center">
                            <i class="fa-solid fa-list-check d-block mb-1"></i>
                            <span>Summary generation</span>
                        </div>
                    </div>
                    <div class="col step-item" data-step="report">
                        <div class="step-box p-2 rounded-2 text-center">
                            <i class="fa-solid fa-file-pdf d-block mb-1"></i>
                            <span>Report generation</span>
                        </div>
                    </div>
                    <div class="col step-item" data-step="done">
                        <div class="step-box p-2 rounded-2 text-center">
                            <i class="fa-solid fa-circle-check d-block mb-1"></i>
                            <span>Completed processing</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Result Section -->
        <section id="resultSection" class="d-none">
            <div class="glass-card p-4 p-md-5 mb-4">
                <!-- Result Action Buttons -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-4 mb-4 border-bottom">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Executive Summary Report</h4>
                        <div class="small text-muted font-mono" id="resultMetaLine"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="copySummaryBtn" class="btn btn-outline-primary btn-sm fw-semibold">
                            <i class="fa-regular fa-copy me-1"></i>Copy Summary
                        </button>
                        <a id="downloadPdfBtn" href="#" class="btn btn-primary btn-sm fw-semibold" download>
                            <i class="fa-solid fa-file-pdf me-1"></i>Download PDF
                        </a>
                        <button type="button" id="newAudioBtn" class="btn btn-dark btn-sm fw-semibold">
                            <i class="fa-solid fa-rotate-left me-1"></i>New Audio
                        </button>
                    </div>
                </div>

                <!-- Section 1: Overall Summary -->
                <div class="result-block mb-4">
                    <h5 class="section-heading">1. Overall Summary</h5>
                    <p id="secOverallSummary" class="mb-0 lh-lg"></p>
                </div>

                <!-- Section 2: Main Topics Discussed -->
                <div class="result-block mb-4">
                    <h5 class="section-heading">2. Main Topics Discussed</h5>
                    <ul id="secMainTopics" class="result-list mb-0"></ul>
                </div>

                <!-- Section 3: Important Points -->
                <div class="result-block mb-4">
                    <h5 class="section-heading">3. Important Points</h5>
                    <ul id="secImportantPoints" class="result-list mb-0"></ul>
                </div>

                <!-- Section 4: Key Metrics, References & Information -->
                <div class="result-block mb-4">
                    <h5 class="section-heading">4. Key Metrics, References &amp; Information</h5>
                    <ul id="secKeyMetrics" class="result-list mb-0"></ul>
                </div>

                <!-- Section 5: Decisions & Conclusions -->
                <div class="result-block mb-4">
                    <h5 class="section-heading">5. Decisions &amp; Conclusions</h5>
                    <div id="secDecisionsConclusions"></div>
                </div>

                <!-- Section 6: Action Items & Next Steps -->
                <div class="result-block mb-4">
                    <h5 class="section-heading">6. Action Items &amp; Next Steps</h5>
                    <div id="secActionItems"></div>
                </div>

                <!-- Section 7: High-Level Synthesis -->
                <div class="result-block mb-2">
                    <h5 class="section-heading">7. High-Level Synthesis</h5>
                    <p id="secHighLevelSynthesis" class="mb-0 lh-lg"></p>
                </div>
            </div>

            <!-- Collapsible Complete Audio Transcription -->
            <div class="glass-card p-3 p-md-4 accordion" id="transcriptAccordion">
                <div class="accordion-item border-0 bg-transparent">
                    <h2 class="accordion-header" id="transcriptHeading">
                        <button
                            class="accordion-button collapsed bg-transparent fw-bold px-2 py-2 shadow-none"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#transcriptCollapseBody"
                            aria-expanded="false"
                            aria-controls="transcriptCollapseBody"
                        >
                            <i class="fa-solid fa-file-lines me-2 text-primary"></i>Complete Audio Transcription (Whisper AI)
                        </button>
                    </h2>
                    <div
                        id="transcriptCollapseBody"
                        class="accordion-collapse collapse"
                        aria-labelledby="transcriptHeading"
                        data-bs-parent="#transcriptAccordion"
                    >
                        <div class="accordion-body px-2 pt-3">
                            <div class="d-flex justify-content-end mb-2">
                                <button type="button" id="copyTranscriptBtn" class="btn btn-sm btn-outline-secondary fw-semibold">
                                    <i class="fa-regular fa-copy me-1"></i>Copy Transcript
                                </button>
                            </div>
                            <pre id="fullTranscriptText" class="transcript-box p-3 rounded-3 mb-0"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>