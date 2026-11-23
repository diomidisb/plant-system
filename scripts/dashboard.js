const dashboardPage =
    document.getElementById("dashboardPage");

const WATER_CSRF_TOKEN =
    dashboardPage?.dataset.csrfToken || "";

const DASHBOARD_API_URL =
    dashboardPage?.dataset.apiUrl || "php-scripts/dashboard-script.php";


// =====================================================
// SAFE JSON RESPONSE READER
// =====================================================

async function readJsonResponse(response) {

    let raw =
        await response.text();


    // Remove UTF-8 BOM and surrounding whitespace.
    raw =
        raw
        .replace(
            /^\uFEFF/,
            ""
        )
        .trim();


    try {

        const parsed=JSON.parse(raw);if(parsed.redirect)window.location.assign(parsed.redirect);return parsed;

    }

    catch (error) {

        console.error(
            "INVALID JSON ENDPOINT:",
            response.url
        );

        console.error(
            "HTTP STATUS:",
            response.status
        );

        console.error(
            "RAW SERVER RESPONSE:",
            raw
        );


        throw new Error(
            "Invalid JSON from "
            +
            response.url
        );
    }
}



// =====================================================
// STATE
// =====================================================

let selectedRange =
    "today";


let temperatureChart = null;
let humidityChart = null;
let soilChart = null;
let lightChart = null;
let expandedChart = null;


let historyAbortController = null;
let historyRequestId = 0;


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
                    cubicInterpolationMode: 'monotone',
                    spanGaps: true,

                        fill:
                            false,

                        pointRadius: values.length === 1 ? 3 : 0,

                        pointHoverRadius:
                            4,

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

                animation:
                    false,

                normalized:
                    true,


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
// EXPANDED / ZOOMABLE CHART
// =====================================================

function getHistoryChartByKey(chartKey) {

    const chartMap = {
        temperature: temperatureChart,
        humidity: humidityChart,
        soil: soilChart,
        light: lightChart
    };

    return chartMap[chartKey] || null;
}


function closeExpandedChart() {

    const modal =
        document.getElementById(
            "chartModal"
        );

    if (!modal) {
        return;
    }


    if (expandedChart) {
        expandedChart.destroy();
        expandedChart = null;
    }


    modal.classList.remove(
        "open"
    );

    modal.setAttribute(
        "aria-hidden",
        "true"
    );

    document.body.classList.remove(
        "chart-modal-open"
    );
}


function openExpandedChart(
    chartKey,
    title,
    unit
) {

    const sourceChart =
        getHistoryChartByKey(
            chartKey
        );

    const modal =
        document.getElementById(
            "chartModal"
        );

    const titleElement =
        document.getElementById(
            "chartModalTitle"
        );

    const canvas =
        document.getElementById(
            "expandedChart"
        );


    if (
        !sourceChart
        ||
        !modal
        ||
        !titleElement
        ||
        !canvas
    ) {
        return;
    }


    if (expandedChart) {
        expandedChart.destroy();
        expandedChart = null;
    }


    titleElement.textContent =
        title;


    modal.classList.add(
        "open"
    );

    modal.setAttribute(
        "aria-hidden",
        "false"
    );

    document.body.classList.add(
        "chart-modal-open"
    );


    const labels =
        [...sourceChart.data.labels];

    const values =
        [...sourceChart.data.datasets[0].data];


    expandedChart =
        new Chart(
            canvas.getContext("2d"),
            {
                type: "line",

                data: {
                    labels: labels,

                    datasets: [
                        {
                            label: title,
                            data: values,
                            tension: 0.28,
                    cubicInterpolationMode: 'monotone',
                    spanGaps: true,
                            fill: false,
                            pointRadius: values.length === 1 ? 3 : 0,
                            pointHoverRadius: 5,
                            borderWidth: 2
                        }
                    ]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    normalized: true,

                    interaction: {
                        intersect: false,
                        mode: "index"
                    },

                    plugins: {
                        legend: {
                            display: false
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
                        },

                        zoom: {
                            limits: {
                                x: {
                                    minRange: 4
                                }
                            },

                            pan: {
                                enabled: true,
                                mode: "x",
                                modifierKey: "shift"
                            },

                            zoom: {
                                wheel: {
                                    enabled: true,
                                    speed: 0.08
                                },

                                pinch: {
                                    enabled: true
                                },

                                drag: {
                                    enabled: true,
                                    borderWidth: 1,
                                    borderColor: "rgba(47, 112, 66, 0.55)",
                                    backgroundColor: "rgba(47, 112, 66, 0.10)"
                                },

                                mode: "x"
                            }
                        }
                    },

                    scales: {
                        x: {
                            ticks: {
                                maxTicksLimit: 14
                            }
                        },

                        y: {
                            beginAtZero: false
                        }
                    }
                }
            }
        );


    requestAnimationFrame(
        () => {
            if (expandedChart) {
                expandedChart.resize();
            }
        }
    );
}


document
.querySelectorAll(
    ".chart-expand-btn"
)
.forEach(
    button => {

        button.addEventListener(
            "click",
            () => {

                openExpandedChart(
                    button.dataset.chartKey,
                    button.dataset.chartTitle,
                    button.dataset.chartUnit
                );
            }
        );
    }
);


document
.getElementById(
    "chartModalCloseBtn"
)
.addEventListener(
    "click",
    closeExpandedChart
);


document
.getElementById(
    "chartResetZoomBtn"
)
.addEventListener(
    "click",
    () => {

        if (
            expandedChart
            &&
            typeof expandedChart.resetZoom === "function"
        ) {
            expandedChart.resetZoom();
        }
    }
);


document
.getElementById(
    "chartModal"
)
.addEventListener(
    "click",
    event => {

        if (
            event.target.id ===
            "chartModal"
        ) {
            closeExpandedChart();
        }
    }
);


document.addEventListener(
    "keydown",
    event => {

        if (
            event.key === "Escape"
            &&
            document
            .getElementById(
                "chartModal"
            )
            .classList
            .contains(
                "open"
            )
        ) {
            closeExpandedChart();
        }
    }
);


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
                DASHBOARD_API_URL
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
            await readJsonResponse(
                response
            );


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
// LIVE LATEST READING
// =====================================================

function clearLiveReadings() {

    document.getElementById("latestTemperature").textContent = "--";
    document.getElementById("latestHumidity").textContent = "--";
    document.getElementById("latestSoil").textContent = "--";
    document.getElementById("latestLight").textContent = "--";

    const timeElement =
        document.getElementById("latestReadingTime");

    const statusElement =
        document.getElementById("liveReadingStatus");

    if (timeElement) {
        timeElement.textContent =
            "No reading received yet.";
    }

    if (statusElement) {
        statusElement.textContent =
            "Waiting for data";
    }
}


async function loadLatestReading() {

    const deviceSelect =
        document.getElementById(
            "deviceSelect"
        );

    if (!deviceSelect) {
        return;
    }

    const deviceId =
        deviceSelect.value;

    try {

        const response =
            await fetch(
                DASHBOARD_API_URL
                +
                "?action=latest_reading"
                +
                "&device_id="
                +
                encodeURIComponent(deviceId)
                +
                "&_="
                +
                Date.now(),
                {
                    cache: "no-store"
                }
            );

        const data =
            await readJsonResponse(
                response
            );

        if (
            !response.ok
            ||
            !data.success
        ) {
            throw new Error(
                data.message
                ||
                "Could not load latest reading."
            );
        }

        if (!data.reading) {
            clearLiveReadings();
            return;
        }

        const latest =
            data.reading;

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


        const readingDate =
            latest.recorded_at
                ? new Date(
                    latest.recorded_at.replace(
                        " ",
                        "T"
                    )
                )
                : null;

        const timeElement =
            document.getElementById(
                "latestReadingTime"
            );

        const statusElement =
            document.getElementById(
                "liveReadingStatus"
            );

        if (timeElement) {
            timeElement.textContent =
                readingDate
                    ? "Last sensor update: " + readingDate.toLocaleString()
                    : "Latest sensor reading loaded.";
        }

        if (statusElement) {
            statusElement.textContent =
                "Live data";
        }

    } catch (error) {

        console.error(
            "Latest reading error:",
            error
        );

        const statusElement =
            document.getElementById(
                "liveReadingStatus"
            );

        if (statusElement) {
            statusElement.textContent =
                "Update unavailable";
        }
    }
}


// =====================================================
// LOAD HISTORY DATA
// =====================================================

function historyStatus(text, loading=false, error=false) {
    let status=document.getElementById('history-status');
    if(!status){status=document.createElement('div');status.id='history-status';status.setAttribute('role','status');status.setAttribute('aria-live','polite');const chart=document.getElementById('temperatureChart');chart.closest('.chart-container').parentElement.before(status);}
    status.className='history-status'+(loading?' is-loading':'')+(error?' is-error':'');
    status.textContent=text;
    document.querySelectorAll('.chart-container').forEach(el=>{el.classList.toggle('history-loading',loading);el.setAttribute('aria-busy',String(loading));});
}
async function loadReadings() {
    const deviceId=document.getElementById('deviceSelect').value;
    if(historyAbortController) historyAbortController.abort();
    const controller=new AbortController();historyAbortController=controller;
    const requestId=++historyRequestId;
    historyStatus('Loading measurements…',true);
    const timeout=setTimeout(()=>controller.abort('timeout'),20000);
    try {
        const response=await fetch(DASHBOARD_API_URL+'?action=readings&device_id='+encodeURIComponent(deviceId)+'&range='+encodeURIComponent(selectedRange),{cache:'no-store',signal:controller.signal});
        const data=await readJsonResponse(response);
        if(requestId!==historyRequestId)return;
        if(data.redirect){window.location.assign(data.redirect);return;}
        if(!response.ok || !data.success)throw new Error(data.message||'Unable to load measurements.');
        const readings=data.readings;
        destroyCharts();
        if(!readings.length){historyStatus('No measurements for this period.');return;}
        const labels=readings.map(r=>formatDate(r.recorded_at));
        temperatureChart=createChart('temperatureChart','Temperature',labels,readings.map(r=>r.temperature),' °C');
        humidityChart=createChart('humidityChart','Humidity',labels,readings.map(r=>r.humidity),' %');
        soilChart=createChart('soilChart','Soil Moisture',labels,readings.map(r=>r.soil_moisture),' %');
        lightChart=createChart('lightChart','Light',labels,readings.map(r=>r.light_level),' %');
        historyStatus(data.aggregation_minutes===0?'Showing individual measurements. Live values update separately.':'Showing hourly averages. Live values update separately.');
    }catch(error){
        if(requestId!==historyRequestId)return;
        historyStatus(controller.signal.aborted?'Loading took too long. Select the period again to retry.':(error.message||'Unable to load measurements. Please try again.'),false,true);
        // A failed range change must not leave a chart labelled as the new range.
        destroyCharts();
    }finally{clearTimeout(timeout);if(requestId===historyRequestId){document.querySelectorAll('.chart-container').forEach(el=>{el.classList.remove('history-loading');el.setAttribute('aria-busy','false');});}}
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


    button.innerHTML =
        '<span class="button-icon">💧</span>'
        +
        '<span>Sending...</span>';

    await new Promise(
        resolve => requestAnimationFrame(resolve)
    );


    try {

        const formData =
            new URLSearchParams();

        formData.set(
            "device_id",
            String(deviceId)
        );

        formData.set(
            "duration",
            String(duration)
        );

        formData.set(
            "csrf_token",
            WATER_CSRF_TOKEN
        );


        const response =
            await fetch(
                DASHBOARD_API_URL + "?action=water_now",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded;charset=UTF-8"
                    },

                    body:
                        formData.toString(),

                    cache:
                        "no-store"
                }
            );


        let raw =
            await response.text();

        raw =
            raw
            .replace(
                /^\uFEFF/,
                ""
            )
            .trim();


        console.log(
            "WATER NOW RAW RESPONSE:",
            raw
        );


        if (
            !response.ok
            ||
            !raw.startsWith("OK|")
        ) {

            const message =
                raw.startsWith("ERROR|")
                    ? raw.substring(6)
                    : (
                        raw
                        ||
                        "Watering request failed."
                    );

            throw new Error(
                message
            );
        }


        const commandId =
            raw.substring(3);


        showWaterMessage(
            "Watering command queued for "
            +
            duration
            +
            " seconds. Command: "
            +
            commandId,
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


        button.innerHTML =
            '<span class="button-icon">💧</span>'
            +
            '<span>Water Now</span>';
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


document
.getElementById(
    "stopWaterBtn"
)
.addEventListener(
    "click",
    stopWatering
);


async function stopWatering() {

    const deviceSelect =
        document.getElementById(
            "deviceSelect"
        );

    const stopButton =
        document.getElementById(
            "stopWaterBtn"
        );


    if (
        !deviceSelect
        ||
        !stopButton
    ) {
        return;
    }


    const deviceId =
        Number(
            deviceSelect.value
        );


    if (!deviceId) {

        showWaterMessage(
            "Please select a device.",
            "error"
        );

        return;
    }


    stopButton.disabled = true;
    stopButton.innerHTML =
        '<span class="button-icon">■</span>'
        +
        '<span>Stopping...</span>';

    await new Promise(
        resolve => requestAnimationFrame(resolve)
    );


    try {

        const formData =
            new URLSearchParams();

        formData.set(
            "device_id",
            String(deviceId)
        );

        formData.set(
            "csrf_token",
            WATER_CSRF_TOKEN
        );


        const response =
            await fetch(
                DASHBOARD_API_URL + "?action=stop_watering",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded;charset=UTF-8"
                    },

                    body:
                        formData.toString(),

                    cache:
                        "no-store"
                }
            );


        let raw =
            await response.text();

        raw =
            raw
            .replace(
                /^\uFEFF/,
                ""
            )
            .trim();


        if (
            !response.ok
            ||
            !raw.startsWith("OK|")
        ) {

            const message =
                raw.startsWith("ERROR|")
                    ? raw.substring(6)
                    : (
                        raw
                        ||
                        "Stop request failed."
                    );

            throw new Error(
                message
            );
        }


        showWaterMessage(
            raw.substring(3),
            "success"
        );

    } catch (error) {

        showWaterMessage(
            error.message,
            "error"
        );

    } finally {

        stopButton.disabled = false;
        stopButton.innerHTML =
            '<span class="button-icon">■</span>'
            +
            '<span>Stop Watering</span>';
    }
}


// =====================================================
// CLEAR STUCK COMMANDS
// =====================================================

async function clearStuckCommands() {

    const deviceSelect =
        document.getElementById(
            "deviceSelect"
        );

    const button =
        document.getElementById(
            "clearCommandsBtn"
        );

    if (
        !deviceSelect
        ||
        !button
    ) {
        return;
    }

    const deviceId =
        Number(
            deviceSelect.value
        );

    if (!deviceId) {

        showWaterMessage(
            "Please select a device.",
            "error"
        );

        return;
    }


    button.disabled = true;

    button.innerHTML =
        '<span class="button-icon">↻</span>'
        +
        '<span>Clearing...</span>';

    await new Promise(
        resolve => requestAnimationFrame(resolve)
    );


    try {

        const formData =
            new URLSearchParams();

        formData.set(
            "device_id",
            String(deviceId)
        );

        formData.set(
            "csrf_token",
            WATER_CSRF_TOKEN
        );


        const response =
            await fetch(
                DASHBOARD_API_URL + "?action=clear_irrigation_commands",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded;charset=UTF-8"
                    },

                    body:
                        formData.toString(),

                    cache:
                        "no-store"
                }
            );


        let raw =
            await response.text();

        raw =
            raw
            .replace(
                /^\uFEFF/,
                ""
            )
            .trim();


        if (
            !response.ok
            ||
            !raw.startsWith("OK|")
        ) {

            const message =
                raw.startsWith("ERROR|")
                    ? raw.substring(6)
                    : (
                        raw
                        ||
                        "Could not clear stuck commands."
                    );

            throw new Error(
                message
            );
        }


        showWaterMessage(
            raw.substring(3)
            +
            " You can use Water Now again.",
            "success"
        );

    } catch (error) {

        showWaterMessage(
            error.message
            ||
            "Could not clear stuck commands.",
            "error"
        );

    } finally {

        button.disabled = false;

        button.innerHTML =
            '<span class="button-icon">↻</span>'
            +
            '<span>Clear Stuck Commands</span>';
    }
}


document
.getElementById(
    "clearCommandsBtn"
)
.addEventListener(
    "click",
    clearStuckCommands
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
                DASHBOARD_API_URL + "?action=irrigation_rule&device_id="
                +
                encodeURIComponent(
                    deviceId
                ),
                {
                    cache: "no-store"
                }
            );

        const data =
            await readJsonResponse(
                response
            );

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
                ??
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

    await new Promise(
        resolve => requestAnimationFrame(resolve)
    );


    try {

        const response =
            await fetch(
                DASHBOARD_API_URL + "?action=save_irrigation_rule",
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
            await readJsonResponse(
                response
            );

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

        loadLatestReading();
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

loadLatestReading();
loadReadings();
loadDeviceStatus();


// =====================================================
// AUTO REFRESH
// =====================================================

setInterval(
    loadLatestReading,
    3000
);


setInterval(
    loadReadings,
    60000
);


setInterval(
    loadDeviceStatus,
    5000
);