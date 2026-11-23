

const WATER_CSRF_TOKEN =
    document.getElementById("irrigation-page").dataset.csrfToken;



// =====================================================
// STATE
// =====================================================

let selectedRange =
    "today";


let temperatureChart = null;
let humidityChart = null;
let soilChart = null;
let lightChart = null;


// =====================================================
// DATE LABEL
// =====================================================

function formatDate(dateString) {

    const date =
        new Date(
            dateString.replace(
                " ",
                "T"
            )
        );


    if (
        selectedRange === "today"
        ||
        selectedRange === "yesterday"
    ) {

        return date.toLocaleTimeString(
            [],
            {
                hour: "2-digit",
                minute: "2-digit"
            }
        );
    }


    return date.toLocaleDateString(
        [],
        {
            day: "2-digit",
            month: "2-digit"
        }
    )
    +
    " "
    +
    date.toLocaleTimeString(
        [],
        {
            hour: "2-digit",
            minute: "2-digit"
        }
    );
}


// =====================================================
// CREATE CHART
// =====================================================

function createChart(
    canvasId,
    label,
    labels,
    values,
    unit
) {

    const ctx =
        document
        .getElementById(
            canvasId
        )
        .getContext(
            "2d"
        );


    return new Chart(

        ctx,

        {

            type:
                "line",

            data: {

                labels:
                    labels,

                datasets: [

                    {

                        label:
                            label,

                        data:
                            values,

                        tension:
                            0.3,

                        fill:
                            false,

                        pointRadius:
                            2,

                        borderWidth:
                            2
                    }

                ]
            },


            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,


                interaction: {

                    intersect:
                        false,

                    mode:
                        "index"
                },


                plugins: {

                    legend: {

                        display:
                            false
                    },


                    tooltip: {

                        callbacks: {

                            label: function(context) {

                                return (
                                    context.parsed.y
                                    +
                                    unit
                                );
                            }
                        }
                    }
                },


                scales: {

                    x: {

                        ticks: {

                            maxTicksLimit:
                                8
                        }
                    },


                    y: {

                        beginAtZero:
                            false
                    }
                }
            }
        }
    );
}


// =====================================================
// DESTROY OLD CHARTS
// =====================================================

function destroyCharts() {

    if (temperatureChart) {
        temperatureChart.destroy();
    }

    if (humidityChart) {
        humidityChart.destroy();
    }

    if (soilChart) {
        soilChart.destroy();
    }

    if (lightChart) {
        lightChart.destroy();
    }
}


// =====================================================
// DEVICE ONLINE / OFFLINE STATUS
// =====================================================

async function loadDeviceStatus() {

    const deviceSelect =
        document.getElementById(
            "deviceSelect"
        );


    const statusElement =
        document.getElementById(
            "deviceStatus"
        );


    const lastSeenElement =
        document.getElementById(
            "lastSeen"
        );


    if (
        !deviceSelect
        ||
        !statusElement
        ||
        !lastSeenElement
    ) {

        return;
    }


    const deviceId =
        deviceSelect.value;


    try {

        const response =
            await fetch(
                "php-scripts/dashboard-script.php"
                +
                "?action=device_status"
                +
                "&device_id="
                +
                encodeURIComponent(
                    deviceId
                )
                +
                "&_="
                +
                Date.now(),
                {
                    cache: "no-store"
                }
            );


        const data =
            await response.json();


        if (!data.success) {

            statusElement.textContent =
                "● Unknown";

            statusElement.className =
                "device-status offline";

            lastSeenElement.textContent =
                "";

            return;
        }


        if (
            Number(data.is_online) === 1
        ) {

            statusElement.textContent =
                "● Online";

            statusElement.className =
                "device-status online";

            lastSeenElement.textContent =
                "Device connected";

        } else {

            statusElement.textContent =
                "● Offline";

            statusElement.className =
                "device-status offline";


            if (data.last_seen) {

                const lastSeenDate =
                    new Date(
                        data.last_seen.replace(
                            " ",
                            "T"
                        )
                    );


                lastSeenElement.textContent =
                    "Last seen: "
                    +
                    lastSeenDate.toLocaleString();

            } else {

                lastSeenElement.textContent =
                    "Never seen";
            }
        }

    } catch (error) {

        console.error(
            "Device status error:",
            error
        );


        statusElement.textContent =
            "● Unknown";

        statusElement.className =
            "device-status offline";

        lastSeenElement.textContent =
            "";
    }
}


// =====================================================
// LOAD DATA
// =====================================================

async function loadReadings() {

    const deviceId =
        document
        .getElementById(
            "deviceSelect"
        )
        .value;


    try {

        const response =
            await fetch(

                "api-readings.php"
                +
                "?device_id="
                +
                encodeURIComponent(
                    deviceId
                )
                +
                "&range="
                +
                encodeURIComponent(
                    selectedRange
                )

            );


        const data =
            await response.json();


        if (!data.success) {

            console.error(
                data.message
            );

            return;
        }


        const readings =
            data.readings;


        destroyCharts();


        // =================================================
        // NO READINGS
        // =================================================

        if (
            readings.length === 0
        ) {

            document
            .getElementById(
                "latestTemperature"
            )
            .textContent =
                "--";


            document
            .getElementById(
                "latestHumidity"
            )
            .textContent =
                "--";


            document
            .getElementById(
                "latestSoil"
            )
            .textContent =
                "--";


            document
            .getElementById(
                "latestLight"
            )
            .textContent =
                "--";


            return;
        }


        // =================================================
        // LATEST VALUES
        // =================================================

        const latest =
            readings[
                readings.length - 1
            ];


        document
        .getElementById(
            "latestTemperature"
        )
        .textContent =
            latest.temperature
            +
            " °C";


        document
        .getElementById(
            "latestHumidity"
        )
        .textContent =
            latest.humidity
            +
            " %";


        document
        .getElementById(
            "latestSoil"
        )
        .textContent =
            latest.soil_moisture
            +
            " %";


        document
        .getElementById(
            "latestLight"
        )
        .textContent =
            latest.light_level
            +
            " %";


        // =================================================
        // ARRAYS
        // =================================================

        const labels =
            readings.map(
                reading =>
                    formatDate(
                        reading.recorded_at
                    )
            );


        const temperatures =
            readings.map(
                reading =>
                    reading.temperature
            );


        const humidities =
            readings.map(
                reading =>
                    reading.humidity
            );


        const soils =
            readings.map(
                reading =>
                    reading.soil_moisture
            );


        const lights =
            readings.map(
                reading =>
                    reading.light_level
            );


        // =================================================
        // CHARTS
        // =================================================

        temperatureChart =
            createChart(
                "temperatureChart",
                "Temperature",
                labels,
                temperatures,
                " °C"
            );


        humidityChart =
            createChart(
                "humidityChart",
                "Humidity",
                labels,
                humidities,
                " %"
            );


        soilChart =
            createChart(
                "soilChart",
                "Soil Moisture",
                labels,
                soils,
                " %"
            );


        lightChart =
            createChart(
                "lightChart",
                "Light",
                labels,
                lights,
                " %"
            );

    }

    catch (error) {

        console.error(
            "Dashboard error:",
            error
        );
    }
}


// =====================================================
// WATER NOW
// =====================================================

function showWaterMessage(
    message,
    type
) {

    const box =
        document.getElementById(
            "waterMessage"
        );


    if (!box) {
        return;
    }


    box.textContent =
        message;


    box.className =
        "water-message show "
        +
        type;
}


async function waterNow() {

    const deviceSelect =
        document.getElementById(
            "deviceSelect"
        );


    const durationSelect =
        document.getElementById(
            "wateringDuration"
        );


    const button =
        document.getElementById(
            "waterNowBtn"
        );


    if (
        !deviceSelect
        ||
        !durationSelect
        ||
        !button
    ) {

        return;
    }


    const deviceId =
        Number(
            deviceSelect.value
        );


    const duration =
        Number(
            durationSelect.value
        );


    if (
        !deviceId
        ||
        duration < 1
        ||
        duration > 600
    ) {

        showWaterMessage(
            "Invalid watering request.",
            "error"
        );

        return;
    }


    button.disabled =
        true;


    button.textContent =
        "Sending...";


    try {

        const response =
            await fetch(
                "water-now.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify({
                            device_id:
                                deviceId,

                            duration:
                                duration,

                            csrf_token:
                                WATER_CSRF_TOKEN
                        })
                }
            );


        const data =
            await response.json();


        if (
            !response.ok
            ||
            !data.success
        ) {

            throw new Error(
                data.message
                ||
                "Watering request failed."
            );
        }


        showWaterMessage(
            "Watering command queued for "
            +
            duration
            +
            " seconds. Command: "
            +
            data.command_id,
            "success"
        );

    }

    catch (error) {

        showWaterMessage(
            error.message
            ||
            "Could not create watering command.",
            "error"
        );
    }

    finally {

        button.disabled =
            false;


        button.textContent =
            "Water Now";
    }
}


document
.getElementById(
    "waterNowBtn"
)
.addEventListener(
    "click",
    waterNow
);


// =====================================================
// AUTOMATIC IRRIGATION RULE
// =====================================================

function showRuleMessage(
    message,
    type
) {

    const box =
        document.getElementById(
            "ruleMessage"
        );

    if (!box) {
        return;
    }

    box.textContent =
        message;

    box.className =
        "rule-message show "
        +
        type;
}


function getConditionData(
    prefix
) {

    return {
        enabled:
            document.getElementById(
                prefix + "Enabled"
            ).checked,

        operator:
            document.getElementById(
                prefix + "Operator"
            ).value,

        value:
            Number(
                document.getElementById(
                    prefix + "Value"
                ).value
            )
    };
}


function setConditionData(
    prefix,
    data,
    fallbackValue
) {

    document.getElementById(
        prefix + "Enabled"
    ).checked =
        Boolean(
            data?.enabled
        );

    document.getElementById(
        prefix + "Operator"
    ).value =
        data?.operator
        || "<";

    document.getElementById(
        prefix + "Value"
    ).value =
        data?.value
        ??
        fallbackValue;
}


async function loadAutomaticRule() {

    const deviceId =
        Number(
            document.getElementById(
                "deviceSelect"
            ).value
        );

    if (!deviceId) {
        return;
    }

    try {

        const response =
            await fetch(
                "get-irrigation-rule.php?device_id="
                +
                encodeURIComponent(
                    deviceId
                ),
                {
                    cache: "no-store"
                }
            );

        const data =
            await response.json();

        if (
            !response.ok
            ||
            !data.success
        ) {

            throw new Error(
                data.message
                ||
                "Could not load automatic rule."
            );
        }

        const rule =
            data.rule;

        if (!rule) {

            document.getElementById(
                "ruleEnabled"
            ).checked =
                false;

            setConditionData(
                "soil",
                null,
                10
            );

            setConditionData(
                "light",
                null,
                40
            );

            setConditionData(
                "temperature",
                null,
                20
            );

            setConditionData(
                "humidity",
                null,
                40
            );

            document.getElementById(
                "ruleLogic"
            ).value =
                "AND";

            document.getElementById(
                "ruleDuration"
            ).value =
                "30";

            document.getElementById(
                "ruleCooldown"
            ).value =
                "30";

            return;
        }

        document.getElementById(
            "ruleEnabled"
        ).checked =
            Boolean(
                rule.enabled
            );

        setConditionData(
            "soil",
            rule.soil,
            10
        );

        setConditionData(
            "light",
            rule.light,
            40
        );

        setConditionData(
            "temperature",
            rule.temperature,
            20
        );

        setConditionData(
            "humidity",
            rule.humidity,
            40
        );

        document.getElementById(
            "ruleLogic"
        ).value =
            rule.logic
            ||
            "AND";

        document.getElementById(
            "ruleDuration"
        ).value =
            String(
                rule.duration_seconds
                ||
                30
            );

        document.getElementById(
            "ruleCooldown"
        ).value =
            String(
                rule.cooldown_minutes
                ||
                30
            );

    }

    catch (error) {

        showRuleMessage(
            error.message,
            "error"
        );
    }
}


async function saveAutomaticRule() {

    const button =
        document.getElementById(
            "saveRuleBtn"
        );

    const deviceId =
        Number(
            document.getElementById(
                "deviceSelect"
            ).value
        );

    const payload = {

        csrf_token:
            WATER_CSRF_TOKEN,

        device_id:
            deviceId,

        enabled:
            document.getElementById(
                "ruleEnabled"
            ).checked,

        logic:
            document.getElementById(
                "ruleLogic"
            ).value,

        duration_seconds:
            Number(
                document.getElementById(
                    "ruleDuration"
                ).value
            ),

        cooldown_minutes:
            Number(
                document.getElementById(
                    "ruleCooldown"
                ).value
            ),

        soil:
            getConditionData(
                "soil"
            ),

        light:
            getConditionData(
                "light"
            ),

        temperature:
            getConditionData(
                "temperature"
            ),

        humidity:
            getConditionData(
                "humidity"
            )
    };


    const activeCount =
        [
            payload.soil,
            payload.light,
            payload.temperature,
            payload.humidity
        ]
        .filter(
            condition =>
                condition.enabled
        )
        .length;


    if (
        payload.enabled
        &&
        activeCount === 0
    ) {

        showRuleMessage(
            "Enable at least one sensor condition.",
            "error"
        );

        return;
    }


    button.disabled =
        true;

    button.textContent =
        "Saving...";


    try {

        const response =
            await fetch(
                "save-irrigation-rule.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify(
                            payload
                        )
                }
            );

        const data =
            await response.json();

        if (
            !response.ok
            ||
            !data.success
        ) {

            throw new Error(
                data.message
                ||
                "Could not save automatic rule."
            );
        }

        showRuleMessage(
            "Automatic watering rule saved.",
            "success"
        );

    }

    catch (error) {

        showRuleMessage(
            error.message
            ||
            "Could not save automatic rule.",
            "error"
        );
    }

    finally {

        button.disabled =
            false;

        button.textContent =
            "Save Automatic Rule";
    }
}


document
.getElementById(
    "saveRuleBtn"
)
.addEventListener(
    "click",
    saveAutomaticRule
);


// Load saved rule for initial device.
loadAutomaticRule();


// =====================================================
// DEVICE CHANGE
// =====================================================

document
.getElementById(
    "deviceSelect"
)
.addEventListener(

    "change",

    function() {

        loadReadings();

        loadAutomaticRule();
        loadDeviceStatus();
    }

);


// =====================================================
// RANGE BUTTONS
// =====================================================

document
.querySelectorAll(
    ".range-btn"
)
.forEach(

    button => {

        button.addEventListener(

            "click",

            function() {

                document
                .querySelectorAll(
                    ".range-btn"
                )
                .forEach(
                    btn =>
                        btn.classList.remove(
                            "active"
                        )
                );


                this.classList.add(
                    "active"
                );


                selectedRange =
                    this.dataset.range;


                loadReadings();
            }

        );
    }

);


// =====================================================
// INITIAL LOAD
// =====================================================

loadReadings();
loadDeviceStatus();


// =====================================================
// AUTO REFRESH
// =====================================================

setInterval(
    loadReadings,
    10000
);


setInterval(
    loadDeviceStatus,
    5000
);
