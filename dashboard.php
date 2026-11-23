<?php require __DIR__.'/php-scripts/dashboard-page.php'; ?>

<!DOCTYPE html>

<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Dashboard | Plant System
</title>

<link
    rel="stylesheet"
    href="styles/navstyles.css"
>

<script src="/scripts/vendor/chart.umd.min.js"></script>
<script src="/scripts/vendor/hammer.min.js"></script>
<script src="/scripts/vendor/chartjs-plugin-zoom.min.js"></script>


<link rel="stylesheet" href="styles/dashboard.css"><link rel="stylesheet" href="styles/history.css?v=20261009c">

</head>


<body>

<?php include "navigation.php"; ?>


<main
    class="dashboard-page"
    id="dashboardPage"
    data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    data-api-url="php-scripts/dashboard-script.php"
>


<div class="dashboard-header">

    <div>

        <h1>
            Welcome,
            <?= ps_e($userName) ?>
        </h1>

        <p>
            Monitor your plants and view sensor history.
        </p>

    </div>

</div>


<?php if (count($devices) === 0): ?>


<div class="empty">

    <h2>
        No devices found
    </h2>

    <p>
        Add a device to start monitoring.
    </p>

    <a href="add-device.php">
        Add Device
    </a>

</div>


<?php else: ?>


<div class="dashboard-toolbar">

    <div class="toolbar-group">
        <label class="toolbar-label" for="deviceSelect">
            Device
        </label>

        <select id="deviceSelect">
            <?php foreach ($devices as $device): ?>

            <option value="<?= (int)$device["id"] ?>">
                <?= ps_e($device["device_name"]) ?>
            </option>

            <?php endforeach; ?>
        </select>
    </div>


    <div class="device-status-box">

        <span
            id="deviceStatus"
            class="device-status offline"
        >
            ● Checking...
        </span>

        <span
            id="lastSeen"
            class="last-seen"
        ></span>

    </div>

</div>


<section class="dashboard-section">

    <div class="section-heading">

        <div>
            <div class="section-kicker">LIVE DATA</div>
            <h2 class="section-title">Live Measurements</h2>
            <p class="section-subtitle">
                Current sensor values from the selected device.
            </p>
        </div>

        <div class="live-badge">
            <span class="live-dot"></span>
            <span id="liveReadingStatus">Waiting for data</span>
        </div>

    </div>


    <div class="sensor-grid">

        <div class="sensor-card">
            <div class="sensor-card-top">
                <span>Temperature</span>
                <div class="sensor-icon">🌡️</div>
            </div>

            <strong id="latestTemperature">--</strong>
        </div>


        <div class="sensor-card">
            <div class="sensor-card-top">
                <span>Humidity</span>
                <div class="sensor-icon">💧</div>
            </div>

            <strong id="latestHumidity">--</strong>
        </div>


        <div class="sensor-card">
            <div class="sensor-card-top">
                <span>Soil Moisture</span>
                <div class="sensor-icon">🌱</div>
            </div>

            <strong id="latestSoil">--</strong>
        </div>


        <div class="sensor-card">
            <div class="sensor-card-top">
                <span>Light</span>
                <div class="sensor-icon">☀️</div>
            </div>

            <strong id="latestLight">--</strong>
        </div>

    </div>

    <div
        id="latestReadingTime"
        class="live-reading-time"
    >
        No reading received yet.
    </div>

</section>


<section class="dashboard-section">

    <div class="section-heading">
        <div>
            <div class="section-kicker">MANUAL CONTROL</div>
            <h2 class="section-title">Irrigation Control</h2>
            <p class="section-subtitle">
                Start, stop or clear a stuck irrigation command.
            </p>
        </div>
    </div>


    <div class="control-panel">

        <div class="irrigation-actions">

            <div class="duration-field">
                <label for="wateringDuration">
                    Watering duration
                </label>

                <select id="wateringDuration">
                    <option value="10">10 seconds</option>
                    <option value="30" selected>30 seconds</option>
                    <option value="60">1 minute</option>
                    <option value="120">2 minutes</option>
                    <option value="300">5 minutes</option>
                    <option value="600">10 minutes</option>
                </select>
            </div>


            <button
                type="button"
                id="waterNowBtn"
                class="action-btn water-now-btn"
            >
                <span class="button-icon">💧</span>
                <span>Water Now</span>
            </button>


            <button
                type="button"
                id="stopWaterBtn"
                class="action-btn stop-water-btn"
            >
                <span class="button-icon">■</span>
                <span>Stop Watering</span>
            </button>


            <button
                type="button"
                id="clearCommandsBtn"
                class="action-btn clear-commands-btn"
            >
                <span class="button-icon">↻</span>
                <span>Clear Stuck Commands</span>
            </button>

        </div>


        <div
            id="waterMessage"
            class="water-message"
        ></div>

    </div>

</section>


<section class="dashboard-section">

    <div class="auto-rule-card">

        <div class="auto-rule-header">

            <div class="auto-rule-title-wrap">
                <div class="section-kicker">AUTOMATIC CONTROL</div>

                <div class="auto-rule-title">
                    Automatic Watering
                </div>

                <div class="auto-rule-subtitle">
                    Choose the sensor conditions that should trigger irrigation.
                </div>
            </div>


            <label class="switch-label">

                <input
                    type="checkbox"
                    id="ruleEnabled"
                >

                <span class="switch-track"></span>
                <span>Automatic watering</span>

            </label>

        </div>


        <div class="rule-conditions-grid">

            <div class="rule-condition">

                <label class="rule-sensor">
                    <input type="checkbox" id="soilEnabled">
                    <span>Soil Moisture</span>
                </label>

                <select id="soilOperator" aria-label="Soil moisture operator">
                    <option value="<">&lt;</option>
                    <option value=">">&gt;</option>
                    <option value="=">=</option>
                </select>

                <div class="rule-value-wrap">
                    <input
                        type="number"
                        id="soilValue"
                        min="0"
                        max="100"
                        step="0.1"
                        value="10"
                    >
                    <span class="rule-unit">%</span>
                </div>

            </div>


            <div class="rule-condition">

                <label class="rule-sensor">
                    <input type="checkbox" id="lightEnabled">
                    <span>Light</span>
                </label>

                <select id="lightOperator" aria-label="Light operator">
                    <option value="<">&lt;</option>
                    <option value=">">&gt;</option>
                    <option value="=">=</option>
                </select>

                <div class="rule-value-wrap">
                    <input
                        type="number"
                        id="lightValue"
                        min="0"
                        max="100"
                        step="0.1"
                        value="40"
                    >
                    <span class="rule-unit">%</span>
                </div>

            </div>


            <div class="rule-condition">

                <label class="rule-sensor">
                    <input type="checkbox" id="temperatureEnabled">
                    <span>Temperature</span>
                </label>

                <select id="temperatureOperator" aria-label="Temperature operator">
                    <option value="<">&lt;</option>
                    <option value=">">&gt;</option>
                    <option value="=">=</option>
                </select>

                <div class="rule-value-wrap">
                    <input
                        type="number"
                        id="temperatureValue"
                        min="-50"
                        max="100"
                        step="0.1"
                        value="20"
                    >
                    <span class="rule-unit">°C</span>
                </div>

            </div>


            <div class="rule-condition">

                <label class="rule-sensor">
                    <input type="checkbox" id="humidityEnabled">
                    <span>Air Humidity</span>
                </label>

                <select id="humidityOperator" aria-label="Humidity operator">
                    <option value="<">&lt;</option>
                    <option value=">">&gt;</option>
                    <option value="=">=</option>
                </select>

                <div class="rule-value-wrap">
                    <input
                        type="number"
                        id="humidityValue"
                        min="0"
                        max="100"
                        step="0.1"
                        value="40"
                    >
                    <span class="rule-unit">%</span>
                </div>

            </div>

        </div>


        <div class="rule-divider"></div>


        <div class="rule-settings">

            <div class="rule-field">
                <label for="ruleLogic">
                    Conditions
                </label>

                <select id="ruleLogic">
                    <option value="AND">ALL must match (AND)</option>
                    <option value="OR">ANY can match (OR)</option>
                </select>
            </div>


            <div class="rule-field">
                <label for="ruleDuration">
                    Watering duration
                </label>

                <select id="ruleDuration">
                    <option value="10">10 sec</option>
                    <option value="30" selected>30 sec</option>
                    <option value="60">1 min</option>
                    <option value="120">2 min</option>
                    <option value="300">5 min</option>
                    <option value="600">10 min</option>
                </select>
            </div>


            <div class="rule-field">
                <label for="ruleCooldown">
                    Cooldown after watering
                </label>

                <select id="ruleCooldown">
                    <option value="0">0 min (no cooldown)</option>
                    <option value="5">5 min</option>
                    <option value="15">15 min</option>
                    <option value="30" selected>30 min</option>
                    <option value="60">1 hour</option>
                    <option value="120">2 hours</option>
                    <option value="360">6 hours</option>
                    <option value="720">12 hours</option>
                    <option value="1440">24 hours</option>
                </select>
            </div>

        </div>


        <div class="rule-footer">

            <button
                type="button"
                id="saveRuleBtn"
                class="rule-save-btn"
            >
                Save Automatic Rule
            </button>

        </div>


        <div
            id="ruleMessage"
            class="rule-message"
        ></div>

    </div>

</section>


<section class="dashboard-section chart-section">

    <div class="section-heading history-heading">

        <div>
            <div class="section-kicker">HISTORY</div>
            <h2 class="section-title">Sensor History</h2>
            <p class="section-subtitle">
                Choose the period you want to display in the charts.
            </p>
        </div>

        <div class="history-range-wrap">

            <span class="toolbar-label">
                History range
            </span>

            <div class="range-switcher">

                <button class="range-btn active" data-range="today" type="button">
                    Today
                </button>

                <button class="range-btn" data-range="yesterday" type="button">
                    Yesterday
                </button>

                <button class="range-btn" data-range="7days" type="button">
                    7 Days
                </button>

                <button class="range-btn" data-range="30days" type="button">
                    30 Days
                </button>

            </div>

        </div>

    </div>


<div class="chart-grid">


<div class="chart-card">

    <div class="chart-card-header">

        <button
            type="button"
            class="chart-expand-btn"
            data-chart-key="temperature"
            data-chart-title="Temperature"
            data-chart-unit=" °C"
            aria-label="Open Temperature chart"
            title="Open larger chart"
        >
            🔍
        </button>

        <h2>
            Temperature
        </h2>

    </div>

    <div class="chart-container">

        <canvas id="temperatureChart"></canvas>

    </div>

</div>


<div class="chart-card">

    <div class="chart-card-header">

        <button
            type="button"
            class="chart-expand-btn"
            data-chart-key="humidity"
            data-chart-title="Humidity"
            data-chart-unit=" %"
            aria-label="Open Humidity chart"
            title="Open larger chart"
        >
            🔍
        </button>

        <h2>
            Humidity
        </h2>

    </div>

    <div class="chart-container">

        <canvas id="humidityChart"></canvas>

    </div>

</div>


<div class="chart-card">

    <div class="chart-card-header">

        <button
            type="button"
            class="chart-expand-btn"
            data-chart-key="soil"
            data-chart-title="Soil Moisture"
            data-chart-unit=" %"
            aria-label="Open Soil Moisture chart"
            title="Open larger chart"
        >
            🔍
        </button>

        <h2>
            Soil Moisture
        </h2>

    </div>

    <div class="chart-container">

        <canvas id="soilChart"></canvas>

    </div>

</div>


<div class="chart-card">

    <div class="chart-card-header">

        <button
            type="button"
            class="chart-expand-btn"
            data-chart-key="light"
            data-chart-title="Light"
            data-chart-unit=" %"
            aria-label="Open Light chart"
            title="Open larger chart"
        >
            🔍
        </button>

        <h2>
            Light
        </h2>

    </div>

    <div class="chart-container">

        <canvas id="lightChart"></canvas>

    </div>

</div>


</div>

</section>


<div
    id="chartModal"
    class="chart-modal"
    aria-hidden="true"
>

    <div
        class="chart-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="chartModalTitle"
    >

        <div class="chart-modal-header">

            <div class="chart-modal-title-wrap">
                <div class="chart-modal-eyebrow">
                    EXPANDED CHART
                </div>

                <h2
                    id="chartModalTitle"
                    class="chart-modal-title"
                >
                    Sensor Chart
                </h2>
            </div>


            <div class="chart-modal-actions">

                <button
                    type="button"
                    id="chartResetZoomBtn"
                    class="chart-modal-btn"
                >
                    Reset Zoom
                </button>

                <button
                    type="button"
                    id="chartModalCloseBtn"
                    class="chart-modal-btn chart-modal-close"
                    aria-label="Close chart"
                    title="Close"
                >
                    ×
                </button>

            </div>

        </div>


        <div class="chart-modal-help">
            <span><strong>Zoom:</strong> mouse wheel / trackpad</span>
            <span><strong>Area zoom:</strong> drag on the chart</span>
            <span><strong>Move:</strong> Shift + drag</span>
            <span><strong>Touch:</strong> pinch to zoom</span>
        </div>


        <div class="chart-modal-body">

            <div class="chart-modal-canvas-wrap">
                <canvas id="expandedChart"></canvas>
            </div>

        </div>

    </div>

</div>


<?php endif; ?>


</main>


<?php include "footer.php"; ?>

<?php if (count($devices) > 0): ?>
<script src="scripts/dashboard.js?v=20261009c"></script>
<?php endif; ?>

</body>

</html>
