<?php require __DIR__.'/php-scripts/irrigation-page.php'; ?>

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


<link rel="stylesheet" href="/styles/dashboard-automatic-irrigation.css?v=20261011">

</head>


<body id="irrigation-page" data-csrf-token="<?= ps_e($csrfToken) ?>">

<?php include "navigation.php"; ?>


<main class="dashboard-page">


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


<div class="dashboard-controls">


<select id="deviceSelect">

<?php foreach ($devices as $device): ?>

<option
    value="<?= (int)$device["id"] ?>"
>

<?= htmlspecialchars(
    $device["device_name"]
) ?>

</option>

<?php endforeach; ?>

</select>


<button
    class="range-btn active"
    data-range="today"
>
Today
</button>


<button
    class="range-btn"
    data-range="yesterday"
>
Yesterday
</button>


<button
    class="range-btn"
    data-range="7days"
>
7 Days
</button>


<button
    class="range-btn"
    data-range="30days"
>
30 Days
</button>


<div class="irrigation-control">

    <select id="wateringDuration">
        <option value="10">10 sec</option>
        <option value="30" selected>30 sec</option>
        <option value="60">1 min</option>
        <option value="120">2 min</option>
        <option value="300">5 min</option>
        <option value="600">10 min</option>
    </select>


    <button
        type="button"
        id="waterNowBtn"
        class="water-now-btn"
    >
        Water Now
    </button>

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
    >
    </span>

</div>


</div>


<div
    id="waterMessage"
    class="water-message"
></div>


<div class="auto-rule-card">

    <div class="auto-rule-header">

        <div class="auto-rule-title">
            Automatic Watering
        </div>

        <label>
            <input
                type="checkbox"
                id="ruleEnabled"
            >
            Enable automatic watering
        </label>

    </div>


    <div class="rule-condition">

        <label class="rule-sensor">
            <input type="checkbox" id="soilEnabled">
            Soil Moisture
        </label>

        <select id="soilOperator">
            <option value="<">&lt;</option>
            <option value=">">&gt;</option>
            <option value="=">=</option>
        </select>

        <input
            type="number"
            id="soilValue"
            min="0"
            max="100"
            step="0.1"
            value="10"
            placeholder="%"
        >

    </div>


    <div class="rule-condition">

        <label class="rule-sensor">
            <input type="checkbox" id="lightEnabled">
            Light
        </label>

        <select id="lightOperator">
            <option value="<">&lt;</option>
            <option value=">">&gt;</option>
            <option value="=">=</option>
        </select>

        <input
            type="number"
            id="lightValue"
            min="0"
            max="100"
            step="0.1"
            value="40"
            placeholder="%"
        >

    </div>


    <div class="rule-condition">

        <label class="rule-sensor">
            <input type="checkbox" id="temperatureEnabled">
            Temperature
        </label>

        <select id="temperatureOperator">
            <option value="<">&lt;</option>
            <option value=">">&gt;</option>
            <option value="=">=</option>
        </select>

        <input
            type="number"
            id="temperatureValue"
            min="-50"
            max="100"
            step="0.1"
            value="20"
            placeholder="°C"
        >

    </div>


    <div class="rule-condition">

        <label class="rule-sensor">
            <input type="checkbox" id="humidityEnabled">
            Air Humidity
        </label>

        <select id="humidityOperator">
            <option value="<">&lt;</option>
            <option value=">">&gt;</option>
            <option value="=">=</option>
        </select>

        <input
            type="number"
            id="humidityValue"
            min="0"
            max="100"
            step="0.1"
            value="40"
            placeholder="%"
        >

    </div>


    <div class="rule-settings">

        <div class="rule-field">
            <label for="ruleLogic">
                Conditions
            </label>

            <select id="ruleLogic">
                <option value="AND">
                    ALL must match (AND)
                </option>
                <option value="OR">
                    ANY can match (OR)
                </option>
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


    <button
        type="button"
        id="saveRuleBtn"
        class="rule-save-btn"
    >
        Save Automatic Rule
    </button>


    <div
        id="ruleMessage"
        class="rule-message"
    ></div>

</div>


<div class="sensor-grid">


<div class="sensor-card">

    <span>
        Temperature
    </span>

    <strong id="latestTemperature">
        --
    </strong>

</div>


<div class="sensor-card">

    <span>
        Humidity
    </span>

    <strong id="latestHumidity">
        --
    </strong>

</div>


<div class="sensor-card">

    <span>
        Soil Moisture
    </span>

    <strong id="latestSoil">
        --
    </strong>

</div>


<div class="sensor-card">

    <span>
        Light
    </span>

    <strong id="latestLight">
        --
    </strong>

</div>


</div>


<div class="chart-grid">


<div class="chart-card">

    <h2>
        Temperature
    </h2>

    <div class="chart-container">

        <canvas id="temperatureChart"></canvas>

    </div>

</div>


<div class="chart-card">

    <h2>
        Humidity
    </h2>

    <div class="chart-container">

        <canvas id="humidityChart"></canvas>

    </div>

</div>


<div class="chart-card">

    <h2>
        Soil Moisture
    </h2>

    <div class="chart-container">

        <canvas id="soilChart"></canvas>

    </div>

</div>


<div class="chart-card">

    <h2>
        Light
    </h2>

    <div class="chart-container">

        <canvas id="lightChart"></canvas>

    </div>

</div>


</div>


<?php endif; ?>


</main>


<?php include "footer.php"; ?>


<?php if (count($devices) > 0): ?>


<script src="/scripts/dashboard-automatic-irrigation.js?v=20261011" defer></script>


<?php endif; ?>


</body>

</html>
